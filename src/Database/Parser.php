<?php

namespace Quant\Database;

class Parser
{
    public static function parse(string $databaseString): array
    {
        // Format: mysql:host=localhost:3306;dbname=db@root:

        if (empty($databaseString)) {
            throw new \InvalidArgumentException('Database string is empty');
        }

        // 1. Extraire le driver
        if (strpos($databaseString, ':') !== false) {
            [$driver, $rest] = explode(':', $databaseString, 2);
        } else {
            $driver = 'mysql';
            $rest = $databaseString;
        }

        // 2. Extraire username:password (après @)
        $username = 'root';
        $password = '';

        if (strpos($rest, '@') !== false) {
            [$paramsPart, $authPart] = explode('@', $rest, 2);

            // Gérer le cas où il y a un @ dans le mot de passe
            if (strpos($authPart, ':') !== false) {
                [$username, $password] = explode(':', $authPart, 2);
            } else {
                $username = $authPart;
            }
        } else {
            $paramsPart = $rest;
        }

        // 3. Parser les paramètres (host=localhost:3306;dbname=db)
        $params = [];
        foreach (explode(';', $paramsPart) as $param) {
            if (strpos($param, '=') !== false) {
                [$key, $value] = explode('=', $param, 2);
                $params[$key] = $value;
            }
        }

        // 4. Extraire host et port
        $host = $params['host'] ?? 'localhost';
        $port = 3306;

        if (strpos($host, ':') !== false) {
            [$host, $port] = explode(':', $host);
            $port = (int) $port;
        }

        // 5. 🔥 Gérer le nom de base avec chiffres
        $database = $params['dbname'] ?? 'quant';
        // Si le nom contient des chiffres, on le garde tel quel (PDO gère)
        // mais on s'assure qu'il est correctement encodé

        return [
            'driver' => $driver,
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username,
            'password' => $password,
            'charset' => 'utf8mb4'
        ];
    }
}
