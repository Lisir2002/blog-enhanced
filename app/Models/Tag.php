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
}
