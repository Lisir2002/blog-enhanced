<?php

namespace App\Controllers\Admin;

use App\Models\Tag;
use Core\Http\Response;
use Core\Http\Request;
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
        if ($slug === '') $slug = $this->slugify($name);
        while (Tag::query()->where('slug', '=', $slug)->first()) {
            $slug .= '-' . bin2hex(random_bytes(2));
        }
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

    private function slugify(string $text): string
    {
        if (preg_match('/^[\x{4e00}-\x{9fa5}]+/u', $text)) {
            return bin2hex(random_bytes(4));
        }
        $text = preg_replace('~[^\pL\d]+~u', '-', $text) ?? 'tag';
        $text = strtolower(preg_replace('/[^a-z0-9\-]/i', '', $text) ?? 'tag');
        $text = trim($text, '-');
        return $text !== '' ? $text : 'tag-' . bin2hex(random_bytes(3));
    }
}
