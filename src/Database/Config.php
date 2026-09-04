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
        'charset' => 'utf8mb4'
    ];

    private static bool $loaded = false;

    public static function set(array $config): void
    {
        self::$config = array_merge(self::$config, $config);
        self::$loaded = true;
    }

    public static function fromEnv(?string $envFile = null): void
    {
        if (self::$loaded) {
            return;
        }

        // Chercher le .env
        if ($envFile === null) {
            $envFile = getcwd() . '/.env';
            if (!file_exists($envFile)) {
                $paths = [
                    __DIR__ . '/../../.env',
                    __DIR__ . '/../../../.env',
                    __DIR__ . '/../../../../.env',
                    __DIR__ . '/../../../../../.env',
                ];
                foreach ($paths as $path) {
                    if (file_exists($path)) {
                        $envFile = $path;
                        break;
                    }
                }
            }
        }

        if (!$envFile || !file_exists($envFile)) {
            return;
        }

        // Lire le .env
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $env = [];
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                [$key, $value] = explode('=', $line, 2);
                $env[$key] = trim($value);
            }
        }

        if (!isset($env['DATABASE'])) {
            return;
        }

        self::fromString($env['DATABASE']);
    }

    public static function fromString(string $dbString): void
    {
        // Extraire le driver
        if (strpos($dbString, ':') !== false) {
            [$driver, $rest] = explode(':', $dbString, 2);
        } else {
            $driver = 'mysql';
            $rest = $dbString;
        }

        // Extraire username:password (après @)
        $username = 'root';
        $password = '';
        $paramsPart = $rest;

        if (strpos($rest, '@') !== false) {
            [$paramsPart, $authPart] = explode('@', $rest, 2);
            if (strpos($authPart, ':') !== false) {
                [$username, $password] = explode(':', $authPart, 2);
            } else {
                $username = $authPart;
            }
        }

        // Parser les paramètres
        $params = [];
        foreach (explode(';', $paramsPart) as $param) {
            if (strpos($param, '=') !== false) {
                [$key, $value] = explode('=', $param, 2);
                $params[$key] = trim($value);
            }
        }

        // Extraire host et port
        $host = $params['host'] ?? 'localhost';
        $port = (int) ($params['port'] ?? 3306);

        if (strpos($host, ':') !== false && !isset($params['port'])) {
            [$host, $port] = explode(':', $host);
            $port = (int) $port;
        }

        self::set([
            'driver' => $driver,
            'host' => $host,
            'port' => $port,
            'database' => $params['dbname'] ?? 'quant',
            'username' => $username,
            'password' => $password,
            'charset' => $params['charset'] ?? 'utf8mb4'
        ]);

        self::$loaded = true;
    }

    public static function get(?string $key = null)
    {
        if (!self::$loaded) {
            self::fromEnv();
        }

        if ($key === null) {
            return self::$config;
        }
        return self::$config[$key] ?? null;
    }

    public static function getDriver(): string
    {
        return self::get('driver') ?? 'mysql';
    }

    public static function getDsn(): string
    {
        $config = self::get();
        $driver = $config['driver'] ?? 'mysql';

        return match ($driver) {
            'mysql' => sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $config['host'],
                $config['port'],
                $config['database'],
                $config['charset']
            ),
            'pgsql' => sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                $config['host'],
                $config['port'] ?? 5432,
                $config['database']
            ),
            'sqlite' => sprintf(
                'sqlite:%s',
                $config['database']
            ),
            default => throw new \Exception("Unsupported driver: {$driver}")
        };
    }
}
