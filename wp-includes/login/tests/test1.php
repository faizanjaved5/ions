<?php
// oauth-debug-comprehensive.php - Replace your google-oauth.php temporarily with this
session_start();

// Force error logging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

error_log("=== COMPREHENSIVE OAUTH DEBUG START ===");
error_log("Time: " . date('Y-m-d H:i:s'));
error_log("Session ID: " . session_id());
error_log("Initial session: " . print_r($_SESSION, true));
error_log("GET parameters: " . print_r($_GET, true));
error_log("POST parameters: " . print_r($_POST, true));
error_log("Server info: " . print_r($_SERVER, true));

try {
    // Step 1: Check if we have authorization code
    if (!isset($_GET['code'])) {
        error_log("STEP 1 FAILED: No authorization code received");
        error_log("Available GET params: " . print_r($_GET, true));
        error_log("Full request URI: " . ($_SERVER['REQUEST_URI'] ?? 'unknown'));
        
        $_SESSION['otp_error'] = '❌ No authorization code received from Google.';
        header('Location: /login/index.php?error=no_code');
        exit;
    }
    
    $auth_code = $_GET['code'];
    error_log("STEP 1 SUCCESS: Authorization code received: " . substr($auth_code, 0, 20) . "...");
    
    // Step 2: Load configuration
    error_log("STEP 2: Loading configuration...");
    $config_path = __DIR__ . '/../config/config.php';
    error_log("Config path: " . $config_path);
    error_log("Config file exists: " . (file_exists($config_path) ? 'YES' : 'NO'));
    
    if (!file_exists($config_path)) {
        throw new Exception("Config file not found at: " . $config_path);
    }
    
    $config = require $config_path;
    error_log("STEP 2 SUCCESS: Configuration loaded");
    error_log("Config type: " . gettype($config));
    error_log("Config keys: " . (is_array($config) ? implode(', ', array_keys($config)) : 'not array'));
    
    $client_id = $config['google_client_id'] ?? null;
    $client_secret = $config['google_client_secret'] ?? null;
    $redirect_uri = $config['google_redirect_uri'] ?? null;
    
    error_log("Client ID: " . ($client_id ? substr($client_id, 0, 20) . "..." : 'NULL'));
    error_log("Client Secret: " . ($client_secret ? 'SET (length: ' . strlen($client_secret) . ')' : 'NULL'));
    error_log("Redirect URI: " . ($redirect_uri ?: 'NULL'));
    
    if (!$client_id || !$client_secret || !$redirect_uri) {
        throw new Exception("Missing OAuth configuration - ID: " . ($client_id ? 'SET' : 'MISSING') . ", Secret: " . ($client_secret ? 'SET' : 'MISSING') . ", URI: " . ($redirect_uri ?: 'MISSING'));
    }
    
    // Step 3: Load database
    error_log("STEP 3: Loading database...");
    $db_path = __DIR__ . '/../config/database.php';
    error_log("Database path: " . $db_path);
    error_log("Database file exists: " . (file_exists($db_path) ? 'YES' : 'NO'));
    
    require_once $db_path;
    
    global $db;
    if (!$db) {
        throw new Exception("Database object not available");
    }
    
    if (!$db->isConnected()) {
        throw new Exception("Database not connected");
    }
    
    error_log("STEP 3 SUCCESS: Database connected");
    
    // Step 4: Exchange code for token
    error_log("STEP 4: Exchanging authorization code for access token...");
    
    $token_url = "https://oauth2.googleapis.com/token";
    $post_data = [
        'code' => $auth_code,
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'redirect_uri' => $redirect_uri,
        'grant_type' => 'authorization_code'
    ];
    
    error_log("Token URL: " . $token_url);
    error_log("Post data: " . print_r($post_data, true));
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $token_url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($post_data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_SSL_VERIFYPEER => false, // For debugging
        CURLOPT_TIMEOUT => 30,
        CURLOPT_VERBOSE => true
    ]);
    
    $token_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    error_log("Token response HTTP code: " . $http_code);
    error_log("Token response: " . $token_response);
    error_log("cURL error: " . ($curl_error ?: 'none'));
    
    if ($curl_error) {
        throw new Exception("cURL error during token request: " . $curl_error);
    }
    
    if ($http_code !== 200) {
        throw new Exception("Token request failed with HTTP " . $http_code . ": " . $token_response);
    }
    
    $token_data = json_decode($token_response, true);
    if (!$token_data || !isset($token_data['access_token'])) {
        error_log("Token parse error or missing access_token: " . print_r($token_data, true));
        throw new Exception("Failed to parse token response or missing access_token");
    }
    
    $access_token = $token_data['access_token'];
    error_log("STEP 4 SUCCESS: Access token obtained: " . substr($access_token, 0, 20) . "...");
    
    // Step 5: Get user info
    error_log("STEP 5: Getting user information...");
    
    $userinfo_url = "https://www.googleapis.com/oauth2/v2/userinfo";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $userinfo_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $access_token],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $user_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    error_log("User info HTTP code: " . $http_code);
    error_log("User info response: " . $user_response);
    error_log("cURL error: " . ($curl_error ?: 'none'));
    
    if ($curl_error) {
        throw new Exception("cURL error getting user info: " . $curl_error);
    }
    
    if ($http_code !== 200) {
        throw new Exception("User info request failed with HTTP " . $http_code . ": " . $user_response);
    }
    
    $user_data = json_decode($user_response, true);
    if (!$user_data || !isset($user_data['email'])) {
        error_log("User data parse error or missing email: " . print_r($user_data, true));
        throw new Exception("Failed to parse user data or missing email");
    }
    
    $email = $user_data['email'];
    $name = $user_data['name'] ?? '';
    $picture = $user_data['picture'] ?? null;
    $google_id = $user_data['id'] ?? null;
    
    error_log("STEP 5 SUCCESS: User info obtained");
    error_log("Email: " . $email);
    error_log("Name: " . $name);
    error_log("Google ID: " . ($google_id ?: 'none'));
    
    // Step 6: Database operations
    error_log("STEP 6: Database operations...");
    
    $existing_user = $db->get_row("SELECT * FROM IONEERS WHERE email = ?", $email);
    error_log("Existing user query result: " . print_r($existing_user, true));
    
    $current_time = date('Y-m-d H:i:s');
    
    if (!$existing_user) {
        // New user
        error_log("STEP 6A: Adding new blocked user");
        
        $insert_data = [
            'email' => $email,
            'fullname' => $name,
            'photo_url' => $picture,
            'google_id' => $google_id,
            'user_role' => 'None',
            'status' => 'blocked',
            'last_login' => null,
            'login_count' => 0
        ];
        
        error_log("Insert data: " . print_r($insert_data, true));
        
        $insert_result = $db->insert('IONEERS', $insert_data);
        error_log("Insert result: " . ($insert_result ? 'SUCCESS' : 'FAILED'));
        error_log("Last error: " . ($db->last_error ?? 'none'));
        
        if (!$insert_result) {
            throw new Exception("Failed to insert new user: " . ($db->last_error ?? 'Unknown error'));
        }
        
        error_log("STEP 6A SUCCESS: New user added, redirecting with unauthorized message");
        session_unset();
        $_SESSION['otp_error'] = '❌ Access Denied: Your email is not authorized. Please contact an administrator.';
        header('Location: /login/index.php?error=unauthorized');
        exit;
        
    } else {
        // Existing user
        error_log("STEP 6B: Processing existing user");
        
        $user_role = $existing_user->user_role ?? '';
        $status = $existing_user->status ?? '';
        
        error_log("User role: '$user_role'");
        error_log("User status: '$status'");
        
        $blocked_roles = ['None'];
        $is_blocked_role = in_array($user_role, $blocked_roles);
        $is_blocked_status = ($status === 'blocked');
        $is_authorized = !$is_blocked_role && !$is_blocked_status;
        
        error_log("Is blocked role: " . ($is_blocked_role ? 'YES' : 'NO'));
        error_log("Is blocked status: " . ($is_blocked_status ? 'YES' : 'NO'));
        error_log("Is authorized: " . ($is_authorized ? 'YES' : 'NO'));
        
        if (!$is_authorized) {
            error_log("STEP 6B: User not authorized, redirecting");
            session_unset();
            $_SESSION['otp_error'] = '❌ Access Denied: Your account is not authorized. Please contact an administrator.';
            header('Location: /login/index.php?error=unauthorized');
            exit;
        }
        
        // Update login info
        error_log("STEP 6C: Updating login information");
        
        $current_login_count = intval($existing_user->login_count ?? 0);
        $new_login_count = $current_login_count + 1;
        
        error_log("Current login count: " . $current_login_count);
        error_log("New login count: " . $new_login_count);
        
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
        error_log("Last error: " . ($db->last_error ?? 'none'));
        
        if ($update_result === false) {
            error_log("WARNING: Database update failed but continuing with login");
        }
        
        // Verify update
        $verify_user = $db->get_row("SELECT last_login, login_count FROM IONEERS WHERE email = ?", $email);
        error_log("Update verification: " . print_r($verify_user, true));
        
        // Set session
        error_log("STEP 7: Setting session variables");
        
        session_unset();
        $_SESSION['authenticated'] = true;
        $_SESSION['user_email'] = $email;
        $_SESSION['last_activity'] = time();
        $_SESSION['session_regenerated'] = time();
        
        error_log("Session variables set: " . print_r($_SESSION, true));
        
        // Force session save
        session_write_close();
        session_start();
        
        error_log("Session after restart: " . print_r($_SESSION, true));
        
        // Success redirect
        error_log("STEP 8: Successful login, redirecting to /app/directory.php");
        error_log("=== COMPREHENSIVE OAUTH DEBUG SUCCESS ===");
        
        header('Location: /app/directory.php');
        exit;
    }
    
} catch (Exception $e) {
    error_log("=== OAUTH EXCEPTION ===");
    error_log("Exception message: " . $e->getMessage());
    error_log("Exception file: " . $e->getFile());
    error_log("Exception line: " . $e->getLine());
    error_log("Stack trace: " . $e->getTraceAsString());
    error_log("=== END EXCEPTION ===");
    
    session_unset();
    $_SESSION['otp_error'] = '❌ Authentication error: ' . $e->getMessage();
    header('Location: /login/index.php?error=exception');
    exit;
    
} catch (Throwable $t) {
    error_log("=== OAUTH FATAL ERROR ===");
    error_log("Error message: " . $t->getMessage());
    error_log("Error file: " . $t->getFile());
    error_log("Error line: " . $t->getLine());
    error_log("Stack trace: " . $t->getTraceAsString());
    error_log("=== END FATAL ERROR ===");
    
    session_unset();
    $_SESSION['otp_error'] = '❌ System error occurred during authentication.';
    header('Location: /login/index.php?error=fatal');
    exit;
}

error_log("=== COMPREHENSIVE OAUTH DEBUG END (should not reach here) ===");
?>