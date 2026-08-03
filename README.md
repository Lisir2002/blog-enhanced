# Blog CMS

基于原生 PHP + Composer 的多作者博客内容管理系统，带有类 WordPress 的主题/插件机制。

## 特性

- **纯原生 PHP**（无框架），最低 PHP 8.2
- **双数据库驱动**：SQLite（默认）+ MySQL（可切换）
- **类 WordPress 主题机制**：上传 zip、激活、切换、自定义 functions.php
- **类 WordPress 插件机制**：Hook（`do_action` / `apply_filters`）、激活/停用
- **多用户多角色**：admin / editor / author / contributor / subscriber
- **Markdown 编辑**（Parsedown）+ 实时预览
- **媒体库**（按年/月分目录）
- **SEO 友好 URL**、RSS 订阅、Sitemap
- **高性能 SSR** + 现代 CSS、零前端框架依赖
- **安全**：CSRF 防护、bcrypt 密码哈希、PDO 参数化查询、登录限流、Parsedown 安全模式
- **日志系统**：按天分文件、8 级日志、异常自动记录
- **测试**：PHPUnit 10 + 内存 SQLite 隔离、22 个核心测试

## 快速开始

### 1. 环境要求

- PHP >= 8.2
- 扩展：PDO + pdo_sqlite（或 pdo_mysql）、mbstring、json、ctype、xml、simplexml、dom、fileinfo、tokenizer
- 推荐：curl、gd、intl、zip、opcache
- Composer

### 2. 安装

```bash
cd blog
cp .env.example .env
composer install --no-dev --optimize-autoloader
php blog install
```

安装脚本会：
- 创建 SQLite 数据库（database/database.sqlite）
- 执行建表 SQL
- 创建管理员账号：`admin` / `admin123`（非交互模式下）
- 写入默认站点设置
- 创建示例分类和欢迎文章

### 3. 启动开发服务器

```bash
php -S localhost:8080 -t public public/index.php
```

或用 CLI 命令：

```bash
php blog serve
```

### 4. 访问

- **前台**：http://localhost:8080
- **后台**：http://localhost:8080/admin
- **管理员**：`admin` / `admin123`

### 5. 切换到 MySQL

编辑 `.env`：

```ini
DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_NAME=blog
DB_USER=root
DB_PASS=your_password
```

然后执行迁移：

```bash
php blog migrate
```

## 主题开发

主题位于 `resources/themes/{name}/`，结构：

```
my-theme/
├── theme.json          # 主题元数据
├── functions.php       # 主题入口，注册 hooks
├── templates/          # 模板层级
│   ├── home.php        # 首页
│   ├── single.php      # 文章详情
│   ├── page.php        # 独立页面
│   ├── category.php    # 分类归档
│   ├── tag.php         # 标签归档
│   ├── author.php      # 作者归档
│   ├── search.php      # 搜索结果
│   ├── archive.php      # 通用归档（fallback）
│   ├── 404.php         # 404 页
│   └── error.php       # 500 错误页
├── partials/           # 局部模板
│   ├── header.php      # get_header()
│   ├── footer.php      # get_footer()
│   └── sidebar.php     # get_sidebar()
└── assets/             # 静态资源
    ├── css/
    ├── js/
    └── img/
```

打包 zip 上传到后台 → 外观 → 主题 → 上传，自动解压激活。

### 主题模板标签

```php
// 输出 header / footer / sidebar
get_header();
get_footer();
get_sidebar();

// 站点信息
$siteName = \App\Models\Option::get('site_name', config('app.name'));

// 文章列表循环
foreach ($posts as $r) {
    $post = new \App\Models\Post($r);
    echo $post->getAttribute('title');
    echo $post->url();
    echo $post->html();        // Markdown → HTML
    echo $post->excerpt(160);  // 摘要
}

// Hook（可被插件扩展）
do_action('wp_head');           // <head> 区域
do_action('wp_footer');          // 页脚
do_action('post_header');        // 文章头部
apply_filters('the_content', $html);  // 过滤内容
```

## 插件开发

插件位于 `plugins/{name}/`，主文件头部：

```php
<?php
/**
 * Plugin Name: My First Plugin
 * Description: 一个示例插件
 * Version: 1.0.0
 * Author: You
 */

// 注册 filter - 修改文章内容
add_filter('the_content', function ($content) {
    return $content . "<p>Powered by my plugin.</p>";
});

// 注册 action - 在 head 输出
add_action('wp_head', function () {
    echo "<meta name='my-plugin' content='active'>\n";
});
```

在后台 → 插件 → 激活后生效。

## CLI 命令

```bash
php blog install      # 初始化数据库 + 创建管理员
php blog migrate      # 执行数据库迁移
php blog seed         # 填充种子数据
php blog serve        # 启动开发服务器
```

## 目录结构

```
blog/
├── public/               # Web 入口（唯一对外目录）
│   ├── index.php         # 前置控制器
│   ├── .htaccess         # Apache 重写
│   └── assets/           # 后台 CSS/JS
├── app/
│   ├── Controllers/
│   │   ├── Web/          # 前台控制器（9 个）
│   │   └── Admin/        # 后台控制器（11 个）
│   ├── Models/           # 数据模型（7 个）
│   └── Services/         # LoginRateLimiter 等业务服务
├── core/                  # 核心引擎
│   ├── Application.php   # 应用主类
│   ├── Container.php     # IoC 容器
│   ├── Router.php        # 路由器（正则缓存）
│   ├── Http/             # Request / Response / Session
│   ├── Database/         # PDO + QueryBuilder（不可变）+ Model
│   ├── Hook/             # Action + Filter（类 WP）
│   ├── View/             # ThemeManager + ViewRenderer
│   ├── Plugin/           # PluginManager
│   ├── Auth/             # AuthManager + Capability
│   ├── Cache/            # FileCache + CacheInterface
│   ├── Log/              # Log（8 级日志）
│   ├── Console/          # CLI
│   └── Support/          # Config + helpers.php
├── resources/
│   ├── views/            # 后台视图
│   └── themes/default/  # 默认主题
├── plugins/              # 插件目录
├── storage/              # 运行时存储
│   ├── cache/
│   ├── logs/
│   ├── sessions/
│   └── uploads/
├── database/             # SQL + 迁移 + 种子
├── config/               # 配置文件
├── routes/               # 路由表
├── composer.json
├── .env.example
└── README.md
```

## 测试

```bash
# 用 phar 运行（无需 composer install phpunit）
php phpunit.phar

# 或用 composer
composer install
vendor/bin/phpunit
```

测试使用内存 SQLite（`:memory:`），每个测试方法自动隔离。详见 [docs/architecture.md](docs/architecture.md)。

## License

MIT
