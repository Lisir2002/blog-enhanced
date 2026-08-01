<?php

namespace Core\Http;

/**
 * Request 抽象，封装 $_GET / $_POST / $_SERVER / Cookie / Files。
 */
class Request
{
    public readonly string $method;
    public readonly string $path;

    private array $inputs = [];
    private array $files = [];
    private array $cookies = [];
    private array $server = [];

    public function __construct()
    {
        $this->inputs = array_merge($_GET, $_POST);
        $this->files = $_FILES;
        $this->cookies = $_COOKIE;
        $this->server = $_SERVER;
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $this->path = '/' . trim(parse_url($uri, PHP_URL_PATH) ?? '/', '/') ?: '/';
    }

    public static function capture(): static
    {
        return new static();
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * 是否 XHR / POST ajax。
     */
    public function ajax(): bool
    {
        return (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');
    }

    public function expectsJson(): bool
    {
        return $this->ajax() || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->inputs[$key] ?? $default;
    }

    public function all(): array
    {
        return $this->inputs;
    }

    public function only(array $keys): array
    {
        return array_intersect_key($this->inputs, array_flip($keys));
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function files(): array
    {
        return $this->files;
    }

    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    public function server(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }

    public function ip(): string
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    public function userAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    /**
     * 上次提交后保留的旧输入。
     */
    public function old(string $key, mixed $default = null): mixed
    {
        $sess = app(\Core\Http\Session::class);
        $old = $sess->get('_old_input', []);
        return $old[$key] ?? $default;
    }
}
