<?php
/**
 * 高级辅助函数 — i18n / 缓存 / 图片 / SEO / 邮件 / Webhook。
 */

use Core\i18n\Translator;
use Core\Cache\CacheInterface;
use Core\Email\EmailTemplate;
use Core\Webhook\Webhook;

/* ═══════════════ i18n ═══════════════ */

if (!function_exists('__')) {
    function __(string $key, array $params = []): string
    {
        return app(Translator::class)->translate($key, $params);
    }
}

if (!function_exists('_e')) {
    function _e(string $key, array $params = []): void
    {
        echo __($key, $params);
    }
}

if (!function_exists('set_locale')) {
    function set_locale(string $locale): void
    {
        app(Translator::class)->setLocale($locale);
    }
}

/* ═══════════════ 片段缓存 ═══════════════ */

if (!function_exists('cache_fragment')) {
    function cache_fragment(string $key, int $ttl, callable $callback): string
    {
        $cache = app(\Core\Cache\CacheInterface::class);
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
        app(\Core\Cache\CacheInterface::class)->delete('fragment:' . $key);
    }
}

/* ═══════════════ 图片系统 ═══════════════ */

if (!function_exists('add_image_size')) {
    function add_image_size(string $name, int $width, int $height = 0, bool $crop = false): void
    {
        app(\Core\View\ImageProcessor::class)->addSize($name, $width, $height, $crop);
    }
}

if (!function_exists('post_thumbnail')) {
    function post_thumbnail(\App\Models\Post $post, string $size = 'medium', array $attrs = []): string
    {
        $cover = $post->getAttribute('cover');
        if (!$cover) {
            $featuredId = $post->getAttribute('featured_image_id');
            if ($featuredId) {
                $media = \App\Models\Media::find($featuredId);
                if ($media) {
                    $cover = $media->getAttribute('path');
                }
            }
        }
        if (!$cover) {
            return '';
        }

        $processor = app(\Core\View\ImageProcessor::class);
        $src = asset('themes/default/assets/' . $cover);
        $srcset = '';
        $sizes = $processor->getSizes();
        if (!empty($attrs['srcset'])) {
            $srcsetItems = [];
            foreach ($attrs['srcset'] as $s) {
                if (isset($sizes[$s]) && $sizes[$s]['width'] > 0) {
                    $srcsetItems[] = $src . ' ' . $sizes[$s]['width'] . 'w';
                }
            }
            $srcset = ' srcset="' . implode(', ', $srcsetItems) . '"';
        }

        $lazy = $attrs['loading'] ?? 'lazy';
        $alt = $attrs['alt'] ?? '';
        return '<img src="' . e($src) . '"' . $srcset
            . ' loading="' . e($lazy) . '"'
            . ' alt="' . e($alt) . '" class="post-thumbnail size-' . e($size) . '">';
    }
}

if (!function_exists('has_post_thumbnail')) {
    function has_post_thumbnail(\App\Models\Post $post): bool
    {
        return (bool) ($post->getAttribute('cover') || $post->getAttribute('featured_image_id'));
    }
}

/* ═══════════════ SEO ═══════════════ */

if (!function_exists('breadcrumbs')) {
    function breadcrumbs(array $items): string
    {
        return \Core\SEO\Sitemap::breadcrumbs($items);
    }
}

if (!function_exists('robots_txt')) {
    function robots_txt(): string
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin/',
            'Disallow: /api/',
            'Sitemap: ' . url('/sitemap.xml'),
        ];
        return implode("\n", $lines) . "\n";
    }
}

/* ═══════════════ 邮件 ═══════════════ */

if (!function_exists('render_email')) {
    function render_email(string $templateId, array $data = []): string
    {
        return EmailTemplate::render($templateId, $data);
    }
}

if (!function_exists('send_email')) {
    function send_email(string $to, string $subject, string $html): bool
    {
        $headers = 'Content-Type: text/html; charset=UTF-8' . "\r\n";
        $from = config('mail.from', '');
        if ($from) {
            $headers .= 'From: ' . e($from) . "\r\n";
        }
        try {
            return @mail($to, $subject, $html, $headers);
        } catch (\Throwable) {
            return false;
        }
    }
}

/* ═══════════════ Webhook ═══════════════ */

if (!function_exists('webhook_trigger')) {
    function webhook_trigger(string $event, array $payload = []): void
    {
        Webhook::trigger($event, $payload);
    }
}

/* ═══════════════ 调试器 ═══════════════ */

if (!function_exists('debug_bar')) {
    function debug_bar(): string
    {
        return \Core\View\DebugBar::render();
    }
}
