<?php

namespace Core\Providers;

use Core\Cache\CacheInterface;
use Core\Queue\Queue;

class QueueProvider extends Provider
{
    public function register(): void
    {
        $this->app->singleton(Queue::class, function () {
            return new Queue($this->app->get(CacheInterface::class));
        });
    }

    public function boot(): void
    {
        // 注册队列相关 Hook
        add_action('post_saved', function ($id, $data, $isUpdate) {
            // 文章保存时推送 SEO 重建任务到队列
            if (config('queue.driver', 'sync') !== 'sync') {
                app(Queue::class)->push(
                    \App\Jobs\RebuildSitemapJob::class,
                    ['post_id' => $id],
                    'default'
                );
            }
        }, 50);
    }
}
