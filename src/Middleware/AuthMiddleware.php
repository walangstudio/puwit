<?php

declare(strict_types=1);

namespace Puwit\Middleware;

use Puwit\Auth\ApiKeyGuard;
use Puwit\Auth\JwtGuard;
use Puwit\Http\Request;
use Puwit\Http\Response;

class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ApiKeyGuard $apiKeyGuard,
        private readonly JwtGuard    $jwtGuard,
    ) {}

    private const PUBLIC_ROUTES = [
        'POST /admin/users/login',
    ];

    public function process(Request $request, callable $next): Response
    {
        if ($request->method() === 'OPTIONS') {
            return $next($request);
        }

        $key = $request->method() . ' ' . $request->path();
        if (in_array($key, self::PUBLIC_ROUTES, true)) {
            return $next($request);
        }

        $token = $request->bearerToken();
        if ($token !== null) {
            $context = $this->jwtGuard->validate($token);
            if ($context !== null) {
                return $next($request->withAttribute('token_context', $context));
            }
            return Response::unauthorized('Invalid or expired JWT');
        }

        $apiKey = $request->apiKey();
        if ($apiKey !== null) {
            $context = $this->apiKeyGuard->validate($apiKey);
            if ($context !== null) {
                return $next($request->withAttribute('token_context', $context));
            }
            return Response::unauthorized('Invalid or revoked API key');
        }

        return Response::unauthorized('Authentication required');
    }
}
