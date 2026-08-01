<?php

namespace Core\Hook;

/**
 * WordPress 风格 Filter hook 系统。
 *
 * - add_filter('the_content', [MyPlugin, 'appendSignature'])
 * - echo apply_filters('the_content', $post->content);
 */
class Filter
{
    /** @var array<string, array<int, array{callback: callable, priority: int}>> */
    private array $hooks = [];

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

    public function apply(string $name, mixed $value, mixed ...$args): mixed
    {
        if (empty($this->hooks[$name])) {
            return $value;
        }
        foreach ($this->hooks[$name] as $callbacks) {
            foreach ($callbacks as $callback) {
                $value = call_user_func($callback, $value, ...$args);
            }
        }
        return $value;
    }
}
