<?php
/** @var array $tags */
ob_start();
?>
<div class="page-header">
    <h2>标签管理</h2>
    <div class="page-header-actions">
        <button class="btn btn-primary" data-toggle-form="tag-form">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            新建标签
        </button>
    </div>
</div>

<div id="tag-form" class="card mb-16 form-toggle">
    <form method="post" action="<?= route('admin.tags.store') ?>">
        <input type="hidden" name="_token" value="<?= csrf_token() ?>">
        <div class="form-row">
            <div class="form-group"><label>名称</label><input type="text" name="name" class="form-control" required></div>
            <div class="form-group"><label>别名</label><input type="text" name="slug" class="form-control"></div>
        </div>
        <div class="btn-row">
            <button type="submit" class="btn btn-primary">创建</button>
            <button type="button" class="btn btn-secondary" data-toggle-form="tag-form">取消</button>
        </div>
    </form>
</div>

<div class="table-wrap">
    <table>
        <thead><tr><th>名称</th><th>别名</th><th>文章数</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($tags as $r): $tag = new \App\Models\Tag($r); ?>
            <tr>
                <td><?= e($tag->getAttribute('name')) ?></td>
                <td><?= e($tag->getAttribute('slug')) ?></td>
                <td><?= $tag->postCount() ?></td>
                <td>
                    <div class="btn-row">
                        <a href="<?= $tag->url() ?>" target="_blank" class="btn btn-sm btn-secondary">查看</a>
                        <form method="post" action="<?= route('admin.tags.delete', ['id' => $tag->getAttribute('id')]) ?>" data-confirm="确定删除？">
                            <input type="hidden" name="_token" value="<?= csrf_token() ?>">
                            <button type="submit" class="btn btn-sm btn-danger">删除</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($tags)): ?>
            <tr><td colspan="4" class="empty-cell">暂无标签</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
$content = ob_get_clean();
require resource_path('views/layouts/admin.php');