<?php

namespace App\Controllers\Admin;

use App\Models\Category;
use Core\Http\Response;
use Core\Http\Request;
use Core\Http\Session;

class CategoryController
{
    public function index(): Response
    {
        $categories = Category::query()->orderBy('name', 'ASC')->get();
        return view('admin.categories.index', [
            'categories' => $categories,
            'pageTitle'  => '分类管理',
        ]);
    }

    public function store(): Response
    {
        $request = app(Request::class);
        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            return redirect(route('admin.categories.index'));
        }
        $slug = trim((string) $request->input('slug', ''));
        if ($slug === '') {
            $slug = $this->slugify($name);
        }
        // unique
        while (Category::query()->where('slug', '=', $slug)->first()) {
            $slug .= '-' . bin2hex(random_bytes(2));
        }
        Category::query()->insert([
            'name'        => $name,
            'slug'        => $slug,
            'description' => trim((string) $request->input('description', '')),
            'parent_id'   => (int) $request->input('parent_id', 0),
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
        app(Session::class)->flash('success', '分类已创建');
        return redirect(route('admin.categories.index'));
    }

    public function update(array $params): Response
    {
        $id = (int) $params['id'];
        $cat = Category::find($id);
        if (!$cat) return redirect(route('admin.categories.index'));
        $request = app(Request::class);
        $data = [
            'name'        => trim((string) $request->input('name', '')),
            'slug'        => trim((string) $request->input('slug', '')),
            'description' => trim((string) $request->input('description', '')),
            'parent_id'   => (int) $request->input('parent_id', 0),
            'updated_at'  => date('Y-m-d H:i:s'),
        ];
        if ($data['slug'] === '') $data['slug'] = $this->slugify($data['name']);
        Category::query()->where('id', '=', $id)->update($data);
        app(Session::class)->flash('success', '分类已更新');
        return redirect(route('admin.categories.index'));
    }

    public function delete(array $params): Response
    {
        $id = (int) $params['id'];
        $cat = Category::find($id);
        if ($cat) {
            $cat->delete();
            app(Session::class)->flash('success', '分类已删除');
        }
        return redirect(route('admin.categories.index'));
    }

    private function slugify(string $text): string
    {
        $text = trim($text);
        if (preg_match('/^[\x{4e00}-\x{9fa5}]+/u', $text)) {
            return bin2hex(random_bytes(4));
        }
        $text = preg_replace('~[^\pL\d]+~u', '-', $text) ?? 'cat';
        $text = preg_replace('/[^a-z0-9\-]/i', '', $text) ?? $text;
        $text = strtolower(trim($text, '-'));
        return $text !== '' ? $text : 'cat-' . bin2hex(random_bytes(3));
    }
}
