<?php

namespace Core\Plugin;

/**
 * 插件管理器 - WordPress 风格。
 *
 * - 插件目录 plugins/{name}/
 * - 主插件文件 plugins/{name}/{name}.php 头部含 Plugin Name 等元数据
 * - activate / deactivate / uninstall 钩子
 * - 已激活的插件在 boot 时加载
 */
class PluginManager
{
    private string $pluginRoot;

    /** @var array<string, array{file: string, meta: array}> */
    private array $plugins = [];

    /** @var array<string, true> */
    private array $active = [];

    /** @var array<string, true> */
    private array $loaded = [];

    private bool $booted = false;

    public function __construct()
    {
        $this->pluginRoot = plugins_path();
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;

        $this->scanPlugins();
        $this->loadActiveList();

        foreach (array_keys($this->active) as $name) {
            if (isset($this->plugins[$name])) {
                $this->loadPlugin($name);
            }
        }

        do_action('plugins_loaded');
    }

    private function scanPlugins(): void
    {
        if (!is_dir($this->pluginRoot)) {
            return;
        }
        foreach ((array) glob($this->pluginRoot . '/*', GLOB_ONLYDIR) as $dir) {
            $name = basename($dir);
            $mainFile = $dir . '/' . $name . '.php';
            if (!is_file($mainFile)) {
                // Fallback: index.php
                $mainFile = $dir . '/index.php';
                if (!is_file($mainFile)) {
                    continue;
                }
            }
            $meta = $this->parsePluginHeaders($mainFile);
            $this->plugins[$name] = [
                'file' => $mainFile,
                'meta' => $meta,
            ];
        }
    }

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
            // DB not ready (install state)
        }
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

    /**
     * 列出所有插件（含元数据 + 是否激活）。
     */
    public function listPlugins(): array
    {
        $list = [];
        foreach ($this->plugins as $name => $info) {
            $list[$name] = [
                'name' => $name,
                'meta' => $info['meta'],
                'active' => $this->isActive($name),
            ];
        }
        ksort($list);
        return $list;
    }

    public function isActive(string $name): bool
    {
        return isset($this->active[$name]);
    }

    /**
     * Get list of active plugin names.
     */
    public function getActiveList(): array
    {
        return $this->active;
    }

    public function activate(string $name): void
    {
        if (!isset($this->plugins[$name])) {
            throw new \RuntimeException("Plugin [$name] not found.");
        }
        if ($this->isActive($name)) {
            return;
        }
        // Load plugin before activation hook so its code can register hooks.
        $this->loadPlugin($name);
        $this->active[$name] = true;
        $this->persistActiveList();

        // Fire activation hook
        do_action("activate_{$name}", $name);
        if (function_exists('register_deactivation_hook')) {
            // Not implemented here — would register callbacks in main file.
        }
    }

    public function deactivate(string $name): void
    {
        if (!isset($this->plugins[$name])) {
            return;
        }
        do_action("deactivate_{$name}", $name);
        unset($this->active[$name]);
        $this->persistActiveList();
    }

    public function uninstall(string $name): void
    {
        $this->deactivate($name);
        // Fire uninstall hook — plugins should clean up their data
        do_action("uninstall_{$name}", $name);

        // Remove directory
        $dir = $this->pluginRoot . '/' . $name;
        if (is_dir($dir)) {
            $this->rrmdir($dir);
        }
    }

    private function loadPlugin(string $name): void
    {
        if (isset($this->loaded[$name])) {
            return;
        }
        $this->loaded[$name] = true;
        $file = $this->plugins[$name]['file'] ?? null;
        if ($file && is_file($file)) {
            require $file;
        }
    }

    private function persistActiveList(): void
    {
        $names = array_keys($this->active);
        $json = json_encode(array_values($names));
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
    }

    public function installFromZip(string $zipPath): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('ZipArchive extension required for plugin upload.');
        }
        $zip = new \ZipArchive();
        if (($code = $zip->open($zipPath)) !== true) {
            throw new \RuntimeException("Cannot open zip: error code $code");
        }
        $tmpDir = $this->pluginRoot . '/.upload-' . substr(md5((string) microtime(true)), 0, 8);
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0777, true);
        }
        $zip->extractTo($tmpDir);
        $zip->close();

        // Find plugin root: a folder containing a PHP file with "Plugin Name" header
        $pluginDir = null;
        $pluginName = null;
        $entries = array_diff(scandir($tmpDir), ['.', '..']);
        foreach ($entries as $entry) {
            $candidate = $tmpDir . '/' . $entry;
            if (is_dir($candidate)) {
                // Check {entry}/{entry}.php or index.php
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
            } elseif (substr($entry, -4) === '.php') {
                // Maybe single-file plugin at root
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

        // Re-scan
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
