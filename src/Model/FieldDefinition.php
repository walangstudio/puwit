<?php

declare(strict_types=1);

namespace Puwit\Model;

class FieldDefinition
{
    public function __construct(
        public readonly string  $name,
        public readonly string  $type,
        public readonly bool    $nullable = true,
        public readonly mixed   $default = null,
        public readonly ?string $relation = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name:     $data['name'],
            type:     $data['type'],
            nullable: (bool)($data['nullable'] ?? true),
            default:  $data['default'] ?? null,
            relation: $data['relation'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'name'     => $this->name,
            'type'     => $this->type,
            'nullable' => $this->nullable,
            'default'  => $this->default,
            'relation' => $this->relation,
        ];
    }
}
