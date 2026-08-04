<?php
/**
 * Theme default options (theme 级配置，非 DB options)。
 *
 * 使用方式：
 *   theme_config('single_sidebar_mode')  → 'toc'
 *   theme_config('inline_css')           → true  (web preview 容器默认开启)
 *   theme_config('asset.relative')       → false (CSS 内联时也应设 false)
 */
return [
    // [复发防护 1] CSS 内联开关 — Web 预览容器下建议开启，避免外部样式表加载失败
    'inline_css'            => env('THEME_INLINE_CSS', true),

    // [复发防护 2] 资源是否使用相对路径（部署到子路径时可切换）
    'asset.relative'        => env('THEME_ASSET_RELATIVE', false),

    // [复发防护 3] 详情页侧边栏模式: toc | sidebar | both
    'single_sidebar_mode'   => env('THEME_SINGLE_SIDEBAR_MODE', 'toc'),

    // 是否启用首页封面占位（没封面时显示 SVG 渐变）
    'home_cover_placeholder' => env('THEME_HOME_COVER_PLACEHOLDER', false),

    // 每页文章数（覆盖 app.per_page）
    'per_page'              => env('THEME_PER_PAGE', 10),

    // 相关文章最大显示数
    'related_max'           => env('THEME_RELATED_MAX', 6),
];
