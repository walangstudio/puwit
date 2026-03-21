<?php

declare(strict_types=1);

namespace Puwit\Middleware;

use Puwit\Config\Config;
use Puwit\Http\Request;
use Puwit\Http\Response;

class CorsMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $origins = Config::get('CORS_ORIGINS', '*');

        if ($request->method() === 'OPTIONS') {
            return $this->preflight($origins);
        }

        $response = $next($request);

        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $origins)
            ->withHeader('Access-Control-Expose-Headers', 'X-Total-Count');

        if ($origins !== '*') {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }

    private function preflight(string $origins): Response
    {
        return Response::json(null, 204)
            ->withHeader('Access-Control-Allow-Origin', $origins)
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-API-Key')
            ->withHeader('Access-Control-Max-Age', '86400');
    }
}
