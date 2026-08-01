<?php

namespace App\Controllers\Web;

use App\Models\Post;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Option;
use Core\Http\Response;

class HomeController
{
    public function index(array $params = []): Response
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = (int) config('app.per_page', 10);

        $posts = Post::published($page, $perPage);
        $total = Post::publishedCount();
        $totalPages = max(1, (int) ceil($total / $perPage));

        // Recent categories & tags for sidebar
        $categories = Category::all();
        $tags = Tag::all();

        do_action('home_loaded', $posts, $page, $totalPages);

        return theme_view('home', [
            'posts'       => $posts,
            'page'        => $page,
            'totalPages'  => $totalPages,
            'categories'  => $categories,
            'tags'        => $tags,
            'pageTitle'   => Option::get('site_name', config('app.name')) . ' - 首页',
        ]);
    }
}
