<?php
/** @var array $users */
ob_start();
$roles = \Core\Auth\Capability::roles();
?>
<div class="page-header">
    <h2>用户管理</h2>
    <div class="page-header-actions">
        <a href="<?= route('admin.users.create') ?>" class="btn btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            添加用户
        </a>
    </div>
</div>

<div class="table-wrap">
    <table>
        <thead><tr><th>用户</th><th>邮箱</th><th>角色</th><th>状态</th><th>注册时间</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($users as $r): $u = new \App\Models\User($r); ?>
            <tr>
                <td>
                    <div class="user-cell">
                        <img src="<?= $u->avatarUrl(32) ?>" alt="" loading="lazy">
                        <div class="user-info">
                            <a href="<?= route('admin.users.edit', ['id' => $u->getAttribute('id')]) ?>" class="user-name"><?= e($u->displayName()) ?></a>
                            <span class="user-username">@<?= e($u->getAttribute('username')) ?></span>
                        </div>
                    </div>
                </td>
                <td><?= e($u->getAttribute('email')) ?></td>
                <td><span class="badge badge-<?= $u->getAttribute('role') === 'super_admin' ? 'blue' : 'gray' ?>"><?= $roles[$u->getAttribute('role')] ?? $u->getAttribute('role') ?></span></td>
                <td><span class="badge badge-<?= $u->getAttribute('status') === 'active' ? 'green' : 'red' ?>"><?= $u->getAttribute('status') ?></span></td>
                <td><?= $u->getAttribute('created_at') ?></td>
                <td>
                    <div class="btn-row">
                        <a href="<?= route('admin.users.edit', ['id' => $u->getAttribute('id')]) ?>" class="btn btn-sm btn-secondary">编辑</a>
                        <?php if ($u->getAttribute('id') != current_user()->getAttribute('id')): ?>
                        <form method="post" action="<?= route('admin.users.delete', ['id' => $u->getAttribute('id')]) ?>" data-confirm="确定删除？">
                            <input type="hidden" name="_token" value="<?= csrf_token() ?>">
                            <button type="submit" class="btn btn-sm btn-danger">删除</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php
$content = ob_get_clean();
require resource_path('views/layouts/admin.php');