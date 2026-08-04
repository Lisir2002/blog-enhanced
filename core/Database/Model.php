<?php

namespace Core\Database;

use Core\Database\Relations\Relation;
use Core\Database\Relations\BelongsTo;
use Core\Database\Relations\HasMany;
use Core\Database\Relations\HasOne;
use Core\Database\Relations\BelongsToMany;

/**
 * 基础 Model 类 - 增强版。
 *
 * 增强能力：
 * - 关联关系：belongsTo / hasOne / hasMany / belongsToMany
 * - 预加载（Eager Loading）：with() 解决 N+1 查询
 * - 软删除（Soft Delete）：deleted_at 字段，delete() 仅标记
 * - 模型事件：saving/saved/creating/created/updating/updated/deleting/deleted
 * - 模型作用域（Query Scopes）：scopeXxx() 可复用查询条件
 */
abstract class Model
{
    protected static string $table;
    protected static string $primaryKey = 'id';
    protected array $attributes = [];
    protected array $casts = [];

    /** @var bool 是否启用软删除 */
    protected static bool $softDelete = false;

    /** @var array<string, mixed> 已加载的关联 */
    private array $relations = [];

    /** @var array<string, bool> 预加载的关联名 */
    protected array $eagerLoad = [];

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    public static function table(): string
    {
        return static::$table;
    }

    public function __get(string $name): mixed
    {
        // 优先返回已加载的关联
        if (array_key_exists($name, $this->relations)) {
            return $this->relations[$name];
        }

        // 如果是关联方法，自动加载
        if (method_exists($this, $name)) {
            $relation = $this->$name();
            if ($relation instanceof Relation) {
                $result = $relation->getResults();
                $this->relations[$name] = $result;
                return $result;
            }
        }

        return $this->attributes[$name] ?? null;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]) || array_key_exists($name, $this->relations);
    }

    public function toArray(): array
    {
        $arr = $this->attributes;
        // 包含已加载的关联
        foreach ($this->relations as $key => $value) {
            if (is_array($value)) {
                $arr[$key] = array_map(fn($m) => $m instanceof self ? $m->toArray() : $m, $value);
            } elseif ($value instanceof self) {
                $arr[$key] = $value->toArray();
            }
        }
        return $arr;
    }

    public function fill(array $data): static
    {
        foreach ($data as $k => $v) {
            $this->attributes[$k] = $v;
        }
        return $this;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->attributes)) {
            $value = $this->attributes[$key];
            if (isset($this->casts[$key])) {
                $value = $this->castTo($value, $this->casts[$key]);
            }
            return $value;
        }
        return $default;
    }

    private function castTo(mixed $value, string $type): mixed
    {
        return match ($type) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => (bool) $value,
            'array' => is_array($value) ? $value : (is_string($value) ? json_decode($value, true) : []),
            'json' => is_string($value) ? json_decode($value, true) : $value,
            'datetime' => $value ? new \DateTime($value) : null,
            default => $value,
        };
    }

    /* ─────── 关联关系 ─────── */

    /**
     * 多对一关联（如 Post belongsTo User）。
     */
    protected function belongsTo(string $related, string $foreignKey, ?string $ownerKey = null): BelongsTo
    {
        $ownerKey = $ownerKey ?? (new $related())::primaryKeyName();
        return new BelongsTo($related, $this, $foreignKey, $ownerKey);
    }

    /**
     * 一对多关联（如 User hasMany Post）。
     */
    protected function hasMany(string $related, string $foreignKey, ?string $localKey = null): HasMany
    {
        $localKey = $localKey ?? static::primaryKeyName();
        return new HasMany($related, $this, $foreignKey, $localKey);
    }

    /**
     * 一对一关联（如 User hasOne Profile）。
     */
    protected function hasOne(string $related, string $foreignKey, ?string $localKey = null): HasOne
    {
        $localKey = $localKey ?? static::primaryKeyName();
        return new HasOne($related, $this, $foreignKey, $localKey);
    }

    /**
     * 多对多关联（如 Post belongsToMany Tag）。
     */
    protected function belongsToMany(
        string $related,
        string $table,
        string $foreignPivotKey,
        string $relatedPivotKey,
        ?string $parentKey = null,
        ?string $relatedKey = null,
    ): BelongsToMany {
        $parentKey = $parentKey ?? static::primaryKeyName();
        $relatedKey = $relatedKey ?? (new $related())::primaryKeyName();
        return new BelongsToMany($related, $this, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey);
    }

    /**
     * 设置已加载的关联（用于预加载）。
     */
    public function setRelation(string $name, mixed $value): static
    {
        $this->relations[$name] = $value;
        return $this;
    }

    /* ─────── 预加载（Eager Loading） ─────── */

    /**
     * 静态方法：with() 预加载关联。
     *
     * 用法：Post::with(['author', 'category'])->get();
     */
    public static function with(array|string $relations): QueryBuilder
    {
        $relations = is_array($relations) ? $relations : [$relations];
        $qb = static::query();
        // 将预加载信息附加到 QueryBuilder
        if (method_exists($qb, 'setEagerLoad')) {
            $qb->setEagerLoad(static::class, $relations);
        }
        // 同时暂存到静态属性（兼容方式）
        foreach ($relations as $rel) {
            static::$pendingEagerLoad[$rel] = null;
        }
        return $qb;
    }

    /**
     * 获取已加载的关联。
     */
    public function getRelation(string $name): mixed
    {
        return $this->relations[$name] ?? null;
    }

    /**
     * 动态调用模型作用域。
     *
     * 用法：$model->scope('published')  → 调用 scopePublished()
     */
    public function scope(string $name, ...$args): static
    {
        $method = 'scope' . ucfirst($name);
        if (method_exists($this, $method)) {
            $this->$method(...$args);
        }
        return $this;
    }

    /**
     * 取消全局作用域。
     */
    public static function withoutGlobalScope(string $scope): QueryBuilder
    {
        $qb = static::query();
        // 简化实现：标记跳过某个作用域
        $qb->_withoutScope = $scope;
        return $qb;
    }

    /* ─────── 软删除 ─────── */

    /**
     * 软删除：设置 deleted_at 字段。
     */
    public function delete(): bool
    {
        $pk = static::$primaryKey;
        if (empty($this->attributes[$pk])) {
            return false;
        }

        // 触发 deleting 事件
        if (!static::fireModelEvent('deleting', $this)) {
            return false;
        }

        if (static::$softDelete) {
            $result = static::query()
                ->where($pk, '=', $this->attributes[$pk])
                ->update(['deleted_at' => date('Y-m-d H:i:s')]) > 0;
        } else {
            $result = static::query()
                ->where($pk, '=', $this->attributes[$pk])
                ->delete() > 0;
        }

        if ($result) {
            static::fireModelEvent('deleted', $this, false);
        }
        return $result;
    }

    /**
     * 恢复软删除的模型。
     */
    public function restore(): bool
    {
        if (!static::$softDelete || empty($this->attributes['deleted_at'])) {
            return false;
        }
        $pk = static::$primaryKey;
        $result = static::query()
            ->where($pk, '=', $this->attributes[$pk])
            ->update(['deleted_at' => null]) > 0;
        if ($result) {
            $this->attributes['deleted_at'] = null;
        }
        return $result;
    }

    /**
     * 强制删除（物理删除）。
     */
    public function forceDelete(): bool
    {
        $pk = static::$primaryKey;
        if (empty($this->attributes[$pk])) {
            return false;
        }
        return static::query()
            ->where($pk, '=', $this->attributes[$pk])
            ->delete() > 0;
    }

    /**
     * 查询时排除软删除的记录（默认行为）。
     */
    protected static function applySoftDeleteScope(QueryBuilder $qb): void
    {
        if (static::$softDelete) {
            $qb->whereNull('deleted_at');
        }
    }

    /**
     * 包含已软删除的记录。
     */
    public static function withTrashed(): QueryBuilder
    {
        $qb = static::query();
        // 不应用软删除作用域
        return $qb;
    }

    /**
     * 仅查询已软删除的记录。
     */
    public static function onlyTrashed(): QueryBuilder
    {
        $qb = app(QueryBuilder::class)->table(static::$table);
        if (static::$softDelete) {
            $qb->whereNotNull('deleted_at');
        }
        return $qb;
    }

    /* ─────── 模型事件 ─────── */

    /** @var array<string, array<int, callable>> 注册的事件监听器 */
    private static array $globalListeners = [];

    /**
     * 注册模型事件监听器。
     *
     * @param string $event  saving|saved|creating|created|updating|updated|deleting|deleted
     */
    public static function on(string $event, callable $callback): void
    {
        self::$globalListeners[static::class . '.' . $event][] = $callback;
    }

    /**
     * 触发模型事件。返回 false 表示中止操作。
     */
    protected static function fireModelEvent(string $event, self $model, bool $halt = true): bool
    {
        $key = static::class . '.' . $event;
        $listeners = self::$globalListeners[$key] ?? [];

        foreach ($listeners as $callback) {
            $result = $callback($model);
            if ($halt && $result === false) {
                return false;
            }
        }
        return true;
    }

    /* ─────── Active Record 静态方法 ─────── */

    public static function query(): QueryBuilder
    {
        $qb = app(QueryBuilder::class)->table(static::$table);
        // 应用软删除作用域
        if (static::$softDelete) {
            $qb->whereNull('deleted_at');
        }
        // 应用全局作用域
        static::applyGlobalScopes($qb);
        return $qb;
    }

    /**
     * 预加载关联 - 解决 N+1 查询问题。
     *
     * 用法：
     *   Post::with('author', 'category')->get();  // 一次查询 + 2 次关联查询
     *   Post::with(['comments' => fn($q) => $q->where('status', 'approved')])->get();
     */
    public static function withEager(string|array ...$relations): QueryBuilder
    {
        $qb = static::query();
        // 将预加载关系暂存到静态属性，get() 后处理
        $eagerLoad = [];
        foreach ($relations as $rel) {
            if (is_string($rel)) {
                $eagerLoad[$rel] = null;
            } elseif (is_array($rel)) {
                foreach ($rel as $k => $v) {
                    $eagerLoad[$k] = is_callable($v) ? $v : null;
                }
            }
        }
        // 通过静态属性传递（简化实现）
        static::$pendingEagerLoad = array_merge(static::$pendingEagerLoad, $eagerLoad);
        return $qb;
    }

    /** @var array<string, callable|null> 待处理的预加载 */
    protected static array $pendingEagerLoad = [];

    /**
     * 处理预加载（在 get()/all() 后调用）。
     *
     * @param static[] $models
     */
    protected static function processEagerLoad(array $models): void
    {
        if (empty($models) || empty(static::$pendingEagerLoad)) {
            return;
        }

        foreach (static::$pendingEagerLoad as $relationName => $callback) {
            // 取第一个模型实例，获取关联对象
            $firstModel = $models[0];
            if (!method_exists($firstModel, $relationName)) {
                continue;
            }
            $relation = $firstModel->$relationName();
            if (!($relation instanceof \Core\Database\Relations\Relation)) {
                continue;
            }
            // 应用回调约束
            if ($callback !== null) {
                $callback($relation->getQuery());
            }
            // 批量加载
            $relation->eagerLoad($models, $relationName);
        }

        // 清空待处理列表
        static::$pendingEagerLoad = [];
    }

    /**
     * 应用全局作用域（子类可覆写）。
     */
    protected static function applyGlobalScopes(QueryBuilder $qb): void
    {
        // 默认无全局作用域，子类可覆写
    }

    public static function find(int|string $id): ?static
    {
        $row = static::query()->where(static::$primaryKey, '=', $id)->first();
        return $row ? new static($row) : null;
    }

    public static function findBy(string $column, mixed $value): ?static
    {
        $row = static::query()->where($column, '=', $value)->first();
        return $row ? new static($row) : null;
    }

    /**
     * @return static[]
     */
    public static function all(): array
    {
        $models = array_map(fn($r) => new static($r), static::query()->get());
        static::processEagerLoad($models);
        return $models;
    }

    public static function create(array $values): int|string
    {
        // 触发 creating 事件
        $model = new static($values);
        if (!static::fireModelEvent('creating', $model)) {
            return false;
        }
        $id = static::query()->insert($values);
        if ($id) {
            $model->setAttribute(static::$primaryKey, $id);
            static::fireModelEvent('created', $model, false);
        }
        return $id;
    }

    /**
     * Save the current model state.
     */
    public function save(): bool
    {
        $pk = static::$primaryKey;

        // 触发 saving 事件
        if (!static::fireModelEvent('saving', $this)) {
            return false;
        }

        if (empty($this->attributes[$pk])) {
            // Insert
            if (!static::fireModelEvent('creating', $this)) {
                return false;
            }
            $id = static::query()->insert($this->attributes);
            $this->attributes[$pk] = $id;
            $success = $id !== null && $id !== false;
            if ($success) {
                static::fireModelEvent('created', $this, false);
            }
        } else {
            // Update
            if (!static::fireModelEvent('updating', $this)) {
                return false;
            }
            $affected = static::query()
                ->where($pk, '=', $this->attributes[$pk])
                ->update($this->attributes);
            $success = $affected > 0;
            if ($success) {
                static::fireModelEvent('updated', $this, false);
            }
        }

        if ($success) {
            static::fireModelEvent('saved', $this, false);
        }
        return $success;
    }

    public function setAttribute(string $key, mixed $value): static
    {
        $this->attributes[$key] = $value;
        return $this;
    }

    /**
     * 获取主键字段名。
     */
    public static function primaryKeyName(): string
    {
        return static::$primaryKey;
    }

    /**
     * 获取表名（实例方法）。
     */
    public function getTable(): string
    {
        return static::$table;
    }
}
