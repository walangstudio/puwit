<?php

declare(strict_types=1);

namespace Puwit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Puwit\Http\Request;
use Puwit\Http\Router;

class RouterTest extends TestCase
{
    private function makeRequest(string $method, string $path): Request
    {
        return new Request($method, $path, [], [], []);
    }

    public function testStaticRoute(): void
    {
        $router = new Router();
        $router->add('GET', '/admin/models', fn() => 'models');

        $match = $router->dispatch($this->makeRequest('GET', '/admin/models'));

        $this->assertNotNull($match);
        $this->assertEmpty($match['params']);
    }

    public function testDynamicRoute(): void
    {
        $router = new Router();
        $router->add('GET', '/api/{model}/{id}', fn() => 'show');

        $match = $router->dispatch($this->makeRequest('GET', '/api/product/42'));

        $this->assertNotNull($match);
        $this->assertEquals(['model' => 'product', 'id' => '42'], $match['params']);
    }

    public function testStaticBeatsParam(): void
    {
        $router = new Router();
        $router->add('POST', '/admin/users/login', fn() => 'login');
        $router->add('GET',  '/admin/users/{id}',  fn() => 'show');

        $match = $router->dispatch($this->makeRequest('POST', '/admin/users/login'));

        $this->assertNotNull($match);
        $this->assertEmpty($match['params']);
    }

    public function testNotFound(): void
    {
        $router = new Router();
        $match  = $router->dispatch($this->makeRequest('GET', '/missing'));

        $this->assertNull($match);
    }

    public function testMethodNotAllowed(): void
    {
        $router = new Router();
        $router->add('GET', '/foo', fn() => 'bar');

        $match = $router->dispatch($this->makeRequest('DELETE', '/foo'));

        $this->assertArrayHasKey('method_not_allowed', $match);
    }

    public function testOptionsAlwaysAllowed(): void
    {
        $router = new Router();
        $router->add('GET', '/foo', fn() => 'bar');

        $match = $router->dispatch($this->makeRequest('OPTIONS', '/foo'));

        $this->assertNotNull($match);
        $this->assertNotNull($match['handler']);
    }
}
