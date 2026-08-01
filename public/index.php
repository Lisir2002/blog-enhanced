<?php

/**
 * 前置控制器 - 所有请求的唯一入口。
 *
 * 服务器配置：
 *   Nginx:  try_files $uri $uri/ /index.php?$query_string;
 *   Apache: 使用 .htaccess（见同目录）
 *   开发:   php -S localhost:8080 -t public public/index.php
 */

declare(strict_types=1);

// 1. Composer 自动加载
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    echo "Composer dependencies not installed. Run: composer install\n";
    echo "Looking for: $autoload\n";
    exit;
}
require $autoload;

// 2. Bootstrap application
$app = new \Core\Application();
$app->bootstrap();

// 3. Dispatch request
$app->run();
