<?php
/**
 * Header partial
 * @var string $pageTitle
 * @var array $data
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
    <link rel="icon" href="<?= e($logoUrl ?: asset('themes/default/assets/img/favicon.svg')) ?>">
    <link rel="stylesheet" href="<?= asset('themes/default/assets/css/style.css') ?>">
    <?php do_action('wp_head') ?>
</head>
<body>
<a class="skip-link" href="#main">跳到主内容</a>
<header class="site-header">
    <div class="container">
        <div class="site-branding">
            <?php if ($logoUrl): ?>
                <a href="<?= url('/') ?>" class="logo-link"><img src="<?= e($logoUrl) ?>" alt="<?= e($siteName) ?>"></a>
            <?php else: ?>
                <a href="<?= url('/') ?>" class="site-title"><?= e($siteName) ?></a>
            <?php endif; ?>
            <?php if ($siteDesc): ?>
                <p class="site-description"><?= e($siteDesc) ?></p>
            <?php endif; ?>
        </div>
        <nav class="main-nav" aria-label="主导航">
            <button class="menu-toggle" aria-expanded="false" aria-controls="primary-menu">
                <span class="menu-icon"></span>
            </button>
            <ul id="primary-menu" class="menu">
                <li class="menu-item<?= $currentPage === '/' ? ' active' : '' ?>"><a href="<?= url('/') ?>">首页</a></li>
                <?php
                $cats = \App\Models\Category::query()->where('parent_id', '=', 0)->orderBy('name', 'ASC')->get();
                foreach ($cats as $c):
                    $cat = $c instanceof \App\Models\Category ? $c : new \App\Models\Category($c);
                ?>
                    <li class="menu-item<?= str_starts_with($currentPage, '/category/' . $cat->getAttribute('slug')) ? ' active' : '' ?>">
                        <a href="<?= $cat->url() ?>"><?= e($cat->getAttribute('name')) ?></a>
                    </li>
                <?php endforeach; ?>
                <li class="menu-item search-item">
                    <form action="<?= url('/search') ?>" method="get" class="search-form" role="search">
                        <input type="search" name="q" placeholder="搜索文章..." value="<?= e(app(\Core\Http\Request::class)->input('q', '')) ?>" aria-label="搜索">
                        <button type="submit" class="search-btn" aria-label="搜索">🔍</button>
                    </form>
                </li>
            </ul>
        </nav>
    </div>
</header>
<div id="main" class="site-content">
