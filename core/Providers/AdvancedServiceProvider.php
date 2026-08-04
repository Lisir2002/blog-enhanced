<?php

namespace Core\Providers;

use Core\Cache\PageCache;
use Core\Email\EmailTemplate;
use Core\i18n\Translator;
use Core\View\AssetManager;
use Core\View\DebugBar;
use Core\View\ImageProcessor;
use Core\View\MenuManager;
use Core\View\Shortcode;
use Core\View\ThemeManager;
use Core\View\WidgetManager;
use Core\SEO\Sitemap;

class AdvancedServiceProvider extends Provider
{
    public function register(): void
    {
        $this->app->singleton(Translator::class);
        $this->app->singleton(ImageProcessor::class);
        $this->app->singleton(Sitemap::class);
        $this->app->singleton(PageCache::class);
    }

    public function boot(): void
    {
        // 注册安全头中间件
        $router = $this->app->get(\Core\Router::class);
        $router->middleware('security', \Core\Http\Middleware\SecurityHeadersMiddleware::class);

        // 注册邮件模板
        EmailTemplate::register('comment_notification', '评论通知', '当收到新评论时通知作者');
        EmailTemplate::register('register_welcome', '欢迎注册', '新用户注册成功通知');
        EmailTemplate::register('password_reset', '密码重置', '密码重置链接');

        // 注册 Webhook 触发点
        add_action('post_saved', function ($id, $data, $isUpdate) {
            \Core\Webhook\Webhook::trigger('post.saved', [
                'post_id' => $id,
                'is_update' => $isUpdate,
                'title' => $data['title'] ?? '',
            ]);
        }, 20);

        add_action('comment_created', function ($commentId, $postId) {
            \Core\Webhook\Webhook::trigger('comment.created', [
                'comment_id' => $commentId,
                'post_id' => $postId,
            ]);
        }, 20);

        // 文章保存时清缓存
        add_action('post_saved', function ($id) {
            $pageCache = $this->app->get(PageCache::class);
            $pageCache->flush(str_replace('/', '-', 'post-' . $id));
            cache_forget('sidebar.recent');
        }, 30);

        // 输出调试条
        add_action('wp_footer', function () {
            echo debug_bar();
        }, 99);
    }
}
