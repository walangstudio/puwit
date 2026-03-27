<?php

declare(strict_types=1);

namespace Puwit\Admin;

use Puwit\Crud\CrudRouter;
use Puwit\Database\Connection;
use Puwit\Database\SchemaBuilder;
use Puwit\Http\Request;
use Puwit\Http\Response;
use Puwit\Model\ModelDefinition;
use Puwit\Model\ModelRegistry;
use Puwit\Model\ModelValidator;

class ModelController
{
    public function __construct(
        private readonly Connection      $conn,
        private readonly ModelRegistry   $registry,
        private readonly ModelValidator  $validator,
        private readonly SchemaBuilder   $schema,
        private readonly CrudRouter      $crudRouter,
    ) {}

    public function index(Request $request): Response
    {
        $models = array_values(array_map(
            fn($m) => $m->toArray(),
            $this->registry->all()
        ));
        return Response::ok($models, ['model' => 'model']);
    }

    public function create(Request $request): Response
    {
        $data   = $request->allBody();
        $errors = $this->validator->validate($data);

        if (!empty($errors)) {
            return Response::error('Validation failed', 422, ['errors' => $errors]);
        }

        $name = $data['name'];
        if ($this->registry->find($name) !== null) {
            return Response::error("Model '{$name}' already exists", 409);
        }

        $definition = ModelDefinition::fromArray($data);

        try {
            $this->conn->transaction(function () use ($definition) {
                $this->schema->createTable($definition);
                $this->registry->persist($definition);
            });
        } catch (\Throwable $e) {
            try {
                $this->schema->dropTable($definition);
            } catch (\Throwable) {}

            throw $e;
        }

        $this->crudRouter->register($definition);

        return Response::created($definition->toArray(), ['model' => 'model']);
    }

    public function show(Request $request): Response
    {
        $name = $request->param('name');
        $def  = $this->registry->find($name);

        if ($def === null) {
            return Response::notFound("Model '{$name}' not found");
        }

        return Response::ok($def->toArray(), ['model' => 'model']);
    }

    public function update(Request $request): Response
    {
        $name = $request->param('name');
        $def  = $this->registry->find($name);

        if ($def === null) {
            return Response::notFound("Model '{$name}' not found");
        }

        $body = $request->allBody();

        $merged = [
            'name'      => $def->name,
            'fields'    => isset($body['fields'])
                ? array_map(fn($f) => is_array($f) ? $f : $f->toArray(), $body['fields'])
                : array_map(fn($f) => $f->toArray(), $def->fields),
            'relations' => $body['relations'] ?? $def->relations,
            'public'    => $body['public'] ?? $def->isPublic,
        ];

        $errors = $this->validator->validate($merged);
        if (!empty($errors)) {
            return Response::error('Validation failed', 422, ['errors' => $errors]);
        }

        $updated = ModelDefinition::fromArray($merged);
        $updated = new ModelDefinition(
            name:      $def->name,
            tableName: $def->tableName,
            fields:    $updated->fields,
            relations: $updated->relations,
            isPublic:  (bool)($body['public'] ?? $def->isPublic),
        );

        $alterErrors = $this->schema->alterTable($def, $updated);
        if (!empty($alterErrors)) {
            return Response::error('Validation failed', 422, ['errors' => $alterErrors]);
        }

        $this->registry->update($updated);

        return Response::ok($updated->toArray(), ['model' => 'model']);
    }

    public function destroy(Request $request): Response
    {
        $name = $request->param('name');
        $def  = $this->registry->find($name);

        if ($def === null) {
            return Response::notFound("Model '{$name}' not found");
        }

        $this->registry->delete($name);
        $this->schema->dropTable($def);

        return Response::noContent();
    }
}
