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
        foreach ($this->hooks[$name] as $callbacks) {
            foreach ($callbacks as $callback) {
                call_user_func_array($callback, $args);
            }
        }
    }

    public function didRun(string $name): bool
    {
        return isset($this->didRun[$name]);
    }
}
