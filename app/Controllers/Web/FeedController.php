<?php

namespace App\Controllers\Web;

use App\Models\Post;
use App\Models\Option;
use Core\Http\Response;

class FeedController
{
    public function rss(): Response
    {
        $posts = Post::published(1, 20);
        $siteName = Option::get('site_name', config('app.name'));
        $siteDesc = Option::get('site_description', '最新文章');
        $baseUrl = config('app.url');

        $items = '';
        foreach ($posts as $post) {
            $p = new \App\Models\Post($post);
            $url = $p->url();
            $items .= <<<XML
    <item>
      <title><![CDATA[{$p->getAttribute('title')}]]></title>
      <link>{$url}</link>
      <guid isPermaLink="true">{$url}</guid>
      <description><![CDATA[{$p->excerpt(300)}]]></description>
      <pubDate>{$this->rssDate($p->getAttribute('published_at'))}</pubDate>
    </item>

XML;
        }

        $lastBuild = date('D, d M Y H:i:s +0000');
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title><![CDATA[{$siteName}]]></title>
    <link>{$baseUrl}</link>
    <description><![CDATA[{$siteDesc}]]></description>
    <language>zh-CN</language>
    <lastBuildDate>{$lastBuild}</lastBuildDate>
{$items}  </channel>
</rss>
XML;

        return (new Response())
            ->setContentType('application/rss+xml; charset=UTF-8')
            ->setBody($xml);
    }

    public function sitemap(): Response
    {
        $baseUrl = config('app.url');
        $posts = Post::published(1, 1000);

        $urls = "  <url>\n    <loc>{$baseUrl}/</loc>\n    <changefreq>daily</changefreq>\n    <priority>1.0</priority>\n  </url>\n";

        foreach ($posts as $post) {
            $p = new \App\Models\Post($post);
            $url = $p->url();
            $date = substr((string) $p->getAttribute('published_at'), 0, 10);
            $urls .= "  <url>\n    <loc>{$url}</loc>\n    <lastmod>{$date}</lastmod>\n    <changefreq>weekly</changefreq>\n    <priority>0.8</priority>\n  </url>\n";
        }

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
{$urls}</urlset>
XML;

        return (new Response())
            ->setContentType('application/xml; charset=UTF-8')
            ->setBody($xml);
    }

    private function rssDate(?string $datetime): string
    {
        if (!$datetime) {
            return date('D, d M Y H:i:s +0000');
        }
        $ts = strtotime($datetime);
        return gmdate('D, d M Y H:i:s +0000', $ts ?: time());
    }
}
