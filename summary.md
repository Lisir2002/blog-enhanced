# Blog Enhanced 项目重构总结

> 更新时间：2026-08-05（v3 增补：主题/插件页面深度优化）
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

---

## 七、主题/插件页面深度优化（v3 增补）

> 本轮迭代聚焦于主题与插件管理后台的交互体验、视觉一致性、移动端适配，并将搜索筛选体系全面对齐其他后台页面（文章/评论/用户等）的统一规范。

### 7.1 主题管理页面优化

#### 7.1.1 当前主题纳入「可用主题」列表

**问题**：原实现将当前激活主题从可用主题列表中分离，仅在顶部大卡片中展示，导致可用主题数量与实际列表数不一致，且用户无法在列表中对当前主题执行「详情」等操作。

**解决方案**：当前主题既在顶部以大卡片形式突出展示，也作为可用主题之一出现在列表中，并通过视觉徽章和按钮差异区分状态。

- 列表数据源：`$listThemes = $themes;`（包含全部主题，含当前主题）
- 当前主题卡片左上角显示「当前主题」毛玻璃徽章
- 当前主题卡片不显示「激活」按钮（因为已激活），仅保留「详情」入口
- 状态筛选：`全部 / 当前主题 / 其他主题` 三档切换

```php
<?php foreach ($listThemes as $t): $isActiveTheme = ($t['name'] === $active); ?>
    <div class="theme-card <?= $isActiveTheme ? 'active' : '' ?>">
        <div class="theme-card-preview">
            ...
            <?php if ($isActiveTheme): ?>
            <div class="theme-card-active-badge">
                <svg ...><polyline points="20 6 9 17 4 12"/></svg>
                当前主题
            </div>
            <?php endif; ?>
        </div>
        ...
        <div class="theme-card-actions">
            <a href="<?= route('admin.themes.detail', ['name' => $t['name']]) ?>" class="btn btn-sm btn-secondary">详情</a>
            <?php if (!$isActiveTheme): ?>
            <form method="post" action="<?= route('admin.themes.activate', ['name' => $t['name']]) ?>" class="inline-form">
                <button type="submit" class="btn btn-sm btn-primary">激活</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
```

#### 7.1.2 搜索/筛选统一对齐

将主题页面原先的非标准搜索 UI 替换为统一的 `.admin-filter-bar` 模式：

- 搜索框：`.search-box` + 防抖跳转（400ms）
- 状态标签：`.filter-tabs` + `data-status` 属性切换
- 后端支持：`ThemeController::index()` 新增 `q` 与 `status` 查询参数

```php
public function index(): Response
{
    can_or_403('switch_themes');
    $theme = app(ThemeManager::class);
    $themes = $theme->listThemes();
    $active = $theme->activeTheme();

    $search = trim($_GET['q'] ?? '');
    $statusFilter = trim($_GET['status'] ?? '');

    return view('admin.themes.index', [
        'themes'       => $themes,
        'active'       => $active,
        'search'       => $search,
        'statusFilter' => $statusFilter,
        'pageTitle'    => '主题管理',
    ]);
}
```

#### 7.1.3 主题详情入口补齐

为主题管理列表的每张卡片补齐「详情」按钮入口，移动端和 PC 端均可直接点击进入 `admin.themes.detail` 页面，无需先激活再查看。

### 7.2 插件管理页面重设计

#### 7.2.1 页面布局重构

**问题**：原插件页面布局简陋，缺少统计概览、视图切换、批量操作引导。

**解决方案**：四区域布局，符合现代后台管理界面规范。

```
┌─────────────────────────────────────────────────┐
│  页面头部 (.plugin-page-header)                  │
│  ├─ 标题 + 描述                                   │
│  └─ 操作区：视图切换 + 上传插件按钮               │
├─────────────────────────────────────────────────┤
│  上传区域 (.plugin-upload-section，可折叠)        │
├─────────────────────────────────────────────────┤
│  统计卡片 (.plugin-stats-bar)                    │
│  [总插件] [已激活] [未激活] [有异常]              │
├─────────────────────────────────────────────────┤
│  筛选区域 (.admin-filter-bar)                    │
│  🔍 [搜索框] [全部/已激活/未激活/有异常]          │
├─────────────────────────────────────────────────┤
│  批量操作栏 (.batch-actions-bar，选中时显示)     │
├─────────────────────────────────────────────────┤
│  插件列表 (.plugin-list-section)                 │
│  ├─ 网格视图 (.plugin-grid)                      │
│  └─ 列表视图 (.plugin-list-view)                 │
└─────────────────────────────────────────────────┘
```

#### 7.2.2 视图切换（网格 / 列表）

**问题**：移动端列表视图与网格视图视觉差异不明显，仅 `flex-direction` 改变无实际效果。

**解决方案**：列表视图采用横向行式布局，移动端自适应换行为 2 行结构（标题行 + 操作行）。

- PC 端列表视图：单行 `[图标 | 标题+描述 | 状态 | 操作]`
- 移动端列表视图：2 行 `[图标 | 标题 | 状态]` + `[操作按钮组]`
- 长标题自动省略号截断，避免挤压操作按钮

```css
.plugin-list-section[data-view="list"] .plugin-card {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 16px;
  padding: 12px 16px;
}

@media (max-width: 768px) {
  .plugin-list-section[data-view="list"] .plugin-card {
    flex-wrap: wrap;
    /* 标题行占满第一行 */
  }
}
```

#### 7.2.3 统计卡片可点击筛选

4 个统计卡片（总数/已激活/未激活/有异常）均可点击切换对应状态筛选，与文章列表的状态卡片交互一致：

```php
<a href="javascript:;" class="plugin-stat-item <?= $statusFilter === 'active' ? 'is-current' : '' ?>" data-status="active">
    <div class="stat-icon stat-icon-active">...</div>
    <div class="stat-info">
        <span class="stat-num"><?= $activeCount ?></span>
        <span class="stat-label">已激活</span>
    </div>
</a>
```

#### 7.2.4 插件详情页重设计

`resources/views/admin/plugins/detail.php` 采用两栏布局：

- 左栏：插件基本信息（名称/版本/描述/作者/许可证/主页）+ 元数据列表
- 右栏：操作面板（激活/停用/删除）+ 依赖关系 + Hook 注册清单 + 兼容性信息

### 7.3 移动端适配修复

#### 7.3.1 侧边栏遮挡定制页面

**问题**：主题定制页面在移动端被固定侧边栏遮挡，无法完整查看。

**解决方案**：

- 在 `resources/views/layouts/admin.php` 新增移动端遮罩层 `.sidebar-overlay`
- 点击遮罩或导航项后自动收起侧边栏
- 侧边栏通过 `transform: translateX(-100%)` 隐藏，避免布局抖动

```php
<div class="sidebar-overlay" id="sidebarOverlay"></div>
```

```javascript
// admin.js
sidebarToggle.addEventListener('click', () => {
    document.body.classList.toggle('sidebar-open');
});
sidebarOverlay.addEventListener('click', () => {
    document.body.classList.remove('sidebar-open');
});
```

#### 7.3.2 筛选栏移动端挤压

**问题**：`.admin-filter-row-top` 默认 `flex-wrap: nowrap`，导致移动端搜索框与视图切换按钮溢出。

**解决方案**：移动端断点下启用 `flex-wrap: wrap`，搜索框占满整行，视图切换右对齐。

```css
@media (max-width: 768px) {
  .admin-filter-row-top {
    flex-wrap: wrap;
  }
  .admin-filter-row-top .search-box {
    flex: 1 1 100%;
  }
}
```

#### 7.3.3 主题卡片当前主题徽章

新增 `.theme-card-active-badge` 毛玻璃效果样式，确保在浅色与深色封面图上均可清晰可见：

```css
.theme-card-active-badge {
  position: absolute;
  top: 10px;
  left: 10px;
  background: rgba(79, 110, 247, 0.85);
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
  color: #fff;
  padding: 4px 10px;
  border-radius: 16px;
  font-size: 0.75rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 4px;
  border: 1px solid rgba(255, 255, 255, 0.2);
}
```

### 7.4 搜索/筛选统一规范

为彻底解决「不同后台页面搜索筛选写法不一致」问题，确立以下统一规范，主题/插件页面已全面对齐：

#### 7.4.1 UI 结构规范

所有后台列表页必须使用以下统一结构：

```html
<div class="admin-filter-bar">
    <div class="admin-filter-row-top">
        <div class="search-box">
            <svg class="search-icon">...</svg>
            <input type="text" id="{module}SearchInput" class="form-control" autocomplete="off">
        </div>
        <!-- 可选：视图切换 / 高级筛选按钮 -->
    </div>
    <div class="filter-tabs admin-filter-tabs">
        <a href="javascript:;" class="filter-tab is-default <?= $statusFilter === '' ? 'active' : '' ?>" data-status="">全部</a>
        <a href="javascript:;" class="filter-tab <?= $statusFilter === 'active' ? 'active' : '' ?>" data-status="active">已激活</a>
        <a href="javascript:;" class="filter-tab <?= $statusFilter === 'inactive' ? 'active' : '' ?>" data-status="inactive">未激活</a>
    </div>
</div>
```

#### 7.4.2 交互规范

- **搜索输入**：400ms 防抖跳转，回车立即提交
- **状态切换**：点击 `data-status` 标签即跳转，保留当前搜索关键词
- **URL 参数**：`?q=关键词&status=active`，便于分享和浏览器后退
- **后端处理**：控制器读取 `$_GET['q']` 与 `$_GET['status']`，在 PHP 层过滤后传给视图

#### 7.4.3 对齐清单

| 页面 | 搜索框 | 状态标签 | 视图切换 | 批量操作 | 统计卡片 |
|------|--------|----------|----------|----------|----------|
| 文章列表 | ✓ | ✓ | - | ✓ | - |
| 分类列表 | ✓ | - | - | ✓ | - |
| 标签列表 | ✓ | - | - | ✓ | - |
| 评论列表 | ✓ | ✓ | - | ✓ | - |
| 用户列表 | ✓ | ✓ | - | ✓ | - |
| **主题列表** | ✓ | ✓ | - | - | - |
| **插件列表** | ✓ | ✓ | ✓ | ✓ | ✓ |

### 7.5 修改文件清单（v3 增补）

| 文件 | 改动 |
|------|------|
| `resources/views/admin/themes/index.php` | 当前主题纳入列表 + 统一筛选 UI + 详情入口 |
| `resources/views/admin/plugins/index.php` | 四区域布局 + 视图切换 + 统计卡片 + 统一筛选 |
| `resources/views/admin/plugins/detail.php` | **重写** - 两栏布局 + Hook 清单 |
| `resources/views/admin/themes/detail.php` | **新增** - 主题详情页 |
| `resources/views/admin/themes/customize.php` | **新增** - 主题定制页 |
| `resources/views/layouts/admin.php` | 移动端遮罩层 + 侧边栏抽屉 |
| `app/Controllers/Admin/ThemeController.php` | 新增 `q` / `status` 查询参数支持 |
| `app/Controllers/Admin/PluginController.php` | 重构搜索 + 统计 + 详情 |
| `public/assets/admin/admin.css` | 主题卡片徽章 + 移动端筛选栏 + 列表视图样式 |
| `public/assets/admin/admin.js` | 侧边栏切换 + 批量操作 + 视图切换逻辑 |
| `routes/admin.php` | 新增主题/插件详情路由 |

### 7.6 验证结果（v3）

- 主题管理页面（http://localhost:8000/admin/themes）：
  - 顶部大卡片展示当前主题 ✓
  - 可用主题列表含当前主题，数量徽章显示「1 个」 ✓
  - 当前主题卡片显示「当前主题」徽章 ✓
  - 当前主题卡片无「激活」按钮，仅显示「详情」 ✓
- 插件管理页面（http://localhost:8000/admin/plugins）：
  - 四区域布局正常 ✓
  - 视图切换（网格/列表）功能正常 ✓
  - 移动端列表视图 2 行布局正常 ✓
  - 统计卡片可点击筛选 ✓
- 移动端适配：
  - 侧边栏抽屉 + 遮罩层正常 ✓
  - 筛选栏搜索框 + 标签换行正常 ✓
