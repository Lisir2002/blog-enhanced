<?php

return [
    'name' => env('APP_NAME', 'Blog CMS'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => rtrim((string) env('APP_URL', 'http://localhost'), '/'),
    'key' => env('APP_KEY', ''),
    'theme' => env('ACTIVE_THEME', 'default'),
    'locale' => 'zh-CN',
    'timezone' => 'Asia/Shanghai',
    'per_page' => 10,
];
