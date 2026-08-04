<?php
/**
 * Author template - BEM
 * @var \App\Models\User $author
 */
get_header();
?>
<div class="blog-container">
    <div class="blog-layout">
        <main class="blog-layout__main" role="main">
            <header class="blog-archive-header blog-archive-header--author">
                <img src="<?= $author->avatarUrl(80) ?>" alt="" class="blog-archive-header__avatar">
                <h1 class="blog-archive-header__title"><?= e($author->displayName()) ?></h1>
                <?php if ($author->getAttribute('bio')): ?>
                    <p class="blog-archive-header__desc"><?= e($author->getAttribute('bio')) ?></p>
                <?php endif; ?>
            </header>

            <?php if (empty($posts)): ?>
                <div class="blog-empty-state"><div class="blog-empty-state__icon">📭</div><p class="blog-empty-state__text">该作者暂无文章</p></div>
            <?php else: ?>
                <div class="blog-card-list">
                    <?php foreach ($posts as $r): $post = is_object($r) ? $r : new \App\Models\Post($r); ?>
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
                                <h2 class="blog-card__title"><a href="<?= $post->url() ?>"><?= e($post->getAttribute('title')) ?></a></h2>
                                <p class="blog-card__excerpt"><?= e($post->excerpt(160)) ?></p>
                                <div class="blog-card__meta">
                                    <time class="blog-card__date">📅 <?= substr((string) $post->getAttribute('published_at'), 0, 10) ?></time>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?= paginate_links([
                    'total'     => $totalPages,
                    'current'   => $page,
                    'base'      => url('/author/' . $author->getAttribute('username') . '/page/%#%'),
                ]) ?>
            <?php endif; ?>
        </main>
        <?php get_sidebar(); ?>
    </div>
</div>
<?php get_footer(); ?>
