<?php

namespace Core;

use Closure;
use ReflectionClass;
use ReflectionNamedType;

/**
 * IoC 容器 - 增强版。
 *
 * 增强能力：
 * - 上下文绑定（Contextual Binding）：同一接口在不同类中解析为不同实现
 * - 懒加载（Lazy Loading）：defer() 延迟解析，首次访问才实例化
 * - 容器事件（Container Events）：resolving / resolved 钩子
 * - 标签绑定（Tagged Bindings）：tag() + tagged() 批量解析一组服务
 * - 扩展（Extenders）：extend() 在解析后修改实例
 */
class Container
{
    /** @var array<string, mixed> */
    private array $bindings = [];

    /** @var array<string, bool> 标记哪些是 singleton */
    private array $singletons = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /** @var array<string, string> */
    private array $aliases = [];

    /** @var array<string, array<string, string>> 上下文绑定：[concrete => [abstract => implementation]] */
    private array $contextualBindings = [];

    /** @var array<string, array<int, string>> 标签绑定：[tag => [abstract1, abstract2, ...]] */
    private array $tags = [];

    /** @var array<string, array<int, Closure>> 解析事件回调 */
    private array $resolvingCallbacks = [];

    /** @var array<string, array<int, Closure>> 解析后事件回调 */
    private array $resolvedCallbacks = [];

    /** @var array<string, array<int, Closure>> 全局解析回调（所有类型） */
    private array $globalResolving = [];

    /** @var array<string, array<int, Closure>> 扩展器：解析后修改实例 */
    private array $extenders = [];

    /** @var array<string, bool> 延迟加载标记 */
    private array $deferred = [];

    /** @var string|null 当前正在 build 的具体类（用于上下文绑定） */
    private ?string $currentBuild = null;

    /**
     * 注册单例绑定 - resolve 后会缓存实例。
     */
    public function singleton(string $abstract, Closure|string|null $concrete = null): void
    {
        $this->bindings[$abstract] = $concrete ?? $abstract;
        $this->singletons[$abstract] = true;
    }

    /**
     * 注册绑定（每次 resolve 都新建实例）。
     */
    public function bind(string $abstract, Closure|string $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
        $this->singletons[$abstract] = false;
    }

    /**
     * 延迟绑定 - 标记为 defer，首次 get 时才真正注册。
     */
    public function defer(string $abstract, Closure|string $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
        $this->singletons[$abstract] = true;
        $this->deferred[$abstract] = true;
    }

    /**
     * 上下文绑定 - 当 $concrete 类需要 $abstract 时，解析为 $implementation。
     *
     * 例：$container->addContextualBinding(PaymentController::class, LoggerInterface::class, FileLogger::class)
     */
    public function addContextualBinding(string $concrete, string $abstract, string $implementation): void
    {
        $this->contextualBindings[$concrete][$abstract] = $implementation;
    }

    /**
     * 为服务打标签 - 可通过 tagged() 批量解析。
     *
     * 例：$container->tag([RedisLogger::class, FileLogger::class], 'loggers')
     *     $loggers = $container->tagged('loggers');
     */
    public function tag(array $abstracts, string $tag): void
    {
        foreach ($abstracts as $abstract) {
            $this->tags[$tag][] = $abstract;
        }
    }

    /**
     * 解析带某标签的全部服务。
     *
     * @return array<int, mixed>
     */
    public function tagged(string $tag): array
    {
        $result = [];
        foreach ($this->tags[$tag] ?? [] as $abstract) {
            $result[] = $this->get($abstract);
        }
        return $result;
    }

    /**
     * 注册 resolving 回调 - 在实例化前触发。
     */
    public function resolving(string $abstract, Closure $callback): void
    {
        $this->resolvingCallbacks[$abstract][] = $callback;
    }

    /**
     * 注册 resolved 回调 - 在实例化后触发。
     */
    public function resolved(string $abstract, Closure $callback): void
    {
        $this->resolvedCallbacks[$abstract][] = $callback;
    }

    /**
     * 注册全局 resolving 回调 - 所有类型解析时触发。
     */
    public function resolvingAny(Closure $callback): void
    {
        $this->globalResolving[] = $callback;
    }

    /**
     * 注册扩展器 - 解析后修改实例。
     *
     * 例：$container->extend(CacheInterface::class, function ($cache, $app) {
     *         return new CacheDecorator($cache);
     *     });
     */
    public function extend(string $abstract, Closure $callback): void
    {
        $this->extenders[$abstract][] = $callback;
    }

    public function alias(string $abstract, string $alias): void
    {
        $this->aliases[$alias] = $abstract;
    }

    public function bound(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]) || class_exists($abstract);
    }

    /**
     * 解析依赖。
     */
    public function get(string $abstract): mixed
    {
        if (isset($this->aliases[$abstract])) {
            $abstract = $this->aliases[$abstract];
        }
        // 已缓存为单例
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // 上下文绑定：如果当前正在 build 某个类，且该类对 $abstract 有上下文绑定
        if ($this->currentBuild !== null && isset($this->contextualBindings[$this->currentBuild][$abstract])) {
            $abstract = $this->contextualBindings[$this->currentBuild][$abstract];
            if (isset($this->aliases[$abstract])) {
                $abstract = $this->aliases[$abstract];
            }
        }

        $concrete = $this->bindings[$abstract] ?? $abstract;
        $isSingleton = $this->singletons[$abstract] ?? false;

        // 触发 resolving 回调（实例化前）
        $this->fireResolvingCallbacks($abstract);

        if ($concrete instanceof Closure) {
            $object = $concrete($this);
        } elseif (is_string($concrete) && class_exists($concrete)) {
            $object = $this->build($concrete);
        } else {
            throw new \RuntimeException("Cannot resolve class [$abstract].");
        }

        // 应用扩展器
        if (isset($this->extenders[$abstract])) {
            foreach ($this->extenders[$abstract] as $extender) {
                $object = $extender($object, $this);
            }
        }

        // 单例：缓存实例
        if ($isSingleton) {
            $this->instances[$abstract] = $object;
        }

        // 触发 resolved 回调（实例化后）
        $this->fireResolvedCallbacks($abstract, $object);

        return $object;
    }

    /**
     * 自动装配对象。
     */
    public function build(string $concrete): object
    {
        $ref = new ReflectionClass($concrete);
        if (! $ref->isInstantiable()) {
            throw new \RuntimeException("[$concrete] is not instantiable.");
        }
        $ctor = $ref->getConstructor();
        if ($ctor === null) {
            return new $concrete();
        }

        // 设置当前 build 上下文，供 get() 检查上下文绑定
        $previousBuild = $this->currentBuild;
        $this->currentBuild = $concrete;

        $args = [];
        foreach ($ctor->getParameters() as $param) {
            $type = $param->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $args[] = $this->get($type->getName());
                continue;
            }
            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }
            // 恢复上下文
            $this->currentBuild = $previousBuild;
            throw new \RuntimeException("Cannot resolve parameter [\${$param->name}] of [$concrete].");
        }

        // 恢复上下文
        $this->currentBuild = $previousBuild;
        return $ref->newInstanceArgs($args);
    }

    public function instance(string $abstract, mixed $object): void
    {
        $this->instances[$abstract] = $object;
        $this->singletons[$abstract] = true;
    }

    /**
     * 触发 resolving 回调。
     */
    private function fireResolvingCallbacks(string $abstract): void
    {
        // 全局回调
        foreach ($this->globalResolving as $callback) {
            $callback($this);
        }
        // 特定类型回调
        foreach ($this->resolvingCallbacks[$abstract] ?? [] as $callback) {
            $callback($this);
        }
    }

    /**
     * 触发 resolved 回调。
     */
    private function fireResolvedCallbacks(string $abstract, mixed $object): void
    {
        foreach ($this->resolvedCallbacks[$abstract] ?? [] as $callback) {
            $callback($object, $this);
        }
    }

    /**
     * 测试辅助方法：重置 Application 单例。
     */
    public static function resetForTesting(): void
    {
        if (class_exists(Application::class, false)) {
            $ref = new ReflectionClass(Application::class);
            if ($ref->hasProperty('instance')) {
                $prop = $ref->getProperty('instance');
                $prop->setAccessible(true);
                $prop->setValue(null, null);
            }
        }
    }

    /**
     * 获取所有绑定（用于调试）。
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * 获取所有标签（用于调试）。
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * 清空所有绑定和实例（用于测试）。
     */
    public function flush(): void
    {
        $this->bindings = [];
        $this->singletons = [];
        $this->instances = [];
        $this->aliases = [];
        $this->contextualBindings = [];
        $this->tags = [];
        $this->resolvingCallbacks = [];
        $this->resolvedCallbacks = [];
        $this->globalResolving = [];
        $this->extenders = [];
        $this->deferred = [];
    }
}
