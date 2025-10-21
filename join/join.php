<?php
/**
 * ION Join Page - User Registration with Optional Upgrade
 * Located at /join/index.php
 * 
 * User Role Hierarchy:
 * - Guest: Signed up but not verified or upgraded
 * - Creator: Signed up and verified email, but not upgraded
 * - Member: Signed up and paid/upgraded
 * 
 * Flow for existing accounts:
 * - If user exists with 'Guest' role -> proceed to step 2 (upgrade)
 * - If user exists with any other role -> OTP verification
 * - If new user -> create account as Guest and proceed to step 2
 * 
 * Google OAuth Integration Notes:
 * The OAuth callback should implement the same logic:
 * 1. Check if user exists by email
 * 2. If exists and role='Guest' -> set $_SESSION['oauth_user_data'] and redirect here with ?oauth_existing_guest=1
 * 3. If exists and role!='Guest' -> set up OTP and redirect here
 * 4. If new user -> create as Guest, then redirect here with ?oauth_new_user=1
 */

declare(strict_types=1);
session_start();

// Config + DB bootstrap
$CONFIG_FILE = __DIR__ . '/../config/config.php';
$DB_FILE     = __DIR__ . '/../config/database.php';

if (!file_exists($CONFIG_FILE)) {
    http_response_code(500);
    exit('Missing /config/config.php');
}

$config = require $CONFIG_FILE;

if (!file_exists($DB_FILE)) {
    http_response_code(500);
    exit('Missing /config/database.php');
}

require_once $DB_FILE;

// Debug mode - set to true to see session info
define('DEBUG_MODE', false);

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
 * Send OTP and handle verification inline
 */
function ion_send_otp_inline(string $email): array
{
    // In production, this would actually send an OTP email
    // For now, we'll just set up the session for OTP verification
    $_SESSION['pending_otp_email'] = $email;
    $_SESSION['otp_sent_time'] = time();
    $_SESSION['otp_code'] = sprintf('%06d', mt_rand(0, 999999)); // In production, store this securely
    
    return [
        'success' => true,
        'message' => 'We\'ve sent a verification code to ' . $email
    ];
}

// Get database connection
global $db;
if (!$db || !$db->isConnected()) {
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
                                $otp_result = ion_send_otp_inline($email);
                                if ($otp_result['success']) {
                                    $messages[] = $otp_result['message'];
                                    $show_otp_form = true;
                                    $otp_email = $email;
                                    $step = 3;
                                    ion_login_user((array)$existing);
                                } else {
                                    $errors[] = 'Unable to send verification code. Please try again.';
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
                
                case 'verify_otp': {
                    // Handle OTP verification
                    if (empty($_SESSION['pending_otp_email'])) {
                        $errors[] = 'Session expired. Please try again.';
                        break;
                    }
                    
                    $entered_otp = preg_replace('/\D/', '', $_POST['otp_code'] ?? '');
                    
                    if (strlen($entered_otp) !== 6) {
                        $errors[] = 'Please enter the 6-digit code.';
                        $show_otp_form = true;
                        $otp_email = $_SESSION['pending_otp_email'];
                        $step = 3;
                    } else {
                        // In production, verify the actual OTP
                        // For now, we'll simulate success
                        $otp_valid = ($entered_otp === $_SESSION['otp_code']);
                        
                        if ($otp_valid) {
                            // Clear OTP session data
                            unset($_SESSION['pending_otp_email']);
                            unset($_SESSION['otp_code']);
                            unset($_SESSION['otp_sent_time']);
                            
                            // Update user role from Guest to Creator if they were Guest
                            if ($_SESSION['user_role'] === 'Guest') {
                                $userId = (int) $_SESSION['user_id'];
                                $db->update('IONEERS', ['user_role' => 'Creator'], ['user_id' => $userId]);
                                $_SESSION['user_role'] = 'Creator';
                            }
                            
                            // Check if they need to complete profile
                            $_SESSION['show_profile_wizard'] = true;
                            
                            // Redirect to app dashboard
                            ion_redirect(APP_DASHBOARD_PATH);
                        } else {
                            $errors[] = 'Invalid verification code. Please try again.';
                            $show_otp_form = true;
                            $otp_email = $_SESSION['pending_otp_email'];
                            $step = 3;
                        }
                    }
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
                    switch ($payment_method) {
                        case 'paypal':
                            // In production, this would redirect to PayPal checkout
                            // For now, simulate successful payment
                            $_SESSION['payment_redirect'] = [
                                'provider' => 'paypal',
                                'plan' => $plan,
                                'amount' => $plan === 'yearly' ? '60.00' : ($plan === 'quarterly' ? '24.00' : '8.95')
                            ];
                            
                            // Simulate PayPal redirect
                            $messages[] = 'Redirecting to PayPal...';
                            
                            // In production: header('Location: ' . $paypal_checkout_url);
                            // For demo: simulate success
                            $approved = true;
                            break;
                            
                        case 'stripe':
                            // In production, this would create a Stripe checkout session
                            $_SESSION['payment_redirect'] = [
                                'provider' => 'stripe',
                                'plan' => $plan,
                                'amount' => $plan === 'yearly' ? '60.00' : ($plan === 'quarterly' ? '24.00' : '8.95')
                            ];
                            
                            // Simulate Stripe redirect
                            $messages[] = 'Redirecting to secure payment...';
                            
                            // In production: header('Location: ' . $stripe_checkout_url);
                            // For demo: simulate success
                            $approved = true;
                            break;
                            
                        case 'direct_card':
                            // Original inline card processing (if enabled)
                            if (!PAYMENT_SETTINGS['show_direct_card_input']) {
                                $errors[] = 'Direct card input is not enabled.';
                                break;
                            }
                            
                            // Card validation
                            $card = preg_replace('/\D+/', '', (string)($_POST['card_number'] ?? ''));
                            $exp  = preg_replace('/\s+/', '', (string)($_POST['card_exp'] ?? ''));
                            $cvc  = preg_replace('/\D+/', '', (string)($_POST['card_cvc'] ?? ''));
                            
                            if (strlen($card) < 12) {
                                $errors[] = 'Please enter a valid card number.';
                            }
                            
                            if (!preg_match('/^\d{2}\/\d{2}$/', $exp)) {
                                $errors[] = 'Use MM/YY for expiry.';
                            }
                            
                            if (strlen($cvc) < 3) {
                                $errors[] = 'Please enter a valid CVC.';
                            }
                            
                            if (!$errors) {
                                // PAYMENT STUB: approve test card 4242...
                                $approved = ($card === '4242424242424242');
                                
                                if (!$approved) {
                                    $errors[] = 'Payment declined. Try card 4242 4242 4242 4242 for testing.';
                                }
                            }
                            break;
                            
                        default:
                            $errors[] = 'Invalid payment method selected.';
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
                        
                        <form method="POST" class="ajax-form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ion_csrf_token()) ?>">
                            <input type="hidden" name="action" value="verify_otp">
                            
                            <div class="form-group">
                                <label for="otp_code">Verification Code</label>
                                <input
                                    type="text"
                                    id="otp_code"
                                    name="otp_code"
                                    placeholder="000000"
                                    maxlength="6"
                                    pattern="\d{6}"
                                    style="text-align: center; font-size: 24px; letter-spacing: 0.5em;"
                                    required
                                    autofocus
                                >
                            </div>
                            
                            <button class="btn btn-primary" type="submit">
                                Verify & Continue →
                            </button>
                            
                            <p class="text-center" style="margin-top: 20px; font-size: 14px; color: var(--ion-text-muted);">
                                Didn't receive the code? 
                                <a href="#" class="link" onclick="alert('Resend functionality would be implemented here'); return false;">Resend</a>
                            </p>
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
                        
                        <a href="<?= htmlspecialchars($google_oauth_url) ?>" class="btn btn-outline" style="margin-bottom: 20px;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 48 48" style="vertical-align: middle;">
                                <path fill="#FFC107" d="M43.611,20.083H42V20H24v8h11.303c-1.649,4.657-6.08,8-11.303,8c-6.627,0-12-5.373-12-12c0-6.627,5.373-12,12-12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C12.955,4,4,12.955,4,24c0,11.045,8.955,20,20,20c11.045,0,20-8.955,20-20C44,22.659,43.862,21.35,43.611,20.083z"/>
                                <path fill="#FF3D00" d="M6.306,14.691l6.571,4.819C14.655,15.108,18.961,12,24,12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C16.318,4,9.656,8.337,6.306,14.691z"/>
                                <path fill="#4CAF50" d="M24,44c5.166,0,9.86-1.977,13.409-5.192l-6.19-5.238C29.211,35.091,26.715,36,24,36c-5.202,0-9.619-3.317-11.283-7.946l-6.522,5.025C9.505,39.556,16.227,44,24,44z"/>
                                <path fill="#1976D2" d="M43.611,20.083H42V20H24v8h11.303c-0.792,2.237-2.231,4.166-4.087,5.571c0.001-0.001,0.002-0.001,0.003-0.002l6.19,5.238C36.971,39.205,44,34,44,24C44,22.659,43.862,21.35,43.611,20.083z"/>
                            </svg>
                            Continue with Google
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
                                    
                                    <?php if (PAYMENT_SETTINGS['show_direct_card_input']): ?>
                                        <!-- Direct card input (if enabled) -->
                                        <div class="form-group card-input-group">
                                            <span class="card-icon">💳</span>
                                            <label for="card_number">Card Number</label>
                                            <input
                                                name="card_number"
                                                id="card_number"
                                                placeholder="4242 4242 4242 4242 (test card)"
                                                required
                                            >
                                        </div>
                                        
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label for="card_exp">Expiry</label>
                                                <input
                                                    name="card_exp"
                                                    id="card_exp"
                                                    placeholder="MM/YY"
                                                    required
                                                >
                                            </div>
                                            
                                            <div class="form-group">
                                                <label for="card_cvc">CVC</label>
                                                <input
                                                    name="card_cvc"
                                                    id="card_cvc"
                                                    placeholder="123"
                                                    required
                                                >
                                            </div>
                                        </div>
                                        
                                        <script>
                                            // Set payment method for direct card
                                            document.getElementById('selected-payment-method').value = 'direct_card';
                                        </script>
                                    <?php else: ?>
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
                                    <?php endif; ?>
                                    
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
        
        // Format card number (if direct input is enabled)
        document.getElementById('card_number')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s/g, '');
            let formatted = value.match(/.{1,4}/g)?.join(' ') || value;
            e.target.value = formatted;
        });
        
        // Format expiry (if direct input is enabled)
        document.getElementById('card_exp')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.slice(0, 2) + '/' + value.slice(2, 4);
            }
            e.target.value = value;
        });
        
        // Only numbers in CVC (if direct input is enabled)
        document.getElementById('card_cvc')?.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '');
        });
        
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
        
        // OTP input formatting
        const otpInput = document.getElementById('otp_code');
        if (otpInput) {
            otpInput.addEventListener('input', function(e) {
                // Only allow numbers
                e.target.value = e.target.value.replace(/\D/g, '');
                
                // Auto-submit when 6 digits entered
                if (e.target.value.length === 6) {
                    // Optional: auto-submit the form
                    // e.target.form.submit();
                }
            });
            
            // Focus on page load
            otpInput.focus();
        }
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