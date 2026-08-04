<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\DTO\PostData;
use App\Models\Post;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use App\Models\Comment;
use App\Services\PostService;
use App\Support\Slugify;
use Core\Database\Migrator;

/**
 * 第三批验证：Migration 系统 + Feature 测试。
 */
class ThirdBatchTest extends TestCase
{
    private PostService $postService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->postService = $this->app->get(PostService::class);
    }

    /* ─────────── Migration 系统 ─────────── */

    public function test_migrator_creates_tables(): void
    {
        $migrator = $this->app->get(Migrator::class);

        // Run migrations — should create all tables
        $ran = $migrator->run();
        $this->assertNotEmpty($ran, 'First run should execute the initial migration');

        // Verify tables exist
        $pdo = $this->app->get(\Core\Database\Connection::class)->pdo();
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll();
        $tableNames = array_column($tables, 'name');
        $this->assertContains('users', $tableNames);
        $this->assertContains('posts', $tableNames);
        $this->assertContains('migrations', $tableNames);

        // Status should show as ran
        $status = $migrator->status();
        $this->assertTrue($status[0]['ran']);
    }

    public function test_migrator_run_is_idempotent(): void
    {
        $migrator = $this->app->get(Migrator::class);

        // First run creates all tables (IF NOT EXISTS = safe)
        $ran1 = $migrator->run();
        $this->assertNotEmpty($ran1);

        // Second run should be no-op (migration already recorded)
        $ran2 = $migrator->run();
        $this->assertEmpty($ran2, 'Second run should find nothing to migrate');
    }

    public function test_migrator_status_shows_pending_and_ran(): void
    {
        $migrator = $this->app->get(Migrator::class);
        $status = $migrator->status();

        $this->assertIsArray($status);
        // 初始化 schema 后，初始迁移应该标记为已执行
        $first = $status[0];
        $this->assertArrayHasKey('migration', $first);
        $this->assertArrayHasKey('ran', $first);
        $this->assertArrayHasKey('batch', $first);
    }

    /* ─────────── PostService 完整流程 ─────────── */

    public function test_post_service_full_lifecycle(): void
    {
        $this->initializeDatabase();
        $this->seedPostServiceData();

        // Create
        $dto = $this->makeValidPostData();
        $id = $this->postService->create($dto, 1);
        $this->assertGreaterThan(0, $id);

        // Find
        $post = Post::find($id);
        $this->assertNotNull($post);
        $this->assertSame('Test Title', $post->getAttribute('title'));
        $this->assertNotSame('', $post->getAttribute('slug'));
        $this->assertNotSame('', $post->getAttribute('content_html'));

        // Update
        $updateDto = $this->makeValidPostData();
        $updateDto->title = 'Updated Title';
        $updateDto->slug = '';
        $updated = $this->postService->update($id, $updateDto);
        $this->assertTrue($updated);

        $post2 = Post::find($id);
        $this->assertSame('Updated Title', $post2->getAttribute('title'));

        // Delete
        $deleted = $this->postService->delete($id);
        $this->assertTrue($deleted);
        $this->assertNull(Post::find($id));
    }

    public function test_post_service_sync_tags(): void
    {
        $this->initializeDatabase();
        $this->seedPostServiceData();

        $dto = $this->makeValidPostData();
        $dto->tags = 'PHP, Laravel, Testing';
        $id = $this->postService->create($dto, 1);

        $post = Post::find($id);
        $tags = $post->tags();
        $this->assertCount(3, $tags);

        $names = array_map(fn($t) => $t->getAttribute('name'), $tags);
        sort($names);
        $this->assertSame(['Laravel', 'PHP', 'Testing'], $names);

        // Update tags: remove one, add another
        $updateDto = $this->makeValidPostData();
        $updateDto->tags = 'PHP, Vue, Testing';
        $this->postService->update($id, $updateDto);

        $post2 = Post::find($id);
        $tags2 = $post2->tags();
        $this->assertCount(3, $tags2);

        $names2 = array_map(fn($t) => $t->getAttribute('name'), $tags2);
        sort($names2);
        $this->assertSame(['PHP', 'Testing', 'Vue'], $names2);
    }

    public function test_post_service_empty_tags_clears_all(): void
    {
        $this->initializeDatabase();
        $this->seedPostServiceData();

        $dto = $this->makeValidPostData();
        $dto->tags = 'A, B, C';
        $id = $this->postService->create($dto, 1);
        $this->assertCount(3, Post::find($id)->tags());

        $updateDto = $this->makeValidPostData();
        $updateDto->tags = '';
        $this->postService->update($id, $updateDto);
        $this->assertCount(0, Post::find($id)->tags());
    }

    /* ─────────── Model 关系方法 ─────────── */

    public function test_category_posts_returns_published_only(): void
    {
        $this->initializeDatabase();
        $this->seedPostServiceData();

        // Create published + draft posts in category 1
        $dto1 = $this->makeValidPostData();
        $dto1->status = 'published';
        $dto1->published_at = date('Y-m-d H:i:s');
        $this->postService->create($dto1, 1);

        $dto2 = $this->makeValidPostData();
        $dto2->title = 'Draft Post';
        $dto2->status = 'draft';
        $dto2->published_at = null;
        $this->postService->create($dto2, 1);

        $cat = Category::find(1);
        $posts = $cat->posts(1, 10);
        $this->assertCount(1, $posts, 'Category::posts() should return only published');
        $this->assertSame('Test Title', $posts[0]->getAttribute('title'));
    }

    public function test_tag_posts_returns_published_only(): void
    {
        $this->initializeDatabase();
        $this->seedPostServiceData();

        $dto = $this->makeValidPostData();
        $dto->tags = 'PHP';
        $postId = $this->postService->create($dto, 1);

        $tag = Tag::query()->where('name', '=', 'PHP')->first();
        $this->assertNotNull($tag);
        $tagObj = new Tag($tag);
        $posts = $tagObj->posts(1, 10);
        $this->assertCount(1, $posts);
        $this->assertSame($postId, $posts[0]->getAttribute('id'));
    }

    public function test_post_comment_count(): void
    {
        $this->initializeDatabase();
        $this->seedPostServiceData();

        $dto = $this->makeValidPostData();
        $id = $this->postService->create($dto, 1);

        $now = date('Y-m-d H:i:s');
        $pdo = $this->app->get(\Core\Database\Connection::class)->pdo();
        $pdo->exec("INSERT INTO comments (post_id, author_name, author_email, content, status, created_at)
            VALUES ($id, 'Alice', 'a@ex.com', 'Nice!', 'approved', '$now')");
        $pdo->exec("INSERT INTO comments (post_id, author_name, author_email, content, status, created_at)
            VALUES ($id, 'Bob', 'b@ex.com', 'Cool!', 'pending', '$now')");

        $post = Post::find($id);
        $this->assertSame(1, $post->commentCount(), 'commentCount should only count approved comments');
    }

    public function test_post_related_returns_same_category(): void
    {
        $this->initializeDatabase();
        $this->seedPostServiceData();

        // Create 3 posts in same category
        $dto1 = $this->makeValidPostData();
        $id1 = $this->postService->create($dto1, 1);

        $dto2 = $this->makeValidPostData();
        $dto2->title = 'Second Post';
        $id2 = $this->postService->create($dto2, 1);

        $dto3 = $this->makeValidPostData();
        $dto3->title = 'Third Post';
        $id3 = $this->postService->create($dto3, 1);

        $post = Post::find($id1);
        $related = $post->related(5);
        $relatedIds = array_map(fn($p) => $p->getAttribute('id'), $related);

        $this->assertNotContains($id1, $relatedIds, 'related() should exclude self');
        $this->assertContains($id2, $relatedIds);
        $this->assertContains($id3, $relatedIds);
    }

    public function test_user_post_count_and_comments(): void
    {
        $this->initializeDatabase();
        $this->seedPostServiceData();

        $dto1 = $this->makeValidPostData();
        $dto1->status = 'published';
        $this->postService->create($dto1, 1);

        $dto2 = $this->makeValidPostData();
        $dto2->title = 'Draft';
        $dto2->status = 'draft';
        $this->postService->create($dto2, 1);

        $user = User::find(1);
        $this->assertSame(1, $user->postCount(), 'postCount should only count published');

        // Test comments via email match
        $now = date('Y-m-d H:i:s');
        $pdo = $this->app->get(\Core\Database\Connection::class)->pdo();
        $pdo->exec("INSERT INTO comments (post_id, author_name, author_email, content, status, created_at)
            VALUES (1, 'Author', 'author@example.com', 'Self comment', 'approved', '$now')");

        $comments = $user->comments();
        $this->assertCount(1, $comments);
    }

    public function test_comment_replies(): void
    {
        $this->initializeDatabase();
        $this->seedPostServiceData();

        $dto = $this->makeValidPostData();
        $id = $this->postService->create($dto, 1);

        $now = date('Y-m-d H:i:s');
        $pdo = $this->app->get(\Core\Database\Connection::class)->pdo();
        $pdo->exec("INSERT INTO comments (post_id, author_name, author_email, content, status, parent_id, created_at)
            VALUES ($id, 'Alice', 'a@ex.com', 'Parent', 'approved', 0, '$now')");
        $parentId = (int) $pdo->lastInsertId();
        $pdo->exec("INSERT INTO comments (post_id, author_name, author_email, content, status, parent_id, created_at)
            VALUES ($id, 'Bob', 'b@ex.com', 'Reply 1', 'approved', $parentId, '$now')");
        $pdo->exec("INSERT INTO comments (post_id, author_name, author_email, content, status, parent_id, created_at)
            VALUES ($id, 'Charlie', 'c@ex.com', 'Reply 2', 'pending', $parentId, '$now')");

        $parent = Comment::find($parentId);
        $replies = $parent->replies();
        $this->assertCount(1, $replies, 'replies() should only return approved replies');
        $this->assertSame('Reply 1', $replies[0]->getAttribute('content'));
    }

    public function test_slugify_unique(): void
    {
        $this->initializeDatabase();
        $this->seedPostServiceData();

        // Insert a tag with slug 'php'
        $now = date('Y-m-d H:i:s');
        $pdo = $this->app->get(\Core\Database\Connection::class)->pdo();
        $pdo->exec("INSERT INTO tags (name, slug, created_at, updated_at) VALUES ('PHP', 'php', '$now', '$now')");

        // unique() should append -2
        $slug = Slugify::unique('php', 'tags');
        $this->assertSame('php-2', $slug);

        // With exceptId, should not conflict with existing
        $slug2 = Slugify::unique('php', 'tags', 'slug', 1);
        $this->assertSame('php', $slug2);
    }

    /* ─────────── helpers ─────────── */

    private function makeValidPostData(): PostData
    {
        $dto = new PostData();
        $dto->title = 'Test Title';
        $dto->slug = '';
        $dto->content_md = '# Hello World';
        $dto->excerpt = '';
        $dto->cover = '';
        $dto->category_id = 1;
        $dto->status = 'published';
        $dto->seo_title = '';
        $dto->seo_description = '';
        $dto->published_at = date('Y-m-d H:i:s');
        $dto->tags = '';
        return $dto;
    }

    private function seedPostServiceData(): void
    {
        $now = date('Y-m-d H:i:s');
        $pdo = $this->app->get(\Core\Database\Connection::class)->pdo();
        $pdo->exec("INSERT INTO users (username, email, password, role, status, created_at, updated_at)
            VALUES ('author', 'author@example.com', 'x', 'editor_admin', 'active', '$now', '$now')");
        $pdo->exec("INSERT INTO categories (name, slug, created_at, updated_at)
            VALUES ('Tech', 'tech', '$now', '$now')");
    }
}
