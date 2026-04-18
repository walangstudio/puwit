<?php

declare(strict_types=1);

namespace Puwit\Model;

use Puwit\Database\Connection;

class ModelRegistry
{
    private array $cache  = [];
    private bool  $loaded = false;

    public function __construct(private Connection $conn) {}

    public function all(): array
    {
        if ($this->loaded) {
            return $this->cache;
        }

        $rows = $this->conn->select("SELECT * FROM puwit_models ORDER BY name");
        foreach ($rows as $row) {
            $def = ModelDefinition::fromRow($row);
            $this->cache[$def->name] = $def;
        }

        $this->loaded = true;
        return $this->cache;
    }

    public function find(string $name): ?ModelDefinition
    {
        if (isset($this->cache[$name])) {
            return $this->cache[$name];
        }

        $row = $this->conn->selectOne(
            "SELECT * FROM puwit_models WHERE name = ?",
            [$name]
        );

        if ($row === null) {
            return null;
        }

        $def = ModelDefinition::fromRow($row);
        $this->cache[$name] = $def;
        return $def;
    }

    public function persist(ModelDefinition $definition): void
    {
        $this->conn->insert(
            "INSERT INTO puwit_models (name, table_name, fields, relations, is_public) VALUES (?, ?, ?, ?, ?)",
            [
                $definition->name,
                $definition->tableName,
                json_encode(array_map(fn($f) => $f->toArray(), $definition->fields)),
                json_encode($definition->relations),
                (int)$definition->isPublic,
            ]
        );

        $this->cache[$definition->name] = $definition;
    }

    public function update(ModelDefinition $definition): void
    {
        $this->conn->affectingStatement(
            "UPDATE puwit_models SET fields = ?, relations = ?, is_public = ?, updated_at = ? WHERE name = ?",
            [
                json_encode(array_map(fn($f) => $f->toArray(), $definition->fields)),
                json_encode($definition->relations),
                (int)$definition->isPublic,
                date('Y-m-d H:i:s'),
                $definition->name,
            ]
        );

        $this->cache[$definition->name] = $definition;
    }

    public function delete(string $name): void
    {
        $this->conn->affectingStatement(
            "DELETE FROM puwit_models WHERE name = ?",
            [$name]
        );
        unset($this->cache[$name]);
    }

}
