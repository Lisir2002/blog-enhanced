<?php

namespace App\Controllers\Web;

use App\Models\Post;
use App\Models\Option;
use Core\Http\Request;
use Core\Http\Response;

class SearchController
{
    public function index(): Response
    {
        $request = app(Request::class);
        $q = trim((string) $request->input('q', ''));
        $page = max(1, (int) $request->input('page', 1));
        $perPage = (int) config('app.per_page', 10);
        $offset = ($page - 1) * $perPage;

        $posts = [];
        $total = 0;
        if ($q !== '') {
            // 全文搜索：title / content_md / excerpt 三个字段 OR LIKE
            $conn = app(\Core\Database\Connection::class);
            $pdo = $conn->pdo();
            $like = '%' . $q . '%';
            $now = date('Y-m-d H:i:s');

            $sql = "SELECT * FROM posts
                    WHERE status = 'published' AND published_at <= ?
                      AND (title LIKE ? OR content_md LIKE ? OR excerpt LIKE ?)
                    ORDER BY published_at DESC
                    LIMIT ? OFFSET ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$now, $like, $like, $like, $perPage, $offset]);
            $posts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $countSql = "SELECT COUNT(*) FROM posts
                          WHERE status = 'published' AND published_at <= ?
                            AND (title LIKE ? OR content_md LIKE ? OR excerpt LIKE ?)";
            $stmt2 = $pdo->prepare($countSql);
            $stmt2->execute([$now, $like, $like, $like]);
            $total = (int) $stmt2->fetchColumn();
        }
        $totalPages = max(1, (int) ceil($total / $perPage));

        return theme_view('search', [
            'query'      => $q,
            'posts'      => $posts,
            'page'       => $page,
            'totalPages' => $totalPages,
            'total'      => $total,
            'pageTitle'  => '搜索: ' . e($q) . ' - ' . Option::get('site_name', config('app.name')),
        ]);
    }
}
