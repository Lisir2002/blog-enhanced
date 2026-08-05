<?php

/**
 * 路由校验脚本 — 检查所有 route() 调用是否存在对应路由定义。
 *
 * 用法：
 *   php scripts/validate-routes.php
 *   php scripts/validate-routes.php --verbose   # 显示已注册路由列表
 */

require_once __DIR__ . '/../vendor/autoload.php';

$app = new \Core\Application();
$app->bootstrap();

use Core\Router;
use Core\Routing\RouteValidator;

$router = $app->get(Router::class);

// 加载所有路由
$router->loadRoutes(base_path('routes/web.php'));
$router->loadRoutes(base_path('routes/admin.php'));

$validator = new RouteValidator($router);
$result    = $validator->validate();

$verbose = in_array('--verbose', $argv ?? [], true);

echo $validator->report() . "\n";

if ($verbose) {
    echo "\n已注册路由列表:\n";
    echo str_repeat('-', 60) . "\n";
    foreach ($result['defined'] as $name) {
        echo "  - {$name}\n";
    }
}

exit(empty($result['missing']) ? 0 : 1);
