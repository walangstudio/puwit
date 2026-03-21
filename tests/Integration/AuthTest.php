<?php

declare(strict_types=1);

namespace Puwit\Tests\Integration;

class AuthTest extends BaseIntegrationTest
{
    // -------------------------------------------------------------------------
    // Unauthenticated / OPTIONS
    // -------------------------------------------------------------------------

    public function testUnauthenticatedRequestReturns401(): void
    {
        $res = $this->request('GET', '/admin/models');
        $this->assertEquals(401, $res['status']);
    }

    public function testOptionsNeverRequiresAuth(): void
    {
        $res = $this->request('OPTIONS', '/admin/models');
        $this->assertEquals(204, $res['status']);
    }

    public function testOptionsOnUnregisteredPathReturns204(): void
    {
        // Previously returned 404 without CORS headers — now handled before routing
        $res = $this->request('OPTIONS', '/api/no_such_model');
        $this->assertEquals(204, $res['status']);
    }

    public function testLoginEndpointIsPublic(): void
    {
        // Should get 422 (bad credentials format), not 401 (no auth)
        $res = $this->request('POST', '/admin/users/login', ['username' => '', 'password' => '']);
        $this->assertEquals(422, $res['status']);
    }

    // -------------------------------------------------------------------------
    // Bootstrap admin key
    // -------------------------------------------------------------------------

    public function testBootstrapAdminKeyGrants200(): void
    {
        $res = $this->admin('GET', '/admin/models');
        $this->assertEquals(200, $res['status']);
        $this->assertIsArray($res['body']['data']);
    }

    public function testWrongApiKeyReturns401(): void
    {
        $res = $this->apiKey('GET', '/admin/models', 'totally-wrong-key');
        $this->assertEquals(401, $res['status']);
    }

    // -------------------------------------------------------------------------
    // API key lifecycle
    // -------------------------------------------------------------------------

    public function testCreateApiKey(): void
    {
        $res = $this->admin('POST', '/admin/keys', ['label' => 'ci', 'scopes' => ['write']]);

        $this->assertEquals(201, $res['status']);
        $data = $res['body']['data'];
        $this->assertArrayHasKey('key', $data);
        $this->assertNotEmpty($data['key']);
        $this->assertEquals('ci', $data['label']);
        $this->assertArrayNotHasKey('key_hash', $data); // raw hash must not be exposed
    }

    public function testCreatedApiKeyWorks(): void
    {
        $this->admin('POST', '/admin/models', [
            'name'   => 'item',
            'fields' => [['name' => 'title', 'type' => 'string', 'nullable' => true]],
        ]);
        $key = $this->admin('POST', '/admin/keys', ['label' => 'k', 'scopes' => ['read']])['body']['data']['key'];

        $res = $this->apiKey('GET', '/api/item', $key);
        $this->assertEquals(200, $res['status']);
    }

    public function testRevokedKeyReturns401(): void
    {
        $create = $this->admin('POST', '/admin/keys', ['label' => 'rev', 'scopes' => ['admin']]);
        $id     = $create['body']['data']['id'];
        $key    = $create['body']['data']['key'];

        $this->admin('PATCH', "/admin/keys/{$id}", ['revoked' => 1]);

        $res = $this->apiKey('GET', '/admin/models', $key);
        $this->assertEquals(401, $res['status']);
    }

    public function testExpiredKeyReturns401(): void
    {
        $key = $this->admin('POST', '/admin/keys', [
            'label'      => 'exp',
            'scopes'     => ['admin'],
            'expires_at' => '2000-01-01 00:00:00',
        ])['body']['data']['key'];

        $res = $this->apiKey('GET', '/admin/models', $key);
        $this->assertEquals(401, $res['status']);
    }

    public function testCreateKeyWithInvalidScopeReturns422(): void
    {
        $res = $this->admin('POST', '/admin/keys', ['label' => 'bad', 'scopes' => ['superadmin']]);
        $this->assertEquals(422, $res['status']);
    }

    public function testCreateKeyWithInvalidExpiresAtReturns422(): void
    {
        $res = $this->admin('POST', '/admin/keys', [
            'label'      => 'bad',
            'scopes'     => ['read'],
            'expires_at' => 'not-a-date',
        ]);
        $this->assertEquals(422, $res['status']);
    }

    public function testPatchKeyUpdatesScopes(): void
    {
        $create = $this->admin('POST', '/admin/keys', ['label' => 'k', 'scopes' => ['read']]);
        $id     = $create['body']['data']['id'];

        $res = $this->admin('PATCH', "/admin/keys/{$id}", ['scopes' => ['write']]);
        $this->assertEquals(200, $res['status']);
        $this->assertStringContainsString('write', $res['body']['data']['scopes']);
    }

    public function testPatchKeyWithInvalidScopeReturns422(): void
    {
        $id = $this->admin('POST', '/admin/keys', ['label' => 'k', 'scopes' => ['read']])['body']['data']['id'];
        $res = $this->admin('PATCH', "/admin/keys/{$id}", ['scopes' => ['overlord']]);
        $this->assertEquals(422, $res['status']);
    }

    public function testPatchKeyWithInvalidExpiresAtReturns422(): void
    {
        $id = $this->admin('POST', '/admin/keys', ['label' => 'k', 'scopes' => ['read']])['body']['data']['id'];
        $res = $this->admin('PATCH', "/admin/keys/{$id}", ['expires_at' => 'garbage']);
        $this->assertEquals(422, $res['status']);
    }

    public function testDeleteKeyReturns204(): void
    {
        $id = $this->admin('POST', '/admin/keys', ['label' => 'k', 'scopes' => ['read']])['body']['data']['id'];
        $this->assertEquals(204, $this->admin('DELETE', "/admin/keys/{$id}")['status']);
        $this->assertEquals(404, $this->admin('GET',    "/admin/keys/{$id}")['status']);
    }

    // -------------------------------------------------------------------------
    // User + JWT lifecycle
    // -------------------------------------------------------------------------

    public function testCreateUser(): void
    {
        $res = $this->admin('POST', '/admin/users', [
            'username' => 'alice',
            'password' => 'secret123',
            'scopes'   => ['admin'],
        ]);
        $this->assertEquals(201, $res['status']);
        $this->assertEquals('alice', $res['body']['data']['username']);
        $this->assertArrayNotHasKey('password_hash', $res['body']['data']);
    }

    public function testCreateUserWithInvalidScopeReturns422(): void
    {
        $res = $this->admin('POST', '/admin/users', [
            'username' => 'bob',
            'password' => 'secret123',
            'scopes'   => ['overlord'],
        ]);
        $this->assertEquals(422, $res['status']);
    }

    public function testCreateUserDuplicateReturns409(): void
    {
        $this->admin('POST', '/admin/users', ['username' => 'alice', 'password' => 'secret123']);
        $res = $this->admin('POST', '/admin/users', ['username' => 'alice', 'password' => 'other123']);
        $this->assertEquals(409, $res['status']);
    }

    public function testLoginReturnsToken(): void
    {
        $this->admin('POST', '/admin/users', [
            'username' => 'alice',
            'password' => 'secret123',
            'scopes'   => ['admin'],
        ]);

        $res = $this->request('POST', '/admin/users/login', [
            'username' => 'alice',
            'password' => 'secret123',
        ]);

        $this->assertEquals(200, $res['status']);
        $this->assertArrayHasKey('token', $res['body']['data']);
        $this->assertEquals('Bearer', $res['body']['data']['type']);
    }

    public function testLoginWithWrongPasswordReturns401(): void
    {
        $this->admin('POST', '/admin/users', ['username' => 'alice', 'password' => 'secret123']);
        $res = $this->request('POST', '/admin/users/login', ['username' => 'alice', 'password' => 'wrong']);
        $this->assertEquals(401, $res['status']);
    }

    public function testLoginWithUnknownUserReturns401(): void
    {
        $res = $this->request('POST', '/admin/users/login', ['username' => 'ghost', 'password' => 'secret123']);
        $this->assertEquals(401, $res['status']);
    }

    public function testJwtTokenAuthorizesRequests(): void
    {
        $this->admin('POST', '/admin/users', [
            'username' => 'alice',
            'password' => 'secret123',
            'scopes'   => ['admin'],
        ]);
        $token = $this->request('POST', '/admin/users/login', [
            'username' => 'alice',
            'password' => 'secret123',
        ])['body']['data']['token'];

        $res = $this->bearer('GET', '/admin/models', $token);
        $this->assertEquals(200, $res['status']);
    }

    public function testLogoutBlocksToken(): void
    {
        $this->admin('POST', '/admin/users', [
            'username' => 'alice',
            'password' => 'secret123',
            'scopes'   => ['admin'],
        ]);
        $token = $this->request('POST', '/admin/users/login', [
            'username' => 'alice',
            'password' => 'secret123',
        ])['body']['data']['token'];

        $this->bearer('POST', '/admin/users/logout', $token);

        $res = $this->bearer('GET', '/admin/models', $token);
        $this->assertEquals(401, $res['status']);
    }

    public function testMalformedJwtReturns401(): void
    {
        $res = $this->bearer('GET', '/admin/models', 'not.a.jwt.token');
        $this->assertEquals(401, $res['status']);
    }

    public function testDisabledUserCannotLogin(): void
    {
        $id = $this->admin('POST', '/admin/users', [
            'username' => 'bob',
            'password' => 'secret123',
        ])['body']['data']['id'];

        $this->admin('PATCH', "/admin/users/{$id}", ['active' => 0]);

        $res = $this->request('POST', '/admin/users/login', [
            'username' => 'bob',
            'password' => 'secret123',
        ]);
        $this->assertEquals(403, $res['status']);
    }

    public function testUpdateUserWithInvalidScopeReturns422(): void
    {
        $id = $this->admin('POST', '/admin/users', [
            'username' => 'bob',
            'password' => 'secret123',
        ])['body']['data']['id'];

        $res = $this->admin('PATCH', "/admin/users/{$id}", ['scopes' => ['wizard']]);
        $this->assertEquals(422, $res['status']);
    }

    // -------------------------------------------------------------------------
    // Scope enforcement on data endpoints
    // -------------------------------------------------------------------------

    public function testReadKeyCannotWriteToApi(): void
    {
        $this->admin('POST', '/admin/models', [
            'name'   => 'item',
            'fields' => [['name' => 'title', 'type' => 'string', 'nullable' => false]],
        ]);
        $key = $this->admin('POST', '/admin/keys', ['label' => 'ro', 'scopes' => ['read']])['body']['data']['key'];

        $res = $this->apiKey('POST', '/api/item', $key, ['title' => 'hello']);
        $this->assertEquals(403, $res['status']);
    }

    public function testReadKeyCanReadFromApi(): void
    {
        $this->admin('POST', '/admin/models', [
            'name'   => 'item',
            'fields' => [['name' => 'title', 'type' => 'string', 'nullable' => true]],
        ]);
        $key = $this->admin('POST', '/admin/keys', ['label' => 'ro', 'scopes' => ['read']])['body']['data']['key'];

        $res = $this->apiKey('GET', '/api/item', $key);
        $this->assertEquals(200, $res['status']);
    }

    public function testWriteKeyCannotAccessAdmin(): void
    {
        $key = $this->admin('POST', '/admin/keys', ['label' => 'wr', 'scopes' => ['write']])['body']['data']['key'];

        $res = $this->apiKey('GET', '/admin/models', $key);
        $this->assertEquals(403, $res['status']);
    }
}
