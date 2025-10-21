<?php
session_start();

require_once __DIR__ . '/../config/database.php';
$config = require __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

// Get POST data
$user_id = $_POST['user_id'] ?? $_SESSION['pending_user_id'] ?? '';
$plan_type = $_POST['plan_type'] ?? 'monthly';
$card_number = preg_replace('/\s+/', '', $_POST['card_number'] ?? '');
$expiry = $_POST['expiry'] ?? '';
$cvc = $_POST['cvc'] ?? '';
$card_name = trim($_POST['card_name'] ?? '');

// Basic validation
if (empty($user_id)) {
    echo json_encode(['status' => 'error', 'message' => '❌ User session expired. Please start over.']);
    exit;
}

if (strlen($card_number) < 13 || strlen($card_number) > 19) {
    echo json_encode(['status' => 'error', 'message' => '❌ Invalid card number.']);
    exit;
}

if (!preg_match('/^\d{2}\/\d{2}$/', $expiry)) {
    echo json_encode(['status' => 'error', 'message' => '❌ Invalid expiry date format.']);
    exit;
}

if (strlen($cvc) < 3 || strlen($cvc) > 4) {
    echo json_encode(['status' => 'error', 'message' => '❌ Invalid CVC.']);
    exit;
}

if (empty($card_name)) {
    echo json_encode(['status' => 'error', 'message' => '❌ Please enter cardholder name.']);
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
    // Get user details
    $user = $db->get_row("SELECT user_id, email, fullname FROM IONEERS WHERE user_id = ?", $user_id);
    
    if (!$user) {
        throw new Exception('User not found');
    }
    
    // In a real implementation, you would:
    // 1. Process payment through Stripe/PayPal/etc
    // 2. Store transaction details
    // 3. Handle webhooks for payment confirmation
    
    // For this demo, we'll simulate a successful payment
    // You should replace this with actual payment gateway integration
    
    $payment_successful = true; // Simulate payment success
    
    if ($payment_successful) {
        // Calculate subscription dates - note: these columns don't exist in your table
        // You'll need to add them or track subscriptions in a separate table
        $subscription_data = [
            'user_role' => 'Member', // Upgrade from Guest to Member
            'last_login' => date('Y-m-d H:i:s')
        ];
        
        // Update user to Member status
        $update_result = $db->update('IONEERS', $subscription_data, ['user_id' => $user_id]);
        
        if (!$update_result) {
            throw new Exception('Failed to update subscription status');
        }
        
        // Log the payment (you would normally store this in a payments table)
        error_log("DEBUG process-payment.php - Payment processed for user: {$user->email} (Plan: $plan_type)");
        
        // Set up session for login
        $_SESSION['user_id'] = $user_id;
        $_SESSION['email'] = $user->email;
        $_SESSION['fullname'] = $user->fullname;
        $_SESSION['user_role'] = 'Member';
        $_SESSION['logged_in'] = true;
        
        // Clear pending registration data
        unset($_SESSION['pending_user_id']);
        unset($_SESSION['pending_email']);
        
        echo json_encode([
            'status' => 'success',
            'message' => '✅ Payment successful! Welcome to ION Pro!',
            'redirect' => '/app/'
        ]);
        
    } else {
        // Payment failed
        echo json_encode([
            'status' => 'error',
            'message' => '❌ Payment declined. Please check your card details and try again.'
        ]);
    }
    
} catch (\Throwable $t) {
    error_log("DEBUG process-payment.php - Payment error: " . $t->getMessage());
    echo json_encode(['status' => 'error', 'message' => '❌ Payment processing error: ' . $t->getMessage()]);
}
?>