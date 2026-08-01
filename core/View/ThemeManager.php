<?php

namespace Core\View;

use Core\Http\Response;

/**
 * 主题管理器 - 模仿 WordPress 模板层级。
 *
 * - 主题位于 resources/themes/{name}/
 * - 主题入口 functions.php 在 boot 时加载，可注册 hooks
 * - 模板按层级查找：single-post-{slug}.php → single-post.php → single.php
 * - 后台可切换/上传/删除主题
 */
class ThemeManager
{
    private string $activeTheme;
    private string $themeRoot;

    /** @var array<string, mixed> */
    private array $themeConfig = [];

    /** @var array<int, array> data stack for partials to access parent scope */
    private array $dataStack = [];

    private bool $booted = false;

    public function __construct()
    {
        $this->themeRoot = themes_path();
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;
        // Pick active theme from options table (DB) or config
        $this->activeTheme = $this->resolveActiveTheme();

        // Run theme's functions.php if exists
        $this->loadThemeFunctions();
    }

    private function resolveActiveTheme(): string
    {
        // Try DB-stored option first
        try {
            $qb = app(\Core\Database\QueryBuilder::class);
            $row = $qb->table('options')->where('key_name', 'active_theme')->first();
            if (!empty($row['value'])) {
                return $row['value'];
            }
        } catch (\Throwable $e) {
            // table may not exist yet (install state)
        }
        return (string) config('app.theme', 'default');
    }

    public function setActiveTheme(string $name): void
    {
        $this->activeTheme = $name;
    }

    public function activeTheme(): string
    {
        return $this->activeTheme;
    }

    public function path(string $relative = ''): string
    {
        return $this->themeRoot . '/' . $this->activeTheme . ($relative ? '/' . ltrim($relative, '/') : '');
    }

    private function loadThemeFunctions(): void
    {
        $file = $this->path('functions.php');
        if (is_file($file)) {
            require $file;
        }
        do_action('theme_loaded');
    }

    public function templateExists(string $template): bool
    {
        return is_file($this->templatePath($template));
    }

    public function templatePath(string $template): string
    {
        return $this->path('templates/' . $template . '.php');
    }

    /**
     * Render a template, with template hierarchy fallback.
     */
    public function render(string $template, array $data = []): Response
    {
        do_action('template_redirect', $template, $data);

        // Allow plugins to override template entirely
        $override = apply_filters('template_include', null, $template, $data);
        if (is_string($override) && is_file($override)) {
            return $this->renderFile($override, $data);
        }

        // Try fallback hierarchy
        $candidates = $this->templateHierarchy($template);
        foreach ($candidates as $tpl) {
            if ($this->templateExists($tpl)) {
                return $this->renderFile($this->templatePath($tpl), $data);
            }
        }
        // Render a simple default body if no template found
        $resp = new Response();
        $resp->setContentType('text/html')
            ->setBody("<h1>Template missing: $template</h1>")
            ->setStatus(500);
        return $resp;
    }

    /**
     * Build WordPress-style template hierarchy for a given template name.
     */
    private function templateHierarchy(string $template): array
    {
        // Example: 'single' → ['single', 'index']
        // 'page'   → ['page', 'index']
        // 'archive'→ ['archive', 'index']
        // '404'    → ['404']
        return match ($template) {
            'home', 'index', 'single', 'page', 'archive', 'category', 'tag', 'author', 'search', '404', 'feed', 'error' => [$template, 'index'],
            default => [$template],
        };
    }

    private function renderFile(string $__path, array $__data): Response
    {
        if (!is_file($__path)) {
            return (new Response())->setBody("Missing template: $__path")->setStatus(500);
        }
        // Push data onto stack so partials can access it
        $this->dataStack[] = $__data;
        extract($__data, EXTR_SKIP);
        ob_start();
        // Provide $theme var to template
        $theme = $this;
        $resp = new Response();
        try {
            include $__path;
        } catch (\Throwable $e) {
            ob_end_clean();
            array_pop($this->dataStack);
            throw $e;
        }
        $body = ob_get_clean();
        array_pop($this->dataStack);
        do_action('template_rendered', $__path, $__data);
        $body = apply_filters('template_output', $body, $__path, $__data);
        $resp->setContentType('text/html')
            ->setBody((string) $body);
        return $resp;
    }

    /**
     * Render a partial (smaller reusable template).
     */
    public function partial(string $name, array $data = []): string
    {
        $path = $this->path('partials/' . $name . '.php');
        if (!is_file($path)) {
            return "<!-- partial $name missing -->";
        }
        // Merge with parent template's data from the stack
        $parentData = !empty($this->dataStack) ? end($this->dataStack) : [];
        $merged = array_merge($parentData, $data);
        $this->dataStack[] = $merged;
        extract($merged, EXTR_SKIP);
        ob_start();
        $theme = $this;
        try {
            include $path;
        } catch (\Throwable $e) {
            ob_end_clean();
            array_pop($this->dataStack);
            throw $e;
        }
        $body = ob_get_clean();
        array_pop($this->dataStack);
        return (string) $body;
    }

    /**
     * Asset URL for current theme.
     */
    public function asset(string $path): string
    {
        return url('themes/' . $this->activeTheme . '/' . ltrim($path, '/'));
    }

    /**
     * List all themes under themes_path.
     *
     * @return array<string, array{name: string, dir: string, meta: array}>
     */
    public function listThemes(): array
    {
        $result = [];
        if (!is_dir($this->themeRoot)) {
            return $result;
        }
        foreach ((array) glob($this->themeRoot . '/*', GLOB_ONLYDIR) as $dir) {
            $name = basename($dir);
            $meta = $this->readThemeMeta($dir);
            $result[$name] = [
                'name' => $name,
                'dir' => $dir,
                'meta' => $meta,
            ];
        }
        return $result;
    }

    public function readThemeMeta(string $dir): array
    {
        $jsonFile = $dir . '/theme.json';
        if (is_file($jsonFile)) {
            $data = json_decode(file_get_contents($jsonFile) ?: '', true);
            return is_array($data) ? $data : [];
        }
        // Fall back to functions.php header
        $file = $dir . '/functions.php';
        if (is_file($file)) {
            $headers = $this->parseFileHeaders($file, [
                'name' => 'Theme Name',
                'description' => 'Description',
                'version' => 'Version',
                'author' => 'Author',
            ]);
            if (!empty($headers['name'])) {
                return $headers;
            }
        }
        return ['name' => basename($dir), 'version' => '0.0.0'];
    }

    private function parseFileHeaders(string $file, array $fields): array
    {
        $content = file_get_contents($file);
        $result = [];
        foreach ($fields as $key => $label) {
            if (preg_match('/\*\s*' . preg_quote($label, '/') . '\s*:\s*(.+)/i', $content, $m)) {
                $result[$key] = trim($m[1]);
            }
        }
        return $result;
    }

    /**
     * Install a theme from a zip file.
     */
    public function installFromZip(string $zipPath): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('ZipArchive extension required.');
        }
        $zip = new \ZipArchive();
        if (($code = $zip->open($zipPath)) !== true) {
            throw new \RuntimeException("Cannot open zip: error code $code");
        }
        $tmpDir = $this->themeRoot . '/.upload-' . substr(md5((string) microtime(true)), 0, 8);
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0777, true);
        }
        $zip->extractTo($tmpDir);
        $zip->close();

        // Find the theme root: a top-level folder containing theme.json or functions.php
        $themeDir = null;
        $entries = array_diff(scandir($tmpDir), ['.', '..']);
        foreach ($entries as $entry) {
            $candidate = $tmpDir . '/' . $entry;
            if (is_dir($candidate) && (is_file($candidate . '/theme.json') || is_file($candidate . '/functions.php'))) {
                $themeDir = $candidate;
                $themeName = $entry;
                break;
            }
        }
        if ($themeDir === null) {
            // If files are at root, use a temp name
            $themeDir = $tmpDir;
            $themeName = 'theme-' . substr(md5((string) microtime(true)), 0, 6);
        }

        $target = $this->themeRoot . '/' . $themeName;
        if (is_dir($target)) {
            $this->rrmdir($target);
        }
        rename($themeDir, $target);

        // Clean up
        if (is_dir($tmpDir)) {
            $this->rrmdir($tmpDir);
        }
        $meta = $this->readThemeMeta($target);
        return ['name' => $themeName, 'meta' => $meta];
    }

    public function deleteTheme(string $name): bool
    {
        $dir = $this->themeRoot . '/' . $name;
        if (!is_dir($dir) || $name === $this->activeTheme) {
            return false;
        }
        $this->rrmdir($dir);
        return true;
    }

    private function rrmdir(string $dir): void
    {
        foreach (array_diff(scandir($dir), ['.', '..']) as $f) {
            $path = "$dir/$f";
            if (is_dir($path)) {
                $this->rrmdir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
