<?php
// google-oauth.php - Debug version with extensive logging
session_start();

// Add comprehensive logging from the start
error_log("=== GOOGLE OAUTH DEBUG START ===");
error_log("Session ID: " . session_id());
error_log("GET parameters: " . print_r($_GET, true));
error_log("Session before processing: " . print_r($_SESSION, true));

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

global $db;

// Test database connection
if (!$db || !$db->isConnected()) {
    error_log("CRITICAL: Database not connected in google-oauth.php");
    $_SESSION['otp_error'] = '❌ Database connection error.';
    header('Location: /login/index.php');
    exit;
} else {
    error_log("Database connection OK in google-oauth.php");
}

$client_id = $config['google_client_id'];
$client_secret = $config['google_client_secret'];
$redirect_uri = $config['google_redirect_uri'];

error_log("Google OAuth Config - Client ID: " . substr($client_id, 0, 10) . "...");
error_log("Google OAuth Config - Redirect URI: " . $redirect_uri);

if (!isset($_GET['code'])) {
    error_log("ERROR: No authorization code received from Google");
    $_SESSION['otp_error'] = '❌ No authorization code received from Google.';
    header('Location: /login/index.php');
    exit;
}

$auth_code = $_GET['code'];
error_log("Received authorization code: " . substr($auth_code, 0, 20) . "...");

// Step 1: Exchange authorization code for access token
$token_url = "https://accounts.google.com/o/oauth2/token";

$post_data = array(
    'code'          => $auth_code,
    'client_id'     => $client_id,
    'client_secret' => $client_secret,
    'redirect_uri'  => $redirect_uri,
    'grant_type'    => 'authorization_code'
);

error_log("Token request data: " . print_r($post_data, true));

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $token_url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Temporarily disable for debugging
curl_setopt($ch, CURLOPT_VERBOSE, true);
$response = curl_exec($ch);

$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
error_log("Token request HTTP code: " . $http_code);

if (curl_error($ch)) {
    $curl_error = curl_error($ch);
    error_log("CURL Error during token request: " . $curl_error);
    curl_close($ch);
    $_SESSION['otp_error'] = '❌ Network error during authentication: ' . $curl_error;
    header('Location: /login/index.php');
    exit;
}

curl_close($ch);
error_log("Token response (raw): " . $response);

$token = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("JSON decode error for token: " . json_last_error_msg());
    $_SESSION['otp_error'] = '❌ Failed to parse token response.';
    header('Location: /login/index.php');
    exit;
}

error_log("Token response (parsed): " . print_r($token, true));

if (!isset($token['access_token'])) {
    error_log("ERROR: No access token in response");
    if (isset($token['error'])) {
        error_log("Token error: " . $token['error'] . " - " . ($token['error_description'] ?? 'No description'));
    }
    $_SESSION['otp_error'] = '❌ Failed to obtain access token from Google.';
    header('Location: /login/index.php');
    exit;
}

$access_token = $token['access_token'];
error_log("Access token obtained: " . substr($access_token, 0, 20) . "...");

// Step 2: Get user info using access token
$userinfo_url = "https://www.googleapis.com/oauth2/v1/userinfo?access_token=" . $access_token;
error_log("Requesting user info from: " . $userinfo_url);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $userinfo_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Temporarily disable for debugging
$response = curl_exec($ch);

$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
error_log("User info request HTTP code: " . $http_code);

if (curl_error($ch)) {
    $curl_error = curl_error($ch);
    error_log("CURL Error getting user info: " . $curl_error);
    curl_close($ch);
    $_SESSION['otp_error'] = '❌ Error retrieving user information: ' . $curl_error;
    header('Location: /login/index.php');
    exit;
}

curl_close($ch);
error_log("User info response (raw): " . $response);

$user_info = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("JSON decode error for user info: " . json_last_error_msg());
    $_SESSION['otp_error'] = '❌ Failed to parse user information.';
    header('Location: /login/index.php');
    exit;
}

error_log("User info (parsed): " . print_r($user_info, true));

if (!isset($user_info['email'])) {
    error_log("ERROR: No email in user info response");
    $_SESSION['otp_error'] = '❌ Could not retrieve email from Google account.';
    header('Location: /login/index.php');
    exit;
}

$email = $user_info['email'];
$current_time = date('Y-m-d H:i:s');

error_log("Processing Google login for email: " . $email);
error_log("Current time: " . $current_time);

// Step 3: Check user authorization in database
try {
    error_log("Checking user in database...");
    $existing_user = $db->get_row("SELECT user_role, login_count, status FROM IONEERS WHERE email = ?", $email);
    error_log("Database query result: " . print_r($existing_user, true));
    
    if (!$existing_user) {
        error_log("User not found in database - creating blocked user");
        
        $insert_result = $db->insert('IONEERS', [
            'email'       => $email,
            'fullname'    => $user_info['name'] ?? '',
            'photo_url'   => $user_info['picture'] ?? null,
            'otp_code'    => null,
            'expires_at'  => null,
            'last_login'  => null,
            'login_count' => 0,
            'user_role'   => 'none',
            'status'      => 'blocked'
        ]);
        
        error_log("Insert result: " . ($insert_result ? 'SUCCESS' : 'FAILED'));
        if (!$insert_result && isset($db->last_error)) {
            error_log("Insert error: " . $db->last_error);
        }
        
        error_log("Unauthorized user added - redirecting to login");
        $_SESSION['otp_error'] = '❌ Access Denied: Your email is not authorized. Please contact an administrator.';
        header('Location: /login/index.php?error=unauthorized');
        exit;
        
    } else {
        error_log("User found - checking authorization");
        error_log("User role: " . ($existing_user->user_role ?? 'NULL'));
        error_log("User status: " . ($existing_user->status ?? 'NULL'));
        
        // Check authorization
        $is_blocked = ($existing_user->status === 'blocked');
        $has_no_role = empty($existing_user->user_role) || 
                      ($existing_user->user_role === 'none') || 
                      ($existing_user->user_role === 'Guest');
        
        error_log("Is blocked: " . ($is_blocked ? 'YES' : 'NO'));
        error_log("Has no role: " . ($has_no_role ? 'YES' : 'NO'));
        
        if ($is_blocked || $has_no_role) {
            error_log("User is not authorized - blocking login");
            $_SESSION['otp_error'] = '❌ Access Denied: Your account is not authorized. Please contact an administrator.';
            header('Location: /login/index.php?error=unauthorized');
            exit;
        }
        
        // User is authorized - update login info
        error_log("User is authorized - updating login info");
        $login_count = ($existing_user->login_count ?? 0) + 1;
        error_log("New login count: " . $login_count);

        $update_result = $db->update('IONEERS', [
            'last_login' => $current_time,
            'login_count' => $login_count,
            'fullname' => $user_info['name'] ?? '',
            'photo_url' => $user_info['picture'] ?? null
        ], ['email' => $email]);

        error_log("Update result: " . ($update_result !== false ? 'SUCCESS' : 'FAILED'));
        if ($update_result === false && isset($db->last_error)) {
            error_log("Update error: " . $db->last_error);
        }

        // Verify the update worked
        $verify_update = $db->get_row("SELECT last_login, login_count FROM IONEERS WHERE email = ?", $email);
        error_log("Verification query result: " . print_r($verify_update, true));

        // Set session variables for successful login
        error_log("Setting session variables for successful login");
        $_SESSION['authenticated'] = true;
        $_SESSION['user_email'] = $email;
        
        error_log("Session after login: " . print_r($_SESSION, true));

        // Clear any error messages
        unset($_SESSION['otp_error']);

        // Determine redirect
        $redirect = $_SESSION['redirect_after_login'] ?? '/app/directory.php';
        unset($_SESSION['redirect_after_login']);
        
        // Security check
        if (!str_starts_with($redirect, '/')) {
            $redirect = '/app/directory.php';
        }
        
        error_log("LOGIN SUCCESS - Redirecting to: " . $redirect);
        error_log("About to send header redirect...");
        
        // Clear any output buffers and send redirect
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        header("Location: " . $redirect);
        error_log("Header sent - exiting");
        exit;
    }
    
} catch (\Throwable $t) {
    error_log("EXCEPTION in google-oauth.php: " . $t->getMessage());
    error_log("Stack trace: " . $t->getTraceAsString());
    $_SESSION['otp_error'] = '❌ Database error during authentication.';
    header('Location: /login/index.php');
    exit;
}

error_log("=== GOOGLE OAUTH DEBUG END (should not reach here) ===");
?>