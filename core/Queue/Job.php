<?php

namespace Core\Queue;

/**
 * Job 基类 - 队列任务。
 *
 * 子类实现 handle() 方法执行任务逻辑。
 */
abstract class Job
{
    protected array $args;

    public function __construct(array $args = [])
    {
        $this->args = $args;
    }

    /**
     * 执行任务。
     */
    abstract public function handle(): void;

    /**
     * 获取参数。
     */
    public function getArgs(): array
    {
        return $this->args;
    }
}
