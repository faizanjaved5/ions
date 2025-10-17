<?php

declare(strict_types=1);

namespace App\Models;

final class ExampleModel extends BaseModel
{
    public static function all(): array
    {
        $stmt = self::db()->query('SELECT 1 AS ok');
        return $stmt->fetchAll();
    }
}
