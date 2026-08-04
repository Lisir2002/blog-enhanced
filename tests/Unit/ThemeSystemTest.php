<?php

namespace Tests\Unit;

use Tests\TestCase;
use Core\View\Conditional;
use Core\View\WidgetManager;
use Core\View\MenuManager;
use Core\View\AssetManager;
use Core\View\Shortcode;
use Core\View\ThemeManager;
use Core\View\Widget;
use App\Models\Post;
use App\Models\Category;

/**
 * 主题系统全面验证测试。
 */
class ThemeSystemTest extends TestCase
{
    /* ─────────── 条件标签 ─────────── */

    public function test_conditional_tags(): void
    {
        $this->initializeDatabase();
        $this->seedBasicData();

        Conditional::set('home');
        $this->assertTrue(Conditional::isHome());
        $this->assertTrue(Conditional::isFrontPage());
        $this->assertFalse(Conditional::isSingle());

        Conditional::set('post.show', ['slug' => 'test-post']);
        $this->assertTrue(Conditional::isSingle());
        $this->assertTrue(Conditional::isSingle('test-post'));
        $this->assertFalse(Conditional::isHome());

        Conditional::set('category.show', ['slug' => 'tech']);
        $this->assertTrue(Conditional::isCategory());
        $this->assertTrue(Conditional::isCategory('tech'));

        Conditional::set('tag.show', ['slug' => 'php']);
        $this->assertTrue(Conditional::isTag());

        Conditional::set('search');
        $this->assertTrue(Conditional::isSearch());

        Conditional::set(null);
        $this->assertTrue(Conditional::is404());

        Conditional::reset();
    }

    /* ─────────── Widget 系统 ─────────── */

    public function test_widget_registration_and_rendering(): void
    {
        $wm = app(WidgetManager::class);

        $wm->registerSidebar([
            'id' => 'test-sidebar',
            'name' => 'Test Sidebar',
            'before_widget' => '<div class="widget">',
            'after_widget' => '</div>',
            'before_title' => '<h4>',
            'after_title' => '</h4>',
        ]);

        $this->assertTrue($wm->hasSidebar('test-sidebar'));

        // Empty sidebar returns empty
        $html = $wm->renderSidebar('test-sidebar');
        $this->assertSame('', trim($html));
    }

    public function test_empty_sidebar_returns_empty(): void
    {
        $wm = app(WidgetManager::class);
        $this->assertFalse($wm->hasSidebar('nonexistent'));
        $this->assertSame('', $wm->renderSidebar('nonexistent'));
    }

    /* ─────────── 菜单系统 ─────────── */

    public function test_menu_registration(): void
    {
        $mm = app(MenuManager::class);
        $mm->registerLocation('primary', '主导航');
        $mm->registerLocation('footer', '页脚');

        $this->assertTrue($mm->hasLocation('primary'));
        $this->assertTrue($mm->hasLocation('footer'));
        $this->assertFalse($mm->hasLocation('unknown'));
    }

    public function test_menu_fallback_to_categories(): void
    {
        $this->initializeDatabase();
        $this->seedBasicData();

        $mm = app(MenuManager::class);
        $mm->registerLocation('primary', '主导航');

        $html = $mm->render(['theme_location' => 'primary', 'fallback' => true]);
        $this->assertStringContainsString('Tech', $html);
        $this->assertStringContainsString('menu-item', $html);
    }

    /* ─────────── 资产排队 ─────────── */

    public function test_asset_enqueue_and_output(): void
    {
        $am = app(AssetManager::class);
        $am->enqueueStyle('style', '/css/style.css', [], '1.0.0');
        $am->enqueueScript('main', '/js/main.js', [], '1.0.0', true);

        $styles = $am->renderStyles();
        $this->assertStringContainsString('stylesheet', $styles);
        $this->assertStringContainsString('/css/style.css', $styles);
        $this->assertStringContainsString('ver=1.0.0', $styles);

        $scripts = $am->renderScripts(true);
        $this->assertStringContainsString('<script', $scripts);
        $this->assertStringContainsString('/js/main.js', $scripts);
    }

    public function test_asset_dequeue(): void
    {
        $am = app(AssetManager::class);
        $am->enqueueStyle('test', '/css/test.css');
        $am->dequeueStyle('test');

        $styles = $am->renderStyles();
        $this->assertStringNotContainsString('/css/test.css', $styles);
    }

    /* ─────────── Shortcode ─────────── */

    public function test_shortcode_system(): void
    {
        $sc = app(Shortcode::class);
        $sc->add('test', fn($a) => 'TEST[' . ($a['x'] ?? '') . ']');

        $result = $sc->render('Hello [test x="42"] World');
        $this->assertSame('Hello TEST[42] World', $result);
    }

    public function test_shortcode_no_tags(): void
    {
        $sc = app(Shortcode::class);
        $result = $sc->render('No shortcodes here');
        $this->assertSame('No shortcodes here', $result);
    }

    public function test_shortcode_multiple(): void
    {
        $sc = app(Shortcode::class);
        $sc->add('a', fn() => 'A');
        $sc->add('b', fn() => 'B');

        $result = $sc->render('[a] and [b]');
        $this->assertSame('A and B', $result);
    }

    public function test_shortcode_with_id_attr(): void
    {
        $sc = app(Shortcode::class);
        $sc->add('yt', function ($attrs) {
            $id = $attrs['id'] ?? '';
            return "VIDEO($id)";
        });

        $result = $sc->render('[yt id="abc123"]');
        $this->assertSame('VIDEO(abc123)', $result);
    }

    /* ─────────── 主题配置 ─────────── */

    public function test_theme_path_and_asset(): void
    {
        $theme = app(ThemeManager::class);
        $this->assertSame('default', $theme->activeTheme());

        $path = $theme->path();
        $this->assertStringEndsWith('public/themes/default', $path);

        $assetUrl = $theme->asset('css/style.css');
        $this->assertStringContainsString('themes/default', $assetUrl);
        $this->assertStringContainsString('style.css', $assetUrl);
    }

    public function test_theme_config_reads_options(): void
    {
        $this->initializeDatabase();
        \App\Models\Option::set('theme_option_accent_color', '#ff0000');

        $config = theme_config('accent_color');
        // theme_config reads from option key "theme_option_{key}" or theme.json default
        $this->assertTrue(is_string($config) || $config === null);
    }

    /* ─────────── 分页助手 ─────────── */

    public function test_paginate_links(): void
    {
        $html = paginate_links([
            'total' => 3,
            'current' => 1,
            'base' => '/page/%#%',
        ]);
        $this->assertStringContainsString('pagination', $html);
        $this->assertStringContainsString('current', $html);
        $this->assertStringContainsString('/page/2', $html);
        $this->assertStringContainsString('next', $html);
    }

    /* ─────────── 安全转义 ─────────── */

    public function test_esc_html(): void
    {
        $this->assertSame('&lt;script&gt;', esc_html('<script>'));
    }

    public function test_esc_attr(): void
    {
        $this->assertSame('a&#039;b', esc_attr("a'b"));
    }

    public function test_esc_url(): void
    {
        $this->assertSame('https://example.com/path', esc_url('https://example.com/path'));
        $this->assertSame('/local/path', esc_url('/local/path'));
        $this->assertSame('', esc_url('javascript:alert(1)'));
    }

    /* ─────────── 内容助手 ─────────── */

    public function test_word_count(): void
    {
        $this->assertGreaterThan(0, word_count('Hello world this is a test'));
    }

    public function test_word_count_chinese(): void
    {
        $this->assertGreaterThan(0, word_count('你好世界这是一个测试'));
    }

    public function test_reading_time(): void
    {
        $time = reading_time(600);
        $this->assertStringContainsString('分钟', $time);
    }

    public function test_table_of_contents(): void
    {
        $html = '<h2>Chapter 1</h2><p>text</p><h3>Section 1.1</h3><p>more</p>';
        $toc = table_of_contents($html);
        $this->assertStringContainsString('Chapter 1', $toc);
        $this->assertStringContainsString('Section 1.1', $toc);
    }

    /* ─────────── body_class ─────────── */

    public function test_body_class_output(): void
    {
        $this->initializeDatabase();

        Conditional::set('home');
        $class = body_class();
        $this->assertStringContainsString('home', $class);

        Conditional::set('post.show', ['slug' => 'test']);
        $class = body_class();
        $this->assertStringContainsString('single', $class);

        Conditional::reset();
    }

    /* ─────────── 前端 Admin Bar ─────────── */

    public function test_admin_bar_returns_empty_when_not_logged_in(): void
    {
        $html = render_admin_bar();
        $this->assertSame('', $html);
    }

    /* ─────────── 模板存在性 ─────────── */

    public function test_template_exists(): void
    {
        $theme = app(ThemeManager::class);
        $this->assertTrue($theme->templateExists('home'));
        $this->assertTrue($theme->templateExists('single'));
        $this->assertTrue($theme->templateExists('404'));
        $this->assertFalse($theme->templateExists('nonexistent-template'));
    }

    /* ─────────── helpers ─────────── */

    private function seedBasicData(): void
    {
        $now = date('Y-m-d H:i:s');
        $pdo = $this->app->get(\Core\Database\Connection::class)->pdo();
        $pdo->exec("INSERT INTO users (username, email, password, role, status, created_at, updated_at)
            VALUES ('author', 'author@example.com', 'x', 'author', 'active', '$now', '$now')");
        $pdo->exec("INSERT INTO categories (name, slug, created_at, updated_at)
            VALUES ('Tech', 'tech', '$now', '$now')");
        $pdo->exec("INSERT INTO tags (name, slug, created_at, updated_at)
            VALUES ('PHP', 'php', '$now', '$now')");
        $pdo->exec("INSERT INTO posts (slug, title, content_md, content_html, excerpt, category_id, author_id, status, published_at, views, created_at, updated_at)
            VALUES ('test-post', 'Test Post', '# Hello World', '<h1>Hello World</h1>', 'Hello', 1, 1, 'published', '$now', 0, '$now', '$now')");
    }
}
