<?php
/**
 * Fix Upload ID Column Size
 * R2 upload IDs can be up to 400+ characters
 * Current column is VARCHAR(255) which truncates them
 * 
 * Problem: r2_upload_id has an index that prevents TEXT conversion
 * Solution: Drop index, alter column, recreate index with prefix
 */

require_once __DIR__ . '/../config/database.php';

$db = new IONDatabase();

echo "=== Fixing IONUploadSessions.r2_upload_id Column Size ===\n\n";

// Step 1: Check current column type
$result = $db->get_row("SHOW COLUMNS FROM IONUploadSessions LIKE 'r2_upload_id'");
if ($result) {
    echo "Current column type: " . $result->Type . "\n";
    echo "Current key type: " . $result->Key . "\n\n";
}

// Step 2: Check for existing indexes on this column
echo "Checking for indexes on r2_upload_id...\n";
$indexes = $db->get_results("SHOW INDEX FROM IONUploadSessions WHERE Column_name = 'r2_upload_id'");
if ($indexes) {
    foreach ($indexes as $index) {
        echo "Found index: " . $index->Key_name . " (Non-unique: " . $index->Non_unique . ")\n";
        
        // Drop the index
        echo "Dropping index: " . $index->Key_name . "...\n";
        $success = $db->query("ALTER TABLE IONUploadSessions DROP INDEX " . $index->Key_name);
        if ($success) {
            echo "✅ Index dropped successfully\n";
        } else {
            echo "❌ Failed to drop index: " . $db->last_error . "\n";
        }
    }
} else {
    echo "No indexes found on r2_upload_id\n";
}
echo "\n";

// Step 3: Alter column to TEXT
echo "Altering r2_upload_id column to TEXT...\n";
$success = $db->query("
    ALTER TABLE IONUploadSessions 
    MODIFY COLUMN r2_upload_id TEXT NULL
");

if ($success) {
    echo "✅ SUCCESS: Column altered to TEXT\n\n";
    
    // Step 4: Recreate index with prefix (first 255 chars)
    echo "Recreating index with prefix...\n";
    $success = $db->query("
        ALTER TABLE IONUploadSessions 
        ADD INDEX idx_r2_upload_id (r2_upload_id(255))
    ");
    
    if ($success) {
        echo "✅ Index recreated with 255-char prefix\n\n";
    } else {
        echo "⚠️ Note: Could not recreate index (this is OK): " . $db->last_error . "\n\n";
    }
    
    // Verify the change
    $result = $db->get_row("SHOW COLUMNS FROM IONUploadSessions LIKE 'r2_upload_id'");
    if ($result) {
        echo "New column type: " . $result->Type . "\n";
    }
} else {
    echo "❌ FAILED: Could not alter column\n";
    echo "Error: " . $db->last_error . "\n";
}

echo "\n=== Done ===\n";
?>

