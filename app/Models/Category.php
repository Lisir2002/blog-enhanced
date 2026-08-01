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
}
