<?php

namespace App\Controllers\Admin;

use Core\Plugin\PluginManager;
use Core\Http\Response;
use Core\Http\Request;
use Core\Http\Session;

class PluginController
{
    public function index(array $params = []): Response
    {
        can_or_403('activate_plugins');
        $pm = app(PluginManager::class);
        $plugins = $pm->listPlugins();
        $active = $pm->getActiveList();
        $errors = $pm->getErrors();

        // 搜索与筛选
        $search = trim($_GET['q'] ?? '');
        $statusFilter = trim($_GET['status'] ?? '');
        if ($search !== '') {
            $plugins = array_filter($plugins, fn ($p) =>
                stripos($p['name'], $search) !== false ||
                stripos($p['meta']['name'] ?? '', $search) !== false ||
                stripos($p['meta']['description'] ?? '', $search) !== false
            );
        }
        if ($statusFilter === 'active') {
            $plugins = array_filter($plugins, fn ($p) => $p['active']);
        } elseif ($statusFilter === 'inactive') {
            $plugins = array_filter($plugins, fn ($p) => !$p['active']);
        } elseif ($statusFilter === 'error') {
            $plugins = array_filter($plugins, fn ($p) => isset($errors[$p['name']]));
        }

        // 检查循环依赖
        $cycles = $pm->detectCircularDependencies();
        $hasCycles = !empty($cycles);

        return view('admin.plugins.index', [
            'plugins'   => $plugins,
            'active'    => $active,
            'errors'    => $errors,
            'search'    => $search,
            'statusFilter' => $statusFilter,
            'cycles'    => $cycles,
            'hasCycles' => $hasCycles,
            'pageTitle' => '插件管理',
        ]);
    }

    public function detail(array $params): Response
    {
        can_or_403('activate_plugins');
        $name = $params['name'] ?? '';
        $pm = app(PluginManager::class);
        $plugin = $pm->getPlugin($name);
        if ($plugin === null) {
            app(Session::class)->flash('error', "插件 [$name] 不存在");
            return redirect(route('admin.plugins.index'));
        }

        // 检查更新
        $updateInfo = $pm->checkForUpdates($name);

        // 获取配置
        $config = $pm->getAllConfig($name);

        return view('admin.plugins.detail', [
            'plugin'     => $plugin,
            'updateInfo' => $updateInfo,
            'config'     => $config,
            'pageTitle'  => '插件详情 - ' . ($plugin['meta']['name'] ?? $name),
        ]);
    }

    public function activate(array $params): Response
    {
        can_or_403('activate_plugins');
        $name = $params['name'] ?? '';
        $pm = app(PluginManager::class);

        // 兼容性检查
        $compat = $pm->validateCompatibility($name);
        if (!$compat['valid']) {
            app(Session::class)->flash('error', '插件不兼容: ' . implode('; ', $compat['errors']));
            return redirect(route('admin.plugins.index'));
        }

        try {
            $pm->activate($name);
            app(Session::class)->flash('success', "插件 [$name] 已激活");
        } catch (\Throwable $e) {
            app(Session::class)->flash('error', $e->getMessage());
        }
        return redirect(route('admin.plugins.index'));
    }

    public function deactivate(array $params): Response
    {
        can_or_403('activate_plugins');
        $name = $params['name'] ?? '';
        $pm = app(PluginManager::class);
        try {
            $pm->deactivate($name);
            app(Session::class)->flash('success', "插件 [$name] 已停用");
        } catch (\Throwable $e) {
            app(Session::class)->flash('error', $e->getMessage());
        }
        return redirect(route('admin.plugins.index'));
    }

    public function upload(): Response
    {
        can_or_403('activate_plugins');
        $request = app(Request::class);
        $sess = app(Session::class);
        $file = $request->file('plugin_zip');
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $sess->flash('error', '上传失败');
            return redirect(route('admin.plugins.index'));
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'zip') {
            $sess->flash('error', '请上传 zip 格式的插件包');
            return redirect(route('admin.plugins.index'));
        }
        $pm = app(PluginManager::class);
        try {
            $info = $pm->installFromZip($file['tmp_name']);
            $sess->flash('success', "插件 [{$info['name']}] 已上传");
        } catch (\Throwable $e) {
            $sess->flash('error', '插件上传失败: ' . $e->getMessage());
        }
        return redirect(route('admin.plugins.index'));
    }

    public function delete(array $params): Response
    {
        can_or_403('activate_plugins');
        $name = $params['name'] ?? '';
        $pm = app(PluginManager::class);
        try {
            $pm->uninstall($name);
            app(Session::class)->flash('success', "插件 [$name] 已删除");
        } catch (\Throwable $e) {
            app(Session::class)->flash('error', '删除失败: ' . $e->getMessage());
        }
        return redirect(route('admin.plugins.index'));
    }

    public function batch(): Response
    {
        can_or_403('activate_plugins');
        $request = app(Request::class);
        $sess = app(Session::class);
        $body = $request->getBody();
        $ids = explode(',', $body['batch_ids'] ?? '');
        $action = $body['batch_action'] ?? '';
        $pm = app(PluginManager::class);

        $success = 0;
        $fail = 0;
        foreach ($ids as $name) {
            $name = trim($name);
            if ($name === '') continue;
            try {
                if ($action === 'activate') {
                    $pm->activate($name);
                } elseif ($action === 'deactivate') {
                    $pm->deactivate($name);
                } elseif ($action === 'delete') {
                    $pm->uninstall($name);
                }
                $success++;
            } catch (\Throwable $e) {
                $fail++;
            }
        }

        if ($success > 0) {
            $sess->flash('success', "批量操作完成：{$success} 成功，{$fail} 失败");
        } else {
            $sess->flash('error', "批量操作失败：{$fail} 个全部失败");
        }
        return redirect(route('admin.plugins.index'));
    }

    public function saveConfig(array $params): Response
    {
        can_or_403('activate_plugins');
        $name = $params['name'] ?? '';
        $request = app(Request::class);
        $body = $request->getBody();
        $pm = app(PluginManager::class);

        foreach ($body as $key => $value) {
            if (str_starts_with($key, '_')) continue;
            $pm->setConfig($name, $key, $value);
        }

        app(Session::class)->flash('success', "插件 [$name] 配置已保存");
        return redirect(route('admin.plugins.detail', ['name' => $name]));
    }

    public function checkUpdate(array $params): Response
    {
        can_or_403('activate_plugins');
        $name = $params['name'] ?? '';
        $pm = app(PluginManager::class);
        $updateInfo = $pm->checkForUpdates($name);

        if ($updateInfo === null) {
            app(Session::class)->flash('info', "插件 [$name] 无可用更新或未配置更新地址");
        } elseif ($updateInfo['update_available']) {
            app(Session::class)->flash('info', "插件 [$name] 有新版本 {$updateInfo['latest_version']} 可用");
        } else {
            app(Session::class)->flash('success', "插件 [$name] 已是最新版本");
        }

        return redirect(route('admin.plugins.detail', ['name' => $name]));
    }

    public function doUpdate(array $params): Response
    {
        can_or_403('activate_plugins');
        $name = $params['name'] ?? '';
        $pm = app(PluginManager::class);
        try {
            $result = $pm->updateFromUrl($name);
            app(Session::class)->flash('success', "插件 [$name] 已更新至版本 {$result['meta']['version']}");
        } catch (\Throwable $e) {
            app(Session::class)->flash('error', '更新失败: ' . $e->getMessage());
        }
        return redirect(route('admin.plugins.detail', ['name' => $name]));
    }
}