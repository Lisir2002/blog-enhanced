<?php
/**
 * Header partial - 顶栏 v3 重构
 * 布局：Logo（左）→ 弹性留空 → 工具组（搜索 / 主题切换 / 头像 / ☰导航）
 */
$siteName = \App\Models\Option::get('site_name', config('app.name'));
$logoUrl  = \App\Models\Option::get('logo_url', '');
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

            <!-- 主题切换 -->
            <button class="blog-header__icon blog-header__theme" id="themeToggle" aria-label="切换深色/浅色模式" type="button">
                <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            </button>

            <!-- 头像 -->
            <?php if (logged_in()): $user = current_user(); ?>
                <a href="<?= url('/admin') ?>" class="blog-header__user" title="进入后台">
                    <img src="<?= e($user->avatarUrl(32)) ?>" alt="<?= e($user->displayName()) ?>" width="32" height="32">
                </a>
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
    </div>
</header>

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
