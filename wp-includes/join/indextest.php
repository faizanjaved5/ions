<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Test basic functionality
echo "<h1>Join Test Page</h1>";
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>PHP Version: " . phpversion() . "</p>";

// Test database connection
try {
    require_once __DIR__ . '/../config/database.php';
    global $db;
    
    if ($db && $db->isConnected()) {
        echo "<p style='color: green;'>✓ Database connected</p>";
        
        // Test query
        $test = $db->get_row("SELECT COUNT(*) as count FROM IONEERS");
        echo "<p>Users in database: " . ($test->count ?? 'Error') . "</p>";
    } else {
        echo "<p style='color: red;'>✗ Database NOT connected</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Database Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test config
try {
    $config = require __DIR__ . '/../config/config.php';
    echo "<p style='color: green;'>✓ Config loaded</p>";
    echo "<p>Site Name: " . ($config['siteName'] ?? 'Not set') . "</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>Config Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test PHPMailer
$phpmailer_files = [
    '/config/phpmailer/PHPMailer.php',
    '/config/phpmailer/Exception.php',
    '/config/phpmailer/SMTP.php'
];

foreach ($phpmailer_files as $file) {
    $full_path = __DIR__ . '/..' . $file;
    if (file_exists($full_path)) {
        echo "<p style='color: green;'>✓ Found: $file</p>";
    } else {
        echo "<p style='color: red;'>✗ Missing: $file</p>";
    }
}

// Test sendotp.php endpoint
$sendotp_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
               '://' . $_SERVER['HTTP_HOST'] . '/login/sendotp.php';
echo "<p>SendOTP URL: " . htmlspecialchars($sendotp_url) . "</p>";

// Simple form to test
?>
<hr>
<h2>Test OTP Sending</h2>
<form method="POST">
    <input type="email" name="test_email" placeholder="Enter email" required>
    <button type="submit">Test Send OTP</button>
</form>

<?php
if (isset($_POST['test_email'])) {
    $email = filter_var($_POST['test_email'], FILTER_VALIDATE_EMAIL);
    if ($email) {
        echo "<h3>Testing OTP for: " . htmlspecialchars($email) . "</h3>";
        
        // Test direct database OTP update
        try {
            $otp = rand(100000, 999999);
            $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            $result = $db->update('IONEERS', [
                'otp_code' => $otp,
                'expires_at' => $expires_at
            ], ['email' => $email]);
            
            if ($result !== false) {
                echo "<p style='color: green;'>✓ OTP saved to database: $otp</p>";
                
                // Verify
                $check = $db->get_row("SELECT otp_code, expires_at FROM IONEERS WHERE email = ?", $email);
                echo "<p>Verified in DB: OTP = " . ($check->otp_code ?? 'NULL') . ", Expires = " . ($check->expires_at ?? 'NULL') . "</p>";
            } else {
                echo "<p style='color: red;'>✗ Failed to save OTP to database</p>";
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>Database Error: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
}
?>

<hr>
<p><a href="/join/">Back to Join Page</a></p>