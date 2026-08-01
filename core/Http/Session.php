<?php

namespace Core\Http;

/**
 * 基于文件的 Session 抽象。
 */
class Session
{
    private string $id;
    private bool $started = false;

    public function __construct()
    {
        $this->configure();
    }

    private function configure(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            return;
        }
        $driver = config('session.driver', 'file');
        if ($driver === 'file') {
            $path = storage_path('sessions');
            if (!is_dir($path)) {
                @mkdir($path, 0777, true);
            }
            ini_set('session.save_handler', 'files');
            ini_set('session.save_path', $path);
        }
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        session_set_cookie_params([
            'lifetime' => config('session.lifetime', 120) * 60,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_name('blog_session');
        session_start();
        $this->started = true;
    }

    public function id(): string
    {
        return session_id();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function flush(): void
    {
        $_SESSION = [];
    }

    /**
     * 取 CSRF token，没有则生成。
     */
    public function csrfToken(): string
    {
        if (!$this->has('_csrf')) {
            $this->set('_csrf', bin2hex(random_bytes(32)));
        }
        return $this->get('_csrf');
    }

    /**
     * 校验 CSRF token。
     */
    public function verifyCsrf(?string $token): bool
    {
        return is_string($token) && hash_equals($this->csrfToken(), $token);
    }

    /**
     * 闪存数据（仅在下次请求可见）。
     */
    public function flash(string $key, mixed $value): void
    {
        $old = $this->get('_old_input', []);
        $old[$key] = $value;
        $this->set('_old_input', $old);
    }

    public function flashInput(array $input): void
    {
        $this->set('_old_input', $input);
    }

    /**
     * 拉取一次数据（fetch-once）。
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        $v = $this->get($key, $default);
        $this->forget($key);
        return $v;
    }
}
