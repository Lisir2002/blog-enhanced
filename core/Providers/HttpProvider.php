<?php

namespace Core\Providers;

use Core\Http\Request;
use Core\Http\Session;

class HttpProvider extends Provider
{
    public function register(): void
    {
        $this->app->singleton(Session::class);
        $this->app->singleton(Request::class, fn() => Request::capture());
    }
}
