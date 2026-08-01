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

    public static function has(string $role, string $capability): bool
    {
        if (!isset(self::MATRIX[$role])) {
            return false;
        }
        $caps = self::MATRIX[$role];
        return in_array('*', $caps, true) || in_array($capability, $caps, true);
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
