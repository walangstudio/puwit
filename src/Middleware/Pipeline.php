<?php

declare(strict_types=1);

namespace Puwit\Middleware;

use Puwit\Http\Request;
use Puwit\Http\Response;

class Pipeline
{
    /** @var MiddlewareInterface[] */
    private array $middleware = [];

    public function pipe(MiddlewareInterface $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    public function run(Request $request, callable $handler): Response
    {
        $chain = $handler;

        foreach (array_reverse($this->middleware) as $mw) {
            $next  = $chain;
            $chain = fn(Request $req) => $mw->process($req, $next);
        }

        return $chain($request);
    }
}
