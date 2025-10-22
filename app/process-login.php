<?php

/**
 * AJAX Login Processor
 * Handles modal login requests without page redirect
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON header
header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Check if this is an AJAX login request
$is_ajax = isset($_POST['ajax_login']) && $_POST['ajax_login'] === '1';

try {
    // Load database configuration
    require_once __DIR__ . '/../config/database.php';

    // Get login credentials
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']) && $_POST['remember'] === '1';

    // Validate input
    if (empty($email) || empty($password)) {
        throw new Exception('Email and password are required');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    // Get PDO instance
    $pdo = $db->getPDO();

    // Query user from IONEERS table
    $stmt = $pdo->prepare("
        SELECT user_id, first_name, last_name, email, password, 
               user_role, account_status, phone, email_verified, photo_url
        FROM IONEERS 
        WHERE email = :email
        LIMIT 1
    ");

    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if user exists
    if (!$user) {
        // Log failed attempt
        error_log("LOGIN ATTEMPT: User not found - {$email}");
        throw new Exception('Invalid email or password');
    }

    // Verify password
    // Check both hashed password and plain text for backward compatibility
    $password_valid = false;

    if (password_verify($password, $user['password'])) {
        // Modern hashed password
        $password_valid = true;
    } elseif ($password === $user['password']) {
        // Legacy plain text password (update to hash)
        $password_valid = true;

        // Update to hashed password
        $new_hash = password_hash($password, PASSWORD_DEFAULT);
        $update_stmt = $pdo->prepare("UPDATE IONEERS SET password = :password WHERE user_id = :user_id");
        $update_stmt->execute([
            ':password' => $new_hash,
            ':user_id' => $user['user_id']
        ]);

        error_log("PASSWORD UPGRADED: User {$user['user_id']} password updated to bcrypt hash");
    }

    if (!$password_valid) {
        error_log("LOGIN ATTEMPT: Invalid password for {$email}");
        throw new Exception('Invalid email or password');
    }

    // Check account status
    if ($user['account_status'] === 'Suspended') {
        throw new Exception('Your account has been suspended. Please contact support.');
    }

    if ($user['account_status'] === 'Inactive') {
        throw new Exception('Your account is inactive. Please contact support.');
    }

    // Check email verification (optional - can be disabled)
    // if ($user['email_verified'] != 1) {
    //     throw new Exception('Please verify your email address before logging in.');
    // }

    // Login successful - set session variables
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
    $_SESSION['first_name'] = $user['first_name'];
    $_SESSION['last_name'] = $user['last_name'];
    $_SESSION['user_role'] = $user['user_role'];
    $_SESSION['phone'] = $user['phone'];
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();
    $full_name_for_avatar = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    $avatar_fallback = 'https://i0.wp.com/ui-avatars.com/api/?name=' . urlencode($full_name_for_avatar ?: $user['email']) . '&size=256';
    $_SESSION['photo_url'] = !empty($user['photo_url']) ? $user['photo_url'] : $avatar_fallback;

    // Set remember me cookie if requested
    if ($remember) {
        $cookie_name = 'ion_remember_token';
        $token = bin2hex(random_bytes(32));
        $expires = time() + (30 * 24 * 60 * 60); // 30 days

        // Store token in database (you may need to create this table)
        try {
            $token_stmt = $pdo->prepare("
                INSERT INTO user_remember_tokens (user_id, token, expires_at)
                VALUES (:user_id, :token, FROM_UNIXTIME(:expires))
                ON DUPLICATE KEY UPDATE token = :token, expires_at = FROM_UNIXTIME(:expires)
            ");
            $token_stmt->execute([
                ':user_id' => $user['user_id'],
                ':token' => password_hash($token, PASSWORD_DEFAULT),
                ':expires' => $expires
            ]);

            // Set cookie
            setcookie($cookie_name, $token, $expires, '/', '', true, true);
        } catch (Exception $e) {
            // Table might not exist - that's okay, continue without remember me
            error_log("Remember me token storage failed: " . $e->getMessage());
        }
    }

    // Log successful login
    error_log("LOGIN SUCCESS: User {$user['user_id']} ({$email}) logged in successfully");

    // Update last login time
    try {
        $login_update = $pdo->prepare("UPDATE IONEERS SET last_login = NOW() WHERE user_id = :user_id");
        $login_update->execute([':user_id' => $user['user_id']]);
    } catch (Exception $e) {
        // Column might not exist - that's okay
        error_log("Last login update failed: " . $e->getMessage());
    }

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'user' => [
            'user_id' => $user['user_id'],
            'email' => $user['email'],
            'name' => trim($user['first_name'] . ' ' . $user['last_name']),
            'role' => $user['user_role']
        ]
    ]);
} catch (Exception $e) {
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);

    // Log error
    error_log("LOGIN ERROR: " . $e->getMessage());
}
