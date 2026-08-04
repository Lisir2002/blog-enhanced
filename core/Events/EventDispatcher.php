<?php

namespace Core\Events;

/**
 * 事件调度器 - 解耦的发布/订阅模式。
 *
 * 与 Hook 系统的区别：
 * - Hook 是 WordPress 风格的全局函数，事件是面向对象的
 * - 事件有类型（类名），监听器是类，支持依赖注入
 * - 事件可被监听器消费（停止传播）
 *
 * 用法：
 *   // 定义事件
 *   class PostPublishedEvent
 *   {
 *       public function __construct(public readonly Post $post) {}
 *   }
 *
 *   // 定义监听器
 *   class SendNotificationListener
 *   {
 *       public function handle(PostPublishedEvent $event): void
 *       {
 *           // 发送通知
 *       }
 *   }
 *
 *   // 注册监听器（在 Provider 中）
 *   $dispatcher->listen(PostPublishedEvent::class, SendNotificationListener::class);
 *
 *   // 触发事件
 *   $dispatcher->dispatch(new PostPublishedEvent($post));
 */
class EventDispatcher
{
    /** @var array<class-string, array<int, callable>> */
    private array $listeners = [];

    /**
     * 注册监听器。
     *
     * @param string $eventClass 事件类名
     * @param callable|string $listener 监听器（闭包或类名::method）
     */
    public function listen(string $eventClass, callable|string $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    /**
     * 触发事件，依次调用所有监听器。
     *
     * @param object $event 事件实例
     * @return bool 是否有监听器停止了传播
     */
    public function dispatch(object $event): bool
    {
        $eventClass = get_class($event);
        $propagationStopped = false;

        foreach ($this->listeners[$eventClass] ?? [] as $listener) {
            $this->resolveAndCall($listener, $event);

            // 检查事件是否停止传播
            if (method_exists($event, 'isPropagationStopped') && $event->isPropagationStopped()) {
                $propagationStopped = true;
                break;
            }
        }

        return $propagationStopped;
    }

    /**
     * 获取某事件的所有监听器。
     */
    public function getListeners(string $eventClass): array
    {
        return $this->listeners[$eventClass] ?? [];
    }

    /**
     * 判断事件是否有监听器。
     */
    public function hasListeners(string $eventClass): bool
    {
        return !empty($this->listeners[$eventClass]);
    }

    /**
     * 解析并调用监听器。
     */
    private function resolveAndCall(callable|string $listener, object $event): void
    {
        if (is_string($listener)) {
            // 类名形式：通过容器解析，调用 handle 方法
            $instance = app($listener);
            if (method_exists($instance, 'handle')) {
                $instance->handle($event);
            }
        } else {
            // 闭包形式：直接调用
            $listener($event);
        }
    }
}
