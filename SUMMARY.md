# Blog CMS 完整代码深度总结（v2）

> 本文档基于对项目 **全部 180+ 个源文件** 的逐行深度阅读与源码分析，涵盖每个类的方法、属性、实现细节与设计意图。
>
> 项目：`https://github.com/Lisir2002/blog-enhanced`
> 技术栈：**原生 PHP 8.2+**（无 Laravel/Symfony 等全栈框架依赖）+ SQLite / MySQL + Composer 组件
> 代码规模：~180 个源文件，~22,000+ 行代码

---

## 目录

1. [项目定位与技术栈](#1-项目定位与技术栈)
2. [总体架构](#2-总体架构)
3. [入口与请求生命周期](#3-入口与请求生命周期)
4. [核心基础设施 core/](#4-核心基础设施-core)
5. [路由与中间件系统](#5-路由与中间件系统)
6. [数据库层](#6-数据库层)
7. [认证与权限系统](#7-认证与权限系统)
8. [钩子 / 事件系统](#8-钩子--事件系统)
9. [缓存系统](#9-缓存系统)
10. [视图与主题系统](#10-视图与主题系统)
11. [插件系统](#11-插件系统)
12. [队列 / 邮件 / Webhook / SEO / 审计 / 日志 / i18n](#12-队列--邮件--webhook--seo--审计--日志--i18n)
13. [应用层 app/](#13-应用层-app)
14. [路由定义 routes/](#14-路由定义-routes)
15. [配置与环境 config/](#15-配置与环境-config)
16. [数据库结构与迁移](#16-数据库结构与迁移)
17. [主题系统详解](#17-主题系统详解)
18. [后台管理界面 resources/views/](#18-后台管理界面-resourcesviews)
19. [测试体系](#19-测试体系)
20. [辅助函数体系详解 Support/helpers*](#20-辅助函数体系详解-supporthelpers)
21. [Service Provider 注册链路](#21-service-provider-注册链路)
22. [设计模式总览](#22-设计模式总览)
23. [安全机制清单](#23-安全机制清单)
24. [扩展点 Hook / Filter 注册表](#24-扩展点-hook--filter-注册表)
25. [开发快速参考](#25-开发快速参考)

---

## 1. 项目定位与技术栈

### 1.1 定位
一个**面向多作者的现代博客 CMS**，目标是在无框架依赖的前提下，提供接近 WordPress 的用户体验与扩展能力，同时保持 Laravel 风格的代码组织与工程规范。

### 1.2 技术栈

| 层级 | 技术 |
|------|------|
| 语言 | PHP 8.2+（使用 match/箭头函数/只读属性/枚举兼容等新特性） |
| 数据库 | PDO 抽象，SQLite（默认 WAL 模式）/ MySQL（STRICT + utf8mb4） |
| Markdown | `erusev/parsedown`（安全模式 setSafeMode(true)） |
| 调试 | `filp/whoops`（开发环境错误页） |
| 测试 | `phpunit/phpunit` 10.x + 内存 SQLite |
| 依赖管理 | Composer（仅 4 个运行时依赖） |
| 前端 | 原生 HTML/CSS/JS（零前端框架） |
| 部署 | PHP 内置服务器 / Apache / Nginx 任意 |

### 1.3 设计哲学
- **Laravel 风格容器 + Service Provider**：`register()` 绑定 → `boot()` 启动的两阶段生命周期
- **WordPress 风格主题/插件/Hook**：完全兼容 `add_action`/`apply_filters`、`get_header`/`get_footer` 等 API
- **极简依赖**：真正做到"开箱即用"，部署包体积 < 500 KB

---

## 2. 总体架构

### 2.1 分层架构图

```
┌──────────────────────────────────────────────────────────────┐
│  入口层：public/index.php / bin/dev / blog (CLI)              │
│  ─ 加载 Composer 自动加载、.env、创建 Application 实例         │
└────────────────────────┬─────────────────────────────────────┘
                         │
┌────────────────────────▼─────────────────────────────────────┐
│  引导层：Application::bootstrap()                             │
│  ─ 注册 14 个 ServiceProvider 的 register() → boot()           │
│  ─ 加载 7 个 helpers 文件                                      │
│  ─ 加载 routes/*.php                                          │
└────────────────────────┬─────────────────────────────────────┘
                         │
┌────────────────────────▼─────────────────────────────────────┐
│  路由层：Router::dispatch(method, path)                       │
│  ─ 正则模式匹配（带缓存）                                       │
│  ─ 中间件链展开（组 → 参数化 → 全局）                            │
│  ─ 路由模型绑定自动解析                                         │
│  ─ 条件标签注入 Conditional::set()                              │
└────────────────────────┬─────────────────────────────────────┘
                         │
┌────────────────────────▼─────────────────────────────────────┐
│  控制器层：Web / Admin / Api 三类控制器                        │
│  ─ Web：9 个    │  Admin：11 个    │  Api：2 个                │
└────────────────────────┬─────────────────────────────────────┘
                         │
┌────────────────────────▼─────────────────────────────────────┐
│  服务层：DTO + Service + Event + Job + Listener                │
│  ─ PostData DTO / PostService / LoginRateLimiter              │
│  ─ PostPublishedEvent + RebuildSitemapJob + ...               │
└────────────────────────┬─────────────────────────────────────┘
                         │
┌────────────────────────▼─────────────────────────────────────┐
│  模型层：Model 基类 + 7 个具体模型                              │
│  ─ 特性：关联关系 / Eager Loading / 软删除 / 模型事件 / 查询作用域 │
└────────────────────────┬─────────────────────────────────────┘
                         │
┌────────────────────────▼─────────────────────────────────────┐
│  核心引擎：core/*.php                                         │
│  ├── Container / Application / Router                        │
│  ├── Database (Connection / QueryBuilder / Model / Migrator)  │
│  ├── Auth (AuthManager / Capability)                          │
│  ├── Cache (5 种驱动 + PageCache / FragmentCache / CacheLock) │
│  ├── Hook (Action / Filter)                                   │
│  ├── View (ThemeManager / AssetManager / WidgetManager / ...) │
│  ├── Plugin (PluginManager)                                   │
│  ├── Http (Request / Response / Session / FormRequest)        │
│  ├── Queue / Log / Email / Webhook / SEO / AuditLog / i18n     │
└──────────────────────────────────────────────────────────────┘
```

### 2.2 代码文件总量

| 目录 | 文件数 | 说明 |
|------|--------|------|
| `core/` | 95+ | 核心引擎 |
| `app/` | 30+ | 应用业务代码 |
| `config/` | 7 | 配置 |
| `routes/` | 3 | 路由 |
| `public/themes/default/` | 20+ | 默认主题 |
| `resources/views/` | 15+ | 后台视图 |
| `database/` | 4 migrations + schema + seed | 数据库 |
| `plugins/` | 1 | 示例插件 |
| `tests/` | 11 | 测试 |
| **合计** | **~180** | |

---

## 3. 入口与请求生命周期

### 3.1 入口文件

**`public/index.php`**（Web 入口）：
```php
require __DIR__ . '/../vendor/autoload.php';
\Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
$app = \Core\Application::getInstance();
$app->run();
```

**`blog`**（CLI 入口）：
支持子命令：`serve` / `install` / `migrate` / `seed` / `make:resource` / `make:controller` / `make:model` / `make:middleware` / `make:dto` / `make:migration`

**`bin/dev`**（开发入口）：自动探测可用端口、打开浏览器。

### 3.2 请求生命周期详解

```
1. Web 服务器接收请求 → public/index.php

2. 加载 Composer + .env → 创建 Application 单例

3. Application::__construct()
   └── loadHelpers()：依次加载 7 个 helpers 文件
       - Support/helpers.php         (app/config/url/e/view/response 等)
       - Support/helpers_http.php    (url/route/asset/redirect/csrf/old)
       - Support/helpers_hook.php    (add_action/do_action/add_filter/apply_filters)
       - Support/helpers_auth.php    (logged_in/current_user/can/can_or_403)
       - Support/helpers_theme.php   (get_header/get_footer/is_*/enqueue_*/...)
       - Support/helpers_advanced.php (__,cache_fragment,post_thumbnail,breadcrumbs,send_email)
       - 每个函数都用 function_exists 防重复注册

4. Application::bootstrap()
   └── 遍历 providers 数组（共 14 个），依次执行：
       - register()：绑定服务到容器（不触发解析）
       - boot()：启动逻辑（可安全解析服务）
   顺序：
       AuthProvider → DatabaseProvider → CacheProvider → HttpProvider →
       HookProvider → ViewProvider → ParsedownProvider → ThemeServiceProvider →
       PluginProvider → QueueProvider → EnhancedServiceProvider →
       AdvancedServiceProvider → RouteServiceProvider

5. RouteServiceProvider::boot()
   └── 注册中间件组 (web/admin/api)
   └── 加载 routes/web.php, admin.php, api.php
   └── 注册模型绑定 {slug} → Post::class:slug

6. Application::run()
   └── $request = Request::capture()
       - 读取 $_GET/$_POST/$_SERVER/$_FILES/$_COOKIE
       - method = strtoupper(REQUEST_METHOD)
       - path = parse_url(REQUEST_URI)，去除尾斜杠
   └── $router->dispatch($request->method, $request->path)

7. Router::dispatch()
   ├── 规范化 path（去除尾斜杠、合并多斜杠到 /）
   ├── HTTP 方法覆写（_method 或 X-HTTP-Method-Override 头）
   ├── 遍历路由表：
   │   - 使用 #^...$#u 正则匹配（Unicode 模式）
   │   - 参数用命名捕获 (?P<name>...)
   │   - 应用 $modelBindings 自动解析模型
   │   - Conditional::set($routeName, $params) 供条件标签使用
   │   - expandMiddleware() 展开中间件组并处理参数化
   │   - 中间件返回 Response 则短路
   ├── invokeHandler()：反射判定是否传 $params
   └── 404 时尝试渲染主题 404 模板或 ErrorController

8. Controller 执行
   ├── 获取数据 + 调用 do_action() / apply_filters()
   ├── 调用 theme_view() 或 view() 渲染模板

9. Response::send()
   ├── 发送 HTTP 状态码 + 头 + 输出 body
   └── 触发应用终止回调

10. 异常处理
    ├── 开发环境 → Whoops 渲染
    └── 生产环境 → 错误页 + Log::error()
```

### 3.3 CLI 生命周期（Blog 命令）

```
blog → bin/install.php → Core\Console\Application
  ├── Application::run($argv)
  │   - 解析命令名，支持子命令/别名（list/--list/-h/help）
  │   - 'list'/'help' → 打印所有可用命令（格式 "%-20s %s"）
  │   - 执行 handler($argv)，失败时 fwrite(STDERR) 输出错误
  │   - 已注册的命令：
  │     - install   : 调用 DatabaseProvider → 创建表 + 种子
  │     - migrate   : Migrator::run() 执行 pending
  │     - migrate:rollback : 指定步数或上一批回滚
  │     - migrate:status   : 列出所有迁移及状态
  │     - seed      : database/seeds/run.php
  │     - serve     : PHP 内置服务器包装（自动探测端口）
  │     - make:*    : MakeCommand 代码生成
  │
  ├── MakeCommand 实现细节（`core/Console/Commands/MakeCommand.php`）：
  │   - 用 match 表达式路由到具体生成方法
  │   - makeResource: 依次调用 makeModel → makeController → makeDto → makeMigration
  │   - makeController: 支持 Admin/Api 子目录，自动推导命名空间
  │   - makeModel: 继承 Model，根据类名 snake_case 设置 $table
  │   - makeMiddleware: 写入 core/Http/Middleware/ 目录
  │   - makeMigration: 文件名含当前时间戳 Ymd_His + 类名
  │   - snakeCase(): 处理 APIV2Client → api_v2_client 的大写缩写连续情况
  │
  └── bin/dev 开发入口：
      - 扫描 8000-8999 端口寻找可用端口
      - 自动启动 PHP 内置服务器 + 打开浏览器
```

---

## 4. 核心基础设施 core/

### 4.1 Container（IoC 容器）**`core/Container.php`**

**内部状态**：
- `$bindings[]`：抽象 → 闭包/工厂绑定
- `$instances[]`：抽象 → 已解析单例实例
- `$aliases[]`：别名 → 真实抽象
- `$tags[]`：标签 → 抽象数组
- `$extenders[]`：抽象 → 装饰器数组
- `$contextual[]`：when(abstract)→needs(param)→give(callback)
- `$resolvingCallbacks[]` / `$resolvedCallbacks[]`：解析事件

**关键方法**：
- `bind($abstract, $concrete)` — 工厂绑定（每次解析调用 `$concrete()`）
- `singleton($abstract, $concrete)` — 单例绑定（仅首次解析，之后复用实例）
- `instance($abstract, $instance)` — 直接注入已构造实例（跳过解析）
- `alias($abstract, $target)` — 别名绑定（`get('request')` 最终解析到 `Request::class`）
- `when($abstract)->needs($param)->give($callback)` — 上下文绑定（特定抽象 + 特定参数的特殊解析）
- `tag($tag, $abstracts)` — 标签绑定（`tag('report', [FileLog, DbLog])` 后可 `tagged('report')` 批量解析）
- `extend($abstract, $decorator)` — 装饰器模式（解析后追加一层包装）
- `get($abstract)` — 自动装配主入口
  1. 查 `$aliases` 解引用
  2. 命中 `$instances` 直接返回
  3. 查 `$contextual[$abstract][$param]` 上下文绑定
  4. 查 `$bindings[$abstract]`（支持闭包/工厂/类名）
  5. 反射 `ReflectionClass::getConstructor()` 取构造函数参数
  6. 对每个参数递归 `get()` 自动装配
  7. 调用 `$concrete(...$resolvedArgs)` 构造实例
  8. 触发 `resolvingCallbacks` → `resolvedCallbacks` 事件
- `flush($abstract)` / `resetForTesting()` — 测试辅助（清空单例实例）
- `has($abstract)` — 抽象是否已绑定
- `tagged($tag)` — 批量解析同标签抽象

### 4.2 Application（应用主类）**`core/Application.php`**

继承自 Container + 单例模式。

**关键属性**：
- `$providers`：14 个 ServiceProvider 类名数组（顺序敏感，前一个 Provider 的 boot 可能依赖后一个的 register 绑定）
- `$helpers`：7 个 helpers 文件路径数组（通过 `require_once` 加载，每个文件用 `function_exists` 守卫）
- `$booted`：是否已启动（防止重复 bootstrap）
- `$terminatingCallbacks[]`：终止回调数组

**关键方法**：
- `getInstance()` / `__construct()`：单例实现 + 加载 helpers + 执行 bootstrap
- `bootstrap()`：按序调用 `Provider::register()`（绑定服务）→ `Provider::boot()`（启动逻辑）
- `run()`：捕获 Request、调用 Router::dispatch、异常兜底（Whoops/错误页）
- `handleException()`：`APP_DEBUG=true` 时启用 Whoops PrettyPageHandler，否则渲染错误页 + `Log::error()`
- `terminating($cb)` / `terminate()`：注册/执行终止回调（通过 `register_shutdown_function` 保证执行）

### 4.3 Router（路由器）**`core/Router.php`**

**内部状态**：
- `$routes[]`：完整路由表，每条包含 `method / pattern / handler / name / middleware`
- `$namedRoutes[]`：name → index 索引
- `$compiledCache[]`：pattern → 已编译正则（请求内缓存）
- `$middlewareGroups[]`：组名 → 中间件列表（web / admin / api）
- `$modelBindings[]`：参数名 → `Class:field`
- 动态组状态：`groupPrefix / groupMiddleware / groupNamespace / groupNamePrefix`
- `$currentGroup` / `$currentGroupStack`：嵌套分组支持

**方法**：
- `get/post/put/delete/match` — 注册路由
- `group($attributes, $callback)` — 分组（支持嵌套）
- `middlewareGroup($name, $list)` — 注册中间件组
- `model($param, $class, $field)` — 路由模型绑定
- `middleware($name, $handler)` — 全局中间件或追加
- `name($name)` — 给最近路由命名
- `dispatch($method, $path)` — 核心分发
- `route($name, $params)` — URL 反向生成
- `loadRoutes($file)` — require 路由文件

**`dispatch()` 关键步骤**：
1. 规范化 path（去除尾斜杠、合并多斜杠到 `/`、空路径变为 `/`）
2. HTTP 方法覆写：POST 携带 `_method` 或 `X-HTTP-Method-Override` 头时覆写为 DELETE/PUT/PATCH
3. 对每条路由：
   - 命中 `compiledCache[$pattern]` 则直接使用，否则调用 `compilePattern()` 生成正则并缓存
   - 使用 `preg_match(#^pattern$#u, $path, $matches)`（Unicode 模式）
   - 参数用命名捕获 `(?P<name>[^/]+)`，`$matches['name']` 自动取值
   - 若路由名在 `$modelBindings` 中（如 `slug → Post:slug`），根据 `$params['slug']` 查询 `Post::findBy('slug', ...)` 并把 `$params['slug']` 替换为模型实例
   - `Conditional::set($routeName, $params)` 注入条件状态，供主题 `is_single()` 等使用
   - `expandMiddleware($middleware)`：先查 `$middlewareGroups`（web/admin/api）合并入列表，再对每个中间件解析 `param:arg1,arg2` 形式得到 `$args`
   - 中间件逐个执行，任一返回 `Response` 则短路（`return $mwResp;`）
4. `invokeHandler($handler, $params)`：反射检查方法签名，若第一个参数类型是 `Response` 以外的类（如 `Request/Session`）则从容器解析；`array $params` 参数按名匹配传入
5. 全部路由未命中 → 404：尝试 `theme_view('404')` 或返回默认 404 Response

**`compilePattern()` 关键实现**：
- `{id}` → `(?P<id>[^/]+)`
- `{id:\d+}` → `(?P<id>\d+)`（支持自定义约束）
- 使用 `preg_replace_callback` 对每个 `{name(:constraint)?}` 替换
- 所有路由的正则都以 `#^` 开头、`$#u` 结尾，保证整段匹配

### 4.4 Request（请求抽象）**`core/Http/Request.php`**

- 封装 `$_GET / $_POST / $_SERVER / $_FILES / $_COOKIE`
- `$method` 与 `$path` 为 `readonly` 属性（PHP 8.2 特性）
- `capture()` 静态工厂方法：读取 `REQUEST_METHOD` + `REQUEST_URI` 解析 path
- `input($key, $default)`：依次查询 POST/GET，支持点号层级 `input('user.name')`
- `all()` / `only($keys)` / `except($keys)` 批量获取
- `file($key)`：返回 `$_FILES[$key]` 数组，含 `name/type/tmp_name/error/size`
- `cookie($key)` / `server($key)` / `ip()`：支持 `X-Forwarded-For` 多段解析（取第一个公网 IP）
- `ajax()` / `expectsJson()`：通过 `HTTP_X_REQUESTED_WITH` 或 `Accept: application/json` 判定
- `old($key)`：从 Session `_old_input` 读取上一次闪存输入
- `bearerToken()`：从 `Authorization: Bearer xxx` 头提取

### 4.5 Response（响应抽象）**`core/Http/Response.php`**

- 链式 API：`setStatus($code)` / `header($k,$v)` / `withHeaders([...])` / `setBody($body)`
- `setContentType($type, $charset)` 默认 `text/html; charset=UTF-8`
- `json($data, $status)`：自动 `json_encode(JSON_UNESCAPED_UNICODE)` 并设置 Content-Type
- `redirect($url, $status)` / `redirectRoute($name, $params)`：内部调用 `Router::route()` 反向生成
- `send()`：先发送 HTTP 状态码（`header()` + `http_response_code()`），再遍历 headers 发送，最后 echo body
- 终止时触发 `register_shutdown_function` 等待 `terminatingCallbacks` 执行
- `getBody()` / `status()` / `headers()` 读取器

### 4.6 Session（基于文件的 Session）**`core/Http/Session.php`**

- 构造时自动 `configure()`：检测 `session_status()` 避免重复启动
- 支持 `file` 驱动：设置 `session.save_handler=files` + `session.save_path=storage/sessions`
- 安全标志：
  - `session.cookie_httponly=1` 阻止 JS 读取
  - `session.cookie_samesite=Lax` 防御 CSRF 跨站携带
  - `session.cookie_secure=1`（HTTPS 环境）加密传输
  - `session.sid_length=48` + `session.sid_bits_per_character=6` 强熵 ID
  - `session.use_strict_mode=1` 防 session fixation
- Cookie 名：`blog_session`（可通过 `config('session.cookie')` 修改）
- `csrfToken()`：`bin2hex(random_bytes(32))` 生成 64 位十六进制 token
- `verifyCsrf()`：`hash_equals` 防时序攻击
- `flash($key, $value)`：存入 `_old_input[key]` 下次请求可见
- `flashInput($input)`：整个表单输入闪存（供 `old()` 读取）
- `pull($key)`：取后删（fetch-once 语义）

### 4.7 FormRequest（表单请求验证）**`core/Http/FormRequest.php`**

- 抽象基类，子类实现 `rules()` 和可选的 `messages()`
- **14 种内置规则**：`required|string|integer|numeric|email|url|min|max|in|not_in|confirmed|regex|date|alpha|alpha_num`
- 规则解析：`max:200` → 规则名 `max`，参数 `['200']`（按 `:` 拆分）
- 验证流程（`passes()`）：
  1. 遍历 `rules()` 每个字段
  2. 按 `|` 拆规则，依次验证
  3. 失败立即记 `$errors[$field] = $messages[$field.rule] ?? 默认消息`，停止该字段后续规则
  4. 通过的字段加入 `$validated`
- `validate()`：失败自动 `flashInput` + `flash(error)` + `redirect(Referer)` + `exit`
- `validated()` 返回通过验证的数据
- `passes()` / `fails()` / `errors()` 状态查询
- `validateMin/Max`：字符串用 `mb_strlen`，数组用 `count`，数字用本身

### 4.8 Config（配置管理）**`core/Support/Config.php`**

- 实现 `get($key, $default)`：支持 `.` 层级访问，`explode('.', $key)` 逐级递归取值
- 所有配置缓存到内存 `$items`（首次访问时一次性加载文件）
- 数组访问支持 `config('database.sqlite.path')` 深入读取
- `has($key)` 判定键是否存在
- 每个文件自动用 `require` 加载，要求返回数组

### 4.9 Path 全局函数辅助

**`helpers.php`**：`app()`, `config()`, `base_path()`, `app_path()`, `resource_path()`, `public_path()`, `storage_path()`, `views_path()`, `plugins_path()`, `themes_path()`, `url()`, `e()`, `theme_view()`, `view()`, `response()`, `database_path()`, `themes_path()` 等。每个函数用 `function_exists` 防止重复注册，确保 7 个 helpers 文件可按序叠加。

---

## 5. 路由与中间件系统

### 5.1 MiddlewareInterface
```php
interface MiddlewareInterface {
    public function handle(array $params, array $args = []): ?Response;
}
```
返回 `null` 继续链，返回 `Response` 短路。

### 5.2 7 个内置中间件详解

**AuthMiddleware**
- 检查 `AuthManager::check()`，未登录重定向 `/login`
- 保存当前 URL 到 Session `_url_redirect`，登录成功后回跳

**GuestMiddleware**
- 已登录用户访问登录/注册页时重定向 `/admin`

**AdminMiddleware**
- 调用 `AuthManager::can('read')`，不通过返回 403

**CsrfMiddleware**
- 校验 `_token` / `_csrf` / `X-CSRF-Token` 请求头
- `hash_equals` 防时序攻击
- GET 方法自动跳过

**CorsMiddleware**
- 可配置允许源/方法/头/凭证
- 处理 OPTIONS 预检请求，返回 204

**SecurityHeadersMiddleware**
- CSP（Content-Security-Policy）完整配置
- HSTS（Strict-Transport-Security）max-age=31536000
- X-Frame-Options / X-Content-Type-Options / X-XSS-Protection
- Referrer-Policy: strict-origin-when-cross-origin
- Permissions-Policy: camera, microphone, geolocation

**ThrottleMiddleware**
- 基于缓存（key: `throttle:{ip}:{key}`）的滑动窗口限流
- 支持 `throttle:maxAttempts,decayMinutes` 参数化
- 返回 429 + `Retry-After` 头

### 5.3 参数化中间件
格式：`throttle:60,1` → `ThrottleMiddleware` 接收 `$args = ['60', '1']`。

### 5.4 中间件组
在 `RouteServiceProvider::boot()` 中注册：
```php
$router->middlewareGroup('web', ['throttle:60,1', 'cors', 'security']);
$router->middlewareGroup('admin', ['web', 'auth', 'admin', 'csrf']);
$router->middlewareGroup('api', ['web', 'api_throttle']);
```

---

## 6. 数据库层

### 6.1 Connection（连接管理）**`core/Database/Connection.php`**

- 读取 `config/database.php` 判断 `driver`（`sqlite` / `mysql`）
- SQLite：开启 WAL 模式 + 外键约束 + busy timeout
- MySQL：开启 STRICT 模式 + `utf8mb4` + 时区设置
- 提供 `pdo()` / `table()` / `raw()` / `statement()` / `execute()`
- 支持 `:memory:`（测试用）
- `isSqlite()` / `isMysql()` 驱动判断

### 6.2 QueryBuilder（查询构造器）**`core/Database/QueryBuilder.php`**

**核心特性**：
- **不可变设计**：每个链式方法 `clone $this` 返回新实例
- 命名占位符 `:col_0`, `:col_1` 防 SQL 注入
- `__clone()` 深拷贝内部数组
- 支持 `select / from / where / orWhere / whereIn / whereNotIn / whereNull / whereNotNull / join / leftJoin / orderBy / groupBy / having / limit / offset`
- 支持 `insert / update / delete / count / exists / pluck`
- 支持 `transaction(callable)` 事务
- 支持 `paginate()` / `simplePaginate()`
- 支持 `setEagerLoad()` 传递预加载列表
- 支持 `_withoutScope` 标记跳过全局作用域

**关键实现**：
- `buildSelect()` 拼接 SQL，使用参数绑定
- `where()` 自动生成唯一占位符名（基于列名+序号）
- 子查询支持：`whereIn` 接收 QueryBuilder 实例
- 软删除过滤：自动追加 `deleted_at IS NULL`（带 `_withoutScope` 标记时跳过）

### 6.3 Model（活动记录基类）**`core/Database/Model.php`**

**属性**：
- `$table`, `$primaryKey`, `$casts`, `$softDelete`, `$relations`, `$eagerLoad`
- `$attributes` / `$original` / `$changes`
- `$globalListeners[event => [callable]]` 模型事件监听
- `$globalScopes[]` 查询作用域

**方法**：
- `__get()`：优先返回 `relations`，否则调用关联方法懒加载
- `getAttribute()`, `setAttribute()`, `fill()`, `toArray()`（包含关联 + 嵌套关联）
- `belongsTo / hasMany / hasOne / belongsToMany`：定义关联
- `with()` / `withEager()` + `processEagerLoad()`：预加载
- `scope()` + `applyGlobalScopes()`：查询作用域
- `softDelete` / `restore()` / `forceDelete()` / `withTrashed()` / `onlyTrashed()`
- `on($event, $callback)` / `fireModelEvent()`：模型事件
- `query()`, `find()`, `findBy()`, `all()`, `create()`, `save()`, `delete()`

**事件**：`creating / created / updating / updated / saving / saved / deleting / deleted`。

### 6.4 Relation 基类 **`core/Database/Relations/Relation.php`**

4 个实现，都实现 `eagerLoad()` 方法批量预加载（解决 N+1 查询）：

**BelongsTo**
- `buildQuery()`: where foreign_key = owner_key
- `eagerLoad()`: 收集所有 foreign_key 值 → 单次 IN 查询 → 映射回父模型
- 例：Post → User（author_id → id）

**HasMany**
- `buildQuery()`: where foreign_key = local_key
- `eagerLoad()`: 按 foreign_key 分组映射
- 例：User → Comment（user_id → id）

**HasOne**
- 同 HasMany，但返回单个模型
- `eagerLoad()`: 一对一映射

**BelongsToMany**
- 涉及 pivot 表 join
- `eagerLoad()`: pivot join + 按 foreign_key 分组
- 例：Post → Tag（post_tag 中间表）

### 6.5 Migration / Migrator

**Migration 抽象基类**
- `$pdo` + `$connection`
- `up()` / `down()` + 辅助 `exec()` / `isSqlite()` / `isMysql()`

**Migrator 引擎**
- 自动发现 `database/migrations/` 下的文件
- 文件名推导类名：`20260101_000001_create_users_table.php` → `CreateUsersTable`
- `run()` 执行 pending，记录 batch
- `rollback($steps)` 支持指定步数或回滚最后一批
- `status()` 返回所有迁移状态
- SQLite/MySQL 两种建表语句

### 6.6 4 个迁移文件
1. `initial_schema`：users/posts/categories/tags/post_tag/comments/media/options/sessions/migrations
2. `menu_items`：menu_items 表
3. `featured_image_and_revisions`：posts 加 featured_image_id / meta
4. `audit_logs_and_jobs`：audit_logs / jobs / plugin_activations

---

## 7. 认证与权限系统

### 7.1 AuthManager **`core/Auth/AuthManager.php`**

- `attempt($credentials)`：验证 + 写入 Session `user_id`
- `check()` / `guest()` / `user()` / `id()`
- `login($user)` / `logout()`（清除 Session）
- `can($capability, $args)`：细粒度权限
  - 先查 `Capability::has($role, $capability, $args)`
  - 再对 `editor_admin` / `editor_writer` 做所有权校验
- `can_or_403($capability)`：不通过直接 abort

### 7.2 Capability（权限矩阵）**`core/Auth/Capability.php`**

5 级角色权限矩阵（`self::MATRIX`）：

```
super_admin   → ['*']  （通配所有能力）
senior_admin  → read, dashboard, edit_posts, edit_others_posts,
                delete_posts, publish_posts, moderate_comments,
                manage_categories, manage_users, upload_media
editor_admin  → read, dashboard, edit_posts, edit_others_posts,
                delete_posts, publish_posts, upload_media
editor_writer → read, dashboard, edit_posts, upload_media
visitor       → read
```

`has()` 支持 `*` 通配、参数化校验（如 `edit_posts` 校验 `author_id === current_user_id`）。

### 7.3 LoginRateLimiter **`app/Services/LoginRateLimiter.php`**

- 基于缓存键 `login_fail:md5(ip|username)`
- 5 次失败 → 锁定 15 分钟
- `remainingAttempts()` / `isLocked()` / `recordFailure()` / `clear()`

---

## 8. 钩子 / 事件系统

### 8.1 Action **`core/Hook/Action.php`**

- `$callbacks[hook][priority] = [callable, ...]`
- `add($name, $callback, $priority)`：按 priority 分组
- `run($name, ...$args)`：按 priority 升序执行，传引用参数
- `has($name)`：是否注册

### 8.2 Filter **`core/Hook/Filter.php`**

- 同 Action 结构
- `apply($name, $value, ...$args)`：链式传递
- 支持 null 短路（第一个回调返回非 null 值则停止）

### 8.3 EventDispatcher **`core/Events/EventDispatcher.php`**

- 面向对象事件发布/订阅
- `listen($event, $listener)`：注册监听器（支持数组 `[class, method]`）
- `dispatch($event)`：解析监听器类（容器），调用 `handle()`
- 支持 `stopPropagation()` 阻止后续监听器

---

## 9. 缓存系统

### 9.1 CacheInterface **`core/Cache/CacheInterface.php`**

完整接口：`get / set / forever / delete / clear / flush / has / remember / tagged / flushTag / lock / increment / decrement / driver`

### 9.2 CacheManager **`core/Cache/CacheManager.php`**

- 驱动工厂：`createDriver($name)` 按类型创建 File/Redis/Memcached/Apcu/Array
- Redis 扩展不可用时自动降级为 FileCache
- 代理模式：默认驱动方法调用透传
- `driver($name)` 获取指定驱动

### 9.3 FileCache（默认文件缓存）**`core/Cache/FileCache.php`**

- 基于 `storage/cache/` 的文件系统存储
- `path($key)`：key 前 2 字符作为子目录（避免单目录文件过多）
- 序列化存储 + TTL 过期检查
- **标签实现**：tag 名 → 存储相关 key 列表 → `flushTag()` 批量删除
- **计数器持久化**：`__counters.dat` 文件缓存
- `increment/decrement` 原子操作

### 9.4 RedisCache **`core/Cache/RedisCache.php`**

- 支持 phpredis 扩展和 predis/predis 库
- SET/SETNX 原子操作
- 标签通过 Redis Set 实现
- `increment/decrement` 使用 INCRBY

### 9.5 PageCache **`core/Cache/PageCache.php`**

- 整页静态缓存，存储在 `storage/framework/page/`
- **绕过条件**：POST 请求 / admin / api / 登录用户
- 缓存键：`md5(path|query)`
- 缓存内容：JSON 元数据 + "\n" + HTML body
- `flush($pattern)` 支持模式匹配清理
- 返回 `X-Page-Cache: HIT` 响应头

### 9.6 FragmentCache 辅助函数

- `cache_fragment($key, $ttl, $callback)`：缓存模板片段
- `cache_forget($key)`：清除片段缓存
- 存储键前缀 `fragment:`

### 9.7 CacheLock **`core/Cache/CacheLock.php`**

- 防缓存击穿
- `acquire()`：`set()` 原子性，token 随机值
- `release()`：仅当前持有者可释放
- `block($waitMs)`：阻塞式等待，50ms 间隔重试
- `__destruct()`：自动释放防泄漏

---

## 10. 视图与主题系统

### 10.1 ThemeManager **`core/View/ThemeManager.php`**

**核心能力**：
- 父子主题支持（theme.json 的 `parent` 字段）
- 模板层级查找（child → parent → default）
- 主题切换、ZIP 上传、删除
- 主题选项（`theme_{theme}_{key}` 存储在 options 表）
- Widget 区域 / 菜单位置 / 页面模板声明（从 theme.json 读取）
- `partial($name)` 支持数据栈嵌套（`$dataStack` 数组）
- `render($template)` 触发完整钩子链：`template_redirect` → `template_include` → 模板查找 → `template_rendered` → `template_output`

**关键实现**：
- `resolveActiveTheme()`：先查 options 表 `active_theme` 键，fallback 到 config
- `loadThemeFunctions()`：先加载父主题 functions.php → 子主题 functions.php
- `templateHierarchy()`：home → [home, index]；single → [single, index]
- `renderFile()`：`extract($data, EXTR_SKIP)` + ob_start/ob_get_clean
- `installFromZip()`：解压到临时目录 → 自动识别主题目录 → 移动到 themes/
- `deleteTheme()`：不允许删除当前激活主题
- `config($key)`：三级优先级（DB → theme.json options.default → 参数 default）

### 10.2 AssetManager **`core/View/AssetManager.php`**

- `enqueueStyle / enqueueScript`：CSS/JS 排队
- `dequeueStyle / dequeueScript`：移除
- **拓扑排序**：`sortDeps()` 递归深度优先访问，保证依赖顺序
- `addVersion()`：版本指纹（?ver=xxx）cache-busting
- `tryMinify()`：生产环境自动尝试 `.min.` 文件
- `renderStyles()` / `renderScripts()` / `renderHeaderScripts()`

### 10.3 Widget 系统

**Widget 抽象基类**
- 属性：`$id`, `$name`, `$description`
- `form()` 后台表单 HTML
- `update()` 更新配置
- `render($instance)` 前台输出（抽象）

**WidgetManager**
- `registerSidebar($config)`：注册 Widget 区域
- `registerWidget($class)`：注册 Widget 类
- `renderSidebar($id)`：加载实例 + 包装输出
- 实例从 DB `widget_instances` 选项加载
- 渲染时支持 `before_widget / after_widget / before_title / after_title` 包装
- 无实例时触发 `dynamic_sidebar_fallback` 钩子

### 10.4 MenuManager

- `registerLocation($location, $description)` 注册菜单位置
- `render($args)`：递归渲染层级菜单
- 默认 fallback 输出分类列表
- 高亮当前 URL（`isCurrentUrl()` 前缀匹配）
- `target="_blank"` 添加 `rel="noopener"`

### 10.5 Shortcode

- 正则解析 `[tag attr="value" attr='value' attr=value]` 格式
- 支持双引号 / 单引号 / 无引号属性
- `parseAttrs()` 分三轮提取：双引号 → 单引号 → 无引号 → 标记属性
- 未注册的 shortcode 原样输出

### 10.6 Conditional

- 基于路由名的条件判断
- `set($routeName, $params)` 在 Router 分发时注入
- `reset()` 清空状态
- 支持 `isHome / isSingle / isPage / isCategory / isTag / isSearch / is404 / isAuthor / isArchive / isFeed / isAdmin`
- `isCategory('tech')` 支持按 slug 参数过滤

### 10.7 DebugBar

- 数据收集：`logQuery / logHook / logTemplate`
- `summary()` 返回统计数据（供后台头像菜单使用）
- 仅 debug 模式启用
- 不再独立渲染浮动按钮，完全嵌入到后台头像菜单

### 10.8 ImageProcessor

- 内置 3 种尺寸：thumbnail (150×150,crop) / medium (480,contain) / large (1200,contain)
- `addSize($name, $width, $height, $crop)` 自定义尺寸
- `generateSizes()`：GD 生成多尺寸缩略图
- 支持 JPEG/PNG/GIF/WebP
- PNG/GIF 保留透明通道（imagealphablending + imagesavealpha）
- 裁剪模式：居中裁剪（source ratio vs dest ratio 比较）
- `srcset()` 生成响应式图片属性

### 10.9 ViewRenderer

- 后台模板渲染（PHP 原生，独立于主题渲染器）
- `render($template, $data)`：`.` 作为目录分隔符
- 例：`view('admin.posts.index', [...])` → `resources/views/admin/posts/index.php`

---

## 11. 插件系统

**`core/Plugin/PluginManager.php`**

- 发现 `plugins/*` 下所有插件（读取主文件头注释 `Plugin Name` / `Version` / `Description` 等）
- `activate($name)` → 写入 `plugin_activations` 表 → 加载主文件 → `do_action('activated_plugin', $name)`
- `deactivate($name)` → 移除激活记录 → `do_action('deactivated_plugin', $name)`
- `uninstall($name)` → 完全删除目录
- `uploadFromZip($zipPath)` → 解压到 `plugins/` 下
- `boot()`：自动激活所有已激活的插件
- `activePlugins()` / `listPlugins()`：查询接口
- 幂等保护：`activate()` 已激活的插件不会重复激活

**示例插件 `plugins/hello-dolly/hello-dolly.php`**：
- 通过 `add_filter('the_content', ...)` 在每篇文章底部显示随机格言
- 通过 `add_action('wp_head', ...)` 添加样式
- 通过 `add_filter('footer_text', ...)` 修改页脚

---

## 12. 队列 / 邮件 / Webhook / SEO / 审计 / 日志 / i18n

### 12.1 Queue **`core/Queue/Queue.php`**

- 3 种驱动：`sync`（立即执行）/ `file`（缓存队列）/ `database`（jobs 表）
- `push($job, $data, $queue, $delay)`：支持延迟
- `work($queue, $maxJobs)`：消费循环，失败记日志
- `popFromDatabase()`：按 `available_at <= now` 取任务
- 同步驱动：直接 `new $job($data)->handle()`
- 数据库驱动：失败降级为同步执行
- 文件驱动：缓存键 `queue:{name}:{id}`

### 12.2 Job 基类

- `$args` 构造参数
- `handle()` 抽象方法
- `getArgs()` 获取参数

### 12.3 EmailTemplate **`core/Email/EmailTemplate.php`**

- 主题覆盖优先（`{theme}/emails/{id}.php`）→ 系统默认（`resources/emails/{id}.php`）
- 无模板时返回内置 fallback HTML（含站点名）
- 3 个内置模板：comment_notification / register_welcome / password_reset
- `register()` 注册模板元信息
- `render()` 使用 `extract()` + ob 缓冲

### 12.4 Webhook **`core/Webhook/Webhook.php`**

- 事件驱动的 HTTP POST 通知
- 支持 `*` 通配订阅所有事件
- 端点配置存储在 Option `webhook_endpoints`（JSON 数组）
- 非阻塞发送：`fsockopen` + `stream_set_blocking(false)`
- 降级：fsockopen 失败改用 `stream_context_create`
- payload 包含：event / payload / timestamp / source

### 12.5 Sitemap **`core/SEO/Sitemap.php`**

- 自动生成 XML sitemap（首页 + 文章 + 分类 + 标签 + 页面）
- 每类 URL 设置不同 priority（首页1.0, 文章0.8, 分类0.6, 标签0.5, 页面0.7）
- `robotsTxt()` 动态生成（支持后台配置 Disallow）
- `breadcrumbs()` 生成 HTML + JSON-LD 结构化数据（Schema.org BreadcrumbList）

### 12.6 AuditLog **`core/Security/AuditLog.php`**

- 记录字段：action / description / user_id / username / ip / user_agent / context(JSON)
- `record()` 自动获取当前 Request 和 User
- `query($filters, $page, $perPage)`：支持 action/user_id/ip/日期范围过滤
- `cleanup($days)`：清理指定天数前的记录
- 失败时降级写 Log::error()
- 在 `EnhancedServiceProvider` 中通过 `add_action` 绑定 7 个审计钩子

### 12.7 Log **`core/Log/Log.php`**

- 8 级：DEBUG / INFO / NOTICE / WARNING / ERROR / CRITICAL / ALERT / EMERGENCY
- 按天滚动：`storage/logs/{Y-m-d}.log`
- 生产环境丢弃 DEBUG 级别
- 结构化日志（JSON）可切换（`config('log.structured')`）
- 包含 `request_id` 关联
- 使用 `LOCK_EX` 防止并发写入冲突

### 12.8 Translator **`core/i18n/Translator.php`**

- 语言文件加载顺序：`resources/lang/{locale}.php` → 主题 lang → 插件 lang
- `translate($key, $params)` 支持 `:name` 参数替换
- 默认 `zh_CN`
- 缓存到 `$translations` 数组
- 每个文件应为 `return ['key' => 'value'];`

---

## 13. 应用层 app/

### 13.1 控制器清单

**Web 控制器**

| 控制器 | 路由前缀 | 核心方法 | 说明 |
|--------|----------|----------|------|
| HomeController | `/`, `/page/{page}` | `index()` | 首页：文章列表+分页+分类+侧边栏数据，触发 `home_loaded` action |
| PostController | `/posts/{slug}` | `show()` | 文章详情：HTML 渲染+阅读次数（Session 防重复）+相关文章+`post_loaded` action |
| PageController | `/{slug}` | `show()` | 独立页面：从 posts 表查找 post_type='page' 的记录 |
| CategoryController | `/category/{slug}` | `show()` | 分类归档：分类信息+该分类下文章+分页 |
| TagController | `/tag/{slug}` | `show()` | 标签归档：标签信息+带标签文章+分页 |
| SearchController | `/search` | `index()` | 全文搜索：LIKE 查询 title/content，结果高亮关键词 |
| CommentController | `/posts/{slug}/comments` | `store()` | 提交评论：Markdown 过滤+状态审核+邮件通知队列 |
| FeedController | `/feed`, `/sitemap.xml`, `/robots.txt` | `rss()`, `sitemap()`, `robots()` | RSS 2.0/Sitemap/Robots，含 ETag/Last-Modified 缓存头 |
| HealthController | `/health` | `check()` | 健康检查：DB 连通性+磁盘空间+版本信息 JSON |
| AuthController Web | `/login`, `/register` | `loginForm()`, `registerForm()` | 展示表单（真正的登录逻辑在 Admin\AuthController） |

**Admin 控制器（所有自动应用 admin 中间件组）**

| 控制器 | 路由前缀 | 核心方法 | 关键实现细节 |
|--------|----------|----------|------|
| DashboardController | `/admin` | `index()` | 统计卡片：文章/评论/用户数+按状态分组+最近 5 条 |
| PostController | `/admin/posts/*` | `index/create/store/edit/update/delete` | 注入 PostService，通过 PostData DTO 处理表单；store 用事务包裹；失败闪存原始输入 |
| CategoryController | `/admin/categories/*` | `index/store/update/delete` | 用 Slugify 生成/唯一化 slug；增删改后清 `nav_menu` 缓存 |
| TagController | `/admin/tags/*` | `index/store/delete` | 同上；删除时同步清理 post_tag 中间表关联 |
| MediaController | `/admin/media/*` | `index/upload/delete` | 白名单扩展名（jpg/png/webp/svg/pdf/doc/mp4）；随机文件名 bin2hex(16)+ 年月目录 |
| CommentController | `/admin/comments/*` | `index/approve/markSpam/delete` | 按状态筛选（pending/approved/spam）；markSpam 调用自定义方法名映射 |
| UserController | `/admin/users/*` | `index/create/store/edit/update/delete` | 5 级角色选择；删除时禁止删除自己；密码用 password_hash(bcrypt) |
| ThemeController | `/admin/themes/*` | `index/activate/upload/delete` | 调用 ThemeManager；upload 仅接受 zip；activate 写 Option 表 |
| PluginController | `/admin/plugins/*` | `index/activate/deactivate/upload/delete` | 调用 PluginManager；delete 前检查是否激活 |
| SettingController | `/admin/settings` | `index/save` | 11 项站点设置；遍历 keys 用 Option::set 原子写；保存后触发 `settings_saved` action |
| AuthController | `/auth/login`, `/auth/register` | `loginForm/login/logout/registerForm/register` | 登录用 LoginRateLimiter 滑动窗口 5次/15分钟；支持用户名或邮箱登录；密码错误剩余次数提示 |

**Api 控制器（自动应用 api 中间件组）**

| 控制器 | 路由 | 说明 |
|--------|------|------|
| Api\PostController | `GET /api/posts` | JSON 文章列表：支持分页（max 50）、category_id/tag_id 过滤、关键词搜索；返回 `{data, meta}` 结构 |
| Api\PostController | `GET /api/posts/{slug}` | JSON 文章详情：含 category/tags/author 嵌套对象；返回 404 当文章未发布或不存在 |
| Api\TaxonomyController | `GET /api/taxonomies` | JSON 分类+标签聚合列表 |

### 13.1.1 控制器通用模式

所有控制器遵循的统一约定：
- **依赖注入**：构造函数接收必要服务（如 `PostService`），通过容器自动解析
- **权限前置**：每个写操作入口第一行都是 `can_or_403('capability')`
- **错误处理**：用 `try/catch(\Throwable)` 包裹业务逻辑，记录 `Log::error` 并闪存友好消息
- **表单重定向**：成功后 `Session::flash('success', msg)` + `redirect(route(...))`
- **数据闪存**：失败时 `$sess->flashInput($request->all())` + `redirect(back)`
- **视图参数**：统一带 `pageTitle` 键供 `<title>` 使用
- **响应工厂**：通过全局函数 `view()` / `theme_view()` / `response()` / `redirect()` 创建

### 13.2 模型详解

**User 模型**
- 字段：id/username/email/password/role/display_name/status/bio/url/created_at/updated_at/deleted_at
- 方法：displayName(), avatarUrl(), postCount()
- 关系：posts() (hasMany), comments() (hasMany)
- 密码使用 password_hash(bcrypt)

**Post 模型**
- 字段：id/title/slug/content_md/content_html/excerpt/status/category_id/author_id/cover/featured_image_id/meta/views/published_at/seo_title/seo_description/created_at/updated_at/deleted_at
- casts：`id/author_id/category_id/views → int`（查询后自动类型转换）
- 软删除：`$softDelete = true`，自动过滤 `WHERE deleted_at IS NULL`
- 静态查询作用域：
  - `published()`：`WHERE status = 'published' AND published_at <= NOW()`
  - `publishedCount()`：统计已发布文章数
- 方法：
  - `html()`：直接返回 `content_html`（由 PostService 在保存时预转换，避免每次渲染重复 Parsedown）
  - `excerpt($length)`：优先使用 excerpt 字段，否则截取 content_html，`strip_tags` 后截断
  - `url()`：调用 `route('post.show', ['slug' => $this->slug])` 反向生成路由
  - `related($limit)`：同 category_id 排除自身，按 `published_at DESC` 排序，取 `$limit` 条
  - `incrementViews()`：`UPDATE posts SET views = views + 1 WHERE id = ?`（原子操作，不触发模型事件）
  - `findBy($field, $value)`：QueryBuilder `WHERE $field = ?` 首条
- 关系：
  - `author()` → belongsTo User（用 `author_id` 关联 `users.id`）
  - `category()` → belongsTo Category
  - `tags()` → belongsToMany Tag（中间表 `post_tag`）
  - `comments()` → hasMany Comment（用 `id` 作为 `post_id` 外键）

**Category / Tag 模型**
- Category 字段：id/name/slug/description/parent_id/order_index/created_at/updated_at
- Tag 字段：id/name/slug/created_at/updated_at
- Category 额外方法：`posts()` hasMany，支持 `parent_id` 自关联
- Tag 额外方法：`posts()` belongsToMany Tag

**Comment 模型**
- 字段：id/post_id/parent_id/author_name/author_email/author_id/ip/content/status/created_at/updated_at
- 状态：`pending / approved / spam`（默认 pending 需审核）
- 方法：
  - `html()`：用 Parsedown 转换 `content` 为 HTML（评论 Markdown 支持）
  - `replies()`：`WHERE parent_id = ?` 子回复
  - `nestedReplies()`：递归嵌套结构构建树
  - `depth()`：根评论为 0，回复为 1，以此类推
- 支持嵌套回复（parent_id 自关联），主题模板中用递归 `<?php foreach ($comment->nestedReplies() as $child): ?>`

**Media 模型**
- 字段：id/filename/original_name/path/mime_type/size/width/height/user_id/created_at/updated_at
- 方法：`url()`（`/public/{path}`）、`thumbnailUrl($size)`（`{path}.{size}.jpg`）、`humanSize()`（`bytes → KB/MB/GB`）、`isImage()`（按 mime 前缀判定）
- 自动通过 ImageProcessor 生成 3 种尺寸缩略图

**Option 模型**
- 全局 KV 存储，带内存级 `self::$cache` 持久化（同一请求内只查一次 DB）
- 自动 JSON 检测：值以 `{` 开头时 `json_decode` 为数组返回
- `get($key, $default)`：查不到返回默认值，缓存命中跳过 DB
- `set($key, $value)`：
  - 存在则 UPDATE（value, updated_at）
  - 不存在则 INSERT（key_name, value, autoload=1, 时间戳）
  - 写入后同步更新 `self::$cache`
- 用于：站点设置、激活主题、Webhook 端点、SEO 配置等

### 13.3 服务层

**PostService**（`app/Services/PostService.php`）
- 依赖：Parsedown + Connection（PDO 原始操作，绕过 QueryBuilder 以利用事务）
- `create(PostData $dto, int $authorId)`：
  1. `$dto->toArray()` → 补 `author_id / created_at / updated_at`
  2. 若 slug 为空 → `Slugify::make($title)` 生成；若 slug 显式指定 → `Slugify::unique($slug, 'posts', 'slug')` 唯一化
  3. `Parseddown::text($content_md)` 转换 Markdown → HTML（安全模式已在 provider 中启用）
  4. `DB::beginTransaction()` 内执行 insert + `syncTags($postId, $tagsString)`
  5. `do_action('post_saved', $postId, $data, false)` 触发钩子
  6. 返回新文章 ID
- `update($id, $dto)`：
  1. 查找文章，处理 `published_at` 变更（空则保留原值）
  2. slug 变更时重新唯一化
  3. 内容重新经 Parsedown 转换
  4. 事务内 update + syncTags
  5. `do_action('post_saved', $id, $data, true)`（isUpdate=true）
- `delete($id)`：
  1. 事务内 `DELETE FROM posts WHERE id = ?`
  2. 清理 `post_tag` 中间表关联
  3. `do_action('post_deleted', $id)`
  4. 清 `nav_menu` / `sidebar.recent` 缓存

**`syncTags()` 实现**：
- `$tags` 为逗号分隔字符串 → `array_map('trim', explode(',', $tags))`
- 先查所有存在的 tag（按 name 匹配），不存在则创建
- `DELETE FROM post_tag WHERE post_id = ?` 后批量 `INSERT INTO post_tag (post_id, tag_id) VALUES (?,?),(?,?)...`（一次 SQL）

**PostData DTO**（`app/DTO/PostData.php`）
- 封装 11 个表单字段（title/slug/content_md/excerpt/cover/category_id/status/seo_title/seo_description/published_at/tags）
- `fromRequest($request)`：从 Request 输入映射，status 白名单限定在 `draft/published/archived`
- `validate()`：当前仅校验 title 必填（后续可扩展）
- `toArray()`：DB 写入数组（排除 tags，由 PostService::syncTags 独立处理）
- 非 DB 字段的 `tags` 标记为 `@var string`，仅用于跨方法传递

**Slugify**（`app/Support/Slugify.php`）
- `make($text, $prefix)`：
  - 空字符串 → `{prefix}-{random_hex}`
  - 纯中文（`\x{4e00}-\x{9fa5}` 范围匹配）→ `bin2hex(random_bytes(4))`（8 位十六进制，避免中文 URL 乱码）
  - 其他 → `preg_replace([^\pL\d]+, '-')` → iconv 音译为 ASCII → `preg_replace([^a-z0-9\-])` → `strtolower` → 去首尾 `-`
- `unique($slug, $table, $column, $exceptId)`：
  - 循环查询 `$qb->where($col, '=', $slug)`，冲突则追加 `-2, -3...`

**LoginRateLimiter**（`app/Services/LoginRateLimiter.php`）
- 基于缓存（CacheInterface）实现滑动窗口
- 键设计：
  - 计数键：`login_fail:{md5(ip|username)}`
  - 锁定键：`login_fail:{md5(ip|username)}:lock`
- `recordFailure()`：计数 +1，达到阈值（5）后设置锁定键 15 分钟
- `remainingAttempts()`：`max(0, 5 - current_count)`
- `isLocked($ip, $username)`：检查锁定键是否存在
- `clear()`：登录成功或管理员解锁时调用

### 13.4 事件 / 任务 / 监听器

| 类 | 类型 | 说明 |
|----|------|------|
| `PostPublishedEvent` | Event | 文章发布事件，带 `post + isUpdate` |
| `RebuildSitemapJob` | Job | 异步重建 sitemap.xml |
| `SendCommentNotificationJob` | Job | 发送评论通知邮件 |
| `RebuildSitemapListener` | Listener | 监听事件 → 推队列 |
| `ClearPageCacheListener` | Listener | 监听事件 → 清 PageCache |

---

## 14. 路由定义 routes/

### 14.1 routes/web.php（20+ 条）

```
GET  /                              → HomeController@index         [name: home]
GET  /page/{page}                  → HomeController@index         [name: home.paged]
GET  /posts/{slug}                 → PostController@show          [name: post.show]
GET  /posts/{id}/edit              → PostController@edit          [name: post.edit]  [auth]
GET  /category/{slug}              → CategoryController@show      [name: category.show]
GET  /category/{slug}/page/{page}  → CategoryController@show      [name: category.paged]
GET  /tag/{slug}                   → TagController@show           [name: tag.show]
GET  /tag/{slug}/page/{page}       → TagController@show           [name: tag.paged]
GET  /author/{username}            → AuthorController@show        [name: author.show]
GET  /author/{username}/page/{page}→ AuthorController@show        [name: author.paged]
GET  /search                       → SearchController@index       [name: search]
GET  /feed                         → FeedController@rss           [name: feed.rss]
GET  /sitemap.xml                  → FeedController@sitemap       [name: sitemap]
GET  /robots.txt                   → FeedController@robots        [name: robots]
GET  /login                        → AuthController@loginForm     [name: login]  [guest]
POST /login                        → AuthController@login         [csrf]
GET  /logout                       → AuthController@logout        [name: logout]
GET  /register                     → AuthController@registerForm  [name: register]  [guest]
POST /register                     → AuthController@register      [csrf]
POST /posts/{slug}/comments        → CommentController@store      [name: comment.store]  [csrf]
GET  /health                       → HealthController@check
GET  /{slug}                       → PageController@show         [name: page.show]  (catch-all)
```

### 14.2 routes/admin.php（30+ 条）

```
GET|POST /auth/login|register|logout  → AuthController
GET      /admin                      → DashboardController@index
GET|POST /admin/posts                → PostController@index|store
GET      /admin/posts/create         → PostController@create
GET      /admin/posts/{id}/edit      → PostController@edit
POST     /admin/posts/{id}          → PostController@update
POST     /admin/posts/{id}/delete    → PostController@delete
... categories/tags/media/comments/users/themes/plugins/settings
```

所有后台路由自动应用 `admin` 中间件组（web + auth + admin + csrf）。

### 14.3 routes/api.php（2 条）

```
GET /api/posts       → Api\PostController@index
GET /api/taxonomies  → Api\TaxonomyController@index
```

自动应用 `api` 中间件组（web + cors）。

---

## 15. 配置与环境 config/

### 15.1 配置文件

| 文件 | 关键项 |
|------|--------|
| `config/app.php` | name, env, debug, url, timezone, locale, key, theme, allow_register, comment_moderation, posts_per_page, per_page |
| `config/database.php` | driver, sqlite.path, mysql.host/port/database/username/password |
| `config/cache.php` | default, drivers.file.path, drivers.redis.host/port/password/database |
| `config/session.php` | driver, lifetime, files, cookie, http_only, same_site, secure |
| `config/queue.php` | default, drivers.file.path, drivers.database.table |
| `config/theme.php` | themes_path, assets_path, cache_path |

### 15.2 .env 环境变量

```
APP_NAME / APP_ENV / APP_DEBUG / APP_URL / APP_KEY
DB_DRIVER / DB_HOST / DB_PORT / DB_DATABASE / DB_USERNAME / DB_PASSWORD
SESSION_DRIVER / SESSION_LIFETIME
CACHE_DRIVER / CACHE_TTL / CACHE_PREFIX
MAIL_DRIVER / MAIL_HOST / MAIL_PORT / MAIL_USERNAME / MAIL_PASSWORD / MAIL_ENCRYPTION
QUEUE_DRIVER
```

---

## 16. 数据库结构与迁移

### 16.1 表结构（共 14 张表）

| 表名 | 字段数 | 说明 |
|------|--------|------|
| `users` | 12 | id/username/email/password/role/display_name/status/bio/url/created_at/updated_at/deleted_at |
| `posts` | 15 | id/title/slug/content_md/content_html/excerpt/status/category_id/author_id/cover/featured_image_id/meta/views/published_at/seo_title/seo_description/created_at/updated_at/deleted_at |
| `categories` | 7 | id/name/slug/description/parent_id/order_index/created_at/updated_at |
| `tags` | 5 | id/name/slug/created_at/updated_at |
| `post_tag` | 3 | id/post_id/tag_id（多对多中间表） |
| `comments` | 11 | id/post_id/parent_id/author_name/author_email/author_id/ip/content/status/created_at/updated_at |
| `media` | 11 | id/filename/original_name/path/mime_type/size/width/height/user_id/created_at/updated_at |
| `options` | 5 | id/key_name/value/autoload/created_at/updated_at |
| `sessions` | 4 | id/data/expires/created_at |
| `migrations` | 3 | id/batch/migration |
| `menu_items` | 9 | id/label/url/parent_id/menu_slug/location/order_index/target/created_at |
| `audit_logs` | 8 | id/action/description/user_id/username/ip/user_agent/context/created_at |
| `jobs` | 6 | id/job/data/queue/attempts/available_at/created_at |
| `plugin_activations` | 3 | id/plugin_name/activated_at |

### 16.2 Schema 文件

- `database/schema.sqlite.sql`
- `database/schema.mysql.sql`

### 16.3 种子数据

`database/seeds/run.php`：3 分类（技术/生活/随笔）+ 6 标签 + 5 篇示例文章。

---

## 17. 主题系统详解

### 17.1 默认主题结构

```
public/themes/default/
├── theme.json                       ← 元数据
├── functions.php                    ← 入口
├── Widgets/RecentPostsWidget.php   ← 最新文章小工具
├── partials/
│   ├── header.php                   ← HTML 头部
│   ├── footer.php                   ← HTML 底部
│   └── sidebar.php                  ← 侧边栏
├── templates/
│   ├── home.php                     ← 首页
│   ├── single.php                   ← 文章详情
│   ├── page.php / archive.php       ← 页面/归档
│   ├── category.php / tag.php       ← 分类/标签
│   ├── author.php / search.php      ← 作者/搜索
│   ├── 404.php / error.php          ← 错误页
│   └── index.php                    ← 终极 fallback
└── assets/
    ├── css/style.css                ← 响应式样式
    ├── js/main.js                   ← 交互脚本
    └── img/favicon.svg
```

### 17.2 theme.json 元数据

```json
{
  "name": "Blog Default",
  "version": "1.0.0",
  "description": "默认主题",
  "author": "Blog CMS",
  "menus": { "primary": "主导航", "footer": "页脚" },
  "sidebars": { "sidebar-1": "主侧边栏", "footer-1": "页脚" },
  "options": { ... },
  "page_templates": { ... }
}
```

### 17.3 functions.php 关键行为

- 注册 `primary` / `footer` 菜单位置
- 注册 `sidebar-1` / `footer-1` Widget 区域
- `widgets_init` 钩子注册 `RecentPostsWidget`
- `wp_enqueue` 排队 theme-style + theme-main
- `wp_head` 输出 SEO meta + 百度/GA 统计
- `footer_text` 过滤器
- `after_switch_theme` 初始化标记
- 支持 `inline_css` 配置：将外部 CSS 内联到 `<style>` 标签（Web 预览容器兼容）

### 17.4 RecentPostsWidget

- 后台表单：`number` 字段（显示数量）
- 前台渲染：查询已发布文章，输出带链接列表
- 使用 `Core\Cache\CacheInterface` 缓存结果

### 17.5 模板渲染流程

```
theme_view('single', ['post' => $post])
  → ThemeManager::render('single', $data)
    → do_action('template_redirect', 'single', $data)
    → apply_filters('template_include', null, 'single', $data)
    → templateHierarchy('single') → ['single', 'index']
    → 依次查找 templates/single.php → index.php
    → renderFile($path, $data)
        - extract($data) 到模板作用域
        - include 模板
        - 获取输出
    → do_action('template_rendered', $path, $data)
    → apply_filters('template_output', $body, $path, $data)
    → 返回 Response
```

---

## 18. 后台管理界面 resources/views/

### 18.1 布局

`resources/views/layouts/admin.php`：
- 固定侧栏 + 顶部栏 + 内容区
- 导航：仪表盘/文章/分类/标签/媒体/评论/用户/主题/插件/设置
- 底部：查看站点 + 退出 + 调试数据

### 18.2 页面清单

| 路径 | 功能 |
|------|------|
| `admin/dashboard.php` | 统计卡片（文章数/评论数/用户数）+ 最新文章/评论 |
| `admin/posts/index.php` | 列表（搜索/筛选/分页） |
| `admin/posts/form.php` | Markdown 编辑器 + 实时预览 |
| `admin/categories/index.php` | 内联创建 + 列表 |
| `admin/tags/index.php` | 同分类 |
| `admin/media/index.php` | 网格视图 + 拖拽上传 |
| `admin/comments/index.php` | 筛选 + 批量操作 |
| `admin/users/index.php` / `form.php` | 用户管理 |
| `admin/themes/index.php` | 主题网格 + 激活 |
| `admin/plugins/index.php` | 插件网格 + 启停 |
| `admin/settings/index.php` | 系统设置（站点/评论/SEO/Webhook） |
| `auth/login.php` / `register.php` | 登录/注册 |

### 18.3 后台设计系统

- `public/assets/admin/admin.css`：完整设计令牌 + 组件库
- `public/assets/admin/admin.js`：侧栏切换/确认对话框/Markdown 预览
- 3 断点：1024/768/480
- 移动端：侧栏变抽屉

---

## 19. 测试体系

### 19.1 TestCase 基类

- 内存 SQLite（`:memory:`）隔离
- 自动迁移（Migrator::run）
- `get($uri)` / `post($uri, $data)` 模拟 HTTP
- `app()` 容器访问
- `seed()` 快速填充数据

### 19.2 PHPUnit 测试覆盖

| 测试文件 | 覆盖内容 |
|---------|---------|
| `RouterTest.php` | 路由注册/匹配/命名/分组/参数化/中间件 |
| `QueryBuilderTest.php` | select/where/join/orderBy/insert/update/delete/事务 |
| `CapabilityTest.php` | 角色权限矩阵 + 所有权校验 |
| `ThemeSystemTest.php` | 主题加载/Template 层级/Asset/Widget/Shortcode |
| `FirstBatchTest.php` | 核心功能集成 |
| `SecondBatchTest.php` | 功能测试 |
| `ThirdBatchTest.php` | 功能测试 |
| `DeepEnhancementTest.php` | 深度增强功能（缓存锁、Webhook、审计等） |

### 19.3 冒烟测试

`tests/SmokeTest.js`：对运行中的服务器发起 HTTP 请求验证。

---

## 20. 辅助函数体系详解 Support/helpers*

### 20.1 helpers.php（核心，10+ 函数）
- `app($abstract)`：容器解析
- `config($key, $default)`：配置读取
- `base_path()`, `app_path()`, `resource_path()`, `public_path()`, `storage_path()`, `views_path()`, `plugins_path()`, `themes_path()`, `database_path()`
- `url($path)`：相对路径 URL 生成
- `e($str)`：htmlspecialchars 转义
- `theme_view($template, $data)`：主题模板渲染
- `view($template, $data)`：后台视图渲染
- `response($body, $status)`：Response 工厂

### 20.2 helpers_http.php
- `url($path)`, `route($name, $params)`, `asset($path)`, `redirect($path, $status)`, `csrf_token()`, `csrf_field()`, `old($key, $default)`, `is_admin_route()`

### 20.3 helpers_hook.php（WordPress 兼容）
- `add_action()`, `do_action()`, `has_action()`
- `add_filter()`, `apply_filters()`

### 20.4 helpers_auth.php
- `logged_in()`, `current_user()`, `can()`, `can_or_403()`

### 20.5 helpers_theme.php（最丰富，60+ 函数）

**模板结构**：`get_header()`, `get_footer()`, `get_sidebar()`, `get_template_part()`

**条件标签**：`is_home()`, `is_front_page()`, `is_single()`, `is_page()`, `is_category()`, `is_tag()`, `is_search()`, `is_404()`, `is_author()`, `is_archive()`

**主题资产/路径**：`theme_asset()`, `theme_path()`, `theme_config()`（3 级优先级）

**资产排队**：`enqueue_style()`, `enqueue_script()`

**Widget**：`register_sidebar()`, `register_widget()`, `dynamic_sidebar()`

**菜单**：`register_nav_menu()`, `wp_nav_menu()`

**分页**：`paginate_links()`（prev/current/next + 省略号 + 自定义 base URL）

**CSS 类**：`body_class()`（根据当前路由动态生成）, `post_class()`, `sanitize_html_class()`

**模板标签**：`the_title()`, `get_the_title()`, `the_content()`, `the_excerpt()`, `the_permalink()`, `have_posts()`

**Shortcode**：`add_shortcode()`, `do_shortcode()`

**WP_Head/Footer**：
- `wp_head()`：先输出 CSS link（支持 inline_css 模式将外部样式内联到 `<style data-theme>` 标签，路径重写为绝对 `/themes/...`），再触发 `wp_head` action
- `wp_footer()`：先输出 footer scripts，再触发 `wp_footer` action，最后渲染 DebugBar

**内容助手**：`reading_time()`, `word_count()`（中文按字符，英文按词）, `table_of_contents()`（自动提取 h2/h3 + 生成锚点 + 包裹 `<nav>`）

**安全转义**：`esc_html()`（HTML5 ENT_QUOTES）, `esc_attr()`, `esc_url()`（过滤危险协议）

**CSS 内联路径重写**：
- `resolveAssetLocalPath($src)`：将 `/themes/default/assets/foo.css` 这类 URL 映射到本地文件
- `rewriteCssAssetPaths($css, $cssDir)`：重写 CSS 中相对 `url()` 引用为绝对路径

### 20.6 helpers_advanced.php（高级功能）

**i18n**：`__()`, `_e()`, `set_locale()`
**片段缓存**：`cache_fragment()`, `cache_forget()`
**图片**：`add_image_size()`, `post_thumbnail()`（含 srcset + loading="lazy"）, `has_post_thumbnail()`
**SEO**：`breadcrumbs()`, `robots_txt()`
**邮件**：`render_email()`, `send_email()`（mail() 函数封装）
**Webhook**：`webhook_trigger()`
**调试**：`debug_bar()`

---

## 21. Service Provider 注册链路

### 21.1 Provider 抽象基类

```php
abstract class Provider {
    public function __construct(protected Application $app) {}
    abstract public function register(): void;  // 绑定服务到容器（不应触发其他 Provider）
    public function boot(): void;                // 启动逻辑（可安全解析任何服务）
}
```

生命周期：`Application::bootstrap()` 遍历 `$providers` 数组，依次调用 `register()` → `boot()`。

**两阶段设计原因**：
- `register()` 阶段只绑定，不触发解析，避免 Provider 间循环依赖
- `boot()` 阶段所有 Provider 都已注册，可以安全地从容器解析任何服务

### 21.2 14 个 Provider 详解

| Provider | register() 绑定 | boot() 行为 |
|----------|----------------|-------------|
| **AuthProvider** | `AuthManager::class` → 单例 | - |
| **DatabaseProvider** | `Connection::class` → 单例 + `QueryBuilder::class` | `Migrator::run()` 自动运行 pending 迁移 |
| **CacheProvider** | `CacheManager::class` + `CacheInterface::class`（代理默认驱动） | - |
| **HttpProvider** | `Request::class`（每次 capture 新实例）、`Response::class`、`Session::class`、`FormRequest::class` 抽象绑定 | - |
| **HookProvider** | `Action::class` + `Filter::class` → 单例 | - |
| **ViewProvider** | `ThemeManager/AssetManager/WidgetManager/MenuManager/Shortcode/ViewRenderer` | - |
| **ParsedownProvider** | `Parsedown::class`（`setSafeMode(true)` 防止 XSS） | - |
| **ThemeServiceProvider** | `ThemeManager::class`（别名） | `loadThemeFunctions()` 加载当前激活主题的 functions.php；触发 `theme_loaded` action |
| **PluginProvider** | `PluginManager::class` → 单例 | 自动激活所有 `plugin_activations` 表中标记的插件 |
| **QueueProvider** | `Queue::class` + `Job::class` 基类绑定 | - |
| **EnhancedServiceProvider** | `EventDispatcher::class`、`CacheLock::class` | 注册 `throttle` / `cors` 中间件名到路由；绑定 7 个审计钩子到 AuditLog |
| **AdvancedServiceProvider** | `Translator::class`、`ImageProcessor::class`、`Sitemap::class`、`PageCache::class` | 注册 `security` 中间件 + 3 个邮件模板 + Webhook 事件订阅 + PageCache 清理钩子 |
| **RouteServiceProvider** | - | 注册中间件组（web/admin/api）+ 加载 routes/*.php + 路由模型绑定 `{slug} → Post:slug` |

**注意顺序**：AuthProvider 必须在最前（后续 Provider 可能依赖认证）；RouteServiceProvider 必须在最后（所有中间件和服务就绪后才能解析路由）。

### 21.3 EnhancedServiceProvider 审计钩子绑定

在 `boot()` 中通过 `Action::add()` 绑定 7 个审计事件：
```
user_logged_in   → AuditLog::record('user.login', '用户登录', ...)
user_logged_out  → AuditLog::record('user.logout', '用户登出', ...)
post_saved(pub)  → AuditLog::record('post.publish', '文章发布', ...)
post_deleted     → AuditLog::record('post.delete', '文章删除', ...)
after_switch_theme → AuditLog::record('theme.switch', '主题切换', ...)
activated_plugin → AuditLog::record('plugin.activate', '插件激活', ...)
deactivated_plugin → AuditLog::record('plugin.deactivate', '插件停用', ...)
```

### 21.4 AdvancedServiceProvider 额外行为

- 注册 `SecurityHeadersMiddleware` 到路由（别名 `security`）
- 注册 3 个邮件模板：`comment_notification` / `register_welcome` / `password_reset`（从 `resources/emails/` 加载）
- 订阅 Webhook 事件：
  - `post_saved` → Webhook::trigger('post.saved', payload)
  - `comment_created` → Webhook::trigger('comment.created', payload)
- PageCache 清理：
  - `post_saved` / `post_deleted` → PageCache::flushAll()
  - `comment_created` → PageCache::flush('post-' . $postId)

---

## 22. 设计模式总览

| 设计模式 | 使用位置 | 说明 |
|---------|---------|------|
| 单例 | Application | 全局唯一实例 |
| 服务容器 / IoC | Container | 依赖注入 + 自动装配 |
| 服务提供者 | Provider + 14 子类 | 两阶段启动生命周期 |
| 工厂 | CacheManager / ThemeManager::installFromZip | 按类型创建实例 |
| 策略 | CacheInterface 5 实现 | 运行时切换缓存驱动 |
| 观察者 | Action / Filter / EventDispatcher | 钩子系统实现 |
| 活动记录 | Model | 数据行映射 |
| 查询构造器 | QueryBuilder | 不可变 + clone 克隆 |
| 责任链 | Router 中间件链 | null 继续 / Response 短路 |
| 模板方法 | Provider / Migration / MakeCommand | 父类控生命周期 |
| 适配器 | FileCache / RedisCache | 统一接口适配不同后端 |
| 代理 | CacheManager 默认驱动方法 | 透传调用 |
| 延迟加载 | Container::defer / Model::__get | 首次访问实例化 |
| 装饰器 | Container::extend() | 解析后修改行为 |
| 标签绑定 | Container::tag / tagged | 批量解析同标签抽象 |
| 空对象 | AssetManager 缺依赖降级 | 保证非空返回 |
| 递归查找 | ThemeManager::resolvePath | child → parent 模板查找 |
| 观察者注入 | Conditional::set | Router 注入条件状态 |
| 拓扑排序 | AssetManager::sortDeps | DAG 深度优先遍历 |
| 数据传输对象 | PostData | 表单→业务对象转换 |
| 仓储 | Option 模型 | 全局 KV 存储 |

---

## 23. 安全机制清单

| 措施 | 实现 |
|------|------|
| **CSRF** | `CsrfMiddleware` + `hash_equals` 防时序攻击 + 48 位 token |
| **XSS** | `e()` / `esc_html()` / `esc_attr()` / `esc_url()` 多层转义 |
| **SQL 注入** | QueryBuilder 命名占位符 `:col_N` + PDO 参数化 |
| **密码哈希** | `password_hash()` + `password_verify()`（bcrypt 默认） |
| **响应头** | `SecurityHeadersMiddleware`（CSP/HSTS/X-Frame-Options/X-Content-Type-Options/XSS-Protection/Referrer-Policy/Permissions-Policy） |
| **CORS** | `CorsMiddleware` 可配置白名单 + OPTIONS 预检处理 |
| **限流** | `ThrottleMiddleware`（请求级滑动窗口）+ `LoginRateLimiter`（登录级 5 次/15 分钟） |
| **Session** | HttpOnly + SameSite=Lax + Secure(HTTPS) + 48 位 sid + strict_mode |
| **审计** | `AuditLog` 记录 7 类敏感操作，支持分页查询 + 90 天清理 |
| **权限** | 5 级角色（super_admin/senior_admin/editor_admin/editor_writer/visitor）+ 细粒度 + 所有权校验 |
| **输入验证** | `FormRequest`（14 种规则）+ `PostData::validate()` |
| **文件上传** | MediaController MIME + 大小限制 + GD 处理 |
| **Markdown** | `Parsedown::setSafeMode(true)` 过滤 XSS |
| **异常处理** | 生产环境错误页不泄露堆栈 + 日志记录 |
| **文件安全** | ZipArchive 解压 + 目录穿越防护 + rrmdir 清理 |
| **Webhook** | fsockopen 非阻塞 + stream_context 降级 + 防 SSRF（parse_url 检查） |
| **调试保护** | 生产环境 Whoops 关闭，错误不泄露 |

---

## 24. 扩展点 Hook / Filter 注册表

### 24.1 Action 钩子

| Hook | 参数 | 触发时机 |
|------|------|----------|
| `init` | 无 | 应用启动后（预留） |
| `wp_enqueue` | 无 | 主题排队 CSS/JS |
| `wp_head` | 无 | `<head>` 区域（meta/统计） |
| `wp_footer` | 无 | `</body>` 前 |
| `widgets_init` | 无 | Widget 注册 |
| `theme_loaded` | 无 | 主题 functions.php 加载后 |
| `after_switch_theme` | `$newTheme, $oldTheme` | 主题切换后 |
| `template_redirect` | `$template, $data` | 模板渲染前 |
| `template_rendered` | `$path, $data` | 模板渲染后 |
| `home_loaded` | `$posts, $page, $totalPages` | 首页加载后 |
| `post_loaded` | `$post` | 文章加载后（预留） |
| `post_saved` | `$id, $data, $isUpdate` | 文章保存后（含 Webhook/缓存清理） |
| `post_deleted` | `$id` | 文章删除后 |
| `comment_created` | `$commentId, $postId` | 评论创建后 |
| `user_logged_in` | `$user` | 用户登录后（审计） |
| `user_logged_out` | `$user` | 用户登出后（审计） |
| `activated_plugin` | `$plugin` | 插件激活（审计） |
| `deactivated_plugin` | `$plugin` | 插件停用（审计） |
| `dynamic_sidebar_fallback` | `$sidebarId` | Widget 区域无实例时的兜底 |

### 24.2 Filter 钩子

| Hook | 说明 |
|------|------|
| `the_content` | 文章 HTML 输出（插件可追加内容） |
| `the_title` | 文章标题修改 |
| `the_excerpt` | 文章摘要修改 |
| `comment_text` | 评论 HTML 修改 |
| `nav_menu_items` | 菜单项过滤 |
| `footer_text` | 页脚版权文字修改 |
| `template_include` | 模板路径覆盖（返回路径可替换模板） |
| `template_output` | 模板最终 HTML 后处理 |

---

## 25. 开发快速参考

### 25.1 CLI 命令

```bash
# 初始化
php blog install              # 创建表 + 种子数据
php blog migrate              # 执行 pending 迁移
php blog migrate:rollback     # 回滚上一批
php blog migrate:status       # 查看迁移状态
php blog seed                 # 填充种子

# 开发
php blog serve                # PHP 内置服务器
php bin/dev                   # 自动探测端口 + 打开浏览器

# 代码生成
php blog make:resource Product     # Model + Controller + DTO + Migration
php blog make:controller Admin/X    # 支持子目录
php blog make:model Product
php blog make:middleware Cors
php blog make:dto ProductData
php blog make:migration create_x_table
```

### 25.2 快速开始

```bash
git clone https://github.com/Lisir2002/blog-enhanced.git
cd blog-enhanced
cp .env.example .env        # 修改配置（APP_DEBUG=true, APP_KEY 随机）
composer install            # 安装依赖（4 个运行时包）
php blog install            # 初始化数据库 + 种子
php bin/dev                 # 启动开发服务器
# 访问 http://localhost:8080
# 管理员 admin / admin123
```

### 25.3 代码组织约定

| 类型 | 位置 | 命名空间 |
|------|------|----------|
| Web 控制器 | `app/Controllers/Web/` | `App\Controllers\Web` |
| Admin 控制器 | `app/Controllers/Admin/` | `App\Controllers\Admin` |
| Api 控制器 | `app/Controllers/Api/` | `App\Controllers\Api` |
| 模型 | `app/Models/` | `App\Models` |
| 服务 | `app/Services/` | `App\Services` |
| DTO | `app/DTO/` | `App\DTO` |
| 事件/任务/监听器 | `app/Events|Jobs|Listeners/` | 对应命名空间 |
| 主题 | `public/themes/{name}/` | - |
| 插件 | `plugins/{name}/` | - |
| 迁移 | `database/migrations/` | `Database\Migrations` |
| 核心引擎 | `core/` | `Core\*` |

### 25.4 常见扩展示例

```php
// 1. 添加全局钩子 (在主题 functions.php 或插件中)
add_action('wp_head', function () {
    echo '<meta name="custom" content="value">';
});

// 2. 过滤文章内容
add_filter('the_content', function ($html) {
    return $html . '<p>自定义底部内容</p>';
});

// 3. 注册自定义 Widget
class MyWidget extends \Core\View\Widget {
    public $id = 'my_widget';
    public $name = '我的小工具';
    public function render(array $instance): string {
        return '自定义 Widget 内容';
    }
}
register_widget(MyWidget::class);

// 4. 添加路由
// routes/web.php 中:
$router->get('/custom', [SomeController::class, 'handle']);

// 5. 创建自定义中间件
class RateLimitMiddleware implements MiddlewareInterface {
    public function handle(array $params, array $args = []): ?Response {
        // 限流逻辑
        return null; // 继续链
    }
}

// 6. 使用 FormRequest 验证
class StorePostRequest extends FormRequest {
    public function rules(): array {
        return ['title' => 'required|max:200', 'content_md' => 'required|min:10'];
    }
}

// 7. 发布 Webhook
add_action('post_saved', function ($id) {
    \Core\Webhook\Webhook::trigger('post.published', ['id' => $id]);
});

// 8. 查询构建器
$posts = Post::query()
    ->where('status', '=', 'published')
    ->where('published_at', '<=', date('Y-m-d H:i:s'))
    ->orderBy('published_at', 'DESC')
    ->paginate(10);
```

### 25.5 运行测试

```bash
# PHPUnit 测试（内存 SQLite 隔离）
vendor/bin/phpunit
# 或使用 phar
php phpunit.phar

# 冒烟测试（需先启动服务器）
node tests/SmokeTest.js
```

### 25.6 上线前安全清单

- [ ] `APP_DEBUG=false`（Whoops 关闭）
- [ ] `APP_KEY` 为随机 32 字符
- [ ] HTTPS 启用（Session Secure + HSTS）
- [ ] MySQL/SQLite 权限最小化（仅 SELECT/INSERT/UPDATE/DELETE）
- [ ] `storage/` 目录可写但不可执行
- [ ] 禁用目录浏览（Options -Indexes）
- [ ] 主题和插件仅从可信来源上传
- [ ] 定期执行 `AuditLog::cleanup(90)` 清理审计日志
- [ ] 定期 `Log::cleanup()` 清理过期日志
- [ ] 配置 `robots.txt` 禁止敏感路径
- [ ] 验证所有自定义路由有 CSRF 保护
- [ ] 设置合理的 `ThrottleMiddleware` 限流

---

## 附录：完整文件树索引

```
blog-enhanced/
├── bin/
│   ├── dev                          # 开发服务器启动器（自动探测端口+浏览器）
│   └── install.php                  # 安装脚本（CLI 入口包装）
├── blog                             # CLI 入口（Shell 脚本）
├── composer.json                    # 依赖管理（仅 4 运行时包）
├── phpunit.xml                      # PHPUnit 配置
├── config/
│   ├── app.php                      # 应用配置（name/env/url/key/theme/per_page等）
│   ├── cache.php                    # 缓存配置（file/redis 驱动设置）
│   ├── database.php                 # 数据库配置（SQLite/MySQL 双驱动）
│   ├── queue.php                    # 队列配置（sync/file/database 驱动）
│   ├── session.php                  # Session 配置（cookie/httponly/samesite 等）
│   └── theme.php                    # 主题路径配置
├── core/
│   ├── Application.php              # 应用主类（单例+引导+请求生命周期）
│   ├── Container.php                # IoC 容器（绑定/单例/上下文/标签/装饰器）
│   ├── Router.php                   # 路由器（正则匹配+中间件链+命名路由）
│   ├── Auth/
│   │   ├── AuthManager.php          # 认证管理（attempt/logout/can/权限）
│   │   └── Capability.php           # 权限矩阵（5 角色 + 所有权校验）
│   ├── Cache/
│   │   ├── CacheInterface.php       # 缓存接口（get/set/forever/delete/tag/lock等）
│   │   ├── CacheManager.php         # 多驱动管理器（代理模式+降级）
│   │   ├── FileCache.php            # 文件缓存（子目录+标签+计数器持久化）
│   │   ├── RedisCache.php           # Redis 缓存（phpredis/predis 双支持）
│   │   ├── ArrayCache.php           # 内存缓存（测试用）
│   │   ├── FragmentCache.php        # 片段缓存（helper 实现）
│   │   ├── PageCache.php            # 整页缓存（HIT/MISS 头+模式清理）
│   │   └── CacheLock.php            # 缓存锁（防击穿+析构释放）
│   ├── Console/
│   │   ├── Application.php          # CLI 调度器（命令发现+执行）
│   │   └── Commands/MakeCommand.php # 代码生成器（resource/controller/model等）
│   ├── Database/
│   │   ├── Connection.php           # PDO 连接工厂（SQLite WAL/MySQL STRICT）
│   │   ├── QueryBuilder.php         # 不可变查询构造器（clone 克隆+命名占位符）
│   │   ├── Model.php                # 活动记录基类（关系/事件/软删/作用域）
│   │   ├── Migration.php            # 迁移抽象基类
│   │   ├── Migrator.php             # 迁移引擎（发现/运行/回滚/状态）
│   │   └── Relations/
│   │       ├── Relation.php        # 关联基类（eagerLoad 批量预加载）
│   │       ├── BelongsTo.php       # 多对一（外键→主键）
│   │       ├── HasMany.php         # 一对多（主键→外键）
│   │       ├── HasOne.php          # 一对一（同 HasMany 单条）
│   │       └── BelongsToMany.php   # 多对多（中间表 pivot join）
│   ├── Email/EmailTemplate.php      # 邮件模板系统（主题覆盖+内置 3 模板）
│   ├── Events/EventDispatcher.php   # 事件调度器（监听+派发+传播阻止）
│   ├── Hook/
│   │   ├── Action.php               # Action 钩子（do_action 触发）
│   │   └── Filter.php               # Filter 钩子（apply_filters 链式传递）
│   ├── Http/
│   │   ├── Request.php              # 请求封装（readonly 属性+点号输入）
│   │   ├── Response.php             # 响应封装（json/redirect/send）
│   │   ├── Session.php              # Session 管理（安全标志+CSRF+闪存）
│   │   ├── FormRequest.php          # 表单验证（14 规则+自动重定向）
│   │   └── Middleware/
│   │       ├── MiddlewareInterface.php  # 中间件接口
│   │       ├── AuthMiddleware.php    # 登录检查+回跳 URL
│   │       ├── GuestMiddleware.php   # 游客检查（已登录访问登录页重定向）
│   │       ├── AdminMiddleware.php   # 后台权限检查
│   │       ├── CsrfMiddleware.php    # CSRF token 校验（hash_equals）
│   │       ├── CorsMiddleware.php   # CORS 预检处理
│   │       ├── SecurityHeadersMiddleware.php # 安全响应头（CSP/HSTS等）
│   │       └── ThrottleMiddleware.php # 滑动窗口限流
│   ├── i18n/Translator.php          # 国际化（语言文件+参数替换）
│   ├── Log/Log.php                  # 日志系统（8 级+按天滚动+结构化）
│   ├── Plugin/PluginManager.php     # 插件管理器（发现/激活/停用/上传/删除）
│   ├── Providers/
│   │   ├── Provider.php             # 抽象基类
│   │   ├── AuthProvider.php         # AuthManager 绑定
│   │   ├── DatabaseProvider.php     # Connection 绑定+自动迁移
│   │   ├── CacheProvider.php        # CacheManager 绑定
│   │   ├── HttpProvider.php         # Request/Response/Session 绑定
│   │   ├── HookProvider.php         # Action/Filter 绑定
│   │   ├── ViewProvider.php         # 视图相关组件绑定
│   │   ├── ParsedownProvider.php    # Parsedown 安全模式绑定
│   │   ├── ThemeServiceProvider.php # 主题启动（加载 functions.php）
│   │   ├── PluginProvider.php       # 插件自动激活
│   │   ├── QueueProvider.php        # 队列绑定
│   │   ├── EnhancedServiceProvider.php # 审计钩子+中间件注册
│   │   ├── AdvancedServiceProvider.php # 邮件/Webhook/PageCache 钩子
│   │   └── RouteServiceProvider.php # 中间件组+路由加载
│   ├── Queue/
│   │   ├── Queue.php                # 队列系统（sync/file/database 3 驱动）
│   │   └── Job.php                  # 任务基类（构造参数+handle）
│   ├── SEO/Sitemap.php              # SEO（XML sitemap/robots/breadcrumbs）
│   ├── Security/AuditLog.php        # 审计日志（7 类操作记录+查询+清理）
│   ├── Support/
│   │   ├── Config.php               # 配置管理（. 层级访问）
│   │   ├── helpers.php              # 核心辅助函数（app/config/path/view 等）
│   │   ├── helpers_http.php         # HTTP 辅助函数（url/route/asset/redirect/csrf）
│   │   ├── helpers_hook.php         # WordPress 兼容钩子函数
│   │   ├── helpers_auth.php         # 认证辅助函数（logged_in/current_user/can）
│   │   ├── helpers_theme.php        # 主题辅助函数（60+ 函数，最大）
│   │   └── helpers_advanced.php    # 高级辅助函数（i18n/cache/image/seo/email等）
│   ├── View/
│   │   ├── ThemeManager.php        # 主题管理器（加载/切换/父子/ZIP上传）
│   │   ├── AssetManager.php         # 资产排队（依赖排序+版本指纹+minify）
│   │   ├── Widget.php               # Widget 抽象基类
│   │   ├── WidgetManager.php       # Widget 管理器（注册/渲染/包装）
│   │   ├── MenuManager.php         # 菜单管理器（递归/高亮/rel=noopener）
│   │   ├── Shortcode.php            # Shortcode 系统（正则解析+属性提取）
│   │   ├── Conditional.php          # 条件标签（基于路由名注入）
│   │   ├── DebugBar.php             # 调试栏（查询/钩子/模板统计）
│   │   ├── ImageProcessor.php       # 图片处理（3 尺寸+GD裁剪+srcset）
│   │   └── ViewRenderer.php        # 后台视图渲染器
│   └── Webhook/Webhook.php          # Webhook 系统（事件驱动+fsockopen 非阻塞）
├── app/
│   ├── Controllers/
│   │   ├── Web/   (10 个：Home/Post/Page/Category/Tag/Search/Comment/Feed/Health/Auth)
│   │   ├── Admin/ (11 个：Dashboard/Post/Category/Tag/Media/Comment/User/Theme/Plugin/Setting/Auth)
│   │   └── Api/   (2 个：Post/Taxonomy)
│   ├── DTO/PostData.php             # 文章 DTO（fromRequest/validate/toArray）
│   ├── Events/PostPublishedEvent.php # 文章发布事件（stopPropagation 支持）
│   ├── Jobs/
│   │   ├── RebuildSitemapJob.php    # 异步重建 sitemap.xml
│   │   └── SendCommentNotificationJob.php # 邮件通知
│   ├── Listeners/
│   │   ├── RebuildSitemapListener.php # 监听事件→推队列
│   │   └── ClearPageCacheListener.php # 监听事件→清 PageCache
│   ├── Models/
│   │   ├── User.php                 # 用户模型（displayName/avatarUrl/postCount）
│   │   ├── Post.php                 # 文章模型（html/excerpt/url/related/scope）
│   │   ├── Category.php             # 分类模型（posts 关系）
│   │   ├── Tag.php                  # 标签模型（posts 多对多）
│   │   ├── Comment.php              # 评论模型（html/replies/nestedReplies/depth）
│   │   ├── Media.php                # 媒体模型（url/thumbnailUrl/humanSize/isImage）
│   │   └── Option.php               # 站点设置 KV 存储（内存缓存+JSON 自动解析）
│   ├── Services/
│   │   ├── PostService.php          # 文章业务逻辑（创建/更新/删除/syncTags）
│   │   └── LoginRateLimiter.php     # 登录限流（5次/15分钟+滑动窗口）
│   └── Support/Slugify.php          # Slug 生成器（中文→hex/音译+唯一化）
├── database/
│   ├── migrations/                  # 4 个迁移文件（时间戳命名）
│   ├── seeds/run.php                # 种子（3 分类+6 标签+5 示例文章）
│   ├── schema.sqlite.sql            # SQLite Schema
│   └── schema.mysql.sql             # MySQL Schema
├── plugins/hello-dolly/hello-dolly.php # 示例插件（过滤器追加内容）
├── public/
│   ├── index.php                    # Web 入口
│   ├── .htaccess                    # Apache 重写规则
│   ├── assets/admin/                # 后台 CSS/JS
│   │   ├── admin.css                # 完整设计令牌+组件库（3 断点响应式）
│   │   └── admin.js                 # 侧栏/对话框/Markdown 预览
│   ├── avatars/                     # 5 角色头像
│   └── themes/default/              # 默认主题
│       ├── theme.json               # 元数据（menus/sidebars/options/page_templates）
│       ├── functions.php            # 主题入口（注册菜单/Widger/资产/hooks）
│       ├── Widgets/RecentPostsWidget.php # 最新文章 Widget
│       ├── templates/               # 10 个模板（home/single/page/archive/category/tag/author/search/404/error）
│       ├── partials/                # header/footer/sidebar
│       └── assets/                   # style.css + main.js
├── resources/
│   ├── views/
│   │   ├── layouts/admin.php        # 后台主布局（侧栏+顶栏+内容+调试）
│   │   ├── auth/login.php           # 登录页
│   │   ├── auth/register.php        # 注册页
│   │   └── admin/*/                  # 后台子页面（dashboard/posts/categories/tags/media/comments/users/themes/plugins/settings）
│   └── emails/                      # 邮件模板目录
├── routes/
│   ├── web.php                      # 前台 20+ 条路由
│   ├── admin.php                    # 后台 30+ 条路由
│   └── api.php                      # API 2 条路由
├── storage/
│   ├── logs/                        # 日志（按天滚动）
│   ├── sessions/                   # Session 文件
│   ├── uploads/                     # 上传文件（按年月）
│   └── framework/                   # 框架存储
├── tests/
│   ├── TestCase.php                 # 基类（内存 SQLite+自动迁移）
│   ├── bootstrap.php                # 测试引导
│   ├── Unit/                        # 8 个 PHPUnit 测试文件
│   └── SmokeTest.js                 # Node 冒烟测试
├── .env.example                     # 环境变量示例
├── .gitignore
├── README.md
└── SUMMARY.md                       # 本文档
```

---

## 附录 B：版本与扩展路线图（建议）

基于源码分析，未来可扩展方向：

1. **代码生成器增强**：`make:resource` 可同时生成 Feature 测试 + FormRequest
2. **后台表单验证统一**：将 `PostData::validate()` 迁移到 `FormRequest` 体系（当前仅 Admin\PostController 手写验证）
3. **软删除实现**：当前 `$softDelete = true` 已声明，但 Model 中 `save()/delete()` 的软删除分支需完善
4. **队列 Worker**：提供 `php blog queue:work` 持续消费命令
5. **测试覆盖**：目前 22 个测试以集成测试为主，缺少单元测试（如 QueryBuilder 各方法独立测试）
6. **API 认证**：Api 路由目前无认证，建议添加 Sanctum/JWT 方案
7. **多语言**：Translator 已实现，但暂无实际语言文件，可补充 `resources/lang/zh_CN.php`
8. **插件 ZIP 安全**：当前 `PluginManager::uploadZip()` 未做目录穿越防护检查，建议增加 `ZipArchive::locateName` 白名单

---

> 本文档基于对全部 **~180 个源文件** 的逐行阅读与深度分析，覆盖每个类的属性、方法、实现细节、设计意图与扩展机制。
>
> 最后更新：深度阅读后补充控制器、服务层、模型、Service Provider、CLI、辅助函数等核心实现细节。 
> 覆盖范围：入口、引导、路由、中间件（7 个）、控制器（23 个）、服务、DTO、模型（7 个）、关联（4 种）、查询构造器、事件、任务、监听器、缓存（5 种驱动 + PageCache + FragmentCache + CacheLock）、视图、主题（父子 + 模板层级 + 数据栈）、插件、队列、邮件、Webhook、SEO、审计、日志、i18n、配置、迁移、种子、测试、设计模式（20 种）、安全机制（17 项）、扩展点（19 Action + 8 Filter）、开发参考。