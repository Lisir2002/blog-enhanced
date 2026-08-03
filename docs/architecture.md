# 架构文档

## 总体设计

Blog CMS 是一个基于原生 PHP 的内容管理系统，核心设计理念：

- **无框架**：不依赖 Laravel/Symfony 等全栈框架，所有核心组件自实现
- **PSR-4 自动加载**：通过 Composer 的 autoload 管理类文件
- **IoC 容器**：最小化的依赖注入容器，支持 singleton / bind / 自动注入
- **类 WordPress Hook**：`do_action` / `apply_filters` 机制，插件和主题可挂载逻辑

## 核心组件

### Application（应用主类）

`core/Application.php` — 单例，整个请求的生命周期入口。

```
请求 → Application::run() → Router::dispatch() → Controller → Response::send()
```

职责：
- 注册核心服务到容器（Connection、QueryBuilder、Session、Cache、Auth 等）
- 加载路由文件（routes/admin.php、routes/api.php、routes/web.php）
- 启动插件（PluginManager::boot）
- 异常处理 + 日志记录

### Container（IoC 容器）

`core/Container.php` — 最简容器，支持：
- `singleton($abstract, $concrete)` — 单例绑定
- `bind($abstract, $concrete)` — 普通绑定
- `instance($abstract, $object)` — 直接注入实例
- `get($abstract)` — 解析依赖（自动注入构造函数参数）

### Router（路由器）

`core/Router.php` — 静态注册 + 动态分发。

特点：
- 支持 GET / POST / PUT / PATCH / DELETE
- 路由参数：`/posts/{slug}`、`/page/{id:\d+}`（正则约束）
- 命名路由：`->name('posts.show')` + `route('posts.show', ['slug' => $slug])`
- 中间件：`->middleware(['auth', 'admin', 'csrf'])`
- **正则编译缓存**：同一 pattern 只编译一次，避免重复 `preg`

### QueryBuilder（查询构造器）

`core/Database/QueryBuilder.php` — 不可变流式查询构造器。

特点：
- **不可变**：所有链式方法（where、orderBy、limit 等）返回 clone，不修改原对象
- 支持 SELECT / INSERT / UPDATE / DELETE
- 参数化查询（PDO prepare），防 SQL 注入
- JOIN / GROUP BY / HAVING 支持
- 事务支持：`transaction(callable)`

### Hook（钩子系统）

`core/Hook/Action.php` + `core/Hook/Filter.php`

- **Action**：`do_action($name, ...$args)` — 执行挂载的动作
- **Filter**：`apply_filters($name, $value, ...$args)` — 链式过滤值
- 注册：`add_action($name, $callback, $priority)` / `add_filter($name, $callback, $priority)`

### Auth（鉴权）

`core/Auth/AuthManager.php` + `core/Auth/Capability.php`

- 基于 Session 的登录状态
- 5 个角色：admin / editor / author / contributor / subscriber
- 细粒度权限：`can('edit_posts', $post)` — author 只能编辑自己的文章
- 登录限流：`LoginRateLimiter` — 5 次失败锁定 15 分钟

### Cache（缓存）

`core/Cache/FileCache.php` + `core/Cache/CacheInterface.php`

- 文件缓存，PSR-16 风格 API
- `get` / `set` / `delete` / `clear` / `has` / `remember`
- 用于：导航菜单缓存、浏览量 IP 去重

### Log（日志）

`core/Log/Log.php`

- 8 级日志：DEBUG / INFO / NOTICE / WARNING / ERROR / CRITICAL / ALERT / EMERGENCY
- 按天文件：`storage/logs/{Y-m-d}.log`
- 生产环境自动丢弃 DEBUG
- 异常自动记录（含 URI / IP / stack trace）

## 请求生命周期

```
1. public/index.php → Application::__construct()
2. Application::bootstrap()
   ├── registerCoreServices()     — 注册容器绑定
   ├── loadHelpers()              — 加载 helpers.php
   ├── loadRoutes()               — 加载 routes/*.php
   └── bootPlugins()              — 启动插件
3. Application::run()
   ├── Router::dispatch(method, path)
   │   ├── 匹配路由（正则缓存）
   │   ├── 执行中间件链（auth / admin / csrf / guest）
   │   └── Controller::method($params) → Response
   └── Response::send()           — 输出 HTTP 响应
4. 异常捕获 → Log::error() + handleException() → 500 错误页
```

## 数据库

- **SQLite**（默认）：WAL 模式，外键约束开启，支持 `:memory:`（测试用）
- **MySQL**：STRICT 模式，utf8mb4
- Schema 文件：`database/schema.sqlite.sql` / `database/schema.mysql.sql`
- 迁移：`database/migrations/`
- 种子：`database/seeds/run.php`

## 安全设计

- **CSRF**：所有 POST/PUT/DELETE 请求需 `_token` 字段，`hash_equals` 比对
- **Session**：HttpOnly + SameSite=Lax + HTTPS 自动 Secure + `use_strict_mode` + sid 48 字符
- **密码**：`password_hash` bcrypt
- **SQL 注入**：QueryBuilder 全部参数化
- **XSS**：`e()` 函数转义输出，Parsedown `setSafeMode(true)` 过滤危险 HTML
- **登录限流**：IP + 用户名组合，5 次失败锁定 15 分钟
- **评论**：邮箱 FILTER_VALIDATE_EMAIL + 内容长度限制 + honeypot 反垃圾
