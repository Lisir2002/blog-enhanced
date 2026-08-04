<?php

return [
    // 队列驱动：sync（同步）/ file / database
    'driver' => env('QUEUE_DRIVER', 'sync'),

    // 默认队列名
    'default_queue' => 'default',

    // 任务最大重试次数
    'max_attempts' => 3,

    // 任务超时（秒）
    'timeout' => 60,

    // 重试延迟（秒）
    'retry_delay' => 5,
];
