<?php

/**
 * 路由列表命令 — 列出所有已注册路由。
 *
 * 用法：
 *   php scripts/list-routes.php
 *   php scripts/list-routes.php --admin     # 只显示 admin 路由
 *   php scripts/list-routes.php --method=POST  # 只显示 POST 路由
 */

require_once __DIR__ . '/../vendor/autoload.php';

$app = new \Core\Application();
$app->bootstrap();

use Core\Router;

$router = $app->get(Router::class);

// 加载所有路由
$router->loadRoutes(base_path('routes/web.php'));
$router->loadRoutes(base_path('routes/admin.php'));

$filters = [
    'admin'   => in_array('--admin', $argv ?? [], true),
    'method'  => null,
];

foreach ($argv ?? [] as $arg) {
    if (preg_match('/--method=(\w+)/', $arg, $m)) {
        $filters['method'] = strtoupper($m[1]);
    }
}

$routes = $router->getRoutes();

// 过滤
$routes = array_filter($routes, function (array $r) use ($filters) {
    if ($filters['admin'] && !str_contains($r['pattern'], '/admin')) {
        return false;
    }
    if ($filters['method'] && $r['method'] !== $filters['method']) {
        return false;
    }
    return true;
});

// 按名称排序
usort($routes, function (array $a, array $b) {
    return ($a['name'] ?? '') <=> ($b['name'] ?? '');
});

// 输出
$cols = ['METHOD', 'PATTERN', 'NAME'];
$lens = [6, 0, 0];

foreach ($routes as $r) {
    $lens[1] = max($lens[1], strlen($r['pattern']));
    $lens[2] = max($lens[2], strlen($r['name'] ?? ''));
}

$lens[1] = max($lens[1], strlen($cols[1]));
$lens[2] = max($lens[2], strlen($cols[2]));

$sep = '+--' . str_repeat('-', $lens[0] + 2) . '-' . str_repeat('-', $lens[1] + 2) . '-' . str_repeat('-', $lens[2] + 2) . '+';

echo $sep . "\n";
printf("| %-{$lens[0]}s | %-{$lens[1]}s | %-{$lens[2]}s |\n", $cols[0], $cols[1], $cols[2]);
echo $sep . "\n";

foreach ($routes as $r) {
    printf(
        "| %-{$lens[0]}s | %-{$lens[1]}s | %-{$lens[2]}s |\n",
        $r['method'],
        $r['pattern'],
        $r['name'] ?? '(未命名)'
    );
}

echo $sep . "\n";
echo "\n共 " . count($routes) . " 条路由\n";
