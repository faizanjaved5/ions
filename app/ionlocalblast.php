<?php
require_once __DIR__ . '/../login/session.php';

/**
 * ION Multi-Channel Video Distribution Platform
 * 
 * This tool allows administrators to:
 * 1. Search for videos by title, ID, or channel
 * 2. Search for available channels
 * 3. Select multiple channels for video distribution
 * 4. Set publishing schedule and expiration
 * 5. Distribute videos across multiple channels
 */

// CRITICAL: Prevent city system from processing this file (relaxed check)
$script_name = basename($_SERVER['SCRIPT_NAME']);
if (strpos($script_name, 'iondynamic') !== false || strpos($script_name, 'city') !== false) {
    die('ERROR: This file cannot be processed by the city system');
}

// Check if this is being processed by iondynamic.php or city system
if (strpos($_SERVER['REQUEST_URI'], 'iondynamic.php') !== false || 
    strpos($_SERVER['REQUEST_URI'], 'city/') !== false) {
    die('ERROR: This file cannot be processed by the city system. Access directly as /app/ionlocalblast.php');
}

// Debug: Check if this is being processed correctly
if (isset($_GET['debug'])) {
    echo "DEBUG: ionlocalblast.php is being processed correctly<br>";
    echo "REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "<br>";
    echo "SCRIPT_NAME: " . $_SERVER['SCRIPT_NAME'] . "<br>";
    echo "QUERY_STRING: " . $_SERVER['QUERY_STRING'] . "<br>";
    echo "FILE_EXISTS: " . (file_exists(__FILE__) ? 'YES' : 'NO') . "<br>";
    echo "IS_FILE: " . (is_file(__FILE__) ? 'YES' : 'NO') . "<br>";
    echo "CURRENT_FILE: " . __FILE__ . "<br>";
    echo "CURRENT_DIR: " . __DIR__ . "<br>";
    echo "DOCUMENT_ROOT: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
    echo "SCRIPT_FILENAME: " . $_SERVER['SCRIPT_FILENAME'] . "<br>";
    echo "HTTP_HOST: " . $_SERVER['HTTP_HOST'] . "<br>";
    echo "SERVER_NAME: " . $_SERVER['SERVER_NAME'] . "<br>";
    echo "All GET params: " . print_r($_GET, true) . "<br>";
    echo "All POST params: " . print_r($_POST, true) . "<br>";
    exit;
}

// Check if this file is being processed by the city system
if (isset($_GET['slug']) || isset($_GET['city']) || isset($_GET['category']) || isset($_GET['subpath'])) {
    echo "ERROR: This file is being processed by the city system. Please access it directly as /app/ionlocalblast.php<br>";
    echo "Current parameters: " . print_r($_GET, true);
    exit;
}

// Additional check: If we're getting city system output, exit immediately (relaxed check)
if (strpos($_SERVER['REQUEST_URI'], 'iondynamic.php') !== false || 
    strpos($_SERVER['REQUEST_URI'], '/city/') !== false) {
    echo "ERROR: This file cannot be processed by the city system<br>";
    echo "Current URI: " . $_SERVER['REQUEST_URI'];
    exit;
}

// Start output buffering to prevent headers already sent errors
ob_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session already started by session.php

// Prevent this page from being processed by city system
if (isset($_GET['city']) || isset($_GET['category'])) {
    ob_end_clean();
    die('This page cannot be accessed with city parameters');
}

// Include necessary files - use EXACT same pattern as minimal test that works
require_once '../config/database.php';
require_once __DIR__ . '/../search/SearchFactory.php';
require_once __DIR__ . '/../search/SearchMigration.php';
require_once '../includes/ioncategories.php';

// Use EXACT same database setup as minimal test that works
global $db;
$wpdb = $db;

// Simple check - same as minimal test
if (!isset($wpdb) || !$wpdb) {
    die('Database connection not available');
}

// Test the connection using wpdb methods (like other files do)
$test_query = $wpdb->get_var("SELECT 1");
if ($test_query != 1) {
    die('Database connection test failed');
}

// Create PDO connection for functions that need it - use same method as working files
global $pdo;
if (method_exists($wpdb, 'getPDO')) {
    $pdo = $wpdb->getPDO();
} else {
    $pdo = null;
}

// Removed inclusion of city framework to prevent global header/menu and status messages from rendering on this tool page

// If the functions are not available, define them here as a fallback
if (!function_exists('add_video_to_channels')) {
    function add_video_to_channels($video_id, $channels, $category = 'General', $published_at = null, $expires_at = null, $priority = 0) {
        global $wpdb;
        
        try {
            // First, get the numeric video ID from IONLocalVideos table
            $video_sql = "SELECT id FROM IONLocalVideos WHERE video_id = %s OR id = %s LIMIT 1";
            $video_record = $wpdb->get_row($wpdb->prepare($video_sql, $video_id, $video_id));
            
            // Convert object to array for compatibility
            if ($video_record) {
                $video_record = (array) $video_record;
            }
            
            if (!$video_record) {
                throw new Exception("Video not found: $video_id");
            }
            
            $numeric_video_id = $video_record['id'];
            
            $published_at = $published_at ?: date('Y-m-d H:i:s');
            
            foreach ($channels as $channel_slug) {
                // Get the proper channel slug from IONLocalNetwork
                $channel_sql = "SELECT slug FROM IONLocalNetwork WHERE slug = %s OR city_name = %s LIMIT 1";
                $channel_record = $wpdb->get_row($wpdb->prepare($channel_sql, $channel_slug, $channel_slug));
                
                // Convert object to array for compatibility
                if ($channel_record) {
                    $channel_record = (array) $channel_record;
                }
                
                if (!$channel_record) {
                    error_log("Channel not found: $channel_slug");
                    continue; // Skip this channel
                }
                
                $proper_channel_slug = $channel_record['slug'];
                
                // Debug: Log each channel being inserted
                error_log("Inserting video $numeric_video_id to channel $proper_channel_slug with category $category");
                $insert_sql = "
                    INSERT INTO IONLocalBlast (
                        video_id, channel_slug, category, published_at, expires_at,
                        status, priority, added_at
                    ) VALUES (
                        %d, %s, %s, %s, %s,
                        'active', %d, NOW()
                    ) ON DUPLICATE KEY UPDATE
                        published_at = VALUES(published_at),
                        expires_at = VALUES(expires_at),
                        status = 'active',
                        priority = GREATEST(priority, VALUES(priority))
                ";
                
                $wpdb->query($wpdb->prepare($insert_sql,
                    $numeric_video_id,
                    $proper_channel_slug,
                    $category,
                    $published_at,
                    $expires_at,
                    $priority
                ));
            }
            
            $pdo->commit();
            return [
                'success' => true, 
                'message' => 'Video successfully distributed to ' . count($channels) . ' channels',
                'channels' => $channels,
                'category' => $category
            ];
            
        } catch (Exception $e) {
            error_log("Error adding video {$video_id} to channels: " . $e->getMessage());
            return ['success' => false, 'message' => 'Distribution error: ' . $e->getMessage()];
        }
    }
}

// Database connection already tested above with $wpdb

// Debug session information
if (isset($_GET['debug_session'])) {
    echo "DEBUG SESSION INFO:<br>";
    echo "Session ID: " . session_id() . "<br>";
    echo "Session Status: " . session_status() . "<br>";
    echo "Session Data: " . print_r($_SESSION, true) . "<br>";
    echo "User ID: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NOT SET') . "<br>";
    echo "User Role: " . (isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'NOT SET') . "<br>";
    echo "All Session Variables: " . print_r($_SESSION, true) . "<br>";
    exit;
}

// Simple authentication check - same as working files
if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    echo "ERROR: No user_id in session. Please log in first.<br>";
    echo "<a href='../login/'>Go to Login</a>";
    exit;
}

// Get user data the same way as creators.php
$user_email = $_SESSION['user_email'];
$user_data = $wpdb->get_row($wpdb->prepare("SELECT user_id, user_role, email, fullname FROM IONEERS WHERE email = %s", $user_email));

// Convert object to array for compatibility
if ($user_data) {
    $user_data = (array) $user_data;
}

if (!$user_data) {
    ob_end_clean();
    echo "ERROR: User data not found. Please log in again.<br>";
    echo "<a href='../login/'>Go to Login</a>";
    exit;
}

$user_role = $user_data['user_role'] ?? 'Guest';

// Simple role check - same as working files
if (!in_array($user_role, ['Admin', 'Owner'])) {
    ob_end_clean();
    echo "ERROR: Insufficient privileges. Your role: " . $user_role . "<br>";
    echo "Required: Admin or Owner<br>";
    echo "<a href='../login/'>Go to Login</a>";
    exit;
}

// Handle preselected video from external page
$preselected_video_id = $_GET['preselected_video_id'] ?? null;
$preselected_video_title = $_GET['preselected_video_title'] ?? null;
$preselected_video_thumbnail = $_GET['preselected_video_thumbnail'] ?? null;

// Debug: Log what video parameters we received
error_log("IONLOCALBLAST: Received preselected video parameters:");
error_log("- Video ID: " . ($preselected_video_id ?? 'NULL'));
error_log("- Video Title: " . ($preselected_video_title ?? 'NULL'));
error_log("- Video Thumbnail: " . ($preselected_video_thumbnail ?? 'NULL'));
error_log("- Full REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'NULL'));

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Suppress any PHP warnings/notices that might interfere with JSON
    error_reporting(E_ERROR | E_PARSE);
    
    // Clear any previous output that might interfere
    while (ob_get_level()) {
        ob_end_clean();
    }
    ob_start();
    
    // Set proper headers
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    
    $action = $_POST['action'] ?? '';
    
    try {
    switch ($action) {
        case 'search_videos':
            $result = search_videos($_POST['query'] ?? '');
            echo json_encode($result);
            break;
            
        case 'debug_session':
            echo json_encode([
                'success' => true,
                'session_data' => [
                    'user_id' => $_SESSION['user_id'] ?? null,
                    'user_role' => $_SESSION['user_role'] ?? null,
                    'user_email' => $_SESSION['user_email'] ?? null,
                    'session_keys' => array_keys($_SESSION)
                ]
            ]);
            break;
            
        case 'test_video_lookup':
            $test_id = intval($_POST['test_id'] ?? 0);
            $results = [];
            
            // Test direct ID lookup
            $by_id = $wpdb->get_row($wpdb->prepare("SELECT id, video_id, title, status FROM IONLocalVideos WHERE id = %d", $test_id));
            $results['by_id'] = $by_id ? [
                'found' => true,
                'id' => $by_id->id,
                'video_id' => $by_id->video_id,
                'title' => $by_id->title,
                'status' => $by_id->status
            ] : ['found' => false];
            
            // Test video_id lookup if we have the video_id
            if ($by_id && $by_id->video_id) {
                $by_video_id = $wpdb->get_row($wpdb->prepare("SELECT id, video_id, title, status FROM IONLocalVideos WHERE video_id = %s", $by_id->video_id));
                $results['by_video_id'] = $by_video_id ? [
                    'found' => true,
                    'id' => $by_video_id->id,
                    'video_id' => $by_video_id->video_id,
                    'title' => $by_video_id->title,
                    'status' => $by_video_id->status
                ] : ['found' => false];
            }
            
            echo json_encode([
                'success' => true,
                'test_id' => $test_id,
                'results' => $results
            ]);
            break;

        case 'get_permissions':
                // Return only permission info without running a search
                $user_role = $_SESSION['user_role'] ?? null;
                $user_id = $_SESSION['user_id'] ?? null;
                if (!$user_id) {
                    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
                    break;
                }
                $info = [
                    'user_role' => $user_role,
                    'can_see_all' => in_array($user_role, ['Owner', 'Admin'])
                ];
                echo json_encode(['success' => true, 'permission_info' => $info]);
            break;
            
        case 'search_channels':
                $result = search_channels($_POST['query'] ?? '');
                echo json_encode($result);
            break;
            
        case 'distribute_video':
                $result = distribute_video($_POST);
                echo json_encode($result);
            break;
            
        case 'get_video_details':
                $result = get_video_details($_POST['video_id'] ?? '');
                echo json_encode($result);
                break;
                
            case 'search_packages':
                $result = search_packages($_POST['query'] ?? '');
                echo json_encode($result);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }
    exit;
}

/**
 * Search for videos with enhanced security
 */
function search_videos($query) {
    global $wpdb;
    
    if (empty($query)) {
        return ['success' => false, 'message' => 'Search query is required'];
    }
    
    // Debug database connection
    error_log("SEARCH DEBUG: Starting search for query: " . $query);
    error_log("SEARCH DEBUG: wpdb class: " . get_class($wpdb));
    error_log("SEARCH DEBUG: wpdb connected: " . ($wpdb->isConnected() ? 'yes' : 'no'));
    error_log("SEARCH DEBUG: wpdb last_error before query: " . $wpdb->last_error);
    
    // Test simple query first
    $test_query = "SHOW TABLES LIKE 'IONLocalVideos'";
    $table_exists = $wpdb->get_var($test_query);
    error_log("SEARCH DEBUG: Table check result: " . ($table_exists ? 'table exists' : 'table missing'));
    error_log("SEARCH DEBUG: Table check error: " . $wpdb->last_error);
    
    // Get current user info
    $user_id = $_SESSION['user_id'] ?? null;
    $user_role = $_SESSION['user_role'] ?? null;
    
    if (!$user_id) {
        return ['success' => false, 'message' => 'User not authenticated'];
    }
    
    try {
        // Simple and direct approach - try each search method separately
        $videos_objects = null;
        $search_method = '';
        
        // Method 1: Direct ID match (if query is numeric)
        if (is_numeric($query)) {
            error_log("SEARCH: Trying direct ID match for: $query");
            $videos_objects = $wpdb->get_results($wpdb->prepare("
                SELECT v.id, v.video_id, v.title, v.thumbnail, v.channel_title, v.published_at, v.status, v.visibility, v.user_id, u.fullname as owner_name
                FROM IONLocalVideos v
                LEFT JOIN IONEERS u ON v.user_id = u.user_id  
                WHERE v.id = %d
                ORDER BY v.published_at DESC LIMIT 20
            ", intval($query)));
            if ($videos_objects && count($videos_objects) > 0) {
                $search_method = 'direct_id';
                error_log("SEARCH: Found by direct ID - " . count($videos_objects) . " results");
            }
        }
        
        // Method 2: Exact ID/Link matches
        if (!$videos_objects || count($videos_objects) == 0) {
            error_log("SEARCH: Trying exact ID/link matches for: $query");
            $videos_objects = $wpdb->get_results($wpdb->prepare("
                SELECT v.id, v.video_id, v.title, v.thumbnail, v.channel_title, v.published_at, v.status, v.visibility, v.user_id, u.fullname as owner_name
                FROM IONLocalVideos v
                LEFT JOIN IONEERS u ON v.user_id = u.user_id  
                WHERE (v.video_id = %s OR v.video_link = %s OR v.short_link = %s)
                ORDER BY v.published_at DESC LIMIT 20
            ", $query, $query, $query));
            if ($videos_objects && count($videos_objects) > 0) {
                $search_method = 'exact_id_link_match';
                error_log("SEARCH: Found by exact ID/link match - " . count($videos_objects) . " results");
            }
        }
        
        // Method 3: Partial ID search (for partial video IDs or link fragments)
        if (!$videos_objects || count($videos_objects) == 0) {
            error_log("SEARCH: Trying partial ID search for: $query");
            $search_term = '%' . $query . '%';
            $videos_objects = $wpdb->get_results($wpdb->prepare("
                SELECT v.id, v.video_id, v.title, v.thumbnail, v.channel_title, v.published_at, v.status, v.visibility, v.user_id, u.fullname as owner_name
                FROM IONLocalVideos v
                LEFT JOIN IONEERS u ON v.user_id = u.user_id  
                WHERE (v.video_id LIKE %s OR v.video_link LIKE %s OR v.short_link LIKE %s)
                ORDER BY v.published_at DESC LIMIT 20
            ", $search_term, $search_term, $search_term));
            if ($videos_objects && count($videos_objects) > 0) {
                $search_method = 'partial_id_search';
                error_log("SEARCH: Found by partial ID search - " . count($videos_objects) . " results");
            }
        }
        
        // Method 4: Comprehensive text search (case-insensitive)
        if (!$videos_objects || count($videos_objects) == 0) {
            error_log("SEARCH: Trying comprehensive text search for: $query");
            $search_term = '%' . $query . '%';
            $videos_objects = $wpdb->get_results($wpdb->prepare("
                SELECT v.id, v.video_id, v.title, v.thumbnail, v.channel_title, v.published_at, v.status, v.visibility, v.user_id, u.fullname as owner_name
                FROM IONLocalVideos v
                LEFT JOIN IONEERS u ON v.user_id = u.user_id  
                WHERE (
                    LOWER(v.title) LIKE LOWER(%s) OR 
                    LOWER(v.channel_title) LIKE LOWER(%s) OR 
                    LOWER(v.slug) LIKE LOWER(%s) OR
                    LOWER(v.description) LIKE LOWER(%s) OR
                    LOWER(v.tags) LIKE LOWER(%s) OR
                    LOWER(v.transcript) LIKE LOWER(%s)
                )
                ORDER BY v.published_at DESC LIMIT 20
            ", $search_term, $search_term, $search_term, $search_term, $search_term, $search_term));
            if ($videos_objects && count($videos_objects) > 0) {
                $search_method = 'comprehensive_text_search';
                error_log("SEARCH: Found by comprehensive text search - " . count($videos_objects) . " results");
            }
        }
        
        // Apply user role filtering AFTER we get results
        if ($videos_objects && count($videos_objects) > 0) {
            if ($user_role !== 'Owner' && $user_role !== 'Admin') {
                // Filter to only approved videos and user's own videos
                $videos_objects = array_filter($videos_objects, function($video) use ($user_id) {
                    return $video->status === 'Approved' || $video->user_id == $user_id;
                });
                $videos_objects = array_values($videos_objects); // Re-index array
            }
        }
        
        if (!$videos_objects) {
            error_log("SEARCH ERROR: wpdb->get_results() returned null - " . $wpdb->last_error);
            error_log("SEARCH ERROR: Last query was - " . $wpdb->last_query);
            error_log("SEARCH ERROR: wpdb class - " . get_class($wpdb));
            error_log("SEARCH ERROR: wpdb connected - " . ($wpdb->isConnected() ? 'yes' : 'no'));
            return ['success' => false, 'message' => 'Database query failed: ' . $wpdb->last_error];
        }
        
        // Convert objects to arrays for compatibility
        $videos = [];
        if ($videos_objects) {
            foreach ($videos_objects as $video_obj) {
                $videos[] = (array) $video_obj;
            }
        }
        
        // Debug: Log search results
        error_log("Search videos query returned " . count($videos) . " results for query: $query using method: " . $search_method);
        if (count($videos) > 0) {
            error_log("First video result: " . json_encode($videos[0]));
            // Log all video IDs found
            $video_ids = array_column($videos, 'video_id');
            error_log("All video IDs found: " . implode(', ', array_slice($video_ids, 0, 5))); // Show first 5
        }
        
        return [
            'success' => true,
            'videos' => $videos,
            'permission_info' => [
                'user_role' => $user_role,
                'can_see_all' => in_array($user_role, ['Owner', 'Admin'])
            ],
            'debug_info' => [
                'query_searched' => $query,
                'search_method' => $search_method,
                'total_results_found' => count($videos),
                'user_id' => $user_id,
                'user_role' => $user_role,
                'direct_id_check' => is_numeric($query) ? $wpdb->get_var($wpdb->prepare("SELECT id FROM IONLocalVideos WHERE id = %d", intval($query))) : null,
                'video_id_check' => $wpdb->get_var($wpdb->prepare("SELECT id FROM IONLocalVideos WHERE video_id = %s", $query)),
                'table_exists' => $wpdb->get_var("SHOW TABLES LIKE 'IONLocalVideos'"),
                'total_videos_in_table' => $wpdb->get_var("SELECT COUNT(*) FROM IONLocalVideos")
            ]
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Parse zip code query with optional radius
 */
function parseZipCodeQuery($query) {
    if (preg_match('/^(\d{4,6})[,.]\s*(\d+)$/', $query, $matches)) {
        return [
            'zip_code' => $matches[1],
            'radius' => min(intval($matches[2]), 100) // Cap at 100 miles
        ];
    } else {
        return [
            'zip_code' => $query,
            'radius' => 30 // Default 30 miles
        ];
    }
}

/**
 * Search for available channels with role-based security
 */
function search_channels($query) {
    try {
        global $wpdb;
        
        // Enhanced security: Only Admin/Owner roles can search channels
        $user_id = $_SESSION['user_id'] ?? null;
        $user_role = $_SESSION['user_role'] ?? null;
        
        error_log("🔍 CHANNEL SEARCH: Query = '$query', User ID = $user_id, User Role = $user_role");
        
        if (!$user_id) {
            return ['success' => false, 'message' => 'User not authenticated'];
        }
        
        if (empty($query)) {
            return ['success' => false, 'message' => 'Search query is required'];
        }
        
        // Check if search term looks like a zip/postal code
        $is_zip = preg_match('/^\d{4,6}([-,.]\s*\d+)?$/', $query);
        error_log("🔍 CHANNEL SEARCH: Is zip code? " . ($is_zip ? 'YES' : 'NO'));
        
        if ($is_zip) {
            // Zip code search with distance calculation
            $zip_data = parseZipCodeQuery($query);
            $zip_code = $zip_data['zip_code'];
            $radius = $zip_data['radius'];
            
            $coords = get_coordinates_for_zip($zip_code);
            if (!$coords) {
                return ['success' => false, 'message' => "No coordinates found for zip code: {$zip_code}"];
            }
            
            $sql = "SELECT slug, city_name, channel_name, population, state_name, state_code, country_name, country_code, latitude, longitude,
                    (3959 * acos(cos(radians(?)) * cos(radians(latitude)) * 
                    cos(radians(longitude) - radians(?)) + 
                    sin(radians(?)) * sin(radians(latitude)))) AS distance
                    FROM IONLocalNetwork 
                    WHERE slug IS NOT NULL 
                    AND city_name IS NOT NULL 
                    AND latitude IS NOT NULL 
                    AND longitude IS NOT NULL 
                    AND latitude != '' 
                    AND longitude != ''
                    HAVING distance <= ?
                    ORDER BY distance ASC, population DESC
                    LIMIT 50";
            
            $channels_objects = $wpdb->get_results($wpdb->prepare($sql, $coords['lat'], $coords['lng'], $coords['lat'], $radius));
            
            // Convert objects to arrays for compatibility
            $channels = [];
            if ($channels_objects) {
                foreach ($channels_objects as $channel_obj) {
                    $channels[] = (array) $channel_obj;
                }
            }
            
        } else {
            // Text search - include slug and domain search (case-insensitive)
            $search_term = '%' . strtolower($query) . '%';
            
            // First, check if table exists and has data
            $table_check = $wpdb->get_var("SHOW TABLES LIKE 'IONLocalNetwork'");
            if (!$table_check) {
                error_log("🔍 CHANNEL SEARCH ERROR: IONLocalNetwork table does not exist!");
                return ['success' => false, 'message' => 'Channel database table not found. Please contact administrator.'];
            }
            
            $row_count = $wpdb->get_var("SELECT COUNT(*) FROM IONLocalNetwork WHERE slug IS NOT NULL AND city_name IS NOT NULL");
            error_log("🔍 CHANNEL SEARCH: IONLocalNetwork has $row_count valid rows (with slug AND city_name NOT NULL)");
            
            // Debug: Check total rows and NULL conditions
            $total_rows = $wpdb->get_var("SELECT COUNT(*) FROM IONLocalNetwork");
            $null_slug = $wpdb->get_var("SELECT COUNT(*) FROM IONLocalNetwork WHERE slug IS NULL");
            $null_city = $wpdb->get_var("SELECT COUNT(*) FROM IONLocalNetwork WHERE city_name IS NULL");
            error_log("🔍 CHANNEL SEARCH DEBUG: Total rows=$total_rows, NULL slug=$null_slug, NULL city_name=$null_city");
            
            // Try WITHOUT the strict NULL checks first to see if we get any data
            $sql = "SELECT slug, city_name, channel_name, population, state_name, state_code, country_name, country_code, latitude, longitude, custom_domain
                    FROM IONLocalNetwork 
                    WHERE (LOWER(city_name) LIKE ? OR LOWER(state_name) LIKE ? OR LOWER(country_name) LIKE ? OR LOWER(channel_name) LIKE ? OR LOWER(slug) LIKE ? OR LOWER(custom_domain) LIKE ?)
                    ORDER BY population DESC, city_name ASC
                    LIMIT 50";
            
            $channels_objects = $wpdb->get_results($wpdb->prepare($sql, $search_term, $search_term, $search_term, $search_term, $search_term, $search_term));
            
            error_log("🔍 CHANNEL SEARCH: Text search for '$query' returned " . (is_array($channels_objects) ? count($channels_objects) : 'NULL') . " results");
            error_log("🔍 CHANNEL SEARCH: Search term used: '$search_term'");
            error_log("🔍 CHANNEL SEARCH: Last query = " . $wpdb->last_query);
            if ($wpdb->last_error) {
                error_log("🔍 CHANNEL SEARCH ERROR: " . $wpdb->last_error);
            }
            
            // Convert objects to arrays for compatibility
            $channels = [];
            if ($channels_objects) {
                foreach ($channels_objects as $channel_obj) {
                    $channels[] = (array) $channel_obj;
                }
            }
        }
        
        error_log("🔍 CHANNEL SEARCH: Final result = " . count($channels) . " channels");
        
        return [
            'success' => true,
            'channels' => $channels,
            'total' => count($channels),
            'search_type' => $is_zip ? 'zip_code' : 'text'
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Search error: ' . $e->getMessage()];
    }
}

/**
 * Calculate distance between two coordinates using Haversine formula
 */
function calculate_distance($lat1, $lng1, $lat2, $lng2) {
    $earth_radius = 3959; // Earth's radius in miles
    
    $d_lat = deg2rad($lat2 - $lat1);
    $d_lng = deg2rad($lng2 - $lng1);
    
    $a = sin($d_lat/2) * sin($d_lat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($d_lng/2) * sin($d_lng/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return $earth_radius * $c;
}

/**
 * Get coordinates for a zip/postal code using IONGeoCodes table
 */
function get_coordinates_for_zip($zip_code) {
    try {
        global $wpdb;
        
        // Check if IONGeoCodes table exists and has data
        $table_check = $wpdb->get_results("SHOW TABLES LIKE 'IONGeoCodes'");
        if (empty($table_check)) {
            error_log("IONGeoCodes table does not exist");
            return null;
        }
        
        // Query IONGeoCodes table directly
        $sql = "SELECT geo_point, official_usps_city_name, official_state_name, zip_code FROM IONGeoCodes WHERE zip_code = %s";
        $result = $wpdb->get_row($wpdb->prepare($sql, $zip_code));
        
        // Convert object to array for compatibility
        if ($result) {
            $result = (array) $result;
        }
        
        if ($result && !empty($result['geo_point'])) {
            $coords = explode(', ', $result['geo_point']);
            if (count($coords) === 2) {
                return [
                    'lat' => floatval(trim($coords[0])),
                    'lng' => floatval(trim($coords[1]))
                ];
            }
        }
        
        // Fallback: Try to find any zip code with similar prefix
        $prefix = substr($zip_code, 0, 3);
        $fallback_sql = "SELECT geo_point, official_usps_city_name, official_state_name, zip_code FROM IONGeoCodes WHERE zip_code LIKE %s LIMIT 1";
        $fallback_result = $wpdb->get_row($wpdb->prepare($fallback_sql, $prefix . '%'));
        
        // Convert object to array for compatibility
        if ($fallback_result) {
            $fallback_result = (array) $fallback_result;
        }
        
        if ($fallback_result && !empty($fallback_result['geo_point'])) {
            $coords = explode(', ', $fallback_result['geo_point']);
            if (count($coords) === 2) {
                return [
                    'lat' => floatval(trim($coords[0])),
                    'lng' => floatval(trim($coords[1]))
                ];
            }
        }
        
        return null;
        
    } catch (Exception $e) {
        error_log("Error getting coordinates for zip $zip_code: " . $e->getMessage());
        return null;
    }
}

/**
 * Get detailed video information
 */
function get_video_details($video_id) {
    global $wpdb;
    
    try {
        // First try IONLocalVideos table
        $sql = "
            SELECT 
                video_id,
                title,
                thumbnail,
                channel_title,
                description,
                published_at,
                status,
                visibility,
                view_count,
                tags
            FROM IONLocalVideos 
            WHERE video_id = %s
        ";
        $video = $wpdb->get_row($wpdb->prepare($sql, $video_id));
        
        // Convert object to array for compatibility
        if ($video) {
            $video = (array) $video;
        }
        
        // If not found in IONLocalVideos, try the main videos table
        if (!$video) {
            $sql2 = "
                SELECT 
                    id as video_id,
                    title,
                    thumbnail,
                    'ION Network' as channel_title,
                    description,
                    date_added as published_at,
                    status,
                    visibility,
                    0 as view_count,
                    tags
                FROM videos 
                WHERE id = %s
            ";
            $video = $wpdb->get_row($wpdb->prepare($sql2, $video_id));
            
            // Convert object to array for compatibility
            if ($video) {
                $video = (array) $video;
            }
        }
        
        if (!$video) {
            error_log("Video not found in any table for ID: " . $video_id);
            return ['success' => false, 'message' => 'Video not found in distribution database. Please ensure the video is approved and available for distribution.'];
        }
        
        // Ensure required fields have default values
        $video['channel_title'] = $video['channel_title'] ?? 'ION Network';
        $video['description'] = $video['description'] ?? '';
        $video['view_count'] = $video['view_count'] ?? 0;
        $video['tags'] = $video['tags'] ?? '';
        
        return [
            'success' => true,
            'video' => $video
        ];
        
    } catch (Exception $e) {
        error_log("Database error in get_video_details: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Search for channel packages with role-based security
 */
function search_packages($query) {
    global $wpdb;
    
    // Enhanced security: Only Admin/Owner roles can search packages
    $user_id = $_SESSION['user_id'] ?? null;
    $user_role = $_SESSION['user_role'] ?? null;
    
    if (!$user_id) {
        return ['success' => false, 'message' => 'User not authenticated'];
    }
    
    try {
        $sql = "SELECT * FROM IONLocalBundles WHERE status = 'active'";
        $params = [];
        
        if (!empty($query)) {
            $sql .= " AND (bundle_name LIKE %s OR description LIKE %s)";
            $search_term = '%' . $query . '%';
            $packages_objects = $wpdb->get_results($wpdb->prepare($sql, $search_term, $search_term));
            
            // Convert objects to arrays for compatibility
            $packages = [];
            if ($packages_objects) {
                foreach ($packages_objects as $package_obj) {
                    $packages[] = (array) $package_obj;
                }
            }
        } else {
            $packages_objects = $wpdb->get_results($sql);
            
            // Convert objects to arrays for compatibility
            $packages = [];
            if ($packages_objects) {
                foreach ($packages_objects as $package_obj) {
                    $packages[] = (array) $package_obj;
                }
            }
        }
        
        return [
            'success' => true,
            'packages' => $packages
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Distribute video to selected channels
 */
function distribute_video($data) {
    $video_id = $data['video_id'] ?? '';
    $channels = json_decode($data['channels'] ?? '[]', true);
    $packages = json_decode($data['packages'] ?? '[]', true);
    $ott_channels = json_decode($data['ott_channels'] ?? '[]', true);
    $category = $data['category'] ?? 'General';
    $published_at = $data['published_at'] ?? '';
    $expires_at = $data['expires_at'] ?? '';
    $priority = (int)($data['priority'] ?? 0);
    
    if (empty($video_id) || (empty($channels) && empty($packages) && empty($ott_channels))) {
        return ['success' => false, 'message' => 'Video ID and at least one channel, package, or OTT channel are required'];
    }
    
    // Check user permissions
    $user_id = $_SESSION['user_id'] ?? null;
    $user_role = $_SESSION['user_role'] ?? null;
    
    if (!$user_id) {
        return ['success' => false, 'message' => 'User not authenticated'];
    }
    
    // Verify user has permission to distribute this video
    global $wpdb;
    try {
        $sql = "SELECT user_id FROM IONLocalVideos WHERE video_id = %s";
        $video = $wpdb->get_row($wpdb->prepare($sql, $video_id));
        
        // Convert object to array for compatibility
        if ($video) {
            $video = (array) $video;
        }
        
        if (!$video) {
            return ['success' => false, 'message' => 'Video not found'];
        }
        
        // Check if user can distribute this video
        if ($user_role !== 'Owner' && $user_role !== 'Admin' && $video['user_id'] != $user_id) {
            return ['success' => false, 'message' => 'You do not have permission to distribute this video'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
    
    try {
        // Get channels from packages
        $package_channels = [];
        if (!empty($packages)) {
            $package_slugs = implode(',', array_map(function($pkg) { return "'" . addslashes($pkg) . "'"; }, $packages));
            $sql = "SELECT bundle_slug, channels FROM IONLocalBundles WHERE bundle_slug IN ($package_slugs) AND status = 'active'";
            $package_data_objects = $wpdb->get_results($sql);
            
            // Convert objects to arrays for compatibility
            $package_data = [];
            if ($package_data_objects) {
                foreach ($package_data_objects as $package_obj) {
                    $package_data[] = (array) $package_obj;
                }
            }
            
            foreach ($package_data as $pkg) {
                $pkg_channels = json_decode($pkg['channels'] ?? '[]', true);
                $package_channels = array_merge($package_channels, $pkg_channels);
            }
        }
        
        // Combine individual channels, package channels, and OTT channels
        $all_channels = array_unique(array_merge($channels, $package_channels));
        $all_ott_channels = $ott_channels;
        
        if (empty($all_channels) && empty($all_ott_channels)) {
            return ['success' => false, 'message' => 'No valid channels found'];
        }
        
        // Debug: Log the channels being distributed to
        error_log("Distributing video $video_id to channels: " . implode(', ', $all_channels) . " with category: $category");
        if (!empty($all_ott_channels)) {
            error_log("Distributing video $video_id to OTT channels: " . implode(', ', $all_ott_channels) . " with category: $category");
        }
        
        $result = add_video_to_channels(
            $video_id,
            $all_channels,
            $category,
            $published_at ?: null,
            $expires_at ?: null,
            $priority
        );
        
        // Handle OTT channels separately (for now, just log them)
        if (!empty($all_ott_channels)) {
            error_log("OTT channels selected: " . implode(', ', $all_ott_channels));
            // TODO: Implement OTT channel distribution logic
            $result['ott_channels'] = $all_ott_channels;
        }
        
        return $result;
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Distribution error: ' . $e->getMessage()];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ION Multi-Channel Video Distribution Platform</title>
    <link rel="stylesheet" href="ionuploader.css">
    <link rel="stylesheet" href="css/pricing-localblast.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../cart/css/cart-styles.css?v=<?php echo time(); ?>">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f7fa;
            color: #333;
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }
        
        /* ION Header Styles */
        .ion-header {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            border-bottom: 3px solid #f59e0b;
            padding: 0;
            margin: 0;
        }
        
        .ion-header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            padding: 20px;
            gap: 20px;
        }
        
        .ion-header-left {
            text-align: left;
        }
        
        .ion-title {
            color: #f59e0b;
            font-size: 24px;
            font-weight: 700;
            margin: 0;
            letter-spacing: 1px;
        }
        
        .ion-subtitle {
            color: #a0a0a0;
            font-size: 14px;
            margin: 4px 0 0 0;
            font-weight: 400;
        }
        
        .ion-header-center {
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .ion-logo {
            height: 60px;
            width: auto;
        }
        .ion-header-right {
            text-align: right;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Shift main content when cart is open */
        body.cart-open .main-content-wrapper {
            margin-right: 420px;
            transition: margin-right 0.3s ease;
        }
        
        .main-content-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transition: margin-right 0.3s ease;
        }
        
        /* Remove margin approach - using grid columns instead */
        
        /* Modal container styles */
        .modal-container {
            max-width: 2000px;
            margin: 0 auto;
        }
        
        /* Modal Header Layout */
        .modal-header {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            border-bottom: 3px solid #f59e0b;
            padding: 20px;
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            gap: 20px;
        }
        
        .header-left {
            text-align: left;
            flex: 1;
        }
        
        .modal-title-text {
            color: #f59e0b;
            font-size: 24px;
            font-weight: 700;
            margin: 0;
            letter-spacing: 1px;
        }
        
        .header-center {
            display: flex;
            justify-content: center;
            align-items: center;
            flex: 0 0 auto;
        }
        
        .header-right {
            text-align: right;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
            position: relative;
            z-index: 10;
            flex: 1;
        }
        
        /* Cart Button Styles */
        .cart-btn {
            position: relative;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
        }
        
        .cart-btn:hover {
            background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
        }
        
        .cart-btn.has-items {
            animation: cartPulse 2s infinite;
        }
        
        @keyframes cartPulse {
            0%, 100% { box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3); }
            50% { box-shadow: 0 4px 16px rgba(245, 158, 11, 0.6); }
        }
        
        .cart-btn.disabled {
            background: #6b7280;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .cart-btn.disabled:hover {
            background: #6b7280;
            transform: none;
        }
        
        .cart-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 12px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 20px;
        }
        
        .cart-count.hidden {
            display: none;
        }
        
        /* Close Button Styles */
        .close-btn {
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 8px;
            width: 48px;
            height: 48px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
            transition: all 0.2s ease;
        }
        
        .close-btn:hover {
            background: #dc2626;
            transform: translateY(-1px);
        }
        
        /* Cart animations */
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
        
        /* Override container styles for full-screen modal */
        .modal-container .container {
            max-width: none;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        /* Custom scrollbar styling for webkit browsers */
        .container::-webkit-scrollbar {
            width: 8px;
        }

        .container::-webkit-scrollbar-track {
            background: rgba(30, 41, 59, 0.3);
            border-radius: 4px;
        }

        .container::-webkit-scrollbar-thumb {
            background: rgba(178, 130, 84, 0.6);
            border-radius: 4px;
        }

        .container::-webkit-scrollbar-thumb:hover {
            background: rgba(178, 130, 84, 0.8);
        }
        
        .header h1 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 2.5em;
        }
        
        .header p {
            color: #7f8c8d;
            font-size: 1.1em;
        }
        
        .tool-section {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .section-header {
            position: relative;
            margin-bottom: 0px;
        }
        
        .section-title {
            color: #2c3e50;
            margin: 0 0 25px 0;
            font-size: 1.5em;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
            white-space: nowrap;
            width: 100%;
        }
        
        .section-header {
            margin-bottom: 25px;
        }
        
        .section-header h2 {
            color: #2c3e50;
            font-size: 1.5em;
            font-weight: 600;
            margin: 0 0 10px 0;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
            white-space: nowrap;
            width: 100%;
            position: relative;
        }
        
        .section-header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .permission-indicator {
            color: #1565c0;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.85em;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            margin-left: 15px;
            top: 0px;
            right: 0px;
            position: absolute;
        }
        
        .permission-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .permission-badge.owner {
            background: #e8f5e8;
            color: #2d5a2d;
            border: 1px solid #4caf50;
        }
        
        .permission-badge.admin {
            background: #e3f2fd;
            color: #1565c0;
            border: 1px solid #2196f3;
        }
        
        .permission-badge.creator {
            background: #fff3e0;
            color: #e65100;
            border: 1px solid #ff9800;
        }
        
        .permission-badge.member {
            background: #f3e5f5;
            color: #7b1fa2;
            border: 1px solid #9c27b0;
        }
        
        .permission-badge.viewer {
            background: #fafafa;
            color: #616161;
            border: 1px solid #9e9e9e;
        }
        
        .permission-badge.error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #f44336;
        }
        
        /* Initially hide the badge until permissions are loaded */
        #permissionBadge {
            display: none;
        }
        
        .section-title-container {
            width: 100%;
        }
        
        .search-container {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .search-help {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #495057;
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .search-help ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .search-help li {
            margin-bottom: 5px;
            color: #6c757d;
        }
        
        .search-input-wrapper {
            position: relative;
            flex: 1;
        }
        
        .search-loading {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 10;
        }
        
        .spinner {
            width: 16px;
            height: 16px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .btn-loading {
            display: none;
        }
        
        .btn.loading .btn-text {
            display: none;
        }
        
        .btn.loading .btn-loading {
            display: inline;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s ease;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .search-input {
            flex: 1;
            min-width: 310px;
            padding: 12px 16px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: #27ae60;
            color: white;
        }
        
        .btn-success:hover {
            background: #229954;
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }
        
        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .main-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
            background: #ffffff;
            transition: all 0.3s ease;
        }
        
        /* When cart is open, don't change grid - cart is now outside */
        body.cart-open .main-layout {
            /* Grid stays 1fr 1fr - cart is separate now */
        }
        
        /* Adjust column content for better fit when compressed */
        body.cart-open .left-column,
        body.cart-open .right-column {
            min-width: 0; /* Allow columns to shrink below content width */
        }
        
        body.cart-open .results-grid {
            grid-template-columns: 1fr; /* Single column when compressed */
        }
        
        body.cart-open .search-input {
            min-width: 200px; /* Reduce min-width when compressed */
        }
        
        /* Fix column overflow issues */
        .left-column,
        .right-column {
            display: flex;
            flex-direction: column;
            max-height: calc(100vh - 200px);
            overflow: hidden;
        }
        
        .left-column > *:not(.section-header),
        .right-column > *:not(.section-header) {
            overflow-y: auto;
            overflow-x: hidden;
        }
        
        #channelResults,
        #videoResults {
            max-height: calc(100vh - 400px);
            overflow-y: auto;
            overflow-x: hidden;
            padding-right: 8px;
        }
        
        /* Custom scrollbar for channel/video results */
        #channelResults::-webkit-scrollbar,
        #videoResults::-webkit-scrollbar {
            width: 6px;
        }
        
        #channelResults::-webkit-scrollbar-track,
        #videoResults::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.05);
            border-radius: 3px;
        }
        
        #channelResults::-webkit-scrollbar-thumb,
        #videoResults::-webkit-scrollbar-thumb {
            background: rgba(100, 116, 139, 0.4);
            border-radius: 3px;
        }
        
        #channelResults::-webkit-scrollbar-thumb:hover,
        #videoResults::-webkit-scrollbar-thumb:hover {
            background: rgba(100, 116, 139, 0.6);
        }
        
        /* Override main-layout for full-screen modal */
        .modal-container .main-layout {
            margin: 0;
            padding: 20px;
            height: 100%;
            gap: 20px;
            background: #ffffff;
        }
        
        .result-card {
            background: #f8f9fa;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            padding: 20px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .result-card:hover {
            border-color: #3498db;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .result-card.selected {
            border-color: #27ae60;
            background: #e8f5e8;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(39, 174, 96, 0.3);
            padding: 24px;
        }
        
        .result-card.selected.preselected {
            border-color: #f59e0b;
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            box-shadow: 0 6px 20px rgba(245, 158, 11, 0.3);
        }
        
        .result-card {
            position: relative;
        }
        
        .selected-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #27ae60;
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: bold;
            z-index: 1;
        }
        
        .preselected-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: #f59e0b;
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: bold;
            z-index: 1;
        }
        
        .preselected-note {
            background: rgba(245, 158, 11, 0.1);
            border-left: 3px solid #f59e0b;
            padding: 8px 12px;
            margin-top: 8px;
            border-radius: 4px;
            color: #92400e;
            font-size: 0.9em;
        }
        
        .video-description {
            background: rgba(100, 116, 139, 0.1);
            padding: 8px 12px;
            border-radius: 4px;
            margin-top: 8px;
            font-size: 0.9em;
            line-height: 1.5;
        }
        
        .toggle-hint {
            background: rgba(59, 130, 246, 0.1);
            border: 1px dashed rgba(59, 130, 246, 0.3);
            padding: 6px 10px;
            border-radius: 4px;
            text-align: center;
        }
        
        .result-card h3 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 1.2em;
        }
        
        .result-card p {
            color: #7f8c8d;
            margin-bottom: 5px;
            font-size: 0.9em;
        }
        
        /* Channel Card Specific Styles */
        .channel-card {
            position: relative;
            display: flex;
            flex-direction: column;
            max-height: 320px;
            overflow: visible;
        }
        
        .channel-card .card-content {
            flex: 1;
            overflow: hidden;
        }
        
        /* Add to Cart Button for Channels */
        .add-to-cart-channel-btn {
            width: 100%;
            padding: 10px 16px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 12px;
            position: relative;
            z-index: 1;
        }
        
        .add-to-cart-channel-btn:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        
        .channel-card.selected .add-to-cart-channel-btn {
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
            cursor: default;
        }
        
        .channel-card.selected .add-to-cart-channel-btn:hover {
            transform: none;
            box-shadow: none;
        }
        
        /* Quick Pricing Tooltip */
        .quick-pricing-tooltip {
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            color: white;
            padding: 12px 16px;
            border-radius: 8px;
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.4);
            z-index: 1000;
            min-width: 250px;
            margin-bottom: 8px;
            border: 2px solid rgba(178, 130, 84, 0.3);
            animation: tooltipFadeIn 0.2s ease-out;
        }
        
        .quick-pricing-tooltip::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 8px solid transparent;
            border-top-color: #1e293b;
        }
        
        @keyframes tooltipFadeIn {
            from {
                opacity: 0;
                transform: translateX(-50%) translateY(-5px);
            }
            to {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }
        }
        
        .pricing-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 0;
            border-bottom: 1px solid rgba(178, 130, 84, 0.2);
        }
        
        .pricing-row:last-child {
            border-bottom: none;
        }
        
        .pricing-label {
            font-size: 0.9em;
            color: #94a3b8;
        }
        
        .pricing-value {
            font-weight: 600;
            color: #f59e0b;
            font-size: 1em;
        }
        
        .pricing-tier-badge {
            display: inline-block;
            padding: 2px 8px;
            background: rgba(245, 158, 11, 0.2);
            border: 1px solid rgba(245, 158, 11, 0.4);
            border-radius: 4px;
            font-size: 0.75em;
            color: #fbbf24;
            margin-top: 6px;
        }
        
        .result-card .thumbnail {
            width: 100%;
            height: 120px;
            object-fit: cover;
            border-radius: 6px;
            margin-bottom: 15px;
        }
        
        /* Package Card Styles */
        .package-card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 0;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 12px;
            display: flex;
            overflow: hidden;
            min-height: 120px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .package-card:hover {
            border-color: #3498db;
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.2);
        }
        
        .package-card.selected {
            border-color: #27ae60;
            background: #f8fff9;
        }
        
        .package-image {
            width: 100px;
            height: 100px;
            flex-shrink: 0;
            overflow: hidden;
        }
        
        .package-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .package-content {
            flex: 1;
            padding: 12px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 100px;
        }
        
        .package-info {
            flex: 1;
        }
        
        .package-info h4 {
            margin: 0 0 4px 0;
            color: #2c3e50;
            font-size: 1.1em;
            line-height: 1.2;
            font-weight: 600;
        }
        
        .package-description {
            color: #666;
            margin: 0 0 6px 0;
            line-height: 1.2;
            font-size: 0.85em;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .package-meta {
            display: flex;
            gap: 6px;
            margin-bottom: 6px;
            flex-wrap: wrap;
        }
        
        .package-count {
            background: #e3f2fd;
            color: #1565c0;
            padding: 2px 6px;
            border-radius: 8px;
            font-size: 0.75em;
            font-weight: 500;
        }
        
        .package-group {
            background: #f3e5f5;
            color: #7b1fa2;
            padding: 2px 6px;
            border-radius: 8px;
            font-size: 0.75em;
            font-weight: 500;
        }
        
        .package-pricing {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #e9ecef;
            padding-top: 8px;
            margin-top: auto;
        }
        
        .price-selector {
            margin: 0;
        }
        
        .interval-selector {
            padding: 4px 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
            font-size: 0.8em;
            min-width: 80px;
        }
        
        .price-display {
            margin: 0;
            text-align: right;
        }
        
        .price-amount {
            font-size: 1.3em;
            font-weight: bold;
            color: #27ae60;
            line-height: 1;
        }
        
        .price-currency {
            font-size: 0.7em;
            color: #666;
            margin-left: 2px;
            vertical-align: top;
        }
        
        .price-interval {
            font-size: 0.7em;
            color: #666;
            font-style: italic;
            margin-top: 2px;
            display: block;
        }
        
        /* OTT Card Styles */
        .ott-card {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 0;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 15px;
            display: flex;
            overflow: hidden;
            min-height: 200px;
        }
        
        .ott-card:hover {
            border-color: #3498db;
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.2);
        }
        
        .ott-card.selected {
            border-color: #27ae60;
            background: #f8fff9;
        }
        
        .ott-image {
            width: 180px;
            height: 180px;
            flex-shrink: 0;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
        }
        
        .ott-logo {
            max-width: 120px;
            max-height: 120px;
            object-fit: contain;
        }
        
        .ott-content {
            flex: 1;
            padding: 15px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 180px;
        }
        
        .ott-content h3 {
            margin: 0 0 8px 0;
            color: #2c3e50;
            font-size: 1.2em;
            line-height: 1.3;
        }
        
        .ott-platform {
            color: #666;
            margin: 0 0 10px 0;
            font-weight: 500;
        }
        
        .ott-description {
            color: #666;
            margin: 0;
            line-height: 1.3;
            font-size: 0.9em;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        /* Right-Side Cart Panel - Full height sidebar */
        .cart-panel {
            display: none;
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            border-left: 2px solid rgba(178, 130, 84, 0.3);
            box-shadow: -4px 0 24px rgba(0, 0, 0, 0.3);
            transition: transform 0.3s ease;
            flex-direction: column;
            width: 420px;
            height: 100vh;
            overflow: hidden;
            position: fixed;
            right: 0;
            top: 0;
            z-index: 1001;
            transform: translateX(100%);
        }
        
        .cart-panel:not(.hidden) {
            display: flex;
            transform: translateX(0);
        }
        
        @keyframes slideInFromRight {
            from {
                opacity: 0;
                transform: translateX(20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .cart-panel-header {
            padding: 20px 24px;
            border-bottom: 1px solid rgba(178, 130, 84, 0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(178, 130, 84, 0.1);
        }
        
        .cart-panel-header h3 {
            color: #f1f5f9;
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
        }
        
        .cart-close-btn {
            background: none;
            border: none;
            color: #f1f5f9;
            font-size: 28px;
            cursor: pointer;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            transition: background 0.2s;
        }
        
        .cart-close-btn:hover {
            background: rgba(178, 130, 84, 0.2);
        }
        
        .cart-panel-content {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            scrollbar-width: thin;
            scrollbar-color: rgba(178, 130, 84, 0.6) rgba(30, 41, 59, 0.3);
            padding: 20px;
            scrollbar-width: thin;
            scrollbar-color: rgba(178, 130, 84, 0.6) rgba(30, 41, 59, 0.3);
        }
        
        .cart-panel-content::-webkit-scrollbar {
            width: 8px;
        }
        
        .cart-panel-content::-webkit-scrollbar-track {
            background: rgba(30, 41, 59, 0.3);
        }
        
        .cart-panel-content::-webkit-scrollbar-thumb {
            background: rgba(178, 130, 84, 0.6);
            border-radius: 4px;
        }
        
        .cart-video-section {
            margin-bottom: 24px;
            padding-bottom: 24px;
            border-bottom: 1px solid rgba(178, 130, 84, 0.2);
        }
        
        .cart-channels-section {
            /* Channel items will be added here */
        }
        
        .cart-channel-item {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(178, 130, 84, 0.2);
            border-radius: 8px;
            padding: 10px 12px;
            margin-bottom: 8px;
            transition: all 0.2s;
            cursor: pointer;
        }
        
        .cart-channel-item.expanded {
            padding: 16px;
        }
        
        .cart-channel-item:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(178, 130, 84, 0.4);
        }
        
        .cart-channel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
        }
        
        .cart-channel-header.clickable {
            cursor: pointer;
        }
        
        .cart-channel-info {
            flex: 1;
        }
        
        .cart-channel-name {
            color: #f1f5f9;
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .cart-channel-location {
            color: #94a3b8;
            font-size: 0.8rem;
        }
        
        .cart-channel-remove {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .cart-channel-remove:hover {
            background: rgba(239, 68, 68, 0.2);
            border-color: rgba(239, 68, 68, 0.5);
        }
        
        .cart-interval-selector {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
        }
        
        .cart-interval-option {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(178, 130, 84, 0.3);
            border-radius: 6px;
            padding: 12px 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .cart-interval-option:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(178, 130, 84, 0.5);
        }
        
        .cart-interval-option.selected {
            background: rgba(178, 130, 84, 0.2);
            border-color: rgb(178, 130, 84);
            box-shadow: 0 0 0 2px rgba(178, 130, 84, 0.3);
        }
        
        .cart-interval-label {
            color: #f1f5f9;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        
        .cart-interval-price {
            color: #34d399;
            font-size: 0.95rem;
            font-weight: 700;
        }
        
        .cart-interval-option.best-value {
            position: relative;
        }
        
        .cart-interval-option.best-value::before {
            content: 'BEST';
            position: absolute;
            top: -6px;
            right: -6px;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            font-size: 9px;
            padding: 2px 6px;
            border-radius: 8px;
            font-weight: 700;
        }
        
        /* Collapsed channel styles */
        .cart-channel-collapsed {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
        }
        
        .cart-channel-expand-icon {
            color: #94a3b8;
            font-size: 14px;
            transition: transform 0.2s;
            flex-shrink: 0;
        }
        
        .cart-channel-item.expanded .cart-channel-expand-icon {
            transform: rotate(90deg);
        }
        
        .cart-channel-quick-info {
            flex: 1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            min-width: 0;
        }
        
        .cart-channel-title {
            color: #f1f5f9;
            font-size: 0.85rem;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            flex: 1;
        }
        
        .cart-channel-price-badge {
            background: rgba(52, 211, 153, 0.2);
            color: #34d399;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 700;
            white-space: nowrap;
        }
        
        .cart-channel-details {
            display: none;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid rgba(178, 130, 84, 0.2);
        }
        
        .cart-channel-item.expanded .cart-channel-details {
            display: block;
        }
        
        /* Distribution Settings in Cart */
        .cart-distribution-section {
            margin-top: 24px;
            padding-top: 24px;
            border-top: 2px solid rgba(178, 130, 84, 0.3);
        }
        
        .cart-section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            cursor: pointer;
            user-select: none;
        }
        
        .cart-section-title {
            color: #f1f5f9;
            font-size: 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .cart-section-icon {
            color: #94a3b8;
            font-size: 14px;
            transition: transform 0.2s;
        }
        
        .cart-section-header.expanded .cart-section-icon {
            transform: rotate(90deg);
        }
        
        .cart-section-content {
            display: none;
            padding: 12px 0;
        }
        
        .cart-section-header.expanded + .cart-section-content {
            display: block;
        }
        
        .cart-form-group {
            margin-bottom: 16px;
        }
        
        .cart-form-label {
            color: #cbd5e1;
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 6px;
            display: block;
        }
        
        .cart-form-input,
        .cart-form-select {
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(178, 130, 84, 0.3);
            border-radius: 6px;
            padding: 8px 12px;
            color: #f1f5f9;
            font-size: 0.9rem;
        }
        
        .cart-form-input:focus,
        .cart-form-select:focus {
            outline: none;
            border-color: rgb(178, 130, 84);
            box-shadow: 0 0 0 2px rgba(178, 130, 84, 0.2);
        }
        
        .cart-form-select option {
            background: #1e293b;
            color: #f1f5f9;
        }
        
        .cart-panel-footer {
            padding: 20px 24px;
            border-top: 2px solid rgba(178, 130, 84, 0.3);
            background: rgba(0, 0, 0, 0.3);
            display: flex;
            flex-direction: column;
            gap: 12px;
            position: sticky;
            bottom: 0;
            z-index: 10;
            box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.3);
        }
        
        .cart-submit-btn {
            width: 100%;
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3);
        }
        
        .cart-submit-btn:hover {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            box-shadow: 0 6px 16px rgba(34, 197, 94, 0.4);
            transform: translateY(-1px);
        }
        
        .cart-submit-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        .selected-item {
            background: white;
            border: 1px solid #27ae60;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            position: relative;
        }
        
        .selected-item.preselected {
            border: 2px solid #f59e0b;
            background: linear-gradient(135deg, #fef3c7 0%, #fbbf24 5%, #ffffff 15%);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.2);
        }
        
        .selected-video-content {
            display: flex;
            gap: 15px;
            flex: 1;
        }
        
        .selected-video-thumbnail {
            position: relative;
            flex-shrink: 0;
        }
        
        .selected-video-thumbnail img {
            width: 120px;
            height: 80px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #ddd;
        }
        
        .preselected-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #f59e0b;
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 12px;
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .selected-video-info {
            flex: 1;
        }
        
        .selected-video-info h4 {
            margin: 0 0 8px 0;
            color: #2c3e50;
            font-size: 16px;
            line-height: 1.3;
        }
        
        .selected-video-info p {
            margin: 4px 0;
            font-size: 13px;
            color: #555;
            line-height: 1.4;
        }
        
        .video-description {
            font-style: italic;
            color: #666 !important;
        }
        
        .preselected-note {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.3);
            border-radius: 4px;
            padding: 6px 8px;
            margin-top: 8px !important;
            font-size: 12px !important;
            color: #92400e !important;
            font-weight: 500;
        }
        
        .selected-item .remove-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
            flex-shrink: 0;
        }
        
        .selected-item .remove-btn:hover {
            background: #c0392b;
            transform: translateY(-1px);
        }
        
        .preselected-welcome {
            background: linear-gradient(135deg, #fef3c7 0%, #fbbf24 10%, #ffffff 25%);
            border: 2px solid #f59e0b;
            border-radius: 12px;
            padding: 24px;
            text-align: center;
            margin: 20px 0;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.2);
        }
        
        .preselected-welcome h3 {
            color: #92400e;
            margin-bottom: 12px;
            font-size: 18px;
        }
        
        .preselected-welcome p {
            color: #78350f;
            margin: 8px 0;
            font-size: 14px;
        }
        
        .error-message {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 10%, #ffffff 25%);
            border: 2px solid #ef4444;
            border-radius: 12px;
            padding: 24px;
            text-align: center;
            margin: 20px 0;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
        }
        
        .error-message h3 {
            color: #dc2626;
            margin-bottom: 12px;
            font-size: 18px;
        }
        
        .error-message p {
            color: #991b1b;
            margin: 8px 0;
            font-size: 14px;
        }
        
        .error-actions {
            margin-top: 20px;
            display: flex;
            gap: 12px;
            justify-content: center;
        }
        
        .retry-btn, .close-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .retry-btn {
            background: #3b82f6;
            color: white;
        }
        
        .retry-btn:hover {
            background: #2563eb;
            transform: translateY(-1px);
        }
        
        .close-btn {
            background: #6b7280;
            color: white;
        }
        
        .close-btn:hover {
            background: #4b5563;
            transform: translateY(-1px);
            color: var(--text-primary);
        }
        
        .dialog-header .close-btn {
            position: static;
            transform: none;
        }

        .dialog-content label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-weight: 500;
        }


        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
        }
        
        .error {
            background: #fdf2f2;
            border: 2px solid #fecaca;
            color: #dc2626;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .success {
            background: #f0fdf4;
            border: 2px solid #bbf7d0;
            color: #166534;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .hidden {
            display: none;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-approved {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        /* Package Selection Styles */
        .package-section {
            margin-bottom: 30px;
        }
        
        .package-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .package-card {
            background: #f8f9fa;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            position: relative;
        }
        
        .package-card:hover {
            border-color: #3498db;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .package-card.selected {
            border-color: #27ae60;
            background: #e8f5e8;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(39, 174, 96, 0.3);
        }
        
        .package-card.selected::after {
            content: "✓ SELECTED";
            position: absolute;
            top: 10px;
            right: 10px;
            background: #27ae60;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .package-card h4 {
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 1.1em;
        }
        
        .package-card p {
            color: #7f8c8d;
            margin-bottom: 10px;
            font-size: 0.9em;
        }
        
        .package-count {
            display: inline-block;
            background: #3498db;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .package-card.selected .package-count {
            background: #27ae60;
        }
        
        .individual-search {
            border-top: 2px solid #e1e8ed;
            padding-top: 20px;
        }
        
        /* Toggle Styles */
        .toggle-container {
            position: absolute;
            top: 0;
            right: 0;
            margin-bottom: 25px;
        }
        
        .toggle-buttons {
            display: flex;
            background: #f8f9fa;
            border-radius: 6px;
            padding: 0px;
            border: 1px solid #e1e8ed;
        }
        
        .toggle-btn {
            flex: 1;
            padding: 4px 8px;
            border: none;
            background: transparent;
            color: #7f8c8d;
            font-weight: 500;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 11px;
            white-space: nowrap;
        }
        
        .toggle-btn.active {
            background: #3498db;
            color: white;
            box-shadow: 0 1px 3px rgba(52, 152, 219, 0.3);
        }
        
        .toggle-btn:hover:not(.active) {
            background: #e9ecef;
            color: #495057;
        }
        
        /* Tab Content */
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Pagination Styles */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
            padding: 20px 0;
        }
        
        .pagination button {
            padding: 8px 12px;
            border: 2px solid #e1e8ed;
            background: white;
            color: #495057;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .pagination button:hover:not(:disabled) {
            border-color: #3498db;
            color: #3498db;
            transform: translateY(-1px);
        }
        
        .pagination button.active {
            background: #3498db;
            border-color: #3498db;
            color: white;
        }
        
        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .pagination .page-info {
            color: #7f8c8d;
            font-size: 14px;
            margin: 0 10px;
        }
        
        /* Video Results Grid - 4 per row */
        .video-results-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 20px;
        }
        
        /* Package Results Grid */
        .package-results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        @media (max-width: 1400px) {
            body.cart-open .main-layout {
                grid-template-columns: 0.8fr 0.8fr 420px;
                gap: 12px;
            }
        }
        
        @media (max-width: 768px) {
            .main-layout {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            body.cart-open .main-layout {
                grid-template-columns: 1fr;
            }
            
            .cart-panel {
                max-height: 400px;
            }
            
            .section-header {
                position: relative;
            }
            
            .toggle-container {
                position: static;
                margin-left: 0;
                margin-bottom: 15px;
                width: 100%;
            }
            
            .toggle-buttons {
                width: 100%;
            }
            
            .search-container {
                flex-direction: column;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .results-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
    </head>
<body>
    <div class="modal-container" style="width:100%;height:100vh;margin:0;padding:0;border-radius:0;overflow:hidden;display:flex;flex-direction:row;">
        <!-- Main Content Wrapper -->
        <div class="main-content-wrapper">
            <div class="modal-header">
                <div class="header-left">
                    <h1 class="modal-title-text">ION Channel Blast</h1>
                </div>
                <div class="header-center">
                    <div class="ion-logo">
                        <img src="https://ions.com/menu/ion-logo-gold.png" alt="ION Network" class="h-[70px] w-auto" style="height:70px;width:auto">
                    </div>
                </div>
                <div class="header-right">
                    <button class="cart-btn" id="cartBtn" onclick="toggleCartPanel()" title="Distribution Cart">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="9" cy="21" r="1"></circle>
                            <circle cx="20" cy="21" r="1"></circle>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                        </svg>
                        <span class="cart-count" id="cartCount">0</span>
                    </button>
                    <button class="close-btn" id="closeModalBtn" onclick="window.parent.postMessage('close_blast_modal', '*')" title="Close">
                        ×
                    </button>
                </div>          
            </div>
            <div class="container" style="flex:1;margin:0;padding:0;max-width:none;height:100%;min-height:500px;overflow-y:auto;scrollbar-width:thin;scrollbar-color:rgba(178,130,84,0.6) rgba(30,41,59,0.3);">
        <!-- Main Layout: Side by Side -->
        <div class="main-layout">
            <!-- Left Column: Video Search -->
            <div class="left-column">
            <div class="section-header">
                <div class="section-header-content">
                    <h2>1. Search & Select Video</h2>
                    <div class="permission-indicator" id="permissionIndicator">
                        <div class="spinner"></div>
                        <span id="permissionBadge">Loading permissions...</span>
                    </div>
                </div>
            </div>
            <div class="search-container">
                <div class="search-input-wrapper">
                <input type="text" id="videoSearch" class="search-input" placeholder="Search videos by title, ID, or channel...">
                    <div class="search-loading hidden" id="videoSearchLoading">
                        <div class="spinner"></div>
                    </div>
                </div>
                <button onclick="searchVideos()" class="btn btn-primary" id="videoSearchBtn">
                    <span class="btn-text">Search Videos</span>
                    <span class="btn-loading hidden">Searching...</span>
                </button>
            </div>
            <div id="videoResults" class="results-grid"></div>
                <div id="videoPagination" class="pagination hidden"></div>
                
                <!-- Selected Video Display - HIDDEN, info shown in card instead -->
                <div id="selectedVideo" style="display: none;"></div>
        </div>
        
            <!-- Right Column: Channel Selection -->
            <div class="right-column">
                <div class="section-header">
                    <div class="section-title-container">
                        <h2 class="section-title">2. Select Channels</h2>
                    </div>
                    <!-- Toggle between Channels, Packages, and OTT -->
                    <div class="toggle-container">
                        <div class="toggle-buttons">
                            <button id="channelsTab" class="toggle-btn active" onclick="switchTab('channels')">📺 Channels</button>
                            <button id="packagesTab" class="toggle-btn" onclick="switchTab('packages')">📦 Packages</button>
                            <button id="ottTab" class="toggle-btn" onclick="switchTab('ott')">📡 OTT</button>
                        </div>
                    </div>
                </div>
                
                <!-- Channels Tab -->
                <div id="channelsContent" class="tab-content active">
            <div class="search-container">
                        <div class="search-input-wrapper">
                <input type="text" id="channelSearch" class="search-input" placeholder="Search by name, city, slug, or zip code (e.g., 90210, 90210,50)...">
                            <div class="search-loading hidden" id="channelSearchLoading">
                                <div class="spinner"></div>
                            </div>
                        </div>
                        <button onclick="searchChannels()" class="btn btn-primary" id="channelSearchBtn">
                            <span class="btn-text">Search Channels</span>
                            <span class="btn-loading hidden">Searching...</span>
                        </button>
            </div>
            <div class="search-help" id="channelSearchHelp">
                <p><strong>Search Options:</strong></p>
                <ul>
                    <li><strong>Text Search:</strong> Search by channel name, city name, or slug</li>
                    <li><strong>Zip Code Search:</strong> Enter a zip code (e.g., 90210) to find channels within 30 miles</li>
                    <li><strong>Custom Radius:</strong> Use comma or period to specify radius (e.g., 90210,50 for 50 miles)</li>
                </ul>
            </div>
            <div id="channelResults" class="results-grid"></div>
                    <div id="channelPagination" class="pagination hidden"></div>
                </div>
                
                <!-- Packages Tab -->
                <div id="packagesContent" class="tab-content">
                    <div class="search-container">
                        <div class="search-input-wrapper">
                            <input type="text" id="packageSearch" class="search-input" placeholder="Search packages by name or description...">
                            <div class="search-loading hidden" id="packageSearchLoading">
                                <div class="spinner"></div>
                            </div>
                        </div>
                        <button onclick="searchPackages()" class="btn btn-primary" id="packageSearchBtn">
                            <span class="btn-text">Search Packages</span>
                            <span class="btn-loading hidden">Searching...</span>
                        </button>
                    </div>
                    <div id="packageResults" class="results-grid"></div>
                    <div id="packagePagination" class="pagination hidden"></div>
                </div>
                
                <!-- OTT Tab -->
                <div id="ottContent" class="tab-content">
                    <div class="search-container">
                        <div class="search-input-wrapper">
                            <input type="text" id="ottSearch" class="search-input" placeholder="Search OTT channels by name or platform...">
                            <div class="search-loading hidden" id="ottSearchLoading">
                                <div class="spinner"></div>
                            </div>
                        </div>
                        <button onclick="searchOTTChannels()" class="btn btn-primary" id="ottSearchBtn">
                            <span class="btn-text">Search OTT</span>
                            <span class="btn-loading hidden">Searching...</span>
                        </button>
                    </div>
                    <div id="ottResults" class="results-grid"></div>
                    <div id="ottPagination" class="pagination hidden"></div>
                </div>
            </div>
        </div>
        
        <!-- Distribution Settings (kept for backward compatibility) -->
        <div id="distributionSettings" class="tool-section hidden">

        <!-- Distribution Settings -->
        <div id="distributionSettings" class="tool-section hidden">
            <h2 class="section-title">3. Distribution Settings</h2>
            <form id="distributionForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="category">Category</label>
                        <select id="category" name="category" required>
                            <?php echo generate_ion_category_options('News', false); ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="priority">Priority (0-100)</label>
                        <input type="number" id="priority" name="priority" min="0" max="100" value="0">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="published_at">Publish Date & Time</label>
                        <input type="datetime-local" id="published_at" name="published_at">
                    </div>
                    <div class="form-group">
                        <label for="expires_at">Expiration Date & Time (Optional)</label>
                        <input type="datetime-local" id="expires_at" name="expires_at">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-success">🚀 Distribute Video</button>
            </form>
        </div>
        
        <!-- Results -->
        <div id="results"></div>
            </div>
        </div>
        <!-- End Main Content Wrapper -->
        
        <!-- Full Height Cart Panel (Fixed Right Sidebar) -->
        <div id="cartPanel" class="cart-panel hidden">
            <div class="cart-panel-header">
                <h3>📦 Distribution Cart</h3>
                <button class="cart-close-btn" onclick="toggleCartPanel()">×</button>
            </div>
            
            <div class="cart-panel-content">
                <!-- Selected Video -->
                <div id="cartSelectedVideo" class="cart-video-section"></div>
                
                <!-- Selected Channels -->
                <div id="cartSelectedChannels" class="cart-channels-section"></div>
                
                <!-- Distribution Settings -->
                <div class="cart-distribution-section" id="cartDistributionSection" style="display: none;">
                    <div class="cart-section-header expanded" onclick="toggleDistributionSettings()">
                        <div class="cart-section-title">
                            <span class="cart-section-icon">▶</span>
                            ⚙️ Distribution Settings
                        </div>
                    </div>
                    <div class="cart-section-content">
                        <div class="cart-form-group">
                            <label class="cart-form-label" for="cartCategory">Category</label>
                            <select id="cartCategory" class="cart-form-select">
                                <?php echo generate_ion_category_options('News', false); ?>
                            </select>
                        </div>
                        <div class="cart-form-group">
                            <label class="cart-form-label" for="cartPriority">Priority (0-100)</label>
                            <input type="number" id="cartPriority" class="cart-form-input" min="0" max="100" value="0">
                        </div>
                        <div class="cart-form-group">
                            <label class="cart-form-label" for="cartPublishedAt">Publish Date & Time</label>
                            <input type="datetime-local" id="cartPublishedAt" class="cart-form-input">
                        </div>
                        <div class="cart-form-group">
                            <label class="cart-form-label" for="cartExpiresAt">Expiration (Optional)</label>
                            <input type="datetime-local" id="cartExpiresAt" class="cart-form-input">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="cart-panel-footer">
                <button class="btn btn-secondary" style="flex: 1;" onclick="clearAllSelections()">Clear All</button>
                <button class="cart-submit-btn" onclick="distributeFromCart()">🚀 Distribute Video</button>
            </div>
        </div>
        <!-- End Cart Panel -->
    </div>
    <!-- End Modal Container -->

    <script src="../cart/js/cart-manager.js?v=<?php echo time(); ?>"></script>
    <script>
        let selectedVideo = null;
        let selectedChannels = [];
        let selectedPackages = [];
        let selectedOTTChannels = [];
        
        // Handle preselected video from external page
        const preselectedVideoId = <?= json_encode($preselected_video_id) ?>;
        const preselectedVideoTitle = <?= json_encode($preselected_video_title) ?>;
        const preselectedVideoThumbnail = <?= json_encode($preselected_video_thumbnail) ?>;
        
        // Pagination variables
        let currentVideoPage = 1;
        let currentChannelPage = 1;
        let currentPackagePage = 1;
        let currentOTTPage = 1;
        let videosPerPage = 4;
        let channelsPerPage = 6;
        let packagesPerPage = 6;
        let ottPerPage = 6;
        let totalVideos = 0;
        let totalChannels = 0;
        let totalPackages = 0;
        let totalOTTChannels = 0;
        let allVideos = [];
        let allChannels = [];
        let allPackages = [];
        let allOTTChannels = [];
        
        // Set default publish time to now
        document.getElementById('published_at').value = new Date().toISOString().slice(0, 16);
        document.getElementById('cartPublishedAt').value = new Date().toISOString().slice(0, 16);
        
        // OTT Channel definitions
        const ottChannels = [
            { id: 'netflix', name: 'Netflix', platform: 'Netflix', description: 'Global streaming platform' },
            { id: 'hulu', name: 'Hulu', platform: 'Hulu', description: 'American streaming service' },
            { id: 'disney-plus', name: 'Disney+', platform: 'Disney+', description: 'Disney streaming platform' },
            { id: 'amazon-prime', name: 'Amazon Prime Video', platform: 'Amazon Prime', description: 'Amazon streaming service' },
            { id: 'hbo-max', name: 'HBO Max', platform: 'HBO Max', description: 'Warner Bros streaming' },
            { id: 'paramount-plus', name: 'Paramount+', platform: 'Paramount+', description: 'ViacomCBS streaming' },
            { id: 'peacock', name: 'Peacock', platform: 'Peacock', description: 'NBCUniversal streaming' },
            { id: 'apple-tv', name: 'Apple TV+', platform: 'Apple TV+', description: 'Apple streaming service' },
            { id: 'youtube-tv', name: 'YouTube TV', platform: 'YouTube TV', description: 'Google live TV service' },
            { id: 'sling-tv', name: 'Sling TV', platform: 'Sling TV', description: 'Dish Network streaming' },
            { id: 'fubo-tv', name: 'FuboTV', platform: 'FuboTV', description: 'Sports-focused streaming' },
            { id: 'philo', name: 'Philo', platform: 'Philo', description: 'Entertainment streaming' },
            { id: 'crackle', name: 'Crackle', platform: 'Crackle', description: 'Free streaming service' },
            { id: 'tubi', name: 'Tubi', platform: 'Tubi', description: 'Free ad-supported streaming' },
            { id: 'pluto-tv', name: 'Pluto TV', platform: 'Pluto TV', description: 'Free streaming TV' },
            { id: 'roku-channel', name: 'The Roku Channel', platform: 'Roku', description: 'Roku streaming service' },
            { id: 'vudu', name: 'Vudu', platform: 'Vudu', description: 'Walmart streaming service' },
            { id: 'imdb-tv', name: 'IMDb TV', platform: 'IMDb TV', description: 'Amazon free streaming' },
            { id: 'plex', name: 'Plex', platform: 'Plex', description: 'Media server platform' },
            { id: 'kanopy', name: 'Kanopy', platform: 'Kanopy', description: 'Library streaming service' }
        ];
        
        // Channel package definitions
        const channelPackages = {
            'major_cities': [
                'new-york', 'los-angeles', 'chicago', 'houston', 'phoenix', 'philadelphia', 'san-antonio', 'san-diego', 'dallas', 'san-jose',
                'austin', 'jacksonville', 'fort-worth', 'columbus', 'charlotte', 'san-francisco', 'indianapolis', 'seattle', 'denver', 'washington',
                'boston', 'el-paso', 'nashville', 'detroit', 'oklahoma-city'
            ],
            'sports_networks': [
                'sports-nyc', 'sports-la', 'sports-chicago', 'sports-houston', 'sports-phoenix', 'sports-philly', 'sports-dallas', 'sports-san-diego',
                'sports-austin', 'sports-jacksonville', 'sports-columbus', 'sports-charlotte', 'sports-seattle', 'sports-denver', 'sports-boston'
            ],
            'news_networks': [
                'news-nyc', 'news-la', 'news-chicago', 'news-houston', 'news-phoenix', 'news-philly', 'news-dallas', 'news-san-diego',
                'news-austin', 'news-jacksonville', 'news-columbus', 'news-charlotte', 'news-seattle', 'news-denver', 'news-boston',
                'news-miami', 'news-atlanta', 'news-detroit', 'news-minneapolis', 'news-portland'
            ],
            'entertainment': [
                'entertainment-nyc', 'entertainment-la', 'entertainment-chicago', 'entertainment-houston', 'entertainment-phoenix',
                'entertainment-philly', 'entertainment-dallas', 'entertainment-san-diego', 'entertainment-austin', 'entertainment-jacksonville',
                'entertainment-columbus', 'entertainment-charlotte', 'entertainment-seattle', 'entertainment-denver', 'entertainment-boston',
                'entertainment-miami', 'entertainment-atlanta', 'entertainment-nashville'
            ],
            'business': [
                'business-nyc', 'business-la', 'business-chicago', 'business-houston', 'business-phoenix', 'business-philly',
                'business-dallas', 'business-san-diego', 'business-austin', 'business-seattle', 'business-denver', 'business-boston'
            ],
            'all_active': [] // This will be populated dynamically
        };
        
        // Tab switching
        function switchTab(tab) {
            // Update toggle buttons
            document.getElementById('channelsTab').classList.toggle('active', tab === 'channels');
            document.getElementById('packagesTab').classList.toggle('active', tab === 'packages');
            document.getElementById('ottTab').classList.toggle('active', tab === 'ott');
            
            // Update tab content
            document.getElementById('channelsContent').classList.toggle('active', tab === 'channels');
            document.getElementById('packagesContent').classList.toggle('active', tab === 'packages');
            document.getElementById('ottContent').classList.toggle('active', tab === 'ott');
            
            // Load data if switching to respective tabs
            if (tab === 'packages' && allPackages.length === 0) {
                loadPackages();
            }
            if (tab === 'ott' && allOTTChannels.length === 0) {
                loadOTTChannels();
            }
        }
        
        // Search videos
        async function searchVideos() {
            const query = document.getElementById('videoSearch').value.trim();
            if (!query) {
                alert('Please enter a search query');
                return;
            }
            
            // Show loading state
            const searchBtn = document.getElementById('videoSearchBtn');
            const searchLoading = document.getElementById('videoSearchLoading');
            const resultsDiv = document.getElementById('videoResults');
            const paginationDiv = document.getElementById('videoPagination');
            
            searchBtn.classList.add('loading');
            searchLoading.classList.remove('hidden');
            resultsDiv.innerHTML = '<div class="loading">Searching videos...</div>';
            paginationDiv.classList.add('hidden');
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=search_videos&query=${encodeURIComponent(query)}`
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const responseText = await response.text();
                let data;
                
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('JSON parse error:', parseError);
                    console.error('Response was:', responseText);
                    throw new Error('Server returned invalid JSON. Response: ' + responseText.substring(0, 200) + '...');
                }
                
                if (data.success) {
                    allVideos = data.videos;
                    totalVideos = allVideos.length;
                    currentVideoPage = 1;
                    displayVideoResults();
                    
                    // Update permission indicator
                    updatePermissionIndicator(data.permission_info);
                } else {
                    resultsDiv.innerHTML = `<div class="error">Error: ${data.message}</div>`;
                }
            } catch (error) {
                console.error('Search videos error:', error);
                resultsDiv.innerHTML = `<div class="error">Error: ${error.message}</div>`;
            } finally {
                // Hide loading state
                searchBtn.classList.remove('loading');
                searchLoading.classList.add('hidden');
            }
        }
        
        // Update permission indicator
        function updatePermissionIndicator(permissionInfo) {
            const badge = document.getElementById('permissionBadge');
            const indicator = document.getElementById('permissionIndicator');
            
            if (!badge || !permissionInfo) return;
            
            const role = permissionInfo.user_role || 'Unknown';
            const canSeeAll = permissionInfo.can_see_all || false;
            
            // Update badge text and class
            badge.textContent = `${role} ${canSeeAll ? '- All Videos' : '- Own Videos Only'}`;
            badge.className = `permission-badge ${role.toLowerCase()}`;
            
            // Hide spinner and show badge
            if (indicator) {
                const spinner = indicator.querySelector('.spinner');
                if (spinner) {
                    spinner.style.display = 'none';
                }
                badge.style.display = 'inline';
            }
            
            console.log('✅ Permission indicator updated:', role, canSeeAll ? 'All Videos' : 'Own Videos Only');
        }
        
        // Display video results with pagination
        function displayVideoResults() {
            const resultsDiv = document.getElementById('videoResults');
            const paginationDiv = document.getElementById('videoPagination');
            
            if (allVideos.length === 0) {
                resultsDiv.innerHTML = '<div class="error">No videos found matching your search.</div>';
                paginationDiv.classList.add('hidden');
                return;
            }
            
            // Calculate pagination
            const startIndex = (currentVideoPage - 1) * videosPerPage;
            const endIndex = startIndex + videosPerPage;
            const videosToShow = allVideos.slice(startIndex, endIndex);
            const totalPages = Math.ceil(totalVideos / videosPerPage);
            
            // Display videos
            resultsDiv.innerHTML = videosToShow.map(video => {
                const isSelected = selectedVideo && selectedVideo.video_id === video.video_id;
                const isPreselected = preselectedVideoId && video.video_id == preselectedVideoId;
                const ownerInfo = video.owner_name ? `by ${video.owner_name}` : 'Unknown Owner';
                const thumbnail = isPreselected && preselectedVideoThumbnail ? preselectedVideoThumbnail : video.thumbnail;
                
                // Show enhanced details if selected, otherwise basic card
                if (isSelected) {
                    const description = selectedVideo.description ? selectedVideo.description : '';
                    return `
                        <div class="result-card selected ${isPreselected ? 'preselected' : ''}" onclick="selectVideo('${video.video_id}')">
                            ${isSelected ? '<div class="selected-badge">✓ SELECTED</div>' : ''}
                            ${isPreselected ? '<div class="preselected-badge">🎯 Pre-selected</div>' : ''}
                            <img src="${thumbnail}" alt="${video.title}" class="thumbnail" onerror="this.src='https://iblog.bz/assets/ionthumbnail.png'">
                            <h3>${video.title}</h3>
                            <p><strong>Channel:</strong> ${selectedVideo.channel_title || 'ION Network'}</p>
                            <p><strong>Owner:</strong> ${ownerInfo}</p>
                            <p><strong>Published:</strong> ${new Date(video.published_at).toLocaleDateString()}</p>
                            <p><strong>Status:</strong> <span class="status-badge status-${video.status.toLowerCase()}">${video.status}</span></p>
                            ${description ? `<p class="video-description"><strong>Description:</strong> ${description.substring(0, 150)}${description.length > 150 ? '...' : ''}</p>` : ''}
                            <p><strong>Video ID:</strong> ${selectedVideo.video_id}</p>
                            ${isPreselected ? '<p class="preselected-note">📌 This video was pre-selected from the creators page</p>' : ''}
                            <p class="toggle-hint" style="font-size: 0.85em; color: #64748b; margin-top: 8px;">💡 Click card again to deselect</p>
                        </div>
                    `;
                } else {
                    return `
                        <div class="result-card" onclick="selectVideo('${video.video_id}')">
                            <img src="${thumbnail}" alt="${video.title}" class="thumbnail" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjEyMCIgdmlld0JveD0iMCAwIDMwMCAxMjAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIzMDAiIGhlaWdodD0iMTIwIiBmaWxsPSIjRjNGNEY2Ii8+CjxwYXRoIGQ9Ik0xMjAgNDBIMTgwVjgwSDEyMFY0MFoiIGZpbGw9IiM5Q0EzQUYiLz4KPHN2ZyB4PSIxMzAiIHk9IjUwIiB3aWR0aD0iNDAiIGhlaWdodD0iMjAiIHZpZXdCb3g9IjAgMCA0MCAyMCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTE1IDVMMjUgMTBMMTUgMTVWNVoiIGZpbGw9IndoaXRlIi8+Cjwvc3ZnPgo8L3N2Zz4K'">
                            <h3>${video.title}</h3>
                            <p><strong>Channel:</strong> ${video.channel_title}</p>
                            <p><strong>Owner:</strong> ${ownerInfo}</p>
                            <p><strong>Published:</strong> ${new Date(video.published_at).toLocaleDateString()}</p>
                            <p><strong>Status:</strong> <span class="status-badge status-${video.status.toLowerCase()}">${video.status}</span></p>
                        </div>
                    `;
                }
            }).join('');
            
            // Display pagination
            if (totalPages > 1) {
                paginationDiv.classList.remove('hidden');
                paginationDiv.innerHTML = createPagination('video', currentVideoPage, totalPages, totalVideos);
            } else {
                paginationDiv.classList.add('hidden');
            }
        }
        
        // Select video
        async function selectVideo(videoId) {
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=get_video_details&video_id=${encodeURIComponent(videoId)}`
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const responseText = await response.text();
                let data;
                
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('JSON parse error:', parseError);
                    console.error('Response was:', responseText);
                    throw new Error('Server returned invalid JSON. Response: ' + responseText.substring(0, 200) + '...');
                }
                
                if (data.success) {
                    // Toggle selection: if clicking same video, deselect it
                    if (selectedVideo && selectedVideo.video_id === data.video.video_id) {
                        selectedVideo = null;
                        console.log('❌ Video deselected');
                        document.getElementById('distributionSettings').classList.add('hidden');
                    } else {
                        selectedVideo = data.video;
                        console.log('✅ Video selected:', selectedVideo.title);
                        showDistributionSettings();
                    }
                    
                    // Clear any preselected welcome message
                    const welcomeMsg = document.querySelector('.preselected-welcome');
                    if (welcomeMsg) {
                        welcomeMsg.remove();
                    }
                    
                    // Refresh video results to show selection state (with enhanced details)
                    const currentQuery = document.getElementById('videoSearch').value;
                    if (currentQuery) {
                        searchVideos();
                    } else if (allVideos && allVideos.length > 0) {
                        displayVideoResults();
                    }
                    
                    // Scroll to selected video if it exists
                    setTimeout(() => {
                        const selectedCard = document.querySelector('.result-card.selected');
                        if (selectedCard) {
                            selectedCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                    }, 100);
                } else {
                    // Show a more user-friendly error message
                    const resultsDiv = document.getElementById('results');
                    resultsDiv.innerHTML = `
                        <div class="error-message">
                            <h3>❌ Video Not Available for Distribution</h3>
                            <p><strong>Error:</strong> ${data.message}</p>
                            <p>This video may not be approved for distribution yet, or it may not exist in the distribution database.</p>
                            <div class="error-actions">
                                <button onclick="location.reload()" class="retry-btn">🔄 Refresh Page</button>
                                <button onclick="window.parent.postMessage('close_blast_modal', '*')" class="close-btn">❌ Close</button>
                            </div>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Select video error:', error);
                const resultsDiv = document.getElementById('results');
                resultsDiv.innerHTML = `
                    <div class="error-message">
                        <h3>⚠️ Connection Error</h3>
                        <p><strong>Error:</strong> ${error.message}</p>
                        <p>There was a problem connecting to the server. Please try again.</p>
                        <div class="error-actions">
                            <button onclick="location.reload()" class="retry-btn">🔄 Try Again</button>
                            <button onclick="window.parent.postMessage('close_blast_modal', '*')" class="close-btn">❌ Close</button>
                        </div>
                    </div>
                `;
            }
        }
        
        // Display selected video
        function displaySelectedVideo() {
            const selectedDiv = document.getElementById('selectedVideo');
            
            // Determine if this is a preselected video
            const isPreselected = preselectedVideoId && selectedVideo.video_id === preselectedVideoId;
            const thumbnail = isPreselected && preselectedVideoThumbnail ? 
                preselectedVideoThumbnail : 
                (selectedVideo.thumbnail || 'https://iblog.bz/assets/ionthumbnail.png');
            
            selectedDiv.innerHTML = `
                <div class="selected-item ${isPreselected ? 'preselected' : ''}">
                    <div class="selected-video-content">
                        <div class="selected-video-thumbnail">
                            <img src="${thumbnail}" alt="${selectedVideo.title}" 
                                 onerror="this.src='https://iblog.bz/assets/ionthumbnail.png'">
                            ${isPreselected ? '<div class="preselected-badge">🎯 Pre-selected</div>' : ''}
                        </div>
                        <div class="selected-video-info">
                            <h4>${selectedVideo.title}</h4>
                            <p><strong>Channel:</strong> ${selectedVideo.channel_title}</p>
                            <p><strong>Published:</strong> ${new Date(selectedVideo.published_at).toLocaleDateString()}</p>
                            ${selectedVideo.description ? `<p class="video-description"><strong>Description:</strong> ${selectedVideo.description.substring(0, 150)}${selectedVideo.description.length > 150 ? '...' : ''}</p>` : ''}
                            <p><strong>Video ID:</strong> ${selectedVideo.video_id}</p>
                            ${isPreselected ? '<p class="preselected-note">📌 This video was pre-selected from the creators page</p>' : ''}
                        </div>
                    </div>
                    <button class="remove-btn" onclick="removeVideo()" title="Remove this video">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 6L6 18M6 6l12 12"/>
                        </svg>
                        Remove
                    </button>
                </div>
            `;
        }
        
        // Remove video
        function removeVideo() {
            selectedVideo = null;
            document.getElementById('selectedVideo').innerHTML = '';
            document.getElementById('distributionSettings').classList.add('hidden');
        }
        
        // Pagination helper function
        function createPagination(type, currentPage, totalPages, totalItems) {
            let html = '';
            
            // Previous button
            html += `<button onclick="changePage('${type}', ${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>‹ Previous</button>`;
            
            // Page numbers
            const startPage = Math.max(1, currentPage - 2);
            const endPage = Math.min(totalPages, currentPage + 2);
            
            if (startPage > 1) {
                html += `<button onclick="changePage('${type}', 1)">1</button>`;
                if (startPage > 2) {
                    html += `<span class="page-info">...</span>`;
                }
            }
            
            for (let i = startPage; i <= endPage; i++) {
                html += `<button onclick="changePage('${type}', ${i})" class="${i === currentPage ? 'active' : ''}">${i}</button>`;
            }
            
            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    html += `<span class="page-info">...</span>`;
                }
                html += `<button onclick="changePage('${type}', ${totalPages})">${totalPages}</button>`;
            }
            
            // Next button
            html += `<button onclick="changePage('${type}', ${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>Next ›</button>`;
            
            // Page info - improved display format
            const itemsPerPage = type === 'video' ? videosPerPage : type === 'channel' ? channelsPerPage : packagesPerPage;
            const itemType = type === 'video' ? 'video' : type === 'channel' ? 'channel' : 'package';
            const itemTypePlural = itemType + 's';
            
            let pageInfo = '';
            if (totalItems === 0) {
                pageInfo = `Showing 0 ${itemTypePlural}`;
            } else if (totalPages === 1) {
                // Single page - no pagination needed
                if (totalItems === 1) {
                    pageInfo = `Showing 1 of 1 ${itemType}`;
                } else if (totalItems <= 20) {
                    pageInfo = `Showing all ${totalItems} ${itemTypePlural}`;
                } else {
                    pageInfo = `Showing all ${totalItems} ${itemTypePlural}`;
                }
            } else {
                // Multiple pages - use range format
                const startItem = (currentPage - 1) * itemsPerPage + 1;
                const endItem = Math.min(startItem + itemsPerPage - 1, totalItems);
                pageInfo = `Showing ${startItem}-${endItem} of ${totalItems} ${itemTypePlural}`;
            }
            
            html += `<span class="page-info">${pageInfo}</span>`;
            
            return html;
        }
        
        // Change page function
        function changePage(type, page) {
            if (type === 'video') {
                currentVideoPage = page;
                displayVideoResults();
            } else if (type === 'channel') {
                currentChannelPage = page;
                displayChannelResults();
            } else if (type === 'package') {
                currentPackagePage = page;
                displayPackageResults();
            }
        }
        
        // Search channels
        async function searchChannels() {
            const query = document.getElementById('channelSearch').value.trim();
            
            console.log('🔍 Starting channel search for:', query);
            
            const resultsDiv = document.getElementById('channelResults');
            const paginationDiv = document.getElementById('channelPagination');
            resultsDiv.innerHTML = '<div class="loading">Searching channels...</div>';
            paginationDiv.classList.add('hidden');
            
            try {
                const requestBody = `action=search_channels&query=${encodeURIComponent(query)}`;
                console.log('📤 Request body:', requestBody);
                
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: requestBody
                });
                
                console.log('📥 Response status:', response.status, response.statusText);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const responseText = await response.text();
                console.log('🔍 Channel Search Raw Response:', responseText);
                let data;
                
                try {
                    data = JSON.parse(responseText);
                    console.log('🔍 Channel Search Parsed Data:', data);
                } catch (parseError) {
                    console.error('JSON parse error:', parseError);
                    console.error('Response was:', responseText);
                    throw new Error('Server returned invalid JSON. Response: ' + responseText.substring(0, 200) + '...');
                }
                
                if (data.success) {
                    console.log('✅ Search successful, channels found:', data.channels ? data.channels.length : 0);
                    console.log('📊 Channels data:', data.channels);
                    allChannels = data.channels;
                    totalChannels = allChannels.length;
                    currentChannelPage = 1;
                    displayChannelResults();
                } else {
                    console.error('❌ Search failed:', data.message);
                    resultsDiv.innerHTML = `<div class="error">Error: ${data.message}</div>`;
                }
                
                // Hide zip code hint when search completes
                hideZipCodeHint();
            } catch (error) {
                console.error('Search channels error:', error);
                resultsDiv.innerHTML = `<div class="error">Error: ${error.message}</div>`;
                
                // Hide zip code hint on error
                hideZipCodeHint();
            }
        }
        
        // Display channel results with pagination
        function displayChannelResults() {
            const resultsDiv = document.getElementById('channelResults');
            const paginationDiv = document.getElementById('channelPagination');
            const searchHelpDiv = document.getElementById('channelSearchHelp');
            
            // Hide search help section when results are displayed
            if (searchHelpDiv) {
                searchHelpDiv.style.display = 'none';
            }
            
            if (allChannels.length === 0) {
                resultsDiv.innerHTML = '<div class="error">No channels found matching your search.</div>';
                paginationDiv.classList.add('hidden');
                // Show search help again if no results found
                if (searchHelpDiv) {
                    searchHelpDiv.style.display = 'block';
                }
                return;
            }
            
            // Calculate pagination
            const startIndex = (currentChannelPage - 1) * channelsPerPage;
            const endIndex = startIndex + channelsPerPage;
            const channelsToShow = allChannels.slice(startIndex, endIndex);
            const totalPages = Math.ceil(totalChannels / channelsPerPage);
            
            // Display channels
            resultsDiv.innerHTML = channelsToShow.map(channel => {
                const isSelected = selectedChannels.some(ch => ch.slug === channel.slug);
                const location = [];
                if (channel.state_name) location.push(channel.state_name);
                if (channel.country_name) location.push(channel.country_name);
                const locationText = location.length > 0 ? location.join(', ') : 'Location not specified';
                
                // Add distance info if available (for zip code searches)
                const distanceInfo = channel.distance ? `<p><strong>Distance:</strong> ${channel.distance.toFixed(1)} miles</p>` : '';
                const populationInfo = channel.population ? `<p><strong>Population:</strong> ${channel.population.toLocaleString()}</p>` : '';
                
                return `
                    <div class="result-card channel-card ${isSelected ? 'selected' : ''}" 
                         data-channel-slug="${channel.slug}">
                        <div class="card-content">
                            <h3>${channel.channel_name}</h3>
                            <p><strong>City:</strong> ${channel.city_name}</p>
                            <p><strong>Slug:</strong> ${channel.slug}</p>
                            <p><strong>Location:</strong> ${locationText}</p>
                            ${populationInfo}
                            ${distanceInfo}
                        </div>
                        <button class="add-to-cart-channel-btn" 
                                onclick="event.stopPropagation(); addChannelDirectlyToCart('${channel.slug}', '${channel.channel_name}')"
                                onmouseenter="showQuickPricing(this, '${channel.slug}')"
                                onmouseleave="hideQuickPricing(this)">
                            ${isSelected ? '✓ Added' : '🛒 Add to Cart'}
                        </button>
                        <div class="quick-pricing-tooltip" style="display: none;"></div>
                    </div>
                `;
            }).join('');
            
            // Display pagination
            if (totalPages > 1) {
                paginationDiv.classList.remove('hidden');
                paginationDiv.innerHTML = createPagination('channel', currentChannelPage, totalPages, totalChannels);
            } else {
                paginationDiv.classList.add('hidden');
            }
        }
        
        // Load packages from database
        async function loadPackages() {
            const resultsDiv = document.getElementById('packageResults');
            const paginationDiv = document.getElementById('packagePagination');
            resultsDiv.innerHTML = '<div class="loading">Loading packages...</div>';
            paginationDiv.classList.add('hidden');
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=search_packages&query='
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const responseText = await response.text();
                let data;
                
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('JSON parse error:', parseError);
                    console.error('Response was:', responseText);
                    throw new Error('Server returned invalid JSON. Response: ' + responseText.substring(0, 200) + '...');
                }
                
                if (data.success) {
                    allPackages = data.packages;
                    totalPackages = allPackages.length;
                    currentPackagePage = 1;
                    displayPackageResults();
                } else {
                    resultsDiv.innerHTML = `<div class="error">Error: ${data.message}</div>`;
                }
            } catch (error) {
                console.error('Load packages error:', error);
                resultsDiv.innerHTML = `<div class="error">Error: ${error.message}</div>`;
            }
        }
        
        // Search packages
        async function searchPackages() {
            const query = document.getElementById('packageSearch').value.trim();
            
            const resultsDiv = document.getElementById('packageResults');
            const paginationDiv = document.getElementById('packagePagination');
            resultsDiv.innerHTML = '<div class="loading">Searching packages...</div>';
            paginationDiv.classList.add('hidden');
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=search_packages&query=${encodeURIComponent(query)}`
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const responseText = await response.text();
                let data;
                
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('JSON parse error:', parseError);
                    console.error('Response was:', responseText);
                    throw new Error('Server returned invalid JSON. Response: ' + responseText.substring(0, 200) + '...');
                }
                
                if (data.success) {
                    allPackages = data.packages;
                    totalPackages = allPackages.length;
                    currentPackagePage = 1;
                    displayPackageResults();
                } else {
                    resultsDiv.innerHTML = `<div class="error">Error: ${data.message}</div>`;
                }
            } catch (error) {
                console.error('Search packages error:', error);
                resultsDiv.innerHTML = `<div class="error">Error: ${error.message}</div>`;
            }
        }
        
        // Display package results with pagination
        function displayPackageResults() {
            const resultsDiv = document.getElementById('packageResults');
            const paginationDiv = document.getElementById('packagePagination');
            
            if (allPackages.length === 0) {
                resultsDiv.innerHTML = '<div class="error">No packages found matching your search.</div>';
                paginationDiv.classList.add('hidden');
                return;
            }
            
            // Calculate pagination
            const startIndex = (currentPackagePage - 1) * packagesPerPage;
            const endIndex = startIndex + packagesPerPage;
            const packagesToShow = allPackages.slice(startIndex, endIndex);
            const totalPages = Math.ceil(totalPackages / packagesPerPage);
            
            // Display packages
            resultsDiv.innerHTML = packagesToShow.map(pkg => {
                const isSelected = selectedPackages.some(selectedPkg => selectedPkg.name === pkg.bundle_slug);
                const channels = JSON.parse(pkg.channels || '[]');
                
                return `
                    <div class="package-card ${isSelected ? 'selected' : ''}" onclick="selectPackage('${pkg.bundle_slug}')">
                        <div class="package-image">
                            <img src="${pkg.image_url || 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjMwMCIgdmlld0JveD0iMCAwIDMwMCAzMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIzMDAiIGhlaWdodD0iMzAwIiBmaWxsPSIjRjNGNEY2Ii8+CjxwYXRoIGQ9Ik0xMjAgMTIwSDE4MFYxODBIMTIwVjEyMFoiIGZpbGw9IiM5Q0EzQUYiLz4KPHN2ZyB4PSIxMzAiIHk9IjEzMCIgd2lkdGg9IjQwIiBoZWlnaHQ9IjQwIiB2aWV3Qm94PSIwIDAgNDAgNDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxwYXRoIGQ9Ik0xNSA1TDI1IDEwTDE1IDE1VjVaIiBmaWxsPSJ3aGl0ZSIvPgo8L3N2Zz4KPC9zdmc+Cg=='}" 
                                 alt="${pkg.bundle_name}" 
                                 class="package-img"
                                 onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjMwMCIgdmlld0JveD0iMCAwIDMwMCAzMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIzMDAiIGhlaWdodD0iMzAwIiBmaWxsPSIjRjNGNEY2Ii8+CjxwYXRoIGQ9Ik0xMjAgMTIwSDE4MFYxODBIMTIwVjEyMFoiIGZpbGw9IiM5Q0EzQUYiLz4KPHN2ZyB4PSIxMzAiIHk9IjEzMCIgd2lkdGg9IjQwIiBoZWlnaHQ9IjQwIiB2aWV3Qm94PSIwIDAgNDAgNDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxwYXRoIGQ9Ik0xNSA1TDI1IDEwTDE1IDE1VjVaIiBmaWxsPSJ3aGl0ZSIvPgo8L3N2Zz4KPC9zdmc+Cg=='">
                        </div>
                        <div class="package-content">
                            <div class="package-info">
                                <h4>${pkg.bundle_name}</h4>
                                <p class="package-description">${pkg.description || 'No description available'}</p>
                                <div class="package-meta">
                                    <span class="package-count">${pkg.channel_count} channels</span>
                                    <span class="package-group">${pkg.channel_group}</span>
                                </div>
                            </div>
                                                            <div class="package-pricing">
                                    <div class="price-selector">
                                        <select class="interval-selector" onchange="updatePackagePrice('${pkg.bundle_slug}', this.value)" onclick="event.stopPropagation()">
                                            <option value="30">Monthly</option>
                                            <option value="90">Quarterly</option>
                                            <option value="180">Semi-Annual</option>
                                            <option value="365">Annual</option>
                                        </select>
                                    </div>
                                    <div class="price-display">
                                        <div>
                                            <span class="price-amount" id="price-${pkg.bundle_slug}">$${pkg.price}</span>
                                            <span class="price-currency">${pkg.currency}</span>
                                        </div>
                                        <div class="price-interval" id="interval-${pkg.bundle_slug}">Monthly</div>
                                    </div>
                                </div>
                        </div>
                    </div>
                `;
            }).join('');
            
            // Display pagination
            if (totalPages > 1) {
                paginationDiv.classList.remove('hidden');
                paginationDiv.innerHTML = createPagination('package', currentPackagePage, totalPages, totalPackages);
            } else {
                paginationDiv.classList.add('hidden');
            }
        }
        
        // Select/Unselect channel
        function selectChannel(slug, cityName, channelName) {
            // Check if already selected - if so, unselect it
            const existingIndex = selectedChannels.findIndex(ch => ch.slug === slug);
            if (existingIndex !== -1) {
                // Unselect the channel
                selectedChannels.splice(existingIndex, 1);
                displaySelectedChannels();
                showDistributionSettings();
                
                // Refresh channel results to show selection state
                const currentQuery = document.getElementById('channelSearch').value;
                if (currentQuery) {
                    searchChannels();
                }
                return;
            }
            
            // Select the channel
            selectedChannels.push({
                slug: slug,
                city_name: cityName,
                channel_name: channelName
            });
            
            displaySelectedChannels();
            showDistributionSettings();
            
            // Refresh channel results to show selection state
            const currentQuery = document.getElementById('channelSearch').value;
            if (currentQuery) {
                searchChannels();
            }
        }
        
        // Select/Unselect package
        function selectPackage(packageSlug) {
            const packageCard = document.querySelector(`[onclick="selectPackage('${packageSlug}')"]`);
            
            // Find the package data
            const packageData = allPackages.find(pkg => pkg.bundle_slug === packageSlug);
            if (!packageData) {
                console.error('Package not found:', packageSlug);
                return;
            }
            
            // Check if already selected - if so, unselect it
            const existingIndex = selectedPackages.findIndex(pkg => pkg.name === packageSlug);
            if (existingIndex !== -1) {
                // Unselect the package
                selectedPackages.splice(existingIndex, 1);
                packageCard.classList.remove('selected');
                
                // Remove from cart using new cart manager
                if (typeof cartManager !== 'undefined') {
                    cartManager.removeItem(packageSlug);
                } else {
                    // Fallback to old system
                    removeFromCart(packageSlug);
                }
                
                // Remove channels from this package
                const packageChannels = JSON.parse(packageData.channels || '[]');
                selectedChannels = selectedChannels.filter(ch => !packageChannels.includes(ch.slug));
                
                displaySelectedChannels();
                showDistributionSettings();
                return;
            }
            
            // Select the package
            selectedPackages.push({
                name: packageSlug,
                bundle_name: packageData.bundle_name,
                channels: JSON.parse(packageData.channels || '[]')
            });
            packageCard.classList.add('selected');
            
            // Add to cart using new cart manager
            if (typeof cartManager !== 'undefined') {
                cartManager.addItem('package', {
                    id: packageSlug,
                    name: packageData.bundle_name,
                    interval: 'package',
                    price: parseFloat(packageData.price),
                    currency: 'USD'
                });
            } else {
                // Fallback to old system
                addToCart('package', {
                    id: packageSlug,
                    name: packageData.bundle_name,
                    type: 'package',
                    duration: 'package',
                    price: parseFloat(packageData.price)
                });
            }
            
            // Add channels from this package (if not already selected)
            const packageChannels = JSON.parse(packageData.channels || '[]');
            packageChannels.forEach(slug => {
                if (!selectedChannels.some(ch => ch.slug === slug)) {
                    // For now, we'll add with generic names - in a real implementation,
                    // you'd fetch the actual channel names from the database
                    selectedChannels.push({
                        slug: slug,
                        city_name: slug.replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase()),
                        channel_name: slug.replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
                    });
                }
            });
            
            displaySelectedChannels();
            showDistributionSettings();
        }
        
        // Display selected channels in cart panel
        function displaySelectedChannels() {
            const cartPanel = document.getElementById('cartPanel');
            const channelsSection = document.getElementById('cartSelectedChannels');
            const cartCount = document.getElementById('cartCount');
            const cartBtn = document.getElementById('cartBtn');
            
            // Calculate total items
            const totalItems = selectedChannels.length + selectedPackages.length + selectedOTTChannels.length;
            cartCount.textContent = totalItems;
            
            // Update cart button state
            if (totalItems > 0) {
                cartBtn.classList.add('has-items');
                cartPanel.classList.remove('hidden');
                document.body.classList.add('cart-open');
            } else {
                cartBtn.classList.remove('has-items');
                cartPanel.classList.add('hidden');
                document.body.classList.remove('cart-open');
                return;
            }
            
            let html = '';
            
            // Show individual channels with interval selectors (collapsed by default)
            if (selectedChannels.length > 0) {
                const prices = { monthly: '$3.95', quarterly: '$6.95', annual: '$24.95' };
                html += selectedChannels.map((channel, index) => {
                    const interval = channel.interval || 'monthly';
                    const price = prices[interval];
                    const expanded = channel.expanded ? ' expanded' : '';
                    
                    return `
                    <div class="cart-channel-item${expanded}" id="cart-channel-${channel.slug}">
                        <div class="cart-channel-collapsed" onclick="toggleChannelExpand('${channel.slug}')">
                            <span class="cart-channel-expand-icon">▶</span>
                            <div class="cart-channel-quick-info">
                                <div class="cart-channel-title">${channel.channel_name || channel.city_name}</div>
                                <div class="cart-channel-price-badge">${price}/${interval.charAt(0).toUpperCase() + interval.slice(1)}</div>
                            </div>
                        </div>
                        <div class="cart-channel-details">
                            <div class="cart-channel-info">
                                <div class="cart-channel-location">${channel.city_name}, ${channel.state_name || ''}</div>
                            </div>
                            <div style="margin-top: 12px;">
                                <div class="cart-interval-selector">
                                    <div class="cart-interval-option ${interval === 'monthly' ? 'selected' : ''}" 
                                         onclick="event.stopPropagation(); setChannelInterval('${channel.slug}', 'monthly')">
                                        <div class="cart-interval-label">Monthly</div>
                                        <div class="cart-interval-price">$3.95</div>
                                    </div>
                                    <div class="cart-interval-option ${interval === 'quarterly' ? 'selected' : ''}" 
                                         onclick="event.stopPropagation(); setChannelInterval('${channel.slug}', 'quarterly')">
                                        <div class="cart-interval-label">Quarterly</div>
                                        <div class="cart-interval-price">$6.95</div>
                                    </div>
                                    <div class="cart-interval-option best-value ${interval === 'annual' ? 'selected' : ''}" 
                                         onclick="event.stopPropagation(); setChannelInterval('${channel.slug}', 'annual')">
                                        <div class="cart-interval-label">Annual</div>
                                        <div class="cart-interval-price">$24.95</div>
                                    </div>
                                </div>
                            </div>
                            <div style="margin-top: 12px; text-align: right;">
                                <button class="cart-channel-remove" onclick="event.stopPropagation(); removeChannel('${channel.slug}')">Remove</button>
                            </div>
                        </div>
                    </div>
                `;
                }).join('');
            }
            
            channelsSection.innerHTML = html;
            
            // Show distribution settings if video and channels are selected
            const distSection = document.getElementById('cartDistributionSection');
            if (selectedVideo && totalItems > 0) {
                distSection.style.display = 'block';
            } else {
                distSection.style.display = 'none';
            }
        }
        
        // Set interval for a specific channel
        function setChannelInterval(slug, interval) {
            const channel = selectedChannels.find(ch => ch.slug === slug);
            if (channel) {
                channel.interval = interval;
                displaySelectedChannels();
            }
        }
        
        // Toggle channel expand/collapse
        function toggleChannelExpand(slug) {
            const channel = selectedChannels.find(ch => ch.slug === slug);
            if (channel) {
                channel.expanded = !channel.expanded;
                displaySelectedChannels();
            }
        }
        
        // Toggle distribution settings section
        function toggleDistributionSettings() {
            const header = document.querySelector('.cart-section-header');
            if (header) {
                header.classList.toggle('expanded');
            }
        }
        
        // Toggle cart panel
        function toggleCartPanel() {
            const cartPanel = document.getElementById('cartPanel');
            const isHidden = cartPanel.classList.contains('hidden');
            
            cartPanel.classList.toggle('hidden');
            
            // Add/remove cart-open class on body to shrink main content
            if (isHidden) {
                document.body.classList.add('cart-open');
            } else {
                document.body.classList.remove('cart-open');
            }
        }
        
        // Clear all selections
        function clearAllSelections() {
            if (confirm('Remove all selected channels?')) {
                selectedChannels = [];
                selectedPackages = [];
                selectedOTTChannels = [];
                displaySelectedChannels();
            }
        }
        
        // Distribute from cart
        async function distributeFromCart() {
            if (!selectedVideo || (selectedChannels.length === 0 && selectedPackages.length === 0)) {
                alert('Please select a video and at least one channel');
                return;
            }
            
            const category = document.getElementById('cartCategory').value;
            const priority = document.getElementById('cartPriority').value;
            const published_at = document.getElementById('cartPublishedAt').value;
            const expires_at = document.getElementById('cartExpiresAt').value;
            
            const formData = new FormData();
            formData.append('action', 'distribute_video');
            formData.append('video_id', selectedVideo.video_id);
            formData.append('channels', JSON.stringify(selectedChannels.map(ch => ch.slug)));
            formData.append('packages', JSON.stringify(selectedPackages.map(pkg => pkg.name)));
            formData.append('ott_channels', JSON.stringify(selectedOTTChannels.map(ch => ch.id)));
            formData.append('category', category);
            formData.append('priority', priority);
            if (published_at) formData.append('published_at', published_at);
            if (expires_at) formData.append('expires_at', expires_at);
            
            const resultsDiv = document.getElementById('results');
            resultsDiv.innerHTML = '<div class="loading">Distributing video...</div>';
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const responseText = await response.text();
                let data;
                
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('JSON parse error:', parseError);
                    throw new Error('Server returned invalid JSON');
                }
                
                if (data.success) {
                    resultsDiv.innerHTML = `
                        <div class="success">
                            <h3>✅ Video Successfully Distributed!</h3>
                            <p>Video "${selectedVideo.title}" has been distributed to ${selectedChannels.length} channels.</p>
                            ${selectedChannels.length > 0 ? `<p><strong>Channels:</strong> ${selectedChannels.map(ch => ch.channel_name).join(', ')}</p>` : ''}
                        </div>
                    `;
                    
                    // Reset
                    selectedVideo = null;
                    selectedChannels = [];
                    selectedPackages = [];
                    selectedOTTChannels = [];
                    displaySelectedChannels();
                    document.getElementById('selectedVideo').innerHTML = '';
                    
                    // Close cart panel
                    toggleCartPanel();
                } else {
                    resultsDiv.innerHTML = `<div class="error">Error: ${data.message}</div>`;
                }
            } catch (error) {
                console.error('Distribution error:', error);
                resultsDiv.innerHTML = `<div class="error">Error: ${error.message}</div>`;
            }
        }
        
        // Remove channel
        function removeChannel(slug) {
            selectedChannels = selectedChannels.filter(ch => ch.slug !== slug);
            displaySelectedChannels();
        }
        
        // Remove package
        function removePackage(packageSlug) {
            // Find the package data
            const packageData = allPackages.find(pkg => pkg.bundle_slug === packageSlug);
            
            // Remove from selected packages
            selectedPackages = selectedPackages.filter(pkg => pkg.name !== packageSlug);
            
            // Remove package card selection
            const packageCard = document.querySelector(`[onclick="selectPackage('${packageSlug}')"]`);
            if (packageCard) {
                packageCard.classList.remove('selected');
            }
            
            // Remove from cart using new cart manager
            if (typeof cartManager !== 'undefined') {
                cartManager.removeItem(packageSlug);
            } else {
                // Fallback to old system
                removeFromCart(packageSlug);
            }
            
            // Remove channels from this package
            if (packageData) {
                const packageChannels = JSON.parse(packageData.channels || '[]');
                selectedChannels = selectedChannels.filter(ch => !packageChannels.includes(ch.slug));
            }
            
            displaySelectedChannels();
        }
        
        // Remove OTT channel
        function removeOTTChannel(channelId) {
            selectedOTTChannels = selectedOTTChannels.filter(ch => ch.id !== channelId);
            displaySelectedChannels();
        }
        
        // Show distribution settings
        function showDistributionSettings() {
            // Distribution settings will be shown when user clicks "Continue" in cart panel
            // No need to auto-show it anymore
        }
        
        // Handle form submission
        document.getElementById('distributionForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            if (!selectedVideo || (selectedChannels.length === 0 && selectedPackages.length === 0)) {
                alert('Please select a video and at least one channel or package');
                return;
            }
            
            const formData = new FormData(this);
            formData.append('action', 'distribute_video');
            formData.append('video_id', selectedVideo.video_id);
            formData.append('channels', JSON.stringify(selectedChannels.map(ch => ch.slug)));
            formData.append('packages', JSON.stringify(selectedPackages.map(pkg => pkg.name)));
            formData.append('ott_channels', JSON.stringify(selectedOTTChannels.map(ch => ch.id)));
            
            const resultsDiv = document.getElementById('results');
            resultsDiv.innerHTML = '<div class="loading">Distributing video...</div>';
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                // Check if response is ok
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                // Get response text first to debug
                const responseText = await response.text();
                console.log('Response text:', responseText);
                
                // Try to parse as JSON
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('JSON parse error:', parseError);
                    console.error('Response was:', responseText);
                    throw new Error('Server returned invalid JSON. Response: ' + responseText.substring(0, 200) + '...');
                }
                
                if (data.success) {
                    let totalChannels = selectedChannels.length;
                    let packageInfo = '';
                    
                    if (selectedPackages.length > 0) {
                        packageInfo = `<p><strong>Packages:</strong> ${selectedPackages.map(pkg => pkg.name.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())).join(', ')}</p>`;
                    }
                    
                    resultsDiv.innerHTML = `
                        <div class="success">
                            <h3>✅ Video Successfully Distributed!</h3>
                            <p>Video "${selectedVideo.title}" has been distributed to ${totalChannels} channels.</p>
                            ${packageInfo}
                            <p><strong>Individual Channels:</strong> ${selectedChannels.map(ch => ch.channel_name).join(', ')}</p>
                            ${selectedOTTChannels.length > 0 ? `<p><strong>OTT Channels:</strong> ${selectedOTTChannels.map(ch => ch.name).join(', ')}</p>` : ''}
                        </div>
                    `;
                    
                    // Reset form
                    selectedVideo = null;
                    selectedChannels = [];
                    selectedPackages = [];
                    selectedOTTChannels = [];
                    
                    // Reset package card selections
                    document.querySelectorAll('.package-card').forEach(card => {
                        card.classList.remove('selected');
                    });
                    
                    document.getElementById('distributionSettings').classList.add('hidden');
                    document.getElementById('videoResults').innerHTML = '';
                    document.getElementById('channelResults').innerHTML = '';
                    document.getElementById('selectedVideo').innerHTML = '';
                    document.getElementById('videoSearch').value = '';
                    document.getElementById('channelSearch').value = '';
                } else {
                    resultsDiv.innerHTML = `<div class="error">Error: ${data.message}</div>`;
                }
            } catch (error) {
                console.error('Distribution error:', error);
                resultsDiv.innerHTML = `<div class="error">Error: ${error.message}</div>`;
            }
        });
        
        // Enter key support for search
        document.getElementById('videoSearch').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchVideos();
            }
        });
        
        // Smart debouncing for channel search
        let channelSearchTimeout;
        
        // Add smart input event listener for channel search
        document.getElementById('channelSearch').addEventListener('input', function() {
            const query = this.value.trim();
            
            // Clear any existing timeout
            clearTimeout(channelSearchTimeout);
            
            // Smart debouncing logic:
            // - For zip codes: Only search when exactly 5 digits are entered
            // - For text: Search with 1+ characters after 300ms delay
            if (isZipCode(query)) {
                if (query.length === 5) {
                    // Zip code is complete (5 digits) - search immediately
                    showZipCodeHint('Searching...', 'info');
                    searchChannels();
                } else if (query.length > 0) {
                    // Incomplete zip code - show hint
                    showZipCodeHint(`Enter ${5 - query.length} more digit${5 - query.length === 1 ? '' : 's'} to search`, 'waiting');
                } else {
                    hideZipCodeHint();
                }
            } else if (query.length >= 1) {
                // Text search - wait 300ms after user stops typing
                hideZipCodeHint();
                channelSearchTimeout = setTimeout(() => {
                    searchChannels();
                }, 300);
            } else {
                // Empty query - show search help and clear results
                hideZipCodeHint();
                const searchHelpDiv = document.getElementById('channelSearchHelp');
                const resultsDiv = document.getElementById('channelResults');
                const paginationDiv = document.getElementById('channelPagination');
                
                if (searchHelpDiv) {
                    searchHelpDiv.style.display = 'block';
                }
                if (resultsDiv) {
                    resultsDiv.innerHTML = '';
                }
                if (paginationDiv) {
                    paginationDiv.classList.add('hidden');
                }
            }
        });
        
        // Handle Enter key with smart logic
        document.getElementById('channelSearch').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(channelSearchTimeout);
                const query = this.value.trim();
                
                // Smart Enter key logic:
                // - For zip codes: Only search if 5 digits
                // - For text: Search if 1+ characters
                if (isZipCode(query)) {
                    if (query.length === 5) {
                        searchChannels();
                    }
                    // For incomplete zip codes, don't search on Enter
                } else if (query.length >= 1) {
                    searchChannels();
                } else {
                    // Empty query - show search help and clear results
                    const searchHelpDiv = document.getElementById('channelSearchHelp');
                    const resultsDiv = document.getElementById('channelResults');
                    const paginationDiv = document.getElementById('channelPagination');
                    
                    if (searchHelpDiv) {
                        searchHelpDiv.style.display = 'block';
                    }
                    if (resultsDiv) {
                        resultsDiv.innerHTML = '';
                    }
                    if (paginationDiv) {
                        paginationDiv.classList.add('hidden');
                    }
                }
            }
        });
        
        // Helper function to detect if input is a zip code
        function isZipCode(input) {
            // Check if input contains only digits (1-5 digits)
            return /^\d{1,5}$/.test(input);
        }
        
        // Helper functions for zip code hints
        function showZipCodeHint(message, type = 'info') {
            hideZipCodeHint(); // Remove any existing hint
            
            const hint = document.createElement('div');
            hint.id = 'zip-code-hint';
            hint.className = `zip-code-hint ${type}`;
            hint.textContent = message;
            
            // Style the hint
            hint.style.cssText = `
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: ${type === 'waiting' ? '#ffa500' : '#007bff'};
                color: white;
                padding: 8px 12px;
                border-radius: 4px;
                font-size: 14px;
                z-index: 1000;
                margin-top: 4px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            `;
            
            // Insert after the search input
            const searchInput = document.getElementById('channelSearch');
            if (searchInput) {
                searchInput.parentNode.insertBefore(hint, searchInput.nextSibling);
            }
        }
        
        function hideZipCodeHint() {
            const existingHint = document.getElementById('zip-code-hint');
            if (existingHint) {
                existingHint.remove();
            }
        }
        
        // Load OTT channels
        function loadOTTChannels() {
            allOTTChannels = [...ottChannels];
            totalOTTChannels = allOTTChannels.length;
            currentOTTPage = 1;
            displayOTTResults();
        }
        
        // Search OTT channels
        function searchOTTChannels() {
            const query = document.getElementById('ottSearch').value.trim().toLowerCase();
            
            if (query === '') {
                allOTTChannels = [...ottChannels];
            } else {
                allOTTChannels = ottChannels.filter(channel => 
                    channel.name.toLowerCase().includes(query) || 
                    channel.platform.toLowerCase().includes(query) ||
                    channel.description.toLowerCase().includes(query)
                );
            }
            
            totalOTTChannels = allOTTChannels.length;
            currentOTTPage = 1;
            displayOTTResults();
        }
        
        // Display OTT results with pagination
        function displayOTTResults() {
            const resultsDiv = document.getElementById('ottResults');
            const paginationDiv = document.getElementById('ottPagination');
            
            if (allOTTChannels.length === 0) {
                resultsDiv.innerHTML = '<div class="error">No OTT channels found matching your search.</div>';
                paginationDiv.classList.add('hidden');
                return;
            }
            
            // Calculate pagination
            const startIndex = (currentOTTPage - 1) * ottPerPage;
            const endIndex = startIndex + ottPerPage;
            const ottToShow = allOTTChannels.slice(startIndex, endIndex);
            const totalPages = Math.ceil(totalOTTChannels / ottPerPage);
            
            // Display OTT channels
            resultsDiv.innerHTML = ottToShow.map(channel => {
                const isSelected = selectedOTTChannels.some(selected => selected.id === channel.id);
                const platformLogo = getOTTPlatformLogo(channel.platform);
                
                return `
                    <div class="ott-card ${isSelected ? 'selected' : ''}" onclick="selectOTTChannel('${channel.id}', '${channel.name}', '${channel.platform}')">
                        <div class="ott-image">
                            <img src="${platformLogo}" 
                                 alt="${channel.platform} Logo" 
                                 class="ott-logo"
                                 onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjMwMCIgdmlld0JveD0iMCAwIDMwMCAzMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIzMDAiIGhlaWdodD0iMzAwIiBmaWxsPSIjRjNGNEY2Ii8+CjxwYXRoIGQ9Ik0xMjAgMTIwSDE4MFYxODBIMTIwVjEyMFoiIGZpbGw9IiM5Q0EzQUYiLz4KPHN2ZyB4PSIxMzAiIHk9IjEzMCIgd2lkdGg9IjQwIiBoZWlnaHQ9IjQwIiB2aWV3Qm94PSIwIDAgNDAgNDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxwYXRoIGQ9Ik0xNSA1TDI1IDEwTDE1IDE1VjVaIiBmaWxsPSJ3aGl0ZSIvPgo8L3N2Zz4KPC9zdmc+Cg=='">
                        </div>
                        <div class="ott-content">
                            <h3>${channel.name}</h3>
                            <p class="ott-platform"><strong>Platform:</strong> ${channel.platform}</p>
                            <p class="ott-description">${channel.description}</p>
                        </div>
                    </div>
                `;
            }).join('');
            
            // Display pagination
            if (totalPages > 1) {
                paginationDiv.classList.remove('hidden');
                paginationDiv.innerHTML = createPagination('ott', currentOTTPage, totalPages, totalOTTChannels);
            } else {
                paginationDiv.classList.add('hidden');
            }
        }
        
        // Select OTT channel
        function selectOTTChannel(id, name, platform) {
            // Check if already selected - if so, unselect it
            const existingIndex = selectedOTTChannels.findIndex(ch => ch.id === id);
            if (existingIndex !== -1) {
                // Unselect the channel
                selectedOTTChannels.splice(existingIndex, 1);
                displaySelectedChannels();
                showDistributionSettings();
                
                // Refresh OTT results to show selection state
                const currentQuery = document.getElementById('ottSearch').value;
                if (currentQuery) {
                    searchOTTChannels();
                }
                return;
            }
            
            // Add to selected channels
            selectedOTTChannels.push({ id, name, platform });
            displaySelectedChannels();
            showDistributionSettings();
            
            // Refresh OTT results to show selection state
            const currentQuery = document.getElementById('ottSearch').value;
            if (currentQuery) {
                searchOTTChannels();
            }
        }
        
        // Update package price based on selected interval
        function updatePackagePrice(bundleSlug, interval) {
            const package = allPackages.find(pkg => pkg.bundle_slug === bundleSlug);
            if (!package) return;
            
            let price, intervalText;
            switch(interval) {
                case '30':
                    price = package.price;
                    intervalText = 'Monthly';
                    break;
                case '90':
                    price = package.price_90;
                    intervalText = 'Quarterly';
                    break;
                case '180':
                    price = package.price_180;
                    intervalText = 'Semi-Annual';
                    break;
                case '365':
                    price = package.price_365;
                    intervalText = 'Annual';
                    break;
                default:
                    price = package.price;
                    intervalText = 'Monthly';
            }
            
            // Update the price display
            const priceElement = document.getElementById(`price-${bundleSlug}`);
            const intervalElement = document.getElementById(`interval-${bundleSlug}`);
            
            if (priceElement) {
                priceElement.textContent = `$${price}`;
            }
            if (intervalElement) {
                intervalElement.textContent = intervalText;
            }
        }
        
        // Get OTT platform logo URL
        function getOTTPlatformLogo(platform) {
            const logos = {
                'Netflix': 'https://upload.wikimedia.org/wikipedia/commons/0/08/Netflix_2015_logo.svg',
                'Amazon Prime Video': 'https://upload.wikimedia.org/wikipedia/commons/f/f1/Prime_Video.png',
                'Disney+': 'https://upload.wikimedia.org/wikipedia/commons/7/77/Disney_Plus_logo.svg',
                'Hulu': 'https://upload.wikimedia.org/wikipedia/commons/e/e4/Hulu_Plus.svg',
                'HBO Max': 'https://upload.wikimedia.org/wikipedia/commons/1/17/HBO_Max_Logo.svg',
                'Apple TV+': 'https://upload.wikimedia.org/wikipedia/commons/4/4e/Apple_TV_Plus_logo.svg',
                'Paramount+': 'https://upload.wikimedia.org/wikipedia/commons/8/8a/Paramount_Plus_logo.svg',
                'Peacock': 'https://upload.wikimedia.org/wikipedia/commons/4/4e/Peacock_logo.svg',
                'YouTube TV': 'https://upload.wikimedia.org/wikipedia/commons/9/98/YouTube_TV_logo.svg',
                'Sling TV': 'https://upload.wikimedia.org/wikipedia/commons/7/7a/Sling_TV_logo.svg',
                'FuboTV': 'https://upload.wikimedia.org/wikipedia/commons/8/8a/FuboTV_logo.svg',
                'Roku': 'https://upload.wikimedia.org/wikipedia/commons/4/4e/Roku_logo.svg',
                'Fire TV': 'https://upload.wikimedia.org/wikipedia/commons/4/4e/Amazon_Fire_TV_logo.svg',
                'Chromecast': 'https://upload.wikimedia.org/wikipedia/commons/4/4e/Google_Chromecast_logo.svg',
                'Smart TV': 'https://upload.wikimedia.org/wikipedia/commons/4/4e/Smart_TV_logo.svg'
            };
            
            return logos[platform] || 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjMwMCIgdmlld0JveD0iMCAwIDMwMCAzMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIzMDAiIGhlaWdodD0iMzAwIiBmaWxsPSIjRjNGNEY2Ii8+CjxwYXRoIGQ9Ik0xMjAgMTIwSDE4MFYxODBIMTIwVjEyMFoiIGZpbGw9IiM5Q0EzQUYiLz4KPHN2ZyB4PSIxMzAiIHk9IjEzMCIgd2lkdGg9IjQwIiBoZWlnaHQ9IjQwIiB2aWV3Qm94PSIwIDAgNDAgNDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxwYXRoIGQ9Ik0xNSA1TDI1IDEwTDE1IDE1VjVaIiBmaWxsPSJ3aGl0ZSIvPgo8L3N2Zz4KPC9zdmc+Cg==';
        }
        
        // Distribution Cart System
        let distributionCart = [];
        
        // Load cart from localStorage on page load
        function loadCart() {
            const savedCart = localStorage.getItem('ionDistributionCart');
            if (savedCart) {
                distributionCart = JSON.parse(savedCart);
                updateDistributionCartDisplay();
            }
        }
        
        // Save cart to localStorage
        function saveCart() {
            localStorage.setItem('ionDistributionCart', JSON.stringify(distributionCart));
        }
        
        // Add item to distribution cart
        function addToDistributionCart(item) {
            // Check if a video is selected
            if (!selectedVideo) {
                alert('⚠️ Please select a video first before adding packages to cart');
                return false;
            }
            
            const existingItem = distributionCart.find(cartItem => 
                cartItem.id === item.id && cartItem.type === item.type
            );
            
            if (existingItem) {
                existingItem.quantity += 1;
            } else {
                distributionCart.push({...item, quantity: 1});
            }
            
            saveCart();
            updateDistributionCartDisplay();
            return true;
        }
        
        // Remove item from distribution cart
        function removeFromDistributionCart(itemId, itemType) {
            distributionCart = distributionCart.filter(item => !(item.id === itemId && item.type === itemType));
            saveCart();
            updateDistributionCartDisplay();
        }
        
        // Clear entire distribution cart
        function clearDistributionCart() {
            distributionCart = [];
            saveCart();
            updateDistributionCartDisplay();
        }
        
        // Update distribution cart display
        function updateDistributionCartDisplay() {
            // This function is deprecated - cart display is now handled by displaySelectedChannels()
            // Just update the header cart icon if the function exists
            if (typeof updateCartDisplay === 'function') {
                updateCartDisplay();
            }
        }
        
        // Checkout function
        function checkout() {
            if (cart.length === 0) {
                alert('Your cart is empty!');
                return;
            }
            
            // Here you would integrate with your payment system
            alert(`Proceeding to checkout with ${cart.length} items totaling $${cart.reduce((sum, item) => sum + (item.price * item.quantity), 0).toFixed(2)}`);
        }
        
        // Load user permissions on page load
        async function loadUserPermissions() {
            try {
                console.log('🔐 Loading user permissions...');
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=get_permissions'
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                if (data.success && data.permission_info) {
                    console.log('✅ Permissions loaded:', data.permission_info);
                    updatePermissionIndicator(data.permission_info);
                } else {
                    console.error('❌ Failed to load permissions:', data.message);
                    // Show error state
                    const badge = document.getElementById('permissionBadge');
                    const indicator = document.getElementById('permissionIndicator');
                    if (badge) {
                        badge.textContent = 'Permission Error';
                        badge.className = 'permission-badge error';
                        badge.style.display = 'inline';
                    }
                    if (indicator) {
                        const spinner = indicator.querySelector('.spinner');
                        if (spinner) spinner.style.display = 'none';
                    }
                }
            } catch (error) {
                console.error('❌ Error loading permissions:', error);
                // Show error state
                const badge = document.getElementById('permissionBadge');
                const indicator = document.getElementById('permissionIndicator');
                if (badge) {
                    badge.textContent = 'Permission Error';
                    badge.className = 'permission-badge error';
                    badge.style.display = 'inline';
                }
                if (indicator) {
                    const spinner = indicator.querySelector('.spinner');
                    if (spinner) spinner.style.display = 'none';
                }
            }
        }
        
        // Search for preselected video and display it in video results
        async function searchVideosForPreselected(videoId, videoTitle) {
            try {
                console.log('🔍 Searching for preselected video:', videoId, videoTitle);
                
                // First, debug session data
                const sessionResponse = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=debug_session'
                });
                const sessionData = await sessionResponse.json();
                console.log('🔧 Session debug data:', sessionData);
                
                // Test direct video lookup
                const testResponse = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=test_video_lookup&test_id=${encodeURIComponent(videoId)}`
                });
                const testData = await testResponse.json();
                console.log('🧪 Direct video lookup test:', testData);
                
            // Search by video ID first
            const response = await fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=search_videos&query=${encodeURIComponent(videoId)}`
            });
                
                const data = await response.json();
                
                // Debug: Log search response details
                console.log('🔍 Search response for video ID:', videoId);
                console.log('📊 Full response data:', JSON.stringify(data, null, 2));
                
                if (data.debug_info) {
                    console.log('🔧 Debug info found:');
                    console.log('  - Query searched:', data.debug_info.query_searched);
                    console.log('  - Search term used:', data.debug_info.search_term_used);
                    console.log('  - Results found:', data.debug_info.total_results_found);
                    console.log('  - User role:', data.debug_info.user_role);
                    console.log('  - SQL params count:', data.debug_info.sql_params_count);
                    console.log('  - SQL preview:', data.debug_info.prepared_sql_preview);
                } else {
                    console.log('❌ No debug_info in response!');
                }
                
                if (data.success && data.videos && data.videos.length > 0) {
                    // Found the video by ID, display it
                    allVideos = data.videos;
                    totalVideos = allVideos.length;
                    currentVideoPage = 1;
                    displayVideoResults();
                    
                    console.log('✅ Found preselected video by ID:', videoId);
                    return;
                }
                
            // If not found by ID, try searching by title
            console.log('🔍 Video not found by ID, searching by title:', videoTitle);
            const titleResponse = await fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=search_videos&query=${encodeURIComponent(videoTitle)}`
            });
                
                const titleData = await titleResponse.json();
                
                if (titleData.success && titleData.videos && titleData.videos.length > 0) {
                    // Found video(s) by title, display them
                    allVideos = titleData.videos;
                    totalVideos = allVideos.length;
                    currentVideoPage = 1;
                    displayVideoResults();
                    
                    console.log('✅ Found preselected video by title:', videoTitle);
                } else {
                    // Video not found, show a message in video results
                    const videoResultsDiv = document.getElementById('videoResults');
                    videoResultsDiv.innerHTML = `
                        <div class="error" style="padding: 16px; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 8px; color: #ef4444;">
                            <h4>🔍 Video Not Found in Search</h4>
                            <p>The preselected video "${videoTitle}" (ID: ${videoId}) could not be found in the video database.</p>
                            <p style="font-size: 0.9em; opacity: 0.8;">This might be due to access permissions or the video may have been removed.</p>
                        </div>
                    `;
                    console.warn('⚠️ Preselected video not found in search results');
                }
                
            } catch (error) {
                console.error('❌ Error searching for preselected video:', error);
                const videoResultsDiv = document.getElementById('videoResults');
                videoResultsDiv.innerHTML = `
                    <div class="error">Error searching for preselected video: ${error.message}</div>
                `;
            }
        }
        
        // Initialize cart and permissions on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadCart();
            loadUserPermissions();
            
            // Auto-select preselected video if provided
            if (preselectedVideoId) {
                console.log('🎯 Auto-selecting preselected video:', preselectedVideoId, preselectedVideoTitle);
                
                // Show a welcome message for preselected video
                const resultsDiv = document.getElementById('results');
                resultsDiv.innerHTML = `
                    <div class="preselected-welcome" style="padding: 20px; background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 8px; margin: 16px 0;">
                        <h3 style="color: #3b82f6; margin: 0 0 12px 0;">🎯 Video Pre-selected for Distribution</h3>
                        <p style="margin: 8px 0; color: #e2e8f0;">The video "<strong style="color: #f59e0b;">${preselectedVideoTitle}</strong>" has been automatically selected from the creators page.</p>
                        <p style="margin: 8px 0; color: #94a3b8;">Loading video details...</p>
                        <div class="loading-spinner" style="display: inline-block; width: 16px; height: 16px; border: 2px solid #3b82f6; border-top: 2px solid transparent; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                    </div>
                `;
                
                // Add CSS for spinner animation if not already present
                if (!document.querySelector('#spinner-style')) {
                    const style = document.createElement('style');
                    style.id = 'spinner-style';
                    style.textContent = '@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }';
                    document.head.appendChild(style);
                }
                
                // Auto-search and display the preselected video in the search results
                setTimeout(async () => {
                    try {
                        console.log('🔍 Searching for preselected video:', preselectedVideoId, preselectedVideoTitle);
                        
                        // First, search for the video to display it in the video search results
                        await searchVideosForPreselected(preselectedVideoId, preselectedVideoTitle);
                        
                        // Then auto-select the video
                        console.log('🎯 Auto-selecting preselected video:', preselectedVideoId);
                        await selectVideo(preselectedVideoId);
                        console.log('✅ Video selection completed successfully');
                        
                    } catch (error) {
                        console.error('❌ Error processing preselected video:', error);
                        const resultsDiv = document.getElementById('results');
                        resultsDiv.innerHTML = `
                            <div class="error-message" style="padding: 20px; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 8px; margin: 16px 0;">
                                <h3 style="color: #ef4444; margin: 0 0 12px 0;">❌ Unable to Load Pre-selected Video</h3>
                                <p style="margin: 8px 0; color: #e2e8f0;">The video "<strong>${preselectedVideoTitle}</strong>" could not be loaded.</p>
                                <p style="margin: 8px 0; color: #94a3b8;">Error: ${error.message}</p>
                                <div style="margin-top: 16px;">
                                    <button onclick="location.reload()" style="background: #3b82f6; color: white; border: none; padding: 8px 16px; border-radius: 6px; margin-right: 8px; cursor: pointer;">🔄 Retry</button>
                                    <button onclick="document.getElementById('results').innerHTML = ''; document.getElementById('videoSearch').focus();" style="background: #6b7280; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer;">🔍 Search Manually</button>
                                </div>
                            </div>
                        `;
                    }
                }, 500); // Small delay to ensure page is fully loaded
            }
        });
        
        // ===== CART MANAGEMENT SYSTEM =====
        
        // Shopping cart state
        let shoppingCart = {
            items: [],
            total: 0
        };
        
        // Initialize cart from localStorage
        function initializeCart() {
            const savedCart = localStorage.getItem('ionBlastCart');
            if (savedCart) {
                shoppingCart = JSON.parse(savedCart);
            }
            updateCartDisplay();
        }
        
        // Add item to cart
        function addToCart(type, item) {
            // Check if a video is selected
            if (!selectedVideo) {
                showCartFeedback('⚠️ Please select a video first before adding channels to cart', 'error');
                return false;
            }
            
            // Check if item already exists
            const existingIndex = shoppingCart.items.findIndex(cartItem => cartItem.id === item.id);
            
            if (existingIndex !== -1) {
                // Update existing item
                shoppingCart.items[existingIndex] = item;
            } else {
                // Add new item
                shoppingCart.items.push(item);
            }
            
            // Update total
            shoppingCart.total = shoppingCart.items.reduce((sum, item) => sum + parseFloat(item.price || 0), 0);
            
            // Save to localStorage
            localStorage.setItem('ionBlastCart', JSON.stringify(shoppingCart));
            
            // Update display
            updateCartDisplay();
            
            // Show feedback
            showCartFeedback(`Added ${item.name} to cart`);
            return true;
        }
        
        // Remove item from cart
        function removeFromCart(itemId) {
            shoppingCart.items = shoppingCart.items.filter(item => item.id !== itemId);
            shoppingCart.total = shoppingCart.items.reduce((sum, item) => sum + parseFloat(item.price || 0), 0);
            
            localStorage.setItem('ionBlastCart', JSON.stringify(shoppingCart));
            updateCartDisplay();
        }
        
        // Clear cart
        function clearCart() {
            shoppingCart = { items: [], total: 0 };
            localStorage.removeItem('ionBlastCart');
            updateCartDisplay();
        }
        
        // Update cart display - unified cart system
        function updateCartDisplay() {
            const cartBtn = document.getElementById('cartBtn');
            const cartCount = document.getElementById('cartCount');
            
            // All items now go to shoppingCart (both channels and packages)
            const totalItems = shoppingCart.items.length;
            
            if (totalItems === 0) {
                cartBtn.classList.add('disabled');
                cartCount.classList.add('hidden');
                cartCount.textContent = '0';
            } else {
                cartBtn.classList.remove('disabled');
                cartCount.classList.remove('hidden');
                cartCount.textContent = totalItems.toString();
            }
        }
        
        // Show cart feedback
        function showCartFeedback(message, type = 'success') {
            // Create temporary feedback element
            const feedback = document.createElement('div');
            const backgroundColor = type === 'error' ? '#ef4444' : '#10b981';
            feedback.style.cssText = `
                position: fixed;
                top: 100px;
                right: 20px;
                background: ${backgroundColor};
                color: white;
                padding: 12px 20px;
                border-radius: 8px;
                z-index: 1000;
                animation: slideInRight 0.3s ease;
                max-width: 300px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            `;
            feedback.textContent = message;
            document.body.appendChild(feedback);
            
            // Remove after 4 seconds for errors, 3 seconds for success
            const duration = type === 'error' ? 4000 : 3000;
            setTimeout(() => {
                feedback.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => {
                    if (document.body.contains(feedback)) {
                        document.body.removeChild(feedback);
                    }
                }, 300);
            }, duration);
        }
        
        // Toggle cart display
        function toggleCart() {
            if (shoppingCart.items.length === 0) {
                showCartFeedback('Cart is empty');
                return;
            }
            
            // Create cart modal
            showCartModal();
        }
        
        // Show cart modal
        function showCartModal() {
            const modal = document.createElement('div');
            modal.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 1000;
                display: flex;
                align-items: center;
                justify-content: center;
            `;
            
            const cartContent = `
                <div style="background: white; border-radius: 12px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto; padding: 24px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #e5e7eb; padding-bottom: 16px;">
                        <h2 style="margin: 0; color: #1f2937; font-size: 1.5rem;">Shopping Cart</h2>
                        <button onclick="document.body.removeChild(this.closest('.cart-modal'))" style="background: #ef4444; color: white; border: none; border-radius: 6px; width: 32px; height: 32px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: bold;">×</button>
                    </div>
                    <div class="cart-items-list">
                        ${shoppingCart.items.map(item => `
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #f3f4f6;">
                                <div>
                                    <div style="font-weight: 600; color: #1f2937;">${item.name}</div>
                                    <div style="font-size: 0.875rem; color: #6b7280;">${item.duration} - ${item.type === 'package' ? 'Package' : 'Channel'}</div>
                                </div>
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <span style="font-weight: 600; color: #059669;">$${parseFloat(item.price).toFixed(2)}</span>
                                    <button onclick="removeFromCart('${item.id}'); document.body.removeChild(this.closest('.cart-modal')); showCartModal();" style="background: #ef4444; color: white; border: none; border-radius: 4px; padding: 4px 8px; cursor: pointer; font-size: 12px;">Remove</button>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                    <div style="margin-top: 20px; padding-top: 16px; border-top: 2px solid #e5e7eb;">
                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 1.25rem; font-weight: 700; margin-bottom: 16px;">
                            <span>Total:</span>
                            <span style="color: #059669;">$${shoppingCart.total.toFixed(2)}</span>
                        </div>
                        <div style="display: flex; gap: 12px;">
                            <button onclick="clearCart(); document.body.removeChild(this.closest('.cart-modal'))" style="background: #6b7280; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-weight: 600;">Clear Cart</button>
                            <button onclick="proceedToCheckout()" style="background: #059669; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-weight: 600; flex: 1;">Proceed to Checkout</button>
                        </div>
                    </div>
                </div>
            `;
            
            modal.innerHTML = cartContent;
            modal.className = 'cart-modal';
            document.body.appendChild(modal);
        }
        
        // Proceed to checkout
        function proceedToCheckout() {
            alert('Checkout functionality would be implemented here. Cart contains ' + shoppingCart.items.length + ' items totaling $' + shoppingCart.total.toFixed(2));
        }
        
        // ===== ION PRICING DISPLAY FUNCTIONS =====
        
        // Cache for pricing data
        const pricingCache = new Map();
        
        // Show pricing for a specific channel
        async function showChannelPricing(slug, cardElement) {
            const displayDiv = cardElement.querySelector('.channel-pricing-overlay');
            const button = cardElement.querySelector('.show-pricing-btn');
            
            // If already showing, return
            if (displayDiv.style.display !== 'none') {
                return;
            }
            
            // Show loading state
            if (button) {
                button.innerHTML = '<span class="pricing-icon">⏳</span><span class="pricing-text">Loading...</span>';
            }
            displayDiv.style.display = 'block';
            displayDiv.innerHTML = '<div class="pricing-loading" style="padding: 20px; text-align: center; color: #6b7280;">Loading pricing information...</div>';
            
            try {
                const pricing = await getChannelPricing(slug);
                if (pricing) {
                    displayPricing(displayDiv, button, pricing);
                } else {
                    displayPricingError(displayDiv, button);
                }
            } catch (error) {
                console.error('Error loading pricing:', error);
                displayPricingError(displayDiv, button);
            }
        }
        
        // Get pricing data for a channel
        async function getChannelPricing(slug) {
            // Check cache first
            if (pricingCache.has(slug)) {
                return pricingCache.get(slug);
            }
            
            try {
                const response = await fetch('/api/get-pricing.php?action=get_pricing&slug=' + encodeURIComponent(slug));
                const data = await response.json();
                
                if (data.success && data.pricing) {
                    // Correct format: data.pricing
                    const locationData = {
                        slug: slug,
                        name: data.location?.name || 'Unknown',
                        state: data.location?.state || '',
                        pricing: data.pricing
                    };
                    pricingCache.set(slug, locationData);
                    return locationData;
                } else if (data.success && data.location && data.location.pricing) {
                    // Legacy format: data.location.pricing
                    pricingCache.set(slug, data.location);
                    return data.location;
                }
                return null;
            } catch (error) {
                console.error('Pricing API error:', error);
                return null;
            }
        }
        
        // Get tier class based on pricing data
        function getTierClass(pricing) {
            const label = (pricing.label || '').toLowerCase();
            
            if (label.includes('tier 1')) return 'tier-1';
            if (label.includes('tier 2')) return 'tier-2';
            if (label.includes('tier 3')) return 'tier-3';
            if (label.includes('tier 4')) return 'tier-4';
            if (label.includes('tier 5')) return 'tier-5';
            if (label.includes('bundle')) return 'bundle';
            if (label.includes('custom')) return 'custom';
            
            if (pricing.type) {
                const type = pricing.type.toLowerCase();
                if (type === 'bundle') return 'bundle';
                if (type === 'custom') return 'custom';
                if (type === 'tier') {
                    const tierMatch = label.match(/tier\s*(\d+)/);
                    if (tierMatch) {
                        const tierNum = parseInt(tierMatch[1]);
                        if (tierNum >= 1 && tierNum <= 5) {
                            return `tier-${tierNum}`;
                        }
                    }
                }
            }
            
            // Default fallback based on price ranges (monthly pricing)
            if (pricing.monthly) {
                const monthly = parseFloat(pricing.monthly);
                if (monthly <= 3.99) return 'tier-1';  // Tier 1: Up to $3.99
                if (monthly <= 4.99) return 'tier-2';  // Tier 2: $4.00-$4.99
                if (monthly <= 5.99) return 'tier-3';  // Tier 3: $5.00-$5.99
                if (monthly <= 7.99) return 'tier-4';  // Tier 4: $6.00-$7.99
                return 'tier-5';                       // Tier 5: $8.00+
            }
            
            return 'other';
        }

        // Display pricing information
        function displayPricing(displayDiv, button, pricing) {
            const { monthly, quarterly, semi_annual, annual, currency, label } = pricing.pricing;
            const tierClass = getTierClass(pricing.pricing);
            
            // Remove existing tier classes and add new one
            displayDiv.className = displayDiv.className.replace(/\b(tier-\d+|bundle|custom|other|error)\b/g, '');
            displayDiv.classList.add(tierClass, 'channel-pricing-overlay');
            
            displayDiv.innerHTML = `
                <div class="pricing-content">
                    <div class="pricing-header">
                        <h4>${pricing.name}${pricing.state ? ', ' + pricing.state : ''}</h4>
                        <span class="pricing-tier">${label}</span>
                        <button class="pricing-close" onclick="event.stopPropagation(); hideChannelPricing(this.closest('.channel-card'))" aria-label="Close pricing">×</button>
                    </div>
                    <div class="pricing-options">
                        <div class="pricing-option" onclick="selectPricingOption('${pricing.slug}', 'monthly', ${monthly})">
                            <span class="option-duration">Monthly</span>
                            <span class="option-price">$${monthly.toFixed(2)}</span>
                        </div>
                        <div class="pricing-option" onclick="selectPricingOption('${pricing.slug}', 'quarterly', ${quarterly})">
                            <span class="option-duration">Quarterly</span>
                            <span class="option-price">$${quarterly.toFixed(2)}</span>
                        </div>
                        <div class="pricing-option" onclick="selectPricingOption('${pricing.slug}', 'semi_annual', ${semi_annual})">
                            <span class="option-duration">Semi-Annual</span>
                            <span class="option-price">$${semi_annual.toFixed(2)}</span>
                        </div>
                        <div class="pricing-option best-value" onclick="selectPricingOption('${pricing.slug}', 'annual', ${annual})">
                            <span class="option-duration">Annual</span>
                            <span class="option-price">$${annual.toFixed(2)}</span>
                            <span class="option-badge">Best Value</span>
                        </div>
                    </div>
                    <div class="pricing-footer">
                        <small>All prices in ${currency}</small>
                    </div>
                    <div class="pricing-actions">
                        <button class="add-to-cart-btn" onclick="addChannelToCart('${pricing.slug}', '${pricing.name}', ${monthly}, ${quarterly}, ${semi_annual}, ${annual}, '${currency}')">
                            Add to Cart
                        </button>
                    </div>
                </div>
            `;
            
            if (button) {
                button.innerHTML = '<span class="pricing-icon">💰</span><span class="pricing-text">Hide Pricing</span>';
            }
        }
        
        // Display error state
        function displayPricingError(displayDiv, button) {
            displayDiv.classList.add('error', 'channel-pricing-overlay');
            displayDiv.innerHTML = `
                <div class="pricing-content">
                    <div class="pricing-header">
                        <h4>Pricing Error</h4>
                        <button class="pricing-close" onclick="event.stopPropagation(); hideChannelPricing(this.closest('.channel-card'))" aria-label="Close pricing">×</button>
                    </div>
                    <div class="pricing-error">⚠️ Pricing information unavailable</div>
                </div>
            `;
            if (button) {
                button.innerHTML = '<span class="pricing-icon">❌</span><span class="pricing-text">Error</span>';
            }
        }
        
        // Toggle pricing display (for button clicks)
        function toggleChannelPricing(slug, buttonElement) {
            const cardElement = buttonElement.closest('.channel-card');
            const displayDiv = cardElement.querySelector('.channel-pricing-overlay');
            
            if (displayDiv.style.display !== 'none') {
                hideChannelPricing(cardElement);
            } else {
                showChannelPricing(slug, cardElement);
            }
        }
        
        // Hide pricing on mouse leave with delay
        let hideTimeout;
        function hideChannelPricing(cardElement) {
            hideTimeout = setTimeout(() => {
                const displayDiv = cardElement.querySelector('.channel-pricing-overlay');
                const button = cardElement.querySelector('.show-pricing-btn');
                if (displayDiv && displayDiv.style.display !== 'none') {
                    displayDiv.style.display = 'none';
                    if (button) {
                        button.innerHTML = '<span class="pricing-icon">💰</span><span class="pricing-text">Show Pricing</span>';
                    }
                }
            }, 300); // 300ms delay before hiding
        }
        
        // Clear hide timeout on mouse enter
        function clearHideTimeout() {
            if (hideTimeout) {
                clearTimeout(hideTimeout);
                hideTimeout = null;
            }
        }
        
        // Select a pricing option (highlight selection only, cart addition happens via "Add to Cart" button)
        function selectPricingOption(slug, duration, price) {
            // Remove previous selections
            document.querySelectorAll(`[data-channel-slug="${slug}"] .pricing-option`).forEach(option => {
                option.classList.remove('selected');
            });
            
            // Add selection to clicked option
            event.target.closest('.pricing-option').classList.add('selected');
            
            console.log(`Selected ${duration} pricing for ${slug}: $${price}`);
        }
        
        // Add channel to cart with pricing
        function addChannelToCart(slug, name, monthly, quarterly, semiAnnual, annual, currency) {
            // Get selected pricing option
            const selectedOption = document.querySelector(`[data-channel-slug="${slug}"] .pricing-option.selected`);
            let selectedDuration = 'monthly';
            let selectedPrice = monthly;
            
            if (selectedOption) {
                const durationText = selectedOption.querySelector('.option-duration').textContent.toLowerCase();
                switch (durationText) {
                    case 'quarterly':
                        selectedDuration = 'quarterly';
                        selectedPrice = quarterly;
                        break;
                    case 'semi-annual':
                        selectedDuration = 'semi_annual';
                        selectedPrice = semiAnnual;
                        break;
                    case 'annual':
                        selectedDuration = 'annual';
                        selectedPrice = annual;
                        break;
                    default:
                        selectedDuration = 'monthly';
                        selectedPrice = monthly;
                }
            }
            
            // Create cart item
            const cartItem = {
                id: slug,
                type: 'channel',
                slug: slug,
                name: name,
                duration: selectedDuration,
                price: selectedPrice,
                currency: currency,
                addedAt: new Date().toISOString()
            };
            
            // Add to cart (implement your cart logic here)
            addToCart('channel', cartItem);
            
            // Show success feedback on button if found
            const button = document.querySelector(`[data-channel-slug="${slug}"] .add-to-cart-btn`);
            if (button) {
                const originalText = button.innerHTML;
                button.innerHTML = '✅ Added to Cart';
                button.disabled = true;
                
                setTimeout(() => {
                    button.innerHTML = originalText;
                    button.disabled = false;
                }, 2000);
            }
        }
        
        // ===== NEW QUICK PRICING TOOLTIP SYSTEM =====
        
        // Show quick pricing tooltip on button hover
        async function showQuickPricing(buttonElement, slug) {
            const cardElement = buttonElement.closest('.channel-card');
            const tooltip = cardElement.querySelector('.quick-pricing-tooltip');
            
            // Show loading state
            tooltip.innerHTML = '<div style="text-align: center; color: #94a3b8;">Loading pricing...</div>';
            tooltip.style.display = 'block';
            
            try {
                const pricing = await getChannelPricing(slug);
                if (pricing && pricing.pricing) {
                    const p = pricing.pricing;
                    tooltip.innerHTML = `
                        <div class="pricing-row">
                            <span class="pricing-label">Monthly:</span>
                            <span class="pricing-value">$${p.monthly.toFixed(2)}</span>
                        </div>
                        <div class="pricing-row">
                            <span class="pricing-label">Quarterly:</span>
                            <span class="pricing-value">$${p.quarterly.toFixed(2)}</span>
                        </div>
                        <div class="pricing-row">
                            <span class="pricing-label">Semi-Annual:</span>
                            <span class="pricing-value">$${p.semi_annual.toFixed(2)}</span>
                        </div>
                        <div class="pricing-row">
                            <span class="pricing-label">Annual:</span>
                            <span class="pricing-value">$${p.annual.toFixed(2)}</span>
                        </div>
                        <div class="pricing-tier-badge">${p.label || 'Standard Pricing'}</div>
                    `;
                } else {
                    tooltip.innerHTML = '<div style="text-align: center; color: #ef4444;">Pricing unavailable</div>';
                }
            } catch (error) {
                console.error('Error loading pricing:', error);
                tooltip.innerHTML = '<div style="text-align: center; color: #ef4444;">Error loading pricing</div>';
            }
        }
        
        // Hide quick pricing tooltip
        function hideQuickPricing(buttonElement) {
            const cardElement = buttonElement.closest('.channel-card');
            const tooltip = cardElement.querySelector('.quick-pricing-tooltip');
            setTimeout(() => {
                tooltip.style.display = 'none';
            }, 200);
        }
        
        // Add channel directly to cart with default monthly pricing
        async function addChannelDirectlyToCart(slug, name) {
            // Check if video is selected
            if (!selectedVideo) {
                showCartFeedback('⚠️ Please select a video first', 'error');
                return;
            }
            
            // Check if already in cart
            if (selectedChannels.some(ch => ch.slug === slug)) {
                showCartFeedback('Channel already in cart', 'error');
                return;
            }
            
            try {
                // Get pricing for the channel
                const pricing = await getChannelPricing(slug);
                if (!pricing || !pricing.pricing) {
                    showCartFeedback('Could not load pricing for this channel', 'error');
                    return;
                }
                
                // Add to selected channels with monthly interval by default
                selectedChannels.push({
                    slug: slug,
                    name: name,
                    interval: 'monthly',
                    price: pricing.pricing.monthly
                });
                
                // Update display
                displaySelectedChannels();
                showCartFeedback(`Added ${name} to cart`);
                
                // Refresh channel results to show selection state
                displayChannelResults();
                
            } catch (error) {
                console.error('Error adding channel to cart:', error);
                showCartFeedback('Error adding channel to cart', 'error');
            }
        }
        
        // Legacy cart functions removed to prevent conflicts with new shopping cart system
        
        // Initialize cart display on page load
        document.addEventListener('DOMContentLoaded', function() {
            initializeCart();
            loadCart(); // Load distribution cart as well
        });
        
    </script>
</body>
</html>
<?php
// Clean up output buffer
ob_end_flush();
?>
