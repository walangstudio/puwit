<?php

declare(strict_types=1);

namespace Puwit\Crud;

use Puwit\Http\Request;
use Puwit\Http\Router;
use Puwit\Model\ModelDefinition;
use Puwit\Model\ModelRegistry;

class CrudRouter
{
    public function __construct(
        private readonly Router      $router,
        private readonly ModelRegistry $registry,
        private readonly CrudHandler $handler,
    ) {}

    public function boot(): void
    {
        foreach ($this->registry->all() as $model) {
            $this->register($model);
        }
    }

    public function register(ModelDefinition $model): void
    {
        $name     = $model->name;
        $handler  = $this->handler;
        $registry = $this->registry;

        $resolve = function () use ($registry, $name): ModelDefinition {
            $def = $registry->find($name);
            if ($def === null) {
                throw new \RuntimeException("Model '{$name}' not found");
            }
            return $def;
        };

        $this->router->add('GET',    "/api/{$name}",       fn(Request $req) => $handler->index($req, $resolve()));
        $this->router->add('POST',   "/api/{$name}",       fn(Request $req) => $handler->create($req, $resolve()));
        $this->router->add('GET',    "/api/{$name}/{id}",  fn(Request $req) => $handler->show($req, $resolve(), (int)$req->param('id')));
        $this->router->add('PUT',    "/api/{$name}/{id}",  fn(Request $req) => $handler->replace($req, $resolve(), (int)$req->param('id')));
        $this->router->add('PATCH',  "/api/{$name}/{id}",  fn(Request $req) => $handler->partialUpdate($req, $resolve(), (int)$req->param('id')));
        $this->router->add('DELETE', "/api/{$name}/{id}",  fn(Request $req) => $handler->destroy($req, $resolve(), (int)$req->param('id')));
    }
}
