<?php
/**
 * Archive template (fallback for lists)
 */
get_header();
?>
<div class="container">
    <div class="layout-grid">
        <main class="content-area" role="main">
            <header class="page-header"><h1 class="page-title">归档</h1></header>
            <div class="post-list">
                <?php foreach ($posts ?? [] as $r): $post = new \App\Models\Post($r); ?>
                    <article class="post-card"><div class="post-card-inner"><div class="body">
                        <h2 class="title"><a href="<?= $post->url() ?>"><?= e($post->getAttribute('title')) ?></a></h2>
                        <p class="excerpt"><?= e($post->excerpt(160)) ?></p>
                    </div></div></article>
                <?php endforeach; ?>
            </div>
        </main>
        <?php get_sidebar(); ?>
    </div>
</div>
<?php get_footer();
