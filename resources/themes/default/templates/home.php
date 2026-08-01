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
                        <article class="post-card">
                            <div class="post-card-inner">
                                <?php if ($post->getAttribute('cover')): ?>
                                <a class="cover" href="<?= $post->url() ?>">
                                    <img src="<?= url($post->getAttribute('cover')) ?>" alt="<?= e($post->getAttribute('title')) ?>" loading="lazy">
                                </a>
                                <?php else: ?>
                                <a class="cover" href="<?= $post->url() ?>" aria-hidden="true" tabindex="-1"></a>
                                <?php endif; ?>
                                <div class="body">
                                    <div class="meta">
                                        <?php $cat = $post->category(); if ($cat): ?>
                                            <a href="<?= $cat->url() ?>"><?= e($cat->getAttribute('name')) ?></a>
                                        <?php endif; ?>
                                        <span><?= substr((string) $post->getAttribute('published_at'), 0, 10) ?></span>
                                        <?php $author = $post->author(); if ($author): ?>
                                            <span>by <a href="<?= url('/author/' . $author->getAttribute('username')) ?>"><?= e($author->displayName()) ?></a></span>
                                        <?php endif; ?>
                                    </div>
                                    <h2 class="title"><a href="<?= $post->url() ?>"><?= e($post->getAttribute('title')) ?></a></h2>
                                    <p class="excerpt"><?= e($post->excerpt(160)) ?></p>
                                    <a class="read-more" href="<?= $post->url() ?>">阅读全文 →</a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                <nav class="pagination" aria-label="分页">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?= $i ?></span>
                        <?php else: ?>
                            <a href="<?= $i === 1 ? url('/') : url('/page/' . $i) ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </nav>
                <?php endif; ?>
            <?php endif; ?>
        </main>
        <?php get_sidebar(); ?>
    </div>
</div>
<?php
get_footer();
