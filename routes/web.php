<?php

/**
 * 前台路由
 */

use App\Controllers\Web\HomeController;
use App\Controllers\Web\PostController;
use App\Controllers\Web\CategoryController;
use App\Controllers\Web\TagController;
use App\Controllers\Web\AuthorController;
use App\Controllers\Web\SearchController;
use App\Controllers\Web\PageController;
use App\Controllers\Web\FeedController;
use App\Controllers\Admin\AuthController;

$router->get('/', [HomeController::class, 'index'])->name('home');
$router->get('/page/{page}', [HomeController::class, 'index'])->name('home.paged');
$router->get('/posts/{slug}', [PostController::class, 'show'])->name('post.show');
$router->get('/posts/{id}/edit', [PostController::class, 'edit'])->name('post.edit')->middleware(['auth']);
$router->get('/category/{slug}', [CategoryController::class, 'show'])->name('category.show');
$router->get('/category/{slug}/page/{page}', [CategoryController::class, 'show'])->name('category.paged');
$router->get('/tag/{slug}', [TagController::class, 'show'])->name('tag.show');
$router->get('/tag/{slug}/page/{page}', [TagController::class, 'show'])->name('tag.paged');
$router->get('/author/{username}', [AuthorController::class, 'show'])->name('author.show');
$router->get('/author/{username}/page/{page}', [AuthorController::class, 'show'])->name('author.paged');
$router->get('/search', [SearchController::class, 'index'])->name('search');

$router->get('/feed', [FeedController::class, 'rss'])->name('feed.rss');
$router->get('/sitemap.xml', [FeedController::class, 'sitemap'])->name('sitemap');
$router->get('/robots.txt', [FeedController::class, 'robots'])->name('robots');

// 登录/登出
$router->get('/login', [AuthController::class, 'loginForm'])->name('login')->middleware(['guest']);
$router->post('/login', [AuthController::class, 'login'])->middleware(['csrf']);
$router->get('/logout', [AuthController::class, 'logout'])->name('logout');
$router->get('/register', [AuthController::class, 'registerForm'])->name('register')->middleware(['guest']);
$router->post('/register', [AuthController::class, 'register'])->middleware(['csrf']);

// 评论提交
$router->post('/posts/{slug}/comments', [\App\Controllers\Web\CommentController::class, 'store'])->name('comment.store')->middleware(['csrf']);

// Catch-all single page (must be LAST — matches /{any-slug})
$router->get('/{slug}', [PageController::class, 'show'])->name('page.show');
