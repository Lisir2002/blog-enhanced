<?php

namespace App\Controllers\Admin;

use App\Models\Post;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Option;
use Core\Http\Response;
use Core\Http\Session;
use Core\Http\Request;

class PostController
{
    public function index(): Response
    {
        $request = app(Request::class);
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $qb = Post::query();
        $status = $request->input('status');
        if ($status && in_array($status, ['draft', 'published', 'archived'], true)) {
            $qb = $qb->where('status', '=', $status);
        }
        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $qb = $qb->where('title', 'LIKE', '%' . $search . '%');
        }
        $total = $qb->count();
        $posts = $qb->orderBy('created_at', 'DESC')->limit($perPage)->offset($offset)->get();
        $totalPages = max(1, (int) ceil($total / $perPage));

        return view('admin.posts.index', [
            'posts'      => $posts,
            'page'       => $page,
            'totalPages' => $totalPages,
            'total'      => $total,
            'status'     => $status ?? '',
            'search'     => $search,
            'pageTitle'  => '文章列表',
        ]);
    }

    public function create(): Response
    {
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
        $request = app(Request::class);
        $sess = app(Session::class);

        $data = $this->validateAndCollect($request);
        $data['author_id'] = current_user()->getAttribute('id');
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        $data['published_at'] = $data['status'] === 'published' ? ($data['published_at'] ?? date('Y-m-d H:i:s')) : null;

        // Generate slug if not provided
        if (empty($data['slug'])) {
            $data['slug'] = $this->slugify($data['title']);
        }
        // Ensure slug uniqueness
        $data['slug'] = $this->uniqueSlug($data['slug']);

        // Convert markdown to html
        $parsedown = app(\Parsedown::class);
        $data['content_html'] = $parsedown->text($data['content_md'] ?? '');

        $pdo = app(\Core\Database\Connection::class)->pdo();
        try {
            $pdo->beginTransaction();
            $id = Post::query()->insert($data);
            $this->syncTags((int) $id, $request->input('tags', ''));
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            \Core\Log\Log::error('Post store failed', [
                'msg' => $e->getMessage(),
            ]);
            $sess->flash('error', '保存失败：' . e($e->getMessage()));
            return redirect(route('admin.posts.create'));
        }

        do_action('post_saved', $id, $data, false);

        $sess->flash('success', '文章已保存');
        return redirect(route('admin.posts.edit', ['id' => $id]));
    }

    public function edit(array $params): Response
    {
        $id = (int) $params['id'];
        $post = Post::find($id);
        if (!$post) {
            return redirect(route('admin.posts.index'));
        }
        $categories = Category::all();
        $tagIds = app(\Core\Database\QueryBuilder::class)
            ->table('post_tag')
            ->where('post_id', '=', $id)
            ->get();
        $tagIds = array_column($tagIds, 'tag_id');
        $tags = [];
        foreach ($tagIds as $tid) {
            $t = Tag::find($tid);
            if ($t) $tags[] = $t->getAttribute('name');
        }

        return view('admin.posts.form', [
            'post'       => $post,
            'categories' => $categories,
            'tags'       => implode(', ', $tags),
            'pageTitle'  => '编辑文章',
            'action'     => route('admin.posts.update', ['id' => $id]),
        ]);
    }

    public function update(array $params): Response
    {
        $id = (int) $params['id'];
        $post = Post::find($id);
        if (!$post) {
            return redirect(route('admin.posts.index'));
        }
        $request = app(Request::class);
        $sess = app(Session::class);
        $data = $this->validateAndCollect($request);
        $data['updated_at'] = date('Y-m-d H:i:s');
        if ($data['status'] === 'published' && empty($post->getAttribute('published_at'))) {
            $data['published_at'] = date('Y-m-d H:i:s');
        }

        if (empty($data['slug'])) {
            $data['slug'] = $this->slugify($data['title']);
        }
        if ($data['slug'] !== $post->getAttribute('slug')) {
            $data['slug'] = $this->uniqueSlug($data['slug'], $id);
        }

        $parsedown = app(\Parsedown::class);
        $data['content_html'] = $parsedown->text($data['content_md'] ?? '');

        $pdo = app(\Core\Database\Connection::class)->pdo();
        try {
            $pdo->beginTransaction();
            Post::query()->where('id', '=', $id)->update($data);
            $this->syncTags($id, $request->input('tags', ''));
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            \Core\Log\Log::error('Post update failed', [
                'id'  => $id,
                'msg' => $e->getMessage(),
            ]);
            $sess->flash('error', '更新失败：' . e($e->getMessage()));
            return redirect(route('admin.posts.edit', ['id' => $id]));
        }

        do_action('post_saved', $id, $data, true);

        $sess->flash('success', '文章已更新');
        return redirect(route('admin.posts.edit', ['id' => $id]));
    }

    public function delete(array $params): Response
    {
        $id = (int) $params['id'];
        $post = Post::find($id);
        if ($post) {
            $pdo = app(\Core\Database\Connection::class)->pdo();
            try {
                $pdo->beginTransaction();
                $post->delete();
                app(\Core\Database\QueryBuilder::class)
                    ->table('post_tag')
                    ->where('post_id', '=', $id)
                    ->delete();
                $pdo->commit();
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                \Core\Log\Log::error('Post delete failed', [
                    'id'  => $id,
                    'msg' => $e->getMessage(),
                ]);
                app(Session::class)->flash('error', '删除失败');
                return redirect(route('admin.posts.index'));
            }
            app(Session::class)->flash('success', '文章已删除');
        }
        return redirect(route('admin.posts.index'));
    }

    private function validateAndCollect(Request $request): array
    {
        return [
            'title'           => trim((string) $request->input('title', '')),
            'slug'            => trim((string) $request->input('slug', '')),
            'content_md'      => (string) $request->input('content_md', ''),
            'excerpt'         => trim((string) $request->input('excerpt', '')),
            'cover'           => trim((string) $request->input('cover', '')),
            'category_id'     => (int) $request->input('category_id', 0) ?: null,
            'status'          => in_array($request->input('status'), ['draft', 'published', 'archived'], true) ? $request->input('status') : 'draft',
            'seo_title'        => trim((string) $request->input('seo_title', '')),
            'seo_description'  => trim((string) $request->input('seo_description', '')),
            'is_pinned'       => $request->input('is_pinned') ? 1 : 0,
            'allow_comments'  => $request->input('allow_comments') === null ? 1 : ($request->input('allow_comments') ? 1 : 0),
            'published_at'    => trim((string) $request->input('published_at', '')) ?: null,
        ];
    }

    private function syncTags(int $postId, string $tagStr): void
    {
        $qb = app(\Core\Database\QueryBuilder::class);
        $qb->table('post_tag')->where('post_id', '=', $postId)->delete();
        if (trim($tagStr) === '') return;
        $names = array_filter(array_map('trim', explode(',', $tagStr)));
        foreach ($names as $name) {
            $existing = Tag::query()->where('name', '=', $name)->first();
            $tagId = $existing ? $existing['id'] : null;
            if (!$tagId) {
                $slug = $this->slugify($name);
                $tagId = Tag::query()->insert([
                    'name'       => $name,
                    'slug'       => $this->uniqueTagSlug($slug),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
            $qb->table('post_tag')->insert([
                'post_id' => $postId,
                'tag_id'  => $tagId,
            ]);
        }
    }

    private function slugify(string $text): string
    {
        $text = trim($text);
        if ($text === '') return 'post-' . bin2hex(random_bytes(3));
        // Allow Chinese characters: convert to pinyin would be ideal; use hash fallback
        if (preg_match('/^[\x{4e00}-\x{9fa5}]+/u', $text)) {
            return bin2hex(random_bytes(4));
        }
        $text = preg_replace('~[^\pL\d]+~u', '-', $text) ?? 'post';
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
        $text = preg_replace('/[^a-z0-9\-]/i', '', $text) ?? $text;
        $text = strtolower($text);
        $text = trim($text, '-');
        return $text !== '' ? $text : 'post-' . bin2hex(random_bytes(3));
    }

    private function uniqueSlug(string $slug, ?int $exceptId = null): string
    {
        $base = $slug;
        $i = 1;
        while (true) {
            $qb = Post::query()->where('slug', '=', $slug);
            if ($exceptId) {
                $qb = $qb->where('id', '!=', $exceptId);
            }
            if (!$qb->first()) break;
            $slug = $base . '-' . (++$i);
        }
        return $slug;
    }

    private function uniqueTagSlug(string $slug): string
    {
        $base = $slug;
        $i = 1;
        while (Tag::query()->where('slug', '=', $slug)->first()) {
            $slug = $base . '-' . (++$i);
        }
        return $slug;
    }
}
