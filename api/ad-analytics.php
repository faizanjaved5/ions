<?php
/**
 * Ad Analytics API Endpoint
 * 
 * Collects and processes ad analytics events from the video players
 */

// Set headers for API response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Include database configuration
require_once '../config/database.php';

try {
    // Get raw input data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Invalid JSON data');
    }
    
    // Validate required fields
    $required = ['event', 'timestamp', 'playerId'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            throw new Exception("Missing required field: {$field}");
        }
    }
    
    // Sanitize and prepare data
    $eventData = [
        'event' => sanitizeString($data['event']),
        'timestamp' => date('Y-m-d H:i:s', intval($data['timestamp']) / 1000),
        'player_id' => sanitizeString($data['playerId']),
        'session_id' => sanitizeString($data['sessionId'] ?? ''),
        'video_id' => sanitizeString($data['videoId'] ?? ''),
        'channel_id' => sanitizeString($data['channelId'] ?? ''),
        'ad_system' => sanitizeString($data['adSystem'] ?? ''),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
        'additional_data' => json_encode($data['data'] ?? [])
    ];
    
    // Insert into database
    $result = insertAnalyticsEvent($eventData);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Event logged successfully']);
    } else {
        throw new Exception('Failed to insert event into database');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
    
    // Log error for debugging
    error_log('[ION Ad Analytics] Error: ' . $e->getMessage() . ' | Data: ' . $input);
}

/**
 * Sanitize string input
 */
function sanitizeString($input, $maxLength = 255) {
    if (!is_string($input)) {
        return '';
    }
    return substr(trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8')), 0, $maxLength);
}

/**
 * Insert analytics event into database
 */
function insertAnalyticsEvent($data) {
    global $wpdb;
    
    // Create table if it doesn't exist
    createAnalyticsTable();
    
    $sql = "INSERT INTO IONAdAnalytics (
        event, 
        timestamp, 
        player_id, 
        session_id, 
        video_id, 
        channel_id, 
        ad_system, 
        user_agent, 
        ip_address, 
        additional_data,
        created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $wpdb->prepare($sql, [
        $data['event'],
        $data['timestamp'],
        $data['player_id'],
        $data['session_id'],
        $data['video_id'],
        $data['channel_id'],
        $data['ad_system'],
        $data['user_agent'],
        $data['ip_address'],
        $data['additional_data']
    ]);
    
    return $wpdb->query($stmt);
}

/**
 * Create analytics table if it doesn't exist
 */
function createAnalyticsTable() {
    global $wpdb;
    
    $tableName = 'IONAdAnalytics';
    
    // Check if table exists
    $tableExists = $wpdb->get_var("SHOW TABLES LIKE '{$tableName}'");
    
    if (!$tableExists) {
        $sql = "CREATE TABLE {$tableName} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            event VARCHAR(100) NOT NULL,
            timestamp DATETIME NOT NULL,
            player_id VARCHAR(255) NOT NULL,
            session_id VARCHAR(255) DEFAULT '',
            video_id VARCHAR(255) DEFAULT '',
            channel_id VARCHAR(255) DEFAULT '',
            ad_system VARCHAR(50) DEFAULT '',
            user_agent TEXT DEFAULT '',
            ip_address VARCHAR(45) DEFAULT '',
            additional_data TEXT DEFAULT '',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_event (event),
            INDEX idx_timestamp (timestamp),
            INDEX idx_video_id (video_id),
            INDEX idx_session_id (session_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $wpdb->query($sql);
        
        // Log table creation
        error_log('[ION Ad Analytics] Created analytics table: ' . $tableName);
    }
}

/**
 * Get analytics summary (for admin interface)
 */
function getAnalyticsSummary($startDate = null, $endDate = null) {
    global $wpdb;
    
    $whereClause = '';
    $params = [];
    
    if ($startDate && $endDate) {
        $whereClause = 'WHERE timestamp BETWEEN ? AND ?';
        $params = [$startDate, $endDate];
    }
    
    $sql = "SELECT 
        event,
        ad_system,
        COUNT(*) as count,
        DATE(timestamp) as date
    FROM IONAdAnalytics 
    {$whereClause}
    GROUP BY event, ad_system, DATE(timestamp)
    ORDER BY timestamp DESC";
    
    if ($params) {
        $stmt = $wpdb->prepare($sql, $params);
        return $wpdb->get_results($stmt);
    } else {
        return $wpdb->get_results($sql);
    }
}

// If this is a GET request for analytics summary (admin use)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['summary'])) {
    // Simple authentication check (enhance as needed)
    session_start();
    if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['Owner', 'Admin'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit();
    }
    
    $startDate = $_GET['start_date'] ?? null;
    $endDate = $_GET['end_date'] ?? null;
    
    $summary = getAnalyticsSummary($startDate, $endDate);
    echo json_encode(['data' => $summary]);
    exit();
}
?>
