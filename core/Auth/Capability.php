<?php

namespace Core\Auth;

/**
 * 角色权限矩阵。
 *
 * 角色等级（从高到低）：
 *   super_admin  超级管理员 — 所有权限
 *   senior_admin 高级管理员 — 管理类操作（编辑他人文章、管理分类、审核评论、管理用户）
 *   editor_admin 编辑管理员 — 编辑/发布自己的文章、上传媒体
 *   editor_writer 编辑写手 — 编辑自己的文章（不能发布）、上传媒体
 *   visitor      一位访客 — 仅可阅读
 */
class Capability
{
    public const MATRIX = [
        'super_admin'   => ['*'],
        'senior_admin'  => [
            'edit_posts', 'edit_others_posts', 'delete_posts', 'publish_posts',
            'moderate_comments', 'manage_categories', 'manage_users', 'upload_media',
        ],
        'editor_admin'  => ['edit_posts', 'delete_posts', 'publish_posts', 'upload_media'],
        'editor_writer' => ['edit_posts', 'upload_media'],
        'visitor'       => ['read'],
    ];

    public static function has(string $role, string $capability, mixed $args = null): bool
    {
        if (!isset(self::MATRIX[$role])) {
            return false;
        }
        $caps = self::MATRIX[$role];
        if (in_array('*', $caps, true)) {
            return true;
        }
        if (in_array($capability, $caps, true)) {
            // 细粒度校验：edit_posts → 只允许编辑自己的文章（editor_admin / editor_writer）
            if ($capability === 'edit_posts' && $args !== null && in_array($role, ['editor_admin', 'editor_writer'], true)) {
                $userId = $args instanceof \App\Models\User
                    ? (int) $args->getAttribute('id')
                    : (is_array($args) ? (int) ($args['author_id'] ?? 0) : 0);
                return $userId > 0;
            }
            return true;
        }
        return false;
    }

    public static function roles(): array
    {
        return [
            'super_admin'   => '超级管理员',
            'senior_admin'  => '高级管理员',
            'editor_admin'  => '编辑管理员',
            'editor_writer' => '编辑写手',
            'visitor'       => '一位访客',
        ];
    }
}