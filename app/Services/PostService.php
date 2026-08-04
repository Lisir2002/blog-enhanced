<?php

namespace App\Services;

use App\DTO\PostData;
use App\Models\Post;
use App\Models\Tag;
use App\Support\Slugify;
use Core\Database\Connection;
use Core\Database\QueryBuilder;

/**
 * 文章业务逻辑层 — 封装 CRUD + 标签同步 + slug 生成 + 事务管理。
 *
 * Controller 只需：
 *   $dto = PostData::fromRequest($request);
 *   $id = $postService->create($dto, $authorId);
 */
class PostService
{
    public function __construct(
        private \Parsedown $parsedown,
        private Connection $connection,
    ) {}

    /**
     * 创建文章，返回新 ID。
     */
    public function create(PostData $data, int $authorId): int
    {
        $arr = $data->toArray();
        $arr['author_id']   = $authorId;
        $arr['created_at'] = date('Y-m-d H:i:s');
        $arr['updated_at'] = date('Y-m-d H:i:s');

        // slug 生成
        $slug = $arr['slug'] !== '' ? $arr['slug'] : Slugify::make($arr['title']);
        $arr['slug'] = Slugify::unique($slug, 'posts');

        // Markdown → HTML
        $arr['content_html'] = $this->parsedown->text($arr['content_md'] ?? '');

        $pdo = $this->connection->pdo();
        try {
            $pdo->beginTransaction();
            $id = (int) Post::query()->insert($arr);
            $this->syncTags($id, $data->tags);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        // 清除导航缓存（如果有新文章可能影响菜单）
        $this->clearNavCache();

        do_action('post_saved', $id, $arr, false);
        return $id;
    }

    /**
     * 更新文章。
     */
    public function update(int $id, PostData $data): bool
    {
        $post = Post::find($id);
        if (!$post) {
            return false;
        }

        $arr = $data->toArray();
        $arr['updated_at'] = date('Y-m-d H:i:s');

        // 发布时间处理：首次发布才设置
        if ($arr['status'] === 'published' && empty($post->getAttribute('published_at'))) {
            $arr['published_at'] = $arr['published_at'] ?? date('Y-m-d H:i:s');
        }
        if ($arr['status'] === 'archived') {
            $arr['published_at'] = $post->getAttribute('published_at');
        }

        // slug 处理：空则生成，变更则确保唯一
        if ($arr['slug'] === '') {
            $arr['slug'] = Slugify::make($arr['title']);
        }
        if ($arr['slug'] !== $post->getAttribute('slug')) {
            $arr['slug'] = Slugify::unique($arr['slug'], 'posts', 'slug', $id);
        }

        // Markdown → HTML
        $arr['content_html'] = $this->parsedown->text($arr['content_md'] ?? '');

        $pdo = $this->connection->pdo();
        try {
            $pdo->beginTransaction();
            Post::query()->where('id', '=', $id)->update($arr);
            $this->syncTags($id, $data->tags);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        do_action('post_saved', $id, $arr, true);
        return true;
    }

    /**
     * 删除文章 + 清理标签关联。
     */
    public function delete(int $id): bool
    {
        $post = Post::find($id);
        if (!$post) {
            return false;
        }

        $pdo = $this->connection->pdo();
        try {
            $pdo->beginTransaction();
            $post->delete();
            app(QueryBuilder::class)
                ->table('post_tag')
                ->where('post_id', '=', $id)
                ->delete();
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $this->clearNavCache();
        return true;
    }

    /**
     * 同步标签 — 逗号分隔的标签名字符串 → post_tag 关联表。
     */
    public function syncTags(int $postId, string $tagStr): void
    {
        // 先清空旧关联
        app(QueryBuilder::class)
            ->table('post_tag')
            ->where('post_id', '=', $postId)
            ->delete();

        $names = array_filter(array_map('trim', explode(',', $tagStr)));
        $names = array_unique($names);
        if (empty($names)) {
            return;
        }

        foreach ($names as $name) {
            if ($name === '') {
                continue;
            }

            // 查找已有标签
            $tag = Tag::query()->where('name', '=', $name)->first();
            if (!$tag) {
                // 创建新标签
                $slug = Slugify::make($name, 'tag');
                $slug = Slugify::unique($slug, 'tags');
                $now  = date('Y-m-d H:i:s');
                $tagId = app(QueryBuilder::class)
                    ->table('tags')
                    ->insert([
                        'name'       => $name,
                        'slug'       => $slug,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
            } else {
                $tagId = $tag['id'];
            }

            // 插入关联（忽略重复）
            $exists = app(QueryBuilder::class)
                ->table('post_tag')
                ->where('post_id', '=', $postId)
                ->where('tag_id', '=', $tagId)
                ->first();
            if (!$exists) {
                app(QueryBuilder::class)
                    ->table('post_tag')
                    ->insert([
                        'post_id' => $postId,
                        'tag_id'  => $tagId,
                    ]);
            }
        }
    }

    private function clearNavCache(): void
    {
        app(\Core\Cache\CacheInterface::class)->delete('nav_menu');
    }
}
