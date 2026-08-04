<?php
/**
 * 主题模板辅助函数 — 完整模板标签 API。
 *
 * 涵盖：模板结构 / 条件标签 / 资产 / Widget / 菜单 / 分页 /
 *       SEO / 内容助手 / 安全转义 / Admin Bar / Shortcode
 */

use Core\Http\Request;
use Core\Http\Response;
use Core\View\Conditional;
use Core\View\AssetManager;
use Core\View\WidgetManager;
use Core\View\MenuManager;
use Core\View\Shortcode;

/* ═══════════════ 模板结构 ═══════════════ */

if (!function_exists('get_header')) {
    function get_header(string $name = ''): void
    {
        echo app(\Core\View\ThemeManager::class)->partial('header' . ($name ? '-' . $name : ''), []);
    }
}

if (!function_exists('get_footer')) {
    function get_footer(string $name = ''): void
    {
        echo app(\Core\View\ThemeManager::class)->partial('footer' . ($name ? '-' . $name : ''), []);
    }
}

if (!function_exists('get_sidebar')) {
    function get_sidebar(string $name = ''): void
    {
        echo app(\Core\View\ThemeManager::class)->partial('sidebar' . ($name ? '-' . $name : ''), []);
    }
}

if (!function_exists('get_template_part')) {
    function get_template_part(string $slug, string|array $nameOrData = '', ?array $data = null): void
    {
        $name = '';
        if (is_string($nameOrData)) {
            $name = $nameOrData;
        } elseif (is_array($nameOrData) && $data === null) {
            $data = $nameOrData;
        }
        echo app(\Core\View\ThemeManager::class)->partial($slug . ($name ? '-' . $name : ''), $data ?? []);
    }
}

/* ═══════════════ 条件标签 ═══════════════ */

if (!function_exists('is_home')) {
    function is_home(): bool { return Conditional::isHome(); }
}
if (!function_exists('is_front_page')) {
    function is_front_page(): bool { return Conditional::isFrontPage(); }
}
if (!function_exists('is_single')) {
    function is_single(?string $slug = null): bool { return Conditional::isSingle($slug); }
}
if (!function_exists('is_page')) {
    function is_page(?string $slug = null): bool { return Conditional::isPage($slug); }
}
if (!function_exists('is_category')) {
    function is_category(?string $slug = null): bool { return Conditional::isCategory($slug); }
}
if (!function_exists('is_tag')) {
    function is_tag(?string $slug = null): bool { return Conditional::isTag($slug); }
}
if (!function_exists('is_search')) {
    function is_search(): bool { return Conditional::isSearch(); }
}
if (!function_exists('is_404')) {
    function is_404(): bool { return Conditional::is404(); }
}
if (!function_exists('is_author')) {
    function is_author(): bool { return Conditional::isAuthor(); }
}
if (!function_exists('is_archive')) {
    function is_archive(): bool { return Conditional::isArchive(); }
}

/* ═══════════════ 主题资产/路径 ═══════════════ */

if (!function_exists('theme_asset')) {
    function theme_asset(string $path = ''): string
    {
        return app(\Core\View\ThemeManager::class)->asset($path);
    }
}

if (!function_exists('theme_path')) {
    function theme_path(string $path = ''): string
    {
        return app(\Core\View\ThemeManager::class)->path($path);
    }
}

if (!function_exists('theme_config')) {
    function theme_config(?string $key = null, mixed $default = null): mixed
    {
        /** @var \Core\View\ThemeManager $tm */
        $tm = app(\Core\View\ThemeManager::class);
        $themeMeta = $tm->config() ?: [];
        $themeOptions = $themeMeta['options'] ?? [];

        if ($key === null) {
            // 合并优先级：DB 中 ThemeManager->config() 返回的 meta options
            // → config/theme.php 全局配置 → 函数默认
            $global = function_exists('config') ? (array) config('theme', []) : [];
            $flat = [];
            foreach ($themeOptions as $k => $v) {
                if (is_array($v) && array_key_exists('default', $v)) {
                    $flat[$k] = $v['default'];
                } else {
                    $flat[$k] = $v;
                }
            }
            return array_replace($global, $flat, []);
        }

        // 先从 ThemeManager::config($key) 查（走 DB theme_{theme}_{key} + theme.json options.default）
        $val = $tm->config($key, "__NOT_FOUND__");
        if ($val !== "__NOT_FOUND__") {
            return $val;
        }
        // 再查全局 config/theme.php
        if (function_exists('config')) {
            $gVal = config('theme.' . $key, "__NOT_FOUND__");
            if ($gVal !== "__NOT_FOUND__") {
                return $gVal;
            }
        }
        // 最后 theme_json.options
        if (isset($themeOptions[$key]['default'])) {
            return $themeOptions[$key]['default'];
        }
        if (array_key_exists($key, $themeOptions)) {
            return $themeOptions[$key];
        }
        return $default;
    }
}

/* ═══════════════ 资产排队 ═══════════════ */

if (!function_exists('enqueue_style')) {
    function enqueue_style(string $id, string $src, array $deps = [], string $ver = ''): void
    {
        app(AssetManager::class)->enqueueStyle($id, $src, $deps, $ver);
    }
}

if (!function_exists('enqueue_script')) {
    function enqueue_script(string $id, string $src, array $deps = [], string $ver = '', bool $footer = false): void
    {
        app(AssetManager::class)->enqueueScript($id, $src, $deps, $ver, $footer);
    }
}

/* ═══════════════ Widget 系统 ═══════════════ */

if (!function_exists('register_sidebar')) {
    function register_sidebar(array $config): void
    {
        app(WidgetManager::class)->registerSidebar($config);
    }
}

if (!function_exists('register_widget')) {
    function register_widget(string $class): void
    {
        app(WidgetManager::class)->registerWidget($class);
    }
}

if (!function_exists('dynamic_sidebar')) {
    function dynamic_sidebar(string $id): string
    {
        return app(WidgetManager::class)->renderSidebar($id);
    }
}

/* ═══════════════ 菜单系统 ═══════════════ */

if (!function_exists('register_nav_menu')) {
    function register_nav_menu(string $location, string $description): void
    {
        app(MenuManager::class)->registerLocation($location, $description);
    }
}

if (!function_exists('wp_nav_menu')) {
    function wp_nav_menu(array $args = []): string
    {
        return app(MenuManager::class)->render($args);
    }
}

/* ═══════════════ 分页 ═══════════════ */

if (!function_exists('paginate_links')) {
    function paginate_links(array $args = []): string
    {
        $total = $args['total'] ?? 1;
        $current = $args['current'] ?? 1;
        $base = $args['base'] ?? '?page=%#%';
        $prevText = $args['prev_text'] ?? '«';
        $nextText = $args['next_text'] ?? '»';
        $midSize = $args['mid_size'] ?? 1;

        if ($total <= 1) {
            return '';
        }

        $html = '<nav class="pagination" aria-label="分页">';
        if ($current > 1) {
            $url = str_replace('%#%', (string)($current - 1), $base);
            $html .= '<a href="' . $url . '" class="prev">' . $prevText . '</a>';
        }
        $start = max(1, $current - $midSize);
        $end = min($total, $current + $midSize);
        for ($i = $start; $i <= $end; $i++) {
            if ($i == $current) {
                $html .= '<span class="current">' . $i . '</span>';
            } else {
                $url = str_replace('%#%', (string)$i, $base);
                $html .= '<a href="' . $url . '">' . $i . '</a>';
            }
        }
        if ($current < $total) {
            $url = str_replace('%#%', (string)($current + 1), $base);
            $html .= '<a href="' . $url . '" class="next">' . $nextText . '</a>';
        }
        return $html . '</nav>';
    }
}

/* ═══════════════ body_class ═══════════════ */

if (!function_exists('body_class')) {
    function body_class(string $extra = ''): string
    {
        $classes = [];
        if (is_home()) { $classes[] = 'home'; $classes[] = 'blog'; }
        if (is_single()) { $classes[] = 'single'; $classes[] = 'single-post'; }
        if (is_page()) { $classes[] = 'page'; }
        if (is_category()) { $classes[] = 'archive'; $classes[] = 'category'; }
        if (is_tag()) { $classes[] = 'archive'; $classes[] = 'tag'; }
        if (is_search()) { $classes[] = 'search'; }
        if (is_404()) { $classes[] = 'error404'; }
        if (is_author()) { $classes[] = 'archive'; $classes[] = 'author'; }
        if (logged_in()) { $classes[] = 'logged-in'; }

        $sidebar = theme_config('layout', '');
        if ($sidebar) { $classes[] = $sidebar; }

        if ($extra !== '') { $classes[] = $extra; }

        return implode(' ', $classes);
    }
}

if (!function_exists('sanitize_html_class')) {
    function sanitize_html_class(string $class): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $class) ?? $class;
    }
}

if (!function_exists('post_class')) {
    function post_class(string $extra = ''): string
    {
        $classes = ['post'];
        $post = $GLOBALS['post'] ?? null;
        if ($post) {
            $classes[] = 'post-' . ($post->getAttribute('id') ?? '');
            $status = $post->getAttribute('status') ?? '';
            if ($status) { $classes[] = 'status-' . $status; }
            $cat = $post->getAttribute('category_id');
            if ($cat) { $classes[] = 'category-' . $cat; }
        }
        if ($extra) { $classes[] = $extra; }
        return implode(' ', $classes);
    }
}

/* ═══════════════ 模板标签 ═══════════════ */

if (!function_exists('the_title')) {
    function the_title(): void
    {
        global $post;
        echo e($post?->getAttribute('title') ?? '');
    }
}

if (!function_exists('get_the_title')) {
    function get_the_title(): string
    {
        global $post;
        return (string) ($post?->getAttribute('title') ?? '');
    }
}

if (!function_exists('the_content')) {
    function the_content(): void
    {
        global $post;
        echo $post?->html() ?? '';
    }
}

if (!function_exists('the_excerpt')) {
    function the_excerpt(int $length = 200): void
    {
        global $post;
        echo e($post?->excerpt($length) ?? '');
    }
}

if (!function_exists('the_permalink')) {
    function the_permalink(): void
    {
        global $post;
        echo $post?->url() ?? '';
    }
}

if (!function_exists('get_the_permalink')) {
    function get_the_permalink(): string
    {
        global $post;
        return $post?->url() ?? '';
    }
}

if (!function_exists('have_posts')) {
    function have_posts(): bool
    {
        global $posts;
        return !empty($posts);
    }
}

/* ═══════════════ Shortcode ═══════════════ */

if (!function_exists('add_shortcode')) {
    function add_shortcode(string $tag, callable $callback): void
    {
        app(Shortcode::class)->add($tag, $callback);
    }
}

if (!function_exists('do_shortcode')) {
    function do_shortcode(string $content): string
    {
        return app(Shortcode::class)->render($content);
    }
}

/* ═══════════════ SEO ═══════════════ */

if (!function_exists('wp_head')) {
    function wp_head(): void
    {
        $stylesHtml = app(AssetManager::class)->renderStyles();

        // [复发防护] CSS 内联：Web 预览容器可能无法加载外部 stylesheet，
        // 开启时把所有 <link rel="stylesheet"> 转成 <style data-theme>。
        if (theme_config('inline_css', false)) {
            $stylesHtml = preg_replace_callback(
                '/<link\s+rel="stylesheet"\s+href="([^"]+)"\s*>/i',
                function (array $m): string {
                    $src = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
                    $path = resolveAssetLocalPath($src);
                    if ($path === null || !is_file($path)) {
                        return $m[0];
                    }
                    $content = (string) file_get_contents($path);
                    if ($content === '') {
                        return $m[0];
                    }
                    // Web preview 容器下可能把相对路径的 url() 解析到错误位置 — 这里统一把
                    // CSS url() 内相对路径重写成 /themes/xxx/yyy 绝对路径形式。
                    $cssDir = dirname($path);
                    $content = rewriteCssAssetPaths($content, $cssDir);
                    $escapedSrc = e($src);
                    return sprintf(
                        "<style type=\"text/css\" data-theme=\"%s\">\n/* %s (inlined) */\n%s\n</style>\n",
                        $escapedSrc,
                        basename($path),
                        $content
                    );
                },
                $stylesHtml
            );
        }

        echo $stylesHtml;

        // 2. Run wp_head hook (theme/plugin meta tags, analytics, etc.)
        do_action('wp_head');
    }
}

/**
 * 把 URL (或路径) 解析为本地文件绝对路径。
 *   - /themes/default/foo.css → {resources}/themes/default/foo.css
 *   - /css/bar.css            → {public}/css/bar.css
 *   - http(s)://xxx/path.css  → 非本站资源返回 null（不内联）
 */
if (!function_exists('resolveAssetLocalPath')) {
    function resolveAssetLocalPath(string $src): ?string
    {
        if (preg_match('#^https?://#i', $src)) {
            $appUrl = rtrim((string) config('app.url', ''), '/');
            if ($appUrl !== '' && stripos($src, $appUrl . '/') === 0) {
                $src = substr($src, strlen($appUrl));
            } else {
                return null; // 第三方资源不内联
            }
        }
        $src = parse_url($src, PHP_URL_PATH) ?? $src;
        // 去尾 & 参数
        $src = preg_replace('/\?.*$/', '', (string) $src);

        $baseName = basename((string) $src);

        // 1) 主题资源
        if (preg_match('#^/?themes/([^/]+)/(.+)$#', (string) $src, $m)) {
            $candidate = rtrim(themes_path(), '/') . '/' . $m[1] . '/' . $m[2];
            if (is_file($candidate)) {
                return $candidate;
            }
        }
        // 2) public 目录资源
        $candidate = rtrim(public_path(), '/') . '/' . ltrim((string) $src, '/');
        if (is_file($candidate)) {
            return $candidate;
        }
        return null;
    }
}

/**
 * 将 CSS 中相对路径的 url() 重写为基于主题目录的完整路径。
 *   asset.relative 为 true 时用于资源路径重写。
 */
if (!function_exists('rewriteCssAssetPaths')) {
    function rewriteCssAssetPaths(string $css, string $cssDir): string
    {
        return (string) preg_replace_callback(
            '/url\((["\']?)([^"\')\s]+)(\1)\)/i',
            function (array $m) use ($cssDir): string {
                $url = $m[2];
                // 跳过绝对/远程/data URI/锚点
                if ($url === '' || str_starts_with($url, 'data:') || str_starts_with($url, '#') ||
                    preg_match('#^(https?:)?//#i', $url) || str_starts_with($url, '/')) {
                    return $m[0];
                }
                $abs = $cssDir . '/' . $url;
                // 简化为 /themes/... 相对根
                $abs = realpath($abs) ?: $abs;
                $themeRoot = themes_path();
                if (is_string($abs) && stripos((string) $abs, rtrim($themeRoot, '/')) === 0) {
                    $url = '/themes' . str_replace('\\', '/', substr((string) $abs, strlen(rtrim($themeRoot, '/'))));
                }
                return "url({$m[1]}{$url}{$m[3]})";
            },
            $css
        );
    }
}

if (!function_exists('wp_footer')) {
    function wp_footer(): void
    {
        // 1. Output enqueued footer scripts
        echo app(AssetManager::class)->renderScripts();

        // 2. Run wp_footer hook
        do_action('wp_footer');

        // 3. Admin bar for logged-in users
        if (logged_in() && !is_admin_route()) {
            echo render_admin_bar();
        }
    }
}

/* ═══════════════ 内容助手 ═══════════════ */

if (!function_exists('reading_time')) {
    function reading_time(int $wordCount = 0): string
    {
        $minutes = max(1, (int) ceil($wordCount / 300));
        return $minutes . ' 分钟阅读';
    }
}

if (!function_exists('word_count')) {
    function word_count(string $text): int
    {
        $text = strip_tags($text);
        if (preg_match('/[\x{4e00}-\x{9fa5}]/u', $text)) {
            // Chinese — count characters
            return mb_strlen(preg_replace('/[^\p{L}\p{N}]/u', '', $text));
        }
        // Latin — count words
        return str_word_count($text);
    }
}

if (!function_exists('table_of_contents')) {
    function table_of_contents(string $html): string
    {
        if (!preg_match_all('/<h([23])[^>]*>(.*?)<\/h\1>/i', $html, $matches, PREG_SET_ORDER)) {
            return '';
        }
        $toc = '<nav class="toc"><h3>目录</h3><ul>';
        foreach ($matches as $m) {
            $level = (int) $m[1];
            $title = trim(strip_tags($m[2]));
            $id = 'toc-' . sanitize_html_class(strtolower(preg_replace('/\s+/', '-', $title)));
            $html = preg_replace(
                '/<h' . $level . '([^>]*)>' . preg_quote($m[2], '/') . '<\/h' . $level . '>/i',
                '<h' . $level . '$1 id="' . $id . '">' . $m[2] . '</h' . $level . '>',
                $html,
                1
            );
            $indent = $level === 3 ? ' style="padding-left:12px"' : '';
            $toc .= '<li' . $indent . '><a href="#' . $id . '">' . e($title) . '</a></li>';
        }
        $toc .= '</ul></nav>';
        $GLOBALS['toc_filtered_html'] = $html;
        return $toc;
    }
}

/* ═══════════════ 安全转义 ═══════════════ */

if (!function_exists('esc_html')) {
    function esc_html(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    function esc_url(string $url): string
    {
        $url = str_replace([' ', '"', "'", '<', '>', "\t", "\n"], '', $url);
        if (preg_match('#^(https?:|/|mailto:|tel:)#i', $url)) {
            return e($url);
        }
        return '';
    }
}

/* ═══════════════ Admin Bar ═══════════════ */

if (!function_exists('render_admin_bar')) {
    function render_admin_bar(): string
    {
        $user = current_user();
        if (!$user) {
            return '';
        }
        $items = [
            '<a href="' . url('admin') . '">后台</a>',
            '<a href="' . url('admin/posts/create') . '">写文章</a>',
            '<a href="' . url('admin/media') . '">媒体</a>',
            '<a href="' . url('logout') . '">退出</a>',
        ];
        return '<div class="admin-bar" style="position:fixed;top:0;left:0;right:0;z-index:99999;'
            . 'background:#1e293b;color:#fff;padding:0 16px;display:flex;align-items:center;'
            . 'height:32px;font-size:13px;gap:12px">'
            . '<strong style="margin-right:auto">' . e($user->displayName()) . '</strong>'
            . implode('', $items)
            . '</div>'
            . '<style>body{padding-top:32px !important}</style>';
    }
}
