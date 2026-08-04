<?php

namespace Core\Console\Commands;

/**
 * 代码生成器 - 快速创建 Model/Controller/Middleware 等。
 *
 * 用法：
 *   php blog make:resource Product
 *   php blog make:controller Admin/OrderController --resource
 *   php blog make:model Order --migration
 *   php blog make:middleware Cors
 */
class MakeCommand
{
    private string $appPath;

    public function __construct()
    {
        $this->appPath = base_path('app');
    }

    public function handle(array $args): int
    {
        $type = $args[0] ?? '';
        $name = $args[1] ?? '';

        if ($type === '' || $name === '') {
            echo "Usage: php blog make:<type> <Name>\n";
            echo "Types: resource, controller, model, middleware, dto, migration\n";
            return 1;
        }

        return match ($type) {
            'resource' => $this->makeResource($name),
            'controller' => $this->makeController($name),
            'model' => $this->makeModel($name),
            'middleware' => $this->makeMiddleware($name),
            'dto' => $this->makeDto($name),
            'migration' => $this->makeMigration($name),
            default => $this->makeResource($type),
        };
    }

    private function makeResource(string $name): int
    {
        $this->makeModel($name);
        $this->makeController('Admin/' . $name . 'Controller');
        $this->makeDto($name . 'Data');
        $this->makeMigration('create_' . strtolower($this->snakeCase($name)) . '_table');
        echo "✅ Resource [$name] created (Model + Controller + DTO + Migration)\n";
        return 0;
    }

    private function makeModel(string $name): int
    {
        $path = $this->appPath . '/Models/' . $name . '.php';
        if (file_exists($path)) {
            echo "⚠️  Model already exists: $path\n";
            return 1;
        }
        $table = strtolower($this->snakeCase($name));
        $content = "<?php\n\nnamespace App\\Models;\n\nuse Core\\Database\\Model;\n\nclass {$name} extends Model\n{\n    protected static string \$table = '{$table}';\n\n    protected array \$casts = [\n        'id' => 'int',\n    ];\n\n    // TODO: 定义关联关系\n}\n";
        file_put_contents($path, $content);
        echo "✅ Model created: $path\n";
        return 0;
    }

    private function makeController(string $name): int
    {
        $parts = explode('/', $name);
        $className = array_pop($parts);
        $subDir = implode('/', $parts);
        $namespace = 'App\\Controllers' . ($subDir ? '\\' . str_replace('/', '\\', $subDir) : '');

        $dir = $this->appPath . '/Controllers' . ($subDir ? '/' . $subDir : '');
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $path = $dir . '/' . $className . '.php';

        if (file_exists($path)) {
            echo "⚠️  Controller already exists: $path\n";
            return 1;
        }

        $content = "<?php\n\nnamespace {$namespace};\n\nuse Core\\Http\\Request;\nuse Core\\Http\\Response;\nuse Core\\Http\\Session;\n\nclass {$className}\n{\n    public function index(): Response\n    {\n        return (new Response())->setBody('TODO: index');\n    }\n\n    public function create(): Response\n    {\n        return (new Response())->setBody('TODO: create');\n    }\n\n    public function store(Request \$request, Session \$session): Response\n    {\n        return (new Response())->redirect('/');\n    }\n\n    public function edit(array \$params): Response\n    {\n        return (new Response())->setBody('TODO: edit');\n    }\n\n    public function update(array \$params, Request \$request, Session \$session): Response\n    {\n        return (new Response())->redirect('/');\n    }\n\n    public function delete(array \$params, Session \$session): Response\n    {\n        return (new Response())->redirect('/');\n    }\n}\n";
        file_put_contents($path, $content);
        echo "✅ Controller created: $path\n";
        return 0;
    }

    private function makeDto(string $name): int
    {
        $dir = $this->appPath . '/DTO';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $path = $dir . '/' . $name . '.php';
        if (file_exists($path)) {
            echo "⚠️  DTO already exists: $path\n";
            return 1;
        }
        $content = "<?php\n\nnamespace App\\DTO;\n\nuse Core\\Http\\Request;\n\nclass {$name}\n{\n    public static function fromRequest(Request \$request): static\n    {\n        return new static();\n    }\n\n    public function validate(): array\n    {\n        return [];\n    }\n\n    public function isValid(): bool\n    {\n        return empty(\$this->validate());\n    }\n\n    public function toArray(): array\n    {\n        return [];\n    }\n}\n";
        file_put_contents($path, $content);
        echo "✅ DTO created: $path\n";
        return 0;
    }

    private function makeMigration(string $name): int
    {
        $dir = base_path('database/migrations');
        $timestamp = date('Ymd_His');
        $filename = $timestamp . '_' . $name . '.php';
        $path = $dir . '/' . $filename;
        $className = str_replace('_', '', ucwords($name, '_'));
        $content = "<?php\n\nnamespace Database\\Migrations;\n\nuse Core\\Database\\Migration;\n\nclass {$className} extends Migration\n{\n    public function up(): void\n    {\n        \$table = str_replace('create_', '', '{$name}');\n        \$table = str_replace('_table', '', \$table);\n        \$this->pdo->exec(\"CREATE TABLE IF NOT EXISTS {\$table} (id INTEGER PRIMARY KEY AUTOINCREMENT, created_at TEXT, updated_at TEXT)\");\n    }\n\n    public function down(): void\n    {\n        // TODO: 回滚\n    }\n}\n";
        file_put_contents($path, $content);
        echo "✅ Migration created: $path\n";
        return 0;
    }

    private function makeMiddleware(string $name): int
    {
        $path = base_path('core/Http/Middleware/' . $name . 'Middleware.php');
        if (file_exists($path)) {
            echo "⚠️  Middleware already exists: $path\n";
            return 1;
        }
        $content = "<?php\n\nnamespace Core\\Http\\Middleware;\n\nuse Core\\Http\\Response;\n\nclass {$name}Middleware implements MiddlewareInterface\n{\n    public function handle(array \$params, array \$args = []): ?Response\n    {\n        return null;\n    }\n}\n";
        file_put_contents($path, $content);
        echo "✅ Middleware created: $path\n";
        return 0;
    }

    private function snakeCase(string $name): string
    {
        // 处理连续大写字母（如 APIV2Client → api_v2_client）
        $name = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1_$2', $name);
        // 处理小写后跟大写（如 camelCase → camel_case）
        $name = preg_replace('/([a-z\d])([A-Z])/', '$1_$2', $name);
        return strtolower($name);
    }
}
