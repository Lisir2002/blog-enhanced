<?php

namespace App\Middleware;

use Core\Http\Response;

/**
 * 仅限访客访问中间件（登录页用）。
 */
class GuestMiddleware
{
    public function handle(callable $next): Response
    {
        $auth = app(\Core\Auth\AuthManager::class);
        if ($auth->check()) {
            return redirect(url('/admin'));
        }
        return $next();
    }
}
