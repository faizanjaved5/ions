<?php
/**
 * Verification & Finalization Script for ION Video Networks
 * - Verifies all columns exist
 * - Adds missing constraints and indexes
 * - Reports current state
 */

require_once __DIR__ . '/../config/database.php';

// Use global $db from database.php
global $db;

if (!$db || !$db->isConnected()) {
    die("<p style='color: red;'>❌ Database connection failed. Check config/config.php settings.</p>");
}

echo "<h2>ION Video Networks - Verification & Finalization</h2>\n";
echo "<p>Checking database structure...</p>\n";

$errors = [];
$warnings = [];
$success = [];

try {
    // Helper function to check if index exists
    function index_exists($table, $index_name) {
        global $db;
        $result = $db->get_var(
            "SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME = ? 
             AND INDEX_NAME = ?",
            $table, $index_name
        );
        return !is_null($result);
    }

    // Helper function to check if FK exists
    function fk_exists($table, $constraint_name) {
        global $db;
        $result = $db->get_var(
            "SELECT CONSTRAINT_NAME 
             FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME = ? 
             AND CONSTRAINT_NAME = ?",
            $table, $constraint_name
        );
        return !is_null($result);
    }

    echo "<h3>1. Verifying IONLocalVideos Columns</h3>\n";
    
    // Verify columns exist
    $required_columns = ['slug', 'ion_channel', 'ion_category', 'ion_network'];
    $existing_columns = $db->get_col("SHOW COLUMNS FROM IONLocalVideos");
    
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>\n";
    echo "<tr><th>Column</th><th>Status</th></tr>\n";
    
    foreach ($required_columns as $col) {
        $exists = in_array($col, $existing_columns);
        $status = $exists ? '✅ EXISTS' : '❌ MISSING';
        $color = $exists ? 'green' : 'red';
        echo "<tr><td><strong>$col</strong></td><td style='color: $color;'>$status</td></tr>\n";
        
        if ($exists) {
            $success[] = "Column '$col' exists in IONLocalVideos";
        } else {
            $errors[] = "Column '$col' is MISSING from IONLocalVideos";
        }
    }
    echo "</table>\n";

    echo "<h3>2. Verifying IONVideoNetworks Structure</h3>\n";
    
    $required_vn_columns = ['id', 'video_id', 'network_id', 'is_primary', 'priority', 'assigned_at', 'assigned_by', 'notes'];
    $existing_vn_columns = $db->get_col("SHOW COLUMNS FROM IONVideoNetworks");
    
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>\n";
    echo "<tr><th>Column</th><th>Status</th></tr>\n";
    
    foreach ($required_vn_columns as $col) {
        $exists = in_array($col, $existing_vn_columns);
        $status = $exists ? '✅ EXISTS' : '❌ MISSING';
        $color = $exists ? 'green' : 'red';
        echo "<tr><td><strong>$col</strong></td><td style='color: $color;'>$status</td></tr>\n";
        
        if ($exists) {
            $success[] = "Column '$col' exists in IONVideoNetworks";
        } else {
            $errors[] = "Column '$col' is MISSING from IONVideoNetworks";
        }
    }
    echo "</table>\n";

    echo "<h3>3. Checking & Adding Indexes</h3>\n";
    
    // Check/add indexes on IONLocalVideos
    $indexes_to_check = [
        'IONLocalVideos' => ['idx_ion_channel', 'idx_ion_network'],
        'IONVideoNetworks' => ['idx_primary']
    ];
    
    foreach ($indexes_to_check as $table => $indexes) {
        foreach ($indexes as $index) {
            if (!index_exists($table, $index)) {
                echo "<p style='color: orange;'>⚠️ Index '$index' missing on $table - attempting to add...</p>\n";
                
                if ($index === 'idx_ion_channel') {
                    $db->query("ALTER TABLE IONLocalVideos ADD INDEX idx_ion_channel (ion_channel)");
                } elseif ($index === 'idx_ion_network') {
                    $db->query("ALTER TABLE IONLocalVideos ADD INDEX idx_ion_network (ion_network)");
                } elseif ($index === 'idx_primary') {
                    $db->query("ALTER TABLE IONVideoNetworks ADD INDEX idx_primary (video_id, is_primary)");
                }
                
                if ($db->last_error) {
                    $warnings[] = "Failed to add index '$index': " . $db->last_error;
                } else {
                    $success[] = "✅ Added index '$index' on $table";
                }
            } else {
                $success[] = "✅ Index '$index' exists on $table";
            }
        }
    }

    echo "<h3>4. Checking & Adding Unique Constraint</h3>\n";
    
    if (!index_exists('IONVideoNetworks', 'unique_video_network')) {
        echo "<p style='color: orange;'>⚠️ Unique constraint 'unique_video_network' missing - attempting to add...</p>\n";
        
        $result = $db->query("ALTER TABLE IONVideoNetworks ADD UNIQUE KEY unique_video_network (video_id, network_id)");
        
        if ($result === false) {
            $warnings[] = "Failed to add unique constraint: " . $db->last_error;
            echo "<p style='color: red;'>❌ Failed: " . $db->last_error . "</p>\n";
        } else {
            $success[] = "✅ Added unique constraint on (video_id, network_id)";
            echo "<p style='color: green;'>✅ Added unique constraint</p>\n";
        }
    } else {
        $success[] = "✅ Unique constraint already exists";
        echo "<p style='color: green;'>✅ Unique constraint already exists</p>\n";
    }

    echo "<h3>5. Checking & Adding Foreign Keys</h3>\n";
    
    // Check FK for video_id
    if (!fk_exists('IONVideoNetworks', 'fk_video_networks_video')) {
        echo "<p style='color: orange;'>⚠️ Foreign key 'fk_video_networks_video' missing - attempting to add...</p>\n";
        
        $result = $db->query("ALTER TABLE IONVideoNetworks 
            ADD CONSTRAINT fk_video_networks_video 
            FOREIGN KEY (video_id) REFERENCES IONLocalVideos(id) ON DELETE CASCADE");
        
        if ($result === false) {
            $warnings[] = "Failed to add FK for video_id: " . $db->last_error;
            echo "<p style='color: red;'>❌ Failed: " . $db->last_error . "</p>\n";
        } else {
            $success[] = "✅ Added foreign key for video_id";
            echo "<p style='color: green;'>✅ Added FK for video_id</p>\n";
        }
    } else {
        $success[] = "✅ Foreign key for video_id already exists";
        echo "<p style='color: green;'>✅ FK for video_id exists</p>\n";
    }
    
    // Check FK for network_id
    if (!fk_exists('IONVideoNetworks', 'fk_video_networks_network')) {
        echo "<p style='color: orange;'>⚠️ Foreign key 'fk_video_networks_network' missing - attempting to add...</p>\n";
        
        $result = $db->query("ALTER TABLE IONVideoNetworks 
            ADD CONSTRAINT fk_video_networks_network 
            FOREIGN KEY (network_id) REFERENCES IONNetworks(id) ON DELETE CASCADE");
        
        if ($result === false) {
            $warnings[] = "Failed to add FK for network_id: " . $db->last_error;
            echo "<p style='color: red;'>❌ Failed: " . $db->last_error . "</p>\n";
        } else {
            $success[] = "✅ Added foreign key for network_id";
            echo "<p style='color: green;'>✅ Added FK for network_id</p>\n";
        }
    } else {
        $success[] = "✅ Foreign key for network_id already exists";
        echo "<p style='color: green;'>✅ FK for network_id exists</p>\n";
    }

    // Summary
    echo "<hr>\n";
    echo "<h3>📊 Final Summary</h3>\n";
    
    if (!empty($success)) {
        echo "<h4 style='color: green;'>✅ Success (" . count($success) . ")</h4>\n";
        echo "<ul>\n";
        foreach ($success as $msg) {
            echo "<li>$msg</li>\n";
        }
        echo "</ul>\n";
    }
    
    if (!empty($warnings)) {
        echo "<h4 style='color: orange;'>⚠️ Warnings (" . count($warnings) . ")</h4>\n";
        echo "<ul>\n";
        foreach ($warnings as $msg) {
            echo "<li>$msg</li>\n";
        }
        echo "</ul>\n";
    }
    
    if (!empty($errors)) {
        echo "<h4 style='color: red;'>❌ Errors (" . count($errors) . ")</h4>\n";
        echo "<ul>\n";
        foreach ($errors as $msg) {
            echo "<li>$msg</li>\n";
        }
        echo "</ul>\n";
    }
    
    if (empty($errors)) {
        echo "<p style='color: green; font-weight: bold; font-size: 18px;'>🎉 Migration Complete & Verified!</p>\n";
        
        // Show final structure
        echo "<h3>📋 IONLocalVideos Structure (Primary Fields)</h3>\n";
        $columns = $db->get_results("SHOW COLUMNS FROM IONLocalVideos WHERE Field IN ('slug', 'ion_channel', 'ion_category', 'ion_network')");
        if ($columns) {
            echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>\n";
            echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>\n";
            foreach ($columns as $col) {
                echo "<tr>";
                echo "<td><strong>{$col->Field}</strong></td>";
                echo "<td>{$col->Type}</td>";
                echo "<td>{$col->Null}</td>";
                echo "<td>{$col->Key}</td>";
                echo "<td>" . ($col->Default ?? 'NULL') . "</td>";
                echo "</tr>\n";
            }
            echo "</table>\n";
        }
        
        echo "<h3>📋 IONVideoNetworks Structure</h3>\n";
        $vn_columns = $db->get_results("SHOW COLUMNS FROM IONVideoNetworks");
        if ($vn_columns) {
            echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>\n";
            echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>\n";
            foreach ($vn_columns as $col) {
                echo "<tr>";
                echo "<td><strong>{$col->Field}</strong></td>";
                echo "<td>{$col->Type}</td>";
                echo "<td>{$col->Null}</td>";
                echo "<td>{$col->Key}</td>";
                echo "<td>" . ($col->Default ?? 'NULL') . "</td>";
                echo "</tr>\n";
            }
            echo "</table>\n";
        }
        
        // Show sample data structure
        echo "<h3>📝 Example Usage</h3>\n";
        echo "<pre style='background: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto;'>";
        echo "-- Insert video with primary channel, category, and network:\n";
        echo "\$data = [\n";
        echo "    'slug'         => 'ion-chandler',\n";
        echo "    'ion_channel'  => 'ION Chandler',\n";
        echo "    'ion_category' => 'ION Sports',\n";
        echo "    'ion_network'  => 'Leagues on ION',\n";
        echo "    'title'        => 'Video Title',\n";
        echo "    'thumbnail'    => 'path/to/thumbnail.jpg',\n";
        echo "    // ... other fields\n";
        echo "];\n";
        echo "\$db->insert('IONLocalVideos', \$data);\n";
        echo "\$video_id = \$db->insert_id;\n\n";
        
        echo "-- Add additional networks via junction table:\n";
        echo "\$db->insert('IONVideoNetworks', [\n";
        echo "    'video_id'    => \$video_id,\n";
        echo "    'network_id'  => 5,\n";
        echo "    'is_primary'  => 1,  // Mark as primary\n";
        echo "]);\n\n";
        
        echo "-- Add secondary network:\n";
        echo "\$db->insert('IONVideoNetworks', [\n";
        echo "    'video_id'    => \$video_id,\n";
        echo "    'network_id'  => 8,\n";
        echo "    'is_primary'  => 0,  // Secondary network\n";
        echo "]);\n";
        echo "</pre>\n";
        
    } else {
        echo "<p style='color: red; font-weight: bold;'>⚠️ Please fix the errors above before proceeding.</p>\n";
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Fatal error: " . $e->getMessage() . "</p>\n";
}
?>
