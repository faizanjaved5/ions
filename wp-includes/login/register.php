<?php
session_start();

require_once __DIR__ . '/../config/database.php';
$config = require __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

// Get POST data
$fullname = trim($_POST['fullname'] ?? '');
$email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);

// Validate input
if (empty($fullname)) {
    echo json_encode(['status' => 'error', 'message' => '❌ Please enter your full name.']);
    exit;
}

if (!$email) {
    echo json_encode(['status' => 'error', 'message' => '❌ Invalid email address.']);
    exit;
}

global $db;

// Reinitialize database connection if not connected
if (!$db->isConnected()) {
    $db = new IONDatabase();
    if (!$db->isConnected()) {
        echo json_encode(['status' => 'error', 'message' => '❌ Database connection failed.']);
        exit;
    }
}

try {
    // Check if user already exists
    $existing_user = $db->get_row("SELECT user_id, email FROM IONEERS WHERE email = ?", $email);
    
    if ($existing_user) {
        echo json_encode(['status' => 'error', 'message' => '❌ An account with this email already exists. Please log in instead.']);
        exit;
    }
    
    // Prepare user data - user_id will be auto-generated
    $user_data = [
        'fullname' => $fullname,
        'email' => $email,
        'user_role' => 'Guest', // Start as Guest, upgrade to Member after payment
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s'),
        'login_count' => 0
    ];
    
    // Insert new user
    $insert_result = $db->insert('IONEERS', $user_data);
    
    if (!$insert_result) {
        throw new Exception('Failed to create user account');
    }
    
    // Get the newly created user_id
    $user_id = $db->insert_id();
    
    // Store user ID in session for the upgrade flow
    $_SESSION['pending_user_id'] = $user_id;
    $_SESSION['pending_email'] = $email;
    
    // Log the registration
    error_log("DEBUG register.php - New user registered: $email (ID: $user_id)");
    
    echo json_encode([
        'status' => 'success', 
        'message' => '✅ Account created successfully!',
        'user_id' => $user_id
    ]);
    
} catch (\Throwable $t) {
    error_log("DEBUG register.php - Registration error: " . $t->getMessage());
    echo json_encode(['status' => 'error', 'message' => '❌ Error creating account: ' . $t->getMessage()]);
    exit;
}
?>