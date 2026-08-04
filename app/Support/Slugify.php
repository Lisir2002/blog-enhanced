<?php

namespace App\Support;

/**
 * Slug 生成器 — 从标题生成 URL-safe slug。
 *
 * 规则：
 *   - 中文标题 → 随机 hex（无拼音库时的安全降级）
 *   - 其他文字 → 音译 + 去特殊字符 + 小写 + 连字符分隔
 *   - 空结果 → {prefix}-{random}
 */
class Slugify
{
    public static function make(string $text, string $prefix = 'post'): string
    {
        $text = trim($text);
        if ($text === '') {
            return $prefix . '-' . bin2hex(random_bytes(3));
        }

        // 纯中文 → 随机 hex（避免 URL 乱码）
        if (preg_match('/^[\x{4e00}-\x{9fa5}]+/u', $text)) {
            return bin2hex(random_bytes(4));
        }

        $text = preg_replace('~[^\pL\d]+~u', '-', $text) ?? $prefix;

        // 音译为 ASCII（如果 iconv 可用）
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if ($converted !== false && $converted !== '') {
                $text = $converted;
            }
        }

        $text = preg_replace('/[^a-z0-9\-]/i', '', $text) ?? $text;
        $text = strtolower($text);
        $text = trim($text, '-');

        return $text !== '' ? $text : $prefix . '-' . bin2hex(random_bytes(3));
    }

    /**
     * 确保 slug 在指定表唯一——冲突时追加 -2, -3…
     */
    public static function unique(string $slug, string $table, string $column = 'slug', ?int $exceptId = null): string
    {
        $base = $slug;
        $i = 1;
        while (true) {
            $qb = app(\Core\Database\QueryBuilder::class)
                ->table($table)
                ->where($column, '=', $slug);
            if ($exceptId) {
                $qb = $qb->where('id', '!=', $exceptId);
            }
            if (!$qb->first()) {
                break;
            }
            $slug = $base . '-' . (++$i);
        }
        return $slug;
    }
}
