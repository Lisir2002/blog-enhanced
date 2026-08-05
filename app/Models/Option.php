<?php

namespace App\Models;

use Core\Database\Model;

/**
 * 站点设置 - key/value 表
 */
class Option extends Model
{
    protected static string $table = 'options';

    /** @var array<string, mixed> */
    private static array $cache = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }
        $row = static::query()->where('key_name', '=', $key)->first();
        if (!$row) {
            return $default;
        }
        $value = $row['value'];
        // Auto-detect JSON
        if (is_string($value) && strlen($value) > 0 && $value[0] === '{') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            }
        }
        return self::$cache[$key] = $value;
    }

    public static function set(string $key, mixed $value): void
    {
        $stored = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value;
        $qb = static::query();
        $exists = $qb->where('key_name', '=', $key)->first();
        $now = date('Y-m-d H:i:s');
        if ($exists) {
            static::query()->where('key_name', '=', $key)->update([
                'value' => $stored,
                'updated_at' => $now,
            ]);
        } else {
            static::query()->insert([
                'key_name' => $key,
                'value' => $stored,
                'autoload' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        self::$cache[$key] = $value;
    }

    public static function remove(string $key): void
    {
        static::query()->where('key_name', '=', $key)->delete();
        unset(self::$cache[$key]);
    }
}
