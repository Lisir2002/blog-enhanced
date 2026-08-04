<?php

namespace Core\Cache;

use Core\Http\Request;
use Core\Http\Response;

/**
 * 整页静态缓存 — 对匿名访问的已发布页面缓存完整 HTML。
 *
 * 缓存键: method + path + query
 * 失效: 文章保存/删除时清除关联缓存
 */
class PageCache
{
    private string $cacheDir;
    private int $ttl;

    public function __construct()
    {
        $this->cacheDir = storage_path('framework/page');
        $this->ttl = (int) config('cache.page_ttl', 3600);
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0777, true);
        }
    }

    public function get(Request $request): ?Response
    {
        if ($this->shouldBypass($request)) {
            return null;
        }

        $key = $this->key($request);
        $file = $this->cacheDir . '/' . $key . '.html';
        if (!is_file($file)) {
            return null;
        }
        if (time() - filemtime($file) > $this->ttl) {
            @unlink($file);
            return null;
        }

        $data = file_get_contents($file) ?: '';
        $meta = json_decode(substr($data, 0, (int) strpos($data, "\n")), true) ?: [];
        $body = substr($data, (int) strpos($data, "\n") + 1);

        $resp = (new Response())->setBody($body)->setStatus($meta['status'] ?? 200);
        $resp->header('Content-Type', $meta['content_type'] ?? 'text/html');
        $resp->header('X-Page-Cache', 'HIT');
        return $resp;
    }

    public function put(Request $request, Response $response): void
    {
        if ($this->shouldBypass($request) || $response->status() >= 400) {
            return;
        }

        $key = $this->key($request);
        $file = $this->cacheDir . '/' . $key . '.html';
        $meta = json_encode([
            'status' => $response->status(),
            'content_type' => $response->headers()['Content-Type'] ?? 'text/html',
        ]);
        file_put_contents($file, $meta . "\n" . $response->getBody());
    }

    public function flush(?string $pattern = null): void
    {
        $files = glob($this->cacheDir . '/*.html') ?: [];
        foreach ($files as $file) {
            if ($pattern === null || str_contains(basename($file), $pattern)) {
                @unlink($file);
            }
        }
    }

    public function flushAll(): void
    {
        $this->flush();
    }

    private function shouldBypass(Request $request): bool
    {
        // 不缓存: POST 请求、后台、登录用户、API
        if ($request->method !== 'GET') {
            return true;
        }
        if (str_starts_with(trim($request->path, '/'), 'admin')) {
            return true;
        }
        if (str_starts_with(trim($request->path, '/'), 'api')) {
            return true;
        }
        if (logged_in()) {
            return true;
        }
        return false;
    }

    private function key(Request $request): string
    {
        $path = trim($request->path, '/');
        $query = '';
        $params = $request->all();
        ksort($params);
        if (!empty($params)) {
            $query = md5(http_build_query($params));
        }
        return md5($path . '|' . $query);
    }
}
