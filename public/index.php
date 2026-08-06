<?php

/**
 * 前置控制器 - 所有请求的唯一入口。
 *
 * 服务器配置：
 *   Nginx:  try_files $uri $uri/ /index.php?$query_string;
 *   Apache: 使用 .htaccess（见同目录）
 *   开发:   php -S localhost:8080 -t public public/index.php
 *
 * 架构说明：
 *   主题文件位于 public/themes/ 下，静态资源（CSS/JS/图片）由 Web 服务器直接响应。
 *   PHP 模板文件受 .htaccess 和 index.php 双重保护，禁止直接 URL 访问。
 */

declare(strict_types=1);

// ── 安全拦截：禁止直接访问主题 PHP 文件 ─────────────────
// 即使 .htaccess 失效（如 PHP 内置服务器），此层仍提供保护。
$uri = $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($uri, PHP_URL_PATH) ?? '';

// 拒绝直接访问 themes/ 下的任何 .php 文件
if (preg_match('#^/themes/.*\.php$#', (string) $path)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo '403 Forbidden';
    exit;
}

// PHP 内置服务器：静态文件直接返回，不经过后续处理
if (php_sapi_name() === 'cli-server') {
    $filePath = __DIR__ . $path;
    if (is_file($filePath)) {
        return false;
    }
}

// ── 引导应用 ──────────────────────────────────────

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
try {
    $app->run();
} catch (\Throwable $e) {
    file_put_contents('/tmp/php_error_debug.log', sprintf(
        "[%s] %s in %s:%d\n%s\n---\n",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    ), FILE_APPEND);
    throw $e;
}