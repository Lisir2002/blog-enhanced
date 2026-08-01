<?php

namespace Core;

use Closure;
use ReflectionClass;
use ReflectionNamedType;

/**
 * 最简 IoC 容器。
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
        $concrete = $this->bindings[$abstract] ?? $abstract;
        $isSingleton = $this->singletons[$abstract] ?? false;

        if ($concrete instanceof Closure) {
            $object = $concrete($this);
        } elseif (is_string($concrete) && class_exists($concrete)) {
            $object = $this->build($concrete);
        } else {
            throw new \RuntimeException("Cannot resolve class [$abstract].");
        }

        // 单例：缓存实例
        if ($isSingleton) {
            $this->instances[$abstract] = $object;
        }
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
            throw new \RuntimeException("Cannot resolve parameter [\${$param->name}] of [$concrete].");
        }
        return $ref->newInstanceArgs($args);
    }

    public function instance(string $abstract, mixed $object): void
    {
        $this->instances[$abstract] = $object;
        $this->singletons[$abstract] = true;
    }
}
