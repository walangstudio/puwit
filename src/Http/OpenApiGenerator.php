<?php

declare(strict_types=1);

namespace Puwit\Http;

use Puwit\Model\ModelRegistry;

class OpenApiGenerator
{
    public function __construct(private ModelRegistry $registry) {}

    public function generate(string $serverUrl = ''): array
    {
        $spec = [
            'openapi' => '3.1.0',
            'info'    => ['title' => 'PUWIT API', 'version' => '0.3.0'],
            'servers' => [['url' => $serverUrl ?: '/']],
            'components' => [
                'securitySchemes' => [
                    'ApiKey' => [
                        'type' => 'apiKey',
                        'in'   => 'header',
                        'name' => 'X-API-Key',
                    ],
                    'BearerAuth' => [
                        'type'         => 'http',
                        'scheme'       => 'bearer',
                        'bearerFormat' => 'JWT',
                    ],
                ],
                'schemas' => [],
            ],
            'paths' => [],
        ];

        $security = [['ApiKey' => []], ['BearerAuth' => []]];

        $spec['paths'] = array_merge($spec['paths'], $this->adminPaths($security));

        foreach ($this->registry->all() as $model) {
            $spec['components']['schemas'][$model->name] = $this->modelSchema($model);

            $spec['paths']['/api/' . $model->name] = [
                'get' => [
                    'summary'    => "List {$model->name}",
                    'tags'       => [$model->name],
                    'security'   => $security,
                    'parameters' => $this->listParameters(),
                    'responses'  => ['200' => ['description' => 'OK']],
                ],
                'post' => [
                    'summary'     => "Create {$model->name}",
                    'tags'        => [$model->name],
                    'security'    => $security,
                    'requestBody' => $this->requestBody($model->name),
                    'responses'   => ['201' => ['description' => 'Created']],
                ],
            ];

            $spec['paths']['/api/' . $model->name . '/{id}'] = [
                'get' => [
                    'summary'    => "Get {$model->name}",
                    'tags'       => [$model->name],
                    'security'   => $security,
                    'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'responses'  => ['200' => ['description' => 'OK'], '404' => ['description' => 'Not found']],
                ],
                'put' => [
                    'summary'     => "Replace {$model->name}",
                    'tags'        => [$model->name],
                    'security'    => $security,
                    'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'requestBody' => $this->requestBody($model->name),
                    'responses'   => ['200' => ['description' => 'OK']],
                ],
                'patch' => [
                    'summary'     => "Update {$model->name}",
                    'tags'        => [$model->name],
                    'security'    => $security,
                    'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'requestBody' => $this->requestBody($model->name),
                    'responses'   => ['200' => ['description' => 'OK']],
                ],
                'delete' => [
                    'summary'    => "Delete {$model->name}",
                    'tags'       => [$model->name],
                    'security'   => $security,
                    'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'responses'  => ['204' => ['description' => 'No content']],
                ],
            ];
        }

        return $spec;
    }

    private function modelSchema(object $model): array
    {
        $properties = [
            'id'         => ['type' => 'integer', 'readOnly' => true],
            'created_at' => ['type' => 'string', 'format' => 'date-time', 'readOnly' => true],
            'updated_at' => ['type' => 'string', 'format' => 'date-time', 'readOnly' => true],
        ];

        foreach ($model->fields as $field) {
            $properties[$field->name] = $this->fieldSchema($field);
        }

        return ['type' => 'object', 'properties' => $properties];
    }

    private function fieldSchema(object $field): array
    {
        $schema = match ($field->type) {
            'int'      => ['type' => 'integer'],
            'float'    => ['type' => 'number'],
            'boolean'  => ['type' => 'boolean'],
            'datetime' => ['type' => 'string', 'format' => 'date-time'],
            'json'     => ['type' => 'object'],
            'relation' => ['type' => 'integer', 'description' => 'Foreign key ID'],
            default    => ['type' => 'string'],
        };

        if ($field->nullable) {
            $schema['nullable'] = true;
        }

        return $schema;
    }

    private function listParameters(): array
    {
        return [
            ['name' => 'page',     'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 1]],
            ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 20]],
            ['name' => 'sort',     'in' => 'query', 'schema' => ['type' => 'string', 'default' => 'id']],
            ['name' => 'order',    'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['asc', 'desc']]],
            ['name' => 'with',     'in' => 'query', 'schema' => ['type' => 'string'], 'description' => 'Comma-separated relation names'],
        ];
    }

    private function requestBody(string $modelName): array
    {
        return [
            'content' => [
                'application/json' => [
                    'schema' => ['$ref' => "#/components/schemas/{$modelName}"],
                ],
            ],
        ];
    }

    private function adminPaths(array $security): array
    {
        return [
            '/admin/users/login' => [
                'post' => [
                    'summary'     => 'Login',
                    'tags'        => ['Auth'],
                    'requestBody' => ['content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['username' => ['type' => 'string'], 'password' => ['type' => 'string']]]]]],
                    'responses'   => ['200' => ['description' => 'JWT token']],
                ],
            ],
            '/admin/users/logout' => [
                'post' => [
                    'summary'   => 'Logout',
                    'tags'      => ['Auth'],
                    'security'  => $security,
                    'responses' => ['200' => ['description' => 'Logged out']],
                ],
            ],
            '/admin/models' => [
                'get'  => ['summary' => 'List models',  'tags' => ['Models'], 'security' => $security, 'responses' => ['200' => ['description' => 'OK']]],
                'post' => ['summary' => 'Create model', 'tags' => ['Models'], 'security' => $security, 'responses' => ['201' => ['description' => 'Created']]],
            ],
            '/admin/models/{name}' => [
                'get'    => ['summary' => 'Get model',    'tags' => ['Models'], 'security' => $security, 'parameters' => [['name' => 'name', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]], 'responses' => ['200' => ['description' => 'OK']]],
                'patch'  => ['summary' => 'Update model', 'tags' => ['Models'], 'security' => $security, 'parameters' => [['name' => 'name', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]], 'responses' => ['200' => ['description' => 'OK']]],
                'delete' => ['summary' => 'Delete model', 'tags' => ['Models'], 'security' => $security, 'parameters' => [['name' => 'name', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]], 'responses' => ['204' => ['description' => 'No content']]],
            ],
            '/admin/keys' => [
                'get'  => ['summary' => 'List API keys',  'tags' => ['API Keys'], 'security' => $security, 'responses' => ['200' => ['description' => 'OK']]],
                'post' => ['summary' => 'Create API key', 'tags' => ['API Keys'], 'security' => $security, 'responses' => ['201' => ['description' => 'Created']]],
            ],
            '/admin/keys/{id}' => [
                'get'    => ['summary' => 'Get API key',    'tags' => ['API Keys'], 'security' => $security, 'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]], 'responses' => ['200' => ['description' => 'OK']]],
                'patch'  => ['summary' => 'Update API key', 'tags' => ['API Keys'], 'security' => $security, 'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]], 'responses' => ['200' => ['description' => 'OK']]],
                'delete' => ['summary' => 'Delete API key', 'tags' => ['API Keys'], 'security' => $security, 'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]], 'responses' => ['204' => ['description' => 'No content']]],
            ],
            '/admin/users' => [
                'get'  => ['summary' => 'List users',  'tags' => ['Users'], 'security' => $security, 'responses' => ['200' => ['description' => 'OK']]],
                'post' => ['summary' => 'Create user', 'tags' => ['Users'], 'security' => $security, 'responses' => ['201' => ['description' => 'Created']]],
            ],
            '/admin/users/{id}' => [
                'get'    => ['summary' => 'Get user',    'tags' => ['Users'], 'security' => $security, 'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]], 'responses' => ['200' => ['description' => 'OK']]],
                'patch'  => ['summary' => 'Update user', 'tags' => ['Users'], 'security' => $security, 'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]], 'responses' => ['200' => ['description' => 'OK']]],
                'delete' => ['summary' => 'Delete user', 'tags' => ['Users'], 'security' => $security, 'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]], 'responses' => ['204' => ['description' => 'No content']]],
            ],
        ];
    }
}
