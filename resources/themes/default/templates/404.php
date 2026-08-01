<?php
/**
 * 404 template
 * @var string $message
 */
get_header();
?>
<div class="container">
    <main class="content-area error-page" role="main">
        <p class="error-code">404</p>
        <p class="error-message"><?= e($message ?? '页面不存在') ?></p>
        <a href="<?= url('/') ?>" class="button">← 返回首页</a>
    </main>
</div>
<?php get_footer();
