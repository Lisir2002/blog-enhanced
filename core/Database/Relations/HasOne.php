<?php

namespace Core\Database\Relations;

use Core\Database\Model;
use Core\Database\QueryBuilder;

/**
 * 一对一关联（HasOne）。
 *
 * 例：User hasOne Profile（用户有一个资料）
 *   foreign_key: profiles.user_id
 *   local_key:   users.id
 */
class HasOne extends Relation
{
    private string $foreignKey;
    private string $localKey;

    public function __construct(string $related, Model $parent, string $foreignKey, string $localKey)
    {
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;
        parent::__construct($related, $parent);
    }

    protected function buildQuery(): QueryBuilder
    {
        $localValue = $this->parent->getAttribute($this->localKey);
        return app(QueryBuilder::class)
            ->table((new ($this->related))::table())
            ->where($this->foreignKey, '=', $localValue);
    }

    public function getResults(): ?Model
    {
        $row = $this->query->first();
        return $row ? $this->makeModel($row) : null;
    }

    public function eagerLoad(array $models, string $relationName): void
    {
        $localValues = array_unique(array_filter(array_map(
            fn($m) => $m->getAttribute($this->localKey),
            $models,
        )));

        if (empty($localValues)) {
            foreach ($models as $model) {
                $model->setRelation($relationName, null);
            }
            return;
        }

        $rows = app(QueryBuilder::class)
            ->table((new ($this->related))::table())
            ->whereIn($this->foreignKey, $localValues)
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[$row[$this->foreignKey]] = $this->makeModel($row);
        }

        foreach ($models as $model) {
            $localValue = $model->getAttribute($this->localKey);
            $model->setRelation($relationName, $map[$localValue] ?? null);
        }
    }
}
