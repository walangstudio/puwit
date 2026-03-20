<?php

declare(strict_types=1);

namespace Puwit\Database;

use Puwit\Model\FieldDefinition;
use Puwit\Model\ModelDefinition;

class SchemaBuilder
{
    public function __construct(private readonly Connection $conn) {}

    public function createTable(ModelDefinition $model): void
    {
        $dialect   = $this->conn->dialect();
        $pk        = $dialect->autoIncrementPrimaryKey();
        $ts        = $dialect->timestampDefault();
        $tableName = $model->tableName;

        $columns = ["id {$pk}"];

        foreach ($model->fields as $field) {
            $columns[] = $this->columnDef($field);
        }

        $columns[] = "created_at {$ts}";
        $columns[] = "updated_at {$ts}";

        foreach ($model->fields as $field) {
            if ($field->type === 'relation' && $field->relation !== null) {
                $refRow = $this->conn->selectOne(
                    "SELECT table_name FROM puwit_models WHERE name = ?",
                    [$field->relation]
                );
                if ($refRow !== null) {
                    $columns[] = $dialect->foreignKey($field->name, $refRow['table_name']);
                }
            }
        }

        $colSql = implode(",\n    ", $columns);
        $this->conn->statement("CREATE TABLE {$tableName} (\n    {$colSql}\n)");
    }

    public function dropTable(ModelDefinition $model): void
    {
        $this->conn->statement("DROP TABLE IF EXISTS {$model->tableName}");
    }

    private function columnDef(FieldDefinition $field): string
    {
        $dialect  = $this->conn->dialect();
        $type     = $dialect->columnType($field->type);
        $nullable = $field->nullable ? 'NULL' : 'NOT NULL';
        $default  = '';

        if ($field->default !== null) {
            $default = ' DEFAULT ' . $this->quoteDefault($field->default);
        }

        return "{$field->name} {$type} {$nullable}{$default}";
    }

    private function quoteDefault(mixed $value): string
    {
        if (is_string($value)) {
            return "'" . addslashes($value) . "'";
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        return (string)$value;
    }
}
