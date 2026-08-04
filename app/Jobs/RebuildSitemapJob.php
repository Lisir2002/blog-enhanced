<?php

namespace App\Jobs;

/**
 * 重建 Sitemap 任务 - 异步执行，避免阻塞请求。
 *
 * 用法：
 *   app(\Core\Queue\Queue::class)->push(RebuildSitemapJob::class, ['post_id' => 123]);
 */
class RebuildSitemapJob
{
    public function __construct(public array $data = []) {}

    public function handle(): void
    {
        try {
            $sitemap = app(\Core\SEO\Sitemap::class);
            $xml = $sitemap->generate();

            // 写入文件
            $path = public_path('sitemap.xml');
            file_put_contents($path, $xml);

            \Core\Log\Log::info('Sitemap rebuilt', [
                'post_id' => $this->data['post_id'] ?? null,
            ]);
        } catch (\Throwable $e) {
            \Core\Log\Log::error('Sitemap rebuild failed', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
