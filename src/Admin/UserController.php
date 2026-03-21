<?php

declare(strict_types=1);

namespace Puwit\Admin;

use Puwit\Auth\JwtGuard;
use Puwit\Database\Connection;
use Puwit\Http\Request;
use Puwit\Http\Response;

class UserController
{
    public function __construct(
        private readonly Connection $conn,
        private readonly JwtGuard   $jwtGuard,
    ) {}

    public function index(Request $request): Response
    {
        $rows = $this->conn->select(
            "SELECT id, username, scopes, active, created_at FROM puwit_users ORDER BY id DESC"
        );
        return Response::ok($rows);
    }

    private const VALID_SCOPES = ['read', 'write', 'admin'];

    public function create(Request $request): Response
    {
        $username = trim($request->body('username', ''));
        $password = $request->body('password', '');
        $scopes   = $request->body('scopes', ['read']);

        if (empty($username)) {
            return Response::error('username is required', 422);
        }
        if (!preg_match('/^[a-zA-Z0-9_\-\.]{1,100}$/', $username)) {
            return Response::error('username may only contain letters, digits, underscores, hyphens, and dots (max 100 chars)', 422);
        }
        if (strlen($password) < 8) {
            return Response::error('password must be at least 8 characters', 422);
        }
        if (!is_array($scopes)) {
            return Response::error('scopes must be an array', 422);
        }
        foreach ($scopes as $scope) {
            if (!in_array($scope, self::VALID_SCOPES, true)) {
                return Response::error("Invalid scope '{$scope}'", 422);
            }
        }

        $exists = $this->conn->selectOne(
            "SELECT id FROM puwit_users WHERE username = ?",
            [$username]
        );
        if ($exists !== null) {
            return Response::error("Username '{$username}' already taken", 409);
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $this->conn->insert(
            "INSERT INTO puwit_users (username, password_hash, scopes) VALUES (?, ?, ?)",
            [$username, $hash, json_encode($scopes)]
        );

        $row = $this->conn->selectOne(
            "SELECT id, username, scopes, active, created_at FROM puwit_users WHERE username = ?",
            [$username]
        );

        return Response::created($row);
    }

    public function show(Request $request): Response
    {
        $id  = (int)$request->param('id');
        $row = $this->conn->selectOne(
            "SELECT id, username, scopes, active, created_at FROM puwit_users WHERE id = ?",
            [$id]
        );

        if ($row === null) {
            return Response::notFound("User #{$id} not found");
        }

        return Response::ok($row);
    }

    public function update(Request $request): Response
    {
        $id  = (int)$request->param('id');
        $row = $this->conn->selectOne("SELECT id FROM puwit_users WHERE id = ?", [$id]);

        if ($row === null) {
            return Response::notFound("User #{$id} not found");
        }

        $sets     = [];
        $bindings = [];

        if ($request->body('password') !== null) {
            $pass = $request->body('password');
            if (strlen($pass) < 8) {
                return Response::error('password must be at least 8 characters', 422);
            }
            $sets[]     = 'password_hash = ?';
            $bindings[] = password_hash($pass, PASSWORD_BCRYPT);
        }
        if ($request->body('scopes') !== null) {
            $scopes = $request->body('scopes');
            if (!is_array($scopes)) {
                return Response::error('scopes must be an array', 422);
            }
            foreach ($scopes as $scope) {
                if (!in_array($scope, self::VALID_SCOPES, true)) {
                    return Response::error("Invalid scope '{$scope}'", 422);
                }
            }
            $sets[]     = 'scopes = ?';
            $bindings[] = json_encode($scopes);
        }
        if ($request->body('active') !== null) {
            $sets[]     = 'active = ?';
            $bindings[] = (int)$request->body('active');
        }

        if (empty($sets)) {
            return Response::error('No fields to update', 422);
        }

        $sets[]     = 'updated_at = ?';
        $bindings[] = date('Y-m-d H:i:s');
        $bindings[] = $id;

        $this->conn->affectingStatement(
            "UPDATE puwit_users SET " . implode(', ', $sets) . " WHERE id = ?",
            $bindings
        );

        $updated = $this->conn->selectOne(
            "SELECT id, username, scopes, active, created_at FROM puwit_users WHERE id = ?",
            [$id]
        );

        return Response::ok($updated);
    }

    public function destroy(Request $request): Response
    {
        $id  = (int)$request->param('id');
        $row = $this->conn->selectOne("SELECT id FROM puwit_users WHERE id = ?", [$id]);

        if ($row === null) {
            return Response::notFound("User #{$id} not found");
        }

        $this->conn->affectingStatement("DELETE FROM puwit_users WHERE id = ?", [$id]);

        return Response::noContent();
    }

    public function login(Request $request): Response
    {
        $username = trim($request->body('username', ''));
        $password = $request->body('password', '');

        if (empty($username) || empty($password)) {
            return Response::error('username and password required', 422);
        }

        $user = $this->conn->selectOne(
            "SELECT id, password_hash, scopes, active FROM puwit_users WHERE username = ?",
            [$username]
        );

        $hash = $user['password_hash'] ?? '$2y$10$invalidsaltpaddingtopreventimenumeration0000000000000';
        if ($user === null || !password_verify($password, $hash)) {
            return Response::unauthorized('Invalid credentials');
        }

        if ((int)$user['active'] !== 1) {
            return Response::forbidden('Account disabled');
        }

        $scopes = json_decode($user['scopes'], true) ?? [];
        $token  = $this->jwtGuard->issue((int)$user['id'], $scopes);

        return Response::ok(['token' => $token, 'type' => 'Bearer']);
    }

    public function logout(Request $request): Response
    {
        $token = $request->bearerToken();

        if ($token !== null) {
            $this->jwtGuard->blocklist($token);
        }

        return Response::ok(['message' => 'Logged out']);
    }
}
