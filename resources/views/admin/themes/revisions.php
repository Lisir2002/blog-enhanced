<?php
/**
 * 配置历史页面
 * @var array $theme
 * @var array $snapshots
 */
ob_start();
?>
<div class="page-header">
    <div class="page-header-left">
        <a href="<?= route('admin.themes.customize', ['name' => $theme['name']]) ?>" class="btn btn-back">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            返回定制
        </a>
        <h2>配置历史 - <?= e($theme['meta']['name'] ?? $theme['name']) ?></h2>
    </div>
    <div class="page-header-actions">
        <form method="post" action="<?= route('admin.themes.revisions.create', ['name' => $theme['name']]) ?>" class="inline-form" style="display:flex;gap:8px;align-items:center;">
            <input type="hidden" name="_token" value="<?= csrf_token() ?>">
            <input type="text" name="note" class="form-control" placeholder="快照说明..." style="width:200px;" maxlength="100">
            <button type="submit" class="btn btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                创建快照
            </button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">配置快照</h3>
        <span class="theme-count-badge"><?= count($snapshots) ?> 个</span>
    </div>

    <?php if (empty($snapshots)): ?>
    <div class="card-empty">
        <p>暂无配置快照。保存配置或点击"创建快照"可手动备份当前配置。</p>
    </div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>说明</th>
                    <th>创建时间</th>
                    <th>回滚时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($snapshots as $snap): ?>
                <tr>
                    <td><strong>#<?= (int)($snap['id'] ?? 0) ?></strong></td>
                    <td><?= e($snap['note'] ?? '无说明') ?></td>
                    <td><?= e($snap['created_at'] ?? '') ?></td>
                    <td>
                        <?php if (!empty($snap['restored_at'])): ?>
                            <span class="badge badge-success"><?= e($snap['restored_at']) ?></span>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="post" action="<?= route('admin.themes.revisions.restore', ['name' => $theme['name'], 'id' => $snap['id']]) ?>" class="inline-form" onsubmit="return confirm('确定回滚到快照 #<?= (int)($snap['id'] ?? 0) ?>？当前配置将自动备份。')">
                            <input type="hidden" name="_token" value="<?= csrf_token() ?>">
                            <button type="submit" class="btn btn-sm btn-primary">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
                                回滚
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require resource_path('views/layouts/admin.php');