<?php

namespace Core\View;

use Core\Http\Response;
use Core\Theme\ThemeInstaller;
use Core\Theme\TemplateResolver;
use Core\Theme\ThemeConfigManager;

/**
 * 主题管理器 — 协调器（Facade 模式）。
 *
 * 统筹三个子组件：
 * - ThemeInstaller    → 安装/删除/校验
 * - TemplateResolver  → 模板查找/渲染/父子回溯
 * - ThemeConfigManager → 配置读写/元数据解析/合并
 *
 * 保持向后兼容，原有公共方法签名不变。
 */
class ThemeManager
{
    private string $activeTheme = 'default';
    private string $themeRoot;

    private ThemeInstaller $installer;
    private TemplateResolver $resolver;
    private ThemeConfigManager $configManager;

    private bool $booted = false;

    /** @var array<string, string> 错误记录 */
    private array $errors = [];

    public function __construct()
    {
        $this->themeRoot = themes_path();
        $this->installer = new ThemeInstaller($this->themeRoot);
        $this->resolver = new TemplateResolver($this->themeRoot);
        $this->configManager = new ThemeConfigManager($this->themeRoot);
    }

    /* ═══════════════ 生命周期 ═══════════════ */

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;
        $this->activeTheme = $this->resolveActiveTheme();
        $parentTheme = $this->resolveParentTheme();
        $this->configManager->setActiveTheme($this->activeTheme, $parentTheme);
        $this->resolver->setActiveTheme($this->activeTheme, $parentTheme);

        // 加载主题函数
        $this->loadThemeFunctions();

        // 注册主题侧边栏/菜单
        $this->registerThemeSidebars();
        $this->registerThemeMenus();

        // 自动注册 theme.json 声明的资源
        $this->autoRegisterAssets();

        // 输出 CSS 自定义属性
        $this->outputCssVariables();

        do_action('theme_loaded');
    }

    /**
     * 从数据库解析当前激活的主题。
     */
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

    /**
     * 解析父主题名称。
     */
    private function resolveParentTheme(): ?string
    {
        $config = $this->configManager->getConfig();
        $parent = $config['parent'] ?? null;
        if ($parent && is_dir($this->themeRoot . '/' . $parent)) {
            return $parent;
        }
        return null;
    }

    /**
     * 加载主题 functions.php。
     */
    private function loadThemeFunctions(): void
    {
        $parent = $this->configManager->getParentTheme();

        // 先加载父主题 functions.php
        if ($parent) {
            $parentFunc = $this->themeRoot . '/' . $parent . '/functions.php';
            if (is_file($parentFunc)) {
                try {
                    require $parentFunc;
                } catch (\Throwable $e) {
                    $this->errors['parent_theme'] = '父主题加载错误: ' . $e->getMessage();
                    \Core\Log\Log::warning('Parent theme functions.php error: ' . $e->getMessage());
                }
            }
        }

        // 再加载子主题 functions.php
        $childFunc = $this->path('functions.php');
        if ($childFunc && is_file($childFunc)) {
            try {
                require $childFunc;
            } catch (\Throwable $e) {
                $this->errors[$this->activeTheme] = 'load_error: ' . $e->getMessage();
                \Core\Log\Log::warning('Theme functions.php error: ' . $e->getMessage());
            }
        }
    }

    /* ═══════════════ 激活/停用（含安全回滚） ═══════════════ */

    /**
     * 激活主题 — 含激活前校验和故障自动回滚。
     */
    public function activate(string $name): void
    {
        if (!$this->installer->exists($name)) {
            throw new \RuntimeException("主题 [$name] 不存在。");
        }

        // 激活前校验
        $this->validateBeforeActivate($name);

        $old = $this->activeTheme;

        // 触发 before_switch_theme 钩子
        $canSwitch = apply_filters('before_switch_theme', true, $name, $old);
        if (!$canSwitch) {
            throw new \RuntimeException("主题切换已被拦截。");
        }

        // 保存旧主题以备回滚
        $previousActive = $this->activeTheme;

        // 执行切换
        $this->activeTheme = $name;
        $this->configManager->setActiveTheme($name, $this->resolveParentThemeFor($name));
        $this->resolver->setActiveTheme($name, $this->resolveParentThemeFor($name));

        try {
            // 尝试加载新主题
            \App\Models\Option::set('active_theme', $name);
            $this->loadThemeFunctions();
            $this->registerThemeSidebars();
            $this->registerThemeMenus();
            $this->autoRegisterAssets();

            do_action('theme_activated', $name, $old);
            do_action('after_switch_theme', $name, $old);
        } catch (\Throwable $e) {
            // 故障自动回滚
            $this->activeTheme = $previousActive;
            $this->configManager->setActiveTheme($previousActive, $this->resolveParentThemeFor($previousActive));
            $this->resolver->setActiveTheme($previousActive, $this->resolveParentThemeFor($previousActive));
            \App\Models\Option::set('active_theme', $previousActive);

            \Core\Log\Log::error("Theme activation failed, rolled back to [$previousActive]: " . $e->getMessage());
            throw new \RuntimeException("主题 [$name] 激活失败，已自动回滚到 [$previousActive]: " . $e->getMessage());
        }

        do_action('theme_deactivated', $old, $name);
    }

    /**
     * 激活前校验。
     */
    private function validateBeforeActivate(string $name): void
    {
        $dir = $this->themeRoot . '/' . $name;

        // 校验 theme.json 是否为合法 JSON
        $jsonFile = $dir . '/theme.json';
        if (is_file($jsonFile)) {
            $content = file_get_contents($jsonFile);
            if ($content === false || json_decode($content) === null && $content !== '') {
                throw new \RuntimeException("主题 [$name] 的 theme.json 文件格式错误。");
            }
        }

        // 校验 PHP 语法
        $funcFile = $dir . '/functions.php';
        if (is_file($funcFile)) {
            if (!$this->installer->validatePhpSyntax($funcFile)) {
                throw new \RuntimeException("主题 [$name] 的 functions.php 存在语法错误。");
            }
        }

        // 校验 requires_php
        $meta = $this->configManager->getConfig();
        $requiresPhp = $meta['requires_php'] ?? '';
        if ($requiresPhp) {
            $required = ltrim($requiresPhp, '>=^~');
            if (version_compare(PHP_VERSION, $required, '<')) {
                throw new \RuntimeException("主题 [$name] 需要 PHP {$required}+，当前 PHP " . PHP_VERSION);
            }
        }
    }

    /**
     * 解析指定主题的父主题。
     */
    private function resolveParentThemeFor(string $name): ?string
    {
        $dir = $this->themeRoot . '/' . $name;
        $jsonFile = $dir . '/theme.json';
        if (is_file($jsonFile)) {
            $data = json_decode(file_get_contents($jsonFile) ?: '', true);
            $parent = $data['parent'] ?? null;
            if ($parent && is_dir($this->themeRoot . '/' . $parent)) {
                return $parent;
            }
        }
        return null;
    }

    /* ═══════════════ 委托方法 ═══════════════ */

    /** @see ThemeInstaller::installFromZip() */
    public function installFromZip(string $zipPath): array
    {
        return $this->installer->installFromZip($zipPath);
    }

    /** @deprecated 使用 installFromZip */
    public function uploadZip(string $zipPath, string $originalName = ''): array
    {
        return $this->installer->installFromZip($zipPath);
    }

    /** @see ThemeInstaller::deleteTheme() */
    public function deleteTheme(string $name): bool
    {
        return $this->installer->deleteTheme($name);
    }

    /** @see ThemeInstaller::listThemes() */
    public function listThemes(): array
    {
        return $this->installer->listThemes();
    }

    /** @see ThemeInstaller::exists() */
    public function exists(string $name): bool
    {
        return $this->installer->exists($name);
    }

    /** @see TemplateResolver::render() */
    public function render(string $template, array $data = []): Response
    {
        return $this->resolver->render($template, $data);
    }

    /** @see TemplateResolver::partial() */
    public function partial(string $name, array $data = []): string
    {
        return $this->resolver->partial($name, $data);
    }

    /** @see TemplateResolver::renderFile() */
    public function renderFile(string $path, array $data): Response
    {
        return $this->resolver->renderFile($path, $data);
    }

    /** @see TemplateResolver::resolvePath() */
    public function resolvePath(string $relative): ?string
    {
        return $this->resolver->resolvePath($relative);
    }

    /** @see TemplateResolver::templateExists() */
    public function templateExists(string $template): bool
    {
        return $this->resolver->templateExists($template);
    }

    /** @see TemplateResolver::templatePath() */
    public function templatePath(string $template): string
    {
        return $this->resolver->templatePath($template);
    }

    /** @see TemplateResolver::assetUrl() */
    public function asset(string $path): string
    {
        $url = $this->resolver->assetUrl($path);
        // 自动附加版本号（基于主题版本或文件修改时间）
        $themeVersion = $this->configManager->getConfig()['version'] ?? '';
        $sep = str_contains($url, '?') ? '&' : '?';
        if ($themeVersion) {
            $url .= $sep . 'ver=' . urlencode($themeVersion);
        } else {
            // 基于文件修改时间
            $relative = ltrim($path, '/');
            $filePath = $this->path($relative);
            if (is_file($filePath)) {
                $url .= $sep . 't=' . filemtime($filePath);
            }
        }
        return $url;
    }

    /** @see ThemeConfigManager::getOption() */
    public function config(?string $key = null, mixed $default = null): mixed
    {
        return $this->configManager->getOption($key, $default);
    }

    /** @see ThemeConfigManager::getAllOptions() */
    public function getAllConfig(): array
    {
        return $this->configManager->getAllOptions();
    }

    /** @see ThemeConfigManager::setOption() */
    public function setConfig(string $key, mixed $value): void
    {
        $this->configManager->setOption($key, $value);
    }

    /** @see ThemeConfigManager::deleteOption() */
    public function deleteConfig(string $key): void
    {
        $this->configManager->deleteOption($key);
    }

    /** @see ThemeConfigManager::getPageTemplates() */
    public function getPageTemplates(): array
    {
        return $this->configManager->getPageTemplates();
    }

    /** @see ThemeConfigManager::getMenuLocations() */
    public function getMenuLocations(): array
    {
        return $this->configManager->getMenuLocations();
    }

    /** @see ThemeConfigManager::getSidebars() */
    public function getSidebars(): array
    {
        return $this->configManager->getSidebars();
    }

    /** @see ThemeConfigManager::getScreenshots() */
    public function getScreenshots(): array
    {
        return $this->configManager->getScreenshots();
    }

    /** @see ThemeConfigManager::getChangelog() */
    public function getChangelog(): array
    {
        return $this->configManager->getChangelog();
    }

    /** @see ThemeConfigManager::getRecommendedPlugins() */
    public function getRecommendedPlugins(): array
    {
        return $this->configManager->getRecommendedPlugins();
    }

    /** @see ThemeConfigManager::getCategory() */
    public function getCategory(): string
    {
        return $this->configManager->getCategory();
    }

    /** @see ThemeConfigManager::getTags() */
    public function getTags(): array
    {
        return $this->configManager->getTags();
    }

    /** @see ThemeConfigManager::getRequires() */
    public function getRequires(): string
    {
        return $this->configManager->getRequires();
    }

    /** @see ThemeConfigManager::getRequiresPhp() */
    public function getRequiresPhp(): string
    {
        return $this->configManager->getRequiresPhp();
    }

    /** @see ThemeConfigManager::getUpdateUrl() */
    public function getUpdateUrl(): string
    {
        return $this->configManager->getUpdateUrl();
    }

    /** @see ThemeConfigManager::getDemoUrl() */
    public function getDemoUrl(): string
    {
        return $this->configManager->getDemoUrl();
    }

    /** @see ThemeConfigManager::getGroupedOptions() */
    public function getGroupedOptions(): array
    {
        return $this->configManager->getGroupedOptions();
    }

    /** @see ThemeConfigManager::getFlatOptions() */
    public function getFlatOptions(): array
    {
        return $this->configManager->getFlatOptions();
    }

    /** @see ThemeConfigManager::generateCssVariables() */
    public function generateCssVariables(): string
    {
        return $this->configManager->generateCssVariables();
    }

    /** @see ThemeConfigManager::generateFullCssVariables() */
    public function generateFullCssVariables(): string
    {
        return $this->configManager->generateFullCssVariables();
    }

    /** @see ThemeConfigManager::createSnapshot() */
    public function createSnapshot(string $note = ''): int
    {
        return $this->configManager->createSnapshot($note);
    }

    /** @see ThemeConfigManager::getSnapshots() */
    public function getSnapshots(int $limit = 50): array
    {
        return $this->configManager->getSnapshots($limit);
    }

    /** @see ThemeConfigManager::getSnapshot() */
    public function getSnapshot(int $id): ?array
    {
        return $this->configManager->getSnapshot($id);
    }

    /** @see ThemeConfigManager::restoreSnapshot() */
    public function restoreSnapshot(int $id): void
    {
        $this->configManager->restoreSnapshot($id);
    }

    /* ═══════════════ 简单属性访问 ═══════════════ */

    public function activeTheme(): string
    {
        return $this->activeTheme;
    }

    public function parentTheme(): ?string
    {
        return $this->configManager->getParentTheme();
    }

    public function path(string $relative = ''): string
    {
        return $this->themeRoot . '/' . $this->activeTheme . ($relative ? '/' . ltrim($relative, '/') : '');
    }

    public function parentPath(string $relative = ''): ?string
    {
        $parent = $this->configManager->getParentTheme();
        if (!$parent) {
            return null;
        }
        return $this->themeRoot . '/' . $parent . ($relative ? '/' . ltrim($relative, '/') : '');
    }

    public function setActiveTheme(string $name): void
    {
        $this->activeTheme = $name;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getInstaller(): ThemeInstaller
    {
        return $this->installer;
    }

    public function getResolver(): TemplateResolver
    {
        return $this->resolver;
    }

    public function getConfigManager(): ThemeConfigManager
    {
        return $this->configManager;
    }

    /* ═══════════════ 内部辅助 ═══════════════ */

    private function registerThemeSidebars(): void
    {
        $sidebars = $this->configManager->getSidebars();
        foreach ($sidebars as $id => $config) {
            if (is_array($config)) {
                $config['id'] = $id;
                register_sidebar($config);
            }
        }
    }

    private function registerThemeMenus(): void
    {
        $menus = $this->configManager->getMenuLocations();
        foreach ($menus as $location => $description) {
            register_nav_menu($location, $description);
        }
    }

    /**
     * 自动注册 theme.json 中声明的样式和脚本资源。
     */
    private function autoRegisterAssets(): void
    {
        $styles = $this->configManager->getDeclaredStyles();
        foreach ($styles as $id => $cfg) {
            if (is_array($cfg) && isset($cfg['src'])) {
                $src = $this->asset($cfg['src']);
                $deps = $cfg['deps'] ?? [];
                $ver = $cfg['ver'] ?? ($this->configManager->getConfig()['version'] ?? '');
                enqueue_style($id, $src, $deps, $ver);
            }
        }

        $scripts = $this->configManager->getDeclaredScripts();
        foreach ($scripts as $id => $cfg) {
            if (is_array($cfg) && isset($cfg['src'])) {
                $src = $this->asset($cfg['src']);
                $deps = $cfg['deps'] ?? [];
                $ver = $cfg['ver'] ?? ($this->configManager->getConfig()['version'] ?? '');
                $footer = $cfg['footer'] ?? true;
                enqueue_script($id, $src, $deps, $ver, $footer);
            }
        }
    }

    /**
     * 输出 CSS 自定义属性（用于主题定制器）。
     * 支持预览模式：从 session 读取临时配置覆盖 DB 值。
     * session 在闭包内读取（而非 boot 时），确保 previewAjax 设置的配置能生效。
     */
    private function outputCssVariables(): void
    {
        $themeName = $this->activeTheme;

        add_action('wp_head', function () use ($themeName) {
            // 在渲染时读取 session（而非 boot 时），确保 previewAjax 设置的配置生效
            $previewConfig = [];
            $previewMode = false;
            try {
                $session = app(\Core\Http\Session::class);
                // 优先使用 session 中的预览主题名，fallback 到当前激活主题
                $previewTheme = $session->get('theme_preview', '');
                $configKey = 'theme_preview_config_' . ($previewTheme ?: $themeName);
                $previewConfig = $session->get($configKey, []);
                $previewMode = !empty($previewTheme);
            } catch (\Throwable) {
                // 无 session 或非预览模式
            }

            // 在渲染时读取配置值（确保预览模式下能获取到 session 中的最新值）
            $customCss = $this->configManager->getOption('custom_css', '');
            $customJsHead = $this->configManager->getOption('custom_js_head', '');
            $cssVars = $this->configManager->generateFullCssVariables();

            if ($previewMode && !empty($previewConfig)) {
                // 预览模式：输出预览配置生成的 CSS 变量（覆盖原有变量）
                $previewCss = $this->generatePreviewCssVars($previewConfig);
                if ($previewCss) {
                    echo "<style id=\"theme-css-vars\">\n{$previewCss}</style>\n";
                } else {
                    echo "<style id=\"theme-css-vars\">\n{$cssVars}</style>\n";
                }
            } else {
                if ($cssVars) {
                    echo "<style id=\"theme-css-vars\">\n{$cssVars}</style>\n";
                }
            }
            if ($customCss) {
                echo "<style id=\"theme-custom-css\">\n{$customCss}\n</style>\n";
            }
            if ($customJsHead) {
                echo "<script id=\"theme-custom-js-head\">\n{$customJsHead}\n</script>\n";
            }
        }, 1);

        // 页脚 JS 单独通过 wp_footer 注入（在 wp_head 闭包外，避免重复注册）
        add_action('wp_footer', function () use ($themeName) {
            // 同样在渲染时读取 session，确保预览模式生效
            $customJsFooter = $this->configManager->getOption('custom_js_footer', '');
            if ($customJsFooter) {
                echo "<script id=\"theme-custom-js-footer\">\n{$customJsFooter}\n</script>\n";
            }
        }, 100);
    }

    /**
     * 根据预览配置生成 CSS 变量。
     * 只输出有 css_var 映射且值不同于默认值的配置项。
     */
    private function generatePreviewCssVars(array $previewConfig): string
    {
        $flat = $this->configManager->getFlatOptions();
        $vars = [];

        foreach ($flat as $key => $config) {
            if (!is_array($config)) continue;
            $cssVar = $config['css_var'] ?? '';
            if (empty($cssVar)) continue;

            if (!isset($previewConfig[$key])) continue;
            $value = $previewConfig[$key];

            if ($config['type'] === 'range' && isset($config['unit']) && $config['unit'] !== '') {
                $vars[] = "  {$cssVar}: {$value}{$config['unit']};";
            } elseif ($config['type'] === 'switch') {
                $vars[] = "  {$cssVar}: " . ($value ? '1' : '0') . ";";
            } else {
                $vars[] = "  {$cssVar}: {$value};";
            }
        }

        if (empty($vars)) return '';
        return ":root {\n" . implode("\n", $vars) . "\n}\n";
    }
}