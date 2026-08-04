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

    /**
     * 用户已发布文章数。
     */
    public function postCount(): int
    {
        return Post::query()
            ->where('author_id', '=', $this->getAttribute('id'))
            ->where('status', '=', 'published')
            ->count();
    }

    /**
     * 用户发表的评论（按邮箱匹配）。
     *
     * @return array<int, Comment>
     */
    public function comments(): array
    {
        $email = $this->getAttribute('email');
        if (!$email) {
            return [];
        }
        $rows = Comment::query()
            ->where('author_email', '=', $email)
            ->orderBy('created_at', 'DESC')
            ->get();
        return array_map(fn($r) => new Comment($r), $rows);
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
