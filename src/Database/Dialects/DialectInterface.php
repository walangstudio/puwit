<?php

declare(strict_types=1);

namespace Puwit\Database\Dialects;

use Puwit\Database\Connection;

interface DialectInterface
{
    public function columnType(string $type): string;

    public function tableExists(Connection $conn, string $table): bool;

    public function autoIncrementPrimaryKey(): string;

    public function timestampDefault(): string;

    public function foreignKey(string $column, string $refTable, string $refColumn = 'id'): string;

    public function placeholder(int $index): string;

    public function limitOffset(int $limit, int $offset): string;

    public function quoteIdentifier(string $name): string;
}
