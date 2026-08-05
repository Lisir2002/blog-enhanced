<?php

namespace Core;

use Core\Http\Request;
use Core\Http\Response;
use Core\Providers\Provider;
use Core\Support\Config;

/**
 * 应用主类，单例。
 */
class Application extends Container
{
    private static ?Application $instance = null;

    /** @var array<int, class-string<Provider>> */
    protected array $providers = [
        Providers\HttpProvider::class,
        Providers\DatabaseProvider::class,
        Providers\CacheProvider::class,
        Providers\AuthProvider::class,
        Providers\HookProvider::class,
        Providers\ParsedownProvider::class,
        Providers\ViewProvider::class,
        Providers\ThemeServiceProvider::class,
        Providers\AdvancedServiceProvider::class,
        Providers\PluginProvider::class,
        Providers\RouteServiceProvider::class,
        Providers\QueueProvider::class,
        Providers\EnhancedServiceProvider::class,
    ];

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

    /**
     * 注册额外 Provider（供插件使用）。
     *
     * @param class-string<Provider> $providerClass
     */
    public function registerProvider(string $providerClass): void
    {
        $this->providers[] = $providerClass;
    }

    public function bootstrap(): void
    {
        $this->loadHelpers();

        // Phase 1: register all services (binding only, no resolution)
        $instances = [];
        foreach ($this->providers as $providerClass) {
            $provider = new $providerClass($this);
            $provider->register();
            $instances[] = $provider;
        }

        // Phase 2: boot all services (can safely resolve & use services)
        foreach ($instances as $provider) {
            try {
                $provider->boot();
            } catch (\Throwable $e) {
                // Log to error_log as fallback (Log class may not be available yet)
                error_log('Provider boot failed: ' . get_class($provider) . ' — ' . $e->getMessage());
            }
        }
    }

    private function loadHelpers(): void
    {
        // Already loaded via composer autoload 'files'
        if (!function_exists('app')) {
            require core_path('Support/helpers.php');
        }
    }

    /**
     * 运行应用 — 捕获请求、分发路由、发送响应。
     */
    public function run(): void
    {
        try {
            $request = $this->get(Request::class);
            $router = $this->get(Router::class);
            $response = $router->dispatch($request->method, $request->path);
            $response->send();
        } catch (\Throwable $e) {
            $this->handleException($e)->send();
        }
    }

    private function handleException(\Throwable $e): Response
    {
        // 记录异常日志到文件（用于调试）
        @file_put_contents('/tmp/php_error.log', sprintf(
            "[%s] %s in %s:%d\n%s\n---\n",
            date('Y-m-d H:i:s'),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        ), FILE_APPEND);

        // 记录异常日志
        try {
            \Core\Log\Log::error($e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        } catch (\Throwable) {
            // 日志本身失败时不影响响应
        }

        // 检查请求是否期望 JSON 响应
        $expectsJson = false;
        try {
            $req = $this->get(Request::class);
            $expectsJson = $req->expectsJson();
        } catch (\Throwable) {
            // Request 还没准备好，用 Accept 头判断
            $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
            $expectsJson = str_contains($accept, 'application/json');
        }

        // 开发环境：显示完整错误信息
        if (config('app.debug')) {
            if ($expectsJson) {
                return (new Response())
                    ->json(['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()])
                    ->setStatus(500);
            }
            $resp = (new Response())
                ->setContentType('text/html')
                ->setStatus(500);
            $resp->setBody(sprintf(
                '<h1>Server Error</h1><pre>%s</pre><pre>%s:%d</pre><pre>%s</pre>',
                e($e->getMessage()),
                e($e->getFile()),
                $e->getLine(),
                e($e->getTraceAsString())
            ));
            return $resp;
        }
        // 生产环境不泄露细节，只显示通用错误页
        if ($expectsJson) {
            return (new Response())
                ->json(['error' => '内部服务器错误'])
                ->setStatus(500);
        }
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
