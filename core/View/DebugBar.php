<?php

namespace Core\View;

/**
 * 调试工具栏 — 数据收集器，渲染整合到顶栏头像气泡菜单中。
 *
 * 显示逻辑：
 *   - debug=true 且非后台页面 → 在头像菜单中显示调试数据
 *   - debug=false 或后台页面 → 不渲染
 *   - 不再独立输出浮动按钮/脚本
 */
class DebugBar
{
    private static array $queries = [];
    private static array $hooks = [];
    private static array $templates = [];

    public static function logQuery(string $sql, float $ms): void
    {
        if (!config('app.debug', false)) return;
        self::$queries[] = ['sql' => $sql, 'ms' => round($ms * 1000, 2)];
    }

    public static function logHook(string $name, int $callbacks, float $ms): void
    {
        if (!config('app.debug', false)) return;
        self::$hooks[] = ['name' => $name, 'callbacks' => $callbacks, 'ms' => round($ms * 1000, 2)];
    }

    public static function logTemplate(string $hierarchy, string $resolved): void
    {
        if (!config('app.debug', false)) return;
        self::$templates[] = ['hierarchy' => $hierarchy, 'resolved' => $resolved];
    }

    public static function reset(): void
    {
        self::$queries = [];
        self::$hooks = [];
        self::$templates = [];
    }

    /**
     * 不再独立渲染浮动按钮，由头像菜单调用 renderInline() 嵌入
     */
    public static function render(): string
    {
        return '';
    }

    /**
     * 返回调试数据的摘要（供头像菜单使用）
     */
    public static function summary(): array
    {
        if (!config('app.debug', false)) {
            return ['enabled' => false];
        }

        return [
            'enabled'      => true,
            'queries'      => self::$queries,
            'hooks'        => self::$hooks,
            'templates'    => self::$templates,
            'queryCount'   => count(self::$queries),
            'hookCount'    => count(self::$hooks),
            'templateCount'=> count(self::$templates),
            'queryMs'      => array_sum(array_column(self::$queries, 'ms')),
            'hookMs'       => array_sum(array_column(self::$hooks, 'ms')),
        ];
    }
}
