<?php
/** @var array $themes
 *  @var string $active
 */
ob_start();
?>
<div class="page-header">
    <h2>主题管理</h2>
    <div class="page-header-actions">
        <form method="post" action="<?= route('admin.themes.upload') ?>" enctype="multipart/form-data" class="flex items-center gap-8">
            <input type="hidden" name="_token" value="<?= csrf_token() ?>">
            <input type="file" name="theme_zip" accept=".zip" class="form-control input-file" required>
            <button type="submit" class="btn btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                上传主题
            </button>
        </form>
    </div>
</div>

<div class="theme-grid">
    <?php foreach ($themes as $t): ?>
        <div class="theme-card <?= $t['name'] === $active ? 'active' : '' ?>">
            <div class="screenshot">
                <?php if (!empty($t['meta']['screenshot'])): ?>
                    <img src="<?= asset('themes/' . $t['name'] . '/' . $t['meta']['screenshot']) ?>" alt="">
                <?php else: ?>
                    <div class="theme-no-screenshot">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19l7-7 3 3-7 7-3-3z"/><path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"/><path d="M2 2l7.586 7.586"/><circle cx="11" cy="11" r="2"/></svg>
                    </div>
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
        <div class="empty-state" style="grid-column:1/-1">
            <div class="empty-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19l7-7 3 3-7 7-3-3z"/><path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"/><path d="M2 2l7.586 7.586"/><circle cx="11" cy="11" r="2"/></svg>
            </div>
            <h3>暂无主题</h3>
            <p>上传主题包 (.zip) 或手动放到 themes/ 目录</p>
        </div>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require resource_path('views/layouts/admin.php');