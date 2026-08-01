<?php

namespace App\Middleware;

use Core\Http\Response;

/**
 * 管理员角色中间件。
 */
class AdminMiddleware
{
    public function handle(callable $next): Response
    {
        $auth = app(\Core\Auth\AuthManager::class);
        if ($auth->guest() || !$auth->isAdmin()) {
            if ($auth->guest()) {
                return redirect(url('/login'));
            }
            return (new Response())
                ->setBody('Forbidden. Admin role required.')
                ->setStatus(403)
                ->setContentType('text/plain');
        }
        return $next();
    }
}
