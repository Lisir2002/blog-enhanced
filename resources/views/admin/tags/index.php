<?php
/** @var array $tags */
ob_start();
?>
<div class="action-bar">
    <h3>标签列表 (<?= count($tags) ?>)</h3>
    <a href="#" class="btn btn-primary" onclick="document.getElementById('tag-form').style.display='block';return false">+ 新建</a>
</div>
<div id="tag-form" style="display:none" class="card">
    <form method="post" action="<?= route('admin.tags.store') ?>">
        <input type="hidden" name="_token" value="<?= csrf_token() ?>">
        <div class="form-row">
            <div class="form-group"><label>名称</label><input type="text" name="name" required></div>
            <div class="form-group"><label>别名</label><input type="text" name="slug"></div>
        </div>
        <button type="submit" class="btn btn-primary">创建</button>
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
                <td class="btn-row">
                    <a href="<?= $tag->url() ?>" target="_blank" class="btn btn-sm btn-secondary">查看</a>
                    <form method="post" action="<?= route('admin.tags.delete', ['id' => $tag->getAttribute('id')]) ?>" data-confirm="确定删除？">
                        <input type="hidden" name="_token" value="<?= csrf_token() ?>">
                        <button type="submit" class="btn btn-sm btn-danger">删除</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($tags)): ?>
            <tr><td colspan="4" style="text-align:center;color:var(--c-text-muted);padding:40px">暂无标签</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
$content = ob_get_clean();
require resource_path('views/layouts/admin.php');
