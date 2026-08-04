<?php
/** @var array $plugins
 *  @var array $active
 */
ob_start();
?>
<div class="page-header">
    <h2>插件管理</h2>
    <div class="page-header-actions">
        <form method="post" action="<?= route('admin.plugins.upload') ?>" enctype="multipart/form-data" class="flex items-center gap-8">
            <input type="hidden" name="_token" value="<?= csrf_token() ?>">
            <input type="file" name="plugin_zip" accept=".zip" class="form-control input-file" required>
            <button type="submit" class="btn btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                上传插件
            </button>
        </form>
    </div>
</div>

<div class="plugin-grid">
    <?php foreach ($plugins as $p): $isActive = isset($active[$p['name']]); ?>
        <div class="plugin-card <?= $isActive ? 'active' : '' ?>">
            <div class="info">
                <h3><?= e($p['meta']['name'] ?? $p['name']) ?> <small>v<?= e($p['meta']['version'] ?? '1.0') ?></small></h3>
                <p><?= e($p['meta']['description'] ?? '—') ?></p>
                <p>作者: <?= e($p['meta']['author'] ?? '—') ?></p>
                <div class="actions">
                    <?php if ($isActive): ?>
                        <a href="<?= route('admin.plugins.deactivate', ['name' => $p['name']]) ?>" class="btn btn-sm btn-secondary">停用</a>
                    <?php else: ?>
                        <a href="<?= route('admin.plugins.activate', ['name' => $p['name']]) ?>" class="btn btn-sm btn-primary">激活</a>
                    <?php endif; ?>
                    <?php if (!$isActive): ?>
                        <form method="post" action="<?= route('admin.plugins.delete', ['name' => $p['name']]) ?>" data-confirm="确定删除？">
                            <input type="hidden" name="_token" value="<?= csrf_token() ?>">
                            <button type="submit" class="btn btn-sm btn-danger">删除</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (empty($plugins)): ?>
        <div class="empty-state" style="grid-column:1/-1">
            <div class="empty-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="8" height="8"/><rect x="14" y="2" width="8" height="8"/><rect x="14" y="14" width="8" height="8"/><rect x="2" y="14" width="8" height="8"/></svg>
            </div>
            <h3>暂无插件</h3>
            <p>插件放在 plugins/ 目录，每个插件目录含一个主插件文件</p>
        </div>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require resource_path('views/layouts/admin.php');