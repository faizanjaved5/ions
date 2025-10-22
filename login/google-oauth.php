<?php
// google-oauth.php - Updated to handle both login and join flows
session_start();

$config = require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

global $db;

// Comprehensive error logging
error_log("=== GOOGLE OAUTH START ===");
error_log("GET params: " . print_r($_GET, true));

if (!$db || !$db->isConnected()) {
    error_log("FATAL: Database not connected in google-oauth.php");
    $_SESSION['otp_error'] = '❌ Database connection error.';
    header('Location: /login/index.php');
    exit;
}

// Check if this is a join request
$is_join_request = isset($_GET['state']) && $_GET['state'] === 'join';
error_log("Is join request: " . ($is_join_request ? 'YES' : 'NO'));

// Properly access config array
$client_id = $config['google_client_id'] ?? null;
$client_secret = $config['google_client_secret'] ?? null;
$redirect_uri = $config['google_redirect_uri'] ?? null;

// Validate config values
if (!$client_id || !$client_secret || !$redirect_uri) {
    error_log("FATAL: Missing OAuth configuration");
    $_SESSION['otp_error'] = '❌ OAuth configuration error.';
    header('Location: ' . ($is_join_request ? '/join/index.php' : '/login/index.php'));
    exit;
}

if (!isset($_GET['code'])) {
    error_log("ERROR: No authorization code from Google");
    $_SESSION['otp_error'] = '❌ No authorization code received from Google.';
    header('Location: ' . ($is_join_request ? '/join/index.php?error=oauth_failed' : '/login/index.php?error=no_code'));
    exit;
}

$auth_code = $_GET['code'];

// Exchange authorization code for access token
$token_url = "https://oauth2.googleapis.com/token";
$post_data = [
    'code' => $auth_code,
    'client_id' => $client_id,
    'client_secret' => $client_secret,
    'redirect_uri' => $redirect_uri,
    'grant_type' => 'authorization_code'
];

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

if (curl_error($ch)) {
    $curl_error = curl_error($ch);
    error_log("CURL error: " . $curl_error);
    curl_close($ch);
    $_SESSION['otp_error'] = '❌ Network error during authentication.';
    header('Location: ' . ($is_join_request ? '/join/index.php?error=oauth_failed' : '/login/index.php'));
    exit;
}

curl_close($ch);

$token_data = json_decode($token_response, true);

if (!$token_data || isset($token_data['error'])) {
    error_log("Token error: " . print_r($token_data, true));
    $_SESSION['otp_error'] = '❌ Authentication failed.';
    header('Location: ' . ($is_join_request ? '/join/index.php?error=oauth_failed' : '/login/index.php'));
    exit;
}

if (!isset($token_data['access_token'])) {
    $_SESSION['otp_error'] = '❌ No access token received from Google.';
    header('Location: ' . ($is_join_request ? '/join/index.php?error=oauth_failed' : '/login/index.php'));
    exit;
}

$access_token = $token_data['access_token'];

// Get user info
$userinfo_url = "https://www.googleapis.com/oauth2/v2/userinfo";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $userinfo_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$user_response = curl_exec($ch);
curl_close($ch);

$user_data = json_decode($user_response, true);
if (!$user_data || !isset($user_data['email'])) {
    $_SESSION['otp_error'] = '❌ Could not retrieve email from Google account.';
    header('Location: ' . ($is_join_request ? '/join/index.php?error=oauth_failed' : '/login/index.php'));
    exit;
}

$email = trim(strtolower($user_data['email']));
$name = $user_data['name'] ?? '';
$picture = $user_data['picture'] ?? null;
$google_id = $user_data['id'] ?? null;

error_log("User email: $email");
error_log("Is join request: " . ($is_join_request ? 'YES' : 'NO'));

try {
    // Check if user exists
    $existing_user = $db->get_row("SELECT * FROM IONEERS WHERE email = ?", $email);

    if ($is_join_request) {
        // JOIN FLOW
        if ($existing_user) {
            // User already exists
            error_log("Join request but user exists - checking role");

            if ($existing_user->user_role === 'Guest') {
                // Existing Guest user - continue to step 2
                $_SESSION['user_id'] = $existing_user->user_id;
                $_SESSION['email'] = $existing_user->email;
                $_SESSION['fullname'] = $existing_user->fullname;
                $_SESSION['user_role'] = 'Guest';
                $_SESSION['logged_in'] = true;
                $_SESSION['post_join_ready_for_upgrade'] = true;
                $_SESSION['oauth_user_data'] = true;
                $_SESSION['photo_url'] = ($existing_user->photo_url ?: ($picture ?: ('https://i0.wp.com/ui-avatars.com/api/?name=' . urlencode($existing_user->fullname ?: $email) . '&size=256')));

                header('Location: /join/index.php?oauth_existing_guest=1');
                exit;
            } else {
                // User is already upgraded - send OTP for verification
                $_SESSION['pending_otp_email'] = $email;
                $_SESSION['otp_sent_time'] = time();
                $_SESSION['otp_code'] = sprintf('%06d', mt_rand(0, 999999));

                // Log them in temporarily for the OTP flow
                $_SESSION['user_id'] = $existing_user->user_id;
                $_SESSION['email'] = $existing_user->email;
                $_SESSION['fullname'] = $existing_user->fullname;
                $_SESSION['user_role'] = $existing_user->user_role;
                $_SESSION['logged_in'] = true;
                $_SESSION['photo_url'] = ($existing_user->photo_url ?: ($picture ?: ('https://i0.wp.com/ui-avatars.com/api/?name=' . urlencode($existing_user->fullname ?: $email) . '&size=256')));

                header('Location: /join/index.php');
                exit;
            }
        }

        // Create new user with Guest role
        error_log("Creating new user through Google OAuth join");

        $insert_data = [
            'email' => $email,
            'fullname' => $name,
            'photo_url' => $picture,
            'google_id' => $google_id,
            'user_role' => 'Guest',  // Start as Guest for join flow
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'login_count' => 1
        ];

        $insert_result = $db->insert('IONEERS', $insert_data);

        if (!$insert_result) {
            error_log("Failed to create user");
            header('Location: /join/index.php?error=creation_failed');
            exit;
        }

        // Get the newly created user
        $new_user = $db->get_row("SELECT * FROM IONEERS WHERE email = ?", $email);

        if ($new_user) {
            // Set session for new user
            $_SESSION['user_id'] = $new_user->user_id;
            $_SESSION['email'] = $new_user->email;
            $_SESSION['fullname'] = $new_user->fullname;
            $_SESSION['user_role'] = 'Guest';
            $_SESSION['logged_in'] = true;
            $_SESSION['post_join_ready_for_upgrade'] = true;
            $_SESSION['oauth_user_data'] = true;
            $_SESSION['photo_url'] = ($new_user->photo_url ?: ($picture ?: ('https://i0.wp.com/ui-avatars.com/api/?name=' . urlencode($new_user->fullname ?: $email) . '&size=256')));

            // Redirect to join step 2 (upgrade offer)
            header('Location: /join/index.php?oauth_new_user=1');
            exit;
        }
    } else {
        // LOGIN FLOW
        if (!$existing_user) {
            // User doesn't exist - for login flow, create them as Guest and redirect to join
            error_log("Login attempt but user doesn't exist - creating as Guest");

            $insert_data = [
                'email' => $email,
                'fullname' => $name,
                'photo_url' => $picture,
                'google_id' => $google_id,
                'user_role' => 'Guest',  // Start as Guest
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'login_count' => 0
            ];

            $insert_result = $db->insert('IONEERS', $insert_data);

            if (!$insert_result) {
                error_log("Failed to create user during login flow");
                $_SESSION['otp_error'] = '❌ Unable to create account. Please try signing up.';
                header('Location: /join/index.php');
                exit;
            }

            // Get the newly created user
            $new_user = $db->get_row("SELECT * FROM IONEERS WHERE email = ?", $email);

            if ($new_user) {
                // Set session for new guest user
                $_SESSION['user_id'] = $new_user->user_id;
                $_SESSION['email'] = $new_user->email;
                $_SESSION['fullname'] = $new_user->fullname;
                $_SESSION['user_role'] = 'Guest';
                $_SESSION['logged_in'] = true;
                $_SESSION['post_join_ready_for_upgrade'] = true;
                $_SESSION['oauth_user_data'] = true;

                // Redirect to join step 2 since they're new
                header('Location: /join/index.php?oauth_new_user=1');
                exit;
            }
        }

        // User exists - check their role
        $user_role = trim($existing_user->user_role ?? '');
        $status = trim($existing_user->status ?? '');

        // Check if this is a Guest user who hasn't completed registration
        if ($user_role === 'Guest') {
            error_log("Guest user attempting login - redirecting to join step 2");

            // Set session for guest user
            $_SESSION['user_id'] = $existing_user->user_id;
            $_SESSION['email'] = $existing_user->email;
            $_SESSION['fullname'] = $existing_user->fullname;
            $_SESSION['user_role'] = 'Guest';
            $_SESSION['logged_in'] = true;
            $_SESSION['post_join_ready_for_upgrade'] = true;
            $_SESSION['oauth_user_data'] = true;
            $_SESSION['photo_url'] = ($existing_user->photo_url ?: ($picture ?: ('https://i0.wp.com/ui-avatars.com/api/?name=' . urlencode($existing_user->fullname ?: $email) . '&size=256')));

            // Redirect to join step 2 to complete registration
            header('Location: /join/index.php?oauth_existing_guest=1');
            exit;
        }

        // Check authorization for non-Guest users
        $blocked_roles = ['None', 'none', ''];
        $valid_roles = ['Owner', 'Admin', 'Creator', 'Member', 'Uploader'];

        $is_valid_role = in_array($user_role, $valid_roles);
        $is_blocked_status = in_array(strtolower($status), ['blocked', 'inactive', 'disabled']);

        if (!$is_valid_role || $is_blocked_status) {
            error_log("User not authorized - role: $user_role, status: $status");
            $_SESSION['otp_error'] = '❌ Access Denied: Your account is not authorized.';
            header('Location: /login/index.php?error=unauthorized');
            exit;
        }

        // User is authorized - update login info
        $update_data = [
            'last_login' => date('Y-m-d H:i:s'),
            'login_count' => intval($existing_user->login_count ?? 0) + 1,
            'fullname' => $name ?: $existing_user->fullname,
            'photo_url' => $picture ?: $existing_user->photo_url,
            'google_id' => $google_id ?: $existing_user->google_id
        ];

        $db->update('IONEERS', $update_data, ['email' => $email]);

        // Set session for successful login
        $_SESSION['authenticated'] = true;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_role'] = $user_role;
        $_SESSION['user_id'] = $existing_user->user_id;
        $_SESSION['user_name'] = $name ?: $existing_user->fullname;
        $_SESSION['last_activity'] = time();
        $_SESSION['photo_url'] = ($existing_user->photo_url ?: ($picture ?: ('https://i0.wp.com/ui-avatars.com/api/?name=' . urlencode(($name ?: $existing_user->fullname) ?: $email) . '&size=256')));

        // Check for redirect URL from session
        $redirect = $_SESSION['redirect_after_login'] ?? '/app/directory.php';
        unset($_SESSION['redirect_after_login']);

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
    }
} catch (Exception $e) {
    error_log("Database exception: " . $e->getMessage());
    $_SESSION['otp_error'] = '❌ Database error during authentication.';
    header('Location: ' . ($is_join_request ? '/join/index.php?error=oauth_failed' : '/login/index.php'));
    exit;
}
