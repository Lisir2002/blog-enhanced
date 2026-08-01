<?php
/**
 * Author template
 * @var \App\Models\User $author
 */
get_header();
?>
<div class="container">
    <div class="layout-grid">
        <main class="content-area" role="main">
            <header class="page-header author-header">
                <img src="<?= $author->avatarUrl(80) ?>" alt="" class="author-avatar">
                <h1 class="page-title"><?= e($author->displayName()) ?></h1>
                <?php if ($author->getAttribute('bio')): ?>
                    <p class="archive-description"><?= e($author->getAttribute('bio')) ?></p>
                <?php endif; ?>
            </header>
            <?php if (empty($posts)): ?>
                <div class="empty-state"><div class="icon">📭</div><p>该作者暂无文章</p></div>
            <?php else: ?>
                <div class="post-list">
                    <?php foreach ($posts as $r): $post = new \App\Models\Post($r); ?>
                        <article class="post-card">
                            <div class="post-card-inner">
                                <div class="body">
                                    <div class="meta"><span><?= substr((string) $post->getAttribute('published_at'), 0, 10) ?></span></div>
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
                            <a href="<?= url('/author/' . $author->getAttribute('username') . '/page/' . $i) ?>"><?= $i ?></a>
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
