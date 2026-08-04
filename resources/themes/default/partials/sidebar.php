<?php
/**
 * Sidebar partial — 优先输出 Widget 区域，无 Widget 时降级到内置内容
 * @var array $categories
 * @var array $tags
 */
$wm = app(\Core\View\WidgetManager::class);
$sidebarHtml = $wm->renderSidebar('sidebar-1');

if (trim($sidebarHtml) !== '') {
    echo $sidebarHtml;
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
<aside class="sidebar" aria-label="侧边栏">
    <section class="widget widget-recent">
        <h3 class="widget-title">最新文章</h3>
        <ul class="recent-list">
            <?php foreach ($recent as $r): $post = $r instanceof \App\Models\Post ? $r : new \App\Models\Post($r); ?>
                <li><a href="<?= $post->url() ?>"><?= e($post->getAttribute('title')) ?></a></li>
            <?php endforeach; ?>
            <?php if (empty($recent)): ?>
                <li class="muted">暂无文章</li>
            <?php endif; ?>
        </ul>
    </section>

    <section class="widget widget-categories">
        <h3 class="widget-title">分类</h3>
        <ul class="category-list">
            <?php foreach ($categories as $c): $cat = $c instanceof \App\Models\Category ? $c : new \App\Models\Category($c); ?>
                <li>
                    <a href="<?= $cat->url() ?>"><?= e($cat->getAttribute('name')) ?></a>
                    <span class="count"><?= $cat->postCount() ?></span>
                </li>
            <?php endforeach; ?>
            <?php if (empty($categories)): ?>
                <li class="muted">暂无分类</li>
            <?php endif; ?>
        </ul>
    </section>

    <?php if (!empty($tags)): ?>
    <section class="widget widget-tags">
        <h3 class="widget-title">标签</h3>
        <div class="tag-cloud">
            <?php foreach ($tags as $tag): $tag = $tag instanceof \App\Models\Tag ? $tag : new \App\Models\Tag($tag); ?>
                <a href="<?= $tag->url() ?>" class="tag-link"><?= e($tag->getAttribute('name')) ?></a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php do_action('sidebar_bottom') ?>
</aside>
