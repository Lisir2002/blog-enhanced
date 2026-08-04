<?php
/**
 * 核心辅助函数 — 容器、配置、路径、通用工具。
 *
 * 其他辅助函数按领域拆分：
 * @see helpers_http.php    URL / 重定向 / CSRF
 * @see helpers_auth.php    登录态 / 权限
 * @see helpers_hook.php    Action / Filter 钩子
 * @see helpers_theme.php   主题模板标签
 */

use Core\Application;
use Core\Http\Request;
use Core\Http\Response;
use Core\Support\Config;

/* ─────────────── 容器 & 配置 ─────────────── */

if (!function_exists('app')) {
    /**
     * 获取应用实例或绑定/解析依赖。
     *
     * @param  string|null  $abstract
     * @return Application|mixed
     */
    function app(?string $abstract = null): mixed
    {
        $instance = Application::getInstance();
        if ($abstract === null) {
            return $instance;
        }
        return $instance->get($abstract);
    }
}

if (!function_exists('config')) {
    /**
     * 获取配置项。
     *
     * @param  string|null  $key
     * @param  mixed  $default
     */
    function config(?string $key = null, $default = null)
    {
        $config = app(Config::class);
        if ($key === null) {
            return $config;
        }
        return $config->get($key, $default);
    }
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false || $value === null) {
            return $default;
        }
        $value = (string) $value;
        return match (strtolower($value)) {
            'true' => true,
            'false' => false,
            'null', '' => null,
            default => $value,
        };
    }
}

/* ─────────────── 路径辅助 ─────────────── */

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return rtrim(dirname(__DIR__, 2), '/') . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('app_path')) {
    function app_path(string $path = ''): string
    {
        return base_path('app') . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('core_path')) {
    function core_path(string $path = ''): string
    {
        return base_path('core') . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('public_path')) {
    function public_path(string $path = ''): string
    {
        return base_path('public') . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return base_path('storage') . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('resource_path')) {
    function resource_path(string $path = ''): string
    {
        return base_path('resources') . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('themes_path')) {
    function themes_path(string $path = ''): string
    {
        return public_path('themes') . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('plugins_path')) {
    function plugins_path(string $path = ''): string
    {
        return base_path('plugins') . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('plugin_path')) {
    /**
     * 取某插件文件所在目录。
     */
    function plugin_path(string $file): string
    {
        return dirname($file);
    }
}

if (!function_exists('database_path')) {
    function database_path(string $path = ''): string
    {
        return base_path('database') . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('config_path')) {
    function config_path(string $path = ''): string
    {
        return base_path('config') . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('route_path')) {
    function route_path(string $path = ''): string
    {
        return base_path('routes') . ($path ? '/' . ltrim($path, '/') : '');
    }
}

/* ─────────────── 通用工具 ─────────────── */

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('dd')) {
    function dd(mixed ...$vars): void
    {
        foreach ($vars as $v) {
            echo '<pre style="background:#f4f4f4;padding:12px;border:1px solid #ddd;border-radius:4px;font:13px/1.5 Menlo,Monaco,monospace;white-space:pre-wrap;">';
            echo htmlspecialchars(var_export($v, true));
            echo '</pre>';
        }
        exit(1);
    }
}

if (!function_exists('tap')) {
    function tap(mixed $value, callable $callback): mixed
    {
        $callback($value);
        return $value;
    }
}

if (!function_exists('view')) {
    /**
     * 渲染视图。
     *
     * @param  string  $template  模板名
     * @param  array  $data
     */
    function view(string $template, array $data = []): Response
    {
        return app(\Core\View\ViewRenderer::class)->render($template, $data);
    }
}

if (!function_exists('theme_view')) {
    /**
     * 用当前主题模板渲染。
     */
    function theme_view(string $template, array $data = []): Response
    {
        return app(\Core\View\ThemeManager::class)->render($template, $data);
    }
}
