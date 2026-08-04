<?php

namespace App\Models;

use Core\Database\Model;

class Category extends Model
{
    protected static string $table = 'categories';
    protected array $casts = ['id' => 'int'];

    public function url(): string
    {
        return route('category.show', ['slug' => $this->getAttribute('slug')]);
    }

    public function postCount(): int
    {
        return Post::query()
            ->where('category_id', '=', $this->getAttribute('id'))
            ->where('status', '=', 'published')
            ->count();
    }

    /**
     * 该分类下的已发布文章。
     *
     * @return array<int, Post>
     */
    public function posts(int $page = 1, int $perPage = 10): array
    {
        $offset = ($page - 1) * $perPage;
        $rows = Post::query()
            ->where('category_id', '=', $this->getAttribute('id'))
            ->where('status', '=', 'published')
            ->where('published_at', '<=', date('Y-m-d H:i:s'))
            ->orderBy('published_at', 'DESC')
            ->limit($perPage)
            ->offset($offset)
            ->get();
        return array_map(fn($r) => new Post($r), $rows);
    }
}
