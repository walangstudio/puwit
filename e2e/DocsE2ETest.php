<?php

declare(strict_types=1);

namespace Puwit\E2E;

class DocsE2ETest extends BaseE2ETest
{
    private bool $docsEnabled = false;

    protected function setUp(): void
    {
        $probe = $this->http('GET', '/openapi.json');
        // 404 means API_DOCS=false — skip rather than fail
        if ($probe['status'] === 404) {
            $this->markTestSkipped('API_DOCS is not enabled on this server. Set API_DOCS=true in .env to run docs tests.');
        }
        $this->docsEnabled = true;
    }

    public function testOpenApiJsonReturnsValidSpec(): void
    {
        $res = $this->http('GET', '/openapi.json');
        $this->assertSame(200, $res['status']);

        $spec = $res['body'];
        $this->assertSame('3.1.0', $spec['openapi']);
        $this->assertArrayHasKey('info',  $spec);
        $this->assertArrayHasKey('paths', $spec);
        $this->assertArrayHasKey('components', $spec);
    }

    public function testOpenApiSpecIncludesAdminPaths(): void
    {
        $res  = $this->http('GET', '/openapi.json');
        $paths = array_keys($res['body']['paths']);

        $this->assertContains('/admin/models',      $paths);
        $this->assertContains('/admin/keys',         $paths);
        $this->assertContains('/admin/users/login',  $paths);
    }

    public function testOpenApiSpecIncludesDynamicModelPaths(): void
    {
        $this->createModel('e2e_docs_item', [
            ['name' => 'title', 'type' => 'string', 'nullable' => false],
        ]);

        $res   = $this->http('GET', '/openapi.json');
        $paths = array_keys($res['body']['paths']);

        $this->assertContains('/api/e2e_docs_item',     $paths);
        $this->assertContains('/api/e2e_docs_item/{id}', $paths);
    }

    public function testDocsPageReturnsHtml(): void
    {
        $ch = curl_init(self::baseUrl() . '/docs');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $raw    = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->assertSame(200, $status);
        $this->assertStringContainsString('<!DOCTYPE html>',   $raw);
        $this->assertStringContainsString('/openapi.json',     $raw);
        $this->assertStringContainsString('@scalar/api-reference', $raw);
    }

    public function testOpenApiJsonRequiresNoAuth(): void
    {
        $res = $this->http('GET', '/openapi.json');
        $this->assertSame(200, $res['status']);
    }
}
