<?php

declare(strict_types=1);

namespace Puwit\Middleware;

use Puwit\Auth\TokenContext;
use Puwit\Http\Request;
use Puwit\Http\Response;

class ScopeMiddleware implements MiddlewareInterface
{
    private const PUBLIC_ROUTES = [
        'POST /admin/users/login',
        'POST /admin/users/logout',
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

        /** @var TokenContext|null $context */
        $context = $request->getAttribute('token_context');

        if ($context === null) {
            return Response::unauthorized('Authentication required');
        }

        $required = $this->requiredScope($request->method(), $request->path());

        if (!$context->hasScope($required)) {
            return Response::forbidden("Scope '{$required}' required");
        }

        return $next($request);
    }

    private function requiredScope(string $method, string $path): string
    {
        if (str_starts_with($path, '/admin')) {
            return 'admin';
        }
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return 'read';
        }
        return 'write';
    }
}
