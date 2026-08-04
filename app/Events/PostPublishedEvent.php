<?php

namespace App\Events;

use App\Models\Post;

/**
 * 文章发布事件 - 当文章状态变为 published 时触发。
 *
 * 监听器可订阅此事件执行：
 * - 清除页面缓存
 * - 重建 Sitemap
 * - 发送订阅通知
 * - 推送 Webhook
 */
class PostPublishedEvent
{
    public function __construct(
        public readonly Post $post,
        public readonly bool $isUpdate = false,
    ) {}

    private bool $propagationStopped = false;

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }
}
