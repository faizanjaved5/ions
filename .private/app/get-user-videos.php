<?php
session_start();

// Include required files
require_once '../config/config.php';
require_once '../login/roles.php';

// Set JSON content type
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    echo json_encode(['success' => false, 'error' => 'User not authenticated']);
    exit();
}

$user_role = $_SESSION['user_role'];
$user_email = $_SESSION['user_email'];

// Check if user can access user management (only Admin/Owner)
if (!IONRoles::canAccessSection($user_role, 'IONEERS')) {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit();
}

// Only handle GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

try {
    // Get user ID from query parameter
    $user_id = isset($_GET['user_id']) ? trim($_GET['user_id']) : '';
    
    if (empty($user_id)) {
        echo json_encode(['success' => false, 'error' => 'User ID is required']);
        exit();
    }

    // Fetch user videos with error handling
    $videos_query = "
        SELECT id, title, description, category, status, source, video_id, video_link, 
               thumbnail, date_added, user_email
        FROM IONLocalVideos 
        WHERE user_id = %s 
        ORDER BY date_added DESC
        LIMIT 50
    ";
    
    $videos = $wpdb->get_results($wpdb->prepare($videos_query, $user_id));
    
    if ($videos === false) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $wpdb->last_error]);
        exit();
    }

    // Process videos for safe output
    $processed_videos = [];
    foreach ($videos as $video) {
        $processed_videos[] = [
            'id' => (int)$video->id,
            'title' => html_entity_decode($video->title, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'description' => $video->description,
            'category' => $video->category,
            'status' => $video->status,
            'source' => $video->source,
            'video_id' => $video->video_id,
            'video_link' => $video->video_link,
            'thumbnail' => $video->thumbnail,
            'date_added' => $video->date_added,
            'user_email' => $video->user_email
        ];
    }

    echo json_encode([
        'success' => true,
        'videos' => $processed_videos,
        'count' => count($processed_videos)
    ]);

} catch (Exception $e) {
    error_log("GET USER VIDEOS ERROR: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error occurred']);
}
?>