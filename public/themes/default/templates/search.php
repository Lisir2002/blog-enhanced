<?php
/**
 * Search results template - BEM
 * @var string $query
 * @var array $posts
 * @var int $total
 */
get_header();
?>
<div class="blog-container">
    <div class="blog-layout">
        <main class="blog-layout__main" role="main">
            <header class="blog-archive-header">
                <h1 class="blog-archive-header__title blog-archive-header__title--search">搜索: &quot;<?= e($query) ?>&quot;</h1>
                <p class="blog-archive-header__desc">找到 <?= (int) $total ?> 条结果</p>
            </header>

            <?php if (empty($posts)): ?>
                <div class="blog-empty-state"><div class="blog-empty-state__icon">🔍</div><p class="blog-empty-state__text">没有找到匹配的文章</p></div>
            <?php else: ?>
                <div class="blog-card-list">
                    <?php foreach ($posts as $r): $post = is_object($r) ? $r : new \App\Models\Post($r); ?>
                        <?php $cat = $post->category(); ?>
                        <article class="blog-card blog-card--compact <?= $post->getAttribute('cover') ? '' : 'blog-card--no-cover' ?>">
                            <div class="blog-card__body">
                                <?php if ($cat): ?>
                                    <a href="<?= $cat->url() ?>" class="blog-card__category"><?= e($cat->getAttribute('name')) ?></a>
                                <?php endif; ?>
                                <h2 class="blog-card__title"><a href="<?= $post->url() ?>"><?= e($post->getAttribute('title')) ?></a></h2>
                                <p class="blog-card__excerpt"><?= e($post->excerpt(160)) ?></p>
                                <a class="blog-card__readmore" href="<?= $post->url() ?>">阅读全文 →</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if (!empty($totalPages) && $totalPages > 1): ?>
                <?= paginate_links([
                    'total'     => $totalPages,
                    'current'   => $page ?? 1,
                    'base'      => url('/search?q=' . urlencode($query) . '&page=%#%'),
                ]) ?>
                <?php endif; ?>
            <?php endif; ?>
        </main>
        <?php get_sidebar(); ?>
    </div>
</div>
<?php get_footer(); ?>
