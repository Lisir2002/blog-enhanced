<?php

namespace App\Controllers\Admin;

use App\Models\Comment;
use App\Models\Post;
use Core\Http\Response;
use Core\Http\Request;
use Core\Http\Session;

class CommentController
{
    public function index(): Response
    {
        $page = max(1, (int) app(Request::class)->input('page', 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;
        $qb = Comment::query();
        $status = app(Request::class)->input('status');
        if ($status && in_array($status, ['pending', 'approved', 'spam'], true)) {
            $qb->where('status', '=', $status);
        }
        $total = (clone $qb)->count();
        $items = $qb->orderBy('created_at', 'DESC')->limit($perPage)->offset($offset)->get();
        $totalPages = max(1, (int) ceil($total / $perPage));

        return view('admin.comments.index', [
            'items'      => $items,
            'page'       => $page,
            'totalPages' => $totalPages,
            'pageTitle'  => '评论管理',
        ]);
    }

    public function approve(array $params): Response
    {
        $id = (int) $params['id'];
        Comment::query()->where('id', '=', $id)->update(['status' => 'approved', 'updated_at' => date('Y-m-d H:i:s')]);
        app(Session::class)->flash('success', '已批准');
        return redirect(route('admin.comments.index'));
    }

    public function markSpam(array $params): Response
    {
        $id = (int) $params['id'];
        Comment::query()->where('id', '=', $id)->update(['status' => 'spam', 'updated_at' => date('Y-m-d H:i:s')]);
        app(Session::class)->flash('success', '已标记为垃圾');
        return redirect(route('admin.comments.index'));
    }

    public function delete(array $params): Response
    {
        $id = (int) $params['id'];
        Comment::query()->where('id', '=', $id)->delete();
        app(Session::class)->flash('success', '已删除');
        return redirect(route('admin.comments.index'));
    }
}
