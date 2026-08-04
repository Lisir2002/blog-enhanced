<?php
/**
 * 404 template - BEM
 * @var string $message
 */
get_header();
?>
<div class="blog-container">
    <main class="blog-error-page" role="main">
        <p class="blog-error-page__code">404</p>
        <p class="blog-error-page__message"><?= e($message ?? '页面不存在') ?></p>
        <a href="<?= url('/') ?>" class="blog-btn blog-btn--primary">← 返回首页</a>
    </main>
</div>
<?php get_footer(); ?>
