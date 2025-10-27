<?php
/**
 * Discord OAuth 2.0 Handler for Login (Mirroring Google OAuth Flow)
 * Handles login/join flows: Auth → Tokens → User Info → DB Create/Lookup/Update → Session → Redirect
 * Captures Discord username, avatar, and stores in DB/session like Google.
 */

error_log('═══════════════════════════════════════════════════════════');
error_log('🔵 DISCORD OAUTH CALLBACK STARTED');
error_log('🔵 Request URI: ' . ($_SERVER['REQUEST_URI'] ?? 'UNKNOWN'));
error_log('🔵 GET params: ' . json_encode($_GET));
error_log('═══════════════════════════════════════════════════════════');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    error_log('🔄 Session started in OAuth callback');
} else {
    error_log('✅ Session already active in OAuth callback');
    error_log('🔍 SESSION CHECK: user_id = ' . ($_SESSION['user_id'] ?? 'NOT SET'));
    error_log('🔍 SESSION CHECK: user_email = ' . ($_SESSION['user_email'] ?? 'NOT SET'));
}

// Load configuration & DB
$config = require __DIR__ . '/../config/config.php';
global $db;  // Match Google: Assume global $db from database.php

if (!$db || !$db->isConnected()) {
    error_log("FATAL: Database not connected in discord-oauth.php");
    $_SESSION['otp_error'] = '❌ Database connection error.';
    header('Location: /login/index.php');
    exit;
}

// Discord OAuth credentials
$clientId     = $config['discord_clientid'] ?? '';
$clientSecret = $config['discord_secret'] ?? '';
$redirectUri  = $config['discord_redirect_uri'] ?? '';

// Validate config values
if (!$clientId || !$clientSecret || !$redirectUri) {
    error_log("FATAL: Missing Discord OAuth configuration");
    $_SESSION['otp_error'] = '❌ OAuth configuration error.';
    header('Location: /login/index.php');
    exit;
}

// Check if this is a join request (mirror Google logic)
$is_join_request = isset($_GET['state']) && $_GET['state'] === 'join';
error_log("Is join request: " . ($is_join_request ? 'YES' : 'NO'));

// Handle Discord errors
if (isset($_GET['error'])) {
    $errorDesc = $_GET['error_description'] ?? $_GET['error'] ?? 'Unknown';
    error_log('❌ Discord error: ' . $errorDesc);
    $_SESSION['otp_error'] = '❌ Discord authorization failed: ' . htmlspecialchars($errorDesc);
    header('Location: ' . ($is_join_request ? '/join/index.php?error=oauth_failed' : '/login/index.php?error=oauth_failed'));
    exit;
}

// ============================================
// STEP 1: Redirect to Discord (if no code)
// ============================================
if (!isset($_GET['code'])) {
    $_SESSION['discord_oauth_state'] = bin2hex(random_bytes(16));
    $authUrl = 'https://discord.com/api/oauth2/authorize?' . http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => 'identify email',
        'state' => $is_join_request ? 'join' : $_SESSION['discord_oauth_state'],
        'prompt' => 'consent'
    ]);
    error_log('🔗 Redirecting to Auth URL: ' . $authUrl);
    header('Location: ' . $authUrl);
    exit;
}

// ============================================
// STEP 2: Verify State & Get Code
// ============================================
$receivedState = $_GET['state'] ?? '';
if ($is_join_request) {
    $expectedState = 'join';
} else {
    $expectedState = $_SESSION['discord_oauth_state'] ?? '';
    unset($_SESSION['discord_oauth_state']);
}

if ($receivedState !== $expectedState) {
    error_log("❌ State mismatch: Got '$receivedState', Expected '$expectedState'");
    $_SESSION['otp_error'] = '❌ OAuth security check failed. Please try again.';
    header('Location: ' . ($is_join_request ? '/join/index.php?error=oauth_failed' : '/login/index.php?error=no_code'));
    exit;
}

$code = $_GET['code'] ?? '';
if (empty($code)) {
    error_log('❌ No code received');
    $_SESSION['otp_error'] = '❌ No authorization code received from Discord.';
    header('Location: ' . ($is_join_request ? '/join/index.php?error=oauth_failed' : '/login/index.php?error=no_code'));
    exit;
}

// ============================================
// STEP 3: Exchange Code for Tokens (Mirror Google)
// ============================================
$token_url = "https://discord.com/api/oauth2/token";
$post_data = [
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'grant_type' => 'authorization_code',
    'code' => $code,
    'redirect_uri' => $redirectUri
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
$curl_error = curl_error($ch);

if ($curl_error) {
    error_log("CURL error: " . $curl_error);
    curl_close($ch);
    $_SESSION['otp_error'] = '❌ Network error during authentication.';
    header('Location: ' . ($is_join_request ? '/join/index.php' : '/login/index.php'));
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
    $_SESSION['otp_error'] = '❌ No access token received from Discord.';
    header('Location: ' . ($is_join_request ? '/join/index.php?error=oauth_failed' : '/login/index.php'));
    exit;
}

$access_token = $token_data['access_token'];

// ============================================
// STEP 4: Get User Info (Mirror Google /userinfo)
// ============================================
$userinfo_url = "https://discord.com/api/users/@me";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $userinfo_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$user_response = curl_exec($ch);
$user_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($user_http_code !== 200) {
    error_log("User info HTTP error: " . $user_http_code . ", Response: " . $user_response);
    $_SESSION['otp_error'] = '❌ Could not retrieve user info from Discord.';
    header('Location: ' . ($is_join_request ? '/join/index.php?error=oauth_failed' : '/login/index.php'));
    exit;
}

$user_data = json_decode($user_response, true);
if (!$user_data || !isset($user_data['email'])) {
    error_log("No email in user data: " . print_r($user_data, true));
    $_SESSION['otp_error'] = '❌ Could not retrieve email from Discord account.';
    header('Location: ' . ($is_join_request ? '/join/index.php?error=oauth_failed' : '/login/index.php'));
    exit;
}

$email = trim(strtolower($user_data['email']));
$verified = $user_data['verified'] ?? false;
if (!$verified) {
    error_log("Email not verified: " . $email);
    $_SESSION['otp_error'] = '❌ Your Discord email is not verified. Please verify it in Discord settings.';
    header('Location: ' . ($is_join_request ? '/join/index.php?error=oauth_failed' : '/login/index.php'));
    exit;
}

$discordUserId = $user_data['id'] ?? null;
$name = $user_data['global_name'] ?? $user_data['username'] ?? '';  // Prefer display name, fallback to username

// Generate Discord avatar URL (mirror Google's photo_url handling)
$avatar_hash = $user_data['avatar'] ?? null;
if ($avatar_hash) {
    $picture = "https://cdn.discordapp.com/avatars/{$discordUserId}/{$avatar_hash}.png?size=256";
} else {
    // Default Discord avatar based on ID/discriminator (simplified)
    $default_avatar = "https://cdn.discordapp.com/embed/avatars/0.png";  // Or compute from ID
    $picture = $default_avatar;
}

error_log("User email: $email, name: $name, avatar: $picture, verified: " . ($verified ? 'YES' : 'NO'));

try {
    // Check if user exists (mirror Google)
    $existing_user = $db->get_row("SELECT * FROM IONEERS WHERE email = ?", [$email]);
   
    if ($is_join_request) {
        // JOIN FLOW (mirror Google)
        if ($existing_user) {
            error_log("Join request but user exists - checking role");
           
            if ($existing_user->user_role === 'Guest') {
                // Existing Guest - continue to step 2
                $_SESSION['user_id'] = $existing_user->user_id;
                $_SESSION['email'] = $existing_user->email;
                $_SESSION['fullname'] = $existing_user->fullname;
                $_SESSION['user_role'] = 'Guest';
                $_SESSION['logged_in'] = true;
                $_SESSION['post_join_ready_for_upgrade'] = true;
                $_SESSION['oauth_user_data'] = true;
                $_SESSION['user_handle'] = $existing_user->handle ?? '';
               
                header('Location: /join/index.php?oauth_existing_guest=1');
                exit;
            } else {
                // Already upgraded - send OTP
                $_SESSION['pending_otp_email'] = $email;
                $_SESSION['otp_sent_time'] = time();
                $_SESSION['otp_code'] = sprintf('%06d', mt_rand(0, 999999));
               
                $_SESSION['user_id'] = $existing_user->user_id;
                $_SESSION['email'] = $existing_user->email;
                $_SESSION['fullname'] = $existing_user->fullname;
                $_SESSION['user_role'] = $existing_user->user_role;
                $_SESSION['logged_in'] = true;
                $_SESSION['user_handle'] = $existing_user->handle ?? '';
               
                header('Location: /join/index.php');
                exit;
            }
        }
       
        // Create new user with Guest role
        error_log("Creating new user through Discord OAuth join");
       
        $insert_data = [
            'email' => $email,
            'fullname' => $name,
            'photo_url' => $picture,
            'discord_user_id' => $discordUserId,  // Discord-specific
            'user_role' => 'Guest',
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
       
        $new_user = $db->get_row("SELECT * FROM IONEERS WHERE email = ?", [$email]);
       
        if ($new_user) {
            $_SESSION['user_id'] = $new_user->user_id;
            $_SESSION['email'] = $new_user->email;
            $_SESSION['fullname'] = $new_user->fullname;
            $_SESSION['user_role'] = 'Guest';
            $_SESSION['logged_in'] = true;
            $_SESSION['post_join_ready_for_upgrade'] = true;
            $_SESSION['oauth_user_data'] = true;
            $_SESSION['user_handle'] = $new_user->handle ?? '';
           
            header('Location: /join/index.php?oauth_new_user=1');
            exit;
        }
       
    } else {
        // LOGIN FLOW (mirror Google)
        if (!$existing_user) {
            // Create as Guest and redirect to join
            error_log("Login attempt but user doesn't exist - creating as Guest");
           
            $insert_data = [
                'email' => $email,
                'fullname' => $name,
                'photo_url' => $picture,
                'discord_user_id' => $discordUserId,
                'user_role' => 'Guest',
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
           
            $new_user = $db->get_row("SELECT * FROM IONEERS WHERE email = ?", [$email]);
           
            if ($new_user) {
                $_SESSION['user_id'] = $new_user->user_id;
                $_SESSION['email'] = $new_user->email;
                $_SESSION['fullname'] = $new_user->fullname;
                $_SESSION['user_role'] = 'Guest';
                $_SESSION['logged_in'] = true;
                $_SESSION['post_join_ready_for_upgrade'] = true;
                $_SESSION['oauth_user_data'] = true;
                $_SESSION['user_handle'] = $new_user->handle ?? '';
               
                header('Location: /join/index.php?oauth_new_user=1');
                exit;
            }
        }
       
        // User exists - check role (mirror Google)
        $user_role = trim($existing_user->user_role ?? '');
        $status = trim($existing_user->status ?? '');
       
        if ($user_role === 'Guest') {
            error_log("Guest user attempting login - redirecting to join step 2");
           
            $_SESSION['user_id'] = $existing_user->user_id;
            $_SESSION['email'] = $existing_user->email;
            $_SESSION['fullname'] = $existing_user->fullname;
            $_SESSION['user_role'] = 'Guest';
            $_SESSION['logged_in'] = true;
            $_SESSION['post_join_ready_for_upgrade'] = true;
            $_SESSION['oauth_user_data'] = true;
            $_SESSION['user_handle'] = $existing_user->handle ?? '';
           
            header('Location: /join/index.php?oauth_existing_guest=1');
            exit;
        }
       
        // Check authorization
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
       
        // Update login info & Discord data (mirror Google update)
        $update_data = [
            'last_login' => date('Y-m-d H:i:s'),
            'login_count' => intval($existing_user->login_count ?? 0) + 1,
            'fullname' => $name ?: $existing_user->fullname,
            'photo_url' => $picture ?: $existing_user->photo_url,
            'discord_user_id' => $discordUserId ?: $existing_user->discord_user_id  // Discord-specific
        ];
       
        $db->update('IONEERS', $update_data, ['email' => $email]);
       
        // Set session (mirror Google exactly)
        $_SESSION['authenticated'] = true;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_role'] = $user_role;
        $_SESSION['user_id'] = $existing_user->user_id;
        $_SESSION['user_name'] = $name ?: $existing_user->fullname;
        $_SESSION['last_activity'] = time();
        $_SESSION['photo_url'] = ($existing_user->photo_url ?: ($picture ?: ('https://i0.wp.com/ui-avatars.com/api/?name=' . urlencode(($name ?: $existing_user->fullname) ?: $email) . '&size=256')));
        $_SESSION['user_handle'] = $existing_user->handle ?? '';
       
        // Check for redirect URL from session (mirror Google)
        $redirect = $_SESSION['redirect_after_login'] ?? '/app/directory.php';
        unset($_SESSION['redirect_after_login']);
       
        error_log('✅ Discord login successful - Redirecting to: ' . $redirect);
       
        // Check if popup (mirror Google JS)
        echo '<script>
            if (window.opener && window.opener !== window) {
                window.opener.postMessage({type: "login-success"}, window.location.origin);
                window.close();
            } else {
                window.location.href = "' . htmlspecialchars($redirect, ENT_QUOTES) . '";
            }
        </script>';
        exit;
    }
   
} catch (Exception $e) {
    error_log("Database exception: " . $e->getMessage());
    $_SESSION['otp_error'] = '❌ Database error during authentication: ' . htmlspecialchars($e->getMessage());
    header('Location: ' . ($is_join_request ? '/join/index.php?error=oauth_failed' : '/login/index.php'));
    exit;
}
?>