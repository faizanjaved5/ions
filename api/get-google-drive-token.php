<?php
/**
 * Get Google Drive Access Token
 * Retrieves current access token for a user's Google Drive connection
 * 
 * POST Parameters:
 * - email: Google account email
 * 
 * Returns:
 * - success: true/false
 * - access_token: Current access token (if successful)
 * - expires_at: When the token expires
 * - error: Error message (if failed)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Start session to access user ID
session_start();

// Set JSON response header
header('Content-Type: application/json');

// Clean output buffer
ob_start();
ob_clean();

try {
    // Verify user is logged in
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['user_email'])) {
        throw new Exception('User not logged in');
    }
    
    // Get user ID from session or database
    $userId = $_SESSION['user_id'] ?? null;
    
    if (!$userId && isset($_SESSION['user_email'])) {
        // Get user_id from email
        $db = new IONDatabase();
        $user = $db->get_row("SELECT user_id FROM IONEERS WHERE email = ?", [$_SESSION['user_email']]);
        if ($user) {
            $userId = $user->user_id;  // FIX: Access as object, not array
            error_log('📧 Get Google Drive token: Got user_id ' . $userId . ' from email ' . $_SESSION['user_email']);
        } else {
            throw new Exception('User not found in database');
        }
    }
    
    if (!$userId) {
        throw new Exception('Could not determine user ID');
    }
    
    // Get email parameter
    $email = $_POST['email'] ?? $_GET['email'] ?? '';
    
    if (empty($email)) {
        throw new Exception('Email parameter is required');
    }
    
    error_log('🔑 Get Google Drive token: Looking up token for user_id=' . $userId . ', email=' . $email);
    
    // Get stored token from database
    if (!isset($db)) {
        $db = new IONDatabase();
    }
    $tokenData = $db->get_row("
        SELECT access_token, expires_at, refresh_token
        FROM IONGoogleDriveTokens
        WHERE user_id = ? AND email = ?
    ", [$userId, $email]);
    
    error_log('🔍 Get Google Drive token: Query result: ' . ($tokenData ? 'FOUND' : 'NOT FOUND'));
    
    if (!$tokenData) {
        throw new Exception('No Google Drive connection found for this email');
    }
    
    // FIX: Access as object properties, not array
    // Check if token is expired
    $expiresAt = strtotime($tokenData->expires_at);
    $isExpired = time() >= $expiresAt;
    
    // Return token info
    echo json_encode([
        'success' => true,
        'access_token' => $tokenData->access_token,
        'expires_at' => $tokenData->expires_at,
        'is_expired' => $isExpired,
        'has_refresh_token' => !empty($tokenData->refresh_token),
        'email' => $email
    ]);
    
} catch (Exception $e) {
    // Log error
    error_log('Get Google Drive token error: ' . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

