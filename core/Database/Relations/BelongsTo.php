<?php

namespace Core\Database\Relations;

use Core\Database\Model;
use Core\Database\QueryBuilder;

/**
 * 多对一关联（BelongsTo）。
 *
 * 例：Post belongsTo User（文章属于一个作者）
 *   foreign_key: posts.author_id
 *   owner_key:   users.id
 */
class BelongsTo extends Relation
{
    private string $foreignKey;
    private string $ownerKey;

    public function __construct(string $related, Model $parent, string $foreignKey, string $ownerKey)
    {
        $this->foreignKey = $foreignKey;
        $this->ownerKey = $ownerKey;
        parent::__construct($related, $parent);
    }

    protected function buildQuery(): QueryBuilder
    {
        $ownerValue = $this->parent->getAttribute($this->foreignKey);
        return app(QueryBuilder::class)
            ->table((new ($this->related))::table())
            ->where($this->ownerKey, '=', $ownerValue);
    }

    public function getResults(): ?Model
    {
        $row = $this->query->first();
        return $row ? $this->makeModel($row) : null;
    }

    public function eagerLoad(array $models, string $relationName): void
    {
        $ownerValues = array_unique(array_filter(array_map(
            fn($m) => $m->getAttribute($this->foreignKey),
            $models,
        )));

        if (empty($ownerValues)) {
            foreach ($models as $model) {
                $model->setRelation($relationName, null);
            }
            return;
        }

        $rows = app(QueryBuilder::class)
            ->table((new ($this->related))::table())
            ->whereIn($this->ownerKey, $ownerValues)
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[$row[$this->ownerKey]] = $this->makeModel($row);
        }

        foreach ($models as $model) {
            $ownerValue = $model->getAttribute($this->foreignKey);
            $model->setRelation($relationName, $map[$ownerValue] ?? null);
        }
    }
}
