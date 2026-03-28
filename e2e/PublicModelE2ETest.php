<?php

declare(strict_types=1);

namespace Puwit\E2E;

class PublicModelE2ETest extends BaseE2ETest
{
    protected function setUp(): void
    {
        $this->createModel('e2e_pub_post', [
            ['name' => 'title', 'type' => 'string', 'nullable' => false],
            ['name' => 'body',  'type' => 'text',   'nullable' => true],
        ], public: true);

        $this->createModel('e2e_priv_post', [
            ['name' => 'title', 'type' => 'string', 'nullable' => false],
        ], public: false);
    }

    public function testPublicModelListRequiresNoAuth(): void
    {
        $res = $this->http('GET', '/api/e2e_pub_post');
        $this->assertSame(200, $res['status']);
        $this->assertIsArray($res['body']['data']);
    }

    public function testPublicModelShowRequiresNoAuth(): void
    {
        $id = $this->admin('POST', '/api/e2e_pub_post', ['title' => 'Hello'])['body']['data']['id'];

        $res = $this->http('GET', "/api/e2e_pub_post/{$id}");
        $this->assertSame(200, $res['status']);
        $this->assertSame('Hello', $res['body']['data']['title']);
    }

    public function testPublicModelPostRequiresAuth(): void
    {
        $res = $this->http('POST', '/api/e2e_pub_post', ['title' => 'Sneaky']);
        $this->assertSame(401, $res['status']);
    }

    public function testPublicModelPatchRequiresAuth(): void
    {
        $id = $this->admin('POST', '/api/e2e_pub_post', ['title' => 'Existing'])['body']['data']['id'];

        $res = $this->http('PATCH', "/api/e2e_pub_post/{$id}", ['title' => 'Tampered']);
        $this->assertSame(401, $res['status']);
    }

    public function testPublicModelDeleteRequiresAuth(): void
    {
        $id = $this->admin('POST', '/api/e2e_pub_post', ['title' => 'ToDelete'])['body']['data']['id'];

        $res = $this->http('DELETE', "/api/e2e_pub_post/{$id}");
        $this->assertSame(401, $res['status']);
    }

    public function testPrivateModelRequiresAuth(): void
    {
        $res = $this->http('GET', '/api/e2e_priv_post');
        $this->assertSame(401, $res['status']);
    }

    public function testTogglePublicFlagViaAdminPatch(): void
    {
        // Make private model public
        $patch = $this->admin('PATCH', '/admin/models/e2e_priv_post', [
            'fields' => [['name' => 'title', 'type' => 'string', 'nullable' => false]],
            'public' => true,
        ]);
        $this->assertSame(200, $patch['status']);
        $this->assertTrue($patch['body']['data']['public']);

        $res = $this->http('GET', '/api/e2e_priv_post');
        $this->assertSame(200, $res['status']);

        // Toggle back
        $this->admin('PATCH', '/admin/models/e2e_priv_post', [
            'fields' => [['name' => 'title', 'type' => 'string', 'nullable' => false]],
            'public' => false,
        ]);

        $res = $this->http('GET', '/api/e2e_priv_post');
        $this->assertSame(401, $res['status']);
    }
}
