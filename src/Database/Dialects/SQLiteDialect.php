<?php

declare(strict_types=1);

namespace Puwit\Database\Dialects;

use Puwit\Database\Connection;

class SQLiteDialect implements DialectInterface
{
    private const TYPE_MAP = [
        'string'   => 'TEXT',
        'int'      => 'INTEGER',
        'float'    => 'REAL',
        'boolean'  => 'INTEGER',
        'text'     => 'TEXT',
        'datetime' => 'TEXT',
        'json'     => 'TEXT',
        'relation' => 'INTEGER',
    ];

    public function columnType(string $type): string
    {
        return self::TYPE_MAP[$type] ?? 'TEXT';
    }

    public function tableExists(Connection $conn, string $table): bool
    {
        $row = $conn->selectOne(
            "SELECT name FROM sqlite_master WHERE type='table' AND name=?",
            [$table]
        );
        return $row !== null;
    }

    public function autoIncrementPrimaryKey(): string
    {
        return 'INTEGER PRIMARY KEY AUTOINCREMENT';
    }

    public function timestampDefault(): string
    {
        return "TEXT NOT NULL DEFAULT (datetime('now'))";
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
        return '?';
    }

    public function limitOffset(int $limit, int $offset): string
    {
        return "LIMIT {$limit} OFFSET {$offset}";
    }

    public function quoteIdentifier(string $name): string
    {
        return '"' . str_replace('"', '""', $name) . '"';
    }
}
