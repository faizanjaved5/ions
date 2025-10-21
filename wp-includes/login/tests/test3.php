<?php
// test-oauth-debug.php - Create this file to test your OAuth setup
session_start();

echo "<h1>OAuth Debug Information</h1>";

// Test 1: Check configuration
echo "<h2>1. Configuration Check</h2>";
$config = require __DIR__ . '/../config/config.php';

echo "<p><strong>Google Client ID:</strong> " . (isset($config['google_client_id']) ? substr($config['google_client_id'], 0, 20) . "..." : "NOT SET") . "</p>";
echo "<p><strong>Google Client Secret:</strong> " . (isset($config['google_client_secret']) ? "SET (length: " . strlen($config['google_client_secret']) . ")" : "NOT SET") . "</p>";
echo "<p><strong>Redirect URI:</strong> " . ($config['google_redirect_uri'] ?? "NOT SET") . "</p>";

// Test 2: Check database connection
echo "<h2>2. Database Connection</h2>";
try {
    require_once __DIR__ . '/../config/database.php';
    global $db;
    
    if ($db && $db->isConnected()) {
        echo "<p style='color: green;'>✅ Database connected successfully</p>";
        
        // Test a simple query
        $test_query = $db->get_var("SELECT COUNT(*) FROM IONEERS");
        echo "<p>Total users in IONEERS table: " . $test_query . "</p>";
        
    } else {
        echo "<p style='color: red;'>❌ Database not connected</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database error: " . $e->getMessage() . "</p>";
}

// Test 3: Check session
echo "<h2>3. Session Information</h2>";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
echo "<p><strong>Session Data:</strong></p>";
echo "<pre>" . print_r($_SESSION, true) . "</pre>";

// Test 4: Check URL parameters (if any)
echo "<h2>4. URL Parameters</h2>";
if (!empty($_GET)) {
    echo "<pre>" . print_r($_GET, true) . "</pre>";
} else {
    echo "<p>No GET parameters</p>";
}

// Test 5: Server info
echo "<h2>5. Server Information</h2>";
echo "<p><strong>Server Name:</strong> " . ($_SERVER['SERVER_NAME'] ?? 'Unknown') . "</p>";
echo "<p><strong>Request URI:</strong> " . ($_SERVER['REQUEST_URI'] ?? 'Unknown') . "</p>";
echo "<p><strong>HTTPS:</strong> " . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'Yes' : 'No') . "</p>";

// Test 6: PHP Version and Extensions
echo "<h2>6. PHP Environment</h2>";
echo "<p><strong>PHP Version:</strong> " . PHP_VERSION . "</p>";
echo "<p><strong>cURL Enabled:</strong> " . (extension_loaded('curl') ? 'Yes' : 'No') . "</p>";
echo "<p><strong>JSON Enabled:</strong> " . (extension_loaded('json') ? 'Yes' : 'No') . "</p>";

// Test 7: Recent error logs (if accessible)
echo "<h2>7. Recent Error Log Entries</h2>";
echo "<p><em>Check your server's error log for entries starting with 'GOOGLE OAUTH DEBUG' or 'DEBUG verifyotp.php'</em></p>";

// Test 8: Generate a test Google OAuth URL
echo "<h2>8. Test Google OAuth URL</h2>";
if (isset($config['google_client_id']) && isset($config['google_redirect_uri'])) {
    $oauth_url = "https://accounts.google.com/o/oauth2/auth?" . http_build_query([
        'client_id' => $config['google_client_id'],
        'redirect_uri' => $config['google_redirect_uri'],
        'scope' => 'email profile',
        'response_type' => 'code',
        'access_type' => 'offline'
    ]);
    
    echo "<p><a href='" . htmlspecialchars($oauth_url) . "' target='_blank'>Test Google OAuth Flow</a></p>";
    echo "<p><small>This should take you to Google login, then back to your redirect URI</small></p>";
} else {
    echo "<p style='color: red;'>Cannot generate OAuth URL - missing configuration</p>";
}

?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
h1 { color: #333; }
h2 { color: #666; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; }
</style>