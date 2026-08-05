<?php
/**
 * Admin layout - independent design system
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

    <!-- ===== Sidebar ===== -->
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="sidebar-header">
            <div class="sidebar-brand">
                <div class="brand-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                </div>
                <a href="<?= route('admin.dashboard') ?>"><?= e($siteName) ?></a>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section-label">管理</div>
            <?php if (can('read') || can('dashboard')): ?>
            <a href="<?= route('admin.dashboard') ?>" class="nav-item <?= $currentPage === '/admin' ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                仪表盘
            </a>
            <?php endif; ?>
            <?php if (can('edit_posts')): ?>
            <a href="<?= route('admin.posts.index') ?>" class="nav-item <?= str_starts_with($currentPage, '/admin/posts') ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                文章
            </a>
            <?php endif; ?>
            <?php if (can('manage_categories')): ?>
            <a href="<?= route('admin.categories.index') ?>" class="nav-item <?= str_starts_with($currentPage, '/admin/categories') ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                分类
            </a>
            <a href="<?= route('admin.tags.index') ?>" class="nav-item <?= str_starts_with($currentPage, '/admin/tags') ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                标签
            </a>
            <?php endif; ?>
            <?php if (can('upload_media')): ?>
            <a href="<?= route('admin.media.index') ?>" class="nav-item <?= str_starts_with($currentPage, '/admin/media') ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                媒体
            </a>
            <?php endif; ?>
            <?php if (can('moderate_comments')): ?>
            <a href="<?= route('admin.comments.index') ?>" class="nav-item <?= str_starts_with($currentPage, '/admin/comments') ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                评论
            </a>
            <?php endif; ?>

            <?php if (can('manage_options') || can('manage_users') || can('switch_themes') || can('activate_plugins')): ?>
            <div class="nav-section-label">系统</div>
            <?php if (can('manage_users')): ?>
            <a href="<?= route('admin.users.index') ?>" class="nav-item <?= str_starts_with($currentPage, '/admin/users') ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                用户
            </a>
            <?php endif; ?>
            <?php if (can('switch_themes')): ?>
            <a href="<?= route('admin.themes.index') ?>" class="nav-item <?= str_starts_with($currentPage, '/admin/themes') ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19l7-7 3 3-7 7-3-3z"/><path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"/><path d="M2 2l7.586 7.586"/><circle cx="11" cy="11" r="2"/></svg>
                主题
            </a>
            <?php endif; ?>
            <?php if (can('activate_plugins')): ?>
            <a href="<?= route('admin.plugins.index') ?>" class="nav-item <?= str_starts_with($currentPage, '/admin/plugins') ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="8" height="8"/><rect x="14" y="2" width="8" height="8"/><rect x="14" y="14" width="8" height="8"/><rect x="2" y="14" width="8" height="8"/></svg>
                插件
            </a>
            <?php endif; ?>
            <?php if (can('manage_options')): ?>
            <a href="<?= route('admin.settings.index') ?>" class="nav-item <?= str_starts_with($currentPage, '/admin/settings') ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                设置
            </a>
            <?php endif; ?>
            <?php endif; ?>
        </nav>

        <div class="sidebar-footer">
            <a href="<?= url('/') ?>" target="_blank" class="nav-item">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                查看站点
            </a>
            <a href="<?= route('admin.logout') ?>" class="nav-item">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                退出
            </a>
        </div>
    </aside>

    <!-- ===== Main Content ===== -->
    <main class="admin-main">
        <header class="admin-header">
            <div class="header-left">
                <button class="sidebar-toggle" id="sidebarToggle" aria-label="切换侧栏">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
                <h1 class="admin-title"><?= e($pageTitle) ?></h1>
            </div>
            <div class="header-right">
                <div class="admin-user">
                    <img src="<?= $user ? $user->avatarUrl(32) : '' ?>" alt="" class="avatar" loading="lazy">
                    <span><?= e($user ? $user->displayName() : '') ?></span>
                </div>
            </div>
        </header>

        <div class="admin-content">
            <?php
            $flash = app(\Core\Http\Session::class);
            $success = $flash->pull('success');
            $error = $flash->pull('error');
            ?>
            <?php if ($success): ?>
                <div class="flash flash-success">
                    <svg class="flash-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    <?= e($success) ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="flash flash-error">
                    <svg class="flash-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    <?= e($error) ?>
                </div>
            <?php endif; ?>
            <?= $content ?? '' ?>
        </div>
    </main>
</div>

<script src="<?= asset('admin/admin.js') ?>?v=<?= time() ?>"></script>
<?php do_action('admin_footer') ?>
</body>
</html>