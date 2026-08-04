<?php

namespace Database\Migrations;

use Core\Database\Migration;

/**
 * 初始 schema — 从 schema.sqlite.sql / schema.mysql.sql 创建全部表。
 *
 * 此迁移使用 CREATE TABLE IF NOT EXISTS，对已有数据库安全。
 * 后续增量迁移在此基础上添加列/表/索引。
 */
class CreateInitialSchema extends Migration
{
    public function up(): void
    {
        $schemaFile = $this->isSqlite() ? 'schema.sqlite.sql' : 'schema.mysql.sql';
        $path = database_path($schemaFile);

        if (!is_file($path)) {
            throw new \RuntimeException("Schema file not found: $path");
        }

        $sql = file_get_contents($path);

        // 按分号拆分语句，逐条执行（schema.sql 不含触发器/存储过程，安全拆分）
        $statements = array_filter(
            array_map('trim', explode(';', $sql))
        );

        foreach ($statements as $stmt) {
            if ($stmt !== '') {
                $this->pdo->exec($stmt);
            }
        }
    }

    public function down(): void
    {
        // 按依赖反序删除所有表
        $tables = [
            'pages', 'options', 'media', 'comments',
            'post_tag', 'posts', 'tags', 'categories', 'users',
        ];

        foreach ($tables as $table) {
            if ($this->isSqlite()) {
                $this->exec("DROP TABLE IF EXISTS $table");
            } else {
                $this->exec("DROP TABLE IF EXISTS `$table`");
            }
        }
    }
}
