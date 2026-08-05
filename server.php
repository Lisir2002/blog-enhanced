<?php

/**
 * PHP 内置开发服务器路由脚本
 *
 * 用法: php -S 0.0.0.0:8000 server.php
 */

$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);

// 将请求映射到 public 目录
$publicPath = __DIR__ . '/public' . $path;

// 如果静态文件存在，直接返回
if (is_file($publicPath)) {
    return false;
}

// 将所有其他请求交由 public/index.php 处理
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/public/index.php';
require __DIR__ . '/public/index.php';