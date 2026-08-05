<?php

namespace Database\Migrations;

use Core\Database\Migration;

/**
 * 为 posts 表添加 deleted_at 列，支持软删除。
 */
class AddDeletedAtToPosts extends Migration
{
    public function up(): void
    {
        if ($this->isSqlite()) {
            // SQLite 不支持直接 ADD COLUMN 带默认值且非空，但我们的列可为空
            try {
                $this->exec("ALTER TABLE posts ADD COLUMN deleted_at TEXT");
            } catch (\PDOException $e) {
                // 列可能已存在
                if (str_contains($e->getMessage(), 'duplicate column')) {
                    return;
                }
                throw $e;
            }
        } else {
            try {
                $this->exec("ALTER TABLE posts ADD COLUMN deleted_at DATETIME DEFAULT NULL");
            } catch (\PDOException $e) {
                if (str_contains($e->getMessage(), 'Duplicate column')) {
                    return;
                }
                throw $e;
            }
        }
    }

    public function down(): void
    {
        if (!$this->isSqlite()) {
            try {
                $this->exec("ALTER TABLE posts DROP COLUMN deleted_at");
            } catch (\PDOException $e) {
                // ignore
            }
        }
        // SQLite 不支持 DROP COLUMN，忽略
    }
}