<?php

namespace Core\Cache;

/**
 * 内存数组缓存 - 测试用，请求结束即销毁。
 *
 * 特点：
 * - 零外部依赖，纯内存
 * - 支持全部 CacheInterface 接口
 * - 用于单元测试，避免文件 IO
 */
class ArrayCache implements CacheInterface
{
    /** @var array<string, array{value: mixed, expires_at: int}> */
    private array $store = [];

    /** @var array<string, array<int, string>> 标签 → 键列表 */
    private array $tagMap = [];

    /** @var array<string, int> 计数器 */
    private array $counters = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (!isset($this->store[$key])) {
            return $default;
        }
        $item = $this->store[$key];
        if ($item['expires_at'] !== 0 && $item['expires_at'] < time()) {
            unset($this->store[$key]);
            return $default;
        }
        return $item['value'];
    }

    public function set(string $key, mixed $value, ?int $ttl = 3600): bool
    {
        $this->store[$key] = [
            'value'      => $value,
            'expires_at' => $ttl === 0 ? 0 : time() + $ttl,
        ];
        return true;
    }

    public function forever(string $key, mixed $value): bool
    {
        return $this->set($key, $value, 0);
    }

    public function delete(string $key): bool
    {
        unset($this->store[$key]);
        return true;
    }

    public function clear(): bool
    {
        $this->store = [];
        $this->tagMap = [];
        $this->counters = [];
        return true;
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

    public function tagged(string $key, mixed $value, array $tags, ?int $ttl = 3600): bool
    {
        $result = $this->set($key, $value, $ttl);
        foreach ($tags as $tag) {
            $this->tagMap[$tag][] = $key;
        }
        return $result;
    }

    public function flushTag(string $tag): bool
    {
        $keys = $this->tagMap[$tag] ?? [];
        foreach ($keys as $key) {
            $this->delete($key);
        }
        unset($this->tagMap[$tag]);
        return true;
    }

    public function lock(string $key, int $ttl = 10): CacheLock
    {
        $lock = new CacheLock($key, $ttl);
        $lock->bind($this);
        return $lock;
    }

    public function increment(string $key, int $step = 1): int
    {
        $current = (int) ($this->counters[$key] ?? 0);
        $current += $step;
        $this->counters[$key] = $current;
        return $current;
    }

    public function decrement(string $key, int $step = 1): int
    {
        return $this->increment($key, -$step);
    }

    public function driver(): string
    {
        return 'array';
    }
}
