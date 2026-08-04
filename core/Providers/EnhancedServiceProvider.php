<?php

namespace Core\Providers;

use Core\Cache\ArrayCache;
use Core\Cache\CacheInterface;
use Core\Cache\CacheLock;
use Core\Cache\CacheManager;
use Core\Events\EventDispatcher;
use Core\Queue\Queue;
use Core\Security\AuditLog;

/**
 * 增强服务提供者 - 注册优化新增的服务。
 *
 * 注册的服务：
 * - CacheManager：多驱动缓存管理
 * - EventDispatcher：事件调度器
 * - Queue：队列系统
 * - AuditLog：审计日志（静态类，无需注册）
 */
class EnhancedServiceProvider extends Provider
{
    public function register(): void
    {
        // 多驱动缓存管理器
        $this->app->singleton(CacheManager::class);

        // 默认 CacheInterface 绑定到默认驱动
        $this->app->singleton(CacheInterface::class, function () {
            return $this->app->get(CacheManager::class)->driver();
        });

        // 事件调度器
        $this->app->singleton(EventDispatcher::class);

        // 队列系统
        $this->app->singleton(Queue::class);
    }

    public function boot(): void
    {
        $router = $this->app->get(\Core\Router::class);

        // 注册新中间件
        $router->middleware('throttle', \Core\Http\Middleware\ThrottleMiddleware::class);
        $router->middleware('cors', \Core\Http\Middleware\CorsMiddleware::class);

        // 注册事件监听器
        $this->registerEventListeners();

        // 注册审计日志 Hook
        $this->registerAuditHooks();
    }

    /**
     * 注册事件监听器。
     */
    private function registerEventListeners(): void
    {
        $dispatcher = $this->app->get(EventDispatcher::class);

        // 文章发布事件 → 清缓存 + 发通知
        // 实际监听器类应在 app/Listeners/ 中定义
    }

    /**
     * 注册审计日志 Hook。
     */
    private function registerAuditHooks(): void
    {
        // 用户登录成功
        add_action('user_logged_in', function ($user) {
            AuditLog::record('user.login', '用户登录成功', [
                'user_id'  => $user->getAttribute('id'),
                'username' => $user->getAttribute('username'),
            ]);
        }, 10);

        // 用户登出
        add_action('user_logged_out', function ($user) {
            AuditLog::record('user.logout', '用户登出', [
                'user_id'  => $user->getAttribute('id'),
                'username' => $user->getAttribute('username'),
            ]);
        }, 10);

        // 文章发布
        add_action('post_saved', function ($id, $data, $isUpdate) {
            if (isset($data['status']) && $data['status'] === 'published') {
                AuditLog::record('post.publish', '文章发布', [
                    'post_id'   => $id,
                    'title'     => $data['title'] ?? '',
                    'is_update' => $isUpdate,
                ]);
            }
        }, 20);

        // 文章删除
        add_action('post_deleted', function ($id) {
            AuditLog::record('post.delete', '文章删除', ['post_id' => $id]);
        }, 20);

        // 主题切换
        add_action('after_switch_theme', function ($newTheme, $oldTheme) {
            AuditLog::record('theme.switch', '主题切换', [
                'new_theme' => $newTheme,
                'old_theme' => $oldTheme,
            ]);
        }, 20);

        // 插件激活/停用
        add_action('activated_plugin', function ($plugin) {
            AuditLog::record('plugin.activate', '插件激活', ['plugin' => $plugin]);
        }, 20);

        add_action('deactivated_plugin', function ($plugin) {
            AuditLog::record('plugin.deactivate', '插件停用', ['plugin' => $plugin]);
        }, 20);
    }
}
