<?php

namespace App\Jobs;

use App\Models\Post;
use App\Models\User;
use Core\Email\EmailTemplate;

/**
 * 发送评论通知邮件 - 异步执行。
 *
 * 用法：
 *   app(\Core\Queue\Queue::class)->push(SendCommentNotificationJob::class, [
 *       'comment_id' => 456,
 *       'post_id'    => 123,
 *   ]);
 */
class SendCommentNotificationJob
{
    public function __construct(public array $data = []) {}

    public function handle(): void
    {
        $post = Post::find($this->data['post_id'] ?? 0);
        if (!$post) {
            return;
        }

        $author = User::find($post->getAttribute('author_id'));
        if (!$author || !$author->getAttribute('email')) {
            return;
        }

        $html = EmailTemplate::render('comment_notification', [
            'site_name' => config('app.name'),
            'post'      => $post,
            'author'    => $author,
        ]);

        $subject = '[' . config('app.name') . '] 您的文章收到新评论';
        $to = $author->getAttribute('email');

        // 使用 mail() 发送（生产环境应替换为 SMTP/队列驱动）
        @mail($to, $subject, $html, 'Content-Type: text/html; charset=UTF-8');

        \Core\Log\Log::info('Comment notification sent', [
            'to'       => $to,
            'post_id'  => $post->getAttribute('id'),
        ]);
    }
}
