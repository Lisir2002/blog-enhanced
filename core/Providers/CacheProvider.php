<?php

namespace Core\Providers;

use Core\Cache\CacheInterface;
use Core\Cache\FileCache;

class CacheProvider extends Provider
{
    public function register(): void
    {
        $this->app->singleton(FileCache::class);
        $this->app->singleton(CacheInterface::class, fn() => $this->app->get(FileCache::class));
    }
}
