<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use App\Models\ExampleModel;

require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

echo "Testing ExampleModel with new credentials...\n";
echo "DB Host: " . ($_ENV['DB_HOST'] ?? 'not set') . "\n";
echo "DB Name: " . ($_ENV['DB_NAME'] ?? 'not set') . "\n";
echo "DB User: " . ($_ENV['DB_USER'] ?? 'not set') . "\n\n";

try {
    $data = ExampleModel::all();
    echo "✅ ExampleModel connection successful!\n";
    echo "Query result: " . json_encode($data) . "\n";
} catch (Exception $e) {
    echo "❌ ExampleModel connection failed!\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "Error Code: " . $e->getCode() . "\n";

    if ($e->getCode() == 1045) {
        echo "\n💡 Authentication error. Please verify:\n";
        echo "   - Username: " . ($_ENV['DB_USER'] ?? 'not set') . "\n";
        echo "   - Password: " . (empty($_ENV['DB_PASSWORD']) ? 'empty' : 'set') . "\n";
        echo "   - Database: " . ($_ENV['DB_NAME'] ?? 'not set') . "\n";
        echo "   - User has access from your IP address\n";
    }
}


