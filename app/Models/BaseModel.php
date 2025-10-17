<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

abstract class BaseModel
{
    protected static ?PDO $pdo = null;

    protected static function db(): PDO
    {
        if (!self::$pdo) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $_ENV['DB_HOST'] ?? '127.0.0.1',
                $_ENV['DB_PORT'] ?? '3306',
                $_ENV['DB_NAME'] ?? 'app'
            );
            self::$pdo = new PDO($dsn, $_ENV['DB_USER'] ?? 'app', $_ENV['DB_PASSWORD'] ?? '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }
        return self::$pdo;
    }
}
