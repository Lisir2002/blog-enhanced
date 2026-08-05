<?php

namespace App\Controllers\Admin;

use Core\View\ThemeManager;
use App\Models\Option;
use Core\Http\Response;
use Core\Http\Request;
use Core\Http\Session;
use Core\Http\JsonResponse;

class ThemeController
{
    public function index(): Response
    {
        can_or_403('switch_themes');
        $theme = app(ThemeManager::class);
        $themes = $theme->listThemes();
        $active = $theme->activeTheme();

        $search = trim($_GET['q'] ?? '');
        $statusFilter = trim($_GET['status'] ?? '');

        return view('admin.themes.index', [
            'themes'       => $themes,
            'active'       => $active,
            'search'       => $search,
            'statusFilter' => $statusFilter,
            'pageTitle'    => '主题管理',
        ]);
    }

    /**
     * 主题详情页。
     */
    public function detail(array $params): Response
    {
        can_or_403('switch_themes');
        $name = $params['name'] ?? '';
        $theme = app(ThemeManager::class);

        $themes = $theme->listThemes();
        $plugin = $themes[$name] ?? null;
        if ($plugin === null) {
            app(Session::class)->flash('error', "主题 [$name] 不存在");
            return redirect(route('admin.themes.index'));
        }

        $active = $theme->activeTheme();
        $screenshots = $theme->getScreenshots();
        $changelog = $theme->getChangelog();
        $pageTemplates = $theme->getPageTemplates();
        $menuLocations = $theme->getMenuLocations();
        $sidebars = $theme->getSidebars();
        $recommendedPlugins = $theme->getRecommendedPlugins();
        $category = $theme->getCategory();
        $tags = $theme->getTags();
        $requires = $theme->getRequires();
        $requiresPhp = $theme->getRequiresPhp();
        $demoUrl = $theme->getDemoUrl();
        $parentTheme = $theme->parentTheme();

        return view('admin.themes.detail', [
            'theme'              => $plugin,
            'active'             => $active,
            'screenshots'        => $screenshots,
            'changelog'          => $changelog,
            'pageTemplates'      => $pageTemplates,
            'menuLocations'      => $menuLocations,
            'sidebars'           => $sidebars,
            'recommendedPlugins' => $recommendedPlugins,
            'category'           => $category,
            'tags'               => $tags,
            'requires'           => $requires,
            'requiresPhp'        => $requiresPhp,
            'demoUrl'            => $demoUrl,
            'parentTheme'        => $parentTheme,
            'isActive'           => ($name === $active),
            'pageTitle'          => '主题详情 - ' . ($plugin['meta']['name'] ?? $name),
        ]);
    }

    /**
     * 主题定制器 — 配置选项页面。
     */
    public function customize(array $params): Response
    {
        can_or_403('switch_themes');
        $name = $params['name'] ?? '';
        $theme = app(ThemeManager::class);

        $themes = $theme->listThemes();
        $plugin = $themes[$name] ?? null;
        if ($plugin === null) {
            app(Session::class)->flash('error', "主题 [$name] 不存在");
            return redirect(route('admin.themes.index'));
        }

        $groupedOptions = $theme->getGroupedOptions();
        $configValues = $theme->getAllConfig();
        $snapshots = $theme->getSnapshots(10);

        return view('admin.themes.customize', [
            'theme'          => $plugin,
            'groupedOptions' => $groupedOptions,
            'configValues'   => $configValues,
            'snapshots'      => $snapshots,
            'pageTitle'      => '主题定制 - ' . ($plugin['meta']['name'] ?? $name),
        ]);
    }

    public function activate(array $params): Response
    {
        can_or_403('switch_themes');
        $name = $params['name'] ?? '';
        $theme = app(ThemeManager::class);
        try {
            $theme->activate($name);
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
            $info = $theme->installFromZip($file['tmp_name']);
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

    /**
     * 保存主题定制配置（自动创建快照）。
     */
    public function saveConfig(array $params): Response
    {
        can_or_403('switch_themes');
        $name = $params['name'] ?? '';
        $request = app(Request::class);
        $sess = app(Session::class);
        $theme = app(ThemeManager::class);

        $options = $request->input('options', []);

        try {
            // 保存前创建快照（自动备份）
            try {
                $theme->createSnapshot('保存配置前自动备份');
            } catch (\Throwable) {
                // 快照非关键，继续执行
            }

            foreach ($options as $key => $value) {
                $theme->setConfig($key, $value);
            }
            $sess->flash('success', "主题配置已保存");
        } catch (\Throwable $e) {
            $sess->flash('error', '保存配置失败: ' . $e->getMessage());
        }

        return redirect(route('admin.themes.customize', ['name' => $name]));
    }

    /**
     * 预览主题（临时切换，不写入数据库）。
     */
    public function preview(array $params): Response
    {
        can_or_403('switch_themes');
        $name = $params['name'] ?? '';
        $theme = app(ThemeManager::class);

        if (!$theme->exists($name)) {
            app(Session::class)->flash('error', "主题 [$name] 不存在");
            return redirect(route('admin.themes.index'));
        }

        // 将预览主题存入 session
        app(Session::class)->set('theme_preview', $name);
        app(Session::class)->flash('info', "正在预览主题 [$name]");

        return redirect(url('/'));
    }

    /**
     * 查看配置历史快照列表。
     */
    public function revisions(array $params): Response
    {
        can_or_403('switch_themes');
        $name = $params['name'] ?? '';
        $theme = app(ThemeManager::class);

        $themes = $theme->listThemes();
        $plugin = $themes[$name] ?? null;
        if ($plugin === null) {
            app(Session::class)->flash('error', "主题 [$name] 不存在");
            return redirect(route('admin.themes.index'));
        }

        $snapshots = $theme->getSnapshots(100);

        return view('admin.themes.revisions', [
            'theme'     => $plugin,
            'snapshots' => $snapshots,
            'pageTitle' => '配置历史 - ' . ($plugin['meta']['name'] ?? $name),
        ]);
    }

    /**
     * 手动创建配置快照。
     */
    public function createRevision(array $params): Response
    {
        can_or_403('switch_themes');
        $name = $params['name'] ?? '';
        $request = app(Request::class);
        $sess = app(Session::class);
        $theme = app(ThemeManager::class);

        $note = trim($request->input('note', ''));
        try {
            $id = $theme->createSnapshot($note ?: '手动保存');
            $sess->flash('success', "配置快照 #{$id} 已创建");
        } catch (\Throwable $e) {
            $sess->flash('error', '创建快照失败: ' . $e->getMessage());
        }

        return redirect(route('admin.themes.revisions', ['name' => $name]));
    }

    /**
     * 回滚到指定配置快照。
     */
    public function restoreRevision(array $params): Response
    {
        can_or_403('switch_themes');
        $name = $params['name'] ?? '';
        $id = (int) ($params['id'] ?? 0);
        $sess = app(Session::class);
        $theme = app(ThemeManager::class);

        try {
            $theme->restoreSnapshot($id);
            $sess->flash('success', "已回滚到快照 #{$id}");
        } catch (\Throwable $e) {
            $sess->flash('error', '回滚失败: ' . $e->getMessage());
        }

        return redirect(route('admin.themes.customize', ['name' => $name]));
    }

    /**
     * AJAX 预览 — 接受临时配置参数并返回渲染后的首页 HTML。
     * 用于实时预览定制器 iframe 加载。
     */
    public function previewAjax(array $params): Response
    {
        can_or_403('switch_themes');
        $name = $params['name'] ?? '';
        $theme = app(ThemeManager::class);

        if (!$theme->exists($name)) {
            return new \Core\Http\JsonResponse(['error' => '主题不存在'], 404);
        }

        // 从 GET 参数读取临时配置
        $tempConfig = $_GET['config'] ?? [];
        if (is_string($tempConfig)) {
            $tempConfig = json_decode($tempConfig, true) ?? [];
        }

        // 将临时配置存入 session，前端渲染时读取
        app(Session::class)->set('theme_preview_config_' . $name, $tempConfig);
        app(Session::class)->set('theme_preview', $name);

        // 重定向到首页以获取完整渲染
        return redirect(url('/'));
    }
}