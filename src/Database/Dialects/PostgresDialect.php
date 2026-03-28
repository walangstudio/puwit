<?php

declare(strict_types=1);

namespace Puwit\Database\Dialects;

use Puwit\Database\Connection;

class PostgresDialect implements DialectInterface
{
    private const TYPE_MAP = [
        'string'   => 'VARCHAR(255)',
        'int'      => 'INTEGER',
        'float'    => 'NUMERIC(10,4)',
        'boolean'  => 'BOOLEAN',
        'text'     => 'TEXT',
        'datetime' => 'TIMESTAMP',
        'json'     => 'JSONB',
        'relation' => 'INTEGER',
    ];

    public function columnType(string $type): string
    {
        return self::TYPE_MAP[$type] ?? 'VARCHAR(255)';
    }

    public function tableExists(Connection $conn, string $table): bool
    {
        $row = $conn->selectOne(
            "SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename = ?",
            [$table]
        );
        return $row !== null;
    }

    public function autoIncrementPrimaryKey(): string
    {
        return 'SERIAL PRIMARY KEY';
    }

    public function timestampDefault(): string
    {
        return 'TIMESTAMP NOT NULL DEFAULT NOW()';
    }

    public function foreignKey(string $column, string $refTable, string $refColumn = 'id'): string
    {
        $qc  = $this->quoteIdentifier($column);
        $qt  = $this->quoteIdentifier($refTable);
        $qrc = $this->quoteIdentifier($refColumn);
        return "FOREIGN KEY ({$qc}) REFERENCES {$qt}({$qrc}) ON DELETE RESTRICT ON UPDATE CASCADE";
    }

    public function placeholder(int $index): string
    {
        return '$' . $index;
    }

    public function limitOffset(int $limit, int $offset): string
    {
        return "LIMIT {$limit} OFFSET {$offset}";
    }

    public function quoteIdentifier(string $name): string
    {
        return '"' . str_replace('"', '""', $name) . '"';
    }

    public function columnExists(Connection $conn, string $table, string $column): bool
    {
        $row = $conn->selectOne(
            "SELECT COUNT(*) as cnt FROM information_schema.columns WHERE table_schema = 'public' AND table_name = ? AND column_name = ?",
            [$table, $column]
        );
        return (int)($row['cnt'] ?? 0) > 0;
    }
}
