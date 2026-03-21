<?php

declare(strict_types=1);

namespace Puwit\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Puwit\Config\Config;
use Puwit\Database\ConnectionFactory;
use Puwit\Http\Request;
use Puwit\Kernel;

abstract class BaseIntegrationTest extends TestCase
{
    protected Kernel $kernel;

    protected const ADMIN_KEY  = 'test-bootstrap-admin-key-for-ci';
    // HS256 requires minimum 32 bytes (256 bits)
    protected const JWT_SECRET = 'test-jwt-secret-exactly-32-bytes';

    protected function setUp(): void
    {
        Config::reset();
        Config::set('DB_DRIVER',        'sqlite');
        Config::set('DB_PATH',          ':memory:');
        Config::set('PUWIT_ADMIN_KEY',  self::ADMIN_KEY);
        Config::set('JWT_SECRET',       self::JWT_SECRET);
        Config::set('APP_DEBUG',        true);
        Config::set('CORS_ORIGINS',     '*');

        ConnectionFactory::reset();

        $this->kernel = new Kernel();
    }

    /** @return array{status:int, body:mixed} */
    protected function request(
        string $method,
        string $path,
        array  $body    = [],
        array  $headers = [],
        array  $query   = [],
    ): array {
        $request  = new Request($method, $path, $headers, $query, $body);
        $response = $this->kernel->process($request);
        return ['status' => $response->status(), 'body' => $response->body()];
    }

    /** @return array{status:int, body:mixed} */
    protected function admin(string $method, string $path, array $body = [], array $query = []): array
    {
        return $this->request($method, $path, $body, ['x-api-key' => self::ADMIN_KEY], $query);
    }

    /** @return array{status:int, body:mixed} */
    protected function bearer(string $method, string $path, string $token, array $body = [], array $query = []): array
    {
        return $this->request($method, $path, $body, ['authorization' => 'Bearer ' . $token], $query);
    }

    /** @return array{status:int, body:mixed} */
    protected function apiKey(string $method, string $path, string $key, array $body = [], array $query = []): array
    {
        return $this->request($method, $path, $body, ['x-api-key' => $key], $query);
    }
}
