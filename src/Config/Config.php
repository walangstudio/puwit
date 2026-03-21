<?php

declare(strict_types=1);

namespace Puwit\Config;

use Dotenv\Dotenv;

class Config
{
    private static array $data = [];
    private static bool $loaded = false;

    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }

        self::$loaded = true;

        if (!defined('PUWIT_ROOT')) {
            return;
        }

        $dotenv = Dotenv::createImmutable(PUWIT_ROOT);
        $dotenv->safeLoad();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$data)) {
            return self::$data[$key];
        }

        self::load();

        $env = getenv($key);
        return $_ENV[$key] ?? $_SERVER[$key] ?? ($env !== false ? $env : $default);
    }

    public static function set(string $key, mixed $value): void
    {
        self::$data[$key] = $value;
    }

    public static function reset(): void
    {
        self::$data   = [];
        self::$loaded = false;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $val = self::get($key, $default);
        if (is_bool($val)) {
            return $val;
        }
        return in_array(strtolower((string)$val), ['true', '1', 'yes', 'on'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        return (int)(self::get($key, $default) ?? $default);
    }
}
