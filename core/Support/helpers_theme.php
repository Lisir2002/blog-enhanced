<?php
/**
 * 主题模板辅助函数 — WordPress 风格模板标签。
 *
 * 供主题模板文件直接调用：get_header() / the_title() / have_posts() 等。
 */

/* ─────────────── 模板结构 ─────────────── */

if (!function_exists('get_header')) {
    function get_header(string $name = ''): void
    {
        $theme = app(\Core\View\ThemeManager::class);
        echo $theme->partial('header' . ($name ? '-' . $name : ''), []);
    }
}

if (!function_exists('get_footer')) {
    function get_footer(string $name = ''): void
    {
        $theme = app(\Core\View\ThemeManager::class);
        echo $theme->partial('footer' . ($name ? '-' . $name : ''), []);
    }
}

if (!function_exists('get_sidebar')) {
    function get_sidebar(string $name = ''): void
    {
        $theme = app(\Core\View\ThemeManager::class);
        echo $theme->partial('sidebar' . ($name ? '-' . $name : ''), []);
    }
}

if (!function_exists('get_template_part')) {
    function get_template_part(string $slug, string $name = ''): void
    {
        $theme = app(\Core\View\ThemeManager::class);
        echo $theme->partial($slug . ($name ? '-' . $name : ''), []);
    }
}

/* ─────────────── Body / Post CSS class ─────────────── */

if (!function_exists('body_class')) {
    function body_class(): string
    {
        $request = app(\Core\Http\Request::class);
        $path = trim($request->path(), '/');
        $parts = array_filter(explode('/', $path));
        $classes = ['site'];
        if (empty($parts)) {
            $classes[] = 'home';
        } else {
            $classes[] = 'page-' . $parts[0];
            foreach ($parts as $p) {
                $classes[] = sanitize_html_class($p);
            }
        }
        return 'class="' . implode(' ', $classes) . '"';
    }
}

if (!function_exists('sanitize_html_class')) {
    function sanitize_html_class(string $class): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '-', $class);
    }
}

if (!function_exists('post_class')) {
    function post_class(): string
    {
        return 'class="post"';
    }
}

/* ─────────────── Post 模板标签（依赖全局 $post） ─────────────── */

if (!function_exists('the_title')) {
    function the_title(): void
    {
        global $post;
        if ($post ?? null) {
            echo e($post->getAttribute('title'));
        }
    }
}

if (!function_exists('the_content')) {
    function the_content(): void
    {
        global $post;
        if ($post ?? null) {
            echo $post->html();
        }
    }
}

if (!function_exists('the_excerpt')) {
    function the_excerpt(int $length = 200): void
    {
        global $post;
        if ($post ?? null) {
            echo e($post->excerpt($length));
        }
    }
}

if (!function_exists('the_permalink')) {
    function the_permalink(): void
    {
        global $post;
        if ($post ?? null) {
            echo $post->url();
        }
    }
}

if (!function_exists('get_the_title')) {
    function get_the_title(): string
    {
        global $post;
        return $post ? (string) $post->getAttribute('title') : '';
    }
}

if (!function_exists('get_the_permalink')) {
    function get_the_permalink(): string
    {
        global $post;
        return $post ? $post->url() : '';
    }
}

if (!function_exists('have_posts')) {
    function have_posts(): bool
    {
        global $posts;
        return !empty($posts);
    }
}

/* ─────────────── 导航菜单 ─────────────── */

if (!function_exists('wp_nav_menu')) {
    function wp_nav_menu(): void
    {
        // 菜单缓存 1 小时，新增分类时插件应清空 cache:nav_menu
        /** @var \Core\Cache\CacheInterface $cache */
        $cache = app(\Core\Cache\CacheInterface::class);
        $html = $cache->remember('nav_menu', function () {
            $cats = \App\Models\Category::all();
            $out = '<ul class="menu">';
            foreach ($cats as $c) {
                $cat = new \App\Models\Category($c);
                $out .= '<li><a href="' . $cat->url() . '">' . e($cat->getAttribute('name')) . '</a></li>';
            }
            $out .= '</ul>';
            return $out;
        }, 3600);
        echo $html;
    }
}
