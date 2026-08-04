<?php

namespace Core\Cache;

/**
 * Redis 缓存驱动 - 需 predis/predis 或 phpredis 扩展。
 *
 * 特点：
 * - 高性能，适合大规模生产环境
 * - 原子性操作（SETNX 实现锁）
 * - 支持 TTL 自动过期
 * - 标签通过 SET 集合实现
 */
class RedisCache implements CacheInterface
{
    /** @var \Predis\Client|\Redis|null */
    private $client;

    /** @var array<string, array> 标签映射（本地缓存，减少网络请求） */
    private array $tagMap = [];

    public function __construct(array $config = [])
    {
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 6379;
        $password = $config['password'] ?? null;
        $database = $config['database'] ?? 0;

        if (extension_loaded('redis')) {
            $this->client = new \Redis();
            $this->client->connect($host, (int) $port);
            if ($password) {
                $this->client->auth($password);
            }
            $this->client->select((int) $database);
        } elseif (class_exists('\\Predis\\Client')) {
            $params = [
                'scheme' => 'tcp',
                'host'   => $host,
                'port'   => $port,
            ];
            if ($password) {
                $params['password'] = $password;
            }
            if ($database) {
                $params['database'] = $database;
            }
            $this->client = new \Predis\Client($params);
        } else {
            throw new \RuntimeException('Redis extension or predis package not installed.');
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->client->get($key);
        if ($value === false || $value === null) {
            return $default;
        }
        return unserialize($value);
    }

    public function set(string $key, mixed $value, ?int $ttl = 3600): bool
    {
        $serialized = serialize($value);
        if ($ttl === 0) {
            return (bool) $this->client->set($key, $serialized);
        }
        return (bool) $this->client->setex($key, $ttl, $serialized);
    }

    public function forever(string $key, mixed $value): bool
    {
        return $this->set($key, $value, 0);
    }

    public function delete(string $key): bool
    {
        return (bool) $this->client->del($key);
    }

    public function clear(): bool
    {
        return (bool) $this->client->flushdb();
    }

    public function has(string $key): bool
    {
        return (bool) $this->client->exists($key);
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
            $this->client->sAdd('tag:' . $tag, $key);
            if ($ttl > 0) {
                $this->client->expire('tag:' . $tag, $ttl);
            }
        }
        return $result;
    }

    public function flushTag(string $tag): bool
    {
        $tagKey = 'tag:' . $tag;
        $keys = $this->client->sMembers($tagKey);
        if (!empty($keys)) {
            foreach ($keys as $key) {
                $this->delete($key);
            }
        }
        $this->client->del($tagKey);
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
        $result = $this->client->incrBy($key, $step);
        return (int) $result;
    }

    public function decrement(string $key, int $step = 1): int
    {
        return $this->increment($key, -$step);
    }

    public function driver(): string
    {
        return 'redis';
    }
}
