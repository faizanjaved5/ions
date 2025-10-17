<?php

declare(strict_types=1);

use PDO;

echo "Testing database connection with updated credentials...\n";

// Updated database credentials from .env.example
$host = 'lightcoral-panther-974798.hostingersite.com'; // Removed https:// and /wp-admin
$port = '3306';
$dbname = 'u150458267_RPl22';
$username = 'u150458267_edb87';
$password = 'EjtevWAB#G^sUmPRLXMQRD6^S0';

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

    // Test if we can see tables
    $stmt = $pdo->query('SHOW TABLES');
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Available tables: " . json_encode($tables) . "\n";
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
        echo "   - Network connectivity to the host\n";
    } elseif ($e->getCode() == 1049) {
        echo "\n💡 This looks like a database not found error. Please check:\n";
        echo "   - Database '$dbname' exists on MySQL server\n";
        echo "   - Create the database if it doesn't exist\n";
    }
}


