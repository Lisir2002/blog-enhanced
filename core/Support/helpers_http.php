<?php
/**
 * HTTP / URL / 表单辅助函数。
 */

use Core\Http\Request;
use Core\Http\Response;

if (!function_exists('url')) {
    /**
     * 生成绝对 URL。
     */
    function url(string $path = ''): string
    {
        $base = rtrim(config('app.url', ''), '/');
        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('route')) {
    /**
     * 用路由名 + 参数生成 URL。
     */
    function route(string $name, array $params = []): string
    {
        return app(\Core\Router::class)->route($name, $params);
    }
}

if (!function_exists('asset')) {
    /**
     * 生成静态资源 URL。
     */
    function asset(string $path = ''): string
    {
        return url('assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path = '', int $status = 302): Response
    {
        // If path is already absolute URL, use as-is; otherwise prepend base URL
        if (preg_match('#^https?://#', $path)) {
            return (new Response())->redirect($path, $status);
        }
        return (new Response())->redirect(url($path), $status);
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return app(\Core\Http\Session::class)->csrfToken();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . csrf_token() . '">';
    }
}

if (!function_exists('old')) {
    /**
     * 取上次表单提交的字段值。
     */
    function old(string $key, mixed $default = null): mixed
    {
        return app(Request::class)->old($key, $default);
    }
}

if (!function_exists('is_admin_route')) {
    function is_admin_route(): bool
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        return str_starts_with(trim($path, '/'), 'admin');
    }
}
