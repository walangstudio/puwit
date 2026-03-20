<?php

declare(strict_types=1);

namespace Puwit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Puwit\Http\Request;
use Puwit\Http\Response;
use Puwit\Middleware\MiddlewareInterface;
use Puwit\Middleware\Pipeline;

class PipelineTest extends TestCase
{
    public function testMiddlewareRunsInOrder(): void
    {
        $log = [];

        $mw1 = new class ($log, 'first') implements MiddlewareInterface {
            public function __construct(private array &$log, private string $name) {}
            public function process(Request $req, callable $next): Response
            {
                $this->log[] = $this->name . ':before';
                $res = $next($req);
                $this->log[] = $this->name . ':after';
                return $res;
            }
        };

        $mw2 = new class ($log, 'second') implements MiddlewareInterface {
            public function __construct(private array &$log, private string $name) {}
            public function process(Request $req, callable $next): Response
            {
                $this->log[] = $this->name . ':before';
                $res = $next($req);
                $this->log[] = $this->name . ':after';
                return $res;
            }
        };

        $pipeline = new Pipeline();
        $pipeline->pipe($mw1)->pipe($mw2);

        $request = new Request('GET', '/', [], [], []);
        $pipeline->run($request, fn($req) => Response::ok([]));

        $this->assertEquals(['first:before', 'second:before', 'second:after', 'first:after'], $log);
    }

    public function testShortCircuit(): void
    {
        $reached = false;

        $mw = new class implements MiddlewareInterface {
            public function process(Request $req, callable $next): Response
            {
                return Response::unauthorized();
            }
        };

        $pipeline = new Pipeline();
        $pipeline->pipe($mw);

        $request  = new Request('GET', '/', [], [], []);
        $response = $pipeline->run($request, function ($req) use (&$reached) {
            $reached = true;
            return Response::ok([]);
        });

        $this->assertFalse($reached);
        $this->assertEquals(401, $response->status());
    }
}
