<?php

namespace App\Services;

use Core\Cache\CacheInterface;

/**
 * 登录限流器 - 基于 IP + 用户名的失败计数。
 *
 * 规则：
 *   - 同一 (IP + username) 连续失败 5 次后，锁定 15 分钟
 *   - 锁定期间即使密码正确也拒绝登录
 *   - 成功登录时清零计数
 */
class LoginRateLimiter
{
    private const MAX_ATTEMPTS = 5;
    private const LOCK_TTL = 900; // 15 分钟
    private const ATTEMPT_TTL = 1800; // 30 分钟内累计失败

    private CacheInterface $cache;

    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    private function key(string $ip, string $username): string
    {
        return 'login_fail:' . md5($ip . '|' . strtolower($username));
    }

    public function isLocked(string $ip, string $username): bool
    {
        return $this->cache->has($this->key($ip, $username) . ':lock');
    }

    public function recordFailure(string $ip, string $username): int
    {
        $k = $this->key($ip, $username);
        $current = (int) $this->cache->get($k, 0);
        $current++;
        $this->cache->set($k, $current, self::ATTEMPT_TTL);
        if ($current >= self::MAX_ATTEMPTS) {
            $this->cache->set($k . ':lock', true, self::LOCK_TTL);
        }
        return $current;
    }

    public function clear(string $ip, string $username): void
    {
        $k = $this->key($ip, $username);
        $this->cache->delete($k);
        $this->cache->delete($k . ':lock');
    }

    public function remainingAttempts(string $ip, string $username): int
    {
        if ($this->isLocked($ip, $username)) {
            return 0;
        }
        $k = $this->key($ip, $username);
        $current = (int) $this->cache->get($k, 0);
        return max(0, self::MAX_ATTEMPTS - $current);
    }
}
