<?php
// session.php - Complete fixed version with proper OAuth handling
session_start();
require_once __DIR__ . '/../config/database.php';

// --- IDENTIFY CURRENT CONTEXT ---
$currentScript = basename($_SERVER['SCRIPT_NAME']);
$isLoginPage = ($currentScript === 'index.php' && strpos(__DIR__, '/login') !== false);
$isOAuthCallback = ($currentScript === 'google-oauth.php');
$isLogoutPage = ($currentScript === 'logout.php');

error_log("Session.php - Current script: $currentScript, Is login: " . ($isLoginPage ? 'yes' : 'no') . ", Is OAuth: " . ($isOAuthCallback ? 'yes' : 'no'));

// --- SKIP ALL CHECKS FOR OAUTH CALLBACK ---
if ($isOAuthCallback) {
    error_log("Session.php - Skipping all checks for OAuth callback");
    return; // Let google-oauth.php handle everything
}

// --- REDIRECT TO LOGIN IF NOT AUTHENTICATED ---
if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated']) {
    if (!$isLoginPage && !$isLogoutPage) {
        error_log("Session.php - User not authenticated, redirecting to login");
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: /login/index.php');
        exit();
    }
}

// --- IF ALREADY LOGGED IN AND ACCESSING LOGIN PAGE ---
if ($isLoginPage && isset($_SESSION['authenticated']) && $_SESSION['authenticated']) {
    $redirect = $_SESSION['redirect_after_login'] ?? '/app/directory.php';
    unset($_SESSION['redirect_after_login']);
    
    error_log("Session.php - User already authenticated, redirecting to: $redirect");
    
    // Ensure redirect is safe
    if (!str_starts_with($redirect, '/')) {
        $redirect = '/app/directory.php';
    }
    
    header("Location: " . $redirect);
    exit();
}

// --- SESSION TIMEOUT AFTER 30 MIN ---
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    error_log("Session.php - Session timeout");
    session_unset();
    session_destroy();
    header('Location: /login/index.php?timeout=1');
    exit();
}

// --- SESSION FRESHNESS ---
$_SESSION['last_activity'] = time();
if (!isset($_SESSION['session_regenerated']) || $_SESSION['session_regenerated'] < (time() - 300)) {
    session_regenerate_id(true);
    $_SESSION['session_regenerated'] = time();
}

// --- USER STILL EXISTS CHECK (only for authenticated users) ---
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] && isset($_SESSION['user_email'])) {
    global $db;
    try {
        $user_email = $_SESSION['user_email'];
        error_log("TRACKING SESSION: Checking user existence for: $user_email");
        
        // Updated query to handle status correctly - include 'active' users
        $user_exists = $db->get_var("SELECT COUNT(*) FROM IONEERS WHERE email = ? AND (status = 'active' OR status IS NULL OR status = '')", $user_email);
        error_log("TRACKING SESSION: User exists check result: $user_exists");
        
        if (!$user_exists) {
            error_log("Session.php - User no longer exists or is blocked: " . $user_email);
            error_log("TRACKING SESSION: USER NOT FOUND/BLOCKED - destroying session");
            session_unset();
            session_destroy();
            header('Location: /login/index.php?error=unauthorized');
            exit();
        } else {
            error_log("TRACKING SESSION: User exists and is active");
        }
    } catch (Exception $e) {
        error_log("Session.php - Database error checking user: " . $e->getMessage());
        // Don't kill session on database errors, just log
    }
}

// --- MESSAGE HANDLER ---
$message = '';
$message_type = '';
if (isset($_GET['timeout'])) {
    $message = 'Your session has expired. Please log in again.';
    $message_type = 'timeout';
} elseif (isset($_GET['error'])) {
    $errors = [
        'unauthorized'   => 'Your access has been revoked. Please contact an administrator.',
        'invalid_email'  => 'Please enter a valid email address.',
        'database'       => 'A database error occurred. Please try again.',
        'email_send'     => 'We could not send the OTP. Please try again later.',
        'invalid_format' => 'Invalid email or OTP format.',
        'invalid_otp'    => 'The code you entered is invalid. Please try again.',
        'expired_otp'    => 'Your OTP expired. Please request a new one.',
        'oauth_failed'   => 'Google OAuth failed. Please try again.',
        'no_code'        => 'No authorization code received from Google.',
        'network_error'  => 'Network error during Google authentication.',
        'token_failed'   => 'Failed to obtain access token from Google.',
        'userinfo_error' => 'Error retrieving user information from Google.',
        'email_failed'   => 'Could not retrieve email from Google account.',
        'exception'      => 'An error occurred during authentication.',
        'fatal'          => 'A system error occurred. Please try again later.'
    ];
    $key = $_GET['error'];
    $message = $errors[$key] ?? 'Unknown error.';
    $message_type = 'error';
} elseif (isset($_GET['otp_sent'])) {
    $message = 'OTP has been sent to your email. Please check your inbox.';
    $message_type = 'success';
}

// Handle session-stored error messages (like from OAuth)
if (isset($_SESSION['otp_error'])) {
    $message = $_SESSION['otp_error'];
    $message_type = 'error';
    unset($_SESSION['otp_error']);
}
?>