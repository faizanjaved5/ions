<?php
/**
 * Video Tracking API Endpoint
 * /api/video-track.php
 * 
 * Receives tracking data from client-side JavaScript
 */

// Allow CORS if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// Load your config
$config = require __DIR__ . '/../../config/config.php';

// Include the tracker class
require_once __DIR__ . '/../../includes/video-tracker.php';

// Set up PDO
try {
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}", 
        $config['username'], 
        $config['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Video tracking PDO connection failed: " . $e->getMessage());
    exit;
}

// Set up Redis (optional)
$redis = null;
if (isset($config['redis_enabled']) && $config['redis_enabled']) {
    try {
        $redis = new Redis();
        $redis->connect($config['redis_host'] ?? '127.0.0.1', $config['redis_port'] ?? 6379);
        if (isset($config['redis_password'])) {
            $redis->auth($config['redis_password']);
        }
    } catch (Exception $e) {
        error_log("Redis connection failed: " . $e->getMessage());
        $redis = null;
    }
}

// Initialize tracker
$tracker = new VideoTracker($pdo, $redis);

// Get JSON data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid data']);
    exit;
}

// Validate and sanitize data
$validatedBatch = [];
foreach ($data as $event) {
    if (isset($event['event']) && isset($event['videoId']) && isset($event['videoType'])) {
        $validatedBatch[] = [
            'event' => in_array($event['event'], ['impression', 'click']) ? $event['event'] : null,
            'videoId' => substr(preg_replace('/[^a-zA-Z0-9_-]/', '', $event['videoId']), 0, 255),
            'videoType' => substr(preg_replace('/[^a-zA-Z0-9_-]/', '', $event['videoType']), 0, 50),
            'pageSlug' => substr(preg_replace('/[^a-zA-Z0-9_-]/', '', $event['pageSlug'] ?? ''), 0, 255),
            'citySlug' => substr(preg_replace('/[^a-zA-Z0-9_-]/', '', $event['citySlug'] ?? ''), 0, 255),
            'sessionId' => substr(preg_replace('/[^a-zA-Z0-9_-]/', '', $event['sessionId'] ?? ''), 0, 100),
            'timestamp' => $event['timestamp'] ?? time()
        ];
    }
}

if (empty($validatedBatch)) {
    http_response_code(400);
    echo json_encode(['error' => 'No valid events']);
    exit;
}

// Process the batch
try {
    $result = $tracker->processBatch($validatedBatch);
    http_response_code(200);
    echo json_encode($result);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Video tracking processing failed: " . $e->getMessage());
    echo json_encode(['error' => 'Processing failed']);
}
?>