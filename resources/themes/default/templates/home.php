<?php
/**
 * Home template - 文章列表
 * @var array $posts
 * @var int $page
 * @var int $totalPages
 * @var array $categories
 * @var array $tags
 * @var string $pageTitle
 */
get_header();
?>
<div class="container">
    <div class="layout-grid">
        <main class="content-area" role="main">
            <?php if (empty($posts)): ?>
                <div class="empty-state">
                    <div class="icon">📝</div>
                    <p>暂无文章，去后台发布第一篇吧！</p>
                </div>
            <?php else: ?>
                <div class="post-list">
                    <?php foreach ($posts as $r): $post = new \App\Models\Post($r); ?>
                        <article class="<?= post_class() ?>">
                            <div class="post-card-inner">
                                <?php if ($post->getAttribute('cover')): ?>
                                    <a href="<?= $post->url() ?>" class="post-thumb">
                                        <img src="<?= e($post->getAttribute('cover')) ?>" alt="<?= e($post->getAttribute('title')) ?>" loading="lazy">
                                    </a>
                                <?php endif; ?>
                                <div class="body">
                                    <h2 class="title"><a href="<?= $post->url() ?>"><?= e($post->getAttribute('title')) ?></a></h2>
                                    <div class="post-meta">
                                        <time datetime="<?= e($post->getAttribute('published_at') ?? $post->getAttribute('created_at')) ?>"><?= e(date('Y-m-d', strtotime($post->getAttribute('published_at') ?? $post->getAttribute('created_at')))) ?></time>
                                        <?php $cat = $post->category(); if ($cat): ?>
                                            <span class="cat-link"><a href="<?= $cat->url() ?>"><?= e($cat->getAttribute('name')) ?></a></span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="excerpt"><?= e($post->excerpt(160)) ?></p>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?= paginate_links([
                    'total'     => $totalPages,
                    'current'   => $page,
                    'base'      => $page === 1 ? url('/page/%#%') : url('/page/%#%'),
                ]) ?>
            <?php endif; ?>
        </main>
        <?php get_sidebar(); ?>
    </div>
</div>
<?php
get_footer();
