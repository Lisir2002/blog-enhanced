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
        Providers\PluginProvider::class,
        Providers\RouteServiceProvider::class,
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
            $provider->boot();
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
        // 记录异常日志
        try {
            \Core\Log\Log::error($e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        } catch (\Throwable) {
            // 日志本身失败时不影响响应
        }

        // 开发环境：显示完整错误信息
        if (config('app.debug')) {
            $resp = (new Response())
                ->setContentType('text/html')
                ->setStatus(500);
            $resp->setBody(sprintf(
                '<h1>Server Error</h1><pre>%s</pre><pre>%s:%d</pre><pre>%s</pre>',
                e($e->getMessage()),
                e($e->getFile()),
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
