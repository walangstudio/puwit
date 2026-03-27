<?php

declare(strict_types=1);

namespace Puwit\Tests\Integration;

use Puwit\Config\Config;
use Puwit\Database\ConnectionFactory;

class DocsTest extends BaseIntegrationTest
{
    protected function setUp(): void
    {
        Config::reset();
        Config::set('DB_DRIVER',       'sqlite');
        Config::set('DB_PATH',         ':memory:');
        Config::set('PUWIT_ADMIN_KEY', self::ADMIN_KEY);
        Config::set('JWT_SECRET',      self::JWT_SECRET);
        Config::set('APP_DEBUG',       true);
        Config::set('CORS_ORIGINS',    '*');
        Config::set('API_DOCS',        true);

        ConnectionFactory::reset();

        $this->kernel = new \Puwit\Kernel();
    }

    public function testOpenApiJsonReturnsSpec(): void
    {
        $res = $this->request('GET', '/openapi.json');
        $this->assertSame(200, $res['status']);
        $this->assertSame('3.1.0', $res['body']['openapi']);
        $this->assertArrayHasKey('paths', $res['body']);
        $this->assertArrayHasKey('components', $res['body']);
    }

    public function testDocsPageReturnsHtml(): void
    {
        $res = $this->request('GET', '/docs');
        $this->assertSame(200, $res['status']);
        $this->assertStringContainsString('scalar', (string) $res['body']);
    }

    public function testDocsDisabledByDefault(): void
    {
        Config::reset();
        Config::set('DB_DRIVER',       'sqlite');
        Config::set('DB_PATH',         ':memory:');
        Config::set('PUWIT_ADMIN_KEY', self::ADMIN_KEY);
        Config::set('JWT_SECRET',      self::JWT_SECRET);

        ConnectionFactory::reset();

        $kernel = new \Puwit\Kernel();

        $request  = new \Puwit\Http\Request('GET', '/openapi.json', [], [], []);
        $response = $kernel->process($request);
        $this->assertSame(404, $response->status());
    }
}
