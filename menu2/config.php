<?php
/**
 * Database Configuration File
 * Copy this file and update with your database credentials
 */

 ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_WARNING);

// Database configuration
// define('DB_HOST', 'localhost');
// define('DB_PORT', '3306');
// define('DB_NAME', 'ion');
// define('DB_USER', 'faizan');
// define('DB_PASS', 'abhorent1994');

// define('DB_HOST', '191.101.79.52');
// define('DB_PORT', '3306');
// define('DB_NAME', 'u731359835_iontest');
// define('DB_USER', 'u731359835_faizan');
// define('DB_PASS', 'wZ3s0~t?');

define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'u185424179_WtONJ');
define('DB_USER', 'u185424179_S4e4w');
define('DB_PASS', '04JE8wHMrl');

// Create PDO connection
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}
