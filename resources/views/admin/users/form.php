<?php
/** @var \App\Models\User|null $user
 *  @var string $action
 *  @var string $pageTitle
 */
ob_start();
$roles = \Core\Auth\Capability::roles();
?>
<form method="post" action="<?= $action ?>">
    <input type="hidden" name="_token" value="<?= csrf_token() ?>">
    <div class="card" style="max-width:600px">
        <div class="form-group">
            <label>用户名 *</label>
            <input type="text" name="username" value="<?= e($user ? $user->getAttribute('username') : old('username', '')) ?>" required <?= $user ? 'readonly' : '' ?>>
        </div>
        <div class="form-group">
            <label>邮箱 *</label>
            <input type="email" name="email" value="<?= e($user ? $user->getAttribute('email') : old('email', '')) ?>" required>
        </div>
        <div class="form-group">
            <label>显示名</label>
            <input type="text" name="display_name" value="<?= e($user ? $user->getAttribute('display_name') : old('display_name', '')) ?>">
        </div>
        <div class="form-group">
            <label>密码 <?= $user ? '(留空不修改)' : '*' ?></label>
            <input type="password" name="password" <?= $user ? '' : 'required' ?> minlength="6">
        </div>
        <div class="form-group">
            <label>角色</label>
            <select name="role">
                <?php foreach ($roles as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $user && $user->getAttribute('role') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>个人简介</label>
            <textarea name="bio" rows="3"><?= e($user ? ($user->getAttribute('bio') ?? '') : old('bio', '')) ?></textarea>
        </div>
        <div class="form-group">
            <label>个人网址</label>
            <input type="url" name="url" value="<?= e($user ? ($user->getAttribute('url') ?? '') : old('url', '')) ?>">
        </div>
        <div class="btn-row">
            <button type="submit" class="btn btn-primary"><?= $user ? '保存' : '创建用户' ?></button>
            <a href="<?= route('admin.users.index') ?>" class="btn btn-secondary">取消</a>
        </div>
    </div>
</form>
<?php
$content = ob_get_clean();
require resource_path('views/layouts/admin.php');
