<?php
/**
 * Footer partial - 紧凑型底部布局
 */
?>
</div><!-- #main -->

<footer class="blog-footer" role="contentinfo">
    <div class="blog-container blog-footer__inner">
        <div class="blog-footer__left">
            <span class="blog-footer__copyright"><?= e(apply_filters('footer_text', '© ' . date('Y') . ' Blog CMS')) ?></span>
            <span class="blog-footer__divider">·</span>
            <span class="blog-footer__powered">Powered by <a href="https://github.com/" target="_blank" rel="noopener">Blog CMS</a></span>
        </div>
        <nav class="blog-footer__nav" aria-label="底部导航">
            <a href="<?= url('/feed') ?>" class="blog-footer__link">RSS</a>
            <a href="<?= url('/sitemap.xml') ?>" class="blog-footer__link">Sitemap</a>
            <?php
            $footerMenu = wp_nav_menu(['theme_location' => 'footer', 'container' => 'span', 'echo' => false]);
            if ($footerMenu) {
                echo preg_replace(
                    ['/<li class="[^"]*">/', '/<\/li>/', '/<a\s+/', '/<ul\s*>/', '/<\/ul>/'],
                    ['<a class="blog-footer__link" ', '</a>', '<a class="blog-footer__link" ', '', ''],
                    $footerMenu
                );
            }
            ?>
        </nav>
    </div>
</footer>
<?php wp_footer() ?>
</body>
</html>