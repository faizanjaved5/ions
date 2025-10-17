<?php
session_start();

// Include required files
require_once '../config/config.php';
require_once '../login/roles.php';

// Set JSON content type
header('Content-Type: application/json');

// Check authentication - try multiple session variable names
if (!isset($_SESSION['user_email']) || empty($_SESSION['user_email'])) {
    echo json_encode(['success' => false, 'error' => 'User not authenticated']);
    exit();
}

// Include database connection
require_once '../config/database.php';
$wpdb = $db;

// Get user role from database
$user_email = $_SESSION['user_email'];
$user_data = $wpdb->get_row($wpdb->prepare("SELECT user_role FROM IONEERS WHERE email = %s", $user_email));

if (!$user_data) {
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit();
}

$user_role = $user_data->user_role;

// Check if user can manage video status (Admin/Owner only)
if (!in_array($user_role, ['Owner', 'Admin'])) {
    echo json_encode(['success' => false, 'error' => 'Access denied - Admin permissions required']);
    exit();
}

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

try {
    $video_id = intval($_POST['video_id'] ?? 0);
    $new_status = trim($_POST['status'] ?? '');
    
    // Basic debugging
    error_log("VIDEO STATUS UPDATE: video_id=$video_id, status=$new_status");
    
    // Validate input
    if ($video_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid video ID']);
        exit();
    }
    
    // Valid status options
    $valid_statuses = ['Pending', 'Approved', 'Rejected', 'Paused'];
    
    if (!in_array($new_status, $valid_statuses)) {
        echo json_encode(['success' => false, 'error' => 'Invalid status value']);
        exit();
    }
    
    // Get video info first with detailed debugging
    error_log("VIDEO STATUS: Querying for video ID: $video_id");
    $video = $wpdb->get_row($wpdb->prepare(
        "SELECT id, title, status, user_email FROM IONLocalVideos WHERE id = %d",
        $video_id
    ));
    
    error_log("VIDEO STATUS: Query result: " . ($video ? "FOUND" : "NOT FOUND"));
    error_log("VIDEO STATUS: Last query: " . $wpdb->last_query);
    error_log("VIDEO STATUS: Last error: " . ($wpdb->last_error ?: "none"));
    
    if (!$video) {
        // Check if video exists with any ID
        $all_count = $wpdb->get_var("SELECT COUNT(*) FROM IONLocalVideos");
        $video_check = $wpdb->get_row($wpdb->prepare(
            "SELECT id, title, slug FROM IONLocalVideos WHERE id = %d OR slug LIKE %s",
            $video_id,
            '%' . $video_id . '%'
        ));
        
        error_log("VIDEO STATUS: Total videos in DB: $all_count");
        error_log("VIDEO STATUS: Video check result: " . print_r($video_check, true));
        
        echo json_encode([
            'success' => false, 
            'error' => "Video not found in database (ID: $video_id). This video may have been imported incorrectly. Please delete and re-import this video.",
            'suggestion' => 'Delete this video and import it again to fix the issue',
            'debug' => [
                'requested_id' => $video_id,
                'total_videos' => $all_count,
                'last_query' => $wpdb->last_query,
                'last_error' => $wpdb->last_error
            ]
        ]);
        exit();
    }
    
    // Update video status
    $result = $wpdb->update(
        'IONLocalVideos',
        ['status' => $new_status],
        ['id' => $video_id],
        ['%s'],
        ['%d']
    );
    
    if ($result === false) {
        echo json_encode(['success' => false, 'error' => 'Database update failed: ' . $wpdb->last_error]);
        exit();
    }
    
    // Log the status change for security/audit purposes
    error_log("VIDEO STATUS CHANGE: User {$_SESSION['user_email']} ({$user_role}) changed video {$video_id} ('{$video->title}') status from '{$video->status}' to '{$new_status}'");
    
    // Optional: Send notification email to video owner if status changed to approved/rejected
    if (in_array($new_status, ['Approved', 'Rejected']) && $video->user_email) {
        // You could implement email notification here
        // sendStatusChangeNotification($video->user_email, $video->title, $new_status);
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Video status changed to {$new_status}",
        'new_status' => $new_status,
        'status_class' => 'status-' . str_replace(' ', '-', strtolower($new_status))
    ]);
    
} catch (Exception $e) {
    error_log('Video status update error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error occurred']);
}
?>