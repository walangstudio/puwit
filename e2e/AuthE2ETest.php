<?php

declare(strict_types=1);

namespace Puwit\E2E;

class AuthE2ETest extends BaseE2ETest
{
    private array $usersToDelete = [];
    private array $keysToDelete  = [];

    protected function tearDown(): void
    {
        foreach ($this->usersToDelete as $id) {
            $this->admin('DELETE', "/admin/users/{$id}");
        }
        $this->usersToDelete = [];

        foreach ($this->keysToDelete as $id) {
            $this->admin('DELETE', "/admin/keys/{$id}");
        }
        $this->keysToDelete = [];

        parent::tearDown();
    }

    private function createUser(string $username, string $password = 'E2eSecret!99', array $scopes = ['admin']): array
    {
        $res = $this->admin('POST', '/admin/users', compact('username', 'password', 'scopes'));
        $this->assertSame(201, $res['status'], "Failed to create user '{$username}'");
        $this->usersToDelete[] = $res['body']['data']['id'];
        return $res['body']['data'];
    }

    private function createKey(string $label, array $scopes): array
    {
        $res = $this->admin('POST', '/admin/keys', compact('label', 'scopes'));
        $this->assertSame(201, $res['status']);
        $this->keysToDelete[] = $res['body']['data']['id'];
        return $res['body']['data'];
    }

    // -------------------------------------------------------------------------
    // Bootstrap / API key
    // -------------------------------------------------------------------------

    public function testAdminKeyReachesServer(): void
    {
        $res = $this->admin('GET', '/admin/models');
        $this->assertSame(200, $res['status']);
        $this->assertIsArray($res['body']['data']);
    }

    public function testWrongKeyReturns401(): void
    {
        $res = $this->apiKey('GET', '/admin/models', 'wrong-key-totally-invalid');
        $this->assertSame(401, $res['status']);
    }

    public function testUnauthenticatedRequestReturns401(): void
    {
        $res = $this->http('GET', '/admin/models');
        $this->assertSame(401, $res['status']);
    }

    public function testCreateApiKeyAndUseIt(): void
    {
        $key = $this->createKey('e2e-read', ['read']);

        $this->assertNotEmpty($key['key']);
        $this->assertArrayNotHasKey('key_hash', $key);

        $this->createModel('e2e_auth_item', [
            ['name' => 'title', 'type' => 'string', 'nullable' => false],
        ]);

        $res = $this->apiKey('GET', '/api/e2e_auth_item', $key['key']);
        $this->assertSame(200, $res['status']);
    }

    public function testRevokedKeyReturns401(): void
    {
        $key = $this->createKey('e2e-rev', ['admin']);

        $this->admin('PATCH', "/admin/keys/{$key['id']}", ['revoked' => 1]);

        $res = $this->apiKey('GET', '/admin/models', $key['key']);
        $this->assertSame(401, $res['status']);
    }

    // -------------------------------------------------------------------------
    // JWT
    // -------------------------------------------------------------------------

    public function testLoginAndUseJwt(): void
    {
        $username = 'e2e_user_' . substr(uniqid(), -6);
        $this->createUser($username);

        $login = $this->http('POST', '/admin/users/login', [
            'username' => $username,
            'password' => 'E2eSecret!99',
        ]);
        $this->assertSame(200, $login['status']);
        $token = $login['body']['data']['token'];
        $this->assertNotEmpty($token);

        $res = $this->bearer('GET', '/admin/models', $token);
        $this->assertSame(200, $res['status']);
    }

    public function testLogoutInvalidatesToken(): void
    {
        $username = 'e2e_logout_' . substr(uniqid(), -6);
        $this->createUser($username);

        $token = $this->http('POST', '/admin/users/login', [
            'username' => $username,
            'password' => 'E2eSecret!99',
        ])['body']['data']['token'];

        $logout = $this->bearer('POST', '/admin/users/logout', $token);
        $this->assertSame(200, $logout['status']);

        $res = $this->bearer('GET', '/admin/models', $token);
        $this->assertSame(401, $res['status']);
    }

    public function testWrongPasswordReturns401(): void
    {
        $username = 'e2e_wp_' . substr(uniqid(), -6);
        $this->createUser($username);

        $res = $this->http('POST', '/admin/users/login', [
            'username' => $username,
            'password' => 'wrongpassword',
        ]);
        $this->assertSame(401, $res['status']);
    }

    // -------------------------------------------------------------------------
    // Scope enforcement
    // -------------------------------------------------------------------------

    public function testReadScopeCannotWrite(): void
    {
        $this->createModel('e2e_scope_item', [
            ['name' => 'title', 'type' => 'string', 'nullable' => false],
        ]);

        $key = $this->createKey('e2e-ro', ['read']);

        $res = $this->apiKey('POST', '/api/e2e_scope_item', $key['key'], ['title' => 'x']);
        $this->assertSame(403, $res['status']);
    }

    public function testWriteScopeCannotAccessAdmin(): void
    {
        $key = $this->createKey('e2e-wr', ['write']);

        $res = $this->apiKey('GET', '/admin/models', $key['key']);
        $this->assertSame(403, $res['status']);
    }
}
