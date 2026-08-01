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
            </nav>
        </div>
    </div>
</footer>

<script src="<?= asset('themes/default/assets/js/main.js') ?>"></script>
<?php do_action('wp_footer') ?>
</body>
</html>
