<?php

namespace Core\Email;

use App\Models\Option;

/**
 * 邮件模板系统 — 主题/插件可注册邮件模板。
 *
 * 用法:
 *   $html = EmailTemplate::render('comment_notification', [
 *       'site_name' => 'My Blog',
 *       'comment' => $comment,
 *       'post' => $post,
 *   ]);
 *   mail($to, $subject, $html);
 *
 * 主题可覆盖:
 *   resources/themes/{theme}/emails/comment_notification.php
 */
class EmailTemplate
{
    /** @var array<string, string> */
    private static array $registered = [];

    public static function register(string $id, string $name, string $description = ''): void
    {
        self::$registered[$id] = $name;
    }

    public static function getRegistered(): array
    {
        return self::$registered;
    }

    public static function render(string $templateId, array $data = []): string
    {
        $path = self::resolvePath($templateId);
        if (!$path || !is_file($path)) {
            return self::defaultTemplate($templateId, $data);
        }

        extract($data, EXTR_SKIP);
        ob_start();
        try {
            include $path;
        } catch (\Throwable $e) {
            ob_end_clean();
            return '';
        }
        return (string) ob_get_clean();
    }

    private static function resolvePath(string $templateId): ?string
    {
        // 主题覆盖优先
        try {
            $theme = app(\Core\View\ThemeManager::class);
            $path = $theme->path('emails/' . $templateId . '.php');
            if ($path && is_file($path)) {
                return $path;
            }
        } catch (\Throwable) {}

        // 系统默认
        $system = resource_path('emails/' . $templateId . '.php');
        return is_file($system) ? $system : null;
    }

    private static function defaultTemplate(string $templateId, array $data): string
    {
        $siteName = $data['site_name'] ?? Option::get('site_name', config('app.name', 'Blog'));
        return "<!DOCTYPE html><html><body style=\"font-family:sans-serif;max-width:600px;margin:0 auto;padding:20px\">"
            . "<h2>" . e($siteName) . "</h2>"
            . "<p>您收到一条来自 <strong>" . e($siteName) . "</strong> 的通知。</p>"
            . "<p>模板: " . e($templateId) . "</p>"
            . "</body></html>";
    }
}
