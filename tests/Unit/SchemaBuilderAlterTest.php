<?php

declare(strict_types=1);

namespace Puwit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Puwit\Database\Connection;
use Puwit\Database\Dialects\SQLiteDialect;
use Puwit\Database\SchemaBuilder;
use Puwit\Model\FieldDefinition;
use Puwit\Model\ModelDefinition;

class SchemaBuilderAlterTest extends TestCase
{
    private Connection    $conn;
    private SchemaBuilder $schema;

    protected function setUp(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $this->conn   = new Connection($pdo, new SQLiteDialect());
        $this->schema = new SchemaBuilder($this->conn);

        $this->conn->statement("CREATE TABLE puwit_models (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            table_name TEXT NOT NULL,
            fields TEXT NOT NULL DEFAULT '[]',
            relations TEXT NOT NULL DEFAULT '[]',
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            updated_at TEXT NOT NULL DEFAULT (datetime('now'))
        )");
    }

    private function makeDef(string $name, array $fields): ModelDefinition
    {
        return new ModelDefinition(
            name:      $name,
            tableName: "puwit_m_{$name}",
            fields:    array_map(fn($f) => new FieldDefinition(...$f), $fields),
            relations: [],
        );
    }

    public function testTypeChangeReturnsError(): void
    {
        $old = $this->makeDef('item', [
            ['name' => 'title', 'type' => 'string', 'nullable' => false, 'default' => null, 'relation' => null],
        ]);
        $new = $this->makeDef('item', [
            ['name' => 'title', 'type' => 'text', 'nullable' => false, 'default' => null, 'relation' => null],
        ]);

        $errors = $this->schema->alterTable($old, $new);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('cannot change type', $errors[0]);
    }

    public function testAddNotNullWithoutDefaultReturnsError(): void
    {
        $old = $this->makeDef('item', [
            ['name' => 'title', 'type' => 'string', 'nullable' => false, 'default' => null, 'relation' => null],
        ]);
        $new = $this->makeDef('item', [
            ['name' => 'title', 'type' => 'string', 'nullable' => false, 'default' => null, 'relation' => null],
            ['name' => 'qty',   'type' => 'int',    'nullable' => false, 'default' => null, 'relation' => null],
        ]);

        $errors = $this->schema->alterTable($old, $new);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('NOT NULL', $errors[0]);
    }

    public function testAddNullableColumnSucceeds(): void
    {
        $old = $this->makeDef('item', [
            ['name' => 'title', 'type' => 'string', 'nullable' => false, 'default' => null, 'relation' => null],
        ]);
        $new = $this->makeDef('item', [
            ['name' => 'title', 'type' => 'string', 'nullable' => false, 'default' => null, 'relation' => null],
            ['name' => 'qty',   'type' => 'int',    'nullable' => true,  'default' => null, 'relation' => null],
        ]);

        $this->schema->createTable($old);
        $errors = $this->schema->alterTable($old, $new);
        $this->assertEmpty($errors);

        $row = $this->conn->selectOne("PRAGMA table_info(puwit_m_item)");
        $cols = $this->conn->select("PRAGMA table_info(puwit_m_item)");
        $names = array_column($cols, 'name');
        $this->assertContains('qty', $names);
    }
}
