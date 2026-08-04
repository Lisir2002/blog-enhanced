<?php

namespace Core\View;

/**
 * Widget 系统基类 — 主题和插件可注册自定义 Widget。
 *
 * 用法：
 *   class RecentPostsWidget extends Widget {
 *       public function form(): string { ... }   // 后台表单
 *       public function render(array $instance): string { ... }  // 前台输出
 *   }
 *   register_widget(MyWidget::class);
 */
abstract class Widget
{
    public string $id;
    public string $name;
    public string $description = '';

    public function __construct(string $id, string $name)
    {
        $this->id = $id;
        $this->name = $name;
    }

    /** 后台配置表单 HTML */
    public function form(array $instance = []): string
    {
        return '';
    }

    /** 更新配置时处理输入 */
    public function update(array $input, array $old): array
    {
        return $input;
    }

    /** 前台渲染输出 */
    abstract public function render(array $instance): string;
}
