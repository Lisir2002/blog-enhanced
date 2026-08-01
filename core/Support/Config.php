<?php

namespace Core\Support;

/**
 * 配置加载器，从 config/*.php 与 .env 读取。
 */
class Config
{
    /** @var array<string, mixed> */
    private array $items = [];

    public function __construct()
    {
        $this->loadEnvironment();
        $this->loadConfigFiles();
    }

    private function loadEnvironment(): void
    {
        $envFile = base_path('.env');
        if (!is_file($envFile)) {
            return;
        }
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
            $k = trim($k);
            $v = trim($v);
            // Strip surrounding quotes
            if (strlen($v) >= 2 && $v[0] === '"' && $v[-1] === '"') {
                $v = substr($v, 1, -1);
            }
            $_ENV[$k] = $v;
            $_SERVER[$k] = $v;
            putenv("$k=$v");
        }
    }

    private function loadConfigFiles(): void
    {
        $dir = config_path();
        if (!is_dir($dir)) {
            return;
        }
        foreach ((array) glob("$dir/*.php") as $file) {
            $key = basename($file, '.php');
            $this->items[$key] = require $file;
        }
    }

    /**
     * Get config value by dotted key.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        // First try .env override for app.* keys
        $envKey = strtoupper(str_replace('.', '_', $key));
        if (isset($_ENV[$envKey])) {
            return $_ENV[$envKey];
        }

        $parts = explode('.', $key);
        $value = $this->items;
        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    public function set(string $key, mixed $value): void
    {
        $parts = explode('.', $key);
        $ref = &$this->items;
        while (count($parts) > 1) {
            $part = array_shift($parts);
            if (!isset($ref[$part]) || !is_array($ref[$part])) {
                $ref[$part] = [];
            }
            $ref = &$ref[$part];
        }
        $ref[array_shift($parts)] = $value;
    }

    public function all(): array
    {
        return $this->items;
    }
}
