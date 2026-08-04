<?php

namespace App\Controllers\Admin;

use Core\View\ThemeManager;
use App\Models\Option;
use Core\Http\Response;
use Core\Http\Request;
use Core\Http\Session;

class ThemeController
{
    public function index(): Response
    {
        can_or_403('switch_themes');
        $theme = app(ThemeManager::class);
        $themes = $theme->listThemes();
        $active = $theme->activeTheme();

        return view('admin.themes.index', [
            'themes'    => $themes,
            'active'    => $active,
            'pageTitle' => '主题管理',
        ]);
    }

    public function activate(array $params): Response
    {
        can_or_403('switch_themes');
        $name = $params['name'] ?? '';
        $theme = app(ThemeManager::class);
        try {
            $theme->activate($name);
            Option::set('active_theme', $name);
            app(Session::class)->flash('success', "主题 [$name] 已激活");
        } catch (\Throwable $e) {
            app(Session::class)->flash('error', $e->getMessage());
        }
        return redirect(route('admin.themes.index'));
    }

    public function upload(): Response
    {
        can_or_403('switch_themes');
        $request = app(Request::class);
        $sess = app(Session::class);
        $file = $request->file('theme_zip');
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $sess->flash('error', '上传失败');
            return redirect(route('admin.themes.index'));
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'zip') {
            $sess->flash('error', '请上传 zip 格式的主题包');
            return redirect(route('admin.themes.index'));
        }
        $theme = app(ThemeManager::class);
        try {
            $info = $theme->uploadZip($file['tmp_name'], $file['name']);
            $sess->flash('success', "主题 [{$info['name']}] 已上传");
        } catch (\Throwable $e) {
            $sess->flash('error', '主题上传失败: ' . $e->getMessage());
        }
        return redirect(route('admin.themes.index'));
    }

    public function delete(array $params): Response
    {
        can_or_403('switch_themes');
        $name = $params['name'] ?? '';
        $theme = app(ThemeManager::class);
        if ($theme->deleteTheme($name)) {
            app(Session::class)->flash('success', "主题 [$name] 已删除");
        } else {
            app(Session::class)->flash('error', '无法删除当前激活的主题或主题不存在');
        }
        return redirect(route('admin.themes.index'));
    }
}
