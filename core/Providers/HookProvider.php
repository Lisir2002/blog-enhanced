<?php

namespace Core\Providers;

use Core\Hook\Action;
use Core\Hook\Filter;

class HookProvider extends Provider
{
    public function register(): void
    {
        $this->app->singleton(Action::class);
        $this->app->singleton(Filter::class);
    }
}
