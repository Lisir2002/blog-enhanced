<?php

namespace Core\Database\Relations;

use Core\Database\Model;
use Core\Database\QueryBuilder;

/**
 * 多对多关联（BelongsToMany）。
 *
 * 例：Post belongsToMany Tag（文章有多个标签）
 *   pivot_table: post_tag
 *   foreign_key: post_id（本表在 pivot 的外键）
 *   related_key: tag_id（关联表在 pivot 的外键）
 */
class BelongsToMany extends Relation
{
    private string $pivotTable;
    private string $foreignKey;
    private string $relatedKey;
    private string $parentKey;

    public function __construct(
        string $related,
        Model $parent,
        string $pivotTable,
        string $foreignKey,
        string $relatedKey,
        string $parentKey = 'id'
    ) {
        $this->pivotTable = $pivotTable;
        $this->foreignKey = $foreignKey;
        $this->relatedKey = $relatedKey;
        $this->parentKey = $parentKey;
        parent::__construct($related, $parent);
    }

    protected function buildQuery(): QueryBuilder
    {
        $parentValue = $this->parent->getAttribute($this->parentKey);
        $relatedTable = (new ($this->related))::table();

        return app(QueryBuilder::class)
            ->table($this->pivotTable)
            ->select($relatedTable . '.*')
            ->join($relatedTable, $relatedTable . '.id = ' . $this->pivotTable . '.' . $this->relatedKey)
            ->where($this->pivotTable . '.' . $this->foreignKey, '=', $parentValue);
    }

    public function getResults(): array
    {
        $rows = $this->query->get();
        return array_map(fn($row) => $this->makeModel($row), $rows);
    }

    public function eagerLoad(array $models, string $relationName): void
    {
        $parentValues = array_unique(array_filter(array_map(
            fn($m) => $m->getAttribute($this->parentKey),
            $models,
        )));

        if (empty($parentValues)) {
            foreach ($models as $model) {
                $model->setRelation($relationName, []);
            }
            return;
        }

        $relatedTable = (new ($this->related))::table();
        $rows = app(QueryBuilder::class)
            ->table($this->pivotTable)
            ->select($relatedTable . '.*', $this->pivotTable . '.' . $this->foreignKey . ' as __pivot_key')
            ->join($relatedTable, $relatedTable . '.id = ' . $this->pivotTable . '.' . $this->relatedKey)
            ->whereIn($this->pivotTable . '.' . $this->foreignKey, $parentValues)
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $pivotKey = $row['__pivot_key'] ?? null;
            unset($row['__pivot_key']);
            if ($pivotKey !== null) {
                if (!isset($map[$pivotKey])) {
                    $map[$pivotKey] = [];
                }
                $map[$pivotKey][] = $this->makeModel($row);
            }
        }

        foreach ($models as $model) {
            $parentValue = $model->getAttribute($this->parentKey);
            $model->setRelation($relationName, $map[$parentValue] ?? []);
        }
    }
}
