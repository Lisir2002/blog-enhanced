<?php

namespace Core\View;

/**
 * 调试工具栏 — 仅在 debug=true 时显示，右下角齿轮图标。
 *
 * 显示逻辑：
 *   - debug=true 且非后台页面 → 右下角显示齿轮图标，点击弹出调试面板
 *   - 已登录用户入口由前台顶栏头像/后台顶栏承担，此处不重复渲染头像
 *   - debug=false 或后台页面 → 不渲染任何内容
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
        if (!config('app.debug', false) || is_admin_route()) {
            return '';
        }

        $id = 'debug-bar-' . bin2hex(random_bytes(4));
        $totalQueryMs = array_sum(array_column(self::$queries, 'ms'));
        $totalHookMs = array_sum(array_column(self::$hooks, 'ms'));

        ob_start();
        ?>
<style>
#<?= $id ?>-wrap {
  position:fixed;bottom:16px;right:16px;z-index:99999;
  font:12px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;
}
#<?= $id ?>-btn {
  display:flex;align-items:center;justify-content:center;
  width:40px;height:40px;
  border-radius:50%;cursor:pointer;
  border:none;background:#1e293b;color:#94a3b8;
  box-shadow:0 4px 14px rgba(0,0,0,0.25);
  transition:background 0.15s,color 0.15s,transform 0.15s;
  font-size:20px;padding:0;
}
#<?= $id ?>-btn:hover { background:#334155;color:#e2e8f0;transform:scale(1.05); }
#<?= $id ?>-dropdown {
  display:none;position:absolute;bottom:52px;right:0;
  width:320px;max-height:70vh;overflow-y:auto;
  background:#1e293b;color:#e2e8f0;
  border:1px solid #334155;border-radius:10px;
  box-shadow:0 8px 30px rgba(0,0,0,0.3);
}
#<?= $id ?>-dropdown.is-open { display:block; }
#<?= $id ?>-header {
  display:flex;align-items:center;gap:10px;
  padding:12px 14px;border-bottom:1px solid #334155;
  font-weight:600;font-size:13px;color:#f1f5f9;
}
#<?= $id ?>-header .badge {
  font-size:11px;font-weight:500;padding:2px 8px;
  border-radius:999px;background:#334155;color:#94a3b8;
}
#<?= $id ?>-body { padding:8px 0; }
#<?= $id ?>-body details {
  border-top:1px solid #334155;
}
#<?= $id ?>-body details:first-child { border-top:none; }
#<?= $id ?>-body summary {
  padding:8px 14px;cursor:pointer;font-weight:600;font-size:12px;
  list-style:none;display:flex;align-items:center;gap:8px;
  color:#60a5fa;user-select:none;
}
#<?= $id ?>-body summary::-webkit-details-marker { display:none; }
#<?= $id ?>-body summary::before {
  content:"▶";font-size:9px;transition:transform 0.15s;
}
#<?= $id ?>-body details[open] summary::before { transform:rotate(90deg); }
#<?= $id ?>-body table { width:100%;border-collapse:collapse;font:11px/1.6 Menlo,Monaco,monospace; }
#<?= $id ?>-body table td { padding:3px 14px;vertical-align:top; }
#<?= $id ?>-body table td:first-child { color:#64748b;width:28px; }
#<?= $id ?>-body table td:last-child { color:#cbd5e1;text-align:right;white-space:nowrap; }
#<?= $id ?>-body .row { padding:4px 14px;font-size:12px;color:#cbd5e1; }
#<?= $id ?>-body .empty { padding:20px;text-align:center;color:#64748b; }
</style>
<div id="<?= $id ?>-wrap">
  <button id="<?= $id ?>-btn" type="button" aria-label="调试面板" title="Debug">⚙</button>
  <div id="<?= $id ?>-dropdown">
    <div id="<?= $id ?>-header">
      <span>🛠 Debug</span>
      <span class="badge">Q:<?= count(self::$queries) ?> (<?= $totalQueryMs ?>ms)</span>
      <span class="badge">H:<?= count(self::$hooks) ?> (<?= $totalHookMs ?>ms)</span>
      <span class="badge">T:<?= count(self::$templates) ?></span>
    </div>
    <div id="<?= $id ?>-body">
      <?php if (!empty(self::$queries)): ?>
      <details open>
        <summary>Query Log (<?= count(self::$queries) ?>)</summary>
        <table>
          <?php foreach (self::$queries as $i => $q): ?>
          <tr><td><?= $i + 1 ?></td><td style="word-break:break-all;color:#e2e8f0"><?= e($q['sql']) ?></td><td><?= $q['ms'] ?>ms</td></tr>
          <?php endforeach ?>
        </table>
      </details>
      <?php endif ?>

      <?php if (!empty(self::$hooks)): ?>
      <details>
        <summary style="color:#f59e0b">Hook Execution (<?= count(self::$hooks) ?>)</summary>
        <?php foreach (self::$hooks as $h): ?>
        <div class="row">
          <?= e($h['name']) ?> → <span style="color:#f59e0b"><?= $h['callbacks'] ?> callbacks</span> (<?= $h['ms'] ?>ms)
        </div>
        <?php endforeach ?>
      </details>
      <?php endif ?>

      <?php if (!empty(self::$templates)): ?>
      <details>
        <summary style="color:#10b981">Template Hierarchy (<?= count(self::$templates) ?>)</summary>
        <?php foreach (self::$templates as $t): ?>
        <div class="row">
          <?= e($t['hierarchy']) ?> → <strong style="color:#10b981"><?= e($t['resolved']) ?></strong>
        </div>
        <?php endforeach ?>
      </details>
      <?php endif ?>

      <?php if (empty(self::$queries) && empty(self::$hooks) && empty(self::$templates)): ?>
      <div class="empty">暂无调试数据</div>
      <?php endif; ?>
    </div>
  </div>
</div>
<script>
(function(){
  var wrap=document.getElementById('<?= $id ?>-wrap');
  var btn=document.getElementById('<?= $id ?>-btn');
  var dd=document.getElementById('<?= $id ?>-dropdown');
  if(!wrap||!btn||!dd)return;
  btn.addEventListener('click',function(e){
    e.stopPropagation();
    dd.classList.toggle('is-open');
  });
  document.addEventListener('click',function(e){
    if(dd.classList.contains('is-open') && !wrap.contains(e.target)){
      dd.classList.remove('is-open');
    }
  });
})();
</script>
<?php
        return (string) ob_get_clean();
    }
}
