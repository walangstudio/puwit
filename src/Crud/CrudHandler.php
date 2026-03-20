<?php

declare(strict_types=1);

namespace Puwit\Crud;

use Puwit\Database\Connection;
use Puwit\Database\QueryBuilder;
use Puwit\Http\Request;
use Puwit\Http\Response;
use Puwit\Model\ModelDefinition;
use Puwit\Model\RelationResolver;

class CrudHandler
{
    public function __construct(
        private readonly Connection       $conn,
        private readonly RelationResolver $resolver,
    ) {}

    public function index(Request $request, ModelDefinition $model): Response
    {
        $page    = max(1, (int)$request->query('page', 1));
        $perPage = min(100, max(1, (int)$request->query('per_page', 20)));
        $sort    = $request->query('sort', 'id');
        $order   = $request->query('order', 'asc');

        $qb = (new QueryBuilder($this->conn))->table($model->tableName);

        $allowedCols = array_merge(
            ['id', 'created_at', 'updated_at'],
            array_map(fn($f) => $f->name, $model->fields)
        );

        $filters = $request->query('filter', []);
        if (is_array($filters)) {
            foreach ($filters as $col => $value) {
                if (in_array($col, $allowedCols, true)) {
                    $qb = $qb->where($col, $value);
                }
            }
        }

        if (!in_array($sort, $allowedCols, true)) {
            $sort = 'id';
        }

        $total   = $qb->count();
        $rows    = $qb->orderBy($sort, $order)->limit($perPage)->offset(($page - 1) * $perPage)->get();

        $withs = $this->parseWith($request);
        if (!empty($withs)) {
            $rows = $this->resolver->resolve($model, $rows, $withs);
        }

        return Response::ok($rows, [
            'model'    => $model->name,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
        ]);
    }

    public function create(Request $request, ModelDefinition $model): Response
    {
        $data   = $this->filterFields($request->allBody(), $model);
        $errors = $this->validateInput($data, $model, false);

        if (!empty($errors)) {
            return Response::error('Validation failed', 422, ['errors' => $errors]);
        }

        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        $id  = (new QueryBuilder($this->conn))->table($model->tableName)->insert($data);
        $row = (new QueryBuilder($this->conn))->table($model->tableName)->find((int)$id);

        return Response::created($row, ['model' => $model->name]);
    }

    public function show(Request $request, ModelDefinition $model, int $id): Response
    {
        $row = (new QueryBuilder($this->conn))->table($model->tableName)->find($id);

        if ($row === null) {
            return Response::notFound("{$model->name} #{$id} not found");
        }

        $withs = $this->parseWith($request);
        if (!empty($withs)) {
            $rows = $this->resolver->resolve($model, [$row], $withs);
            $row  = $rows[0];
        }

        return Response::ok($row, ['model' => $model->name]);
    }

    public function replace(Request $request, ModelDefinition $model, int $id): Response
    {
        $existing = (new QueryBuilder($this->conn))->table($model->tableName)->find($id);
        if ($existing === null) {
            return Response::notFound("{$model->name} #{$id} not found");
        }

        $data   = $this->filterFields($request->allBody(), $model);
        $errors = $this->validateInput($data, $model, false);

        if (!empty($errors)) {
            return Response::error('Validation failed', 422, ['errors' => $errors]);
        }

        $data['updated_at'] = date('Y-m-d H:i:s');

        (new QueryBuilder($this->conn))->table($model->tableName)->where('id', $id)->update($data);
        $row = (new QueryBuilder($this->conn))->table($model->tableName)->find($id);

        return Response::ok($row, ['model' => $model->name]);
    }

    public function partialUpdate(Request $request, ModelDefinition $model, int $id): Response
    {
        $existing = (new QueryBuilder($this->conn))->table($model->tableName)->find($id);
        if ($existing === null) {
            return Response::notFound("{$model->name} #{$id} not found");
        }

        $data   = $this->filterFields($request->allBody(), $model);
        $errors = $this->validateInput($data, $model, true);

        if (!empty($errors)) {
            return Response::error('Validation failed', 422, ['errors' => $errors]);
        }

        $data['updated_at'] = date('Y-m-d H:i:s');

        (new QueryBuilder($this->conn))->table($model->tableName)->where('id', $id)->update($data);
        $row = (new QueryBuilder($this->conn))->table($model->tableName)->find($id);

        return Response::ok($row, ['model' => $model->name]);
    }

    public function destroy(Request $request, ModelDefinition $model, int $id): Response
    {
        $existing = (new QueryBuilder($this->conn))->table($model->tableName)->find($id);
        if ($existing === null) {
            return Response::notFound("{$model->name} #{$id} not found");
        }

        try {
            (new QueryBuilder($this->conn))->table($model->tableName)->where('id', $id)->delete();
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'foreign key') || str_contains($e->getMessage(), 'FOREIGN KEY')) {
                return Response::error('Cannot delete: dependent records exist', 409);
            }
            throw $e;
        }

        return Response::noContent();
    }

    private function filterFields(array $body, ModelDefinition $model): array
    {
        $allowed = array_map(fn($f) => $f->name, $model->fields);
        return array_filter(
            $body,
            fn($key) => in_array($key, $allowed, true),
            ARRAY_FILTER_USE_KEY
        );
    }

    private function validateInput(array $data, ModelDefinition $model, bool $partial): array
    {
        $errors = [];

        foreach ($model->fields as $field) {
            if ($partial && !array_key_exists($field->name, $data)) {
                continue;
            }
            if (!$field->nullable && $field->default === null && !array_key_exists($field->name, $data)) {
                $errors[] = "{$field->name} is required";
            }
        }

        return $errors;
    }

    private function parseWith(Request $request): array
    {
        $with = $request->query('with', '');
        if ($with === '' || $with === null) {
            return [];
        }
        return array_filter(explode(',', $with));
    }
}
