<?php

namespace Core\View;

use Core\Http\Response;

/**
 * 主题管理器 — 模仿 WordPress 模板层级 + 父子主题 + 页面模板。
 *
 * - 主题位于 resources/themes/{name}/
 * - 主题入口 functions.php 在 boot 时加载
 * - 子主题在 theme.json 声明 "parent" → 模板按 child → parent 顺序查找
 * - 模板按层级查找：single-post-{slug}.php → single-post.php → single.php → index.php
 * - 后台可切换/上传/删除主题
 * - 激活时触发 after_switch_theme hook
 */
class ThemeManager
{
    private string $activeTheme = 'default';
    private string $themeRoot;

    /** @var array<string, mixed> */
    private array $themeConfig = [];

    /** @var array<string, mixed>|null parent theme config */
    private ?array $parentConfig = null;

    private ?string $parentTheme = null;

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
        $this->activeTheme = $this->resolveActiveTheme();
        $this->themeConfig = $this->readThemeMeta($this->path());

        // Detect parent theme
        $parent = $this->themeConfig['parent'] ?? null;
        if ($parent && is_dir($this->themeRoot . '/' . $parent)) {
            $this->parentTheme = $parent;
            $this->parentConfig = $this->readThemeMeta($this->themeRoot . '/' . $parent);
        }

        $this->loadThemeFunctions();
    }

    private function resolveActiveTheme(): string
    {
        try {
            $qb = app(\Core\Database\QueryBuilder::class);
            $row = $qb->table('options')->where('key_name', 'active_theme')->first();
            if (!empty($row['value'])) {
                return $row['value'];
            }
        } catch (\Throwable) {
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

    public function parentTheme(): ?string
    {
        return $this->parentTheme;
    }

    /**
     * 当前主题目录路径（子主题优先）。
     */
    public function path(string $relative = ''): string
    {
        return $this->themeRoot . '/' . $this->activeTheme . ($relative ? '/' . ltrim($relative, '/') : '');
    }

    /**
     * 父主题目录路径。
     */
    public function parentPath(string $relative = ''): ?string
    {
        if (!$this->parentTheme) {
            return null;
        }
        return $this->themeRoot . '/' . $this->parentTheme . ($relative ? '/' . ltrim($relative, '/') : '');
    }

    /**
     * 在子→父中查找文件路径。
     */
    public function resolvePath(string $relative): ?string
    {
        $childPath = $this->path($relative);
        if (is_file($childPath)) {
            return $childPath;
        }
        if ($this->parentPath()) {
            $parentPath = $this->parentPath($relative);
            if ($parentPath && is_file($parentPath)) {
                return $parentPath;
            }
        }
        return null;
    }

    private function loadThemeFunctions(): void
    {
        // Load parent functions.php first (if child theme)
        if ($this->parentPath('functions.php')) {
            require $this->parentPath('functions.php');
        }
        // Then child functions.php (can override parent hooks)
        $childFunc = $this->path('functions.php');
        if ($childFunc && (!file_exists($childFunc) || $childFunc !== $this->parentPath('functions.php'))) {
            if (is_file($childFunc)) {
                require $childFunc;
            }
        }
        do_action('theme_loaded');
    }

    public function templateExists(string $template): bool
    {
        return $this->resolvePath('templates/' . $template . '.php') !== null;
    }

    public function templatePath(string $template): string
    {
        $resolved = $this->resolvePath('templates/' . $template . '.php');
        return $resolved ?? $this->path('templates/' . $template . '.php');
    }

    /**
     * Render a template, with template hierarchy fallback.
     */
    public function render(string $template, array $data = []): Response
    {
        do_action('template_redirect', $template, $data);

        $override = apply_filters('template_include', null, $template, $data);
        if (is_string($override) && is_file($override)) {
            return $this->renderFile($override, $data);
        }

        $candidates = $this->templateHierarchy($template);
        foreach ($candidates as $tpl) {
            if ($this->templateExists($tpl)) {
                return $this->renderFile($this->templatePath($tpl), $data);
            }
        }
        $resp = new Response();
        $resp->setContentType('text/html')
            ->setBody("<h1>Template missing: $template</h1>")
            ->setStatus(500);
        return $resp;
    }

    private function templateHierarchy(string $template): array
    {
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
        $this->dataStack[] = $__data;
        extract($__data, EXTR_SKIP);
        ob_start();
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
        $resp->setContentType('text/html')->setBody((string) $body);
        return $resp;
    }

    /**
     * Render a partial with data — 子→父 fallback。
     */
    public function partial(string $name, array $data = []): string
    {
        $relative = 'partials/' . $name . '.php';
        $path = $this->resolvePath($relative);
        if (!$path) {
            return "<!-- partial $name missing -->";
        }
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
     * Asset URL for current theme (子主题资产 → 父主题 fallback)。
     */
    public function asset(string $path): string
    {
        $relative = ltrim($path, '/');
        // 子主题有此资产 → 用子主题 URL
        if (is_file($this->path($relative))) {
            return url('themes/' . $this->activeTheme . '/' . $relative);
        }
        // 父主题有此资产 → 用父主题 URL
        if ($this->parentPath($relative) && is_file($this->parentPath($relative))) {
            return url('themes/' . $this->parentTheme . '/' . $relative);
        }
        // 默认用子主题路径
        return url('themes/' . $this->activeTheme . '/' . $relative);
    }

    /**
     * 读取主题选项值（从 DB options 表，key 前缀 theme_{theme}_{key}）。
     */
    public function config(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->themeConfig;
        }
        $optionKey = 'theme_' . $this->activeTheme . '_' . $key;
        try {
            $val = \App\Models\Option::get($optionKey);
            if ($val !== null) {
                return $val;
            }
        } catch (\Throwable) {
        }
        // Fallback to theme.json default
        $options = $this->themeConfig['options'] ?? [];
        if (isset($options[$key]['default'])) {
            return $options[$key]['default'];
        }
        return $default;
    }

    /**
     * 获取主题自定义页面模板列表。
     */
    public function getPageTemplates(): array
    {
        $templates = $this->themeConfig['page_templates'] ?? [];
        if ($this->parentConfig) {
            $parentTemplates = $this->parentConfig['page_templates'] ?? [];
            $templates = array_merge($parentTemplates, $templates);
        }
        return $templates;
    }

    /**
     * 获取主题声明的菜单位置。
     */
    public function getMenuLocations(): array
    {
        $menus = $this->themeConfig['menus'] ?? [];
        if ($this->parentConfig) {
            $menus = array_merge($this->parentConfig['menus'] ?? [], $menus);
        }
        return $menus;
    }

    /**
     * 获取主题声明的 Widget 区域。
     */
    public function getSidebars(): array
    {
        $sidebars = $this->themeConfig['sidebars'] ?? [];
        if ($this->parentConfig) {
            $sidebars = array_merge($this->parentConfig['sidebars'] ?? [], $sidebars);
        }
        return $sidebars;
    }

    /**
     * 激活主题 — 触发 after_switch_theme。
     */
    public function activate(string $name): void
    {
        $old = $this->activeTheme;
        $this->activeTheme = $name;
        $this->themeConfig = $this->readThemeMeta($this->path());
        $this->parentTheme = null;
        $this->parentConfig = null;

        $parent = $this->themeConfig['parent'] ?? null;
        if ($parent && is_dir($this->themeRoot . '/' . $parent)) {
            $this->parentTheme = $parent;
            $this->parentConfig = $this->readThemeMeta($this->themeRoot . '/' . $parent);
        }

        \App\Models\Option::set('active_theme', $name);

        // Register theme sidebars/menus from theme.json
        $this->registerThemeSidebars();
        $this->registerThemeMenus();

        do_action('after_switch_theme', $name, $old);
    }

    /**
     * 从 theme.json 注册 Widget 区域。
     */
    private function registerThemeSidebars(): void
    {
        $sidebars = $this->getSidebars();
        foreach ($sidebars as $id => $config) {
            if (is_array($config)) {
                $config['id'] = $id;
                register_sidebar($config);
            }
        }
    }

    /**
     * 从 theme.json 注册菜单位置。
     */
    private function registerThemeMenus(): void
    {
        $menus = $this->getMenuLocations();
        foreach ($menus as $location => $description) {
            register_nav_menu($location, $description);
        }
    }

    /**
     * List all themes.
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
            $result[$name] = ['name' => $name, 'dir' => $dir, 'meta' => $meta];
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
        $file = $dir . '/functions.php';
        if (is_file($file)) {
            $headers = $this->parseFileHeaders($file, [
                'name' => 'Theme Name', 'description' => 'Description',
                'version' => 'Version', 'author' => 'Author',
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
            $themeDir = $tmpDir;
            $themeName = 'theme-' . substr(md5((string) microtime(true)), 0, 6);
        }

        $target = $this->themeRoot . '/' . $themeName;
        if (is_dir($target)) {
            $this->rrmdir($target);
        }
        rename($themeDir, $target);

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
