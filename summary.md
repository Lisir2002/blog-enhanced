# Blog Enhanced 项目重构总结

> 更新时间：2026-08-05
> 项目地址：https://github.com/Lisir2002/blog-enhanced

---

## 一、项目概述

基于原生 PHP + Composer 组件的轻量级博客 CMS 系统，零框架依赖，核心特性包括：

- 自研路由/容器/ORM/模板引擎
- 插件与主题体系
- AJAX 实时搜索与筛选
- 多数据库驱动（SQLite / MySQL 可切换）

---

## 二、本次重构核心改动

### 2.1 后台统一搜索/筛选模块

**问题**：分类、标签、用户、评论、文章 5 个后台列表页各自实现搜索逻辑，代码重复、交互不一致。

**解决方案**：创建通用 `admin-search.js` 模块，通过工厂模式 + 配置化实现复用。

#### 新增文件

| 文件 | 说明 |
|------|------|
| `public/assets/admin/admin-search.js` | 通用搜索模块（工厂模式） |
| `core/Routing/RouteValidator.php` | 路由校验器 |
| `scripts/validate-routes.php` | 路由校验 CLI 脚本 |
| `scripts/list-routes.php` | 路由列表 CLI 脚本 |

#### 重写的视图

| 文件 | 说明 |
|------|------|
| `resources/views/admin/categories/index.php` | 分类管理：AJAX 搜索 + 状态筛选 + 批量操作 |
| `resources/views/admin/tags/index.php` | 标签管理：AJAX 搜索 + 排序 + 批量操作 |
| `resources/views/admin/users/index.php` | 用户管理：角色/状态筛选 + 批量操作 |
| `resources/views/admin/comments/index.php` | 评论管理：状态标签页 + 批量审核/标记垃圾 |
| `resources/views/admin/posts/index.php` | 文章管理：回收站切换 + 状态/分类/作者筛选 |

#### 统一 UI 组件

```
┌─────────────────────────────────────────────────┐
│  .admin-filter-bar                               │
│  ┌─────────────────────────────────────────────┐ │
│  │ 🔍 [ 搜索输入框 ]     [状态标签] [高级筛选]  │ │
│  └─────────────────────────────────────────────┘ │
│  ┌─────────────────────────────────────────────┐ │
│  │ 表格（AJAX 动态渲染）     批量操作工具栏     │ │
│  └─────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────┘
```

#### admin-search.js 配置示例

```javascript
window.AdminSearch.init({
    searchUrl: '<?= route('admin.posts.search') ?>',
    pageWrap: '#postsPage',
    tbodyId: 'postsTableBody',
    searchInputId: 'postsSearchInput',
    debounceMs: 300,
    stateDefaults: { status: 'all', category: '0', author: '0' },
    renderRow: function(item) { /* 行渲染逻辑 */ },
});
```

#### 后端 API 模式统一

所有搜索接口遵循相同的 JSON 响应格式：

```json
{
    "items": [...],
    "total": 100,
    "page": 1,
    "totalPages": 10,
    "perPage": 10
}
```

### 2.2 路由防错体系

**问题**：多次出现 `Route [admin.xxx.yyy] not defined` 错误，根因是路由注册与视图/控制器引用之间没有一致性校验。

**三层防御方案**：

#### 第一层：运行时自动校验（debug 模式）

在 `RouteServiceProvider.boot()` 中集成 `RouteValidator`，每次请求自动扫描所有 `route()` 调用并比对已注册路由，缺失时记录到 error_log。

#### 第二层：友好降级（debug 模式）

修改 `helpers_http.php` 中的 `route()` 函数：
- debug 模式下路由不存在时**不抛异常**，而是记录日志 + 返回占位 URL，页面不崩溃
- 生产模式仍抛异常

#### 第三层：CLI 校验工具

```bash
# 校验所有路由引用
php scripts/validate-routes.php

# 列出所有已注册路由
php scripts/list-routes.php --admin
```

#### Router 类新增方法

| 方法 | 说明 |
|------|------|
| `hasRoute(string $name): bool` | 检查路由是否存在 |
| `getRouteNames(): array` | 获取所有路由名 |
| `getRoutes(): array` | 获取所有路由详情 |

### 2.3 主题管理页重新设计

**问题**：原主题管理页布局简陋，无封面预览，交互体验差。

**解决方案**：全新三区域布局。

#### 新增封面

为 Default 主题生成首页截图 `public/themes/default/screenshot.jpg`，在主题卡片中展示。

#### 页面布局

```
┌─────────────────────────────────────────────────┐
│  当前主题大卡片 (.theme-featured)                │
│  ┌──────────────┐  ┌──────────────────────────┐ │
│  │  封面预览     │  │  名称 v1.0.0             │ │
│  │  (screenshot) │  │  描述                     │ │
│  │  [当前主题]   │  │  作者/许可证/主页         │ │
│  │              │  │  侧边栏: 2个 菜单位置: 2个 │ │
│  │              │  │  [预览站点]               │ │
│  └──────────────┘  └──────────────────────────┘ │
├─────────────────────────────────────────────────┤
│  安装新主题 (.theme-upload-section)              │
│  ┌─────────────────────────────────────────────┐ │
│  │  📦 拖拽上传或点击选择 .zip 文件             │ │
│  └─────────────────────────────────────────────┘ │
├─────────────────────────────────────────────────┤
│  可用主题网格 (.theme-list-section)              │
│  ┌──────┐ ┌──────┐ ┌──────┐                    │
│  │封面  │ │封面  │ │封面  │                    │
│  │名称  │ │名称  │ │名称  │                    │
│  │描述  │ │描述  │ │描述  │                    │
│  │[激活]│ │[激活]│ │[删除]│                    │
│  └──────┘ └──────┘ └──────┘                    │
└─────────────────────────────────────────────────┘
```

### 2.4 CSS 设计系统优化

#### 新增设计 Token

```css
--radius-sm: 6px;   --radius: 10px;   --radius-lg: 14px;
--c-primary: #4f6ef7;  --c-primary-hover: #3b5de7;
--c-success: #10b981;  --c-warning: #f59e0b;  --c-danger: #ef4444;
```

#### 修复移动端 tap 高亮

为消除点击菜单时的蓝色方块闪烁：
- `body.admin-body *` 添加 `-webkit-tap-highlight-color: transparent`
- `a, button, .nav-item` 添加 `outline: none`
- `:active` 状态统一显示激活态背景

#### 半透明徽章

当前主题标签改为毛玻璃效果：
```css
background: rgba(79, 110, 247, 0.75);
backdrop-filter: blur(6px);
border: 1px solid rgba(255, 255, 255, 0.25);
```

### 2.5 路由修复汇总

本次修复了以下路由缺失/不一致问题：

| 模块 | 问题 | 修复 |
|------|------|------|
| Themes | 缺少 `upload`, `delete` 路由；`activate` 参数无 `{name}` | 补齐 3 条路由 |
| Plugins | 缺少 `upload`, `delete` 路由；`{id}` 应为 `{name}` | 补齐 4 条路由 + 参数修正 |
| Settings | 路由名 `admin.settings.update` 与视图引用 `admin.settings.save` 不一致 | 改为 `admin.settings.save` |

---

## 三、修改文件清单

### 核心框架

| 文件 | 改动 |
|------|------|
| `core/Router.php` | 新增 `hasRoute()`, `getRouteNames()`, `getRoutes()` |
| `core/Routing/RouteValidator.php` | **新增** - 路由校验器 |
| `core/Providers/RouteServiceProvider.php` | 集成自动路由校验 |
| `core/Support/helpers_http.php` | `route()` 函数 debug 模式友好降级 |

### 控制器

| 文件 | 改动 |
|------|------|
| `app/Controllers/Admin/CategoryController.php` | 新增 `search()` AJAX 方法（JOIN 防 N+1） |
| `app/Controllers/Admin/TagController.php` | 新增 `search()` AJAX 方法 |
| `app/Controllers/Admin/UserController.php` | 新增 `search()` 方法（角色/状态筛选） |
| `app/Controllers/Admin/CommentController.php` | 新增 `search()` 方法（状态筛选） |
| `app/Controllers/Admin/PostController.php` | 重构搜索 + 回收站 + 批量操作 |

### 视图

| 文件 | 改动 |
|------|------|
| `resources/views/admin/categories/index.php` | **重写** - AJAX 搜索 UI |
| `resources/views/admin/tags/index.php` | **重写** - AJAX 搜索 UI |
| `resources/views/admin/users/index.php` | **重写** - 角色/状态筛选 UI |
| `resources/views/admin/comments/index.php` | **重写** - 状态标签页 UI |
| `resources/views/admin/posts/index.php` | **重写** - 统一模块 + 回收站 |
| `resources/views/admin/themes/index.php` | **重写** - 三区域布局 |
| `resources/views/layouts/admin.php` | 侧边栏导航优化 |

### 路由

| 文件 | 改动 |
|------|------|
| `routes/admin.php` | 补齐 themes/plugins/settings 缺失路由 |

### 静态资源

| 文件 | 改动 |
|------|------|
| `public/assets/admin/admin.css` | 新增 150+ 行主题页样式 + tap-highlight 修复 |
| `public/assets/admin/admin.js` | 修复排序冲突 bug |
| `public/assets/admin/admin-search.js` | **新增** - 通用搜索模块 |
| `public/themes/default/screenshot.jpg` | **新增** - 主题封面 |

### CLI 工具

| 文件 | 改动 |
|------|------|
| `scripts/validate-routes.php` | **新增** - 路由校验 |
| `scripts/list-routes.php` | **新增** - 路由列表 |

---

## 四、验证结果

### 路由校验

```
========== 路由校验报告 ==========
已注册路由数: 80
缺失路由数:   0

✓ 所有 route() 调用均有对应路由定义。
```

### 页面访问测试

| 页面 | HTTP 状态 |
|------|-----------|
| admin/dashboard | 200 |
| admin/posts | 200 |
| admin/categories | 200 |
| admin/tags | 200 |
| admin/users | 200 |
| admin/comments | 200 |
| admin/themes | 200 |
| admin/plugins | 200 |
| admin/settings | 200 |
| admin/media | 200 |

---

## 五、开发规范

### 新增后台列表页流程

1. **创建控制器**：实现 `index()` + `search()` 方法
2. **注册路由**：在 `routes/admin.php` 添加 `GET /xxx` 和 `POST /api/xxx/search`
3. **创建视图**：使用 `admin-search.js` 配置化集成
4. **验证**：运行 `php scripts/validate-routes.php`

### 路由命名规范

```
admin.{module}.{action}
```

- `admin.posts.index` — 列表页
- `admin.posts.search` — AJAX 搜索
- `admin.posts.create` — 创建页
- `admin.posts.store` — 保存
- `admin.posts.edit` — 编辑页
- `admin.posts.update` — 更新
- `admin.posts.delete` — 删除

### 搜索 API 规范

所有搜索接口统一：
- **方法**：POST（参数在 body 中，避免 URL 编码问题）
- **响应**：`{ items, total, page, totalPages, perPage }`
- **查询**：使用 JOIN 预加载关联数据，防止 N+1

---

## 六、运行项目

```bash
# 安装依赖
composer install

# 初始化数据库
php bin/install.php

# 启动开发服务器
php -S 0.0.0.0:8000 -t public/ public/index.php

# 默认账号
用户名: admin / 密码: admin

# 工具命令
php scripts/validate-routes.php   # 校验路由
php scripts/list-routes.php       # 列出路由
```
