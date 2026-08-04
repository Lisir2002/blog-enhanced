<?php

namespace App\Controllers\Web;

use App\Models\Post;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Comment;
use App\Models\Option;
use Core\Http\Response;

class PostController
{
    public function show(array $params): Response
    {
        $post = Post::findBy('slug', $params['slug']);
        if (!$post || $post->getAttribute('status') !== 'published') {
            return theme_view('404', ['message' => '文章不存在'])->setStatus(404);
        }

        // Increment views (rate-limited by IP ideally)
        if (!isset($_SESSION['viewed_' . $post->getAttribute('id')])) {
            $post->incrementViews();
            $_SESSION['viewed_' . $post->getAttribute('id')] = true;
        }

        $category = $post->category();
        $author   = $post->author();
        $tags     = $post->tags();
        $comments = $post->comments();

        do_action('post_loaded', $post);

        $related = $post->related(5);

        return theme_view('single', [
            'post'      => $post,
            'category'  => $category,
            'author'    => $author,
            'tags'      => $tags,
            'comments'  => $comments,
            'related'   => $related,
            'pageTitle' => $post->getAttribute('seo_title') ?: $post->getAttribute('title') . ' - ' . Option::get('site_name', config('app.name')),
        ]);
    }

}

