<?php

namespace App\Controllers\Web;

use App\Models\Category;
use App\Models\Post;

class CategoryController
{
    public function show(array $params)
    {
        $category = Category::findBy('slug', $params['slug']);
        if (!$category) {
            return theme_view('404', ['message' => '分类不存在'])->setStatus(404);
        }
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = (int) config('app.per_page', 10);
        $offset = ($page - 1) * $perPage;
        $posts = Post::query()
            ->where('category_id', '=', $category->getAttribute('id'))
            ->where('status', '=', 'published')
            ->orderBy('published_at', 'DESC')
            ->limit($perPage)
            ->offset($offset)
            ->get();
        $total = Post::query()
            ->where('category_id', '=', $category->getAttribute('id'))
            ->where('status', '=', 'published')
            ->count();
        $totalPages = max(1, (int) ceil($total / $perPage));

        return theme_view('category', [
            'category'   => $category,
            'posts'      => $posts,
            'page'       => $page,
            'totalPages' => $totalPages,
            'pageTitle' => $category->getAttribute('name') . ' - ' . Option::get('site_name', config('app.name')),
        ]);
    }
}

use App\Models\Option;
