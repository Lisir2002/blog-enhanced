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

// ── 开发服务器静态资源路由 ──────────────────────────
// PHP 内置服务器在部分环境下不跟随符号链接，导致 public/themes/ 下的
// 主题静态资源（JS/CSS/图片）返回 404。这里手动将请求映射到对应目录。
// 生产环境（Nginx/Apache）直接使用 .htaccess / try_files 规则，不走此路由。
$uri = $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($uri, PHP_URL_PATH) ?? '';

// 通用静态文件服务：检查文件是否存在于 public/ 或 resources/ 目录
$staticDirs = [
    '/assets/' => dirname(__DIR__) . '/public',
    '/themes/' => dirname(__DIR__) . '/resources',
];
$matchedDir = null;
foreach ($staticDirs as $prefix => $dir) {
    if (str_starts_with($path, $prefix)) {
        $matchedDir = $dir;
        break;
    }
}
if ($matchedDir !== null) {
    $filePath = $matchedDir . $path;
    if (is_file($filePath)) {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mimeTypes = [
            'js'   => 'application/javascript',
            'css'  => 'text/css',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'svg'  => 'image/svg+xml',
            'webp' => 'image/webp',
            'ico'  => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2'=> 'font/woff2',
            'ttf'  => 'font/ttf',
            'eot'  => 'application/vnd.ms-fontobject',
        ];
        $mime = $mimeTypes[$ext] ?? 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('X-Content-Type-Options: nosniff');
        readfile($filePath);
        return true;
    }
}

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
