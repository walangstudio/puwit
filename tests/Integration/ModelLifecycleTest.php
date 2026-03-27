<?php

declare(strict_types=1);

namespace Puwit\Tests\Integration;

use Puwit\Database\ConnectionFactory;

class ModelLifecycleTest extends BaseIntegrationTest
{
    private function createProduct(): array
    {
        return $this->admin('POST', '/admin/models', [
            'name'   => 'product',
            'fields' => [
                ['name' => 'title',    'type' => 'string',  'nullable' => false],
                ['name' => 'price',    'type' => 'float',   'nullable' => true],
                ['name' => 'in_stock', 'type' => 'boolean', 'nullable' => true, 'default' => 1],
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // Create
    // -------------------------------------------------------------------------

    public function testCreateModelReturns201(): void
    {
        $res = $this->createProduct();

        $this->assertEquals(201, $res['status']);
        $this->assertEquals('product', $res['body']['data']['name']);
        $this->assertEquals('puwit_m_product', $res['body']['data']['table_name']);
        $this->assertCount(3, $res['body']['data']['fields']);
    }

    public function testCreatedModelTableExistsInDb(): void
    {
        $this->createProduct();

        // Verify by doing a CRUD call — if table doesn't exist, SELECT would throw
        $res = $this->admin('GET', '/api/product');
        $this->assertEquals(200, $res['status']);
        $this->assertIsArray($res['body']['data']);
    }

    public function testCreateDuplicateModelReturns409(): void
    {
        $this->createProduct();
        $res = $this->createProduct();
        $this->assertEquals(409, $res['status']);
    }

    public function testCreateModelWithUppercaseNameReturns422(): void
    {
        $res = $this->admin('POST', '/admin/models', [
            'name'   => 'Product',
            'fields' => [['name' => 'title', 'type' => 'string']],
        ]);
        $this->assertEquals(422, $res['status']);
        $this->assertStringContainsString('lowercase', implode(' ', $res['body']['errors']));
    }

    public function testCreateModelMissingFieldsReturns422(): void
    {
        $res = $this->admin('POST', '/admin/models', ['name' => 'thing']);
        $this->assertEquals(422, $res['status']);
    }

    public function testCreateModelWithReservedFieldReturns422(): void
    {
        $res = $this->admin('POST', '/admin/models', [
            'name'   => 'thing',
            'fields' => [['name' => 'id', 'type' => 'int']],
        ]);
        $this->assertEquals(422, $res['status']);
    }

    public function testCreateModelWithInvalidFieldTypeReturns422(): void
    {
        $res = $this->admin('POST', '/admin/models', [
            'name'   => 'thing',
            'fields' => [['name' => 'foo', 'type' => 'uuid']],
        ]);
        $this->assertEquals(422, $res['status']);
    }

    public function testCreateModelWithWrongDefaultTypeReturns422(): void
    {
        $res = $this->admin('POST', '/admin/models', [
            'name'   => 'thing',
            'fields' => [['name' => 'qty', 'type' => 'int', 'default' => 'not-a-number']],
        ]);
        $this->assertEquals(422, $res['status']);
    }

    // -------------------------------------------------------------------------
    // List / Show
    // -------------------------------------------------------------------------

    public function testListModels(): void
    {
        $this->createProduct();
        $res = $this->admin('GET', '/admin/models');

        $this->assertEquals(200, $res['status']);
        $names = array_column($res['body']['data'], 'name');
        $this->assertContains('product', $names);
    }

    public function testShowModel(): void
    {
        $this->createProduct();
        $res = $this->admin('GET', '/admin/models/product');

        $this->assertEquals(200, $res['status']);
        $this->assertEquals('product', $res['body']['data']['name']);
    }

    public function testShowModelNotFoundReturns404(): void
    {
        $res = $this->admin('GET', '/admin/models/ghost');
        $this->assertEquals(404, $res['status']);
    }

    // -------------------------------------------------------------------------
    // Schema alteration (PATCH)
    // -------------------------------------------------------------------------

    public function testAddNullableField(): void
    {
        $this->createProduct();

        $res = $this->admin('PATCH', '/admin/models/product', [
            'fields' => [
                ['name' => 'title',    'type' => 'string',  'nullable' => false],
                ['name' => 'price',    'type' => 'float',   'nullable' => true],
                ['name' => 'in_stock', 'type' => 'boolean', 'nullable' => true, 'default' => 1],
                ['name' => 'sku',      'type' => 'string',  'nullable' => true],  // new
            ],
        ]);

        $this->assertEquals(200, $res['status']);
        $names = array_column($res['body']['data']['fields'], 'name');
        $this->assertContains('sku', $names);

        // New field is usable in CRUD immediately
        $create = $this->admin('POST', '/api/product', ['title' => 'Widget', 'sku' => 'W001']);
        $this->assertEquals(201, $create['status']);
        $this->assertEquals('W001', $create['body']['data']['sku']);
    }

    public function testAddFieldWithDefault(): void
    {
        $this->createProduct();

        $res = $this->admin('PATCH', '/admin/models/product', [
            'fields' => [
                ['name' => 'title',    'type' => 'string',  'nullable' => false],
                ['name' => 'price',    'type' => 'float',   'nullable' => true],
                ['name' => 'in_stock', 'type' => 'boolean', 'nullable' => true, 'default' => 1],
                ['name' => 'qty',      'type' => 'int',     'nullable' => false, 'default' => 0],
            ],
        ]);

        $this->assertEquals(200, $res['status']);
    }

    public function testAddNotNullFieldWithoutDefaultReturns422(): void
    {
        $this->createProduct();

        $res = $this->admin('PATCH', '/admin/models/product', [
            'fields' => [
                ['name' => 'title',    'type' => 'string',  'nullable' => false],
                ['name' => 'price',    'type' => 'float',   'nullable' => true],
                ['name' => 'in_stock', 'type' => 'boolean', 'nullable' => true],
                ['name' => 'required', 'type' => 'string',  'nullable' => false],  // no default
            ],
        ]);

        $this->assertEquals(422, $res['status']);
    }

    public function testTypeChangeReturns422(): void
    {
        $this->createProduct();

        $res = $this->admin('PATCH', '/admin/models/product', [
            'fields' => [
                ['name' => 'title', 'type' => 'text', 'nullable' => false],  // string → text
                ['name' => 'price', 'type' => 'float', 'nullable' => true],
            ],
        ]);

        $this->assertEquals(422, $res['status']);
        $this->assertStringContainsString('cannot change type', $res['body']['errors'][0]);
    }

    public function testRemoveField(): void
    {
        $this->createProduct();

        $res = $this->admin('PATCH', '/admin/models/product', [
            'fields' => [
                ['name' => 'title', 'type' => 'string', 'nullable' => false],
                // price and in_stock removed
            ],
        ]);

        $this->assertEquals(200, $res['status']);
        $names = array_column($res['body']['data']['fields'], 'name');
        $this->assertNotContains('price', $names);
        $this->assertNotContains('in_stock', $names);
    }

    public function testPatchNonExistentModelReturns404(): void
    {
        $res = $this->admin('PATCH', '/admin/models/ghost', [
            'fields' => [['name' => 'title', 'type' => 'string']],
        ]);
        $this->assertEquals(404, $res['status']);
    }

    // -------------------------------------------------------------------------
    // Delete
    // -------------------------------------------------------------------------

    public function testDeleteModelReturns204(): void
    {
        $this->createProduct();
        $res = $this->admin('DELETE', '/admin/models/product');
        $this->assertEquals(204, $res['status']);
    }

    public function testDeletedModelNoLongerListedOrAccessible(): void
    {
        $this->createProduct();
        $this->admin('DELETE', '/admin/models/product');

        $res = $this->admin('GET', '/admin/models/product');
        $this->assertEquals(404, $res['status']);
    }

    public function testDeleteNonExistentModelReturns404(): void
    {
        $res = $this->admin('DELETE', '/admin/models/ghost');
        $this->assertEquals(404, $res['status']);
    }

    public function testCreateModelWithPublicFlag(): void
    {
        $res = $this->admin('POST', '/admin/models', [
            'name' => 'flagged', 'fields' => [['name' => 'body', 'type' => 'text', 'nullable' => false]], 'public' => true
        ]);
        $this->assertSame(201, $res['status']);
        $this->assertTrue($res['body']['data']['public']);
    }

    public function testPatchModelPublicFlag(): void
    {
        $this->admin('POST', '/admin/models', [
            'name' => 'toggleme', 'fields' => [['name' => 'body', 'type' => 'text', 'nullable' => false]]
        ]);
        $res = $this->admin('PATCH', '/admin/models/toggleme', ['public' => true]);
        $this->assertSame(200, $res['status']);
        $this->assertTrue($res['body']['data']['public']);
    }
}
