<?php
/** @var array $themes
 *  @var string $active
 */
ob_start();
?>
<div class="action-bar">
    <h3>主题管理</h3>
    <form method="post" action="<?= route('admin.themes.upload') ?>" enctype="multipart/form-data" style="display:inline">
        <input type="hidden" name="_token" value="<?= csrf_token() ?>">
        <input type="file" name="theme_zip" accept=".zip" required>
        <button type="submit" class="btn btn-primary">上传主题</button>
    </form>
</div>

<div class="theme-grid">
    <?php foreach ($themes as $t): ?>
        <div class="theme-card <?= $t['name'] === $active ? 'active' : '' ?>">
            <div class="screenshot">
                <?php if (!empty($t['meta']['screenshot'])): ?>
                    <img src="<?= asset('themes/' . $t['name'] . '/' . $t['meta']['screenshot']) ?>" alt="">
                <?php else: ?>
                    <div style="display:flex;align-items:center;justify-content:center;height:180px;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;font-size:48px">🎨</div>
                <?php endif; ?>
                <?php if ($t['name'] === $active): ?><span class="badge-active">已激活</span><?php endif; ?>
            </div>
            <div class="info">
                <h3><?= e($t['meta']['name'] ?? $t['name']) ?> <small>v<?= e($t['meta']['version'] ?? '1.0') ?></small></h3>
                <p><?= e($t['meta']['description'] ?? '—') ?></p>
                <p>作者: <?= e($t['meta']['author'] ?? '—') ?></p>
                <div class="actions">
                    <?php if ($t['name'] !== $active): ?>
                        <a href="<?= route('admin.themes.activate', ['name' => $t['name']]) ?>" class="btn btn-sm btn-primary">激活</a>
                        <form method="post" action="<?= route('admin.themes.delete', ['name' => $t['name']]) ?>" data-confirm="确定删除？">
                            <input type="hidden" name="_token" value="<?= csrf_token() ?>">
                            <button type="submit" class="btn btn-sm btn-danger">删除</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (empty($themes)): ?>
        <div class="empty-state"><div class="icon">🎨</div><p>暂无主题</p></div>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require resource_path('views/layouts/admin.php');
