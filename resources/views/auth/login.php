<?php
/** @var string $pageTitle $next */
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - <?= e(\App\Models\Option::get('site_name', config('app.name'))) ?></title>
    <link rel="stylesheet" href="<?= url('themes/default/assets/css/style.css') ?>">
</head>
<body class="auth-page">
<div class="auth-container">
    <a href="<?= url('/') ?>" class="back-link">← 返回首页</a>
    <h1 class="auth-title"><?= e($pageTitle) ?></h1>

    <?php
    $sess = app(\Core\Http\Session::class);
    $success = $sess->pull('success');
    $error = $sess->pull('error');
    ?>
    <?php if ($success): ?><div class="flash flash-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

    <form method="post" action="<?= url('/login') ?>">
        <input type="hidden" name="_token" value="<?= csrf_token() ?>">
        <?php if ($next): ?><input type="hidden" name="next" value="<?= e($next) ?>"><?php endif; ?>
        <div class="form-group">
            <label>用户名 / 邮箱</label>
            <input type="text" name="username" required autofocus value="<?= e(old('username', '')) ?>">
        </div>
        <div class="form-group">
            <label>密码</label>
            <input type="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">登录</button>
    </form>

    <p class="auth-footer">还没有账号？<a href="<?= url('/register') ?>">注册</a></p>
</div>
</body>
</html>
