<?php
/**
 * Archive template (fallback for lists) - BEM
 */
get_header();
?>
<div class="blog-container">
    <div class="blog-layout">
        <main class="blog-layout__main" role="main">
            <header class="blog-archive-header"><h1 class="blog-archive-header__title">归档</h1></header>
            <div class="blog-card-list">
                <?php foreach ($posts ?? [] as $r): $post = is_object($r) ? $r : new \App\Models\Post($r); ?>
                    <article class="blog-card blog-card--compact blog-card--no-cover">
                        <div class="blog-card__body">
                            <h2 class="blog-card__title"><a href="<?= $post->url() ?>"><?= e($post->getAttribute('title')) ?></a></h2>
                            <p class="blog-card__excerpt"><?= e($post->excerpt(160)) ?></p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </main>
        <?php get_sidebar(); ?>
    </div>
</div>
<?php get_footer(); ?>
