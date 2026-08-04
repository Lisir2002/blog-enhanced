<?php

namespace Core\SEO;

use App\Models\Post;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Option;

/**
 * Sitemap 生成器 — 自动生成 XML sitemap + robots.txt + 面包屑。
 */
class Sitemap
{
    public function generate(): string
    {
        $urls = [];
        $urls[] = $this->entry(url('/'), '1.0', 'daily', date('c'));

        try {
            $posts = Post::query()
                ->where('status', '=', 'published')
                ->where('published_at', '<=', date('Y-m-d H:i:s'))
                ->orderBy('published_at', 'DESC')->get();
            foreach ($posts as $r) {
                $post = new Post($r);
                $urls[] = $this->entry($post->url(), '0.8', 'monthly',
                    date('c', strtotime($r['updated_at'] ?? $r['created_at'])));
            }
        } catch (\Throwable) {}

        try {
            foreach (Category::all() as $c) {
                $cat = new Category($c);
                $urls[] = $this->entry($cat->url(), '0.6', 'weekly');
            }
        } catch (\Throwable) {}

        try {
            foreach (Tag::all() as $t) {
                $tag = new Tag($t);
                $urls[] = $this->entry($tag->url(), '0.5', 'weekly');
            }
        } catch (\Throwable) {}

        try {
            $pages = app(\Core\Database\QueryBuilder::class)
                ->table('pages')->where('status', '=', 'published')->get();
            foreach ($pages as $p) {
                $urls[] = $this->entry(url('/' . $p['slug']), '0.7', 'monthly',
                    date('c', strtotime($p['updated_at'] ?? $p['created_at'])));
            }
        } catch (\Throwable) {}

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            $xml .= "  <url>\n    <loc>" . e($u['loc']) . "</loc>\n";
            if (isset($u['lastmod'])) $xml .= "    <lastmod>{$u['lastmod']}</lastmod>\n";
            if (isset($u['changefreq'])) $xml .= "    <changefreq>{$u['changefreq']}</changefreq>\n";
            if (isset($u['priority'])) $xml .= "    <priority>{$u['priority']}</priority>\n";
            $xml .= "  </url>\n";
        }
        return $xml . "</urlset>\n";
    }

    private function entry(string $loc, string $priority, string $freq, ?string $lastmod = null): array
    {
        return compact('loc', 'priority', 'freq', 'lastmod') + ['changefreq' => $freq];
    }

    public function robotsTxt(): string
    {
        $lines = ['User-agent: *', 'Allow: /', 'Disallow: /admin', 'Disallow: /api', ''];
        $block = Option::get('robots_disallow', '');
        if ($block) {
            foreach (array_filter(array_map('trim', explode("\n", $block))) as $path) {
                $lines[] = 'Disallow: ' . $path;
            }
        }
        $lines[] = '';
        $lines[] = 'Sitemap: ' . url('/sitemap.xml');
        return implode("\n", $lines) . "\n";
    }

    public static function breadcrumbs(array $items): string
    {
        $html = '<nav class="breadcrumbs" aria-label="面包屑导航"><ol>';
        $last = array_key_last($items);
        foreach ($items as $i => $item) {
            $title = e($item['title'] ?? '');
            if ($i === $last || empty($item['url'])) {
                $html .= '<li class="breadcrumb-item active" aria-current="page">' . $title . '</li>';
            } else {
                $html .= '<li class="breadcrumb-item"><a href="' . e($item['url']) . '">' . $title . '</a></li>';
            }
        }
        $html .= '</ol></nav>';

        $listItems = [];
        foreach ($items as $i => $item) {
            $listItems[] = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $item['title'] ?? '',
                'item' => $item['url'] ?? '',
            ];
        }
        $jsonLd = '<script type="application/ld+json">' . json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $listItems,
        ], JSON_UNESCAPED_UNICODE) . '</script>';

        return $html . $jsonLd;
    }
}
