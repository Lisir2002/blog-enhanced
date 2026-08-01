<?php

namespace App\Middleware;

use Core\Http\Response;

/**
 * 登录要求中间件。
 */
class AuthMiddleware
{
    public function handle(callable $next): Response
    {
        $auth = app(\Core\Auth\AuthManager::class);
        if ($auth->guest()) {
            $request = app(\Core\Http\Request::class);
            $sess = app(\Core\Http\Session::class);
            $sess->set('intended_url', $request->path());
            return redirect(url('/login?next=' . urlencode($request->path())));
        }
        return $next();
    }
}
