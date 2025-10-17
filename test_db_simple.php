<?php

declare(strict_types=1);

use PDO;

echo "Testing database connection...\n";

// Default database credentials
$host = '100.64.0.4';
$port = '3306';
$dbname = 'ion';
$username = 'faizan';
$password = 'abhorent1994';

echo "DB Host: $host\n";
echo "DB Port: $port\n";
echo "DB Name: $dbname\n";
echo "DB User: $username\n";
echo "DB Password: " . (empty($password) ? '(empty)' : '(set)') . "\n\n";

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $host,
        $port,
        $dbname
    );

    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "✅ Database connection successful!\n";

    // Test a simple query
    $stmt = $pdo->query('SELECT 1 AS test_value');
    $result = $stmt->fetch();
    echo "Query result: " . json_encode($result) . "\n";
} catch (Exception $e) {
    echo "❌ Database connection failed!\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "Error Code: " . $e->getCode() . "\n";

    if ($e->getCode() == 1045) {
        echo "\n💡 This looks like an authentication error. Please check:\n";
        echo "   - MySQL username and password\n";
        echo "   - MySQL user has proper permissions\n";
    } elseif ($e->getCode() == 2002) {
        echo "\n💡 This looks like a connection error. Please check:\n";
        echo "   - MySQL server is running\n";
        echo "   - Host and port are correct\n";
    } elseif ($e->getCode() == 1049) {
        echo "\n💡 This looks like a database not found error. Please check:\n";
        echo "   - Database 'app' exists on MySQL server\n";
        echo "   - Create the database if it doesn't exist\n";
    }
}
