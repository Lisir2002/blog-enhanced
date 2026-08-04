<?php

namespace Core\Providers;

use Core\Cache\ArrayCache;
use Core\Cache\CacheInterface;
use Core\Cache\CacheManager;
use Core\Cache\FileCache;
use Core\Cache\RedisCache;

class CacheProvider extends Provider
{
    public function register(): void
    {
        // 文件缓存（默认驱动）
        $this->app->singleton(FileCache::class);

        // 内存数组缓存（测试用）
        $this->app->singleton(ArrayCache::class);

        // Redis 缓存（当扩展可用时注册）
        if (class_exists('\Redis') || class_exists('\Predis\Client')) {
            $this->app->singleton(RedisCache::class);
        }

        // 多驱动缓存管理器
        $this->app->singleton(CacheManager::class);

        // 默认 CacheInterface 绑定到默认驱动
        $this->app->singleton(CacheInterface::class, function () {
            return $this->app->get(CacheManager::class)->driver();
        });
    }
}
