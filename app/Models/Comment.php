<?php

namespace App\Models;

use Core\Database\Model;

class Comment extends Model
{
    protected static string $table = 'comments';
    protected array $casts = ['id' => 'int', 'post_id' => 'int', 'parent_id' => 'int'];

    public function post(): ?Post
    {
        $pid = $this->getAttribute('post_id');
        return $pid ? Post::find($pid) : null;
    }

    public function author(): string
    {
        $u = current_user();
        if ($u) {
            return $u->displayName();
        }
        return $this->getAttribute('author_name') ?: '匿名';
    }

    public function html(): string
    {
        $content = (string) $this->getAttribute('content');
        $content = nl2br(e($content));
        return (string) apply_filters('comment_text', $content, $this);
    }

    public function replies(): array
    {
        $cid = $this->getAttribute('id');
        if (!$cid) {
            return [];
        }
        $rows = static::query()
            ->where('parent_id', '=', $cid)
            ->where('status', '=', 'approved')
            ->orderBy('created_at', 'ASC')
            ->get();
        return array_map(fn($r) => new static($r), $rows);
    }
}
