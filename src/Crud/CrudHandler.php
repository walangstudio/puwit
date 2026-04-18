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
        private Connection       $conn,
        private RelationResolver $resolver,
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

        $data = $this->coerceTypes($data, $model);
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

        $data = $this->coerceTypes($data, $model);

        // PUT = full replacement: null out any nullable fields not present in the body
        foreach ($model->fields as $field) {
            if (!array_key_exists($field->name, $data) && $field->nullable) {
                $data[$field->name] = null;
            }
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

        $data = $this->coerceTypes($data, $model);
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

    /**
     * PDO binds PHP false as '' (empty string) instead of 0.
     * Coerce boolean and integer fields to their proper scalar types before binding.
     */
    private function coerceTypes(array $data, ModelDefinition $model): array
    {
        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }
            $field = $model->field($key);
            if ($field === null) {
                continue;
            }
            $data[$key] = match ($field->type) {
                'boolean'  => (int)(bool)$value,
                'int', 'relation' => (int)$value,
                'float'    => (float)$value,
                default    => $value,
            };
        }
        return $data;
    }

    private function validateInput(array $data, ModelDefinition $model, bool $partial): array
    {
        $errors = [];

        foreach ($model->fields as $field) {
            if ($partial && !array_key_exists($field->name, $data)) {
                continue;
            }
            if (!array_key_exists($field->name, $data)) {
                if (!$field->nullable && $field->default === null) {
                    $errors[] = "{$field->name} is required";
                }
                continue;
            }

            $value = $data[$field->name];
            if ($value === null) {
                if (!$field->nullable) {
                    $errors[] = "{$field->name} cannot be null";
                }
                continue;
            }

            $typeError = match ($field->type) {
                'int'      => filter_var($value, FILTER_VALIDATE_INT) === false
                                ? "{$field->name} must be an integer"
                                : null,
                'float'    => !is_numeric($value)
                                ? "{$field->name} must be numeric"
                                : null,
                'boolean'  => !is_bool($value) && !in_array($value, [0, 1, '0', '1'], true)
                                ? "{$field->name} must be a boolean"
                                : null,
                'relation' => filter_var($value, FILTER_VALIDATE_INT) === false || (int)$value < 1
                                ? "{$field->name} must be a positive integer (foreign key)"
                                : null,
                default    => null,
            };

            if ($typeError !== null) {
                $errors[] = $typeError;
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
