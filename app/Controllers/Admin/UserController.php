<?php

namespace App\Controllers\Admin;

use App\Models\User;
use Core\Http\Response;
use Core\Http\Request;
use Core\Http\Session;

class UserController
{
    public function index(): Response
    {
        can_or_403('manage_users');
        $users = User::query()->orderBy('created_at', 'DESC')->get();
        return view('admin.users.index', [
            'users'     => $users,
            'pageTitle' => '用户管理',
        ]);
    }

    public function create(): Response
    {
        can_or_403('manage_users');
        return view('admin.users.form', [
            'user'      => null,
            'pageTitle' => '添加用户',
            'action'    => route('admin.users.store'),
        ]);
    }

    public function store(): Response
    {
        can_or_403('manage_users');
        $request = app(Request::class);
        $sess = app(Session::class);
        $username = trim((string) $request->input('username', ''));
        $email = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');
        $role = (string) $request->input('role', 'visitor');

        if ($username === '' || $email === '' || strlen($password) < 6) {
            $sess->flash('error', '用户名/邮箱不能为空，密码至少6位');
            return redirect(route('admin.users.create'));
        }
        if (User::query()->where('username', '=', $username)->first()) {
            $sess->flash('error', '用户名已存在');
            return redirect(route('admin.users.create'));
        }
        if (User::query()->where('email', '=', $email)->first()) {
            $sess->flash('error', '邮箱已存在');
            return redirect(route('admin.users.create'));
        }

        User::query()->insert([
            'username'    => $username,
            'email'       => $email,
            'password'    => password_hash($password, PASSWORD_BCRYPT),
            'display_name'=> trim((string) $request->input('display_name', '')) ?: $username,
            'role'        => in_array($role, array_keys(\Core\Auth\Capability::roles()), true) ? $role : 'visitor',
            'bio'         => trim((string) $request->input('bio', '')),
            'url'         => trim((string) $request->input('url', '')),
            'status'      => 'active',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        $sess->flash('success', '用户已创建');
        return redirect(route('admin.users.index'));
    }

    public function edit(array $params): Response
    {
        can_or_403('manage_users');
        $user = User::find((int) $params['id']);
        if (!$user) return redirect(route('admin.users.index'));
        return view('admin.users.form', [
            'user'      => $user,
            'pageTitle' => '编辑用户',
            'action'    => route('admin.users.update', ['id' => $user->getAttribute('id')]),
        ]);
    }

    public function update(array $params): Response
    {
        can_or_403('manage_users');
        $id = (int) $params['id'];
        $user = User::find($id);
        if (!$user) return redirect(route('admin.users.index'));
        $request = app(Request::class);
        $sess = app(Session::class);
        $data = [
            'email'        => trim((string) $request->input('email', '')),
            'display_name' => trim((string) $request->input('display_name', '')),
            'role'         => in_array($request->input('role'), array_keys(\Core\Auth\Capability::roles()), true) ? $request->input('role') : 'visitor',
            'bio'          => trim((string) $request->input('bio', '')),
            'url'          => trim((string) $request->input('url', '')),
            'updated_at'   => date('Y-m-d H:i:s'),
        ];
        $password = (string) $request->input('password', '');
        if ($password !== '') {
            if (strlen($password) < 6) {
                $sess->flash('error', '密码至少6位');
                return redirect(route('admin.users.edit', ['id' => $id]));
            }
            $data['password'] = password_hash($password, PASSWORD_BCRYPT);
        }
        User::query()->where('id', '=', $id)->update($data);
        $sess->flash('success', '用户已更新');
        return redirect(route('admin.users.edit', ['id' => $id]));
    }

    public function delete(array $params): Response
    {
        can_or_403('manage_users');
        $id = (int) $params['id'];
        if ($id === (int) current_user()->getAttribute('id')) {
            app(Session::class)->flash('error', '不能删除自己');
            return redirect(route('admin.users.index'));
        }
        $user = User::find($id);
        if ($user) {
            $user->delete();
            app(Session::class)->flash('success', '用户已删除');
        }
        return redirect(route('admin.users.index'));
    }
}
