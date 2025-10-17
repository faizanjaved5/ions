<?php
// Prevent any HTML output
ob_start();

// Set JSON header first
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Error handling function
function sendJsonError($message, $details = null) {
    ob_clean(); // Clear any previous output
    echo json_encode([
        'success' => false, 
        'error' => $message,
        'details' => $details,
        'debug' => [
            'file' => __FILE__,
            'time' => date('Y-m-d H:i:s')
        ]
    ]);
    exit();
}

// Catch any fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        sendJsonError('PHP Fatal Error', $error['message']);
    }
});

// Log the request for debugging
error_log("BADGE API: Request received - " . print_r($_POST, true));

// Safely include required files
try {
    require_once __DIR__ . '/../config/database.php';
} catch (Exception $e) {
    sendJsonError('Database config error', $e->getMessage());
}

try {
    require_once __DIR__ . '/../login/session.php';
} catch (Exception $e) {
    sendJsonError('Session config error', $e->getMessage());
}

// Check if database connection exists
if (!isset($db) || !$db) {
    sendJsonError('Database connection failed', 'Database object not found');
}

$wpdb = $db;

// Debug session
error_log("BADGE API: Session data - " . print_r($_SESSION, true));

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Owner', 'Admin'])) {
    error_log("BADGE API ERROR: Unauthorized access - user_id: " . ($_SESSION['user_id'] ?? 'not set') . ", role: " . ($_SESSION['user_role'] ?? 'not set'));
    sendJsonError('Unauthorized access', 'User must be Owner or Admin');
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$video_id = $_POST['video_id'] ?? $_GET['video_id'] ?? '';
$badge = $_POST['badge'] ?? $_GET['badge'] ?? '';

error_log("BADGE API: Received - action: $action, video_id: $video_id (type: " . gettype($video_id) . "), badge: $badge");

// Use isset() instead of empty() to allow video_id = 0
if (!isset($_POST['action']) && !isset($_GET['action'])) {
    sendJsonError('Missing required parameters', "action parameter is missing");
}

if (!isset($_POST['video_id']) && !isset($_GET['video_id'])) {
    sendJsonError('Missing required parameters', "video_id parameter is missing");
}

// Validate video_id is numeric and convert to integer
if (!is_numeric($video_id)) {
    sendJsonError('Invalid video ID', "video_id must be numeric, got: $video_id (type: " . gettype($video_id) . ")");
}

$video_id = intval($video_id);

if ($video_id < 1) {
    sendJsonError('Invalid video ID', "video_id must be a positive integer, got: $video_id");
}

// Check if video exists in database
$video_exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM IONLocalVideos WHERE id = %d", $video_id));
error_log("BADGE API: Video ID $video_id exists check: " . ($video_exists ? 'YES' : 'NO'));

if (!$video_exists) {
    sendJsonError('Video not found', "Video with ID $video_id does not exist in the database. This video may have been imported incorrectly. Please delete and re-import it.");
}

// Get valid badges from database
$valid_badges_query = $wpdb->get_results("SELECT id, name FROM IONBadges ORDER BY name");
$valid_badges = [];
$badge_id_map = [];
foreach ($valid_badges_query as $badge_row) {
    $valid_badges[] = $badge_row->name;
    $badge_id_map[$badge_row->name] = $badge_row->id;
}

if (!empty($badge) && !in_array($badge, $valid_badges)) {
    sendJsonError('Invalid badge type', "Badge: $badge, Valid badges: " . implode(', ', $valid_badges));
}

try {
    switch ($action) {
        case 'add_badge':
            if (empty($badge)) {
                sendJsonError('Badge type required');
            }
            
            $badge_id = $badge_id_map[$badge];
            $user_id = $_SESSION['user_id'];
            
            // Check if badge already exists for this video
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM IONVideoBadges WHERE video_id = %d AND badge_id = %d",
                $video_id, $badge_id
            ));
            
            if ($existing == 0) {
                // Add the badge
                $result = $wpdb->insert(
                    'IONVideoBadges',
                    [
                        'video_id' => $video_id,
                        'badge_id' => $badge_id,
                        'assigned_by' => $user_id
                    ],
                    ['%d', '%d', '%d']
                );
                
                if ($result !== false) {
                    // Get updated badge list
                    $badges = $wpdb->get_results($wpdb->prepare(
                        "SELECT b.name FROM IONBadges b 
                         JOIN IONVideoBadges vb ON b.id = vb.badge_id 
                         WHERE vb.video_id = %d ORDER BY b.name",
                        $video_id
                    ));
                    
                    $badge_names = array_map(function($b) { return $b->name; }, $badges);
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => "Badge '$badge' added successfully",
                        'badges' => implode('|', $badge_names),
                        'badge_list' => $badge_names
                    ]);
                } else {
                    sendJsonError('Failed to add badge');
                }
            } else {
                // Get current badge list
                $badges = $wpdb->get_results($wpdb->prepare(
                    "SELECT b.name FROM IONBadges b 
                     JOIN IONVideoBadges vb ON b.id = vb.badge_id 
                     WHERE vb.video_id = %d ORDER BY b.name",
                    $video_id
                ));
                
                $badge_names = array_map(function($b) { return $b->name; }, $badges);
                
                echo json_encode([
                    'success' => true, 
                    'message' => "Badge '$badge' already exists",
                    'badges' => implode('|', $badge_names),
                    'badge_list' => $badge_names
                ]);
            }
            break;
            
        case 'remove_badge':
            if (empty($badge)) {
                sendJsonError('Badge type required');
            }
            
            $badge_id = $badge_id_map[$badge];
            
            // Remove the badge
            $result = $wpdb->delete(
                'IONVideoBadges',
                [
                    'video_id' => $video_id,
                    'badge_id' => $badge_id
                ],
                ['%d', '%d']
            );
            
            // Get updated badge list
            $badges = $wpdb->get_results($wpdb->prepare(
                "SELECT b.name FROM IONBadges b 
                 JOIN IONVideoBadges vb ON b.id = vb.badge_id 
                 WHERE vb.video_id = %d ORDER BY b.name",
                $video_id
            ));
            
            $badge_names = array_map(function($b) { return $b->name; }, $badges);
            
            if ($result !== false) {
                echo json_encode([
                    'success' => true, 
                    'message' => "Badge '$badge' removed successfully",
                    'badges' => implode('|', $badge_names),
                    'badge_list' => $badge_names
                ]);
            } else {
                echo json_encode([
                    'success' => true, 
                    'message' => "Badge '$badge' was not assigned to this video",
                    'badges' => implode('|', $badge_names),
                    'badge_list' => $badge_names
                ]);
            }
            break;
            
        case 'toggle_badge':
            if (empty($badge)) {
                sendJsonError('Badge type required');
            }
            
            $badge_id = $badge_id_map[$badge];
            $user_id = $_SESSION['user_id'];
            
            // Check if badge exists for this video
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM IONVideoBadges WHERE video_id = %d AND badge_id = %d",
                $video_id, $badge_id
            ));
            
            if ($existing > 0) {
                // Remove badge
                $wpdb->delete(
                    'IONVideoBadges',
                    ['video_id' => $video_id, 'badge_id' => $badge_id],
                    ['%d', '%d']
                );
                $action_taken = 'removed';
            } else {
                // Add badge
                $wpdb->insert(
                    'IONVideoBadges',
                    [
                        'video_id' => $video_id,
                        'badge_id' => $badge_id,
                        'assigned_by' => $user_id
                    ],
                    ['%d', '%d', '%d']
                );
                $action_taken = 'added';
            }
            
            // Get updated badge list
            $badges = $wpdb->get_results($wpdb->prepare(
                "SELECT b.name FROM IONBadges b 
                 JOIN IONVideoBadges vb ON b.id = vb.badge_id 
                 WHERE vb.video_id = %d ORDER BY b.name",
                $video_id
            ));
            
            $badge_names = array_map(function($b) { return $b->name; }, $badges);
            
            echo json_encode([
                'success' => true, 
                'message' => "Badge '$badge' $action_taken successfully",
                'badges' => implode('|', $badge_names),
                'badge_list' => $badge_names,
                'action_taken' => $action_taken
            ]);
            break;
            
        case 'get_badges':
            $badges = $wpdb->get_results($wpdb->prepare(
                "SELECT b.name, b.icon, b.color FROM IONBadges b 
                 JOIN IONVideoBadges vb ON b.id = vb.badge_id 
                 WHERE vb.video_id = %d ORDER BY b.name",
                $video_id
            ));
            
            $badge_names = array_map(function($b) { return $b->name; }, $badges);
            
            echo json_encode([
                'success' => true,
                'badges' => implode('|', $badge_names),
                'badge_array' => $badge_names,
                'badge_details' => $badges
            ]);
            break;
            
        default:
            sendJsonError('Invalid action');
            break;
    }
    
} catch (Exception $e) {
    error_log("Badge Management Error: " . $e->getMessage());
    sendJsonError('An error occurred while processing the request', $e->getMessage());
}
?>