<?php

/**
 * Default 主题入口
 *
 * 演示完整的主题 API 用法。
 */

use App\Models\Option;

// ── 注册菜单位置 ──────────────────────────
register_nav_menu('primary', '主导航');
register_nav_menu('footer', '页脚导航');

// ── 注册 Widget 区域 ──────────────────────
register_sidebar([
    'id'            => 'sidebar-1',
    'name'          => '主侧边栏',
    'description'   => '文章页右侧边栏',
    'before_widget' => '<section class="widget %s">',
    'after_widget'  => '</section>',
    'before_title'  => '<h3 class="widget-title">',
    'after_title'   => '</h3>',
]);

register_sidebar([
    'id'            => 'footer-1',
    'name'          => '页脚区域',
    'description'   => '页脚 Widget 区',
]);

// ── 注册自定义 Widget ─────────────────────
add_action('widgets_init', function () {
    register_widget(\Themes\Default\Widgets\RecentPostsWidget::class);
});

// ── 排队 CSS/JS（替代 header.php 硬编码）──
add_action('wp_enqueue', function () {
    $ver = file_exists(theme_path('assets/css/style.css'))
        ? (string) filemtime(theme_path('assets/css/style.css'))
        : '1.0.0';
    enqueue_style('theme-style', theme_asset('assets/css/style.css'), [], $ver);
    enqueue_script('theme-main', theme_asset('assets/js/main.js'), [], $ver, true);
});

// ── 站点 <head> 输出 ──────────────────────
add_action('wp_head', function () {
    $siteName = Option::get('site_name', config('app.name'));
    $desc = Option::get('site_description', '');
    $keywords = Option::get('site_keywords', '');

    echo "<meta name=\"description\" content=\"" . e($desc) . "\">\n";
    echo "<meta name=\"keywords\" content=\"" . e($keywords) . "\">\n";
    echo "<meta property=\"og:site_name\" content=\"" . e($siteName) . "\">\n";
    echo "<meta property=\"og:type\" content=\"website\">\n";

    // Analytics
    $baidu = Option::get('baidu_analytics', '');
    if ($baidu) {
        echo "<script>var _hmt=_hmt||[];(function(){var hm=document.createElement('script');hm.src='https://hm.baidu.com/hm.js?{$baidu}';var s=document.getElementsByTagName('script')[0];s.parentNode.insertBefore(hm,s);})();</script>\n";
    }
    $ga = Option::get('google_analytics', '');
    if ($ga) {
        echo "<script async src=\"https://www.googletagmanager.com/gtag/js?id={$ga}\"></script>\n";
        echo "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','{$ga}');</script>\n";
    }
});

// ── 页脚版权 ──────────────────────────────
add_filter('footer_text', function ($text) {
    $custom = Option::get('footer_text', '');
    if ($custom) return $custom;
    $year = date('Y');
    $name = Option::get('site_name', 'Blog CMS');
    return "© {$year} {$name}. Powered by Blog CMS.";
});

// ── 主题激活时初始化 ─────────────────────
add_action('after_switch_theme', function () {
    Option::set('theme_default_initialized', true);
});
