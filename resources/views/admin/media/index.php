<?php
/** @var array $items $page $totalPages */
ob_start();
?>
<div class="page-header">
    <h2>媒体库</h2>
    <div class="page-header-actions">
        <form method="post" action="<?= route('admin.media.upload') ?>" enctype="multipart/form-data" class="flex items-center gap-8">
            <input type="hidden" name="_token" value="<?= csrf_token() ?>">
            <input type="file" name="file" accept="image/*,application/pdf,audio/*,video/*" class="form-control input-file" required>
            <button type="submit" class="btn btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                上传
            </button>
        </form>
    </div>
</div>

<div class="media-grid">
    <?php foreach ($items as $r): $m = new \App\Models\Media($r); ?>
        <div class="media-card">
            <?php if ($m->isImage()): ?>
                <div class="thumb"><img src="<?= $m->url() ?>" alt="" loading="lazy"></div>
            <?php else: ?>
                <div class="thumb file-icon">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                </div>
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
        <div class="empty-state" style="grid-column:1/-1">
            <div class="empty-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            </div>
            <h3>暂无媒体文件</h3>
            <p>上传图片、PDF、音视频等文件</p>
        </div>
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