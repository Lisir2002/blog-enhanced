<?php
/**
 * Home template - 布局 D：卡片式博客首页 (Grid Card Layout)
 * @var array $posts
 * @var int $page
 * @var int $totalPages
 * @var array $categories
 * @var array $tags
 * @var string $pageTitle
 */
get_header();
?>
<div class="blog-container">
    <div class="blog-layout">
        <main class="blog-layout__main" role="main">
            <?php if (empty($posts)): ?>
                <div class="blog-empty-state">
                    <div class="blog-empty-state__icon">📝</div>
                    <p class="blog-empty-state__text">暂无文章，去后台发布第一篇吧！</p>
                </div>
            <?php else: ?>
                <div class="blog-card-list">
                    <?php foreach ($posts as $r): $post = new \App\Models\Post($r); ?>
                        <?php $cat = $post->category(); ?>
                        <article class="blog-card <?= $post->getAttribute('cover') ? '' : 'blog-card--no-cover' ?>">
                            <?php if ($post->getAttribute('cover')): ?>
                                <a href="<?= $post->url() ?>" class="blog-card__cover">
                                    <img src="<?= e($post->getAttribute('cover')) ?>" alt="<?= e($post->getAttribute('title')) ?>" loading="lazy">
                                </a>
                            <?php endif; ?>
                            <div class="blog-card__body">
                                <?php if ($cat): ?>
                                    <a href="<?= $cat->url() ?>" class="blog-card__category"><?= e($cat->getAttribute('name')) ?></a>
                                <?php endif; ?>
                                <h2 class="blog-card__title">
                                    <a href="<?= $post->url() ?>"><?= e($post->getAttribute('title')) ?></a>
                                </h2>
                                <p class="blog-card__excerpt"><?= e($post->excerpt(160)) ?></p>
                                <div class="blog-card__meta">
                                    <time class="blog-card__date" datetime="<?= e($post->getAttribute('published_at') ?? $post->getAttribute('created_at')) ?>">
                                        📅 <?= e(date('Y-m-d', strtotime($post->getAttribute('published_at') ?? $post->getAttribute('created_at')))) ?>
                                    </time>
                                    <span class="blog-card__views">👁 <?= (int) $post->getAttribute('views') ?></span>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?= paginate_links([
                    'total'     => $totalPages,
                    'current'   => $page,
                    'base'      => url('/page/%#%'),
                ]) ?>
            <?php endif; ?>
        </main>
        <?php get_sidebar(); ?>
    </div>
</div>
<?php get_footer(); ?>
