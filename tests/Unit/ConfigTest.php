<?php

declare(strict_types=1);

namespace Puwit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Puwit\Config\Config;

class ConfigTest extends TestCase
{
    public function testFalsyEnvValueNotSwappedForDefault(): void
    {
        Config::set('TEST_FALSY_ZERO', '0');
        $this->assertSame('0', Config::get('TEST_FALSY_ZERO', 'default'));
    }

    public function testEmptyStringEnvValueNotSwappedForDefault(): void
    {
        Config::set('TEST_FALSY_EMPTY', '');
        $this->assertSame('', Config::get('TEST_FALSY_EMPTY', 'default'));
    }

    public function testMissingKeyReturnsDefault(): void
    {
        $this->assertSame('fallback', Config::get('TEST_DEFINITELY_NOT_SET_' . mt_rand(), 'fallback'));
    }
}
