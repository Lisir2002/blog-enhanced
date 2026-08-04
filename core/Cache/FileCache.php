<?php

namespace Core\Cache;

/**
 * 简易文件缓存，提供 PSR-16 风格 API。
 */
class FileCache implements CacheInterface
{
    private string $dir;

    public function __construct()
    {
        $this->dir = storage_path('cache');
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0777, true);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $path = $this->path($key);
        if (!is_file($path)) {
            return $default;
        }
        $content = (string) file_get_contents($path);
        $data = @unserialize($content);
        if (!is_array($data) || !isset($data['expires_at'])) {
            @unlink($path);
            return $default;
        }
        if ($data['expires_at'] !== 0 && $data['expires_at'] < time()) {
            @unlink($path);
            return $default;
        }
        return $data['value'];
    }

    public function set(string $key, mixed $value, ?int $ttl = 3600): bool
    {
        $data = [
            'expires_at' => $ttl === 0 ? 0 : time() + $ttl,
            'value' => $value,
        ];
        $path = $this->path($key);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return (bool) file_put_contents($path, serialize($data), LOCK_EX);
    }

    public function delete(string $key): bool
    {
        $path = $this->path($key);
        if (is_file($path)) {
            return @unlink($path);
        }
        return true;
    }

    public function clear(): bool
    {
        $this->rrmdir($this->dir, false);
        return true;
    }

    /**
     * 别名 clear()。
     */
    public function flush(): bool
    {
        return $this->clear();
    }

    public function has(string $key): bool
    {
        return $this->get($key, $this) !== $this;
    }

    public function remember(string $key, callable $callback, ?int $ttl = 3600): mixed
    {
        $value = $this->get($key, $this);
        if ($value === $this) {
            $value = $callback();
            $this->set($key, $value, $ttl);
        }
        return $value;
    }

    private function path(string $key): string
    {
        $key = preg_replace('/[^a-z0-9_\-\.]/i', '_', $key);
        $prefix = substr((string) $key, 0, 2);
        return $this->dir . '/' . $prefix . '/' . $key;
    }

    private function rrmdir(string $dir, bool $removeSelf = true): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (array_diff(scandir($dir), ['.', '..']) as $f) {
            $path = "$dir/$f";
            if (is_dir($path)) {
                $this->rrmdir($path, true);
            } else {
                @unlink($path);
            }
        }
        if ($removeSelf) {
            @rmdir($dir);
        }
    }
}
