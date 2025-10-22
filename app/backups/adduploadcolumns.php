<?php
/**
 * Add Missing Columns to IONUploadSessions
 * Adds title, description, category, visibility, user_id columns
 */

require_once __DIR__ . '/../config/database.php';

$db = new IONDatabase();

echo "=== Adding Missing Columns to IONUploadSessions ===\n\n";

$columns = [
    'title' => "VARCHAR(512) NULL AFTER file_name",
    'description' => "TEXT NULL AFTER title",
    'category' => "VARCHAR(255) NULL AFTER description",
    'visibility' => "VARCHAR(50) NULL AFTER category",
    'user_id' => "INT(11) NULL AFTER visibility",
    'thumbnail' => "VARCHAR(512) NULL AFTER user_id"
];

foreach ($columns as $column => $definition) {
    // Check if column exists
    $exists = $db->get_row("SHOW COLUMNS FROM IONUploadSessions LIKE '$column'");
    
    if ($exists) {
        echo "✅ Column '$column' already exists\n";
    } else {
        echo "Adding column '$column'...\n";
        $sql = "ALTER TABLE IONUploadSessions ADD COLUMN $column $definition";
        $success = $db->query($sql);
        
        if ($success) {
            echo "✅ Column '$column' added successfully\n";
        } else {
            echo "❌ Failed to add column '$column': " . $db->last_error . "\n";
        }
    }
}

echo "\n=== Done ===\n";
?>

