<?php

namespace Tests;

use Core\Plugin\PluginManager;
use Core\Hook\Action;
use Core\Hook\Filter;

/**
 * P0 插件模块深度测试 — 覆盖所有修改和边界场景。
 */
class PluginDeepTest extends TestCase
{
    private string $origPluginDir;
    private string $testPluginsDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->origPluginDir = plugins_path();
        $this->testPluginsDir = sys_get_temp_dir() . '/plugin_test_' . uniqid();
        @mkdir($this->testPluginsDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->testPluginsDir);
        parent::tearDown();
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

    // ============================================================
    //  1. 基础扫描与加载
    // ============================================================

    public function test_scan_detects_plugin_with_php_headers(): void
    {
        $this->createPlugin('test-one', [
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: Test One\n * Description: Desc\n * Version: 1.0.0\n * Author: Tester\n */\n",
        ]);

        $pm = $this->makeManager();
        $list = $pm->listPlugins();
        $this->assertArrayHasKey('test-one', $list);
        $this->assertSame('Test One', $list['test-one']['meta']['name']);
        $this->assertSame('1.0.0', $list['test-one']['meta']['version']);
        $this->assertFalse($list['test-one']['active']);
    }

    public function test_scan_detects_plugin_with_plugin_json(): void
    {
        // plugin.json + PHP 文件
        $this->createPlugin('test-json', [
            'plugin.json' => json_encode([
                'name' => 'JSON Plugin',
                'description' => 'From JSON',
                'version' => '2.1.0',
                'author' => 'JSON Author',
            ]),
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: JSON Plugin\n */\n",
        ]);

        $pm = $this->makeManager();
        $list = $pm->listPlugins();
        $this->assertArrayHasKey('test-json', $list);
        $this->assertSame('JSON Plugin', $list['test-json']['meta']['name']);
        $this->assertSame('2.1.0', $list['test-json']['meta']['version']);
    }

    public function test_json_priority_over_php_headers(): void
    {
        $this->createPlugin('test-prio', [
            'plugin.json' => json_encode([
                'name' => 'JSON Name',
                'version' => '2.0.0',
            ]),
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: PHP Name\n * Version: 1.0.0\n */\n",
        ]);

        $pm = $this->makeManager();
        $list = $pm->listPlugins();
        $this->assertSame('JSON Name', $list['test-prio']['meta']['name']);
        $this->assertSame('2.0.0', $list['test-prio']['meta']['version']);
    }

    public function test_json_php_merge_php_fills_missing(): void
    {
        $this->createPlugin('test-merge', [
            'plugin.json' => json_encode([
                'name' => 'Merge Plugin',
            ]),
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: Merge Plugin\n * Description: PHP Desc\n * Version: 3.0.0\n */\n",
        ]);

        $pm = $this->makeManager();
        $list = $pm->listPlugins();
        $this->assertSame('Merge Plugin', $list['test-merge']['meta']['name']);
        $this->assertSame('PHP Desc', $list['test-merge']['meta']['description']);
        $this->assertSame('3.0.0', $list['test-merge']['meta']['version']);
    }

    // ============================================================
    //  2. 激活/停用/卸载流程
    // ============================================================

    public function test_activate_plugin(): void
    {
        $this->createPlugin('test-act', [
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: Act Test\n */\n",
        ]);

        $pm = $this->makeManager();
        $pm->activate('test-act');
        $this->assertTrue($pm->isActive('test-act'));
    }

    public function test_activate_nonexistent_throws(): void
    {
        $this->expectException(\RuntimeException::class);
        $pm = $this->makeManager();
        $pm->activate('no-such-plugin');
    }

    public function test_double_activate_is_idempotent(): void
    {
        $this->createPlugin('test-idem', [
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: Idem\n */\n",
        ]);

        $pm = $this->makeManager();
        $pm->activate('test-idem');
        $pm->activate('test-idem'); // should not throw
        $this->assertTrue($pm->isActive('test-idem'));
    }

    public function test_deactivate_plugin(): void
    {
        $this->createPlugin('test-deact', [
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: Deact Test\n */\n",
        ]);

        $pm = $this->makeManager();
        $pm->activate('test-deact');
        $this->assertTrue($pm->isActive('test-deact'));
        $pm->deactivate('test-deact');
        $this->assertFalse($pm->isActive('test-deact'));
    }

    public function test_deactivate_nonexistent_does_not_throw(): void
    {
        $pm = $this->makeManager();
        $pm->deactivate('no-such-plugin');
        $this->assertTrue(true);
    }

    public function test_uninstall_removes_directory(): void
    {
        $this->createPlugin('test-uninstall', [
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: Uninstall Test\n */\n",
        ]);

        $pm = $this->makeManager();
        $pm->activate('test-uninstall');
        $this->assertTrue(is_dir($this->testPluginsDir . '/test-uninstall'));
        $pm->uninstall('test-uninstall');
        $this->assertFalse(is_dir($this->testPluginsDir . '/test-uninstall'));
    }

    // ============================================================
    //  3. 钩子注册与执行
    // ============================================================

    public function test_activation_hook_is_called(): void
    {
        $this->createPlugin('test-hook', [
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: Hook Test\n */\n" .
                "register_activation_hook(function() {\n" .
                "    file_put_contents('/tmp/plugin_hook_flag', 'activated');\n" .
                "});\n",
        ]);

        @unlink('/tmp/plugin_hook_flag');
        $pm = $this->makeManager();
        $pm->activate('test-hook');
        $this->assertFileExists('/tmp/plugin_hook_flag');
        $this->assertStringEqualsFile('/tmp/plugin_hook_flag', 'activated');
        @unlink('/tmp/plugin_hook_flag');
    }

    public function test_deactivation_hook_is_called(): void
    {
        $this->createPlugin('test-deact-hook', [
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: Deact Hook\n */\n" .
                "register_deactivation_hook(function() {\n" .
                "    file_put_contents('/tmp/plugin_deact_flag', 'deactivated');\n" .
                "});\n",
        ]);

        @unlink('/tmp/plugin_deact_flag');
        $pm = $this->makeManager();
        $pm->activate('test-deact-hook');
        $pm->deactivate('test-deact-hook');
        $this->assertFileExists('/tmp/plugin_deact_flag');
        $this->assertStringEqualsFile('/tmp/plugin_deact_flag', 'deactivated');
        @unlink('/tmp/plugin_deact_flag');
    }

    public function test_uninstall_hook_is_called(): void
    {
        $this->createPlugin('test-uninstall-hook', [
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: Uninstall Hook\n */\n" .
                "register_uninstall_hook(function() {\n" .
                "    file_put_contents('/tmp/plugin_uninstall_flag', 'uninstalled');\n" .
                "});\n",
        ]);

        @unlink('/tmp/plugin_uninstall_flag');
        $pm = $this->makeManager();
        $pm->activate('test-uninstall-hook');
        $pm->uninstall('test-uninstall-hook');
        $this->assertFileExists('/tmp/plugin_uninstall_flag');
        $this->assertStringEqualsFile('/tmp/plugin_uninstall_flag', 'uninstalled');
        @unlink('/tmp/plugin_uninstall_flag');
    }

    public function test_hook_exception_does_not_block_activation(): void
    {
        $this->createPlugin('test-hook-ex', [
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: Hook Exception\n */\n" .
                "register_activation_hook(function() {\n" .
                "    throw new \RuntimeException('hook failed');\n" .
                "});\n",
        ]);

        $pm = $this->makeManager();
        $pm->activate('test-hook-ex');
        $this->assertTrue($pm->isActive('test-hook-ex'));
        $errors = $pm->getErrors();
        $this->assertArrayHasKey('test-hook-ex', $errors);
        $this->assertStringContainsString('activation_hook_error', $errors['test-hook-ex'] ?? '');
    }

    // ============================================================
    //  4. 错误隔离
    // ============================================================

    public function test_broken_plugin_does_not_crash_system(): void
    {
        // Plugin with syntax error
        $this->createPlugin('test-broken', [
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: Broken\n */\n" . "syntax error {{{{\n",
        ]);

        // A healthy plugin
        $this->createPlugin('test-healthy', [
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: Healthy\n */\n" . "add_action('test_hook', function() { });\n",
        ]);

        $pm = $this->makeManager();
        // Activate healthy plugin — should work
        $pm->activate('test-healthy');
        $this->assertTrue($pm->isActive('test-healthy'));

        // Activate broken plugin — load error is caught
        $pm->activate('test-broken');
        $errors = $pm->getErrors();
        $this->assertArrayHasKey('test-broken', $errors);
        $this->assertStringContainsString('load_error', $errors['test-broken'] ?? '');
    }

    public function test_healthy_plugins_work_after_broken_one(): void
    {
        $this->createPlugin('test-broken2', [
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: Broken2\n */\n" . "syntax error {{{{\n",
        ]);
        $this->createPlugin('test-healthy2', [
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: Healthy2\n */\n",
        ]);

        $pm = $this->makeManager();
        $pm->activate('test-healthy2');
        $this->assertTrue($pm->isActive('test-healthy2'));
    }

    // ============================================================
    //  5. 兼容性检查
    // ============================================================

    public function test_validate_compatibility_php_version_pass(): void
    {
        $this->createPlugin('test-compat', [
            'plugin.json' => json_encode([
                'name' => 'Compat Test',
                'php_version' => '>=8.0',
            ]),
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: Compat Test\n */\n",
        ]);

        $pm = $this->makeManager();
        $result = $pm->validateCompatibility('test-compat');
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function test_validate_compatibility_php_version_fail(): void
    {
        $this->createPlugin('test-compat-fail', [
            'plugin.json' => json_encode([
                'name' => 'Compat Fail',
                'php_version' => '>=999.0',
            ]),
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: Compat Fail\n */\n",
        ]);

        $pm = $this->makeManager();
        $result = $pm->validateCompatibility('test-compat-fail');
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_validate_compatibility_missing_extension(): void
    {
        $this->createPlugin('test-ext', [
            'plugin.json' => json_encode([
                'name' => 'Ext Test',
                'requires' => [
                    'extensions' => ['this_extension_does_not_exist_xyz'],
                ],
            ]),
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: Ext Test\n */\n",
        ]);

        $pm = $this->makeManager();
        $result = $pm->validateCompatibility('test-ext');
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('this_extension_does_not_exist_xyz', implode(' ', $result['errors']));
    }

    public function test_validate_compatibility_nonexistent_plugin(): void
    {
        $pm = $this->makeManager();
        $result = $pm->validateCompatibility('no-such-plugin');
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('不存在', implode(' ', $result['errors']));
    }

    // ============================================================
    //  6. loadPlugin 全局变量追踪
    // ============================================================

    public function test_plugin_loading_current_plugin_is_set(): void
    {
        $this->createPlugin('test-global', [
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: Global\n */\n" .
                "global \$__current_plugin_loading;\n" .
                "file_put_contents('/tmp/plugin_global_name', \$__current_plugin_loading);\n",
        ]);

        @unlink('/tmp/plugin_global_name');
        $pm = $this->makeManager();
        $pm->activate('test-global');
        $this->assertFileExists('/tmp/plugin_global_name');
        $this->assertStringEqualsFile('/tmp/plugin_global_name', 'test-global');
        @unlink('/tmp/plugin_global_name');
    }

    // ============================================================
    //  7. 插件的 Hook 系统集成
    // ============================================================

    public function test_plugin_add_action_and_filter(): void
    {
        $this->createPlugin('test-integration', [
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: Integration\n */\n" .
                "add_action('my_integration_action', function() {\n" .
                "    file_put_contents('/tmp/integration_flag', 'action_ran');\n" .
                "});\n" .
                "add_filter('my_integration_filter', function(\$val) {\n" .
                "    return \$val . '_filtered';\n" .
                "});\n",
        ]);

        @unlink('/tmp/integration_flag');
        $pm = $this->makeManager();
        $pm->activate('test-integration');

        // Test action
        do_action('my_integration_action');
        $this->assertFileExists('/tmp/integration_flag');
        $this->assertStringEqualsFile('/tmp/integration_flag', 'action_ran');
        @unlink('/tmp/integration_flag');

        // Test filter
        $result = apply_filters('my_integration_filter', 'hello');
        $this->assertSame('hello_filtered', $result);
    }

    // ============================================================
    //  8. 边界情况
    // ============================================================

    public function test_parse_plugin_headers_empty(): void
    {
        $this->createPlugin('test-empty', [
            'php' => 'php://' . "<?php\n// no headers\n",
        ]);

        $pm = $this->makeManager();
        $list = $pm->listPlugins();
        $this->assertArrayHasKey('test-empty', $list);
        $this->assertEmpty($list['test-empty']['meta']['name'] ?? '');
    }

    public function test_plugin_with_index_fallback(): void
    {
        $dir = $this->testPluginsDir . '/test-index';
        @mkdir($dir, 0777, true);
        file_put_contents("$dir/index.php", "<?php\n/**\n * Plugin Name: Index Fallback\n */\n");

        $pm = $this->makeManager();
        $list = $pm->listPlugins();
        $this->assertArrayHasKey('test-index', $list);
        $this->assertSame('Index Fallback', $list['test-index']['meta']['name']);
    }

    public function test_plugin_with_json_entry_field(): void
    {
        $dir = $this->testPluginsDir . '/test-entry';
        @mkdir($dir, 0777, true);
        file_put_contents("$dir/plugin.json", json_encode([
            'name' => 'Entry Plugin',
            'entry' => 'src/bootstrap.php',
        ]));
        @mkdir("$dir/src", 0777, true);
        file_put_contents("$dir/src/bootstrap.php", "<?php\n/**\n * Plugin Name: Entry Plugin\n */\n");

        $pm = $this->makeManager();
        $list = $pm->listPlugins();
        $this->assertArrayHasKey('test-entry', $list);
        $this->assertSame('Entry Plugin', $list['test-entry']['meta']['name']);
    }

    // ============================================================
    //  9. 插件持久化状态
    // ============================================================

    public function test_active_list_persisted_to_database(): void
    {
        $this->createPlugin('test-persist', [
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: Persist Test\n */\n",
        ]);

        $pm = $this->makeManager();
        $pm->activate('test-persist');

        // Check active list
        $active = $pm->getActiveList();
        $this->assertArrayHasKey('test-persist', $active);

        // Verify in database
        $qb = app(\Core\Database\QueryBuilder::class);
        $row = $qb->table('options')->where('key_name', 'active_plugins')->first();
        $this->assertNotEmpty($row);
        $stored = json_decode($row['value'], true);
        $this->assertContains('test-persist', $stored);

        // Cleanup
        $pm->deactivate('test-persist');
    }

    // ============================================================
    //  10. 多个插件同时激活
    // ============================================================

    public function test_multiple_plugins_activation(): void
    {
        $this->createPlugin('multi-a', [
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: Multi A\n */\n",
        ]);
        $this->createPlugin('multi-b', [
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: Multi B\n */\n",
        ]);

        $pm = $this->makeManager();
        $pm->activate('multi-a');
        $pm->activate('multi-b');

        $this->assertTrue($pm->isActive('multi-a'));
        $this->assertTrue($pm->isActive('multi-b'));

        $pm->deactivate('multi-a');
        $this->assertFalse($pm->isActive('multi-a'));
        $this->assertTrue($pm->isActive('multi-b'));
    }

    // ============================================================
    //  11. loadPlugin 嵌套保护
    // ============================================================

    public function test_double_load_plugin_is_idempotent(): void
    {
        $this->createPlugin('test-double-load', [
            'php' => 'php://' . "<?php\n/**\n * Plugin Name: Double Load\n */\n" .
                "file_put_contents('/tmp/double_load_flag', 'loaded');\n",
        ]);

        @unlink('/tmp/double_load_flag');
        $pm = $this->makeManager();
        $pm->activate('test-double-load');
        // Second load should be skipped (loaded flag stays)
        $pm->activate('test-double-load');
        $this->assertFileExists('/tmp/double_load_flag');
        @unlink('/tmp/double_load_flag');
    }

    // ============================================================
    //  Helper: 创建测试插件
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

        // 注册到容器，使 register_activation_hook 等函数能通过 app() 找到
        $this->app->singleton(PluginManager::class, fn () => $pm);

        // Override the pluginRoot via reflection
        $ref = new \ReflectionProperty(PluginManager::class, 'pluginRoot');
        $ref->setAccessible(true);
        $ref->setValue($pm, $this->testPluginsDir);

        // Reset internal state
        $props = ['booted', 'plugins', 'active', 'loaded', 'errors', 'activationHooks', 'deactivationHooks', 'uninstallHooks'];
        foreach ($props as $prop) {
            $r = new \ReflectionProperty(PluginManager::class, $prop);
            $r->setAccessible(true);
            $val = ($prop === 'booted') ? false : [];
            $r->setValue($pm, $val);
        }

        // Boot to scan
        $pm->boot();

        return $pm;
    }
}