<?php
/**
 * 全局辅助函数
 */

use Core\Application;
use Core\Container;
use Core\Http\Request;
use Core\Http\Response;
use Core\Support\Config;

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
        return resource_path('themes') . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('plugins_path')) {
    function plugins_path(string $path = ''): string
    {
        return base_path('plugins') . ($path ? '/' . ltrim($path, '/') : '');
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

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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

if (!function_exists('old')) {
    /**
     * 取上次表单提交的字段值。
     */
    function old(string $key, mixed $default = null): mixed
    {
        return app(Request::class)->old($key, $default);
    }
}

if (!function_exists('is_admin')) {
    function is_admin_route(): bool
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        return str_starts_with(trim($path, '/'), 'admin');
    }
}

if (!function_exists('logged_in')) {
    function logged_in(): bool
    {
        return app(\Core\Auth\AuthManager::class)->check();
    }
}

if (!function_exists('current_user')) {
    function current_user(): ?\App\Models\User
    {
        return app(\Core\Auth\AuthManager::class)->user();
    }
}

if (!function_exists('can')) {
    function can(string $capability, $args = null): bool
    {
        return app(\Core\Auth\AuthManager::class)->can($capability, $args);
    }
}

/* ─────────────── WordPress-style hook helpers ─────────────── */

if (!function_exists('add_action')) {
    function add_action(string $name, callable $callback, int $priority = 10): void
    {
        app(\Core\Hook\Action::class)->add($name, $callback, $priority);
    }
}

if (!function_exists('do_action')) {
    function do_action(string $name, ...$args): void
    {
        app(\Core\Hook\Action::class)->run($name, ...$args);
    }
}

if (!function_exists('has_action')) {
    function has_action(string $name): bool
    {
        return app(\Core\Hook\Action::class)->has($name);
    }
}

if (!function_exists('add_filter')) {
    function add_filter(string $name, callable $callback, int $priority = 10): void
    {
        app(\Core\Hook\Filter::class)->add($name, $callback, $priority);
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters(string $name, mixed $value, ...$args): mixed
    {
        return app(\Core\Hook\Filter::class)->apply($name, $value, ...$args);
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

/* ============================================
 * 主题模板辅助函数 (WordPress-style template tags)
 * ============================================ */

if (!function_exists('get_header')) {
    function get_header(string $name = ''): void
    {
        $theme = app(\Core\View\ThemeManager::class);
        echo $theme->partial('header' . ($name ? '-' . $name : ''), []);
    }
}

if (!function_exists('get_footer')) {
    function get_footer(string $name = ''): void
    {
        $theme = app(\Core\View\ThemeManager::class);
        echo $theme->partial('footer' . ($name ? '-' . $name : ''), []);
    }
}

if (!function_exists('get_sidebar')) {
    function get_sidebar(string $name = ''): void
    {
        $theme = app(\Core\View\ThemeManager::class);
        echo $theme->partial('sidebar' . ($name ? '-' . $name : ''), []);
    }
}

if (!function_exists('get_template_part')) {
    function get_template_part(string $slug, string $name = ''): void
    {
        $theme = app(\Core\View\ThemeManager::class);
        echo $theme->partial($slug . ($name ? '-' . $name : ''), []);
    }
}

if (!function_exists('body_class')) {
    function body_class(): string
    {
        $request = app(\Core\Http\Request::class);
        $path = trim($request->path(), '/');
        $parts = array_filter(explode('/', $path));
        $classes = ['site'];
        if (empty($parts)) {
            $classes[] = 'home';
        } else {
            $classes[] = 'page-' . $parts[0];
            foreach ($parts as $p) {
                $classes[] = sanitize_html_class($p);
            }
        }
        return 'class="' . implode(' ', $classes) . '"';
    }
}

if (!function_exists('sanitize_html_class')) {
    function sanitize_html_class(string $class): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '-', $class);
    }
}

if (!function_exists('post_class')) {
    function post_class(): string
    {
        return 'class="post"';
    }
}

if (!function_exists('the_title')) {
    function the_title(): void
    {
        global $post;
        if ($post ?? null) {
            echo e($post->getAttribute('title'));
        }
    }
}

if (!function_exists('the_content')) {
    function the_content(): void
    {
        global $post;
        if ($post ?? null) {
            echo $post->html();
        }
    }
}

if (!function_exists('the_excerpt')) {
    function the_excerpt(int $length = 200): void
    {
        global $post;
        if ($post ?? null) {
            echo e($post->excerpt($length));
        }
    }
}

if (!function_exists('the_permalink')) {
    function the_permalink(): void
    {
        global $post;
        if ($post ?? null) {
            echo $post->url();
        }
    }
}

if (!function_exists('get_the_title')) {
    function get_the_title(): string
    {
        global $post;
        return $post ? (string) $post->getAttribute('title') : '';
    }
}

if (!function_exists('get_the_permalink')) {
    function get_the_permalink(): string
    {
        global $post;
        return $post ? $post->url() : '';
    }
}

if (!function_exists('have_posts')) {
    function have_posts(): bool
    {
        global $posts;
        return !empty($posts);
    }
}

if (!function_exists('wp_nav_menu')) {
    function wp_nav_menu(): void
    {
        // 菜单缓存 1 小时，新增分类时插件应清空 cache:nav_menu
        /** @var \Core\Cache\CacheInterface $cache */
        $cache = app(\Core\Cache\CacheInterface::class);
        $html = $cache->remember('nav_menu', function () {
            $cats = \App\Models\Category::all();
            $out = '<ul class="menu">';
            foreach ($cats as $c) {
                $cat = new \App\Models\Category($c);
                $out .= '<li><a href="' . $cat->url() . '">' . e($cat->getAttribute('name')) . '</a></li>';
            }
            $out .= '</ul>';
            return $out;
        }, 3600);
        echo $html;
    }
}

