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
    private static Application $instance;

    public function __construct()
    {
        // Register self as singleton instance
        self::$instance = $this;
        $this->instance(Application::class, $this);

        // Load .env & config first
        $this->instance(Config::class, new Config());
    }

    public static function getInstance(): static
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
        $this->singleton(\Core\Database\QueryBuilder::class);
        $this->singleton(\Parsedown::class, function() {
            $pd = new \Parsedown();
            $pd->setSafeMode(false);
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

        // Register built-in middleware
        $router->middleware('auth', function ($params) {
            $auth = $this->get(\Core\Auth\AuthManager::class);
            if (!$auth->check()) {
                $sess = $this->get(Session::class);
                $sess->set('_url_redirect', $_SERVER['REQUEST_URI'] ?? '/');
                return (new Response())->redirect(url('login'));
            }
        });

        $router->middleware('admin', function ($params) {
            $auth = $this->get(\Core\Auth\AuthManager::class);
            if (!$auth->check() || !in_array($auth->user()->getAttribute('role'), ['admin','editor','author','contributor'])) {
                if ($auth->guest()) {
                    return (new Response())->redirect(url('login'));
                }
                return (new Response())->setBody('Forbidden. Admin role required.')->setStatus(403);
            }
        });

        $router->middleware('csrf', function ($params) {
            $sess = $this->get(Session::class);
            $token = $_POST['_token'] ?? $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
            if (!$sess->verifyCsrf($token)) {
                return (new Response())->setBody('CSRF token mismatch.')->setStatus(419);
            }
        });

        $router->middleware('guest', function ($params) {
            $auth = $this->get(\Core\Auth\AuthManager::class);
            if ($auth->check()) {
                return (new Response())->redirect(url('admin'));
            }
        });

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
            $response = $this->handleException($e);
        }
        // Persist flash data
        $sess = $this->get(Session::class);
        if ($sess->has('_old_input')) {
            // Clear old input from previous flash; only valid for the next request
            // We'll clear when sending response back.
            // Note: real flash should be cleared after one read; we keep simple here.
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
                "<h1>Server Error (500)</h1><p>%s (%d)</p><pre>%s</pre>",
                e($e->getMessage()),
                $e->getCode(),
                e($e->getTraceAsString())
            ));
            return $resp;
        }
        // Try theme error page
        try {
            $theme = $this->get(\Core\View\ThemeManager::class);
            if ($theme->templateExists('error')) {
                return $theme->render('error', ['exception' => $e])->setStatus(500);
            }
        } catch (\Throwable $ee) {
            // fall through
        }
        return (new Response())->setBody('<h1>Server Error</h1>')->setStatus(500)
            ->setContentType('text/html');
    }
}
