<?php

namespace App\Controllers\Web;

use App\Models\Post;
use App\Models\Comment;
use Core\Http\Response;
use Core\Http\Session;

class CommentController
{
    public function store(array $params): Response
    {
        $request = app(\Core\Http\Request::class);
        $sess = app(Session::class);

        // Honeypot anti-spam
        $honeypot = $request->input('website_url');
        if (!empty($honeypot)) {
            return redirect(url('/'));
        }

        $post = Post::findBy('slug', $params['slug']);
        if (!$post) {
            return redirect(url('/'));
        }

        $authorName = trim((string) $request->input('author_name', ''));
        $authorEmail = trim((string) $request->input('author_email', ''));
        $content = trim((string) $request->input('content', ''));
        $parentId = (int) $request->input('parent_id', 0);

        if ($authorName === '' || $content === '') {
            $sess->flashInput($request->only(['author_name', 'author_email', 'content', 'parent_id']));
            $sess->flash('error', '昵称和内容不能为空');
            return redirect($post->url() . '#comment-form');
        }
        if (mb_strlen($content) > 2000) {
            $sess->flash('error', '评论内容过长');
            return redirect($post->url() . '#comment-form');
        }
        if ($authorEmail !== '' && !filter_var($authorEmail, FILTER_VALIDATE_EMAIL)) {
            $sess->flashInput($request->only(['author_name', 'author_email', 'content', 'parent_id']));
            $sess->flash('error', '邮箱格式不正确');
            return redirect($post->url() . '#comment-form');
        }
        // 昵称长度限制，防止恶意超长字符串
        if (mb_strlen($authorName) > 50) {
            $authorName = mb_substr($authorName, 0, 50);
        }

        $user = current_user();
        $status = 'pending';
        if ($user) {
            $status = 'approved';
        }

        Comment::query()->insert([
            'post_id'      => $post->getAttribute('id'),
            'parent_id'    => $parentId > 0 ? $parentId : 0,
            'author_name'  => $authorName,
            'author_email' => $authorEmail,
            'content'      => $content,
            'status'       => $status,
            'ip'           => $request->ip(),
            'user_agent'   => substr($request->userAgent(), 0, 255),
            'created_at'   => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);

        do_action('comment_posted', $post, $content);

        $sess->flash('success', $status === 'approved' ? '评论成功' : '评论已提交，等待审核');
        return redirect($post->url() . '#comments');
    }
}
