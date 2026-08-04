<?php

return [
    // 默认缓存驱动
    'default' => env('CACHE_DRIVER', 'file'),

    // 页面缓存 TTL（秒）
    'page_ttl' => env('PAGE_CACHE_TTL', 3600),

    // 缓存驱动配置
    'drivers' => [
        'file' => [
            'type' => 'file',
            'path' => storage_path('cache'),
        ],
        'array' => [
            'type' => 'array',
        ],
        'redis' => [
            'type'     => 'redis',
            'host'      => env('REDIS_HOST', '127.0.0.1'),
            'port'      => env('REDIS_PORT', 6379),
            'password'  => env('REDIS_PASSWORD', null),
            'database'  => env('REDIS_DATABASE', 0),
        ],
        'memcached' => [
            'type' => 'memcached',
            'host' => env('MEMCACHED_HOST', '127.0.0.1'),
            'port' => env('MEMCACHED_PORT', 11211),
        ],
        'apcu' => [
            'type' => 'apcu',
        ],
    ],
];
