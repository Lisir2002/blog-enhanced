<?php
/**
 * 测试引导文件 - 加载项目 autoload + 测试基类 + 初始化测试环境变量。
 */
require __DIR__ . '/../vendor/autoload.php';

// 手动加载测试基类（autoload-dev 未生效，因 phpunit.phar 独立运行）
require_once __DIR__ . '/TestCase.php';

// 测试环境 env
putenv('APP_ENV=testing');
putenv('APP_DEBUG=true');
putenv('DB_DRIVER=sqlite');
putenv('DB_PATH=:memory:');
putenv('APP_URL=http://localhost');

// 备用：确保 helpers 函数已加载（composer autoload 已注册，但 phpunit.phar 独立运行时可能未触发 files autoload）
$helperFiles = [
    'helpers.php',
    'helpers_http.php',
    'helpers_auth.php',
    'helpers_hook.php',
    'helpers_theme.php',
];
foreach ($helperFiles as $file) {
    if (!function_exists('app') || ($file === 'helpers_http.php' && !function_exists('url'))) {
        require __DIR__ . '/../core/Support/' . $file;
    }
}
