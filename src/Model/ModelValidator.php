<?php

declare(strict_types=1);

namespace Puwit\Model;

class ModelValidator
{
    private const VALID_TYPES = ['string', 'int', 'float', 'boolean', 'text', 'datetime', 'json', 'relation'];
    private const RESERVED    = ['id', 'created_at', 'updated_at'];

    public function validate(array $data): array
    {
        $errors = [];

        if (empty($data['name'])) {
            $errors[] = 'name is required';
        } elseif (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,99}$/', $data['name'])) {
            $errors[] = 'name must start with a letter and contain only alphanumerics/underscores (max 100 chars)';
        }

        if (!isset($data['fields']) || !is_array($data['fields'])) {
            $errors[] = 'fields must be an array';
            return $errors;
        }

        if (empty($data['fields'])) {
            $errors[] = 'fields must contain at least one field';
        }

        $names = [];
        foreach ($data['fields'] as $i => $field) {
            $prefix = "fields[{$i}]";

            if (empty($field['name'])) {
                $errors[] = "{$prefix}.name is required";
                continue;
            }

            if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,99}$/', $field['name'])) {
                $errors[] = "{$prefix}.name '{$field['name']}' is invalid";
            }

            if (in_array($field['name'], self::RESERVED, true)) {
                $errors[] = "{$prefix}.name '{$field['name']}' is reserved";
            }

            if (isset($names[$field['name']])) {
                $errors[] = "{$prefix}.name '{$field['name']}' is duplicated";
            }
            $names[$field['name']] = true;

            if (empty($field['type'])) {
                $errors[] = "{$prefix}.type is required";
            } elseif (!in_array($field['type'], self::VALID_TYPES, true)) {
                $errors[] = "{$prefix}.type '{$field['type']}' is invalid; valid: " . implode(', ', self::VALID_TYPES);
            }

            if (($field['type'] ?? '') === 'relation' && empty($field['relation'])) {
                $errors[] = "{$prefix}.relation must specify the related model name when type is 'relation'";
            }
        }

        return $errors;
    }
}
