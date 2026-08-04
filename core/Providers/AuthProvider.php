<?php

namespace Core\Providers;

use Core\Auth\AuthManager;

class AuthProvider extends Provider
{
    public function register(): void
    {
        $this->app->singleton(AuthManager::class);
    }
}
