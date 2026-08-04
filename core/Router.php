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

    /** @var array<string, array<int, string>> 中间件组 */
    private array $middlewareGroups = [];

    /** @var array<string, string> 路由模型绑定：[param_name => ModelClass:field] */
    private array $modelBindings = [];

    /** @var array<string, array<int, string>> 路由绑定的模型绑定（按路由 index） */
    private array $routeModelBindings = [];

    /**
     * 加载路由文件（默认 routes/web.php）。
     */
    public function loadRoutes(string $file): void
    {
        $router = $this;
        require $file;
    }

    /**
     * 路由分组 - 共享前缀/中间件/命名空间。
     *
     * 用法：
     *   $router->group(['prefix' => '/admin', 'middleware' => ['admin']], function ($router) {
     *       $router->get('/posts', [PostController::class, 'index']);
     *   });
     */
    public function group(array $attributes, Closure $callback): static
    {
        $prefix = $attributes['prefix'] ?? '';
        $middleware = isset($attributes['middleware'])
            ? (is_array($attributes['middleware']) ? $attributes['middleware'] : [$attributes['middleware']])
            : [];
        $namespace = $attributes['namespace'] ?? '';
        $namePrefix = $attributes['name'] ?? '';

        // 保存当前组属性
        $previousPrefix = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;
        $previousNamespace = $this->groupNamespace;
        $previousNamePrefix = $this->groupNamePrefix;

        // 合并组属性
        $this->groupPrefix = $previousPrefix . $prefix;
        $this->groupMiddleware = array_merge($previousMiddleware, $middleware);
        $this->groupNamespace = $previousNamespace . $namespace;
        $this->groupNamePrefix = $previousNamePrefix . $namePrefix;

        // 执行回调
        $callback($this);

        // 恢复组属性
        $this->groupPrefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
        $this->groupNamespace = $previousNamespace;
        $this->groupNamePrefix = $previousNamePrefix;

        return $this;
    }

    /** @var string 当前组前缀 */
    private string $groupPrefix = '';

    /** @var array<int, string> 当前组中间件 */
    private array $groupMiddleware = [];

    /** @var string 当前组命名空间 */
    private string $groupNamespace = '';

    /** @var string 当前组名称前缀 */
    private string $groupNamePrefix = '';

    /**
     * 注册中间件组。
     */
    public function middlewareGroup(string $name, array $middleware): static
    {
        $this->middlewareGroups[$name] = $middleware;
        return $this;
    }

    /**
     * 路由模型绑定 - {post:slug} 自动解析为 Post 模型。
     */
    public function model(string $param, string $class, ?string $field = null): static
    {
        $this->modelBindings[$param] = $class . ':' . ($field ?? 'id');
        return $this;
    }

    /**
     * 注册一条路由。
     */
    public function add(string $method, string $pattern, callable|array $handler, ?string $name = null, array $middleware = []): static
    {
        $method = strtoupper($method);

        // 应用组前缀
        $pattern = $this->groupPrefix . $pattern;
        // 规范化：去除首尾斜杠，合并多个斜杠，确保以 / 开头
        $pattern = preg_replace('#/+#', '/', $pattern);
        $pattern = '/' . trim($pattern, '/');
        if ($pattern === '/') {
            $pattern = '/';
        }

        // 应用组命名空间（如果是 [Class, method] 形式且 Class 不含 \）
        if (is_array($handler) && $this->groupNamespace !== '' && isset($handler[0]) && is_string($handler[0]) && !str_starts_with($handler[0], '\\')) {
            $handler[0] = $this->groupNamespace . '\\' . $handler[0];
        }

        // 合并组中间件 + 路由中间件
        $allMiddleware = array_merge($this->groupMiddleware, $middleware);

        $index = count($this->routes);
        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'handler' => $handler,
            'name' => $name !== null ? $this->groupNamePrefix . $name : $name,
            'middleware' => $allMiddleware,
        ];
        if ($name !== null) {
            $this->namedRoutes[$this->groupNamePrefix . $name] = $index;
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
    public function middleware(array|string $middleware, callable|string|null $handler = null): static
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

            // 路由模型绑定：自动查询模型实例
            foreach ($params as $key => $value) {
                if (isset($this->modelBindings[$key])) {
                    [$class, $field] = explode(':', $this->modelBindings[$key], 2);
                    if (class_exists($class) && method_exists($class, 'findBy')) {
                        $model = $class::findBy($field, $value);
                        if ($model === null) {
                            return $this->invokeNotFound();
                        }
                        $params[$key] = $model;
                    }
                }
            }

            // Inject route context for Conditional tags
            \Core\View\Conditional::set($route['name'], $params);

            // 展开中间件组 + 执行中间件链
            $middlewareList = $this->expandMiddleware($route['middleware']);
            foreach ($middlewareList as $mw) {
                // 解析参数化中间件：throttle:60,1 → ['throttle', ['60', '1']]
                $mwName = $mw;
                $mwArgs = [];
                if (str_contains($mw, ':')) {
                    [$mwName, $argStr] = explode(':', $mw, 2);
                    $mwArgs = explode(',', $argStr);
                }

                if (isset($this->globalMiddleware[$mwName])) {
                    $handler = $this->globalMiddleware[$mwName];
                    if (is_string($handler) && class_exists($handler)) {
                        $instance = app($handler);
                        $result = method_exists($instance, 'handle')
                            ? $instance->handle($params, $mwArgs)
                            : $instance($params);
                    } else {
                        $result = $handler($params);
                    }
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
     * 展开中间件组：将组名替换为组内中间件列表。
     */
    private function expandMiddleware(array $middleware): array
    {
        $expanded = [];
        foreach ($middleware as $mw) {
            // 如果是中间件组名（且不含 :），展开组
            $baseName = str_contains($mw, ':') ? substr($mw, 0, strpos($mw, ':')) : $mw;
            if (isset($this->middlewareGroups[$baseName]) && !isset($this->globalMiddleware[$baseName])) {
                $groupMw = $this->middlewareGroups[$baseName];
                // 如果带参数（如 web:custom），传递给组内每个中间件
                if (str_contains($mw, ':')) {
                    $argStr = substr($mw, strpos($mw, ':') + 1);
                    $groupMw = array_map(fn($m) => $m . ':' . $argStr, $groupMw);
                }
                $expanded = array_merge($expanded, $this->expandMiddleware($groupMw));
            } else {
                $expanded[] = $mw;
            }
        }
        return $expanded;
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
