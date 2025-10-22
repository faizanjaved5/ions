<?php
/**
 * Google Drive Token Refresh API
 * Automatically refreshes expired access tokens using stored refresh tokens
 * 
 * POST Parameters:
 * - email: Google account email to refresh token for
 * 
 * Returns:
 * - success: true/false
 * - access_token: New access token (if successful)
 * - expires_in: Token lifespan in seconds
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
            error_log('📧 Refresh Google Drive token: Got user_id ' . $userId . ' from email ' . $_SESSION['user_email']);
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
    
    error_log('🔄 Refresh Google Drive token: User ID=' . $userId . ', Email=' . $email);
    
    // Load configuration - FIX: Use correct config keys
    $config = require __DIR__ . '/../config/config.php';
    $clientId = $config['google_client_id'] ?? '';
    $clientSecret = $config['google_client_secret'] ?? '';
    
    if (empty($clientId) || empty($clientSecret)) {
        throw new Exception('Google Drive OAuth credentials not configured');
    }
    
    // Get stored tokens from database
    if (!isset($db)) {
        $db = new IONDatabase();
    }
    $tokenData = $db->get_row("
        SELECT id, refresh_token, email, access_token, expires_at
        FROM IONGoogleDriveTokens
        WHERE user_id = ? AND email = ?
    ", [$userId, $email]);
    
    error_log('🔍 Refresh token lookup result: ' . ($tokenData ? 'FOUND' : 'NOT FOUND'));
    
    if (!$tokenData) {
        throw new Exception('No saved Google Drive connection found for this email');
    }
    
    // FIX: Access as object properties, not array
    if (empty($tokenData->refresh_token)) {
        throw new Exception('No refresh token available. Please reconnect your Google Drive account.');
    }
    
    // ============================================
    // Request New Access Token Using Refresh Token
    // ============================================
    
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_POSTFIELDS => http_build_query([
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $tokenData->refresh_token,
            'grant_type' => 'refresh_token'
        ])
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        error_log('Google token refresh failed (HTTP ' . $httpCode . '): ' . $response);
        
        // Parse error response
        $errorData = json_decode($response, true);
        $errorMsg = $errorData['error_description'] ?? 'Failed to refresh token';
        
        // Check if refresh token is invalid/revoked
        if (isset($errorData['error']) && in_array($errorData['error'], ['invalid_grant', 'unauthorized_client'])) {
            // Delete invalid token from database - FIX: Access as object
            $db->query("DELETE FROM IONGoogleDriveTokens WHERE id = ?", [$tokenData->id]);
            throw new Exception('Google Drive access has been revoked. Please reconnect your account.');
        }
        
        throw new Exception($errorMsg);
    }
    
    $tokens = json_decode($response, true);
    
    if (!isset($tokens['access_token'])) {
        error_log('Token refresh response missing access_token: ' . $response);
        throw new Exception('Failed to obtain new access token from Google');
    }
    
    // ============================================
    // Update Database with New Access Token
    // ============================================
    
    $expiresIn = $tokens['expires_in'] ?? 3600;
    $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);
    
    // Update access token and expiry time in database - FIX: Access as object
    $db->query("
        UPDATE IONGoogleDriveTokens
        SET access_token = ?,
            expires_at = ?,
            updated_at = NOW()
        WHERE id = ?
    ", [
        $tokens['access_token'],
        $expiresAt,
        $tokenData->id
    ]);
    
    // ============================================
    // Return Success Response
    // ============================================
    
    echo json_encode([
        'success' => true,
        'access_token' => $tokens['access_token'],
        'expires_in' => $expiresIn,
        'email' => $email,
        'message' => 'Token refreshed successfully'
    ]);
    
} catch (Exception $e) {
    // Log error
    error_log('Google Drive token refresh error: ' . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

