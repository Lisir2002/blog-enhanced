<?php
/** @var array $categories */
ob_start();
?>
<div class="action-bar">
    <h3>分类列表 (<?= count($categories) ?>)</h3>
    <a href="#" class="btn btn-primary" onclick="document.getElementById('cat-form').style.display='block';return false">+ 新建</a>
</div>
<div id="cat-form" style="display:none" class="card">
    <form method="post" action="<?= route('admin.categories.store') ?>">
        <input type="hidden" name="_token" value="<?= csrf_token() ?>">
        <div class="form-row">
            <div class="form-group"><label>名称</label><input type="text" name="name" required></div>
            <div class="form-group"><label>别名 (留空自动生成)</label><input type="text" name="slug"></div>
            <div class="form-group"><label>描述</label><input type="text" name="description"></div>
        </div>
        <button type="submit" class="btn btn-primary">创建</button>
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('cat-form').style.display='none'">取消</button>
    </form>
</div>
<div class="table-wrap">
    <table>
        <thead><tr><th>名称</th><th>别名</th><th>描述</th><th>文章数</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($categories as $r): $cat = new \App\Models\Category($r); ?>
            <tr>
                <td><?= e($cat->getAttribute('name')) ?></td>
                <td><?= e($cat->getAttribute('slug')) ?></td>
                <td><?= e($cat->getAttribute('description') ?? '') ?></td>
                <td><?= $cat->postCount() ?></td>
                <td class="btn-row">
                    <a href="<?= $cat->url() ?>" target="_blank" class="btn btn-sm btn-secondary">查看</a>
                    <form method="post" action="<?= route('admin.categories.delete', ['id' => $cat->getAttribute('id')]) ?>" data-confirm="确定删除？">
                        <input type="hidden" name="_token" value="<?= csrf_token() ?>">
                        <button type="submit" class="btn btn-sm btn-danger">删除</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($categories)): ?>
            <tr><td colspan="5" style="text-align:center;color:var(--c-text-muted);padding:40px">暂无分类</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
$content = ob_get_clean();
require resource_path('views/layouts/admin.php');
