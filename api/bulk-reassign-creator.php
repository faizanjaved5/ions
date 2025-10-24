<?php
/**
 * Bulk Reassign Creator API
 * Reassigns multiple videos to a new creator
 * Version: 2.0 - Fixed PDO usage (uses getPDO() instead of prepare())
 */

// CRITICAL: Prevent any HTML output that would break JSON response
@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
@error_reporting(0); // Completely suppress all errors from displaying

// Start output buffering IMMEDIATELY to catch any accidental output
ob_start();

// Set JSON header FIRST before anything else
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// Error handler that outputs JSON instead of HTML
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("API Error: [$errno] $errstr in $errfile:$errline");
    // Don't output anything, just log
    return true;
});

// Exception handler that outputs JSON
set_exception_handler(function($exception) {
    ob_clean(); // Clear any output
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $exception->getMessage()
    ]);
    exit;
});

// Start session for authentication
try {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
} catch (Exception $e) {
    error_log("Session start error: " . $e->getMessage());
}

// Discard any output from session/includes
ob_clean();

// Diagnostic endpoint - GET request returns version info
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    ob_clean();
    echo json_encode([
        'version' => '2.0',
        'description' => 'Bulk Reassign Creator API - Fixed PDO usage',
        'timestamp' => date('Y-m-d H:i:s'),
        'file' => __FILE__
    ]);
    ob_end_flush();
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    ob_end_flush();
    exit;
}

// Check if user is logged in and is Admin or Owner
$user_role = $_SESSION['user_role'] ?? '';
if (!in_array($user_role, ['Admin', 'Owner'])) {
    ob_clean();
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Only Admin or Owner can reassign videos.']);
    ob_end_flush();
    exit;
}

// Load dependencies
try {
    require_once __DIR__ . '/../config/database.php';
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    ob_end_flush();
    exit;
}

// Clean buffer again after includes
ob_clean();

// Get JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
    ob_end_flush();
    exit;
}

// Validate input
$video_ids = $data['video_ids'] ?? [];
$new_handle = trim($data['new_handle'] ?? '');

if (empty($video_ids) || !is_array($video_ids)) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No video IDs provided']);
    ob_end_flush();
    exit;
}

if (empty($new_handle)) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'New creator handle is required']);
    ob_end_flush();
    exit;
}

// Sanitize video IDs (ensure they are integers)
$video_ids = array_map('intval', $video_ids);
$video_ids = array_filter($video_ids, function($id) {
    return $id > 0;
});

if (empty($video_ids)) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No valid video IDs provided']);
    ob_end_flush();
    exit;
}

try {
    // Get raw PDO connection (IONDatabase::prepare returns string, not PDOStatement)
    try {
        $pdo = $db->getPDO();
    } catch (Exception $e) {
        ob_clean();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Database connection error: ' . $e->getMessage()
        ]);
        ob_end_flush();
        exit;
    }
    
    // Verify we have a PDO instance
    if (!($pdo instanceof PDO)) {
        ob_clean();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Database connection failed: PDO not available'
        ]);
        ob_end_flush();
        exit;
    }
    
    // Find the user by handle
    $stmt = $pdo->prepare("SELECT user_id FROM IONEERS WHERE handle = ? LIMIT 1");
    $stmt->execute([$new_handle]);
    $new_user = $stmt->fetch(PDO::FETCH_OBJ);
    
    if (!$new_user) {
        ob_clean();
        http_response_code(404);
        echo json_encode([
            'success' => false, 
            'error' => "Creator '@{$new_handle}' not found. Please check the handle and try again."
        ]);
        ob_end_flush();
        exit;
    }
    
    $new_user_id = $new_user->user_id;
    
    // Create placeholders for prepared statement
    $placeholders = implode(',', array_fill(0, count($video_ids), '?'));
    
    // Update videos
    $update_query = "UPDATE IONLocalVideos SET user_id = ? WHERE id IN ($placeholders)";
    $params = array_merge([$new_user_id], $video_ids);
    
    $stmt = $pdo->prepare($update_query);
    $stmt->execute($params);
    
    $updated_count = $stmt->rowCount();
    
    // Log the action
    error_log("Bulk Reassign: User {$_SESSION['user_id']} reassigned {$updated_count} videos to user {$new_user_id} (@{$new_handle})");
    
    // Return success response
    ob_clean();
    echo json_encode([
        'success' => true,
        'updated_count' => $updated_count,
        'new_handle' => $new_handle,
        'message' => "Successfully reassigned {$updated_count} video(s) to @{$new_handle}"
    ]);
    ob_end_flush();
    
} catch (Exception $e) {
    error_log("Bulk Reassign Error: " . $e->getMessage());
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
    ob_end_flush();
}
