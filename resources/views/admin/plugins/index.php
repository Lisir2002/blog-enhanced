<?php
/** @var array $plugins
 *  @var array $active
 */
ob_start();
?>
<div class="action-bar">
    <h3>插件管理</h3>
    <form method="post" action="<?= route('admin.plugins.upload') ?>" enctype="multipart/form-data" style="display:inline">
        <input type="hidden" name="_token" value="<?= csrf_token() ?>">
        <input type="file" name="plugin_zip" accept=".zip" required>
        <button type="submit" class="btn btn-primary">上传插件</button>
    </form>
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
        <div class="empty-state"><div class="icon">🔌</div><p>暂无插件。插件放在 plugins/ 目录，每个插件目录含一个主插件文件。</p></div>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require resource_path('views/layouts/admin.php');
