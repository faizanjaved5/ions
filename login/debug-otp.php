<?php
/**
 * OTP Debug Script
 * 
 * This script shows the current OTP data for a given email address.
 * Usage: Navigate to /iblog/login/debug-otp.php?email=your@email.com
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/database.php';

// Get email from query string
$email = filter_var($_GET['email'] ?? '', FILTER_VALIDATE_EMAIL);

if (!$email) {
    echo "<h1>OTP Debug Tool</h1>";
    echo "<p>Please provide an email address in the URL:</p>";
    echo "<p>Example: <code>debug-otp.php?email=your@email.com</code></p>";
    exit;
}

global $db;

// Fetch OTP data
$user_data = $db->get_row("SELECT email, otp_code, expires_at, user_role, status, UNIX_TIMESTAMP(expires_at) as exp_timestamp, UNIX_TIMESTAMP() as current_timestamp FROM IONEERS WHERE email = ?", $email);

?>
<!DOCTYPE html>
<html>
<head>
    <title>OTP Debug - <?= htmlspecialchars($email) ?></title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #4CAF50;
            padding-bottom: 10px;
        }
        .info-row {
            padding: 10px;
            margin: 10px 0;
            background: #f9f9f9;
            border-left: 4px solid #2196F3;
            border-radius: 4px;
        }
        .label {
            font-weight: bold;
            color: #555;
            display: inline-block;
            width: 200px;
        }
        .value {
            color: #000;
            font-family: 'Courier New', monospace;
            background: #fff;
            padding: 2px 6px;
            border-radius: 3px;
        }
        .error {
            background: #ffebee;
            border-left-color: #f44336;
            color: #c62828;
        }
        .success {
            background: #e8f5e9;
            border-left-color: #4CAF50;
            color: #2e7d32;
        }
        .warning {
            background: #fff3e0;
            border-left-color: #ff9800;
            color: #e65100;
        }
        .debug-section {
            margin-top: 30px;
            padding: 20px;
            background: #f0f0f0;
            border-radius: 4px;
        }
        .code {
            background: #263238;
            color: #aed581;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 OTP Debug Tool</h1>
        <p><strong>Email:</strong> <?= htmlspecialchars($email) ?></p>
        
        <?php if (!$user_data): ?>
            <div class="info-row error">
                <strong>❌ No user found with this email address.</strong>
            </div>
        <?php else: ?>
            
            <div class="info-row">
                <span class="label">User Role:</span>
                <span class="value"><?= htmlspecialchars($user_data->user_role ?? 'NULL') ?></span>
            </div>
            
            <div class="info-row">
                <span class="label">Account Status:</span>
                <span class="value"><?= htmlspecialchars($user_data->status ?? 'NULL') ?></span>
            </div>
            
            <div class="info-row <?= $user_data->otp_code ? 'success' : 'warning' ?>">
                <span class="label">OTP Code (Stored):</span>
                <span class="value"><?= $user_data->otp_code ? htmlspecialchars($user_data->otp_code) : 'NULL (no OTP)' ?></span>
            </div>
            
            <?php if ($user_data->otp_code): ?>
            <div class="info-row">
                <span class="label">OTP Type:</span>
                <span class="value"><?= gettype($user_data->otp_code) ?></span>
            </div>
            
            <div class="info-row">
                <span class="label">OTP Length:</span>
                <span class="value"><?= strlen($user_data->otp_code) ?> characters</span>
            </div>
            <?php endif; ?>
            
            <div class="info-row">
                <span class="label">Expires At:</span>
                <span class="value"><?= $user_data->expires_at ?: 'NULL' ?></span>
            </div>
            
            <?php if ($user_data->expires_at): ?>
                <?php
                $exp_time = strtotime($user_data->expires_at);
                $current_time = time();
                $diff_seconds = $exp_time - $current_time;
                $diff_minutes = round($diff_seconds / 60, 1);
                $is_expired = $diff_seconds < 0;
                ?>
                <div class="info-row <?= $is_expired ? 'error' : 'success' ?>">
                    <span class="label">Expiration Status:</span>
                    <span class="value">
                        <?php if ($is_expired): ?>
                            ❌ EXPIRED (<?= abs($diff_minutes) ?> minutes ago)
                        <?php else: ?>
                            ✅ VALID (expires in <?= $diff_minutes ?> minutes)
                        <?php endif; ?>
                    </span>
                </div>
            <?php endif; ?>
            
            <div class="debug-section">
                <h3>📝 Technical Details</h3>
                <div class="code">
                    <?php
                    echo "Database Query Result:\n";
                    echo "=====================\n";
                    print_r($user_data);
                    ?>
                </div>
                
                <?php if ($user_data->otp_code): ?>
                <div class="code">
                    <?php
                    echo "Character Analysis:\n";
                    echo "==================\n";
                    $otp_string = (string)$user_data->otp_code;
                    for ($i = 0; $i < strlen($otp_string); $i++) {
                        printf("Position %d: '%s' (ASCII: %d)\n", $i, $otp_string[$i], ord($otp_string[$i]));
                    }
                    ?>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="debug-section">
                <h3>🔧 Troubleshooting</h3>
                <?php if (!$user_data->otp_code): ?>
                    <p>✅ <strong>Solution:</strong> Request a new OTP code from the login page.</p>
                <?php elseif ($is_expired): ?>
                    <p>✅ <strong>Solution:</strong> Your OTP has expired. Request a new code.</p>
                <?php else: ?>
                    <p>✅ Your OTP appears valid. If you're still having issues:</p>
                    <ul>
                        <li>Make sure you're entering all 6 digits exactly as shown in the email</li>
                        <li>Check for any extra spaces or characters</li>
                        <li>The code is: <strong style="font-size: 20px; color: #4CAF50;"><?= htmlspecialchars($user_data->otp_code) ?></strong></li>
                    </ul>
                <?php endif; ?>
            </div>
            
        <?php endif; ?>
    </div>
</body>
</html>

