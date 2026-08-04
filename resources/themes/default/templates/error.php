<?php
/**
 * Error template (500) - BEM
 * @var \Throwable $exception
 */
get_header();
?>
<div class="blog-container">
    <main class="blog-error-page" role="main">
        <p class="blog-error-page__code blog-error-page__code--500">500</p>
        <p class="blog-error-page__message">服务器出错了，请稍后再试</p>
        <?php if (config('app.debug') && isset($exception)): ?>
            <pre class="blog-error-page__detail"><?= e($exception->getMessage()) ?>

<?= e($exception->getFile()) ?>:<?= (int) $exception->getLine() ?></pre>
        <?php endif; ?>
        <a href="<?= url('/') ?>" class="blog-btn blog-btn--primary">← 返回首页</a>
    </main>
</div>
<?php get_footer(); ?>
