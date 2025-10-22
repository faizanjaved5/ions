<?php
/**
 * Check if a video already exists by URL or provider video ID
 * Used for early duplicate detection before upload/import
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../wp-load.php';
require_once __DIR__ . '/../config/database.php';

// Get user session
session_start();
if (!isset($_SESSION['user_email'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$userEmail = $_SESSION['user_email'];

try {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    if ($action === 'check_platform_url') {
        checkPlatformUrl();
    } elseif ($action === 'check_file_hash') {
        checkFileHash();
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Check if a platform video (YouTube, Vimeo, etc.) already exists
 */
function checkPlatformUrl() {
    global $db, $userEmail;
    
    $url = $_POST['url'] ?? $_GET['url'] ?? '';
    $platform = $_POST['platform'] ?? $_GET['platform'] ?? '';
    
    if (empty($url) || empty($platform)) {
        echo json_encode(['success' => false, 'error' => 'Missing URL or platform']);
        return;
    }
    
    // Extract video ID from URL
    $videoId = extractVideoId($url, $platform);
    if (!$videoId) {
        echo json_encode(['success' => false, 'exists' => false, 'message' => 'Could not extract video ID']);
        return;
    }
    
    // Check if this video already exists in database
    // Check both video_url and provider_video_id fields
    $query = $db->prepare(
        "SELECT id, title, slug FROM IONLocalVideos 
         WHERE (video_url = %s OR video_url LIKE %s OR provider_video_id = %s) 
         AND upload_status != 'Failed'
         LIMIT 1",
        $url,
        '%' . $videoId . '%',
        $videoId
    );
    
    $existingVideo = $db->get_row($query);
    
    if ($existingVideo) {
        echo json_encode([
            'success' => true,
            'exists' => true,
            'message' => 'This video has already been imported. Please check your uploaded videos or try importing a different video.',
            'video_id' => $existingVideo->id,
            'video_title' => $existingVideo->title,
            'video_slug' => $existingVideo->slug
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'exists' => false,
            'message' => 'Video is available for import'
        ]);
    }
}

/**
 * Extract video ID from platform URL
 */
function extractVideoId($url, $platform) {
    $patterns = [
        'youtube' => '/(?:youtube\.com\/(?:watch\?.*v=|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/',
        'vimeo' => '/vimeo\.com\/(?:channels\/[^\/]+\/|groups\/[^\/]+\/videos\/|album\/[^\/]+\/video\/|video\/|)(\d+)/',
        'muvi' => '/(?:embed\.)?muvi\.com\/embed\/([a-zA-Z0-9]+)/',
        'dailymotion' => '/dai\.ly\/([a-zA-Z0-9]+)|dailymotion\.com\/video\/([a-zA-Z0-9]+)/',
        'wistia' => '/(?:wistia\.(?:com|net)\/(?:medias|embed)\/|wi\.st\/)([a-zA-Z0-9]{10})/',
        'loom' => '/loom\.com\/share\/([a-zA-Z0-9]+)/'
    ];
    
    $pattern = $patterns[$platform] ?? null;
    if (!$pattern) {
        return null;
    }
    
    if (preg_match($pattern, $url, $matches)) {
        // Return the first captured group (video ID)
        return $matches[1] ?? $matches[2] ?? null;
    }
    
    return null;
}

/**
 * Check if a file hash already exists (for file uploads)
 */
function checkFileHash() {
    global $db;
    
    $fileHash = $_POST['file_hash'] ?? '';
    
    if (empty($fileHash)) {
        echo json_encode(['success' => false, 'error' => 'Missing file hash']);
        return;
    }
    
    // Check if file with this hash already exists
    $query = $db->prepare(
        "SELECT id, title, slug FROM IONLocalVideos 
         WHERE file_hash = %s 
         AND upload_status != 'Failed'
         LIMIT 1",
        $fileHash
    );
    
    $existingVideo = $db->get_row($query);
    
    if ($existingVideo) {
        echo json_encode([
            'success' => true,
            'exists' => true,
            'message' => 'This file has already been uploaded. Please check your uploaded videos or try uploading a different file.',
            'video_id' => $existingVideo->id,
            'video_title' => $existingVideo->title,
            'video_slug' => $existingVideo->slug
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'exists' => false,
            'message' => 'File is available for upload'
        ]);
    }
}

