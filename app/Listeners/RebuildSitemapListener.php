<?php

namespace App\Listeners;

use App\Events\PostPublishedEvent;
use Core\Queue\Queue;
use App\Jobs\RebuildSitemapJob;

/**
 * 文章发布时异步重建 Sitemap - 监听 PostPublishedEvent。
 */
class RebuildSitemapListener
{
    public function __construct(
        private Queue $queue,
    ) {}

    public function handle(PostPublishedEvent $event): void
    {
        // 推送到队列异步执行
        $this->queue->push(RebuildSitemapJob::class, [
            'post_id' => $event->post->getAttribute('id'),
        ], 'default');

        \Core\Log\Log::info('Sitemap rebuild queued', [
            'post_id' => $event->post->getAttribute('id'),
        ]);
    }
}
