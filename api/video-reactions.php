<?php
/**
 * ION Video Reactions API
 * Handles like/unlike/dislike actions for videos
 * 
 * Endpoint: /api/video-reactions.php
 * Method: POST
 * 
 * Required Parameters:
 *   - video_id: ID of the video
 *   - action: 'like', 'unlike', or 'dislike'
 * 
 * Returns JSON response with updated counts
 */

header('Content-Type: application/json');
session_start();

// Load configuration and database
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Get PDO instance from the global $db object
global $db;
$pdo = $db->getPDO();

// CORS headers if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

/**
 * Send JSON response
 */
function respond($success, $message, $data = []) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

/**
 * Validate user is logged in
 */
function requireAuth() {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        respond(false, 'You must be logged in to react to videos', []);
    }
    return (int)$_SESSION['user_id'];
}

/**
 * Get video reaction counts
 */
function getVideoCounts($pdo, $videoId) {
    $stmt = $pdo->prepare("SELECT likes, dislikes FROM IONLocalVideos WHERE id = ?");
    $stmt->execute([$videoId]);
    $video = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$video) {
        return null;
    }
    
    return [
        'likes' => (int)$video['likes'],
        'dislikes' => (int)$video['dislikes']
    ];
}

/**
 * Get user's current action on video
 */
function getUserAction($pdo, $videoId, $userId) {
    $stmt = $pdo->prepare("SELECT action_type FROM IONVideoLikes WHERE video_id = ? AND user_id = ?");
    $stmt->execute([$videoId, $userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ? $result['action_type'] : null;
}

/**
 * Handle like action
 */
function handleLike($pdo, $videoId, $userId) {
    try {
        $pdo->beginTransaction();
        
        // Check current action
        $currentAction = getUserAction($pdo, $videoId, $userId);
        
        if ($currentAction === 'like') {
            // User already liked - do nothing (or you could allow unliking)
            $pdo->commit();
            return [
                'action' => 'already_liked',
                'counts' => getVideoCounts($pdo, $videoId),
                'user_action' => 'like'
            ];
        } else if ($currentAction === 'dislike') {
            // User had disliked - change to like
            // Decrement dislikes, increment likes
            $stmt = $pdo->prepare("UPDATE IONLocalVideos SET dislikes = GREATEST(dislikes - 1, 0), likes = likes + 1 WHERE id = ?");
            $stmt->execute([$videoId]);
            
            // Update user action
            $stmt = $pdo->prepare("UPDATE IONVideoLikes SET action_type = 'like', updated_at = NOW() WHERE video_id = ? AND user_id = ?");
            $stmt->execute([$videoId, $userId]);
            
            $pdo->commit();
            return [
                'action' => 'changed_to_like',
                'counts' => getVideoCounts($pdo, $videoId),
                'user_action' => 'like'
            ];
        } else {
            // New like
            $stmt = $pdo->prepare("UPDATE IONLocalVideos SET likes = likes + 1 WHERE id = ?");
            $stmt->execute([$videoId]);
            
            // Insert user action
            $stmt = $pdo->prepare("INSERT INTO IONVideoLikes (video_id, user_id, action_type) VALUES (?, ?, 'like')");
            $stmt->execute([$videoId, $userId]);
            
            $pdo->commit();
            return [
                'action' => 'liked',
                'counts' => getVideoCounts($pdo, $videoId),
                'user_action' => 'like'
            ];
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Handle unlike action
 */
function handleUnlike($pdo, $videoId, $userId) {
    try {
        $pdo->beginTransaction();
        
        // Check current action
        $currentAction = getUserAction($pdo, $videoId, $userId);
        
        if ($currentAction === 'like') {
            // Remove like
            $stmt = $pdo->prepare("UPDATE IONLocalVideos SET likes = GREATEST(likes - 1, 0) WHERE id = ?");
            $stmt->execute([$videoId]);
            
            // Delete user action
            $stmt = $pdo->prepare("DELETE FROM IONVideoLikes WHERE video_id = ? AND user_id = ?");
            $stmt->execute([$videoId, $userId]);
            
            $pdo->commit();
            return [
                'action' => 'unliked',
                'counts' => getVideoCounts($pdo, $videoId),
                'user_action' => null
            ];
        } else if ($currentAction === 'dislike') {
            // Remove dislike
            $stmt = $pdo->prepare("UPDATE IONLocalVideos SET dislikes = GREATEST(dislikes - 1, 0) WHERE id = ?");
            $stmt->execute([$videoId]);
            
            // Delete user action
            $stmt = $pdo->prepare("DELETE FROM IONVideoLikes WHERE video_id = ? AND user_id = ?");
            $stmt->execute([$videoId, $userId]);
            
            $pdo->commit();
            return [
                'action' => 'removed_dislike',
                'counts' => getVideoCounts($pdo, $videoId),
                'user_action' => null
            ];
        } else {
            // No action to remove
            $pdo->commit();
            return [
                'action' => 'no_action',
                'counts' => getVideoCounts($pdo, $videoId),
                'user_action' => null
            ];
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Handle dislike action
 */
function handleDislike($pdo, $videoId, $userId) {
    try {
        $pdo->beginTransaction();
        
        // Check current action
        $currentAction = getUserAction($pdo, $videoId, $userId);
        
        if ($currentAction === 'dislike') {
            // User already disliked - do nothing
            $pdo->commit();
            return [
                'action' => 'already_disliked',
                'counts' => getVideoCounts($pdo, $videoId),
                'user_action' => 'dislike'
            ];
        } else if ($currentAction === 'like') {
            // User had liked - change to dislike
            // Decrement likes, increment dislikes
            $stmt = $pdo->prepare("UPDATE IONLocalVideos SET likes = GREATEST(likes - 1, 0), dislikes = dislikes + 1 WHERE id = ?");
            $stmt->execute([$videoId]);
            
            // Update user action
            $stmt = $pdo->prepare("UPDATE IONVideoLikes SET action_type = 'dislike', updated_at = NOW() WHERE video_id = ? AND user_id = ?");
            $stmt->execute([$videoId, $userId]);
            
            $pdo->commit();
            return [
                'action' => 'changed_to_dislike',
                'counts' => getVideoCounts($pdo, $videoId),
                'user_action' => 'dislike'
            ];
        } else {
            // New dislike
            $stmt = $pdo->prepare("UPDATE IONLocalVideos SET dislikes = dislikes + 1 WHERE id = ?");
            $stmt->execute([$videoId]);
            
            // Insert user action
            $stmt = $pdo->prepare("INSERT INTO IONVideoLikes (video_id, user_id, action_type) VALUES (?, ?, 'dislike')");
            $stmt->execute([$videoId, $userId]);
            
            $pdo->commit();
            return [
                'action' => 'disliked',
                'counts' => getVideoCounts($pdo, $videoId),
                'user_action' => 'dislike'
            ];
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// ============================================
// MAIN REQUEST HANDLER
// ============================================

try {
    // Only allow POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(false, 'Method not allowed', []);
    }
    
    // Get request data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        $input = $_POST;
    }
    
    // Validate required parameters
    if (!isset($input['video_id']) || !isset($input['action'])) {
        respond(false, 'Missing required parameters: video_id and action', []);
    }
    
    $videoId = (int)$input['video_id'];
    $action = strtolower(trim($input['action']));
    
    // Validate video_id
    if ($videoId <= 0) {
        respond(false, 'Invalid video ID', []);
    }
    
    // Validate action
    $validActions = ['like', 'unlike', 'dislike'];
    if (!in_array($action, $validActions)) {
        respond(false, 'Invalid action. Must be: like, unlike, or dislike', []);
    }
    
    // Check authentication
    $userId = requireAuth();
    
    // Verify video exists
    $stmt = $pdo->prepare("SELECT id FROM IONLocalVideos WHERE id = ?");
    $stmt->execute([$videoId]);
    if (!$stmt->fetch()) {
        respond(false, 'Video not found', []);
    }
    
    // Process action
    $result = null;
    
    switch ($action) {
        case 'like':
            $result = handleLike($pdo, $videoId, $userId);
            break;
        case 'unlike':
            $result = handleUnlike($pdo, $videoId, $userId);
            break;
        case 'dislike':
            $result = handleDislike($pdo, $videoId, $userId);
            break;
    }
    
    // Return success response
    respond(true, 'Action completed successfully', $result);
    
} catch (Exception $e) {
    error_log('Video reactions error: ' . $e->getMessage());
    respond(false, 'An error occurred processing your request', []);
}

