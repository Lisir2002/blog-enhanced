<?php

namespace Core\Routing;

use Core\Router;

/**
 * 路由校验器 — 扫描所有 route() 调用，检查是否有对应的路由定义。
 *
 * 使用方式：
 *   $validator = new RouteValidator($router);
 *   $result = $validator->validate();  // 返回 ['missing' => [...], 'defined' => [...]]
 *
 * 也可作为独立脚本运行：php public/index.php /__validate_routes
 */
class RouteValidator
{
    /** @var array<int, string> 需要扫描的目录 */
    private array $scanDirs;

    /** @var array<int, string> 排除的目录/文件模式 */
    private array $excludePatterns = [
        '/vendor/',
        '/storage/',
        '/node_modules/',
        '.min.js',
        '.min.css',
    ];

    /** @var array<string, bool> 已知的合法外部路由（如前台路由在后台视图中引用） */
    private array $allowedExternalRoutes = [
        'category.show' => true,
        'tag.show'      => true,
        'post.show'     => true,
        'home'          => true,
        'login'         => true,
        'register'      => true,
    ];

    public function __construct(private Router $router)
    {
        $this->scanDirs = [
            app_path('Controllers'),
            resource_path('views'),
        ];
    }

    /**
     * 扫描并校验所有 route() 调用。
     *
     * @return array{missing: array<int, array{name: string, file: string, line: int}>, defined: array<int, string>}
     */
    public function validate(): array
    {
        $definedRoutes = $this->router->getRouteNames();
        $referenced    = $this->collectRouteReferences();
        $missing       = [];

        foreach ($referenced as $ref) {
            $name = $ref['name'];
            if (isset($this->allowedExternalRoutes[$name])) {
                continue;
            }
            if (!in_array($name, $definedRoutes, true)) {
                $missing[] = $ref;
            }
        }

        return [
            'missing' => $missing,
            'defined' => $definedRoutes,
        ];
    }

    /**
     * 收集所有 route('xxx') 调用。
     *
     * @return array<int, array{name: string, file: string, line: int}>
     */
    public function collectRouteReferences(): array
    {
        $refs = [];
        foreach ($this->scanDirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $pathname = $file->getPathname();
                if (!$this->shouldScan($pathname)) {
                    continue;
                }
                $content = file_get_contents($pathname);
                if ($content === false) {
                    continue;
                }
                $refs = array_merge($refs, $this->extractRouteCalls($content, $pathname));
            }
        }
        return $refs;
    }

    /**
     * 判断文件是否需要扫描。
     */
    private function shouldScan(string $path): bool
    {
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        if (!in_array($ext, ['php', 'phtml'], true)) {
            return false;
        }
        foreach ($this->excludePatterns as $pattern) {
            if (str_contains($path, $pattern)) {
                return false;
            }
        }
        return true;
    }

    /**
     * 从文件内容中提取所有 route('xxx') 调用。
     *
     * @return array<int, array{name: string, file: string, line: int}>
     */
    private function extractRouteCalls(string $content, string $file): array
    {
        $refs = [];
        // 匹配 route('admin.xxx.yyy'...)
        // 支持：route('name'), route("name"), route('name', [...]), route("name", [...])
        $pattern = '/\broute\s*\(\s*[\'"]([^\'"]+)[\'"]/';
        if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $match) {
                $name    = $match[0];
                $offset  = $match[1];
                $line    = substr_count(substr($content, 0, $offset), "\n") + 1;
                $refs[]  = [
                    'name' => $name,
                    'file' => $file,
                    'line' => $line,
                ];
            }
        }
        return $refs;
    }

    /**
     * 返回格式化的报告字符串。
     */
    public function report(): string
    {
        $result = $this->validate();
        $lines  = [];

        $lines[] = '========== 路由校验报告 ==========';
        $lines[] = '已注册路由数: ' . count($result['defined']);
        $lines[] = '缺失路由数:   ' . count($result['missing']);
        $lines[] = '';

        if (empty($result['missing'])) {
            $lines[] = '✓ 所有 route() 调用均有对应路由定义。';
        } else {
            $lines[] = '✗ 以下路由被引用但未注册：';
            $lines[] = str_repeat('-', 60);
            foreach ($result['missing'] as $m) {
                $lines[] = sprintf('  [%s] %s (line %d)', $m['name'], $this->shortPath($m['file']), $m['line']);
            }
            $lines[] = str_repeat('-', 60);
            $lines[] = '';
            $lines[] = '建议：在 routes/admin.php 中添加对应路由，或检查路由名称拼写。';
        }

        return implode("\n", $lines);
    }

    /**
     * 获取短路径（相对于项目根目录）。
     */
    private function shortPath(string $path): string
    {
        $root = base_path();
        if (str_starts_with($path, $root)) {
            return substr($path, strlen($root) + 1);
        }
        return $path;
    }
}
