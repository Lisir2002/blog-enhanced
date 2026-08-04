<?php

namespace Core\Providers;

use Core\Router;

class RouteServiceProvider extends Provider
{
    public function register(): void
    {
        $this->app->singleton(Router::class);
    }

    public function boot(): void
    {
        $router = $this->app->get(Router::class);

        // Register built-in middleware (class-based → container resolves with DI)
        $router->middleware('auth', \Core\Http\Middleware\AuthMiddleware::class);
        $router->middleware('admin', \Core\Http\Middleware\AdminMiddleware::class);
        $router->middleware('csrf', \Core\Http\Middleware\CsrfMiddleware::class);
        $router->middleware('guest', \Core\Http\Middleware\GuestMiddleware::class);
        $router->middleware('security', \Core\Http\Middleware\SecurityHeadersMiddleware::class);
        $router->middleware('throttle', \Core\Http\Middleware\ThrottleMiddleware::class);
        $router->middleware('cors', \Core\Http\Middleware\CorsMiddleware::class);

        // 注册中间件组
        $router->middlewareGroup('web', ['csrf', 'security']);
        $router->middlewareGroup('api', ['throttle:60,1', 'cors']);

        // 路由模型绑定
        $router->model('post', \App\Models\Post::class, 'slug');
        $router->model('user', \App\Models\User::class, 'username');

        // 健康检查路由（无需中间件）
        $router->get('/health', [\App\Controllers\Web\HealthController::class, 'liveness'])->name('health');
        $router->get('/health/ready', [\App\Controllers\Web\HealthController::class, 'readiness'])->name('health.ready');

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

        // Run 'init' hook before dispatch
        do_action('init');
    }
}
