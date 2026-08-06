<?php

use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\PostController;
use App\Controllers\Admin\CategoryController;
use App\Controllers\Admin\TagController;
use App\Controllers\Admin\CommentController;
use App\Controllers\Admin\UserController;
use App\Controllers\Admin\SettingController;
use App\Controllers\Admin\ThemeController;
use App\Controllers\Admin\PluginController;
use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\MediaController;
use Core\Router;

/** @var Router $router */
$router->group(['prefix' => '/admin'], function (Router $router) {
    $prefix = '';

    // Auth
    $router->get($prefix . '/login', [AuthController::class, 'loginForm'])->name('admin.login')->middleware(['guest']);
    $router->post($prefix . '/login', [AuthController::class, 'login'])->name('admin.login.post')->middleware(['guest', 'csrf']);
    $router->post($prefix . '/logout', [AuthController::class, 'logout'])->name('admin.logout')->middleware(['csrf']);

    // Dashboard
    $router->get($prefix . '/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard')->middleware(['admin']);
    $router->get($prefix . '/', [DashboardController::class, 'index'])->name('admin.dashboard')->middleware(['admin']);

    // Posts
    $router->get($prefix . '/posts', [PostController::class, 'index'])->name('admin.posts.index')->middleware(['admin']);
    $router->post($prefix . '/api/posts/search', [PostController::class, 'search'])->name('admin.posts.search')->middleware(['admin']);
    $router->get($prefix . '/posts/create', [PostController::class, 'create'])->name('admin.posts.create')->middleware(['admin']);
    $router->post($prefix . '/posts', [PostController::class, 'store'])->name('admin.posts.store')->middleware(['admin', 'csrf']);
    $router->post($prefix . '/posts/batch', [PostController::class, 'batch'])->name('admin.posts.batch')->middleware(['admin', 'csrf']);
    $router->get($prefix . '/posts/{id}/edit', [PostController::class, 'edit'])->name('admin.posts.edit')->middleware(['admin']);
    $router->post($prefix . '/posts/{id}', [PostController::class, 'update'])->name('admin.posts.update')->middleware(['admin', 'csrf']);
    $router->post($prefix . '/posts/{id}/delete', [PostController::class, 'delete'])->name('admin.posts.delete')->middleware(['admin', 'csrf']);
    $router->post($prefix . '/posts/{id}/restore', [PostController::class, 'restore'])->name('admin.posts.restore')->middleware(['admin', 'csrf']);
    $router->post($prefix . '/posts/{id}/force-delete', [PostController::class, 'forceDelete'])->name('admin.posts.forceDelete')->middleware(['admin', 'csrf']);

    // Categories
    $router->get($prefix . '/categories', [CategoryController::class, 'index'])->name('admin.categories.index')->middleware(['admin']);
    $router->post($prefix . '/api/categories/search', [CategoryController::class, 'search'])->name('admin.categories.search')->middleware(['admin']);
    $router->post($prefix . '/categories', [CategoryController::class, 'store'])->name('admin.categories.store')->middleware(['admin', 'csrf']);
    $router->post($prefix . '/categories/batch', [CategoryController::class, 'batch'])->name('admin.categories.batch')->middleware(['admin', 'csrf']);
    $router->post($prefix . '/categories/{id}', [CategoryController::class, 'update'])->name('admin.categories.update')->middleware(['admin', 'csrf']);
    $router->post($prefix . '/categories/{id}/delete', [CategoryController::class, 'delete'])->name('admin.categories.delete')->middleware(['admin', 'csrf']);

    // Tags
    $router->get($prefix . '/tags', [TagController::class, 'index'])->name('admin.tags.index')->middleware(['admin']);
    $router->post($prefix . '/api/tags/search', [TagController::class, 'search'])->name('admin.tags.search')->middleware(['admin']);
    $router->post($prefix . '/tags', [TagController::class, 'store'])->name('admin.tags.store')->middleware(['admin', 'csrf']);
    $router->post($prefix . '/tags/batch', [TagController::class, 'batch'])->name('admin.tags.batch')->middleware(['admin', 'csrf']);
    $router->post($prefix . '/tags/{id}', [TagController::class, 'update'])->name('admin.tags.update')->middleware(['admin', 'csrf']);
    $router->post($prefix . '/tags/{id}/delete', [TagController::class, 'delete'])->name('admin.tags.delete')->middleware(['admin', 'csrf']);

    // Comments
    $router->get($prefix . '/comments', [CommentController::class, 'index'])->name('admin.comments.index')->middleware(['admin']);
    $router->post($prefix . '/api/comments/search', [CommentController::class, 'search'])->name('admin.comments.search')->middleware(['admin']);
    $router->post($prefix . '/comments/batch', [CommentController::class, 'batch'])->name('admin.comments.batch')->middleware(['admin', 'csrf']);
    $router->post($prefix . '/comments/{id}/approve', [CommentController::class, 'approve'])->name('admin.comments.approve')->middleware(['admin', 'csrf']);
    $router->post($prefix . '/comments/{id}/spam', [CommentController::class, 'markSpam'])->name('admin.comments.spam')->middleware(['admin', 'csrf']);
    $router->post($prefix . '/comments/{id}/delete', [CommentController::class, 'delete'])->name('admin.comments.delete')->middleware(['admin', 'csrf']);

    // Users
    $router->get($prefix . '/users', [UserController::class, 'index'])->name('admin.users.index')->middleware(['admin']);
    $router->post($prefix . '/api/users/search', [UserController::class, 'search'])->name('admin.users.search')->middleware(['admin']);
    $router->get($prefix . '/users/create', [UserController::class, 'create'])->name('admin.users.create')->middleware(['admin']);
    $router->post($prefix . '/users', [UserController::class, 'store'])->name('admin.users.store')->middleware(['admin', 'csrf']);
    $router->post($prefix . '/users/batch', [UserController::class, 'batch'])->name('admin.users.batch')->middleware(['admin', 'csrf']);
    $router->get($prefix . '/users/{id}/edit', [UserController::class, 'edit'])->name('admin.users.edit')->middleware(['admin']);
    $router->post($prefix . '/users/{id}', [UserController::class, 'update'])->name('admin.users.update')->middleware(['admin', 'csrf']);
    $router->post($prefix . '/users/{id}/delete', [UserController::class, 'delete'])->name('admin.users.delete')->middleware(['admin', 'csrf']);

    // Settings
    $router->get($prefix . '/settings', [SettingController::class, 'index'])->name('admin.settings.index')->middleware(['admin']);
    $router->post($prefix . '/settings/save', [SettingController::class, 'save'])->name('admin.settings.save')->middleware(['admin', 'csrf']);

    // Theme
    $router->get($prefix . '/themes', [ThemeController::class, 'index'])->name('admin.themes.index')->middleware(['admin']);
    $router->get($prefix . '/themes/{name}', [ThemeController::class, 'detail'])->name('admin.themes.detail')->middleware(['admin']);
    $router->get($prefix . '/themes/{name}/customize', [ThemeController::class, 'customize'])->name('admin.themes.customize')->middleware(['admin']);
    $router->post($prefix . '/themes/{name}/activate', [ThemeController::class, 'activate'])->name('admin.themes.activate')->middleware(['admin', 'csrf']);
    $router->post($prefix . '/themes/upload', [ThemeController::class, 'upload'])->name('admin.themes.upload')->middleware(['admin', 'csrf']);
    $router->post($prefix . '/themes/{name}/delete', [ThemeController::class, 'delete'])->name('admin.themes.delete')->middleware(['admin', 'csrf']);
    $router->post($prefix . '/themes/{name}/config', [ThemeController::class, 'saveConfig'])->name('admin.themes.config')->middleware(['admin', 'csrf']);
    $router->get($prefix . '/themes/{name}/preview', [ThemeController::class, 'preview'])->name('admin.themes.preview')->middleware(['admin']);

    // Theme Config Revisions
    $router->get($prefix . '/themes/{name}/revisions', [ThemeController::class, 'revisions'])->name('admin.themes.revisions')->middleware(['admin']);
    $router->post($prefix . '/themes/{name}/revisions/create', [ThemeController::class, 'createRevision'])->name('admin.themes.revisions.create')->middleware(['admin', 'csrf']);
    $router->post($prefix . '/themes/{name}/revisions/{id}/restore', [ThemeController::class, 'restoreRevision'])->name('admin.themes.revisions.restore')->middleware(['admin', 'csrf']);
    // Theme Preview Ajax (for live preview)
    $router->get($prefix . '/themes/{name}/preview-ajax', [ThemeController::class, 'previewAjax'])->name('admin.themes.preview-ajax')->middleware(['admin']);
    // Theme Preview Store Config (POST, store config in session for iframe reload)
    $router->post($prefix . '/themes/{name}/preview-config', [ThemeController::class, 'previewStoreConfig'])->name('admin.themes.preview-config')->middleware(['admin', 'csrf']);

    // Plugins
    $router->get($prefix . '/plugins', [PluginController::class, 'index'])->name('admin.plugins.index')->middleware(['admin']);
    $router->get($prefix . '/plugins/{name}', [PluginController::class, 'detail'])->name('admin.plugins.detail')->middleware(['admin']);
    $router->post($prefix . '/plugins/upload', [PluginController::class, 'upload'])->name('admin.plugins.upload')->middleware(['admin', 'csrf']);
    $router->post($prefix . '/plugins/batch', [PluginController::class, 'batch'])->name('admin.plugins.batch')->middleware(['admin', 'csrf']);
    $router->post($prefix . '/plugins/{name}/activate', [PluginController::class, 'activate'])->name('admin.plugins.activate')->middleware(['admin', 'csrf']);
    $router->post($prefix . '/plugins/{name}/deactivate', [PluginController::class, 'deactivate'])->name('admin.plugins.deactivate')->middleware(['admin', 'csrf']);
    $router->post($prefix . '/plugins/{name}/delete', [PluginController::class, 'delete'])->name('admin.plugins.delete')->middleware(['admin', 'csrf']);
    $router->post($prefix . '/plugins/{name}/config', [PluginController::class, 'saveConfig'])->name('admin.plugins.config')->middleware(['admin', 'csrf']);
    $router->post($prefix . '/plugins/{name}/check-update', [PluginController::class, 'checkUpdate'])->name('admin.plugins.check-update')->middleware(['admin', 'csrf']);
    $router->post($prefix . '/plugins/{name}/do-update', [PluginController::class, 'doUpdate'])->name('admin.plugins.do-update')->middleware(['admin', 'csrf']);

    // Media
    $router->get($prefix . '/media', [MediaController::class, 'index'])->name('admin.media.index')->middleware(['admin']);
    $router->post($prefix . '/media/upload', [MediaController::class, 'upload'])->name('admin.media.upload')->middleware(['admin', 'csrf']);
    $router->post($prefix . '/media/{id}/delete', [MediaController::class, 'delete'])->name('admin.media.delete')->middleware(['admin', 'csrf']);
});