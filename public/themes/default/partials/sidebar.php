<?php
/**
 * Sidebar partial — 优先输出 Widget 区域，无 Widget 时降级到内置内容 (BEM: blog-sidebar)
 * @var array $categories
 * @var array $tags
 */
$wm = app(\Core\View\WidgetManager::class);

ob_start();
$wm->renderSidebar('sidebar-1');
$sidebarHtml = ob_get_clean();

if (trim($sidebarHtml) !== '') {
    // Widget 输出 class 转换为 BEM
    $sidebarHtml = preg_replace('/<section class="widget\s+([^"]*)">/', '<section class="blog-sidebar__widget blog-sidebar__widget--$1">', $sidebarHtml);
    $sidebarHtml = preg_replace('/<section class="widget">/', '<section class="blog-sidebar__widget">', $sidebarHtml);
    $sidebarHtml = preg_replace('/<h3 class="widget-title">/', '<h3 class="blog-sidebar__title">', $sidebarHtml);
    $sidebarHtml = preg_replace('/<ul\s*>/', '<ul class="blog-sidebar__list">', $sidebarHtml);
    $sidebarHtml = preg_replace('/<li\s*>/', '<li class="blog-sidebar__item">', $sidebarHtml);
    $sidebarHtml = preg_replace('/class="tag-cloud"/', 'class="blog-sidebar__tag-cloud"', $sidebarHtml);
    $sidebarHtml = preg_replace('/class="tag-link"/', 'class="blog-sidebar__tag"', $sidebarHtml);
    $sidebarHtml = preg_replace('/class="recent-list"/', 'class="blog-sidebar__list"', $sidebarHtml);
    $sidebarHtml = preg_replace('/class="category-list"/', 'class="blog-sidebar__list"', $sidebarHtml);
    echo '<aside class="blog-sidebar" aria-label="侧边栏">' . "\n" . $sidebarHtml . "\n" . '</aside>';
    return;
}

// Fallback: built-in sidebar content
if (!isset($categories)) {
    $categories = \App\Models\Category::all();
}
if (!isset($tags)) {
    $tags = \App\Models\Tag::all();
}
$recent = \App\Models\Post::published(1, 5);
?>
<aside class="blog-sidebar" aria-label="侧边栏">
    <section class="blog-sidebar__widget blog-sidebar__widget--recent">
        <h3 class="blog-sidebar__title">最新文章</h3>
        <ul class="blog-sidebar__list">
            <?php foreach ($recent as $r): $post = is_object($r) ? $r : new \App\Models\Post($r); ?>
                <li class="blog-sidebar__item"><a href="<?= $post->url() ?>"><?= e($post->getAttribute('title')) ?></a></li>
            <?php endforeach; ?>
            <?php if (empty($recent)): ?>
                <li class="blog-sidebar__item blog-sidebar__item--muted blog-muted">暂无文章</li>
            <?php endif; ?>
        </ul>
    </section>

    <section class="blog-sidebar__widget blog-sidebar__widget--categories">
        <h3 class="blog-sidebar__title">分类</h3>
        <ul class="blog-sidebar__list">
            <?php foreach ($categories as $c): $cat = $c instanceof \App\Models\Category ? $c : new \App\Models\Category($c); ?>
                <li class="blog-sidebar__item">
                    <a href="<?= $cat->url() ?>"><?= e($cat->getAttribute('name')) ?></a>
                    <span class="blog-sidebar__count"><?= $cat->postCount() ?></span>
                </li>
            <?php endforeach; ?>
            <?php if (empty($categories)): ?>
                <li class="blog-sidebar__item blog-sidebar__item--muted blog-muted">暂无分类</li>
            <?php endif; ?>
        </ul>
    </section>

    <?php if (!empty($tags)): ?>
    <section class="blog-sidebar__widget blog-sidebar__widget--tags">
        <h3 class="blog-sidebar__title">标签</h3>
        <div class="blog-sidebar__tag-cloud">
            <?php foreach ($tags as $tag): $tag = $tag instanceof \App\Models\Tag ? $tag : new \App\Models\Tag($tag); ?>
                <a href="<?= $tag->url() ?>" class="blog-sidebar__tag"><?= e($tag->getAttribute('name')) ?></a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php do_action('sidebar_bottom') ?>
</aside>
