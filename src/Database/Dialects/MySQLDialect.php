<?php

declare(strict_types=1);

namespace Puwit\Database\Dialects;

use Puwit\Database\Connection;

class MySQLDialect implements DialectInterface
{
    private const TYPE_MAP = [
        'string'   => 'VARCHAR(255)',
        'int'      => 'INT',
        'float'    => 'DECIMAL(10,4)',
        'boolean'  => 'TINYINT(1)',
        'text'     => 'LONGTEXT',
        'datetime' => 'DATETIME',
        'json'     => 'JSON',
        'relation' => 'INT UNSIGNED',
    ];

    public function columnType(string $type): string
    {
        return self::TYPE_MAP[$type] ?? 'VARCHAR(255)';
    }

    public function tableExists(Connection $conn, string $table): bool
    {
        $row = $conn->selectOne(
            'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        );
        return $row !== null;
    }

    public function autoIncrementPrimaryKey(): string
    {
        return 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
    }

    public function timestampDefault(): string
    {
        return 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP';
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
        return '`' . str_replace('`', '``', $name) . '`';
    }

    public function columnExists(Connection $conn, string $table, string $column): bool
    {
        $row = $conn->selectOne(
            "SELECT COUNT(*) as cnt FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?",
            [$table, $column]
        );
        return (int)($row['cnt'] ?? 0) > 0;
    }
}
