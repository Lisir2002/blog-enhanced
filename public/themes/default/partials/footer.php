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
                    ['/<li[^>]*>\s*<a\s+/', '/<\/a>\s*<\/li>/', '/<ul[^>]*>/', '/<\/ul>/'],
                    ['<a class="blog-footer__link" ', '</a>', '', ''],
                    $footerMenu
                );
            }
            ?>
        </nav>
    </div>
</footer>
<?php wp_footer() ?>
<script>
/**
 * 实时预览消息监听器（来自定制器 iframe）
 * 处理三种消息类型：
 *   - theme-config: 应用配置变更到 CSS 变量
 *   - theme-mode: 切换浅色/深色模式
 *   - 首次加载时从 sessionStorage 读取配置作为兜底
 */
(function() {
    // 检查是否在 iframe 中
    if (window.top === window.self) return;

    /**
     * 应用主题配置变更
     * 接收配置对象 { key: value, ... } 和 CSS 变量映射 { key: --css-var, ... }
     * 将配置值设置到对应的 CSS 变量上
     */
    function applyThemeConfig(config, cssVars) {
        if (!config || typeof config !== 'object') return;
        var root = document.documentElement;
        var vars = cssVars || window._previewCssVars || {};

        for (var key in config) {
            if (!config.hasOwnProperty(key)) continue;
            var value = config[key];
            var cssVar = vars[key];
            if (cssVar) {
                root.style.setProperty(cssVar, value);
            }
        }
    }

    /**
     * 应用主题模式（浅色/深色）
     */
    function applyThemeMode(mode) {
        if (mode === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        } else {
            document.documentElement.removeAttribute('data-theme');
        }
    }

    // ── 首次加载：从 sessionStorage 读取配置（兜底） ──
    try {
        var savedConfig = sessionStorage.getItem('theme_preview_config');
        var savedCssVars = sessionStorage.getItem('theme_preview_cssVars');
        if (savedConfig) {
            var config = JSON.parse(savedConfig);
            var cssVars = savedCssVars ? JSON.parse(savedCssVars) : {};
            window._previewCssVars = cssVars;
            applyThemeConfig(config, cssVars);
        }
    } catch(e) {
        // sessionStorage 不可用时忽略
    }

    // ── 监听 postMessage（实时更新） ──
    window.addEventListener('message', function(e) {
        var data = e.data;
        if (!data || typeof data !== 'object') return;

        if (data.type === 'theme-config') {
            // 保存 cssVars 映射供后续使用
            window._previewCssVars = data.cssVars || {};
            applyThemeConfig(data.config, data.cssVars);
        } else if (data.type === 'theme-mode') {
            applyThemeMode(data.mode);
        }
    });
})();
</script>
</body>
</html>