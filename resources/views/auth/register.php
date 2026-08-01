<?php
/** @var string $pageTitle */
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= asset('themes/default/assets/css/style.css') ?>">
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

    <form method="post" action="<?= url('/register') ?>">
        <input type="hidden" name="_token" value="<?= csrf_token() ?>">
        <div class="form-group">
            <label>用户名</label>
            <input type="text" name="username" required autofocus value="<?= e(old('username', '')) ?>">
        </div>
        <div class="form-group">
            <label>邮箱</label>
            <input type="email" name="email" required value="<?= e(old('email', '')) ?>">
        </div>
        <div class="form-group">
            <label>密码（至少 6 位）</label>
            <input type="password" name="password" required minlength="6">
        </div>
        <button type="submit" class="btn btn-primary btn-block">注册</button>
    </form>

    <p class="auth-footer">已有账号？<a href="<?= url('/login') ?>">登录</a></p>
</div>
</body>
</html>
