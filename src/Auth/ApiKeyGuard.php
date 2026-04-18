<?php

declare(strict_types=1);

namespace Puwit\Auth;

use Puwit\Config\Config;
use Puwit\Database\Connection;

class ApiKeyGuard
{
    public function __construct(private Connection $conn) {}

    public bool $bootstrapKeyUsed = false;

    public function validate(string $key): ?TokenContext
    {
        $adminKey = Config::get('PUWIT_ADMIN_KEY', '');
        if ($adminKey !== '' && hash_equals($adminKey, $key)) {
            $this->bootstrapKeyUsed = true;
            return new TokenContext('api_key', 'bootstrap', ['admin']);
        }

        $hash = hash('sha256', $key);
        $row  = $this->conn->selectOne(
            "SELECT id, scopes, expires_at, revoked FROM puwit_api_keys WHERE key_hash = ?",
            [$hash]
        );

        if ($row === null) {
            return null;
        }
        if ((int)$row['revoked'] === 1) {
            return null;
        }
        if ($row['expires_at'] !== null && strtotime($row['expires_at']) < time()) {
            return null;
        }

        $scopes = json_decode($row['scopes'], true) ?? [];
        return new TokenContext('api_key', (int)$row['id'], $scopes);
    }
}
