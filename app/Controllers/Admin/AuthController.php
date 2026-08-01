<?php

namespace App\Controllers\Admin;

use App\Models\User;
use App\Models\Option;
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
        $username = trim((string) $request->input('username', ''));
        $password = (string) $request->input('password', '');

        if ($username === '' || $password === '') {
            $sess->flash('error', '请输入用户名和密码');
            return redirect(url('/login'));
        }

        $user = User::query()
            ->where('username', '=', $username)
            ->orWhere('email', '=', $username)
            ->first();

        if (!$user || !password_verify($password, $user['password'])) {
            $sess->flash('error', '用户名或密码错误');
            return redirect(url('/login'));
        }
        if (($user['status'] ?? '') !== 'active') {
            $sess->flash('error', '账号已被禁用');
            return redirect(url('/login'));
        }

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
