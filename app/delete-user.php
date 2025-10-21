<?php
require_once __DIR__ . '/../login/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../login/roles.php';

// Error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
error_log('delete-user.php: Execution started.');

// Set JSON response header
header('Content-Type: application/json');

// SECURITY CHECK: Ensure user is authenticated
if (!isset($_SESSION['user_email']) || empty($_SESSION['user_email'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized: Not logged in']);
    exit();
}

// Fetch current user data
$current_user_email = $_SESSION['user_email'];
$current_user_data = $db->get_row($db->prepare("SELECT user_id, user_role FROM IONEERS WHERE email = %s", $current_user_email));

if (!$current_user_data) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized: User not found']);
    exit();
}

$current_user_role = trim($current_user_data->user_role ?? '');

// PERMISSION CHECK: Only Owners and Admins can delete users
if (!in_array($current_user_role, ['Owner', 'Admin'])) {
    error_log("DELETE USER: Permission denied for user {$current_user_email} with role {$current_user_role}");
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden: Insufficient permissions to delete users']);
    exit();
}

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['user_id']) || empty($data['user_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Bad Request: Missing user_id']);
    exit();
}

$user_id_to_delete = intval($data['user_id']);

// SECURITY: Prevent self-deletion
if ($user_id_to_delete == $current_user_data->user_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Cannot delete your own account']);
    exit();
}

// Verify the user exists
$user_to_delete = $db->get_row($db->prepare("SELECT user_id, email, fullname, user_role FROM IONEERS WHERE user_id = %d", $user_id_to_delete));

if (!$user_to_delete) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit();
}

// PERMISSION CHECK: Only Owners can delete other Owners
if ($user_to_delete->user_role === 'Owner' && $current_user_role !== 'Owner') {
    error_log("DELETE USER: Admin cannot delete Owner. Current: {$current_user_role}, Target: {$user_to_delete->user_role}");
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Only Owners can delete other Owners']);
    exit();
}

error_log("DELETE USER: Starting deletion of user_id={$user_id_to_delete}, email={$user_to_delete->email}, by={$current_user_email}");

// Begin transaction
$db->query('START TRANSACTION');

try {
    // 1. Count and delete user's videos from IONLocalVideos
    $video_count = $db->get_var($db->prepare("SELECT COUNT(*) FROM IONLocalVideos WHERE user_id = %s", $user_to_delete->user_id));
    error_log("DELETE USER: Found {$video_count} videos for user_id={$user_id_to_delete}");
    
    if ($video_count > 0) {
        $deleted_videos = $db->query($db->prepare("DELETE FROM IONLocalVideos WHERE user_id = %s", $user_to_delete->user_id));
        if ($deleted_videos === false) {
            throw new Exception('Failed to delete user videos: ' . $db->last_error);
        }
        error_log("DELETE USER: Deleted {$video_count} videos");
    }
    
    // 2. Delete from other related tables (if they exist)
    // Check if tables exist before attempting to delete
    $tables_to_check = [
        'IONUserBadges' => 'user_id',
        'IONComments' => 'user_id',
        'IONLikes' => 'user_id',
        'IONFollowers' => 'follower_id',
        'IONFollowers_following' => 'following_id'
    ];
    
    foreach ($tables_to_check as $table => $column) {
        $table_exists = $db->get_var("SHOW TABLES LIKE '{$table}'");
        if ($table_exists) {
            $deleted = $db->query($db->prepare("DELETE FROM {$table} WHERE {$column} = %s", $user_to_delete->user_id));
            if ($deleted !== false) {
                $affected = $db->rows_affected;
                if ($affected > 0) {
                    error_log("DELETE USER: Deleted {$affected} rows from {$table}");
                }
            }
        }
    }
    
    // 3. Finally, delete the user from IONEERS
    $deleted_user = $db->query($db->prepare("DELETE FROM IONEERS WHERE user_id = %d", $user_id_to_delete));
    if ($deleted_user === false) {
        throw new Exception('Failed to delete user from IONEERS: ' . $db->last_error);
    }
    
    if ($db->rows_affected === 0) {
        throw new Exception('User deletion affected 0 rows - user may not exist');
    }
    
    // Commit transaction
    $db->query('COMMIT');
    
    error_log("DELETE USER: Successfully deleted user_id={$user_id_to_delete}, email={$user_to_delete->email}, videos={$video_count}");
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'User and all related content deleted successfully',
        'videos_deleted' => intval($video_count),
        'user_email' => $user_to_delete->email,
        'user_name' => $user_to_delete->fullname
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $db->query('ROLLBACK');
    
    error_log("DELETE USER ERROR: " . $e->getMessage());
    error_log("DELETE USER ERROR: DB Last Error: " . $db->last_error);
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage(),
        'db_error' => $db->last_error
    ]);
}
