<?php

namespace Core\Database;

use PDO;
use RuntimeException;

/**
 * 迁移引擎 — 管理迁移文件的发现、执行、回滚和状态查询。
 *
 * 工作原理：
 *   1. migrations 表记录已执行的迁移
 *   2. 扫描 database/migrations/ 目录获取可用迁移
 *   3. 对比得出 pending 列表，按文件名排序执行
 *   4. 每次执行记录 batch 号（同一次 run 共享一个 batch）
 *
 * 用法：
 *   $migrator = new Migrator($connection);
 *   $ran = $migrator->run();           // 执行 pending
 *   $rolled = $migrator->rollback();   // 回滚最后一批
 *   $status = $migrator->status();     // 状态列表
 */
class Migrator
{
    private string $migrationDir;

    public function __construct(
        private Connection $connection,
    ) {
        $this->migrationDir = database_path('migrations');
    }

    /**
     * 执行所有 pending 迁移，返回已执行文件名列表。
     *
     * @return array<int, string>
     */
    public function run(): array
    {
        $this->ensureTable();
        $pending = $this->getPending();

        if (empty($pending)) {
            return [];
        }

        $batch = $this->nextBatch();
        $now = date('Y-m-d H:i:s');
        $executed = [];

        foreach ($pending as $file) {
            $migration = $this->loadMigration($file);
            $migration->up();
            $this->record($file, $batch, $now);
            $executed[] = $file;
        }

        return $executed;
    }

    /**
     * 回滚最后一批迁移（或指定步数）。
     *
     * @return array<int, string>
     */
    public function rollback(int $steps = 0): array
    {
        $this->ensureTable();

        if ($steps > 0) {
            // 回滚最近 N 个迁移
            $stmt = $this->pdo()->prepare(
                'SELECT migration FROM migrations ORDER BY id DESC LIMIT ?'
            );
            $stmt->execute([$steps]);
        } else {
            // 回滚最后一批
            $batch = $this->lastBatch();
            if ($batch === 0) {
                return [];
            }
            $stmt = $this->pdo()->prepare(
                'SELECT migration FROM migrations WHERE batch = ? ORDER BY id DESC'
            );
            $stmt->execute([$batch]);
        }

        $files = array_column($stmt->fetchAll(), 'migration');
        $rolled = [];

        foreach ($files as $file) {
            $migration = $this->loadMigration($file);
            $migration->down();
            $this->deleteRecord($file);
            $rolled[] = $file;
        }

        return $rolled;
    }

    /**
     * 获取所有迁移的状态。
     *
     * @return array<int, array{migration: string, ran: bool, batch: ?int, executed_at: ?string}>
     */
    public function status(): array
    {
        $this->ensureTable();

        $available = $this->getAvailable();
        $ran = $this->getRanMap();

        $status = [];
        foreach ($available as $file) {
            $record = $ran[$file] ?? null;
            $status[] = [
                'migration'   => $file,
                'ran'         => $record !== null,
                'batch'       => $record['batch'] ?? null,
                'executed_at' => $record['executed_at'] ?? null,
            ];
        }
        return $status;
    }

    // ─────────── 内部方法 ───────────

    private function pdo(): PDO
    {
        return $this->connection->pdo();
    }

    private function ensureTable(): void
    {
        $driver = $this->connection->driver();

        if ($this->connection->isSqlite()) {
            $this->pdo()->exec(
                "CREATE TABLE IF NOT EXISTS migrations (
                    id          INTEGER PRIMARY KEY AUTOINCREMENT,
                    migration   TEXT NOT NULL UNIQUE,
                    batch       INTEGER NOT NULL,
                    executed_at TEXT NOT NULL
                )"
            );
        } else {
            $this->pdo()->exec(
                "CREATE TABLE IF NOT EXISTS migrations (
                    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    migration   VARCHAR(255) NOT NULL UNIQUE,
                    batch       INT NOT NULL,
                    executed_at DATETIME NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        }
    }

    /**
     * @return array<int, string> 已排序的可用迁移文件名
     */
    private function getAvailable(): array
    {
        if (!is_dir($this->migrationDir)) {
            return [];
        }
        $files = glob($this->migrationDir . '/*.php');
        if ($files === false) {
            return [];
        }
        sort($files);
        return array_map('basename', $files);
    }

    /**
     * @return array<string, array{batch: int, executed_at: string}> migration => record
     */
    private function getRanMap(): array
    {
        $stmt = $this->pdo()->query('SELECT migration, batch, executed_at FROM migrations ORDER BY id ASC');
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[$row['migration']] = [
                'batch'       => (int) $row['batch'],
                'executed_at' => $row['executed_at'],
            ];
        }
        return $map;
    }

    /**
     * @return array<int, string> pending 迁移文件名
     */
    private function getPending(): array
    {
        $available = $this->getAvailable();
        $ran = array_keys($this->getRanMap());
        return array_values(array_diff($available, $ran));
    }

    private function lastBatch(): int
    {
        $stmt = $this->pdo()->query('SELECT MAX(batch) as max FROM migrations');
        $row = $stmt->fetch();
        return (int) ($row['max'] ?? 0);
    }

    private function nextBatch(): int
    {
        return $this->lastBatch() + 1;
    }

    private function loadMigration(string $file): Migration
    {
        $path = $this->migrationDir . '/' . $file;
        if (!is_file($path)) {
            throw new RuntimeException("Migration file not found: $file");
        }

        // 从文件名推导类名：
        // 20260101_000001_create_users_table.php → Database\Migrations\CreateUsersTable
        $base = basename($file, '.php');
        $parts = explode('_', $base);
        // 前两段是时间戳，跳过
        $nameParts = array_slice($parts, 2);
        $className = str_replace(' ', '', ucwords(implode(' ', $nameParts)));
        $fqcn = 'Database\\Migrations\\' . $className;

        if (!class_exists($fqcn)) {
            require_once $path;
        }

        if (!class_exists($fqcn)) {
            throw new RuntimeException("Migration class [$fqcn] not found in $file");
        }

        return new $fqcn($this->pdo(), $this->connection);
    }

    private function record(string $file, int $batch, string $now): void
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO migrations (migration, batch, executed_at) VALUES (?, ?, ?)'
        );
        $stmt->execute([$file, $batch, $now]);
    }

    private function deleteRecord(string $file): void
    {
        $stmt = $this->pdo()->prepare('DELETE FROM migrations WHERE migration = ?');
        $stmt->execute([$file]);
    }
}
