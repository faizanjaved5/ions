<?php
// test-db-structure.php - Test database structure and operations
require_once __DIR__ . '/../config/database.php';

echo "<h1>Database Structure Test</h1>";

global $db;

if (!$db || !$db->isConnected()) {
    die("Database not connected");
}

echo "<h2>1. IONEERS Table Structure</h2>";
try {
    // Get table structure
    $columns = $db->get_results("DESCRIBE IONEERS");
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($col->Field) . "</td>";
        echo "<td>" . htmlspecialchars($col->Type) . "</td>";
        echo "<td>" . htmlspecialchars($col->Null) . "</td>";
        echo "<td>" . htmlspecialchars($col->Key) . "</td>";
        echo "<td>" . htmlspecialchars($col->Default ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($col->Extra) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<h2>2. Current IONEERS Data</h2>";
try {
    $users = $db->get_results("SELECT email, user_role, status, last_login, login_count FROM IONEERS LIMIT 10");
    if ($users) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Email</th><th>User Role</th><th>Status</th><th>Last Login</th><th>Login Count</th></tr>";
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($user->email) . "</td>";
            echo "<td>" . htmlspecialchars($user->user_role ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($user->status ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($user->last_login ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($user->login_count ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No users found</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<h2>3. Test Insert Operation</h2>";
try {
    $test_email = 'test-oauth-' . time() . '@example.com';
    
    $insert_data = [
        'email' => $test_email,
        'fullname' => 'Test OAuth User',
        'user_role' => 'none',
        'status' => 'blocked',
        'last_login' => null,
        'login_count' => 0
    ];
    
    echo "<p><strong>Attempting to insert:</strong></p>";
    echo "<pre>" . print_r($insert_data, true) . "</pre>";
    
    $result = $db->insert('IONEERS', $insert_data);
    
    if ($result) {
        echo "<p style='color: green;'>✅ Insert successful</p>";
        
        // Verify the insert
        $verify = $db->get_row("SELECT * FROM IONEERS WHERE email = ?", $test_email);
        echo "<p><strong>Inserted data verification:</strong></p>";
        echo "<pre>" . print_r($verify, true) . "</pre>";
        
        // Clean up
        $db->delete('IONEERS', ['email' => $test_email]);
        echo "<p><em>Test record cleaned up</em></p>";
        
    } else {
        echo "<p style='color: red;'>❌ Insert failed</p>";
        if (isset($db->last_error)) {
            echo "<p>Error: " . htmlspecialchars($db->last_error) . "</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Exception: " . $e->getMessage() . "</p>";
}

echo "<h2>4. Test Update Operation</h2>";
try {
    // Find an existing user to test update
    $existing_user = $db->get_row("SELECT email, login_count FROM IONEERS LIMIT 1");
    
    if ($existing_user) {
        $current_count = intval($existing_user->login_count ?? 0);
        $new_count = $current_count + 1;
        $current_time = date('Y-m-d H:i:s');
        
        echo "<p><strong>Testing update on:</strong> " . htmlspecialchars($existing_user->email) . "</p>";
        echo "<p><strong>Current login_count:</strong> " . $current_count . "</p>";
        echo "<p><strong>New login_count:</strong> " . $new_count . "</p>";
        
        $update_result = $db->update('IONEERS', [
            'last_login' => $current_time,
            'login_count' => $new_count
        ], ['email' => $existing_user->email]);
        
        if ($update_result !== false) {
            echo "<p style='color: green;'>✅ Update successful</p>";
            
            // Verify the update
            $verify = $db->get_row("SELECT last_login, login_count FROM IONEERS WHERE email = ?", $existing_user->email);
            echo "<p><strong>Updated data verification:</strong></p>";
            echo "<pre>" . print_r($verify, true) . "</pre>";
            
            // Restore original count
            $db->update('IONEERS', ['login_count' => $current_count], ['email' => $existing_user->email]);
            echo "<p><em>Original login_count restored</em></p>";
            
        } else {
            echo "<p style='color: red;'>❌ Update failed</p>";
            if (isset($db->last_error)) {
                echo "<p>Error: " . htmlspecialchars($db->last_error) . "</p>";
            }
        }
    } else {
        echo "<p>No existing users to test update</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Exception: " . $e->getMessage() . "</p>";
}

?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
h1, h2 { color: #333; }
table { margin: 10px 0; }
th, td { padding: 8px 12px; text-align: left; }
th { background: #f5f5f5; }
pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; }
</style>