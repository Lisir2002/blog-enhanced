<?php

namespace Core\Cache;

/**
 * 缓存锁 - 防止缓存击穿（Cache Stampede）。
 *
 * 工作原理：
 *   1. 请求 A 获取锁成功 → 查询数据库 → 写缓存 → 释放锁
 *   2. 请求 B 获取锁失败 → 短暂等待 → 重试获取缓存 → 命中则直接返回
 *
 * 用法：
 *   $lock = $cache->lock('post:123', 10);
 *   if ($lock->acquire()) {
 *       try { $data = fetchData(); $cache->set('post:123', $data); }
 *       finally { $lock->release(); }
 *   } else {
 *       // 等待其他请求写入缓存
 *       usleep(100000);
 *       $data = $cache->get('post:123');
 *   }
 */
class CacheLock
{
    private string $key;
    private int $ttl;
    private ?CacheInterface $cache = null;
    private bool $owned = false;

    public function __construct(string $key, int $ttl = 10, ?CacheInterface $cache = null)
    {
        $this->key = 'lock:' . $key;
        $this->ttl = $ttl;
        $this->cache = $cache;
    }

    /**
     * 绑定缓存驱动（由 CacheManager 调用）。
     */
    public function bind(CacheInterface $cache): void
    {
        $this->cache = $cache;
    }

    /**
     * 尝试获取锁，成功返回 true，已被占用返回 false。
     */
    public function acquire(): bool
    {
        if ($this->cache === null) {
            return true; // 无缓存驱动时退化为无锁
        }
        // 利用 set 的原子性（FileCache 通过文件创建，Redis 通过 SETNX）
        $token = bin2hex(random_bytes(8));
        $this->owned = $this->cache->set($this->key, $token, $this->ttl);
        return $this->owned;
    }

    /**
     * 释放锁（仅当当前持有）。
     */
    public function release(): void
    {
        if ($this->owned && $this->cache !== null) {
            $this->cache->delete($this->key);
            $this->owned = false;
        }
    }

    /**
     * 阻塞式获取锁，最多等待 $waitMs 毫秒。
     */
    public function block(int $waitMs = 1000): bool
    {
        $deadline = microtime(true) + ($waitMs / 1000);
        while (microtime(true) < $deadline) {
            if ($this->acquire()) {
                return true;
            }
            usleep(50000); // 50ms
        }
        return false;
    }

    /**
     * 析构时自动释放锁（防止泄漏）。
     */
    public function __destruct()
    {
        $this->release();
    }
}
