<?php

declare(strict_types=1);

namespace Puwit\Model;

use Puwit\Database\Connection;

class RelationResolver
{
    public function __construct(
        private Connection     $conn,
        private ModelRegistry  $registry,
    ) {}

    public function resolve(ModelDefinition $model, array $rows, array $withs): array
    {
        if (empty($rows) || empty($withs)) {
            return $rows;
        }

        foreach ($withs as $relationName) {
            $rows = $this->resolveRelation($model, $rows, $relationName);
        }

        return $rows;
    }

    private function resolveRelation(ModelDefinition $model, array $rows, string $relationName): array
    {
        $field = $model->field($relationName . '_id') ?? $model->field($relationName);

        if ($field === null || $field->type !== 'relation' || $field->relation === null) {
            return $rows;
        }

        $relatedModel = $this->registry->find($field->relation);
        if ($relatedModel === null) {
            return $rows;
        }

        $fkColumn = $field->name;
        $ids      = array_unique(array_filter(array_column($rows, $fkColumn)));

        if (empty($ids)) {
            return array_map(fn($r) => array_merge($r, [$relationName => null]), $rows);
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $qt      = $this->conn->dialect()->quoteIdentifier($relatedModel->tableName);
        $related = $this->conn->select(
            "SELECT * FROM {$qt} WHERE id IN ({$placeholders})",
            array_values($ids)
        );

        $indexed = [];
        foreach ($related as $row) {
            $indexed[$row['id']] = $row;
        }

        return array_map(function ($row) use ($fkColumn, $relationName, $indexed) {
            $row[$relationName] = $indexed[$row[$fkColumn]] ?? null;
            return $row;
        }, $rows);
    }
}
