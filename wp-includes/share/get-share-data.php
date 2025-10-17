<?php
/**
 * API endpoint to get share data for a video
 */

header('Content-Type: application/json');

// Load dependencies
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/share-manager.php';

// Get video ID from request
$video_id = isset($_GET['video_id']) ? intval($_GET['video_id']) : 0;

if (!$video_id) {
    echo json_encode([
        'success' => false,
        'error' => 'Video ID is required'
    ]);
    exit;
}

try {
    // Initialize share manager
    $share_manager = new IONShareManager($db);
    
    // Get share data
    $share_data = $share_manager->getShareData($video_id);
    
    if ($share_data) {
        echo json_encode([
            'success' => true,
            'data' => $share_data
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Video not found or share data unavailable'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Share data API error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}
?>
