<?php
/**
 * API endpoint to get video data for editing
 */

// Enable error reporting for debugging but don't display errors (breaks JSON)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Log that the script is being called
error_log('GET-VIDEO-DATA: Script accessed at ' . date('Y-m-d H:i:s'));
error_log('GET-VIDEO-DATA: Request method: ' . $_SERVER['REQUEST_METHOD']);
error_log('GET-VIDEO-DATA: Query string: ' . ($_SERVER['QUERY_STRING'] ?? 'none'));

// Start output buffering to catch any unexpected output
ob_start();

header('Content-Type: application/json');

try {
    // Load dependencies
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../login/session.php';
    
    // Clear any buffered output from includes
    ob_clean();

// Use the same database connection as creators.php
$wpdb = $db;

// Security check - ensure user is authenticated
if (!isset($_SESSION['user_email']) || empty($_SESSION['user_email'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

// Get user data to check permissions (using wpdb like creators.php)
$user_email = $_SESSION['user_email'];
$user_data = $wpdb->get_row($wpdb->prepare("SELECT user_id, user_role FROM IONEERS WHERE email = %s", $user_email));

if (!$user_data) {
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit();
}

$user_id = $user_data->user_id;
$user_role = $user_data->user_role;

// Get video ID from request
$video_id = $_GET['id'] ?? '';
error_log('GET-VIDEO-DATA: Requested video ID: ' . $video_id);
error_log('GET-VIDEO-DATA: User ID: ' . $user_id . ', Role: ' . $user_role);

if (empty($video_id)) {
    echo json_encode(['success' => false, 'error' => 'Video ID is required']);
    exit();
}

// Fetch video data using wpdb like creators.php
error_log('GET-VIDEO-DATA: Executing query for video ID: ' . $video_id);
$video = $wpdb->get_row($wpdb->prepare("SELECT * FROM IONLocalVideos WHERE id = %d", $video_id));
error_log('GET-VIDEO-DATA: Query result: ' . ($video ? 'FOUND' : 'NOT FOUND'));

if (!$video) {
    error_log('GET-VIDEO-DATA: Video not found. Last error: ' . $wpdb->last_error);
    error_log('GET-VIDEO-DATA: Last query: ' . $wpdb->last_query);
    
    // Check if video exists with different criteria
    $all_videos_count = $wpdb->get_var("SELECT COUNT(*) FROM IONLocalVideos");
    $video_exists_any = $wpdb->get_row($wpdb->prepare("SELECT id, title, upload_status FROM IONLocalVideos WHERE id = %d", $video_id));
    
    error_log('GET-VIDEO-DATA: Total videos in table: ' . $all_videos_count);
    if ($video_exists_any) {
        error_log('GET-VIDEO-DATA: Video exists but filtered out - ID: ' . $video_exists_any->id . ', Title: ' . $video_exists_any->title . ', Upload Status: ' . ($video_exists_any->upload_status ?? 'NULL'));
    } else {
        error_log('GET-VIDEO-DATA: Video truly does not exist in database');
    }
    
    echo json_encode(['success' => false, 'error' => 'Video not found']);
    exit();
}

error_log('GET-VIDEO-DATA: Found video - Title: ' . $video->title . ', User ID: ' . $video->user_id);

// Security check: Ensure user can edit this video
$can_edit = in_array($user_role, ['Owner', 'Admin']) || ($video->user_id == $user_id);

if (!$can_edit) {
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit();
}

// Return video data in expected format
echo json_encode([
    'success' => true,
    'video' => [
        'id' => $video->id,
        'title' => $video->title,
        'description' => $video->description,
        'category' => $video->category,
        'tags' => $video->tags,
        'visibility' => $video->visibility ?? 'public',
        'thumbnail' => $video->thumbnail,
        'video_link' => $video->video_link,
        'video_id' => $video->video_id,
        'source' => $video->source,
        'status' => $video->status,
        'date_added' => $video->date_added
    ]
]);
    
} catch (Exception $e) {
    error_log("Get video data error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}
?>