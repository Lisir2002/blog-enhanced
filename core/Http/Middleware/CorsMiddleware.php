<?php

namespace Core\Http\Middleware;

use Core\Http\Response;

/**
 * CORS 中间件 - 跨域资源共享。
 *
 * 用法：
 *   $router->middleware('cors', CorsMiddleware::class);
 *   $router->group(['prefix' => '/api', 'middleware' => 'cors'], function ($router) {
 *       // API 路由支持跨域
 *   });
 *
 * 配置（config/app.php）：
 *   'cors' => [
 *       'allowed_origins'      => ['https://example.com', 'https://app.example.com'],
 *       'allowed_methods'      => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
 *       'allowed_headers'      => ['Content-Type', 'Authorization', 'X-Requested-With'],
 *       'exposed_headers'      => ['X-RateLimit-Limit', 'X-RateLimit-Remaining'],
 *       'max_age'              => 3600,
 *       'supports_credentials' => true,
 *   ]
 */
class CorsMiddleware implements MiddlewareInterface
{
    public function handle(array $params, array $args = []): ?Response
    {
        $config = config('app.cors', []);
        $allowedOrigins = $config['allowed_origins'] ?? ['*'];
        $allowedMethods = $config['allowed_methods'] ?? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];
        $allowedHeaders = $config['allowed_headers'] ?? ['Content-Type', 'Authorization'];
        $exposedHeaders = $config['exposed_headers'] ?? [];
        $maxAge = $config['max_age'] ?? 3600;
        $supportsCredentials = $config['supports_credentials'] ?? false;

        $request = app(\Core\Http\Request::class);
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        // 处理 OPTIONS 预检请求
        if ($request->method() === 'OPTIONS') {
            return $this->handlePreflight(
                $origin, $allowedOrigins, $allowedMethods,
                $allowedHeaders, $maxAge, $supportsCredentials
            );
        }

        // 非预检请求：设置 CORS 响应头
        if (!headers_sent() && $origin) {
            $this->setCorsHeaders(
                $origin, $allowedOrigins, $exposedHeaders, $supportsCredentials
            );
        }

        return null;
    }

    /**
     * 处理 OPTIONS 预检请求。
     */
    private function handlePreflight(
        string $origin, array $allowedOrigins, array $allowedMethods,
        array $allowedHeaders, int $maxAge, bool $supportsCredentials
    ): Response {
        $resp = new Response();
        $resp->setStatus(204);

        if ($this->isOriginAllowed($origin, $allowedOrigins)) {
            $resp->header('Access-Control-Allow-Origin', $supportsCredentials ? $origin : '*');
            if ($supportsCredentials) {
                $resp->header('Access-Control-Allow-Credentials', 'true');
            }
            $resp->header('Access-Control-Allow-Methods', implode(', ', $allowedMethods));
            $resp->header('Access-Control-Allow-Headers', implode(', ', $allowedHeaders));
            $resp->header('Access-Control-Max-Age', (string) $maxAge);
        }

        return $resp;
    }

    /**
     * 设置 CORS 响应头（非预检请求）。
     */
    private function setCorsHeaders(
        string $origin, array $allowedOrigins,
        array $exposedHeaders, bool $supportsCredentials
    ): void {
        if ($this->isOriginAllowed($origin, $allowedOrigins)) {
            header('Access-Control-Allow-Origin: ' . ($supportsCredentials ? $origin : '*'));
            if ($supportsCredentials) {
                header('Access-Control-Allow-Credentials: true');
            }
            if (!empty($exposedHeaders)) {
                header('Access-Control-Expose-Headers: ' . implode(', ', $exposedHeaders));
            }
        }
    }

    /**
     * 判断 Origin 是否被允许。
     */
    private function isOriginAllowed(string $origin, array $allowedOrigins): bool
    {
        if (in_array('*', $allowedOrigins, true)) {
            return true;
        }
        return in_array($origin, $allowedOrigins, true);
    }
}
