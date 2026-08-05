<?php
/**
 * WordPress 风格钩子辅助函数 — Action & Filter。
 */

// 当前正在加载的插件名（由 PluginManager::loadPlugin() 设置）
global $__current_plugin_loading;
$__current_plugin_loading = null;

// 当前正在加载的插件名栈（支持嵌套加载场景）
global $__plugin_loading_stack;
$__plugin_loading_stack = [];

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

if (!function_exists('remove_action')) {
    function remove_action(string $name, callable $callback): void
    {
        app(\Core\Hook\Action::class)->remove($name, $callback);
    }
}

if (!function_exists('remove_filter')) {
    function remove_filter(string $name, callable $callback): void
    {
        app(\Core\Hook\Filter::class)->remove($name, $callback);
    }
}

if (!function_exists('register_activation_hook')) {
    /**
     * 注册插件激活时的回调。
     * 在插件主文件中调用，参数为回调函数。
     */
    function register_activation_hook(callable $callback): void
    {
        global $__current_plugin_loading;
        if ($__current_plugin_loading !== null) {
            app(\Core\Plugin\PluginManager::class)->registerActivationHook($__current_plugin_loading, $callback);
        }
    }
}

if (!function_exists('register_deactivation_hook')) {
    /**
     * 注册插件停用时的回调。
     * 在插件主文件中调用，参数为回调函数。
     */
    function register_deactivation_hook(callable $callback): void
    {
        global $__current_plugin_loading;
        if ($__current_plugin_loading !== null) {
            app(\Core\Plugin\PluginManager::class)->registerDeactivationHook($__current_plugin_loading, $callback);
        }
    }
}

if (!function_exists('register_uninstall_hook')) {
    /**
     * 注册插件卸载时的回调。
     * 在插件主文件中调用，参数为回调函数。
     */
    function register_uninstall_hook(callable $callback): void
    {
        global $__current_plugin_loading;
        if ($__current_plugin_loading !== null) {
            app(\Core\Plugin\PluginManager::class)->registerUninstallHook($__current_plugin_loading, $callback);
        }
    }
}
