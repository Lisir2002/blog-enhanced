#!/usr/bin/env php
<?php

/**
 * 安装脚本：初始化数据库 + 创建管理员
 *
 * 用法：
 *   php bin/install          # 交互式创建管理员
 *   php bin/install --non-interactive  # 用环境变量默认值
 */

if (php_sapi_name() !== 'cli') {
    echo "This script can only be run from CLI.\n";
    exit(1);
}

define('BLOG_ROOT', dirname(__DIR__));

// autoload
require BLOG_ROOT . '/vendor/autoload.php';

if (!is_file(BLOG_ROOT . '/.env')) {
    copy(BLOG_ROOT . '/.env.example', BLOG_ROOT . '/.env');
    echo "✓ Created .env from .env.example\n";
    echo "  Edit .env to change database settings.\n";
}

// Bootstrap
$app = new \Core\Application();
$app->bootstrap();

$conn = $app->get(\Core\Database\Connection::class);

// 1. Load schema
if ($conn->isSqlite()) {
    $dbPath = database_path(config('database.path', 'database/database.sqlite'));
    if (!is_file($dbPath)) {
        touch($dbPath);
        chmod($dbPath, 0666);
    }
    $schema = file_get_contents(database_path('schema.sqlite.sql'));
} else {
    $schema = file_get_contents(database_path('schema.mysql.sql'));
}

echo "→ Running schema...\n";
// Split by semicolons (handle both drivers)
$schema = preg_replace('/--.*$/m', '', $schema);
$statements = array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $schema)));
$pdo = $conn->pdo();
$count = 0;
foreach ($statements as $sql) {
    if ($sql === '' || $sql[0] === '/' || str_starts_with($sql, '/*')) continue;
    try {
        $pdo->exec($sql);
        $count++;
    } catch (\Throwable $e) {
        echo "  ⚠ " . substr($sql, 0, 80) . " — " . $e->getMessage() . "\n";
    }
}
echo "✓ Schema applied ({$count} statements)\n";

// 2. Create admin user
$adminEmail = getenv('ADMIN_EMAIL') ?: '';
$adminUser = getenv('ADMIN_USERNAME') ?: 'admin';
$adminPass = getenv('ADMIN_PASSWORD') ?: '';

// Non-interactive mode?
$nonInteractive = in_array('--non-interactive', $argv ?? [], true);

if (!$nonInteractive) {
    echo "\n--- 创建管理员账号 ---\n";
    if (function_exists('readline')) {
        if (empty($adminUser)) $adminUser = readline("用户名 [admin]: ");
        if ($adminUser === '') $adminUser = 'admin';
        if (empty($adminEmail)) $adminEmail = readline("邮箱: ");
        while (empty($adminPass)) {
            $adminPass = system('stty -echo');
            echo "密码: ";
            $adminPass = trim(fgets(STDIN));
            system('stty echo');
            echo "\n";
            if (strlen($adminPass) < 6) {
                echo "密码至少6位\n";
                $adminPass = '';
            }
        }
    } else {
        // Fallback to defaults
        $adminUser = 'admin';
        $adminEmail = $adminEmail ?: 'admin@example.com';
        $adminPass = $adminPass ?: 'admin';
    }
} else {
    if (empty($adminEmail)) $adminEmail = 'admin@example.com';
    if (empty($adminPass)) $adminPass = 'admin';
}

// Check if admin exists
$existing = \App\Models\User::query()->where('username', '=', $adminUser)->first();
if ($existing) {
    echo "→ 管理员 [$adminUser] 已存在，跳过创建\n";
} else {
    \App\Models\User::query()->insert([
        'username'     => $adminUser,
        'email'        => $adminEmail,
        'password'     => password_hash($adminPass, PASSWORD_BCRYPT),
        'display_name' => '管理员',
        'role'         => 'super_admin',
        'status'       => 'active',
        'bio'          => '',
        'created_at'   => date('Y-m-d H:i:s'),
        'updated_at'   => date('Y-m-d H:i:s'),
    ]);
    echo "✓ 管理员已创建: $adminUser <$adminEmail>\n";
}

// 3. Seed default options
$defaults = [
    'site_name'        => 'My Blog',
    'site_description' => '又一个用 Blog CMS 搭建的博客',
    'site_keywords'    => '博客,PHP,Blog CMS',
    'posts_per_page'   => '10',
    'active_theme'     => 'default',
    'moderate_comments' => '1',
    'allow_registration' => '0',
    'footer_text'      => '© ' . date('Y') . ' My Blog. Powered by Blog CMS.',
];
foreach ($defaults as $k => $v) {
    \App\Models\Option::set($k, $v);
}
echo "✓ 默认设置已写入\n";

// 4. Seed default category
$cat = \App\Models\Category::query()->where('slug', '=', 'uncategorized')->first();
if (!$cat) {
    \App\Models\Category::query()->insert([
        'name'        => '未分类',
        'slug'        => 'uncategorized',
        'description' => '默认分类',
        'parent_id'   => 0,
        'created_at'  => date('Y-m-d H:i:s'),
        'updated_at'  => date('Y-m-d H:i:s'),
    ]);
    echo "✓ 默认分类已创建\n";
}

// 5. Create sample post
$post = \App\Models\Post::query()->where('slug', '=', 'hello-world')->first();
if (!$post) {
    $catId = \App\Models\Category::query()->where('slug', '=', 'uncategorized')->first()['id'] ?? 1;
    \App\Models\Post::query()->insert([
        'slug'         => 'hello-world',
        'title'        => '欢迎来到 Blog CMS',
        'content_md'   => "# 欢迎使用 Blog CMS\n\n这是一个**多作者博客 CMS**，基于原生 PHP 构建。\n\n## 主要特性\n\n- 纯原生 PHP + Composer 组件\n- 类 WordPress 主题/插件机制\n- SQLite / MySQL 双驱动\n- Markdown 编辑\n- 多角色权限\n\n## 下一步\n\n1. 登录后台 `/admin`\n2. 写第一篇文章\n3. 上传主题或插件\n\n祝你写作愉快！",
        'excerpt'      => '欢迎使用 Blog CMS —— 多作者博客内容管理系统',
        'category_id'  => $catId,
        'author_id'    => \App\Models\User::query()->where('username', '=', $adminUser)->first()['id'] ?? 1,
        'status'       => 'published',
        'published_at' => date('Y-m-d H:i:s'),
        'views'        => 0,
        'seo_title'    => '欢迎来到 Blog CMS',
        'created_at'   => date('Y-m-d H:i:s'),
        'updated_at'   => date('Y-m-d H:i:s'),
    ]);
    echo "✓ 示例文章已创建\n";
}

echo "\n====================================\n";
echo "  ✓ 安装完成！\n";
echo "====================================\n";
echo "  后台地址: " . config('app.url') . "/admin\n";
echo "  管理员:   $adminUser\n";
echo "  前台:     " . config('app.url') . "\n\n";
