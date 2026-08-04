<?php

namespace Core\Http\Middleware;

use Core\Auth\AuthManager;
use Core\Http\Response;

/**
 * 要求用户未登录（用于登录/注册页面）——已登录则跳转后台。
 */
class GuestMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthManager $auth,
    ) {}

    public function handle(array $params): ?Response
    {
        if ($this->auth->check()) {
            return (new Response())->redirect(url('admin'));
        }
        return null;
    }
}
