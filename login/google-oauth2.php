<?php
// google-oauth.php - Complete fixed version
session_start();

$config = require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

global $db;

// Comprehensive error logging
error_log("=== GOOGLE OAUTH COMPLETE VERSION START ===");
error_log("Request URI: " . $_SERVER['REQUEST_URI']);
error_log("HTTP Host: " . $_SERVER['HTTP_HOST']);
error_log("HTTPS: " . (isset($_SERVER['HTTPS']) ? 'YES' : 'NO'));
error_log("Session ID: " . session_id());
error_log("Initial session: " . print_r($_SESSION, true));
error_log("GET params: " . print_r($_GET, true));
error_log("TRACKING: OAuth callback initiated for investigation");

if (!$db || !$db->isConnected()) {
    error_log("FATAL: Database not connected in google-oauth.php");
    $_SESSION['otp_error'] = '❌ Database connection error.';
    header('Location: /login/index.php');
    exit;
}

// Properly access config array
$client_id = $config['google_client_id'] ?? null;
$client_secret = $config['google_client_secret'] ?? null;
$redirect_uri = $config['google_redirect_uri'] ?? null;

// Validate config values
if (!$client_id || !$client_secret || !$redirect_uri) {
    error_log("FATAL: Missing OAuth configuration");
    error_log("Client ID present: " . ($client_id ? 'YES' : 'NO'));
    error_log("Client Secret present: " . ($client_secret ? 'YES' : 'NO'));
    error_log("Redirect URI present: " . ($redirect_uri ? 'YES' : 'NO'));
    $_SESSION['otp_error'] = '❌ OAuth configuration error.';
    header('Location: /login/index.php');
    exit;
}

// Log the configuration
error_log("=== OAuth Configuration ===");
error_log("Client ID: " . substr($client_id, 0, 20) . "...");
error_log("Client Secret length: " . strlen($client_secret));
error_log("Redirect URI from config: " . $redirect_uri);
error_log("Current URL: " . (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);

if (!isset($_GET['code'])) {
    error_log("ERROR: No authorization code from Google");
    error_log("Available GET parameters: " . print_r($_GET, true));
    $_SESSION['otp_error'] = '❌ No authorization code received from Google.';
    header('Location: /login/index.php?error=no_code');
    exit;
}

$auth_code = $_GET['code'];
error_log("Authorization code received: " . substr($auth_code, 0, 20) . "... (length: " . strlen($auth_code) . ")");

// Exchange authorization code for access token
$token_url = "https://oauth2.googleapis.com/token";
$post_data = [
    'code' => $auth_code,
    'client_id' => $client_id,
    'client_secret' => $client_secret,
    'redirect_uri' => $redirect_uri,
    'grant_type' => 'authorization_code'
];

error_log("=== Token Request Details ===");
error_log("Token URL: " . $token_url);
error_log("Post data: " . print_r($post_data, true));

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $token_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$token_response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

error_log("=== Token Response Details ===");
error_log("HTTP Code: " . $http_code);

if (curl_error($ch)) {
    $curl_error = curl_error($ch);
    error_log("CURL error: " . $curl_error);
    curl_close($ch);
    $_SESSION['otp_error'] = '❌ Network error during authentication: ' . $curl_error;
    header('Location: /login/index.php');
    exit;
}

curl_close($ch);

error_log("Raw token response: " . $token_response);

$token_data = json_decode($token_response, true);
$json_error = json_last_error();

error_log("=== Token Response Analysis ===");
error_log("JSON decode error: " . ($json_error === JSON_ERROR_NONE ? 'None' : json_last_error_msg()));
error_log("Parsed token data: " . print_r($token_data, true));

if (!$token_data) {
    error_log("CRITICAL: Failed to parse JSON response");
    $_SESSION['otp_error'] = '❌ Failed to parse token response from Google.';
    header('Location: /login/index.php');
    exit;
}

// Check for Google error response
if (isset($token_data['error'])) {
    error_log("=== Google OAuth Error ===");
    error_log("Error: " . $token_data['error']);
    error_log("Error description: " . ($token_data['error_description'] ?? 'No description'));
    error_log("Error URI: " . ($token_data['error_uri'] ?? 'No URI'));
    
    $error_msg = $token_data['error'];
    if (isset($token_data['error_description'])) {
        $error_msg .= ': ' . $token_data['error_description'];
    }
    
    $_SESSION['otp_error'] = '❌ Google OAuth Error: ' . $error_msg;
    header('Location: /login/index.php');
    exit;
}

if (!isset($token_data['access_token'])) {
    error_log("CRITICAL: No access token in response but no error either");
    $_SESSION['otp_error'] = '❌ No access token received from Google.';
    header('Location: /login/index.php');
    exit;
}

$access_token = $token_data['access_token'];
error_log("SUCCESS: Access token obtained: " . substr($access_token, 0, 20) . "...");

// Get user info
$userinfo_url = "https://www.googleapis.com/oauth2/v2/userinfo";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $userinfo_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$user_response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

error_log("=== User Info Request ===");
error_log("User info HTTP code: " . $http_code);

if (curl_error($ch)) {
    error_log("CURL error getting user info: " . curl_error($ch));
    curl_close($ch);
    $_SESSION['otp_error'] = '❌ Error retrieving user information.';
    header('Location: /login/index.php');
    exit;
}

curl_close($ch);

error_log("User info response: " . $user_response);

$user_data = json_decode($user_response, true);
if (!$user_data || !isset($user_data['email'])) {
    error_log("Failed to get user email: " . print_r($user_data, true));
    $_SESSION['otp_error'] = '❌ Could not retrieve email from Google account.';
    header('Location: /login/index.php');
    exit;
}

$email = trim(strtolower($user_data['email'])); // Clean the email
$name = $user_data['name'] ?? '';
$picture = $user_data['picture'] ?? null;
$google_id = $user_data['id'] ?? null;

error_log("=== User Information ===");
error_log("Email: " . $email);
error_log("Name: " . $name);
error_log("Google ID: " . ($google_id ?: 'none'));

// Send user info to debug capture
$debug_base = 'https://ions.com/app/oauth_debug_capture.php?action=log&message=';
@file_get_contents($debug_base . urlencode("=== GOOGLE OAUTH USER INFO ==="));
@file_get_contents($debug_base . urlencode("Email from Google: '$email'"));
@file_get_contents($debug_base . urlencode("Name from Google: '$name'"));
@file_get_contents($debug_base . urlencode("Google ID: " . ($google_id ?: 'none')));

try {
    // Check if user exists
    error_log("=== USER LOOKUP DEBUG ===");
error_log("Looking for user with email: '$email'");
error_log("Email length: " . strlen($email));
error_log("Email trim test: '" . trim($email) . "'");

// Also send to debug capture for easier viewing
$debug_base = 'https://ions.com/app/oauth_debug_capture.php?action=log&message=';
@file_get_contents($debug_base . urlencode("=== OAUTH USER LOOKUP START ==="));
@file_get_contents($debug_base . urlencode("Looking for email: '$email'"));
@file_get_contents($debug_base . urlencode("Email length: " . strlen($email)));

$existing_user = $db->get_row("SELECT * FROM IONEERS WHERE email = ?", $email);
error_log("Database query executed: SELECT * FROM IONEERS WHERE email = '$email'");
error_log("Query result type: " . gettype($existing_user));
error_log("Query result: " . print_r($existing_user, true));

@file_get_contents($debug_base . urlencode("Query result type: " . gettype($existing_user)));
@file_get_contents($debug_base . urlencode("User found: " . ($existing_user ? "YES" : "NO")));

// Additional check with different query method
$user_count = $db->get_var("SELECT COUNT(*) FROM IONEERS WHERE email = ?", $email);
error_log("User count query result: $user_count");
@file_get_contents($debug_base . urlencode("User count: $user_count"));

// Check if there are any users in the table at all
$total_users = $db->get_var("SELECT COUNT(*) FROM IONEERS");
error_log("Total users in IONEERS table: $total_users");
@file_get_contents($debug_base . urlencode("Total users: $total_users"));

if ($existing_user) {
    @file_get_contents($debug_base . urlencode("Found user role: " . ($existing_user->user_role ?? 'NULL')));
    @file_get_contents($debug_base . urlencode("Found user status: " . ($existing_user->status ?? 'NULL')));
} else {
    @file_get_contents($debug_base . urlencode("NO USER FOUND - will create new blocked user"));
}

@file_get_contents($debug_base . urlencode("=== OAUTH USER LOOKUP END ==="));

error_log("=== END USER LOOKUP DEBUG ===");
    
    $current_time = date('Y-m-d H:i:s');
    
    if (!$existing_user) {
        // New user - add as blocked with 'None' role (correct enum value)
        error_log("Adding new blocked user: " . $email);
        
        $insert_data = [
            'email' => $email,
            'fullname' => $name,
            'photo_url' => $picture,
            'google_id' => $google_id,
            'user_role' => 'None',  // Correct enum value (capital N)
            'status' => 'blocked',
            'last_login' => null,
            'login_count' => 0
        ];
        
        error_log("Inserting user data: " . print_r($insert_data, true));
        
        $insert_result = $db->insert('IONEERS', $insert_data);
        error_log("Insert result: " . ($insert_result ? 'SUCCESS' : 'FAILED'));
        
        if (!$insert_result) {
            error_log("Insert failed. Last error: " . ($db->last_error ?? 'none'));
        }
        
        // Redirect unauthorized user
        error_log("Redirecting unauthorized user to login");
        
        // Debug capture for new user creation
        @file_get_contents($debug_base . urlencode("CREATING NEW BLOCKED USER - User not found in database"));
        @file_get_contents($debug_base . urlencode("ERROR: Access Denied - Your email is not authorized"));
        
        $_SESSION['otp_error'] = '❌ Access Denied: Your email is not authorized. Please contact an administrator.';
        header('Location: /login/index.php?error=unauthorized');
        exit;
        
    } else {
        // Existing user - check authorization
        $user_role = trim($existing_user->user_role ?? '');
        $status = trim($existing_user->status ?? '');
        
        error_log("=== UPLOADER AUTH DEBUG START ===");
        error_log("Checking authorization for email: $email");
        error_log("Raw role from DB: '" . ($existing_user->user_role ?? 'NULL') . "'");
        error_log("Raw status from DB: '" . ($existing_user->status ?? 'NULL') . "'");
        error_log("Trimmed role: '$user_role' (length: " . strlen($user_role) . ")");
        error_log("Trimmed status: '$status' (length: " . strlen($status) . ")");
        
        // Check if user is authorized (using correct enum values)
        // Handle case sensitivity and common variations
        $blocked_roles = ['None', 'none', 'Guest', 'guest', ''];  // Include common variations
        $valid_roles = ['Owner', 'Admin', 'Creator', 'Uploader'];  // Include both Creator and Uploader for compatibility
        
        $is_blocked_role = in_array($user_role, $blocked_roles);
        $is_valid_role = in_array($user_role, $valid_roles);
        $is_blocked_status = in_array(strtolower($status), ['blocked', 'inactive', 'disabled']);
        
        $is_authorized = $is_valid_role && !$is_blocked_status;
        
        error_log("Is blocked role: " . ($is_blocked_role ? 'YES' : 'NO'));
        error_log("Is valid role: " . ($is_valid_role ? 'YES' : 'NO'));  
        error_log("Is blocked status: " . ($is_blocked_status ? 'YES' : 'NO'));
        error_log("Is authorized: " . ($is_authorized ? 'YES' : 'NO'));
        error_log("Valid roles array: " . json_encode($valid_roles));
        error_log("Blocked status check: status '$status' -> lower: '" . strtolower($status) . "' -> in_array result: " . (in_array(strtolower($status), ['blocked', 'inactive', 'disabled']) ? 'TRUE' : 'FALSE'));
        
        // FORCE AUTHORIZATION FOR DEBUGGING - REMOVE AFTER TESTING
        if ($user_role === 'Creator' && $status === 'active') {
            error_log("FORCE AUTHORIZATION: Creator with active status - bypassing authorization check");
            $is_authorized = true;
        }
        error_log("=== UPLOADER AUTH DEBUG END ===");
        
        if (!$is_authorized) {
            $reason = '';
            if (!$is_valid_role) {
                $reason = "Invalid role: '$user_role'. Valid roles are: " . implode(', ', $valid_roles);
            } elseif ($is_blocked_status) {
                $reason = "Account status: '$status' is blocked";
            }
            
            error_log("User not authorized - redirecting. Reason: $reason");
            $_SESSION['otp_error'] = '❌ Access Denied: Your account is not authorized. Please contact an administrator.';
            
            // For debugging purposes, also log the specific reason
            error_log("AUTHORIZATION FAILURE for $email: $reason");
            
            header('Location: /login/index.php?error=unauthorized');
            exit;
        }
        
        // User is authorized - update login info
        error_log("User is authorized - updating login info");
        
        // Debug capture for successful authorization
        @file_get_contents($debug_base . urlencode("SUCCESS: User found and authorized"));
        @file_get_contents($debug_base . urlencode("User role: '$user_role', Status: '$status'"));
        
        $current_login_count = intval($existing_user->login_count ?? 0);
        $new_login_count = $current_login_count + 1;
        
        error_log("Updating login info - Current count: $current_login_count, New count: $new_login_count");
        
        $update_data = [
            'last_login' => $current_time,
            'login_count' => $new_login_count,
            'fullname' => $name,
            'photo_url' => $picture,
            'google_id' => $google_id
        ];
        
        error_log("Update data: " . print_r($update_data, true));
        
        $update_result = $db->update('IONEERS', $update_data, ['email' => $email]);
        error_log("Update result: " . ($update_result !== false ? 'SUCCESS' : 'FAILED'));
        
        if ($update_result === false) {
            error_log("Update failed. Last error: " . ($db->last_error ?? 'none'));
        }
        
        // Verify the update
        $verify_user = $db->get_row("SELECT last_login, login_count, google_id FROM IONEERS WHERE email = ?", $email);
        error_log("Verification after update: " . print_r($verify_user, true));
        
        // Set session for successful login
        error_log("Setting session variables for successful login");
        
        // Clear any existing session data that might interfere
        unset($_SESSION['otp_error']);
        unset($_SESSION['pending_email']);
        
        // Set session variables
        $_SESSION['authenticated'] = true;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_role'] = $user_role;  // CRITICAL: Add missing role to session
        $_SESSION['user_id'] = $existing_user->user_id;  // Add user ID for proper matching
        $_SESSION['user_name'] = $name;  // Add user name for display
        $_SESSION['last_activity'] = time();
        $_SESSION['session_regenerated'] = time();
        $_SESSION['user_handle'] = $existing_user->handle ?? '';
        
        // Debug capture for session setting
        @file_get_contents($debug_base . urlencode("Setting session variables for: $email"));
        @file_get_contents($debug_base . urlencode("Session authenticated: " . ($_SESSION['authenticated'] ? "TRUE" : "FALSE")));
        @file_get_contents($debug_base . urlencode("Session email: " . ($_SESSION['user_email'] ?? "NOT SET")));
        
        error_log("Session after login: " . print_r($_SESSION, true));
        
        // Force session write and restart to ensure it's saved
        session_write_close();
        session_start();
        
        error_log("Session after restart: " . print_r($_SESSION, true));
        
        // Redirect to admin directory
        error_log("SUCCESS - User $email with role '$user_role' authenticated successfully");
        error_log("SUCCESS - Redirecting to /app/directory.php");
        error_log("TRACKING: About to redirect authenticated user");
        
        // Debug capture for redirect
        @file_get_contents($debug_base . urlencode("OAUTH COMPLETE - Redirecting to /app/directory.php"));
        @file_get_contents($debug_base . urlencode("Final session email: " . ($_SESSION['user_email'] ?? "NOT SET")));
        @file_get_contents($debug_base . urlencode("Final session authenticated: " . ($_SESSION['authenticated'] ? "TRUE" : "FALSE")));
        
        // Clear any output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        header('Location: /app/directory.php');
        exit;
    }
    
} catch (Exception $e) {
    error_log("Database exception: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    $_SESSION['otp_error'] = '❌ Database error during authentication.';
    header('Location: /login/index.php');
    exit;
}

error_log("=== GOOGLE OAUTH COMPLETE END (should not reach here) ===");
?>