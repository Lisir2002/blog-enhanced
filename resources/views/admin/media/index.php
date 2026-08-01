<?php
/** @var array $items $page $totalPages */
ob_start();
?>
<div class="action-bar">
    <h3>媒体库</h3>
    <form method="post" action="<?= route('admin.media.upload') ?>" enctype="multipart/form-data" style="display:inline">
        <input type="hidden" name="_token" value="<?= csrf_token() ?>">
        <input type="file" name="file" accept="image/*,application/pdf,audio/*,video/*" required>
        <button type="submit" class="btn btn-primary">上传</button>
    </form>
</div>
<div class="media-grid">
    <?php foreach ($items as $r): $m = new \App\Models\Media($r); ?>
        <div class="media-card">
            <?php if ($m->isImage()): ?>
                <div class="thumb"><img src="<?= $m->url() ?>" alt="" loading="lazy"></div>
            <?php else: ?>
                <div class="thumb file-icon">📄</div>
            <?php endif; ?>
            <div class="meta">
                <span class="filename" title="<?= e($m->filename()) ?>"><?= e(mb_substr($m->filename(), 0, 24)) ?></span>
                <span class="size"><?= $m->humanSize() ?></span>
            </div>
            <div class="actions">
                <a href="<?= $m->url() ?>" target="_blank" class="btn btn-sm btn-secondary">查看</a>
                <form method="post" action="<?= route('admin.media.delete', ['id' => $m->getAttribute('id')]) ?>" data-confirm="确定删除？">
                    <input type="hidden" name="_token" value="<?= csrf_token() ?>">
                    <button type="submit" class="btn btn-sm btn-danger">删除</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (empty($items)): ?>
        <div class="empty-state"><div class="icon">🖼️</div><p>暂无媒体文件</p></div>
    <?php endif; ?>
</div>
<?php if ($totalPages > 1): ?>
<nav class="pagination">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <?php if ($i == $page): ?>
            <span class="current"><?= $i ?></span>
        <?php else: ?>
            <a href="?page=<?= $i ?>"><?= $i ?></a>
        <?php endif; ?>
    <?php endfor; ?>
</nav>
<?php endif;
$content = ob_get_clean();
require resource_path('views/layouts/admin.php');
