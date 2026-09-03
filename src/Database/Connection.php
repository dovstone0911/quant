<?php

namespace Quant\Database;

use PDO;
use PDOException;

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

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];

        if (!empty($config['options'])) {
            $options = array_merge($options, $config['options']);
        }

        try {
            self::$instance = new PDO(
                $dsn,
                $config['username'] ?? '',
                $config['password'] ?? '',
                $options
            );
        } catch (PDOException $e) {
            throw new PDOException("Connection failed: " . $e->getMessage());
        }
    }

    public static function beginTransaction(): bool
    {
        if (self::$transactionLevel === 0) {
            self::$instance->beginTransaction();
        } else {
            self::$instance->exec("SAVEPOINT level" . self::$transactionLevel);
        }
        self::$transactionLevel++;
        return true;
    }

    public static function commit(): bool
    {
        self::$transactionLevel--;
        if (self::$transactionLevel === 0) {
            return self::$instance->commit();
        } else {
            return self::$instance->exec("RELEASE SAVEPOINT level" . self::$transactionLevel) !== false;
        }
    }

    public static function rollback(): bool
    {
        self::$transactionLevel--;
        if (self::$transactionLevel === 0) {
            return self::$instance->rollBack();
        } else {
            return self::$instance->exec("ROLLBACK TO SAVEPOINT level" . self::$transactionLevel) !== false;
        }
    }

    public static function inTransaction(): bool
    {
        return self::$transactionLevel > 0;
    }

    public static function getTransactionLevel(): int
    {
        return self::$transactionLevel;
    }

    public static function reconnect(): void
    {
        self::$instance = null;
        self::connect();
    }
}
