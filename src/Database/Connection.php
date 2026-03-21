<?php

declare(strict_types=1);

namespace Puwit\Database;

use Puwit\Database\Dialects\DialectInterface;

class Connection
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly DialectInterface $dialect,
    ) {}

    public function dialect(): DialectInterface
    {
        return $this->dialect;
    }

    public function pdo(): \PDO
    {
        return $this->pdo;
    }

    public function query(string $sql, array $bindings = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);
        return $stmt;
    }

    public function select(string $sql, array $bindings = []): array
    {
        return $this->query($sql, $bindings)->fetchAll();
    }

    public function selectOne(string $sql, array $bindings = []): ?array
    {
        $row = $this->query($sql, $bindings)->fetch();
        return $row === false ? null : $row;
    }

    public function insert(string $sql, array $bindings = []): string|false
    {
        $this->query($sql, $bindings);
        return $this->pdo->lastInsertId();
    }

    public function statement(string $sql, array $bindings = []): void
    {
        $this->query($sql, $bindings);
    }

    public function affectingStatement(string $sql, array $bindings = []): int
    {
        return $this->query($sql, $bindings)->rowCount();
    }

    public function transaction(callable $callback): mixed
    {
        if ($this->pdo->inTransaction()) {
            return $callback($this);
        }

        $this->pdo->beginTransaction();
        try {
            $result = $callback($this);
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function tableExists(string $table): bool
    {
        return $this->dialect->tableExists($this, $table);
    }
}
