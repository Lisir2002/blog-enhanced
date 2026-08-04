<?php

namespace Themes\Default\Widgets;

use Core\View\Widget;
use App\Models\Post;

/**
 * 最新文章 Widget — 演示自定义 Widget 开发。
 */
class RecentPostsWidget extends Widget
{
    public function __construct()
    {
        parent::__construct('recent-posts', '最新文章');
        $this->description = '显示最新的已发布文章列表';
    }

    public function form(array $instance = []): string
    {
        $count = $instance['count'] ?? 5;
        return '<label>数量: <input type="number" name="count" value="' . e((string) $count) . '" min="1" max="20"></label>';
    }

    public function render(array $instance): string
    {
        $count = (int) ($instance['count'] ?? 5);
        $posts = Post::published(1, $count);
        if (empty($posts)) {
            return '<p class="muted">暂无文章</p>';
        }
        $html = '<ul class="recent-list">';
        foreach ($posts as $r) {
            $post = $r instanceof Post ? $r : new Post($r);
            $html .= '<li><a href="' . $post->url() . '">' . e($post->getAttribute('title')) . '</a></li>';
        }
        $html .= '</ul>';
        return $html;
    }
}
