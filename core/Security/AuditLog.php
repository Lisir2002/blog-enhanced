<?php

namespace Core\Security;

use Core\Database\QueryBuilder;
use Core\Http\Request;

/**
 * 审计日志 - 记录敏感操作，用于安全审计和合规。
 *
 * 记录的操作类型：
 * - 登录成功/失败
 * - 用户创建/删除/角色变更
 * - 文章发布/删除
 * - 系统配置修改
 * - 主题/插件激活/停用
 *
 * 用法：
 *   AuditLog::record('user.login', '用户登录', ['user_id' => 1, 'ip' => '...']);
 *   AuditLog::record('post.delete', '删除文章', ['post_id' => 123]);
 */
class AuditLog
{
    /**
     * 记录一条审计日志。
     */
    public static function record(string $action, string $description, array $context = []): void
    {
        $request = app(Request::class);
        $user = app(\Core\Auth\AuthManager::class)->user();

        $data = [
            'action'        => $action,
            'description'   => $description,
            'user_id'       => $user?->getAttribute('id'),
            'username'      => $user?->getAttribute('username'),
            'ip'            => $request->ip(),
            'user_agent'    => substr($request->userAgent(), 0, 255),
            'context'       => json_encode($context, JSON_UNESCAPED_UNICODE),
            'created_at'    => date('Y-m-d H:i:s'),
        ];

        try {
            app(QueryBuilder::class)
                ->table('audit_logs')
                ->insert($data);
        } catch (\Throwable $e) {
            \Core\Log\Log::error('Audit log write failed', [
                'action' => $action,
                'error'  => $e->getMessage(),
            ]);
        }

        \Core\Log\Log::info('AUDIT: ' . $action . ' - ' . $description, $context);
    }

    /**
     * 查询审计日志。
     */
    public static function query(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $qb = app(QueryBuilder::class)->table('audit_logs');

        if (isset($filters['action'])) {
            $qb = $qb->where('action', 'LIKE', $filters['action'] . '%');
        }
        if (isset($filters['user_id'])) {
            $qb = $qb->where('user_id', '=', (int) $filters['user_id']);
        }
        if (isset($filters['ip'])) {
            $qb = $qb->where('ip', '=', $filters['ip']);
        }
        if (isset($filters['start_date'])) {
            $qb = $qb->where('created_at', '>=', $filters['start_date']);
        }
        if (isset($filters['end_date'])) {
            $qb = $qb->where('created_at', '<=', $filters['end_date']);
        }

        $total = $qb->count();
        $offset = ($page - 1) * $perPage;
        $data = $qb->orderBy('created_at', 'DESC')
            ->limit($perPage)
            ->offset($offset)
            ->get();

        return [
            'data'    => $data,
            'total'   => $total,
            'page'    => $page,
            'perPage' => $perPage,
        ];
    }

    /**
     * 清理指定天数前的审计日志。
     */
    public static function cleanup(int $days = 90): int
    {
        $cutoff = date('Y-m-d H:i:s', time() - $days * 86400);
        return app(QueryBuilder::class)
            ->table('audit_logs')
            ->where('created_at', '<', $cutoff)
            ->delete();
    }
}
