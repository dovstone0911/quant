<?php

namespace Quant\Database;

use PDO;

class Connection
{
    private static ?PDO $instance = null;
    private static int $transactionLevel = 0;

    public static function get(): PDO
    {
        if (self::$instance === null) {
            self::connect();
        }
        return self::$instance;
    }

    private static function connect(): void
    {
        $config = Config::get();
        $dsn = Config::getDsn();

        self::$instance = new PDO(
            $dsn,
            $config['username'],
            $config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }

    public static function beginTransaction(): bool
    {
        if (self::$transactionLevel === 0) {
            return self::$instance->beginTransaction();
        }
        self::$transactionLevel++;
        return true;
    }

    public static function commit(): bool
    {
        self::$transactionLevel--;
        if (self::$transactionLevel === 0) {
            return self::$instance->commit();
        }
        return true;
    }

    public static function rollback(): bool
    {
        self::$transactionLevel--;
        if (self::$transactionLevel === 0) {
            return self::$instance->rollBack();
        }
        return true;
    }

    public static function inTransaction(): bool
    {
        return self::$transactionLevel > 0;
    }

    public static function lastInsertId(): string
    {
        return self::$instance->lastInsertId();
    }

    public static function quote(string $value): string
    {
        return self::$instance->quote($value);
    }
}
