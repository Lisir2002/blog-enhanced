# 插件开发指南

## 插件结构

```
my-plugin/
├── plugin.json          # 插件元信息（必需）
├── my-plugin.php        # 入口文件（必需，文件名需与目录名一致）
├── assets/
│   ├── css/
│   └── js/
└── includes/            # 可选
    └── Helper.php
```

## plugin.json

```json
{
    "name": "我的插件",
    "description": "一个示例插件",
    "version": "1.0.0",
    "author": "你的名字",
    "entry": "my-plugin.php",
    "min_version": "1.0.0"
}
```

- `entry`：入口文件名（可选，默认为 `{目录名}.php`）

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

## 激活/停用回调

```php
// 激活时执行（创建表、初始化配置等）
register_activation_hook(function () {
    \Core\Log\Log::info('My plugin activated');
});

// 停用时执行（清理数据等）
register_deactivation_hook(function () {
    \Core\Log\Log::info('My plugin deactivated');
});
```

## 打包上传

```bash
# 1. 打包为 zip
cd plugins/
zip -r my-plugin.zip my-plugin/

# 2. 后台 → 插件管理 → 上传 zip

# 3. 激活
```

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
