<?php
/**
 * Error template (500)
 * @var \Throwable $exception
 */
get_header();
?>
<div class="container">
    <main class="content-area error-page" role="main">
        <p class="error-code">500</p>
        <p class="error-message">服务器出错了，请稍后再试</p>
        <a href="<?= url('/') ?>" class="button">← 返回首页</a>
    </main>
</div>
<?php get_footer();
