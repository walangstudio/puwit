<?php

declare(strict_types=1);

namespace Puwit\E2E;

class CrudE2ETest extends BaseE2ETest
{
    protected function setUp(): void
    {
        $this->createModel('e2e_product', [
            ['name' => 'title',  'type' => 'string',  'nullable' => false],
            ['name' => 'price',  'type' => 'float',   'nullable' => true],
            ['name' => 'qty',    'type' => 'int',     'nullable' => true],
            ['name' => 'active', 'type' => 'boolean', 'nullable' => true, 'default' => 1],
        ]);
    }

    private function createRecord(array $data = []): array
    {
        return $this->admin('POST', '/api/e2e_product', array_merge([
            'title' => 'Widget',
            'price' => 9.99,
        ], $data));
    }

    // -------------------------------------------------------------------------
    // Create
    // -------------------------------------------------------------------------

    public function testCreateRecord(): void
    {
        $res = $this->createRecord(['title' => 'Widget', 'price' => 9.99]);

        $this->assertSame(201, $res['status']);
        $this->assertSame('Widget', $res['body']['data']['title']);
        $this->assertArrayHasKey('id', $res['body']['data']);
        $this->assertArrayHasKey('created_at', $res['body']['data']);
    }

    public function testCreateMissingRequiredReturns422(): void
    {
        $res = $this->admin('POST', '/api/e2e_product', ['price' => 5.0]);
        $this->assertSame(422, $res['status']);
        $this->assertStringContainsString('title', implode(' ', $res['body']['errors']));
    }

    public function testCreateWithWrongTypeReturns422(): void
    {
        $res = $this->createRecord(['qty' => 'lots']);
        $this->assertSame(422, $res['status']);
    }

    public function testCreateIgnoresUnknownFields(): void
    {
        $res = $this->createRecord(['injected' => 'bad']);
        $this->assertSame(201, $res['status']);
        $this->assertArrayNotHasKey('injected', $res['body']['data']);
    }

    // -------------------------------------------------------------------------
    // List
    // -------------------------------------------------------------------------

    public function testListReturnsRecords(): void
    {
        $this->createRecord(['title' => 'Alpha']);
        $this->createRecord(['title' => 'Beta']);

        $res = $this->admin('GET', '/api/e2e_product');
        $this->assertSame(200, $res['status']);
        $this->assertGreaterThanOrEqual(2, count($res['body']['data']));
        $this->assertArrayHasKey('total', $res['body']['meta']);
    }

    public function testListPagination(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->createRecord(['title' => "Item {$i}"]);
        }

        $res = $this->admin('GET', '/api/e2e_product', [], ['page' => '1', 'per_page' => '2']);
        $this->assertSame(200, $res['status']);
        $this->assertCount(2, $res['body']['data']);
        $this->assertSame(1, $res['body']['meta']['page']);
        $this->assertSame(2, $res['body']['meta']['per_page']);
    }

    public function testListFilterByField(): void
    {
        $this->createRecord(['title' => 'FilterMe',  'qty' => 42]);
        $this->createRecord(['title' => 'DontMatch', 'qty' => 99]);

        $res = $this->admin('GET', '/api/e2e_product', [], ['filter' => ['qty' => '42']]);
        $this->assertSame(200, $res['status']);
        $titles = array_column($res['body']['data'], 'title');
        $this->assertContains('FilterMe',  $titles);
        $this->assertNotContains('DontMatch', $titles);
    }

    public function testListSortDescending(): void
    {
        $this->createRecord(['title' => 'AAA']);
        $this->createRecord(['title' => 'ZZZ']);

        $res = $this->admin('GET', '/api/e2e_product', [], ['sort' => 'title', 'order' => 'desc']);
        $this->assertSame(200, $res['status']);
        $titles = array_column($res['body']['data'], 'title');
        $pos_z  = array_search('ZZZ', $titles);
        $pos_a  = array_search('AAA', $titles);
        $this->assertLessThan($pos_a, $pos_z);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function testShowRecord(): void
    {
        $id = $this->createRecord(['title' => 'ShowMe'])['body']['data']['id'];

        $res = $this->admin('GET', "/api/e2e_product/{$id}");
        $this->assertSame(200, $res['status']);
        $this->assertSame('ShowMe', $res['body']['data']['title']);
    }

    public function testShowNotFoundReturns404(): void
    {
        $res = $this->admin('GET', '/api/e2e_product/99999999');
        $this->assertSame(404, $res['status']);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function testPatchUpdatesSuppliedFields(): void
    {
        $id = $this->createRecord(['title' => 'Original', 'price' => 5.0])['body']['data']['id'];

        $res = $this->admin('PATCH', "/api/e2e_product/{$id}", ['price' => 99.0]);
        $this->assertSame(200, $res['status']);
        $this->assertSame('Original', $res['body']['data']['title']);
        $this->assertEqualsWithDelta(99.0, (float)$res['body']['data']['price'], 0.001);
    }

    public function testPutReplacesRecord(): void
    {
        $id = $this->createRecord(['title' => 'Old', 'price' => 1.0])['body']['data']['id'];

        $res = $this->admin('PUT', "/api/e2e_product/{$id}", ['title' => 'New', 'price' => 2.0]);
        $this->assertSame(200, $res['status']);
        $this->assertSame('New', $res['body']['data']['title']);
    }

    // -------------------------------------------------------------------------
    // Delete
    // -------------------------------------------------------------------------

    public function testDeleteRecord(): void
    {
        $id = $this->createRecord(['title' => 'ToDelete'])['body']['data']['id'];

        $del = $this->admin('DELETE', "/api/e2e_product/{$id}");
        $this->assertSame(204, $del['status']);

        $get = $this->admin('GET', "/api/e2e_product/{$id}");
        $this->assertSame(404, $get['status']);
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function testRelationInlineWithWith(): void
    {
        $this->createModel('e2e_category', [
            ['name' => 'label', 'type' => 'string', 'nullable' => false],
        ]);

        $this->admin('PATCH', '/admin/models/e2e_product', [
            'fields' => [
                ['name' => 'title',       'type' => 'string',   'nullable' => false],
                ['name' => 'price',       'type' => 'float',    'nullable' => true],
                ['name' => 'qty',         'type' => 'int',      'nullable' => true],
                ['name' => 'active',      'type' => 'boolean',  'nullable' => true, 'default' => 1],
                ['name' => 'category_id', 'type' => 'relation', 'nullable' => true, 'relation' => 'e2e_category'],
            ],
        ]);

        $catId = $this->admin('POST', '/api/e2e_category', ['label' => 'Electronics'])['body']['data']['id'];
        $pid   = $this->createRecord(['title' => 'Phone', 'category_id' => $catId])['body']['data']['id'];

        $res = $this->admin('GET', "/api/e2e_product/{$pid}", [], ['with' => 'category']);
        $this->assertSame(200, $res['status']);
        $this->assertIsArray($res['body']['data']['category']);
        $this->assertSame('Electronics', $res['body']['data']['category']['label']);
    }
}
