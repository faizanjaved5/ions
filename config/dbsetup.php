<?php
require_once __DIR__ . '/database.php';

global $db;

if ($db->pdo) {
    $tables = [
        'IONEERS' => "CREATE TABLE IF NOT EXISTS IONEERS (
            email VARCHAR(255) PRIMARY KEY,
            user_id VARCHAR(255),
            fullname VARCHAR(255),
            user_role ENUM('Owner', 'Admin', 'Member', 'Creator', 'Viewer', 'Guest') DEFAULT 'Guest',
            status ENUM('active', 'inactive', 'blocked') DEFAULT 'active',
            photo_url TEXT,
            last_login DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            google_id VARCHAR(255),
            discord_user_id VARCHAR(255),
            x_user_id VARCHAR(255),
            meta_facebook_id VARCHAR(255),
            linkedin_id VARCHAR(255),
            user_login VARCHAR(255),
            wp_user_id VARCHAR(255),
            user_url TEXT,
            profile_name VARCHAR(255),
            dob DATE,
            handle VARCHAR(100) UNIQUE,
            profile_visibility ENUM('Public', 'Private', 'Restricted') DEFAULT 'Private'
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