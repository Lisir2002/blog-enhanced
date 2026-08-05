<?php

namespace App\Controllers\Admin;

use App\Models\Comment;
use App\Models\Post;
use Core\Database\Connection;
use Core\Http\Response;
use Core\Http\Request;
use Core\Http\Session;

class CommentController
{
    public function index(): Response
    {
        can_or_403('moderate_comments');
        return view('admin.comments.index', [
            'pageTitle' => '评论管理',
        ]);
    }

    /**
     * AJAX 搜索接口 - 返回 JSON
     * 支持：关键词搜索(content/author_name/author_email)、状态标签筛选、排序、分页
     * 使用 LEFT JOIN 预加载文章，避免 N+1
     * 使用 POST body 传参，绕过 URL 编码问题
     */
    public function search(): Response
    {
        can_or_403('moderate_comments');
        $request = app(Request::class);
        $pdo = app(Connection::class)->pdo();

        $page = max(1, (int) $request->input('page', 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT c.id, c.post_id, c.parent_id, c.author_name, c.author_email,
                       c.author_url, c.author_ip, c.content, c.status, c.created_at,
                       p.title AS post_title, p.slug AS post_slug
                FROM comments c
                LEFT JOIN posts p ON p.id = c.post_id
                WHERE 1=1";
        $bindings = [];

        $status = $request->input('status');
        if ($status && in_array($status, ['pending', 'approved', 'spam'], true)) {
            $sql .= " AND c.status = :status";
            $bindings[':status'] = $status;
        }

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $sql .= " AND (c.content LIKE :q1 OR c.author_name LIKE :q2 OR c.author_email LIKE :q3)";
            $like = '%' . $search . '%';
            $bindings[':q1'] = $like;
            $bindings[':q2'] = $like;
            $bindings[':q3'] = $like;
        }

        $countSql = "SELECT COUNT(*) FROM ($sql) AS sub";
        $stmt = $pdo->prepare($countSql);
        $stmt->execute($bindings);
        $total = (int) $stmt->fetchColumn();

        $sortMap = [
            'status'     => 'c.status',
            'created_at' => 'c.created_at',
        ];
        $sort = $request->input('sort', 'created_at');
        $order = strtolower($request->input('order', 'desc'));
        if (!isset($sortMap[$sort])) $sort = 'created_at';
        if (!in_array($order, ['asc', 'desc'])) $order = 'desc';
        $sql .= " ORDER BY {$sortMap[$sort]} $order";

        $sql .= " LIMIT $perPage OFFSET $offset";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($bindings);
        $items = $stmt->fetchAll();

        $totalPages = max(1, (int) ceil($total / $perPage));

        return (new Response())->json([
            'items'      => $items,
            'total'      => $total,
            'page'       => $page,
            'totalPages' => $totalPages,
            'perPage'    => $perPage,
        ]);
    }

    public function batch(): Response
    {
        can_or_403('moderate_comments');
        $request = app(Request::class);
        $sess = app(Session::class);
        $action = $request->input('batch_action');
        $ids = array_filter(array_map('intval', explode(',', $request->input('batch_ids', ''))));
        if (empty($ids)) {
            $sess->flash('error', '请选择要操作的评论');
            return redirect(route('admin.comments.index'));
        }
        $count = 0;
        foreach ($ids as $id) {
            if ($action === 'delete') {
                Comment::query()->where('id', '=', $id)->delete();
                $count++;
            } elseif ($action === 'approve') {
                Comment::query()->where('id', '=', $id)->update(['status' => 'approved']);
                $count++;
            } elseif ($action === 'spam') {
                Comment::query()->where('id', '=', $id)->update(['status' => 'spam']);
                $count++;
            }
        }
        $actionLabels = ['delete' => '删除', 'approve' => '批准', 'spam' => '标垃圾'];
        $label = $actionLabels[$action] ?? '操作';
        $sess->flash('success', "已{$label} {$count} 条评论");
        return redirect(route('admin.comments.index'));
    }

    public function approve(array $params): Response
    {
        can_or_403('moderate_comments');
        $id = (int) $params['id'];
        Comment::query()->where('id', '=', $id)->update(['status' => 'approved']);
        app(Session::class)->flash('success', '已批准');
        return redirect(route('admin.comments.index'));
    }

    public function markSpam(array $params): Response
    {
        can_or_403('moderate_comments');
        $id = (int) $params['id'];
        Comment::query()->where('id', '=', $id)->update(['status' => 'spam']);
        app(Session::class)->flash('success', '已标记为垃圾');
        return redirect(route('admin.comments.index'));
    }

    public function delete(array $params): Response
    {
        can_or_403('moderate_comments');
        $id = (int) $params['id'];
        Comment::query()->where('id', '=', $id)->delete();
        app(Session::class)->flash('success', '已删除');
        return redirect(route('admin.comments.index'));
    }
}