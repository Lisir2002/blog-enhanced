<?php

namespace App\Controllers\Admin;

use Core\Plugin\PluginManager;
use Core\Http\Response;
use Core\Http\Request;
use Core\Http\Session;

class PluginController
{
    public function index(): Response
    {
        $pm = app(PluginManager::class);
        $plugins = $pm->listPlugins();
        $active = $pm->getActiveList();

        return view('admin.plugins.index', [
            'plugins'   => $plugins,
            'active'    => $active,
            'pageTitle' => '插件管理',
        ]);
    }

    public function activate(array $params): Response
    {
        $name = $params['name'] ?? '';
        $pm = app(PluginManager::class);
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
            $info = $pm->uploadZip($file['tmp_name'], $file['name']);
            $sess->flash('success', "插件 [{$info['name']}] 已上传");
        } catch (\Throwable $e) {
            $sess->flash('error', '插件上传失败: ' . $e->getMessage());
        }
        return redirect(route('admin.plugins.index'));
    }

    public function delete(array $params): Response
    {
        $name = $params['name'] ?? '';
        $pm = app(PluginManager::class);
        if ($pm->deletePlugin($name)) {
            app(Session::class)->flash('success', "插件 [$name] 已删除");
        } else {
            app(Session::class)->flash('error', '无法删除当前激活的插件或插件不存在');
        }
        return redirect(route('admin.plugins.index'));
    }
}
