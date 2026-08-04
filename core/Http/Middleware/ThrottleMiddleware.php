<?php

namespace Core\Http\Middleware;

use Core\Cache\CacheInterface;
use Core\Http\Response;

/**
 * 限流中间件 - 防止 API 被恶意高频调用。
 *
 * 用法：
 *   $router->post('/login', [AuthCtrl::class, 'login'])
 *       ->middleware(['throttle:5,1']);  // 每分钟 5 次
 *
 *   $router->group(['middleware' => 'throttle:60,1'], function ($router) {
 *       // API 路由每分钟 60 次
 *   });
 *
 * 参数格式：throttle:maxAttempts,decayMinutes
 *   - maxAttempts: 时间窗口内最大请求数
 *   - decayMinutes: 时间窗口长度（分钟），默认 1
 */
class ThrottleMiddleware implements MiddlewareInterface
{
    public function __construct(
        private CacheInterface $cache,
    ) {}

    public function handle(array $params, array $args = []): ?Response
    {
        $maxAttempts = isset($args[0]) ? (int) $args[0] : 60;
        $decayMinutes = isset($args[1]) ? (int) $args[1] : 1;

        $request = app(\Core\Http\Request::class);
        $key = $this->resolveKey($request);

        $current = $this->cache->increment('throttle:' . $key);
        if ($current === 1) {
            // 首次请求，设置 TTL
            $this->cache->set('throttle:' . $key, 1, $decayMinutes * 60);
        }

        if ($current > $maxAttempts) {
            $retryAfter = $decayMinutes * 60;
            return (new Response())
                ->setBody(json_encode([
                    'error' => 'Too Many Requests',
                    'message' => "Rate limit exceeded. Retry after {$retryAfter} seconds.",
                    'retry_after' => $retryAfter,
                ]))
                ->setStatus(429)
                ->setContentType('application/json')
                ->header('Retry-After', (string) $retryAfter)
                ->header('X-RateLimit-Limit', (string) $maxAttempts)
                ->header('X-RateLimit-Remaining', '0');
        }

        // 设置响应头（通过全局变量，Response::send 时输出）
        if (!headers_sent()) {
            header('X-RateLimit-Limit: ' . $maxAttempts);
            header('X-RateLimit-Remaining: ' . max(0, $maxAttempts - $current));
        }

        return null;
    }

    /**
     * 生成限流键：基于 IP（匿名）或用户 ID（已登录）。
     */
    private function resolveKey(\Core\Http\Request $request): string
    {
        $user = app(\Core\Auth\AuthManager::class)->user();
        if ($user) {
            return 'user:' . $user->getAttribute('id');
        }
        return 'ip:' . $request->ip();
    }
}
