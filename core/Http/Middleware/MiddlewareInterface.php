<?php

namespace Core\Http\Middleware;

use Core\Http\Response;

/**
 * 中间件接口 — 所有中间件类实现此接口。
 *
 * 约定：返回 null 表示继续链；返回 Response 表示短路。
 */
interface MiddlewareInterface
{
    public function handle(array $params): ?Response;
}
