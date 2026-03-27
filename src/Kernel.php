<?php

declare(strict_types=1);

namespace Puwit;

use Puwit\Admin\ApiKeyController;
use Puwit\Admin\DocsController;
use Puwit\Admin\ModelController;
use Puwit\Admin\UserController;
use Puwit\Auth\ApiKeyGuard;
use Puwit\Auth\JwtGuard;
use Puwit\Config\Config;
use Puwit\Crud\CrudHandler;
use Puwit\Crud\CrudRouter;
use Puwit\Database\ConnectionFactory;
use Puwit\Database\Migrations\SystemMigration;
use Puwit\Database\SchemaBuilder;
use Puwit\Http\OpenApiGenerator;
use Puwit\Http\Request;
use Puwit\Http\Response;
use Puwit\Http\Router;
use Puwit\Middleware\AuthMiddleware;
use Puwit\Middleware\CorsMiddleware;
use Puwit\Middleware\Pipeline;
use Puwit\Middleware\ScopeMiddleware;
use Puwit\Model\ModelRegistry;
use Puwit\Model\ModelValidator;
use Puwit\Model\RelationResolver;

class Kernel
{
    private Router   $router;
    private Pipeline $pipeline;

    public function __construct()
    {
        Config::load();

        $conn = ConnectionFactory::make();

        (new SystemMigration($conn))->run();

        $apiKeyGuard = new ApiKeyGuard($conn);
        $jwtGuard    = new JwtGuard($conn);
        $registry    = new ModelRegistry($conn);
        $resolver    = new RelationResolver($conn, $registry);
        $schema      = new SchemaBuilder($conn);
        $validator   = new ModelValidator();
        $handler     = new CrudHandler($conn, $resolver);

        $this->router = new Router();

        $crudRouter = new CrudRouter($this->router, $registry, $handler);
        $crudRouter->boot();

        $modelCtrl  = new ModelController($conn, $registry, $validator, $schema, $crudRouter);
        $apiKeyCtrl = new ApiKeyController($conn);
        $userCtrl   = new UserController($conn, $jwtGuard);

        $this->registerAdminRoutes($modelCtrl, $apiKeyCtrl, $userCtrl);

        if (Config::bool('API_DOCS', false)) {
            $generator = new OpenApiGenerator($registry);
            $docs = new DocsController($generator);
            $this->router->add('GET', '/openapi.json', [$docs, 'openapi']);
            $this->router->add('GET', '/docs',          [$docs, 'docs']);
        }

        $this->pipeline = new Pipeline();
        $this->pipeline
            ->pipe(new CorsMiddleware())
            ->pipe(new AuthMiddleware($apiKeyGuard, $jwtGuard))
            ->pipe(new ScopeMiddleware());
    }

    public function handle(): void
    {
        $this->process(Request::fromGlobals())->send();
    }

    public function process(Request $request): Response
    {
        try {
            return $this->dispatch($request);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    public function pushMiddleware(\Puwit\Middleware\MiddlewareInterface $middleware): void
    {
        $this->pipeline->pipe($middleware);
    }

    private function dispatch(Request $request): Response
    {
        if ($request->method() === 'OPTIONS') {
            return $this->pipeline->run($request, fn($req) => Response::json(null, 204));
        }

        $match = $this->router->dispatch($request);

        if ($match === null) {
            return Response::notFound('Route not found');
        }

        if (isset($match['method_not_allowed'])) {
            return Response::error('Method not allowed', 405);
        }

        $request = $request->withParams($match['params']);

        return $this->pipeline->run($request, $match['handler']);
    }

    private function handleException(\Throwable $e): Response
    {
        if ($e instanceof \InvalidArgumentException) {
            return Response::error($e->getMessage(), 400);
        }

        if (Config::bool('APP_DEBUG', false)) {
            return Response::error($e->getMessage(), 500, [
                'exception' => get_class($e),
                'trace'     => array_slice(explode("\n", $e->getTraceAsString()), 0, 10),
            ]);
        }

        error_log('[PUWIT] ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

        return Response::error('Internal server error', 500);
    }

    private function registerAdminRoutes(
        ModelController $modelCtrl,
        ApiKeyController $apiKeyCtrl,
        UserController $userCtrl,
    ): void {
        $r = $this->router;

        $r->add('GET',    '/admin/models',        [$modelCtrl, 'index']);
        $r->add('POST',   '/admin/models',        [$modelCtrl, 'create']);
        $r->add('GET',    '/admin/models/{name}', [$modelCtrl, 'show']);
        $r->add('PATCH',  '/admin/models/{name}', [$modelCtrl, 'update']);
        $r->add('DELETE', '/admin/models/{name}', [$modelCtrl, 'destroy']);

        $r->add('GET',    '/admin/keys',     [$apiKeyCtrl, 'index']);
        $r->add('POST',   '/admin/keys',     [$apiKeyCtrl, 'create']);
        $r->add('GET',    '/admin/keys/{id}', [$apiKeyCtrl, 'show']);
        $r->add('PATCH',  '/admin/keys/{id}', [$apiKeyCtrl, 'update']);
        $r->add('DELETE', '/admin/keys/{id}', [$apiKeyCtrl, 'destroy']);

        $r->add('GET',    '/admin/users',          [$userCtrl, 'index']);
        $r->add('POST',   '/admin/users',          [$userCtrl, 'create']);
        $r->add('GET',    '/admin/users/{id}',     [$userCtrl, 'show']);
        $r->add('PATCH',  '/admin/users/{id}',     [$userCtrl, 'update']);
        $r->add('DELETE', '/admin/users/{id}',     [$userCtrl, 'destroy']);
        $r->add('POST',   '/admin/users/login',    [$userCtrl, 'login']);
        $r->add('POST',   '/admin/users/logout',   [$userCtrl, 'logout']);
    }
}
