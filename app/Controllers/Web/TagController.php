<?php

namespace App\Controllers\Web;

use App\Models\Tag;
use App\Models\Post;
use App\Models\Option;

class TagController
{
    public function show(array $params)
    {
        $tag = Tag::findBy('slug', $params['slug']);
        if (!$tag) {
            return theme_view('404', ['message' => '标签不存在'])->setStatus(404);
        }
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = (int) config('app.per_page', 10);
        $offset = ($page - 1) * $perPage;
        $qb = app(\Core\Database\QueryBuilder::class);
        $rows = $qb->table('posts')
            ->select('posts.*')
            ->join('post_tag', 'post_tag.post_id = posts.id')
            ->where('post_tag.tag_id', '=', $tag->getAttribute('id'))
            ->where('posts.status', '=', 'published')
            ->orderBy('posts.published_at', 'DESC')
            ->limit($perPage)
            ->offset($offset)
            ->get();
        $total = $qb->table('posts')
            ->join('post_tag', 'post_tag.post_id = posts.id')
            ->where('post_tag.tag_id', '=', $tag->getAttribute('id'))
            ->where('posts.status', '=', 'published')
            ->count();
        $totalPages = max(1, (int) ceil($total / $perPage));

        return theme_view('tag', [
            'tag'        => $tag,
            'posts'      => $rows,
            'page'       => $page,
            'totalPages' => $totalPages,
            'pageTitle' => '标签: ' . $tag->getAttribute('name') . ' - ' . Option::get('site_name', config('app.name')),
        ]);
    }
}
