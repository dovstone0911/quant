<?php

namespace Quant\Database;

class Config
{
    private static array $config = [
        'driver' => 'mysql',
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'quant',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'options' => []
    ];

    public static function set(array $config): void
    {
        self::$config = array_merge(self::$config, $config);
    }

    public static function get(?string $key = null)
    {
        if ($key === null) {
            return self::$config;
        }
        return self::$config[$key] ?? null;
    }

    public static function getDriver(): string
    {
        return self::$config['driver'] ?? 'mysql';
    }

    public static function getDsn(): string
    {
        $config = self::$config;
        $driver = $config['driver'] ?? 'mysql';

        return match ($driver) {
            'mysql' => sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $config['host'],
                $config['port'] ?? 3306,
                $config['database'],
                $config['charset'] ?? 'utf8mb4'
            ),
            'pgsql', 'postgresql' => sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                $config['host'],
                $config['port'] ?? 5432,
                $config['database']
            ),
            'sqlite' => sprintf(
                'sqlite:%s',
                $config['database']
            ),
            'sqlsrv' => sprintf(
                'sqlsrv:Server=%s,%s;Database=%s',
                $config['host'],
                $config['port'] ?? 1433,
                $config['database']
            ),
            default => throw new \Exception("Unsupported driver: {$driver}")
        };
    }
}
