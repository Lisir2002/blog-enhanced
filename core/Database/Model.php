<?php

namespace Core\Database;

/**
 * 基础 Model 类，提供 Active Record 风格操作。
 */
abstract class Model
{
    protected static string $table;
    protected static string $primaryKey = 'id';
    protected array $attributes = [];
    protected array $casts = [];

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
        return $this->attributes[$name] ?? null;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    public function toArray(): array
    {
        return $this->attributes;
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

    /* ─────── Active Record 静态方法 ─────── */

    public static function query(): QueryBuilder
    {
        return app(QueryBuilder::class)->table(static::$table);
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
        return array_map(fn($r) => new static($r), static::query()->get());
    }

    public static function create(array $values): int|string
    {
        return static::query()->insert($values);
    }

    /**
     * Save the current model state.
     */
    public function save(): bool
    {
        $pk = static::$primaryKey;
        if (empty($this->attributes[$pk])) {
            // Insert
            $id = static::query()->insert($this->attributes);
            $this->attributes[$pk] = $id;
            return $id !== null && $id !== false;
        }
        // Update
        $affected = static::query()
            ->where($pk, '=', $this->attributes[$pk])
            ->update($this->attributes);
        return $affected > 0;
    }

    public function delete(): bool
    {
        $pk = static::$primaryKey;
        if (empty($this->attributes[$pk])) {
            return false;
        }
        return static::query()
            ->where($pk, '=', $this->attributes[$pk])
            ->delete() > 0;
    }

    public function setAttribute(string $key, mixed $value): static
    {
        $this->attributes[$key] = $value;
        return $this;
    }
}
