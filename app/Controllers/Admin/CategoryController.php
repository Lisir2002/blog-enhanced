<?php

namespace App\Controllers\Admin;

use App\Models\Category;
use App\Support\Slugify;
use Core\Http\Request;
use Core\Http\Response;
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
