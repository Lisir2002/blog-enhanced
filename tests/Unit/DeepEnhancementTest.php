<?php

namespace Tests\Unit;

use Tests\TestCase;
use Core\i18n\Translator;
use Core\SEO\Sitemap;
use Core\Cache\PageCache;
use Core\Email\EmailTemplate;
use Core\Webhook\Webhook;
use Core\View\ImageProcessor;
use Core\View\DebugBar;
use App\Models\Post;
use App\Models\Comment;

/**
 * 五方向深度强化验证测试。
 */
class DeepEnhancementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DebugBar::reset();
    }

    /* ─────────── 方向一: 内容/媒体 ─────────── */

    public function test_image_processor_default_sizes(): void
    {
        $ip = $this->app->get(ImageProcessor::class);
        $sizes = $ip->getSizes();
        $this->assertArrayHasKey('thumbnail', $sizes);
        $this->assertArrayHasKey('medium', $sizes);
        $this->assertArrayHasKey('large', $sizes);
    }

    public function test_image_processor_add_custom_size(): void
    {
        $ip = $this->app->get(ImageProcessor::class);
        $ip->addSize('custom', 300, 200, true);
        $this->assertArrayHasKey('custom', $ip->getSizes());
    }

    public function test_migration_adds_featured_image_column(): void
    {
        $this->initializeDatabase();
        $pdo = $this->app->get(\Core\Database\Connection::class)->pdo();
        $cols = $pdo->query('PRAGMA table_info(posts)')->fetchAll();
        $colNames = array_column($cols, 'name');
        $this->assertContains('featured_image_id', $colNames);

        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll();
        $tableNames = array_column($tables, 'name');
        $this->assertContains('post_revisions', $tableNames);
    }

    /* ─────────── 方向三: 性能/缓存 ─────────── */

    public function test_fragment_cache(): void
    {
        $this->initializeDatabase();
        $cache = $this->app->get(\Core\Cache\CacheInterface::class);
        $cache->flush();

        $result = cache_fragment('test_key', 60, function () {
            return 'cached_content';
        });
        $this->assertSame('cached_content', $result);

        // Second call should return cached
        $result2 = cache_fragment('test_key', 60, function () {
            return 'should_not_reach_here';
        });
        $this->assertSame('cached_content', $result2);

        $cache->flush();
    }

    public function test_cache_forget(): void
    {
        $this->initializeDatabase();
        $cache = $this->app->get(\Core\Cache\CacheInterface::class);
        $cache->flush();

        cache_fragment('forget_test', 60, fn () => 'data');
        cache_forget('forget_test');

        $has = $cache->has('fragment:forget_test');
        $this->assertFalse($has);

        $cache->flush();
    }

    public function test_page_cache_bypasses_post_requests(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $pc = $this->app->get(PageCache::class);
        $req = new \Core\Http\Request();
        // POST requests should not be cached
        $result = $pc->get($req);
        $this->assertNull($result);
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    /* ─────────── 方向四: SEO ─────────── */

    public function test_sitemap_generates_xml(): void
    {
        $this->initializeDatabase();
        $this->seedBasicData();

        $xml = $this->app->get(Sitemap::class)->generate();
        $this->assertStringContainsString('<?xml', $xml);
        $this->assertStringContainsString('<urlset', $xml);
        $this->assertStringContainsString('sitemaps.org', $xml);
    }

    public function test_breadcrumbs_with_jsonld(): void
    {
        $html = breadcrumbs([
            ['title' => '首页', 'url' => url('/')],
            ['title' => '技术', 'url' => url('/category/tech')],
            ['title' => '文章标题'],
        ]);
        $this->assertStringContainsString('breadcrumb', $html);
        $this->assertStringContainsString('BreadcrumbList', $html);
        $this->assertStringContainsString('首页', $html);
    }

    public function test_robots_txt_generation(): void
    {
        $txt = robots_txt();
        $this->assertStringContainsString('User-agent: *', $txt);
        $this->assertStringContainsString('Disallow: /admin', $txt);
        $this->assertStringContainsString('Sitemap:', $txt);
    }

    /* ─────────── 方向二: 开发者工具 ─────────── */

    public function test_debug_bar_empty_when_debug_off(): void
    {
        // Reset debug to false
        putenv('APP_DEBUG=false');
        $config = $this->app->get(\Core\Support\Config::class);
        $config->set('app.debug', false);

        DebugBar::reset();
        $this->assertSame('', DebugBar::render());
    }

    public function test_debug_bar_shows_queries_when_debug_on(): void
    {
        putenv('APP_DEBUG=true');
        $config = $this->app->get(\Core\Support\Config::class);
        $config->set('app.debug', true);

        DebugBar::reset();
        DebugBar::logQuery('SELECT 1', 0.5);
        $html = DebugBar::render();
        $this->assertStringContainsString('Query Log', $html);
        $this->assertStringContainsString('SELECT 1', $html);

        putenv('APP_DEBUG=false');
        $config->set('app.debug', false);
    }

    /* ─────────── 方向五: i18n + 邮件 + Webhook ─────────── */

    public function test_i18n_translator(): void
    {
        $t = $this->app->get(Translator::class);
        $t->setLocale('zh_CN');
        // No translation files, should return original key
        $this->assertSame('Hello', $t->translate('Hello'));
        // With params
        $this->assertSame('Welcome, Alice', $t->translate('Welcome, :name', ['name' => 'Alice']));
    }

    public function test_email_template_render(): void
    {
        $html = EmailTemplate::render('comment_notification', [
            'site_name' => 'Test Blog',
            'comment' => ['author' => 'Alice', 'content' => 'Great!'],
        ]);
        $this->assertStringContainsString('<html', $html);
        $this->assertStringContainsString('Test Blog', $html);
    }

    public function test_email_template_registration(): void
    {
        EmailTemplate::register('test_template', 'Test Template', 'A test');
        $registered = EmailTemplate::getRegistered();
        $this->assertArrayHasKey('test_template', $registered);
    }

    public function test_webhook_get_endpoints_empty(): void
    {
        // No endpoints configured — trigger should be a no-op
        Webhook::trigger('test.event', ['data' => 1]);
        $this->assertTrue(true); // No exception thrown
    }

    /* ─────────── 嵌套评论增强 ─────────── */

    public function test_comment_depth(): void
    {
        $this->initializeDatabase();
        $this->seedCommentData();

        $root = Comment::find(1);
        $this->assertSame(0, $root->depth());

        $reply = Comment::find(2);
        $this->assertSame(1, $reply->depth());

        $nested = Comment::find(3);
        $this->assertSame(2, $nested->depth());
    }

    public function test_comment_nested_replies(): void
    {
        $this->initializeDatabase();
        $this->seedCommentData();

        $root = Comment::find(1);
        $replies = $root->nestedReplies();
        $this->assertCount(1, $replies);

        $firstReply = $replies[0];
        $nested = $firstReply->getAttribute('_nested_replies');
        $this->assertIsArray($nested);
        $this->assertCount(1, $nested);
    }

    /* ─────────── helpers ─────────── */

    private function seedBasicData(): void
    {
        $now = date('Y-m-d H:i:s');
        $pdo = $this->app->get(\Core\Database\Connection::class)->pdo();
        $pdo->exec("INSERT INTO users (username, email, password, role, status, created_at, updated_at)
            VALUES ('author', 'author@example.com', 'x', 'editor_admin', 'active', '$now', '$now')");
        $pdo->exec("INSERT INTO categories (name, slug, created_at, updated_at)
            VALUES ('Tech', 'tech', '$now', '$now')");
        $pdo->exec("INSERT INTO tags (name, slug, created_at, updated_at)
            VALUES ('PHP', 'php', '$now', '$now')");
        $pdo->exec("INSERT INTO posts (slug, title, content_md, content_html, excerpt, category_id, author_id, status, published_at, views, created_at, updated_at)
            VALUES ('test-post', 'Test Post', '# Hello World', '<h1>Hello World</h1>', 'Hello', 1, 1, 'published', '$now', 0, '$now', '$now')");
    }

    private function seedCommentData(): void
    {
        $this->seedBasicData();
        $now = date('Y-m-d H:i:s');
        $pdo = $this->app->get(\Core\Database\Connection::class)->pdo();
        $pdo->exec("INSERT INTO comments (post_id, parent_id, author_name, author_email, content, status, created_at)
            VALUES (1, 0, 'Alice', 'alice@example.com', 'Great post!', 'approved', '$now')");
        $pdo->exec("INSERT INTO comments (post_id, parent_id, author_name, author_email, content, status, created_at)
            VALUES (1, 1, 'Bob', 'bob@example.com', 'Thanks!', 'approved', '$now')");
        $pdo->exec("INSERT INTO comments (post_id, parent_id, author_name, author_email, content, status, created_at)
            VALUES (1, 2, 'Charlie', 'charlie@example.com', 'Me too!', 'approved', '$now')");
    }
}
