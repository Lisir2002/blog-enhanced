<?php

namespace App\Controllers\Admin;

use App\Models\Post;
use App\Models\Comment;
use App\Models\User;
use App\Models\Option;
use Core\Http\Response;

class DashboardController
{
    public function index(): Response
    {
        $postCount = Post::query()->count();
        $publishedCount = Post::query()->where('status', '=', 'published')->count();
        $draftCount = Post::query()->where('status', '=', 'draft')->count();
        $commentCount = Comment::query()->count();
        $pendingComments = Comment::query()->where('status', '=', 'pending')->count();
        $userCount = User::query()->count();

        $recentPosts = Post::query()->orderBy('created_at', 'DESC')->limit(5)->get();
        $recentComments = Comment::query()->orderBy('created_at', 'DESC')->limit(5)->get();

        return view('admin.dashboard', [
            'postCount'        => $postCount,
            'publishedCount'   => $publishedCount,
            'draftCount'       => $draftCount,
            'commentCount'     => $commentCount,
            'pendingComments'  => $pendingComments,
            'userCount'        => $userCount,
            'recentPosts'      => $recentPosts,
            'recentComments'   => $recentComments,
            'pageTitle'        => '仪表盘',
        ]);
    }
}
