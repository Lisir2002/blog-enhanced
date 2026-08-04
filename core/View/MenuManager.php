<?php

namespace Core\View;

/**
 * 菜单管理器 — 注册菜单位置 + 渲染自定义菜单。
 *
 * 用法（functions.php）：
 *   register_nav_menu('primary', '主导航');
 *
 * 模板内：
 *   wp_nav_menu(['theme_location' => 'primary']);
 */
class MenuManager
{
    /** @var array<string, string> location => description */
    private array $locations = [];

    /** @var array<int, array> menu items from DB */
    private ?array $items = null;

    public function registerLocation(string $location, string $description): void
    {
        $this->locations[$location] = $description;
    }

    public function getLocations(): array
    {
        return $this->locations;
    }

    public function hasLocation(string $location): bool
    {
        return isset($this->locations[$location]);
    }

    /**
     * 渲染菜单 HTML。
     *
     * @param array{theme_location?: string, menu_class?: string, container?: string, fallback?: callable} $args
     */
    public function render(array $args = []): string
    {
        $location = $args['theme_location'] ?? '';
        $menuClass = $args['menu_class'] ?? 'menu';
        $container = $args['container'] ?? 'nav';

        $items = $this->loadItems($location);

        // 无菜单项 → 触发 fallback（默认输出分类列表）
        if (empty($items)) {
            if (isset($args['fallback']) && is_callable($args['fallback'])) {
                return ($args['fallback'])();
            }
            // 默认 fallback: 输出分类列表
            return $this->renderCategoryFallback($menuClass, $container);
        }

        $currentPath = '/' . ltrim($_SERVER['REQUEST_URI'] ?? '/', '/');
        $currentPath = parse_url($currentPath, PHP_URL_PATH) ?: '/';

        $html = "<{$container} class=\"{$menuClass}\">\n";
        $html .= $this->renderItems($items, 0, $currentPath);
        $html .= "</{$container}>\n";

        return $html;
    }

    /**
     * 递归渲染菜单项。
     */
    private function renderItems(array $items, int $parentId, string $currentPath): string
    {
        $children = array_filter($items, fn($i) => (int)($i['parent_id'] ?? 0) === $parentId);
        if (empty($children)) {
            return '';
        }

        usort($children, fn($a, $b) => (int)($a['order_index'] ?? 0) <=> (int)($b['order_index'] ?? 0));

        $html = "<ul>\n";
        foreach ($children as $item) {
            $url = $item['url'] ?? '#';
            $title = $item['title'] ?? '';
            $target = $item['target'] ?? '_self';
            $active = $this->isCurrentUrl($url, $currentPath);

            $cls = $active ? ' class="menu-item active"' : ' class="menu-item"';
            $targetAttr = $target === '_blank' ? ' target="_blank" rel="noopener"' : '';

            $html .= "  <li{$cls}><a href=\"{$url}\"{$targetAttr}>" . e($title) . "</a>";

            // 递归子菜单
            $subMenu = $this->renderItems($items, (int)$item['id'], $currentPath);
            if ($subMenu) {
                $html .= "\n  <ul class=\"sub-menu\">\n{$subMenu}  </ul>\n";
            }

            $html .= "  </li>\n";
        }
        $html .= "</ul>\n";
        return $html;
    }

    private function isCurrentUrl(string $url, string $currentPath): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        if ($path === '' || $path === '/') {
            return $currentPath === '/';
        }
        return $currentPath === $path || str_starts_with($currentPath, $path . '/');
    }

    /**
     * 默认 fallback: 输出分类列表。
     */
    private function renderCategoryFallback(string $menuClass, string $container): string
    {
        $html = "<{$container} class=\"{$menuClass}\">\n<ul>\n";
        $cats = \App\Models\Category::all();
        foreach ($cats as $c) {
            $cat = $c instanceof \App\Models\Category ? $c : new \App\Models\Category($c);
            // Defensive: route may not be defined in test environments
            try {
                $url = $cat->url();
            } catch (\Throwable) {
                $url = url('/category/' . $cat->getAttribute('slug'));
            }
            $html .= '  <li class="menu-item"><a href="' . $url . '">'
                . e($cat->getAttribute('name')) . "</a></li>\n";
        }
        $html .= "</ul>\n</{$container}>\n";
        return $html;
    }

    /**
     * 从 DB 加载菜单项。
     */
    private function loadItems(string $location): array
    {
        if ($this->items !== null) {
            return array_filter($this->items, fn($i) => ($i['location'] ?? '') === $location);
        }

        try {
            $rows = app(\Core\Database\QueryBuilder::class)
                ->table('menu_items')
                ->where('location', '=', $location)
                ->orderBy('order_index', 'ASC')
                ->get();
            $this->items = $rows;
            return $rows;
        } catch (\Throwable) {
            $this->items = [];
            return [];
        }
    }
}
