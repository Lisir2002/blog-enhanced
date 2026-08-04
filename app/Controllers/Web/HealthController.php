<?php

namespace App\Controllers\Web;

use Core\Database\Connection;
use Core\Http\Response;
use Core\Cache\CacheInterface;

/**
 * 健康检查控制器 - 供负载均衡器/监控系统调用。
 *
 * 路由：
 *   /health       存活检查（Liveness）- 进程是否在运行
 *   /health/ready 就绪检查（Readiness）- 是否可接收流量
 */
class HealthController
{
    /**
     * 存活检查 - 简单返回 200，表示进程存活。
     */
    public function liveness(): Response
    {
        return (new Response())
            ->setContentType('application/json')
            ->setBody(json_encode(['status' => 'alive', 'timestamp' => time()]));
    }

    /**
     * 就绪检查 - 检查依赖服务（数据库、缓存、存储）是否可用。
     */
    public function readiness(): Response
    {
        $checks = [
            'database' => fn() => $this->checkDatabase(),
            'cache'    => fn() => $this->checkCache(),
            'storage'  => fn() => $this->checkStorage(),
        ];

        $results = [];
        $allHealthy = true;

        foreach ($checks as $name => $check) {
            try {
                $results[$name] = $check();
                if ($results[$name] !== true) {
                    $allHealthy = false;
                }
            } catch (\Throwable $e) {
                $results[$name] = 'error: ' . $e->getMessage();
                $allHealthy = false;
            }
        }

        return (new Response())
            ->setContentType('application/json')
            ->setStatus($allHealthy ? 200 : 503)
            ->setBody(json_encode([
                'status' => $allHealthy ? 'ready' : 'not_ready',
                'checks' => $results,
                'timestamp' => time(),
            ], JSON_PRETTY_PRINT));
    }

    private function checkDatabase(): bool
    {
        try {
            $pdo = app(Connection::class)->pdo();
            $pdo->query('SELECT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkCache(): bool
    {
        try {
            $cache = app(CacheInterface::class);
            $cache->set('health_check', 'ok', 10);
            return $cache->get('health_check') === 'ok';
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkStorage(): bool
    {
        $path = storage_path();
        return is_dir($path) && is_writable($path);
    }
}
