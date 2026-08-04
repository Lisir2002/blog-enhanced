<?php
/**
 * Page template (single page) - BEM
 * @var array $page
 * @var string $html
 */
get_header();
?>
<div class="blog-container">
    <div class="blog-layout blog-layout--single">
        <main class="blog-single-layout__content blog-page-content" role="main">
            <article class="blog-page">
                <header class="blog-page__header">
                    <h1 class="blog-page__title"><?= e($page['title']) ?></h1>
                </header>
                <div class="blog-page__body blog-single__content"><?= $html ?></div>
            </article>
        </main>
    </div>
</div>
<?php get_footer(); ?>
