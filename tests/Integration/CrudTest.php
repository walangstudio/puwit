<?php

declare(strict_types=1);

namespace Puwit\Tests\Integration;

class CrudTest extends BaseIntegrationTest
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->admin('POST', '/admin/models', [
            'name'   => 'product',
            'fields' => [
                ['name' => 'title', 'type' => 'string',  'nullable' => false],
                ['name' => 'price', 'type' => 'float',   'nullable' => true],
                ['name' => 'qty',   'type' => 'int',     'nullable' => true],
                ['name' => 'active','type' => 'boolean', 'nullable' => true, 'default' => 1],
            ],
        ]);
    }

    private function createRecord(array $data = []): array
    {
        return $this->admin('POST', '/api/product', array_merge([
            'title' => 'Widget',
            'price' => 9.99,
            'qty'   => 10,
        ], $data));
    }

    // -------------------------------------------------------------------------
    // Create
    // -------------------------------------------------------------------------

    public function testCreateRecord(): void
    {
        $res = $this->createRecord(['title' => 'Widget', 'price' => 9.99]);

        $this->assertEquals(201, $res['status']);
        $this->assertEquals('Widget', $res['body']['data']['title']);
        $this->assertEquals('9.9900', $res['body']['data']['price']);
        $this->assertArrayHasKey('id', $res['body']['data']);
        $this->assertArrayHasKey('created_at', $res['body']['data']);
    }

    public function testCreateRecordMissingRequiredReturns422(): void
    {
        $res = $this->admin('POST', '/api/product', ['price' => 5.0]);  // title missing
        $this->assertEquals(422, $res['status']);
        $this->assertStringContainsString('title', implode(' ', $res['body']['errors']));
    }

    public function testCreateRecordWithWrongIntTypeReturns422(): void
    {
        $res = $this->createRecord(['title' => 'Widget', 'qty' => 'lots']);
        $this->assertEquals(422, $res['status']);
    }

    public function testCreateRecordWithWrongFloatTypeReturns422(): void
    {
        $res = $this->createRecord(['title' => 'Widget', 'price' => 'cheap']);
        $this->assertEquals(422, $res['status']);
    }

    public function testCreateRecordIgnoresUnknownFields(): void
    {
        $res = $this->createRecord(['title' => 'Widget', 'injected' => 'bad']);
        $this->assertEquals(201, $res['status']);
        $this->assertArrayNotHasKey('injected', $res['body']['data']);
    }

    public function testNullableFieldAcceptsNull(): void
    {
        $res = $this->admin('POST', '/api/product', ['title' => 'Widget', 'price' => null]);
        $this->assertEquals(201, $res['status']);
        $this->assertNull($res['body']['data']['price']);
    }

    public function testBooleanFalseStoredAsZeroNotEmptyString(): void
    {
        $res = $this->admin('POST', '/api/product', ['title' => 'Widget', 'active' => false]);
        $this->assertEquals(201, $res['status']);
        // PHP false must be coerced to int 0 before PDO binding, not empty string ''
        $this->assertSame(0, $res['body']['data']['active']);
    }

    public function testBooleanFilterWorks(): void
    {
        $this->admin('POST', '/api/product', ['title' => 'Active',   'active' => true]);
        $this->admin('POST', '/api/product', ['title' => 'Inactive', 'active' => false]);

        $res = $this->admin('GET', '/api/product', [], ['filter' => ['active' => '0']]);
        $this->assertEquals(200, $res['status']);
        $titles = array_column($res['body']['data'], 'title');
        $this->assertContains('Inactive', $titles);
        $this->assertNotContains('Active', $titles);
    }

    // -------------------------------------------------------------------------
    // List
    // -------------------------------------------------------------------------

    public function testListReturnsAllRecords(): void
    {
        $this->createRecord(['title' => 'Alpha']);
        $this->createRecord(['title' => 'Beta']);

        $res = $this->admin('GET', '/api/product');
        $this->assertEquals(200, $res['status']);
        $this->assertCount(2, $res['body']['data']);
        $this->assertEquals(2, $res['body']['meta']['total']);
    }

    public function testListPagination(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->createRecord(['title' => "Item {$i}"]);
        }

        $res = $this->admin('GET', '/api/product', [], ['page' => '2', 'per_page' => '2']);
        $this->assertEquals(200, $res['status']);
        $this->assertCount(2, $res['body']['data']);
        $this->assertEquals(5, $res['body']['meta']['total']);
        $this->assertEquals(2, $res['body']['meta']['page']);
    }

    public function testListFilterByField(): void
    {
        $this->createRecord(['title' => 'Alpha', 'qty' => 5]);
        $this->createRecord(['title' => 'Beta',  'qty' => 10]);

        $res = $this->admin('GET', '/api/product', [], ['filter' => ['qty' => '5']]);
        $this->assertEquals(200, $res['status']);
        $this->assertCount(1, $res['body']['data']);
        $this->assertEquals('Alpha', $res['body']['data'][0]['title']);
    }

    public function testListSortDescending(): void
    {
        $this->createRecord(['title' => 'Alpha']);
        $this->createRecord(['title' => 'Beta']);

        $res = $this->admin('GET', '/api/product', [], ['sort' => 'title', 'order' => 'desc']);
        $this->assertEquals(200, $res['status']);
        $this->assertEquals('Beta', $res['body']['data'][0]['title']);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function testShowRecord(): void
    {
        $id = $this->createRecord(['title' => 'Widget'])['body']['data']['id'];

        $res = $this->admin('GET', "/api/product/{$id}");
        $this->assertEquals(200, $res['status']);
        $this->assertEquals('Widget', $res['body']['data']['title']);
    }

    public function testShowNotFoundReturns404(): void
    {
        $res = $this->admin('GET', '/api/product/9999');
        $this->assertEquals(404, $res['status']);
    }

    // -------------------------------------------------------------------------
    // Replace (PUT)
    // -------------------------------------------------------------------------

    public function testReplaceRecord(): void
    {
        $id = $this->createRecord(['title' => 'Old', 'price' => 5.0])['body']['data']['id'];

        $res = $this->admin('PUT', "/api/product/{$id}", ['title' => 'New', 'price' => 15.0]);
        $this->assertEquals(200, $res['status']);
        $this->assertEquals('New', $res['body']['data']['title']);
        $this->assertEquals('15.0000', $res['body']['data']['price']);
    }

    public function testReplaceNotFoundReturns404(): void
    {
        $res = $this->admin('PUT', '/api/product/9999', ['title' => 'Ghost']);
        $this->assertEquals(404, $res['status']);
    }

    public function testReplaceNullsOmittedNullableFields(): void
    {
        $id = $this->createRecord(['title' => 'Full', 'price' => 9.99, 'qty' => 5])['body']['data']['id'];

        // PUT with only required fields — nullable fields should become null
        $res = $this->admin('PUT', "/api/product/{$id}", ['title' => 'Slim']);
        $this->assertEquals(200, $res['status']);
        $this->assertNull($res['body']['data']['price']);
        $this->assertNull($res['body']['data']['qty']);
    }

    // -------------------------------------------------------------------------
    // Partial update (PATCH)
    // -------------------------------------------------------------------------

    public function testPartialUpdateChangesOnlySuppliedFields(): void
    {
        $id = $this->createRecord(['title' => 'Original', 'price' => 5.0])['body']['data']['id'];

        $res = $this->admin('PATCH', "/api/product/{$id}", ['price' => 99.0]);
        $this->assertEquals(200, $res['status']);
        $this->assertEquals('Original', $res['body']['data']['title']); // unchanged
        $this->assertEquals('99.0000', $res['body']['data']['price']);  // updated
    }

    public function testPartialUpdateWithWrongTypeReturns422(): void
    {
        $id = $this->createRecord()['body']['data']['id'];
        $res = $this->admin('PATCH', "/api/product/{$id}", ['qty' => 'many']);
        $this->assertEquals(422, $res['status']);
    }

    // -------------------------------------------------------------------------
    // Delete
    // -------------------------------------------------------------------------

    public function testDeleteRecord(): void
    {
        $id = $this->createRecord()['body']['data']['id'];

        $res = $this->admin('DELETE', "/api/product/{$id}");
        $this->assertEquals(204, $res['status']);

        $res = $this->admin('GET', "/api/product/{$id}");
        $this->assertEquals(404, $res['status']);
    }

    public function testDeleteNotFoundReturns404(): void
    {
        $res = $this->admin('DELETE', '/api/product/9999');
        $this->assertEquals(404, $res['status']);
    }

    // -------------------------------------------------------------------------
    // Unknown model
    // -------------------------------------------------------------------------

    public function testUnknownModelReturns404(): void
    {
        $res = $this->admin('GET', '/api/ghost');
        $this->assertEquals(404, $res['status']);
    }

    public function testMethodNotAllowedReturns405(): void
    {
        $res = $this->admin('DELETE', '/api/product');  // no DELETE on collection
        $this->assertEquals(405, $res['status']);
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function testRelationWithQuery(): void
    {
        // Create a second model: category
        $this->admin('POST', '/admin/models', [
            'name'   => 'category',
            'fields' => [
                ['name' => 'label', 'type' => 'string', 'nullable' => false],
            ],
        ]);

        // Add category_id relation field to product
        $this->admin('PATCH', '/admin/models/product', [
            'fields' => [
                ['name' => 'title',       'type' => 'string',   'nullable' => false],
                ['name' => 'price',       'type' => 'float',    'nullable' => true],
                ['name' => 'qty',         'type' => 'int',      'nullable' => true],
                ['name' => 'active',      'type' => 'boolean',  'nullable' => true, 'default' => 1],
                ['name' => 'category_id', 'type' => 'relation', 'nullable' => true, 'relation' => 'category'],
            ],
        ]);

        $catId = $this->admin('POST', '/api/category', ['label' => 'Electronics'])['body']['data']['id'];
        $pid   = $this->admin('POST', '/api/product',  ['title' => 'Phone', 'category_id' => $catId])['body']['data']['id'];

        $res = $this->admin('GET', "/api/product/{$pid}", [], ['with' => 'category']);
        $this->assertEquals(200, $res['status']);
        $this->assertIsArray($res['body']['data']['category']);
        $this->assertEquals('Electronics', $res['body']['data']['category']['label']);
    }

    public function testRelationReturnsNullWhenFkIsNull(): void
    {
        $this->admin('POST', '/admin/models', [
            'name'   => 'category',
            'fields' => [['name' => 'label', 'type' => 'string', 'nullable' => false]],
        ]);

        $this->admin('PATCH', '/admin/models/product', [
            'fields' => [
                ['name' => 'title',       'type' => 'string',   'nullable' => false],
                ['name' => 'price',       'type' => 'float',    'nullable' => true],
                ['name' => 'qty',         'type' => 'int',      'nullable' => true],
                ['name' => 'active',      'type' => 'boolean',  'nullable' => true, 'default' => 1],
                ['name' => 'category_id', 'type' => 'relation', 'nullable' => true, 'relation' => 'category'],
            ],
        ]);

        $pid = $this->admin('POST', '/api/product', ['title' => 'Uncategorised'])['body']['data']['id'];

        $res = $this->admin('GET', "/api/product/{$pid}", [], ['with' => 'category']);
        $this->assertEquals(200, $res['status']);
        $this->assertNull($res['body']['data']['category']);
    }

    // -------------------------------------------------------------------------
    // Schema change reflected in subsequent CRUD
    // -------------------------------------------------------------------------

    public function testAddedFieldUsableImmediately(): void
    {
        $this->admin('PATCH', '/admin/models/product', [
            'fields' => [
                ['name' => 'title',  'type' => 'string', 'nullable' => false],
                ['name' => 'price',  'type' => 'float',  'nullable' => true],
                ['name' => 'qty',    'type' => 'int',    'nullable' => true],
                ['name' => 'active', 'type' => 'boolean','nullable' => true, 'default' => 1],
                ['name' => 'sku',    'type' => 'string', 'nullable' => true],
            ],
        ]);

        $res = $this->admin('POST', '/api/product', ['title' => 'Widget', 'sku' => 'WG-001']);
        $this->assertEquals(201, $res['status']);
        $this->assertEquals('WG-001', $res['body']['data']['sku']);
    }

    public function testRemovedFieldNotReturned(): void
    {
        $this->admin('PATCH', '/admin/models/product', [
            'fields' => [
                ['name' => 'title', 'type' => 'string', 'nullable' => false],
                // price, qty, active removed
            ],
        ]);

        $id = $this->admin('POST', '/api/product', ['title' => 'Slim'])['body']['data']['id'];

        $res = $this->admin('GET', "/api/product/{$id}");
        $this->assertEquals(200, $res['status']);
        $this->assertArrayNotHasKey('price', $res['body']['data']);
    }
}
