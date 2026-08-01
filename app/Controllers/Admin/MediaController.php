<?php

namespace App\Controllers\Admin;

use App\Models\Media;
use Core\Http\Response;
use Core\Http\Request;
use Core\Http\Session;

class MediaController
{
    public function index(): Response
    {
        $page = max(1, (int) app(Request::class)->input('page', 1));
        $perPage = 24;
        $offset = ($page - 1) * $perPage;
        $qb = Media::query();
        $total = (clone $qb)->count();
        $items = $qb->orderBy('created_at', 'DESC')->limit($perPage)->offset($offset)->get();
        $totalPages = max(1, (int) ceil($total / $perPage));

        return view('admin.media.index', [
            'items'      => $items,
            'page'       => $page,
            'totalPages' => $totalPages,
            'pageTitle'  => '媒体库',
        ]);
    }

    public function upload(): Response
    {
        $request = app(Request::class);
        $sess = app(Session::class);
        $file = $request->file('file');
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $sess->flash('error', '上传失败');
            return redirect(route('admin.media.index'));
        }

        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'doc', 'docx', 'mp4', 'mp3'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            $sess->flash('error', '不支持的文件类型');
            return redirect(route('admin.media.index'));
        }

        $yearMonth = date('Y/m');
        $relDir = 'uploads/' . $yearMonth;
        $absDir = public_path($relDir);
        if (!is_dir($absDir)) {
            @mkdir($absDir, 0777, true);
        }
        $safeName = bin2hex(random_bytes(8)) . '.' . $ext;
        $absPath = $absDir . '/' . $safeName;
        $relPath = $relDir . '/' . $safeName;

        if (!move_uploaded_file($file['tmp_name'], $absPath)) {
            $sess->flash('error', '文件保存失败');
            return redirect(route('admin.media.index'));
        }

        $mime = function_exists('mime_content_type') ? mime_content_type($absPath) : ($file['type'] ?? 'application/octet-stream');
        Media::query()->insert([
            'filename'    => $file['name'],
            'path'        => $relPath,
            'mime'        => $mime,
            'size'        => (int) $file['size'],
            'uploaded_by' => current_user()?->getAttribute('id'),
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        $sess->flash('success', '上传成功');
        return redirect(route('admin.media.index'));
    }

    public function delete(array $params): Response
    {
        $id = (int) $params['id'];
        $media = Media::find($id);
        if ($media) {
            $absPath = public_path($media->getAttribute('path'));
            if (is_file($absPath)) @unlink($absPath);
            $media->delete();
            app(Session::class)->flash('success', '已删除');
        }
        return redirect(route('admin.media.index'));
    }
}
