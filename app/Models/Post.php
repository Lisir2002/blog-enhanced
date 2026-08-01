<?php

namespace App\Models;

use Core\Database\Model;

class Post extends Model
{
    protected static string $table = 'posts';

    protected array $casts = [
        'id' => 'int',
        'author_id' => 'int',
        'category_id' => 'int',
        'views' => 'int',
    ];

    /**
     * 取文章 HTML 内容（Markdown → HTML）。
     */
    public function html(): string
    {
        $content = (string) ($this->getAttribute('content_md') ?? '');
        $parsed = app(\Parsedown::class)->text($content);
        return (string) apply_filters('the_content', $parsed, $this);
    }

    public function excerpt(int $length = 200): string
    {
        $excerpt = $this->getAttribute('excerpt');
        if ($excerpt) {
            return $excerpt;
        }
        $content = strip_tags($this->html());
        if (mb_strlen($content) <= $length) {
            return $content;
        }
        return mb_substr($content, 0, $length) . '...';
    }

    public function url(): string
    {
        return route('post.show', ['slug' => $this->getAttribute('slug')]);
    }

    public function author(): ?User
    {
        $id = $this->getAttribute('author_id');
        return $id ? User::find($id) : null;
    }

    public function category(): ?Category
    {
        $id = $this->getAttribute('category_id');
        return $id ? Category::find($id) : null;
    }

    public function tags(): array
    {
        $postId = $this->getAttribute('id');
        if (!$postId) {
            return [];
        }
        $qb = app(\Core\Database\QueryBuilder::class);
        $rows = $qb->table('post_tag')
            ->select('tags.*')
            ->join('tags', 'tags.id = post_tag.tag_id')
            ->where('post_tag.post_id', '=', $postId)
            ->get();
        return array_map(fn($r) => new Tag($r), $rows);
    }

    public function comments(): array
    {
        $postId = $this->getAttribute('id');
        if (!$postId) {
            return [];
        }
        $rows = Comment::query()
            ->where('post_id', '=', $postId)
            ->where('status', '=', 'approved')
            ->orderBy('created_at', 'ASC')
            ->get();
        return array_map(fn($r) => new Comment($r), $rows);
    }

    public function incrementViews(): void
    {
        $id = $this->getAttribute('id');
        if (!$id) {
            return;
        }
        $pdo = app(\Core\Database\Connection::class)->pdo();
        $pdo->exec("UPDATE posts SET views = views + 1 WHERE id = " . (int) $id);
    }

    public static function published(int $page = 1, int $perPage = 10): array
    {
        $offset = ($page - 1) * $perPage;
        return static::query()
            ->where('status', '=', 'published')
            ->where('published_at', '<=', date('Y-m-d H:i:s'))
            ->orderBy('published_at', 'DESC')
            ->limit($perPage)
            ->offset($offset)
            ->get();
    }

    public static function publishedCount(): int
    {
        return static::query()
            ->where('status', '=', 'published')
            ->where('published_at', '<=', date('Y-m-d H:i:s'))
            ->count();
    }

    public function toArray(): array
    {
        $arr = parent::toArray();
        $arr['url'] = $this->url();
        $arr['excerpt'] = $this->excerpt();
        $author = $this->author();
        $arr['author_name'] = $author?->displayName();
        $cat = $this->category();
        $arr['category_name'] = $cat?->getAttribute('name');
        return $arr;
    }
}
