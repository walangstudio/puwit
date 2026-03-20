<?php

declare(strict_types=1);

namespace Puwit\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Puwit\Config\Config;
use Puwit\Database\Connection;

class JwtGuard
{
    public function __construct(private readonly Connection $conn) {}

    public function validate(string $token): ?TokenContext
    {
        $secret = Config::get('JWT_SECRET', '');
        if ($secret === '') {
            return null;
        }

        try {
            $payload = JWT::decode($token, new Key($secret, 'HS256'));
        } catch (\Throwable) {
            return null;
        }

        $jti = $payload->jti ?? null;
        if ($jti !== null && $this->isBlocklisted($jti)) {
            return null;
        }

        $scopes = (array)($payload->scopes ?? []);
        $sub    = $payload->sub ?? null;

        if ($sub === null) {
            return null;
        }

        return new TokenContext('jwt', $sub, $scopes);
    }

    public function issue(int $userId, array $scopes): string
    {
        $secret = Config::get('JWT_SECRET', '');
        $ttl    = Config::int('JWT_TTL', 3600);
        $now    = time();
        $jti    = bin2hex(random_bytes(16));

        $payload = [
            'iss'    => 'puwit',
            'sub'    => $userId,
            'iat'    => $now,
            'exp'    => $now + $ttl,
            'jti'    => $jti,
            'scopes' => $scopes,
        ];

        return JWT::encode($payload, $secret, 'HS256');
    }

    public function blocklist(string $token): void
    {
        $secret = Config::get('JWT_SECRET', '');
        try {
            $payload = JWT::decode($token, new Key($secret, 'HS256'));
        } catch (\Throwable) {
            return;
        }

        $jti = $payload->jti ?? null;
        $exp = $payload->exp ?? (time() + 3600);

        if ($jti === null) {
            return;
        }

        try {
            $this->conn->statement(
                "INSERT INTO puwit_jwt_blocklist (jti, expires_at) VALUES (?, ?)",
                [$jti, date('Y-m-d H:i:s', (int)$exp)]
            );
        } catch (\PDOException) {
            // already blocklisted — duplicate jti, safe to ignore
        }
    }

    private function isBlocklisted(string $jti): bool
    {
        $row = $this->conn->selectOne(
            "SELECT id FROM puwit_jwt_blocklist WHERE jti = ?",
            [$jti]
        );
        return $row !== null;
    }
}
