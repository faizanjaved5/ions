<?php
/**
 * Simple Database Connection Test
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Database Connection Test</h1>";

// Try to load database
try {
    require_once __DIR__ . '/../config/database.php';
    echo "<p>✅ database.php loaded successfully</p>";
    
    global $db;
    
    if (!$db) {
        echo "<p>❌ \$db object is null</p>";
        exit;
    }
    
    echo "<p>✅ \$db object exists</p>";
    
    if (!$db->isConnected()) {
        echo "<p>❌ Database not connected. Error: " . ($db->last_error ?? 'Unknown') . "</p>";
        exit;
    }
    
    echo "<p>✅ Database connected</p>";
    
    // Try a simple query
    $result = $db->get_var("SELECT COUNT(*) FROM IONEERS");
    echo "<p>✅ Query executed. Total users in IONEERS: " . $result . "</p>";
    
    // If email provided, check that specific user
    if (isset($_GET['email'])) {
        $email = filter_var($_GET['email'], FILTER_VALIDATE_EMAIL);
        if ($email) {
            $user = $db->get_row("SELECT email, user_role, status, otp_code FROM IONEERS WHERE email = ?", $email);
            if ($user) {
                echo "<h2>User Found:</h2>";
                echo "<pre>" . print_r($user, true) . "</pre>";
            } else {
                echo "<p>❌ No user found with email: " . htmlspecialchars($email) . "</p>";
                echo "<p>Database error: " . ($db->last_error ?? 'None') . "</p>";
            }
        }
    }
    
} catch (Throwable $e) {
    echo "<p>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
?>

