<?php

declare(strict_types=1);

namespace Puwit\E2E;

use PHPUnit\Framework\TestCase;

abstract class BaseE2ETest extends TestCase
{
    private static string $baseUrl  = '';
    private static string $adminKey = '';

    protected static function baseUrl(): string  { return self::$baseUrl; }
    protected static function adminKey(): string { return self::$adminKey; }

    public static function setUpBeforeClass(): void
    {
        $env = __DIR__ . '/.env.e2e';
        if (file_exists($env)) {
            foreach (file($env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if (str_starts_with(trim($line), '#')) {
                    continue;
                }
                [$k, $v] = explode('=', $line, 2) + [1 => ''];
                $_ENV[trim($k)] = trim($v);
            }
        }

        self::$baseUrl  = rtrim($_ENV['E2E_BASE_URL']  ?? '', '/');
        self::$adminKey = $_ENV['E2E_ADMIN_KEY'] ?? '';

        if (empty(self::$baseUrl)) {
            self::markTestSkipped('E2E_BASE_URL is not set. Copy e2e/.env.e2e.example to e2e/.env.e2e and configure it.');
        }
    }

    // -------------------------------------------------------------------------
    // HTTP client
    // -------------------------------------------------------------------------

    /** @return array{status:int, body:mixed, raw:string} */
    protected function http(
        string $method,
        string $path,
        array  $body    = [],
        array  $headers = [],
        array  $query   = [],
    ): array {
        $url = self::$baseUrl . $path;
        if ($query) {
            $url .= '?' . http_build_query($query);
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST,  $method);
        curl_setopt($ch, CURLOPT_TIMEOUT,        10);

        $reqHeaders = ['Content-Type: application/json', 'Accept: application/json'];
        foreach ($headers as $name => $value) {
            $reqHeaders[] = "{$name}: {$value}";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $reqHeaders);

        if ($body !== [] || in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $raw    = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->fail("curl error on {$method} {$url}: {$error}");
        }

        $decoded = json_decode($raw, true);
        return ['status' => $status, 'body' => $decoded, 'raw' => $raw];
    }

    /** @return array{status:int, body:mixed, raw:string} */
    protected function admin(string $method, string $path, array $body = [], array $query = []): array
    {
        return $this->http($method, $path, $body, ['X-API-Key' => self::$adminKey], $query);
    }

    /** @return array{status:int, body:mixed, raw:string} */
    protected function apiKey(string $method, string $path, string $key, array $body = [], array $query = []): array
    {
        return $this->http($method, $path, $body, ['X-API-Key' => $key], $query);
    }

    /** @return array{status:int, body:mixed, raw:string} */
    protected function bearer(string $method, string $path, string $token, array $body = [], array $query = []): array
    {
        return $this->http($method, $path, $body, ['Authorization' => 'Bearer ' . $token], $query);
    }

    // -------------------------------------------------------------------------
    // Model helpers
    // -------------------------------------------------------------------------

    /** Creates a model and registers it for cleanup. */
    protected function createModel(string $name, array $fields, bool $public = false): void
    {
        $payload = ['name' => $name, 'fields' => $fields];
        if ($public) {
            $payload['public'] = true;
        }
        $res = $this->admin('POST', '/admin/models', $payload);
        $this->assertContains($res['status'], [201, 409], "Could not create model '{$name}'");
        $this->modelsToDelete[] = $name;
    }

    protected array $modelsToDelete = [];

    protected function tearDown(): void
    {
        foreach (array_reverse($this->modelsToDelete) as $name) {
            $this->admin('DELETE', "/admin/models/{$name}");
        }
        $this->modelsToDelete = [];
    }
}
