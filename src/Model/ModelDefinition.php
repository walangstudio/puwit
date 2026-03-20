<?php

declare(strict_types=1);

namespace Puwit\Model;

class ModelDefinition
{
    /** @param FieldDefinition[] $fields */
    public function __construct(
        public readonly string $name,
        public readonly string $tableName,
        public readonly array  $fields,
        public readonly array  $relations = [],
    ) {}

    public static function fromArray(array $data): self
    {
        $name   = $data['name'];
        $table  = 'puwit_m_' . strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $name));
        $fields = array_map(
            fn($f) => FieldDefinition::fromArray($f),
            $data['fields'] ?? []
        );

        return new self(
            name:      $name,
            tableName: $table,
            fields:    $fields,
            relations: $data['relations'] ?? [],
        );
    }

    public static function fromRow(array $row): self
    {
        $fields    = json_decode($row['fields'], true) ?? [];
        $relations = json_decode($row['relations'], true) ?? [];

        return new self(
            name:      $row['name'],
            tableName: $row['table_name'],
            fields:    array_map(fn($f) => FieldDefinition::fromArray($f), $fields),
            relations: $relations,
        );
    }

    public function toArray(): array
    {
        return [
            'name'       => $this->name,
            'table_name' => $this->tableName,
            'fields'     => array_map(fn($f) => $f->toArray(), $this->fields),
            'relations'  => $this->relations,
        ];
    }

    public function field(string $name): ?FieldDefinition
    {
        foreach ($this->fields as $field) {
            if ($field->name === $name) {
                return $field;
            }
        }
        return null;
    }
}
