<?php
/**
 * ION Join & Login (Tabbed) + Optional Upgrade Checkout
 * Based on your provided sample with improvements
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

// Get database connection
global $db;
if (!$db || !$db->isConnected()) {
    http_response_code(500);
    exit('Database connection error');
}

// Controller variables
$errors    = [];
$messages  = [];
$activeTab = $_GET['tab'] ?? 'join';
$step = !empty($_SESSION['post_join_ready_for_upgrade']) ? 2 : 1;

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
                    
                    $activeTab = 'join';
                } break;
                
                case 'skip_upgrade': {
                    // User chooses not to upgrade
                    unset($_SESSION['post_join_ready_for_upgrade']);
                    ion_redirect(APP_DASHBOARD_PATH);
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
                            ion_redirect(APP_DASHBOARD_PATH);
                        } else {
                            $errors[] = 'Payment declined. Try card 4242 4242 4242 4242 for testing.';
                        }
                    }
                    
                    $step = 2;
                    $activeTab = 'join';
                } break;
                
            }
            
        } catch (Throwable $e) {
            $errors[] = 'Unexpected error: ' . htmlspecialchars($e->getMessage());
        }
    }
}

/**
 * Helper for tab class
 */
function is_active_tab(string $tab, string $active): string
{
    return $tab === $active ? 'tab-active' : '';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Join ION • Create Account &amp; Upgrade</title>
    
    <!-- Try to load external stylesheet -->
    <link rel="stylesheet" href="./login.css">
    <link rel="stylesheet" href="./join.css">
    
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
        }
        
        body {
            margin: 0;
            padding: 0;
            background: var(--ion-bg);
            color: var(--ion-text);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .wrapper {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .card {
            background: var(--ion-surface);
            backdrop-filter: blur(16px);
            border: 1px solid var(--ion-border);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }
        
        .headerbar {
            background: rgba(255, 255, 255, 0.03);
            padding: 20px 30px;
            border-bottom: 1px solid var(--ion-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .header-logo img {
            height: 50px;
            width: auto;
        }
        
        .header-tabs {
            display: flex;
            gap: 10px;
        }
        
        .tab {
            padding: 10px 20px;
            border-radius: 8px;
            color: var(--ion-text-muted);
            text-decoration: none;
            font-weight: 600;
            transition: 0.2s;
            border: 1px solid transparent;
        }
        
        .tab:hover {
            border-color: var(--ion-border);
            background: rgba(255, 255, 255, 0.05);
        }
        
        .tab-active {
            background: var(--ion-accent);
            color: white;
            border-color: var(--ion-accent);
        }
        
        .content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 500px;
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
            padding: 40px;
        }
        
        .left {
            border-right: 1px solid rgba(137, 105, 72, 0.1);
        }
        
        .h1 {
            font-size: 28px;
            font-weight: 700;
            margin: 0 0 10px;
            color: white;
        }
        
        .muted {
            color: var(--ion-text-muted);
            margin-bottom: 30px;
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
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px var(--ion-accent-shadow);
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
            padding: 20px 40px;
            background: rgba(255, 255, 255, 0.02);
            min-height: 20px;
        }
        
        .notice .error {
            color: #ff4d4d;
            margin: 5px 0;
        }
        
        .notice .success {
            color: #00cc66;
            margin: 5px 0;
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
        
        .features-list {
            list-style: none;
            padding: 0;
            margin: 20px 0;
        }
        
        .features-list li {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 16px;
            color: var(--ion-text-muted);
        }
        
        .check-icon {
            color: var(--ion-accent);
            font-size: 20px;
            flex-shrink: 0;
            margin-top: 2px;
        }
        
        .link {
            color: var(--ion-accent);
            text-decoration: none;
        }
        
        .link:hover {
            text-decoration: underline;
        }
        
        .testimonial {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--ion-border);
            border-radius: 12px;
            padding: 24px;
            margin: 20px 0;
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
    </style>
</head>
<body>
    
    <div class="wrapper">
        <div class="card">
            
            <!-- Header with logo and tabs -->
            <div class="headerbar">
                <div class="header-logo">
                    <img src="https://ions.com/menu/ion-logo-gold.png" alt="ION Network">
                </div>
                
                <div class="header-tabs">
                    <a class="tab <?= is_active_tab('join', $activeTab) ?>" href="?tab=join">Join</a>
                    <a class="tab <?= is_active_tab('login', $activeTab) ?>" href="?tab=login">Login</a>
                </div>
            </div>
            
            <!-- Notices -->
            <div class="notice">
                <?php foreach ($messages as $m): ?>
                    <div class="success"><?= htmlspecialchars($m) ?></div>
                <?php endforeach; ?>
                
                <?php foreach ($errors as $e): ?>
                    <div class="error"><?= htmlspecialchars($e) ?></div>
                <?php endforeach; ?>
            </div>
            
            <!-- Content -->
            <?php if ($activeTab === 'login'): ?>
                
                <!-- LOGIN TAB -->
                <div class="content">
                    <div class="left">
                        <div class="h1">Welcome back</div>
                        <div class="muted">Sign in with your email to continue.</div>
                        
                        <form method="POST" action="sendotp.php">
                            <div class="form-group">
                                <label for="login-email">Email Address</label>
                                <input
                                    class="input"
                                    type="email"
                                    id="login-email"
                                    name="email"
                                    placeholder="you@example.com"
                                    required
                                >
                            </div>
                            
                            <button class="btn" type="submit">
                                🔐 Send Login Code
                            </button>
                        </form>
                        
                        <p style="margin-top: 20px; font-size: 14px; color: var(--ion-text-muted);">
                            Don't have an account? 
                            <a href="?tab=join" class="link">Create one</a>
                        </p>
                    </div>
                    
                    <div class="right">
                        <div class="h1">Secure Access</div>
                        <p class="muted">
                            We'll send you a one-time code to verify your identity. Check your email after clicking send.
                        </p>
                        
                        <div class="testimonial">
                            <p class="testimonial-text">
                                "ION's secure login gives me peace of mind knowing my content is protected."
                            </p>
                            <p class="testimonial-author">- Sarah Chen, Content Creator</p>
                        </div>
                    </div>
                </div>
                
            <?php else: ?>
                
                <?php if ($step === 1): ?>
                    
                    <!-- JOIN STEP 1 -->
                    <div class="content">
                        <div class="left">
                            <div class="h1">Get started today!</div>
                            <div class="muted">7 day free trial. No card required.</div>
                            
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
                            
                            <p style="margin-top: 20px; font-size: 14px; color: var(--ion-text-muted);">
                                Already have an account? 
                                <a href="?tab=login" class="link">Log in here</a>
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
                        </div>
                    </div>
                    
                <?php else: ?>
                    
                    <!-- JOIN STEP 2 (UPGRADE) -->
                    <div class="content">
                        <div class="left">
                            <div class="h1">Unlock Pro Features</div>
                            <p class="muted">
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
                                    
                                    <button class="btn btn-sm" type="submit">
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
                            
                            <div style="margin-top: 30px; padding: 20px; background: rgba(16, 185, 129, 0.1); border-radius: 8px; border: 1px solid rgba(16, 185, 129, 0.2);">
                                <p style="color: #10b981; font-weight: 600; margin: 0;">
                                    30-Day Money Back Guarantee
                                </p>
                                <p style="color: var(--ion-text-muted); font-size: 14px; margin: 5px 0 0;">
                                    Not satisfied? Get a full refund, no questions asked.
                                </p>
                            </div>
                        </div>
                    </div>
                    
                <?php endif; ?>
                
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