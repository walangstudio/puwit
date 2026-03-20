<?php

declare(strict_types=1);

namespace Puwit\Http;

/**
 * Trie-based router. Each node: ['__h__' => [METHOD => handler], '__p__' => ['__n__' => paramName, ...children], ...literal children]
 */
class Router
{
    private array $root = ['__h__' => []];

    public function add(string $method, string $path, callable $handler): void
    {
        $node = &$this->root;

        foreach ($this->segments($path) as $seg) {
            if (str_starts_with($seg, '{')) {
                $name = trim($seg, '{}');
                if (!isset($node['__p__'])) {
                    $node['__p__'] = ['__n__' => $name, '__h__' => []];
                }
                $node = &$node['__p__'];
            } else {
                if (!isset($node[$seg])) {
                    $node[$seg] = ['__h__' => []];
                }
                $node = &$node[$seg];
            }
        }

        $node['__h__'][strtoupper($method)] = $handler;
    }

    public function dispatch(Request $request): ?array
    {
        $node   = &$this->root;
        $params = [];

        foreach ($this->segments($request->path()) as $seg) {
            if (isset($node[$seg])) {
                $node = &$node[$seg];
            } elseif (isset($node['__p__'])) {
                $params[$node['__p__']['__n__']] = $seg;
                $node = &$node['__p__'];
            } else {
                return null;
            }
        }

        $method = strtoupper($request->method());

        if ($method === 'OPTIONS') {
            return ['handler' => fn($req) => Response::json(null, 204), 'params' => $params];
        }

        $handlers = $node['__h__'] ?? [];

        if (isset($handlers[$method])) {
            return ['handler' => $handlers[$method], 'params' => $params];
        }

        if (!empty($handlers)) {
            return ['handler' => null, 'params' => $params, 'method_not_allowed' => true];
        }

        return null;
    }

    private function segments(string $path): array
    {
        $path = trim($path, '/');
        return $path === '' ? [] : explode('/', $path);
    }
}
