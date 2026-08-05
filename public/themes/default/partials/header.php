<?php
/**
 * Header partial - 顶栏 v4
 * 布局：Logo（左）→ 弹性留空 → 工具组（搜索 / 头像(点击弹菜单) / ☰导航）
 */
$siteName = \App\Models\Option::get('site_name', config('app.name'));
$logoUrl  = \App\Models\Option::get('logo_url', '');
$debug    = \Core\View\DebugBar::summary();
$roleLabels = [
    'super_admin'   => '超级管理员',
    'senior_admin'  => '高级管理员',
    'editor_admin'  => '编辑管理员',
    'editor_writer' => '编辑写手',
    'visitor'       => '访客',
];
?>
<!DOCTYPE html>
<html lang="zh-CN" data-theme="<?= $_COOKIE['theme'] ?? '' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? $siteName) ?></title>
    <link rel="alternate" type="application/rss+xml" title="<?= e($siteName) ?>" href="<?= url('/feed') ?>">
    <link rel="icon" href="<?= e($logoUrl ?: theme_asset('assets/img/favicon.svg')) ?>">
    <?php wp_head() ?>
</head>
<body class="<?= body_class() ?>">

<header class="blog-header">
    <div class="blog-header__inner">

        <!-- Logo -->
        <a href="<?= url('/') ?>" class="blog-header__brand">
            <?php if ($logoUrl): ?>
                <img src="<?= e($logoUrl) ?>" alt="<?= e($siteName) ?>">
            <?php else: ?>
                <strong><?= e($siteName) ?></strong>
            <?php endif; ?>
        </a>

        <!-- 弹性留空 -->
        <span class="blog-header__spacer"></span>

        <!-- 工具组 -->
        <div class="blog-header__tools">

            <!-- 搜索 -->
            <button class="blog-header__icon" id="searchToggle" aria-label="搜索文章" type="button">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </button>

            <!-- 头像按钮 -->
            <?php if (logged_in()): $user = current_user(); $role = $user->getAttribute('role') ?? 'visitor'; ?>
            <div class="blog-header__avatar-wrap">
                <button class="blog-header__user" id="avatarToggle" aria-label="用户菜单" aria-expanded="false" type="button">
                    <img src="<?= e($user->avatarUrl(32)) ?>" alt="<?= e($user->displayName()) ?>" width="32" height="32">
                </button>
            </div>
            <?php else: ?>
                <a href="<?= url('/login') ?>" class="blog-header__user blog-header__user--guest" title="登录">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </a>
            <?php endif; ?>

            <!-- ☰ 导航 -->
            <button class="blog-header__icon blog-header__toggle" id="navToggle" aria-label="导航菜单" aria-expanded="false" type="button">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>

        </div>
    </div>

    <!-- 搜索面板（下拉） -->
    <div class="blog-search" id="searchPanel">
        <form action="<?= url('/search') ?>" method="get" class="blog-search__form">
            <svg class="blog-search__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="search" name="q" class="blog-search__input" placeholder="搜索文章…" autocomplete="off">
            <button type="button" class="blog-search__close" id="searchClose" aria-label="关闭搜索">Esc</button>
        </form>
        <div class="blog-search__results" id="searchResults"></div>
    </div>
    <?php
    // 内嵌所有已发布文章数据供客户端实时搜索（避免 XHR 请求）
    $searchPosts = \App\Models\Post::query()
        ->where('status', '=', 'published')
        ->where('published_at', '<=', date('Y-m-d H:i:s'))
        ->orderBy('published_at', 'DESC')
        ->select('id', 'title', 'slug', 'excerpt')
        ->get();
    $searchIndex = array_map(function ($r) {
        return [
            'title'   => $r['title'],
            'slug'    => $r['slug'],
            'excerpt' => mb_substr(strip_tags($r['excerpt'] ?? ''), 0, 120),
            'url'     => url('/posts/' . $r['slug']),
        ];
    }, $searchPosts);
    ?>
    <script id="searchIndex" type="application/json"><?= json_encode($searchIndex, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
</header>

<?php if (logged_in()): ?>
<!-- 遮罩层（移动端）— 放在 header 外部，避免 backdrop-filter 创建包含块 -->
<div class="blog-popover__overlay" id="userMenuOverlay"></div>

<!-- 气泡菜单 — 放在 header 外部，使 position:fixed 相对于视口 -->
<div class="blog-popover" id="userMenu" role="dialog" aria-modal="true" aria-label="用户菜单">
    <div class="blog-popover__arrow"></div>

    <!-- 顶部把手（仅移动端可见） -->
    <div class="blog-popover__handle">
        <span class="blog-popover__handle-title">账户</span>
    </div>

    <!-- 可滚动内容区 -->
    <div class="blog-popover__scroll">
        <!-- 用户信息 -->
        <div class="blog-popover__user">
            <img src="<?= e($user->avatarUrl(40)) ?>" alt="" class="blog-popover__avatar">
            <div class="blog-popover__info">
                <span class="blog-popover__name"><?= e($user->displayName()) ?></span>
                <span class="blog-popover__role"><?= e($roleLabels[$role] ?? $role) ?></span>
            </div>
        </div>

        <!-- 管理入口 -->
        <?php if (can('read') || can('dashboard')): ?>
        <a href="<?= url('/admin') ?>" class="blog-popover__item">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            <span>后台管理</span>
        </a>
        <?php endif; ?>
        <?php if (can('edit_posts')): ?>
        <a href="<?= url('/admin/posts/create') ?>" class="blog-popover__item">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
            <span>写文章</span>
        </a>
        <?php endif; ?>

        <div class="blog-popover__divider"></div>

        <!-- 主题切换 -->
        <button class="blog-popover__item blog-popover__theme" id="themeToggle" type="button">
            <svg class="icon-sun" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
            <svg class="icon-moon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            <span class="theme-label-light">切换深色模式</span>
            <span class="theme-label-dark">切换浅色模式</span>
        </button>

        <?php if ($debug['enabled']): ?>
        <div class="blog-popover__divider"></div>

        <!-- 调试面板 -->
        <div class="blog-popover__debug">
            <button class="blog-popover__item" id="debugToggle" type="button">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                <span>调试面板</span>
                <span class="blog-popover__badge">Q:<?= $debug['queryCount'] ?> H:<?= $debug['hookCount'] ?></span>
            </button>
            <div class="blog-popover__debug-body" id="debugBody">
                <?php if (!empty($debug['queries'])): ?>
                <details open>
                    <summary>Query Log (<?= $debug['queryCount'] ?>, <?= $debug['queryMs'] ?>ms)</summary>
                    <table>
                        <?php foreach ($debug['queries'] as $i => $q): ?>
                        <tr><td><?= $i + 1 ?></td><td><?= e($q['sql']) ?></td><td><?= $q['ms'] ?>ms</td></tr>
                        <?php endforeach ?>
                    </table>
                </details>
                <?php endif ?>
                <?php if (!empty($debug['hooks'])): ?>
                <details>
                    <summary>Hooks (<?= $debug['hookCount'] ?>, <?= $debug['hookMs'] ?>ms)</summary>
                    <?php foreach ($debug['hooks'] as $h): ?>
                    <div class="debug-row"><?= e($h['name']) ?> → <?= $h['callbacks'] ?> (<?= $h['ms'] ?>ms)</div>
                    <?php endforeach ?>
                </details>
                <?php endif ?>
                <?php if (!empty($debug['templates'])): ?>
                <details>
                    <summary>Templates (<?= $debug['templateCount'] ?>)</summary>
                    <?php foreach ($debug['templates'] as $t): ?>
                    <div class="debug-row"><?= e($t['hierarchy']) ?> → <strong><?= e($t['resolved']) ?></strong></div>
                    <?php endforeach ?>
                </details>
                <?php endif ?>
                <?php if (empty($debug['queries']) && empty($debug['hooks']) && empty($debug['templates'])): ?>
                <div class="debug-empty">暂无调试数据</div>
                <?php endif ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="blog-popover__divider"></div>

        <!-- 退出 -->
        <a href="<?= url('/logout') ?>" class="blog-popover__item blog-popover__item--danger">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            <span>退出登录</span>
        </a>
    </div>
</div>
<?php endif; ?>

<!-- 右侧抽屉导航 -->
<div class="blog-drawer" id="navDrawer">
    <div class="blog-drawer__overlay" id="navOverlay"></div>
    <aside class="blog-drawer__panel" id="navPanel" role="navigation" aria-label="主导航">
        <div class="blog-drawer__header">
            <span class="blog-drawer__title">菜单</span>
            <button class="blog-drawer__close" id="navClose" aria-label="关闭菜单" type="button">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <?php
        ob_start();
        echo wp_nav_menu(['theme_location' => 'primary', 'menu_class' => 'blog-nav__list', 'fallback' => true]);
        $navHtml = ob_get_clean();
        $navHtml = preg_replace('/<li class="menu-item(?:\s+current-menu-item)?"/', '<li class="blog-nav__item"', $navHtml);
        $navHtml = preg_replace('/<li class="menu-item current-menu-item([^"]*)"/', '<li class="blog-nav__item blog-nav__item--active$1"', $navHtml);
        $navHtml = preg_replace('/<a\s+href=/', '<a class="blog-nav__link" href=', $navHtml);
        echo $navHtml;
        ?>
    </aside>
</div>

<div id="main" class="blog-site-content">
