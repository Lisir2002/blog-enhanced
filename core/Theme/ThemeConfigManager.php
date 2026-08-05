<?php

namespace Core\Theme;

/**
 * 主题配置管理器 — 负责主题元数据读取、父子合并、配置持久化。
 *
 * 职责单一：
 * - 读取和解析 theme.json（支持 section 分组）
 * - 父子主题配置合并
 * - 配置选项的持久化读写（DB options 表）
 * - 声明式资源（styles/scripts）的解析
 * - CSS 变量映射（所有类型，含暗色模式双值）
 * - 配置历史快照与回滚
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
     * 获取扁平化的 options 映射（key => config）。
     * 支持新旧格式：旧格式 {"key": config}，新格式 [{"section":"...","fields":{"key":config}}]
     */
    public function getFlatOptions(): array
    {
        $raw = $this->themeConfig['options'] ?? [];
        $flat = [];
        $this->flattenOptions($raw, $flat);
        // 合并父主题
        if ($this->parentConfig !== null) {
            $parentRaw = $this->parentConfig['options'] ?? [];
            $this->flattenOptions($parentRaw, $flat);
        }
        return $flat;
    }

    /**
     * 获取分组后的 options（用于定制器 UI 渲染）。
     * 返回数组：[ ["section"=>"名称","description"=>"","fields"=>[key=>config,...]], ... ]
     */
    public function getGroupedOptions(): array
    {
        $raw = $this->themeConfig['options'] ?? [];
        $groups = [];

        // 新格式：分区数组
        if ($this->isSectionedFormat($raw)) {
            foreach ($raw as $group) {
                if (!isset($group['section'])) continue;
                $groups[] = [
                    'section'     => $group['section'],
                    'description' => $group['description'] ?? '',
                    'fields'      => $group['fields'] ?? [],
                ];
            }
        } else {
            // 旧格式：平铺，自动归入一个"常规"分区
            $groups[] = [
                'section'     => '常规',
                'description' => '基本配置选项',
                'fields'      => $raw,
            ];
        }

        // 合并父主题分组
        if ($this->parentConfig !== null) {
            $parentRaw = $this->parentConfig['options'] ?? [];
            if ($this->isSectionedFormat($parentRaw)) {
                foreach ($parentRaw as $group) {
                    if (!isset($group['section'])) continue;
                    $exists = false;
                    foreach ($groups as &$g) {
                        if ($g['section'] === $group['section']) {
                            $g['fields'] = array_merge($group['fields'] ?? [], $g['fields']);
                            $exists = true;
                            break;
                        }
                    }
                    unset($g);
                    if (!$exists) {
                        $groups[] = [
                            'section'     => $group['section'],
                            'description' => $group['description'] ?? '',
                            'fields'      => $group['fields'] ?? [],
                        ];
                    }
                }
            }
        }

        return $groups;
    }

    /**
     * 判断 options 是否为分区格式（数组 vs 对象）。
     */
    private function isSectionedFormat(array $options): bool
    {
        if (empty($options)) return false;
        // 检查第一个元素是否有 section 键
        $first = reset($options);
        return is_array($first) && (isset($first['section']) || isset($first['fields']));
    }

    /**
     * 递归展平 options。
     */
    private function flattenOptions(array $raw, array &$flat): void
    {
        if ($this->isSectionedFormat($raw)) {
            foreach ($raw as $group) {
                if (isset($group['fields']) && is_array($group['fields'])) {
                    foreach ($group['fields'] as $key => $config) {
                        if (!isset($flat[$key])) {
                            $flat[$key] = is_array($config) ? $config : ['type' => 'text', 'default' => $config];
                        }
                    }
                }
            }
        } else {
            foreach ($raw as $key => $config) {
                if (!isset($flat[$key])) {
                    $flat[$key] = is_array($config) ? $config : ['type' => 'text', 'default' => $config];
                }
            }
        }
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
        $flat = $this->getFlatOptions();
        if (isset($flat[$key]['default'])) {
            return $flat[$key]['default'];
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
        $flat = $this->getFlatOptions();
        $result = [];

        // 先用默认值填充
        foreach ($flat as $key => $config) {
            if (is_array($config) && isset($config['default'])) {
                $result[$key] = $config['default'];
            } elseif (!is_array($config)) {
                $result[$key] = $config;
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

    /* ═══════════════ 配置历史快照（Phase F） ═══════════════ */

    /**
     * 创建当前配置的快照。
     *
     * @param string $note 变更说明
     * @return int 快照 ID
     */
    public function createSnapshot(string $note = ''): int
    {
        $config = $this->getAllOptions();
        try {
            $qb = app(\Core\Database\QueryBuilder::class);
            $qb->table('theme_config_revisions')->insert([
                'theme'      => $this->activeTheme,
                'snapshot'   => json_encode($config, JSON_UNESCAPED_UNICODE),
                'note'       => $note,
                'created_by' => $_SESSION['user_id'] ?? 0,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            return (int) $qb->getPdo()->lastInsertId();
        } catch (\Throwable $e) {
            throw new \RuntimeException("创建配置快照失败: " . $e->getMessage());
        }
    }

    /**
     * 获取配置快照列表。
     */
    public function getSnapshots(int $limit = 50): array
    {
        try {
            $qb = app(\Core\Database\QueryBuilder::class);
            return $qb->table('theme_config_revisions')
                ->where('theme', '=', $this->activeTheme)
                ->orderBy('created_at', 'DESC')
                ->limit($limit)
                ->get();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * 获取单个快照。
     */
    public function getSnapshot(int $id): ?array
    {
        try {
            $qb = app(\Core\Database\QueryBuilder::class);
            $row = $qb->table('theme_config_revisions')
                ->where('id', '=', $id)
                ->where('theme', '=', $this->activeTheme)
                ->first();
            return $row ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * 回滚到指定快照。
     */
    public function restoreSnapshot(int $id): void
    {
        $snapshot = $this->getSnapshot($id);
        if (!$snapshot) {
            throw new \RuntimeException("快照 #{$id} 不存在");
        }

        $config = json_decode($snapshot['snapshot'], true);
        if (!is_array($config)) {
            throw new \RuntimeException("快照数据损坏");
        }

        // 先创建当前配置的快照（自动备份）
        $this->createSnapshot("回滚前自动备份 (-> #{$id})");

        // 清除当前所有配置
        $flat = $this->getFlatOptions();
        foreach ($flat as $key => $cfg) {
            if (isset($config[$key])) {
                $this->setOption($key, $config[$key]);
            } else {
                $this->deleteOption($key);
            }
        }

        // 记录回滚
        try {
            $qb = app(\Core\Database\QueryBuilder::class);
            $qb->table('theme_config_revisions')
                ->where('id', '=', $id)
                ->update(['restored_at' => date('Y-m-d H:i:s')]);
        } catch (\Throwable) {
            // ignore
        }
    }

    /* ═══════════════ 页面模板 / 菜单 / 侧边栏 ═══════════════ */

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
     * 支持所有类型（color/range/select/text），通过 css_var 配置映射。
     * 支持暗色模式双值（dark_default）。
     *
     * @param string $themeMode 'light' 或 'dark'，指定生成哪种模式
     * @return string
     */
    public function generateCssVariables(string $themeMode = 'light'): string
    {
        $flat = $this->getFlatOptions();
        $vars = [];
        $darkVars = [];
        $allOptions = $this->getAllOptions();

        foreach ($flat as $key => $config) {
            if (!is_array($config)) continue;
            $cssVar = $config['css_var'] ?? '';
            if (empty($cssVar)) continue;

            $value = $allOptions[$key] ?? $config['default'] ?? '';

            // 处理颜色类型：如果有 dark_default 且当前为暗色模式
            if ($themeMode === 'dark' && isset($config['dark_default'])) {
                $darkValue = $this->getDarkValue($key, $config, $allOptions);
                $darkVars[] = "  {$cssVar}: {$darkValue};";
            }

            // 处理 select 类型：可能 choices 中的值需要映射
            if ($config['type'] === 'select' && isset($config['choices']) && is_array($config['choices'])) {
                // 直接使用选中的值
                $vars[] = "  {$cssVar}: {$value};";
            } elseif ($config['type'] === 'range' && isset($config['unit']) && $config['unit'] !== '') {
                $vars[] = "  {$cssVar}: {$value}{$config['unit']};";
            } elseif ($config['type'] === 'switch') {
                $vars[] = "  {$cssVar}: " . ($value ? '1' : '0') . ";";
            } else {
                $vars[] = "  {$cssVar}: {$value};";
            }
        }

        $output = '';
        if (!empty($vars)) {
            $output .= ":root {\n" . implode("\n", $vars) . "\n}\n";
        }
        if (!empty($darkVars)) {
            $output .= "[data-theme=\"dark\"] {\n" . implode("\n", $darkVars) . "\n}\n";
        }

        return $output;
    }

    /**
     * 获取暗色模式下的颜色值。
     * 优先级：DB 存储的暗色值 > theme.json dark_default > 默认值
     */
    private function getDarkValue(string $key, array $config, array $allOptions): string
    {
        // 尝试从 DB 读取暗色值（约定键名：key + _dark 后缀）
        $darkKey = $key . '_dark';
        if (isset($allOptions[$darkKey]) && !empty($allOptions[$darkKey])) {
            return $allOptions[$darkKey];
        }
        // 回退到 theme.json dark_default
        if (isset($config['dark_default']) && !empty($config['dark_default'])) {
            return $config['dark_default'];
        }
        // 再回退到默认值
        return $allOptions[$key] ?? $config['default'] ?? '';
    }

    /**
     * 生成完整的 CSS 变量输出（含浅色 + 暗色模式）。
     * 用于前端 <head> 注入。
     */
    public function generateFullCssVariables(): string
    {
        $light = $this->generateCssVariables('light');
        $darkOnly = $this->generateCssVariables('dark');

        // 如果暗色模式没有额外变量，只输出浅色
        if (empty(trim($darkOnly))) {
            return $light;
        }

        // 去掉 light 中的暗色部分（generateCssVariables('light') 不会产生暗色块）
        return $light . "\n" . $darkOnly;
    }
}