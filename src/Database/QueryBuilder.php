<?php

declare(strict_types=1);

namespace Puwit\Database;

class QueryBuilder
{
    private const ALLOWED_OPS = ['=', '!=', '<>', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE'];

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
        $op = strtoupper($op);
        if (!in_array($op, self::ALLOWED_OPS, true)) {
            throw new \InvalidArgumentException("Invalid operator: {$op}");
        }
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

        $qt  = $this->qi($this->table);
        $sql = "SELECT * FROM {$qt}{$whereSql}";
        $sql .= $this->buildOrder();
        $sql .= $this->buildLimit();

        return $this->conn->select($sql, $whereBindings);
    }

    public function count(): int
    {
        [$whereSql, $whereBindings] = $this->buildWhere(1);

        $qt  = $this->qi($this->table);
        $sql = "SELECT COUNT(*) as cnt FROM {$qt}{$whereSql}";
        $row = $this->conn->selectOne($sql, $whereBindings);
        return (int)($row['cnt'] ?? 0);
    }

    public function find(int $id): ?array
    {
        return $this->where('id', $id)->limit(1)->get()[0] ?? null;
    }

    public function insert(array $data): string|false
    {
        $placeholders = [];
        $values       = [];
        $idx          = 1;

        foreach ($data as $value) {
            $placeholders[] = $this->conn->dialect()->placeholder($idx++);
            $values[]       = $value;
        }

        $cols = implode(', ', array_map(fn($c) => $this->qi($c), array_keys($data)));
        $phs  = implode(', ', $placeholders);
        $qt   = $this->qi($this->table);

        return $this->conn->insert(
            "INSERT INTO {$qt} ({$cols}) VALUES ({$phs})",
            $values
        );
    }

    public function update(array $data): int
    {
        if (empty($this->wheres)) {
            throw new \LogicException('update() requires at least one where() condition');
        }

        $sets    = [];
        $values  = [];
        $idx     = 1;

        foreach ($data as $col => $value) {
            $sets[]   = $this->qi($col) . ' = ' . $this->conn->dialect()->placeholder($idx++);
            $values[] = $value;
        }

        [$whereSql, $whereBindings] = $this->buildWhere($idx);

        $qt  = $this->qi($this->table);
        $sql = "UPDATE {$qt} SET " . implode(', ', $sets) . $whereSql;

        return $this->conn->affectingStatement($sql, array_merge($values, $whereBindings));
    }

    public function delete(): int
    {
        if (empty($this->wheres)) {
            throw new \LogicException('delete() requires at least one where() condition');
        }

        [$whereSql, $whereBindings] = $this->buildWhere(1);

        $qt  = $this->qi($this->table);
        $sql = "DELETE FROM {$qt}{$whereSql}";

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
            $parts[]    = $this->qi($col) . " {$op} " . $this->conn->dialect()->placeholder($idx++);
            $bindings[] = $val;
        }

        return [' WHERE ' . implode(' AND ', $parts), $bindings, $idx];
    }

    private function buildOrder(): string
    {
        if (empty($this->orders)) {
            return '';
        }
        $parts = array_map(fn($o) => $this->qi($o[0]) . " {$o[1]}", $this->orders);
        return ' ORDER BY ' . implode(', ', $parts);
    }

    private function qi(string $name): string
    {
        return $this->conn->dialect()->quoteIdentifier($name);
    }

    private function buildLimit(): string
    {
        if ($this->limit === null) {
            return '';
        }
        return ' ' . $this->conn->dialect()->limitOffset($this->limit, $this->offset ?? 0);
    }
}
