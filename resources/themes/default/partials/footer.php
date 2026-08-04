<?php
/**
 * Footer partial
 */
?>
</div><!-- #main -->

<footer class="site-footer">
    <div class="container">
        <div class="footer-inner">
            <p class="footer-text"><?= e(apply_filters('footer_text', '© ' . date('Y') . ' Blog CMS')) ?></p>
            <nav class="footer-nav">
                <a href="<?= url('/feed') ?>">RSS</a>
                <a href="<?= url('/sitemap.xml') ?>">Sitemap</a>
                <?= wp_nav_menu(['theme_location' => 'footer', 'container' => 'span', 'menu_class' => 'footer-menu']) ?>
            </nav>
        </div>
    </div>
</footer>
<?php wp_footer() ?>
</body>
</html>
