<?php
/** @var array $posts $page $totalPages */
ob_start();
?>
<div class="page-header">
    <h2>文章管理</h2>
    <div class="page-header-actions">
        <a href="<?= route('admin.posts.create') ?>" class="btn btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            写文章
        </a>
    </div>
</div>

<div class="table-wrap">
    <div class="table-toolbar">
        <div class="table-toolbar-left">
            <form method="get" class="filter-form">
                <div class="search-box">
                    <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" name="q" value="<?= e(app(\Core\Http\Request::class)->input('q', '')) ?>" placeholder="搜索文章..." class="form-control">
                </div>
                <select name="status" class="form-control">
                    <option value="">全部状态</option>
                    <option value="published" <?= app(\Core\Http\Request::class)->input('status') === 'published' ? 'selected' : '' ?>>已发布</option>
                    <option value="draft" <?= app(\Core\Http\Request::class)->input('status') === 'draft' ? 'selected' : '' ?>>草稿</option>
                </select>
                <button type="submit" class="btn btn-sm btn-primary">筛选</button>
            </form>
        </div>
    </div>
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
                <td>
                    <div class="btn-row">
                        <a href="<?= $post->url() ?>" target="_blank" class="btn btn-sm btn-secondary">查看</a>
                        <a href="<?= route('admin.posts.edit', ['id' => $post->getAttribute('id')]) ?>" class="btn btn-sm btn-primary">编辑</a>
                        <form method="post" action="<?= route('admin.posts.delete', ['id' => $post->getAttribute('id')]) ?>" data-confirm="确定删除该文章？">
                            <input type="hidden" name="_token" value="<?= csrf_token() ?>">
                            <button type="submit" class="btn btn-sm btn-danger">删除</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($posts)): ?>
            <tr><td colspan="6" class="empty-cell">暂无文章</td></tr>
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