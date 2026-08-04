# 项目完整总结文档

> 生成日期：2026-08-04
> 项目名称：blog-enhanced（Native PHP 多作者博客 CMS）
> 代码总量：150+ 个文件，约 15,000+ 行代码

---

## 目录

1. [项目概述](#1-项目概述)
2. [架构总览](#2-架构总览)
3. [核心框架（core/）](#3-核心框架core)
4. [应用层（app/）](#4-应用层app)
5. [路由系统](#5-路由系统)
6. [数据库设计](#6-数据库设计)
7. [视图与模板](#7-视图与模板)
8. [前端主题系统](#8-前端主题系统)
9. [后台管理系统](#9-后台管理系统)
10. [配置系统](#10-配置系统)
11. [测试体系](#11-测试体系)
12. [开发工作流](#12-开发工作流)
13. [设计模式清单](#13-设计模式清单)
14. [安全机制](#14-安全机制)
15. [扩展性设计](#15-扩展性设计)

---

## 1. 项目概述

### 1.1 定位

一个**原生 PHP（无框架依赖）的多作者博客 CMS**，具备 WordPress 风格的主题/插件系统，支持 SQLite 和 MySQL 双数据库驱动。

### 1.2 技术栈

| 层 | 技术 |
|----|------|
| 语言 | PHP 8.2+ |
| 数据库 | SQLite（默认）/ MySQL |
| 前端 | 原生 HTML + CSS + JavaScript，无前端框架依赖 |
| Markdown | Parsedown Extra |
| 缓存 | File / Redis / Memcached / APCu / Array |
| 测试 | PHPUnit + Node.js（冒烟测试） |

### 1.3 设计理念

- **Laravel 风格**：服务容器、服务提供者、中间件链
- **WordPress 兼容**：钩子系统（Action/Filter）、模板标签 API、主题/插件体系
- **零框架依赖**：核心框架完全自研，仅依赖 4 个 Composer 包（Parsedown、Whoops、VarDumper、PHPUnit）

---

## 2. 架构总览

### 2.1 分层架构

```
┌─────────────────────────────────────────────────────┐
│                   入口层                             │
│  public/index.php (Web)  /  blog (CLI)  /  bin/dev  │
└──────────────────────┬──────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────┐
│                   路由层                             │
│  routes/web.php  /  routes/admin.php  /  routes/api  │
│  Router.php (正则匹配 + 参数解析 + 中间件链)          │
└──────────────────────┬──────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────┐
│                   控制器层                           │
│  App\Controllers\Web\*    (前台)                     │
│  App\Controllers\Admin\*  (后台)                     │
│  App\Controllers\Api\*    (API)                     │
└──────────────────────┬──────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────┐
│                   服务层                             │
│  PostService / LoginRateLimiter / Slugify           │
│  DTO / Event / Job / Listener                       │
└──────────────────────┬──────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────┐
│                   模型层                             │
│  Post / Category / Tag / Comment / User / Media     │
│  Option / MenuItem                                   │
└──────────────────────┬──────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────┐
│                   核心框架                           │
│  Container / Application / Router / Database        │
│  Auth / Cache / Hook / Plugin / View / Queue        │
└─────────────────────────────────────────────────────┘
```

### 2.2 请求生命周期

```
1. public/index.php 加载
   ├── 加载 Composer 自动加载
   ├── 加载 .env 配置
   └── 获取 Application 实例

2. Application::run()
   ├── 第一阶段：注册所有 Service Provider（register）
   ├── 第二阶段：启动所有 Service Provider（boot）
   ├── 捕获 Request
   └── 调用 Router::dispatch($request)

3. Router::dispatch()
   ├── 匹配路由（正则 + 缓存）
   ├── 执行中间件链（CSRF → Auth → Admin → Throttle → ...）
   ├── 解析控制器方法参数（模型绑定）
   ├── 执行控制器方法
   └── 返回 Response

4. Application::send()
   ├── 发送响应头
   ├── 输出响应体
   └── 执行终止回调
```

---

## 3. 核心框架（core/）

### 3.1 依赖注入容器（Container）

- **自动装配**：通过反射自动解析构造函数参数类型，递归创建依赖
- **绑定方式**：`bind()`（工厂）/ `singleton()`（单例）/ `instance()`（实例）/ `defer()`（懒加载）
- **高级特性**：别名绑定、上下文绑定、标签绑定、扩展器（`extend()`）、容器事件（`resolving`/`resolved`）
- **测试支持**：`resetForTesting()` / `flush()`

### 3.2 应用启动（Application）

- 继承自 Container，单例模式
- 管理 13 个 Service Provider 的两阶段生命周期
- 自动加载 7 个辅助函数文件
- 捕获异常 → 开发环境 Whoops 渲染 / 生产环境错误页面

### 3.3 路由系统（Router）

- 支持路由分组（`prefix`/`middleware`/`namespace`）
- 参数化路由（`/post/{id}` → 自动正则编译）
- 命名路由 → `route('name', params)` 反向生成 URL
- 中间件链（责任链模式）
- 路由正则缓存（`/storage/cache/routes_cached.php`）
- 支持 PUT/DELETE 方法覆盖（`_method` 字段）

### 3.4 数据库层

| 组件 | 说明 |
|------|------|
| Connection | PDO 封装，支持 SQLite（WAL 模式）/ MySQL 双驱动 |
| QueryBuilder | 不可变流式 API，命名占位符防 SQL 注入，支持事务 |
| Model | Active Record 基类，字段类型转换、模型事件、软删除、全局作用域 |
| Migration | 迁移基类 + Migrator 自动发现引擎，支持 batch 回滚 |
| Relations | BelongsTo / HasMany / HasOne / BelongsToMany，支持预加载（Eager Loading） |

### 3.5 认证系统

- 基于 Session 的登录态管理
- 5 级角色权限：`admin` > `editor` > `author` > `contributor` > `subscriber`
- 细粒度权限：`*` 通配符（admin 全权限），author/contributor 编辑文章时校验所有权
- 登录限流：`LoginRateLimiter` 防止暴力破解

### 3.6 缓存系统

- 统一接口 `CacheInterface`，5 种驱动实现
- `CacheManager` 工厂模式，支持自动降级
- 缓存锁（`CacheLock`）防止缓存击穿
- 整页静态缓存（`PageCache`）对匿名 GET 请求缓存完整 HTML
- 片段缓存（`FragmentCache`）缓存模板片段

### 3.7 钩子系统（Hook）

- **Action**：`add_action()` → `do_action()`，按优先级排序
- **Filter**：`add_filter()` → `apply_filters()`，支持条件注册、null 短路
- 性能追踪：开发模式下记录每个钩子的执行时间

### 3.8 事件系统

- 面向对象的事件发布/订阅
- `EventDispatcher`：事件类 + 监听器类，容器自动解析监听器
- 支持传播停止（`stopPropagation()`）

### 3.9 中间件链

| 中间件 | 职责 |
|--------|------|
| AuthMiddleware | 要求登录，未登录跳转 /login |
| GuestMiddleware | 要求未登录，已登录跳转后台 |
| AdminMiddleware | 要求后台角色（admin/editor/author/contributor） |
| CsrfMiddleware | CSRF 令牌校验 |
| CorsMiddleware | CORS 跨域配置 |
| SecurityHeadersMiddleware | 安全响应头（CSP/HSTS/X-Frame-Options 等） |
| ThrottleMiddleware | 基于缓存的请求限流（429 + Retry-After） |

### 3.10 视图引擎

- `ViewRenderer`：后台 PHP 模板渲染
- `ThemeManager`：主题管理器，支持父子主题、模板层级查找、页面模板
- `AssetManager`：CSS/JS 排队管理，依赖排序（拓扑排序）、版本指纹
- `Shortcode`：解析 `[tag attr="value"]` 标记
- `Widget` / `WidgetManager`：Widget 基类 + 区域管理器

### 3.11 其他子系统

| 子系统 | 说明 |
|--------|------|
| PluginManager | WordPress 风格插件管理器，ZIP 安装/激活/停用/卸载 |
| Queue | 队列系统（sync/file/database 三种驱动） |
| Sitemap | XML Sitemap 自动生成 + robots.txt + 面包屑 + JSON-LD |
| AuditLog | 审计日志，记录敏感操作 |
| Webhook | 事件发生时异步推送 HTTP POST 通知 |
| Translator | i18n 国际化翻译器 |
| Log | 按天滚动日志文件，8 级日志级别 |
| ImageProcessor | GD 缩略图生成，多尺寸，srcset |

---

## 4. 应用层（app/）

### 4.1 控制器体系

#### Web 控制器（前台）

| 控制器 | 路由 | 功能 |
|--------|------|------|
| HomeController | `GET /` | 首页文章列表，分页 |
| PostController | `GET /post/{slug}` | 文章详情，Markdown 渲染，上一篇/下一篇 |
| PageController | `GET /page/{slug}` | 页面展示 |
| CategoryController | `GET /category/{slug}` | 分类文章列表 |
| TagController | `GET /tag/{slug}` | 标签文章列表 |
| AuthorController | `GET /author/{id}` | 作者文章列表 |
| SearchController | `GET /search` | 搜索文章 |
| CommentController | `POST /comment` | 提交评论 |
| FeedController | `GET /feed` | RSS/Atom 订阅 |
| HealthController | `GET /health` | 健康检查 |

#### Admin 控制器（后台）

| 控制器 | 路由前缀 | 功能 |
|--------|---------|------|
| AuthController | `/auth` | 登录/注册/退出 |
| DashboardController | `/admin` | 仪表盘统计 |
| PostController | `/admin/posts` | 文章 CRUD + 搜索/筛选/分页 |
| CategoryController | `/admin/categories` | 分类 CRUD |
| TagController | `/admin/tags` | 标签 CRUD |
| MediaController | `/admin/media` | 媒体上传/删除/列表 |
| CommentController | `/admin/comments` | 评论审核/标记垃圾/删除 |
| UserController | `/admin/users` | 用户 CRUD |
| ThemeController | `/admin/themes` | 主题激活/删除/上传 |
| PluginController | `/admin/plugins` | 插件激活/停用/删除/上传 |
| SettingController | `/admin/settings` | 系统设置保存 |

#### API 控制器

| 控制器 | 功能 |
|--------|------|
| PostController | 文章列表 API（JSON） |
| TaxonomyController | 分类/标签 API |

### 4.2 模型层

| 模型 | 表名 | 核心字段 | 关联关系 |
|------|------|---------|---------|
| User | `users` | id, username, email, password, role, display_name, status, bio, url | hasMany Post, hasMany Comment |
| Post | `posts` | id, title, slug, content, content_md, excerpt, status, category_id, author_id, cover, published_at, seo_title, seo_description | belongsTo User, belongsTo Category, belongsToMany Tag, hasMany Comment |
| Category | `categories` | id, name, slug, description | hasMany Post |
| Tag | `tags` | id, name, slug | belongsToMany Post |
| Comment | `comments` | id, content, author_name, author_email, ip, status, post_id, user_id | belongsTo Post |
| Media | `media` | id, filename, original_name, path, mime_type, size, width, height, user_id | - |
| Option | `options` | key, value | - |
| MenuItem | `menu_items` | id, label, url, parent_id, menu_slug, order, target | - |

### 4.3 服务层

| 服务 | 职责 |
|------|------|
| PostService | 文章创建/更新业务逻辑，Slug 自动生成，事件触发 |
| LoginRateLimiter | 基于缓存的登录频率限制，5 次/分钟锁定 15 分钟 |

### 4.4 事件驱动

```
PostPublishedEvent
  ├── RebuildSitemapListener → 重建 Sitemap
  └── ClearPageCacheListener → 清除页面缓存

SendCommentNotificationJob
  └── 异步发送评论通知邮件（通过 Queue 队列）
```

---

## 5. 路由系统

### 5.1 路由定义汇总

**Web 路由（routes/web.php）—— 12 条：**

```
GET  /                    → HomeController@index
GET  /post/{slug}        → PostController@show
GET  /page/{slug}        → PageController@show
GET  /category/{slug}    → CategoryController@show
GET  /tag/{slug}         → TagController@show
GET  /author/{id}        → PostController@byAuthor
GET  /search             → SearchController@search
POST /comment            → CommentController@store
GET  /feed               → FeedController@feed
GET  /feed.rss           → FeedController@feed
GET  /health             → HealthController@check
GET  /sitemap.xml        → Sitemap 生成
```

**Admin 路由（routes/admin.php）—— 20+ 条：**

```
GET/POST /auth/login       → AuthController
GET/POST /auth/register    → AuthController
GET      /auth/logout      → AuthController
GET      /admin              → DashboardController
GET/POST /admin/posts/*     → PostController (CRUD + preview)
GET/POST /admin/categories/* → CategoryController
GET/POST /admin/tags/*      → TagController
GET/POST /admin/media/*     → MediaController
GET/POST /admin/comments/*  → CommentController
GET/POST /admin/users/*     → UserController
GET/POST /admin/themes/*    → ThemeController
GET/POST /admin/plugins/*   → PluginController
GET/POST /admin/settings/*  → SettingController
```

**API 路由（routes/api.php）—— 2 条：**

```
GET  /api/posts       → Api\PostController@index
GET  /api/taxonomies  → Api\TaxonomyController@index
```

### 5.2 路由分组

| 分组 | 前缀 | 中间件 | 命名空间 |
|------|------|--------|---------|
| Web | 无 | web | App\Controllers\Web |
| Admin | 无 | web + auth + admin | App\Controllers\Admin |
| API | api | api + cors | App\Controllers\Api |

---

## 6. 数据库设计

### 6.1 表结构

| 表名 | 说明 | 核心字段数 | 索引数 |
|------|------|-----------|--------|
| `users` | 用户表 | 12 | 4 |
| `posts` | 文章表 | 16 | 7 |
| `categories` | 分类表 | 5 | 2 |
| `tags` | 标签表 | 4 | 1 |
| `post_tag` | 文章-标签中间表 | 2 | 3 |
| `comments` | 评论表 | 10 | 4 |
| `media` | 媒体文件表 | 10 | 3 |
| `options` | 配置键值表 | 3 | 1 |
| `sessions` | Session 表 | 4 | 1 |
| `migrations` | 迁移记录表 | 3 | 0 |
| `menu_items` | 菜单项表 | 8 | 2 |
| `audit_logs` | 审计日志表 | 6 | 2 |
| `jobs` | 队列任务表 | 5 | 1 |
| `plugin_activations` | 插件激活表 | 2 | 1 |

### 6.2 数据库驱动

- **默认**：SQLite（`database/database.sqlite`），WAL 模式
- **可选**：MySQL（通过 `.env` 配置 `DB_DRIVER=mysql`）
- Schema 文件：`database/schema.mysql.sql` / `database/schema.sqlite.sql`

### 6.3 迁移系统

- 4 个迁移文件覆盖所有表结构
- 自动发现 `database/migrations/` 中的文件
- 支持 batch 回滚和状态查询

---

## 7. 视图与模板

### 7.1 后台视图（resources/views/）

```
layouts/
  └── admin.php              ← 后台布局模板（侧栏 + 顶部栏 + 内容区）

admin/
  ├── dashboard.php          ← 仪表盘（统计卡片 + 最新文章/评论）
  ├── posts/
  │   ├── index.php          ← 文章列表（搜索/筛选/分页）
  │   └── form.php           ← 文章编辑（Markdown 编辑器 + 预览）
  ├── categories/index.php   ← 分类管理（内联创建表单）
  ├── tags/index.php         ← 标签管理（内联创建表单）
  ├── media/index.php        ← 媒体库（网格视图 + 上传）
  ├── comments/index.php     ← 评论管理（筛选标签 + 批量操作）
  ├── users/
  │   ├── index.php          ← 用户列表
  │   └── form.php           ← 用户编辑表单
  ├── themes/index.php       ← 主题管理（网格 + 截屏 + 激活）
  ├── plugins/index.php      ← 插件管理（网格 + 激活/停用）
  └── settings/index.php     ← 系统设置（分组表单 + 统计代码）

auth/
  ├── login.php              ← 登录页面
  └── register.php           ← 注册页面
```

### 7.2 后台设计系统

- **独立 CSS 设计系统**（`public/assets/admin/admin.css`），与前端主题完全解耦
- **Design Tokens**：CSS 变量定义颜色、间距、阴影、圆角、字体
- **组件库**：卡片、表格、表单、按钮（6 种变体、3 种尺寸）、分页、徽章、弹窗、下拉菜单、搜索框、统计卡片、媒体网格、上传区
- **响应式**：3 个断点（1024px / 768px / 480px），侧栏在移动端切换为抽屉式
- **JavaScript**（`public/assets/admin/admin.js`）：侧栏切换、确认对话框、Markdown 预览、表单切换

---

## 8. 前端主题系统

### 8.1 主题结构

```
public/themes/default/
├── theme.json              ← 主题元数据（名称、版本、作者、描述）
├── functions.php           ← 主题函数（注册 Widget 区域、菜单、Shortcode）
├── Widgets/
│   └── RecentPostsWidget.php  ← 最新文章小工具
├── partials/
│   ├── header.php          ← HTML 头部（meta + CSS + 导航栏）
│   ├── footer.php          ← HTML 底部（JS + 页脚）
│   └── sidebar.php         ← 侧边栏（分类/标签云/最新文章/评论）
├── templates/
│   ├── home.php            ← 首页（文章列表 + 分页）
│   ├── single.php          ← 文章详情页（Markdown 渲染 + 目录 + 评论）
│   ├── page.php            ← 页面
│   ├── archive.php         ← 归档
│   ├── category.php        ← 分类页
│   ├── tag.php             ← 标签页
│   ├── author.php          ← 作者页
│   ├── search.php          ← 搜索结果页
│   ├── 404.php             ← 404 页面
│   └── error.php           ← 错误页面
└── assets/
    ├── css/style.css       ← 主题样式（响应式 + 暗色适配）
    ├── js/main.js          ← 主题 JS（移动端菜单 + 回到顶部）
    └── img/favicon.svg     ← 网站图标
```

### 8.2 主题特性

- **WordPress 风格模板标签**：`get_header()` / `get_footer()` / `get_sidebar()` / `the_title()` / `the_content()` / `the_excerpt()` / `paginate_links()` / `wp_nav_menu()` / `dynamic_sidebar()` / `body_class()` / `post_class()`
- **条件标签**：`is_home()` / `is_single()` / `is_page()` / `is_category()` / `is_tag()` / `is_author()` / `is_search()` / `is_404()`
- **Widget 系统**：`register_sidebar()` / `dynamic_sidebar()`，内置 RecentPostsWidget
- **菜单系统**：`register_nav_menu()` / `wp_nav_menu()`，支持层级菜单
- **Shortcode**：`[gallery]` / `[youtube]` / `[quote]`
- **内容助手**：`reading_time()` / `word_count()` / `table_of_contents()`
- **安全转义**：`esc_html()` / `esc_attr()` / `esc_url()`
- **响应式 CSS**：Flexbox 栅格、3 个断点、暗色模式支持

---

## 9. 后台管理系统

### 9.1 功能清单

| 页面 | 功能描述 |
|------|---------|
| 仪表盘 | 统计卡片（文章/评论/用户数）、待审核提醒、最新文章/评论列表 |
| 文章管理 | 列表/搜索/筛选/分页、Markdown 编辑+实时预览、分类/标签选择、SEO 设置 |
| 分类管理 | 列表+内联创建表单、删除确认 |
| 标签管理 | 同分类管理 |
| 媒体库 | 网格视图、上传（图片/PDF/音视频）、删除 |
| 评论管理 | 筛选标签（全部/待审/已批准/垃圾）、批准/标垃圾/删除 |
| 用户管理 | 列表、添加/编辑（角色/密码/个人资料）、删除（不可删自己） |
| 主题管理 | 网格视图、截屏、激活/删除、ZIP 上传 |
| 插件管理 | 网格视图、激活/停用/删除、ZIP 上传 |
| 系统设置 | 基本信息、交互设置、统计代码 |

### 9.2 布局结构

```
┌──────────────┬──────────────────────────────────┐
│              │  Header                          │
│   Sidebar    │  ├ 三条杠按钮 + 页面标题          │
│   (固定)     │  └ 用户头像 + 用户名              │
│              ├──────────────────────────────────┤
│  导航项      │  Content                         │
│  ├ 仪表盘     │  ├ Flash 消息                    │
│  ├ 文章       │  ├ 页面标题 + 操作按钮           │
│  ├ 分类       │  ├ 表格/卡片/表单/网格            │
│  ├ 标签       │  └ 分页                          │
│  ├ 媒体       │                                  │
│  ├ 评论       │                                  │
│  ├ 用户       │                                  │
│  ├ 主题       │                                  │
│  ├ 插件       │                                  │
│  └ 设置       │                                  │
│              │                                  │
│  查看站点    │                                  │
│  退出        │                                  │
└──────────────┴──────────────────────────────────┘
```

---

## 10. 配置系统

### 10.1 配置文件

| 文件 | 核心配置项 |
|------|-----------|
| `config/app.php` | name, env, debug, url, timezone, locale, key, theme, plugins, registration, allow_register, comment_moderation, posts_per_page |
| `config/database.php` | driver (sqlite/mysql), sqlite.path, mysql.host/port/database/username/password |
| `config/cache.php` | default (file/redis), drivers.file.path, drivers.redis.host/port/password/database |
| `config/session.php` | driver (file/database), lifetime, files, cookie, http_only, same_site, secure |
| `config/queue.php` | default (sync/file/database), drivers.file.path, drivers.database.table |
| `config/theme.php` | 主题路径、资产路径、缓存路径 |

### 10.2 环境变量（.env）

```
APP_NAME, APP_ENV, APP_DEBUG, APP_URL, APP_KEY
DB_DRIVER, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD
SESSION_DRIVER, SESSION_LIFETIME, CACHE_DRIVER
MAIL_DRIVER, MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, MAIL_ENCRYPTION
QUEUE_DRIVER
```

---

## 11. 测试体系

### 11.1 PHPUnit 测试

| 测试文件 | 覆盖内容 | 测试项数 |
|---------|---------|---------|
| RouterTest.php | 路由注册、匹配、参数提取、命名路由、分组 | 8+ |
| QueryBuilderTest.php | select/where/join/orderBy/insert/update/delete/事务 | 10+ |
| CapabilityTest.php | 角色权限矩阵、admin 通配符、所有权校验 | 6+ |
| ThemeSystemTest.php | 主题加载、模板层级、Asset 排队、Widget、Shortcode | 10+ |
| FirstBatchTest.php | 核心功能集成测试 | 10+ |
| SecondBatchTest.php | 功能测试 | 10+ |
| ThirdBatchTest.php | 功能测试 | 10+ |
| DeepEnhancementTest.php | 深度增强功能测试 | 10+ |

### 11.2 冒烟测试

- `tests/SmokeTest.js`：Node.js 脚本，对运行中的服务器执行 HTTP 请求，验证所有页面返回 200

### 11.3 测试基础设施

- `tests/TestCase.php`：测试基类，提供应用初始化、数据库迁移、HTTP 请求模拟
- `tests/bootstrap.php`：测试引导文件，自动加载 + 错误处理

---

## 12. 开发工作流

### 12.1 入口文件

| 入口 | 用途 |
|------|------|
| `public/index.php` | Web 服务器入口（Apache/Nginx/PHP 内置服务器） |
| `blog` | CLI 命令行入口，支持 `php blog serve` 启动内置服务器 |
| `bin/dev` | 开发服务器脚本，自动检测端口、打开浏览器 |

### 12.2 启动方式

```bash
# 方式一：PHP 内置服务器
php -S localhost:8080 -t public

# 方式二：CLI 入口
php blog serve

# 方式三：开发脚本（推荐）
php bin/dev
```

### 12.3 质量保障体系

```
┌─────────────────────────────────────────────────────┐
│                三层防护体系                          │
├─────────────────────────────────────────────────────┤
│  第一层：pre-commit hook（本地）                      │
│  ├ 检查内联 onclick                                  │
│  ├ 检查 admin.php 内联脚本                           │
│  ├ 检查 CSS 类是否在 admin.css 中定义                 │
│  └ 检查 data-* 属性引用是否有效                       │
├─────────────────────────────────────────────────────┤
│  第二层：composer 自动安装                           │
│  ├ post-install-cmd → bash scripts/setup.sh          │
│  ├ post-update-cmd → bash scripts/setup.sh           │
│  └ setup.sh 自动安装 pre-commit hook + 创建存储目录   │
├─────────────────────────────────────────────────────┤
│  第三层：GitHub Actions CI（远程）                    │
│  ├ 只在 admin 相关文件变更时触发                      │
│  ├ 检查内联 onclick                                  │
│  ├ 检查内联脚本                                      │
│  └ 检查 CSS 类缺失                                  │
└─────────────────────────────────────────────────────┘
```

### 12.4 新环境快速启动

```bash
git clone <repo>
cd <project>
composer install                    # 自动安装 hook + 创建存储目录
cp .env.example .env                # 配置环境变量
php bin/dev                         # 启动开发服务器
```

---

## 13. 设计模式清单

| 设计模式 | 使用位置 | 说明 |
|---------|---------|------|
| **单例模式** | Application | 全局唯一实例 |
| **服务容器/IoC** | Container | 依赖注入容器，自动装配 |
| **服务提供者** | Provider 及其 13 个子类 | 两阶段启动（register → boot） |
| **工厂模式** | CacheManager | 根据驱动名创建对应缓存驱动 |
| **策略模式** | CacheInterface 的 5 种实现 | 统一接口，运行时切换策略 |
| **观察者模式** | Hook/Action + Hook/Filter | WordPress 风格钩子系统 |
| **事件/监听器** | EventDispatcher | 面向对象的事件发布/订阅 |
| **Active Record** | Model 基类 | 数据行映射为对象 |
| **查询构造器** | QueryBuilder | 不可变流式 API，克隆模式 |
| **责任链模式** | 中间件链 | 返回 null 继续链，返回 Response 短路 |
| **模板方法** | Provider / Migration | 父类控制生命周期，子类实现具体逻辑 |
| **适配器模式** | RedisCache / FileCache | 统一接口适配不同后端 |
| **代理模式** | CacheManager | 代理对默认驱动的方法调用 |
| **延迟加载** | Container::defer() | 首次访问时才实例化 |
| **装饰器模式** | Container::extend() | 解析后通过扩展器修改实例 |
| **标签绑定** | Container::tag() / tagged() | 批量解析一组相关服务 |

---

## 14. 安全机制

| 安全措施 | 实现位置 | 说明 |
|---------|---------|------|
| CSRF 保护 | CsrfMiddleware | 所有 POST/PUT/DELETE 请求校验 token |
| XSS 防护 | `e()` / `esc_html()` / `esc_attr()` / `esc_url()` | HTML 实体转义 |
| SQL 注入防护 | QueryBuilder 命名占位符 | PDO 参数化查询 |
| 密码哈希 | `password_hash()` + `password_verify()` | bcrypt 算法 |
| 安全响应头 | SecurityHeadersMiddleware | CSP / HSTS / X-Frame-Options / X-Content-Type-Options / Referrer-Policy / Permissions-Policy |
| CORS 控制 | CorsMiddleware | 可配置允许源/方法/头/凭证 |
| 请求限流 | ThrottleMiddleware | 基于缓存的 IP/用户限流，429 + Retry-After |
| 登录限流 | LoginRateLimiter | 5 次/分钟锁定 15 分钟 |
| Session 安全 | Session 配置 | HttpOnly + SameSite=Lax + 32 字符随机 ID |
| 审计日志 | AuditLog | 记录敏感操作到数据库 |
| 角色权限 | Capability | 5 级角色 + 细粒度权限校验 |
| 输入验证 | FormRequest | 18 种验证规则，验证失败自动重定向 |
| 文件上传限制 | MediaController | MIME 类型检查 + 文件大小限制 |

---

## 15. 扩展性设计

### 15.1 插件系统

- WordPress 风格：`plugins/` 目录下每个子目录为一个插件
- 插件元数据：通过文件头部注释声明（名称、版本、描述、作者）
- 生命周期：激活 → 运行 → 停用 → 卸载
- ZIP 安装：支持通过后台上传 ZIP 包安装
- 钩子集成：插件通过 `add_action()` / `add_filter()` 扩展功能

### 15.2 主题系统

- 父子主题支持
- 模板层级查找（子主题 → 父主题 → 默认）
- theme.json 元数据声明
- 页面模板（自定义页面模板文件）
- Widget 区域注册 + 渲染
- 菜单位置注册 + 递归渲染
- ZIP 上传安装

### 15.3 服务提供者扩展

- 新增功能只需创建 Provider 类，在 `Application::$providers` 数组中注册
- Provider 两阶段生命周期保障依赖解析顺序

### 15.4 Hook 扩展

- Action 钩子：在关键流程中插入 `do_action()`，插件/主题可注入自定义逻辑
- Filter 钩子：通过 `apply_filters()` 允许外部代码修改数据

### 15.5 内置扩展点

- 后台 `admin_head` / `admin_footer` 钩子
- 前台 `wp_head` / `wp_footer` 钩子
- Shortcode 系统（`[gallery]` / `[youtube]` / `[quote]`）
- 代码生成器（`make:resource` / `make:controller` / `make:model` 等）

---

> 本文档覆盖了项目中所有 150+ 个文件，包括核心框架 90+ 个文件、应用层 30+ 个文件、视图模板 30+ 个文件、配置文件 8 个、路由文件 3 个、测试文件 12 个。通过对每个文件的深度阅读和理解，完整呈现了项目的架构设计、功能实现和工程实践。