<?php
/**
 * ============================================================
 * ION Join & Login (Tabbed) + Optional Upgrade Checkout
 * ------------------------------------------------------------
 * - Schema-aligned to IONEERS (no updated_at / upgraded_at / subscription_plan)
 * - Inline base styles so it looks good even if login.css fails to load
 * - Still attempts to load login.css (relative and absolute paths)
 * - Accent color: #896948
 * - Header logo: https://ions.com/menu/ion-logo-gold.png
 * - Email Join (Full name + Email) and Login (OTP via sendotp.php)
 * - Step 2: Optional upgrade UI; payment is stubbed for now
 * - On payment "success": user_role -> Member; redirect to /app
 * ============================================================
 */

declare(strict_types=1);
session_start();

/* ----------------------------------------------------------------
 * Config + DB bootstrap
 * ---------------------------------------------------------------- */

$CONFIG_FILE = __DIR__ . '/../config/config.php';
$DB_FILE     = __DIR__ . '/../config/database.php';

if (!file_exists($CONFIG_FILE)) {
    http_response_code(500);
    exit('Missing /config/config.php');
}

require_once $CONFIG_FILE;

if (!file_exists($DB_FILE)) {
    http_response_code(500);
    exit('Missing /config/database.php');
}

require_once $DB_FILE;


/**
 * Acquire PDO from either:
 *  - global $pdo
 *  - IONDatabase class exposing ->pdo or ->getPdo()
 *  - any PDO property in IONDatabase (as a last resort)
 */
function ion_get_pdo() : PDO {
    // 1) Global $pdo style
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        return $GLOBALS['pdo'];
    }

    // 2) Class style: IONDatabase
    if (class_exists('IONDatabase')) {
        $db = new IONDatabase();

        // Prefer an explicit getter if available
        if (method_exists($db, 'getPdo')) {
            $pdo = $db->getPdo();
            if ($pdo instanceof PDO) return $pdo;
        }

        // Fallback: scan only PUBLIC props (safe; no private access)
        foreach (get_object_vars($db) as $v) {
            if ($v instanceof PDO) return $v;
        }
    }

    throw new RuntimeException('Unable to acquire PDO from database.php');
}


try {
    $pdo = ion_get_pdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {
    http_response_code(500);
    exit('DB connection error: ' . htmlspecialchars($e->getMessage()));
}


/* ----------------------------------------------------------------
 * Constants / small helpers
 * ---------------------------------------------------------------- */

const APP_DASHBOARD_PATH = '/app';
const GOOGLE_OAUTH_START = './google-oauth.php?redirect=/join.php';


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
 * Normalize email (trim + lowercase + validate)
 */
function ion_normalize_email(string $e): string
{
    $e = trim(mb_strtolower($e));
    return filter_var($e, FILTER_VALIDATE_EMAIL) ? $e : '';
}


/**
 * Clean name (collapse whitespace and limit length)
 */
function ion_clean_name(string $n): string
{
    $n = trim(preg_replace('/\s+/', ' ', $n));
    return mb_substr($n, 0, 120);
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
 * Mark user as logged in (use your schema shape)
 */
function ion_login_user(array $u): void
{
    $_SESSION['ioneers_user_id'] = isset($u['user_id']) ? (int)$u['user_id'] : null;
    $_SESSION['ioneers_email']   = $u['email'] ?? null;
    $_SESSION['ioneers_role']    = $u['user_role'] ?? 'User';
    $_SESSION['is_logged_in']    = true;
}


/**
 * Check if a column exists on a table in the current database
 */
function ion_table_has_col(PDO $pdo, string $table, string $col): bool
{
    $q = $pdo->prepare("
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = :t
          AND COLUMN_NAME  = :c
        LIMIT 1
    ");

    $q->execute([
        ':t' => $table,
        ':c' => $col
    ]);

    return (bool)$q->fetchColumn();
}


/**
 * Upsert user using only columns your IONEERS table actually has
 * - Required: email (PK/unique), fullname (optional but provided)
 * - Optional columns considered: user_role, status, created_at
 */
function ion_upsert_user(PDO $pdo, string $email, string $fullname): array
{
    $now = date('Y-m-d H:i:s');

    // 1) Try to find existing user by email
    $sel = $pdo->prepare("SELECT * FROM `IONEERS` WHERE `email` = :email LIMIT 1");
    $sel->execute([':email' => $email]);
    $row = $sel->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        // If fullname is empty, update it
        if ((isset($row['fullname']) && $row['fullname'] === '') && $fullname !== '') {
            $upd = $pdo->prepare("
                UPDATE `IONEERS`
                   SET `fullname` = :fullname
                 WHERE `email`    = :email
                 LIMIT 1
            ");
            $upd->execute([
                ':fullname' => $fullname,
                ':email'    => $email
            ]);
            $row['fullname'] = $fullname;
        }
        return $row;
    }

    // 2) Insert with only available columns
    $fields = [
        'email'    => $email,
        'fullname' => $fullname
    ];

    if (ion_table_has_col($pdo, 'IONEERS', 'user_role')) {
        $fields['user_role'] = 'User';
    }

    if (ion_table_has_col($pdo, 'IONEERS', 'status')) {
        // Your enum is ('active', 'inactive', 'blocked'), default to 'active'
        $fields['status'] = 'active';
    }

    if (ion_table_has_col($pdo, 'IONEERS', 'created_at')) {
        $fields['created_at'] = $now;
    }

    $cols = array_map(fn($c) => "`$c`", array_keys($fields));
    $ph   = array_map(fn($c) => ":$c", array_keys($fields));

    $sql = "INSERT INTO `IONEERS` (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $ph) . ")";
    $ins = $pdo->prepare($sql);
    $ins->execute($fields);

    // 3) Re-fetch the row by email
    $sel = $pdo->prepare("SELECT * FROM `IONEERS` WHERE `email` = :email LIMIT 1");
    $sel->execute([':email' => $email]);
    return $sel->fetch(PDO::FETCH_ASSOC) ?: [];
}


/**
 * Promote user to Member (schema doesn’t track plan or upgraded_at)
 */
function ion_upgrade_to_member(PDO $pdo, int $userId, string $plan): void
{
    $upd = $pdo->prepare("
        UPDATE `IONEERS`
           SET `user_role` = 'Member'
         WHERE `user_id`   = :uid
         LIMIT 1
    ");

    $upd->execute([':uid' => $userId]);

    // Reflect in session
    $_SESSION['ioneers_role'] = 'Member';
}


/* ----------------------------------------------------------------
 * Controller: tab state, steps, and form handling
 * ---------------------------------------------------------------- */

$errors    = [];
$messages  = [];
$activeTab = $_GET['tab'] ?? 'join';   // 'join' or 'login'

// Step control: after successful Join, we show Step 2 (upgrade)
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
                        $user = ion_upsert_user($pdo, $email, $fullname);

                        if (!$user) {
                            $errors[] = 'Unable to create your account. Please try again.';
                        } else {
                            ion_login_user($user);
                            $_SESSION['post_join_ready_for_upgrade'] = true;
                            $step = 2;
                            $messages[] = 'Welcome! Your account has been created.';
                        }
                    }

                    $activeTab = 'join';
                } break;


                case 'login_email': {
                    // Redirect to your existing OTP flow, preserving the email
                    $email = isset($_POST['email']) ? ion_normalize_email((string)$_POST['email']) : '';

                    if ($email === '') {
                        $errors[] = 'Please enter a valid email address.';
                    }

                    if (!$errors) {
                        ion_redirect('/sendotp.php?email=' . urlencode($email));
                    }

                    $activeTab = 'login';
                } break;


                case 'skip_upgrade': {
                    // User chooses not to upgrade now; go to app
                    unset($_SESSION['post_join_ready_for_upgrade']);
                    ion_redirect(APP_DASHBOARD_PATH);
                } break;


                case 'checkout': {
                    // Payment is stubbed in this version
                    if (empty($_SESSION['ioneers_user_id'])) {
                        $errors[] = 'Your session expired. Please sign in again.';
                        break;
                    }

                    $userId = (int) $_SESSION['ioneers_user_id'];
                    $plan   = $_POST['plan'] ?? 'monthly'; // kept for UI; schema doesn’t save plan

                    // Card fields (in real use, tokenize with Stripe/Braintree/Spreedly)
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
                        // ---- PAYMENT STUB: approve Stripe test card 4242... ----
                        $approved = ($card === '4242424242424242');

                        if ($approved) {
                            ion_upgrade_to_member($pdo, $userId, ($plan === 'annual' ? 'annual' : 'monthly'));
                            unset($_SESSION['post_join_ready_for_upgrade']);
                            ion_redirect(APP_DASHBOARD_PATH);
                        } else {
                            $errors[] = 'Payment declined. Please try another card.';
                        }
                    }

                    $step      = 2;
                    $activeTab = 'join';
                } break;

            } // end switch

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
    <meta
        name="viewport"
        content="width=device-width,initial-scale=1"
    >

    <title>Join ION • Create Account &amp; Upgrade</title>

    <!-- Attempt to load your external stylesheet from both relative and absolute paths -->
    <link rel="stylesheet" href="./login.css">

    <style>
        /* ============================================================
         * Inline Base Styles (kept verbose and unminified intentionally)
         * ============================================================ */

        :root {
            --ion-fg:               #111827;
            --ion-fg-weak:          #6b7280;
            --ion-border:           rgba(0, 0, 0, 0.08);
            --ion-surface:          #ffffff;
            --ion-accent:           #896948;  /* requested accent */
            --ion-accent-weak:      #89694822;
            --ion-tab-shadow:       rgba(137, 105, 72, 0.25);
            --ion-page-bg:          #eef2f7;
            --ion-input-border:     #e5e7eb;
            --ion-input-shadow:     #89694822;
            --ion-link-blue:        #1d4ed8;
            --ion-divider-fade:     #89694855;
            --ion-muted:            #6b7280;
            --ion-ghost-border:     #e5e7eb;
            --ion-ghost-bg-hover:   #f9fafb;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            background: var(--ion-page-bg);
            color: var(--ion-fg);
            font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        a {
            color: var(--ion-link-blue);
            text-decoration: underline;
        }

        a.inline-flex {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.7rem 1rem;
            border-radius: 10px;
            border: 1px solid var(--ion-input-border);
            background: #fff;
            text-decoration: none;
            font-weight: 600;
        }

        a.inline-flex:hover {
            background: var(--ion-ghost-bg-hover);
        }

        .link {
            color: var(--ion-link-blue);
            text-decoration: underline;
        }

        .wrapper {
            width: 100%;
            max-width: 1080px;
            margin: 40px auto;
            padding: 0 16px;
        }

        .card {
            background: var(--ion-surface);
            border: 1px solid var(--ion-border);
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 40px 80px rgba(0, 0, 0, 0.12);
        }

        .headerbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 18px;
            background: linear-gradient(135deg, #64748b22, #94a3b833);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .header-tabs {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .tab {
            padding: 10px 14px;
            border-radius: 12px;
            color: #374151;
            font-weight: 700;
            text-decoration: none;
            border: 1px solid transparent;
            transition: 0.2s;
        }

        .tab:hover {
            border-color: var(--ion-ghost-border);
            background: var(--ion-ghost-bg-hover);
        }

        .tab-active {
            color: #fff;
            background: var(--ion-accent);
            border-color: var(--ion-accent);
            box-shadow: 0 6px 14px var(--ion-tab-shadow);
        }

        .content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 520px;
        }

        @media (max-width: 900px) {
            .content {
                grid-template-columns: 1fr;
            }
        }

        .left,
        .right {
            padding: 32px;
        }

        .left {
            border-right: 1px solid #f0f2f5;
        }

        .h1 {
            font-size: 32px;
            font-weight: 800;
            margin: 4px 0 8px;
        }

        .muted {
            color: var(--ion-muted);
        }

        .small {
            font-size: 12px;
            color: var(--ion-muted);
        }

        .input {
            width: 100%;
            height: 48px;
            padding: 0 14px;
            border-radius: 10px;
            border: 2px solid var(--ion-input-border);
            background: #fff;
            color: #111;
            transition: 0.2s;
        }

        .input:focus {
            outline: none;
            border-color: var(--ion-accent);
            box-shadow: 0 0 0 3px var(--ion-input-shadow);
        }

        .row {
            display: flex;
            gap: 12px;
        }

        .col {
            flex: 1;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            width: 100%;
            height: 46px;
            border-radius: 10px;
            border: 1px solid transparent;
            background: var(--ion-accent);
            color: #fff;
            font-weight: 800;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn:hover {
            filter: brightness(0.95);
        }

        .btn-ghost {
            background: transparent;
            border: 1px solid var(--ion-ghost-border);
            color: #374151;
        }

        .btn-ghost:hover {
            background: var(--ion-ghost-bg-hover);
        }

        .btn-sm {
            height: 40px;
            padding: 0 12px;
            border-radius: 8px;
        }

        .notice {
            padding: 8px 16px;
            min-height: 8px;
        }

        .notice .error {
            color: #b91c1c;
            margin: 4px 0;
        }

        .notice .success {
            color: #059669;
            margin: 4px 0;
        }

        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--ion-divider-fade), transparent);
            margin: 18px 0;
            position: relative;
        }

        .divider small {
            position: absolute;
            left: 50%;
            top: -8px;
            transform: translateX(-50%);
            padding: 0 8px;
            background: #fff;
            color: var(--ion-muted);
        }

        .pricing {
            display: grid;
            gap: 14px;
            grid-template-columns: 1fr 1fr;
            margin-top: 8px;
        }

        .plan {
            border: 1px solid var(--ion-ghost-border);
            border-radius: 12px;
            padding: 14px;
            background: #fff;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .plan strong {
            color: var(--ion-accent);
        }

        .footer-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 10px;
        }

        .kbd {
            font-family: ui-monospace, Menlo, Consolas, monospace;
            font-size: 12px;
            padding: 2px 6px;
            border-radius: 6px;
            background: #f3f4f6;
            border: 1px solid var(--ion-ghost-border);
            color: #374151;
        }
    </style>
</head>
<body>

    <div class="wrapper">
        <div class="card">

            <!-- =====================================================
                 Top bar: logo + tabs
                 ===================================================== -->
            <div class="headerbar">

                <div class="header-left">
                    <img
                        src="https://ions.com/menu/ion-logo-gold.png"
                        alt="ION Network"
                        style="height:38px;width:auto;margin-right:8px;display:block"
                    />
                </div>

                <div class="header-tabs">
                    <a
                        class="tab <?= is_active_tab('join', $activeTab) ?>"
                        href="?tab=join"
                    >Join</a>

                    <a
                        class="tab <?= is_active_tab('login', $activeTab) ?>"
                        href="?tab=login"
                    >Login</a>
                </div>

            </div>
            <!-- /headerbar -->


            <!-- =====================================================
                 Notices (errors / messages)
                 ===================================================== -->
            <div class="notice">
                <?php foreach (($messages ?? []) as $m): ?>
                    <div class="success"><?= htmlspecialchars($m) ?></div>
                <?php endforeach; ?>

                <?php foreach (($errors ?? []) as $e): ?>
                    <div class="error"><?= htmlspecialchars($e) ?></div>
                <?php endforeach; ?>
            </div>


            <!-- =====================================================
                 Content panes
                 ===================================================== -->
            <?php if ($activeTab === 'login'): ?>

                <!-- =========================
                     LOGIN PANE
                     ========================= -->
                <div class="content">

                    <div class="left">
                        <div class="h1">Welcome back</div>

                        <div class="muted">
                            Sign in to continue to your ION Console.
                        </div>

                        <form
                            method="post"
                            style="margin-top:18px;max-width:420px"
                        >
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= htmlspecialchars(ion_csrf_token()) ?>"
                            >

                            <input
                                type="hidden"
                                name="action"
                                value="login_email"
                            >

                            <input
                                class="input"
                                type="email"
                                name="email"
                                placeholder="you@company.com"
                                required
                                style="margin-bottom:10px"
                            >

                            <button
                                class="btn"
                                type="submit"
                                aria-label="Send one-time code"
                            >
                                Send OTP
                            </button>
                        </form>

                        <div class="divider">
                            <small>- or -</small>
                        </div>

                        <a
                            class="inline-flex"
                            href="<?= htmlspecialchars(GOOGLE_OAUTH_START) ?>"
                        >
                            <!-- simple circular mark -->
                            <svg
                                width="18"
                                height="18"
                                viewBox="0 0 24 24"
                                fill="#4285F4"
                                aria-hidden="true"
                            >
                                <circle cx="12" cy="12" r="10"></circle>
                            </svg>

                            Continue with Google
                        </a>

                        <p class="small" style="margin-top:10px;">
                            We’ll email you a one-time code to verify it’s you.
                        </p>
                    </div>

                    <div class="right">
                        <div class="h1">Tips</div>

                        <p class="muted">
                            Use the email you signed up with. After OTP, you’ll land in
                            <span class="kbd"><?= APP_DASHBOARD_PATH ?></span>.
                        </p>

                        <div class="divider"></div>

                        <p class="small">
                            Trouble receiving codes? Check spam or allowlist our domain.
                        </p>
                    </div>

                </div>
                <!-- /LOGIN PANE -->

            <?php else: ?>

                <?php if ($step === 1): ?>

                    <!-- =========================
                         JOIN — STEP 1
                         ========================= -->
                    <div class="content">

                        <div class="left">

                            <div class="h1">Get started — free</div>

                            <div class="muted">
                                7-day free trial. No card required.
                            </div>

                            <a
                                class="inline-flex"
                                href="<?= htmlspecialchars(GOOGLE_OAUTH_START) ?>"
                                style="margin-top:16px"
                            >
                                <svg
                                    width="18"
                                    height="18"
                                    viewBox="0 0 24 24"
                                    fill="#4285F4"
                                    aria-hidden="true"
                                >
                                    <circle cx="12" cy="12" r="10"></circle>
                                </svg>

                                Continue with Google
                            </a>

                            <div class="divider">
                                <small>- or -</small>
                            </div>

                            <form
                                method="post"
                                style="max-width:420px"
                            >
                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= htmlspecialchars(ion_csrf_token()) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="join_email"
                                >

                                <input
                                    class="input"
                                    type="text"
                                    name="fullname"
                                    placeholder="Full name"
                                    required
                                    style="margin-bottom:10px"
                                >

                                <input
                                    class="input"
                                    type="email"
                                    name="email"
                                    placeholder="you@company.com"
                                    required
                                    style="margin-bottom:10px"
                                >

                                <button
                                    class="btn"
                                    type="submit"
                                    aria-label="Create my account"
                                >
                                    Create my account
                                </button>
                            </form>

                            <p class="small" style="margin-top:10px;">
                                Already have an account?
                                <a class="link" href="?tab=login">Log in</a>
                            </p>

                        </div>

                        <div class="right">

                            <div class="h1">Why creators pick ION</div>

                            <p class="muted">
                                Faster approvals, premium tools, and higher upload capacity when you upgrade.
                            </p>

                            <div class="divider"></div>

                            <ul style="line-height:1.9;margin:0 0 0 20px">
                                <li>Premium content tools</li>
                                <li>Higher upload capacity</li>
                                <li>Faster moderation</li>
                            </ul>

                        </div>

                    </div>
                    <!-- /JOIN STEP 1 -->

                <?php else: ?>

                    <!-- =========================
                         JOIN — STEP 2 (UPGRADE)
                         ========================= -->
                    <div class="content">

                        <div class="left">

                            <div class="h1">Go Pro — optional</div>

                            <p class="muted">
                                Unlock premium features now or skip and upgrade later.
                            </p>

                            <form
                                method="post"
                                id="plan-form"
                                style="margin-top:12px;max-width:520px"
                            >
                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= htmlspecialchars(ion_csrf_token()) ?>"
                                >

                                <div class="pricing">

                                    <label class="plan">
                                        <input
                                            type="radio"
                                            name="plan"
                                            value="monthly"
                                            checked
                                        >
                                        <strong>$7.99</strong>/mo • Cancel anytime
                                    </label>

                                    <label class="plan">
                                        <input
                                            type="radio"
                                            name="plan"
                                            value="annual"
                                        >
                                        <strong>$5</strong>/mo • Billed annually ($60)
                                    </label>

                                </div>
                            </form>

                            <div class="divider"></div>

                            <form
                                method="post"
                                id="checkout-form"
                                autocomplete="off"
                                novalidate
                                style="max-width:520px"
                            >
                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= htmlspecialchars(ion_csrf_token()) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="checkout"
                                >

                                <input
                                    type="hidden"
                                    name="plan"
                                    id="selected-plan"
                                    value="monthly"
                                >

                                <input
                                    class="input"
                                    name="card_number"
                                    inputmode="numeric"
                                    autocomplete="cc-number"
                                    placeholder="Card number (try 4242 4242 4242 4242)"
                                    required
                                    style="margin-bottom:10px"
                                >

                                <div class="row" style="margin-top:0">

                                    <div class="col">
                                        <input
                                            class="input"
                                            name="card_exp"
                                            inputmode="numeric"
                                            autocomplete="cc-exp"
                                            placeholder="MM/YY"
                                            required
                                        >
                                    </div>

                                    <div class="col">
                                        <input
                                            class="input"
                                            name="card_cvc"
                                            inputmode="numeric"
                                            autocomplete="cc-csc"
                                            placeholder="CVC"
                                            required
                                        >
                                    </div>

                                </div>

                                <div class="footer-actions">

                                    <button
                                        class="btn"
                                        type="submit"
                                        style="width:auto;padding:0 18px"
                                        aria-label="Checkout"
                                    >
                                        Checkout
                                    </button>

                                    <button
                                        class="btn btn-ghost btn-sm"
                                        type="button"
                                        id="skip-btn"
                                        title="Continue without upgrading"
                                    >
                                        Skip →
                                    </button>

                                </div>

                                <p class="small" style="margin-top:8px">
                                    Replace this stub with your real gateway (Stripe, Braintree, Spreedly, etc.).
                                </p>

                            </form>

                        </div>

                        <div class="right">

                            <div class="h1">What you get</div>

                            <ul class="muted" style="line-height:1.9;margin:0 0 0 20px">
                                <li>Premium content management tools</li>
                                <li>Upload up to 100 creations</li>
                                <li>Priority moderation</li>
                            </ul>

                            <div class="divider"></div>

                            <p class="small">
                                You can also upgrade later from your account settings.
                            </p>

                        </div>

                    </div>
                    <!-- /JOIN STEP 2 -->

                <?php endif; ?>

            <?php endif; ?>

        </div> <!-- /card -->
    </div> <!-- /wrapper -->


    <script>
        // ============================================================
        // Keep the selected plan radio synced into the hidden <input>
        // ============================================================
        const planForm   = document.getElementById('plan-form');
        const hiddenPlan = document.getElementById('selected-plan');

        if (planForm && hiddenPlan) {
            planForm.addEventListener('change', () => {
                const sel = planForm.querySelector('input[name="plan"]:checked');
                if (sel) {
                    hiddenPlan.value = sel.value;
                }
            });
        }


        // ============================================================
        // "Skip" button posts a small hidden form with skip_upgrade
        // ============================================================
        const skipBtn = document.getElementById('skip-btn');

        if (skipBtn) {
            skipBtn.addEventListener('click', () => {

                const f = document.createElement('form');
                f.method = 'POST';
                f.style.display = 'none';

                f.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ion_csrf_token()) ?>">
                    <input type="hidden" name="action" value="skip_upgrade">
                `;

                document.body.appendChild(f);
                f.submit();
            });
        }
    </script>

</body>
</html>
