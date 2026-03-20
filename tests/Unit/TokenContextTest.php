<?php

declare(strict_types=1);

namespace Puwit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Puwit\Auth\TokenContext;

class TokenContextTest extends TestCase
{
    public function testAdminImpliesAll(): void
    {
        $ctx = new TokenContext('api_key', 1, ['admin']);

        $this->assertTrue($ctx->hasScope('admin'));
        $this->assertTrue($ctx->hasScope('write'));
        $this->assertTrue($ctx->hasScope('read'));
    }

    public function testWriteImpliesRead(): void
    {
        $ctx = new TokenContext('jwt', 1, ['write']);

        $this->assertTrue($ctx->hasScope('write'));
        $this->assertTrue($ctx->hasScope('read'));
        $this->assertFalse($ctx->hasScope('admin'));
    }

    public function testReadOnlyScope(): void
    {
        $ctx = new TokenContext('api_key', 1, ['read']);

        $this->assertTrue($ctx->hasScope('read'));
        $this->assertFalse($ctx->hasScope('write'));
        $this->assertFalse($ctx->hasScope('admin'));
    }
}
