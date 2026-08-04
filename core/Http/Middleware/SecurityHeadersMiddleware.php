<?php

namespace Core\Http\Middleware;

use Core\Http\Response;

/**
 * 安全头中间件 — 输出 CSP、HSTS、X-Frame-Options 等安全响应头。
 */
class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function handle(array $params, array $args = []): ?Response
    {
        // 通过 $_SERVER['security_headers'] 全局输出头部
        // 实际安全头在 Response::send() 时输出

        // X-Content-Type-Options
        if (!headers_sent()) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('X-XSS-Protection: 1; mode=block');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

            // HSTS (仅 HTTPS)
            if (($_SERVER['HTTPS'] ?? 'off') !== 'off') {
                header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
            }

            // CSP — 允许内联样式/脚本（主题需要）、外部 https
            $csp = config('app.csp', "default-src 'self'; "
                . "script-src 'self' 'unsafe-inline' https:; "
                . "style-src 'self' 'unsafe-inline' https:; "
                . "img-src 'self' data: https:; "
                . "font-src 'self' https:; "
                . "connect-src 'self' https:; "
                . "frame-src 'self' https:;");
            header('Content-Security-Policy: ' . $csp);
        }

        return null;
    }
}
