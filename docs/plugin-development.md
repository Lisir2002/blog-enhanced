# 插件开发指南

## 插件结构

```
my-plugin/
├── plugin.json          # 插件元信息（推荐，与 PHP 头注释二选一或组合使用）
├── my-plugin.php        # 入口文件（必需，文件名需与目录名一致）
├── assets/
│   ├── css/
│   └── js/
└── includes/            # 可选
    └── Helper.php
```

## plugin.json（推荐）

```json
{
    "name": "我的插件",
    "description": "一个示例插件",
    "version": "1.0.0",
    "author": "你的名字",
    "author_uri": "https://example.com",
    "plugin_uri": "https://example.com/plugin",
    "license": "MIT",
    "entry": "my-plugin.php",
    "min_version": "2.0.0",
    "php_version": ">=8.0",
    "tags": ["SEO", "性能优化"],
    "requires": {
        "extensions": ["zip", "gd"]
    },
    "depends_on": ["other-plugin"]
}
```

### 字段说明

| 字段 | 类型 | 必需 | 说明 |
|------|------|------|------|
| `name` | string | ✓ | 插件名称 |
| `description` | string | ✓ | 插件描述 |
| `version` | string | ✓ | 插件版本（语义化版本） |
| `author` | string | — | 作者名称 |
| `author_uri` | string | — | 作者主页 |
| `plugin_uri` | string | — | 插件主页 |
| `license` | string | — | 许可证类型 |
| `entry` | string | — | 入口文件名（默认 `{目录名}.php`） |
| `min_version` | string | — | 要求的核心 CMS 最低版本 |
| `php_version` | string | — | 要求的 PHP 最低版本（如 `>=8.0`） |
| `tags` | array | — | 插件分类标签 |
| `requires.extensions` | array | — | 要求的 PHP 扩展列表 |
| `depends_on` | array | — | 依赖的其他插件列表 |

> **注意**：plugin.json 与 PHP 头注释可以组合使用，JSON 优先，PHP 头注释补充缺失字段。

## PHP 头注释方式

如果不使用 plugin.json，也可以在入口文件头部添加注释：

```php
<?php
/**
 * Plugin Name: 我的插件
 * Description: 在文章底部添加版权声明
 * Version: 1.0.0
 * Author: 你的名字
 * Author URI: https://example.com
 * Plugin URI: https://example.com/plugin
 * License: MIT
 * Requires PHP: 8.0
 */
```

## 入口文件示例

```php
<?php
/**
 * Plugin Name: 我的插件
 * Description: 在文章底部添加版权声明
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 文章内容底部添加版权
add_filter('the_content', function ($html, $post) {
    $copyright = '<p class="copyright">© ' . date('Y') . ' ' . e(\App\Models\Option::get('site_name', '')) . '</p>';
    return $html . $copyright;
}, 20);

// 在 <head> 添加自定义 CSS
add_action('wp_head', function () {
    echo "<style>.copyright{color:#999;font-size:0.9em;text-align:center;}</style>\n";
});

// 文章保存时记录日志
add_action('post_saved', function ($id, $data, $isUpdate) {
    \Core\Log\Log::info('插件记录：文章已保存', [
        'id' => $id,
        'is_update' => $isUpdate,
    ]);
});
```

## 激活/停用/卸载回调

```php
// 激活时执行（创建表、初始化配置等）
register_activation_hook(function () {
    \Core\Log\Log::info('My plugin activated');
    // 可以在此创建数据库表、设置默认配置等
    \App\Models\Option::set('my_plugin_settings', [
        'enabled' => true,
        'option1' => 'value1',
    ]);
});

// 停用时执行（清理临时数据、保留配置等）
register_deactivation_hook(function () {
    \Core\Log\Log::info('My plugin deactivated');
    // 可以在此清理缓存、临时数据，但保留配置
    app(\Core\Cache\CacheInterface::class)->delete('my_plugin_cache');
});

// 卸载时执行（清理所有数据）
register_uninstall_hook(function () {
    \Core\Log\Log::info('My plugin uninstalled');
    // 可以在此删除所有插件相关的配置、数据表等
    \App\Models\Option::delete_by_key('my_plugin_settings');
});
```

> **重要**：`register_activation_hook`、`register_deactivation_hook`、`register_uninstall_hook` 必须在插件入口文件的顶层调用，不能在函数或类方法内部调用。

## 兼容性检查

插件激活时会自动检查以下兼容性：

1. **PHP 版本**：根据 `min_version` 或 `Requires PHP` 字段
2. **核心版本**：根据 `min_version` 字段
3. **PHP 扩展**：根据 `requires.extensions` 字段
4. **依赖插件**：根据 `depends_on` 字段检查依赖插件是否已激活

如果兼容性检查失败，插件将无法激活，并会显示具体的错误信息。

## 打包上传

```bash
# 1. 打包为 zip
cd plugins/
zip -r my-plugin.zip my-plugin/

# 2. 后台 → 插件管理 → 上传 zip

# 3. 激活
```

## 安全注意事项

1. **ZIP 路径穿透防护**：上传的 ZIP 文件会自动检查是否包含路径穿越攻击（如 `../../../etc/passwd`），不安全的 ZIP 将被拒绝
2. **错误隔离**：插件加载出错不会导致整个系统崩溃，错误会被记录到日志中
3. **输入校验**：插件应自行对用户输入进行校验和清理

## API 参考

### 核心 Helper

| 函数 | 说明 |
|------|------|
| `app($abstract = null)` | 获取容器实例或解析依赖 |
| `config($key, $default = null)` | 读取配置项 |
| `url($path = '')` | 生成完整 URL |
| `route($name, $params = [])` | 用路由名生成 URL |
| `e($string)` | HTML 转义 |
| `view($template, $data = [])` | 渲染视图 |
| `current_user()` | 当前登录用户（未登录返回 null） |
| `can($capability, $args = null)` | 权限检查 |

### Hook 系统

| 函数 | 说明 |
|------|------|
| `add_action($name, $callback, $priority)` | 注册 Action 钩子 |
| `do_action($name, ...$args)` | 执行 Action 钩子 |
| `has_action($name)` | 检查是否有 Action |
| `remove_action($name, $callback)` | 移除 Action 钩子 |
| `add_filter($name, $callback, $priority)` | 注册 Filter 钩子 |
| `apply_filters($name, $value, ...$args)` | 应用 Filter 钩子 |
| `remove_filter($name, $callback)` | 移除 Filter 钩子 |
| `register_activation_hook($callback)` | 注册激活回调 |
| `register_deactivation_hook($callback)` | 注册停用回调 |
| `register_uninstall_hook($callback)` | 注册卸载回调 |

### 模型查询

```php
// 查询单条
$post = \App\Models\Post::find($id);
$post = \App\Models\Post::findBy('slug', 'hello-world');

// 查询多条
$posts = \App\Models\Post::query()
    ->where('status', '=', 'published')
    ->orderBy('published_at', 'DESC')
    ->limit(10)
    ->get();

// 计数
$count = \App\Models\Post::query()->where('status', '=', 'published')->count();

// 插入
$id = \App\Models\Post::query()->insert([
    'title' => 'Hello',
    'slug' => 'hello',
    'author_id' => 1,
    'status' => 'draft',
    'created_at' => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s'),
]);

// 更新
$affected = \App\Models\Post::query()
    ->where('id', '=', $id)
    ->update(['title' => 'New Title']);
```

### 日志

```php
\Core\Log\Log::info('message', ['context' => 'data']);
\Core\Log\Log::warning('message');
\Core\Log\Log::error('message', ['exception' => $e]);
```

### 缓存

```php
// 获取缓存
$value = app(\Core\Cache\CacheInterface::class)->get('key', $default);

// 设置缓存
app(\Core\Cache\CacheInterface::class)->set('key', $value, 3600);

// 记住（缓存不存在则执行回调并缓存）
$data = app(\Core\Cache\CacheInterface::class)->remember('key', function () {
    return expensiveQuery();
}, 3600);

// 删除
app(\Core\Cache\CacheInterface::class)->delete('key');
```

## 注意事项

1. **入口文件不要输出任何内容**：插件文件在 `include` 阶段执行，不要用 `echo` / `print`
2. **使用 Hook 挂载逻辑**：所有输出通过 `add_action` / `add_filter` 注册
3. **SQL 必须参数化**：用 QueryBuilder 或 PDO prepare，不要拼 SQL
4. **输出必须转义**：用户数据输出前用 `e()` 转义
5. **不要直接修改核心文件**：所有自定义通过 Hook 实现
6. **激活回调必须在顶层**：`register_*_hook()` 不能在函数或方法内部调用
7. **处理错误异常**：回调中的异常不会导致系统崩溃，但建议自行 try-catch