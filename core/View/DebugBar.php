<?php

namespace Core\View;

/**
 * 主题调试器 — 开发模式下显示 Query 日志、Hook 执行、模板层级。
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

    public static function render(): string
    {
        if (!config('app.debug', false)) return '';
        if (is_admin_route()) return '';

        $totalQueryMs = array_sum(array_column(self::$queries, 'ms'));
        $totalHookMs = array_sum(array_column(self::$hooks, 'ms'));

        ob_start();
        ?>
<div id="debug-bar" style="position:fixed;bottom:0;left:0;right:0;z-index:99998;background:#1e293b;color:#e2e8f0;font:12px/1.5 Menlo,Monaco,monospace;max-height:300px;overflow:auto;border-top:2px solid #3b82f6">
  <div style="display:flex;gap:16px;padding:6px 12px;background:#0f172a;border-bottom:1px solid #334155">
    <strong>Debug Bar</strong>
    <span>Queries: <?= count(self::$queries) ?> (<?= $totalQueryMs ?>ms)</span>
    <span>Hooks: <?= count(self::$hooks) ?> (<?= $totalHookMs ?>ms)</span>
    <span>Templates: <?= count(self::$templates) ?></span>
    <button onclick="document.getElementById('debug-bar').style.display='none'" style="margin-left:auto;background:#334155;color:#fff;border:none;border-radius:3px;padding:2px 8px;cursor:pointer">×</button>
  </div>
  <?php if (!empty(self::$queries)): ?>
  <details style="border-bottom:1px solid #334155">
    <summary style="padding:4px 12px;cursor:pointer;color:#60a5fa">Query Log (<?= count(self::$queries) ?>)</summary>
    <table style="width:100%;border-collapse:collapse">
      <?php foreach (self::$queries as $i => $q): ?>
      <tr><td style="color:#94a3b8;padding:2px 12px;width:30px"><?= $i + 1 ?></td><td style="padding:2px 8px"><?= e($q['sql']) ?></td><td style="color:#cbd5e1;padding:2px 8px;text-align:right"><?= $q['ms'] ?>ms</td></tr>
      <?php endforeach ?>
    </table>
  </details>
  <?php endif ?>
  <?php if (!empty(self::$hooks)): ?>
  <details style="border-bottom:1px solid #334155">
    <summary style="padding:4px 12px;cursor:pointer;color:#f59e0b">Hook Execution (<?= count(self::$hooks) ?>)</summary>
    <?php foreach (self::$hooks as $h): ?>
    <div style="padding:2px 12px"><?= e($h['name']) ?> → <?= $h['callbacks'] ?> callbacks (<?= $h['ms'] ?>ms)</div>
    <?php endforeach ?>
  </details>
  <?php endif ?>
  <?php if (!empty(self::$templates)): ?>
  <details>
    <summary style="padding:4px 12px;cursor:pointer;color:#10b981">Template Hierarchy (<?= count(self::$templates) ?>)</summary>
    <?php foreach (self::$templates as $t): ?>
    <div style="padding:2px 12px"><?= e($t['hierarchy']) ?> → <strong style="color:#10b981"><?= e($t['resolved']) ?></strong></div>
    <?php endforeach ?>
  </details>
  <?php endif ?>
</div>
        <?php
        return (string) ob_get_clean();
    }
}
