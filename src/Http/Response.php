<?php

declare(strict_types=1);

namespace Puwit\Http;

class Response
{
    private array $headers;

    public function __construct(
        private int    $status,
        private mixed  $body,
        array $headers = [],
    ) {
        $this->headers = $headers;
    }

    public static function json(mixed $data, int $status = 200, array $headers = []): self
    {
        $headers['Content-Type'] = 'application/json';
        return new self($status, $data, $headers);
    }

    public static function ok(mixed $data, array $meta = []): self
    {
        return self::json([
            'data' => $data,
            'meta' => array_merge(['timestamp' => date('c')], $meta),
        ]);
    }

    public static function created(mixed $data, array $meta = []): self
    {
        return self::json([
            'data' => $data,
            'meta' => array_merge(['timestamp' => date('c')], $meta),
        ], 201);
    }

    public static function noContent(): self
    {
        return new self(204, null);
    }

    public static function error(string $message, int $status = 400, array $extra = []): self
    {
        return self::json(array_merge(['error' => $message], $extra), $status);
    }

    public static function notFound(string $message = 'Not found'): self
    {
        return self::error($message, 404);
    }

    public static function unauthorized(string $message = 'Unauthorized'): self
    {
        return self::error($message, 401);
    }

    public static function forbidden(string $message = 'Forbidden'): self
    {
        return self::error($message, 403);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function withStatus(int $status): self
    {
        $clone = clone $this;
        $clone->status = $status;
        return $clone;
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        if ($this->body !== null) {
            echo json_encode($this->body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }
}
