<?php

namespace App\Controllers\Api;

use App\Models\Post;
use App\Models\Category;
use App\Models\Tag;
use Core\Http\Request;
use Core\Http\Response;

/**
 * REST API — 文章 CRUD + 分类/标签查询。
 *
 * 路由: /api/posts, /api/posts/{slug}, /api/categories, /api/tags
 */
class PostController
{
    public function index(): Response
    {
        $request = app(\Core\Http\Request::class);
        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(50, max(1, (int) $request->input('per_page', 10)));

        $qb = Post::query()
            ->where('status', '=', 'published')
            ->where('published_at', '<=', date('Y-m-d H:i:s'));

        $categoryId = $request->input('category_id');
        if ($categoryId) {
            $qb = $qb->where('category_id', '=', (int) $categoryId);
        }

        $tagId = $request->input('tag_id');
        if ($tagId) {
            $qb = $qb->join('post_tag', 'post_tag.post_id = posts.id')
                ->where('post_tag.tag_id', '=', (int) $tagId);
        }

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $qb = $qb->where('title', 'LIKE', '%' . $search . '%');
        }

        $total = $qb->count();
        $posts = $qb->orderBy('published_at', 'DESC')
            ->limit($perPage)->offset(($page - 1) * $perPage)->get();

        $items = array_map(function ($r) {
            $post = new Post($r);
            $arr = $post->toArray();
            $arr['id'] = (int) $r['id'];
            $arr['status'] = $r['status'];
            $arr['views'] = (int) $r['views'];
            $arr['published_at'] = $r['published_at'];
            $arr['created_at'] = $r['created_at'];
            return $arr;
        }, $posts);

        return (new Response())->json([
            'data' => $items,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => max(1, (int) ceil($total / $perPage)),
            ],
        ]);
    }

    public function show(array $params): Response
    {
        $post = Post::findBy('slug', $params['slug']);
        if (!$post || $post->getAttribute('status') !== 'published') {
            return (new Response())->json(['error' => 'Not found'], 404);
        }

        $arr = $post->toArray();
        $arr['id'] = (int) $post->getAttribute('id');
        $arr['status'] = $post->getAttribute('status');
        $arr['views'] = (int) $post->getAttribute('views');
        $arr['content_html'] = $post->html();
        $arr['published_at'] = $post->getAttribute('published_at');
        $arr['created_at'] = $post->getAttribute('created_at');

        $category = $post->category();
        if ($category) {
            $arr['category'] = [
                'id' => (int) $category->getAttribute('id'),
                'name' => $category->getAttribute('name'),
                'slug' => $category->getAttribute('slug'),
            ];
        }

        $tags = $post->tags();
        $arr['tags'] = array_map(fn($t) => [
            'id' => (int) $t->getAttribute('id'),
            'name' => $t->getAttribute('name'),
            'slug' => $t->getAttribute('slug'),
        ], $tags);

        $author = $post->author();
        if ($author) {
            $arr['author'] = [
                'id' => (int) $author->getAttribute('id'),
                'name' => $author->displayName(),
                'avatar' => $author->avatarUrl(80),
            ];
        }

        return (new Response())->json($arr);
    }

    public function search(): Response
    {
        $request = app(Request::class);
        $q = trim((string) $request->input('q', ''));
        if ($q === '') {
            return (new Response())->json(['data' => []]);
        }

        $conn = app(\Core\Database\Connection::class);
        $pdo = $conn->pdo();
        $like = '%' . $q . '%';
        $now = date('Y-m-d H:i:s');
        $limit = min(10, max(1, (int) $request->input('limit', 5)));

        $sql = "SELECT id, title, slug, excerpt, published_at
                FROM posts
                WHERE status = 'published' AND published_at <= ?
                  AND (title LIKE ? OR excerpt LIKE ?)
                ORDER BY published_at DESC
                LIMIT ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$now, $like, $like, $limit]);
        $posts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return (new Response())->json([
            'data' => array_map(fn($r) => [
                'id'       => (int) $r['id'],
                'title'    => $r['title'],
                'slug'     => $r['slug'],
                'excerpt'  => mb_substr(strip_tags($r['excerpt'] ?: $r['title']), 0, 120),
                'url'      => url('/posts/' . $r['slug']),
                'published_at' => $r['published_at'],
            ], $posts),
            'query' => $q,
        ]);
    }
}
