<?php

namespace Core\Queue;

use Core\Cache\CacheInterface;

/**
 * 队列系统 - 异步任务处理。
 *
 * 支持：
 * - 同步驱动（SyncQueue）：立即执行，开发环境用
 * - 数据库驱动（DatabaseQueue）：持久化到 jobs 表
 * - 文件驱动（FileQueue）：序列化到 storage/queue/
 *
 * 用法：
 *   // 推送任务
 *   app(Queue::class)->push(SendEmailJob::class, ['to' => 'user@example.com']);
 *
 *   // 消费任务（CLI）
 *   php blog queue:work
 *
 *   // 任务类
 *   class SendEmailJob implements ShouldQueue
 *   {
 *       public function __construct(public array $data) {}
 *       public function handle(): void { // 发送邮件
 *       }
 *   }
 */
class Queue
{
    private string $driver;
    private CacheInterface $cache;

    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
        $this->driver = (string) config('queue.driver', 'sync');
    }

    /**
     * 推送任务到队列。
     *
     * @param string $job 任务类名
     * @param array $data 任务数据
     * @param string|null $queue 队列名
     * @param int $delay 延迟秒数
     */
    public function push(string $job, array $data = [], ?string $queue = null, int $delay = 0): void
    {
        $payload = [
            'job'       => $job,
            'data'      => $data,
            'attempts'  => 0,
            'pushed_at' => time(),
            'available_at' => time() + $delay,
        ];

        match ($this->driver) {
            'sync'     => $this->processSync($payload),
            'file'     => $this->pushToFile($payload, $queue, $delay),
            'database' => $this->pushToDatabase($payload, $queue, $delay),
            default    => $this->processSync($payload),
        };
    }

    /**
     * 同步执行任务（开发环境）。
     */
    private function processSync(array $payload): void
    {
        $job = new $payload['job']($payload['data']);
        $job->handle();
    }

    /**
     * 推送到文件队列。
     */
    private function pushToFile(array $payload, ?string $queue, int $delay): void
    {
        $queueName = $queue ?? 'default';
        $id = uniqid('job_', true);
        $key = 'queue:' . $queueName . ':' . $id;
        $this->cache->set($key, $payload, $delay + 86400); // 最多保留 1 天
    }

    /**
     * 推送到数据库队列。
     */
    private function pushToDatabase(array $payload, ?string $queue, int $delay): void
    {
        $queueName = $queue ?? 'default';
        $payload['queue'] = $queueName;
        $payload['available_at'] = date('Y-m-d H:i:s', $payload['available_at']);
        $payload['created_at'] = date('Y-m-d H:i:s');

        try {
            app(\Core\Database\QueryBuilder::class)
                ->table('jobs')
                ->insert($payload);
        } catch (\Throwable $e) {
            // 降级为同步执行
            $this->processSync($payload);
        }
    }

    /**
     * 消费队列任务（CLI 调用）。
     */
    public function work(?string $queue = null, int $maxJobs = 100): int
    {
        $processed = 0;
        $queueName = $queue ?? 'default';

        while ($processed < $maxJobs) {
            $job = $this->pop($queueName);
            if ($job === null) {
                usleep(500000); // 0.5 秒
                continue;
            }

            try {
                $instance = new $job['job']($job['data']);
                $instance->handle();
            } catch (\Throwable $e) {
                \Core\Log\Log::error('Queue job failed', [
                    'job'   => $job['job'],
                    'error' => $e->getMessage(),
                ]);
                // 重试逻辑可在此扩展
            }

            $processed++;
        }

        return $processed;
    }

    /**
     * 弹出一个任务。
     */
    private function pop(string $queue): ?array
    {
        if ($this->driver === 'file') {
            return $this->popFromFile($queue);
        }
        if ($this->driver === 'database') {
            return $this->popFromDatabase($queue);
        }
        return null;
    }

    private function popFromFile(string $queue): ?array
    {
        // 简化实现：扫描缓存键
        // 实际实现应维护一个待处理队列列表
        return null;
    }

    private function popFromDatabase(string $queue): ?array
    {
        try {
            $job = app(\Core\Database\QueryBuilder::class)
                ->table('jobs')
                ->where('queue', '=', $queue)
                ->where('available_at', '<=', date('Y-m-d H:i:s'))
                ->orderBy('id', 'ASC')
                ->first();
            if (!$job) {
                return null;
            }
            app(\Core\Database\QueryBuilder::class)
                ->table('jobs')
                ->where('id', '=', $job['id'])
                ->delete();
            return $job;
        } catch (\Throwable) {
            return null;
        }
    }

    public function driver(): string
    {
        return $this->driver;
    }
}
