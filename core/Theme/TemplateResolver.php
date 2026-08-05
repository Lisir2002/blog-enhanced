<?php

namespace Core\Theme;

use Core\Http\Response;

/**
 * 模板解析器 — 负责模板文件查找、层级回溯与渲染。
 *
 * 职责单一：
 * - 按模板层级（WordPress 风格）查找模板文件
 * - 子主题 → 父主题双向回溯
 * - 渲染模板文件并返回 Response
 * - 支持 partial 片段渲染
 */
class TemplateResolver
{
    private string $themeRoot;
    private string $activeTheme = '';
    private ?string $parentTheme = null;

    /** @var array<int, array> 数据栈，partial 访问父作用域 */
    private array $dataStack = [];

    public function __construct(string $themeRoot)
    {
        $this->themeRoot = rtrim($themeRoot, '/');
    }

    /**
     * 设置当前激活的主题。
     */
    public function setActiveTheme(string $name, ?string $parentTheme = null): void
    {
        $this->activeTheme = $name;
        $this->parentTheme = $parentTheme;
    }

    /**
     * 获取当前主题的目录路径。
     */
    public function path(string $relative = ''): string
    {
        return $this->themeRoot . '/' . $this->activeTheme . ($relative ? '/' . ltrim($relative, '/') : '');
    }

    /**
     * 获取父主题的目录路径。
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
        $parentPath = $this->parentPath($relative);
        if ($parentPath !== null && is_file($parentPath)) {
            return $parentPath;
        }
        return null;
    }

    /**
     * 检查模板是否存在。
     */
    public function templateExists(string $template): bool
    {
        return $this->resolvePath('templates/' . $template . '.php') !== null;
    }

    /**
     * 获取模板的完整路径（无 fallback 检查）。
     */
    public function templatePath(string $template): string
    {
        $resolved = $this->resolvePath('templates/' . $template . '.php');
        return $resolved ?? $this->path('templates/' . $template . '.php');
    }

    /**
     * 渲染模板，含层级回溯。
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
            ->setBody("<h1>模板缺失: $template</h1>")
            ->setStatus(500);
        return $resp;
    }

    /**
     * 渲染 partial 片段，返回字符串。
     */
    public function partial(string $name, array $data = []): string
    {
        $relative = 'partials/' . $name . '.php';
        $path = $this->resolvePath($relative);
        if (!$path) {
            return "<!-- partial $name 缺失 -->";
        }
        $parentData = !empty($this->dataStack) ? end($this->dataStack) : [];
        $merged = array_merge($parentData, $data);
        $this->dataStack[] = $merged;
        extract($merged, EXTR_OVERWRITE);
        ob_start();
        $theme = $this; // 兼容旧模板通过 $theme 访问
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
     * 直接渲染文件路径。
     */
    public function renderFile(string $__path, array $__data): Response
    {
        if (!is_file($__path)) {
            return (new Response())->setBody("模板缺失: $__path")->setStatus(500);
        }
        $this->dataStack[] = $__data;
        extract($__data, EXTR_OVERWRITE);
        ob_start();
        $theme = $this;
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
        $resp = new Response();
        $resp->setContentType('text/html')->setBody((string) $body);
        return $resp;
    }

    /**
     * 模板层级回溯。
     */
    private function templateHierarchy(string $template): array
    {
        return match ($template) {
            'home', 'index', 'single', 'page', 'archive', 'category', 'tag', 'author', 'search', '404', 'feed', 'error' => [$template, 'index'],
            default => [$template],
        };
    }

    /**
     * 获取主题资产 URL。
     */
    public function assetUrl(string $path): string
    {
        $relative = ltrim($path, '/');
        if (is_file($this->path($relative))) {
            return url('themes/' . $this->activeTheme . '/' . $relative);
        }
        if ($this->parentTheme !== null && is_file($this->parentPath($relative) ?? '')) {
            return url('themes/' . $this->parentTheme . '/' . $relative);
        }
        return url('themes/' . $this->activeTheme . '/' . $relative);
    }
}