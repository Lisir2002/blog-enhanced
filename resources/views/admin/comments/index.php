<?php
/** @var array $items $page $totalPages */
ob_start();
$status = app(\Core\Http\Request::class)->input('status', '');
?>
<div class="page-header">
    <h2>评论管理</h2>
</div>

<div class="table-wrap">
    <div class="table-toolbar">
        <div class="table-toolbar-left">
            <div class="filter-tabs">
                <a href="<?= route('admin.comments.index') ?>" class="filter-tab <?= !$status ? 'active' : '' ?>">全部</a>
                <a href="?status=pending" class="filter-tab <?= $status === 'pending' ? 'active' : '' ?>">待审</a>
                <a href="?status=approved" class="filter-tab <?= $status === 'approved' ? 'active' : '' ?>">已批准</a>
                <a href="?status=spam" class="filter-tab <?= $status === 'spam' ? 'active' : '' ?>">垃圾</a>
            </div>
        </div>
    </div>
    <table>
        <thead><tr><th>评论</th><th>作者</th><th>文章</th><th>状态</th><th>时间</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($items as $r): $c = new \App\Models\Comment($r); $p = $c->post(); ?>
            <tr>
                <td><?= e(mb_substr($c->getAttribute('content'), 0, 60)) ?></td>
                <td><?= e($c->getAttribute('author_name')) ?><br><span class="text-muted" style="font-size:12px"><?= e($c->getAttribute('ip')) ?></span></td>
                <td><?php if ($p): ?><a href="<?= $p->url() ?>"><?= e($p->getAttribute('title')) ?></a><?php endif; ?></td>
                <td><span class="badge badge-<?= $c->getAttribute('status') ?>"><?= $c->getAttribute('status') ?></span></td>
                <td><?= $c->getAttribute('created_at') ?></td>
                <td>
                    <div class="btn-row">
                        <?php if ($c->getAttribute('status') !== 'approved'): ?>
                            <form method="post" action="<?= route('admin.comments.approve', ['id' => $c->getAttribute('id')]) ?>" class="form-inline">
                                <input type="hidden" name="_token" value="<?= csrf_token() ?>">
                                <button type="submit" class="btn btn-sm btn-success">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                    批准
                                </button>
                            </form>
                        <?php endif; ?>
                        <?php if ($c->getAttribute('status') !== 'spam'): ?>
                            <form method="post" action="<?= route('admin.comments.spam', ['id' => $c->getAttribute('id')]) ?>" class="form-inline">
                                <input type="hidden" name="_token" value="<?= csrf_token() ?>">
                                <button type="submit" class="btn btn-sm btn-warning">标垃圾</button>
                            </form>
                        <?php endif; ?>
                        <form method="post" action="<?= route('admin.comments.delete', ['id' => $c->getAttribute('id')]) ?>" data-confirm="确定删除？" class="form-inline">
                            <input type="hidden" name="_token" value="<?= csrf_token() ?>">
                            <button type="submit" class="btn btn-sm btn-danger">删除</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($items)): ?>
            <tr><td colspan="6" class="empty-cell">暂无评论</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php if ($totalPages > 1): ?>
<nav class="pagination">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <?php if ($i == $page): ?>
            <span class="current"><?= $i ?></span>
        <?php else: ?>
            <a href="?page=<?= $i ?>&status=<?= urlencode($status) ?>"><?= $i ?></a>
        <?php endif; ?>
    <?php endfor; ?>
</nav>
<?php endif;
$content = ob_get_clean();
require resource_path('views/layouts/admin.php');