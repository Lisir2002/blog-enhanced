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
        \Core\Log\Log::info('User logged in', [
            'uid'      => (int) $user->getAttribute('id'),
            'username' => $user->getAttribute('username'),
        ]);
        do_action('user_logged_in', $user);
    }

    public function logOut(): void
    {
        $sess = app(\Core\Http\Session::class);
        if ($this->user) {
            \Core\Log\Log::info('User logged out', [
                'uid'      => (int) $this->user->getAttribute('id'),
                'username' => $this->user->getAttribute('username'),
            ]);
            do_action('user_logged_out', $this->user);
        }
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

    /**
     * 校验当前用户是否拥有指定权限。
     *
     * 支持第二个参数：文章 ID / 文章数组 / User 对象，用于细粒度校验
     * （例如 author 编辑文章时只能编辑自己的）。
     */
    public function can(string $capability, mixed $args = null): bool
    {
        $u = $this->user();
        if (!$u) {
            return false;
        }
        $role = $u->getAttribute('role');
        if (Capability::has($role, $capability, $args)) {
            // author / contributor 编辑文章时，必须是自己的
            if ($capability === 'edit_posts' && $args !== null && in_array($role, ['author', 'contributor'], true)) {
                $authorId = is_array($args) ? (int) ($args['author_id'] ?? 0)
                    : (is_object($args) && method_exists($args, 'getAttribute') ? (int) $args->getAttribute('author_id') : 0);
                return $authorId === (int) $u->getAttribute('id');
            }
            return true;
        }
        return false;
    }
}
