<?php

namespace Core\View;

/**
 * 融合工具栏 — 将 Admin Bar 与 Debug Bar 整合为顶栏头像下拉菜单。
 *
 * 显示逻辑：
 *   - 已登录用户 → 顶栏右侧显示头像，点击弹出下拉菜单（管理 Tab + 调试 Tab）
 *   - 调试模式开启（未登录）→ 顶栏右侧显示齿轮图标，点击弹出调试面板
 *   - 两者都不满足 → 不渲染
 *   - 后台管理页面 → 不渲染（避免干扰后台界面）
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
        $user = current_user();
        $isLoggedIn = (bool)$user;
        $isDebug = config('app.debug', false);
        $showBar = $isLoggedIn || $isDebug;

        if (!$showBar || is_admin_route()) {
            return '';
        }

        $id = 'debug-bar-' . bin2hex(random_bytes(4));
        $totalQueryMs = array_sum(array_column(self::$queries, 'ms'));
        $totalHookMs = array_sum(array_column(self::$hooks, 'ms'));

        ob_start();
        ?>
<style>
#<?= $id ?>-wrap {
  position:fixed;top:0;right:0;z-index:99999;
  font:12px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;
}
#<?= $id ?>-btn {
  display:flex;align-items:center;justify-content:center;
  width:36px;height:36px;margin:8px 12px 0 0;margin-left:auto;
  border-radius:50%;cursor:pointer;overflow:hidden;
  border:2px solid transparent;transition:border-color 0.15s;
  background:transparent;padding:0;float:right;
}
#<?= $id ?>-btn:hover { border-color:#3b82f6; }
#<?= $id ?>-btn img { width:100%;height:100%;object-fit:cover;display:block;border-radius:50%; }
#<?= $id ?>-btn .gear {
  width:100%;height:100%;display:flex;align-items:center;justify-content:center;
  font-size:18px;color:#64748b;border-radius:50%;
  background:#f1f5f9;transition:background 0.15s;
}
#<?= $id ?>-btn .gear:hover { background:#e2e8f0; }
#<?= $id ?>-dropdown {
  display:none;position:absolute;top:52px;right:12px;
  width:280px;max-height:70vh;overflow-y:auto;
  background:#1e293b;color:#e2e8f0;
  border:1px solid #334155;border-radius:8px;
  box-shadow:0 8px 30px rgba(0,0,0,0.3);
}
#<?= $id ?>-dropdown.is-open { display:block; }
#<?= $id ?>-tabs {
  display:flex;border-bottom:1px solid #334155;
  border-radius:8px 8px 0 0;overflow:hidden;
}
#<?= $id ?>-tabs .tab {
  flex:1;padding:10px 0;cursor:pointer;font-size:13px;text-align:center;
  color:#94a3b8;border-bottom:2px solid transparent;
  transition:color 0.15s,border-color 0.15s;
  user-select:none;background:transparent;
}
#<?= $id ?>-tabs .tab:hover { color:#e2e8f0;background:#0f172a; }
#<?= $id ?>-tabs .tab.active { color:#3b82f6;border-bottom-color:#3b82f6;background:#0f172a; }
#<?= $id ?>-dropdown .tab-content { padding:12px;display:none; }
#<?= $id ?>-dropdown .tab-content.active { display:block; }
#<?= $id ?>-dropdown .tab-content summary { padding:4px 0;cursor:pointer;font-weight:600; }
#<?= $id ?>-dropdown .tab-content table { width:100%;border-collapse:collapse; }
#<?= $id ?>-dropdown a { text-decoration:none; }
</style>
<div id="<?= $id ?>-wrap">
  <div id="<?= $id ?>-btn">
    <?php if ($isLoggedIn): ?>
    <img src="<?= e($user->avatarUrl(36)) ?>" alt="User">
    <?php else: ?>
    <span class="gear">⚙</span>
    <?php endif; ?>
  </div>
  <div id="<?= $id ?>-dropdown">
    <div id="<?= $id ?>-tabs">
      <?php if ($isLoggedIn): ?>
      <div class="tab active" data-tab="admin">管理</div>
      <?php endif; ?>
      <?php if ($isDebug): ?>
      <div class="tab <?= !$isLoggedIn ? 'active' : '' ?>" data-tab="debug">调试</div>
      <?php endif; ?>
    </div>

    <?php if ($isLoggedIn): ?>
    <div class="tab-content active" id="<?= $id ?>-tab-admin">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;padding:4px 0;">
        <img src="<?= e($user->avatarUrl(36)) ?>" alt="" width="36" height="36" style="border-radius:50%;">
        <div>
          <div style="font-weight:600;font-size:14px;color:#f1f5f9"><?= e($user->displayName()) ?></div>
          <div style="color:#64748b;font-size:11px"><?= e($user->getAttribute('role') ?? '') ?></div>
        </div>
      </div>
      <div style="display:grid;gap:4px;">
        <a href="<?= url('admin') ?>" style="display:flex;align-items:center;gap:10px;color:#e2e8f0;padding:8px 10px;border-radius:6px;transition:background 0.15s;" onmouseover="this.style.background='#334155'" onmouseout="this.style.background='transparent'">
          <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
          <span>后台管理</span>
        </a>
        <a href="<?= url('admin/posts/create') ?>" style="display:flex;align-items:center;gap:10px;color:#e2e8f0;padding:8px 10px;border-radius:6px;transition:background 0.15s;" onmouseover="this.style.background='#334155'" onmouseout="this.style.background='transparent'">
          <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><line x1="18" y1="2" x2="2" y2="18"></line><path d="M7.5 20.5L19 9l-4-4L3.5 16.5 2 22l5.5-1.5z"></path></svg>
          <span>写文章</span>
        </a>
        <a href="<?= url('logout') ?>" style="display:flex;align-items:center;gap:10px;color:#e2e8f0;padding:8px 10px;border-radius:6px;transition:background 0.15s;" onmouseover="this.style.background='#334155'" onmouseout="this.style.background='transparent'">
          <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
          <span>退出</span>
        </a>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($isDebug): ?>
    <div class="tab-content <?= !$isLoggedIn ? 'active' : '' ?>" id="<?= $id ?>-tab-debug" style="font:12px/1.5 Menlo,Monaco,monospace">
      <div style="display:flex;gap:12px;padding:4px 0 8px;color:#94a3b8;border-bottom:1px solid #334155;margin-bottom:8px;flex-wrap:wrap">
        <strong style="color:#e2e8f0">Debug</strong>
        <span>Queries: <?= count(self::$queries) ?> (<?= $totalQueryMs ?>ms)</span>
        <span>Hooks: <?= count(self::$hooks) ?> (<?= $totalHookMs ?>ms)</span>
        <span>Templates: <?= count(self::$templates) ?></span>
      </div>
      <?php if (!empty(self::$queries)): ?>
      <details style="border-bottom:1px solid #334155">
        <summary style="color:#60a5fa">Query Log (<?= count(self::$queries) ?>)</summary>
        <table>
          <?php foreach (self::$queries as $i => $q): ?>
          <tr><td style="color:#94a3b8;padding:2px 8px;width:24px"><?= $i + 1 ?></td><td style="padding:2px 8px"><?= e($q['sql']) ?></td><td style="color:#cbd5e1;padding:2px 8px;text-align:right;white-space:nowrap"><?= $q['ms'] ?>ms</td></tr>
          <?php endforeach ?>
        </table>
      </details>
      <?php endif ?>
      <?php if (!empty(self::$hooks)): ?>
      <details style="border-bottom:1px solid #334155">
        <summary style="color:#f59e0b">Hook Execution (<?= count(self::$hooks) ?>)</summary>
        <?php foreach (self::$hooks as $h): ?>
        <div style="padding:2px 8px"><?= e($h['name']) ?> → <?= $h['callbacks'] ?> callbacks (<?= $h['ms'] ?>ms)</div>
        <?php endforeach ?>
      </details>
      <?php endif ?>
      <?php if (!empty(self::$templates)): ?>
      <details>
        <summary style="color:#10b981">Template Hierarchy (<?= count(self::$templates) ?>)</summary>
        <?php foreach (self::$templates as $t): ?>
        <div style="padding:2px 8px"><?= e($t['hierarchy']) ?> → <strong style="color:#10b981"><?= e($t['resolved']) ?></strong></div>
        <?php endforeach ?>
      </details>
      <?php endif ?>
      <?php if (empty(self::$queries) && empty(self::$hooks) && empty(self::$templates)): ?>
      <div style="padding:16px;text-align:center;color:#64748b">暂无调试数据</div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<script>
(function(){
  var wrap=document.getElementById('<?= $id ?>-wrap');
  var btn=document.getElementById('<?= $id ?>-btn');
  var dd=document.getElementById('<?= $id ?>-dropdown');
  var tabs=dd.querySelectorAll('#<?= $id ?>-tabs .tab');
  var contents=dd.querySelectorAll('.tab-content');

  btn.addEventListener('click',function(e){
    e.stopPropagation();
    dd.classList.toggle('is-open');
  });

  tabs.forEach(function(tab){
    tab.addEventListener('click',function(){
      var id=tab.dataset.tab;
      tabs.forEach(function(t){t.classList.remove('active');});
      contents.forEach(function(c){c.classList.remove('active');});
      tab.classList.add('active');
      var target=document.getElementById('<?= $id ?>-tab-'+id);
      if(target) target.classList.add('active');
    });
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