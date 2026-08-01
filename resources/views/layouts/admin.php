<?php
/**
 * Admin layout
 * @var string $pageTitle
 * @var string $content (extracted)
 */
$user = current_user();
$siteName = \App\Models\Option::get('site_name', config('app.name'));
$currentPage = app(\Core\Http\Request::class)->path();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - 后台管理</title>
    <link rel="stylesheet" href="<?= asset('admin/admin.css') ?>">
    <?php do_action('admin_head') ?>
</head>
<body class="admin-body">
<div class="admin-wrapper">
    <aside class="admin-sidebar">
        <div class="admin-brand">
            <a href="<?= route('admin') ?>">⚡ <?= e($siteName) ?></a>
        </div>
        <nav class="admin-nav">
            <a href="<?= route('admin') ?>" class="nav-item <?= $currentPage === '/admin' ? 'active' : '' ?>">📊 仪表盘</a>
            <a href="<?= route('admin.posts.index') ?>" class="nav-item <?= str_starts_with($currentPage, '/admin/posts') ? 'active' : '' ?>">📝 文章</a>
            <a href="<?= route('admin.categories.index') ?>" class="nav-item <?= str_starts_with($currentPage, '/admin/categories') ? 'active' : '' ?>">📁 分类</a>
            <a href="<?= route('admin.tags.index') ?>" class="nav-item <?= str_starts_with($currentPage, '/admin/tags') ? 'active' : '' ?>">🏷️ 标签</a>
            <a href="<?= route('admin.media.index') ?>" class="nav-item <?= str_starts_with($currentPage, '/admin/media') ? 'active' : '' ?>">🖼️ 媒体</a>
            <a href="<?= route('admin.comments.index') ?>" class="nav-item <?= str_starts_with($currentPage, '/admin/comments') ? 'active' : '' ?>">💬 评论</a>
            <?php if ($user && $user->getAttribute('role') === 'admin'): ?>
            <div class="nav-section-divider"></div>
            <a href="<?= route('admin.users.index') ?>" class="nav-item <?= str_starts_with($currentPage, '/admin/users') ? 'active' : '' ?>">👥 用户</a>
            <a href="<?= route('admin.themes.index') ?>" class="nav-item <?= str_starts_with($currentPage, '/admin/themes') ? 'active' : '' ?>">🎨 主题</a>
            <a href="<?= route('admin.plugins.index') ?>" class="nav-item <?= str_starts_with($currentPage, '/admin/plugins') ? 'active' : '' ?>">🔌 插件</a>
            <a href="<?= route('admin.settings.index') ?>" class="nav-item <?= str_starts_with($currentPage, '/admin/settings') ? 'active' : '' ?>">⚙️ 设置</a>
            <?php endif; ?>
        </nav>
        <div class="admin-sidebar-footer">
            <a href="<?= url('/') ?>" target="_blank">🌐 查看站点</a>
            <a href="<?= route('logout') ?>">🚪 退出</a>
        </div>
    </aside>
    <main class="admin-main">
        <header class="admin-header">
            <h1 class="admin-title"><?= e($pageTitle) ?></h1>
            <div class="admin-user">
                <img src="<?= $user ? $user->avatarUrl(32) : '' ?>" alt="" class="avatar">
                <span><?= e($user ? $user->displayName() : '') ?></span>
            </div>
        </header>
        <div class="admin-content">
            <?php
            $flash = app(\Core\Http\Session::class);
            $success = $flash->pull('success');
            $error = $flash->pull('error');
            ?>
            <?php if ($success): ?>
                <div class="flash flash-success"><?= e($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="flash flash-error"><?= e($error) ?></div>
            <?php endif; ?>
            <?= $content ?? '' ?>
        </div>
    </main>
</div>
<script src="<?= asset('admin/admin.js') ?>"></script>
<?php do_action('admin_footer') ?>
</body>
</html>
