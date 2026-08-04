<?php

namespace Core\Hook;

/**
 * WordPress 风格 Action hook 系统。
 *
 * - add_action('wp_head', [MyPlugin, 'renderStyles'])
 * - do_action('wp_head')
 */
class Action
{
    /** @var array<string, array<int, array{callback: callable, priority: int}>> */
    private array $hooks = [];

    /** @var array<string, true> */
    private array $didRun = [];

    /** @var array<string, array<int, array{callback: callable, elapsed: float}>> 性能追踪数据 */
    private array $performance = [];

    /** @var bool 是否启用性能追踪 */
    private bool $traceEnabled = false;

    public function add(string $name, callable $callback, int $priority = 10): void
    {
        $this->hooks[$name][$priority][] = $callback;
        ksort($this->hooks[$name]);
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

    public function run(string $name, mixed ...$args): void
    {
        $this->didRun[$name] = true;
        if (empty($this->hooks[$name])) {
            return;
        }
        foreach ($this->hooks[$name] as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                if ($this->traceEnabled) {
                    $start = microtime(true);
                    call_user_func_array($callback, $args);
                    $elapsed = (microtime(true) - $start) * 1000;
                    $this->performance[$name][] = [
                        'callback' => $this->callbackName($callback),
                        'elapsed' => $elapsed,
                        'priority' => $priority,
                    ];
                } else {
                    call_user_func_array($callback, $args);
                }
            }
        }
    }

    public function didRun(string $name): bool
    {
        return isset($this->didRun[$name]);
    }

    /**
     * 启用/禁用性能追踪。
     */
    public function enableTrace(bool $enabled = true): void
    {
        $this->traceEnabled = $enabled;
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
