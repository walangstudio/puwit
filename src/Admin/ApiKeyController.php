<?php

declare(strict_types=1);

namespace Puwit\Admin;

use Puwit\Database\Connection;
use Puwit\Http\Request;
use Puwit\Http\Response;

class ApiKeyController
{
    public function __construct(private readonly Connection $conn) {}

    public function index(Request $request): Response
    {
        $rows = $this->conn->select(
            "SELECT id, label, scopes, expires_at, revoked, created_at FROM puwit_api_keys ORDER BY id DESC"
        );
        return Response::ok($rows);
    }

    public function create(Request $request): Response
    {
        $label     = $request->body('label', '');
        $scopes    = $request->body('scopes', ['read']);
        $expiresAt = $request->body('expires_at');

        if (empty($label)) {
            return Response::error('label is required', 422);
        }

        if (!is_array($scopes)) {
            return Response::error('scopes must be an array', 422);
        }

        $validScopes = ['read', 'write', 'admin'];
        foreach ($scopes as $scope) {
            if (!in_array($scope, $validScopes, true)) {
                return Response::error("Invalid scope '{$scope}'", 422);
            }
        }

        $rawKey  = bin2hex(random_bytes(32));
        $keyHash = hash('sha256', $rawKey);

        $this->conn->insert(
            "INSERT INTO puwit_api_keys (label, key_hash, scopes, expires_at) VALUES (?, ?, ?, ?)",
            [$label, $keyHash, json_encode($scopes), $expiresAt]
        );

        $row = $this->conn->selectOne(
            "SELECT id, label, scopes, expires_at, revoked, created_at FROM puwit_api_keys WHERE key_hash = ?",
            [$keyHash]
        );

        return Response::created(array_merge($row, ['key' => $rawKey]));
    }

    public function show(Request $request): Response
    {
        $id  = (int)$request->param('id');
        $row = $this->conn->selectOne(
            "SELECT id, label, scopes, expires_at, revoked, created_at FROM puwit_api_keys WHERE id = ?",
            [$id]
        );

        if ($row === null) {
            return Response::notFound("API key #{$id} not found");
        }

        return Response::ok($row);
    }

    public function update(Request $request): Response
    {
        $id  = (int)$request->param('id');
        $row = $this->conn->selectOne("SELECT id FROM puwit_api_keys WHERE id = ?", [$id]);

        if ($row === null) {
            return Response::notFound("API key #{$id} not found");
        }

        $sets     = [];
        $bindings = [];

        if ($request->body('label') !== null) {
            $sets[]     = 'label = ?';
            $bindings[] = $request->body('label');
        }
        if ($request->body('scopes') !== null) {
            $sets[]     = 'scopes = ?';
            $bindings[] = json_encode($request->body('scopes'));
        }
        if ($request->body('revoked') !== null) {
            $sets[]     = 'revoked = ?';
            $bindings[] = (int)$request->body('revoked');
        }
        if ($request->body('expires_at') !== null) {
            $sets[]     = 'expires_at = ?';
            $bindings[] = $request->body('expires_at');
        }

        if (empty($sets)) {
            return Response::error('No fields to update', 422);
        }

        $sets[]     = 'updated_at = ?';
        $bindings[] = date('Y-m-d H:i:s');
        $bindings[] = $id;

        $this->conn->affectingStatement(
            "UPDATE puwit_api_keys SET " . implode(', ', $sets) . " WHERE id = ?",
            $bindings
        );

        $updated = $this->conn->selectOne(
            "SELECT id, label, scopes, expires_at, revoked, created_at FROM puwit_api_keys WHERE id = ?",
            [$id]
        );

        return Response::ok($updated);
    }

    public function destroy(Request $request): Response
    {
        $id  = (int)$request->param('id');
        $row = $this->conn->selectOne("SELECT id FROM puwit_api_keys WHERE id = ?", [$id]);

        if ($row === null) {
            return Response::notFound("API key #{$id} not found");
        }

        $this->conn->affectingStatement("DELETE FROM puwit_api_keys WHERE id = ?", [$id]);

        return Response::noContent();
    }
}
