<?php

namespace Core\Database;

/**
 * 流式查询构造器。
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

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    public function table(string $table): static
    {
        $new = clone $this;
        $new->reset();
        $new->table = $this->conn->tablePrefix() . $table;
        return $new;
    }

    private function reset(): void
    {
        $this->table = '';
        $this->wheres = [];
        $this->orders = [];
        $this->limit = null;
        $this->offset = null;
        $this->select = ['*'];
        $this->joins = [];
        $this->bindings = [];
        $this->groupBy = null;
        $this->having = [];
    }

    public function select(string ...$columns): static
    {
        $this->select = $columns;
        return $this;
    }

    public function where(string $column, mixed $op, mixed $value = null): static
    {
        if ($value === null) {
            // 2-arg form: where('col', 'value') → where col = value
            $value = $op;
            $op = '=';
        }
        $placeholder = $this->nextPlaceholder();
        $this->wheres[] = ['AND', "$column $op $placeholder"];
        $this->bindings[$placeholder] = $value;
        return $this;
    }

    public function whereIn(string $column, array $values): static
    {
        if (empty($values)) {
            $this->wheres[] = ['AND', '0'];
            return $this;
        }
        $placeholders = [];
        foreach ($values as $v) {
            $placeholder = $this->nextPlaceholder();
            $placeholders[] = $placeholder;
            $this->bindings[$placeholder] = $v;
        }
        $this->wheres[] = ['AND', "$column IN (" . implode(', ', $placeholders) . ")"];
        return $this;
    }

    public function whereNull(string $column): static
    {
        $this->wheres[] = ['AND', "$column IS NULL"];
        return $this;
    }

    public function whereNotNull(string $column): static
    {
        $this->wheres[] = ['AND', "$column IS NOT NULL"];
        return $this;
    }

    public function whereLike(string $column, string $pattern): static
    {
        $placeholder = $this->nextPlaceholder();
        $this->wheres[] = ['AND', "$column LIKE $placeholder"];
        $this->bindings[$placeholder] = $pattern;
        return $this;
    }

    public function orWhere(string $column, mixed $op, mixed $value = null): static
    {
        if ($value === null) {
            $value = $op;
            $op = '=';
        }
        $placeholder = $this->nextPlaceholder();
        $this->wheres[] = ['OR', "$column $op $placeholder"];
        $this->bindings[$placeholder] = $value;
        return $this;
    }

    public function join(string $table, string $on, string $type = 'INNER'): static
    {
        $prefixedTable = $this->conn->tablePrefix() . $table;
        $this->joins[] = "$type JOIN $prefixedTable ON $on";
        return $this;
    }

    public function leftJoin(string $table, string $on): static
    {
        return $this->join($table, $on, 'LEFT');
    }

    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $this->orders[] = "$column $direction";
        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): static
    {
        $this->offset = $offset;
        return $this;
    }

    public function groupBy(string $col): static
    {
        $this->groupBy = $col;
        return $this;
    }

    public function having(string $cond): static
    {
        $this->having[] = $cond;
        return $this;
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

    public function first(): ?array
    {
        $this->limit(1);
        $rows = $this->get();
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
     * 插入新行。
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
        $pdo = $this->conn->pdo();
        return $pdo->lastInsertId();
    }

    /**
     * 更新匹配的行。
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

    private int $placeholderCounter = 0;

    private function nextPlaceholder(): string
    {
        return ':w' . (++$this->placeholderCounter);
    }
}
