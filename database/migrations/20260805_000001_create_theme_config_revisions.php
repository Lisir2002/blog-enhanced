<?php

namespace Database\Migrations;

use Core\Database\Migration;

/**
 * 创建主题配置快照表（用于配置历史与回滚）。
 */
class CreateThemeConfigRevisions extends Migration
{
    public function up(): void
    {
        if ($this->isSqlite()) {
            $this->exec("CREATE TABLE IF NOT EXISTS theme_config_revisions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                theme TEXT NOT NULL,
                snapshot TEXT NOT NULL,
                note TEXT DEFAULT '',
                created_by INTEGER DEFAULT 0,
                restored_at TEXT DEFAULT NULL,
                created_at TEXT NOT NULL
            )");
            $this->exec("CREATE INDEX IF NOT EXISTS idx_revisions_theme ON theme_config_revisions(theme)");
        } else {
            $this->exec("CREATE TABLE IF NOT EXISTS `theme_config_revisions` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `theme` VARCHAR(100) NOT NULL,
                `snapshot` LONGTEXT NOT NULL,
                `note` VARCHAR(255) DEFAULT '',
                `created_by` INT UNSIGNED DEFAULT 0,
                `restored_at` DATETIME DEFAULT NULL,
                `created_at` DATETIME NOT NULL,
                INDEX `idx_revisions_theme` (`theme`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
    }

    public function down(): void
    {
        $this->exec("DROP TABLE IF EXISTS `theme_config_revisions`");
    }
}