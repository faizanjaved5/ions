<?php
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load dependencies
require_once __DIR__ . '/../config/database.php';
$config = require __DIR__ . '/../config/config.php';

// Check if this is from join flow
$from_join = isset($_POST['from_join']) && $_POST['from_join'] == '1';

// Debug: Log ALL incoming data
error_log("=== DEBUG verifyotp.php START ===");
error_log("DEBUG verifyotp.php - From join flow: " . ($from_join ? 'YES' : 'NO'));
error_log("DEBUG verifyotp.php - Raw \$_POST: " . print_r($_POST, true));
error_log("DEBUG verifyotp.php - Raw \$_REQUEST: " . print_r($_REQUEST, true));

// Handle both array and string OTP formats
$otp_raw = $_POST['otp'] ?? '';
if (is_array($otp_raw)) {
    $otp = implode('', $otp_raw);
    error_log("DEBUG verifyotp.php - OTP from array: " . print_r($otp_raw, true) . " -> '$otp'");
} else {
    $otp = preg_replace('/\D/', '', $otp_raw);
    error_log("DEBUG verifyotp.php - OTP from string: '$otp_raw' -> '$otp'");
}

$email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);

// Debug: Log processed values
error_log("DEBUG verifyotp.php - Final values - Email: '$email', OTP: '$otp', OTP length: " . strlen($otp));

// Validate input
if (!$email || !preg_match('/^\d{6}$/', $otp)) {
    error_log("DEBUG verifyotp.php - Input validation failed - Email valid: " . ($email ? 'yes' : 'no') . ", OTP format valid: " . (preg_match('/^\d{6}$/', $otp) ? 'yes' : 'no'));
    $_SESSION['otp_error'] = 'Please enter a valid 6-digit code.';
    $_SESSION['pending_email'] = $email ?: '';
    
    // Redirect based on flow
    if ($from_join) {
        $_SESSION['pending_otp_email'] = $email;
        header("Location: /join/index.php");
    } else {
        header("Location: index.php?show_otp=1&error=invalid_format");
    }
    exit;
}

global $db;

// Debug: Check what's in the database
$debug_query = "SELECT email, otp_code, expires_at, user_role, UNIX_TIMESTAMP(expires_at) as exp_timestamp, UNIX_TIMESTAMP() as current_timestamp FROM IONEERS WHERE email = ?";
$debug_results = $db->get_results($debug_query, $email);
error_log("DEBUG verifyotp.php - All OTP records for email '$email': " . print_r($debug_results, true));

// Prepare log
$logData = [
    'email'      => $email,
    'ip'         => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    'timestamp'  => date('Y-m-d H:i:s')
];

try {
    // First check if there's any OTP for this email
    $any_otp = $db->get_row("SELECT otp_code, expires_at, last_login, login_count, user_role, user_id FROM IONEERS WHERE email = ?", $email);
    error_log("DEBUG verifyotp.php - Any OTP for email '$email': " . print_r($any_otp, true));
    
    // Now check for exact match
    $query = "SELECT user_id, otp_code, expires_at, last_login, login_count, user_role FROM IONEERS WHERE email = ? AND otp_code = ?";
    error_log("DEBUG verifyotp.php - Exact match query: $query");
    error_log("DEBUG verifyotp.php - Query parameters: email='$email', otp='$otp'");
    
    $row = $db->get_row($query, $email, $otp);
    error_log("DEBUG verifyotp.php - Exact match result: " . print_r($row, true));
    
    if (!$row) {
        error_log("DEBUG verifyotp.php - No matching OTP found");
        
        // Show detailed comparison for debugging
        if ($any_otp && $any_otp->otp_code) {
            $stored = (string)$any_otp->otp_code;
            $entered = (string)$otp;
            error_log("❌ OTP MISMATCH:");
            error_log("   Stored in DB: '$stored' (length: " . strlen($stored) . ", type: " . gettype($any_otp->otp_code) . ")");
            error_log("   User entered: '$entered' (length: " . strlen($entered) . ", type: " . gettype($otp) . ")");
            error_log("   Character comparison:");
            for ($i = 0; $i < max(strlen($stored), strlen($entered)); $i++) {
                $s_char = isset($stored[$i]) ? $stored[$i] : 'NONE';
                $e_char = isset($entered[$i]) ? $entered[$i] : 'NONE';
                $match = ($s_char === $e_char) ? '✓' : '✗';
                error_log("   Position $i: DB='$s_char' vs Entered='$e_char' $match");
            }
        }
        
        $logData['status'] = 'failed';
        $db->insert('IONLoginLogs', $logData);
        $_SESSION['otp_error'] = 'The code you entered is invalid. Please try again.';
        $_SESSION['pending_email'] = $email;
        
        // Redirect based on flow
        if ($from_join) {
            $_SESSION['pending_otp_email'] = $email;
            header("Location: /join/index.php");
        } else {
            header("Location: index.php?show_otp=1&error=invalid_otp");
        }
        exit;
    }

    // Check expiration
    $exp_time = strtotime($row->expires_at);
    $current_time = time();
    error_log("DEBUG verifyotp.php - Expiration check: expires_at='$row->expires_at', exp_time=$exp_time, current_time=$current_time, difference=" . ($exp_time - $current_time) . " seconds");
    
    if ($exp_time < $current_time) {
        error_log("DEBUG verifyotp.php - OTP expired");
        $logData['status'] = 'expired';
        $db->insert('IONLoginLogs', $logData);
        $_SESSION['otp_error'] = 'Your code has expired. Please request a new one.';
        $_SESSION['pending_email'] = $email;
        
        // Redirect based on flow
        if ($from_join) {
            $_SESSION['pending_otp_email'] = $email;
            header("Location: /join/index.php");
        } else {
            header("Location: index.php?show_otp=1&error=expired_otp");
        }
        exit;
    }

    // Valid login - Log success BEFORE updating database
    error_log("DEBUG verifyotp.php - OTP validation successful!");
    $logData['status'] = 'success';
    $db->insert('IONLoginLogs', $logData);

    // Calculate new login count PROPERLY
    $current_login_count = intval($row->login_count ?? 0);
    $new_login_count = $current_login_count + 1;
    error_log("DEBUG verifyotp.php - Login count calculation: current=$current_login_count, new=$new_login_count");

    // Clear OTP and update login info
    $update_data = [
        'otp_code' => null,
        'expires_at' => null,
        'last_login' => date('Y-m-d H:i:s'),
        'login_count' => $new_login_count
    ];
    
    // For join flow, update user role from Guest to Creator
    if ($from_join && $row->user_role === 'Guest') {
        $update_data['user_role'] = 'Creator';
        error_log("DEBUG verifyotp.php - Updating Guest to Creator for join flow");
    }
    
    error_log("DEBUG verifyotp.php - About to update with data: " . print_r($update_data, true));
    
    $update_result = $db->update('IONEERS', $update_data, ['email' => $email]);
    
    error_log("DEBUG verifyotp.php - Update result: " . ($update_result !== false ? 'SUCCESS' : 'FAILED'));
    if ($update_result === false && isset($db->last_error)) {
        error_log("DEBUG verifyotp.php - Update error: " . $db->last_error);
    }
    
    // Verify the update worked
    $verify_update = $db->get_row("SELECT otp_code, expires_at, last_login, login_count, user_role FROM IONEERS WHERE email = ?", $email);
    error_log("DEBUG verifyotp.php - Post-update verification: " . print_r($verify_update, true));

    // Set session variables for successful login
    $_SESSION['authenticated'] = true;
    $_SESSION['user_email'] = $email;
    $_SESSION['user_id'] = $row->user_id;
    $_SESSION['user_role'] = $from_join && $row->user_role === 'Guest' ? 'Creator' : $row->user_role;
    $_SESSION['last_activity'] = time();
    $_SESSION['session_regenerated'] = time();
    // Fetch and set user handle for session
    try {
        $handle_row = $db->get_row("SELECT handle FROM IONEERS WHERE user_id = ?", $row->user_id);
        if ($handle_row && isset($handle_row->handle)) {
            $_SESSION['user_handle'] = $handle_row->handle;
        }
    } catch (Exception $e) {
        // ignore handle fetch failure
    }

    // Clear any error messages
    unset($_SESSION['otp_error']);
    unset($_SESSION['pending_email']);
    unset($_SESSION['pending_otp_email']);
    unset($_SESSION['otp_sent_time']);

    // For join flow, set profile wizard flag
    if ($from_join) {
        $_SESSION['show_profile_wizard'] = true;
        error_log("DEBUG verifyotp.php - Join flow complete, setting profile wizard flag");
    }

    // Redirect logic with explicit default
    $redirect = $_SESSION['redirect_after_login'] ?? '/app/directory.php';
    unset($_SESSION['redirect_after_login']);
    
    // Security check
    if (!str_starts_with($redirect, '/')) {
        $redirect = '/app/directory.php';
    }
    
    error_log("DEBUG verifyotp.php - SUCCESS! Redirecting to: $redirect");
    error_log("=== DEBUG verifyotp.php END ===");
    
    // Clear any output buffers before redirect
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Check if this is opened in a popup window (has opener)
    // If so, notify parent and close popup
    echo '<script>
        if (window.opener && window.opener !== window) {
            // Notify parent window of successful login
            window.opener.postMessage({type: "login-success"}, window.location.origin);
            // Close popup window
            window.close();
        } else {
            // Normal login flow - redirect
            window.location.href = "' . htmlspecialchars($redirect, ENT_QUOTES) . '";
        }
    </script>';
    exit;
    
} catch (\Throwable $t) {
    error_log("DEBUG verifyotp.php - EXCEPTION: " . $t->getMessage());
    error_log("DEBUG verifyotp.php - Stack trace: " . $t->getTraceAsString());
    
    $_SESSION['otp_error'] = 'A database error occurred. Please try again.';
    $_SESSION['pending_email'] = $email;
    
    // Redirect based on flow
    if ($from_join) {
        $_SESSION['pending_otp_email'] = $email;
        header("Location: /join/index.php");
    } else {
        header("Location: index.php?show_otp=1&error=database");
    }
    exit;
}
?>