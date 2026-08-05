<?php

namespace Core\Database;

/**
 * PDO 连接工厂，支持 SQLite 与 MySQL 双驱动。
 *
 * 用法：
 *   $db = app(Connection::class)->pdo();
 *   $stmt = $db->prepare("SELECT * FROM posts WHERE id = ?");
 */
class Connection
{
    private ?\PDO $pdo = null;
    private string $driver;

    public function __construct()
    {
        $this->driver = (string) config('database.driver', 'sqlite');
    }

    public function driver(): string
    {
        return $this->driver;
    }

    public function isSqlite(): bool
    {
        return $this->driver === 'sqlite' || $this->driver === 'sqlite3';
    }

    public function isMysql(): bool
    {
        return $this->driver === 'mysql';
    }

    public function pdo(): \PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }
        try {
            if ($this->driver === 'sqlite' || $this->driver === 'sqlite3') {
                $path = config('database.path', 'database.sqlite');
                // 内存数据库（测试用）：直接走 :memory: 不拼路径
                if ($path === ':memory:') {
                    $this->pdo = new \PDO('sqlite::memory:', null, null, [
                        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                        \PDO::ATTR_EMULATE_PREPARES => true,
                    ]);
                } else {
                    // If path is absolute, use as-is; otherwise resolve under database/
                    if (!str_starts_with($path, '/')) {
                        $path = database_path($path);
                    }
                    // Create parent dir if needed
                    $dir = dirname($path);
                    if (!is_dir($dir)) {
                        @mkdir($dir, 0777, true);
                    }
                    if (!file_exists($path)) {
                        @touch($path);
                        @chmod($path, 0666);
                    }
                    $this->pdo = new \PDO("sqlite:$path", null, null, [
                        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                        \PDO::ATTR_EMULATE_PREPARES => true,
                    ]);
                }
                // Foreign keys on
                $this->pdo->exec('PRAGMA foreign_keys = ON;');
                // Performance for SQLite (WAL 不支持 :memory:，会自动忽略)
                $this->pdo->exec('PRAGMA journal_mode = WAL;');
                $this->pdo->exec('PRAGMA synchronous = NORMAL;');
                $this->pdo->exec('PRAGMA busy_timeout = 5000;');
            } else {
                $host = config('database.host', '127.0.0.1');
                $port = config('database.port', 3306);
                $name = config('database.name', 'blog');
                $user = config('database.user', 'root');
                $pass = config('database.pass', '');
                $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
                $this->pdo = new \PDO($dsn, $user, $pass, [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                $this->pdo->exec("SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
            }
        } catch (\PDOException $e) {
            // 数据库连接失败 - 记录 critical 日志（不泄露密码）
            \Core\Log\Log::critical('Database connection failed', [
                'driver' => $this->driver,
                'msg'    => $e->getMessage(),
            ]);
            throw $e;
        }
        return $this->pdo;
    }

    public function tablePrefix(): string
    {
        return (string) config('database.prefix', '');
    }

    /**
     * 取当前数据库支持的自增主键语法。
     */
    public function pkSyntax(): string
    {
        if ($this->isSqlite()) {
            return 'INTEGER PRIMARY KEY AUTOINCREMENT';
        }
        return 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
    }

    public function textType(): string
    {
        if ($this->isSqlite()) {
            return 'TEXT';
        }
        return 'LONGTEXT';
    }
}
