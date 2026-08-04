<?php

namespace App\Controllers\Api;

use App\Models\Category;
use App\Models\Tag;
use Core\Http\Response;

class TaxonomyController
{
    public function categories(): Response
    {
        $cats = Category::query()->orderBy('name', 'ASC')->get();
        $items = array_map(function ($c) {
            $cat = new Category($c);
            return [
                'id' => (int) $c['id'],
                'name' => $c['name'],
                'slug' => $c['slug'],
                'description' => $c['description'] ?? '',
                'url' => $cat->url(),
                'post_count' => $cat->postCount(),
            ];
        }, $cats);
        return (new Response())->json(['data' => $items]);
    }

    public function tags(): Response
    {
        $tags = Tag::query()->orderBy('name', 'ASC')->get();
        $items = array_map(function ($t) {
            $tag = new Tag($t);
            return [
                'id' => (int) $t['id'],
                'name' => $t['name'],
                'slug' => $t['slug'],
                'url' => $tag->url(),
                'post_count' => $tag->postCount(),
            ];
        }, $tags);
        return (new Response())->json(['data' => $items]);
    }
}
