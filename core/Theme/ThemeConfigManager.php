<?php

namespace Core\Theme;

/**
 * 主题配置管理器 — 负责主题元数据读取、父子合并、配置持久化。
 *
 * 职责单一：
 * - 读取和解析 theme.json
 * - 父子主题配置合并
 * - 配置选项的持久化读写（DB options 表）
 * - 声明式资源（styles/scripts）的解析
 */
class ThemeConfigManager
{
    private string $themeRoot;
    private string $activeTheme = '';

    /** @var array<string, mixed> 当前主题配置 */
    private array $themeConfig = [];

    /** @var array<string, mixed>|null 父主题配置 */
    private ?array $parentConfig = null;

    private ?string $parentTheme = null;

    public function __construct(string $themeRoot)
    {
        $this->themeRoot = rtrim($themeRoot, '/');
    }

    /**
     * 设置当前主题并加载配置。
     */
    public function setActiveTheme(string $name, ?string $parentTheme = null): void
    {
        $this->activeTheme = $name;
        $this->parentTheme = $parentTheme;
        $this->themeConfig = $this->readMetaFromDir($this->path());
        $this->parentConfig = null;

        if ($parentTheme !== null && is_dir($this->themeRoot . '/' . $parentTheme)) {
            $this->parentConfig = $this->readMetaFromDir($this->themeRoot . '/' . $parentTheme);
        }
    }

    /**
     * 获取当前主题目录路径。
     */
    public function path(string $relative = ''): string
    {
        return $this->themeRoot . '/' . $this->activeTheme . ($relative ? '/' . ltrim($relative, '/') : '');
    }

    /**
     * 获取完整配置数组。
     */
    public function getConfig(): array
    {
        return $this->themeConfig;
    }

    /**
     * 获取父主题配置。
     */
    public function getParentConfig(): ?array
    {
        return $this->parentConfig;
    }

    /**
     * 获取父主题名称。
     */
    public function getParentTheme(): ?string
    {
        return $this->parentTheme;
    }

    /**
     * 读取主题选项值（从 DB + theme.json 默认值）。
     */
    public function getOption(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->themeConfig;
        }
        // 从 DB 读取
        $optionKey = 'theme_' . $this->activeTheme . '_' . $key;
        try {
            $val = \App\Models\Option::get($optionKey);
            if ($val !== null) {
                return $val;
            }
        } catch (\Throwable) {
            // DB not ready
        }
        // Fallback to theme.json default
        $options = $this->themeConfig['options'] ?? [];
        if (isset($options[$key]['default'])) {
            return $options[$key]['default'];
        }
        // 从父主题配置查找
        if ($this->parentConfig !== null) {
            $parentOptions = $this->parentConfig['options'] ?? [];
            if (isset($parentOptions[$key]['default'])) {
                return $parentOptions[$key]['default'];
            }
        }
        return $default;
    }

    /**
     * 保存主题选项值到 DB。
     */
    public function setOption(string $key, mixed $value): void
    {
        $optionKey = 'theme_' . $this->activeTheme . '_' . $key;
        try {
            \App\Models\Option::set($optionKey, $value);
        } catch (\Throwable $e) {
            throw new \RuntimeException("保存主题配置失败: " . $e->getMessage());
        }
    }

    /**
     * 删除主题选项值。
     */
    public function deleteOption(string $key): void
    {
        $optionKey = 'theme_' . $this->activeTheme . '_' . $key;
        try {
            \App\Models\Option::remove($optionKey);
        } catch (\Throwable) {
            // ignore
        }
    }

    /**
     * 获取所有配置值的扁平数组（DB 值 + 默认值合并）。
     */
    public function getAllOptions(): array
    {
        $options = $this->themeConfig['options'] ?? [];
        $result = [];

        // 先用默认值填充
        foreach ($options as $key => $config) {
            if (is_array($config) && isset($config['default'])) {
                $result[$key] = $config['default'];
            } elseif (!is_array($config)) {
                $result[$key] = $config;
            }
        }

        // 合并父主题默认值
        if ($this->parentConfig !== null) {
            $parentOptions = $this->parentConfig['options'] ?? [];
            foreach ($parentOptions as $key => $config) {
                if (!isset($result[$key])) {
                    if (is_array($config) && isset($config['default'])) {
                        $result[$key] = $config['default'];
                    } elseif (!is_array($config)) {
                        $result[$key] = $config;
                    }
                }
            }
        }

        // 用 DB 值覆盖
        try {
            $qb = app(\Core\Database\QueryBuilder::class);
            $rows = $qb->table('options')
                ->where('key_name', 'like', "theme_{$this->activeTheme}_%")
                ->get();
            foreach ($rows as $row) {
                $key = substr($row['key_name'], strlen("theme_{$this->activeTheme}_"));
                $val = json_decode($row['value'], true);
                $result[$key] = $val !== null ? $val : $row['value'];
            }
        } catch (\Throwable) {
            // DB not ready
        }

        return $result;
    }

    /**
     * 获取主题声明的页面模板列表。
     */
    public function getPageTemplates(): array
    {
        $templates = $this->themeConfig['page_templates'] ?? [];
        if ($this->parentConfig !== null) {
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
        if ($this->parentConfig !== null) {
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
        if ($this->parentConfig !== null) {
            $sidebars = array_merge($this->parentConfig['sidebars'] ?? [], $sidebars);
        }
        return $sidebars;
    }

    /**
     * 获取 theme.json 中声明的样式资源。
     */
    public function getDeclaredStyles(): array
    {
        return $this->themeConfig['styles'] ?? [];
    }

    /**
     * 获取 theme.json 中声明的脚本资源。
     */
    public function getDeclaredScripts(): array
    {
        return $this->themeConfig['scripts'] ?? [];
    }

    /**
     * 获取主题截图列表。
     */
    public function getScreenshots(): array
    {
        $screenshots = [];

        // 主截图
        $mainScreenshot = $this->themeConfig['screenshot'] ?? '';
        if ($mainScreenshot) {
            $screenshots[] = $mainScreenshot;
        }

        // 额外截图
        $extra = $this->themeConfig['screenshots'] ?? [];
        if (is_array($extra)) {
            foreach ($extra as $s) {
                if (is_string($s) && $s !== $mainScreenshot) {
                    $screenshots[] = $s;
                }
            }
        }

        // 如果没截图，搜索目录下常见的截图文件
        if (empty($screenshots)) {
            $dir = $this->path();
            foreach (['screenshot.jpg', 'screenshot.png', 'screenshot.jpeg', 'screenshot.webp'] as $name) {
                if (is_file($dir . '/' . $name)) {
                    $screenshots[] = $name;
                    break;
                }
            }
        }

        return $screenshots;
    }

    /**
     * 获取变更日志。
     */
    public function getChangelog(): array
    {
        $changelog = $this->themeConfig['changelog'] ?? [];
        return is_array($changelog) ? $changelog : [];
    }

    /**
     * 获取主题要求的系统版本。
     */
    public function getRequires(): string
    {
        return $this->themeConfig['requires'] ?? '';
    }

    /**
     * 获取主题要求的 PHP 版本。
     */
    public function getRequiresPhp(): string
    {
        return $this->themeConfig['requires_php'] ?? '';
    }

    /**
     * 获取推荐插件列表。
     */
    public function getRecommendedPlugins(): array
    {
        $plugins = $this->themeConfig['plugins'] ?? [];
        return is_array($plugins) ? $plugins : [];
    }

    /**
     * 获取主题分类。
     */
    public function getCategory(): string
    {
        return $this->themeConfig['category'] ?? '';
    }

    /**
     * 获取主题标签。
     */
    public function getTags(): array
    {
        $tags = $this->themeConfig['tags'] ?? [];
        return is_array($tags) ? $tags : [];
    }

    /**
     * 获取主题更新 URL。
     */
    public function getUpdateUrl(): string
    {
        return $this->themeConfig['update_url'] ?? '';
    }

    /**
     * 获取演示 URL。
     */
    public function getDemoUrl(): string
    {
        return $this->themeConfig['demo_url'] ?? '';
    }

    /**
     * 从目录读取主题元数据。
     */
    private function readMetaFromDir(string $dir): array
    {
        $jsonFile = $dir . '/theme.json';
        if (is_file($jsonFile)) {
            $data = json_decode(file_get_contents($jsonFile) ?: '', true);
            return is_array($data) ? $data : [];
        }
        return ['name' => basename($dir), 'version' => '0.0.0'];
    }

    /**
     * 生成主题配置的 CSS 自定义属性。
     */
    public function generateCssVariables(): string
    {
        $options = $this->themeConfig['options'] ?? [];
        $vars = [];
        $allOptions = $this->getAllOptions();

        foreach ($options as $key => $config) {
            if (!is_array($config)) {
                continue;
            }
            $type = $config['type'] ?? '';
            // 只有 color 类型自动映射为 CSS 变量
            if ($type === 'color' && isset($allOptions[$key])) {
                $varName = '--theme-' . str_replace('_', '-', $key);
                $vars[] = "  {$varName}: {$allOptions[$key]};";
            }
        }

        if (empty($vars)) {
            return '';
        }

        return ":root {\n" . implode("\n", $vars) . "\n}\n";
    }
}