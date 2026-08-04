<?php

namespace Core\Database\Relations;

use Core\Database\Model;
use Core\Database\QueryBuilder;

/**
 * 一对多关联（HasMany）。
 *
 * 例：User hasMany Post（用户有多篇文章）
 *   foreign_key: posts.author_id
 *   local_key:   users.id
 */
class HasMany extends Relation
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

    public function getResults(): array
    {
        $rows = $this->query->get();
        return array_map(fn($row) => $this->makeModel($row), $rows);
    }

    public function eagerLoad(array $models, string $relationName): void
    {
        $localValues = array_unique(array_filter(array_map(
            fn($m) => $m->getAttribute($this->localKey),
            $models,
        )));

        if (empty($localValues)) {
            foreach ($models as $model) {
                $model->setRelation($relationName, []);
            }
            return;
        }

        $rows = app(QueryBuilder::class)
            ->table((new ($this->related))::table())
            ->whereIn($this->foreignKey, $localValues)
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $fk = $row[$this->foreignKey];
            if (!isset($map[$fk])) {
                $map[$fk] = [];
            }
            $map[$fk][] = $this->makeModel($row);
        }

        foreach ($models as $model) {
            $localValue = $model->getAttribute($this->localKey);
            $model->setRelation($relationName, $map[$localValue] ?? []);
        }
    }
}
