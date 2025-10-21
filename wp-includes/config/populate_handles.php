<?php
require_once __DIR__ . '/database.php';

global $db;

if ($db->pdo) {
    echo "Starting to populate handles for existing users...<br>";
    
    try {
        // Get all users without handles
        $stmt = $db->pdo->query("SELECT email, fullname FROM IONEERS WHERE handle IS NULL OR handle = ''");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Found " . count($users) . " users without handles.<br>";
        
        foreach ($users as $user) {
            $email = $user['email'];
            $fullname = $user['fullname'];
            
            // Generate a handle from email or fullname
            if (!empty($fullname)) {
                // Use fullname to create handle
                $handle = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $fullname));
                $handle = substr($handle, 0, 20); // Limit to 20 characters
            } else {
                // Use email prefix to create handle
                $email_prefix = explode('@', $email)[0];
                $handle = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $email_prefix));
                $handle = substr($handle, 0, 20);
            }
            
            // Make sure handle is unique by adding a number if needed
            $original_handle = $handle;
            $counter = 1;
            while (true) {
                $check_stmt = $db->pdo->prepare("SELECT COUNT(*) FROM IONEERS WHERE handle = ?");
                $check_stmt->execute([$handle]);
                if ($check_stmt->fetchColumn() == 0) {
                    break; // Handle is unique
                }
                $handle = $original_handle . $counter;
                $counter++;
            }
            
            // Update the user with the new handle
            $update_stmt = $db->pdo->prepare("UPDATE IONEERS SET handle = ?, profile_visibility = 'Public' WHERE email = ?");
            $update_stmt->execute([$handle, $email]);
            
            echo "Updated user {$email} with handle: {$handle}<br>";
        }
        
        echo "<br>Handle population completed successfully!<br>";
        echo "<a href='../app/ioneers.php'>Go back to IONEERS page</a>";
        
    } catch (PDOException $e) {
        echo "Failed to populate handles: " . $e->getMessage() . "<br>";
    }
} else {
    echo "Database connection failed.";
}
?>
