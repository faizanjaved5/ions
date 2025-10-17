<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "1. Starting<br>";

declare(strict_types=1);
session_start();

echo "2. Session started<br>";

// Config + DB bootstrap
$CONFIG_FILE = __DIR__ . '/../config/config.php';
$DB_FILE     = __DIR__ . '/../config/database.php';

echo "3. Paths defined<br>";

if (!file_exists($CONFIG_FILE)) {
    http_response_code(500);
    exit('Missing /config/config.php');
}

echo "4. Config exists<br>";

$config = require $CONFIG_FILE;

echo "5. Config loaded<br>";

$GLOBALS['config'] = $config;

echo "6. Config globalized<br>";

if (!file_exists($DB_FILE)) {
    http_response_code(500);
    exit('Missing /config/database.php');
}

echo "7. DB file exists<br>";

require_once $DB_FILE;

echo "8. DB loaded<br>";

// Get database connection
global $db;
if (!$db || !$db->isConnected()) {
    http_response_code(500);
    exit('Database connection error');
}

echo "9. DB connected<br>";

echo "<br>ALL INITIALIZATION SUCCESSFUL!";
?>