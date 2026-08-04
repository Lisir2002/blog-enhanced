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
            ->header('Cache-Control', 'public, max-age=300')
            ->header('ETag', '"' . md5($xml) . '"')
            ->setBody($xml);
    }

    public function sitemap(): Response
    {
        $xml = app(\Core\SEO\Sitemap::class)->generate();

        return (new Response())
            ->setContentType('application/xml; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=3600')
            ->header('ETag', '"' . md5($xml) . '"')
            ->setBody($xml);
    }

    public function robots(): Response
    {
        $baseUrl = config('app.url');
        $disallow = ['admin', 'login', 'register', 'api'];
        $lines = ["User-agent: *"];
        foreach ($disallow as $path) {
            $lines[] = "Disallow: /$path";
        }
        $lines[] = "";
        $lines[] = "Sitemap: {$baseUrl}/sitemap.xml";
        $text = implode("\n", $lines) . "\n";

        return (new Response())
            ->setContentType('text/plain; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=3600')
            ->setBody($text);
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
