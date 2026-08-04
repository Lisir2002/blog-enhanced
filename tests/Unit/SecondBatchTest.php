<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\DTO\PostData;
use App\Models\Post;
use App\Services\PostService;
use App\Support\Slugify;

/**
 * 第二批重构验证：PostService + PostData DTO + Slugify + ServiceProvider。
 */
class SecondBatchTest extends TestCase
{
    private PostService $postService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->postService = $this->app->get(PostService::class);
    }

    /* ─────────── Slugify ─────────── */

    public function test_slugify_english(): void
    {
        $this->assertSame('hello-world', Slugify::make('Hello World'));
    }

    public function test_slugify_chinese_returns_hex(): void
    {
        $result = Slugify::make('你好世界');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $result);
    }

    public function test_slugify_empty_returns_prefix(): void
    {
        $result = Slugify::make('');
        $this->assertStringStartsWith('post-', $result);
    }

    public function test_slugify_with_custom_prefix(): void
    {
        $result = Slugify::make('', 'tag');
        $this->assertStringStartsWith('tag-', $result);
    }

    /* ─────────── PostData DTO ─────────── */

    public function test_post_data_empty_title_is_invalid(): void
    {
        $dto = new PostData();
        $dto->title = '';
        $dto->status = 'draft';
        $this->assertFalse($dto->isValid());
        $errors = $dto->errors();
        $this->assertArrayHasKey('title', $errors);
    }

    public function test_post_data_valid_post_is_valid(): void
    {
        $dto = $this->makeValidPostData();
        $this->assertTrue($dto->isValid());
        $this->assertEmpty($dto->errors());
    }

    public function test_post_data_to_array_excludes_tags(): void
    {
        $dto = $this->makeValidPostData();
        $dto->tags = 'PHP, Laravel';
        $arr = $dto->toArray();
        $this->assertArrayNotHasKey('tags', $arr);
        $this->assertSame('Test Title', $arr['title']);
    }

    /* ─────────── PostService ─────────── */

    public function test_post_service_create(): void
    {
        $this->initializeDatabase();
        $this->seedData();
        $dto = $this->makeValidPostData();
        $id = $this->postService->create($dto, 1);
        $this->assertGreaterThan(0, $id);
    }

    public function test_post_service_create_and_find(): void
    {
        $this->initializeDatabase();
        $this->seedData();
        $dto = $this->makeValidPostData();
        $id = $this->postService->create($dto, 1);

        $post = Post::find($id);
        $this->assertNotNull($post);
        $this->assertSame('Test Title', $post->getAttribute('title'));
        $this->assertSame('published', $post->getAttribute('status'));
        $this->assertNotEmpty($post->getAttribute('slug'));
        $this->assertNotEmpty($post->getAttribute('content_html'));
    }

    public function test_post_service_update(): void
    {
        $this->initializeDatabase();
        $this->seedData();
        $dto = $this->makeValidPostData();
        $id = $this->postService->create($dto, 1);

        $dto->title = 'Updated Title';
        $ok = $this->postService->update($id, $dto);
        $this->assertTrue($ok);

        $post = Post::find($id);
        $this->assertSame('Updated Title', $post->getAttribute('title'));
    }

    public function test_post_service_delete(): void
    {
        $this->initializeDatabase();
        $this->seedData();
        $dto = $this->makeValidPostData();
        $id = $this->postService->create($dto, 1);

        $ok = $this->postService->delete($id);
        $this->assertTrue($ok);
        $this->assertNull(Post::find($id));
    }

    public function test_post_service_sync_tags(): void
    {
        $this->initializeDatabase();
        $this->seedData();
        $dto = $this->makeValidPostData();
        $dto->tags = 'PHP, Laravel, Testing';
        $id = $this->postService->create($dto, 1);

        $post = Post::find($id);
        $tags = $post->tags();
        $this->assertCount(3, $tags);

        $names = array_map(fn($t) => $t->getAttribute('name'), $tags);
        $this->assertContains('PHP', $names);
        $this->assertContains('Laravel', $names);
        $this->assertContains('Testing', $names);
    }

    /* ─────────── ServiceProvider ─────────── */

    public function test_providers_registered_all_core_services(): void
    {
        $this->assertNotNull($this->app->get(\Core\Http\Session::class));
        $this->assertNotNull($this->app->get(\Core\Http\Request::class));
        $this->assertNotNull($this->app->get(\Core\Database\Connection::class));
        $this->assertNotNull($this->app->get(\Core\Router::class));
        $this->assertNotNull($this->app->get(\Core\View\ViewRenderer::class));
        $this->assertNotNull($this->app->get(\Core\Auth\AuthManager::class));
        $this->assertNotNull($this->app->get(\Core\Hook\Action::class));
        $this->assertNotNull($this->app->get(\Core\Hook\Filter::class));
        $this->assertNotNull($this->app->get(\Parsedown::class));
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

    private function seedData(): void
    {
        $now = date('Y-m-d H:i:s');
        $pdo = $this->app->get(\Core\Database\Connection::class)->pdo();
        $pdo->exec("INSERT INTO users (username, email, password, role, status, created_at, updated_at)
            VALUES ('author', 'author@example.com', 'x', 'editor_admin', 'active', '$now', '$now')");
        $pdo->exec("INSERT INTO categories (name, slug, created_at, updated_at)
            VALUES ('Tech', 'tech', '$now', '$now')");
    }
}
