<?php
require_once __DIR__ . '/database.php';

global $db;

if ($db->pdo) {
    $tables = [
        'IONEERS' => "CREATE TABLE IF NOT EXISTS IONEERS (
            email VARCHAR(255) PRIMARY KEY
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        'email_otp' => "CREATE TABLE IF NOT EXISTS email_otp (
            email VARCHAR(255) PRIMARY KEY,
            otp_code VARCHAR(6) NOT NULL,
            expires_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        'IONLoginLogs' => "CREATE TABLE IF NOT EXISTS IONLoginLogs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            status VARCHAR(10) NOT NULL,
            ip VARCHAR(45) NOT NULL,
            user_agent TEXT,
            timestamp DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
    ];
    
    foreach ($tables as $table => $sql) {
        try {
            $db->pdo->exec($sql);
            echo "Table $table created or already exists.<br>";
        } catch (PDOException $e) {
            echo "Failed to create table $table: " . $e->getMessage() . "<br>";
        }
    }
} else {
    echo "Database connection failed.";
}
?>