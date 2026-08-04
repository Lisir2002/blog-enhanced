<?php

namespace Core\View;

/**
 * 资产排队管理器 — 注册 + 依赖排序 + 渲染 CSS/JS 标签。
 *
 * 用法（functions.php）：
 *   add_action('wp_enqueue', function () {
 *       enqueue_style('style', theme_asset('assets/css/style.css'), [], '1.0.0');
 *       enqueue_script('main', theme_asset('assets/js/main.js'), [], '1.0.0', true);
 *   });
 *
 * 模板内（header.php）：
 *   wp_head()  // 自动输出 enqueued styles
 * 模板内（footer.php）：
 *   wp_footer() // 自动输出 enqueued scripts
 */
class AssetManager
{
    /** @var array<string, array{src: string, deps: array, ver: string}> */
    private array $styles = [];

    /** @var array<string, array{src: string, deps: array, ver: string, footer: bool}> */
    private array $scripts = [];

    private bool $stylesOutput = false;
    private bool $scriptsOutput = false;

    public function enqueueStyle(string $id, string $src, array $deps = [], string $ver = ''): void
    {
        $this->styles[$id] = ['src' => $src, 'deps' => $deps, 'ver' => $ver];
    }

    public function enqueueScript(string $id, string $src, array $deps = [], string $ver = '', bool $footer = false): void
    {
        $this->scripts[$id] = ['src' => $src, 'deps' => $deps, 'ver' => $ver, 'footer' => $footer];
    }

    public function dequeueStyle(string $id): void
    {
        unset($this->styles[$id]);
    }

    public function dequeueScript(string $id): void
    {
        unset($this->scripts[$id]);
    }

    /**
     * 输出所有 CSS link 标签（header 用）。
     */
    public function renderStyles(): string
    {
        $this->stylesOutput = true;
        $sorted = $this->sortDeps($this->styles);
        $html = '';
        foreach ($sorted as $id => $asset) {
            $url = $this->addVersion($asset['src'], $asset['ver']);
            if (!config('app.debug', false) && !str_contains($url, '.min.')) {
                $url = $this->tryMinify($url, 'css');
            }
            $html .= "<link rel=\"stylesheet\" href=\"{$url}\">\n";
        }
        return $html;
    }

    /**
     * 输出所有 script 标签（footer 用）。
     */
    public function renderScripts(bool $footerOnly = true): string
    {
        $this->scriptsOutput = true;
        $sorted = $this->sortDeps($this->scripts);
        $html = '';
        foreach ($sorted as $id => $asset) {
            if ($footerOnly && !$asset['footer']) {
                continue;
            }
            $url = $this->addVersion($asset['src'], $asset['ver']);
            $html .= "<script src=\"{$url}\"></script>\n";
        }
        return $html;
    }

    /**
     * 输出 header 区 script（footer=false 的脚本）。
     */
    public function renderHeaderScripts(): string
    {
        $sorted = $this->sortDeps($this->scripts);
        $html = '';
        foreach ($sorted as $id => $asset) {
            if ($asset['footer']) {
                continue;
            }
            $url = $this->addVersion($asset['src'], $asset['ver']);
            $html .= "<script src=\"{$url}\"></script>\n";
        }
        return $html;
    }

    /**
     * 拓扑排序依赖。
     */
    private function sortDeps(array $assets): array
    {
        $result = [];
        $visited = [];

        $visit = function (string $id) use (&$visit, &$result, &$visited, $assets): void {
            if (isset($visited[$id])) {
                return;
            }
            $visited[$id] = true;
            $deps = $assets[$id]['deps'] ?? [];
            foreach ($deps as $dep) {
                if (isset($assets[$dep])) {
                    $visit($dep);
                }
            }
            $result[$id] = $assets[$id];
        };

        foreach (array_keys($assets) as $id) {
            $visit($id);
        }

        return $result;
    }

    /**
     * 附加版本指纹参数（cache-busting）。
     */
    private function addVersion(string $src, string $ver): string
    {
        if ($ver !== '') {
            $sep = str_contains($src, '?') ? '&' : '?';
            return $src . $sep . 'ver=' . urlencode($ver);
        }
        return $src;
    }

    /**
     * 尝试使用 .min 版本（如果文件存在）。
     */
    private function tryMinify(string $url, string $ext): string
    {
        // 将 URL 转为文件路径
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $docRoot = public_path();
        $filePath = $docRoot . $path;
        $minPath = preg_replace('/\.' . $ext . '$/', '.min.' . $ext, $filePath);
        if ($minPath !== $filePath && is_file($minPath)) {
            return preg_replace('/\.' . $ext . '$/', '.min.' . $ext, $url);
        }
        return $url;
    }
}
