<?php
/**
 * Plugin Name: Hello Dolly
 * Description: 示例插件 - 在每篇文章底部显示一句随机格言
 * Version: 1.0.0
 * Author: Blog CMS Demo
 */

namespace Plugins\HelloDolly;

if (!function_exists('hello_dolly_get_lyric')) {
    function hello_dolly_get_lyric(): string
    {
        $lyrics = [
            "Hello, World!",
            "代码改变世界。",
            "今天的你，比昨天更接近梦想。",
            "Stay hungry, stay foolish.",
            "少就是多。",
            "大道至简。",
            "开源即自由。",
            "保持简洁，保持善良。",
        ];
        return $lyrics[array_rand($lyrics)];
    }
}

add_filter('the_content', function ($content, $post = null) {
    if (is_admin_route()) return $content;
    $lyric = hello_dolly_get_lyric();
    $html = "<p class=\"hello-dolly\" style=\"padding:12px 16px;margin-top:24px;border-left:3px solid #3b82f6;background:#f8fafc;color:#475569;font-style:italic;border-radius:0 6px 6px 0\">💬 " . e($lyric) . "</p>";
    return $content . $html;
}, 20);

add_action('wp_head', function () {
    echo "<style>.hello-dolly { font-size: 14px; }</style>\n";
}, 5);
