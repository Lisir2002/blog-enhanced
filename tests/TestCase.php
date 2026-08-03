<?php

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Core\Application;
use Core\Container;
use Core\Support\Config;

/**
 * 测试基类 - 为每个测试方法提供干净的应用容器和 SQLite 内存数据库。
 */
abstract class TestCase extends BaseTestCase
{
    protected Application $app;

    protected function setUp(): void
    {
        parent::setUp();

        // 强制测试环境变量（在 Config 加载前设置，但 .env 会覆盖）
        $_ENV['APP_ENV']   = 'testing';
        $_ENV['APP_DEBUG'] = 'true';
        $_ENV['DB_DRIVER'] = 'sqlite';
        $_ENV['DB_PATH']   = ':memory:';
        $_ENV['APP_URL']   = 'http://localhost';
        putenv('APP_ENV=testing');
        putenv('APP_DEBUG=true');
        putenv('DB_DRIVER=sqlite');
        putenv('DB_PATH=:memory:');
        putenv('APP_URL=http://localhost');

        // 构造最小应用实例，不启动路由 / 插件
        $this->app = new Application();
        $this->app->instance(Application::class, $this->app);
        // 注入 Config 实例（会加载 .env，但我们会强制覆盖关键值）
        $config = new Config();
        // .env 已把 DB_PATH 覆盖回 database.sqlite —— 必须在 Config 加载后强制再覆盖
        $config->set('database.path', ':memory:');
        $config->set('database.driver', 'sqlite');
        $config->set('app.debug', true);
        $config->set('app.url', 'http://localhost');
        $this->app->instance(Config::class, $config);

        $this->registerCoreServices();
        $this->initializeDatabase();
    }

    protected function registerCoreServices(): void
    {
        $this->app->singleton(\Core\Database\Connection::class);
        $this->app->singleton(\Core\Database\QueryBuilder::class);
        $this->app->singleton(\Core\Http\Session::class);
        $this->app->singleton(\Core\Cache\FileCache::class);
        $this->app->singleton(\Core\Cache\CacheInterface::class, fn (Container $c) => $c->get(\Core\Cache\FileCache::class));
        $this->app->singleton(\Parsedown::class, fn () => (new \Parsedown())->setSafeMode(true));
    }

    /**
     * 初始化内存 SQLite，执行 schema。
     */
    protected function initializeDatabase(): void
    {
        $conn = $this->app->get(\Core\Database\Connection::class);
        $pdo = $conn->pdo();
        $schema = file_get_contents(__DIR__ . '/../database/schema.sqlite.sql');
        $pdo->exec($schema);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Container::resetForTesting();
    }
}
