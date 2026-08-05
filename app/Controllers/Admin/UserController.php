<?php

namespace App\Controllers\Admin;

use App\Models\User;
use Core\Database\Connection;
use Core\Http\Response;
use Core\Http\Request;
use Core\Http\Session;

class UserController
{
    public function index(): Response
    {
        can_or_403('manage_users');
        return view('admin.users.index', [
            'roles'     => \Core\Auth\Capability::roles(),
            'pageTitle' => '用户管理',
        ]);
    }

    /**
     * AJAX 搜索接口 - 返回 JSON
     * 支持：关键词搜索(display_name/username/email)、角色筛选、状态筛选、排序、分页
     * 使用 POST body 传参，绕过 URL 编码问题
     */
    public function search(): Response
    {
        can_or_403('manage_users');
        $request = app(Request::class);
        $pdo = app(Connection::class)->pdo();

        $page = max(1, (int) $request->input('page', 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT id, username, email, display_name, role, bio, url, status, created_at
                FROM users
                WHERE 1=1";
        $bindings = [];

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $sql .= " AND (display_name LIKE :q1 OR username LIKE :q2 OR email LIKE :q3)";
            $like = '%' . $search . '%';
            $bindings[':q1'] = $like;
            $bindings[':q2'] = $like;
            $bindings[':q3'] = $like;
        }

        $role = $request->input('role');
        $validRoles = array_keys(\Core\Auth\Capability::roles());
        if ($role && in_array($role, $validRoles, true)) {
            $sql .= " AND role = :role";
            $bindings[':role'] = $role;
        }

        $status = $request->input('status');
        if ($status && in_array($status, ['active', 'inactive', 'banned'], true)) {
            $sql .= " AND status = :status";
            $bindings[':status'] = $status;
        }

        $countSql = "SELECT COUNT(*) FROM ($sql) AS sub";
        $stmt = $pdo->prepare($countSql);
        $stmt->execute($bindings);
        $total = (int) $stmt->fetchColumn();

        $sortMap = [
            'display_name' => 'display_name',
            'email'        => 'email',
            'role'         => 'role',
            'status'       => 'status',
            'created_at'   => 'created_at',
        ];
        $sort = $request->input('sort', 'created_at');
        $order = strtolower($request->input('order', 'desc'));
        if (!isset($sortMap[$sort])) $sort = 'created_at';
        if (!in_array($order, ['asc', 'desc'])) $order = 'desc';
        $sql .= " ORDER BY {$sortMap[$sort]} $order";

        $sql .= " LIMIT $perPage OFFSET $offset";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($bindings);
        $items = $stmt->fetchAll();

        $totalPages = max(1, (int) ceil($total / $perPage));

        return (new Response())->json([
            'items'      => $items,
            'total'      => $total,
            'page'       => $page,
            'totalPages' => $totalPages,
            'perPage'    => $perPage,
        ]);
    }

    public function batch(): Response
    {
        can_or_403('manage_users');
        $request = app(Request::class);
        $sess = app(Session::class);
        $action = $request->input('batch_action');
        $ids = array_filter(array_map('intval', explode(',', $request->input('batch_ids', ''))));
        if (empty($ids)) {
            $sess->flash('error', '请选择要操作的用户');
            return redirect(route('admin.users.index'));
        }
        $currentUserId = (int) current_user()->getAttribute('id');
        if ($action === 'delete') {
            $count = 0;
            foreach ($ids as $id) {
                if ($id === $currentUserId) continue;
                $user = User::find($id);
                if ($user) { $user->delete(); $count++; }
            }
            $sess->flash('success', "已删除 {$count} 个用户");
        }
        return redirect(route('admin.users.index'));
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