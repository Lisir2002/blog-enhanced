<?php

namespace Core\View;

/**
 * Shortcode 系统 — 文章内容中的 [shortcode] 标记。
 *
 * 用法：
 *   add_shortcode('gallery', function ($attrs) { return '<div>...</div>'; });
 *   add_shortcode('youtube', function ($attrs) {
 *       $id = $attrs['id'] ?? '';
 *       return "<iframe src=\"https://youtube.com/embed/$id\"></iframe>";
 *   });
 *
 * 内容中：
 *   [gallery ids="1,2,3"]
 *   [youtube id="dQw4w9WgXcQ"]
 */
class Shortcode
{
    /** @var array<string, callable> */
    private array $shortcodes = [];

    public function add(string $tag, callable $callback): void
    {
        $this->shortcodes[$tag] = $callback;
    }

    public function remove(string $tag): void
    {
        unset($this->shortcodes[$tag]);
    }

    public function has(string $tag): bool
    {
        return isset($this->shortcodes[$tag]);
    }

    /**
     * 在内容中执行所有 shortcode。
     */
    public function render(string $content): string
    {
        if (empty($this->shortcodes) || !str_contains($content, '[')) {
            return $content;
        }

        // [shortcode] 或 [shortcode attr="value" attr2='value2']
        $pattern = '/\[([a-zA-Z_][\w\-]*)([^\]]*)\]/';
        return preg_replace_callback($pattern, function ($matches) {
            $tag = $matches[1];
            $attrStr = $matches[2] ?? '';

            if (!isset($this->shortcodes[$tag])) {
                return $matches[0]; // 未注册的 shortcode 原样输出
            }

            $attrs = $this->parseAttrs($attrStr);
            return ($this->shortcodes[$tag])($attrs);
        }, $content);
    }

    /**
     * 解析属性字符串：attr="value" attr='value' attr=value
     *
     * @return array<string, string>
     */
    private function parseAttrs(string $str): array
    {
        $attrs = [];
        // 匹配 key="value", key='value', key=value
        if (preg_match_all('/(\w+)="([^"]*)"/', $str, $double)) {
            for ($i = 0; $i < count($double[1]); $i++) {
                $attrs[$double[1][$i]] = $double[2][$i];
            }
        }
        if (preg_match_all("/(\w+)='([^']*)'/", $str, $single)) {
            for ($i = 0; $i < count($single[1]); $i++) {
                $attrs[$single[1][$i]] = $single[2][$i];
            }
        }
        // 无引号的 key=value
        $str2 = preg_replace('/\w+="[^"]*"/', '', $str);
        $str2 = preg_replace("/\w+='[^']*'/", '', $str2);
        if (preg_match_all('/(\w+)=(\S+)/', $str2, $bare)) {
            for ($i = 0; $i < count($bare[1]); $i++) {
                $attrs[$bare[1][$i]] = trim($bare[2][$i], '\'"');
            }
        }
        // 无值的 key（标记属性）
        $str3 = preg_replace('/\w+=\S+/', '', $str2);
        if (preg_match_all('/\b(\w+)\b/', $str3, $flags)) {
            foreach ($flags[1] as $k) {
                if (!isset($attrs[$k])) {
                    $attrs[$k] = true;
                }
            }
        }
        return $attrs;
    }
}
