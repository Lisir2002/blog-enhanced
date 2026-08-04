<?php

namespace Core\Database\Relations;

use Core\Database\Model;
use Core\Database\QueryBuilder;

/**
 * 关联关系基类。
 */
abstract class Relation
{
    protected string $related;
    protected Model $parent;
    protected QueryBuilder $query;

    public function __construct(string $related, Model $parent)
    {
        $this->related = $related;
        $this->parent = $parent;
        $this->query = $this->buildQuery();
    }

    /**
     * 子类实现：构建查询。
     */
    abstract protected function buildQuery(): QueryBuilder;

    /**
     * 获取查询构造器（支持链式追加约束）。
     */
    public function getQuery(): QueryBuilder
    {
        return $this->query;
    }

    /**
     * 执行查询，返回结果。
     */
    abstract public function getResults(): mixed;

    /**
     * 预加载：批量查询多个父模型的关联。
     *
     * @param Model[] $models
     * @param string $relationName
     */
    abstract public function eagerLoad(array $models, string $relationName): void;

    /**
     * 获取关联模型类。
     */
    public function getRelatedClass(): string
    {
        return $this->related;
    }

    /**
     * 创建关联模型实例。
     */
    protected function makeModel(array $attributes): Model
    {
        return new ($this->related)($attributes);
    }
}
