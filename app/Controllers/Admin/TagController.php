<?php

namespace App\Controllers\Admin;

use App\Models\Tag;
use App\Support\Slugify;
use Core\Http\Request;
use Core\Http\Response;
use Core\Http\Session;

class TagController
{
    public function index(): Response
    {
        $tags = Tag::query()->orderBy('name', 'ASC')->get();
        return view('admin.tags.index', [
            'tags'      => $tags,
            'pageTitle' => '标签管理',
        ]);
    }

    public function store(): Response
    {
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
