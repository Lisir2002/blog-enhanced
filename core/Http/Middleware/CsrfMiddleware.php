<?php

namespace Core\Http\Middleware;

use Core\Http\Response;
use Core\Http\Session;

/**
 * CSRF 令牌校验——POST/PUT/DELETE 请求必须携带有效 token。
 */
class CsrfMiddleware implements MiddlewareInterface
{
    public function __construct(
        private Session $session,
    ) {}

    public function handle(array $params, array $args = []): ?Response
    {
        $token = $_POST['_token'] ?? $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!$this->session->verifyCsrf($token)) {
            return (new Response())
                ->setBody('CSRF token mismatch.')
                ->setStatus(419);
        }
        return null;
    }
}
