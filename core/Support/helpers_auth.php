<?php
/**
 * 权限 / 登录态辅助函数。
 */

if (!function_exists('logged_in')) {
    function logged_in(): bool
    {
        return app(\Core\Auth\AuthManager::class)->check();
    }
}

if (!function_exists('current_user')) {
    function current_user(): ?\App\Models\User
    {
        return app(\Core\Auth\AuthManager::class)->user();
    }
}

if (!function_exists('can')) {
    function can(string $capability, $args = null): bool
    {
        return app(\Core\Auth\AuthManager::class)->can($capability, $args);
    }
}

if (!function_exists('can_or_403')) {
    function can_or_403(string $capability, $args = null): void
    {
        if (!can($capability, $args)) {
            $response = (new \Core\Http\Response())
                ->setBody('Forbidden. Required capability: ' . $capability)
                ->setStatus(403);
            $response->send();
            exit;
        }
    }
}
