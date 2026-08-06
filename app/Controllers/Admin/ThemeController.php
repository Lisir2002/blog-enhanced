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
     * 存储预览配置到 session（供 iframe 重载后读取）。
     * 使用 POST 请求，避免 URL 参数长度限制。
     */
    public function previewStoreConfig(array $params): Response
    {
        can_or_403('switch_themes');
        $name = $params['name'] ?? '';
        $theme = app(ThemeManager::class);

        if (!$theme->exists($name)) {
            return (new Response())->json(['error' => '主题不存在'], 404);
        }

        // 从请求体读取配置（支持 form-urlencoded 和 JSON 两种格式）
        $request = app(Request::class);
        $config = $request->input('config', []);
        if (is_string($config)) {
            $config = json_decode($config, true) ?? [];
        }

        // 兜底：尝试从 php://input 直接读取（应对 PHP 内置服务器解析失败等边界情况）
        if (empty($config)) {
            $raw = @file_get_contents('php://input');
            if ($raw) {
                $parsed = [];
                parse_str($raw, $parsed);
                if (isset($parsed['config'])) {
                    $config = is_string($parsed['config']) ? (json_decode($parsed['config'], true) ?? []) : $parsed['config'];
                } else {
                    $json = json_decode($raw, true);
                    if (is_array($json) && isset($json['config'])) {
                        $config = $json['config'];
                    }
                }
            }
        }

        // 存储到 session
        app(Session::class)->set('theme_preview', $name);
        app(Session::class)->set('theme_preview_config_' . $name, $config);

        return (new Response())->json(['success' => true]);
    }

    /**
     * AJAX 预览 — 直接渲染首页 HTML 供 iframe 加载。
     * 配置从 session 读取（由 previewStoreConfig 预先存储），无需 URL 参数。
     */
    public function previewAjax(array $params): Response
    {
        can_or_403('switch_themes');
        $name = $params['name'] ?? '';
        $theme = app(ThemeManager::class);

        if (!$theme->exists($name)) {
            return (new Response())->json(['error' => '主题不存在'], 404);
        }

        // 设置 session 预览主题（配置已在 previewStoreConfig 中存储，或从 session 中读取已有配置）
        app(Session::class)->set('theme_preview', $name);

        // 直接渲染首页（主题模板会通过 ThemeManager::outputCssVariables 读取 session 配置）
        $homeController = app(\App\Controllers\Web\HomeController::class);
        return $homeController->index();
    }
}