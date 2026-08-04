<?php

namespace Core\Http\Middleware;

use Core\Http\Response;

/**
 * 中间件接口 - 增强版，支持参数化。
 *
 * 约定：
 * - 返回 null 表示继续链
 * - 返回 Response 表示短路
 * - $args 参数支持中间件参数化（如 role:admin 传入 ['admin']）
 *
 * 用法：
 *   // 无参数中间件
 *   $router->middleware('auth', AuthMiddleware::class);
 *
 *   // 参数化中间件
 *   $router->middleware('role', RoleMiddleware::class);
 *   $router->get('/admin', [Ctrl::class, 'index'])->middleware(['role:admin,editor']);
 *   // RoleMiddleware::handle($params, ['admin', 'editor'])
 */
interface MiddlewareInterface
{
    /**
     * @param array $params 路由参数（如 {id} => 123）
     * @param array $args 中间件参数（如 role:admin 中的 ['admin']）
     */
    public function handle(array $params, array $args = []): ?Response;
}
