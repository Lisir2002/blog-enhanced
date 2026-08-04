<?php

namespace Core\View;

/**
 * Widget 区域管理器 — 注册侧边栏 + 渲染 Widget。
 *
 * 用法（functions.php）：
 *   register_sidebar([
 *       'id'            => 'sidebar-1',
 *       'name'          => '主侧边栏',
 *       'description'   => '文章页右侧',
 *       'before_widget' => '<section class="widget">',
 *       'after_widget'  => '</section>',
 *   ]);
 *
 * 模板内：
 *   dynamic_sidebar('sidebar-1');
 */
class WidgetManager
{
    /** @var array<string, array> */
    private array $sidebars = [];

    /** @var array<string, Widget> */
    private array $widgets = [];

    /** @var array<string, array> widget instances from DB */
    private ?array $instances = null;

    public function registerSidebar(array $config): void
    {
        $id = $config['id'] ?? '';
        if ($id === '') {
            return;
        }
        $this->sidebars[$id] = array_merge([
            'id'            => $id,
            'name'          => $id,
            'description'   => '',
            'before_widget' => '<section class="widget %s">',
            'after_widget'  => '</section>',
            'before_title'  => '<h3 class="widget-title">',
            'after_title'   => '</h3>',
        ], $config);
    }

    public function registerWidget(string $class): void
    {
        if (!class_exists($class)) {
            return;
        }
        $widget = new $class();
        if ($widget instanceof Widget) {
            $this->widgets[$widget->id] = $widget;
        }
    }

    public function getSidebars(): array
    {
        return $this->sidebars;
    }

    public function hasSidebar(string $id): bool
    {
        return isset($this->sidebars[$id]);
    }

    /**
     * 渲染指定 Widget 区域。
     */
    public function renderSidebar(string $sidebarId): string
    {
        return $this->render($sidebarId);
    }

    public function render(string $sidebarId): string
    {
        if (!$this->hasSidebar($sidebarId)) {
            return '';
        }

        $sidebar = $this->sidebars[$sidebarId];
        $instances = $this->loadInstances($sidebarId);

        if (empty($instances)) {
            // 无 Widget → 触发钩子让插件可兜底
            ob_start();
            do_action('dynamic_sidebar_fallback', $sidebarId);
            return ob_get_clean();
        }

        $output = '';
        foreach ($instances as $instance) {
            $widgetId = $instance['widget_id'] ?? '';
            $widget = $this->widgets[$widgetId] ?? null;
            if (!$widget) {
                continue;
            }

            $before = sprintf($sidebar['before_widget'] ?? '<section class="widget">', $widgetId);
            $after = $sidebar['after_widget'] ?? '</section>';

            $title = $instance['title'] ?? '';
            $titleHtml = $title
                ? ($sidebar['before_title'] ?? '<h3 class="widget-title">') . e($title) . ($sidebar['after_title'] ?? '</h3>')
                : '';

            $content = $widget->render($instance);

            $output .= $before . $titleHtml . $content . $after . "\n";
        }

        return $output;
    }

    /**
     * 从 DB 加载 Widget 实例。
     */
    private function loadInstances(string $sidebarId): array
    {
        if ($this->instances === null) {
            try {
                $data = \App\Models\Option::get('widget_instances', []);
                $this->instances = is_array($data) ? $data : [];
            } catch (\Throwable) {
                $this->instances = [];
            }
        }

        return array_filter(
            $this->instances,
            fn($w) => ($w['sidebar'] ?? '') === $sidebarId
        );
    }

    /**
     * 保存 Widget 实例到 DB。
     */
    public function saveInstances(array $instances): void
    {
        \App\Models\Option::set('widget_instances', $instances);
        $this->instances = $instances;
    }
}
