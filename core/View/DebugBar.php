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

        $id = 'debug-bar-' . bin2hex(random_bytes(4));

        ob_start();
        ?>
<style>
#<?= $id ?>-btn {
  position:fixed;bottom:16px;right:16px;z-index:99999;
  width:44px;height:44px;border-radius:50%;
  background:#1e293b;color:#3b82f6;border:2px solid #3b82f6;
  font:bold 16px/1 Menlo,monospace;cursor:pointer;
  box-shadow:0 4px 12px rgba(0,0,0,0.3);
  display:flex;align-items:center;justify-content:center;
  transition:transform 0.15s,box-shadow 0.15s;
}
#<?= $id ?>-btn:hover { transform:scale(1.1);box-shadow:0 6px 20px rgba(0,0,0,0.4); }
#<?= $id ?>-panel {
  position:fixed;bottom:0;left:0;right:0;z-index:99998;
  background:#1e293b;color:#e2e8f0;
  font:12px/1.5 Menlo,Monaco,monospace;
  max-height:70vh;overflow:auto;
  border-top:2px solid #3b82f6;
  display:none;
  box-shadow:0 -8px 30px rgba(0,0,0,0.3);
}
#<?= $id ?>-panel.is-open { display:block; }
#<?= $id ?>-panel summary { padding:4px 12px;cursor:pointer;font-weight:600; }
#<?= $id ?>-panel table { width:100%;border-collapse:collapse; }
</style>
<button id="<?= $id ?>-btn" onclick="(function(){
  var p=document.getElementById('<?= $id ?>-panel'),b=document.getElementById('<?= $id ?>-btn');
  p.classList.toggle('is-open');
  b.textContent=p.classList.contains('is-open')?'✕':'⚙';
})()" title="Toggle Debug Bar">⚙</button>
<div id="<?= $id ?>-panel">
  <div style="display:flex;gap:16px;padding:6px 12px;background:#0f172a;border-bottom:1px solid #334155">
    <strong>Debug Bar</strong>
    <span>Queries: <?= count(self::$queries) ?> (<?= $totalQueryMs ?>ms)</span>
    <span>Hooks: <?= count(self::$hooks) ?> (<?= $totalHookMs ?>ms)</span>
    <span>Templates: <?= count(self::$templates) ?></span>
  </div>
  <?php if (!empty(self::$queries)): ?>
  <details style="border-bottom:1px solid #334155">
    <summary style="color:#60a5fa">Query Log (<?= count(self::$queries) ?>)</summary>
    <table>
      <?php foreach (self::$queries as $i => $q): ?>
      <tr><td style="color:#94a3b8;padding:2px 12px;width:30px"><?= $i + 1 ?></td><td style="padding:2px 8px"><?= e($q['sql']) ?></td><td style="color:#cbd5e1;padding:2px 8px;text-align:right"><?= $q['ms'] ?>ms</td></tr>
      <?php endforeach ?>
    </table>
  </details>
  <?php endif ?>
  <?php if (!empty(self::$hooks)): ?>
  <details style="border-bottom:1px solid #334155">
    <summary style="color:#f59e0b">Hook Execution (<?= count(self::$hooks) ?>)</summary>
    <?php foreach (self::$hooks as $h): ?>
    <div style="padding:2px 12px"><?= e($h['name']) ?> → <?= $h['callbacks'] ?> callbacks (<?= $h['ms'] ?>ms)</div>
    <?php endforeach ?>
  </details>
  <?php endif ?>
  <?php if (!empty(self::$templates)): ?>
  <details>
    <summary style="color:#10b981">Template Hierarchy (<?= count(self::$templates) ?>)</summary>
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
