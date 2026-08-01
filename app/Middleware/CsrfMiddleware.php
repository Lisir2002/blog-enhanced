<?php

namespace App\Middleware;

use Core\Http\Response;
use Core\Http\Session;

/**
 * CSRF 校验中间件 - 仅 POST/PUT/DELETE 触发。
 */
class CsrfMiddleware
{
    public function handle(callable $next): Response
    {
        $request = app(\Core\Http\Request::class);
        $method = $request->method();
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $sess = app(Session::class);
            $token = $request->input('_token') ?: $request->server('HTTP_X_CSRF_TOKEN');
            $sessionToken = $sess->csrfToken();
            
            // DEBUG
            $debugFile = storage_path('logs/csrf_debug.log');
            @file_put_contents($debugFile, sprintf(
                "[%s] method=%s sid=%s token_from_form=%s token_from_session=%s match=%s\n",
                date('Y-m-d H:i:s'),
                $method,
                $sess->id(),
                substr((string)$token, 0, 16),
                substr($sessionToken, 0, 16),
                ($token && hash_equals($sessionToken, (string)$token)) ? 'YES' : 'NO'
            ), FILE_APPEND);
            
            if (!$sess->verifyCsrf($token)) {
                return (new Response())
                    ->setBody('CSRF token mismatch.')
                    ->setStatus(419)
                    ->setContentType('text/plain');
            }
        }
        return $next();
    }
}
