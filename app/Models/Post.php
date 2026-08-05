<?php

namespace App\Models;

use Core\Database\Model;

class Post extends Model
{
    protected static string $table = 'posts';

    protected static bool $softDelete = true;

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
        // 优先返回已加载的关联（预加载场景）
        if (array_key_exists('author', $this->relations)) {
            return $this->relations['author'];
        }
        $id = $this->getAttribute('author_id');
        return $id ? User::find($id) : null;
    }

    /**
     * BelongsTo 关联 - 用于预加载。
     */
    public function authorRelation(): \Core\Database\Relations\BelongsTo
    {
        return new \Core\Database\Relations\BelongsTo(User::class, $this, 'author_id', 'id');
    }

    public function category(): ?Category
    {
        if (array_key_exists('category', $this->relations)) {
            return $this->relations['category'];
        }
        $id = $this->getAttribute('category_id');
        return $id ? Category::find($id) : null;
    }

    /**
     * BelongsTo 关联 - 用于预加载。
     */
    public function categoryRelation(): \Core\Database\Relations\BelongsTo
    {
        return new \Core\Database\Relations\BelongsTo(Category::class, $this, 'category_id', 'id');
    }

    public function tags(): array
    {
        if (array_key_exists('tags', $this->relations)) {
            return $this->relations['tags'];
        }
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

    /**
     * BelongsToMany 关联 - 用于预加载。
     */
    public function tagsRelation(): \Core\Database\Relations\BelongsToMany
    {
        return new \Core\Database\Relations\BelongsToMany(Tag::class, $this, 'post_tag', 'post_id', 'tag_id', 'id');
    }

    public function comments(): array
    {
        if (array_key_exists('comments', $this->relations)) {
            return $this->relations['comments'];
        }
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

    /**
     * 已审核评论数。
     */
    public function commentCount(): int
    {
        $postId = $this->getAttribute('id');
        if (!$postId) {
            return 0;
        }
        return Comment::query()
            ->where('post_id', '=', $postId)
            ->where('status', '=', 'approved')
            ->count();
    }

    /**
     * 相关文章 — 同分类优先，其次同标签，排除自身。
     *
     * @return array<int, Post>
     */
    public function related(int $limit = 5): array
    {
        $id = $this->getAttribute('id');
        $catId = $this->getAttribute('category_id');
        if (!$id) {
            return [];
        }

        // 先查同分类
        $qb = static::query()
            ->where('id', '!=', $id)
            ->where('status', '=', 'published')
            ->where('published_at', '<=', date('Y-m-d H:i:s'));
        if ($catId) {
            $qb = $qb->where('category_id', '=', $catId);
        }
        $rows = $qb->orderBy('published_at', 'DESC')
            ->limit($limit)
            ->get();

        // 同分类不够则用同标签补
        if (count($rows) < $limit) {
            $tagIds = array_map(
                fn($t) => $t->getAttribute('id'),
                $this->tags(),
            );
            if (!empty($tagIds)) {
                $existing = array_map(fn($r) => $r['id'] ?? null, $rows);
                $tagQb = app(\Core\Database\QueryBuilder::class)
                    ->table('post_tag')
                    ->select('posts.*')
                    ->join('posts', 'posts.id = post_tag.post_id')
                    ->whereIn('post_tag.tag_id', $tagIds)
                    ->where('posts.id', '!=', $id)
                    ->where('posts.status', '=', 'published')
                    ->where('posts.published_at', '<=', date('Y-m-d H:i:s'));
                if (!empty($existing)) {
                    $tagQb = $tagQb->whereNotIn('posts.id', $existing);
                }
                $extra = $tagQb
                    ->orderBy('posts.published_at', 'DESC')
                    ->limit($limit - count($rows))
                    ->get();
                $rows = array_merge($rows, $extra);
            }
        }

        return array_map(fn($r) => new static($r), $rows);
    }

    public function incrementViews(): void
    {
        $id = $this->getAttribute('id');
        if (!$id) {
            return;
        }
        // IP 去重：同一 IP + 文章 ID 30 分钟内只算一次
        /** @var \Core\Cache\CacheInterface $cache */
        $cache = app(\Core\Cache\CacheInterface::class);
        $ip = app(\Core\Http\Request::class)->ip();
        $key = 'viewed:' . $id . ':' . md5($ip);
        if ($cache->has($key)) {
            return;
        }
        $cache->set($key, true, 1800);

        $pdo = app(\Core\Database\Connection::class)->pdo();
        $stmt = $pdo->prepare('UPDATE posts SET views = views + 1 WHERE id = :id');
        $stmt->execute([':id' => $id]);
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
