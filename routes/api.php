<?php

/**
 * REST API 路由 - /api 前缀
 */

use App\Controllers\Api\PostController;
use App\Controllers\Api\AuthController;

$prefix = '/api';

$router->get($prefix . '/posts', [PostController::class, 'index'])->name('api.posts.index');
$router->get($prefix . '/posts/{slug}', [PostController::class, 'show'])->name('api.posts.show');
$router->post($prefix . '/login', [AuthController::class, 'login'])->name('api.login');
