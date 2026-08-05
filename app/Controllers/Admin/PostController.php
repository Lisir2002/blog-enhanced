<?php

namespace App\Controllers\Admin;

use App\DTO\PostData;
use App\Models\Post;
use App\Models\Category;
use App\Models\User;
use App\Services\PostService;
use Core\Database\Connection;
use Core\Http\Request;
use Core\Http\Response;
use Core\Http\Session;

class PostController
{
    public function __construct(
        private PostService $postService,
    ) {}

    public function index(): Response
    {
        can_or_403('edit_posts');
        $request = app(Request::class);
        $trash = $request->input('trash') === '1';

        // 获取分类和作者列表用于筛选下拉框
        $categories = Category::all();
        $pdo = app(Connection::class)->pdo();
        $authors = $pdo->query(
            "SELECT DISTINCT u.id, u.username, u.display_name
             FROM users u
             INNER JOIN posts p ON p.author_id = u.id
             WHERE p.deleted_at IS " . ($trash ? "NOT NULL" : "NULL") . "
             ORDER BY u.username"
        )->fetchAll();

        return view('admin.posts.index', [
            'categories' => $categories,
            'authors'    => $authors,
            'trash'      => $trash,
            'pageTitle'  => $trash ? '文章回收站' : '文章列表',
        ]);
    }

    /**
     * AJAX 搜索接口 - 返回 JSON
     * 支持：关键词搜索(title/content_md/excerpt/slug)、状态/分类/作者/日期筛选、排序、分页
     * 使用 JOIN 预加载作者和分类，避免 N+1 查询
     * 使用 POST 方法，参数在 body 中，彻底绕过 URL 编码问题
     */
    public function search(): Response
    {
        can_or_403('edit_posts');
        $request = app(Request::class);
        $pdo = app(Connection::class)->pdo();

        $page = max(1, (int) $request->input('page', 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;
        $trash = $request->input('trash') === '1';

        // 基础查询 - JOIN 预加载作者和分类
        $sql = "SELECT p.id, p.slug, p.title, p.status, p.views, p.category_id, p.author_id,
                       p.published_at, p.created_at, p.deleted_at,
                       u.display_name AS author_name, u.username AS author_username,
                       c.name AS category_name
                FROM posts p
                LEFT JOIN users u ON u.id = p.author_id
                LEFT JOIN categories c ON c.id = p.category_id
                WHERE 1=1";
        $bindings = [];

        // 软删除过滤
        if ($trash) {
            $sql .= " AND p.deleted_at IS NOT NULL";
        } else {
            $sql .= " AND p.deleted_at IS NULL";
        }

        // 状态筛选
        $status = $request->input('status');
        if ($status && in_array($status, ['draft', 'published', 'archived'], true)) {
            $sql .= " AND p.status = :status";
            $bindings[':status'] = $status;
        }

        // 关键词搜索 - 跨 title, content_md, excerpt, slug
        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $sql .= " AND (p.title LIKE :q1 OR p.content_md LIKE :q2 OR p.excerpt LIKE :q3 OR p.slug LIKE :q4)";
            $like = '%' . $search . '%';
            $bindings[':q1'] = $like;
            $bindings[':q2'] = $like;
            $bindings[':q3'] = $like;
            $bindings[':q4'] = $like;
        }

        // 分类筛选
        $categoryId = (int) $request->input('category_id', 0);
        if ($categoryId > 0) {
            $sql .= " AND p.category_id = :category_id";
            $bindings[':category_id'] = $categoryId;
        }

        // 作者筛选
        $authorId = (int) $request->input('author_id', 0);
        if ($authorId > 0) {
            $sql .= " AND p.author_id = :author_id";
            $bindings[':author_id'] = $authorId;
        }

        // 日期范围筛选
        $dateFrom = $request->input('date_from', '');
        if ($dateFrom) {
            $sql .= " AND date(p.created_at) >= date(:date_from)";
            $bindings[':date_from'] = $dateFrom;
        }
        $dateTo = $request->input('date_to', '');
        if ($dateTo) {
            $sql .= " AND date(p.created_at) <= date(:date_to)";
            $bindings[':date_to'] = $dateTo;
        }

        // 统计总数
        $countSql = "SELECT COUNT(*) FROM ($sql) AS sub";
        $stmt = $pdo->prepare($countSql);
        $stmt->execute($bindings);
        $total = (int) $stmt->fetchColumn();

        // 排序 - 修复 user_id → author_id 的 Bug
        $sortMap = [
            'title'      => 'p.title',
            'author'     => 'p.author_id',
            'status'     => 'p.status',
            'created_at' => 'p.created_at',
            'views'      => 'p.views',
        ];
        $sort = $request->input('sort', 'created_at');
        $order = strtolower($request->input('order', 'desc'));
        if (!isset($sortMap[$sort])) $sort = 'created_at';
        if (!in_array($order, ['asc', 'desc'])) $order = 'desc';
        $sql .= " ORDER BY {$sortMap[$sort]} $order";

        // 分页
        $sql .= " LIMIT $perPage OFFSET $offset";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($bindings);
        $posts = $stmt->fetchAll();

        $totalPages = max(1, (int) ceil($total / $perPage));

        return (new Response())->json([
            'posts'      => $posts,
            'total'      => $total,
            'page'       => $page,
            'totalPages' => $totalPages,
            'perPage'    => $perPage,
        ]);
    }

    public function batch(): Response
    {
        can_or_403('delete_posts');
        $request = app(Request::class);
        $sess = app(Session::class);
        $action = $request->input('batch_action');
        $ids = array_filter(array_map('intval', explode(',', $request->input('batch_ids', ''))));

        if (empty($ids)) {
            $sess->flash('error', '请选择要操作的文章');
            return redirect(route('admin.posts.index'));
        }

        $count = 0;
        if ($action === 'delete') {
            foreach ($ids as $id) {
                try {
                    $this->postService->delete($id);
                    $count++;
                } catch (\Throwable $e) {
                    \Core\Log\Log::error('Batch post delete failed', ['id' => $id, 'msg' => $e->getMessage()]);
                }
            }
            $sess->flash('success', "已移入回收站 {$count} 篇文章");
        } elseif ($action === 'restore') {
            foreach ($ids as $id) {
                try {
                    if ($this->postService->restore($id)) $count++;
                } catch (\Throwable $e) {}
            }
            $sess->flash('success', "已恢复 {$count} 篇文章");
        } elseif ($action === 'force_delete') {
            foreach ($ids as $id) {
                try {
                    if ($this->postService->forceDelete($id)) $count++;
                } catch (\Throwable $e) {}
            }
            $sess->flash('success', "已永久删除 {$count} 篇文章");
        }

        $trash = $request->input('trash') === '1' ? '?trash=1' : '';
        return redirect(route('admin.posts.index') . $trash);
    }

    public function restore(array $params): Response
    {
        can_or_403('edit_posts');
        $id = (int) $params['id'];
        $sess = app(Session::class);

        try {
            $this->postService->restore($id);
            $sess->flash('success', '文章已恢复');
        } catch (\Throwable $e) {
            $sess->flash('error', '恢复失败');
        }
        return redirect(route('admin.posts.index') . '?trash=1');
    }

    public function forceDelete(array $params): Response
    {
        can_or_403('delete_posts');
        $id = (int) $params['id'];
        $sess = app(Session::class);

        try {
            $this->postService->forceDelete($id);
            $sess->flash('success', '文章已永久删除');
        } catch (\Throwable $e) {
            $sess->flash('error', '删除失败');
        }
        return redirect(route('admin.posts.index') . '?trash=1');
    }

    public function create(): Response
    {
        can_or_403('edit_posts');
        $categories = Category::all();
        return view('admin.posts.form', [
            'post'       => null,
            'categories' => $categories,
            'pageTitle'  => '写文章',
            'action'     => route('admin.posts.store'),
        ]);
    }

    public function store(): Response
    {
        can_or_403('edit_posts');
        $request = app(Request::class);
        $sess = app(Session::class);

        $data = PostData::fromRequest($request);
        if (!$data->isValid()) {
            $sess->flashInput($request->all());
            $sess->flash('error', implode(' ', $data->errors()));
            return redirect(route('admin.posts.create'));
        }

        try {
            $id = $this->postService->create($data, (int) current_user()->getAttribute('id'));
        } catch (\Throwable $e) {
            \Core\Log\Log::error('Post store failed', ['msg' => $e->getMessage()]);
            $sess->flashInput($request->all());
            $sess->flash('error', '保存失败：' . e($e->getMessage()));
            return redirect(route('admin.posts.create'));
        }

        $sess->flash('success', '文章已保存');
        return redirect(route('admin.posts.edit', ['id' => $id]));
    }

    public function edit(array $params): Response
    {
        can_or_403('edit_posts');
        $id = (int) $params['id'];
        $post = Post::withTrashed()->where('id', '=', $id)->first();
        if (!$post) {
            return redirect(route('admin.posts.index'));
        }
        $categories = Category::all();
        $tags = $post->tags();
        $tagNames = implode(', ', array_map(fn($t) => $t->getAttribute('name'), $tags));

        return view('admin.posts.form', [
            'post'       => $post,
            'categories' => $categories,
            'tags'       => $tagNames,
            'pageTitle'  => '编辑文章',
            'action'     => route('admin.posts.update', ['id' => $id]),
        ]);
    }

    public function update(array $params): Response
    {
        can_or_403('edit_posts');
        $id = (int) $params['id'];
        $post = Post::withTrashed()->where('id', '=', $id)->first();
        if (!$post) {
            return redirect(route('admin.posts.index'));
        }

        $request = app(Request::class);
        $sess = app(Session::class);

        $data = PostData::fromRequest($request);
        if (!$data->isValid()) {
            $sess->flashInput($request->all());
            $sess->flash('error', implode(' ', $data->errors()));
            return redirect(route('admin.posts.edit', ['id' => $id]));
        }

        try {
            $this->postService->update($id, $data);
        } catch (\Throwable $e) {
            \Core\Log\Log::error('Post update failed', ['id' => $id, 'msg' => $e->getMessage()]);
            $sess->flash('error', '更新失败：' . e($e->getMessage()));
            return redirect(route('admin.posts.edit', ['id' => $id]));
        }

        $sess->flash('success', '文章已更新');
        return redirect(route('admin.posts.edit', ['id' => $id]));
    }

    public function delete(array $params): Response
    {
        can_or_403('delete_posts');
        $id = (int) $params['id'];
        $sess = app(Session::class);

        try {
            $this->postService->delete($id);
            $sess->flash('success', '文章已移入回收站');
        } catch (\Throwable $e) {
            \Core\Log\Log::error('Post delete failed', ['id' => $id, 'msg' => $e->getMessage()]);
            $sess->flash('error', '删除失败');
        }
        return redirect(route('admin.posts.index'));
    }
}