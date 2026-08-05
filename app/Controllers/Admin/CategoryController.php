<?php

namespace App\Controllers\Admin;

use App\Models\Category;
use App\Support\Slugify;
use Core\Database\Connection;
use Core\Http\Request;
use Core\Http\Response;
use Core\Http\Session;

class CategoryController
{
    public function index(): Response
    {
        can_or_403('manage_categories');
        return view('admin.categories.index', [
            'pageTitle' => '分类管理',
        ]);
    }

    /**
     * AJAX 搜索接口 - 返回 JSON
     * 支持：关键词搜索(name/description/slug)、排序、分页
     * 使用 LEFT JOIN 统计文章数，避免 N+1
     * 使用 POST body 传参，绕过 URL 编码问题
     */
    public function search(): Response
    {
        can_or_403('manage_categories');
        $request = app(Request::class);
        $pdo = app(Connection::class)->pdo();

        $page = max(1, (int) $request->input('page', 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT c.id, c.name, c.slug, c.description, c.parent_id, c.created_at,
                       COUNT(p.id) AS post_count
                FROM categories c
                LEFT JOIN posts p ON p.category_id = c.id AND p.deleted_at IS NULL
                WHERE 1=1";
        $bindings = [];

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $sql .= " AND (c.name LIKE :q1 OR c.description LIKE :q2 OR c.slug LIKE :q3)";
            $like = '%' . $search . '%';
            $bindings[':q1'] = $like;
            $bindings[':q2'] = $like;
            $bindings[':q3'] = $like;
        }

        $sql .= " GROUP BY c.id, c.name, c.slug, c.description, c.parent_id, c.created_at";

        // 统计总数（子查询包装，因含 GROUP BY）
        $countSql = "SELECT COUNT(*) FROM ($sql) AS sub";
        $stmt = $pdo->prepare($countSql);
        $stmt->execute($bindings);
        $total = (int) $stmt->fetchColumn();

        $sortMap = [
            'name'       => 'c.name',
            'slug'       => 'c.slug',
            'created_at' => 'c.created_at',
            'post_count' => 'post_count',
        ];
        $sort = $request->input('sort', 'name');
        $order = strtolower($request->input('order', 'desc'));
        if (!isset($sortMap[$sort])) $sort = 'name';
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
        can_or_403('manage_categories');
        $request = app(Request::class);
        $sess = app(Session::class);
        $action = $request->input('batch_action');
        $ids = array_filter(array_map('intval', explode(',', $request->input('batch_ids', ''))));
        if (empty($ids)) {
            $sess->flash('error', '请选择要操作的分类');
            return redirect(route('admin.categories.index'));
        }
        if ($action === 'delete') {
            $count = 0;
            foreach ($ids as $id) {
                $cat = Category::find($id);
                if ($cat) { $cat->delete(); $count++; }
            }
            app(\Core\Cache\CacheInterface::class)->delete('nav_menu');
            $sess->flash('success', "已删除 {$count} 个分类");
        }
        return redirect(route('admin.categories.index'));
    }

    public function store(): Response
    {
        can_or_403('manage_categories');
        $request = app(Request::class);
        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            return redirect(route('admin.categories.index'));
        }
        $slug = trim((string) $request->input('slug', ''));
        if ($slug === '') {
            $slug = Slugify::make($name, 'cat');
        }
        $slug = Slugify::unique($slug, 'categories');

        Category::query()->insert([
            'name'        => $name,
            'slug'        => $slug,
            'description' => trim((string) $request->input('description', '')),
            'parent_id'   => (int) $request->input('parent_id', 0),
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
        app(\Core\Cache\CacheInterface::class)->delete('nav_menu');
        app(Session::class)->flash('success', '分类已创建');
        return redirect(route('admin.categories.index'));
    }

    public function update(array $params): Response
    {
        can_or_403('manage_categories');
        $id = (int) $params['id'];
        $cat = Category::find($id);
        if (!$cat) return redirect(route('admin.categories.index'));
        $request = app(Request::class);
        $slug = trim((string) $request->input('slug', ''));
        if ($slug === '') $slug = Slugify::make(trim((string) $request->input('name', '')), 'cat');
        if ($slug !== $cat->getAttribute('slug')) {
            $slug = Slugify::unique($slug, 'categories', 'slug', $id);
        }
        Category::query()->where('id', '=', $id)->update([
            'name'        => trim((string) $request->input('name', '')),
            'slug'        => $slug,
            'description' => trim((string) $request->input('description', '')),
            'parent_id'   => (int) $request->input('parent_id', 0),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
        app(\Core\Cache\CacheInterface::class)->delete('nav_menu');
        app(Session::class)->flash('success', '分类已更新');
        return redirect(route('admin.categories.index'));
    }

    public function delete(array $params): Response
    {
        can_or_403('manage_categories');
        $id = (int) $params['id'];
        $cat = Category::find($id);
        if ($cat) {
            $cat->delete();
            app(\Core\Cache\CacheInterface::class)->delete('nav_menu');
            app(Session::class)->flash('success', '分类已删除');
        }
        return redirect(route('admin.categories.index'));
    }
}