<?php

namespace Core\Providers;

use Core\Plugin\PluginManager;

class PluginProvider extends Provider
{
    public function register(): void
    {
        $this->app->singleton(PluginManager::class);
    }

    public function boot(): void
    {
        $this->app->get(PluginManager::class)->boot();
    }
}
