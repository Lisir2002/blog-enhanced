<?php

namespace Core\Webhook;

use App\Models\Option;

/**
 * Webhook 系统 — 事件发生时推送 HTTP 通知到外部 URL。
 *
 * 用法:
 *   add_action('post_saved', function ($id) {
 *       Webhook::trigger('post.saved', ['post_id' => $id]);
 *   });
 *
 * 配置 (后台 Options):
 *   webhook_endpoints = json array of ['url' => '...', 'events' => ['post.saved', 'comment.created']]
 */
class Webhook
{
    /**
     * 触发 webhook 通知（异步 fire-and-forget）。
     */
    public static function trigger(string $event, array $payload = []): void
    {
        $endpoints = self::getEndpoints();
        $sent = [];

        foreach ($endpoints as $ep) {
            $events = $ep['events'] ?? [];
            if (!empty($events) && !in_array($event, $events, true) && !in_array('*', $events, true)) {
                continue;
            }

            $url = $ep['url'] ?? '';
            if (!$url || isset($sent[$url])) {
                continue;
            }

            $data = json_encode([
                'event' => $event,
                'payload' => $payload,
                'timestamp' => time(),
                'source' => config('app.name', 'Blog CMS'),
            ], JSON_UNESCAPED_UNICODE);

            self::sendAsync($url, $data);
            $sent[$url] = true;
        }
    }

    /**
     * 非阻塞 HTTP POST。
     */
    private static function sendAsync(string $url, string $body): void
    {
        $parts = parse_url($url);
        if (!$parts || !isset($parts['host'])) {
            return;
        }

        $scheme = $parts['scheme'] ?? 'https';
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        $path = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');

        $errno = 0;
        $errstr = '';
        $timeout = 5;

        // 非阻塞连接 + 极短超时
        $fp = @fsockopen($scheme === 'https' ? 'ssl://' . $parts['host'] : $parts['host'], $port, $errno, $errstr, $timeout);
        if (!$fp) {
            // 降级: 用 stream_context 非阻塞
            self::sendStream($url, $body);
            return;
        }

        stream_set_blocking($fp, false);
        $req = "POST $path HTTP/1.1\r\n"
            . "Host: {$parts['host']}\r\n"
            . "Content-Type: application/json\r\n"
            . "Content-Length: " . strlen($body) . "\r\n"
            . "Connection: close\r\n\r\n"
            . $body;
        fwrite($fp, $req);
        fclose($fp);
    }

    private static function sendStream(string $url, string $body): void
    {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $body,
                'timeout' => 3,
                'ignore_errors' => true,
            ],
        ]);
        @file_get_contents($url, false, $ctx);
    }

    private static function getEndpoints(): array
    {
        try {
            $data = Option::get('webhook_endpoints', []);
            return is_array($data) ? $data : [];
        } catch (\Throwable) {
            return [];
        }
    }
}
