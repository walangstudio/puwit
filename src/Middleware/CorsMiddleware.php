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
        $configured = Config::get('CORS_ORIGINS', '*');
        $origin     = $this->resolveOrigin($request, $configured);

        if ($request->method() === 'OPTIONS') {
            return $this->preflight($origin, $configured);
        }

        $response = $next($request);

        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Access-Control-Expose-Headers', 'X-Total-Count');

        if ($origin !== '*') {
            $response = $response
                ->withHeader('Access-Control-Allow-Credentials', 'true')
                ->withHeader('Vary', 'Origin');
        }

        return $response;
    }

    private function resolveOrigin(Request $request, string $configured): string
    {
        if ($configured === '*') {
            return '*';
        }

        $allowed       = array_map('trim', explode(',', $configured));
        $requestOrigin = $request->header('origin', '');

        if ($requestOrigin !== '' && in_array($requestOrigin, $allowed, true)) {
            return $requestOrigin;
        }

        return $allowed[0];
    }

    private function preflight(string $origin, string $configured): Response
    {
        $response = Response::json(null, 204)
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-API-Key')
            ->withHeader('Access-Control-Max-Age', '86400');

        if ($configured !== '*') {
            $response = $response
                ->withHeader('Access-Control-Allow-Credentials', 'true')
                ->withHeader('Vary', 'Origin');
        }

        return $response;
    }
}
