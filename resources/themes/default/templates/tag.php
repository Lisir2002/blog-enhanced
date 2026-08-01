<?php
/**
 * Tag template
 */
get_header();
?>
<div class="container">
    <div class="layout-grid">
        <main class="content-area" role="main">
            <header class="page-header">
                <h1 class="page-title">标签: #<?= e($tag->getAttribute('name')) ?></h1>
            </header>
            <?php if (empty($posts)): ?>
                <div class="empty-state"><div class="icon">📭</div><p>该标签下暂无文章</p></div>
            <?php else: ?>
                <div class="post-list">
                    <?php foreach ($posts as $r): $post = new \App\Models\Post($r); ?>
                        <article class="post-card">
                            <div class="post-card-inner">
                                <div class="body">
                                    <h2 class="title"><a href="<?= $post->url() ?>"><?= e($post->getAttribute('title')) ?></a></h2>
                                    <p class="excerpt"><?= e($post->excerpt(160)) ?></p>
                                    <a class="read-more" href="<?= $post->url() ?>">阅读全文 →</a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <?php if ($totalPages > 1): ?>
                <nav class="pagination">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?= $i ?></span>
                        <?php else: ?>
                            <a href="<?= url('/tag/' . $tag->getAttribute('slug') . '/page/' . $i) ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </nav>
                <?php endif; ?>
            <?php endif; ?>
        </main>
        <?php get_sidebar(); ?>
    </div>
</div>
<?php get_footer();
