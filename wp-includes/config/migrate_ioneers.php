<?php
require_once __DIR__ . '/database.php';

global $db;

if ($db->pdo) {
    echo "Starting IONEERS table migration...<br>";
    
    try {
        // Check if handle column exists
        $result = $db->pdo->query("SHOW COLUMNS FROM IONEERS LIKE 'handle'");
        if ($result->rowCount() == 0) {
            $db->pdo->exec("ALTER TABLE IONEERS ADD COLUMN handle VARCHAR(100) UNIQUE");
            echo "Added handle column to IONEERS table.<br>";
        } else {
            echo "handle column already exists.<br>";
        }
        
        // Check if profile_visibility column exists
        $result = $db->pdo->query("SHOW COLUMNS FROM IONEERS LIKE 'profile_visibility'");
        if ($result->rowCount() == 0) {
            $db->pdo->exec("ALTER TABLE IONEERS ADD COLUMN profile_visibility ENUM('Public', 'Private', 'Restricted') DEFAULT 'Private'");
            echo "Added profile_visibility column to IONEERS table.<br>";
        } else {
            echo "profile_visibility column already exists.<br>";
        }
        
        // Check if other common columns exist and add them if missing
        $columns_to_check = [
            'user_id' => 'VARCHAR(255)',
            'fullname' => 'VARCHAR(255)',
            'user_role' => "ENUM('Owner', 'Admin', 'Member', 'Creator', 'Viewer', 'Guest') DEFAULT 'Guest'",
            'status' => "ENUM('active', 'inactive', 'blocked') DEFAULT 'active'",
            'photo_url' => 'TEXT',
            'last_login' => 'DATETIME',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'google_id' => 'VARCHAR(255)',
            'discord_user_id' => 'VARCHAR(255)',
            'x_user_id' => 'VARCHAR(255)',
            'meta_facebook_id' => 'VARCHAR(255)',
            'linkedin_id' => 'VARCHAR(255)',
            'user_login' => 'VARCHAR(255)',
            'wp_user_id' => 'VARCHAR(255)',
            'user_url' => 'TEXT',
            'profile_name' => 'VARCHAR(255)',
            'dob' => 'DATE'
        ];
        
        foreach ($columns_to_check as $column => $definition) {
            $result = $db->pdo->query("SHOW COLUMNS FROM IONEERS LIKE '$column'");
            if ($result->rowCount() == 0) {
                $db->pdo->exec("ALTER TABLE IONEERS ADD COLUMN $column $definition");
                echo "Added $column column to IONEERS table.<br>";
            } else {
                echo "$column column already exists.<br>";
            }
        }
        
        echo "IONEERS table migration completed successfully!<br>";
        
    } catch (PDOException $e) {
        echo "Migration failed: " . $e->getMessage() . "<br>";
    }
} else {
    echo "Database connection failed.";
}
?>
