<?php
/** @var array $posts $page $totalPages */
ob_start();
?>
<div class="action-bar">
    <form method="get" style="display:flex;gap:8px">
        <input type="text" name="q" value="<?= e(app(\Core\Http\Request::class)->input('q', '')) ?>" placeholder="搜索文章..." class="form-control" style="padding:6px 12px;border:1px solid var(--c-border);border-radius:6px">
        <select name="status" style="padding:6px 12px;border:1px solid var(--c-border);border-radius:6px">
            <option value="">全部状态</option>
            <option value="published" <?= app(\Core\Http\Request::class)->input('status') === 'published' ? 'selected' : '' ?>>已发布</option>
            <option value="draft" <?= app(\Core\Http\Request::class)->input('status') === 'draft' ? 'selected' : '' ?>>草稿</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">筛选</button>
    </form>
    <a href="<?= route('admin.posts.create') ?>" class="btn btn-primary">+ 写文章</a>
</div>

<div class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>标题</th>
            <th>作者</th>
            <th>分类</th>
            <th>状态</th>
            <th>发布时间</th>
            <th>操作</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($posts as $r): $post = new \App\Models\Post($r); $author = $post->author(); $cat = $post->category(); ?>
            <tr>
                <td><a href="<?= route('admin.posts.edit', ['id' => $post->getAttribute('id')]) ?>"><?= e($post->getAttribute('title')) ?></a></td>
                <td><?= $author ? e($author->displayName()) : '-' ?></td>
                <td><?= $cat ? e($cat->getAttribute('name')) : '-' ?></td>
                <td><span class="badge badge-<?= $post->getAttribute('status') ?>"><?= $post->getAttribute('status') === 'published' ? '已发布' : '草稿' ?></span></td>
                <td><?= e($post->getAttribute('published_at') ?? $post->getAttribute('created_at')) ?></td>
                <td class="btn-row">
                    <a href="<?= $post->url() ?>" target="_blank" class="btn btn-sm btn-secondary">查看</a>
                    <a href="<?= route('admin.posts.edit', ['id' => $post->getAttribute('id')]) ?>" class="btn btn-sm btn-primary">编辑</a>
                    <form method="post" action="<?= route('admin.posts.delete', ['id' => $post->getAttribute('id')]) ?>" data-confirm="确定删除该文章？">
                        <input type="hidden" name="_token" value="<?= csrf_token() ?>">
                        <button type="submit" class="btn btn-sm btn-danger">删除</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($posts)): ?>
            <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--c-text-muted)">暂无文章</td></tr>
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
            <a href="?page=<?= $i ?>&q=<?= urlencode(app(\Core\Http\Request::class)->input('q', '')) ?>&status=<?= urlencode(app(\Core\Http\Request::class)->input('status', '')) ?>"><?= $i ?></a>
        <?php endif; ?>
    <?php endfor; ?>
</nav>
<?php endif;
$content = ob_get_clean();
require resource_path('views/layouts/admin.php');
