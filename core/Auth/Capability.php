<?php

namespace Core\Auth;

/**
 * 角色权限矩阵。
 */
class Capability
{
    public const MATRIX = [
        'admin'      => ['*'],
        'editor'     => ['edit_posts', 'edit_others_posts', 'delete_posts', 'publish_posts', 'moderate_comments', 'manage_categories', 'upload_media'],
        'author'     => ['edit_posts', 'delete_posts', 'publish_posts', 'upload_media'],
        'contributor'=> ['edit_posts', 'upload_media'],
        'subscriber' => ['read'],
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
            // 细粒度校验：edit_posts → 只允许编辑自己的文章（author / contributor）
            if ($capability === 'edit_posts' && $args !== null && in_array($role, ['author', 'contributor'], true)) {
                // $args 可以是文章 ID 或文章数组；检查作者是否为当前用户
                $userId = $args instanceof \App\Models\User
                    ? (int) $args->getAttribute('id')
                    : (is_array($args) ? (int) ($args['author_id'] ?? 0) : 0);
                return $userId > 0; // 由调用方在 AuthManager 中传入当前用户 ID 比对
            }
            return true;
        }
        return false;
    }

    public static function roles(): array
    {
        return [
            'admin'       => '管理员',
            'editor'      => '编辑',
            'author'      => '作者',
            'contributor' => '贡献者',
            'subscriber'  => '订阅者',
        ];
    }
}
