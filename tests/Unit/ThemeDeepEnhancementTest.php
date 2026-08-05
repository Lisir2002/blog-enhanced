<?php

namespace Tests\Unit;

use Tests\TestCase;
use Core\Theme\ThemeInstaller;
use Core\Theme\TemplateResolver;
use Core\Theme\ThemeConfigManager;
use Core\View\ThemeManager;
use Core\Http\Response;

/**
 * 主题模块深度优化测试 — 覆盖所有新组件和功能。
 */
class ThemeDeepEnhancementTest extends TestCase
{
    private string $themeRoot;

    protected function setUp(): void
    {
        parent::setUp();
        // 使用临时目录作为主题根目录
        $this->themeRoot = sys_get_temp_dir() . '/theme-test-' . uniqid();
        @mkdir($this->themeRoot, 0777, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // 清理临时目录
        $this->rrmdir($this->themeRoot);
    }

    /* ═════════════ 1. ThemeInstaller ═════════════ */

    public function test_installer_list_themes_empty(): void
    {
        $installer = new ThemeInstaller($this->themeRoot);
        $themes = $installer->listThemes();
        $this->assertSame([], $themes);
    }

    public function test_installer_detect_theme_from_theme_json(): void
    {
        $this->createTheme('test-theme', [
            'theme.json' => json_encode(['name' => 'Test Theme', 'version' => '1.0.0']),
        ]);

        $installer = new ThemeInstaller($this->themeRoot);
        $themes = $installer->listThemes();
        $this->assertArrayHasKey('test-theme', $themes);
        $this->assertSame('Test Theme', $themes['test-theme']['meta']['name']);
    }

    public function test_installer_exists(): void
    {
        $this->createTheme('exists-test', [
            'theme.json' => json_encode(['name' => 'Exists Test']),
        ]);
        $installer = new ThemeInstaller($this->themeRoot);
        $this->assertTrue($installer->exists('exists-test'));
        $this->assertFalse($installer->exists('non-existent'));
    }

    public function test_installer_delete_theme(): void
    {
        $this->createTheme('delete-me', [
            'theme.json' => json_encode(['name' => 'Delete Me']),
        ]);
        $installer = new ThemeInstaller($this->themeRoot);
        $this->assertTrue($installer->exists('delete-me'));
        $this->assertTrue($installer->deleteTheme('delete-me'));
        $this->assertFalse($installer->exists('delete-me'));
    }

    public function test_installer_delete_nonexistent_returns_false(): void
    {
        $installer = new ThemeInstaller($this->themeRoot);
        $this->assertFalse($installer->deleteTheme('non-existent'));
    }

    public function test_installer_validate_php_syntax_valid(): void
    {
        $file = $this->themeRoot . '/valid.php';
        file_put_contents($file, "<?php\nfunction test() { return 1; }\n");
        $installer = new ThemeInstaller($this->themeRoot);
        $this->assertTrue($installer->validatePhpSyntax($file));
        @unlink($file);
    }

    public function test_installer_validate_php_syntax_invalid(): void
    {
        $file = $this->themeRoot . '/invalid.php';
        file_put_contents($file, "<?php\nfunction test() { return 1; \n");
        $installer = new ThemeInstaller($this->themeRoot);
        $this->assertFalse($installer->validatePhpSyntax($file));
        @unlink($file);
    }

    public function test_installer_validate_php_syntax_empty_file(): void
    {
        $file = $this->themeRoot . '/empty.php';
        file_put_contents($file, '');
        $installer = new ThemeInstaller($this->themeRoot);
        $this->assertTrue($installer->validatePhpSyntax($file));
        @unlink($file);
    }

    public function test_installer_list_skips_hidden_dirs(): void
    {
        $this->createTheme('.hidden-theme', [
            'theme.json' => json_encode(['name' => 'Hidden']),
        ]);
        $this->createTheme('visible-theme', [
            'theme.json' => json_encode(['name' => 'Visible']),
        ]);
        $installer = new ThemeInstaller($this->themeRoot);
        $themes = $installer->listThemes();
        $this->assertArrayHasKey('visible-theme', $themes);
        $this->assertArrayNotHasKey('.hidden-theme', $themes);
    }

    /* ═════════════ 2. TemplateResolver ═════════════ */

    public function test_resolver_path_and_resolve(): void
    {
        $this->createTheme('test-theme', [
            'templates/home.php' => '<h1>Home</h1>',
            'theme.json' => json_encode(['name' => 'Test']),
        ]);
        $resolver = new TemplateResolver($this->themeRoot);
        $resolver->setActiveTheme('test-theme');
        $this->assertStringEndsWith('test-theme', $resolver->path());
        $this->assertStringEndsWith('test-theme/templates/home.php', $resolver->resolvePath('templates/home.php'));
    }

    public function test_resolver_template_exists(): void
    {
        $this->createTheme('test-theme', [
            'templates/home.php' => '<h1>Home</h1>',
            'theme.json' => json_encode(['name' => 'Test']),
        ]);
        $resolver = new TemplateResolver($this->themeRoot);
        $resolver->setActiveTheme('test-theme');
        $this->assertTrue($resolver->templateExists('home'));
        $this->assertFalse($resolver->templateExists('non-existent'));
    }

    public function test_resolver_render_returns_response(): void
    {
        $this->createTheme('test-theme', [
            'templates/index.php' => '<h1>Index</h1>',
            'theme.json' => json_encode(['name' => 'Test']),
        ]);
        $resolver = new TemplateResolver($this->themeRoot);
        $resolver->setActiveTheme('test-theme');
        $resp = $resolver->render('index');
        $this->assertInstanceOf(Response::class, $resp);
        $this->assertStringContainsString('Index', $resp->getBody());
        $this->assertSame(200, $resp->getStatus());
    }

    public function test_resolver_render_fallback_to_index(): void
    {
        $this->createTheme('test-theme', [
            'templates/index.php' => '<h1>Fallback Index</h1>',
            'theme.json' => json_encode(['name' => 'Test']),
        ]);
        $resolver = new TemplateResolver($this->themeRoot);
        $resolver->setActiveTheme('test-theme');
        // 'single' should fallback to 'index'
        $resp = $resolver->render('single');
        $this->assertStringContainsString('Fallback Index', $resp->getBody());
    }

    public function test_resolver_render_missing_template_returns_500(): void
    {
        $this->createTheme('test-theme', [
            'theme.json' => json_encode(['name' => 'Test']),
        ]);
        $resolver = new TemplateResolver($this->themeRoot);
        $resolver->setActiveTheme('test-theme');
        $resp = $resolver->render('no-such-template');
        $this->assertSame(500, $resp->getStatus());
    }

    public function test_resolver_parent_child_fallback(): void
    {
        $this->createTheme('parent-theme', [
            'templates/home.php' => '<h1>Parent Home</h1>',
            'theme.json' => json_encode(['name' => 'Parent']),
        ]);
        $this->createTheme('child-theme', [
            'theme.json' => json_encode(['name' => 'Child', 'parent' => 'parent-theme']),
        ]);
        $resolver = new TemplateResolver($this->themeRoot);
        $resolver->setActiveTheme('child-theme', 'parent-theme');
        // Should find parent's home.php
        $this->assertTrue($resolver->templateExists('home'));
        $resp = $resolver->render('home');
        $this->assertStringContainsString('Parent Home', $resp->getBody());
    }

    public function test_resolver_child_overrides_parent(): void
    {
        $this->createTheme('parent-theme', [
            'templates/home.php' => '<h1>Parent Home</h1>',
            'theme.json' => json_encode(['name' => 'Parent']),
        ]);
        $this->createTheme('child-theme', [
            'templates/home.php' => '<h1>Child Home</h1>',
            'theme.json' => json_encode(['name' => 'Child', 'parent' => 'parent-theme']),
        ]);
        $resolver = new TemplateResolver($this->themeRoot);
        $resolver->setActiveTheme('child-theme', 'parent-theme');
        $resp = $resolver->render('home');
        $this->assertStringContainsString('Child Home', $resp->getBody());
    }

    public function test_resolver_partial_rendering(): void
    {
        $this->createTheme('test-theme', [
            'partials/header.php' => '<header>Header</header>',
            'theme.json' => json_encode(['name' => 'Test']),
        ]);
        $resolver = new TemplateResolver($this->themeRoot);
        $resolver->setActiveTheme('test-theme');
        $html = $resolver->partial('header');
        $this->assertStringContainsString('Header', $html);
    }

    public function test_resolver_partial_missing_returns_comment(): void
    {
        $resolver = new TemplateResolver($this->themeRoot);
        $resolver->setActiveTheme('test-theme');
        $html = $resolver->partial('non-existent');
        $this->assertStringContainsString('缺失', $html);
    }

    public function test_resolver_asset_url(): void
    {
        $this->createTheme('test-theme', [
            'assets/css/style.css' => 'body {}',
            'theme.json' => json_encode(['name' => 'Test']),
        ]);
        $resolver = new TemplateResolver($this->themeRoot);
        $resolver->setActiveTheme('test-theme');
        $url = $resolver->assetUrl('assets/css/style.css');
        $this->assertStringContainsString('themes/test-theme/assets/css/style.css', $url);
    }

    public function test_resolver_parent_asset_fallback(): void
    {
        $this->createTheme('parent-theme', [
            'assets/css/style.css' => 'body {}',
            'theme.json' => json_encode(['name' => 'Parent']),
        ]);
        $this->createTheme('child-theme', [
            'theme.json' => json_encode(['name' => 'Child', 'parent' => 'parent-theme']),
        ]);
        $resolver = new TemplateResolver($this->themeRoot);
        $resolver->setActiveTheme('child-theme', 'parent-theme');
        $url = $resolver->assetUrl('assets/css/style.css');
        $this->assertStringContainsString('themes/parent-theme/assets/css/style.css', $url);
    }

    /* ═════════════ 3. ThemeConfigManager ═════════════ */

    public function test_config_manager_reads_theme_json(): void
    {
        $this->createTheme('test-theme', [
            'theme.json' => json_encode([
                'name' => 'Test',
                'version' => '2.0.0',
                'options' => [
                    'color' => ['type' => 'color', 'default' => '#ff0000'],
                ],
            ]),
        ]);
        $cm = new ThemeConfigManager($this->themeRoot);
        $cm->setActiveTheme('test-theme');
        $config = $cm->getConfig();
        $this->assertSame('Test', $config['name']);
        $this->assertSame('2.0.0', $config['version']);
    }

    public function test_config_manager_get_option_from_default(): void
    {
        $this->createTheme('test-theme', [
            'theme.json' => json_encode([
                'name' => 'Test',
                'options' => [
                    'accent_color' => ['type' => 'color', 'default' => '#3b82f6'],
                ],
            ]),
        ]);
        $cm = new ThemeConfigManager($this->themeRoot);
        $cm->setActiveTheme('test-theme');
        $this->assertSame('#3b82f6', $cm->getOption('accent_color'));
    }

    public function test_config_manager_get_option_custom_default(): void
    {
        $this->createTheme('test-theme', [
            'theme.json' => json_encode(['name' => 'Test']),
        ]);
        $cm = new ThemeConfigManager($this->themeRoot);
        $cm->setActiveTheme('test-theme');
        $this->assertSame('fallback', $cm->getOption('non-existent', 'fallback'));
    }

    public function test_config_manager_get_page_templates(): void
    {
        $this->createTheme('test-theme', [
            'theme.json' => json_encode([
                'name' => 'Test',
                'page_templates' => ['full-width' => '全宽', 'landing' => '落地页'],
            ]),
        ]);
        $cm = new ThemeConfigManager($this->themeRoot);
        $cm->setActiveTheme('test-theme');
        $templates = $cm->getPageTemplates();
        $this->assertArrayHasKey('full-width', $templates);
        $this->assertArrayHasKey('landing', $templates);
    }

    public function test_config_manager_get_menu_locations(): void
    {
        $this->createTheme('test-theme', [
            'theme.json' => json_encode([
                'name' => 'Test',
                'menus' => ['primary' => '主导航', 'footer' => '页脚'],
            ]),
        ]);
        $cm = new ThemeConfigManager($this->themeRoot);
        $cm->setActiveTheme('test-theme');
        $menus = $cm->getMenuLocations();
        $this->assertArrayHasKey('primary', $menus);
        $this->assertArrayHasKey('footer', $menus);
    }

    public function test_config_manager_get_sidebars(): void
    {
        $this->createTheme('test-theme', [
            'theme.json' => json_encode([
                'name' => 'Test',
                'sidebars' => ['sidebar-1' => ['name' => '主侧边栏']],
            ]),
        ]);
        $cm = new ThemeConfigManager($this->themeRoot);
        $cm->setActiveTheme('test-theme');
        $sidebars = $cm->getSidebars();
        $this->assertArrayHasKey('sidebar-1', $sidebars);
    }

    public function test_config_manager_get_screenshots(): void
    {
        $this->createTheme('test-theme', [
            'theme.json' => json_encode([
                'name' => 'Test',
                'screenshot' => 'screenshot.jpg',
                'screenshots' => ['screenshot.jpg', 'screenshot-2.jpg'],
            ]),
        ]);
        $cm = new ThemeConfigManager($this->themeRoot);
        $cm->setActiveTheme('test-theme');
        $screenshots = $cm->getScreenshots();
        $this->assertContains('screenshot.jpg', $screenshots);
        $this->assertContains('screenshot-2.jpg', $screenshots);
    }

    public function test_config_manager_get_changelog(): void
    {
        $this->createTheme('test-theme', [
            'theme.json' => json_encode([
                'name' => 'Test',
                'changelog' => ['1.0.0' => '初始版本', '1.1.0' => '新增功能'],
            ]),
        ]);
        $cm = new ThemeConfigManager($this->themeRoot);
        $cm->setActiveTheme('test-theme');
        $changelog = $cm->getChangelog();
        $this->assertArrayHasKey('1.0.0', $changelog);
        $this->assertArrayHasKey('1.1.0', $changelog);
    }

    public function test_config_manager_get_recommended_plugins(): void
    {
        $this->createTheme('test-theme', [
            'theme.json' => json_encode([
                'name' => 'Test',
                'plugins' => ['seo-pack', 'cache'],
            ]),
        ]);
        $cm = new ThemeConfigManager($this->themeRoot);
        $cm->setActiveTheme('test-theme');
        $plugins = $cm->getRecommendedPlugins();
        $this->assertContains('seo-pack', $plugins);
        $this->assertContains('cache', $plugins);
    }

    public function test_config_manager_get_tags_and_category(): void
    {
        $this->createTheme('test-theme', [
            'theme.json' => json_encode([
                'name' => 'Test',
                'category' => 'blog',
                'tags' => ['minimal', 'responsive'],
            ]),
        ]);
        $cm = new ThemeConfigManager($this->themeRoot);
        $cm->setActiveTheme('test-theme');
        $this->assertSame('blog', $cm->getCategory());
        $this->assertContains('minimal', $cm->getTags());
        $this->assertContains('responsive', $cm->getTags());
    }

    public function test_config_manager_get_requires(): void
    {
        $this->createTheme('test-theme', [
            'theme.json' => json_encode([
                'name' => 'Test',
                'requires' => '>=1.0',
                'requires_php' => '>=8.1',
            ]),
        ]);
        $cm = new ThemeConfigManager($this->themeRoot);
        $cm->setActiveTheme('test-theme');
        $this->assertSame('>=1.0', $cm->getRequires());
        $this->assertSame('>=8.1', $cm->getRequiresPhp());
    }

    public function test_config_manager_parent_child_merge(): void
    {
        $this->createTheme('parent-theme', [
            'theme.json' => json_encode([
                'name' => 'Parent',
                'menus' => ['primary' => '主导航'],
                'sidebars' => ['sidebar-1' => ['name' => '主侧边栏']],
                'page_templates' => ['full-width' => '全宽'],
            ]),
        ]);
        $this->createTheme('child-theme', [
            'theme.json' => json_encode([
                'name' => 'Child',
                'parent' => 'parent-theme',
                'page_templates' => ['landing' => '落地页'],
            ]),
        ]);
        $cm = new ThemeConfigManager($this->themeRoot);
        $cm->setActiveTheme('child-theme', 'parent-theme');

        $templates = $cm->getPageTemplates();
        $this->assertArrayHasKey('full-width', $templates); // from parent
        $this->assertArrayHasKey('landing', $templates);    // from child

        $menus = $cm->getMenuLocations();
        $this->assertArrayHasKey('primary', $menus); // from parent
    }

    public function test_config_manager_generate_css_variables(): void
    {
        $this->createTheme('test-theme', [
            'theme.json' => json_encode([
                'name' => 'Test',
                'options' => [
                    'accent_color' => ['type' => 'color', 'default' => '#3b82f6'],
                    'bg_color' => ['type' => 'color', 'default' => '#ffffff'],
                    'layout' => ['type' => 'select', 'default' => 'full-width'],
                ],
            ]),
        ]);
        $cm = new ThemeConfigManager($this->themeRoot);
        $cm->setActiveTheme('test-theme');
        $cssVars = $cm->generateCssVariables();
        $this->assertStringContainsString('--theme-accent-color', $cssVars);
        $this->assertStringContainsString('--theme-bg-color', $cssVars);
        $this->assertStringNotContainsString('--theme-layout', $cssVars); // select type, not color
    }

    public function test_config_manager_get_declared_styles_and_scripts(): void
    {
        $this->createTheme('test-theme', [
            'theme.json' => json_encode([
                'name' => 'Test',
                'styles' => ['style' => ['src' => 'css/style.css']],
                'scripts' => ['main' => ['src' => 'js/main.js', 'footer' => true]],
            ]),
        ]);
        $cm = new ThemeConfigManager($this->themeRoot);
        $cm->setActiveTheme('test-theme');
        $styles = $cm->getDeclaredStyles();
        $this->assertArrayHasKey('style', $styles);
        $scripts = $cm->getDeclaredScripts();
        $this->assertArrayHasKey('main', $scripts);
    }

    /* ═════════════ 4. Integration: ThemeManager Facade ═════════════ */

    public function test_theme_manager_instantiates_components(): void
    {
        $tm = new ThemeManager();
        $this->assertInstanceOf(ThemeInstaller::class, $tm->getInstaller());
        $this->assertInstanceOf(TemplateResolver::class, $tm->getResolver());
        $this->assertInstanceOf(ThemeConfigManager::class, $tm->getConfigManager());
    }

    public function test_theme_manager_asset_with_version(): void
    {
        // We need to set up a theme with a version
        $this->createTheme('test-theme', [
            'theme.json' => json_encode(['name' => 'Test', 'version' => '1.5.0']),
            'assets/css/style.css' => 'body {}',
        ]);

        // Override the theme root via reflection
        $tm = new ThemeManager();
        $this->setThemeManagerRoot($tm, $this->themeRoot, 'test-theme');

        // Also set up resolver
        $tm->getResolver()->setActiveTheme('test-theme');
        $tm->getConfigManager()->setActiveTheme('test-theme');

        $url = $tm->asset('assets/css/style.css');
        $this->assertStringContainsString('ver=1.5.0', $url);
    }

    public function test_theme_manager_get_screenshots_from_theme(): void
    {
        $this->createTheme('test-theme', [
            'theme.json' => json_encode([
                'name' => 'Test',
                'screenshot' => 'screenshot.jpg',
                'screenshots' => ['screenshot.jpg', 'pic2.jpg'],
            ]),
        ]);
        $tm = new ThemeManager();
        $this->setThemeManagerRoot($tm, $this->themeRoot, 'test-theme');
        $tm->getConfigManager()->setActiveTheme('test-theme');

        $screenshots = $tm->getScreenshots();
        $this->assertContains('screenshot.jpg', $screenshots);
        $this->assertContains('pic2.jpg', $screenshots);
    }

    public function test_theme_manager_delegates_to_installer(): void
    {
        $this->createTheme('test-theme', [
            'theme.json' => json_encode(['name' => 'Test']),
        ]);
        $tm = new ThemeManager();
        $this->setThemeManagerRoot($tm, $this->themeRoot, 'test-theme');

        $this->assertTrue($tm->exists('test-theme'));
        $this->assertFalse($tm->exists('non-existent'));
    }

    public function test_theme_manager_delegates_to_resolver(): void
    {
        $this->createTheme('test-theme', [
            'templates/home.php' => '<h1>Home</h1>',
            'theme.json' => json_encode(['name' => 'Test']),
        ]);
        $tm = new ThemeManager();
        $this->setThemeManagerRoot($tm, $this->themeRoot, 'test-theme');
        $tm->getResolver()->setActiveTheme('test-theme');

        $this->assertTrue($tm->templateExists('home'));
        $resp = $tm->render('home');
        $this->assertStringContainsString('Home', $resp->getBody());
    }

    public function test_theme_manager_delegates_to_config_manager(): void
    {
        $this->createTheme('test-theme', [
            'theme.json' => json_encode([
                'name' => 'Test',
                'options' => [
                    'accent_color' => ['type' => 'color', 'default' => '#ff0000'],
                ],
            ]),
        ]);
        $tm = new ThemeManager();
        $this->setThemeManagerRoot($tm, $this->themeRoot, 'test-theme');
        $tm->getConfigManager()->setActiveTheme('test-theme');

        $this->assertSame('#ff0000', $tm->config('accent_color'));
        $this->assertSame('#ff0000', $tm->getAllConfig()['accent_color']);
    }

    /* ═════════════ 5. Option Model Delete ═════════════ */

    public function test_option_model_delete(): void
    {
        $this->initializeDatabase();
        \App\Models\Option::set('test_key', 'test_value');
        $this->assertSame('test_value', \App\Models\Option::get('test_key'));

        \App\Models\Option::remove('test_key');
        $this->assertNull(\App\Models\Option::get('test_key', null));
    }

    /* ═════════════ 6. Zip Slip Protection ═════════════ */

    public function test_installer_rejects_zip_slip(): void
    {
        // Create a zip file with path traversal
        $zipPath = $this->themeRoot . '/malicious.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);
        $zip->addFromString('../../etc/passwd', 'fake');
        $zip->close();

        $installer = new ThemeInstaller($this->themeRoot . '/safe-dir');
        @mkdir($this->themeRoot . '/safe-dir', 0777, true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('安全拒绝');
        $installer->installFromZip($zipPath);

        @unlink($zipPath);
    }

    /* ═════════════ 7. Syntax Validation Through Installer ═════════════ */

    public function test_validate_php_syntax_nested_braces(): void
    {
        $installer = new ThemeInstaller($this->themeRoot);
        $file = $this->themeRoot . '/nested.php';
        file_put_contents($file, "<?php\nif (true) { foreach(\$a as \$b) { echo \$b; } }\n");
        $this->assertTrue($installer->validatePhpSyntax($file));
        @unlink($file);
    }

    public function test_validate_php_syntax_unclosed_brace(): void
    {
        $installer = new ThemeInstaller($this->themeRoot);
        $file = $this->themeRoot . '/unclosed.php';
        file_put_contents($file, "<?php\nfunction foo() {\n  echo 'hi';\n");
        $this->assertFalse($installer->validatePhpSyntax($file));
        @unlink($file);
    }

    /* ═════════════ 8. TemplateResolver data passing ═════════════ */

    public function test_resolver_render_passes_data(): void
    {
        $this->createTheme('test-theme', [
            'templates/page.php' => '<?php echo $title; ?>',
            'theme.json' => json_encode(['name' => 'Test']),
        ]);
        $resolver = new TemplateResolver($this->themeRoot);
        $resolver->setActiveTheme('test-theme');
        $resp = $resolver->render('page', ['title' => 'Hello World']);
        $this->assertStringContainsString('Hello World', $resp->getBody());
    }

    public function test_resolver_partial_with_data(): void
    {
        $this->createTheme('test-theme', [
            'partials/item.php' => '<?php echo $name; ?>',
            'theme.json' => json_encode(['name' => 'Test']),
        ]);
        $resolver = new TemplateResolver($this->themeRoot);
        $resolver->setActiveTheme('test-theme');
        $html = $resolver->partial('item', ['name' => 'Test Item']);
        $this->assertStringContainsString('Test Item', $html);
    }

    /* ═════════════ 9. ConfigManager set/delete option persistence ═════════════ */

    public function test_config_manager_set_and_delete_option(): void
    {
        $this->initializeDatabase();
        $this->createTheme('test-theme', [
            'theme.json' => json_encode(['name' => 'Test']),
        ]);
        $cm = new ThemeConfigManager($this->themeRoot);
        $cm->setActiveTheme('test-theme');

        $cm->setOption('custom_key', 'custom_value');
        $this->assertSame('custom_value', $cm->getOption('custom_key'));

        $cm->deleteOption('custom_key');
        $this->assertNull($cm->getOption('custom_key'));
    }

    public function test_config_manager_get_all_options_returns_flat_array(): void
    {
        $this->initializeDatabase();
        $this->createTheme('test-theme', [
            'theme.json' => json_encode([
                'name' => 'Test',
                'options' => [
                    'color' => ['type' => 'color', 'default' => '#ff0000'],
                    'layout' => ['type' => 'select', 'default' => 'full-width'],
                ],
            ]),
        ]);
        $cm = new ThemeConfigManager($this->themeRoot);
        $cm->setActiveTheme('test-theme');

        $all = $cm->getAllOptions();
        $this->assertArrayHasKey('color', $all);
        $this->assertArrayHasKey('layout', $all);
        $this->assertSame('#ff0000', $all['color']);
        $this->assertSame('full-width', $all['layout']);
    }

    /* ═════════════ helpers ═════════════ */

    private function setThemeManagerRoot(ThemeManager $tm, string $root, string $activeTheme): void
    {
        $ref = new \ReflectionProperty($tm, 'themeRoot');
        $ref->setAccessible(true);
        $ref->setValue($tm, $root);

        $refActive = new \ReflectionProperty($tm, 'activeTheme');
        $refActive->setAccessible(true);
        $refActive->setValue($tm, $activeTheme);

        // 同步子组件的 themeRoot
        $installerRef = new \ReflectionProperty($tm->getInstaller(), 'themeRoot');
        $installerRef->setAccessible(true);
        $installerRef->setValue($tm->getInstaller(), $root);

        $resolverRef = new \ReflectionProperty($tm->getResolver(), 'themeRoot');
        $resolverRef->setAccessible(true);
        $resolverRef->setValue($tm->getResolver(), $root);

        $configRef = new \ReflectionProperty($tm->getConfigManager(), 'themeRoot');
        $configRef->setAccessible(true);
        $configRef->setValue($tm->getConfigManager(), $root);
    }

    private function createTheme(string $name, array $files): void
    {
        $dir = $this->themeRoot . '/' . $name;
        foreach ($files as $path => $content) {
            $fullPath = $dir . '/' . $path;
            $parent = dirname($fullPath);
            if (!is_dir($parent)) {
                @mkdir($parent, 0777, true);
            }
            file_put_contents($fullPath, $content);
        }
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $f) {
            $path = "$dir/$f";
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}