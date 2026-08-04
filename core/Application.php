<?php

namespace Core;

use Core\Http\Request;
use Core\Http\Response;
use Core\Http\Session;
use Core\Support\Config;

/**
 * 应用主类，单例。
 */
class Application extends Container
{
    private static ?Application $instance = null;

    public function __construct()
    {
        // Register self as singleton instance
        self::$instance = $this;
        $this->instance(Application::class, $this);

        // Load .env & config first
        $this->instance(Config::class, new Config());
    }

    public static function getInstance(): ?static
    {
        return self::$instance;
    }

    public function bootstrap(): void
    {
        $this->registerCoreServices();
        $this->loadHelpers();
        $this->loadRoutes();
        $this->loadTheme();
        $this->loadPlugins();
    }

    private function registerCoreServices(): void
    {
        $this->singleton(Session::class);
        $this->singleton(Request::class, fn() => Request::capture());
        $this->singleton(Router::class);
        $this->singleton(\Core\View\ViewRenderer::class);
        $this->singleton(\Core\View\ThemeManager::class);
        $this->singleton(\Core\Database\Connection::class);
        $this->singleton(\Core\Hook\Action::class);
        $this->singleton(\Core\Hook\Filter::class);
        $this->singleton(\Core\Plugin\PluginManager::class);
        $this->singleton(\Core\Auth\AuthManager::class);
        $this->singleton(\Core\Cache\FileCache::class);
        $this->singleton(\Core\Cache\CacheInterface::class, fn () => $this->get(\Core\Cache\FileCache::class));
        $this->singleton(\Core\Database\QueryBuilder::class);
        $this->singleton(\App\Services\LoginRateLimiter::class);
        $this->singleton(\Parsedown::class, function () {
            $pd = new \Parsedown();
            // 开启安全模式：转义用户提交的 Markdown 中的原始 HTML 标签与危险 URL
            // （在 Parsedown 1.7 中 setSafeMode(true) 会过滤 <script>、on* 属性、javascript: URL）
            $pd->setSafeMode(true);
            return $pd;
        });

        // Run 'init' hook before dispatch
        do_action('init');
    }

    private function loadHelpers(): void
    {
        // Already loaded via composer autoload 'files'
        if (!function_exists('app')) {
            require core_path('Support/helpers.php');
        }
    }

    private function loadRoutes(): void
    {
        $router = $this->get(Router::class);

        // Register built-in middleware (class-based → container resolves with DI)
        $router->middleware('auth', \Core\Http\Middleware\AuthMiddleware::class);
        $router->middleware('admin', \Core\Http\Middleware\AdminMiddleware::class);
        $router->middleware('csrf', \Core\Http\Middleware\CsrfMiddleware::class);
        $router->middleware('guest', \Core\Http\Middleware\GuestMiddleware::class);

        // Load admin & api routes first, then web routes last (catch-all must be final)
        if (is_file(route_path('admin.php'))) {
            $router->loadRoutes(route_path('admin.php'));
        }
        if (is_file(route_path('api.php'))) {
            $router->loadRoutes(route_path('api.php'));
        }
        if (is_file(route_path('web.php'))) {
            $router->loadRoutes(route_path('web.php'));
        }
    }

    private function loadTheme(): void
    {
        $themeManager = $this->get(\Core\View\ThemeManager::class);
        $themeManager->boot();
    }

    private function loadPlugins(): void
    {
        $pm = $this->get(\Core\Plugin\PluginManager::class);
        $pm->boot();
    }

    public function run(): void
    {
        $request = $this->get(Request::class);
        $router = $this->get(Router::class);
        try {
            $response = $router->dispatch($request->method(), $request->path());
        } catch (\Throwable $e) {
            // 记录异常日志（含请求上下文，便于排查）
            \Core\Log\Log::error('Unhandled exception', [
                'msg'   => $e->getMessage(),
                'code'  => $e->getCode(),
                'file'  => $e->getFile() . ':' . $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'uri'   => $_SERVER['REQUEST_URI'] ?? '',
                'ip'    => $request->ip(),
            ]);
            $response = $this->handleException($e);
        }
        $response->send();
    }

    private function handleException(\Throwable $e): Response
    {
        $debug = (bool) config('app.debug', false);
        if ($debug) {
            $resp = new Response();
            $resp->setContentType('text/html');
            $resp->setStatus(500);
            $resp->setBody(sprintf(
                "<h1>Server Error (500)</h1><p>%s (%d)</p><p>%s:%d</p><pre>%s</pre>",
                e($e->getMessage()),
                $e->getCode(),
                e($e->getFile()),
                $e->getLine(),
                e($e->getTraceAsString())
            ));
            return $resp;
        }
        // 生产环境不泄露细节，只显示通用错误页
        try {
            $theme = $this->get(\Core\View\ThemeManager::class);
            if ($theme->templateExists('error')) {
                return $theme->render('error', ['exception' => $e])->setStatus(500);
            }
        } catch (\Throwable $ee) {
            // 主题渲染也失败 - 兜底
            \Core\Log\Log::critical('Theme error page rendering failed', [
                'msg' => $ee->getMessage(),
            ]);
        }
        return (new Response())->setBody('<h1>Server Error</h1><p>内部错误，请联系管理员。</p>')
            ->setStatus(500)
            ->setContentType('text/html');
    }
}
