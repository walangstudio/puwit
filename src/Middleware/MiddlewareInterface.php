<?php

declare(strict_types=1);

namespace Puwit\Middleware;

use Puwit\Http\Request;
use Puwit\Http\Response;

interface MiddlewareInterface
{
    public function process(Request $request, callable $next): Response;
}
