<?php

namespace Core\Auth;

/**
 * 鉴权管理器 - 基于 Session 的状态保持。
 */
class AuthManager
{
    private ?\App\Models\User $user = null;
    private bool $loaded = false;

    /**
     * 当前登录用户（未登录返回 null）。
     */
    public function user(): ?\App\Models\User
    {
        if ($this->loaded) {
            return $this->user;
        }
        $this->loaded = true;
        $sess = app(\Core\Http\Session::class);
        $id = $sess->get('user_id');
        if (!$id) {
            return null;
        }
        $this->user = \App\Models\User::find($id);
        if (!$this->user) {
            $sess->forget('user_id');
        }
        return $this->user;
    }

    public function id(): ?int
    {
        $u = $this->user();
        return $u ? (int) $u->getAttribute('id') : null;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    /**
     * 登录尝试 - 失败返回 false。
     */
    public function attempt(string $login, string $password): bool
    {
        $user = \App\Models\User::findBy('email', $login)
            ?? \App\Models\User::findBy('username', $login);
        if (!$user) {
            return false;
        }
        $hash = $user->getAttribute('password');
        if (!password_verify($password, $hash)) {
            return false;
        }
        $this->logIn($user);
        return true;
    }

    public function logIn(\App\Models\User $user): void
    {
        $sess = app(\Core\Http\Session::class);
        session_regenerate_id(true);
        $sess->set('user_id', (int) $user->getAttribute('id'));
        $this->user = $user;
        $this->loaded = true;
        do_action('user_logged_in', $user);
    }

    public function logOut(): void
    {
        $sess = app(\Core\Http\Session::class);
        do_action('user_logged_out', $this->user);
        $sess->forget('user_id');
        session_regenerate_id(true);
        $this->user = null;
        $this->loaded = true;
    }

    /**
     * 校验当前用户角色是否在列表中。
     */
    public function hasRole(string ...$roles): bool
    {
        $u = $this->user();
        if (!$u) {
            return false;
        }
        return in_array($u->getAttribute('role'), $roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function can(string $capability): bool
    {
        $u = $this->user();
        if (!$u) {
            return false;
        }
        $role = $u->getAttribute('role');
        return Capability::has($role, $capability);
    }
}
