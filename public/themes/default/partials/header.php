<?php
/**
 * Header partial - BEM 命名 (blog-header / blog-nav)
 * @var string $pageTitle
 */
$siteName = \App\Models\Option::get('site_name', config('app.name'));
$siteDesc = \App\Models\Option::get('site_description', '');
$logoUrl = \App\Models\Option::get('logo_url', '');
?>
<!DOCTYPE html>
<html lang="zh-CN">
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
        <a href="<?= url('/') ?>" class="blog-header__brand">
            <?php if ($logoUrl): ?>
                <img src="<?= e($logoUrl) ?>" alt="<?= e($siteName) ?>">
            <?php else: ?>
                <strong><?= e($siteName) ?></strong>
            <?php endif; ?>
        </a>
        <button class="blog-header__toggle" aria-label="菜单" aria-expanded="false" type="button">☰</button>
        <div class="blog-header__actions">
            <?php if (logged_in()): $user = current_user(); ?>
                <a href="<?= url('/admin') ?>" class="blog-header__user" title="进入后台">
                    <img src="<?= e($user->avatarUrl(24)) ?>" alt="" width="24" height="24">
                </a>
                <a href="<?= url('/logout') ?>" class="blog-header__login">退出</a>
            <?php else: ?>
                <a href="<?= url('/login') ?>" class="blog-header__login">登录</a>
            <?php endif; ?>
        </div>
        <nav class="blog-nav" role="navigation" aria-label="主导航">
            <?php
            ob_start();
            echo wp_nav_menu(['theme_location' => 'primary', 'menu_class' => 'blog-nav__list', 'fallback' => true]);
            $navHtml = ob_get_clean();
            // 替换默认菜单 class 为 BEM
            $navHtml = preg_replace('/<li class="menu-item(?:\s+current-menu-item)?"/', '<li class="blog-nav__item"', $navHtml);
            $navHtml = preg_replace('/<li class="menu-item current-menu-item([^"]*)"/', '<li class="blog-nav__item blog-nav__item--active$1"', $navHtml);
            $navHtml = preg_replace('/<a\s+href=/', '<a class="blog-nav__link" href=', $navHtml);
            echo $navHtml;
            ?>
        </nav>
    </div>
</header>
<div id="main" class="blog-site-content">
