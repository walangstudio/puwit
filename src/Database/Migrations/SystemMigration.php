<?php

declare(strict_types=1);

namespace Puwit\Database\Migrations;

use Puwit\Database\Connection;

class SystemMigration
{
    public function __construct(private readonly Connection $conn) {}

    public function run(): void
    {
        $this->conn->transaction(function (Connection $conn) {
            $this->createModelsTable($conn);
            $this->createApiKeysTable($conn);
            $this->createUsersTable($conn);
            $this->createJwtBlocklistTable($conn);
        });
        $this->addIsPublicColumn();
    }

    private function addIsPublicColumn(): void
    {
        if (!$this->conn->columnExists('puwit_models', 'is_public')) {
            $this->conn->statement(
                'ALTER TABLE puwit_models ADD COLUMN is_public INTEGER NOT NULL DEFAULT 0'
            );
        }
    }

    private function createModelsTable(Connection $conn): void
    {
        $pk = $conn->dialect()->autoIncrementPrimaryKey();
        $ts = $conn->dialect()->timestampDefault();

        $conn->statement("
            CREATE TABLE IF NOT EXISTS puwit_models (
                id          {$pk},
                name        VARCHAR(100) NOT NULL UNIQUE,
                table_name  VARCHAR(150) NOT NULL UNIQUE,
                fields      TEXT NOT NULL,
                relations   TEXT NOT NULL DEFAULT '[]',
                is_public   INTEGER NOT NULL DEFAULT 0,
                created_at  {$ts},
                updated_at  {$ts}
            )
        ");
    }

    private function createApiKeysTable(Connection $conn): void
    {
        $pk = $conn->dialect()->autoIncrementPrimaryKey();
        $ts = $conn->dialect()->timestampDefault();

        $conn->statement("
            CREATE TABLE IF NOT EXISTS puwit_api_keys (
                id          {$pk},
                label       VARCHAR(100) NOT NULL,
                key_hash    VARCHAR(64) NOT NULL UNIQUE,
                scopes      TEXT NOT NULL DEFAULT '[\"read\"]',
                expires_at  TEXT NULL,
                revoked     INTEGER NOT NULL DEFAULT 0,
                created_at  {$ts},
                updated_at  {$ts}
            )
        ");
    }

    private function createUsersTable(Connection $conn): void
    {
        $pk = $conn->dialect()->autoIncrementPrimaryKey();
        $ts = $conn->dialect()->timestampDefault();

        $conn->statement("
            CREATE TABLE IF NOT EXISTS puwit_users (
                id            {$pk},
                username      VARCHAR(100) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                scopes        TEXT NOT NULL DEFAULT '[\"read\"]',
                active        INTEGER NOT NULL DEFAULT 1,
                created_at    {$ts},
                updated_at    {$ts}
            )
        ");
    }

    private function createJwtBlocklistTable(Connection $conn): void
    {
        $pk = $conn->dialect()->autoIncrementPrimaryKey();

        $conn->statement("
            CREATE TABLE IF NOT EXISTS puwit_jwt_blocklist (
                id         {$pk},
                jti        VARCHAR(36) NOT NULL UNIQUE,
                expires_at TEXT NOT NULL
            )
        ");
    }
}
