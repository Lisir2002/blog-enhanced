<?php

namespace Tests\Unit;

use Tests\TestCase;
use Core\Http\Middleware\AuthMiddleware;
use Core\Http\Middleware\GuestMiddleware;
use Core\Http\Middleware\CsrfMiddleware;
use Core\Http\Middleware\AdminMiddleware;
use Core\Http\Middleware\MiddlewareInterface;
use App\Models\Post;
use App\Models\User;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Comment;

/**
 * 第一批重构验证：中间件类 + Model 关系方法 + helpers 拆分。
 */
class FirstBatchTest extends TestCase
{
    /* ─────────── 中间件类化 ─────────── */

    public function test_middleware_implement_interface(): void
    {
        $this->assertInstanceOf(MiddlewareInterface::class, new AuthMiddleware(
            $this->app->get(\Core\Auth\AuthManager::class),
            $this->app->get(\Core\Http\Session::class),
        ));
        $this->assertInstanceOf(MiddlewareInterface::class, new AdminMiddleware(
            $this->app->get(\Core\Auth\AuthManager::class),
        ));
        $this->assertInstanceOf(MiddlewareInterface::class, new CsrfMiddleware(
            $this->app->get(\Core\Http\Session::class),
        ));
        $this->assertInstanceOf(MiddlewareInterface::class, new GuestMiddleware(
            $this->app->get(\Core\Auth\AuthManager::class),
        ));
    }

    public function test_guest_middleware_allows_when_not_logged_in(): void
    {
        $this->initializeDatabase();
        $middleware = new GuestMiddleware(
            $this->app->get(\Core\Auth\AuthManager::class),
        );
        $result = $middleware->handle([]);
        // Not logged in → null (continue)
        $this->assertNull($result);
    }

    public function test_csrf_middleware_rejects_invalid_token(): void
    {
        $this->initializeDatabase();
        $middleware = new CsrfMiddleware(
            $this->app->get(\Core\Http\Session::class),
        );
        $result = $middleware->handle([]);
        // No token → 419
        $this->assertSame(419, $result->status());
    }

    public function test_csrf_middleware_accepts_valid_token(): void
    {
        $this->initializeDatabase();
        $session = $this->app->get(\Core\Http\Session::class);
        $token = $session->csrfToken();
        $_POST['_csrf'] = $token;

        $middleware = new CsrfMiddleware($session);
        $result = $middleware->handle([]);
        $this->assertNull($result);

        unset($_POST['_csrf']);
    }

    /* ─────────── Model 关系方法 ─────────── */

    public function test_post_comment_count(): void
    {
        $this->initializeDatabase();
        $this->seedTestData();

        $post = Post::all()[0];
        $this->assertSame(2, $post->commentCount());
    }

    public function test_category_posts(): void
    {
        $this->initializeDatabase();
        $this->seedTestData();

        $cat = Category::all()[0];
        $posts = $cat->posts();
        $this->assertCount(1, $posts);
        $this->assertInstanceOf(Post::class, $posts[0]);
    }

    public function test_tag_posts(): void
    {
        $this->initializeDatabase();
        $this->seedTestData();

        $tag = Tag::all()[0];
        $posts = $tag->posts();
        $this->assertCount(1, $posts);
        $this->assertInstanceOf(Post::class, $posts[0]);
    }

    public function test_user_post_count(): void
    {
        $this->initializeDatabase();
        $this->seedTestData();

        $user = User::all()[0];
        $this->assertSame(1, $user->postCount());
    }

    public function test_post_related(): void
    {
        $this->initializeDatabase();
        $this->seedTestData();

        $post = Post::all()[0];
        $related = $post->related(5);
        // Only 1 published post in same category → related should be empty or have at most 1
        $this->assertIsArray($related);
    }

    public function test_where_not_in_query(): void
    {
        $this->initializeDatabase();
        $this->seedTestData();
        // Add a second post so whereNotIn has something to return
        $now = date('Y-m-d H:i:s');
        $this->app->get(\Core\Database\Connection::class)->pdo()->exec(
            "INSERT INTO posts (author_id, category_id, title, slug, content_md, status, published_at, created_at, updated_at)
             VALUES (1, 1, 'Second Post', 'second-post', '# Bye', 'published', '{$now}', '{$now}', '{$now}')"
        );

        // Should exclude id=1, return only id=2
        $rows = Post::query()
            ->whereNotIn('id', [1])
            ->get();
        $this->assertCount(1, $rows);
        $this->assertSame(2, $rows[0]['id']);
    }

    /* ─────────── helpers 拆分 ─────────── */

    public function test_helper_functions_exist(): void
    {
        // Core helpers
        $this->assertTrue(function_exists('app'));
        $this->assertTrue(function_exists('config'));
        $this->assertTrue(function_exists('base_path'));

        // HTTP helpers
        $this->assertTrue(function_exists('url'));
        $this->assertTrue(function_exists('route'));
        $this->assertTrue(function_exists('csrf_token'));
        $this->assertTrue(function_exists('is_admin_route'));

        // Auth helpers
        $this->assertTrue(function_exists('current_user'));
        $this->assertTrue(function_exists('logged_in'));
        $this->assertTrue(function_exists('can'));

        // Hook helpers
        $this->assertTrue(function_exists('add_action'));
        $this->assertTrue(function_exists('do_action'));
        $this->assertTrue(function_exists('add_filter'));
        $this->assertTrue(function_exists('apply_filters'));

        // Theme helpers
        $this->assertTrue(function_exists('get_header'));
        $this->assertTrue(function_exists('the_title'));
        $this->assertTrue(function_exists('wp_nav_menu'));
    }

    /* ─────────── 测试数据 ─────────── */

    private function seedTestData(): void
    {
        $now = date('Y-m-d H:i:s');

        // User
        $this->app->get(\Core\Database\Connection::class)->pdo()->exec(
            "INSERT INTO users (username, email, password, role, created_at, updated_at)
             VALUES ('testuser', 'test@example.com', 'x', 'super_admin', '{$now}', '{$now}')"
        );

        // Category
        $this->app->get(\Core\Database\Connection::class)->pdo()->exec(
            "INSERT INTO categories (name, slug, created_at, updated_at)
             VALUES ('Test Category', 'test-cat', '{$now}', '{$now}')"
        );

        // Tag
        $this->app->get(\Core\Database\Connection::class)->pdo()->exec(
            "INSERT INTO tags (name, slug, created_at, updated_at)
             VALUES ('Test Tag', 'test-tag', '{$now}', '{$now}')"
        );

        // Post
        $this->app->get(\Core\Database\Connection::class)->pdo()->exec(
            "INSERT INTO posts (author_id, category_id, title, slug, content_md, status, published_at, created_at, updated_at)
             VALUES (1, 1, 'Test Post', 'test-post', '# Hello', 'published', '{$now}', '{$now}', '{$now}')"
        );

        // Post-Tag relation
        $this->app->get(\Core\Database\Connection::class)->pdo()->exec(
            "INSERT INTO post_tag (post_id, tag_id) VALUES (1, 1)"
        );

        // Comments
        $this->app->get(\Core\Database\Connection::class)->pdo()->exec(
            "INSERT INTO comments (post_id, author_name, author_email, content, status, created_at)
             VALUES (1, 'Alice', 'alice@example.com', 'Nice!', 'approved', '{$now}')"
        );
        $this->app->get(\Core\Database\Connection::class)->pdo()->exec(
            "INSERT INTO comments (post_id, author_name, author_email, content, status, created_at)
             VALUES (1, 'Bob', 'test@example.com', 'Cool!', 'approved', '{$now}')"
        );
    }
}
