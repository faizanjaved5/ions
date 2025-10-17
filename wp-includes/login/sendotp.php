<?php
// Move use statements to the very top
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

session_start();

// Load dependencies first
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/phpmailer/PHPMailer.php';
require_once __DIR__ . '/../config/phpmailer/Exception.php';
require_once __DIR__ . '/../config/phpmailer/SMTP.php';
$config = require __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

$email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
if (!$email) {
    echo json_encode(['status' => 'error', 'message' => '❌ Invalid email address.']);
    exit;
}

// Check if this is from join flow
$from_join = isset($_POST['from_join']) && $_POST['from_join'] === true;

global $db;

// Reinitialize database connection if not connected
if (!$db->isConnected()) {
    $db = new IONDatabase();
    if (!$db->isConnected()) {
        echo json_encode(['status' => 'error', 'message' => '❌ Database connection failed.']);
        exit;
    }
}

// ✅ CHECK IF USER EXISTS AND IS AUTHORIZED
try {
    $user_check = $db->get_row("SELECT user_role, status FROM IONEERS WHERE email = ?", $email);
    
    if (!$user_check) {
        echo json_encode(['status' => 'error', 'message' => '❌ Access Denied: Not an authorized user.']);
        exit;
    }
    
    // Check if user is blocked or has no valid role
    $user_role = trim($user_check->user_role ?? '');
    $status = trim($user_check->status ?? '');
    
    // For join flow, we allow Guest and Creator roles
    if ($from_join) {
        $blocked_roles = ['None', 'none', ''];  // Don't block Guest/Creator in join flow
        $valid_roles = ['Owner', 'Admin', 'Creator', 'Member', 'Uploader', 'Guest'];  // All roles except None
    } else {
        // For login flow, use original restrictions
        $blocked_roles = ['None', 'none', 'Guest', 'guest', ''];
        $valid_roles = ['Owner', 'Admin', 'Creator', 'Member', 'Uploader'];
    }
    
    $is_blocked_role = in_array($user_role, $blocked_roles);
    $is_valid_role = in_array($user_role, $valid_roles);
    $is_blocked_status = in_array(strtolower($status), ['blocked', 'inactive', 'disabled']);
    
    $is_authorized = $is_valid_role && !$is_blocked_status;
    
    if (!$is_authorized) {
        $reason = '';
        if (!$is_valid_role) {
            $reason = "Invalid role: '$user_role'. Valid roles are: " . implode(', ', $valid_roles);
        } elseif ($is_blocked_status) {
            $reason = "Account status: '$status' is blocked";
        }
        
        error_log("DEBUG sendotp.php - User is blocked or unauthorized: " . $email . " (role: $user_role, status: $status) - Reason: $reason");
        echo json_encode(['status' => 'error', 'message' => '❌ Access Denied: Your account is not authorized. Please contact an administrator.']);
        exit;
    }
    
} catch (\Throwable $t) {
    echo json_encode(['status' => 'error', 'message' => '❌ Error in user check: ' . $t->getMessage()]);
    exit;
}

// ✅ GENERATE OTP
$otp = rand(100000, 999999);
$expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));

// DEBUG: Log the generated OTP
error_log("DEBUG sendotp.php - Generated OTP for $email: $otp");

try {
    // Clear any existing OTP for this email first
    $db->update('IONEERS', [
        'otp_code' => null,
        'expires_at' => null
    ], ['email' => $email]);
    error_log("DEBUG sendotp.php - Cleared existing OTP for $email");
    
    // Update with new OTP
    $update_result = $db->update('IONEERS', [
        'otp_code' => $otp,
        'expires_at' => $expires_at
    ], ['email' => $email]);
    
    if (!$update_result) {
        throw new Exception('Failed to update OTP in IONEERS table');
    }
    error_log("DEBUG sendotp.php - Updated OTP in IONEERS for $email: $otp");
    
    // Verify what was actually saved
    $saved_otp = $db->get_row("SELECT otp_code, expires_at FROM IONEERS WHERE email = ?", $email);
    error_log("DEBUG sendotp.php - Verified saved OTP: " . print_r($saved_otp, true));
    
} catch (\Throwable $t) {
    error_log("DEBUG sendotp.php - Database error: " . $t->getMessage());
    echo json_encode(['status' => 'error', 'message' => '❌ Error in OTP insertion: ' . $t->getMessage()]);
    exit;
}

// ✅ SEND EMAIL
$mail = new PHPMailer(true);
try {
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
    require_once __DIR__ . '/otpemail.php';
    $mail->Body = get_otp_email($otp, $email);
    
    // DEBUG: Log the OTP being sent in email
    error_log("DEBUG sendotp.php - Sending email with OTP: $otp to $email");
    
    $mail->send();
    
    // Final verification of database state
    $final_check = $db->get_row("SELECT otp_code, expires_at FROM IONEERS WHERE email = ?", $email);
    error_log("DEBUG sendotp.php - Final database check: " . print_r($final_check, true));
    
    $_SESSION['pending_email'] = $email;
    
    // If from join flow, also set the OTP email session
    if ($from_join) {
        $_SESSION['pending_otp_email'] = $email;
        $_SESSION['otp_sent_time'] = time();
    }
    
    echo json_encode(['status' => 'success', 'message' => "✅ OTP sent to $email"]);
    exit;
} catch (\Throwable $t) {
    error_log("DEBUG sendotp.php - Email error: " . $t->getMessage());
    echo json_encode(['status' => 'error', 'message' => '❌ Error in sending OTP: ' . $t->getMessage()]);
    exit;
}
?>