<?php

namespace Core\Hook;

/**
 * WordPress 风格 Filter hook 系统 - 增强版。
 *
 * 增强能力：
 * - 性能追踪：记录每个回调的执行时间
 * - 链短路：回调返回 null 时停止后续回调
 * - 条件回调：add_filter_if() 满足条件才注册
 *
 * - add_filter('the_content', [MyPlugin, 'appendSignature'])
 * - echo apply_filters('the_content', $post->content);
 */
class Filter
{
    /** @var array<string, array<int, array{callback: callable, priority: int}>> */
    private array $hooks = [];

    /** @var array<string, array<int, array{callback: string, elapsed: float, priority: int}>> 性能追踪数据 */
    private array $performance = [];

    /** @var bool 是否启用性能追踪 */
    private bool $traceEnabled = false;

    /** @var bool 回调返回 null 时是否短路 */
    private bool $shortCircuitOnNull = false;

    public function add(string $name, callable $callback, int $priority = 10): void
    {
        $this->hooks[$name][$priority][] = $callback;
        ksort($this->hooks[$name]);
    }

    /**
     * 条件注册：仅当 $condition 为 true 时才注册回调。
     */
    public function addIf(bool $condition, string $name, callable $callback, int $priority = 10): void
    {
        if ($condition) {
            $this->add($name, $callback, $priority);
        }
    }

    public function has(string $name): bool
    {
        return !empty($this->hooks[$name]);
    }

    public function remove(string $name, callable $callback): void
    {
        if (empty($this->hooks[$name])) {
            return;
        }
        foreach ($this->hooks[$name] as $priority => &$callbacks) {
            $callbacks = array_filter(
                $callbacks,
                fn ($c) => $c !== $callback
            );
        }
    }

    /**
     * 应用所有过滤器。
     *
     * @param mixed $value 初始值
     * @return mixed 过滤后的值
     */
    public function apply(string $name, mixed $value, mixed ...$args): mixed
    {
        if (empty($this->hooks[$name])) {
            return $value;
        }
        foreach ($this->hooks[$name] as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                if ($this->traceEnabled) {
                    $start = microtime(true);
                    $value = call_user_func($callback, $value, ...$args);
                    $elapsed = (microtime(true) - $start) * 1000;
                    $this->performance[$name][] = [
                        'callback' => $this->callbackName($callback),
                        'elapsed'  => $elapsed,
                        'priority' => $priority,
                    ];
                } else {
                    $value = call_user_func($callback, $value, ...$args);
                }

                // 链短路：回调返回 null 且启用了短路模式
                if ($this->shortCircuitOnNull && $value === null) {
                    return null;
                }
            }
        }
        return $value;
    }

    /**
     * 启用/禁用性能追踪。
     */
    public function enableTrace(bool $enabled = true): void
    {
        $this->traceEnabled = $enabled;
    }

    /**
     * 启用/禁用 null 短路模式。
     */
    public function enableShortCircuit(bool $enabled = true): void
    {
        $this->shortCircuitOnNull = $enabled;
    }

    /**
     * 获取性能追踪数据。
     */
    public function getPerformance(): array
    {
        return $this->performance;
    }

    /**
     * 获取回调的可读名称。
     */
    private function callbackName(callable $callback): string
    {
        if (is_string($callback)) {
            return $callback;
        }
        if (is_array($callback)) {
            $obj = $callback[0];
            $method = $callback[1];
            if (is_string($obj)) {
                return $obj . '::' . $method;
            }
            return get_class($obj) . '::' . $method;
        }
        if ($callback instanceof \Closure) {
            $ref = new \ReflectionFunction($callback);
            return 'Closure@' . $ref->getStartLine();
        }
        return 'unknown';
    }
}
