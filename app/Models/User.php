<?php

namespace App\Models;

use Core\Database\Model;

class User extends Model
{
    protected static string $table = 'users';

    protected array $casts = [
        'id' => 'int',
    ];

    public function posts()
    {
        return Post::query()->where('author_id', '=', $this->getAttribute('id'))->get();
    }

    public function displayName(): string
    {
        return $this->getAttribute('display_name') ?: $this->getAttribute('username');
    }

    public function avatarUrl(int $size = 80): string
    {
        $email = $this->getAttribute('email') ?? '';
        $hash = md5(strtolower(trim($email)));
        return "https://www.gravatar.com/avatar/{$hash}?s={$size}&d=identicon";
    }

    public static function boot(): void
    {
        // Hook for plugins to extend User
    }
}
