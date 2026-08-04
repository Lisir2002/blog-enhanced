<?php

namespace Database\Migrations;

use Core\Database\Connection;
use Core\Database\Migration;

/**
 * 创建审计日志表和队列表
 */
class CreateAuditLogsAndJobs extends Migration
{
    public function up(): void
    {
        $pdo = $this->connection->pdo();
        $prefix = $this->connection->tablePrefix();

        // 审计日志表
        $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            action VARCHAR(100) NOT NULL,
            description VARCHAR(255) NOT NULL,
            user_id INTEGER NULL,
            username VARCHAR(100) NULL,
            ip VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            context TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_audit_logs_action ON {$prefix}audit_logs(action)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_audit_logs_user_id ON {$prefix}audit_logs(user_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_audit_logs_created_at ON {$prefix}audit_logs(created_at)");

        // 队列任务表
        $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}jobs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            queue VARCHAR(50) NOT NULL DEFAULT 'default',
            job VARCHAR(255) NOT NULL,
            data TEXT NULL,
            attempts INTEGER NOT NULL DEFAULT 0,
            available_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_jobs_queue ON {$prefix}jobs(queue)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_jobs_available_at ON {$prefix}jobs(available_at)");
    }

    public function down(): void
    {
        $pdo = $this->connection->pdo();
        $prefix = $this->connection->tablePrefix();
        $pdo->exec("DROP TABLE IF EXISTS {$prefix}audit_logs");
        $pdo->exec("DROP TABLE IF EXISTS {$prefix}jobs");
    }
}
