<?php

namespace Core\Http\Middleware;

use Core\Auth\AuthManager;
use Core\Http\Response;

/**
 * 要求用户拥有后台管理权限（admin/editor/author/contributor）。
 * 未登录 → 跳转登录；已登录但权限不足 → 403。
 */
class AdminMiddleware implements MiddlewareInterface
{
    private const ADMIN_ROLES = ['admin', 'editor', 'author', 'contributor'];

    public function __construct(
        private AuthManager $auth,
    ) {}

    public function handle(array $params): ?Response
    {
        if (!$this->auth->check() || !in_array($this->auth->user()->getAttribute('role'), self::ADMIN_ROLES, true)) {
            if ($this->auth->guest()) {
                return (new Response())->redirect(url('login'));
            }
            return (new Response())
                ->setBody('Forbidden. Admin role required.')
                ->setStatus(403);
        }
        return null;
    }
}
