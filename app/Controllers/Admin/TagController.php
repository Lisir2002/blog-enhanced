<?php

namespace App\Controllers\Admin;

use App\Models\Tag;
use App\Support\Slugify;
use Core\Database\Connection;
use Core\Http\Request;
use Core\Http\Response;
use Core\Http\Session;

class TagController
{
    public function index(): Response
    {
        can_or_403('manage_categories');
        return view('admin.tags.index', [
            'pageTitle' => '标签管理',
        ]);
    }

    /**
     * AJAX 搜索接口 - 返回 JSON
     * 支持：关键词搜索(name/slug)、排序、分页
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

        $sql = "SELECT t.id, t.name, t.slug, t.description, t.created_at,
                       COUNT(pt.post_id) AS post_count
                FROM tags t
                LEFT JOIN post_tag pt ON pt.tag_id = t.id
                WHERE 1=1";
        $bindings = [];

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $sql .= " AND (t.name LIKE :q1 OR t.slug LIKE :q2 OR t.description LIKE :q3)";
            $like = '%' . $search . '%';
            $bindings[':q1'] = $like;
            $bindings[':q2'] = $like;
            $bindings[':q3'] = $like;
        }

        $sql .= " GROUP BY t.id, t.name, t.slug, t.description, t.created_at";

        $countSql = "SELECT COUNT(*) FROM ($sql) AS sub";
        $stmt = $pdo->prepare($countSql);
        $stmt->execute($bindings);
        $total = (int) $stmt->fetchColumn();

        $sortMap = [
            'name'       => 't.name',
            'slug'       => 't.slug',
            'created_at' => 't.created_at',
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
            $sess->flash('error', '请选择要操作的标签');
            return redirect(route('admin.tags.index'));
        }
        if ($action === 'delete') {
            $count = 0;
            foreach ($ids as $id) {
                $tag = Tag::find($id);
                if ($tag) {
                    $tag->delete();
                    app(\Core\Database\QueryBuilder::class)
                        ->table('post_tag')
                        ->where('tag_id', '=', $id)
                        ->delete();
                    $count++;
                }
            }
            $sess->flash('success', "已删除 {$count} 个标签");
        }
        return redirect(route('admin.tags.index'));
    }

    public function store(): Response
    {
        can_or_403('manage_categories');
        $request = app(Request::class);
        $name = trim((string) $request->input('name', ''));
        if ($name === '') return redirect(route('admin.tags.index'));
        $slug = trim((string) $request->input('slug', ''));
        if ($slug === '') $slug = Slugify::make($name, 'tag');
        $slug = Slugify::unique($slug, 'tags');

        Tag::query()->insert([
            'name'       => $name,
            'slug'       => $slug,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        app(Session::class)->flash('success', '标签已创建');
        return redirect(route('admin.tags.index'));
    }

    public function delete(array $params): Response
    {
        can_or_403('manage_categories');
        $id = (int) $params['id'];
        $tag = Tag::find($id);
        if ($tag) {
            $tag->delete();
            app(\Core\Database\QueryBuilder::class)
                ->table('post_tag')
                ->where('tag_id', '=', $id)
                ->delete();
            app(Session::class)->flash('success', '标签已删除');
        }
        return redirect(route('admin.tags.index'));
    }
}