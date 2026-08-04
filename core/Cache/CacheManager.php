<?php

namespace Core\Cache;

/**
 * 缓存管理器 - 多驱动支持。
 *
 * 支持驱动：
 * - file（默认）：FileCache
 * - redis：RedisCache（需 predis/predis 或 phpredis 扩展）
 * - memcached：MemcachedCache（需 memcached 扩展）
 * - apcu：ApcuCache（需 apcu 扩展）
 * - array：ArrayCache（内存数组，测试用）
 *
 * 用法：
 *   $cache = app(CacheManager::class);
 *   $cache->driver('redis')->set('key', 'value');
 *   $cache->driver()->get('key');  // 默认驱动
 */
class CacheManager
{
    /** @var array<string, CacheInterface> 已实例化的驱动 */
    private array $drivers = [];

    /** @var string 默认驱动名 */
    private string $defaultDriver;

    /** @var array<string, array> 驱动配置 */
    private array $config;

    public function __construct()
    {
        $this->config = config('cache.drivers', []);
        $this->defaultDriver = (string) config('cache.default', 'file');
    }

    /**
     * 获取指定驱动，未指定则用默认驱动。
     */
    public function driver(?string $name = null): CacheInterface
    {
        $name = $name ?? $this->defaultDriver;
        if (isset($this->drivers[$name])) {
            return $this->drivers[$name];
        }
        return $this->drivers[$name] = $this->createDriver($name);
    }

    /**
     * 创建驱动实例。
     */
    private function createDriver(string $name): CacheInterface
    {
        $driverConfig = $this->config[$name] ?? [];
        $type = $driverConfig['type'] ?? $name;

        return match ($type) {
            'file'       => new FileCache(),
            'array'      => new ArrayCache(),
            'redis'      => $this->createRedisDriver($driverConfig),
            'memcached'  => $this->createMemcachedDriver($driverConfig),
            'apcu'       => new ApcuCache(),
            default      => throw new \RuntimeException("Unknown cache driver: {$type}"),
        };
    }

    /**
     * 创建 Redis 驱动（需 predis/predis 或 phpredis 扩展）。
     */
    private function createRedisDriver(array $config): CacheInterface
    {
        if (!class_exists('\\Predis\\Client') && !extension_loaded('redis')) {
            // 降级为文件缓存
            return new FileCache();
        }
        return new RedisCache($config);
    }

    /**
     * 创建 Memcached 驱动。
     */
    private function createMemcachedDriver(array $config): CacheInterface
    {
        if (!class_exists('\\Memcached')) {
            return new FileCache();
        }
        return new MemcachedCache($config);
    }

    /**
     * 获取所有已加载的驱动名。
     */
    public function getLoadedDrivers(): array
    {
        return array_keys($this->drivers);
    }

    /**
     * 代理默认驱动的方法调用。
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->driver()->get($key, $default);
    }

    public function set(string $key, mixed $value, ?int $ttl = 3600): bool
    {
        return $this->driver()->set($key, $value, $ttl);
    }

    public function delete(string $key): bool
    {
        return $this->driver()->delete($key);
    }

    public function has(string $key): bool
    {
        return $this->driver()->has($key);
    }

    public function remember(string $key, callable $callback, ?int $ttl = 3600): mixed
    {
        return $this->driver()->remember($key, $callback, $ttl);
    }

    public function lock(string $key, int $ttl = 10): CacheLock
    {
        $lock = new CacheLock($key, $ttl);
        $lock->bind($this->driver());
        return $lock;
    }
}
