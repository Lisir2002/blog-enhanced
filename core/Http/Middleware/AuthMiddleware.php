<?php

namespace Core\Http\Middleware;

use Core\Auth\AuthManager;
use Core\Http\Response;
use Core\Http\Session;

/**
 * 要求用户已登录——未登录跳转到 /login 并记录回跳地址。
 */
class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthManager $auth,
        private Session $session,
    ) {}

    public function handle(array $params, array $args = []): ?Response
    {
        if (!$this->auth->check()) {
            $this->session->set('_url_redirect', $_SERVER['REQUEST_URI'] ?? '/');
            return (new Response())->redirect(url('login'));
        }
        return null;
    }
}
