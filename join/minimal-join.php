<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

echo "<h1>Join Page Debug</h1>";

// Test 1: Load config
try {
    $config = require __DIR__ . '/../config/config.php';
    echo "<p style='color:green'>✓ Config loaded</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>✗ Config error: " . $e->getMessage() . "</p>";
    exit;
}

// Test 2: Load database
try {
    require_once __DIR__ . '/../config/database.php';
    global $db;
    if ($db && $db->isConnected()) {
        echo "<p style='color:green'>✓ Database connected</p>";
    } else {
        throw new Exception("Database not connected");
    }
} catch (Exception $e) {
    echo "<p style='color:red'>✗ Database error: " . $e->getMessage() . "</p>";
    exit;
}

// Test 3: Define minimal functions
function ion_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

echo "<p style='color:green'>✓ Functions defined</p>";

// Test 4: Basic form
?>
<!DOCTYPE html>
<html>
<head>
    <title>Join Test</title>
</head>
<body>
    <h2>Basic Join Form</h2>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ion_csrf_token()) ?>">
        <input type="hidden" name="action" value="test">
        
        <p>
            <label>Full Name: <input type="text" name="fullname" required></label>
        </p>
        <p>
            <label>Email: <input type="email" name="email" required></label>
        </p>
        <p>
            <button type="submit">Submit</button>
        </p>
    </form>
    
    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo "<h3>POST Data Received:</h3>";
        echo "<pre>" . htmlspecialchars(print_r($_POST, true)) . "</pre>";
        
        // Test sendotp.php call
        if (isset($_POST['email'])) {
            $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
            if ($email) {
                echo "<p>Would send OTP to: " . htmlspecialchars($email) . "</p>";
                
                // Test OTP save
                $otp = rand(100000, 999999);
                $result = $db->update('IONEERS', [
                    'otp_code' => $otp,
                    'expires_at' => date('Y-m-d H:i:s', strtotime('+10 minutes'))
                ], ['email' => $email]);
                
                if ($result !== false) {
                    echo "<p style='color:green'>✓ OTP $otp saved to database</p>";
                } else {
                    echo "<p style='color:red'>✗ Failed to save OTP</p>";
                }
            }
        }
    }
    ?>
    
    <hr>
    <p><a href="/join/">Try Full Join Page</a> | <a href="/join/test-join.php">Test Page</a></p>
</body>
</html>