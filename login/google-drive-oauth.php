<?php
/**
 * Google Drive OAuth 2.0 Handler
 * Implements authorization code flow to obtain refresh tokens for long-term access
 * 
 * Flow:
 * 1. User clicks "Connect Google Drive" → Redirects to Google OAuth
 * 2. User grants permissions → Google redirects back with authorization code
 * 3. Exchange code for access token + refresh token → Store in database
 * 4. Close popup and notify parent window of success
 */

error_log('═══════════════════════════════════════════════════════════');
error_log('🔵 GOOGLE DRIVE OAUTH CALLBACK STARTED');
error_log('🔵 Request URI: ' . ($_SERVER['REQUEST_URI'] ?? 'UNKNOWN'));
error_log('🔵 Has code parameter: ' . (isset($_GET['code']) ? 'YES' : 'NO'));
error_log('🔵 Has state parameter: ' . (isset($_GET['state']) ? 'YES' : 'NO'));
error_log('═══════════════════════════════════════════════════════════');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Start session to access user ID (if not already started)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    error_log('🔄 Session started in OAuth callback');
} else {
    error_log('✅ Session already active in OAuth callback');
}

// Load configuration
$config = require __DIR__ . '/../config/config.php';

// Google Drive OAuth credentials - Use the Google Drive client (configured in Console)
$clientId     = $config['google_drive_clientid'] ?? '';
$clientSecret = $config['google_drive_secretid'] ?? '';

// Use the EXISTING registered redirect URI (matches Google Console configuration)
$redirectUri  = $config['google_redirect_uri'] ?? 'https://iblog.bz/login/google-oauth.php';

// Verify credentials are configured
if (empty($clientId) || empty($clientSecret)) {
    die('Error: Google Drive OAuth credentials not configured in config.php');
}

// Check if user is logged in
error_log('🔍 SESSION CHECK: user_id = ' . ($_SESSION['user_id'] ?? 'NOT SET'));
error_log('🔍 SESSION CHECK: user_email = ' . ($_SESSION['user_email'] ?? 'NOT SET'));
error_log('🔍 All session keys: ' . implode(', ', array_keys($_SESSION)));

if (!isset($_SESSION['user_id']) && !isset($_SESSION['user_email'])) {
    error_log('❌ User not logged in - no user_id or user_email in session');
    die('Error: User not logged in. Please log in first.');
}

// Get user ID (directly if available, or lookup from email)
$userId = $_SESSION['user_id'] ?? null;

if (!$userId && isset($_SESSION['user_email'])) {
    error_log('📧 No user_id in session, looking up from email: ' . $_SESSION['user_email']);
    $db = new IONDatabase();
    $user = $db->get_row("SELECT user_id FROM IONEERS WHERE email = ?", [$_SESSION['user_email']]);
    if ($user) {
        $userId = $user->user_id;
        error_log('✅ Found user_id from email: ' . $userId);
    } else {
        error_log('❌ User not found in database for email: ' . $_SESSION['user_email']);
        die('Error: User account not found.');
    }
}

if (!$userId) {
    error_log('❌ Could not determine user ID');
    die('Error: Could not determine user ID.');
}

error_log('✅ Using user_id: ' . $userId);

// ============================================
// STEP 1: Redirect to Google for Authorization
// ============================================

if (!isset($_GET['code'])) {
    // Generate state token for CSRF protection
    $_SESSION['google_drive_oauth_state'] = bin2hex(random_bytes(16));
    
    // Build authorization URL with special state to identify Google Drive OAuth
    $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => implode(' ', [
            'https://www.googleapis.com/auth/drive.file',      // Non-sensitive: Files selected by user via Picker
                                                               // NOTE: With drive.file scope, files are downloaded in
                                                               // frontend during active Picker session, then uploaded
                                                               // as regular files. This works because drive.file grants
                                                               // access during the session when user explicitly selects.
            'https://www.googleapis.com/auth/userinfo.email',  // Get user email
            'https://www.googleapis.com/auth/userinfo.profile' // Get user profile
        ]),
        'access_type' => 'offline',  // ⚠️ CRITICAL: Request refresh token
        'prompt' => 'consent',       // Force consent screen to ensure refresh token is issued
        'state' => 'googledrive_' . $_SESSION['google_drive_oauth_state'] // Prefix to identify as Google Drive OAuth
    ]);
    
    // Redirect user to Google
    header('Location: ' . $authUrl);
    exit;
}

// ============================================
// STEP 2: Handle Callback from Google
// ============================================

// Verify state token to prevent CSRF attacks
// Remove the "googledrive_" prefix before comparing
$receivedState = $_GET['state'] ?? '';
$expectedState = 'googledrive_' . ($_SESSION['google_drive_oauth_state'] ?? '');

if ($receivedState !== $expectedState) {
    error_log("State mismatch: Received '$receivedState', Expected '$expectedState'");
    die('Error: Invalid state token. Possible CSRF attack.');
}

// Get authorization code from Google
$code = $_GET['code'] ?? '';

if (empty($code)) {
    die('Error: No authorization code received from Google.');
}

// ============================================
// STEP 3: Exchange Code for Tokens
// ============================================

$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_POSTFIELDS => http_build_query([
        'code' => $code,
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri' => $redirectUri,
        'grant_type' => 'authorization_code'
    ])
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    error_log('Google OAuth token exchange failed: ' . $response);
    die('Error: Failed to exchange authorization code for tokens. HTTP ' . $httpCode);
}

$tokens = json_decode($response, true);

// Verify we received tokens
if (!isset($tokens['access_token'])) {
    error_log('Google OAuth response missing access_token: ' . $response);
    die('Error: Failed to obtain access token from Google.');
}

// ⚠️ CRITICAL: Check for refresh token
if (!isset($tokens['refresh_token'])) {
    error_log('Google OAuth response missing refresh_token. User may have already granted access.');
    // This can happen if user has already authorized the app
    // We'll still save the access token, but won't be able to refresh automatically
}

// ============================================
// STEP 4: Get User Info from Google
// ============================================

$ch = curl_init('https://www.googleapis.com/oauth2/v1/userinfo');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $tokens['access_token']]
]);

$userInfoResponse = curl_exec($ch);
$userInfoHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($userInfoHttpCode !== 200) {
    error_log('Failed to fetch Google user info: ' . $userInfoResponse);
    die('Error: Failed to get user information from Google. HTTP ' . $userInfoHttpCode);
}

$userInfo = json_decode($userInfoResponse, true);
$email = $userInfo['email'] ?? 'unknown@gmail.com';

// ============================================
// STEP 5: Store Tokens in Database
// ============================================

// TEMPORARY DEBUG - Show detailed info before trying to save
$debugInfo = [
    'user_id' => $userId,
    'email' => $email,
    'has_access_token' => isset($tokens['access_token']) ? 'YES' : 'NO',
    'has_refresh_token' => isset($tokens['refresh_token']) ? 'YES' : 'NO',
    'access_token_length' => isset($tokens['access_token']) ? strlen($tokens['access_token']) : 0,
    'expires_in' => $tokens['expires_in'] ?? 'NOT SET'
];

error_log('🚀 DEBUG INFO: ' . json_encode($debugInfo));

try {
    error_log('🚀 STARTING Google Drive token storage process');
    error_log('🚀 User ID: ' . $userId . ', Email: ' . $email);
    error_log('🚀 Tokens received: ' . (isset($tokens['access_token']) ? 'YES' : 'NO'));
    error_log('🚀 Refresh token received: ' . (isset($tokens['refresh_token']) ? 'YES' : 'NO'));
    
    $db = new IONDatabase();
    error_log('✅ Database connection established');
    
    // Calculate expiry time (default 3600 seconds = 1 hour)
    $expiresIn = $tokens['expires_in'] ?? 3600;
    $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);
    error_log('📅 Token expires at: ' . $expiresAt);
    
    // Check if we have a refresh token
    if (isset($tokens['refresh_token'])) {
        // Full OAuth flow with refresh token - INSERT or UPDATE
        error_log('🔍 Checking for existing token: user_id=' . $userId . ', email=' . $email);
        $existing = $db->get_row(
            "SELECT id, refresh_token FROM IONGoogleDriveTokens WHERE user_id = ? AND email = ?",
            [$userId, $email]
        );
        error_log('🔍 Existing token: ' . ($existing ? 'FOUND (ID: ' . $existing->id . ')' : 'NOT FOUND'));
        
        if ($existing) {
            // Update existing record
            error_log('🔑 UPDATING existing Google Drive token (ID: ' . $existing->id . ')');
            $updateResult = $db->query("
                UPDATE IONGoogleDriveTokens
                SET access_token = ?,
                    refresh_token = ?,
                    expires_at = ?,
                    updated_at = NOW()
                WHERE user_id = ? AND email = ?
            ", [
                $tokens['access_token'],
                $tokens['refresh_token'],
                $expiresAt,
                $userId,
                $email
            ]);
            error_log('🔑 Update result: ' . ($updateResult ? 'SUCCESS' : 'FAILED'));
            error_log('🔑 Update result type: ' . gettype($updateResult));
            error_log('🔑 Update result value: ' . var_export($updateResult, true));
            
            // Check for database errors
            if ($db->last_error) {
                error_log('❌ Database error after UPDATE: ' . $db->last_error);
            }
        } else {
            // Insert new record
            error_log('🔑 INSERTING NEW Google Drive token for user_id=' . $userId . ', email=' . $email);
            error_log('🔑 Access token length: ' . strlen($tokens['access_token']));
            error_log('🔑 Refresh token: ' . ($tokens['refresh_token'] ? 'YES' : 'NO'));
            error_log('🔑 Expires at: ' . $expiresAt);
            
            $insertResult = $db->query("
                INSERT INTO IONGoogleDriveTokens (user_id, email, access_token, refresh_token, expires_at)
                VALUES (?, ?, ?, ?, ?)
            ", [
                $userId,
                $email,
                $tokens['access_token'],
                $tokens['refresh_token'],
                $expiresAt
            ]);
            
            error_log('🔑 Insert result: ' . ($insertResult ? 'SUCCESS' : 'FAILED'));
            error_log('🔑 Insert result type: ' . gettype($insertResult));
            error_log('🔑 Insert result value: ' . var_export($insertResult, true));
            
            // Check for database errors
            if ($db->last_error) {
                error_log('❌ Database error after INSERT: ' . $db->last_error);
            }
            
            // Verify the insert
            $verifyInsert = $db->get_row("SELECT id, email FROM IONGoogleDriveTokens WHERE user_id = ? AND email = ?", [$userId, $email]);
            error_log('🔍 Verify insert: ' . ($verifyInsert ? 'FOUND (ID: ' . $verifyInsert->id . ')' : 'NOT FOUND'));
            error_log('🔍 Verify result: ' . var_export($verifyInsert, true));
        }
        
        $message = 'Google Drive connected successfully! You can now import videos without re-authenticating.';
        $hasRefreshToken = true;
    } else {
        // Only access token (user previously authorized) - UPDATE only
        $db->query("
            UPDATE IONGoogleDriveTokens
            SET access_token = ?,
                expires_at = ?,
                updated_at = NOW()
            WHERE user_id = ? AND email = ?
        ", [
            $tokens['access_token'],
            $expiresAt,
            $userId,
            $email
        ]);
        
        $message = 'Google Drive access token refreshed.';
        $hasRefreshToken = false;
    }
    
    // ============================================
    // STEP 6: Debug Output (TEMPORARY)
    // ============================================
    
    // Add temporary debug to see if we're reaching this point
    error_log('✅ TOKEN STORAGE COMPLETED - About to render success page');
    error_log('✅ Message: ' . $message);
    error_log('✅ Has refresh token flag: ' . ($hasRefreshToken ? 'YES' : 'NO'));
    
    // ============================================
    // STEP 7: Close Popup and Notify Parent Window
    // ============================================
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Google Drive Connected</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                margin: 0;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
            }
            .container {
                text-align: center;
                padding: 40px;
                background: rgba(255, 255, 255, 0.1);
                border-radius: 16px;
                backdrop-filter: blur(10px);
            }
            .success-icon {
                font-size: 64px;
                margin-bottom: 20px;
            }
            h1 {
                margin: 0 0 10px 0;
                font-size: 24px;
            }
            p {
                margin: 0 0 20px 0;
                opacity: 0.9;
            }
            .email {
                font-weight: bold;
                background: rgba(255, 255, 255, 0.2);
                padding: 8px 16px;
                border-radius: 8px;
                display: inline-block;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="success-icon">✅</div>
            <h1>Connecting...</h1>
            <p><?= htmlspecialchars($email) ?></p>
        </div>
        <script>
            // Send success message to parent window (opener)
            if (window.opener) {
                console.log('🔔 Sending message to opener window');
                
                const message = {
                    type: 'google_drive_connected',
                    email: <?= json_encode($email) ?>,
                    success: true,
                    hasRefreshToken: <?= json_encode($hasRefreshToken) ?>,
                    expiresIn: <?= $expiresIn ?>
                };
                
                // Send to opener (might be parent page)
                window.opener.postMessage(message, '*');
                
                // Also send to iframe if uploader is in iframe
                // Try to find the iframe in the opener's window
                try {
                    const uploaderIframe = window.opener.document.querySelector('iframe[src*="ionuploader"]') ||
                                          window.opener.document.querySelector('#ionVideoUploaderModal iframe');
                    
                    if (uploaderIframe && uploaderIframe.contentWindow) {
                        console.log('🔔 Found uploader iframe, sending message to it too');
                        uploaderIframe.contentWindow.postMessage(message, '*');
                    }
                } catch (e) {
                    console.log('ℹ️ Could not access iframe (expected if cross-origin)');
                }
                
                // Close popup immediately
                setTimeout(() => {
                    console.log('🔴 Closing popup');
                    window.close();
                }, 500); // Reduced from 2000ms to 500ms for better UX
            } else {
                console.log('⚠️ No opener window found');
                // If no opener, redirect to uploader after 3 seconds
                setTimeout(() => {
                    window.location.href = '/app/ionuploader.php';
                }, 3000);
            }
        </script>
    </body>
    </html>
    <?php
    
} catch (Exception $e) {
    error_log('❌ EXCEPTION storing Google Drive tokens: ' . $e->getMessage());
    error_log('❌ Stack trace: ' . $e->getTraceAsString());
    
    // Temporary debug output
    echo '<pre style="background: #000; color: #0f0; padding: 20px; font-family: monospace;">';
    echo "❌ EXCEPTION CAUGHT:\n\n";
    echo "Error: " . $e->getMessage() . "\n\n";
    echo "Stack Trace:\n" . $e->getTraceAsString() . "\n\n";
    echo "User ID: " . $userId . "\n";
    echo "Email: " . $email . "\n";
    echo "Has access token: " . (isset($tokens['access_token']) ? 'YES' : 'NO') . "\n";
    echo "Has refresh token: " . (isset($tokens['refresh_token']) ? 'YES' : 'NO') . "\n";
    echo '</pre>';
    die();
}
?>