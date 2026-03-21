<?php

declare(strict_types=1);

namespace Puwit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Puwit\Database\Connection;
use Puwit\Database\Dialects\SQLiteDialect;
use Puwit\Database\QueryBuilder;

class QueryBuilderGuardTest extends TestCase
{
    private Connection $conn;

    protected function setUp(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $this->conn = new Connection($pdo, new SQLiteDialect());
        $this->conn->statement('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
        $this->conn->statement("INSERT INTO items (name) VALUES ('foo')");
    }

    public function testUpdateWithoutWhereThrows(): void
    {
        $this->expectException(\LogicException::class);
        (new QueryBuilder($this->conn))->table('items')->update(['name' => 'bar']);
    }

    public function testDeleteWithoutWhereThrows(): void
    {
        $this->expectException(\LogicException::class);
        (new QueryBuilder($this->conn))->table('items')->delete();
    }

    public function testUpdateWithWhereSucceeds(): void
    {
        $affected = (new QueryBuilder($this->conn))->table('items')->where('id', 1)->update(['name' => 'bar']);
        $this->assertEquals(1, $affected);
    }

    public function testDeleteWithWhereSucceeds(): void
    {
        $affected = (new QueryBuilder($this->conn))->table('items')->where('id', 1)->delete();
        $this->assertEquals(1, $affected);
    }
}
