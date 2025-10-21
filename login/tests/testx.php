<?php
// google-oauth.php - Simplified version with direct session handling
// Start session but don't include session.php to avoid redirect conflicts
session_start();

// Comprehensive error logging
error_log("=== GOOGLE OAUTH SIMPLE START ===");
error_log("Request URI: " . $_SERVER['REQUEST_URI']);
error_log("Session ID: " . session_id());
error_log("Initial session: " . print_r($_SESSION, true));
error_log("GET params: " . print_r($_GET, true));

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

global $db;

// Check database connection
if (!$db || !$db->isConnected()) {
    error_log("FATAL: Database not connected");
    die("Database connection failed");
}
error_log("Database connection confirmed");

$client_id = $config['google_client_id'];
$client_secret = $config['google_client_secret'];
$redirect_uri = $config['google_redirect_uri'];

// Validate we have the authorization code
if (!isset($_GET['code'])) {
    error_log("ERROR: No authorization code from Google");
    header('Location: /login/index.php?error=oauth_failed');
    exit;
}

$auth_code = $_GET['code'];
error_log("Authorization code received: " . substr($auth_code, 0, 15) . "...");

// Step 1: Exchange code for access token
$token_url = "https://oauth2.googleapis.com/token";  // Updated URL
$post_data = [
    'code' => $auth_code,
    'client_id' => $client_id,
    'client_secret' => $client_secret,
    'redirect_uri' => $redirect_uri,
    'grant_type' => 'authorization_code'
];

error_log("Token request to: " . $token_url);
error_log("Post data: " . print_r($post_data, true));

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $token_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Temporarily for debugging
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$token_response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_error($ch)) {
    $error = curl_error($ch);
    error_log("CURL error: " . $error);
    curl_close($ch);
    header('Location: /login/index.php?error=network_error');
    exit;
}

curl_close($ch);
error_log("Token response HTTP code: " . $http_code);
error_log("Token response: " . $token_response);

$token_data = json_decode($token_response, true);
if (!$token_data || !isset($token_data['access_token'])) {
    error_log("Failed to get access token: " . print_r($token_data, true));
    header('Location: /login/index.php?error=token_failed');
    exit;
}

$access_token = $token_data['access_token'];
error_log("Access token obtained: " . substr($access_token, 0, 15) . "...");

// Step 2: Get user info
$userinfo_url = "https://www.googleapis.com/oauth2/v2/userinfo?access_token=" . urlencode($access_token);
error_log("Getting user info from: " . $userinfo_url);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $userinfo_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$user_response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_error($ch)) {
    $error = curl_error($ch);
    error_log("CURL error getting user info: " . $error);
    curl_close($ch);
    header('Location: /login/index.php?error=userinfo_error');
    exit;
}

curl_close($ch);
error_log("User info HTTP code: " . $http_code);
error_log("User info response: " . $user_response);

$user_data = json_decode($user_response, true);
if (!$user_data || !isset($user_data['email'])) {
    error_log("Failed to get user email: " . print_r($user_data, true));
    header('Location: /login/index.php?error=email_failed');
    exit;
}

$email = $user_data['email'];
$name = $user_data['name'] ?? '';
$picture = $user_data['picture'] ?? null;

error_log("User authenticated: " . $email);
error_log("User name: " . $name);
error_log("User picture: " . ($picture ?: 'none'));

// Step 3: Database operations
try {
    // Check if user exists
    $existing_user = $db->get_row("SELECT * FROM IONEERS WHERE email = ?", $email);
    error_log("Existing user query result: " . print_r($existing_user, true));
    
    $current_time = date('Y-m-d H:i:s');
    
    if (!$existing_user) {
        // New user - add as blocked
        error_log("Adding new blocked user: " . $email);
        
        $insert_data = [
            'email' => $email,
            'fullname' => $name,
            'photo_url' => $picture,
            'user_role' => 'none',
            'status' => 'blocked',
            'last_login' => null,  // Don't set login time for blocked users
            'login_count' => 0,
            'otp_code' => null,
            'expires_at' => null
        ];
        
        error_log("Inserting user data: " . print_r($insert_data, true));
        
        $insert_result = $db->insert('IONEERS', $insert_data);
        error_log("Insert result: " . ($insert_result ? 'SUCCESS' : 'FAILED'));
        
        if (!$insert_result) {
            error_log("Insert failed. Last error: " . ($db->last_error ?? 'No error info'));
        }
        
        // Redirect to login with unauthorized message
        error_log("Redirecting unauthorized user to login");
        header('Location: /login/index.php?error=unauthorized');
        exit;
        
    } else {
        // Existing user - check authorization
        $user_role = $existing_user->user_role ?? '';
        $status = $existing_user->status ?? '';
        
        error_log("Checking authorization - Role: '$user_role', Status: '$status'");
        
        // Check if user is authorized
        $is_authorized = !empty($user_role) && 
                        $user_role !== 'none' && 
                        $user_role !== 'Guest' && 
                        $status !== 'blocked';
        
        error_log("User authorized: " . ($is_authorized ? 'YES' : 'NO'));
        
        if (!$is_authorized) {
            error_log("User not authorized - redirecting");
            header('Location: /login/index.php?error=unauthorized');
            exit;
        }
        
        // User is authorized - update login info
        $current_login_count = intval($existing_user->login_count ?? 0);
        $new_login_count = $current_login_count + 1;
        
        error_log("Updating login info - Current count: $current_login_count, New count: $new_login_count");
        
        $update_data = [
            'last_login' => $current_time,
            'login_count' => $new_login_count,
            'fullname' => $name,  // Update name from Google
            'photo_url' => $picture  // Update picture from Google
        ];
        
        error_log("Update data: " . print_r($update_data, true));
        
        $update_result = $db->update('IONEERS', $update_data, ['email' => $email]);
        error_log("Update result: " . ($update_result !== false ? 'SUCCESS' : 'FAILED'));
        
        if ($update_result === false) {
            error_log("Update failed. Last error: " . ($db->last_error ?? 'No error info'));
        }
        
        // Verify the update worked
        $verify_user = $db->get_row("SELECT last_login, login_count FROM IONEERS WHERE email = ?", $email);
        error_log("Verification after update: " . print_r($verify_user, true));
        
        // Set session variables for successful login
        error_log("Setting session variables for successful login");
        
        // Clear any existing session data first
        session_unset();
        
        // Set fresh session data
        $_SESSION['authenticated'] = true;
        $_SESSION['user_email'] = $email;
        $_SESSION['last_activity'] = time();
        $_SESSION['session_regenerated'] = time();
        
        // Force session write
        session_write_close();
        session_start(); // Restart session
        
        error_log("Session after login: " . print_r($_SESSION, true));
        
        // Redirect to admin directory
        $redirect_url = 'https://' . $_SERVER['HTTP_HOST'] . '/app/directory.php';
        error_log("SUCCESS - Redirecting to: " . $redirect_url);
        
        // Clear output buffer and redirect
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        header('Location: ' . $redirect_url);
        exit;
    }
    
} catch (Exception $e) {
    error_log("Database exception: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    header('Location: /login/index.php?error=database');
    exit;
}

error_log("=== GOOGLE OAUTH END (should not reach here) ===");
?>