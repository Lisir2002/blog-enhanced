<?php
/** @var \App\Models\User|null $user
 *  @var string $action
 *  @var string $pageTitle
 */
ob_start();
$roles = \Core\Auth\Capability::roles();
?>
<div class="page-header">
    <h2><?= e($pageTitle) ?></h2>
</div>

<form method="post" action="<?= $action ?>">
    <input type="hidden" name="_token" value="<?= csrf_token() ?>">
    <div class="card" style="max-width:600px">
        <div class="form-group">
            <label>用户名 *</label>
            <input type="text" name="username" value="<?= e($user ? $user->getAttribute('username') : old('username', '')) ?>" class="form-control" required <?= $user ? 'readonly' : '' ?>>
        </div>
        <div class="form-group">
            <label>邮箱 *</label>
            <input type="email" name="email" value="<?= e($user ? $user->getAttribute('email') : old('email', '')) ?>" class="form-control" required>
        </div>
        <div class="form-group">
            <label>显示名</label>
            <input type="text" name="display_name" value="<?= e($user ? $user->getAttribute('display_name') : old('display_name', '')) ?>" class="form-control">
        </div>
        <div class="form-group">
            <label>密码 <?= $user ? '<span class="label-hint">留空不修改</span>' : '*' ?></label>
            <input type="password" name="password" class="form-control" <?= $user ? '' : 'required' ?> minlength="6">
        </div>
        <div class="form-group">
            <label>角色</label>
            <select name="role" class="form-control">
                <?php foreach ($roles as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $user && $user->getAttribute('role') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>个人简介</label>
            <textarea name="bio" rows="3" class="form-control"><?= e($user ? ($user->getAttribute('bio') ?? '') : old('bio', '')) ?></textarea>
        </div>
        <div class="form-group">
            <label>个人网址</label>
            <input type="url" name="url" value="<?= e($user ? ($user->getAttribute('url') ?? '') : old('url', '')) ?>" class="form-control" placeholder="https://">
        </div>
        <div class="btn-row">
            <button type="submit" class="btn btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                <?= $user ? '保存' : '创建用户' ?>
            </button>
            <a href="<?= route('admin.users.index') ?>" class="btn btn-secondary">取消</a>
        </div>
    </div>
</form>
<?php
$content = ob_get_clean();
require resource_path('views/layouts/admin.php');