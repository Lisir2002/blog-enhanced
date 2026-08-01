<?php

return [
    'driver' => env('DB_DRIVER', 'sqlite'),
    'path' => env('DB_PATH', 'database.sqlite'),
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => (int) env('DB_PORT', 3306),
    'name' => env('DB_NAME', 'blog'),
    'user' => env('DB_USER', 'root'),
    'pass' => env('DB_PASS', ''),
    'prefix' => env('DB_PREFIX', ''),
];
