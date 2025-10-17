<?php
/**
 * Table Name Checker
 * Upload this to your production server to find the correct table name
 */

require_once __DIR__ . '/../config/config.php';

try {
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ]
    );
    
    echo "<h2>All Tables in Database:</h2>";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>$table</li>";
        
        // Check if it's a user/ioneer table
        if (stripos($table, 'ioneer') !== false || stripos($table, 'user') !== false) {
            echo " <strong>← POSSIBLE USERS TABLE</strong>";
        }
    }
    echo "</ul>";
    
    // Try common variations
    echo "<h2>Testing Table Variations:</h2>";
    $variations = ['IONEERS', 'ioneers', 'Ioneers', 'users', 'USERS', 'Users'];
    
    foreach ($variations as $tableName) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM `$tableName`");
            $count = $stmt->fetchColumn();
            echo "<p style='color: green;'>✅ <strong>`$tableName`</strong> exists with $count rows</p>";
        } catch (PDOException $e) {
            echo "<p style='color: red;'>❌ `$tableName` not found</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
