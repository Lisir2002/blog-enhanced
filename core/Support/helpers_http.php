<?php
/**
 * HTTP / URL / 表单辅助函数。
 */

use Core\Http\Request;
use Core\Http\Response;

if (!function_exists('url')) {
    /**
     * 生成 URL（相对路径）。
     * 在预览容器中绝对路径会导致链接跳出代理，因此统一使用相对路径。
     * 如需外部绝对 URL，请使用 config('app.url') 自行拼接。
     */
    function url(string $path = ''): string
    {
        return '/' . ltrim($path, '/');
    }
}

if (!function_exists('route')) {
    /**
     * 用路由名 + 参数生成 URL。
     * 开发模式下若路由不存在，输出清晰的诊断信息而非直接崩溃。
     */
    function route(string $name, array $params = []): string
    {
        try {
            return app(\Core\Router::class)->route($name, $params);
        } catch (\RuntimeException $e) {
            if (config('app.debug')) {
                // 开发模式：给出诊断信息，帮助快速定位
                $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
                $caller = $trace[1] ?? $trace[0] ?? [];
                $file = $caller['file'] ?? '?';
                $line = $caller['line'] ?? '?';

                // 记录到日志（不抛出，页面仍可渲染）
                error_log(sprintf(
                    '[Route Missing] %s — called from %s:%d. ' .
                    'Check routes/admin.php or routes/web.php for the missing route definition.',
                    $name, $file, $line
                ));

                // 返回占位 URL 而不是崩溃
                return '/__missing_route?name=' . urlencode($name);
            }
            throw $e;
        }
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
