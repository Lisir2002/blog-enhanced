<?php

namespace App\Controllers\Admin;

use App\DTO\PostData;
use App\Models\Post;
use App\Models\Category;
use App\Services\PostService;
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
        $post = Post::find($id);
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
        $post = Post::find($id);
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
            $sess->flash('success', '文章已删除');
        } catch (\Throwable $e) {
            \Core\Log\Log::error('Post delete failed', ['id' => $id, 'msg' => $e->getMessage()]);
            $sess->flash('error', '删除失败');
        }
        return redirect(route('admin.posts.index'));
    }
}
