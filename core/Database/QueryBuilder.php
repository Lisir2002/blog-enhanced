<?php

namespace Core\Database;

/**
 * 流式查询构造器 - 不可变（每次链式调用都返回 clone）。
 *
 * 用法：
 *   $rows = $qb->table('posts')
 *       ->where('status', 'published')
 *       ->orderBy('published_at', 'DESC')
 *       ->limit(10)
 *       ->get();
 *
 *   $id = $qb->table('posts')->insert([
 *       'title' => 'Hello', 'content' => '...',
 *       'created_at' => date('Y-m-d H:i:s'),
 *   ]);
 *
 * 注意：链式方法（where/orderBy/limit 等）均返回新实例，不修改原对象。
 *       可以安全地复用 $qb 基础查询分段构造不同条件。
 */
class QueryBuilder
{
    private Connection $conn;
    private string $table = '';
    private array $wheres = [];
    private array $orders = [];
    private ?int $limit = null;
    private ?int $offset = null;
    private array $select = ['*'];
    private array $joins = [];
    private array $bindings = [];
    private ?string $groupBy = null;
    private array $having = [];
    private int $placeholderCounter = 0;

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    /**
     * 起始一个新表查询 - 返回新的已重置实例。
     */
    public function table(string $table): static
    {
        $new = clone $this;
        $new->table = $this->conn->tablePrefix() . $table;
        $new->wheres = [];
        $new->orders = [];
        $new->limit = null;
        $new->offset = null;
        $new->select = ['*'];
        $new->joins = [];
        $new->bindings = [];
        $new->groupBy = null;
        $new->having = [];
        $new->placeholderCounter = 0;
        return $new;
    }

    public function select(string ...$columns): static
    {
        $new = clone $this;
        $new->select = $columns;
        return $new;
    }

    public function where(string $column, mixed $op, mixed $value = null): static
    {
        if ($value === null) {
            $value = $op;
            $op = '=';
        }
        $new = clone $this;
        $placeholder = $new->nextPlaceholder();
        $new->wheres[] = ['AND', "$column $op $placeholder"];
        $new->bindings[$placeholder] = $value;
        return $new;
    }

    public function whereIn(string $column, array $values): static
    {
        $new = clone $this;
        if (empty($values)) {
            $new->wheres[] = ['AND', '0'];
            return $new;
        }
        $placeholders = [];
        foreach ($values as $v) {
            $placeholder = $new->nextPlaceholder();
            $placeholders[] = $placeholder;
            $new->bindings[$placeholder] = $v;
        }
        $new->wheres[] = ['AND', "$column IN (" . implode(', ', $placeholders) . ")"];
        return $new;
    }

    public function whereNotIn(string $column, array $values): static
    {
        $new = clone $this;
        if (empty($values)) {
            return $new;
        }
        $placeholders = [];
        foreach ($values as $v) {
            $placeholder = $new->nextPlaceholder();
            $placeholders[] = $placeholder;
            $new->bindings[$placeholder] = $v;
        }
        $new->wheres[] = ['AND', "$column NOT IN (" . implode(', ', $placeholders) . ")"];
        return $new;
    }

    public function whereNull(string $column): static
    {
        $new = clone $this;
        $new->wheres[] = ['AND', "$column IS NULL"];
        return $new;
    }

    public function whereNotNull(string $column): static
    {
        $new = clone $this;
        $new->wheres[] = ['AND', "$column IS NOT NULL"];
        return $new;
    }

    public function whereLike(string $column, string $pattern): static
    {
        $new = clone $this;
        $placeholder = $new->nextPlaceholder();
        $new->wheres[] = ['AND', "$column LIKE $placeholder"];
        $new->bindings[$placeholder] = $pattern;
        return $new;
    }

    public function orWhere(string $column, mixed $op, mixed $value = null): static
    {
        if ($value === null) {
            $value = $op;
            $op = '=';
        }
        $new = clone $this;
        $placeholder = $new->nextPlaceholder();
        $new->wheres[] = ['OR', "$column $op $placeholder"];
        $new->bindings[$placeholder] = $value;
        return $new;
    }

    public function join(string $table, string $on, string $type = 'INNER'): static
    {
        $new = clone $this;
        $prefixedTable = $this->conn->tablePrefix() . $table;
        $new->joins[] = "$type JOIN $prefixedTable ON $on";
        return $new;
    }

    public function leftJoin(string $table, string $on): static
    {
        return $this->join($table, $on, 'LEFT');
    }

    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $new = clone $this;
        $new->orders[] = "$column $direction";
        return $new;
    }

    public function limit(int $limit): static
    {
        $new = clone $this;
        $new->limit = $limit;
        return $new;
    }

    public function offset(int $offset): static
    {
        $new = clone $this;
        $new->offset = $offset;
        return $new;
    }

    public function groupBy(string $col): static
    {
        $new = clone $this;
        $new->groupBy = $col;
        return $new;
    }

    public function having(string $cond): static
    {
        $new = clone $this;
        $new->having[] = $cond;
        return $new;
    }

    /**
     * 取一组结果。
     */
    public function get(): array
    {
        $sql = $this->buildSelect();
        $stmt = $this->conn->pdo()->prepare($sql);
        $stmt->execute($this->bindings);
        return $stmt->fetchAll();
    }

    /**
     * 取第一行（自动 limit 1，不修改原对象）。
     */
    public function first(): ?array
    {
        $new = clone $this;
        $new->limit = 1;
        $rows = $new->get();
        return $rows[0] ?? null;
    }

    public function count(): int
    {
        $new = clone $this;
        $new->select = ['COUNT(*) AS aggregate'];
        $new->orders = [];
        $new->limit = null;
        $new->offset = null;
        $row = $new->first();
        return (int) ($row['aggregate'] ?? 0);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * 插入新行，返回 lastInsertId。
     */
    public function insert(array $values): int|string
    {
        $cols = array_keys($values);
        $placeholders = [];
        $bindings = [];
        foreach ($values as $col => $value) {
            $placeholder = ':' . $col;
            $placeholders[] = $placeholder;
            $bindings[$placeholder] = $value;
        }
        $sql = "INSERT INTO {$this->table} (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->conn->pdo()->prepare($sql);
        $stmt->execute($bindings);
        return $this->conn->pdo()->lastInsertId();
    }

    /**
     * 更新匹配的行，返回受影响行数。
     */
    public function update(array $values): int
    {
        $setClauses = [];
        $bindings = [];
        foreach ($values as $col => $value) {
            $placeholder = ':' . $col;
            $setClauses[] = "$col = $placeholder";
            $bindings[$placeholder] = $value;
        }
        $sql = "UPDATE {$this->table} SET " . implode(', ', $setClauses);
        if (!empty($this->wheres)) {
            $sql .= " WHERE " . $this->buildWhereClause();
        }
        $bindings = array_merge($bindings, $this->bindings);
        $stmt = $this->conn->pdo()->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->rowCount();
    }

    /**
     * 删除匹配的行，返回受影响行数。
     */
    public function delete(): int
    {
        $sql = "DELETE FROM {$this->table}";
        if (!empty($this->wheres)) {
            $sql .= " WHERE " . $this->buildWhereClause();
        }
        $stmt = $this->conn->pdo()->prepare($sql);
        $stmt->execute($this->bindings);
        return $stmt->rowCount();
    }

    // ----------------------------------------------------------------
    // 事务支持
    // ----------------------------------------------------------------

    /**
     * 在事务中执行回调，自动提交 / 回滚。
     */
    public function transaction(callable $callback): mixed
    {
        $pdo = $this->conn->pdo();
        $wasInTransaction = $pdo->inTransaction();
        if (!$wasInTransaction) {
            $pdo->beginTransaction();
        }
        try {
            $result = $callback($this);
            if (!$wasInTransaction) {
                $pdo->commit();
            }
            return $result;
        } catch (\Throwable $e) {
            if (!$wasInTransaction) {
                $pdo->rollBack();
            }
            \Core\Log\Log::error('Transaction rolled back', [
                'msg' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function beginTransaction(): bool
    {
        return $this->conn->pdo()->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->conn->pdo()->commit();
    }

    public function rollBack(): bool
    {
        return $this->conn->pdo()->rollBack();
    }

    // ----------------------------------------------------------------
    // 内部构建方法
    // ----------------------------------------------------------------

    private function buildSelect(): string
    {
        $sql = "SELECT " . implode(', ', $this->select) . " FROM {$this->table}";
        if (!empty($this->joins)) {
            $sql .= ' ' . implode(' ', $this->joins);
        }
        if (!empty($this->wheres)) {
            $sql .= " WHERE " . $this->buildWhereClause();
        }
        if ($this->groupBy !== null) {
            $sql .= " GROUP BY {$this->groupBy}";
        }
        if (!empty($this->having)) {
            $sql .= " HAVING " . implode(' AND ', $this->having);
        }
        if (!empty($this->orders)) {
            $sql .= " ORDER BY " . implode(', ', $this->orders);
        }
        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }
        if ($this->offset !== null) {
            $sql .= " OFFSET {$this->offset}";
        }
        return $sql;
    }

    /**
     * Build WHERE clause from the wheres stack.
     * Each entry is [conjunction, condition]. First entry's conjunction is ignored.
     */
    private function buildWhereClause(): string
    {
        $parts = [];
        foreach ($this->wheres as $i => $entry) {
            if (is_array($entry)) {
                [$conj, $cond] = $entry;
                if ($i === 0) {
                    $parts[] = $cond;
                } else {
                    $parts[] = "$conj $cond";
                }
            } else {
                // Legacy string format (shouldn't happen but handle gracefully)
                $parts[] = $entry;
            }
        }
        return implode(' ', $parts);
    }

    private function nextPlaceholder(): string
    {
        return ':w' . (++$this->placeholderCounter);
    }
}
