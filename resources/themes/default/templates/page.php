<?php
/**
 * Page template (single page)
 * @var array $page
 * @var string $html
 */
get_header();
?>
<div class="container">
    <main class="content-area page-content" role="main">
        <article class="page">
            <header class="post-header">
                <h1 class="post-title"><?= e($page['title']) ?></h1>
            </header>
            <div class="post-content"><?= $html ?></div>
        </article>
    </main>
</div>
<?php get_footer();
