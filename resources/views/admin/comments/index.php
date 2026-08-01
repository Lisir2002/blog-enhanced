<?php
/** @var array $items $page $totalPages */
ob_start();
?>
<div class="action-bar">
    <div>
        <a href="<?= route('admin.comments.index') ?>" class="btn btn-sm <?= !app(\Core\Http\Request::class)->input('status') ? 'btn-primary' : 'btn-secondary' ?>">全部</a>
        <a href="?status=pending" class="btn btn-sm <?= app(\Core\Http\Request::class)->input('status') === 'pending' ? 'btn-primary' : 'btn-secondary' ?>">待审</a>
        <a href="?status=approved" class="btn btn-sm <?= app(\Core\Http\Request::class)->input('status') === 'approved' ? 'btn-primary' : 'btn-secondary' ?>">已批准</a>
        <a href="?status=spam" class="btn btn-sm <?= app(\Core\Http\Request::class)->input('status') === 'spam' ? 'btn-primary' : 'btn-secondary' ?>">垃圾</a>
    </div>
</div>

<div class="table-wrap">
    <table>
        <thead><tr><th>评论</th><th>作者</th><th>文章</th><th>状态</th><th>时间</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($items as $r): $c = new \App\Models\Comment($r); $p = $c->post(); ?>
            <tr>
                <td><?= e(mb_substr($c->getAttribute('content'), 0, 60)) ?></td>
                <td><?= e($c->getAttribute('author_name')) ?><br><span style="color:var(--c-text-muted);font-size:12px"><?= e($c->getAttribute('ip')) ?></span></td>
                <td><?php if ($p): ?><a href="<?= $p->url() ?>"><?= e($p->getAttribute('title')) ?></a><?php endif; ?></td>
                <td><span class="badge badge-<?= $c->getAttribute('status') ?>"><?= $c->getAttribute('status') ?></span></td>
                <td><?= $c->getAttribute('created_at') ?></td>
                <td class="btn-row">
                    <?php if ($c->getAttribute('status') !== 'approved'): ?>
                        <form method="post" action="<?= route('admin.comments.approve', ['id' => $c->getAttribute('id')]) ?>" style="display:inline">
                            <input type="hidden" name="_token" value="<?= csrf_token() ?>">
                            <button type="submit" class="btn btn-sm btn-success">批准</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($c->getAttribute('status') !== 'spam'): ?>
                        <form method="post" action="<?= route('admin.comments.spam', ['id' => $c->getAttribute('id')]) ?>" style="display:inline">
                            <input type="hidden" name="_token" value="<?= csrf_token() ?>">
                            <button type="submit" class="btn btn-sm btn-warning">标垃圾</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" action="<?= route('admin.comments.delete', ['id' => $c->getAttribute('id')]) ?>" data-confirm="确定删除？" style="display:inline">
                        <input type="hidden" name="_token" value="<?= csrf_token() ?>">
                        <button type="submit" class="btn btn-sm btn-danger">删除</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($items)): ?>
            <tr><td colspan="6" style="text-align:center;color:var(--c-text-muted);padding:40px">暂无评论</td></tr>
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
            <a href="?page=<?= $i ?>&status=<?= urlencode(app(\Core\Http\Request::class)->input('status', '')) ?>"><?= $i ?></a>
        <?php endif; ?>
    <?php endfor; ?>
</nav>
<?php endif;
$content = ob_get_clean();
require resource_path('views/layouts/admin.php');
