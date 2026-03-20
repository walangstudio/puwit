<?php

declare(strict_types=1);

namespace Puwit\Database;

class QueryBuilder
{
    private string $table    = '';
    private array  $wheres   = [];   // [['col', 'op', 'val'], ...]
    private array  $orders   = [];
    private ?int   $limit    = null;
    private ?int   $offset   = null;

    public function __construct(private readonly Connection $conn) {}

    public function table(string $table): self
    {
        $clone        = clone $this;
        $clone->table = $table;
        return $clone;
    }

    public function where(string $column, mixed $value, string $op = '='): self
    {
        $clone           = clone $this;
        $clone->wheres[] = [$column, $op, $value];
        return $clone;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $clone           = clone $this;
        $dir             = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $clone->orders[] = [$column, $dir];
        return $clone;
    }

    public function limit(int $limit): self
    {
        $clone        = clone $this;
        $clone->limit = $limit;
        return $clone;
    }

    public function offset(int $offset): self
    {
        $clone         = clone $this;
        $clone->offset = $offset;
        return $clone;
    }

    public function get(): array
    {
        [$whereSql, $whereBindings, $nextIdx] = $this->buildWhere(1);

        $sql = "SELECT * FROM {$this->table}{$whereSql}";
        $sql .= $this->buildOrder();
        $sql .= $this->buildLimit();

        return $this->conn->select($sql, $whereBindings);
    }

    public function count(): int
    {
        [$whereSql, $whereBindings] = $this->buildWhere(1);

        $sql = "SELECT COUNT(*) as cnt FROM {$this->table}{$whereSql}";
        $row = $this->conn->selectOne($sql, $whereBindings);
        return (int)($row['cnt'] ?? 0);
    }

    public function find(int $id): ?array
    {
        return $this->where('id', $id)->limit(1)->get()[0] ?? null;
    }

    public function insert(array $data): string|false
    {
        $columns      = array_keys($data);
        $placeholders = [];
        $values       = [];
        $idx          = 1;

        foreach ($data as $value) {
            $placeholders[] = $this->conn->dialect()->placeholder($idx++);
            $values[]       = $value;
        }

        $cols = implode(', ', $columns);
        $phs  = implode(', ', $placeholders);

        return $this->conn->insert(
            "INSERT INTO {$this->table} ({$cols}) VALUES ({$phs})",
            $values
        );
    }

    public function update(array $data): int
    {
        $sets    = [];
        $values  = [];
        $idx     = 1;

        foreach ($data as $col => $value) {
            $sets[]   = "{$col} = " . $this->conn->dialect()->placeholder($idx++);
            $values[] = $value;
        }

        [$whereSql, $whereBindings] = $this->buildWhere($idx);

        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets) . $whereSql;

        return $this->conn->affectingStatement($sql, array_merge($values, $whereBindings));
    }

    public function delete(): int
    {
        [$whereSql, $whereBindings] = $this->buildWhere(1);

        $sql = "DELETE FROM {$this->table}{$whereSql}";

        return $this->conn->affectingStatement($sql, $whereBindings);
    }

    private function buildWhere(int $startIdx): array
    {
        if (empty($this->wheres)) {
            return ['', [], $startIdx];
        }

        $parts    = [];
        $bindings = [];
        $idx      = $startIdx;

        foreach ($this->wheres as [$col, $op, $val]) {
            $parts[]    = "{$col} {$op} " . $this->conn->dialect()->placeholder($idx++);
            $bindings[] = $val;
        }

        return [' WHERE ' . implode(' AND ', $parts), $bindings, $idx];
    }

    private function buildOrder(): string
    {
        if (empty($this->orders)) {
            return '';
        }
        $parts = array_map(fn($o) => "{$o[0]} {$o[1]}", $this->orders);
        return ' ORDER BY ' . implode(', ', $parts);
    }

    private function buildLimit(): string
    {
        if ($this->limit === null) {
            return '';
        }
        return ' ' . $this->conn->dialect()->limitOffset($this->limit, $this->offset ?? 0);
    }
}
