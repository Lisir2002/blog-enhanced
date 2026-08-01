<?php

/**
 * Default 主题入口
 *
 * 在这里注册主题级 hooks，可被插件覆盖。
 */

use App\Models\Option;

// 修正：所有全局函数通过 helpers.php 可用

// 站点 <head> 输出
add_action('wp_head', function () {
    $siteName = Option::get('site_name', config('app.name'));
    $desc = Option::get('site_description', '');
    $keywords = Option::get('site_keywords', '');
    echo "<meta name=\"description\" content=\"" . e($desc) . "\">\n";
    echo "<meta name=\"keywords\" content=\"" . e($keywords) . "\">\n";
    echo "<meta property=\"og:site_name\" content=\"" . e($siteName) . "\">\n";
    echo "<meta property=\"og:type\" content=\"website\">\n";
    echo "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1, viewport-fit=cover\">\n";

    // 百度统计
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

// 页脚版权
add_filter('footer_text', function ($text) {
    $custom = Option::get('footer_text', '');
    if ($custom) return $custom;
    $year = date('Y');
    $name = Option::get('site_name', 'Blog CMS');
    return "© {$year} {$name}. Powered by Blog CMS.";
});
