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
        $qt     = $this->conn->dialect()->quoteIdentifier($tableName);
        $this->conn->statement("CREATE TABLE {$qt} (\n    {$colSql}\n)");
    }

    public function dropTable(ModelDefinition $model): void
    {
        $qt = $this->conn->dialect()->quoteIdentifier($model->tableName);
        $this->conn->statement("DROP TABLE IF EXISTS {$qt}");
    }

    /** @return string[] errors — empty on success */
    public function alterTable(ModelDefinition $old, ModelDefinition $new): array
    {
        $oldFields = [];
        foreach ($old->fields as $f) {
            $oldFields[$f->name] = $f;
        }
        $newFields = [];
        foreach ($new->fields as $f) {
            $newFields[$f->name] = $f;
        }

        $added   = array_diff_key($newFields, $oldFields);
        $removed = array_diff_key($oldFields, $newFields);
        $errors  = [];

        foreach ($newFields as $name => $field) {
            if (isset($oldFields[$name]) && $oldFields[$name]->type !== $field->type) {
                $errors[] = "{$name}: cannot change type from {$oldFields[$name]->type} to {$field->type}";
            }
        }

        foreach ($added as $field) {
            if (!$field->nullable && $field->default === null) {
                $errors[] = "{$field->name}: cannot add NOT NULL column without a default value to an existing table";
            }
        }

        if (!empty($errors)) {
            return $errors;
        }

        $dialect   = $this->conn->dialect();
        $tableName = $new->tableName;
        $qt        = $dialect->quoteIdentifier($tableName);
        $isSQLite  = $dialect instanceof \Puwit\Database\Dialects\SQLiteDialect;

        foreach ($added as $field) {
            $this->conn->statement(
                "ALTER TABLE {$qt} ADD COLUMN {$this->columnDef($field)}"
            );

            if ($field->type === 'relation' && $field->relation !== null && !$isSQLite) {
                $refRow = $this->conn->selectOne(
                    "SELECT table_name FROM puwit_models WHERE name = ?",
                    [$field->relation]
                );
                if ($refRow !== null) {
                    $fkName = "fk_{$tableName}_{$field->name}";
                    $this->conn->statement(
                        "ALTER TABLE {$qt} ADD CONSTRAINT {$fkName} " .
                        $dialect->foreignKey($field->name, $refRow['table_name'])
                    );
                }
            }
        }

        foreach ($removed as $field) {
            $qc = $dialect->quoteIdentifier($field->name);
            $this->conn->statement(
                "ALTER TABLE {$qt} DROP COLUMN {$qc}"
            );
        }

        return [];
    }

    private function columnDef(FieldDefinition $field): string
    {
        $dialect  = $this->conn->dialect();
        $qc       = $dialect->quoteIdentifier($field->name);
        $type     = $dialect->columnType($field->type);
        $nullable = $field->nullable ? 'NULL' : 'NOT NULL';
        $default  = '';

        if ($field->default !== null) {
            $default = ' DEFAULT ' . $this->quoteDefault($field->default);
        }

        return "{$qc} {$type} {$nullable}{$default}";
    }

    private function quoteDefault(mixed $value): string
    {
        if (is_string($value)) {
            return "'" . str_replace("'", "''", $value) . "'";
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        return (string)$value;
    }
}
