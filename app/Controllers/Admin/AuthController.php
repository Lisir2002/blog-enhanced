<?php

namespace App\Controllers\Admin;

use App\Models\User;
use App\Models\Option;
use App\Services\LoginRateLimiter;
use Core\Http\Response;
use Core\Http\Request;
use Core\Http\Session;

class AuthController
{
    public function loginForm(): Response
    {
        return view('auth.login', [
            'pageTitle' => '登录',
            'next'      => app(Request::class)->input('next', ''),
        ]);
    }

    public function login(): Response
    {
        $request = app(Request::class);
        $sess = app(Session::class);
        /** @var LoginRateLimiter $limiter */
        $limiter = app(LoginRateLimiter::class);
        $ip = $request->ip();
        $username = trim((string) $request->input('username', ''));
        $password = (string) $request->input('password', '');

        if ($username === '' || $password === '') {
            $sess->flash('error', '请输入用户名和密码');
            return redirect(url('/login'));
        }

        // 限流检查
        if ($limiter->isLocked($ip, $username)) {
            $sess->flash('error', '登录尝试过多，请 15 分钟后再试');
            $sess->flashInput(['username' => $username]);
            return redirect(url('/login'));
        }

        $user = User::query()
            ->where('username', '=', $username)
            ->orWhere('email', '=', $username)
            ->first();

        $ok = $user && password_verify($password, $user['password']);

        if (!$ok) {
            $remaining = $limiter->remainingAttempts($ip, $username);
            $limiter->recordFailure($ip, $username);
            \Core\Log\Log::warning('Login failed', [
                'ip'       => $ip,
                'username' => $username,
                'remaining'=> max(0, $remaining - 1),
            ]);

            if ($remaining <= 1) {
                $msg = '登录失败次数过多，账号已锁定 15 分钟';
            } else {
                $msg = sprintf('用户名或密码错误，剩余尝试次数 %d', $remaining - 1);
            }
            $sess->flash('error', $msg);
            $sess->flashInput(['username' => $username]);
            return redirect(url('/login'));
        }

        if (($user['status'] ?? '') !== 'active') {
            $sess->flash('error', '账号已被禁用');
            return redirect(url('/login'));
        }

        // 成功 - 清零计数 + 登录
        $limiter->clear($ip, $username);
        $auth = app(\Core\Auth\AuthManager::class);
        $auth->logIn(new User($user));

        $next = trim((string) $request->input('next', ''));
        $target = $next !== '' ? $next : url('/admin');
        return redirect($target);
    }

    public function registerForm(): Response
    {
        if (Option::get('allow_registration', '0') !== '1') {
            app(Session::class)->flash('error', '当前未开放注册');
            return redirect(url('/login'));
        }
        return view('auth.register', ['pageTitle' => '注册']);
    }

    public function register(): Response
    {
        $request = app(Request::class);
        $sess = app(Session::class);
        $username = trim((string) $request->input('username', ''));
        $email = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');

        if (strlen($username) < 3 || strlen($password) < 6 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $sess->flash('error', '用户名至少3位，密码至少6位，邮箱格式需正确');
            return redirect(url('/register'));
        }
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $username)) {
            $sess->flash('error', '用户名只允许字母、数字、下划线、连字符');
            return redirect(url('/register'));
        }
        if (User::query()->where('username', '=', $username)->first()) {
            $sess->flash('error', '用户名已存在');
            return redirect(url('/register'));
        }
        if (User::query()->where('email', '=', $email)->first()) {
            $sess->flash('error', '邮箱已注册');
            return redirect(url('/register'));
        }

        User::query()->insert([
            'username'     => $username,
            'email'        => $email,
            'password'     => password_hash($password, PASSWORD_BCRYPT),
            'display_name' => $username,
            'role'         => 'subscriber',
            'status'       => 'active',
            'created_at'   => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);

        $sess->flash('success', '注册成功，请登录');
        return redirect(url('/login'));
    }

    public function logout(): Response
    {
        app(\Core\Auth\AuthManager::class)->logOut();
        return redirect(url('/'));
    }
}
