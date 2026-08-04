<?php

namespace Core\Providers;

use Core\View\ThemeManager;
use Core\View\ViewRenderer;

class ViewProvider extends Provider
{
    public function register(): void
    {
        $this->app->singleton(ViewRenderer::class);
        $this->app->singleton(ThemeManager::class);
    }
}
