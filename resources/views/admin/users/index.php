<?php
/** @var array $users */
ob_start();
$roles = \Core\Auth\Capability::roles();
?>
<div class="action-bar">
    <h3>用户列表 (<?= count($users) ?>)</h3>
    <a href="<?= route('admin.users.create') ?>" class="btn btn-primary">+ 添加用户</a>
</div>
<div class="table-wrap">
    <table>
        <thead><tr><th>用户</th><th>邮箱</th><th>角色</th><th>状态</th><th>注册时间</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($users as $r): $u = new \App\Models\User($r); ?>
            <tr>
                <td>
                    <img src="<?= $u->avatarUrl(28) ?>" alt="" style="width:28px;height:28px;border-radius:50%;vertical-align:middle">
                    <a href="<?= route('admin.users.edit', ['id' => $u->getAttribute('id')]) ?>"><strong><?= e($u->displayName()) ?></strong></a>
                    <span style="color:var(--c-text-muted)">@<?= e($u->getAttribute('username')) ?></span>
                </td>
                <td><?= e($u->getAttribute('email')) ?></td>
                <td><span class="badge badge-<?= $u->getAttribute('role') ?>"><?= $roles[$u->getAttribute('role')] ?? $u->getAttribute('role') ?></span></td>
                <td><span class="badge badge-<?= $u->getAttribute('status') === 'active' ? 'published' : 'spam' ?>"><?= $u->getAttribute('status') ?></span></td>
                <td><?= $u->getAttribute('created_at') ?></td>
                <td class="btn-row">
                    <a href="<?= route('admin.users.edit', ['id' => $u->getAttribute('id')]) ?>" class="btn btn-sm btn-secondary">编辑</a>
                    <?php if ($u->getAttribute('id') != current_user()->getAttribute('id')): ?>
                    <form method="post" action="<?= route('admin.users.delete', ['id' => $u->getAttribute('id')]) ?>" data-confirm="确定删除？">
                        <input type="hidden" name="_token" value="<?= csrf_token() ?>">
                        <button type="submit" class="btn btn-sm btn-danger">删除</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php
$content = ob_get_clean();
require resource_path('views/layouts/admin.php');
