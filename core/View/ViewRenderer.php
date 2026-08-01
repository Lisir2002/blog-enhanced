<?php

namespace Core\View;

use Core\Http\Response;

/**
 * 后台视图渲染器（与主题渲染器分离）。
 */
class ViewRenderer
{
    private string $viewRoot;

    public function __construct()
    {
        $this->viewRoot = resource_path('views');
    }

    public function render(string $template, array $data = []): Response
    {
        $path = $this->viewRoot . '/' . str_replace('.', '/', $template) . '.php';
        if (!is_file($path)) {
            return (new Response())
                ->setBody("View [$template] not found at $path")
                ->setStatus(500)
                ->setContentType('text/html');
        }
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            include $path;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        $body = (string) ob_get_clean();
        return (new Response())->setContentType('text/html')->setBody($body);
    }

    public function exists(string $template): bool
    {
        $path = $this->viewRoot . '/' . str_replace('.', '/', $template) . '.php';
        return is_file($path);
    }
}
