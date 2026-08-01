<?php

namespace App\Controllers\Web;

use App\Models\Option;
use Core\Http\Response;

class PageController
{
    public function show(array $params): Response
    {
        $slug = $params['slug'] ?? '';
        // Try pages table first
        $qb = app(\Core\Database\QueryBuilder::class);
        $page = $qb->table('pages')->where('slug', '=', $slug)->where('status', '=', 'published')->first();
        if (!$page) {
            return theme_view('404', ['message' => '页面不存在'])->setStatus(404);
        }

        $parsedown = app(\Parsedown::class);
        $html = $parsedown->text($page['content_md'] ?? '');

        return theme_view('page', [
            'page'       => $page,
            'html'       => apply_filters('the_content', $html),
            'pageTitle'  => ($page['seo_title'] ?? $page['title']) . ' - ' . Option::get('site_name', config('app.name')),
        ]);
    }
}
