<?php

/**
 * REST API 路由 - /api 前缀
 */

use App\Controllers\Api\PostController;
use App\Controllers\Api\TaxonomyController;

$prefix = '/api';

// 文章
$router->get($prefix . '/posts', [PostController::class, 'index'])->name('api.posts.index');
$router->get($prefix . '/posts/{slug}', [PostController::class, 'show'])->name('api.posts.show');

// 实时搜索
$router->get($prefix . '/search', [PostController::class, 'search'])->name('api.search');

// 分类/标签
$router->get($prefix . '/categories', [TaxonomyController::class, 'categories'])->name('api.categories.index');
$router->get($prefix . '/tags', [TaxonomyController::class, 'tags'])->name('api.tags.index');
