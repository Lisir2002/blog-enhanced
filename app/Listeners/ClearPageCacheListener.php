<?php

namespace App\Listeners;

use App\Events\PostPublishedEvent;
use Core\Cache\PageCache;

/**
 * 文章发布时清除页面缓存 - 监听 PostPublishedEvent。
 */
class ClearPageCacheListener
{
    public function __construct(
        private PageCache $pageCache,
    ) {}

    public function handle(PostPublishedEvent $event): void
    {
        $postId = $event->post->getAttribute('id');
        $this->pageCache->flush(str_replace('/', '-', 'post-' . $postId));

        // 同时清除首页、分类页缓存
        $this->pageCache->flush('home');
        $categoryId = $event->post->getAttribute('category_id');
        if ($categoryId) {
            $this->pageCache->flush('category-' . $categoryId);
        }

        \Core\Log\Log::info('Page cache cleared for post', ['post_id' => $postId]);
    }
}
