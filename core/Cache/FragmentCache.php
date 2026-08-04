<?php

namespace Core\Cache;

/**
 * 片段缓存 — 缓存模板片段的 HTML 输出。
 *
 * 用法（模板内）：
 *   cache_fragment('sidebar.recent', 3600, function () use ($posts) {
 *       return render_partial('partials/recent-posts', ['posts' => $posts]);
 *   });
 *
 * 失效:
 *   app(\Core\Cache\CacheInterface::class)->delete('fragment:sidebar.recent');
 *   或: add_action('post_saved', fn() => cache_forget('sidebar.recent'));
 */

use Core\Cache\CacheInterface;

if (!function_exists('cache_fragment')) {
    function cache_fragment(string $key, int $ttl, callable $callback): string
    {
        $cache = app(CacheInterface::class);
        $cacheKey = 'fragment:' . $key;
        $content = $cache->get($cacheKey);
        if ($content !== null) {
            return $content;
        }
        $content = (string) $callback();
        $cache->set($cacheKey, $content, $ttl);
        return $content;
    }
}

if (!function_exists('cache_forget')) {
    function cache_forget(string $key): void
    {
        app(CacheInterface::class)->delete('fragment:' . $key);
    }
}
