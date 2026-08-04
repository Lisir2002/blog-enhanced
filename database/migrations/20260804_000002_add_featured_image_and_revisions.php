<?php

namespace Database\Migrations;

use Core\Database\Migration;

class AddFeaturedImageAndRevisions extends Migration
{
    public function up(): void
    {
        // posts 表加 featured_image_id (幂等：如果列已存在则跳过)
        $columns = $this->pdo->query("PRAGMA table_info(posts)")->fetchAll();
        $hasColumn = false;
        foreach ($columns as $col) {
            if ($col['name'] === 'featured_image_id') {
                $hasColumn = true;
                break;
            }
        }
        if (!$hasColumn && $this->isSqlite()) {
            $this->exec("ALTER TABLE posts ADD COLUMN featured_image_id INTEGER DEFAULT NULL");
        }

        // 文章修订历史表
        if ($this->isSqlite()) {
            $this->exec("CREATE TABLE IF NOT EXISTS post_revisions (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                post_id     INTEGER NOT NULL,
                author_id   INTEGER,
                title       TEXT NOT NULL,
                content_md  TEXT,
                excerpt     TEXT,
                created_at  TEXT NOT NULL,
                FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
            )");
        } else {
            $this->exec("CREATE TABLE IF NOT EXISTS post_revisions (
                id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                post_id     BIGINT UNSIGNED NOT NULL,
                author_id   BIGINT UNSIGNED,
                title       VARCHAR(500) NOT NULL,
                content_md  LONGTEXT,
                excerpt     TEXT,
                created_at  DATETIME NOT NULL,
                FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
            )");
        }
        $this->exec("CREATE INDEX IF NOT EXISTS idx_revisions_post ON post_revisions(post_id)");
    }

    public function down(): void
    {
        $this->exec("DROP TABLE IF EXISTS post_revisions");
        // SQLite 不支持 DROP COLUMN，跳过
    }
}
