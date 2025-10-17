<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use App\Models\ExampleModel;

require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

echo "Testing database connection...\n";
echo "DB Host: " . ($_ENV['DB_HOST'] ?? '127.0.0.1') . "\n";
echo "DB Port: " . ($_ENV['DB_PORT'] ?? '3306') . "\n";
echo "DB Name: " . ($_ENV['DB_NAME'] ?? 'app') . "\n";
echo "DB User: " . ($_ENV['DB_USER'] ?? 'app') . "\n";
echo "DB Password: " . (empty($_ENV['DB_PASSWORD']) ? '(empty)' : '(set)') . "\n\n";

try {
    $data = ExampleModel::all();
    echo "✅ Database connection successful!\n";
    echo "Query result: " . json_encode($data) . "\n";
} catch (Exception $e) {
    echo "❌ Database connection failed!\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "Error Code: " . $e->getCode() . "\n";

    if ($e->getCode() == 1045) {
        echo "\n💡 This looks like an authentication error. Please check:\n";
        echo "   - DB_USER and DB_PASSWORD in .env file\n";
        echo "   - MySQL user has proper permissions\n";
    } elseif ($e->getCode() == 2002) {
        echo "\n💡 This looks like a connection error. Please check:\n";
        echo "   - MySQL server is running\n";
        echo "   - DB_HOST and DB_PORT in .env file\n";
    } elseif ($e->getCode() == 1049) {
        echo "\n💡 This looks like a database not found error. Please check:\n";
        echo "   - DB_NAME in .env file\n";
        echo "   - Database exists on MySQL server\n";
    }
}


