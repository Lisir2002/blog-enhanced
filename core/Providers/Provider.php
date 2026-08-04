<?php

namespace Core\Providers;

use Core\Application;

/**
 * 服务提供者抽象基类。
 *
 * 生命周期：
 *   1. register() — 绑定服务到容器（不触发解析）
 *   2. boot()      — 启动逻辑（可安全解析服务）
 *
 * 子类按需实现 register()，boot() 可选。
 */
abstract class Provider
{
    public function __construct(
        protected Application $app,
    ) {}

    abstract public function register(): void;

    public function boot(): void
    {
        // 默认空实现，子类按需覆写
    }
}
