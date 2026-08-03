<?php

namespace Core;

/**
 * 路由器 - 静态注册 + 动态分发。
 *
 * 用法：
 *   $router->get('/posts/{id}', [PostController::class, 'show'])->name('posts.show');
 *   $router->add('POST', '/admin/posts', [PostController::class, 'store'])->middleware(['auth','csrf']);
 */
class Router
{
    /** @var array<int, array{method: string, pattern: string, handler: callable|array, name: ?string, middleware: array<int, string>}> */
    private array $routes = [];

    /** @var array<string, int> name → index */
    private array $namedRoutes = [];

    /** @var array<string, callable> */
    private array $globalMiddleware = [];

    /** @var array<int, string> */
    private array $notFoundHandlers = [];

    /** @var array<string, string> pattern → compiled regex（请求内缓存） */
    private array $compiledCache = [];

    /**
     * 加载路由文件（默认 routes/web.php）。
     */
    public function loadRoutes(string $file): void
    {
        $router = $this;
        require $file;
    }

    /**
     * 注册一条路由。
     */
    public function add(string $method, string $pattern, callable|array $handler, ?string $name = null, array $middleware = []): static
    {
        $method = strtoupper($method);
        $index = count($this->routes);
        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'handler' => $handler,
            'name' => $name,
            'middleware' => $middleware,
        ];
        if ($name !== null) {
            $this->namedRoutes[$name] = $index;
        }
        return $this;
    }

    public function get(string $pattern, callable|array $handler, ?string $name = null, array $middleware = []): static
    {
        return $this->add('GET', $pattern, $handler, $name, $middleware);
    }

    public function post(string $pattern, callable|array $handler, ?string $name = null, array $middleware = []): static
    {
        return $this->add('POST', $pattern, $handler, $name, $middleware);
    }

    public function put(string $pattern, callable|array $handler, ?string $name = null, array $middleware = []): static
    {
        return $this->add('PUT', $pattern, $handler, $name, $middleware);
    }

    public function delete(string $pattern, callable|array $handler, ?string $name = null, array $middleware = []): static
    {
        return $this->add('DELETE', $pattern, $handler, $name, $middleware);
    }

    public function match(array $methods, string $pattern, callable|array $handler, ?string $name = null, array $middleware = []): static
    {
        foreach ($methods as $method) {
            $this->add($method, $pattern, $handler, $name, $middleware);
        }
        return $this;
    }

    /**
     * 给最近一条路由命名 - 链式 API。
     */
    public function name(string $name): static
    {
        $index = count($this->routes) - 1;
        if ($index < 0) {
            return $this;
        }
        $this->routes[$index]['name'] = $name;
        $this->namedRoutes[$name] = $index;
        return $this;
    }

    /**
     * 给最近一条路由追加中间件 - 链式 API。
     * 或注册全局中间件：middleware('name', $handler)
     */
    public function middleware(array|string $middleware, ?callable $handler = null): static
    {
        if ($handler !== null && is_string($middleware)) {
            $this->globalMiddleware[$middleware] = $handler;
            return $this;
        }
        $index = count($this->routes) - 1;
        if ($index < 0) {
            return $this;
        }
        $mw = is_array($middleware) ? $middleware : [$middleware];
        $this->routes[$index]['middleware'] = array_merge($this->routes[$index]['middleware'], $mw);
        return $this;
    }

    /**
     * 将 pattern 转为正则，提取参数名。
     */
    private function compilePattern(string $pattern): array
    {
        // {id} → ([^/]+); {id:\d+} → (\d+)
        $regex = preg_replace_callback(
            '/\{([a-zA-Z_]\w*)(?::([^}]+))?\}/',
            function ($m) {
                $name = $m[1];
                $constraint = $m[2] ?? '[^/]+';
                return "(?P<$name>$constraint)";
            },
            $pattern
        );
        $regex = '#^' . $regex . '$#u';
        return [$regex, []];
    }

    /**
     * 分发到匹配的路由。
     */
    public function dispatch(string $method, string $path): \Core\Http\Response
    {
        $method = strtoupper($method);
        $path = rtrim($path, '/');
        if ($path === '') {
            $path = '/';
        }

        // HTTP method override (for forms that don't support PUT/DELETE)
        if ($method === 'POST') {
            $request = app(\Core\Http\Request::class);
            $override = $request->input('_method') ?: $request->server('HTTP_X_HTTP_METHOD_OVERRIDE');
            if ($override !== null) {
                $method = strtoupper($override);
            }
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            // 正则编译缓存：避免每次请求对每条路由重新编译
            if (!isset($this->compiledCache[$route['pattern']])) {
                [$regex] = $this->compilePattern($route['pattern']);
                $this->compiledCache[$route['pattern']] = $regex;
            } else {
                $regex = $this->compiledCache[$route['pattern']];
            }
            if (!preg_match($regex, $path, $matches)) {
                continue;
            }
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            // Run middleware
            foreach ($route['middleware'] as $mw) {
                if (isset($this->globalMiddleware[$mw])) {
                    $result = ($this->globalMiddleware[$mw])($params);
                    if ($result instanceof \Core\Http\Response) {
                        return $result;
                    }
                }
            }

            return $this->invokeHandler($route['handler'], $params);
        }

        // 404 - let theme render
        return $this->invokeNotFound();
    }

    private function invokeHandler(callable|array $handler, array $params): \Core\Http\Response
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;
            $controller = app($class);
            // Use reflection to decide how to call: if method has parameters, pass $params array
            $ref = new \ReflectionMethod($controller, $method);
            $numParams = $ref->getNumberOfParameters();
            if ($numParams > 0) {
                $result = $controller->$method($params);
            } else {
                $result = $controller->$method();
            }
        } else {
            $result = $handler($params);
        }
        if ($result instanceof \Core\Http\Response) {
            return $result;
        }
        if (is_string($result)) {
            return (new \Core\Http\Response())->setBody($result);
        }
        if (is_array($result)) {
            return (new \Core\Http\Response())->json($result);
        }
        if ($result === null) {
            return new \Core\Http\Response();
        }
        return new \Core\Http\Response();
    }

    private function invokeNotFound(): \Core\Http\Response
    {
        // 记录 404，方便排查死链与扫描行为
        try {
            \Core\Log\Log::notice('404 Not Found', [
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
                'ip'  => $_SERVER['REMOTE_ADDR'] ?? '',
            ]);
        } catch (\Throwable) {
            // 日志失败不影响响应
        }

        // Try theme 404 template
        try {
            $theme = app(\Core\View\ThemeManager::class);
            if ($theme->templateExists('404')) {
                $resp = $theme->render('404', []);
                return $resp->setStatus(404);
            }
        } catch (\Throwable $e) {
            try {
                \Core\Log\Log::warning('404 template render failed', [
                    'msg' => $e->getMessage(),
                ]);
            } catch (\Throwable) {
                // fall through
            }
        }
        return (new \Core\Http\Response())
            ->setBody('<h1>404 Not Found</h1>')
            ->setStatus(404)
            ->setContentType('text/html');
    }

    /**
     * 用路由名 + 参数生成 URL。
     */
    public function route(string $name, array $params = []): string
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new \RuntimeException("Route [$name] not defined.");
        }
        $route = $this->routes[$this->namedRoutes[$name]];
        $url = $route['pattern'];
        foreach ($params as $key => $value) {
            $url = str_replace('{' . $key . '}', urlencode((string) $value), $url);
            $url = preg_replace('/\{' . preg_quote($key, '/') . ':([^}]+)\}/', urlencode((string) $value), $url);
        }
        // Strip remaining optional / unset placeholders
        $url = preg_replace('/\{[a-zA-Z_]\w*(?::[^}]+)?\}/', '', $url);
        return url(trim($url, '/'));
    }
}
