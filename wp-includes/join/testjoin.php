<?php
// debug-join-index.php - Put in /join/debug-join-index.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting debug...<br>";

// Test 1: Basic PHP
echo "1. PHP works<br>";

// Test 2: Session
session_start();
echo "2. Session started<br>";

// Test 3: Config
$config = require __DIR__ . '/../config/config.php';
echo "3. Config loaded<br>";

// Test 4: Database
require_once __DIR__ . '/../config/database.php';
echo "4. Database included<br>";

// Test 5: Global db
global $db;
if ($db && $db->isConnected()) {
    echo "5. Database connected<br>";
}

// Test 6: Define constants
define('DEBUG_MODE', true);
define('APP_DASHBOARD_PATH', '/app/');
echo "6. Constants defined<br>";

// Test 7: Define function
function ion_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
echo "7. CSRF function defined<br>";

// Test 8: Google OAuth URL
$google_oauth_url = 'https://accounts.google.com/o/oauth2/auth?' . http_build_query([
    'client_id' => $config['google_client_id'],
    'redirect_uri' => $config['google_redirect_uri'],
    'scope' => 'email profile',
    'response_type' => 'code',
    'access_type' => 'online',
    'prompt' => 'select_account',
    'state' => 'join'
]);
echo "8. OAuth URL created<br>";

// Test 9: Check what's in your actual index.php
$index_file = __DIR__ . '/index.php';
if (file_exists($index_file)) {
    echo "9. index.php exists - " . filesize($index_file) . " bytes<br>";
    
    // Check first few lines
    $lines = file($index_file);
    echo "First line: " . htmlspecialchars(trim($lines[0])) . "<br>";
    
    // Check for BOM
    $content = file_get_contents($index_file);
    if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
        echo "<span style='color:red'>WARNING: File has UTF-8 BOM!</span><br>";
    }
}

echo "<br><strong>All tests passed!</strong><br>";
echo "<br>Now testing the actual problem...<br><br>";

// Test 10: Try to include the sendotp inline function
echo "10. Testing sendotp inline function...<br>";

function ion_send_otp_inline(string $email): array {
    echo "Function called for: $email<br>";
    return ['success' => true, 'message' => 'Test'];
}

$result = ion_send_otp_inline('test@example.com');
echo "Function result: " . print_r($result, true) . "<br>";

echo "<br><strong>If you see this, the basic structure works!</strong>";
?>