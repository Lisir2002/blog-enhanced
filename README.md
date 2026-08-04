# Blog CMS

> 基于原生 PHP 8.2+ 构建的多作者博客内容管理系统，集成类 WordPress 的主题/插件机制与 Laravel 风格的服务容器。

[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php)](https://php.net)
[![SQLite](https://img.shields.io/badge/DB-SQLite%20%7C%20MySQL-003B57)](config/database.php)
[![License](https://img.shields.io/badge/License-MIT-yellow)](LICENSE)

---

## 目录

- [特性](#特性)
- [快速开始](#快速开始)
- [架构概览](#架构概览)
- [命令行工具](#命令行工具)
- [主题开发](#主题开发)
- [插件开发](#插件开发)
- [API 接口](#api-接口)
- [配置参考](#配置参考)
- [测试](#测试)
- [目录结构](#目录结构)
- [安全清单](#安全清单)
- [License](#license)

---

## 特性

### 核心引擎
- **纯原生 PHP** — 零全栈框架依赖，仅 4 个 Composer 运行时包
- **IoC 容器** — 支持自动装配、上下文绑定、标签绑定、装饰器模式
- **双数据库驱动** — SQLite（默认 WAL 模式）与 MySQL（STRICT + utf8mb4）无缝切换
- **不可变查询构造器** — 命名占位符防 SQL 注入，clone 克隆保证状态隔离

### 内容管理
- **Markdown 编辑** — 基于 Parsedown（安全模式）的实时预览编辑器
- **多角色权限** — 5 级角色（super_admin / senior_admin / editor_admin / editor_writer / visitor），细粒度权限 + 所有权校验
- **媒体库** — 按年/月分目录存储，支持图片/文档/视频上传，GD 自动生成缩略图
- **SEO 优化** — 自定义标题/描述、XML Sitemap、RSS 2.0 订阅、结构化面包屑导航

### 扩展系统
- **主题机制** — 父子主题支持、ZIP 上传激活、模板层级查找、Widget 区域、菜单注册
- **插件机制** — Hook 系统（`do_action` / `apply_filters` 完全兼容 WordPress API）、激活/停用生命周期
- **Shortcode** — 正则解析 `[tag attr="value"]` 格式，支持双引号/单引号/无引号属性

### 安全
- CSRF 防护（`hash_equals` 防时序攻击）
- bcrypt 密码哈希 + 登录限流（5 次失败锁定 15 分钟）
- 安全响应头（CSP / HSTS / X-Frame-Options / X-Content-Type-Options）
- 完整审计日志（7 类敏感操作记录）
- PDO 参数化查询 + Parsedown 安全模式

### 性能
- 整页静态缓存（PageCache） + 片段缓存 + 缓存锁防击穿
- 5 种缓存驱动（File / Redis / Array / Memcached / Apcu）
- 资产排队拓扑排序 + 版本指纹缓存清除
- 异步队列（sync / file / database 驱动）

---

## 快速开始

### 环境要求

- PHP >= 8.2（需 `pdo_sqlite` 或 `pdo_mysql`、`mbstring`、`json`、`fileinfo`、`tokenizer`）
- 推荐扩展：`curl`、`gd`、`intl`、`zip`、`opcache`
- Composer

### 安装步骤

```bash
# 1. 克隆项目
git clone https://github.com/Lisir2002/blog-enhanced.git
cd blog-enhanced

# 2. 配置环境变量
cp .env.example .env
# 编辑 .env 修改 APP_NAME、APP_URL 等（默认 SQLite 无需额外配置）

# 3. 安装依赖
composer install --no-dev --optimize-autoloader

# 4. 初始化数据库
php blog install
```

安装脚本自动完成：
- 创建 SQLite 数据库 `database/database.sqlite`
- 执行建表迁移（14 张表）
- 创建管理员账号 **`admin` / `admin123`**
- 写入默认站点设置
- 填充示例数据（3 分类、6 标签、5 篇示例文章）

### 启动开发服务器

```bash
# 方式一：PHP 内置服务器
php -S localhost:8080 -t public public/index.php

# 方式二：CLI 命令（推荐）
php blog serve

# 方式三：开发脚本（自动探测可用端口 + 打开浏览器）
php bin/dev
```

### 访问

| 入口 | 地址 |
|------|------|
| 前台首页 | http://localhost:8080 |
| 后台管理 | http://localhost:8080/admin |
| 管理员登录 | `admin` / `admin123` |

### 切换 MySQL

编辑 `.env`：

```ini
DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=blog
DB_USER=root
DB_PASS=your_password
```

然后执行迁移：

```bash
php blog migrate
```

---

## 架构概览

### 请求生命周期

```
public/index.php
  → 加载 Composer 自动加载 + .env
  → Application 单例（加载 7 个 helpers 文件）
  → 14 个 ServiceProvider 两阶段启动（register → boot）
  → Request::capture() 封装请求
  → Router::dispatch() 匹配路由 + 中间件链
  → Controller 执行（数据获取 + Hook 触发）
  → Response::send() 输出
```

### 分层结构

| 层 | 说明 | 核心组件 |
|-----|------|---------|
| **入口层** | Web / CLI 入口 | `public/index.php`, `blog`, `bin/dev` |
| **引导层** | 服务注册与启动 | `Application`, 14 个 `ServiceProvider` |
| **路由层** | 正则匹配 + 中间件 + 模型绑定 | `Router`, 7 个 `Middleware` |
| **控制器层** | 请求处理与响应 | 23 个控制器（Web/Admin/Api） |
| **服务层** | 业务逻辑封装 | `PostService`, `LoginRateLimiter`, `PostData DTO` |
| **模型层** | 活动记录 + 关联关系 | 7 个 Model（User/Post/Category/Tag/Comment/Media/Option） |
| **核心引擎** | 基础设施 | Container/Cache/Hook/View/Plugin/Auth/Log/Queue/SEO |

### 15 个设计模式

| 模式 | 位置 |
|------|------|
| 单例 | Application |
| IoC 容器 | Container（自动装配 + 上下文绑定 + 标签绑定） |
| 服务提供者 | 14 个 Provider（两阶段生命周期） |
| 活动记录 | Model 基类 |
| 不可变对象 | QueryBuilder（clone 克隆） |
| 责任链 | 中间件链（null 继续 / Response 短路） |
| 观察者 | Action / Filter / EventDispatcher |
| 工厂 | CacheManager / ThemeManager |
| 策略 | CacheInterface 5 种实现 |
| 装饰器 | Container::extend() |
| 数据传输对象 | PostData |

---

## 命令行工具

完整 CLI 通过 `php blog` 调用：

| 命令 | 说明 |
|------|------|
| `php blog install` | 初始化数据库 + 创建管理员 + 种子数据 |
| `php blog migrate` | 执行所有 pending 迁移 |
| `php blog migrate:rollback` | 回滚上一批迁移 |
| `php blog migrate:status` | 查看迁移状态 |
| `php blog seed` | 填充种子数据 |
| `php blog serve` | 启动 PHP 内置开发服务器 |
| `php blog make:resource Product` | 生成 Model + Controller + DTO + Migration |
| `php blog make:controller Admin/XController` | 生成控制器（支持子目录） |
| `php blog make:model Product` | 生成模型 |
| `php blog make:middleware Cors` | 生成中间件 |
| `php blog make:dto ProductData` | 生成 DTO |
| `php blog make:migration create_products_table` | 生成迁移（自动时间戳命名） |

---

## 主题开发

主题位于 `public/themes/{name}/`，支持父子主题继承。

### 主题结构

```
my-theme/
├── theme.json                # 元数据：名称、版本、菜单、侧栏、页面模板声明
├── functions.php             # 主题入口（注册 Widget/菜单/Hooks/资产）
├── templates/
│   ├── home.php              # 首页
│   ├── single.php            # 文章详情
│   ├── page.php              # 独立页面
│   ├── category.php          # 分类归档
│   ├── tag.php               # 标签归档
│   ├── author.php            # 作者归档
│   ├── search.php            # 搜索结果
│   ├── archive.php           # 通用归档（fallback）
│   ├── 404.php               # 404 未找到
│   └── error.php             # 500 服务器错误
├── partials/
│   ├── header.php            # get_header()
│   ├── footer.php            # get_footer()
│   └── sidebar.php           # get_sidebar()
└── assets/
    ├── css/style.css
    ├── js/main.js
    └── img/
```

### 模板标签

```php
// 布局结构
get_header();                    // 输出 <head> + 导航
get_sidebar();                   // 输出侧边栏
get_footer();                    // 输出页脚 + 脚本

// 条件标签
is_home();                       // 是否首页
is_single();                     // 是否文章页
is_category('tech');             // 是否分类页（支持按 slug 判定）
is_tag(); is_search(); is_404(); // 其他条件

// 文章循环
foreach ($posts as $r) :
    $post = new \App\Models\Post($r);
    the_title($post);            // 输出标题
    the_permalink($post);        // 输出链接
    the_content($post);          // 输出 HTML 内容
    the_excerpt($post, 160);     // 输出摘要
endforeach;

// 分页
paginate_links($currentPage, $totalPages, $baseUrl);

// 主题钩子
do_action('wp_head');            // </head> 前
do_action('wp_footer');          // </body> 前
apply_filters('the_content', $html);  // 过滤文章内容
```

### theme.json 配置

```json
{
  "name": "My Theme",
  "version": "1.0.0",
  "description": "主题描述",
  "author": "Your Name",
  "menus": {
    "primary": "主导航",
    "footer": "页脚导航"
  },
  "sidebars": {
    "sidebar-1": "主侧边栏",
    "footer-1": "页脚小工具区"
  },
  "page_templates": {
    "templates/full-width.php": "全宽布局"
  },
  "options": {
    "inline_css": "true"
  }
}
```

### 安装主题

1. 打包为 ZIP 文件（确保 `theme.json` 在根目录）
2. 后台 → 外观 → 主题 → 上传主题
3. 点击"激活"

---

## 插件开发

插件位于 `plugins/{name}/`，主文件头部包含元信息注释。

### 基本结构

```php
<?php
/**
 * Plugin Name: My First Plugin
 * Description: 插件功能描述
 * Version: 1.0.0
 * Author: Your Name
 * License: MIT
 */

// 安全防护：防止直接访问
if (!defined('ABSPATH') && !defined('APP_RUNNING')) {
    exit;
}

// 修改文章内容
add_filter('the_content', function ($content) {
    return $content . '<p>Powered by My Plugin</p>';
});

// 注入头部元数据
add_action('wp_head', function () {
    echo '<meta name="generator" content="My Plugin v1.0">' . "\n";
});

// 修改页脚文字
add_filter('footer_text', function ($text) {
    return $text . ' | 由 My Plugin 强力驱动';
});
```

### 激活/停用钩子

```php
register_activation_hook(__FILE__, function () {
    // 插件激活时执行（如创建自定义表）
});

register_deactivation_hook(__FILE__, function () {
    // 插件停用时执行（如清理缓存）
});
```

### 可用 Hook 参考

**Action 钩子**（19 个）：

| 钩子 | 触发时机 |
|------|----------|
| `init` | 应用启动后 |
| `wp_head` | `<head>` 区域 |
| `wp_footer` | `</body>` 前 |
| `widgets_init` | Widget 注册 |
| `template_redirect` | 模板渲染前 |
| `post_saved` | 文章保存后（含 `$id, $data, $isUpdate`） |
| `post_deleted` | 文章删除后 |
| `comment_created` | 评论创建后 |
| `user_logged_in` / `user_logged_out` | 登录/登出 |

**Filter 钩子**（8 个）：

| 钩子 | 说明 |
|------|------|
| `the_content` | 文章 HTML 输出 |
| `the_title` | 文章标题 |
| `the_excerpt` | 文章摘要 |
| `comment_text` | 评论 HTML |
| `footer_text` | 页脚文字 |
| `template_include` | 模板路径覆盖 |
| `template_output` | 模板最终 HTML |

---

## API 接口

API 路由自动应用 `api` 中间件组（CORS + 限流）。

### 文章列表

```
GET /api/posts?page=1&per_page=10&category_id=1&tag_id=2&q=关键词
```

返回：

```json
{
  "data": [
    {
      "id": 1,
      "title": "文章标题",
      "slug": "article-slug",
      "content_html": "<p>HTML 内容</p>",
      "excerpt": "摘要...",
      "status": "published",
      "views": 42,
      "published_at": "2026-01-15 10:00:00",
      "category": { "id": 1, "name": "技术", "slug": "tech" },
      "tags": [ { "id": 1, "name": "PHP", "slug": "php" } ],
      "author": { "id": 1, "name": "admin", "avatar": "/avatars/admin.jpg" }
    }
  ],
  "meta": {
    "page": 1,
    "per_page": 10,
    "total": 25,
    "total_pages": 3
  }
}
```

### 文章详情

```
GET /api/posts/{slug}
```

### 分类/标签列表

```
GET /api/taxonomies
```

---

## 配置参考

### 核心配置（`config/`）

| 文件 | 关键配置项 |
|------|-----------|
| `app.php` | `name`, `env`, `debug`, `url`, `timezone`, `locale`, `key`, `theme`, `allow_register` |
| `database.php` | `driver`（sqlite/mysql）, `sqlite.path`, `mysql.*` |
| `cache.php` | `default`（file/redis）, `drivers.*` |
| `session.php` | `driver`, `lifetime`, `cookie`, `http_only`, `same_site`, `secure` |
| `queue.php` | `default`（sync/file/database） |
| `theme.php` | `themes_path`, `assets_path`, `cache_path` |

### 环境变量（`.env`）

```ini
APP_NAME="Blog CMS"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://localhost:8080
APP_KEY=32位随机字符串

DB_DRIVER=sqlite
# 或 MySQL
# DB_DRIVER=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=blog
# DB_USERNAME=root
# DB_PASSWORD=secret

SESSION_DRIVER=file
SESSION_LIFETIME=120

CACHE_DRIVER=file
CACHE_TTL=3600
CACHE_PREFIX=blog:

MAIL_DRIVER=mail
MAIL_FROM=noreply@example.com
MAIL_FROM_NAME=Blog

QUEUE_DRIVER=sync
```

---

## 测试

### PHPUnit 测试

```bash
# 安装测试依赖
composer install

# 运行全部测试（内存 SQLite 隔离）
vendor/bin/phpunit

# 或使用 phar
php phpunit.phar
```

测试覆盖范围：路由匹配、查询构造器、权限矩阵、主题系统、核心功能集成、深度增强功能（缓存锁、Webhook、审计），共 **22 个测试**。

### 冒烟测试

```bash
# 先启动开发服务器
php blog serve &

# 运行冒烟测试
node tests/SmokeTest.js
```

---

## 目录结构

```
blog-enhanced/
├── public/                   # Web 入口（唯一对外暴露目录）
│   ├── index.php             # 前置控制器
│   ├── .htaccess             # Apache 重写 + 安全规则
│   ├── assets/admin/         # 后台 CSS/JS（响应式设计）
│   ├── avatars/              # 角色默认头像
│   └── themes/default/       # 默认主题（含 10 个模板）
├── app/                      # 应用业务代码
│   ├── Controllers/
│   │   ├── Web/              # 前台控制器（10 个）
│   │   ├── Admin/            # 后台控制器（11 个）
│   │   └── Api/              # API 控制器（2 个）
│   ├── Models/               # 数据模型（7 个）
│   ├── Services/             # 业务服务
│   ├── DTO/                  # 数据传输对象
│   ├── Events/               # 事件类
│   ├── Jobs/                 # 队列任务
│   ├── Listeners/            # 事件监听器
│   └── Support/              # 工具类（Slugify）
├── core/                     # 核心引擎（无框架依赖）
│   ├── Application.php       # 应用主类（单例 + 引导）
│   ├── Container.php         # IoC 容器（自动装配）
│   ├── Router.php            # 路由器（正则缓存 + 中间件）
│   ├── Auth/                 # 认证 + 权限矩阵
│   ├── Cache/                # 5 种驱动 + PageCache + CacheLock
│   ├── Console/              # CLI 调度器 + 代码生成器
│   ├── Database/             # Connection + QueryBuilder + Model + Migrator
│   ├── Email/                # 邮件模板系统
│   ├── Events/               # 事件调度器
│   ├── Hook/                 # Action + Filter（类 WordPress）
│   ├── Http/                 # Request / Response / Session / FormRequest
│   ├── Http/Middleware/      # 7 个中间件
│   ├── i18n/                 # 国际化翻译器
│   ├── Log/                  # 8 级日志系统
│   ├── Plugin/               # 插件管理器
│   ├── Providers/            # 14 个服务提供者
│   ├── Queue/                # 队列系统（3 驱动）
│   ├── SEO/                  # Sitemap / Robots / Breadcrumbs
│   ├── Security/             # 审计日志
│   ├── Support/              # 配置 + 7 个 helpers 文件
│   ├── View/                 # 主题/资产/Widget/菜单/Shortcode 等
│   └── Webhook/              # Webhook 系统
├── config/                   # 6 个配置文件
├── routes/                   # 3 个路由文件
├── resources/views/          # 后台视图（PHP 模板）
├── database/                 # 迁移 / 种子 / Schema
│   ├── migrations/           # 4 个迁移文件
│   ├── seeds/                # 种子数据
│   ├── schema.sqlite.sql     # SQLite 建表
│   └── schema.mysql.sql      # MySQL 建表
├── plugins/                  # 插件目录
├── storage/                  # 运行时数据
│   ├── cache/                # 文件缓存
│   ├── logs/                 # 按天滚动日志
│   ├── sessions/             # Session 文件
│   └── uploads/              # 上传文件（按年月分目录）
├── tests/                    # 测试
│   ├── TestCase.php          # 测试基类（内存 SQLite）
│   ├── Unit/                 # 8 个 PHPUnit 测试文件
│   └── SmokeTest.js          # 冒烟测试
├── composer.json             # 依赖管理（仅 4 个运行时包）
├── phpunit.xml               # PHPUnit 配置
├── .env.example              # 环境变量模板
└── SUMMARY.md                # 完整代码深度总结文档
```

---

## 安全清单

上线前请确认以下配置：

- [ ] `APP_DEBUG=false`（关闭 Whoops 错误页）
- [ ] `APP_KEY` 为随机 32 字符（`bin2hex(random_bytes(16))`）
- [ ] HTTPS 已启用（Session Secure + HSTS 生效）
- [ ] 数据库权限最小化（仅 SELECT / INSERT / UPDATE / DELETE）
- [ ] `storage/` 目录可写但不可执行
- [ ] Apache 禁用目录浏览（`Options -Indexes`）
- [ ] 主题和插件仅从可信来源上传
- [ ] 配置 `robots.txt` 禁止敏感路径（`/admin`, `/login` 等）
- [ ] 设置合理的限流阈值（`ThrottleMiddleware`）
- [ ] 定期执行 `AuditLog::cleanup(90)` 清理审计日志

---

## 深度参考

完整代码深度分析文档详见 [SUMMARY.md](SUMMARY.md)，涵盖：

- 25 个章节 + 2 个附录，共 1864 行
- 每个类的方法、属性、实现细节
- 20 种设计模式应用分析
- 17 项安全机制详解
- 19 个 Action + 8 个 Filter 扩展点注册表
- 完整的文件树索引

---

## License

[MIT](LICENSE) © 2026 Blog CMS