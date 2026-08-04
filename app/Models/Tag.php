<?php

namespace App\Models;

use Core\Database\Model;

class Tag extends Model
{
    protected static string $table = 'tags';
    protected array $casts = ['id' => 'int'];

    public function url(): string
    {
        return route('tag.show', ['slug' => $this->getAttribute('slug')]);
    }

    public function postCount(): int
    {
        return app(\Core\Database\QueryBuilder::class)
            ->table('post_tag')
            ->join('posts', 'posts.id = post_tag.post_id')
            ->where('post_tag.tag_id', '=', $this->getAttribute('id'))
            ->where('posts.status', '=', 'published')
            ->count();
    }

    /**
     * 该标签下的已发布文章。
     *
     * @return array<int, Post>
     */
    public function posts(int $page = 1, int $perPage = 10): array
    {
        $offset = ($page - 1) * $perPage;
        $rows = app(\Core\Database\QueryBuilder::class)
            ->table('post_tag')
            ->select('posts.*')
            ->join('posts', 'posts.id = post_tag.post_id')
            ->where('post_tag.tag_id', '=', $this->getAttribute('id'))
            ->where('posts.status', '=', 'published')
            ->where('posts.published_at', '<=', date('Y-m-d H:i:s'))
            ->orderBy('posts.published_at', 'DESC')
            ->limit($perPage)
            ->offset($offset)
            ->get();
        return array_map(fn($r) => new Post($r), $rows);
    }
}
