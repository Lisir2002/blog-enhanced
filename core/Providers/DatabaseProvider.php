<?php

namespace Core\Providers;

use Core\Database\Connection;
use Core\Database\QueryBuilder;

class DatabaseProvider extends Provider
{
    public function register(): void
    {
        $this->app->singleton(Connection::class);
        $this->app->singleton(QueryBuilder::class);
    }
}
