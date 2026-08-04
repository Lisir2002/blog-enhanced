<?php

namespace Core\Log;

/**
 * 简易日志器 - 按 level 分文件，按天滚动。
 *
 * 用法：
 *   Log::info('user login', ['uid' => 1]);
 *   Log::error('db failed', ['exception' => $e]);
 *
 * 日志文件：storage/logs/{Y-m-d}.log
 */
class Log
{
    public const DEBUG = 'DEBUG';
    public const INFO = 'INFO';
    public const NOTICE = 'NOTICE';
    public const WARNING = 'WARNING';
    public const ERROR = 'ERROR';
    public const CRITICAL = 'CRITICAL';
    public const ALERT = 'ALERT';
    public const EMERGENCY = 'EMERGENCY';

    /**
     * 取日志文件路径。
     */
    private static function file(): string
    {
        $dir = storage_path('logs');
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir . '/' . date('Y-m-d') . '.log';
    }

    /**
     * 写一条日志。
     */
    public static function write(string $level, string $message, array $context = []): void
    {
        // 生产环境默认丢弃 DEBUG，避免日志爆炸
        if ($level === self::DEBUG && !config('app.debug', false)) {
            return;
        }

        $structured = (bool) config('log.structured', false);

        if ($structured) {
            // 结构化日志（JSON 格式）
            $entry = [
                'timestamp' => date('c'),
                'level' => $level,
                'message' => $message,
                'context' => $context,
            ];
            // 追加请求 ID（如果存在）
            if (isset($_SERVER['X_REQUEST_ID'])) {
                $entry['request_id'] = $_SERVER['X_REQUEST_ID'];
            }
            $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        } else {
            // 传统文本格式
            $line = sprintf(
                "[%s] [%s] %s: %s%s\n",
                date('Y-m-d H:i:s.v'),
                strtoupper(basename($_SERVER['SCRIPT_NAME'] ?? 'cli')),
                $level,
                $message,
                $context ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''
            );
        }

        @file_put_contents(self::file(), $line, FILE_APPEND | LOCK_EX);
    }

    public static function debug(string $message, array $context = []): void
    {
        self::write(self::DEBUG, $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write(self::INFO, $message, $context);
    }

    public static function notice(string $message, array $context = []): void
    {
        self::write(self::NOTICE, $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write(self::WARNING, $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write(self::ERROR, $message, $context);
    }

    public static function critical(string $message, array $context = []): void
    {
        self::write(self::CRITICAL, $message, $context);
    }

    public static function alert(string $message, array $context = []): void
    {
        self::write(self::ALERT, $message, $context);
    }

    public static function emergency(string $message, array $context = []): void
    {
        self::write(self::EMERGENCY, $message, $context);
    }
}
