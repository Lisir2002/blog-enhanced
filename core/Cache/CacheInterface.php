<?php

namespace Core\Cache;

/**
 * 缓存接口 - 增强版。
 *
 * 增强：
 * - 多驱动支持（File/Redis/Memcached/APCu）
 * - 缓存标签（Tags）：分组失效
 * - 缓存锁（Lock）：防缓存击穿
 * - 记忆模式（Remember）：cache-aside 模式
 * - 永久存储（Forever）：不过期
 */
interface CacheInterface
{
    /**
     * 取缓存值，不存在返回 default。
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * 写缓存，ttl 秒后过期。ttl=0 表示永久。
     */
    public function set(string $key, mixed $value, ?int $ttl = 3600): bool;

    /**
     * 永久存储（不过期）。
     */
    public function forever(string $key, mixed $value): bool;

    /**
     * 删除单个缓存。
     */
    public function delete(string $key): bool;

    /**
     * 清空全部缓存。
     */
    public function clear(): bool;

    /**
     * 判断缓存是否存在（不延长 TTL）。
     */
    public function has(string $key): bool;

    /**
     * 记忆模式：缓存不存在时执行 callback 并写入，存在时直接返回。
     */
    public function remember(string $key, callable $callback, ?int $ttl = 3600): mixed;

    /**
     * 带标签写入：写入值并关联到一组标签，后续可按标签批量失效。
     *
     * @param string $key 缓存键
     * @param mixed $value 缓存值
     * @param array<int,string> $tags 关联标签
     * @param int|null $ttl 过期秒数
     */
    public function tagged(string $key, mixed $value, array $tags, ?int $ttl = 3600): bool;

    /**
     * 按标签失效：清除所有关联到指定标签的缓存。
     *
     * @param string $tag 标签名
     */
    public function flushTag(string $tag): bool;

    /**
     * 获取缓存锁（防缓存击穿）。
     *
     * @param string $key 锁键
     * @param int $ttl 锁持有时间（秒）
     */
    public function lock(string $key, int $ttl = 10): CacheLock;

    /**
     * 自增计数器（不存在则从 0 开始）。
     */
    public function increment(string $key, int $step = 1): int;

    /**
     * 自减计数器。
     */
    public function decrement(string $key, int $step = 1): int;

    /**
     * 获取当前驱动名称。
     */
    public function driver(): string;
}
