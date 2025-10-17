<?php

declare(strict_types=1);

use PDO;

echo "Testing database connection with SSL options...\n";

$host = 'lightcoral-panther-974798.hostingersite.com';
$port = '3306';
$dbname = 'u150458267_RPl22';
$username = 'u150458267_edb87';
$password = 'EjtevWAB#G^sUmPRLXMQRD6^S0';

echo "DB Host: $host\n";
echo "DB Port: $port\n";
echo "DB Name: $dbname\n";
echo "DB User: $username\n\n";

// Test 1: Basic connection
echo "=== Test 1: Basic Connection ===\n";
try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "✅ Basic connection successful!\n";
} catch (Exception $e) {
    echo "❌ Basic connection failed: " . $e->getMessage() . "\n";
}

// Test 2: With SSL
echo "\n=== Test 2: With SSL ===\n";
try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_SSL_CA => false,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
    ]);
    echo "✅ SSL connection successful!\n";
} catch (Exception $e) {
    echo "❌ SSL connection failed: " . $e->getMessage() . "\n";
}

// Test 3: Different port (common alternatives)
echo "\n=== Test 3: Different Ports ===\n";
$ports = ['3306', '3307', '33060', '33061'];
foreach ($ports as $testPort) {
    try {
        $dsn = "mysql:host=$host;port=$testPort;dbname=$dbname;charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        echo "✅ Port $testPort connection successful!\n";
        break;
    } catch (Exception $e) {
        echo "❌ Port $testPort failed: " . $e->getMessage() . "\n";
    }
}

// Test 4: Check if host is reachable
echo "\n=== Test 4: Host Reachability ===\n";
$connection = @fsockopen($host, 3306, $errno, $errstr, 5);
if ($connection) {
    echo "✅ Host is reachable on port 3306\n";
    fclose($connection);
} else {
    echo "❌ Host is not reachable on port 3306: $errstr ($errno)\n";
}


