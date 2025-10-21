<?php
/**
 * ION Join Page - User Registration with Optional Upgrade
 * Located at /join/index.php
 */

// Enable error reporting to see what's causing the 500 error
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// declare(strict_types=1);
session_start();

// Config + DB bootstrap
$CONFIG_FILE = __DIR__ . '/../config/config.php';
$DB_FILE     = __DIR__ . '/../config/database.php';

if (!file_exists($CONFIG_FILE)) {
    http_response_code(500);
    exit('Missing /config/config.php');
}

$config = require $CONFIG_FILE;
$GLOBALS['config'] = $config;  // Make config globally accessible

if (!file_exists($DB_FILE)) {
    http_response_code(500);
    exit('Missing /config/database.php');
}

require_once $DB_FILE;

// Debug mode - set to true to see session info
define('DEBUG_MODE', true);

// Enable error reporting in debug mode
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Constants
const APP_DASHBOARD_PATH = '/app/';

// Payment Settings
const PAYMENT_SETTINGS = [
    'show_direct_card_input' => false,  // Set to true to show inline card form
    'payment_providers' => [
        'paypal' => [
            'enabled' => true,
            'name' => 'PayPal',
            'icon' => '💳',
            'description' => 'Fast and secure payment'
        ],
        'stripe' => [
            'enabled' => true,
            'name' => 'Credit or Debit Card',
            'icon' => '💳',
            'description' => 'Powered by Stripe'
        ]
    ]
];

// Generate Google OAuth URL for registration
$google_oauth_url = 'https://accounts.google.com/o/oauth2/auth?' . http_build_query([
    'client_id' => $config['google_client_id'],
    'redirect_uri' => $config['google_redirect_uri'],
    'scope' => 'email profile',
    'response_type' => 'code',
    'access_type' => 'online',
    'prompt' => 'select_account',
    'state' => 'join' // Mark this as a join request
]);

/**
 * CSRF token generator
 */
function ion_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * CSRF token check
 */
function ion_csrf_check(?string $t): bool
{
    return isset($_SESSION['csrf_token'])
        && is_string($t)
        && hash_equals($_SESSION['csrf_token'], $t);
}

/**
 * Normalize email
 */
function ion_normalize_email(string $e): string
{
    $e = trim(mb_strtolower($e));
    return filter_var($e, FILTER_VALIDATE_EMAIL) ? $e : '';
}

/**
 * Clean name
 */
function ion_clean_name(string $n): string
{
    $n = trim(preg_replace('/\s+/', ' ', $n));
    return mb_substr($n, 0, 255);
}

/**
 * Simple redirect helper
 */
function ion_redirect(string $p)
{
    header("Location: {$p}", true, 302);
    exit;
}

/**
 * Mark user as logged in
 */
function ion_login_user(array $u): void
{
    $_SESSION['user_id'] = $u['user_id'] ?? null;
    $_SESSION['email']   = $u['email'] ?? null;
    $_SESSION['fullname'] = $u['fullname'] ?? null;
    $_SESSION['user_role'] = $u['user_role'] ?? 'Guest';
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();
}

/**
 * Send OTP - Simple working version
 */
function ion_send_otp_inline(string $email): array
{
    global $db, $config; 
    
    try {
        // Generate OTP
        $otp = rand(100000, 999999);
        $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        
        // Update OTP in database
        $result = $db->update('IONEERS', [
            'otp_code' => $otp,
            'expires_at' => $expires_at
        ], ['email' => $email]);
        
        if ($result === false) {
            return [
                'success' => false,
                'message' => 'Failed to generate verification code.'
            ];
        }
        
        // Now send the email using the working sendotp.php pattern
        require_once __DIR__ . '/../config/phpmailer/PHPMailer.php';
        require_once __DIR__ . '/../config/phpmailer/Exception.php';
        require_once __DIR__ . '/../config/phpmailer/SMTP.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        $mail->isSMTP();
        $mail->Host = $config['smtpHost'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['smtpUser'];
        $mail->Password = $config['smtpPass'];
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        $mail->setFrom($config['smtpFrom'], $config['siteName']);
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = "Your OTP Code [ $otp ] to access {$config['siteName']}";
        
        // Load email template
        if (file_exists(__DIR__ . '/../login/otpemail.php')) {
            require_once __DIR__ . '/../login/otpemail.php';
            $mail->Body = get_otp_email($otp, $email);
        } else {
            $mail->Body = "<h3>Your verification code is: <strong>$otp</strong></h3><p>This code expires in 10 minutes.</p>";
        }
        
        $mail->send();
        
        // Set session variables
        $_SESSION['pending_otp_email'] = $email;
        $_SESSION['otp_sent_time'] = time();
        
        return [
            'success' => true,
            'message' => 'We\'ve sent a verification code to ' . $email
        ];
        
    } catch (\Exception $e) {
        error_log("OTP Send Error: " . $e->getMessage());
        // Still return success if OTP was saved but email failed
        if (isset($result) && $result !== false) {
            return [
                'success' => true,
                'message' => 'Verification code generated. Please check your email.'
            ];
        }
        return [
            'success' => false,
            'message' => 'Unable to send verification code.'
        ];
    }
}

// Get database connection - FIXED: Removed isConnected() check
global $db;
if (!$db) {
    http_response_code(500);
    exit('Database connection error');
}

// Controller variables - INITIALIZE ALL VARIABLES HERE
$errors = [];
$messages = [];
$show_welcome = false;
$show_otp_form = false;
$otp_email = '';
$step = 1; // Default step

// Handle any OAuth errors
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'oauth_failed':
            $errors[] = 'Authentication failed. Please try again.';
            break;
        case 'no_account':
            $errors[] = 'No account found. Please sign up first.';
            break;
        case 'creation_failed':
            $errors[] = 'Unable to create account. Please try again.';
            break;
    }
}

// Check if we need to show OTP verification
if (!empty($_SESSION['pending_otp_email']) && empty($_POST['action'])) {
    $show_otp_form = true;
    $otp_email = $_SESSION['pending_otp_email'];
    $step = 3; // OTP verification step
    
    // Set OTP sent time if not set
    if (!isset($_SESSION['otp_sent_time'])) {
        $_SESSION['otp_sent_time'] = time();
    }
}

// Check if this is a fresh page load (not a form submission)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !isset($_GET['oauth_new_user']) && !isset($_GET['oauth_existing_guest']) && !$show_otp_form) {
    // Clear upgrade session flag to show Step 1
    unset($_SESSION['post_join_ready_for_upgrade']);
    unset($_SESSION['oauth_user_data']);
}

// Check if we're coming from Google OAuth
if (!$show_otp_form) {
    if (isset($_GET['oauth_new_user']) && isset($_SESSION['oauth_user_data'])) {
        // New user created via OAuth
        $step = 2;
        $messages[] = 'Welcome! Your account has been created.';
        $show_welcome = true;
    } elseif (isset($_GET['oauth_existing_guest']) && isset($_SESSION['oauth_user_data'])) {
        // Existing Guest user via OAuth
        $step = 2;
        $messages[] = 'Welcome back! Complete your upgrade to unlock Pro features.';
        $show_welcome = false;
    } else {
        // Default to step 1 unless user just created account AND submitted form
        $step = (!empty($_SESSION['post_join_ready_for_upgrade']) && $_SERVER['REQUEST_METHOD'] === 'POST') ? 2 : 1;
    }
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $action = $_POST['action'] ?? '';
    $token  = $_POST['csrf_token'] ?? '';
    
    if (!ion_csrf_check($token)) {
        $errors[] = 'Security token invalid or expired. Please refresh and try again.';
    } else {
        
        try {
            
            switch ($action) {
                
                case 'join_email': {
                    $fullname = isset($_POST['fullname']) ? ion_clean_name((string)$_POST['fullname']) : '';
                    $email    = isset($_POST['email'])    ? ion_normalize_email((string)$_POST['email']) : '';
                    
                    if ($fullname === '') {
                        $errors[] = 'Please enter your full name.';
                    }
                    
                    if ($email === '') {
                        $errors[] = 'Please enter a valid email address.';
                    }
                    
                    if (!$errors) {
                        // Check if user exists
                        $existing = $db->get_row("SELECT * FROM IONEERS WHERE email = ?", $email);
                        
                        if ($existing) {
                            // User exists - check their role
                            if ($existing->user_role === 'Guest') {
                                // Not upgraded yet - take them to step 2
                                ion_login_user((array)$existing);
                                $_SESSION['post_join_ready_for_upgrade'] = true;
                                $step = 2;
                                $messages[] = 'Welcome back! Complete your upgrade to unlock Pro features.';
                                $show_welcome = false;
                            } else {
                                // Any other role (Member, Creator, etc.) - already upgraded, send OTP
                                error_log("DEBUG join - User exists with role: " . $existing->user_role);
                                
                                // Log them in first
                                ion_login_user((array)$existing);
                                
                                $otp_result = ion_send_otp_inline($email);
                                if ($otp_result['success']) {
                                    $messages[] = $otp_result['message'];
                                    $show_otp_form = true;
                                    $otp_email = $email;
                                    $step = 3;
                                    $_SESSION['pending_otp_email'] = $email;
                                    $_SESSION['otp_sent_time'] = time();
                                } else {
                                    $errors[] = $otp_result['message'];
                                }
                            }
                        } else {
                            // Create new user
                            $user_data = [
                                'fullname' => $fullname,
                                'email' => $email,
                                'user_role' => 'Guest',
                                'status' => 'active',
                                'created_at' => date('Y-m-d H:i:s'),
                                'login_count' => 0
                            ];
                            
                            $insert_result = $db->insert('IONEERS', $user_data);
                            
                            if (!$insert_result) {
                                $errors[] = 'Unable to create your account. Please try again.';
                            } else {
                                // Get the created user
                                $user = $db->get_row("SELECT * FROM IONEERS WHERE email = ?", $email);
                                
                                if ($user) {
                                    ion_login_user((array)$user);
                                    $_SESSION['post_join_ready_for_upgrade'] = true;
                                    $step = 2;
                                    $messages[] = 'Welcome! Your account has been created.';
                                    $show_welcome = true;
                                } else {
                                    $errors[] = 'Account created but unable to log in. Please try logging in.';
                                }
                            }
                        }
                    }
                } break;
                
                case 'skip_upgrade': {
                    // User chooses not to upgrade - they remain as Guest
                    unset($_SESSION['post_join_ready_for_upgrade']);
                    unset($_SESSION['oauth_user_data']);
                    
                    // Check if they need to complete profile
                    $_SESSION['show_profile_wizard'] = true;
                    
                    // Direct to app as Guest
                    ion_redirect(APP_DASHBOARD_PATH);
                } break;
                
                case 'resend_otp': {
                    // Handle OTP resend
                    if (empty($_SESSION['pending_otp_email'])) {
                        $errors[] = 'Session expired. Please start over.';
                        break;
                    }
                    
                    $email = $_SESSION['pending_otp_email'];
                    $otp_result = ion_send_otp_inline($email);
                    
                    if ($otp_result['success']) {
                        $messages[] = 'New verification code sent to ' . $email;
                        $_SESSION['otp_sent_time'] = time(); // Reset timer
                    } else {
                        $errors[] = $otp_result['message'];
                    }
                    
                    $show_otp_form = true;
                    $otp_email = $email;
                    $step = 3;
                } break;
                
                case 'checkout': {
                    // Payment processing
                    if (empty($_SESSION['user_id'])) {
                        $errors[] = 'Your session expired. Please sign in again.';
                        break;
                    }
                    
                    $userId = (int) $_SESSION['user_id'];
                    $plan   = $_POST['plan'] ?? 'monthly';
                    $payment_method = $_POST['payment_method'] ?? '';
                    
                    if (empty($payment_method)) {
                        $errors[] = 'Please select a payment method.';
                        $step = 2;
                        break;
                    }
                    
                    // Handle different payment methods
                    $approved = false;
                    switch ($payment_method) {
                        case 'paypal':
                        case 'stripe':
                            // For demo purposes, auto-approve
                            $approved = true;
                            break;
                    }
                    
                    if (!empty($approved) && !$errors) {
                        // Upgrade to Member
                        $upgrade_role = 'Member';
                        $db->update('IONEERS', ['user_role' => $upgrade_role], ['user_id' => $userId]);
                        $_SESSION['user_role'] = $upgrade_role;
                        unset($_SESSION['post_join_ready_for_upgrade']);
                        unset($_SESSION['oauth_user_data']);
                        unset($_SESSION['payment_redirect']);
                        
                        // Check if they need to complete profile
                        $_SESSION['show_profile_wizard'] = true;
                        
                        // Direct to app dashboard
                        ion_redirect(APP_DASHBOARD_PATH);
                    }
                    
                    $step = 2;
                } break;
                
            }
            
        } catch (Throwable $e) {
            $errors[] = 'Unexpected error: ' . htmlspecialchars($e->getMessage());
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Join ION Network • Free to start</title>
    
    <!-- Try to load external stylesheet -->
    <link rel="stylesheet" href="/login/login.css">
    <link rel="stylesheet" href="/join/join.css">
    
    <style>
        /* Base styles - ensures page looks good even if external CSS fails */
        :root {
            --ion-accent: #896948;
            --ion-accent-hover: #a47e5a;
            --ion-accent-shadow: rgba(137, 105, 72, 0.25);
            --ion-bg: #1a1a1a;
            --ion-surface: rgba(255, 255, 255, 0.05);
            --ion-border: rgba(137, 105, 72, 0.3);
            --ion-text: #ffffff;
            --ion-text-muted: #cccccc;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            background: var(--ion-bg);
            color: var(--ion-text);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        /* Enhanced Google OAuth Button */
        .google-oauth-button {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background-color: rgba(71, 85, 105, 0.5);
            color: var(--ion-text);
            border: 1px solid rgba(137, 105, 72, 0.4);
            border-radius: 8px;
            padding: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
            min-height: 56px;
            text-decoration: none;
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
        }
        
        .google-oauth-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transition: left 0.5s ease;
        }
        
        .google-oauth-button:hover::before {
            left: 100%;
        }
        
        .google-oauth-button:hover {
            background-color: rgba(100, 116, 139, 0.6);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(137, 105, 72, 0.3);
            border-color: var(--ion-accent);
        }
        
        .google-icon-wrapper {
            background-color: white;
            border-radius: 6px;
            height: 48px;
            width: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .google-button-text {
            flex: 1;
            text-align: center;
            font-size: 16px;
            font-weight: 500;
            padding: 0 16px;
            color: white;
        }
        
        .google-user-indicator {
            height: 48px;
            width: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #e5e7eb, #d1d5db);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border: 2px solid white;
            font-size: 18px;
            font-weight: 600;
            color: #6b7280;
        }
        
        .user-initial {
            text-transform: uppercase;
        }
        
        /* Divider */
        .divider {
            position: relative;
            margin: 24px 0;
            text-align: center;
        }
        
        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: rgba(137, 105, 72, 0.3);
        }
        
        .divider small {
            position: relative;
            display: inline-block;
            padding: 0 16px;
            background: var(--ion-bg);
            color: var(--ion-text-muted);
            font-size: 14px;
        }
        
        /* OTP Styles */
        .otp-boxes {
            display: flex;
            gap: 8px;
            justify-content: center;
            margin-bottom: 20px;
        }
        
        .otp-digit {
            width: 45px;
            height: 45px;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.05);
            color: #ffffff;
            font-size: 18px;
            font-weight: 600;
            text-align: center;
            transition: all 0.3s ease;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }
        
        .otp-digit:focus {
            outline: none;
            border-color: #ffd700;
            background: rgba(255, 255, 255, 0.1);
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.1);
        }
        
        .timer {
            color: #cccccc;
            font-size: 14px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        #countdown {
            color: #ffd700;
            font-weight: 600;
        }
    </style>
</head>
<body>
    
    <div class="join-container">
        <div class="join-card">
            
            <!-- Notices -->
            <?php if ($messages || $errors): ?>
            <div class="notice">
                <?php foreach ($messages as $m): ?>
                    <div class="success"><?= htmlspecialchars($m) ?></div>
                <?php endforeach; ?>
                
                <?php foreach ($errors as $e): ?>
                    <div class="error"><?= htmlspecialchars($e) ?></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- Content -->
            <?php if ($step === 3): ?>
                
                <!-- JOIN STEP 3 (OTP VERIFICATION) -->
                <div class="join-grid">
                    <div class="left-panel">
                        <div class="header-section">
                            <div class="logo-container">
                                <img src="https://ions.com/menu/ion-logo-gold.png" alt="ION Network">
                            </div>
                            <div class="header-content">
                                <h1 class="panel-title">Verify your email</h1>
                                <p class="subtitle">Enter the code we sent to <?= htmlspecialchars($otp_email) ?></p>
                            </div>
                        </div>
                        
                        <form method="POST" action="/login/verifyotp.php" class="ajax-form" id="otp-form">
                            <input type="hidden" name="email" value="<?= htmlspecialchars($otp_email) ?>">
                            <input type="hidden" name="from_join" value="1">
                            
                            <div class="form-group">
                                <label style="display: block; margin-bottom: 15px;">Enter 6-digit code</label>
                                <div class="otp-boxes">
                                    <?php for ($i = 1; $i <= 6; $i++): ?>
                                        <input
                                            type="text"
                                            class="otp-digit"
                                            name="otp[]"
                                            id="digit<?= $i ?>"
                                            maxlength="1"
                                            pattern="\d"
                                            inputmode="numeric"
                                            autocomplete="off"
                                            <?= $i === 1 ? 'autofocus' : '' ?>
                                        >
                                    <?php endfor; ?>
                                </div>
                            </div>
                            
                            <button class="btn btn-primary" type="submit">
                                Verify & Continue →
                            </button>
                            
                            <div class="timer" style="margin-top: 20px;">
                                <span id="countdown">10:00</span> remaining
                            </div>
                            
                            <div style="text-align: center; margin-top: 15px;">
                                <a href="#" 
                                   onclick="resendOTP(); return false;"
                                   style="color: var(--ion-accent); text-decoration: none; font-size: 14px; transition: all 0.3s ease;"
                                   onmouseover="this.style.textDecoration='underline'"
                                   onmouseout="this.style.textDecoration='none'">
                                    Resend Code
                                </a>
                            </div>
                        </form>
                    </div>
                    
                    <div class="right-panel">
                        <div class="floating-shapes">
                            <div class="shape shape-1"></div>
                            <div class="shape shape-2"></div>
                            <div class="shape shape-3"></div>
                        </div>
                        
                        <h2 class="panel-title">Almost there!</h2>
                        
                        <div style="text-align: center; margin: 40px 0;">
                            <div style="font-size: 80px; margin-bottom: 20px;">✉️</div>
                            <p style="color: #d1d5db; font-size: 16px; line-height: 1.6;">
                                We've sent a 6-digit verification code to your email address. 
                                This helps us ensure your account security.
                            </p>
                        </div>
                        
                        <div class="benefits-box" style="margin-top: 40px;">
                            <h3>Why we verify:</h3>
                            <ul style="text-align: left;">
                                <li>✓ Protect your account from unauthorized access</li>
                                <li>✓ Ensure you receive important notifications</li>
                                <li>✓ Enable secure password recovery</li>
                            </ul>
                        </div>
                        
                        <div class="security-badges" style="position: absolute; bottom: 30px; left: 0; right: 0;">
                            <span class="security-badge">🔒 SSL Secured</span>
                            <span class="security-badge">✓ GDPR Compliant</span>
                            <span class="security-badge">⭐ 5-Star Rated</span>
                        </div>
                    </div>
                </div>
                
            <?php elseif ($step === 1): ?>
                
                <!-- JOIN STEP 1 -->
                <div class="join-grid">
                    <div class="left-panel">
                        <div class="header-section">
                            <div class="logo-container">
                                <img src="https://ions.com/menu/ion-logo-gold.png" alt="ION Network">
                            </div>
                            <div class="header-content">
                                <h1 class="panel-title">Get started today!</h1>
                                <p class="subtitle">Free to start. No card required.</p>
                            </div>
                        </div>
                        
                        <a href="<?= htmlspecialchars($google_oauth_url) ?>" class="google-oauth-button">
                            <div class="google-icon-wrapper">
                                <svg width="20" height="20" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                    <defs>
                                        <path d="M18.908 8.14h-9.12v3.843h5.25c-.49 2.441-2.536 3.843-5.25 3.843-3.204 0-5.784-2.623-5.784-5.878 0-3.256 2.58-5.878 5.784-5.878 1.379 0 2.625.497 3.603 1.31l2.848-2.893C14.504.95 12.279 0 9.788 0 4.36 0 0 4.431 0 9.948c0 5.516 4.36 9.948 9.788 9.948 4.893 0 9.342-3.618 9.342-9.948a8.38 8.38 0 00-.222-1.809z" id="google-a"></path>
                                    </defs>
                                    <g fill="none" fill-rule="evenodd">
                                        <mask id="google-b" fill="#fff">
                                            <use href="#google-a"></use>
                                        </mask>
                                        <path fill="#FBBC05" fill-rule="nonzero" mask="url(#google-b)" d="M-.89 15.826V4.07l7.563 5.878z"></path>
                                        <path fill="#EA4335" fill-rule="nonzero" mask="url(#google-b)" d="M-.89 4.07l7.563 5.878L9.788 7.19l10.677-1.764v-6.33H-.89z"></path>
                                        <path fill="#34A853" fill-rule="nonzero" mask="url(#google-b)" d="M-.89 15.826l13.347-10.4 3.515.452 4.493-6.782V20.8H-.89z"></path>
                                        <path fill="#4285F4" fill-rule="nonzero" mask="url(#google-b)" d="M20.465 20.8L6.673 9.948 4.893 8.59l15.573-4.52z"></path>
                                    </g>
                                </svg>
                            </div>
                            <span class="google-button-text">Continue with Google</span>
                            <div class="google-user-indicator">
                                <span class="user-initial">G</span>
                            </div>
                        </a>
                        
                        <div class="divider">
                            <small>or</small>
                        </div>
                        
                        <form method="POST" class="ajax-form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ion_csrf_token()) ?>">
                            <input type="hidden" name="action" value="join_email">
                            
                            <div class="form-group">
                                <label for="fullname">Full Name</label>
                                <input
                                    type="text"
                                    id="fullname"
                                    name="fullname"
                                    placeholder="John Doe"
                                    required
                                >
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    placeholder="you@example.com"
                                    required
                                >
                            </div>
                            
                            <button class="btn btn-primary" type="submit">
                                Create my account →
                            </button>
                        </form>
                    </div>
                    
                    <div class="right-panel">
                        <div class="floating-shapes">
                            <div class="shape shape-1"></div>
                            <div class="shape shape-2"></div>
                            <div class="shape shape-3"></div>
                        </div>
                        
                        <h2 class="panel-title">Grow your sales with ION</h2>
                        
                        <div class="testimonial">
                            <div class="testimonial-avatar">JD</div>
                            <p class="testimonial-text">
                                "ION has been a transformative platform for my business, allowing me to 10x my sales since I started using it."
                            </p>
                            <p class="testimonial-author">Jane Doe, CEO of TechCorp</p>
                            <div class="stars">
                                <span class="star">⭐</span>
                                <span class="star">⭐</span>
                                <span class="star">⭐</span>
                                <span class="star">⭐</span>
                                <span class="star">⭐</span>
                            </div>
                        </div>
                        
                        <p class="text-center" style="margin-top: 40px; font-size: 14px; color: var(--ion-text-muted);">
                            Already have an account? 
                            <a href="/login/" class="link">Log in here</a>
                        </p>
                        
                        <div class="security-badges" style="position: absolute; bottom: 30px; left: 0; right: 0;">
                            <span class="security-badge">🔒 SSL Secured</span>
                            <span class="security-badge">✓ GDPR Compliant</span>
                            <span class="security-badge">⭐ 5-Star Rated</span>
                        </div>
                    </div>
                </div>
                
            <?php else: ?>
                
                <!-- JOIN STEP 2 (UPGRADE) -->
                <div class="join-grid">
                    <div class="left-panel">
                        <div class="payment-form-container">
                            <div class="header-section reverse">
                                <div class="logo-container">
                                    <img src="https://ions.com/menu/ion-logo-gold.png" alt="ION Network">
                                </div>
                                <div class="header-content">
                                    <h1 class="panel-title">Unlock Pro Features</h1>
                                    <p class="subtitle">Get powerful tools to maximize your success.</p>
                                </div>
                            </div>
                            
                            <div class="form-section">
                                <div class="pricing-options">
                                    <div class="pricing-card" data-plan="monthly">
                                        <div class="pricing-title">Monthly</div>
                                        <div class="price-amount">$8.95<span class="price-period">/mo</span></div>
                                        <div class="pricing-description">Cancel anytime</div>
                                    </div>
                                    
                                    <div class="pricing-card" data-plan="quarterly">
                                        <div class="pricing-title">Quarterly</div>
                                        <div class="price-amount">$8<span class="price-period">/mo</span></div>
                                        <div class="pricing-description">$24 billed quarterly</div>
                                        <div class="savings-badge">Save 11%</div>
                                    </div>
                                    
                                    <div class="pricing-card popular" data-plan="yearly">
                                        <span class="popular-label">Best Value</span>
                                        <div class="pricing-title">Yearly</div>
                                        <div class="price-amount">$5<span class="price-period">/mo</span></div>
                                        <div class="pricing-description">$60 billed annually</div>
                                        <div class="savings-badge">Save 44%</div>
                                    </div>
                                </div>
                                
                                <div class="divider"></div>
                                
                                <form method="POST" id="checkout-form" class="ajax-form">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ion_csrf_token()) ?>">
                                    <input type="hidden" name="action" value="checkout">
                                    <input type="hidden" name="plan" id="selected-plan" value="yearly">
                                    <input type="hidden" name="payment_method" id="selected-payment-method" value="">
                                    
                                    <!-- Payment method selection -->
                                    <h3 style="color: white; font-size: 16px; margin-bottom: 15px;">Select Payment Method</h3>
                                    
                                    <div class="payment-methods">
                                        <?php foreach (PAYMENT_SETTINGS['payment_providers'] as $key => $provider): ?>
                                            <?php if ($provider['enabled']): ?>
                                                <div class="payment-method" data-method="<?= htmlspecialchars($key) ?>">
                                                    <div class="payment-method-icon"><?= htmlspecialchars($provider['icon']) ?></div>
                                                    <div class="payment-method-info">
                                                        <div class="payment-method-name"><?= htmlspecialchars($provider['name']) ?></div>
                                                        <div class="payment-method-description"><?= htmlspecialchars($provider['description']) ?></div>
                                                    </div>
                                                    <div class="payment-method-check"></div>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <div class="payment-footer">
                                        <button class="btn btn-primary" type="submit" style="width: 100%;">
                                            🔒 Continue to Payment
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="right-panel">
                        <div class="skip-button-container">
                            <button class="btn btn-ghost" type="button" id="skip-btn">
                                Skip for now →
                            </button>
                        </div>
                        
                        <div class="floating-shapes">
                            <div class="shape shape-1"></div>
                            <div class="shape shape-2"></div>
                            <div class="shape shape-3"></div>
                        </div>
                        
                        <h2 class="panel-title">Pro Benefits</h2>
                        
                        <ul class="features-list">
                            <li>
                                <span class="check-icon">✓</span>
                                <div>
                                    <strong>Premium Content Management</strong><br>
                                    Advanced templates and AI-powered creation tools
                                </div>
                            </li>
                            <li>
                                <span class="check-icon">✓</span>
                                <div>
                                    <strong>100 Creations Upload Capacity</strong><br>
                                    10x more than free accounts
                                </div>
                            </li>
                            <li>
                                <span class="check-icon">✓</span>
                                <div>
                                    <strong>Priority Moderation</strong><br>
                                    Get approved in minutes, not hours
                                </div>
                            </li>
                            <li>
                                <span class="check-icon">✓</span>
                                <div>
                                    <strong>Advanced Analytics</strong><br>
                                    Deep insights into your performance
                                </div>
                            </li>
                            <li>
                                <span class="check-icon">✓</span>
                                <div>
                                    <strong>White-Label Options</strong><br>
                                    Custom branding for your channel
                                </div>
                            </li>
                        </ul>
                        
                        <div class="guarantee-box">
                            <p class="guarantee-title">30-Day Money Back Guarantee</p>
                            <p class="guarantee-text">
                                Not satisfied? Get a full refund, no questions asked.
                            </p>
                        </div>
                    </div>
                </div>
                
            <?php endif; ?>
            
        </div>
    </div>
    
    <!-- Welcome Dialog (hidden by default) -->
    <div class="welcome-dialog-overlay" id="welcome-dialog">
        <div class="welcome-dialog">
            <h2>🎉 Welcome to ION!</h2>
            <p>Your account has been created successfully. Let's get you started!</p>
            <button class="btn btn-primary" onclick="closeWelcomeDialog()">Continue</button>
        </div>
    </div>
    
    <canvas id="confetti-canvas"></canvas>
    
    <script>
        // Pricing card selection
        const pricingCards = document.querySelectorAll('.pricing-card');
        const selectedPlanInput = document.getElementById('selected-plan');
        
        pricingCards.forEach(card => {
            card.addEventListener('click', () => {
                pricingCards.forEach(c => c.classList.remove('selected'));
                card.classList.add('selected');
                selectedPlanInput.value = card.dataset.plan;
            });
        });
        
        // Set default selection
        document.querySelector('[data-plan="yearly"]')?.classList.add('selected');
        
        // Skip button
        const skipBtn = document.getElementById('skip-btn');
        
        if (skipBtn) {
            skipBtn.addEventListener('click', () => {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ion_csrf_token()) ?>">
                    <input type="hidden" name="action" value="skip_upgrade">
                `;
                
                document.body.appendChild(form);
                form.submit();
            });
        }
        
        // Payment method selection
        const paymentMethods = document.querySelectorAll('.payment-method');
        const selectedPaymentInput = document.getElementById('selected-payment-method');
        
        paymentMethods.forEach(method => {
            method.addEventListener('click', () => {
                paymentMethods.forEach(m => m.classList.remove('selected'));
                method.classList.add('selected');
                selectedPaymentInput.value = method.dataset.method;
            });
        });
        
        // Validate payment method on form submit
        const checkoutForm = document.getElementById('checkout-form');
        if (checkoutForm && !document.getElementById('card_number')) {
            checkoutForm.addEventListener('submit', (e) => {
                if (!selectedPaymentInput.value) {
                    e.preventDefault();
                    alert('Please select a payment method');
                }
            });
        }
        
        // OTP input handling
        const otpInputs = document.querySelectorAll('.otp-digit');
        if (otpInputs.length > 0) {
            otpInputs.forEach((input, index) => {
                input.addEventListener('input', function(e) {
                    // Only allow digits
                    this.value = this.value.replace(/[^0-9]/g, '');
                    
                    // Move to next input
                    if (this.value.length === 1 && index < otpInputs.length - 1) {
                        otpInputs[index + 1].focus();
                    }
                    
                    // Auto-submit when all filled
                    if (index === otpInputs.length - 1) {
                        let allFilled = true;
                        otpInputs.forEach(inp => {
                            if (inp.value.length !== 1) allFilled = false;
                        });
                        if (allFilled) {
                            document.getElementById('otp-form').submit();
                        }
                    }
                });
                
                input.addEventListener('keydown', function(e) {
                    // Handle backspace
                    if (e.key === 'Backspace' && this.value === '' && index > 0) {
                        otpInputs[index - 1].focus();
                    }
                });
                
                // Handle paste
                input.addEventListener('paste', function(e) {
                    e.preventDefault();
                    const pastedData = e.clipboardData.getData('text').replace(/[^0-9]/g, '');
                    if (pastedData.length >= 6) {
                        for (let i = 0; i < 6; i++) {
                            if (otpInputs[i]) {
                                otpInputs[i].value = pastedData[i] || '';
                            }
                        }
                        otpInputs[5].focus();
                        // Auto-submit
                        setTimeout(() => {
                            document.getElementById('otp-form').submit();
                        }, 100);
                    }
                });
            });
            
            // Timer countdown
            <?php if ($show_otp_form && isset($_SESSION['otp_sent_time'])): ?>
            const otpSentTime = <?= $_SESSION['otp_sent_time'] ?>;
            const countdownEl = document.getElementById('countdown');
            
            function updateCountdown() {
                const now = Math.floor(Date.now() / 1000);
                const elapsed = now - otpSentTime;
                const remaining = Math.max(0, 600 - elapsed); // 10 minutes
                
                const minutes = Math.floor(remaining / 60);
                const seconds = remaining % 60;
                
                if (countdownEl) {
                    countdownEl.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
                }
                
                if (remaining > 0) {
                    setTimeout(updateCountdown, 1000);
                } else {
                    if (countdownEl) {
                        countdownEl.textContent = '0:00';
                        countdownEl.style.color = '#ff4d4d';
                    }
                }
            }
            
            updateCountdown();
            <?php endif; ?>
        }
        
        // Resend OTP function
        function resendOTP() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            form.innerHTML = `
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ion_csrf_token()) ?>">
                <input type="hidden" name="action" value="resend_otp">
            `;
            
            document.body.appendChild(form);
            form.submit();
        }
        
        // Welcome dialog and confetti
        <?php if ($show_welcome): ?>
        function showWelcomeDialog() {
            const dialog = document.getElementById('welcome-dialog');
            dialog.classList.add('active');
            startConfetti();
            
            // Auto-close after 5 seconds
            setTimeout(() => {
                closeWelcomeDialog();
            }, 5000);
        }
        
        function closeWelcomeDialog() {
            const dialog = document.getElementById('welcome-dialog');
            dialog.classList.remove('active');
            stopConfetti();
        }
        
        // Simple confetti animation
        let confettiInterval;
        const canvas = document.getElementById('confetti-canvas');
        const ctx = canvas.getContext('2d');
        const particles = [];
        
        function resizeCanvas() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        }
        
        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);
        
        class Particle {
            constructor() {
                this.x = Math.random() * canvas.width;
                this.y = -10;
                this.vx = (Math.random() - 0.5) * 2;
                this.vy = Math.random() * 3 + 2;
                this.color = ['#896948', '#a47e5a', '#FFD700', '#FFA500', '#FF69B4'][Math.floor(Math.random() * 5)];
                this.size = Math.random() * 3 + 2;
                this.angle = Math.random() * Math.PI * 2;
                this.angleVelocity = (Math.random() - 0.5) * 0.2;
            }
            
            update() {
                this.x += this.vx;
                this.y += this.vy;
                this.angle += this.angleVelocity;
                this.vy += 0.1; // gravity
            }
            
            draw() {
                ctx.save();
                ctx.translate(this.x, this.y);
                ctx.rotate(this.angle);
                ctx.fillStyle = this.color;
                ctx.fillRect(-this.size / 2, -this.size / 2, this.size, this.size);
                ctx.restore();
            }
        }
        
        function startConfetti() {
            confettiInterval = setInterval(() => {
                for (let i = 0; i < 5; i++) {
                    particles.push(new Particle());
                }
            }, 50);
            
            animate();
        }
        
        function animate() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            particles.forEach((particle, index) => {
                particle.update();
                particle.draw();
                
                if (particle.y > canvas.height) {
                    particles.splice(index, 1);
                }
            });
            
            if (particles.length > 0 || confettiInterval) {
                requestAnimationFrame(animate);
            }
        }
        
        function stopConfetti() {
            clearInterval(confettiInterval);
            confettiInterval = null;
            setTimeout(() => {
                particles.length = 0;
                ctx.clearRect(0, 0, canvas.width, canvas.height);
            }, 3000);
        }
        
        // Show welcome dialog on page load
        window.addEventListener('load', () => {
            setTimeout(showWelcomeDialog, 500);
        });
        <?php endif; ?>
    </script>
    
    <?php if (DEBUG_MODE): ?>
    <!-- Debug Info -->
    <div style="position: fixed; bottom: 10px; right: 10px; background: #333; color: #fff; padding: 10px; font-size: 12px; max-width: 300px;">
        <strong>Debug Info:</strong><br>
        Step: <?= $step ?><br>
        OTP Email: <?= htmlspecialchars($otp_email ?: 'none') ?><br>
        Session User: <?= $_SESSION['email'] ?? 'none' ?><br>
        Role: <?= $_SESSION['user_role'] ?? 'none' ?><br>
        Pending OTP: <?= isset($_SESSION['pending_otp_email']) ? 'yes' : 'no' ?>
    </div>
    <?php endif; ?>
    
</body>
</html>