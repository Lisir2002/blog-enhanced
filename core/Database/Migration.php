<?php

namespace Core\Database;

use PDO;

/**
 * 迁移基类 — 每个迁移文件继承此类。
 *
 * 文件名约定：database/migrations/YYYYMMDD_HHMMSS_description.php
 * 类名约定：将 description 转 PascalCase（去掉时间戳前缀）
 *
 * 子类实现 up()（必须），down() 可选。
 */
abstract class Migration
{
    public function __construct(
        protected PDO $pdo,
        protected ?Connection $connection = null,
    ) {}

    abstract public function up(): void;

    public function down(): void
    {
        // 默认空实现，子类按需覆写
    }

    protected function exec(string $sql): void
    {
        $this->pdo->exec($sql);
    }

    protected function isSqlite(): bool
    {
        return $this->connection?->isSqlite() ?? true;
    }

    protected function isMysql(): bool
    {
        return $this->connection?->isMysql() ?? false;
    }
}
