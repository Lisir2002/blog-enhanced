<?php
/**
 * Header partial
 * @var string $pageTitle
 */
$siteName = \App\Models\Option::get('site_name', config('app.name'));
$siteDesc = \App\Models\Option::get('site_description', '');
$logoUrl = \App\Models\Option::get('logo_url', '');
$currentPage = app(\Core\Http\Request::class)->path();
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
<header class="site-header">
    <div class="container header-inner">
        <a href="<?= url('/') ?>" class="logo">
            <?php if ($logoUrl): ?>
                <img src="<?= e($logoUrl) ?>" alt="<?= e($siteName) ?>">
            <?php else: ?>
                <strong><?= e($siteName) ?></strong>
            <?php endif; ?>
        </a>
        <button class="menu-toggle" aria-label="菜单" aria-expanded="false">☰</button>
        <nav class="main-nav" role="navigation">
            <?= wp_nav_menu(['theme_location' => 'primary', 'menu_class' => 'menu', 'fallback' => true]) ?>
        </nav>
    </div>
</header>
<div id="main" class="site-content">
