<?php

namespace Core\Cache;

/**
 * 简易文件缓存，提供 PSR-16 风格 API。
 *
 * 实现完整的 CacheInterface：get / set / forever / delete / clear / has / remember
 *   tagged / flushTag / lock / increment / decrement / driver。
 */
class FileCache implements CacheInterface
{
    private string $dir;

    /** @var array<string, int> 计数器持久化缓存 */
    private array $counters = [];

    /** @var bool 是否已加载计数器 */
    private bool $countersLoaded = false;

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

    public function forever(string $key, mixed $value): bool
    {
        return $this->set($key, $value, 0);
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

    /* ═══════════════ Tags (持久化 tag ═══════════════ */

    /**
     * 带标签写入：key-value 写入后，把 key 附加到每个 tag 文件中。
     */
    public function tagged(string $key, mixed $value, array $tags, ?int $ttl = 3600): bool
    {
        $ok = $this->set($key, $value, $ttl);
        if (!$ok) {
            return false;
        }
        foreach ($tags as $tag) {
            $tagKey = '__tag__' . $tag;
            $existing = $this->get($tagKey);
            $keys = is_array($existing) ? $existing : [];
            if (!in_array($key, $keys, true)) {
                $keys[] = $key;
                $this->set($tagKey, $keys, 0);
            }
        }
        return true;
    }

    public function flushTag(string $tag): bool
    {
        $tagKey = '__tag__' . $tag;
        $existing = $this->get($tagKey);
        $keys = is_array($existing) ? $existing : [];
        foreach ($keys as $k) {
            $this->delete($k);
        }
        $this->delete($tagKey);
        return true;
    }

    /* ═══════════════ Lock ═══════════════ */

    public function lock(string $key, int $ttl = 10): CacheLock
    {
        $lock = new CacheLock($key, $ttl);
        $lock->bind($this);
        return $lock;
    }

    /* ═══════════════ 计数器 (持久化) ═══════════════ */

    private function counterPath(): string
    {
        return $this->dir . '/__counters.dat';
    }

    private function loadCounters(): void
    {
        if ($this->countersLoaded) {
            return;
        }
        $this->countersLoaded = true;
        $path = $this->counterPath();
        if (!is_file($path)) {
            return;
        }
        $raw = @unserialize((string) file_get_contents($path));
        $this->counters = is_array($raw) ? $raw : [];
    }

    private function saveCounters(): void
    {
        $dir = dirname($this->counterPath());
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        file_put_contents($this->counterPath(), serialize($this->counters), LOCK_EX);
    }

    public function increment(string $key, int $step = 1): int
    {
        $this->loadCounters();
        $this->counters[$key] = (int) ($this->counters[$key] ?? 0) + $step;
        $this->saveCounters();
        return $this->counters[$key];
    }

    public function decrement(string $key, int $step = 1): int
    {
        return $this->increment($key, -$step);
    }

    public function driver(): string
    {
        return 'file';
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
