<?php

namespace Database\Migrations;

use Core\Database\Migration;

class CreateMenuItemsTable extends Migration
{
    public function up(): void
    {
        if ($this->isSqlite()) {
            $this->exec("CREATE TABLE IF NOT EXISTS menu_items (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                location    TEXT NOT NULL DEFAULT 'primary',
                title       TEXT NOT NULL,
                url         TEXT NOT NULL,
                target      TEXT NOT NULL DEFAULT '_self',
                order_index INTEGER NOT NULL DEFAULT 0,
                parent_id   INTEGER NOT NULL DEFAULT 0,
                created_at  TEXT NOT NULL,
                updated_at  TEXT NOT NULL
            )");
        } else {
            $this->exec("CREATE TABLE IF NOT EXISTS menu_items (
                id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                location    VARCHAR(64) NOT NULL DEFAULT 'primary',
                title       VARCHAR(255) NOT NULL,
                url         VARCHAR(500) NOT NULL,
                target      VARCHAR(16) NOT NULL DEFAULT '_self',
                order_index INT NOT NULL DEFAULT 0,
                parent_id   BIGINT UNSIGNED NOT NULL DEFAULT 0,
                created_at  DATETIME NOT NULL,
                updated_at  DATETIME NOT NULL
            )");
        }
        $this->exec("CREATE INDEX IF NOT EXISTS idx_menu_items_location ON menu_items(location)");
    }

    public function down(): void
    {
        $this->exec("DROP TABLE IF EXISTS menu_items");
    }
}
