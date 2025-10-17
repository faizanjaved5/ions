<?php
/**
 * ION Join Page - User Registration with Optional Upgrade
 * Located at /join/index.php
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

// Constants
const APP_DASHBOARD_PATH = '/app/';
const REQUIRE_OTP_AFTER_PAYMENT = false; // Set to true if you want OTP verification after payment

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
 * Send OTP and redirect to verification
 */
function ion_send_otp_redirect(string $email)
{
    $_SESSION['pending_email'] = $email;
    $_SESSION['require_otp_verification'] = true;
    
    // Redirect to login page with OTP flow
    header('Location: /login/index.php?show_otp=1&from_join=1');
    exit;
}

// Get database connection
global $db;
if (!$db || !$db->isConnected()) {
    http_response_code(500);
    exit('Database connection error');
}

// Controller variables
$errors    = [];
$messages  = [];

// Check if we're coming from Google OAuth with a new user
if (isset($_GET['oauth_new_user']) && isset($_SESSION['oauth_user_data'])) {
    $step = 2; // Go directly to upgrade step
    $messages[] = 'Welcome! Your account has been created.';
} else {
    $step = !empty($_SESSION['post_join_ready_for_upgrade']) ? 2 : 1;
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
                            $errors[] = 'An account with this email already exists. Please log in instead.';
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
                    
                    if (REQUIRE_OTP_AFTER_PAYMENT) {
                        // Send OTP for email verification even if they skip
                        ion_send_otp_redirect($_SESSION['email']);
                    } else {
                        // Direct login to app as Guest
                        ion_redirect(APP_DASHBOARD_PATH);
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
                        
                        if ($approved) {
                            // Upgrade to Member
                            $db->update('IONEERS', ['user_role' => 'Member'], ['user_id' => $userId]);
                            $_SESSION['user_role'] = 'Member';
                            unset($_SESSION['post_join_ready_for_upgrade']);
                            unset($_SESSION['oauth_user_data']);
                            
                            if (REQUIRE_OTP_AFTER_PAYMENT) {
                                // Option 1: Send OTP for email verification
                                ion_send_otp_redirect($_SESSION['email']);
                            } else {
                                // Option 2: Direct login (they just created account and paid)
                                ion_redirect(APP_DASHBOARD_PATH);
                            }
                        } else {
                            $errors[] = 'Payment declined. Try card 4242 4242 4242 4242 for testing.';
                        }
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
    <title>Join ION Network • Start Your Free Trial</title>
    
    <!-- Try to load external stylesheet -->
    <link rel="stylesheet" href="/login/login.css">
    
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
        
        .wrapper {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .card {
            background: var(--ion-surface);
            backdrop-filter: blur(16px);
            border: 1px solid var(--ion-border);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }
        
        .content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 600px;
        }
        
        @media (max-width: 768px) {
            .content {
                grid-template-columns: 1fr;
            }
            .right {
                display: none;
            }
        }
        
        .left, .right {
            padding: 60px 50px;
        }
        
        .left {
            border-right: 1px solid rgba(137, 105, 72, 0.1);
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo img {
            height: 100px;
            width: auto;
        }
        
        .h1 {
            font-size: 32px;
            font-weight: 700;
            margin: 0 0 10px;
            color: white;
            text-align: center;
        }
        
        .subtitle {
            color: var(--ion-text-muted);
            margin-bottom: 30px;
            text-align: center;
            font-size: 16px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            color: var(--ion-text-muted);
            font-size: 14px;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.05);
            color: white;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .input:focus {
            outline: none;
            border-color: var(--ion-accent);
            background: rgba(255, 255, 255, 0.1);
            box-shadow: 0 0 0 3px var(--ion-accent-shadow);
        }
        
        .btn {
            width: 100%;
            padding: 14px 20px;
            background: linear-gradient(135deg, var(--ion-accent) 0%, var(--ion-accent-hover) 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            box-shadow: 0 4px 12px var(--ion-accent-shadow);
            text-align: center;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px var(--ion-accent-shadow);
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid var(--ion-border);
            color: var(--ion-text);
        }
        
        .btn-outline:hover {
            background: rgba(137, 105, 72, 0.1);
            border-color: var(--ion-accent);
        }
        
        .btn-ghost {
            background: transparent;
            border: 1px solid var(--ion-border);
            color: var(--ion-accent);
        }
        
        .btn-ghost:hover {
            background: rgba(137, 105, 72, 0.1);
            border-color: var(--ion-accent);
        }
        
        .btn-sm {
            padding: 10px 16px;
            font-size: 14px;
            width: auto;
        }
        
        .notice {
            padding: 0 60px 20px;
            min-height: 20px;
        }
        
        .notice .error {
            color: #ff4d4d;
            margin: 5px 0;
            font-size: 14px;
        }
        
        .notice .success {
            color: #00cc66;
            margin: 5px 0;
            font-size: 14px;
        }
        
        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--ion-border), transparent);
            margin: 30px 0;
            position: relative;
        }
        
        .divider small {
            position: absolute;
            left: 50%;
            top: -10px;
            transform: translateX(-50%);
            padding: 0 15px;
            background: var(--ion-bg);
            color: var(--ion-text-muted);
        }
        
        .google-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 14px 20px;
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            color: white;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        
        .google-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.3);
        }
        
        .link {
            color: var(--ion-accent);
            text-decoration: none;
        }
        
        .link:hover {
            text-decoration: underline;
        }
        
        .features-list {
            list-style: none;
            padding: 0;
            margin: 30px 0;
        }
        
        .features-list li {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 20px;
            color: var(--ion-text-muted);
        }
        
        .check-icon {
            color: var(--ion-accent);
            font-size: 20px;
            flex-shrink: 0;
            margin-top: 2px;
        }
        
        .testimonial {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--ion-border);
            border-radius: 12px;
            padding: 24px;
            margin: 30px 0;
            text-align: center;
        }
        
        .testimonial-text {
            font-style: italic;
            color: var(--ion-text-muted);
            margin-bottom: 10px;
        }
        
        .testimonial-author {
            color: var(--ion-accent);
            font-weight: 600;
        }
        
        .pricing {
            display: grid;
            gap: 15px;
            grid-template-columns: 1fr 1fr;
            margin: 20px 0;
        }
        
        .plan {
            border: 2px solid var(--ion-border);
            border-radius: 8px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.03);
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .plan:hover {
            border-color: var(--ion-accent);
            background: rgba(137, 105, 72, 0.1);
        }
        
        .plan strong {
            color: var(--ion-accent);
        }
        
        .footer-actions {
            display: flex;
            gap: 10px;
            justify-content: space-between;
            margin-top: 20px;
        }
        
        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .guarantee-box {
            margin-top: 30px;
            padding: 20px;
            background: rgba(16, 185, 129, 0.1);
            border-radius: 8px;
            border: 1px solid rgba(16, 185, 129, 0.2);
            text-align: center;
        }
        
        .guarantee-title {
            color: #10b981;
            font-weight: 600;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    
    <div class="wrapper">
        <div class="card">
            
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
            <?php if ($step === 1): ?>
                
                <!-- JOIN STEP 1 -->
                <div class="content">
                    <div class="left">
                        <div class="logo">
                            <img src="https://ions.com/menu/ion-logo-gold.png" alt="ION Network">
                        </div>
                        
                        <div class="h1">Get started today!</div>
                        <div class="subtitle">7 day free trial. No card required.</div>
                        
                        <a href="<?= htmlspecialchars($google_oauth_url) ?>" class="google-btn">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 48 48">
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
                        
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ion_csrf_token()) ?>">
                            <input type="hidden" name="action" value="join_email">
                            
                            <div class="form-group">
                                <label for="fullname">Full Name</label>
                                <input
                                    class="input"
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
                                    class="input"
                                    type="email"
                                    id="email"
                                    name="email"
                                    placeholder="you@example.com"
                                    required
                                >
                            </div>
                            
                            <button class="btn" type="submit">
                                🚀 Start Free Trial
                            </button>
                        </form>
                        
                        <p style="margin-top: 20px; font-size: 14px; color: var(--ion-text-muted); text-align: center;">
                            Already have an account? 
                            <a href="/login/" class="link">Log in here</a>
                        </p>
                    </div>
                    
                    <div class="right">
                        <div class="h1">Grow your sales with ION</div>
                        
                        <div class="testimonial">
                            <p class="testimonial-text">
                                "ION has been a transformative platform for my business, allowing me to 10x my sales since I started using it."
                            </p>
                            <p class="testimonial-author">Jane Doe, CEO of TechCorp</p>
                        </div>
                        
                        <ul class="features-list">
                            <li>
                                <span class="check-icon">✓</span>
                                <div>
                                    <strong>Unlimited Content Creation</strong><br>
                                    Create and manage unlimited posts, videos, and campaigns
                                </div>
                            </li>
                            <li>
                                <span class="check-icon">✓</span>
                                <div>
                                    <strong>Advanced Analytics</strong><br>
                                    Track your performance with detailed insights
                                </div>
                            </li>
                            <li>
                                <span class="check-icon">✓</span>
                                <div>
                                    <strong>24/7 Support</strong><br>
                                    Get help whenever you need it from our expert team
                                </div>
                            </li>
                            <li>
                                <span class="check-icon">✓</span>
                                <div>
                                    <strong>No Setup Fees</strong><br>
                                    Start immediately with zero upfront costs
                                </div>
                            </li>
                        </ul>
                        
                        <div style="text-align: center; margin-top: 40px;">
                            <p style="font-size: 12px; color: var(--ion-text-muted);">
                                🔒 SSL Secured • ✓ GDPR Compliant • ⭐ 5-Star Rated
                            </p>
                        </div>
                    </div>
                </div>
                
            <?php else: ?>
                
                <!-- JOIN STEP 2 (UPGRADE) -->
                <div class="content">
                    <div class="left">
                        <div class="logo">
                            <img src="https://ions.com/menu/ion-logo-gold.png" alt="ION Network">
                        </div>
                        
                        <div class="h1">Unlock Pro Features</div>
                        <p class="subtitle">
                            Get powerful tools to maximize your success, or skip and upgrade later.
                        </p>
                        
                        <form method="POST" id="plan-form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ion_csrf_token()) ?>">
                            
                            <div class="pricing">
                                <label class="plan">
                                    <input type="radio" name="plan" value="monthly" checked>
                                    <div>
                                        <strong>$7.99</strong>/mo<br>
                                        <small>Cancel anytime</small>
                                    </div>
                                </label>
                                
                                <label class="plan">
                                    <input type="radio" name="plan" value="annual">
                                    <div>
                                        <strong>$5.00</strong>/mo<br>
                                        <small>Billed $60 annually</small>
                                    </div>
                                </label>
                            </div>
                        </form>
                        
                        <div class="divider"></div>
                        
                        <form method="POST" id="checkout-form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ion_csrf_token()) ?>">
                            <input type="hidden" name="action" value="checkout">
                            <input type="hidden" name="plan" id="selected-plan" value="monthly">
                            
                            <div class="form-group">
                                <label for="card_number">Card Number</label>
                                <input
                                    class="input"
                                    name="card_number"
                                    id="card_number"
                                    placeholder="4242 4242 4242 4242 (test card)"
                                    required
                                >
                            </div>
                            
                            <div class="row">
                                <div class="form-group">
                                    <label for="card_exp">Expiry</label>
                                    <input
                                        class="input"
                                        name="card_exp"
                                        id="card_exp"
                                        placeholder="MM/YY"
                                        required
                                    >
                                </div>
                                
                                <div class="form-group">
                                    <label for="card_cvc">CVC</label>
                                    <input
                                        class="input"
                                        name="card_cvc"
                                        id="card_cvc"
                                        placeholder="123"
                                        required
                                    >
                                </div>
                            </div>
                            
                            <div class="footer-actions">
                                <button class="btn btn-ghost btn-sm" type="button" id="skip-btn">
                                    Skip for now →
                                </button>
                                
                                <button class="btn btn-sm" type="submit" style="width: auto; padding: 0 30px;">
                                    🔒 Complete Payment
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <div class="right">
                        <div class="h1">Pro Benefits</div>
                        
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
                            <p style="color: var(--ion-text-muted); font-size: 14px;">
                                Not satisfied? Get a full refund, no questions asked.
                            </p>
                        </div>
                    </div>
                </div>
                
            <?php endif; ?>
            
        </div>
    </div>
    
    <script>
        // Sync plan selection
        const planForm = document.getElementById('plan-form');
        const hiddenPlan = document.getElementById('selected-plan');
        
        if (planForm && hiddenPlan) {
            planForm.addEventListener('change', () => {
                const selected = planForm.querySelector('input[name="plan"]:checked');
                if (selected) {
                    hiddenPlan.value = selected.value;
                }
            });
        }
        
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
        
        // Format card number
        document.getElementById('card_number')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s/g, '');
            let formatted = value.match(/.{1,4}/g)?.join(' ') || value;
            e.target.value = formatted;
        });
        
        // Format expiry
        document.getElementById('card_exp')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.slice(0, 2) + '/' + value.slice(2, 4);
            }
            e.target.value = value;
        });
        
        // Only numbers in CVC
        document.getElementById('card_cvc')?.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '');
        });
    </script>
    
</body>
</html>