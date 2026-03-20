<?php

declare(strict_types=1);

namespace Puwit\Http;

class Request
{
    private array $body;
    private array $query;
    private array $params = [];

    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array  $headers,
        array $query,
        array $body,
    ) {
        $this->query = $query;
        $this->body  = $body;
    }

    public static function fromGlobals(): self
    {
        $method  = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri     = $_SERVER['REQUEST_URI'] ?? '/';
        $path    = parse_url($uri, PHP_URL_PATH) ?: '/';
        $headers = self::extractHeaders();
        $query   = $_GET;

        $body = [];
        $contentType = $headers['content-type'] ?? '';
        $raw = file_get_contents('php://input');

        if (str_contains($contentType, 'application/json') && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        } elseif ($method !== 'GET') {
            $body = $_POST;
        }

        return new self($method, $path, $headers, $query, $body);
    }

    private static function extractHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $name = strtolower(str_replace('_', '-', $key));
                $headers[$name] = $value;
            }
        }
        return $headers;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function allQuery(): array
    {
        return $this->query;
    }

    public function body(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    public function allBody(): array
    {
        return $this->body;
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    public function withParams(array $params): self
    {
        $clone = clone $this;
        // preserve __attr_* keys set by middleware
        $attrs = array_filter(
            $clone->params,
            fn($k) => str_starts_with($k, '__attr_'),
            ARRAY_FILTER_USE_KEY
        );
        $clone->params = array_merge($attrs, $params);
        return $clone;
    }

    public function allParams(): array
    {
        return $this->params;
    }

    public function bearerToken(): ?string
    {
        $auth = $this->header('authorization', '');
        if (str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }
        return null;
    }

    public function apiKey(): ?string
    {
        return $this->header('x-api-key');
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->params['__attr_' . $key] ?? $default;
    }

    public function withAttribute(string $key, mixed $value): self
    {
        $clone = clone $this;
        $clone->params['__attr_' . $key] = $value;
        return $clone;
    }
}
