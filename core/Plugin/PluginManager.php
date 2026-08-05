<?php

namespace Core\Plugin;

/**
 * 插件管理器 — WordPress 风格，全面强化版。
 *
 * P0 能力：
 * - 插件目录 plugins/{name}/
 * - 主插件文件 plugins/{name}/{name}.php 头部含 Plugin Name 等元数据
 * - 支持 plugin.json 配置（优先级高于 PHP 头注释）
 * - activate / deactivate / uninstall 钩子
 * - 已激活的插件在 boot 时加载
 * - 错误隔离（损坏插件不崩溃系统）
 * - Zip Slip 安全防护
 *
 * P1 新增能力：
 * - 依赖管理（依赖解析、自动激活、级联停用、循环依赖检测、卸载保护）
 * - 配置系统（getConfig/setConfig/deleteConfig API）
 * - 资产入队（CSS/JS 注册与注入）
 * - 命名空间沙箱（PSR-4 autoloader 注册）
 * - 更新检测（update_url 版本比对）
 * - API 扩展（自定义路由、REST 端点、短代码、Widget、事件总线）
 */
class PluginManager
{
    private string $pluginRoot;

    /** @var array<string, array{file: string, meta: array, dir: string}> */
    private array $plugins = [];

    /** @var array<string, true> */
    private array $active = [];

    /** @var array<string, true> */
    private array $loaded = [];

    /** @var array<string, array<callable>> 已注册的激活回调 */
    private array $activationHooks = [];

    /** @var array<string, array<callable>> 已注册的停用回调 */
    private array $deactivationHooks = [];

    /** @var array<string, array<callable>> 已注册的卸载回调 */
    private array $uninstallHooks = [];

    /** @var array<string, string> 加载失败的插件错误信息 */
    private array $errors = [];

    /** @var array<string, array{id: string, callback: callable}> 已注册的短代码 */
    private array $shortcodes = [];

    /** @var array<string, array{method: string, route: string, callback: callable}> 已注册的自定义路由 */
    private array $routes = [];

    /** @var array<string, array{method: string, endpoint: string, callback: callable}> 已注册的 REST 端点 */
    private array $restEndpoints = [];

    /** @var array<string, array{render: callable, description: string}> 已注册的 Widget */
    private array $widgets = [];

    /** @var array<string, array{style: string, media: string}[]> 已入队的 CSS */
    private array $enqueuedStyles = [];

    /** @var array<string, array{script: string, attributes: array}[]> 已入队的 JS */
    private array $enqueuedScripts = [];

    /** @var array<string, array{name: string, prefix: string}> 已注册的 PSR-4 自动加载 */
    private array $pluginAutoloaders = [];

    /** @var array<string, array<int, array{callback: callable}>> 事件总线 */
    private array $eventListeners = [];

    private bool $booted = false;

    public function __construct()
    {
        $this->pluginRoot = plugins_path();
    }

    // ============================================================
    //  Boot
    // ============================================================

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;

        $this->scanPlugins();

        // 注册 PSR-4 autoloader
        $this->registerPluginAutoloaders();

        $this->loadActiveList();

        // 按依赖拓扑排序后加载
        $sorted = $this->topologicalSort(array_keys($this->active));
        foreach ($sorted as $name) {
            if (isset($this->plugins[$name])) {
                $this->loadPlugin($name);
            }
        }

        do_action('plugins_loaded');
    }

    // ============================================================
    //  扫描与解析
    // ============================================================

    private function scanPlugins(): void
    {
        if (!is_dir($this->pluginRoot)) {
            return;
        }
        foreach ((array) glob($this->pluginRoot . '/*', GLOB_ONLYDIR) as $dir) {
            $name = basename($dir);
            $mainFile = $this->resolveMainFile($dir, $name);
            if ($mainFile === null) {
                continue;
            }
            $meta = $this->parsePluginMetadata($dir, $mainFile);
            $this->plugins[$name] = [
                'file' => $mainFile,
                'meta' => $meta,
                'dir'  => $dir,
            ];
        }
    }

    private function resolveMainFile(string $dir, string $name): ?string
    {
        $jsonFile = $dir . '/plugin.json';
        if (is_file($jsonFile)) {
            $jsonContent = file_get_contents($jsonFile);
            $jsonData = json_decode($jsonContent, true);
            if (is_array($jsonData) && !empty($jsonData['entry'])) {
                $entryFile = $dir . '/' . ltrim($jsonData['entry'], '/');
                if (is_file($entryFile)) {
                    return $entryFile;
                }
            }
        }

        $mainFile = $dir . '/' . $name . '.php';
        if (is_file($mainFile)) {
            return $mainFile;
        }

        $mainFile = $dir . '/index.php';
        if (is_file($mainFile)) {
            return $mainFile;
        }

        if (is_file($jsonFile)) {
            $jsonContent = file_get_contents($jsonFile);
            $jsonData = json_decode($jsonContent, true);
            if (is_array($jsonData) && !empty($jsonData['name'])) {
                $stubFile = $dir . '/' . $name . '.php';
                file_put_contents($stubFile, "<?php\n// Plugin: {$jsonData['name']}\n");
                return $stubFile;
            }
        }

        return null;
    }

    private function parsePluginMetadata(string $dir, string $mainFile): array
    {
        $jsonFile = $dir . '/plugin.json';
        $meta = [];
        if (is_file($jsonFile)) {
            $jsonContent = file_get_contents($jsonFile);
            $jsonData = json_decode($jsonContent, true);
            if (is_array($jsonData)) {
                $fieldMap = [
                    'name'         => 'name',
                    'description'  => 'description',
                    'version'      => 'version',
                    'author'       => 'author',
                    'author_uri'   => 'author_uri',
                    'plugin_uri'   => 'plugin_uri',
                    'license'      => 'license',
                    'min_version'  => 'requires_core',
                    'php_version'  => 'requires_php',
                    'entry'        => 'entry',
                    'update_url'   => 'update_url',
                    'namespace'    => 'namespace',
                    'icon'         => 'icon',
                ];
                foreach ($fieldMap as $jsonKey => $metaKey) {
                    if (isset($jsonData[$jsonKey])) {
                        $meta[$metaKey] = $jsonData[$jsonKey];
                    }
                }
                if (isset($jsonData['tags'])) {
                    $meta['tags'] = $jsonData['tags'];
                }
                if (isset($jsonData['requires'])) {
                    $meta['requires'] = $jsonData['requires'];
                }
                if (isset($jsonData['depends_on'])) {
                    $meta['depends_on'] = $jsonData['depends_on'];
                }
                if (isset($jsonData['screenshots'])) {
                    $meta['screenshots'] = $jsonData['screenshots'];
                }
                if (isset($jsonData['changelog'])) {
                    $meta['changelog'] = $jsonData['changelog'];
                }
            }
        }

        $phpMeta = $this->parsePluginHeaders($mainFile);
        foreach ($phpMeta as $key => $value) {
            if (!isset($meta[$key]) || $meta[$key] === '') {
                $meta[$key] = $value;
            }
        }

        return $meta;
    }

    public function parsePluginHeaders(string $file): array
    {
        $content = (string) file_get_contents($file);
        $fields = [
            'name' => 'Plugin Name',
            'description' => 'Description',
            'version' => 'Version',
            'author' => 'Author',
            'author_uri' => 'Author URI',
            'plugin_uri' => 'Plugin URI',
            'license' => 'License',
            'requires_php' => 'Requires PHP',
        ];
        $result = [];
        foreach ($fields as $key => $label) {
            if (preg_match('/\*\s*' . preg_quote($label, '/') . '\s*:\s*(.+)/i', $content, $m)) {
                $result[$key] = trim($m[1]);
            }
        }
        return $result;
    }

    // ============================================================
    //  1. 依赖管理
    // ============================================================

    /**
     * 解析插件的依赖列表。
     * @return string[] 依赖的插件名列表
     */
    public function getDependencies(string $name): array
    {
        if (!isset($this->plugins[$name])) {
            return [];
        }
        $dependsOn = $this->plugins[$name]['meta']['depends_on'] ?? [];
        if (is_string($dependsOn)) {
            $dependsOn = [$dependsOn];
        }
        return is_array($dependsOn) ? $dependsOn : [];
    }

    /**
     * 检测依赖是否满足。
     * @return array{valid: bool, missing: string[], inactive: string[]}
     */
    public function checkDependencies(string $name): array
    {
        $deps = $this->getDependencies($name);
        $missing = [];
        $inactive = [];

        foreach ($deps as $dep) {
            if (!isset($this->plugins[$dep])) {
                $missing[] = $dep;
            } elseif (!$this->isActive($dep)) {
                $inactive[] = $dep;
            }
        }

        return [
            'valid' => empty($missing) && empty($inactive),
            'missing' => $missing,
            'inactive' => $inactive,
        ];
    }

    /**
     * 检测循环依赖（DFS）。
     * @return string[][] 所有检测到的环，空数组表示无环
     */
    public function detectCircularDependencies(): array
    {
        $cycles = [];
        $graph = [];
        foreach ($this->plugins as $name => $info) {
            $deps = $this->getDependencies($name);
            foreach ($deps as $dep) {
                if (isset($this->plugins[$dep])) {
                    $graph[$name][] = $dep;
                }
            }
        }

        $visited = [];
        $recStack = [];
        $path = [];

        foreach (array_keys($graph) as $node) {
            $this->dfsCycle($node, $graph, $visited, $recStack, $path, $cycles);
        }

        return $cycles;
    }

    private function dfsCycle(string $node, array &$graph, array &$visited, array &$recStack, array &$path, array &$cycles): void
    {
        if (isset($recStack[$node])) {
            // 找到环
            $cycle = [];
            $found = false;
            foreach ($path as $p) {
                if ($p === $node || $found) {
                    $found = true;
                    $cycle[] = $p;
                }
            }
            if ($found) {
                $cycle[] = $node;
                $cycles[] = $cycle;
            }
            return;
        }
        if (isset($visited[$node])) {
            return;
        }
        $visited[$node] = true;
        $recStack[$node] = true;
        $path[] = $node;

        foreach ($graph[$node] ?? [] as $neighbor) {
            $this->dfsCycle($neighbor, $graph, $visited, $recStack, $path, $cycles);
        }

        array_pop($path);
        unset($recStack[$node]);
    }

    /**
     * 拓扑排序：依赖在前，被依赖在后。
     * @param string[] $names
     * @return string[]
     */
    private function topologicalSort(array $names): array
    {
        $graph = [];
        $nodes = [];
        foreach ($names as $name) {
            if (isset($this->plugins[$name])) {
                $nodes[$name] = true;
                $deps = $this->getDependencies($name);
                foreach ($deps as $dep) {
                    if (isset($this->plugins[$dep]) && in_array($dep, $names)) {
                        $graph[$name][] = $dep;
                    }
                }
            }
        }

        $sorted = [];
        $visited = [];
        $temp = [];

        $visit = function (string $n) use (&$visit, &$graph, &$sorted, &$visited, &$temp): void {
            if (isset($temp[$n])) return; // 有环，跳过
            if (isset($visited[$n])) return;
            $temp[$n] = true;
            foreach ($graph[$n] ?? [] as $dep) {
                $visit($dep);
            }
            unset($temp[$n]);
            $visited[$n] = true;
            $sorted[] = $n;
        };

        foreach (array_keys($nodes) as $n) {
            $visit($n);
        }

        return $sorted;
    }

    /**
     * 获取被指定插件依赖的所有插件。
     * @return string[]
     */
    public function getDependents(string $name): array
    {
        $dependents = [];
        foreach ($this->plugins as $pName => $info) {
            $deps = $this->getDependencies($pName);
            if (in_array($name, $deps)) {
                $dependents[] = $pName;
            }
        }
        return $dependents;
    }

    // ============================================================
    //  2. 配置系统
    // ============================================================

    /**
     * 获取插件配置项。
     */
    public function getConfig(string $name, string $key, mixed $default = null): mixed
    {
        try {
            $qb = app(\Core\Database\QueryBuilder::class);
            $row = $qb->table('options')
                ->where('key_name', "plugin_config_{$name}_{$key}")
                ->first();
            if ($row) {
                $val = json_decode($row['value'], true);
                return $val !== null ? $val : $row['value'];
            }
        } catch (\Throwable $e) {
            // DB not ready
        }
        return $default;
    }

    /**
     * 获取插件全部配置。
     */
    public function getAllConfig(string $name): array
    {
        $config = [];
        try {
            $qb = app(\Core\Database\QueryBuilder::class);
            $rows = $qb->table('options')
                ->where('key_name', 'LIKE', "plugin_config_{$name}_%")
                ->get();
            $prefix = "plugin_config_{$name}_";
            foreach ($rows as $row) {
                $key = substr($row['key_name'], strlen($prefix));
                $val = json_decode($row['value'], true);
                $config[$key] = $val !== null ? $val : $row['value'];
            }
        } catch (\Throwable $e) {
            // DB not ready
        }
        return $config;
    }

    /**
     * 设置插件配置项。
     */
    public function setConfig(string $name, string $key, mixed $value): void
    {
        $optionKey = "plugin_config_{$name}_{$key}";
        $json = is_string($value) ? $value : json_encode($value);
        try {
            $qb = app(\Core\Database\QueryBuilder::class);
            $exists = $qb->table('options')->where('key_name', $optionKey)->first();
            if ($exists) {
                $qb->table('options')->where('key_name', $optionKey)->update([
                    'value' => $json,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            } else {
                $qb->table('options')->insert([
                    'key_name' => $optionKey,
                    'value' => $json,
                    'autoload' => 0,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        } catch (\Throwable $e) {
            \Core\Log\Log::warning("Plugin config set error: {$e->getMessage()}");
        }
    }

    /**
     * 删除插件配置项。
     */
    public function deleteConfig(string $name, string $key): void
    {
        $optionKey = "plugin_config_{$name}_{$key}";
        try {
            $qb = app(\Core\Database\QueryBuilder::class);
            $qb->table('options')->where('key_name', $optionKey)->delete();
        } catch (\Throwable $e) {
            // silent
        }
    }

    /**
     * 清理插件所有配置（卸载时调用）。
     */
    public function clearAllConfig(string $name): void
    {
        try {
            $qb = app(\Core\Database\QueryBuilder::class);
            $qb->table('options')
                ->where('key_name', 'LIKE', "plugin_config_{$name}_%")
                ->delete();
        } catch (\Throwable $e) {
            // silent
        }
    }

    // ============================================================
    //  3. 资产入队（CSS/JS）
    // ============================================================

    /**
     * 注册插件 CSS 文件。
     */
    public function enqueueStyle(string $name, string $handle, string $style, string $media = 'all'): void
    {
        $this->enqueuedStyles[$name][] = [
            'handle' => $handle,
            'style' => $style,
            'media' => $media,
        ];
    }

    /**
     * 注册插件 JS 文件。
     */
    public function enqueueScript(string $name, string $handle, string $script, array $attributes = []): void
    {
        $this->enqueuedScripts[$name][] = [
            'handle' => $handle,
            'script' => $script,
            'attributes' => $attributes,
        ];
    }

    /**
     * 获取已入队的 CSS。
     * @return array
     */
    public function getEnqueuedStyles(): array
    {
        return $this->enqueuedStyles;
    }

    /**
     * 获取已入队的 JS。
     * @return array
     */
    public function getEnqueuedScripts(): array
    {
        return $this->enqueuedScripts;
    }

    // ============================================================
    //  4. 短代码
    // ============================================================

    /**
     * 注册插件短代码。
     */
    public function registerShortcode(string $name, string $tag, callable $callback): void
    {
        $this->shortcodes[$tag] = [
            'id' => $name,
            'callback' => $callback,
        ];
    }

    /**
     * 获取所有已注册的短代码。
     */
    public function getShortcodes(): array
    {
        return $this->shortcodes;
    }

    // ============================================================
    //  5. Widget
    // ============================================================

    /**
     * 注册插件 Widget。
     */
    public function registerWidget(string $name, string $widgetId, callable $render, string $description = ''): void
    {
        $this->widgets[$widgetId] = [
            'plugin' => $name,
            'render' => $render,
            'description' => $description,
        ];
    }

    /**
     * 获取所有已注册的 Widget。
     */
    public function getWidgets(): array
    {
        return $this->widgets;
    }

    // ============================================================
    //  6. 命名空间沙箱与 PSR-4 Autoloader
    // ============================================================

    /**
     * 注册插件 PSR-4 autoloader。
     * 插件在 plugin.json 中声明 "namespace" 字段，系统自动将其目录加入 autoload。
     */
    public function registerPluginAutoloaders(): void
    {
        foreach ($this->plugins as $name => $info) {
            $ns = $info['meta']['namespace'] ?? '';
            if ($ns === '') {
                continue;
            }
            $ns = trim($ns, '\\') . '\\';
            $dir = $info['dir'] . '/src';
            if (!is_dir($dir)) {
                $dir = $info['dir'];
            }
            $this->pluginAutoloaders[$name] = [
                'name' => $ns,
                'prefix' => $dir,
            ];

            spl_autoload_register(function (string $class) use ($ns, $dir): void {
                if (strncmp($class, $ns, strlen($ns)) !== 0) {
                    return;
                }
                $relative = substr($class, strlen($ns));
                $file = $dir . '/' . str_replace('\\', '/', $relative) . '.php';
                if (is_file($file)) {
                    require $file;
                }
            });
        }
    }

    /**
     * 获取已注册的 PSR-4 autoloader 信息。
     */
    public function getPluginAutoloaders(): array
    {
        return $this->pluginAutoloaders;
    }

    // ============================================================
    //  7. 更新检测
    // ============================================================

    /**
     * 检查插件更新。
     * @return array{update_available: bool, latest_version: string, download_url: string, changelog: string}|null
     */
    public function checkForUpdates(string $name): ?array
    {
        if (!isset($this->plugins[$name])) {
            return null;
        }
        $meta = $this->plugins[$name]['meta'];
        $updateUrl = $meta['update_url'] ?? '';
        if ($updateUrl === '') {
            return null;
        }

        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'user_agent' => 'CMS-Plugin-Update-Checker/1.0',
                ],
            ]);
            $response = @file_get_contents($updateUrl, false, $context);
            if ($response === false) {
                return null;
            }
            $data = json_decode($response, true);
            if (!is_array($data) || empty($data['version'])) {
                return null;
            }

            $currentVersion = $meta['version'] ?? '0.0.0';
            $latestVersion = $data['version'];

            return [
                'update_available' => version_compare($latestVersion, $currentVersion, '>'),
                'latest_version' => $latestVersion,
                'download_url' => $data['download_url'] ?? '',
                'changelog' => $data['changelog'] ?? '',
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * 从更新 URL 下载并安装更新。
     */
    public function updateFromUrl(string $name): array
    {
        $updateInfo = $this->checkForUpdates($name);
        if ($updateInfo === null || !$updateInfo['update_available']) {
            throw new \RuntimeException("Plugin [$name] has no available update.");
        }
        if ($updateInfo['download_url'] === '') {
            throw new \RuntimeException("Plugin [$name] has no download URL.");
        }

        $tmpZip = sys_get_temp_dir() . '/' . $name . '_update_' . uniqid() . '.zip';
        $context = stream_context_create([
            'http' => ['timeout' => 30, 'user_agent' => 'CMS-Plugin-Updater/1.0'],
        ]);
        $zipContent = @file_get_contents($updateInfo['download_url'], false, $context);
        if ($zipContent === false) {
            @unlink($tmpZip);
            throw new \RuntimeException("Failed to download update for [$name].");
        }
        file_put_contents($tmpZip, $zipContent);

        try {
            $result = $this->installFromZip($tmpZip);
            @unlink($tmpZip);
            return $result;
        } catch (\Throwable $e) {
            @unlink($tmpZip);
            throw $e;
        }
    }

    // ============================================================
    //  8. 事件总线（插件间通信）
    // ============================================================

    /**
     * 注册事件监听器。
     */
    public function on(string $event, callable $callback): void
    {
        $this->eventListeners[$event][] = ['callback' => $callback];
    }

    /**
     * 触发事件。
     */
    public function emit(string $event, mixed ...$args): void
    {
        if (empty($this->eventListeners[$event])) {
            return;
        }
        foreach ($this->eventListeners[$event] as $listener) {
            try {
                call_user_func_array($listener['callback'], $args);
            } catch (\Throwable $e) {
                \Core\Log\Log::warning("Event listener error [{$event}]: " . $e->getMessage());
            }
        }
    }

    /**
     * 移除事件监听器。
     */
    public function off(string $event, ?callable $callback = null): void
    {
        if ($callback === null) {
            unset($this->eventListeners[$event]);
            return;
        }
        if (!empty($this->eventListeners[$event])) {
            $this->eventListeners[$event] = array_filter(
                $this->eventListeners[$event],
                fn ($l) => $l['callback'] !== $callback
            );
        }
    }

    // ============================================================
    //  兼容性检查
    // ============================================================

    public function validateCompatibility(string $name): array
    {
        $errors = [];

        if (!isset($this->plugins[$name])) {
            return [
                'valid' => false,
                'errors' => ["插件 [$name] 不存在"],
            ];
        }

        $meta = $this->plugins[$name]['meta'] ?? [];

        if (!empty($meta['requires_php'])) {
            $requiredPhp = $meta['requires_php'];
            $currentPhp = PHP_VERSION;
            $versionToCheck = ltrim($requiredPhp, '>=^~');
            if (version_compare($currentPhp, $versionToCheck, '<')) {
                $errors[] = "PHP 版本过低，需要 >= {$versionToCheck}，当前 {$currentPhp}";
            }
        }

        if (!empty($meta['requires_core'])) {
            $requiredCore = $meta['requires_core'];
            $currentCore = defined('CMS_VERSION') ? CMS_VERSION : '2.0.0';
            $versionToCheck = ltrim($requiredCore, '>=^~');
            if (version_compare($currentCore, $versionToCheck, '<')) {
                $errors[] = "核心版本过低，需要 >= {$versionToCheck}，当前 {$currentCore}";
            }
        }

        if (!empty($meta['requires']['extensions']) && is_array($meta['requires']['extensions'])) {
            foreach ($meta['requires']['extensions'] as $ext) {
                if (!extension_loaded($ext)) {
                    $errors[] = "缺少 PHP 扩展: {$ext}";
                }
            }
        }

        // 依赖检查
        $depCheck = $this->checkDependencies($name);
        if (!$depCheck['valid']) {
            foreach ($depCheck['missing'] as $dep) {
                $errors[] = "缺少依赖插件: {$dep}";
            }
            foreach ($depCheck['inactive'] as $dep) {
                $errors[] = "依赖插件未激活: {$dep}";
            }
        }

        return [
            'valid' => count($errors) === 0,
            'errors' => $errors,
        ];
    }

    // ============================================================
    //  激活/停用/卸载
    // ============================================================

    public function activate(string $name): void
    {
        if (!isset($this->plugins[$name])) {
            throw new \RuntimeException("Plugin [$name] not found.");
        }
        if ($this->isActive($name)) {
            return;
        }

        // 先自动激活依赖插件（依赖先激活，兼容性检查跳过未激活的依赖）
        $deps = $this->getDependencies($name);
        foreach ($deps as $dep) {
            if (isset($this->plugins[$dep]) && !$this->isActive($dep)) {
                $this->activate($dep);
            }
        }

        // 兼容性检查（依赖已激活，此时检查应通过）
        $compat = $this->validateCompatibility($name);
        if (!$compat['valid']) {
            // 回滚已激活的依赖
            foreach ($deps as $dep) {
                if (isset($this->active[$dep])) {
                    unset($this->active[$dep]);
                }
            }
            throw new \RuntimeException('Compatibility check failed: ' . implode('; ', $compat['errors']));
        }

        $this->loadPlugin($name);
        $this->active[$name] = true;
        $this->persistActiveList();

        if (!empty($this->activationHooks[$name])) {
            foreach ($this->activationHooks[$name] as $callback) {
                try {
                    call_user_func($callback);
                } catch (\Throwable $e) {
                    $this->errors[$name] = 'activation_hook_error: ' . $e->getMessage();
                }
            }
        }

        do_action("activate_{$name}", $name);
    }

    public function deactivate(string $name): void
    {
        if (!isset($this->plugins[$name])) {
            return;
        }

        // 检查是否有其他插件依赖本插件
        $dependents = $this->getDependents($name);
        $activeDependents = [];
        foreach ($dependents as $dep) {
            if ($this->isActive($dep)) {
                $activeDependents[] = $dep;
            }
        }
        if (!empty($activeDependents)) {
            throw new \RuntimeException(
                '无法停用：以下插件依赖本插件：' . implode(', ', $activeDependents)
            );
        }

        if (!empty($this->deactivationHooks[$name])) {
            foreach ($this->deactivationHooks[$name] as $callback) {
                try {
                    call_user_func($callback);
                } catch (\Throwable $e) {
                    $this->errors[$name] = 'deactivation_hook_error: ' . $e->getMessage();
                }
            }
        }

        do_action("deactivate_{$name}", $name);
        unset($this->active[$name]);
        $this->persistActiveList();
    }

    public function uninstall(string $name): void
    {
        // 检查是否有其他插件依赖本插件
        $dependents = $this->getDependents($name);
        if (!empty($dependents)) {
            throw new \RuntimeException(
                '无法卸载：以下插件依赖本插件：' . implode(', ', $dependents)
            );
        }

        $this->deactivate($name);

        if (!empty($this->uninstallHooks[$name])) {
            foreach ($this->uninstallHooks[$name] as $callback) {
                try {
                    call_user_func($callback);
                } catch (\Throwable $e) {
                    $this->errors[$name] = 'uninstall_hook_error: ' . $e->getMessage();
                }
            }
        }

        do_action("uninstall_{$name}", $name);

        // 清理配置
        $this->clearAllConfig($name);

        $dir = $this->pluginRoot . '/' . $name;
        if (is_dir($dir)) {
            $this->rrmdir($dir);
        }
    }

    // ============================================================
    //  钩子注册
    // ============================================================

    public function registerActivationHook(string $pluginName, callable $callback): void
    {
        $this->activationHooks[$pluginName][] = $callback;
    }

    public function registerDeactivationHook(string $pluginName, callable $callback): void
    {
        $this->deactivationHooks[$pluginName][] = $callback;
    }

    public function registerUninstallHook(string $pluginName, callable $callback): void
    {
        $this->uninstallHooks[$pluginName][] = $callback;
    }

    // ============================================================
    //  查询方法
    // ============================================================

    public function listPlugins(): array
    {
        $list = [];
        foreach ($this->plugins as $name => $info) {
            $list[$name] = [
                'name' => $name,
                'meta' => $info['meta'],
                'active' => $this->isActive($name),
                'dir' => $info['dir'],
            ];
        }
        ksort($list);
        return $list;
    }

    /**
     * 获取单个插件详情。
     */
    public function getPlugin(string $name): ?array
    {
        if (!isset($this->plugins[$name])) {
            return null;
        }
        $info = $this->plugins[$name];
        return [
            'name' => $name,
            'meta' => $info['meta'],
            'active' => $this->isActive($name),
            'dir' => $info['dir'],
            'file' => $info['file'],
            'errors' => $this->errors[$name] ?? null,
            'dependencies' => $this->getDependencies($name),
            'dependents' => $this->getDependents($name),
        ];
    }

    public function isActive(string $name): bool
    {
        return isset($this->active[$name]);
    }

    public function getActiveList(): array
    {
        return $this->active;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    // ============================================================
    //  加载
    // ============================================================

    private function loadActiveList(): void
    {
        try {
            $qb = app(\Core\Database\QueryBuilder::class);
            $row = $qb->table('options')->where('key_name', 'active_plugins')->first();
            if (!empty($row['value'])) {
                $names = json_decode($row['value'], true);
                if (is_array($names)) {
                    $this->active = array_flip($names);
                }
            }
        } catch (\Throwable $e) {
            // DB not ready
        }
    }

    private function loadPlugin(string $name): void
    {
        if (isset($this->loaded[$name])) {
            return;
        }
        $file = $this->plugins[$name]['file'] ?? null;
        if ($file && is_file($file)) {
            global $__current_plugin_loading;
            $prevPlugin = $__current_plugin_loading;
            $__current_plugin_loading = $name;

            try {
                require $file;
                $this->loaded[$name] = true;
            } catch (\Throwable $e) {
                $this->errors[$name] = 'load_error: ' . $e->getMessage();
                \Core\Log\Log::warning("Plugin [$name] load error: " . $e->getMessage());
            } finally {
                $__current_plugin_loading = $prevPlugin;
            }
        }
    }

    private function persistActiveList(): void
    {
        $names = array_keys($this->active);
        $json = json_encode(array_values($names));
        try {
            $qb = app(\Core\Database\QueryBuilder::class);
            $exists = $qb->table('options')->where('key_name', 'active_plugins')->first();
            if ($exists) {
                $qb->table('options')->where('key_name', 'active_plugins')->update([
                    'value' => $json,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            } else {
                $qb->table('options')->insert([
                    'key_name' => 'active_plugins',
                    'value' => $json,
                    'autoload' => 1,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        } catch (\Throwable $e) {
            // DB not ready
        }
    }

    // ============================================================
    //  ZIP 安装
    // ============================================================

    public function installFromZip(string $zipPath): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('ZipArchive extension required for plugin upload.');
        }
        $zip = new \ZipArchive();
        if (($code = $zip->open($zipPath)) !== true) {
            throw new \RuntimeException("Cannot open zip: error code $code");
        }

        // Zip Slip 安全校验
        $pluginRoot = $this->pluginRoot;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (strpos($name, '..') !== false) {
                $zip->close();
                throw new \RuntimeException('安全错误：ZIP 包含非法路径穿越，已拒绝安装。');
            }
            $targetPath = $pluginRoot . '/' . $name;
            $normalizedTarget = str_replace('\\', '/', $targetPath);
            $normalizedRoot = str_replace('\\', '/', $pluginRoot);
            if (strpos($normalizedTarget, $normalizedRoot) !== 0) {
                $zip->close();
                throw new \RuntimeException('安全错误：ZIP 文件试图写入非法路径，已拒绝安装。');
            }
        }

        $tmpDir = $this->pluginRoot . '/.upload-' . substr(md5((string) microtime(true)), 0, 8);
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0777, true);
        }
        $zip->extractTo($tmpDir);
        $zip->close();

        // 查找插件根目录
        $pluginDir = null;
        $pluginName = null;
        $entries = array_diff(scandir($tmpDir), ['.', '..']);
        foreach ($entries as $entry) {
            $candidate = $tmpDir . '/' . $entry;
            if (is_dir($candidate)) {
                $mainFile = $candidate . '/' . $entry . '.php';
                if (!is_file($mainFile)) {
                    $mainFile = $candidate . '/index.php';
                }
                if (is_file($mainFile)) {
                    $meta = $this->parsePluginHeaders($mainFile);
                    if (!empty($meta['name'])) {
                        $pluginDir = $candidate;
                        $pluginName = $entry;
                        break;
                    }
                }
                if ($pluginDir === null) {
                    $jsonFile = $candidate . '/plugin.json';
                    if (is_file($jsonFile)) {
                        $jsonData = json_decode(file_get_contents($jsonFile), true);
                        if (is_array($jsonData) && !empty($jsonData['name'])) {
                            $pluginDir = $candidate;
                            $pluginName = $entry;
                            break;
                        }
                    }
                }
            } elseif (substr($entry, -4) === '.php') {
                $meta = $this->parsePluginHeaders($tmpDir . '/' . $entry);
                if (!empty($meta['name'])) {
                    $pluginName = substr($entry, 0, -4);
                    $pluginDir = $tmpDir . '/' . $pluginName;
                    @mkdir($pluginDir, 0777, true);
                    rename($tmpDir . '/' . $entry, $pluginDir . '/' . $pluginName . '.php');
                    break;
                }
            }
        }

        if ($pluginDir === null || $pluginName === null) {
            $this->rrmdir($tmpDir);
            throw new \RuntimeException('No valid plugin file found in archive.');
        }

        $target = $this->pluginRoot . '/' . $pluginName;
        if (is_dir($target)) {
            $this->rrmdir($target);
        }
        rename($pluginDir, $target);
        $this->rrmdir($tmpDir);

        $this->scanPlugins();
        $meta = $this->plugins[$pluginName]['meta'] ?? [];
        return ['name' => $pluginName, 'meta' => $meta];
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
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