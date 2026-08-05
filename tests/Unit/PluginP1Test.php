<?php

namespace Tests;

use Core\Plugin\PluginManager;

/**
 * P1 插件模块全面测试 — 覆盖所有新增优化方向。
 * 包括：依赖管理、配置系统、资产入队、短代码/Widget、命名空间沙箱、更新检测、事件总线。
 */
class PluginP1Test extends TestCase
{
    private string $testPluginsDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testPluginsDir = sys_get_temp_dir() . '/plugin_p1_test_' . uniqid();
        @mkdir($this->testPluginsDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->testPluginsDir);
        parent::tearDown();
    }

    // ============================================================
    //  P1-1: 依赖管理
    // ============================================================

    public function test_get_dependencies_returns_empty_for_no_deps(): void
    {
        $this->createPlugin('test-no-deps', [
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: No Deps\n */\n",
        ]);
        $pm = $this->makeManager();
        $this->assertSame([], $pm->getDependencies('test-no-deps'));
    }

    public function test_get_dependencies_returns_list(): void
    {
        $this->createPlugin('test-with-deps', [
            'plugin.json' => json_encode([
                'name' => 'With Deps',
                'depends_on' => ['dep-a', 'dep-b'],
            ]),
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: With Deps\n */\n",
        ]);
        $pm = $this->makeManager();
        $this->assertSame(['dep-a', 'dep-b'], $pm->getDependencies('test-with-deps'));
    }

    public function test_get_dependencies_string_converted_to_array(): void
    {
        $this->createPlugin('test-string-dep', [
            'plugin.json' => json_encode([
                'name' => 'String Dep',
                'depends_on' => 'dep-single',
            ]),
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: String Dep\n */\n",
        ]);
        $pm = $this->makeManager();
        $this->assertSame(['dep-single'], $pm->getDependencies('test-string-dep'));
    }

    public function test_check_dependencies_all_satisfied(): void
    {
        $this->createPlugin('dep-base', ['php' => 'php://' . "<?php\n/**\n * Plugin Name: Dep Base\n */\n"]);
        $this->createPlugin('dep-child', [
            'plugin.json' => json_encode(['name' => 'Dep Child', 'depends_on' => ['dep-base']]),
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: Dep Child\n */\n",
        ]);

        $pm = $this->makeManager();
        $pm->activate('dep-base');
        $result = $pm->checkDependencies('dep-child');
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['missing']);
        $this->assertEmpty($result['inactive']);
    }

    public function test_check_dependencies_missing(): void
    {
        $this->createPlugin('dep-child', [
            'plugin.json' => json_encode(['name' => 'Dep Child', 'depends_on' => ['dep-missing']]),
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: Dep Child\n */\n",
        ]);

        $pm = $this->makeManager();
        $result = $pm->checkDependencies('dep-child');
        $this->assertFalse($result['valid']);
        $this->assertContains('dep-missing', $result['missing']);
    }

    public function test_check_dependencies_inactive(): void
    {
        $this->createPlugin('dep-base', ['php' => 'php://' . "<?php\n/**\n * Plugin Name: Dep Base\n */\n"]);
        $this->createPlugin('dep-child', [
            'plugin.json' => json_encode(['name' => 'Dep Child', 'depends_on' => ['dep-base']]),
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: Dep Child\n */\n",
        ]);

        $pm = $this->makeManager();
        $result = $pm->checkDependencies('dep-child');
        $this->assertFalse($result['valid']);
        $this->assertContains('dep-base', $result['inactive']);
    }

    public function test_get_dependents(): void
    {
        $this->createPlugin('dep-base', ['php' => 'php://' . "<?php\n/**\n * Plugin Name: Dep Base\n */\n"]);
        $this->createPlugin('dep-child', [
            'plugin.json' => json_encode(['name' => 'Dep Child', 'depends_on' => ['dep-base']]),
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: Dep Child\n */\n",
        ]);
        $this->createPlugin('dep-child2', [
            'plugin.json' => json_encode(['name' => 'Dep Child 2', 'depends_on' => ['dep-base']]),
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: Dep Child 2\n */\n",
        ]);

        $pm = $this->makeManager();
        $dependents = $pm->getDependents('dep-base');
        sort($dependents);
        $this->assertSame(['dep-child', 'dep-child2'], $dependents);
    }

    public function test_detect_circular_dependencies(): void
    {
        $this->createPlugin('circ-a', [
            'plugin.json' => json_encode(['name' => 'Circ A', 'depends_on' => ['circ-b']]),
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: Circ A\n */\n",
        ]);
        $this->createPlugin('circ-b', [
            'plugin.json' => json_encode(['name' => 'Circ B', 'depends_on' => ['circ-a']]),
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: Circ B\n */\n",
        ]);

        $pm = $this->makeManager();
        $cycles = $pm->detectCircularDependencies();
        $this->assertNotEmpty($cycles);
        $found = false;
        foreach ($cycles as $cycle) {
            if (in_array('circ-a', $cycle) && in_array('circ-b', $cycle)) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Should detect circular dependency between circ-a and circ-b');
    }

    public function test_activate_cascades_dependencies(): void
    {
        $this->createPlugin('dep-base', ['php' => 'php://' . "<?php\n/**\n * Plugin Name: Dep Base\n */\n"]);
        $this->createPlugin('dep-child', [
            'plugin.json' => json_encode(['name' => 'Dep Child', 'depends_on' => ['dep-base']]),
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: Dep Child\n */\n",
        ]);

        $pm = $this->makeManager();
        $pm->activate('dep-child');
        $this->assertTrue($pm->isActive('dep-base'), 'Dependency should be auto-activated');
        $this->assertTrue($pm->isActive('dep-child'));
    }

    public function test_deactivate_blocked_by_dependents(): void
    {
        $this->createPlugin('dep-base', ['php' => 'php://' . "<?php\n/**\n * Plugin Name: Dep Base\n */\n"]);
        $this->createPlugin('dep-child', [
            'plugin.json' => json_encode(['name' => 'Dep Child', 'depends_on' => ['dep-base']]),
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: Dep Child\n */\n",
        ]);

        $pm = $this->makeManager();
        $pm->activate('dep-child');
        $this->expectException(\RuntimeException::class);
        $pm->deactivate('dep-base');
    }

    public function test_uninstall_blocked_by_dependents(): void
    {
        $this->createPlugin('dep-base', ['php' => 'php://' . "<?php\n/**\n * Plugin Name: Dep Base\n */\n"]);
        $this->createPlugin('dep-child', [
            'plugin.json' => json_encode(['name' => 'Dep Child', 'depends_on' => ['dep-base']]),
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: Dep Child\n */\n",
        ]);

        $pm = $this->makeManager();
        $pm->activate('dep-child');
        $this->expectException(\RuntimeException::class);
        $pm->uninstall('dep-base');
    }

    // ============================================================
    //  P1-2: 配置系统
    // ============================================================

    public function test_set_and_get_config(): void
    {
        $this->createPlugin('test-config', [
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: Config Test\n */\n",
        ]);

        $pm = $this->makeManager();
        $pm->setConfig('test-config', 'api_key', 'abc123');
        $this->assertSame('abc123', $pm->getConfig('test-config', 'api_key'));
    }

    public function test_get_config_default(): void
    {
        $this->createPlugin('test-config-def', [
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: Config Default\n */\n",
        ]);

        $pm = $this->makeManager();
        $this->assertSame('default_val', $pm->getConfig('test-config-def', 'nonexistent', 'default_val'));
    }

    public function test_get_all_config(): void
    {
        $this->createPlugin('test-all-config', [
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: All Config\n */\n",
        ]);

        $pm = $this->makeManager();
        $pm->setConfig('test-all-config', 'key1', 'val1');
        $pm->setConfig('test-all-config', 'key2', 'val2');
        $config = $pm->getAllConfig('test-all-config');
        $this->assertSame('val1', $config['key1']);
        $this->assertSame('val2', $config['key2']);
    }

    public function test_delete_config(): void
    {
        $this->createPlugin('test-del-config', [
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: Del Config\n */\n",
        ]);

        $pm = $this->makeManager();
        $pm->setConfig('test-del-config', 'temp', 'value');
        $this->assertSame('value', $pm->getConfig('test-del-config', 'temp'));
        $pm->deleteConfig('test-del-config', 'temp');
        $this->assertNull($pm->getConfig('test-del-config', 'temp'));
    }

    public function test_clear_all_config(): void
    {
        $this->createPlugin('test-clear-config', [
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: Clear Config\n */\n",
        ]);

        $pm = $this->makeManager();
        $pm->setConfig('test-clear-config', 'a', '1');
        $pm->setConfig('test-clear-config', 'b', '2');
        $pm->clearAllConfig('test-clear-config');
        $this->assertEmpty($pm->getAllConfig('test-clear-config'));
    }

    // ============================================================
    //  P1-3: 资产入队（CSS/JS）
    // ============================================================

    public function test_enqueue_style(): void
    {
        $pm = $this->makeManager();
        $pm->enqueueStyle('test-plugin', 'main-style', '/assets/plugin.css', 'all');
        $styles = $pm->getEnqueuedStyles();
        $this->assertArrayHasKey('test-plugin', $styles);
        $this->assertSame('/assets/plugin.css', $styles['test-plugin'][0]['style']);
        $this->assertSame('all', $styles['test-plugin'][0]['media']);
    }

    public function test_enqueue_script(): void
    {
        $pm = $this->makeManager();
        $pm->enqueueScript('test-plugin', 'main-script', '/assets/plugin.js', ['defer' => true]);
        $scripts = $pm->getEnqueuedScripts();
        $this->assertArrayHasKey('test-plugin', $scripts);
        $this->assertSame('/assets/plugin.js', $scripts['test-plugin'][0]['script']);
        $this->assertSame(['defer' => true], $scripts['test-plugin'][0]['attributes']);
    }

    public function test_multiple_enqueues(): void
    {
        $pm = $this->makeManager();
        $pm->enqueueStyle('p1', 'a', '/a.css');
        $pm->enqueueStyle('p1', 'b', '/b.css');
        $pm->enqueueStyle('p2', 'c', '/c.css');
        $this->assertCount(2, $pm->getEnqueuedStyles()['p1']);
        $this->assertCount(1, $pm->getEnqueuedStyles()['p2']);
    }

    // ============================================================
    //  P1-4: 短代码与 Widget
    // ============================================================

    public function test_register_shortcode(): void
    {
        $pm = $this->makeManager();
        $cb = fn () => 'hello';
        $pm->registerShortcode('test-plugin', 'hello_world', $cb);
        $shortcodes = $pm->getShortcodes();
        $this->assertArrayHasKey('hello_world', $shortcodes);
        $this->assertSame('test-plugin', $shortcodes['hello_world']['id']);
        $this->assertSame('hello', ($shortcodes['hello_world']['callback'])());
    }

    public function test_register_widget(): void
    {
        $pm = $this->makeManager();
        $cb = fn () => 'widget content';
        $pm->registerWidget('test-plugin', 'my_widget', $cb, 'A test widget');
        $widgets = $pm->getWidgets();
        $this->assertArrayHasKey('my_widget', $widgets);
        $this->assertSame('test-plugin', $widgets['my_widget']['plugin']);
        $this->assertSame('A test widget', $widgets['my_widget']['description']);
        $this->assertSame('widget content', ($widgets['my_widget']['render'])());
    }

    // ============================================================
    //  P1-5: 命名空间沙箱与 PSR-4 Autoloader
    // ============================================================

    public function test_plugin_autoloader_registration(): void
    {
        $this->createPlugin('test-ns', [
            'plugin.json' => json_encode([
                'name' => 'NS Plugin',
                'namespace' => 'MyPlugin\\NS',
            ]),
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: NS Plugin\n */\n",
        ]);

        $pm = $this->makeManager();
        $autoloaders = $pm->getPluginAutoloaders();
        $this->assertArrayHasKey('test-ns', $autoloaders);
        $this->assertSame('MyPlugin\\NS\\', $autoloaders['test-ns']['name']);
        $this->assertStringContainsString('test-ns', $autoloaders['test-ns']['prefix']);
    }

    public function test_plugin_without_namespace_no_autoloader(): void
    {
        $this->createPlugin('test-no-ns', [
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: No NS\n */\n",
        ]);

        $pm = $this->makeManager();
        $autoloaders = $pm->getPluginAutoloaders();
        $this->assertArrayNotHasKey('test-no-ns', $autoloaders);
    }

    // ============================================================
    //  P1-6: 兼容性检查
    // ============================================================

    public function test_validate_compatibility_with_requires_core(): void
    {
        $this->createPlugin('test-core-req', [
            'plugin.json' => json_encode([
                'name' => 'Core Req',
                'min_version' => '1.0.0',
            ]),
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: Core Req\n */\n",
        ]);

        $pm = $this->makeManager();
        $result = $pm->validateCompatibility('test-core-req');
        // Should pass since CMS_VERSION is 2.0.0 (default)
        $this->assertTrue($result['valid']);
    }

    public function test_validate_compatibility_extensions_pass(): void
    {
        $this->createPlugin('test-ext-pass', [
            'plugin.json' => json_encode([
                'name' => 'Ext Pass',
                'requires' => [
                    'extensions' => ['json', 'pdo'],
                ],
            ]),
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: Ext Pass\n */\n",
        ]);

        $pm = $this->makeManager();
        $result = $pm->validateCompatibility('test-ext-pass');
        $this->assertTrue($result['valid']);
    }

    // ============================================================
    //  P1-7: 事件总线
    // ============================================================

    public function test_event_on_and_emit(): void
    {
        $pm = $this->makeManager();
        $result = '';
        $pm->on('test_event', function ($msg) use (&$result) {
            $result = $msg;
        });
        $pm->emit('test_event', 'hello');
        $this->assertSame('hello', $result);
    }

    public function test_event_multiple_listeners(): void
    {
        $pm = $this->makeManager();
        $results = [];
        $pm->on('multi', function ($v) use (&$results) { $results[] = $v . 'a'; });
        $pm->on('multi', function ($v) use (&$results) { $results[] = $v . 'b'; });
        $pm->emit('multi', 'x');
        $this->assertSame(['xa', 'xb'], $results);
    }

    public function test_event_off_removes_listener(): void
    {
        $pm = $this->makeManager();
        $called = false;
        $cb = function () use (&$called) { $called = true; };
        $pm->on('test', $cb);
        $pm->off('test', $cb);
        $pm->emit('test');
        $this->assertFalse($called);
    }

    public function test_event_off_all(): void
    {
        $pm = $this->makeManager();
        $called = false;
        $pm->on('test', function () use (&$called) { $called = true; });
        $pm->off('test');
        $pm->emit('test');
        $this->assertFalse($called);
    }

    public function test_event_listener_exception_does_not_block(): void
    {
        $pm = $this->makeManager();
        $calledSecond = false;
        $pm->on('test', function () { throw new \RuntimeException('fail'); });
        $pm->on('test', function () use (&$calledSecond) { $calledSecond = true; });
        $pm->emit('test');
        $this->assertTrue($calledSecond);
    }

    // ============================================================
    //  P1-8: 插件详情 getPlugin
    // ============================================================

    public function test_get_plugin_returns_detail(): void
    {
        $this->createPlugin('test-detail', [
            'plugin.json' => json_encode([
                'name' => 'Detail Test',
                'version' => '2.0.0',
                'depends_on' => ['some-dep'],
            ]),
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: Detail Test\n */\n",
        ]);

        $pm = $this->makeManager();
        $plugin = $pm->getPlugin('test-detail');
        $this->assertNotNull($plugin);
        $this->assertSame('test-detail', $plugin['name']);
        $this->assertSame('Detail Test', $plugin['meta']['name']);
        $this->assertSame('2.0.0', $plugin['meta']['version']);
        $this->assertFalse($plugin['active']);
        $this->assertArrayHasKey('dependencies', $plugin);
        $this->assertArrayHasKey('dependents', $plugin);
        $this->assertArrayHasKey('errors', $plugin);
    }

    public function test_get_plugin_nonexistent_returns_null(): void
    {
        $pm = $this->makeManager();
        $this->assertNull($pm->getPlugin('no-such-plugin'));
    }

    // ============================================================
    //  边界情况：无插件目录、空目录
    // ============================================================

    public function test_no_plugins_directory(): void
    {
        $pm = new PluginManager();
        $ref = new \ReflectionProperty(PluginManager::class, 'pluginRoot');
        $ref->setAccessible(true);
        $ref->setValue($pm, '/tmp/nonexistent_plugin_dir_' . uniqid());
        $pm->boot();
        $this->assertEmpty($pm->listPlugins());
    }

    public function test_plugin_json_with_changelog_field(): void
    {
        $this->createPlugin('test-changelog', [
            'plugin.json' => json_encode([
                'name' => 'Changelog Plugin',
                'version' => '1.0.0',
                'changelog' => 'v1.0.0: Initial release',
            ]),
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: Changelog Plugin\n */\n",
        ]);

        $pm = $this->makeManager();
        $list = $pm->listPlugins();
        $this->assertArrayHasKey('test-changelog', $list);
        $this->assertSame('Changelog Plugin', $list['test-changelog']['meta']['name']);
        $this->assertSame('v1.0.0: Initial release', $list['test-changelog']['meta']['changelog']);
    }

    // ============================================================
    //  Helper 方法
    // ============================================================

    private function createPlugin(string $name, array $files): void
    {
        $dir = $this->testPluginsDir . '/' . $name;
        @mkdir($dir, 0777, true);

        if (isset($files['php'])) {
            $content = $files['php'];
            if (str_starts_with($content, 'php://')) {
                $content = substr($content, 6);
            }
            file_put_contents("$dir/$name.php", $content);
        }

        if (isset($files['plugin.json'])) {
            file_put_contents("$dir/plugin.json", $files['plugin.json']);
        }

        foreach ($files as $k => $v) {
            if ($k !== 'php' && $k !== 'plugin.json') {
                file_put_contents("$dir/$k", $v);
            }
        }
    }

    private function makeManager(): PluginManager
    {
        $pm = new PluginManager();
        $this->app->singleton(PluginManager::class, fn () => $pm);

        $ref = new \ReflectionProperty(PluginManager::class, 'pluginRoot');
        $ref->setAccessible(true);
        $ref->setValue($pm, $this->testPluginsDir);

        $props = ['booted', 'plugins', 'active', 'loaded', 'errors', 'activationHooks', 'deactivationHooks', 'uninstallHooks'];
        foreach ($props as $prop) {
            $r = new \ReflectionProperty(PluginManager::class, $prop);
            $r->setAccessible(true);
            $val = ($prop === 'booted') ? false : [];
            $r->setValue($pm, $val);
        }

        $pm->boot();
        return $pm;
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (array_diff(scandir($dir), ['.', '..']) as $f) {
            $p = "$dir/$f";
            is_dir($p) ? $this->rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}