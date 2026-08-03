# Hook 参考表

## Action（动作钩子）

Action 用于在特定位置插入逻辑，无返回值。

### 注册方式

```php
add_action('hook_name', function ($arg1, $arg2) {
    // 逻辑
}, $priority = 10);
```

### 核心 Action 列表

| Hook 名 | 参数 | 触发时机 |
|---------|------|---------|
| `init` | 无 | 应用启动后、路由分发前 |
| `wp_head` | 无 | 前台 `<head>` 区域 |
| `wp_footer` | 无 | 前台 `</body>` 前 |
| `post_header` | `$post` | 文章详情页头部 |
| `post_footer` | `$post` | 文章详情页尾部 |
| `post_saved` | `$id, $data, $isUpdate` | 文章保存/更新后 |
| `user_logged_in` | `$user` | 用户登录成功后 |
| `user_logged_out` | `$user` | 用户登出后 |
| `comment_posted` | `$post, $content` | 评论提交后 |
| `theme_switched` | `$themeName` | 主题切换后 |
| `plugin_activated` | `$pluginName` | 插件激活后 |
| `plugin_deactivated` | `$pluginName` | 插件停用后 |

### 使用示例

```php
// 在 <head> 添加 SEO meta
add_action('wp_head', function () {
    $desc = \App\Models\Option::get('site_description', '');
    echo "<meta name='description' content='" . e($desc) . "'>\n";
});

// 文章保存后生成缓存
add_action('post_saved', function ($id, $data, $isUpdate) {
    \Core\Log\Log::info('Post saved', ['id' => $id, 'is_update' => $isUpdate]);
});
```

---

## Filter（过滤钩子）

Filter 用于修改值，必须有返回值。

### 注册方式

```php
add_filter('hook_name', function ($value, $arg1) {
    return $modifiedValue;
}, $priority = 10);
```

### 核心 Filter 列表

| Hook 名 | 参数 | 默认值 | 说明 |
|---------|------|--------|------|
| `the_content` | `$html, $post` | Parsedown 转换后的 HTML | 过滤文章内容输出 |
| `the_title` | `$title, $post` | 文章标题 | 过滤标题显示 |
| `the_excerpt` | `$excerpt, $post` | 摘要文本 | 过滤摘要输出 |
| `nav_menu_items` | `$items` | 分类列表 | 修改导航菜单项 |

### 使用示例

```php
// 文章内容自动添加版权声明
add_filter('the_content', function ($html, $post) {
    $copyright = '<p class="copyright">© ' . date('Y') . ' 本站</p>';
    return $html . $copyright;
});

// 标题添加前缀
add_filter('the_title', function ($title, $post) {
    if ($post && $post->getAttribute('is_pinned')) {
        return '📌 ' . $title;
    }
    return $title;
});
```

---

## 优先级

- 数字越小越先执行（默认 10）
- 同一优先级按注册顺序执行
- Filter 链中，前一个的返回值作为后一个的输入

```php
// 优先级 5：在默认 filter 之前执行
add_filter('the_content', 'my_filter_early', 5);

// 优先级 20：在默认 filter 之后执行
add_filter('the_content', 'my_filter_late', 20);
```
