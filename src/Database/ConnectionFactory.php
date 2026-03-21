<?php

declare(strict_types=1);

namespace Puwit\Database;

use Puwit\Config\Config;
use Puwit\Database\Dialects\DialectInterface;
use Puwit\Database\Dialects\MySQLDialect;
use Puwit\Database\Dialects\PostgresDialect;
use Puwit\Database\Dialects\SQLiteDialect;

class ConnectionFactory
{
    private static ?Connection $instance = null;

    public static function make(): Connection
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $driver = Config::get('DB_DRIVER', 'mysql');
        [$pdo, $dialect] = match ($driver) {
            'mysql'  => self::mysql(),
            'pgsql'  => self::pgsql(),
            'sqlite' => self::sqlite(),
            default  => throw new \RuntimeException("Unsupported DB driver: {$driver}"),
        };

        self::$instance = new Connection($pdo, $dialect);
        return self::$instance;
    }

    private static function mysql(): array
    {
        $host = Config::get('DB_HOST', '127.0.0.1');
        $port = Config::get('DB_PORT', '3306');
        $name = Config::get('DB_NAME', 'puwit');
        $user = Config::get('DB_USER', 'root');
        $pass = Config::get('DB_PASS', '');

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        $pdo = new \PDO($dsn, $user, $pass, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return [$pdo, new MySQLDialect()];
    }

    private static function pgsql(): array
    {
        $host = Config::get('DB_HOST', '127.0.0.1');
        $port = Config::get('DB_PORT', '5432');
        $name = Config::get('DB_NAME', 'puwit');
        $user = Config::get('DB_USER', 'postgres');
        $pass = Config::get('DB_PASS', '');

        $dsn = "pgsql:host={$host};port={$port};dbname={$name}";
        $pdo = new \PDO($dsn, $user, $pass, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return [$pdo, new PostgresDialect()];
    }

    private static function sqlite(): array
    {
        $default = defined('PUWIT_ROOT') ? PUWIT_ROOT . '/storage/database.sqlite' : ':memory:';
        $path    = Config::get('DB_PATH', $default);
        $pdo = new \PDO("sqlite:{$path}", null, null, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON;');

        return [$pdo, new SQLiteDialect()];
    }

    public static function reset(): void
    {
        self::$instance = null;
    }
}
