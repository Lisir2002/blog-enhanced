<?php
/**
 * WordPress 风格钩子辅助函数 — Action & Filter。
 */

if (!function_exists('add_action')) {
    function add_action(string $name, callable $callback, int $priority = 10): void
    {
        app(\Core\Hook\Action::class)->add($name, $callback, $priority);
    }
}

if (!function_exists('do_action')) {
    function do_action(string $name, ...$args): void
    {
        app(\Core\Hook\Action::class)->run($name, ...$args);
    }
}

if (!function_exists('has_action')) {
    function has_action(string $name): bool
    {
        return app(\Core\Hook\Action::class)->has($name);
    }
}

if (!function_exists('add_filter')) {
    function add_filter(string $name, callable $callback, int $priority = 10): void
    {
        app(\Core\Hook\Filter::class)->add($name, $callback, $priority);
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters(string $name, mixed $value, ...$args): mixed
    {
        return app(\Core\Hook\Filter::class)->apply($name, $value, ...$args);
    }
}
