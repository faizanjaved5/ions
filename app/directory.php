<?php
require_once __DIR__ . '/../login/session.php';

error_log('directory.php: Execution started.');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';
error_log('directory.php: Database environment loaded.');
// Use our custom database class instead of $wpdb
$wpdb = $db;
error_log('directory.php: Database object is valid.');

$table = 'IONLocalNetwork'; // Define table name early

// SECURITY CHECK: Ensure user is authenticated
error_log("TRACKING DIRECTORY: User session check - email: " . ($_SESSION['user_email'] ?? 'NOT SET'));

// Debug capture for directory access - DISABLED for performance (was causing 2-3 second delays)
// $debug_base = 'https://ions.com/app/oauth_debug_capture.php?action=log&message=';
// @file_get_contents($debug_base . urlencode("=== DIRECTORY.PHP ACCESSED ==="));
// @file_get_contents($debug_base . urlencode("Session email: " . ($_SESSION['user_email'] ?? 'NOT SET')));
// @file_get_contents($debug_base . urlencode("Session authenticated: " . ($_SESSION['authenticated'] ?? 'NOT SET')));

if (!isset($_SESSION['user_email']) || empty($_SESSION['user_email'])) {
    error_log('SECURITY: Unauthenticated user from directory.php');
    // @file_get_contents($debug_base . urlencode("DIRECTORY: NO SESSION - handling as API or page")); // DISABLED for performance
    
    // Detect AJAX/JSON/API requests
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    $accepts_json = isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
    $has_action = isset($_GET['action']) || isset($_POST['action']);
    
    if ($is_ajax || $accepts_json || $has_action) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Unauthorized access',
            'code' => 'SESSION_EXPIRED',
            'login' => '/login/index.php'
        ]);
        exit();
    }
    
    // Fallback to page redirect for normal requests
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: /login/index.php');
    exit(); // CRITICAL: Stop execution immediately
}

// Fetch user data from IONEERS table for profile photo, role-based permissions, and UI preferences
$user_email = $_SESSION['user_email'];
$user_data = $wpdb->get_row("SELECT photo_url, user_role, preferences, fullname FROM IONEERS WHERE email = %s", $user_email);

// SECURITY CHECK: Ensure user exists in database
error_log("TRACKING DIRECTORY: Checking user data for: $user_email");
if (!$user_data) {
    error_log("SECURITY: User {$user_email} not found in database");
    error_log("TRACKING DIRECTORY: USER NOT FOUND - redirecting to login");
    session_unset();
    session_destroy();
    header('Location: /login/index.php?error=unauthorized');
    exit(); // CRITICAL: Stop execution immediately
}

$user_photo_url = $user_data->photo_url ?? null;
$user_role = $user_data->user_role ?? 'Guest';
$user_preferences_json = $user_data->preferences ?? null;
$user_fullname = $user_data->fullname ?? null;

// SECURITY CHECK: Ensure user has valid role
error_log("TRACKING DIRECTORY: User role check - Role: '$user_role' for email: $user_email");
if (empty($user_role) || $user_role === 'Guest') {
    error_log("SECURITY: User {$user_email} has invalid role: {$user_role}");
    error_log("TRACKING DIRECTORY: INVALID ROLE - redirecting to login");
    header('Location: /login/index.php?error=unauthorized');
    exit(); // CRITICAL: Stop execution immediately
}

// SECURITY CHECK: Restrict access to administrative directory interface
// Only Owner and Admin roles should have access to directory.php
$came_from_navigation = isset($_GET['nav']) || isset($_SERVER['HTTP_REFERER']);
error_log("TRACKING DIRECTORY: Checking role-based access - Role: '$user_role'");
// @file_get_contents($debug_base . urlencode("DIRECTORY: User role check - Role: '$user_role'")); // DISABLED for performance

// Redirect non-administrative users to appropriate interfaces
if (!in_array($user_role, ['Owner', 'Admin'])) {
    error_log("SECURITY: User {$user_email} with role '{$user_role}' denied access to directory.php - redirecting");
    // @file_get_contents($debug_base . urlencode("DIRECTORY: ACCESS DENIED - Role '$user_role' not authorized")); // DISABLED for performance
    
    // Redirect based on role
    if ($user_role === 'Creator') {
        error_log("REDIRECT: Creator {$user_email} redirected to creators.php");
        header('Location: /app/creators.php');
    } elseif ($user_role === 'Member') {
        error_log("REDIRECT: Member {$user_email} redirected to creators.php");
        header('Location: /app/creators.php');
    } else {
        error_log("REDIRECT: User {$user_email} with role '{$user_role}' redirected to login");
        header('Location: /login/index.php?error=access_denied');
    }
    
    // Clear any output buffers to ensure clean redirect
    if (ob_get_length()) ob_clean();
    exit();
} else {
    error_log("TRACKING DIRECTORY: User is NOT being redirected - Role: '$user_role', Came from nav: " . ($came_from_navigation ? 'YES' : 'NO'));
    // @file_get_contents($debug_base . urlencode("DIRECTORY: Not Creator role, continuing with directory")); // DISABLED for performance
}

// Parse user preferences with defaults
// The UI customization system reads JSON preferences from the IONEERS.preferences field
// and applies custom colors, logo, and dark/light mode to the directory interface.
// Users can test different themes using test_preferences.php
$default_preferences = [
    'Theme' => 'Default',
    'IONLogo' => 'https://ions.com/wp-content/uploads/2022/09/ion-logo-speech-bubble-purple-e1663778310135.png',
    'Background' => ['#6366f1', '#7c3aed'],
    'ButtonColor' => '#4f46e5',
    'DefaultMode' => 'LightMode'
];

$user_preferences = $default_preferences;
if (!empty($user_preferences_json)) {
    $parsed_preferences = json_decode($user_preferences_json, true);
    if (is_array($parsed_preferences)) {
        $user_preferences = array_merge($default_preferences, $parsed_preferences);
    }
}

// Include role-based access control
require_once '../login/roles.php';

// Check if user can access IONS section
IONRoles::requireAccess($user_role, 'IONS');

// Determine permissions based on user role
$can_add_edit = IONRoles::canPerformAction($user_role, 'IONS', 'add');
$can_delete = IONRoles::canPerformAction($user_role, 'IONS', 'delete');

// Note: Video content search feature temporarily removed to ensure stable basic search functionality

error_log("User: $user_email, Role: $user_role, Photo: " . ($user_photo_url ? $user_photo_url : 'none'));
error_log("User Preferences: " . json_encode($user_preferences));

require_once('add-ion-handler.php');
error_log('directory.php: add-ion-handler.php included.');

// Simple diagnostic endpoint to check video data
if (isset($_GET['action']) && $_GET['action'] === 'video_diagnostic') {
    // SECURITY CHECK: Authenticate API endpoint
    if (!isset($_SESSION['user_email']) || empty($_SESSION['user_email'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized access']);
        exit();
    }
    
    header('Content-Type: application/json');
    
    $result = [
        'total_videos' => $wpdb->get_var("SELECT COUNT(*) FROM IONLocalVideos"),
        'sample_videos' => $wpdb->get_results("SELECT slug, videotype, title FROM IONLocalVideos ORDER BY id LIMIT 10"),
        'unique_slugs' => $wpdb->get_col("SELECT DISTINCT slug FROM IONLocalVideos ORDER BY slug LIMIT 20"),
        'unique_videotypes' => $wpdb->get_col("SELECT DISTINCT videotype FROM IONLocalVideos ORDER BY videotype"),
        'slug_videotype_combos' => $wpdb->get_results("SELECT slug, videotype, COUNT(*) as count FROM IONLocalVideos GROUP BY slug, videotype ORDER BY count DESC LIMIT 10")
    ];
    
    echo json_encode($result, JSON_PRETTY_PRINT);
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'get_videos' && isset($_GET['slug']) && isset($_GET['source'])) {
    // SECURITY CHECK: Authenticate API endpoint
    if (!isset($_SESSION['user_email']) || empty($_SESSION['user_email'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Unauthorized access']);
        exit();
    }
    
    $slug = sanitize_text_field($_GET['slug']);
    $source = sanitize_text_field($_GET['source']);
    $category = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '';
    $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = 30;
    $offset = ($page - 1) * $per_page;
    
    // Enhanced debug logging - CAPTURE EXACT VALUES
    error_log("=== VIDEO DEBUG START ===");
    error_log("DEBUG: Video request - slug: '{$slug}', source: '{$source}', category: '{$category}', search: '{$search}'");
    error_log("DEBUG: Request URL: " . $_SERVER['REQUEST_URI']);
    error_log("DEBUG: Raw GET params: " . print_r($_GET, true));
    
    // FIRST: Check what slugs exist in IONLocalVideos for debugging
    $existing_video_slugs = $wpdb->get_results("SELECT DISTINCT slug, videotype, COUNT(*) as count FROM IONLocalVideos GROUP BY slug, videotype ORDER BY slug, videotype LIMIT 20");
    error_log("DEBUG: Sample existing video slugs in database:");
    foreach ($existing_video_slugs as $existing) {
        error_log("DEBUG: - EXISTING: slug='{$existing->slug}', videotype='{$existing->videotype}', count={$existing->count}");
    }
    
    // SECOND: Check what network slugs exist near the requested slug
    $network_slugs = $wpdb->get_results($wpdb->prepare("SELECT slug, city_name FROM {$table} WHERE slug LIKE %s ORDER BY slug LIMIT 10", '%' . $slug . '%'));
    error_log("DEBUG: Network slugs similar to '{$slug}':");
    foreach ($network_slugs as $net_slug) {
        error_log("DEBUG: - NETWORK: slug='{$net_slug->slug}', city_name='{$net_slug->city_name}'");
    }
    
    // Create smart slug variations that handle existing prefixes
    $base_slug = preg_replace('/^ion-?/', '', $slug); // Remove any existing ion- or ion prefix
    $slug_variations = [
        $slug,                    // exact match (as passed)
        $base_slug,               // city name without ion prefix (e.g., "tampa")
        'ion-' . $base_slug,      // with ion- prefix (e.g., "ion-tampa")
        str_replace('-', '', $slug), // remove dashes from original
        str_replace('-', '', $base_slug), // remove dashes from base (e.g., "tampa")
        'ion' . $base_slug,       // with ion prefix no dash (e.g., "iontampa")
    ];
    
    // Remove duplicates and empty values
    $slug_variations = array_unique(array_filter($slug_variations));
    
    error_log("DEBUG: Slug variations: [" . implode(', ', array_map(function($s) { return "'{$s}'"; }, $slug_variations)) . "]");
    error_log("DEBUG: Using case-insensitive videotype matching for source: '{$source}'");
    
    $videos = [];
    $found_slug = null;
    $found_videotype = null;
    $debug_attempts = [];
    $executed_sql = null;
    
    // Try each slug variation with case-insensitive videotype matching
    $total_videos = 0;
    foreach ($slug_variations as $test_slug) {
        // Build WHERE clause with optional category filter
        $where_conditions = ["slug = %s", "LOWER(videotype) = LOWER(%s)"];
        $query_params = [$test_slug, $source];
        
        if (!empty($category)) {
            $where_conditions[] = "category = %s";
            $query_params[] = $category;
        }
        
        // Add search functionality
        if (!empty($search)) {
            $where_conditions[] = "(title LIKE %s OR description LIKE %s OR id LIKE %s OR category LIKE %s OR video_link LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $query_params[] = $search_term;
            $query_params[] = $search_term;
            $query_params[] = $search_term;
            $query_params[] = $search_term;
            $query_params[] = $search_term;
        }
        
        $where_clause = implode(" AND ", $where_conditions);
        
        // First, check if there are any videos for this slug/source/category combination
        $count_query = "SELECT COUNT(*) FROM IONLocalVideos WHERE {$where_clause}";
        $total_videos = $wpdb->get_var($wpdb->prepare($count_query, ...$query_params));
        
        if ($total_videos > 0) {
            // Get paginated videos
            $query = "SELECT * FROM IONLocalVideos WHERE {$where_clause} ORDER BY date_added DESC LIMIT %d OFFSET %d";
            $paginated_params = array_merge($query_params, [$per_page, $offset]);
        
            // Capture the actual SQL being executed for debugging
            $actual_sql = $wpdb->prepare($query, ...$paginated_params);
            error_log("DEBUG: Executing paginated SQL: " . $actual_sql);
        
            $test_videos = $wpdb->get_results($wpdb->prepare($query, ...$paginated_params));
        
        $debug_attempts[] = [
            'slug' => $test_slug,
            'videotype' => $source,
                'total_count' => $total_videos,
                'page_count' => count($test_videos),
                'page' => $page,
                'per_page' => $per_page,
                'query' => "Paginated case-insensitive query executed with slug: {$test_slug}, videotype: {$source}",
            'actual_sql' => $actual_sql
        ];
        
            $videos = $test_videos;
            $found_slug = $test_slug;
            $found_videotype = $source;
            $executed_sql = $actual_sql; // Store the successful SQL for display
            error_log("DEBUG: ✅ SUCCESS! Found {$total_videos} total videos, showing " . count($videos) . " on page {$page} using slug '{$test_slug}' and videotype '{$source}' (case-insensitive)");
            break; // Break out of loop
        } else {
            $debug_attempts[] = [
                'slug' => $test_slug,
                'videotype' => $source,
                'total_count' => 0,
                'query' => "Case-insensitive count query executed with slug: {$test_slug}, videotype: {$source}",
                'actual_sql' => $wpdb->prepare($count_query, $test_slug, $source)
            ];
            error_log("DEBUG: ❌ No videos found for slug '{$test_slug}' and videotype '{$source}' (case-insensitive)");
        }
    }
    
    // If still no videos, debug what's available in the database
    if (empty($videos)) {
        error_log("DEBUG: No videos found with any combination. Checking database contents...");
        
        foreach ($slug_variations as $test_slug) {
            $all_videos_for_slug = $wpdb->get_results($wpdb->prepare("SELECT id, title, video_link, category, description, videotype, date_added FROM IONLocalVideos WHERE slug = %s ORDER BY date_added DESC LIMIT 5", $test_slug));
            if (!empty($all_videos_for_slug)) {
                error_log("DEBUG: Found " . count($all_videos_for_slug) . " total videos for slug '{$test_slug}' but none matching source '{$source}'");
                $all_videotypes = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT videotype FROM IONLocalVideos WHERE slug = %s", $test_slug));
                error_log("DEBUG: Available videotypes for slug '{$test_slug}': [" . implode(', ', array_map(function($vt) { return "'{$vt}'"; }, $all_videotypes)) . "]");
                break;
            }
        }
        
        // Check if there are ANY videos in the table
        $total_videos = $wpdb->get_var("SELECT COUNT(*) FROM IONLocalVideos");
        error_log("DEBUG: Total videos in IONLocalVideos table: {$total_videos}");
        
        if ($total_videos > 0) {
            $sample_videos = $wpdb->get_results("SELECT slug, videotype, COUNT(*) as count FROM IONLocalVideos GROUP BY slug, videotype ORDER BY count DESC LIMIT 20");
            error_log("DEBUG: Sample video data from database (top 20):");
            foreach ($sample_videos as $sample) {
                error_log("DEBUG: - slug: '{$sample->slug}', videotype: '{$sample->videotype}', count: {$sample->count}");
            }
            
            // Look specifically for Tampa-related videos
            $tampa_videos = $wpdb->get_results("SELECT DISTINCT slug, videotype FROM IONLocalVideos WHERE slug LIKE '%tampa%' OR slug LIKE '%Tampa%'");
            if (!empty($tampa_videos)) {
                error_log("DEBUG: Found Tampa-related videos:");
                foreach ($tampa_videos as $tv) {
                    error_log("DEBUG: - Tampa video: slug='{$tv->slug}', videotype='{$tv->videotype}'");
                }
            } else {
                error_log("DEBUG: No Tampa-related videos found in database");
            }
        }
    }
    
    // Get unique categories for filtering using the found slug and videotype (case-insensitive)
    $categories = [];
    if ($found_slug && $found_videotype) {
        $categories = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT category FROM IONLocalVideos WHERE slug = %s AND LOWER(videotype) = LOWER(%s) AND category IS NOT NULL AND category != '' ORDER BY category", $found_slug, $found_videotype));
        error_log("DEBUG: Found " . count($categories) . " categories using case-insensitive videotype matching: [" . implode(', ', $categories) . "]");
    }
    
    error_log("DEBUG: Final result - videos: " . count($videos) . ", categories: " . count($categories));
    error_log("=== VIDEO DEBUG END ===");
    
    header('Content-Type: application/json');
    
    // Calculate pagination metadata
    $total_pages = $total_videos > 0 ? ceil($total_videos / $per_page) : 0;
    $has_prev = $page > 1;
    $has_next = $page < $total_pages;
    
    $response = [
        'success' => true,
        'videos' => $videos,
        'categories' => $categories,
        'total' => $total_videos,
        'page_total' => count($videos),
        'pagination' => [
            'current_page' => $page,
            'per_page' => $per_page,
            'total_pages' => $total_pages,
            'total_items' => $total_videos,
            'has_prev' => $has_prev,
            'has_next' => $has_next,
            'prev_page' => $has_prev ? $page - 1 : null,
            'next_page' => $has_next ? $page + 1 : null
        ],
        'executed_sql' => $executed_sql, // Add the successful SQL query for display for debug
        'debug' => [
            'found_slug' => $found_slug,
            'found_videotype' => $found_videotype,
            'requested_slug' => $slug,
            'requested_source' => $source,
            'attempts' => $debug_attempts,
            'total_attempts' => count($debug_attempts),
            'php_time' => date('Y-m-d H:i:s'),
            'request_method' => $_SERVER['REQUEST_METHOD'],
            'query_string' => $_SERVER['QUERY_STRING']
        ]
    ];
    
    error_log("=== FINAL RESPONSE ===");
    error_log("Response being sent: " . json_encode($response));
    echo json_encode($response);
    exit;
}

// Handle duplicate checking for real-time validation
if (isset($_GET['action']) && $_GET['action'] === 'check_duplicate' && isset($_GET['field']) && isset($_GET['value'])) {
    // SECURITY CHECK: Authenticate API endpoint
    if (!isset($_SESSION['user_email']) || empty($_SESSION['user_email'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Unauthorized access']);
        exit();
    }
    
    $field = sanitize_text_field($_GET['field']);
    $value = sanitize_text_field($_GET['value']);
    $exclude_id = intval($_GET['exclude_id'] ?? 0); // ID to exclude for edit mode
    
    // Only allow checking for specific fields
    $allowed_fields = ['city_name', 'channel_name', 'custom_domain'];
    if (!in_array($field, $allowed_fields)) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid field specified.']);
        exit;
    }
    
    // Skip check if value is empty
    if (empty($value)) {
        header('Content-Type: application/json');
        echo json_encode(['duplicate' => false]);
        exit;
    }
    
    // Check for duplicates, excluding current record if in edit mode
    if ($exclude_id > 0) {
        $existing_record = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE $field = %s AND id != %d LIMIT 1",
            $value, $exclude_id
        ));
        $query_debug = $wpdb->prepare("SELECT id FROM $table WHERE $field = %s AND id != %d LIMIT 1", $value, $exclude_id);
        error_log("Duplicate check (EDIT MODE) - Field: $field, Value: '$value', Exclude ID: $exclude_id, Query: $query_debug, Found: " . (!empty($existing_record) ? 'Yes (ID: ' . $existing_record . ')' : 'No'));
    } else {
        $existing_record = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE $field = %s LIMIT 1",
            $value
        ));
        $query_debug = $wpdb->prepare("SELECT id FROM $table WHERE $field = %s LIMIT 1", $value);
        error_log("Duplicate check (CREATE MODE) - Field: $field, Value: '$value', Query: $query_debug, Found: " . (!empty($existing_record) ? 'Yes (ID: ' . $existing_record . ')' : 'No'));
    }
    
    // Additional debug: show some sample records for the field
    if ($field === 'custom_domain') {
        $sample_records = $wpdb->get_results("SELECT id, custom_domain FROM $table WHERE custom_domain IS NOT NULL AND custom_domain != '' LIMIT 5");
        error_log("Sample custom_domain records: " . print_r($sample_records, true));
    }
    
    header('Content-Type: application/json');
    echo json_encode(['duplicate' => !empty($existing_record)]);
    exit;
}

// Handle video deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_video') {
    // SECURITY CHECK: Authenticate API endpoint
    if (!isset($_SESSION['user_email']) || empty($_SESSION['user_email'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
        exit();
    }
    
    // SECURITY CHECK: Only Owners can delete videos from directory
    if (!in_array($user_role, ['Owner'])) {
        error_log("SECURITY: User {$user_email} with role '{$user_role}' attempted to delete video - DENIED");
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Access denied. Only Owners can delete videos.']);
        exit();
    }
    
    $video_id = intval($_POST['video_id'] ?? 0);
    
    if (!$video_id) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid video ID']);
        exit();
    }
    
    // Get video info before deletion for R2 cleanup
    $video = $wpdb->get_row($wpdb->prepare("SELECT * FROM IONLocalVideos WHERE id = %d", $video_id));
    
    if (!$video) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Video not found']);
        exit();
    }
    
    // Get current user info for permission checks
    $current_user = $wpdb->get_row($wpdb->prepare("SELECT * FROM IONEERS WHERE email = %s", $_SESSION['user_email']));
    
    if (!$current_user) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Current user not found']);
        exit();
    }
    
    // Check delete permissions based on role (same logic as creators.php)
    $can_delete = false;
    if (in_array($current_user->user_role, ['Owner', 'Admin'])) {
        // Owners and Admins can delete ANY video
        $can_delete = true;
        error_log("DIRECTORY DELETE PERMISSION: {$current_user->user_role} can delete any video (video_id: $video_id, owner: {$video->user_id})");
    } else if ($video->user_id == $current_user->user_id) {
        // Other users can only delete their own videos
        $can_delete = true;
        error_log("DIRECTORY DELETE PERMISSION: {$current_user->user_role} can delete own video (video_id: $video_id, user_id: {$current_user->user_id})");
    } else {
        error_log("DIRECTORY DELETE PERMISSION DENIED: {$current_user->user_role} cannot delete video (video_id: $video_id, owner: {$video->user_id}, current_user: {$current_user->user_id})");
    }
    
    if (!$can_delete) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'You do not have permission to delete this video']);
        exit();
    }
    
    // Delete from Cloudflare R2 if it's stored there (include the deletion function from creators.php)
    if (!empty($video->video_link)) {
        $is_r2_url = strpos($video->video_link, 'r2.cloudflarestorage.com') !== false;
        $is_r2_custom_domain = strpos($video->video_link, 'pub-') !== false && strpos($video->video_link, '.r2.dev') !== false;
        $is_r2_cdn = strpos($video->video_link, 'cdn.') !== false;
        $is_r2_public_domain = strpos($video->video_link, 'vid.ions.com') !== false;
        
        $is_youtube = strpos($video->video_link, 'youtube.com') !== false || strpos($video->video_link, 'youtu.be') !== false;
        $is_external_platform = strpos($video->video_link, 'vimeo.com') !== false || strpos($video->video_link, 'rumble.com') !== false;
        
        $is_r2_video = ($is_r2_url || $is_r2_custom_domain || $is_r2_cdn || $is_r2_public_domain) && !$is_youtube && !$is_external_platform;
        
        if ($is_r2_video) {
            error_log("🗑️ DIRECTORY DELETE: Attempting R2 deletion for video ID {$video_id}: {$video->video_link}");
            
            // Include the deleteFromCloudflareR2 function from creators.php
            include_once __DIR__ . '/creators.php';
            
            if (function_exists('deleteFromCloudflareR2')) {
                $r2_result = deleteFromCloudflareR2($video->video_link);
                error_log("🗑️ DIRECTORY DELETE: R2 deletion result: " . print_r($r2_result, true));
            } else {
                error_log("⚠️ DIRECTORY DELETE: deleteFromCloudflareR2 function not available");
            }
        }
    }
    
    // Delete thumbnail file (both local and R2)
    if (!empty($video->thumbnail)) {
        error_log("🗑️ DIRECTORY DELETE: Attempting to delete thumbnail: {$video->thumbnail}");
        
        // Check if thumbnail is on R2
        $is_r2_thumbnail = (
            strpos($video->thumbnail, 'r2.cloudflarestorage.com') !== false ||
            strpos($video->thumbnail, '.r2.dev') !== false ||
            strpos($video->thumbnail, 'vid.ions.com') !== false
        );
        
        if ($is_r2_thumbnail) {
            // Delete from R2
            error_log("🗑️ DIRECTORY DELETE: Deleting R2 thumbnail: {$video->thumbnail}");
            
            // Include the deleteFromCloudflareR2 function from creators.php
            include_once __DIR__ . '/creators.php';
            
            if (function_exists('deleteFromCloudflareR2')) {
                $thumb_r2_result = deleteFromCloudflareR2($video->thumbnail);
                if ($thumb_r2_result['success']) {
                    error_log("✅ DIRECTORY DELETE: R2 thumbnail deleted successfully");
                } else {
                    error_log("⚠️ DIRECTORY DELETE: R2 thumbnail deletion failed: " . $thumb_r2_result['error']);
                }
            }
        } else {
            // Delete local thumbnail file
            $thumbnail_path = $_SERVER['DOCUMENT_ROOT'] . $video->thumbnail;
            if (file_exists($thumbnail_path)) {
                if (unlink($thumbnail_path)) {
                    error_log("✅ DIRECTORY DELETE: Local thumbnail file deleted: $thumbnail_path");
                } else {
                    error_log("⚠️ DIRECTORY DELETE: Failed to delete local thumbnail: $thumbnail_path");
                }
            } else {
                error_log("⚠️ DIRECTORY DELETE: Thumbnail file not found: $thumbnail_path");
            }
        }
    } else {
        error_log("⚠️ DIRECTORY DELETE: No thumbnail found for video {$video_id}");
    }
    
    // Delete the video from IONLocalVideos table
    $result = $wpdb->delete('IONLocalVideos', ['id' => $video_id], ['%d']);
    
    if ($result !== false) {
        error_log("VIDEO DELETE: Successfully deleted video ID: $video_id");
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Video deleted successfully']);
    } else {
        error_log("VIDEO DELETE: Failed to delete video ID: $video_id - " . $wpdb->last_error);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Failed to delete video: ' . $wpdb->last_error]);
    }
    exit();
}

// Handle fetching video categories
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'fetch_categories') {
    // SECURITY CHECK: Authenticate API endpoint
    if (!isset($_SESSION['user_email']) || empty($_SESSION['user_email'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
        exit();
    }
    
    // Fetch distinct categories from IONLocalVideos table
    $categories = $wpdb->get_col("SELECT DISTINCT category FROM IONLocalVideos WHERE category IS NOT NULL AND category != '' ORDER BY category");
    
    if ($categories !== false) {
        error_log("CATEGORIES FETCH: Successfully fetched " . count($categories) . " categories");
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'categories' => $categories]);
    } else {
        error_log("CATEGORIES FETCH: Failed to fetch categories - " . $wpdb->last_error);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Failed to fetch categories: ' . $wpdb->last_error]);
    }
    exit();
}

// Debug endpoint to check response format
if (isset($_GET['debug_video_endpoint'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'debug' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'php_version' => PHP_VERSION,
        'session_exists' => isset($_SESSION['user_email']),
        'post_data' => $_POST
    ]);
    exit;
}

// Handle video addition
if (isset($_POST['action']) && $_POST['action'] === 'add_video') {
    // SECURITY CHECK: Authenticate API endpoint
    if (!isset($_SESSION['user_email']) || empty($_SESSION['user_email'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
        exit();
    }
    
    // Use WordPress functions if available, otherwise use PHP alternatives
    if (function_exists('sanitize_text_field')) {
    $title = sanitize_text_field($_POST['title'] ?? '');
    $category = sanitize_text_field($_POST['category'] ?? '');
    $slug = sanitize_text_field($_POST['slug'] ?? '');
    $videotype = sanitize_text_field($_POST['videotype'] ?? '');
    } else {
        $title = trim(strip_tags($_POST['title'] ?? ''));
        $category = trim(strip_tags($_POST['category'] ?? ''));
        $slug = trim(strip_tags($_POST['slug'] ?? ''));
        $videotype = trim(strip_tags($_POST['videotype'] ?? ''));
    }
    
    // Handle URL sanitization
    if (function_exists('sanitize_url')) {
        $url = sanitize_url($_POST['url'] ?? '');
    } else {
        $url = filter_var($_POST['url'] ?? '', FILTER_SANITIZE_URL);
    }
    // Remove @ symbol from beginning of URL if present
    $url = ltrim($url, '@');
    
    // Handle description
    if (function_exists('sanitize_textarea_field')) {
        $description = sanitize_textarea_field($_POST['description'] ?? '');
    } else {
        $description = trim(strip_tags($_POST['description'] ?? ''));
    }
    
    // Handle special characters that might cause encoding issues - simplified
    if (function_exists('wp_strip_all_tags')) {
        $description = wp_strip_all_tags($description);
    } else {
        $description = strip_tags($description);
    }
    $description = html_entity_decode($description, ENT_QUOTES, 'UTF-8');
    
    // Handle tags
    if (function_exists('sanitize_textarea_field')) {
    $tags = sanitize_textarea_field($_POST['tags'] ?? '');
    } else {
        $tags = trim(strip_tags($_POST['tags'] ?? ''));
    }
    
    // New ENUM fields - use safe sanitization
    if (function_exists('sanitize_text_field')) {
    $layout = sanitize_text_field($_POST['layout'] ?? 'Wide');
    $format = sanitize_text_field($_POST['format'] ?? 'Video');
    $source = sanitize_text_field($_POST['source'] ?? 'Youtube');
    $visibility = sanitize_text_field($_POST['visibility'] ?? 'Public');
    $age = sanitize_text_field($_POST['age'] ?? 'Everyone');
    $geo = sanitize_text_field($_POST['geo'] ?? 'None');
    } else {
        $layout = trim(strip_tags($_POST['layout'] ?? 'Wide'));
        $format = trim(strip_tags($_POST['format'] ?? 'Video'));
        $source = trim(strip_tags($_POST['source'] ?? 'Youtube'));
        $visibility = trim(strip_tags($_POST['visibility'] ?? 'Public'));
        $age = trim(strip_tags($_POST['age'] ?? 'Everyone'));
        $geo = trim(strip_tags($_POST['geo'] ?? 'None'));
    }
    
    // Validate ENUM values
    $valid_layouts = ['Wide', 'Tall', 'Short'];
    $valid_formats = ['Video', 'Movie', 'Series', 'Audio'];
    $valid_sources = ['Youtube', 'Vimeo', 'Muvi', 'Rumble'];
    $valid_visibility = ['Public', 'Private', 'Unlisted'];
    $valid_ages = ['Everyone', 'Only 18+'];
    $valid_geos = ['None', 'Geo-blocking'];
    
    if (!in_array($layout, $valid_layouts)) $layout = 'Wide';
    if (!in_array($format, $valid_formats)) $format = 'Video';
    if (!in_array($source, $valid_sources)) $source = 'Youtube';
    if (!in_array($visibility, $valid_visibility)) $visibility = 'Public';
    if (!in_array($age, $valid_ages)) $age = 'Everyone';
    if (!in_array($geo, $valid_geos)) $geo = 'None';
    
    // Debug - log all received data
    error_log("VIDEO ADD: Received data - title: '$title', url: '$url', slug: '$slug', videotype: '$videotype', source: '$source'");
    
    // Debug - check what we received
    if (empty($title)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Missing title']);
        exit;
    }
    if (empty($url)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Missing URL']);
        exit;
    }
    if (empty($slug)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Missing slug']);
        exit;
    }
    if (empty($videotype)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Missing videotype']);
        exit;
    }
    
    // Insert the video
    $insert_data = [
        'title' => $title,
        'video_link' => $url,
        'category' => $category,
        'description' => $description,
        'slug' => $slug,
        'videotype' => $videotype,
        'tags' => $tags,
        'layout' => $layout,
        'format' => $format,
        'source' => $source,
        'visibility' => $visibility,
        'age' => $age,
        'geo' => $geo,
        'date_added' => date('Y-m-d H:i:s')
    ];
    
    error_log("VIDEO ADD: Attempting to insert data: " . json_encode($insert_data));
    
    // Add additional validation for Muvi URLs
    if ($source === 'Muvi' && !preg_match('/^https?:\/\/[^\/]*muvi\.com\//', $url)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid Muvi URL format. Please ensure the URL is from muvi.com']);
        exit;
    }
    
    // Check URL length (typical MySQL TEXT field limit)
    if (strlen($url) > 65535) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Video URL is too long']);
        exit;
    }
    
    // Validate title length
    if (strlen($title) > 255) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Video title is too long (max 255 characters)']);
        exit;
    }
    
    $insert_result = $wpdb->insert('IONLocalVideos', $insert_data);
    
    if ($insert_result !== false) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Video added successfully.', 'video_id' => $wpdb->insert_id]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Failed to add video. Database error: ' . $wpdb->last_error]);
    }
    exit;
}

// Handle domain detach requests
if (isset($_POST['action']) && $_POST['action'] === 'detach_domain' && isset($_POST['slug']) && isset($_POST['domain'])) {
    // SECURITY CHECK: Authenticate API endpoint
    if (!isset($_SESSION['user_email']) || empty($_SESSION['user_email'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
        exit();
    }
    
    // SECURITY CHECK: Only Owners and Admins can detach domains
    if (!in_array($user_role, ['Owner', 'Admin'])) {
        error_log("SECURITY: User {$user_email} with role '{$user_role}' attempted to detach domain - DENIED");
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Access denied. Only Owners and Admins can manage domains.']);
        exit();
    }
    
    $slug = sanitize_text_field($_POST['slug']);
    $domain = sanitize_text_field($_POST['domain']);
    
    error_log("Domain detach request: slug={$slug}, domain={$domain}");
    
    // Validate that the domain belongs to the specified slug
    $existing_record = $wpdb->get_row($wpdb->prepare(
        "SELECT id, custom_domain FROM $table WHERE slug = %s AND custom_domain = %s",
        $slug,
        $domain
    ));
    
    if (!$existing_record) {
        error_log("Domain detach failed: No matching record found for slug={$slug}, domain={$domain}");
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Domain not found for this listing.']);
        exit;
    }
    
    // Update the record to remove the custom_domain and reset cloudflare_active
    $update_result = $wpdb->update(
        $table,
        [
            'custom_domain' => '',
            'cloudflare_active' => 'missing'
        ],
        ['id' => $existing_record->id],
        ['%s', '%s'],
        ['%d']
    );
    
    if ($update_result !== false) {
        error_log("Domain detach successful: slug={$slug}, domain={$domain}");
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Domain detached successfully.']);
    } else {
        error_log("Domain detach failed: Database update failed for slug={$slug}, domain={$domain}");
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Failed to update database.']);
    }
    exit;
}

// Handle inline domain save requests
if (isset($_POST['action']) && $_POST['action'] === 'save_domain' && isset($_POST['slug']) && isset($_POST['domain'])) {
    // SECURITY CHECK: Authenticate API endpoint
    if (!isset($_SESSION['user_email']) || empty($_SESSION['user_email'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
        exit();
    }
    
    // SECURITY CHECK: Only Owners and Admins can save domains
    if (!in_array($user_role, ['Owner', 'Admin'])) {
        error_log("SECURITY: User {$user_email} with role '{$user_role}' attempted to save domain - DENIED");
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Access denied. Only Owners and Admins can manage domains.']);
        exit();
    }
    
    $slug = sanitize_text_field($_POST['slug']);
    $domain = sanitize_text_field($_POST['domain']);
    
    error_log("Domain save request: slug={$slug}, domain={$domain}");
    
    // Basic domain validation
    if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9-]*[a-zA-Z0-9]*\.[a-zA-Z]{2,}$/', $domain)) {
        error_log("Domain save failed: Invalid domain format for {$domain}");
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid domain format.']);
        exit;
    }
    
    // Check if domain is already in use by another listing
    $existing_domain = $wpdb->get_row("SELECT id, slug, custom_domain FROM $table WHERE custom_domain = %s AND slug != %s", $domain, $slug);
    if ($existing_domain) {
        error_log("Domain save failed: Domain {$domain} already in use by slug {$existing_domain->slug}");
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Domain is already in use by another listing.']);
        exit;
    }
    
    // Find the listing by slug
    $listing = $wpdb->get_row("SELECT id, slug, custom_domain FROM $table WHERE slug = %s", $slug);
    if (!$listing) {
        error_log("Domain save failed: No listing found for slug {$slug}");
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Listing not found.']);
        exit;
    }
    
    // Update the listing with the new domain
    $update_result = $wpdb->update(
        $table,
        [
            'custom_domain' => $domain,
            'cloudflare_active' => 'pending'
        ],
        ['id' => $listing->id]
    );
    
    if ($update_result !== false) {
        error_log("Domain save successful: slug={$slug}, domain={$domain}");
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Domain saved successfully.']);
    } else {
        error_log("Domain save failed: Database update failed for slug={$slug}, domain={$domain}");
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Failed to update database.']);
    }
    exit;
}

// Handle inline domain remove requests
if (isset($_POST['action']) && $_POST['action'] === 'remove_domain' && isset($_POST['slug'])) {
    // SECURITY CHECK: Authenticate API endpoint
    if (!isset($_SESSION['user_email']) || empty($_SESSION['user_email'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
        exit();
    }
    
    $slug = sanitize_text_field($_POST['slug']);
    
    error_log("Domain remove request: slug={$slug}");
    
    // Find the listing by slug
    $listing = $wpdb->get_row("SELECT id, slug, custom_domain FROM $table WHERE slug = %s", $slug);
    if (!$listing) {
        error_log("Domain remove failed: No listing found for slug {$slug}");
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Listing not found.']);
        exit;
    }
    
    // Update the listing to remove the domain
    $update_result = $wpdb->update(
        $table,
        [
            'custom_domain' => '',
            'cloudflare_active' => 'missing'
        ],
        ['id' => $listing->id]
    );
    
    if ($update_result !== false) {
        error_log("Domain remove successful: slug={$slug}");
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Domain removed successfully.']);
    } else {
        error_log("Domain remove failed: Database update failed for slug={$slug}");
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Failed to update database.']);
    }
    exit;
}

// Handle domain connection
if (isset($_POST['action']) && $_POST['action'] === 'connect_domain' && isset($_POST['slug']) && isset($_POST['domain'])) {
    // SECURITY CHECK: Authenticate API endpoint
    if (!isset($_SESSION['user_email']) || empty($_SESSION['user_email'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
        exit();
    }
    
    $slug = sanitize_text_field($_POST['slug']);
    $domain = sanitize_text_field($_POST['domain']);
    
    error_log("Domain connect request: slug={$slug}, domain={$domain}");
    
    // Basic domain validation
    if (!preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)*$/i', $domain)) {
        error_log("Domain connect failed: Invalid domain format: {$domain}");
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid domain format.']);
        exit;
    }
    
    // Check if domain is already in use
    $existing_domain = $wpdb->get_row($wpdb->prepare(
        "SELECT id, slug, custom_domain FROM $table WHERE custom_domain = %s",
        $domain
    ));
    
    if ($existing_domain) {
        error_log("Domain connect failed: Domain {$domain} already in use by slug {$existing_domain->slug}");
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'This domain is already connected to another channel.']);
        exit;
    }
    
    // Find the listing by slug
    $listing = $wpdb->get_row($wpdb->prepare(
        "SELECT id, slug, custom_domain FROM $table WHERE slug = %s",
        $slug
    ));
    
    if (!$listing) {
        error_log("Domain connect failed: No listing found for slug {$slug}");
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Listing not found.']);
        exit;
    }
    
    // Update the listing to add the domain
    $update_result = $wpdb->update(
        $table,
        [
            'custom_domain' => $domain,
            'cloudflare_active' => 'pending'
        ],
        ['id' => $listing->id]
    );
    
    if ($update_result !== false) {
        error_log("Domain connect successful: slug={$slug}, domain={$domain}");
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Domain connected successfully. Cloudflare setup is pending.']);
    } else {
        error_log("Domain connect failed: Database update failed for slug={$slug}, domain={$domain}");
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Failed to update database.']);
    }
    exit;
}

$view = $_GET['view'] ?? 'grid';
$map_type = ($view === 'map') ? ($_GET['map_type'] ?? 'us') : 'us';
$search = trim($_GET['q'] ?? '');
$status_filter = $_GET['status'] ?? '';
$country_filter = strtoupper($_GET['country'] ?? '');
$state_filter = strtoupper($_GET['state'] ?? '');
$sort = $_GET['sort'] ?? 'population';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

// AJAX Detection
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

/**
 * Parse zip code query with optional radius - COPIED FROM WORKING IONLOCALBLAST.PHP
 * Format: "zipcode" or "zipcode:radius" (e.g., "90210" or "90210:50")
 * Returns: ['zipcode' => string, 'radius' => int] or null if not a zip code query
 */
function parseZipCodeQuery($search) {
    $search = trim($search);
    
    // Check if it's a zip code format (digits only, or digits:radius)
    if (!preg_match('/^(\d{5})(?::(\d+))?$/', $search, $matches)) {
        return null;
    }
    
    $zipcode = $matches[1];
    $radius = isset($matches[2]) ? (int)$matches[2] : 50; // Default 50 mile radius
    
    return ['zipcode' => $zipcode, 'radius' => $radius];
}

/**
 * Get coordinates for a zip/postal code using IONGeoCodes table
 * This is the same implementation used in ionlocalblast.php and channel-bundle-manager.php
 */
function getZipCodeCoordinates($zip_code) {
    try {
        global $wpdb;
        
        // Get PDO connection from the database class
        $pdo = $wpdb->getPDO();
        
        // Check if IONGeoCodes table exists and has data
        $table_check = $pdo->query("SHOW TABLES LIKE 'IONGeoCodes'")->fetchAll();
        if (empty($table_check)) {
            error_log("IONGeoCodes table does not exist");
            return null;
        }
        
        // Query IONGeoCodes table directly
        $sql = "SELECT geo_point, official_usps_city_name, official_state_name, zip_code FROM IONGeoCodes WHERE zip_code = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$zip_code]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        error_log("ZIP DEBUG: Looking up zip code '$zip_code' - Found: " . ($result ? 'YES' : 'NO'));
        if ($result) {
            error_log("ZIP DEBUG: Result: " . json_encode($result));
        }
        
        if ($result && !empty($result['geo_point'])) {
            $coords = explode(', ', $result['geo_point']);
            if (count($coords) === 2) {
                $coordinates = [
                    'lat' => floatval(trim($coords[0])),
                    'lng' => floatval(trim($coords[1]))
                ];
                error_log("ZIP DEBUG: Zip code '$zip_code' found: " . json_encode($coordinates) . " ({$result['official_usps_city_name']}, {$result['official_state_name']})");
                return $coordinates;
            }
        }
        
        // Fallback: Try to find any zip code with similar prefix
        $prefix = substr($zip_code, 0, 3);
        $fallback_sql = "SELECT geo_point, official_usps_city_name, official_state_name, zip_code FROM IONGeoCodes WHERE zip_code LIKE ? LIMIT 1";
        $fallback_stmt = $pdo->prepare($fallback_sql);
        $fallback_stmt->execute([$prefix . '%']);
        $fallback_result = $fallback_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($fallback_result && !empty($fallback_result['geo_point'])) {
            $coords = explode(', ', $fallback_result['geo_point']);
            if (count($coords) === 2) {
                $coordinates = [
                    'lat' => floatval(trim($coords[0])),
                    'lng' => floatval(trim($coords[1]))
                ];
                error_log("ZIP DEBUG: Fallback zip code found for '$zip_code': " . json_encode($coordinates) . " ({$fallback_result['official_usps_city_name']}, {$fallback_result['official_state_name']}) - using zip {$fallback_result['zip_code']}");
                return $coordinates;
            }
        }
        
        error_log("ZIP DEBUG: No coordinates found for zip code '$zip_code'");
        return null;
        
    } catch (Exception $e) {
        error_log("ZIP DEBUG: Error looking up zip code '$zip_code': " . $e->getMessage());
        return null;
    }
}

// Use EXACT same base WHERE conditions as working ionlocalblast.php
$where = "WHERE slug IS NOT NULL AND city_name IS NOT NULL";
$order_relevance = "";
error_log("SEARCH DEBUG: Base WHERE clause (copied from ionlocalblast.php): " . $where);

if ($search !== '') {
    error_log("SEARCH DEBUG: Starting search for term: '{$search}'");
    
    // Check if search term looks like a zip/postal code (EXACT same logic as ionlocalblast.php)
    $is_zip = preg_match('/^\d{4,6}([-,.]\s*\d+)?$/', $search);
    
    if ($is_zip) {
        error_log("SEARCH DEBUG: Zip code search detected for: {$search}");
        // Zip code search with distance calculation (same as ionlocalblast.php)
        $zip_data = parseZipCodeQuery($search);
        $zip_code = $zip_data['zipcode'];
        $radius = $zip_data['radius'];
        
        $coords = getZipCodeCoordinates($zip_code);
        if ($coords) {
            // Set zip search variables for distance sorting
            $is_zip_search = true;
            $zip_coords = $coords;
            
            // For zip code searches, default to distance sorting unless explicitly overridden
            if (!isset($_GET['sort']) || $_GET['sort'] === '') {
                $orderBy = 'distance';
                error_log("SEARCH DEBUG: Zip code search detected, defaulting to distance sorting");
            }
            
            // Use geographic distance calculation
            $lat = $coords['lat'];
            $lng = $coords['lng'];
            
            $distance_condition = "
                (3959 * acos(
                    cos(radians($lat)) * 
                    cos(radians(COALESCE(latitude, 0))) * 
                    cos(radians(COALESCE(longitude, 0)) - radians($lng)) + 
                    sin(radians($lat)) * 
                    sin(radians(COALESCE(latitude, 0)))
                )) <= $radius
            ";
            
            $where .= " AND ($distance_condition)";
            error_log("SEARCH DEBUG: Using geographic zip code search for $zip_code within {$radius} miles");
        } else {
            // Fallback to text search for zip code
            $search_term = '%' . $zip_code . '%';
            $where .= $wpdb->prepare(" AND (city_name LIKE %s OR custom_domain LIKE %s)", $search_term, $search_term);
            error_log("SEARCH DEBUG: Using text fallback for zip code: $zip_code");
        }
    } else {
        // Text search - Case-insensitive with space/no-space variations using UPPER()
        error_log("SEARCH DEBUG: Text search for: {$search}");
        
        // Create search variations (all uppercase for case-insensitive matching)
        $original_term = '%' . strtoupper($search) . '%';
        $no_spaces_term = '%' . strtoupper(str_replace(' ', '', $search)) . '%';
        
        // Build comprehensive search conditions
        $search_conditions = [];
        $search_params = [];
        
        // For each field, search both with original spacing and without spaces
        $fields = ['city_name', 'state_name', 'country_name', 'channel_name', 'slug', 'custom_domain'];
        
        foreach ($fields as $field) {
            // Original field with original search term (case-insensitive)
            $search_conditions[] = "UPPER($field) LIKE %s";
            $search_params[] = $original_term;
            
            // Original field with no-spaces search term (case-insensitive)
            if ($no_spaces_term !== $original_term) {
                $search_conditions[] = "UPPER($field) LIKE %s";
                $search_params[] = $no_spaces_term;
            }
            
            // Field with spaces removed, searched with original term (case-insensitive)
            $search_conditions[] = "UPPER(REPLACE($field, ' ', '')) LIKE %s";
            $search_params[] = $original_term;
            
            // Field with spaces removed, searched with no-spaces term (case-insensitive)
            if ($no_spaces_term !== $original_term) {
                $search_conditions[] = "UPPER(REPLACE($field, ' ', '')) LIKE %s";
                $search_params[] = $no_spaces_term;
            }
        }
        
        // Combine all conditions with OR
        $where .= $wpdb->prepare(" AND (" . implode(' OR ', $search_conditions) . ")", ...$search_params);
        
        error_log("SEARCH DEBUG: Using case-insensitive search with space variations and UPPER() functions");
        error_log("SEARCH DEBUG: Original term: $original_term, No-spaces term: $no_spaces_term");
        error_log("SEARCH DEBUG: This will match 'New York'↔'NewYork', 'dallas'↔'Dallas', 'new port beach'↔'newport beach', etc.");
    }
}
if ($status_filter !== '') {
    // Handle Cloudflare status filters
    if (strpos($status_filter, 'cf-') === 0) {
        $cf_status = substr($status_filter, 3); // Remove 'cf-' prefix
        $where .= $wpdb->prepare(" AND LOWER(cloudflare_active) = %s", strtolower($cf_status));
    } elseif ($status_filter === 'domain-linked') {
        // Filter for sites with domains
        $where .= " AND (custom_domain IS NOT NULL AND custom_domain != '')";
    } elseif ($status_filter === 'domain-missing') {
        // Filter for sites without domains
        $where .= " AND (custom_domain IS NULL OR custom_domain = '')";
    } else {
        // Regular status filter
        $where .= $wpdb->prepare(" AND status = %s", $status_filter);
    }
}
// Country filter no longer restricts search scope (global search)
// State filter no longer restricts search scope (global search)
error_log('directory.php: SQL WHERE clause constructed.');
error_log("SEARCH DEBUG: Final WHERE clause: " . $where);
error_log("SEARCH DEBUG: Search term: '" . $search . "'");
error_log("SEARCH DEBUG: Country filter: '" . $country_filter . "'");
error_log("SEARCH DEBUG: State filter: '" . $state_filter . "'");
error_log("SEARCH DEBUG: Status filter: '" . $status_filter . "'");

$count_query = "SELECT COUNT(*) FROM $table $where";
error_log("SEARCH DEBUG: Count query: " . $count_query);
error_log("SEARCH DEBUG: Table name: " . $table);
$total = $wpdb->get_var($count_query);

// Build the SELECT clause with optional distance calculation for zip searches (AFTER search logic sets variables)
$select_clause = "SELECT *";
if ($is_zip_search && $zip_coords) {
    $lat = $zip_coords['lat'];
    $lng = $zip_coords['lng'];
    $select_clause = "SELECT *, 
        (3959 * acos(
            cos(radians($lat)) * 
            cos(radians(COALESCE(latitude, 0))) * 
            cos(radians(COALESCE(longitude, 0)) - radians($lng)) + 
            sin(radians($lat)) * 
            sin(radians(COALESCE(latitude, 0)))
        )) AS distance_miles";
    error_log("SEARCH DEBUG: Added distance calculation to SELECT clause for zip code search");
}

if ($wpdb->last_error) {
    error_log('directory.php: ERROR in count query: ' . $wpdb->last_error);
} else {
    error_log('directory.php: Total records query executed. Total count: ' . $total);
    
    // Additional debugging for search results
    if ($search !== '' && $total == 0) {
        error_log("SEARCH DEBUG: No results found for search term: '" . $search . "'");
        // Let's check if there are any records at all in the table
        $total_records = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        error_log("SEARCH DEBUG: Total records in table: " . $total_records);
        
        // Check if there are any records that might contain similar terms
        $sample_records = $wpdb->get_results("SELECT city_name, state_name, country_name FROM $table LIMIT 5");
        error_log("SEARCH DEBUG: Sample records: " . json_encode($sample_records));
    }
}

$validSorts = ['city_name', 'state_name', 'country_name', 'custom_domain', 'status', 'population', 'distance'];
$orderBy = in_array($sort, $validSorts) ? $sort : 'population';

// Initialize zip search variables (will be set by the main search logic)
$is_zip_search = false;
$zip_coords = null;
if ($sort === 'state_name') {
    $orderBy = '(state_name = "" OR state_name IS NULL), state_name';
} elseif ($sort === 'country_name') {
    $orderBy = '(country_name = "" OR country_name IS NULL), country_name';
} elseif ($sort === 'population') {
    $orderBy = "CAST(REPLACE(REPLACE(population, ' ', ''), ',', '') AS UNSIGNED) DESC";
} elseif ($sort === 'distance' && $is_zip_search && $zip_coords) {
    // Distance sorting is only available for zip code searches
    $orderBy = "distance_miles ASC";
    error_log("SEARCH DEBUG: Using distance sorting for zip code search");
}

// Add relevance scoring if needed (after SELECT clause is built)
if ($search !== '' && !empty($order_relevance)) {
    $select_clause .= $order_relevance;
    // When searching, prioritize relevance over other sorting
    $orderBy = "relevance_score DESC, " . $orderBy;
    error_log("SEARCH DEBUG: Using relevance-based ordering");
}

// If a country is selected, bubble it to the top WITHOUT restricting the search scope.
$countryPriority = '';
if ($country_filter !== '') {
    // Rows from the selected country first (0), others after (1)
    $countryPriority = $wpdb->prepare('CASE WHEN country_code = %s THEN 0 ELSE 1 END', $country_filter);
    // Prepend this to ORDER BY (after relevance if present)
    $orderBy = ($orderBy ? $countryPriority . ', ' . $orderBy : $countryPriority);
}
// Deterministic tie-breakers
$orderBy .= ', country_name, state_name, city_name';

$query = $wpdb->prepare("$select_clause FROM $table $where ORDER BY $orderBy LIMIT %d OFFSET %d", $perPage, $offset);
error_log("SEARCH DEBUG: Final main query: " . $query);
error_log("SEARCH DEBUG: WHERE clause: " . $where);
error_log("SEARCH DEBUG: ORDER BY clause: " . $orderBy);
$cities = $wpdb->get_results($query);

// Store debug information for frontend display
$debug_info = [
    'search_term' => $search,
    'count_query' => $count_query,
    'main_query' => $query,
    'total_found' => $total,
    'results_returned' => count($cities),
    'where_clause' => $where,
    'order_by' => $orderBy,
    'page' => $page,
    'per_page' => $perPage,
    'offset' => $offset
];

if ($wpdb->last_error) {
    error_log('directory.php: FATAL WPDB Error: ' . $wpdb->last_error);
} else {
    error_log('directory.php: Main query executed. Found ' . count($cities) . ' cities.');
    if ($search !== '' && count($cities) > 0) {
        error_log("SEARCH DEBUG: First result: " . json_encode($cities[0]));
    }
}

error_log("DIRECTORY: Status filter: " . (isset($status_filter) ? $status_filter : 'undefined'));

// Initialize video counts array BEFORE AJAX response
$counts = [];

if (!empty($cities)) {
    $slugs = array_column($cities, 'slug');
    error_log('VIDEO DEBUG: Found ' . count($slugs) . ' slugs from cities: ' . implode(', ', array_slice($slugs, 0, 5)));
    
    // Create a mapping of possible slug variations to match IONLocalVideos
    $slug_mapping = [];
    $all_video_query_parts = [];
    
    foreach ($slugs as $network_slug) {
        // Try multiple slug variations to match IONLocalVideos format
        $variations = [
            $network_slug,                    // exact match
            'ion-' . $network_slug,          // add ion- prefix
            str_replace('-', '', $network_slug), // remove dashes
            'ion' . $network_slug,           // add ion prefix without dash
        ];
        
        foreach ($variations as $variation) {
            $slug_mapping[$variation] = $network_slug;
            $all_video_query_parts[] = $wpdb->prepare("slug = %s", $variation);
        }
    }
    
    if (!empty($all_video_query_parts)) {
        $video_query = "SELECT slug, videotype, COUNT(*) as count FROM IONLocalVideos WHERE (" . implode(' OR ', $all_video_query_parts) . ") GROUP BY slug, videotype";
        error_log('VIDEO DEBUG: Enhanced slug matching query: ' . $video_query);
        
        $video_counts = $wpdb->get_results($video_query);
        error_log('VIDEO DEBUG: Found ' . count($video_counts) . ' video count rows with enhanced matching');
        
        if (!empty($video_counts)) {
            error_log('VIDEO DEBUG: First few results: ' . json_encode(array_slice($video_counts, 0, 3)));
        }
        
        foreach ($video_counts as $row) {
            $videotype_lower = strtolower(trim($row->videotype));
            $video_slug = $row->slug;
            
            // Map the video slug back to the network slug
            $network_slug = $slug_mapping[$video_slug] ?? $video_slug;
            
            $counts[$network_slug][$videotype_lower] = $row->count;
            error_log("VIDEO DEBUG: Mapping video slug '{$video_slug}' to network slug '{$network_slug}', videotype '{$row->videotype}' (lowercase: '{$videotype_lower}'), count: {$row->count}");
            
            // Debug: Show what keys we're actually creating
            if (!isset($debug_shown_videotypes)) $debug_shown_videotypes = [];
            if (!in_array($videotype_lower, $debug_shown_videotypes)) {
                error_log("VIDEO DEBUG: New videotype key created: '{$videotype_lower}'");
                $debug_shown_videotypes[] = $videotype_lower;
            }
        }
    }
    
    error_log('VIDEO DEBUG: Final counts array keys: ' . implode(', ', array_keys($counts)));
    
    // Debug: Show sample counts for first few slugs
    $sample_slugs = array_slice(array_keys($counts), 0, 3);
    foreach ($sample_slugs as $slug) {
        $slug_counts = $counts[$slug] ?? [];
        error_log("VIDEO DEBUG: Sample counts for slug '{$slug}': " . json_encode($slug_counts));
    }
} else {
    error_log('VIDEO DEBUG: No cities found, skipping video count calculation');
}

// Handle AJAX requests - return HTML fragment for search results
if ($is_ajax) {
    error_log('directory.php: AJAX request detected, returning HTML fragment');
    
    // Clear any output that might have been generated
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    try {
        // Calculate pagination info - fix "1 of 0 pages" issue
        $total_pages = $total > 0 ? ceil($total / $perPage) : 0;
        $has_next = $page < $total_pages;
        $has_prev = $page > 1;
        $showing_start = $total > 0 ? $offset + 1 : 0;
        $showing_end = min($offset + count($cities), $total);
        
        // Debug pagination calculation
        error_log("PAGINATION DEBUG: total=$total, perPage=$perPage, page=$page, total_pages=$total_pages, has_next=" . ($has_next ? 'true' : 'false') . ", has_prev=" . ($has_prev ? 'true' : 'false'));
        
        // Generate HTML content inline
        ob_start();
        
        if ($view === 'grid') {
            // Grid view HTML - match the original structure
            if (empty($cities)) {
                if (!empty(trim($search))) {
                    echo '<p class="no-results">';
                    echo 'No channels found for "' . htmlspecialchars($search) . '".';
                    echo '<br><small>Try a <a href="?q=' . urlencode($search) . '&view=' . htmlspecialchars($view) . '&sort=' . htmlspecialchars($sort) . '&status=&country=&state=&page=1" class="global-search-link">global search</a> with all filters turned off.</small>';
                    echo '</p>';
                } else {
                    echo '<p class="no-results">No channels available.</p>';
                }
            } else {
                echo '<div class="city-grid">';
                foreach ($cities as $city) {
                    if ($city === null || !is_object($city)) continue;
                    
                    echo '<div class="city ' . strtolower((string)$city->status) . '" data-city-id="' . htmlspecialchars($city->id) . '" data-city-slug="' . htmlspecialchars($city->slug) . '">';
                    
                    // Card actions (edit button)
                    if ($can_add_edit) {
                        echo '<div class="card-actions">';
                        echo '<button class="edit-card-btn" title="Edit this listing" data-city-id="' . htmlspecialchars($city->id) . '">';
                        echo '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">';
                        echo '<path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293l6.5-6.5zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>';
                        echo '</svg>';
                        echo '</button>';
                        echo '</div>';
                    }
                    
                    // City name and state
                    echo '<strong>';
                    echo '<a href="' . htmlspecialchars($city->page_URL) . '" target="_blank">';
                    echo htmlspecialchars($city->city_name);
                    if (!empty($city->city_name) && !empty($city->state_name)) echo ', ';
                    echo htmlspecialchars($city->state_name);
                    echo '</a>';
                    echo '</strong><br>';
                    
                    // Domain row
                    echo '<div class="domain-row">';
                    if ($city->custom_domain) {
                        $domain_parts = explode('/', $city->custom_domain, 2);
                        $main_domain = $domain_parts[0];
                        $domain_path = isset($domain_parts[1]) ? '/' . $domain_parts[1] : '';
                        
                        echo '<div class="domain-content">';
                        if (strtolower($city->status) === 'live') {
                            echo '<a href="https://' . htmlspecialchars($city->custom_domain) . '" target="_blank" class="domain-link">';
                            echo '<span class="domain-main">' . htmlspecialchars($main_domain) . '</span>';
                            if ($domain_path) echo '<span class="domain-path">' . htmlspecialchars($domain_path) . '</span>';
                            echo '</a>';
                        } elseif (strtolower($city->status) === 'preview') {
                            echo '<a href="https://' . htmlspecialchars($city->custom_domain) . '" target="_blank" class="domain-link">';
                            echo '<span class="domain-main">' . htmlspecialchars($main_domain) . '</span>';
                            if ($domain_path) echo '<span class="domain-path">' . htmlspecialchars($domain_path) . '</span>';
                            echo '</a>';
                        } else {
                            echo '<span class="domain-main">' . htmlspecialchars($main_domain) . '</span>';
                            if ($domain_path) echo '<span class="domain-path">' . htmlspecialchars($domain_path) . '</span>';
                        }
                        echo '</div>';
                        
                        // Domain management icons
                        if (strtolower($city->status) !== 'active') {
                            echo '<div class="domain-management-icons">';
                            
                            // Cloudflare Status Icon
                            $cloudflare_status = strtolower(trim($city->cloudflare_active ?? 'missing'));
                            $cf_class = '';
                            $cf_icon = '';
                            switch($cloudflare_status) {
                                case 'active':
                                    $cf_class = 'cf-active';
                                    $cf_icon = '/assets/icons/cloudflare.svg';
                                    break;
                                case 'pending':
                                case 'inactive':
                                    $cf_class = 'cf-inactive';
                                    $cf_icon = '/assets/icons/cloudflare-red.svg';
                                    break;
                                default: // missing
                                    $cf_class = 'cf-missing';
                                    $cf_icon = '/assets/icons/cloudflare-off.svg';
                                    break;
                            }
                            
                            echo '<div class="domain-icon cloudflare-status ' . $cf_class . '" title="Cloudflare Status: ' . ucfirst($cloudflare_status) . '">';
                            echo '<img src="' . $cf_icon . '" alt="Cloudflare Status" />';
                            echo '</div>';
                            
                            // Remove Domain Icon
                            echo '<div class="domain-icon remove-domain" title="Detach this domain" data-slug="' . htmlspecialchars($city->slug) . '" data-domain="' . htmlspecialchars($city->custom_domain) . '">';
                            echo '<img src="/assets/icons/remove.svg" alt="Remove Domain" class="default-icon" />';
                            echo '<img src="/assets/icons/remove-bold.svg" alt="Remove Domain" class="hover-icon" style="display: none;" />';
                            echo '</div>';
                            
                            echo '</div>';
                        }
                    } else {
                        // No domain - show connect option
                        if (strtolower($city->status) !== 'active') {
                            echo '<div class="domain-management-icons">';
                            echo '<div class="domain-icon connect-domain" title="Link a new domain" data-slug="' . htmlspecialchars($city->slug) . '">';
                            echo '<img src="/assets/icons/connect.svg" alt="Connect Domain" class="default-icon" style="color: green"/>';
                            echo '<img src="/assets/icons/connect-bold.svg" alt="Connect Domain" class="hover-icon" style="color: green; display: none;" />';
                            echo '</div>';
                            echo '</div>';
                        }
                    }
                    echo '</div>';
                    
                    // City info row with flag, population, and video counts
                    $country_code = strtolower($city->country_code ?? 'xx');
                    $country_name = $city->country_name ?? 'Unknown';
                    
                    echo '<div class="city-info-row">';
                    echo '<div class="meta-column">';
                    echo '<span class="meta-info">';
                    echo '<img src="/assets/flags/' . htmlspecialchars($country_code) . '.svg" alt="' . htmlspecialchars($country_code) . ' Flag" class="flags" />';
                    echo htmlspecialchars($country_name);
                    echo '</span><br>';
                    echo '<span class="meta-info">';
                    echo 'Population: ' . ($city->population ? number_format((int)preg_replace('/[^\d]/', '', $city->population)) : 'N/A');
                    echo '</span><br>';
                    // Show distance for zip code searches
                    if ($is_zip_search && isset($city->distance_miles)) {
                        echo '<span class="meta-info distance-info">';
                        echo 'Distance: ' . number_format($city->distance_miles, 1) . ' miles';
                        echo '</span><br>';
                    }
                    echo '<span class="meta-info">';
                    echo 'Traffic: -';
                    echo '</span><br>';
                    echo '</div>';
                    
                    // Video counts column
                    echo '<div class="video-column">';
                    
                    // YouTube
                    echo '<div class="video-count yt" data-slug="' . htmlspecialchars($city->slug) . '" data-source="youtube">';
                    echo '<span class="icon yt-icon">';
                    echo '<img src="/assets/icons/youtube.svg" alt="YouTube" class="icon-img" />';
                    echo '</span>';
                    echo '(<small>' . number_format($counts[$city->slug]['youtube'] ?? $counts[$city->slug]['Youtube'] ?? 0) . '</small>)';
                    echo '</div>';
                    
                    // Muvi
                    echo '<div class="video-count muvi" data-slug="' . htmlspecialchars($city->slug) . '" data-source="muvi">';
                    echo '<span class="icon muvi-icon">';
                    echo '<img src="/assets/icons/muvi.svg" alt="Muvi" class="icon-img" />';
                    echo '</span>';
                    echo '(<small>' . number_format($counts[$city->slug]['muvi'] ?? $counts[$city->slug]['Muvi'] ?? 0) . '</small>)';
                    echo '</div>';
                    
                    // Vimeo
                    echo '<div class="video-count vimeo" data-slug="' . htmlspecialchars($city->slug) . '" data-source="vimeo">';
                    echo '<span class="icon vimeo-icon">';
                    echo '<img src="/assets/icons/vimeo.svg" alt="Vimeo" class="icon-img" />';
                    echo '</span>';
                    echo '(<small>' . number_format($counts[$city->slug]['vimeo'] ?? $counts[$city->slug]['Vimeo'] ?? 0) . '</small>)';
                    echo '</div>';
                    
                    echo '</div>'; // .video-column
                    echo '</div>'; // .city-info-row
                    echo '</div>'; // .city
                }
                echo '</div>'; // .city-grid
            }
        } else {
            // List view HTML - match the original structure
            if (empty($cities)) {
                if (!empty(trim($search))) {
                    echo '<p class="no-results">';
                    echo 'No channels found for "' . htmlspecialchars($search) . '".';
                    echo '<br><small>Try a <a href="?q=' . urlencode($search) . '&view=' . htmlspecialchars($view) . '&sort=' . htmlspecialchars($sort) . '&status=&country=&state=&page=1" class="global-search-link">global search</a> with all filters turned off.</small>';
                    echo '</p>';
                } else {
                    echo '<p class="no-results">No channels found.</p>';
                }
            } else {
                echo '<div class="city-list">';
                echo '<table class="city-table">';
                echo '<thead>';
                echo '<tr>';
                echo '<th>Channel</th>';
                echo '<th>Location</th>';
                echo '<th>Population</th>';
                if ($is_zip_search) {
                    echo '<th>Distance</th>';
                }
                echo '<th>Status</th>';
                echo '<th>Domain</th>';
                echo '<th>Videos</th>';
                if ($can_add_edit) {
                    echo '<th>Actions</th>';
                }
                echo '</tr>';
                echo '</thead>';
                echo '<tbody>';
                
                foreach ($cities as $city) {
                    $country_code = strtolower($city->country_code ?? 'xx');
                    $country_name = $city->country_name ?? 'Unknown';
                    
                    echo '<tr class="city-row ' . strtolower((string)$city->status) . '">';
                    
                    // Channel name
                    echo '<td class="channel-name">';
                    echo '<strong>' . htmlspecialchars($city->city_name ?? 'Unknown') . '</strong>';
                    if ($city->channel_name && $city->channel_name !== $city->city_name) {
                        echo '<br><small>' . htmlspecialchars($city->channel_name) . '</small>';
                    }
                    echo '</td>';
                    
                    // Location
                    echo '<td class="location">';
                    echo '<img src="/assets/flags/' . htmlspecialchars($country_code) . '.svg" alt="' . htmlspecialchars($country_code) . ' Flag" class="flags" />';
                    echo htmlspecialchars($city->state_name ?? '') . ', ' . htmlspecialchars($country_name);
                    echo '</td>';
                    
                    // Population
                    echo '<td class="population">';
                    echo $city->population ? number_format((int)preg_replace('/[^\d]/', '', $city->population)) : 'N/A';
                    echo '</td>';
                    
                    // Distance (for zip searches)
                    if ($is_zip_search) {
                        echo '<td class="distance">';
                        if (isset($city->distance_miles)) {
                            echo '<span class="distance-info">' . number_format($city->distance_miles, 1) . ' miles</span>';
                        } else {
                            echo 'N/A';
                        }
                        echo '</td>';
                    }
                    
                    // Status
                    echo '<td class="status">';
                    echo '<span class="status-badge ' . strtolower((string)$city->status) . '">';
                    echo ucfirst((string)$city->status);
                    echo '</span>';
                    echo '</td>';
                    
                    // Domain
                    echo '<td class="domain">';
                    if ($city->custom_domain) {
                        echo '<a href="https://' . htmlspecialchars($city->custom_domain) . '" target="_blank">';
                        echo htmlspecialchars($city->custom_domain);
                        echo '</a>';
                    } else {
                        echo '<span class="no-domain">No domain</span>';
                    }
                    echo '</td>';
                    
                    // Videos
                    echo '<td class="videos">';
                    echo '<span class="video-count yt" data-slug="' . htmlspecialchars($city->slug) . '" data-source="youtube">0</span>';
                    echo '</td>';
                    
                    // Actions
                    if ($can_add_edit) {
                        echo '<td class="actions">';
                        echo '<button class="edit-card-btn" title="Edit this listing" data-city-id="' . htmlspecialchars($city->id) . '">';
                        echo '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">';
                        echo '<path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293l6.5-6.5zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>';
                        echo '</svg>';
                        echo '</button>';
                        echo '</td>';
                    }
                    
                    echo '</tr>';
                }
                
                echo '</tbody>';
                echo '</table>';
                echo '</div>';
            }
        }
        
        $html_content = ob_get_clean();
        
        // Return JSON response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'html' => $html_content,
            'total' => $total,
            'page' => $page,
            'total_pages' => $total_pages,
            'showing_start' => $showing_start,
            'showing_end' => $showing_end,
            'has_next' => $has_next,
            'has_prev' => $has_prev,
            'search_term' => $search
        ]);
        
    } catch (Exception $e) {
        error_log('AJAX Error: ' . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Search failed: ' . $e->getMessage()
        ]);
    }
    exit;
}

// Video counts already calculated above before AJAX response

// Load countries from countries.php file instead of database
$countries_file = __DIR__ . '/countries.php';
error_log('directory.php: Attempting to load countries from: ' . $countries_file);
error_log('directory.php: File exists? ' . (file_exists($countries_file) ? 'YES' : 'NO'));
$countries = require $countries_file; // Load countries list
error_log('directory.php: Raw countries loaded. Type: ' . gettype($countries) . ', Count: ' . (is_array($countries) ? count($countries) : 'N/A'));
// Move US to front of list if it exists
$us_country = null;
$other_countries = [];
foreach ($countries as $c) {
    if (!is_array($c) || !isset($c['code'], $c['name'])) continue; // Safety check
    if ($c['code'] === 'US') {
        $us_country = $c;
    } else {
        $other_countries[] = $c;
    }
}
if ($us_country) {
    array_unshift($other_countries, $us_country);
    $countries = $other_countries; // Now assign the complete list with US at front
    error_log('directory.php: US found and moved to front.');
} else {
    $countries = $other_countries; // No US found, use the other countries
    error_log('directory.php: No US found in countries list.');
}
error_log('directory.php: Country list processed. Found ' . count($countries) . ' countries.');
if (count($countries) > 0) {
    error_log('directory.php: First 3 countries: ' . json_encode(array_slice($countries, 0, 3)));
} else {
    error_log('directory.php: ERROR - Countries array is empty!');
}

// Get status counts for filters, considering current filters
$status_counts_transient_key = 'status_counts_' . md5($where);
$status_counts = get_transient($status_counts_transient_key);

if (false === $status_counts) {
    // Get regular status counts
    $status_counts_results = $wpdb->get_results("SELECT status, COUNT(*) as cnt FROM {$table} {$where} GROUP BY status");
    $status_counts = [];
    foreach ($status_counts_results as $row) {
        if (!empty($row->status)) {
            $status_counts[strtolower($row->status)] = $row->cnt;
        }
    }
    
    // Get Cloudflare status counts
    $cf_counts_results = $wpdb->get_results("SELECT LOWER(cloudflare_active) as cf_status, COUNT(*) as cnt FROM {$table} {$where} GROUP BY LOWER(cloudflare_active)");
    foreach ($cf_counts_results as $row) {
        if (!empty($row->cf_status)) {
            $status_counts['cf-' . $row->cf_status] = $row->cnt;
        }
    }
    
    // Get domain presence counts
    $domain_linked_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table} {$where} AND (custom_domain IS NOT NULL AND custom_domain != '')");
    $domain_missing_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table} {$where} AND (custom_domain IS NULL OR custom_domain = '')");
    $status_counts['domain-linked'] = $domain_linked_count;
    $status_counts['domain-missing'] = $domain_missing_count;
    
    set_transient($status_counts_transient_key, $status_counts, HOUR_IN_SECONDS);
}
error_log('directory.php: Status counts processed.');

// Prepare counts for map
$counts_map = [];
$geojson_url = '';
$center_lat = 0;
$center_lng = 0;
$zoom = 1;
$code_prefix = '';
$code_prop = 'ISO_A2';
if ($view === 'map') {
    if ($map_type === 'us') {
        $counts_query = "SELECT state_code AS code, COUNT(*) AS cnt FROM $table $where AND country_code = 'US' GROUP BY state_code";
        $results = $wpdb->get_results($counts_query);
        foreach ($results as $row) {
            if ($row->code) {
                $counts_map['US-' . strtoupper($row->code)] = $row->cnt;
            }
        }
        $geojson_url = 'https://raw.githubusercontent.com/georgique/world-geojson/develop/countries/us/us_states.geojson';
        $center_lat = 37.8;
        $center_lng = -96;
        $zoom = 4;
        $code_prefix = 'US-';
        $code_prop = 'id';
    } elseif ($map_type === 'ca') {
        $counts_query = "SELECT state_code AS code, COUNT(*) AS cnt FROM $table $where AND country_code = 'CA' GROUP BY state_code";
        $results = $wpdb->get_results($counts_query);
        foreach ($results as $row) {
            if ($row->code) {
                $counts_map['CA-' . strtoupper($row->code)] = $row->cnt;
            }
        }
        $geojson_url = 'https://raw.githubusercontent.com/georgique/world-geojson/develop/countries/ca/ca_provinces.geojson';
        $center_lat = 60;
        $center_lng = -95;
        $zoom = 3;
        $code_prefix = 'CA-';
        $code_prop = 'id';
    } else {
        $counts_query = "SELECT country_code AS code, COUNT(*) AS cnt FROM $table $where GROUP BY country_code";
        $results = $wpdb->get_results($counts_query);
        foreach ($results as $row) {
            if ($row->code) {
                $counts_map[strtoupper($row->code)] = $row->cnt;
            }
        }
        $geojson_url = 'https://raw.githubusercontent.com/georgique/world-geojson/develop/countries.geojson';
        $center_lat = 0;
        $center_lng = 0;
        $zoom = 1;
        $code_prefix = '';
        $code_prop = 'ISO_A2';
    }
    $click_script = "var code = " . ($map_type === 'world' ? "feature.properties.ISO_A2" : "feature.properties.id") . "; window.location.search = new URLSearchParams({view: 'grid', country: '" . ($map_type === 'world' ? "' + code + '" : ($map_type === 'us' ? 'US' : 'CA')) . "', state: '" . ($map_type === 'world' ? '' : "' + code.substr(3) + '") . "', q: '$search', status: '$status_filter', sort: '$sort'}).toString();";
}

function h($s) {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function get_flag_emoji($country_code) {
    if (strlen($country_code ?? '') !== 2) {
        return '🌍'; // fallback emoji
    }
    
    // Convert country code to flag emoji
    $country_code = strtoupper($country_code);
    $flag = '';
    
    for ($i = 0; $i < 2; $i++) {
        $flag .= mb_chr(127397 + ord($country_code[$i]), 'UTF-8');
    }
    
    return $flag;
}

// Handle Google profile picture sync
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sync_google_avatar') {
    // SECURITY CHECK: Authenticate API endpoint
    if (!isset($_SESSION['user_email']) || empty($_SESSION['user_email'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
        exit();
    }
    
    $user_email = $_SESSION['user_email'];
    $google_access_token = $_POST['access_token'] ?? '';
    $google_image_url = $_POST['google_image_url'] ?? '';
    
    // For demonstration purposes, allow direct image URL instead of full OAuth flow
    if (!empty($google_image_url)) {
        $avatar_url = $google_image_url;
        $full_name = ''; // We don't get name from direct URL
    } else if (!empty($google_access_token)) {
        // Full OAuth implementation (for future use)
        // Fetch user profile from Google API
        $google_api_url = 'https://www.googleapis.com/oauth2/v1/userinfo?access_token=' . urlencode($google_access_token);
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: ION-Network/1.0',
                    'Accept: application/json'
                ],
                'timeout' => 10
            ]
        ]);
        
        $response = @file_get_contents($google_api_url, false, $context);
        
        if ($response === false) {
            error_log("AVATAR SYNC: Failed to fetch Google profile for user: $user_email");
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Failed to fetch Google profile']);
            exit();
        }
        
        $profile_data = json_decode($response, true);
        
        if (!$profile_data || !isset($profile_data['picture'])) {
            error_log("AVATAR SYNC: Invalid Google profile response for user: $user_email");
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid profile data']);
            exit();
        }
        
        $avatar_url = $profile_data['picture'];
        $full_name = $profile_data['name'] ?? '';
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Access token or image URL required']);
        exit();
    }
    
    // Validate the image URL
    if (!filter_var($avatar_url, FILTER_VALIDATE_URL)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid image URL format']);
        exit();
    }
    
    // Check if user already has a photo_url set
    $existing_photo_url = $wpdb->get_var($wpdb->prepare("SELECT photo_url FROM IONEERS WHERE email = %s", $user_email));
    
    if (!empty($existing_photo_url)) {
        error_log("AVATAR SYNC: User $user_email already has avatar set: $existing_photo_url");
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'error' => 'Avatar already set',
            'message' => 'You already have a profile picture. Use "Custom URL" to change it.',
            'existing_avatar' => $existing_photo_url
        ]);
        exit();
    }
    
    // Update user profile in IONEERS table
    $update_data = ['photo_url' => $avatar_url];
    $where_clause = ['email' => $user_email];
    
    // Also update fullname if we got it and it's currently empty
    if (!empty($full_name)) {
        $current_fullname = $wpdb->get_var($wpdb->prepare("SELECT fullname FROM IONEERS WHERE email = %s", $user_email));
        if (empty($current_fullname)) {
            $update_data['fullname'] = $full_name;
        }
    }
    
    $result = $wpdb->update('IONEERS', $update_data, $where_clause);
    
    if ($result !== false) {
        error_log("AVATAR SYNC: Successfully updated avatar for user: $user_email - URL: $avatar_url");
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'Avatar updated successfully',
            'avatar_url' => $avatar_url,
            'full_name' => $full_name
        ]);
    } else {
        error_log("AVATAR SYNC: Failed to update avatar for user: $user_email - Error: " . $wpdb->last_error);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Database update failed: ' . $wpdb->last_error]);
    }
    exit();
}

// Handle manual avatar URL update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_avatar_url') {
    // SECURITY CHECK: Authenticate API endpoint
    if (!isset($_SESSION['user_email']) || empty($_SESSION['user_email'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
        exit();
    }
    
    $user_email = $_SESSION['user_email'];
    $avatar_url = trim($_POST['avatar_url'] ?? '');
    
    // Validate URL format
    if (!empty($avatar_url) && !filter_var($avatar_url, FILTER_VALIDATE_URL)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid URL format']);
        exit();
    }
    
    // Update user avatar in IONEERS table
    $result = $wpdb->update(
        'IONEERS', 
        ['photo_url' => $avatar_url], 
        ['email' => $user_email]
    );
    
    if ($result !== false) {
        error_log("AVATAR UPDATE: Successfully updated avatar URL for user: $user_email");
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'Avatar updated successfully',
            'avatar_url' => $avatar_url
        ]);
    } else {
        error_log("AVATAR UPDATE: Failed to update avatar for user: $user_email - Error: " . $wpdb->last_error);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Database update failed']);
    }
    exit();
}

error_log('directory.php: Data processing finished. Starting HTML rendering.');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ION Local Channels</title>
    <link rel="icon" href="https://ions.com/assets/icons/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <link rel="stylesheet" href="directory.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="custom.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/ion-pricing-card.css?v=<?php echo time(); ?>">
    
    <!-- Dynamic UI Customization Styles -->
    <style>
        /* Distance info styling for zip code searches */
        .distance-info {
            color: #28a745 !important;
            font-weight: 600 !important;
            background: rgba(40, 167, 69, 0.1);
            padding: 2px 6px;
            border-radius: 3px;
            border-left: 3px solid #28a745;
        }
        
        /* Dark mode distance info styling */
        <?php if (strtolower($user_preferences['DefaultMode']) === 'darkmode'): ?>
        .distance-info {
            color: #4ade80 !important;
            background: rgba(74, 222, 128, 0.15);
            border-left-color: #4ade80;
        }
        <?php endif; ?>
    </style>
    
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
</head>
<body>

<?php require_once('add-ion-modal.php'); ?>

<script>
    (function() {
        const loginUrl = '/login/index.php';
        const originalFetch = window.fetch;
        window.fetch = async function(resource, init) {
            try {
                const response = await originalFetch(resource, init);
                // Redirect on 401 Unauthorized
                if (response.status === 401) {
                    try { sessionStorage.setItem('redirect_after_login', window.location.pathname + window.location.search); } catch (e) {}
                    window.location.href = loginUrl;
                    return response; // Return for callers, though page will navigate
                }
                // If the response was redirected to the login page, follow it
                if (response.redirected && typeof response.url === 'string' && response.url.indexOf('/login/') !== -1) {
                    try { sessionStorage.setItem('redirect_after_login', window.location.pathname + window.location.search); } catch (e) {}
                    window.location.href = loginUrl;
                    return response;
                }
                return response;
            } catch (err) {
                // Network errors: optionally route to login if we detect CORS/login page
                return Promise.reject(err);
            }
        };
    })();
    </script>

<!-- Video Viewer Modal -->
<div id="video-modal" class="modal">
    <div class="modal-content video-modal-content">
        <!-- Compact Header -->
        <div class="video-modal-header">
            <div class="header-left">
                <img id="video-icon" src="" alt="" class="video-service-icon">
                <h2 id="video-modal-title"></h2>
            </div>
            <div class="header-center">
                <div class="video-filters">
                    <input type="text" id="video-search" class="video-search-input" placeholder="Search videos...">
                <select id="category-filter" class="category-filter-compact">
                <option value="">All Categories</option>
            </select>
                </div>
        </div>
            <div class="header-right">
                <button id="add-video-btn" class="btn btn-primary btn-compact">+ Add Video</button>
                <button type="button" id="close-video-modal" class="close-btn">&times;</button>
            </div>
        </div>
        
        <!-- Scrollable Content Area -->
        <div class="video-content-area">
            <ul id="video-list" class="video-list-view"></ul>
            <div id="video-grid" class="video-grid-view" style="display: none;"></div>
            <div id="no-videos-message" class="no-videos" style="display: none;">
                <div class="no-videos-icon">📹</div>
                <p>No videos found for the selected category.</p>
            </div>
            <div id="sql-debug-display" class="sql-debug" style="display: none;">
                <h4>SQL Query Debug Information:</h4>
                <pre id="sql-query-text"></pre>
            </div>
        </div>
        
        <!-- Footer with Controls -->
        <div class="video-modal-footer">
            <div class="footer-left">
                <span id="video-count" class="video-count-display">0 videos</span>
                <span id="pagination-info-text" class="pagination-info-text"></span>
            </div>
            
            <div class="footer-center">
                <div id="video-pagination" class="pagination-controls-compact" style="display: flex;">
                    <button id="prev-page-btn" class="pagination-btn-compact" disabled>
                        <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                            <path d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/>
                        </svg>
                        Previous
                    </button>
                    <div class="pagination-pages-compact" id="pagination-pages"></div>
                    <button id="next-page-btn" class="pagination-btn-compact" disabled>
                        Next
                        <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                            <path d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/>
                        </svg>
                    </button>
                </div>
            </div>
            
            <div class="footer-right">
                <div class="video-view-toggle">
                    <button id="card-view-btn" class="view-toggle-btn active" title="Card View">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                            <path d="M1 2.5A1.5 1.5 0 0 1 2.5 1h3A1.5 1.5 0 0 1 7 2.5v3A1.5 1.5 0 0 1 5.5 7h-3A1.5 1.5 0 0 1 1 5.5v-3zM2.5 2a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3zm6.5.5A1.5 1.5 0 0 1 10.5 1h3A1.5 1.5 0 0 1 15 2.5v3A1.5 1.5 0 0 1 13.5 7h-3A1.5 1.5 0 0 1 9 5.5v-3zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3zM1 10.5A1.5 1.5 0 0 1 2.5 9h3A1.5 1.5 0 0 1 7 10.5v3A1.5 1.5 0 0 1 5.5 15h-3A1.5 1.5 0 0 1 1 13.5v-3zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3zm6.5.5A1.5 1.5 0 0 1 10.5 9h3a1.5 1.5 0 0 1 1.5 1.5v3a1.5 1.5 0 0 1-1.5 1.5h-3A1.5 1.5 0 0 1 9 13.5v-3zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3z"/>
                        </svg>
                    </button>
                    <button id="list-view-btn" class="view-toggle-btn" title="List View">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                            <path d="M2.5 12a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5z"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Video Modal (New Design) -->
<div id="add-video-modal" class="modal-overlay" style="display: none;">
    <div class="modal-container">
        <div class="modal-header">
            <div class="header-left">
                <h2 id="add-video-modal-title">+ Add New Video</h2>
            </div>
            <div class="header-right">
                <button type="button" id="close-add-video-modal" class="close-btn" aria-label="Close modal">×</button>
            </div>
        </div>
        
        <div class="modal-body">
            <form id="video-form" class="video-form">
                <!-- Hidden fields for context -->
                <input type="hidden" id="video-slug" name="slug" value="">
                <input type="hidden" id="video-videotype" name="videotype" value="">
                
                <!-- Video Import Section -->
                <div class="video-import-section">
                    <div class="import-header">
                        <div class="import-icon">⬇️</div>
                        <div class="import-text">
                            <h3>Use Video URL and Import Video</h3>
                            <p class="import-subtitle">Your videos will be private until you publish them.</p>
                        </div>
                        <div class="supported-platforms">
                            <img src="/assets/icons/youtube.svg" alt="YouTube" title="YouTube Supported" class="platform-icon">
                            <img src="/assets/icons/vimeo.svg" alt="Vimeo" title="Vimeo Supported" class="platform-icon">
                            <img src="/assets/icons/muvi.svg" alt="Muvi" title="Muvi Supported" class="platform-icon">
                        </div>
                    </div>
                    
                    <div class="url-input-section">
                        <div class="url-input-container">
                            <div class="url-icon">🔗</div>
                            <input type="url" id="video-url" name="url" class="url-input" 
                                   placeholder="http://youtube.com/watch?v=..." required>
                            <button type="button" id="fetch-video-btn" class="fetch-btn">Please wait...</button>
                        </div>
                    </div>
                </div>
                
                <!-- Auto-populated Video Information -->
                <div class="video-info-section">
                    <div class="form-group">
                        <label for="video-title" class="form-label">Video Title</label>
                        <div class="character-counter">
                            <span id="title-counter">0 characters</span>
                        </div>
                        <input type="text" id="video-title" name="title" class="form-input" 
                               placeholder="Enter video title" required maxlength="100">
                    </div>
                    
                    <div class="form-group">
                        <label for="video-description" class="form-label">Video Description</label>
                        <div class="character-counter">
                            <span id="description-counter">0 characters</span>
                        </div>
                        <textarea id="video-description" name="description" class="form-input description-textarea" 
                                 placeholder="Brief description of the video content" rows="5" maxlength="500"></textarea>
                    </div>
                    
                    <!-- Category Dropdowns -->
                    <div class="category-section">
                        <div class="form-group half-width">
                            <label for="video-category" class="form-label">Category</label>
                            <select id="video-category" name="category" class="form-select">
                                <option value="">Select Category</option>
                                <option value="Entertainment">Entertainment</option>
                                <option value="Education">Education</option>
                                <option value="News">News</option>
                                <option value="Sports">Sports</option>
                                <option value="Music">Music</option>
                                <option value="Travel">Travel</option>
                                <option value="Technology">Technology</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group half-width">
                            <label for="geo-restriction" class="form-label">Geo-Blocking</label>
                            <select id="geo-restriction" name="geo" class="form-select">
                                <option value="None">None</option>
                                <option value="Geo-blocking">Geo-blocking</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Tags Section -->
                    <div class="form-group">
                        <label class="form-label">Tags</label>
                        <div class="tags-container">
                            <div class="tags-list" id="tags-list">
                                <!-- Tags will be dynamically added here -->
                            </div>
                            <input type="text" id="tag-input" class="tag-input" placeholder="Tags, separated by comma">
                            <span class="tags-hint">Tags, separated by comma</span>
                        </div>
                    </div>
                </div>
                
                <!-- Horizontal Radio Groups -->
                <div class="video-settings-horizontal">
                    <div class="settings-row">
                        <div class="setting-group">
                            <label class="setting-label">Format</label>
                            <div class="radio-buttons-horizontal">
                                <label class="radio-button-horizontal">
                                    <input type="radio" name="format" value="Video" checked>
                                    <span class="radio-circle"></span>
                                    <span class="radio-label">Video</span>
                                </label>
                                <label class="radio-button-horizontal">
                                    <input type="radio" name="format" value="Movie">
                                    <span class="radio-circle"></span>
                                    <span class="radio-label">Movie</span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="setting-group">
                            <label class="setting-label">Visibility</label>
                            <div class="radio-buttons-horizontal">
                                <label class="radio-button-horizontal">
                                    <input type="radio" name="visibility" value="Public" checked>
                                    <span class="radio-circle"></span>
                                    <span class="radio-label">Public</span>
                                </label>
                                <label class="radio-button-horizontal">
                                    <input type="radio" name="visibility" value="Private">
                                    <span class="radio-circle"></span>
                                    <span class="radio-label">Private</span>
                                </label>
                                <label class="radio-button-horizontal">
                                    <input type="radio" name="visibility" value="Unlisted">
                                    <span class="radio-circle"></span>
                                    <span class="radio-label">Unlisted</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="settings-row">
                        <div class="setting-group">
                            <label class="setting-label">Age Restriction</label>
                            <div class="radio-buttons-horizontal">
                                <label class="radio-button-horizontal">
                                    <input type="radio" name="age" value="Everyone" checked>
                                    <span class="radio-circle"></span>
                                    <span class="radio-label">Everyone</span>
                                </label>
                                <label class="radio-button-horizontal">
                                    <input type="radio" name="age" value="Only 18+">
                                    <span class="radio-circle"></span>
                                    <span class="radio-label">Only +18</span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="setting-group">
                            <label class="setting-label">Layout</label>
                            <div class="radio-buttons-horizontal">
                                <label class="radio-button-horizontal">
                                    <input type="radio" name="layout" value="Wide" checked>
                                    <span class="radio-circle"></span>
                                    <span class="radio-label">Wide</span>
                                </label>
                                <label class="radio-button-horizontal">
                                    <input type="radio" name="layout" value="Tall">
                                    <span class="radio-circle"></span>
                                    <span class="radio-label">Tall</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Hidden source field (auto-detected) -->
                <input type="hidden" id="video-source" name="source" value="Youtube">
                
                <!-- Hidden tags field (populated by tag input) -->
                <input type="hidden" id="video-tags" name="tags" value="">
            </form>
        </div>
        
        <div class="modal-footer">
            <div class="footer-actions">
                <button type="button" id="cancel-add-video-btn" class="btn btn-secondary">
                    <span class="btn-icon">✕</span>
                    <span class="btn-text">Cancel</span>
                </button>
                <button type="submit" form="video-form" id="submit-video-btn" class="btn btn-primary">
                    <span class="btn-icon" id="submit-video-icon">📹</span>
                    <span class="btn-text" id="submit-video-text">Add Video</span>
                </button>
            </div>
        </div>
    </div>
</div>

<?php
// Configure header for ION Local Channels
$header_config = [
    'title'                  => 'ION Local Channels',
    'search_placeholder'     => 'Search cities, states, slugs or domains',
    'search_value'           => $search,
    'active_tab'             => 'IONS',
    'button_text'            => '+ Add ION Channel',
    'button_id'              => 'add-ion-btn',
    'button_class'           => '',
    'show_button'            => $can_add_edit,
    'additional_form_fields' => '<input type="hidden" name="view" value="' . h($view) . '">',
    'mobile_button_text'     => 'Add ION Channel'
];
include 'headers.php';
?>

<div class="results-controls">
    <div class="left">
        <strong>Showing <?= $offset + 1 ?>-<?= min($offset + $perPage, $total) ?> of <?= $total !== null ? number_format($total) : '0' ?> channels</strong>
    </div>
    <div class="center">
        <form action="" method="get" id="filter-form" style="display: contents;">
            <input type="hidden" name="view" value="<?= h($view) ?>">
            <input type="hidden" name="q" value="<?= h($search) ?>">
            <select name="sort" onchange="this.form.submit()">
                <option value="city_name" <?= $sort === 'city_name' ? 'selected' : '' ?>>Sort by City [A-Z]</option>
                <option value="state_name" <?= $sort === 'state_name' ? 'selected' : '' ?>>Sort by State</option>
                <option value="country_name" <?= $sort === 'country_name' ? 'selected' : '' ?>>Sort by Country</option>
                <option value="population" <?= $sort === 'population' ? 'selected' : '' ?>>Sort by Population</option>
                <?php if ($is_zip_search): ?>
                <option value="distance" <?= $sort === 'distance' ? 'selected' : '' ?>>Sort by Distance</option>
                <?php endif; ?>
                <option value="custom_domain" <?= $sort === 'custom_domain' ? 'selected' : '' ?>>Sort by Domain</option>
                <option value="status" <?= $sort === 'status' ? 'selected' : '' ?>>Sort by Status</option>
            </select>
            <select name="status" onchange="this.form.submit()">
                <option value="">All Status</option>
                <option value="live" <?= $status_filter === 'live' ? 'selected' : '' ?>>Live Channel (<?= number_format($status_counts['live'] ?? 0) ?>)</option>
                <option value="preview" <?= $status_filter === 'preview' ? 'selected' : '' ?>>Preview Page (<?= number_format($status_counts['preview'] ?? 0) ?>)</option>
                <option value="static" <?= $status_filter === 'static' ? 'selected' : '' ?>>Static WP (<?= number_format($status_counts['static'] ?? 0) ?>)</option>
                <option value="draft" <?= $status_filter === 'draft' ? 'selected' : '' ?>>Draft Pages (<?= number_format($status_counts['draft'] ?? 0) ?>)</option>
                <option value="error" <?= $status_filter === 'error' ? 'selected' : '' ?>>Has errors (<?= number_format($status_counts['error'] ?? 0) ?>)</option>
                <option value="cf-active" <?= $status_filter === 'cf-active' ? 'selected' : '' ?>>Cloudflare Active (<?= number_format($status_counts['cf-active'] ?? 0) ?>)</option>
                <option value="cf-missing" <?= $status_filter === 'cf-missing' ? 'selected' : '' ?>>Cloudflare Missing (<?= number_format($status_counts['cf-missing'] ?? 0) ?>)</option>
                <option value="cf-inactive" <?= $status_filter === 'cf-inactive' ? 'selected' : '' ?>>Cloudflare Inactive (<?= number_format($status_counts['cf-inactive'] ?? 0) ?>)</option>
                <option value="cf-pending" <?= $status_filter === 'cf-pending' ? 'selected' : '' ?>>Cloudflare Pending (<?= number_format($status_counts['cf-pending'] ?? 0) ?>)</option>
                <option value="domain-linked" <?= $status_filter === 'domain-linked' ? 'selected' : '' ?>>Domain Linked (<?= number_format($status_counts['domain-linked'] ?? 0) ?>)</option>
                <option value="domain-missing" <?= $status_filter === 'domain-missing' ? 'selected' : '' ?>>Domain Missing (<?= number_format($status_counts['domain-missing'] ?? 0) ?>)</option>
            </select>
            <select name="country" id="country-filter" onchange="this.form.submit()">
                <option value="" <?= $country_filter === '' ? 'selected' : '' ?>>All Countries</option>
                <?php 
                if (isset($countries) && is_array($countries) && count($countries) > 0) {
                    foreach ($countries as $c): 
                        if (!is_array($c) || !isset($c['code'], $c['name'])) continue; ?>
                        <option value="<?= h($c['code']) ?>" <?= ($country_filter === $c['code']) ? 'selected' : '' ?>><?= get_flag_emoji($c['code']) ?> <?= h($c['name']) ?></option>
                    <?php endforeach;
                } else {
                    // Fallback if countries array is somehow empty
                    ?>
                    <option value="US"><?= get_flag_emoji('US') ?> United States</option>
                    <option value="CA"><?= get_flag_emoji('CA') ?> Canada</option>
                    <?php
                }
                ?>
            </select>
            <select name="state" id="state-filter" onchange="this.form.submit()" <?= !$country_filter ? 'disabled' : '' ?>>
                <option value="">All States/Provinces</option>
                <?php if ($country_filter && $state_filter): ?>
                    <?php
                    // Get states for the selected country
                    $states_query = "SELECT DISTINCT state_code, state_name FROM $table WHERE country_code = ? AND state_name != '' ORDER BY state_name";
                    $states_stmt = $wpdb->prepare($states_query, $country_filter);
                    $states = $wpdb->get_results($states_stmt);
                    
                    if ($states) {
                        foreach ($states as $state) {
                            $selected = ($state->state_code === $state_filter) ? 'selected' : '';
                            echo '<option value="' . h($state->state_code) . '" ' . $selected . '>' . h($state->state_name) . '</option>';
                        }
                    }
                    ?>
                <?php endif; ?>
            </select>
        </form>
    </div>
    <div class="right toggle-buttons">
        <a href="?view=grid&<?= http_build_query(array_diff_key($_GET, ['view' => ''])) ?>" class="<?= $view === 'grid' ? 'active' : '' ?>">Grid</a>
        <a href="?view=list&<?= http_build_query(array_diff_key($_GET, ['view' => ''])) ?>" class="<?= $view === 'list' ? 'active' : '' ?>">List</a>
        <a href="?view=map&<?= http_build_query(array_diff_key($_GET, ['view' => ''])) ?>" class="<?= $view === 'map' ? 'active' : '' ?>">Map</a>
    </div>
</div>

<div class="container">
    <?php if ($view === 'map'): ?>
        <div class="map-selector">
            <form method="get">
                <input type="hidden" name="q" value="<?= h($search) ?>">
                <input type="hidden" name="view" value="map">
                <input type="hidden" name="status" value="<?= h($status_filter) ?>">
                <input type="hidden" name="country" value="<?= h($country_filter) ?>">
                <input type="hidden" name="state" value="<?= h($state_filter) ?>">
                <input type="hidden" name="sort" value="<?= h($sort) ?>">
                <select name="map_type" onchange="this.form.submit()">
                    <option value="us"<?= $map_type === 'us' ? ' selected' : '' ?>>United States</option>
                    <option value="ca"<?= $map_type === 'ca' ? ' selected' : '' ?>>Canada</option>
                    <option value="world"<?= $map_type === 'world' ? ' selected' : '' ?>>World</option>
                </select>
            </form>
        </div>
        <div id="map"></div>
        <script>
            var counts = <?= json_encode($counts_map) ?>;
            
            // Prevent duplicate map initialization
            if (window.mapInstance) {
                window.mapInstance.remove();
            }
            
            var map = L.map('map', {
                zoomControl: false // Disable default zoom control to prevent duplicates
            }).setView([<?= $center_lat ?>, <?= $center_lng ?>], <?= $zoom ?>);
            
            // Add zoom control manually to prevent duplicate ID errors
            L.control.zoom({
                position: 'topleft'
            }).addTo(map);
            
            // Store map instance globally
            window.mapInstance = map;
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);

            fetch('<?= $geojson_url ?>')
                .then(response => response.json())
                .then(data => {
                    L.geoJSON(data, {
                        style: function(feature) {
                            var code = feature.properties['<?= $code_prop ?>'];
                            var count = counts[code] || 0;
                            var color = count > 500 ? '#800026' :
                                        count > 200 ? '#BD0026' :
                                        count > 100 ? '#E31A1C' :
                                        count > 50  ? '#FC4E2A' :
                                        count > 20  ? '#FD8D3C' :
                                        count > 10  ? '#FEB24C' :
                                        count > 0   ? '#FED976' :
                                                      '#FFEDA0';
                            return {
                                fillColor: color,
                                weight: 2,
                                opacity: 1,
                                color: 'white',
                                dashArray: '3',
                                fillOpacity: 0.7
                            };
                        },
                        onEachFeature: function(feature, layer) {
                            var code = feature.properties['<?= $code_prop ?>'];
                            var name = feature.properties.name || feature.properties.NAME;
                            layer.bindTooltip(name + ' (Channels: ' + (counts[code] || 0) + ')');
                            layer.on('mouseover', function(e) { this.setStyle({ fillOpacity: 0.9 }); });
                            layer.on('mouseout', function(e) { this.setStyle({ fillOpacity: 0.7 }); });
                            layer.on('click', function(e) {
                                <?= $click_script ?>
                            });
                        }
                    }).addTo(map);
                });
        </script>
    <?php else: // Not map view, so grid or list ?>
    <div class="search-results-container">
        <?php if (empty($cities)): ?>
            <?php if (!empty(trim($search))): ?>
                <p class="no-results">
                    No channels found for "<?= h($search) ?>".
                    <br><small>Try a <a href="?q=<?= urlencode($search) ?>&view=<?= h($view) ?>&sort=<?= h($sort) ?>&status=&country=&state=&page=1" class="global-search-link">global search</a> with all filters turned off.</small>
                </p>
            <?php else: ?>
                <p class="no-results">No channels available. <?php if ($can_add_edit): ?><a href="#" onclick="document.getElementById('add-ion-btn').click(); return false;">Add the first channel</a>.<?php endif; ?></p>
            <?php endif; ?>
        <?php else: ?>
            <?php if ($view === 'grid'): ?>
            <div class="city-grid">
                    <?php foreach ($cities as $city): ?>
                    <?php if ($city === null || !is_object($city)) continue; ?>
                      <div class="city <?= strtolower((string)$city->status) ?>" data-city-id="<?= h($city->id) ?>" data-city-slug="<?= h($city->slug) ?>">
                        <?php if ($can_add_edit): ?>
                        <div class="card-actions">
                          <button class="edit-card-btn" title="Edit this listing" data-city-id="<?= h($city->id) ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                              <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293l6.5-6.5zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                            </svg>
                          </button>
                        </div>
                        <?php endif; ?>
                        <strong>
                          <a href="<?= h($city->page_URL) ?>" target="_blank">
                            <?= h($city->city_name) ?><?= (!empty($city->city_name) && !empty($city->state_name)) ? ', ' : '' ?><?= h($city->state_name) ?>
                          </a>
                        </strong><br>
                    
                        <div class="domain-row">
                          <?php if ($city->custom_domain): ?>
                            <?php
                              // Split domain from path for better truncation
                              $domain_parts = explode('/', $city->custom_domain, 2);
                              $main_domain = $domain_parts[0];
                              $domain_path = isset($domain_parts[1]) ? '/' . $domain_parts[1] : '';
                            ?>
                            <div class="domain-content">
                              <?php if (strtolower($city->status) === 'live'): ?>
                                <a href="https://<?= h($city->custom_domain) ?>" target="_blank" class="domain-link">
                                  <span class="domain-main"><?= h($main_domain) ?></span><?php if ($domain_path): ?><span class="domain-path"><?= h($domain_path) ?></span><?php endif; ?>
                                </a>
                              <?php elseif (strtolower($city->status) === 'preview'): ?>
                                <a href="https://<?= h($city->custom_domain) ?>" target="_blank" class="domain-link">
                                  <span class="domain-main"><?= h($main_domain) ?></span><?php if ($domain_path): ?><span class="domain-path"><?= h($domain_path) ?></span><?php endif; ?>
                                </a>
                              <?php else: ?>
                                <span class="domain-main"><?= h($main_domain) ?></span><?php if ($domain_path): ?><span class="domain-path"><?= h($domain_path) ?></span><?php endif; ?>
                              <?php endif; ?>
                            </div>
                            
                            <?php if (strtolower($city->status) !== 'active'): ?>
                              <div class="domain-management-icons">
                                <!-- Cloudflare Status Icon -->
                                <?php 
                                  $cloudflare_status = strtolower(trim($city->cloudflare_active ?? 'missing'));
                                  $cf_class = '';
                                  $cf_icon = '';
                                  switch($cloudflare_status) {
                                    case 'active':
                                      $cf_class = 'cf-active';
                                      $cf_icon = '/assets/icons/cloudflare.svg';
                                      break;
                                    case 'pending':
                                    case 'inactive':
                                      $cf_class = 'cf-inactive';
                                      $cf_icon = '/assets/icons/cloudflare-red.svg';
                                      break;
                                    default: // missing
                                      $cf_class = 'cf-missing';
                                      $cf_icon = '/assets/icons/cloudflare-off.svg';
                                      break;
                                  }
                                ?>
                                <div class="domain-icon cloudflare-status <?= $cf_class ?>" title="Cloudflare Status: <?= ucfirst($cloudflare_status) ?>">
                                  <img src="<?= $cf_icon ?>" alt="Cloudflare Status" />
                                </div>
                                
                                <!-- Remove Domain Icon -->
                                <div class="domain-icon remove-domain" 
                                     title="Detach this domain" 
                                     data-slug="<?= h($city->slug) ?>" 
                                     data-domain="<?= h($city->custom_domain) ?>">
                                  <img src="/assets/icons/remove.svg" alt="Remove Domain" class="default-icon" />
                                  <img src="/assets/icons/remove-bold.svg" alt="Remove Domain" class="hover-icon" style="display: none;" />
                                </div>
                              </div>
                            <?php endif; ?>
                          <?php else: ?>
                            <?php if (strtolower($city->status) !== 'active'): ?>
                              <div class="domain-management-icons">
                                <!-- Connect Domain Icon -->
                                <div class="domain-icon connect-domain" 
                                     title="Link a new domain" 
                                     data-slug="<?= h($city->slug) ?>">
                                  <img src="/assets/icons/connect.svg"      alt="Connect Domain" class="default-icon" style="color: green"/>
                                  <img src="/assets/icons/connect-bold.svg" alt="Connect Domain" class="hover-icon"   style="color: green; display: none;" />
                                </div>
                            </div>
                            <?php endif; ?>
                          <?php endif; ?>
                        </div>
                    
                        <?php
                          $country_code = strtolower($city->country_code ?? 'xx');
                          $country_name = $city->country_name ?? 'Unknown';
                        ?>
                    
                        <div class="city-info-row">
                          <div class="meta-column">
                            <span class="meta-info">
                              <img src="/assets/flags/<?= htmlspecialchars($country_code) ?>.svg"
                                   alt="<?= htmlspecialchars($country_code) ?> Flag"
                                   class="flags" />
                              <?= htmlspecialchars($country_name) ?>
                            </span><br>
                            <span class="meta-info">
                              Population: <?= $city->population ? number_format((int)preg_replace('/[^\d]/', '', $city->population)) : 'N/A' ?>
                            </span><br>
                            <?php if ($is_zip_search && isset($city->distance_miles)): ?>
                            <span class="meta-info distance-info">
                              Distance: <?= number_format($city->distance_miles, 1) ?> miles
                            </span><br>
                            <?php endif; ?>
                            <span class="meta-info">
                              Traffic: -
                            </span><br>
                          </div>
                    
                          <div class="video-column">
                            <!-- YouTube -->
                            <div class="video-count yt" data-slug="<?= h($city->slug) ?>" data-source="youtube">
                              <span class="icon yt-icon">
                                <img src="/assets/icons/youtube.svg" alt="YouTube" class="icon-img" />
                              </span>
                              (<small><?= number_format($counts[$city->slug]['youtube'] ?? $counts[$city->slug]['Youtube'] ?? 0) ?></small>)
                              <?php if (($counts[$city->slug]['youtube'] ?? 0) == 0): ?>
                                <!-- DEBUG: No YouTube videos for <?= h($city->slug) ?> -->
                              <?php endif; ?>
                            </div>
                    
                            <!-- Muvi -->
                            <div class="video-count muvi" data-slug="<?= h($city->slug) ?>" data-source="muvi">
                              <span class="icon muvi-icon">
                                <img src="/assets/icons/muvi.svg" alt="Muvi" class="icon-img" />
                              </span>
                              (<small><?= number_format($counts[$city->slug]['muvi'] ?? $counts[$city->slug]['Muvi'] ?? 0) ?></small>)
                            </div>
                    
                            <!-- Vimeo -->
                            <div class="video-count vimeo" data-slug="<?= h($city->slug) ?>" data-source="vimeo">
                              <span class="icon vimeo-icon">
                                <img src="/assets/icons/vimeo.svg" alt="Vimeo" class="icon-img" />
                              </span>
                              (<small><?= number_format($counts[$city->slug]['vimeo'] ?? $counts[$city->slug]['Vimeo'] ?? 0) ?></small>)
                            </div>
                          </div>
                        </div> <!-- .city-info-row -->
                      </div> <!-- .city -->
                    <?php endforeach; ?>
                
                </div> <!-- .city-grid -->

            <?php else: // list view ?>
                <div class="city-list">
                    <table class="city-table">
                        <thead>
                            <tr>
                                <th><a href="?sort=city_name&<?= http_build_query(array_diff_key($_GET, ['sort'=>''])) ?>">City</a></th>
                                <th><a href="?sort=state_name&<?= http_build_query(array_diff_key($_GET, ['sort'=>''])) ?>">State/Province</a></th>
                                <th><a href="?sort=country_name&<?= http_build_query(array_diff_key($_GET, ['sort'=>''])) ?>">Country</a></th>
                                <th><a href="?sort=population&<?= http_build_query(array_diff_key($_GET, ['sort'=>''])) ?>">Population</a></th>
                                <th><a href="?sort=custom_domain&<?= http_build_query(array_diff_key($_GET, ['sort'=>''])) ?>">Domain</a></th>
                                <th><a href="?sort=status&<?= http_build_query(array_diff_key($_GET, ['sort'=>''])) ?>">Status</a></th>
                                <th>Videos</th>
                                <th>Domain Management</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($cities as $city): ?>
                        <?php if ($city === null || !is_object($city)) continue; ?>
                            <tr class="<?= strtolower($city->status) ?>">
                                <td><strong><a href="<?= h($city->page_URL) ?>" target="_blank"><?= h($city->city_name) ?><?= !empty($city->city_name) && !empty($city->state_name) ? ', ' : '' ?><?= h($city->state_name) ?></a></strong></td>
                                <td><?= h($city->state_name) ?></td>
                                <td><?= get_flag_emoji($city->country_code) ?> <?= h($city->country_name) ?></td>
                                <td><?= number_format((int)preg_replace('/[^\d]/', '', $city->population)) ?></td>
                                <td>
                                    <?php if ($city->custom_domain): ?>
                                        <?php
                                          // Split domain from path for better truncation
                                          $domain_parts = explode('/', $city->custom_domain, 2);
                                          $main_domain = $domain_parts[0];
                                          $domain_path = isset($domain_parts[1]) ? '/' . $domain_parts[1] : '';
                                        ?>
                                        <div class="domain-content">
                                            <?php if (strtolower($city->status) === 'live'): ?>
                                                <a href="https://<?= h($city->custom_domain) ?>" target="_blank" class="domain-link">
                                                  <span class="domain-main"><?= h($main_domain) ?></span><?php if ($domain_path): ?><span class="domain-path"><?= h($domain_path) ?></span><?php endif; ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="domain-main"><?= h($main_domain) ?></span><?php if ($domain_path): ?><span class="domain-path"><?= h($domain_path) ?></span><?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="status-badge <?= strtolower($city->status) ?>"><?= h($city->status) ?></span></td>
                                <td>
                                <?php
                                    $yt = $counts[$city->slug]['youtube'] ?? 0;
                                    $mu = $counts[$city->slug]['muvi'] ?? 0;
                                    $vi = $counts[$city->slug]['vimeo'] ?? 0;
                                    $videos = [];
                                    if ($yt > 0) {
                                        $videos[] = '<span class="video-count yt" data-slug="' . h($city->slug) . '" data-source="youtube">'
                                                  . '<span class="icon"><img src="/assets/icons/youtube.svg" alt="YouTube" /></span> YT (<strong>' . number_format($yt) . '</strong>)</span>';
                                    } else {
                                        $videos[] = '<span class="video-count yt zero" data-slug="' . h($city->slug) . '" data-source="youtube">'
                                                  . '<span class="icon"><img src="/assets/icons/youtube.svg" class="zero" alt="YouTube" /></span> YT (0)</span>';
                                    }
                                    if ($mu > 0) {
                                        $videos[] = '<span class="video-count muvi" data-slug="' . h($city->slug) . '" data-source="muvi">'
                                                  . '<span class="icon"><img src="/assets/icons/muvi.svg" alt="Muvi" /></span> Mu (<strong>' . number_format($mu) . '</strong>)</span>';
                                    } else {
                                        $videos[] = '<span class="video-count muvi zero" data-slug="' . h($city->slug) . '" data-source="muvi">'
                                                  . '<span class="icon"><img src="/assets/icons/muvi.svg" class="zero" alt="Muvi" /></span> Mu (0)</span>';
                                    }
                                    if ($vi > 0) {
                                        $videos[] = '<span class="video-count vimeo" data-slug="' . h($city->slug) . '" data-source="vimeo">'
                                                  . '<span class="icon"><img src="/assets/icons/vimeo.svg" alt="Vimeo" /></span> Vi (<strong>' . number_format($vi) . '</strong>)</span>';
                                    } else {
                                        $videos[] = '<span class="video-count vimeo zero" data-slug="' . h($city->slug) . '" data-source="vimeo">'
                                                  . '<span class="icon"><img src="/assets/icons/vimeo.svg" class="zero" alt="Vimeo" /></span> Vi (0)</span>';
                                    }
                                    if (!empty($videos)) {
                                        echo '<div class="video-column">' . implode('', $videos) . '</div>';
                                    }
                                ?>
                                </td>
                                <td>
                                    <?php if (strtolower($city->status) !== 'active'): ?>
                                        <div class="domain-management-column table-view">
                                            <?php if (!empty($city->custom_domain)): ?>
                                                <!-- Cloudflare Status Icon -->
                                                <?php 
                                                    $cloudflare_status = strtolower(trim($city->cloudflare_active ?? 'missing'));
                                                    $cf_class = '';
                                                    $cf_icon = '';
                                                    switch($cloudflare_status) {
                                                        case 'active':
                                                            $cf_class = 'cf-active';
                                                            $cf_icon = '/assets/icons/cloudflare.svg';
                                                            break;
                                                        case 'pending':
                                                        case 'inactive':
                                                            $cf_class = 'cf-inactive';
                                                            $cf_icon = '/assets/icons/cloudflare-red.svg';
                                                            break;
                                                        default: // missing
                                                            $cf_class = 'cf-missing';
                                                            $cf_icon = '/assets/icons/cloudflare-off.svg';
                                                            break;
                                                    }
                                                ?>
                                                <div class="domain-icon cloudflare-status <?= $cf_class ?>" title="Cloudflare Status: <?= ucfirst($cloudflare_status) ?>">
                                                    <img src="<?= $cf_icon ?>" alt="Cloudflare Status" />
                                                </div>
                                                
                                                <!-- Remove Domain Icon -->
                                                <div class="domain-icon remove-domain" 
                                                     title="Detach this domain" 
                                                     data-slug="<?= h($city->slug) ?>" 
                                                     data-domain="<?= h($city->custom_domain) ?>">
                                                    <img src="/assets/icons/remove.svg" alt="Remove Domain" class="default-icon" />
                                                    <img src="/assets/icons/remove-bold.svg" alt="Remove Domain" class="hover-icon" style="display: none;" />
                                                </div>
                                            <?php else: ?>
                                                <!-- Connect Domain Icon -->
                                                <div class="domain-icon connect-domain" title="Link a new domain" 
                                                     data-slug="<?= h($city->slug) ?>">
                                                    <img src="/assets/icons/connect.svg" alt="Connect Domain" class="default-icon" />
                                                    <img src="/assets/icons/connect-bold.svg" alt="Connect Domain" class="hover-icon" style="display: none;" />
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div> <!-- .search-results-container -->

        <div class="pagination">
                <?php
                $totalPages = ceil($total / $perPage);
                if ($page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">First</a>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">Previous</a>
                <?php endif; ?>
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                        <?= $i === $page ? "<strong>$i</strong>" : $i ?>
                    </a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next</a>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $totalPages])) ?>">Last</a>
                <?php endif; ?>
                
                <!-- SQL Debug Icon (only for admin@sperse.com) -->
                <?php if ($user_email === 'admin@sperse.com'): ?>
                <span class="sql-debug-trigger" id="sql-debug-trigger" title="Show SQL Debug Info">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M11 9h2V7h-2m1 13c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8m0-18A10 10 0 0 0 2 12a10 10 0 0 0 10 10 10 10 0 0 0 10-10A10 10 0 0 0 12 2m-1 15h2v-6h-2v6Z"/>
                    </svg>
                </span>
                <?php endif; ?>
        </div>
        
        <!-- SQL Debug Panel (hidden by default) -->
        <div id="sql-debug-panel" class="sql-debug-panel" style="display: none;">
            <div class="debug-header">
                <h3>🔍 SQL Debug Information</h3>
                <button id="close-debug-panel" class="close-debug-btn">&times;</button>
            </div>
            <div class="debug-content">
                <div class="debug-section">
                    <h4>Search Query Results</h4>
                    <p><strong>Search Term:</strong> <code id="debug-search-term"></code></p>
                    <p><strong>Total Found:</strong> <span id="debug-total-found"></span></p>
                    <p><strong>Results Returned:</strong> <span id="debug-results-returned"></span></p>
                    <p><strong>Page:</strong> <span id="debug-page"></span> of <span id="debug-total-pages"></span></p>
                </div>
                
                <div class="debug-section">
                    <h4>Count Query</h4>
                    <pre><code id="debug-count-query"></code></pre>
                </div>
                
                <div class="debug-section">
                    <h4>Main Query</h4>
                    <pre><code id="debug-main-query"></code></pre>
                </div>
                
                <div class="debug-section">
                    <h4>WHERE Clause</h4>
                    <pre><code id="debug-where-clause"></code></pre>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
<script>
    // Load countries data for JavaScript
    <?php 
    try {
        $countries_data = require(__DIR__ . '/countries.php');
        if (!is_array($countries_data)) {
            error_log('Countries.php did not return an array');
            $countries_data = [];
        }
    } catch (Exception $e) {
        error_log('Error loading countries.php: ' . $e->getMessage());
        $countries_data = [];
    }
    ?>
    window.ION_COUNTRIES = <?php echo json_encode($countries_data ?: []); ?>;
    console.log('🌍 ION_COUNTRIES loaded:', window.ION_COUNTRIES.length);
    
    // Make user permissions available to JavaScript
    window.userCanAddEdit = <?= json_encode($can_add_edit ?? false) ?>;
    window.userCanDelete = <?= json_encode($can_delete ?? false) ?>;
    window.userRole = <?= json_encode($user_role ?? 'guest') ?>;
    console.log('👤 User permissions - Role:', window.userRole, 'CanAddEdit:', window.userCanAddEdit, 'CanDelete:', window.userCanDelete);
    
    // SQL Debug information
    window.sqlDebugInfo = <?= json_encode($debug_info ?? []) ?>;
    window.totalPages = <?= json_encode($totalPages ?? 0) ?>;
</script>

<script>
    // *** CACHE BUST: <?php echo time(); ?> *** VIDEO SLUG MATCHING FIXED *** 
    // REFRESH YOUR BROWSER WITH CTRL+F5 (hard refresh) if you don't see this timestamp change
    console.log('🔄 CACHE BUST CHECK - Timestamp:', <?php echo time(); ?>, 'Current time:', Date.now());
    
    // Global debug setting - change to true to enable debug info
    const GLOBAL_DEBUG_ENABLED = false;
    
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🚀 ION Directory loaded successfully - Video slug matching logic fixed');
        
        // Initialize event listeners for initial page content
        if (typeof initializeDynamicEventListeners === 'function') {
            initializeDynamicEventListeners();
        }
        
        // Also initialize edit buttons directly like ions version
        const editModal = document.getElementById('add-ion-modal');
        const editButtons = document.querySelectorAll('.edit-card-btn');
        console.log('📝 Direct initialization - Found', editButtons.length, 'edit buttons');
        
        editButtons.forEach(editBtn => {
            editBtn.addEventListener('click', (e) => {
                e.preventDefault();
                const cityId = editBtn.dataset.cityId;
                console.log('📝 Direct edit button clicked for city ID:', cityId);
                
                if (!editModal) {
                    console.error('❌ Add ION modal not found');
                    return;
                }
                
                // Fetch city data for editing - same as ions version
                console.log('🔄 Fetching city data for ID:', cityId);
                fetch('add-ion-handler.php?action=get_city_data&city_id=' + cityId)
                    .then(response => response.json())
                    .then(data => {
                        console.log('📋 City data received:', data);
                        if (data.success) {
                            // Set modal to edit mode
                            editModal.dataset.mode = 'edit';
                            editModal.dataset.cityId = cityId;
                            console.log('✅ Modal set to edit mode');
                            
                            // Update modal title
                            const modalTitle = document.getElementById('modal-title');
                            if (modalTitle) modalTitle.textContent = 'Edit ION Channel';
                            
                            // Initialize wizard for edit mode
                            if (typeof initializeWizard === 'function') {
                                initializeWizard();
                            }
                            
                            // Show modal using working ions pattern
                            editModal.style.display = 'flex';
                            setTimeout(() => editModal.classList.add('show'), 10);
                            console.log('✅ Edit modal should now be visible');
                            
                        } else {
                            console.error('❌ Failed to load city data:', data.error);
                            alert('Failed to load city data: ' + (data.error || 'Unknown error'));
                        }
                    })
                    .catch(error => {
                        console.error('Edit city error:', error);
                        alert('Failed to load city data: ' + error.message);
                    });
            });
        });

        
        // Main page logic
        const hamburger = document.querySelector('.hamburger-menu');
        const mobileMenu = document.querySelector('.mobile-menu');
        const addIonBtn = document.getElementById('add-ion-btn');
        const mobileAddIonBtn = document.getElementById('mobile-add-ion-btn');
        const modal = document.getElementById('add-ion-modal');
        const videoModal = document.getElementById('video-modal');
        const closeVideoModal = document.getElementById('close-video-modal');

        console.log('🔍 DOM Elements Check:', {
            hamburger: !!hamburger,
            mobileMenu: !!mobileMenu,
            addIonBtn: !!addIonBtn,
            modal: !!modal,
            videoModal: !!videoModal
        });

        // User menu functionality is now handled by usermenu.php component

        if(hamburger) {
            hamburger.addEventListener('click', () => {
                mobileMenu.classList.toggle('active');
            });
        }

        // Modern wizard modal handling with animation
        function showModal() {
            // Only reset modal to add mode if NOT in edit mode
            const modal = document.getElementById('add-ion-modal');
            const isEditMode = modal && modal.dataset.mode === 'edit';
            
            if (!isEditMode) {
                // Reset modal to add mode only for new entries
            resetModalToAddMode();
                initializeWizard();
            }
            
            modal.style.display = 'flex';
            setTimeout(() => modal.classList.add('show'), 10);
        }
        
        function hideModal() {
            modal.classList.remove('show');
            setTimeout(() => modal.style.display = 'none', 300);
            // Reset form when closing
            resetModalToAddMode();
        }
        
        // Wizard functionality
        let currentStep = 1;
        const totalSteps = 5;
        let uploadedMediaFiles = [];
        
        function initializeWizard() {
            currentStep = 1;
            uploadedMediaFiles = [];
            updateWizardStep();
            updateStepNavigation();
            updateWizardButtons();
            updatePreview();
        }
        
        function updateWizardStep() {
            // Hide all steps
            document.querySelectorAll('.wizard-step').forEach(step => {
                step.classList.remove('active');
                step.classList.add('hidden');
            });
            
            // Show current step
            const currentStepElement = document.getElementById(`step-${currentStep}`);
            if (currentStepElement) {
                currentStepElement.classList.add('active');
                currentStepElement.classList.remove('hidden');
            }
            
            // Update header description
            const descriptions = {
                1: 'Step 1 of 5: Basics',
                2: 'Step 2 of 5: Media',
                3: 'Step 3 of 5: SEO',
                4: 'Step 4 of 5: Geo',
                5: 'Step 5 of 5: Preview'
            };
            
            const modalDescription = document.getElementById('modal-description');
            if (modalDescription) {
                modalDescription.textContent = descriptions[currentStep];
            }
            
            // Update title for edit mode
            const modalTitle = document.getElementById('modal-title');
            const modal = document.getElementById('add-ion-modal');
            const isEditMode = modal && modal.dataset.mode === 'edit';
            
            if (modalTitle) {
                modalTitle.textContent = isEditMode ? 'Edit ION Channel' : 'Add New ION Channel';
            }
            
            // Update buttons
            updateWizardButtons();
        }
        
        function updateStepNavigation() {
            document.querySelectorAll('.step-button').forEach((btn, index) => {
                const stepNum = index + 1;
                btn.classList.remove('active', 'inactive');
                
                if (stepNum === currentStep) {
                    btn.classList.add('active');
                } else {
                    btn.classList.add('inactive');
                }
            });
        }
        
        function updateWizardButtons() {
            // Update button text and style based on mode
            const createBtn = document.getElementById('create-ion-btn');
            const createBtnText = document.getElementById('create-btn-text');
            const modal = document.getElementById('add-ion-modal');
            const isEditMode = modal && modal.dataset.mode === 'edit';
            
            if (createBtnText) {
                createBtnText.textContent = isEditMode ? 'Update ION Channel' : 'Create ION Channel';
            }
            
            if (createBtn) {
                if (isEditMode) {
                    createBtn.classList.add('edit-mode');
                } else {
                    createBtn.classList.remove('edit-mode');
                }
            }
        }
        
        function goToStep(stepNum) {
            if (stepNum >= 1 && stepNum <= totalSteps) {
                currentStep = stepNum;
                updateWizardStep();
                updateStepNavigation();
                if (currentStep === 5) {
                    updatePreview();
                }
            }
        }
        
        function validateCurrentStep() {
            switch(currentStep) {
                case 1:
                    const cityName = document.getElementById('city_name').value.trim();
                    if (!cityName) {
                        alert('Please enter a city/town name.');
                        return false;
                    }
                    return true;
                case 2:
                    return true; // Media is optional
                case 3:
                    return true; // SEO fields are optional
                case 4:
                    return true; // Geo fields are optional
                case 5:
                    return true;
                default:
                    return true;
            }
        }
        
        function updatePreview() {
            if (currentStep !== 5) return;
            
            // Update preview values
            const updates = {
                'preview-city-name': document.getElementById('city_name').value || 'Not specified',
                'preview-status': document.getElementById('status').value || 'Draft Page',
                'preview-channel-name': document.getElementById('channel_name').value || 'Not specified',
                'preview-domain-name': document.getElementById('custom_domain').value || 'Not specified',
                'preview-country': document.getElementById('country').selectedOptions[0]?.text || 'Not specified',
                'preview-state': document.getElementById('state').selectedOptions[0]?.text || 'All States/Provinces',
                'preview-title': document.getElementById('title').value || 'Not specified',
                'preview-description': document.getElementById('description').value || 'Not specified'
            };
            
            Object.entries(updates).forEach(([id, value]) => {
                const element = document.getElementById(id);
                if (element) {
                    element.textContent = value;
                }
            });
        }
        
        function submitWizardForm() {
            // Validate current step before submitting
            if (!validateCurrentStep()) {
                return;
            }
            
            const modal = document.getElementById('add-ion-modal');
            const isEditMode = modal.dataset.mode === 'edit';
            const cityId = modal.dataset.cityId;
            
            console.log(isEditMode ? 'Update ION via wizard' : 'Create ION via wizard');
            
            const form = document.getElementById('add-ion-form');
            const formData = new FormData(form);
            
            // Combine header image and uploaded media files for image_path
            let allMediaUrls = [];
            
            // Add header image if provided
            const headerImageUrl = document.getElementById('header_image_url').value.trim();
            if (headerImageUrl) {
                allMediaUrls.push(headerImageUrl);
            }
            
            // Add uploaded media files
            if (uploadedMediaFiles.length > 0) {
                allMediaUrls = allMediaUrls.concat(uploadedMediaFiles);
            }
            
            // Set image_path with comma-separated URLs
            if (allMediaUrls.length > 0) {
                const mediaUrls = allMediaUrls.join(',');
                formData.set('image_path', mediaUrls);
                console.log('🎬 Setting image_path:', mediaUrls);
            }
            
            if (isEditMode) {
                formData.append('update_ion', '1');
                formData.append('city_id', cityId);
            } else {
                formData.append('create_ion', '1');
            }
            
            // Show loading state
            const createBtn = document.getElementById('create-ion-btn');
            const originalText = document.getElementById('create-btn-text').textContent;
            createBtn.disabled = true;
            document.getElementById('create-btn-text').textContent = '⏳ Creating...';
            
            fetch('add-ion-handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    hideModal();
                    showSuccessModal();
                    if (typeof loadDirectory === 'function') {
                        loadDirectory();
                    }
                } else {
                    alert('Error: ' + (data.error || 'Unknown error occurred'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error: Network or server error occurred');
            })
            .finally(() => {
                createBtn.disabled = false;
                document.getElementById('create-btn-text').textContent = originalText;
            });
        }
        
        function showSuccessModal() {
            const successModal = document.getElementById('success-modal');
            if (successModal) {
                successModal.style.display = 'flex';
                setTimeout(() => successModal.classList.add('show'), 10);
                
                // Add confetti effect
                createConfetti();
            }
        }
        
        function hideSuccessModal() {
            const successModal = document.getElementById('success-modal');
            if (successModal) {
                successModal.classList.remove('show');
                setTimeout(() => successModal.style.display = 'none', 300);
            }
        }
        
        function createConfetti() {
            // Simple confetti effect
            const colors = ['#ff0000', '#00ff00', '#0000ff', '#ffff00', '#ff00ff', '#00ffff'];
            const confettiCount = 50;
            
            for (let i = 0; i < confettiCount; i++) {
                setTimeout(() => {
                    const confetti = document.createElement('div');
                    confetti.style.position = 'fixed';
                    confetti.style.left = Math.random() * 100 + 'vw';
                    confetti.style.top = '-10px';
                    confetti.style.width = '10px';
                    confetti.style.height = '10px';
                    confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                    confetti.style.pointerEvents = 'none';
                    confetti.style.zIndex = '9999';
                    confetti.style.borderRadius = '50%';
                    
                    document.body.appendChild(confetti);
                    
                    const animation = confetti.animate([
                        { transform: 'translateY(0px) rotate(0deg)', opacity: 1 },
                        { transform: `translateY(${window.innerHeight + 100}px) rotate(720deg)`, opacity: 0 }
                    ], {
                        duration: Math.random() * 2000 + 1000,
                        easing: 'cubic-bezier(0.25, 0.46, 0.45, 0.94)'
                    });
                    
                    animation.onfinish = () => confetti.remove();
                }, i * 50);
            }
        }
        
        // Form validation and character counting
        function initializeFormValidation() {
            const fieldsToValidate = [
                { id: 'city_name', required: true, maxLength: 100 },
                { id: 'channel_name', required: false, maxLength: 100 },
                { id: 'custom_domain', required: true, maxLength: 253 }
            ];
            
            fieldsToValidate.forEach(field => {
                const input = document.getElementById(field.id);
                const charCount = document.getElementById(field.id + '_count');
                const inputGroup = input?.closest('.input-group');
                
                if (input && charCount) {
                    // Initial character count
                    updateCharCount(input, charCount, field.maxLength);
                    
                    // Add event listeners
                    input.addEventListener('input', () => {
                        updateCharCount(input, charCount, field.maxLength);
                        validateField(input, inputGroup, field.required, field.maxLength);
                    });
                    
                    input.addEventListener('blur', () => {
                        validateField(input, inputGroup, field.required, field.maxLength);
                    });
                    
                    // Initial validation
                    validateField(input, inputGroup, field.required, field.maxLength);
                }
            });
            
            // Domain-specific validation
            const domainInput = document.getElementById('custom_domain');
            if (domainInput) {
                domainInput.addEventListener('input', () => {
                    const inputGroup = domainInput.closest('.input-group');
                    validateDomainField(domainInput, inputGroup);
                });
            }
        }
        
        function updateCharCount(input, charCountElement, maxLength) {
            const currentLength = input.value.length;
            charCountElement.textContent = `(${currentLength})`;
            
            // Add visual feedback based on character count
            charCountElement.classList.remove('updated', 'warning', 'error');
            
            if (currentLength > 0) {
                charCountElement.classList.add('updated');
            }
            
            if (currentLength > maxLength * 0.8) {
                charCountElement.classList.add('warning');
            }
            
            if (currentLength > maxLength) {
                charCountElement.classList.add('error');
            }
        }
        
        function validateField(input, inputGroup, required, maxLength) {
            if (!inputGroup) return;
            
            const value = input.value.trim();
            const isValid = validateFieldValue(value, required, maxLength);
            
            // Clear previous states
            inputGroup.classList.remove('valid', 'invalid');
            
            // Add appropriate state
            if (value.length > 0) {
                if (isValid) {
                    inputGroup.classList.add('valid');
                } else {
                    inputGroup.classList.add('invalid');
                }
            }
        }
        
        function validateDomainField(input, inputGroup) {
            if (!inputGroup) return;
            
            const value = input.value.trim();
            
            // Clear previous states
            inputGroup.classList.remove('valid', 'invalid');
            
            if (value.length > 0) {
                // Basic domain validation
                const domainRegex = /^[a-zA-Z0-9][a-zA-Z0-9-]{0,61}[a-zA-Z0-9]?\.[a-zA-Z]{2,}$/;
                const isValid = domainRegex.test(value) && value.length <= 253;
                
                if (isValid) {
                    inputGroup.classList.add('valid');
                } else {
                    inputGroup.classList.add('invalid');
                }
            }
        }
        
        function validateFieldValue(value, required, maxLength) {
            if (required && value.length === 0) {
                return false;
            }
            
            if (maxLength && value.length > maxLength) {
                return false;
            }
            
            return true;
        }

        // Function to reset modal to add mode
        function resetModalToAddMode() {
            const addModal = document.getElementById('add-ion-modal');
            const modalTitle = document.getElementById('modal-title');
            const submitBtnText = document.getElementById('submit-btn-text');
            const submitBtnIcon = document.getElementById('submit-btn-icon');
            const form = document.getElementById('add-ion-form');
            
            // Reset modal state
            addModal.dataset.mode = 'add';
            addModal.removeAttribute('data-city-id');
            
            // Remove delete button if present (only shown in edit mode)
            const deleteBtn = document.getElementById('delete-city-btn');
            if (deleteBtn) {
                deleteBtn.remove();
            }
            
            // Reset UI text
            modalTitle.textContent = 'Add New ION Channel';
            if (submitBtnText) submitBtnText.textContent = 'Create ION Channel';
            if (submitBtnIcon) submitBtnIcon.textContent = '✨';
            
            // Update wizard buttons for add mode
            updateWizardButtons();
            
            // Reset form
            if (form) form.reset();
            
            // Ensure countries are loaded
            loadCountriesIntoDropdown();
            
            // Reset character counts if function exists
            if (typeof updateAllCharacterCounts === 'function') {
                updateAllCharacterCounts();
            }
            
            // Clear validation states when resetting to add mode
            const validationFields = ['city_name', 'channel_name', 'custom_domain'];
            validationFields.forEach(fieldId => {
                const inputField = document.getElementById(fieldId);
                const inputGroup = inputField?.closest('.input-group');
                if (inputGroup) {
                    inputGroup.classList.remove('valid', 'invalid');
                }
            });
        }
        
        if (addIonBtn) addIonBtn.addEventListener('click', showModal);
        if (mobileAddIonBtn) {
            mobileAddIonBtn.addEventListener('click', () => {
                showModal();
                mobileMenu.classList.remove('active');
            });
        }
        
        const closeModalBtn = document.getElementById('close-modal');
        if(closeModalBtn) closeModalBtn.onclick = hideModal;
        if(closeVideoModal) closeVideoModal.onclick = () => videoModal.style.display = 'none';
        
        // Wizard navigation event listeners
        const createIonBtn = document.getElementById('create-ion-btn');
        const closeSuccessBtn = document.getElementById('close-success-btn');
        const viewChannelBtn = document.getElementById('view-channel-btn');
        
        if(createIonBtn) createIonBtn.addEventListener('click', submitWizardForm);
        if(closeSuccessBtn) closeSuccessBtn.addEventListener('click', hideSuccessModal);
        if(viewChannelBtn) viewChannelBtn.addEventListener('click', () => {
            hideSuccessModal();
            // Refresh directory to show new channel
            if (typeof loadDirectory === 'function') {
                loadDirectory();
            }
        });
        
        // Step button navigation
        document.querySelectorAll('.step-button').forEach(btn => {
            btn.addEventListener('click', () => {
                const stepNum = parseInt(btn.dataset.step);
                goToStep(stepNum);
            });
        });
        
        // Media upload functionality
        function initializeMediaUpload() {
            const mediaFilesInput = document.getElementById('media-files');
            const uploadArea = document.getElementById('media-upload-area');
            const uploadedFilesList = document.getElementById('uploaded-files-list');
            
            if (mediaFilesInput && uploadArea) {
                // Drag and drop
                uploadArea.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    uploadArea.style.backgroundColor = 'hsl(var(--muted))';
                });
                
                uploadArea.addEventListener('dragleave', (e) => {
                    e.preventDefault();
                    uploadArea.style.backgroundColor = '';
                });
                
                uploadArea.addEventListener('drop', (e) => {
                    e.preventDefault();
                    uploadArea.style.backgroundColor = '';
                    const files = Array.from(e.dataTransfer.files);
                    handleFileUpload(files);
                });
                
                uploadArea.addEventListener('click', () => {
                    mediaFilesInput.click();
                });
                
                mediaFilesInput.addEventListener('change', (e) => {
                    const files = Array.from(e.target.files);
                    handleFileUpload(files);
                });
            }
            
            async function handleFileUpload(files) {
                for (const file of files) {
                    if (file.type.startsWith('image/') || file.type.startsWith('video/')) {
                        try {
                            // Show upload progress
                            const uploadStatus = document.createElement('div');
                            uploadStatus.className = 'upload-progress';
                            uploadStatus.innerHTML = `
                                <div class="flex items-center justify-between p-2 bg-muted rounded mb-2">
                                    <span class="text-sm">📤 Uploading ${file.name}...</span>
                                    <div class="spinner">⏳</div>
                                </div>
                            `;
                            if (uploadedFilesList) {
                                uploadedFilesList.appendChild(uploadStatus);
                            }
                            
                            // Create FormData for file upload
                            const formData = new FormData();
                            formData.append('media_file', file);
                            
                            // Upload file to server
                            const response = await fetch('add-ion-handler.php?action=upload_media', {
                                method: 'POST',
                                body: formData
                            });
                            
                            const result = await response.json();
                            
                            // Remove upload progress indicator
                            if (uploadStatus.parentNode) {
                                uploadStatus.parentNode.removeChild(uploadStatus);
                            }
                            
                            if (result.success) {
                                // Add successfully uploaded file URL
                                uploadedMediaFiles.push(result.url);
                                updateUploadedFilesList();
                                console.log(`✅ Successfully uploaded: ${result.url}`);
                            } else {
                                console.error(`❌ Upload failed for ${file.name}:`, result.error);
                                alert(`Failed to upload ${file.name}: ${result.error}`);
                            }
                        } catch (error) {
                            console.error(`❌ Upload error for ${file.name}:`, error);
                            alert(`Network error uploading ${file.name}. Please try again.`);
                        }
                    } else {
                        alert(`Invalid file type: ${file.name}. Only images and videos are allowed.`);
                    }
                }
            }
            
            function updateUploadedFilesList() {
                if (uploadedFilesList) {
                    if (uploadedMediaFiles.length === 0) {
                        uploadedFilesList.innerHTML = '<span class="text-muted-foreground">No files uploaded yet</span>';
                    } else {
                        uploadedFilesList.innerHTML = uploadedMediaFiles.map((url, index) => {
                            const fileName = url.split('/').pop();
                            return `
                                <div class="flex items-center justify-between p-2 bg-muted rounded mb-2">
                                    <span class="text-sm">${fileName}</span>
                                    <button type="button" onclick="removeMediaFile(${index})" class="btn-ghost text-xs">✕</button>
                                </div>
                            `;
                        }).join('');
                    }
                    
                    // Update hidden field
                    const imagePathField = document.getElementById('image_path');
                    if (imagePathField) {
                        imagePathField.value = uploadedMediaFiles.join(',');
                    }
                }
            }
            
            // Global function to remove media file
            window.removeMediaFile = function(index) {
                uploadedMediaFiles.splice(index, 1);
                updateUploadedFilesList();
            };
        }
        
        // Initialize media upload when DOM is ready
        initializeMediaUpload();
        
        // Initialize form validation and character counting
        initializeFormValidation();
        
        // Form structure loaded successfully
        
        // Improved modal closing - only on explicit user actions
        function setupModalClosing() {
            // ION Channel Modal
            const ionModal = document.getElementById('add-ion-modal');
            if (ionModal) {
                ionModal.addEventListener('click', (event) => {
                    // Only close if clicking directly on the modal overlay (not its children)
                    if (event.target === ionModal) {
                        hideModal();
                    }
                });
            }
            
            // Video Modal
            const videoModal = document.getElementById('video-modal');
            if (videoModal) {
                videoModal.addEventListener('click', (event) => {
                    if (event.target === videoModal) {
                        videoModal.style.display = 'none';
                    }
                });
            }
            
            // Success Modal
            const successModal = document.getElementById('success-modal');
            if (successModal) {
                successModal.addEventListener('click', (event) => {
                    if (event.target === successModal) {
                        hideSuccessModal();
                    }
                });
            }
            
            // Escape key support for all modals
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    // Close ION modal if open
                    if (ionModal && ionModal.style.display === 'flex') {
                        hideModal();
                    }
                    // Close video modal if open
                    if (videoModal && videoModal.style.display === 'flex') {
                        videoModal.style.display = 'none';
                    }
                    // Close success modal if open
                    if (successModal && successModal.style.display === 'flex') {
                        hideSuccessModal();
                    }
                }
            });
        }
        
        // Initialize modal closing behavior
        setupModalClosing();

        // Theme toggle functionality
        const themeToggle = document.getElementById('theme-toggle');
        if (themeToggle) {
            // Check saved theme preference
            const savedTheme = localStorage.getItem('ion-theme');
            if (savedTheme === 'dark') {
                document.body.classList.add('dark');
            }
            
            // Update icon based on current theme
            function updateThemeIcon() {
                const lightIcon = themeToggle.querySelector('.light-icon');
                const darkIcon = themeToggle.querySelector('.dark-icon');
                const isDark = document.body.classList.contains('dark');
                
                if (lightIcon && darkIcon) {
                    if (isDark) {
                        lightIcon.style.opacity = '0';
                        darkIcon.style.opacity = '1';
                    } else {
                        lightIcon.style.opacity = '1';
                        darkIcon.style.opacity = '0';
                    }
                }
            }
            
            // Initial icon update
            updateThemeIcon();
            
            themeToggle.addEventListener('click', () => {
                document.body.classList.toggle('dark');
                const isDark = document.body.classList.contains('dark');
                localStorage.setItem('ion-theme', isDark ? 'dark' : 'light');
                updateThemeIcon();
            });
        }

        // Expandable sections functionality
        document.querySelectorAll('.section-toggle').forEach(toggle => {
            toggle.addEventListener('click', () => {
                const targetId = toggle.getAttribute('data-target');
                const targetSection = document.getElementById(targetId);
                const toggleIcon = toggle.querySelector('.toggle-icon');
                
                if (targetSection && toggleIcon) {
                    targetSection.classList.toggle('collapsed');
                    // Only update the icon, not the entire button text
                    toggleIcon.textContent = targetSection.classList.contains('collapsed') ? '▼' : '▲';
                    // Set aria-expanded for accessibility
                    toggle.setAttribute('aria-expanded', !targetSection.classList.contains('collapsed'));
                }
            });
        });

        const countryFilter = document.getElementById('country-filter');
        const stateFilter = document.getElementById('state-filter');
        const allStates = <?php 
            try {
                $states_data = $wpdb->get_results("SELECT DISTINCT state_code, state_name, country_code FROM $table WHERE state_name != '' ORDER BY state_name");
                echo json_encode($states_data ?: []);
            } catch (Exception $e) {
                error_log('States query failed: ' . $e->getMessage());
                echo '[]';
            }
        ?>;

        function updateStates(countryCode, stateDropdown, selectedState) {
            stateDropdown.innerHTML = '<option value="">All States/Provinces</option>';
            if (countryCode) {
                let filteredStates = allStates.filter(s => s.country_code === countryCode);
                const naState = filteredStates.find(s => s.state_name === 'N/A');
                filteredStates = filteredStates.filter(s => s.state_name !== 'N/A');
                filteredStates.sort((a, b) => a.state_name.localeCompare(b.state_name));
                if (naState) filteredStates.push(naState);

                filteredStates.forEach(s => {
                    const option = document.createElement('option');
                    const value = (s.state_name === 'N/A') ? 'NA_STATE' : s.state_code;
                    option.value = value;
                    option.textContent = s.state_name;
                    if (value === selectedState) option.selected = true;
                    stateDropdown.appendChild(option);
                });
                stateDropdown.disabled = false;
            } else {
                stateDropdown.disabled = true;
            }
        }

        if(countryFilter) {
            // Initialize states if country is selected
            if (countryFilter.value) {
                updateStates(countryFilter.value, stateFilter, '<?= $state_filter ?>');
            }
            countryFilter.addEventListener('change', (e) => {
                updateStates(e.target.value, stateFilter, '');
                document.getElementById('filter-form').submit();
            });
        }
        
        const mobileCountryFilter = document.getElementById('mobile-country-filter');
        const mobileStateFilter = document.getElementById('mobile-state-filter');
        if (mobileCountryFilter) {
            // Initialize states if country is selected
            if (mobileCountryFilter.value) {
                updateStates(mobileCountryFilter.value, mobileStateFilter, '<?= $state_filter ?>');
            }
            mobileCountryFilter.addEventListener('change', (e) => {
                updateStates(e.target.value, mobileStateFilter, '');
                document.getElementById('mobile-filter-form').submit();
            });
        }

        // JavaScript version of getFlagEmoji function
        function getFlagEmoji(countryCode) {
            if (!countryCode || countryCode.length !== 2) {
                return '';
            }
            return `<img src="https://ions.com/assets/flags/${countryCode.toLowerCase()}.svg" alt="${countryCode.toUpperCase()}" class="flag-icon" width="16" height="12">`;
        }

        // Function to load countries data into the dropdown
        function loadCountriesIntoDropdown() {
            const allCountries = window.ION_COUNTRIES || [];
            const modalCountrySelect = document.getElementById('country');
            
            console.log('🌍 Loading countries into dropdown:', allCountries.length);
            
            if(modalCountrySelect && allCountries.length > 0) {
                modalCountrySelect.innerHTML = '<option value="">Select country</option>';
                allCountries.forEach(country => {
                    const option = document.createElement('option');
                    option.value = country.code;
                    option.innerHTML = `${getFlagEmoji(country.code)} ${country.name} (${country.code})`;
                    modalCountrySelect.appendChild(option);
                });
                
                const modalStateSelect = document.getElementById('state');
                // Set up change listener (only once)
                if (!modalCountrySelect.dataset.listenerAdded) {
                    modalCountrySelect.addEventListener('change', (e) => {
                        updateStates(e.target.value, modalStateSelect, '');
                    });
                    modalCountrySelect.dataset.listenerAdded = 'true';
                }
                
                // Initialize states only if a country is selected
                if (modalCountrySelect.value) {
                    updateStates(modalCountrySelect.value, modalStateSelect, '');
                }
                
                return true;
            } else {
                console.error('❌ Countries data not loaded or modal not found');
                return false;
            }
        }

        // Load countries initially
        loadCountriesIntoDropdown();

        // Get form elements
        const modalCountrySelect = document.getElementById('country');
        const modalStateSelect = document.getElementById('state');
        const form = document.getElementById('add-ion-form');
        const previewBtn = document.getElementById('preview-btn');
        const createBtn = document.getElementById('create-ion-btn');
        const fetchGeoBtn = document.getElementById('fetch-geo-btn');
        const previewDiv = document.getElementById('preview');

        // Character count functionality
        function updateCharacterCount(fieldId) {
            const field = document.getElementById(fieldId);
            const countSpan = document.getElementById(fieldId + '_count');
            
            if (field && countSpan) {
                const count = field.value.length;
                countSpan.textContent = `(${count})`;
                console.log(`Updated character count for ${fieldId}: ${count}`);
                
                // Optional: Add color coding for long fields
                if (count > 100) {
                    countSpan.style.color = 'hsl(var(--destructive))';
                } else if (count > 50) {
                    countSpan.style.color = 'hsl(var(--warning))';
                } else {
                    countSpan.style.color = 'hsl(var(--muted-foreground))';
                }
            } else {
                console.warn(`Cannot update character count for ${fieldId}: field=${!!field}, countSpan=${!!countSpan}`);
            }
        }

        // Initialize character count for all fields
        function initializeCharacterCounts() {
            console.log('Starting character count initialization...');
            const characterCountFields = [
                'city_name', 'channel_name', 'custom_domain', 
                'title', 'description', 'seo_title', 'seo_meta_description'
            ];

            characterCountFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                const countSpan = document.getElementById(fieldId + '_count');
                
                // Only log missing elements if we're in a modal/form context
                if (!field && !countSpan) {
                    return; // Skip silently if both are missing
                }
                
                if (!field || !countSpan) {
                    // Only log if one exists but not the other (potential issue)
                    console.log(`Missing elements for ${fieldId}: field=${!!field}, countSpan=${!!countSpan}`);
                    return;
                }
                
                // Both exist, set up the functionality
                field.addEventListener('input', () => {
                    updateCharacterCount(fieldId);
                });
                
                // Initialize count
                updateCharacterCount(fieldId);
                console.log(`Character count initialized for ${fieldId}`);
            });
            console.log('Character count initialization completed');
        }
        
        // Helper function to update all character counts (used in edit mode)
        function updateAllCharacterCounts() {
            const characterCountFields = [
                'city_name', 'channel_name', 'custom_domain', 
                'title', 'description', 'seo_title', 'seo_meta_description'
            ];
            characterCountFields.forEach(fieldId => {
                updateCharacterCount(fieldId);
            });
        }

        // Debounce function to limit API calls
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        // Function to validate domain format
        function validateDomainFormat(domain) {
            // List of valid TLDs (common ones)
            const validTLDs = [
                'com', 'org', 'net', 'edu', 'gov', 'mil', 'int', 'co', 'io', 'ai', 'app', 'dev',
                'us', 'uk', 'ca', 'de', 'fr', 'jp', 'au', 'br', 'mx', 'es', 'it', 'ru', 'cn', 'in',
                'info', 'biz', 'name', 'pro', 'coop', 'museum', 'aero', 'jobs', 'travel', 'mobi',
                'tel', 'xxx', 'post', 'asia', 'cat', 'eu', 'me', 'tv', 'cc', 'ws', 'tk', 'ml', 'ga', 'cf',
                'club', 'online', 'site', 'website', 'space', 'tech', 'store', 'blog', 'news', 'media',
                'photo', 'pics', 'video', 'music', 'art', 'design', 'style', 'fashion', 'beauty', 'health',
                'fitness', 'food', 'restaurant', 'cafe', 'bar', 'pub', 'wine', 'beer', 'drink', 'shop',
                'shopping', 'market', 'sale', 'deals', 'buy', 'sell', 'trade', 'money', 'finance', 'bank',
                'loan', 'credit', 'insurance', 'invest', 'fund', 'tax', 'law', 'legal', 'attorney', 'lawyer',
                'doctor', 'dental', 'medical', 'clinic', 'hospital', 'care', 'support', 'help', 'service',
                'repair', 'cleaning', 'maintenance', 'construction', 'home', 'house', 'realty', 'property',
                'land', 'garden', 'farm', 'ranch', 'country', 'city', 'town', 'local', 'community', 'social',
                'network', 'chat', 'talk', 'forum', 'group', 'team', 'company', 'business', 'corp', 'inc',
                'llc', 'ltd', 'agency', 'studio', 'lab', 'research', 'science', 'tech', 'software', 'app',
                'web', 'digital', 'cyber', 'data', 'cloud', 'hosting', 'server', 'domain', 'email', 'mail'
            ];
            
            // Basic domain format check
            const domainRegex = /^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)*$/i;
            if (!domainRegex.test(domain)) {
                return { valid: false, error: 'Invalid domain format' };
            }
            
            // Check for valid TLD
            const parts = domain.split('.');
            if (parts.length < 2) {
                return { valid: false, error: 'Domain must have a valid extension' };
            }
            
            const tld = parts[parts.length - 1].toLowerCase();
            if (!validTLDs.includes(tld)) {
                return { valid: false, error: `Invalid domain extension .${tld}` };
            }
            
            return { valid: true };
        }

        // Function to check for duplicates
        function checkDuplicate(fieldId, fieldName, value) {
            const validationStatus = document.getElementById(fieldId + '_validation');
            const inputField = document.getElementById(fieldId);
            
            if (!validationStatus || !inputField) {
                console.log(`Validation elements not found for ${fieldId}`);
                return;
            }
            
            if (!value.trim()) {
                validationStatus.classList.remove('show', 'duplicate', 'valid');
                validationStatus.removeAttribute('data-tooltip');
                inputField.classList.remove('duplicate');
                return;
            }

            // For domain fields, validate format first
            if (fieldName === 'custom_domain') {
                const domainValidation = validateDomainFormat(value.trim());
                if (!domainValidation.valid) {
                    validationStatus.className = 'validation-status show duplicate';
                    validationStatus.setAttribute('data-tooltip', domainValidation.error);
                    inputField.classList.add('duplicate');
                    return;
                }
            }

            // Check if we're in edit mode and get the current record ID
            const modal = document.getElementById('add-ion-modal');
            const isEditMode = modal && modal.dataset.mode === 'edit';
            const currentRecordId = isEditMode ? modal.dataset.cityId : null;

            // Build URL with exclude_id parameter if in edit mode
            let url = `?action=check_duplicate&field=${encodeURIComponent(fieldName)}&value=${encodeURIComponent(value)}`;
            if (isEditMode && currentRecordId) {
                url += `&exclude_id=${encodeURIComponent(currentRecordId)}`;
            }

            console.log(`🔍 Checking duplicate for ${fieldName}: "${value}" (Edit mode: ${isEditMode}, Exclude ID: ${currentRecordId})`);
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    console.log(`🔍 Duplicate check result for ${fieldName}:`, data);
                    
                    if (data.duplicate) {
                        validationStatus.className = 'validation-status show duplicate';
                        validationStatus.setAttribute('data-tooltip', 'Duplicate entry');
                        inputField.classList.add('duplicate');
                        inputField.classList.remove('valid');
                    } else {
                        validationStatus.className = 'validation-status show valid';
                        
                        // Check if domain was cleaned (stored as data attribute)
                        const wasCleaned = inputField.dataset.domainCleaned === 'true';
                        if (wasCleaned && fieldName === 'custom_domain') {
                            validationStatus.setAttribute('data-tooltip', 'Domain automatically cleaned');
                        } else {
                            validationStatus.removeAttribute('data-tooltip');
                        }
                        
                        inputField.classList.remove('duplicate');
                        inputField.classList.add('valid');
                    }
                })
                .catch(err => {
                    console.error('Validation check error:', err);
                    validationStatus.classList.remove('show');
                });
        }

        // Real-time validation initialization
        function initializeValidation() {
            console.log('Starting validation initialization...');
            const validationFields = [
                { id: 'city_name', fieldName: 'city_name' },
                { id: 'channel_name', fieldName: 'channel_name' },
                { id: 'custom_domain', fieldName: 'custom_domain' }
            ];

            // Create debounced validation functions for each field
            validationFields.forEach(field => {
                const inputElement = document.getElementById(field.id);
                const validationElement = document.getElementById(field.id + '_validation');
                
                console.log(`Validation field ${field.id}: input=${!!inputElement}, validation=${!!validationElement}`);
                
                if (inputElement && validationElement) {
                    const debouncedCheck = debounce((value) => {
                        checkDuplicate(field.id, field.fieldName, value);
                    }, 500); // Wait 500ms after user stops typing

                    inputElement.addEventListener('input', function() {
                        debouncedCheck(this.value);
                    });

                    // Also check on blur (when user leaves the field)
                    inputElement.addEventListener('blur', function() {
                        checkDuplicate(field.id, field.fieldName, this.value);
                    });
                    
                    console.log(`Validation initialized for ${field.id}`);
                } else {
                    console.warn(`Missing elements for validation ${field.id}: input=${!!inputElement}, validation=${!!validationElement}`);
                }
            });
            console.log('Validation initialization completed');
        }

        // Auto-fill fields based on city/state/country
        function generateFields() {
            console.log('generateFields() called');
            const city = document.getElementById('city_name').value.trim();
            const state = modalStateSelect.value;
            const country = modalCountrySelect.value;
            
            console.log(`Generate fields data: city="${city}", state="${state}", country="${country}"`);
            
            // Only get state name if a real state is selected (not empty value)
            const stateName = state ? modalStateSelect.options[modalStateSelect.selectedIndex]?.text.split(' (')[0] || '' : '';
            const countryName = modalCountrySelect.options[modalCountrySelect.selectedIndex]?.text.split(' (')[0].replace(/^🇺🇸\s*/, '').replace(/^🏳️\s*/, '') || '';
            
            console.log(`Parsed names: stateName="${stateName}", countryName="${countryName}"`);
            
            if (city) {
                // Channel Name (if not user-edited)
                const channelField = document.getElementById('channel_name');
                if (channelField && !channelField.dataset.userEdited) {
                    let channelName = `ION ${city}`;
                    if (stateName && stateName !== 'N/A') channelName += ` ${stateName}`;
                    channelField.value = channelName;
                    updateCharacterCount('channel_name');
                }
                
                // Title (if not user-edited)
                const titleField = document.getElementById('title');
                if (titleField && !titleField.dataset.userEdited) {
                    let title = `Welcome to ION ${city}`;
                    if (stateName && stateName !== 'N/A') title += ` ${stateName}`;
                    titleField.value = title;
                    updateCharacterCount('title');
                }
                
                // Description (if not user-edited)
                const descField = document.getElementById('description');
                if (descField && !descField.dataset.userEdited) {
                    let desc = `Got your ION ${city}? Explore sports, entertainment, events, businesses & more in ${city}. Stay updated and tune in now!`;
                    descField.value = desc;
                    updateCharacterCount('description');
                }
                
                // SEO Title (if not user-edited)
                const seoTitleField = document.getElementById('seo_title');
                if (seoTitleField && !seoTitleField.dataset.userEdited) {
                    let seoTitle = `ION ${city} Local Community Network`;
                    if (stateName && stateName !== 'N/A') seoTitle += ` | ${stateName}`;
                    seoTitleField.value = seoTitle;
                    updateCharacterCount('seo_title');
                }
                
                // SEO Meta Description (if not user-edited)
                const seoDescField = document.getElementById('seo_meta_description');
                if (seoDescField && !seoDescField.dataset.userEdited) {
                    let seoDesc = `Join the ${city} local community network. Connect with neighbors, discover local events, and support local businesses`;
                    if (stateName && stateName !== 'N/A') seoDesc += ` in ${stateName}`;
                    seoDesc += '.';
                    seoDescField.value = seoDesc;
                    updateCharacterCount('seo_meta_description');
                }
                
                console.log('Auto-fill completed');
            }
        }

        // FORM SPECIFIC FUNCTIONALITY - Only runs if form exists
        if(form) {
            console.log('Form found, initializing form functionality...');
            
            // Track user manual edits to prevent auto-overwriting
            const editableFields = ['channel_name', 'title', 'description', 'seo_title', 'seo_meta_description'];
            editableFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) {
                    field.addEventListener('input', function() {
                        // Mark field as user-edited if user types in it manually
                        this.dataset.userEdited = 'true';
                        console.log(`Field ${fieldId} marked as user-edited`);
                    });
                }
            });

            // Set up auto-generation event listeners
            const cityField = document.getElementById('city_name');
            if (cityField) {
                cityField.addEventListener('input', generateFields);
                console.log('City field event listener attached');
            }
            if (modalStateSelect) {
                modalStateSelect.addEventListener('change', generateFields);
                console.log('State select event listener attached');
            }

            // Initialize character counts
            console.log('Initializing character counts...');
            initializeCharacterCounts();

            // Initialize real-time validation
            console.log('Initializing validation...');
            initializeValidation();

            // Initialize tab functionality for image selector
            const tabBtns = document.querySelectorAll('.tab-btn');
            const tabPanels = document.querySelectorAll('.tab-panel');
            const imageSourceField = document.getElementById('image_source');

            tabBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    const tabId = btn.dataset.tab;
                    
                    // Update tab buttons
                    tabBtns.forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    
                    // Update tab panels
                    tabPanels.forEach(panel => panel.classList.remove('active'));
                    document.getElementById(tabId + '-tab').classList.add('active');
                    
                    // Update hidden field
                    imageSourceField.value = tabId;
                    
                    // Hide/show appropriate image previews when switching tabs
                    const imagePreview = document.getElementById('image-preview');
                    const urlImagePreview = document.getElementById('url-image-preview');
                    
                    if (tabId === 'url') {
                        // Hide upload preview when switching to URL tab
                        if (imagePreview) {
                            imagePreview.style.display = 'none';
                        }
                        // Show URL preview if URL field has content
                        const imageUrlField = document.getElementById('image_url');
                        if (urlImagePreview && imageUrlField && imageUrlField.value.trim()) {
                            imageUrlField.dispatchEvent(new Event('input'));
                        }
                    } else if (tabId === 'upload') {
                        // Hide URL preview when switching to upload tab
                        if (urlImagePreview) {
                            urlImagePreview.style.display = 'none';
                        }
                    }
                });
            });

            // Initialize image upload preview functionality
            const imageUpload = document.getElementById('image_upload');
            if (imageUpload) {
                imageUpload.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    const imagePreview = document.getElementById('image-preview');
                    const previewImage = document.getElementById('preview-image');
                    const previewFilename = document.getElementById('preview-filename');
                    
                    if (file && imagePreview && previewImage && previewFilename) {
                        // Validate file type
                        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                        if (!allowedTypes.includes(file.type)) {
                            alert('Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.');
                            this.value = '';
                            imagePreview.style.display = 'none';
                            return;
                        }
                        
                        // Validate file size (5MB)
                        if (file.size > 5 * 1024 * 1024) {
                            alert('File size too large. Maximum 5MB allowed.');
                            this.value = '';
                            imagePreview.style.display = 'none';
                            return;
                        }
                        
                        // Create preview
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            previewImage.src = e.target.result;
                            previewFilename.textContent = `File: ${file.name} (${(file.size / 1024).toFixed(1)} KB)`;
                            imagePreview.style.display = 'block';
                        };
                        reader.readAsDataURL(file);
                    } else if (imagePreview) {
                        // No file selected, hide preview
                        imagePreview.style.display = 'none';
                    }
                });
            }

            // Initialize image URL preview functionality
            const imageUrlInput = document.getElementById('image_url');
            if (imageUrlInput) {
                // Debounce function to avoid too many requests
                let debounceTimer;
                
                imageUrlInput.addEventListener('input', function(e) {
                    const url = e.target.value.trim();
                    const urlPreview = document.getElementById('url-image-preview');
                    const urlPreviewImage = document.getElementById('url-preview-image');
                    const urlPreviewInfo = document.getElementById('url-preview-info');
                    
                    // Clear previous timer
                    clearTimeout(debounceTimer);
                    
                    if (!url) {
                        // Clear preview when URL is empty
                        if (urlPreview) urlPreview.style.display = 'none';
                        if (urlPreviewImage) urlPreviewImage.src = '';
                        if (urlPreviewInfo) urlPreviewInfo.textContent = '';
                        return;
                    }
                    
                    if (!urlPreview || !urlPreviewImage || !urlPreviewInfo) {
                        return;
                    }
                    
                    // Hide preview while debouncing
                    urlPreview.style.display = 'none';
                    
                    // Debounce the preview loading (wait 500ms after user stops typing)
                    debounceTimer = setTimeout(() => {
                        // Validate URL format
                        try {
                            new URL(url);
                        } catch {
                            urlPreviewInfo.textContent = 'Invalid URL format';
                            urlPreview.style.display = 'block';
                            return;
                        }
                        
                        // Check if URL looks like an image
                        const imageExtensions = /\.(jpg|jpeg|png|gif|webp|svg)(\?.*)?$/i;
                        if (!imageExtensions.test(url)) {
                            urlPreviewInfo.textContent = 'URL does not appear to be an image (supported: JPG, PNG, GIF, WebP, SVG)';
                            urlPreview.style.display = 'block';
                            return;
                        }
                        
                        // Show loading state
                        urlPreviewInfo.textContent = 'Loading image...';
                        urlPreview.style.display = 'block';
                        
                        // Create new image to test loading
                        const testImage = new Image();
                        
                        testImage.onload = function() {
                            // Image loaded successfully
                            urlPreviewImage.src = url;
                            const fileSize = 'Unknown size';
                            urlPreviewInfo.textContent = `Image loaded successfully (${testImage.naturalWidth}×${testImage.naturalHeight})`;
                            urlPreview.style.display = 'block';
                        };
                        
                        testImage.onerror = function() {
                            // Image failed to load
                            urlPreviewImage.src = '';
                            urlPreviewInfo.textContent = 'Failed to load image. Please check the URL.';
                            urlPreview.style.display = 'block';
                        };
                        
                        // Set source to trigger loading
                        testImage.src = url;
                        
                    }, 500); // 500ms debounce
                });
                
                // Also trigger preview when field loses focus (blur)
                imageUrlInput.addEventListener('blur', function(e) {
                    clearTimeout(debounceTimer);
                    const url = e.target.value.trim();
                    const urlPreview = document.getElementById('url-image-preview');
                    
                    if (url && urlPreview) {
                        // Trigger immediate preview on blur
                        imageUrlInput.dispatchEvent(new Event('input'));
                    }
                });
            }

            // Fetch Geo Data functionality
            if (fetchGeoBtn) {
                fetchGeoBtn.addEventListener('click', async () => {
                    console.log('🌍 Fetch Geo Data button clicked');
                    
                    // Get form values
                    const cityName = document.getElementById('city_name')?.value.trim();
                    const stateName = document.getElementById('state')?.value.trim() || '';
                    const countryCode = document.getElementById('country')?.value.trim();
                    
                    console.log('🌍 Form values:', { cityName, stateName, countryCode });
                    
                    // Validate inputs
                    if (!cityName || !countryCode) {
                        alert('Please enter a city name and select a country before fetching geo data.');
                        return;
                    }
                    
                    // Show loading state
                    const btnText = fetchGeoBtn.querySelector('.btn-text');
                    const btnIcon = fetchGeoBtn.querySelector('.btn-icon');
                    const originalText = btnText ? btnText.textContent : 'Fetch Geo Data';
                    const originalIcon = btnIcon ? btnIcon.textContent : '✅';
                    
                    fetchGeoBtn.disabled = true;
                    fetchGeoBtn.style.opacity = '0.6';
                    fetchGeoBtn.style.cursor = 'not-allowed';
                    
                    if (btnText) btnText.textContent = 'Fetching...';
                    if (btnIcon) btnIcon.textContent = '⏳';
                    
                    try {
                        // Build query parameters
                        const params = new URLSearchParams({
                            action: 'fetch_geo',
                            town: cityName,
                            state: stateName,
                            country: countryCode
                        });
                        
                        console.log('🌍 Fetching geo data for:', { cityName, stateName, countryCode });
                        
                        // Make API request
                        const response = await fetch(`add-ion-handler.php?${params.toString()}`);
                        const data = await response.json();
                        
                        console.log('🌍 Geo data response:', data);
                        
                        if (data.success) {
                            console.log('🌍 Success! Setting coordinates and population');
                            
                            // Populate the fields
                            const latField = document.getElementById('latitude');
                            const lonField = document.getElementById('longitude');
                            const popField = document.getElementById('population');
                            
                            if (latField && data.lat) {
                                latField.value = parseFloat(data.lat).toFixed(6);
                                console.log('🌍 Set latitude:', data.lat);
                            }
                            
                            if (lonField && data.lon) {
                                lonField.value = parseFloat(data.lon).toFixed(6);
                                console.log('🌍 Set longitude:', data.lon);
                            }
                            
                            if (popField && data.pop) {
                                // Format population with commas
                                const formattedPop = parseInt(data.pop).toLocaleString();
                                popField.value = formattedPop;
                                console.log('🌍 Set population:', formattedPop);
                            }
                            
                            // Show success message
                            const successMsg = `✅ Geo data fetched successfully!${data.pop ? '' : ' (Population data not found)'}`;
                            
                            // Create temporary success indicator
                            const successElement = document.createElement('div');
                            successElement.style.cssText = `
                                position: absolute;
                                top: -40px;
                                left: 50%;
                                transform: translateX(-50%);
                                background: hsl(160 84% 39%);
                                color: white;
                                padding: 8px 12px;
                                border-radius: 6px;
                                font-size: 14px;
                                font-weight: 500;
                                white-space: nowrap;
                                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                                z-index: 1000;
                                animation: fadeInOut 3s ease-in-out;
                            `;
                            successElement.textContent = successMsg;
                            
                            // Add CSS animation if not already added
                            if (!document.querySelector('#temp-geo-success-styles')) {
                                const style = document.createElement('style');
                                style.id = 'temp-geo-success-styles';
                                style.textContent = `
                                    @keyframes fadeInOut {
                                        0% { opacity: 0; transform: translateX(-50%) translateY(10px); }
                                        20%, 80% { opacity: 1; transform: translateX(-50%) translateY(0); }
                                        100% { opacity: 0; transform: translateX(-50%) translateY(-10px); }
                                    }
                                `;
                                document.head.appendChild(style);
                            }
                            
                            // Position relative to fetch button
                            fetchGeoBtn.style.position = 'relative';
                            fetchGeoBtn.appendChild(successElement);
                            
                            // Remove success message after animation
                            setTimeout(() => {
                                if (successElement.parentNode) {
                                    successElement.parentNode.removeChild(successElement);
                                }
                            }, 3000);
                            
                        } else {
                            // Show error message
                            const errorMsg = data.error || 'Failed to fetch geo data. Please try again.';
                            alert('❌ ' + errorMsg);
                            console.error('🌍 Geo data fetch failed:', data);
                        }
                        
                    } catch (error) {
                        console.error('🌍 Geo data fetch error:', error);
                        alert('❌ Network error while fetching geo data. Please check your connection and try again.');
                    } finally {
                        // Reset button state
                        fetchGeoBtn.disabled = false;
                        fetchGeoBtn.style.opacity = '1';
                        fetchGeoBtn.style.cursor = 'pointer';
                        
                        if (btnText) btnText.textContent = originalText;
                        if (btnIcon) btnIcon.textContent = originalIcon;
                    }
                });
                
                console.log('🌍 Fetch Geo Data button initialized');
            }

            // Create ION button (now handled by wizard)
            if(createBtn) {
                console.log('⚠️ Legacy Create ION button found - wizard flow handles submission now');
                // The wizard's submitWizardForm() function handles form submission
            }
        }

        
        // VIDEO MODAL AND DOMAIN MANAGEMENT LOGIC
        let currentVideos = [];
        let currentCategories = [];
        let currentSlug = '';
        let currentSource = '';
        let currentPage = 1;
        let currentPagination = null;

        // Function to fetch and display videos
        function fetchVideos(slug, source, page = 1) {
            console.log(`🎥 Fetching videos for slug: "${slug}", source: "${source}", page: ${page}`);
            
            const videoList = document.getElementById('video-list');
            const noVideosMessage = document.getElementById('no-videos-message');
            const videoModalTitle = document.getElementById('video-modal-title');
            const videoIcon = document.getElementById('video-icon');
            const searchInput = document.getElementById('video-search');
            const categoryFilter = document.getElementById('category-filter');
            
            // Update modal title and icon
            if (videoModalTitle) {
                videoModalTitle.textContent = `Videos for ${slug}`;
            }
            
            // Set appropriate icon based on source
            if (videoIcon) {
                switch(source) {
                    case 'youtube':
                        videoIcon.src = '/assets/icons/youtube.svg';
                        videoIcon.alt = 'YouTube';
                        break;
                    case 'vimeo':
                        videoIcon.src = '/assets/icons/vimeo.svg';
                        videoIcon.alt = 'Vimeo';
                        break;
                    default:
                        videoIcon.src = '/assets/icons/muvi.svg';
                        videoIcon.alt = 'Video';
                }
            }
            
            // Show loading state
            if (videoList) {
                videoList.innerHTML = '<li class="loading">🔄 Loading videos...</li>';
            }
            
            // Fetch videos from API
            const selectedCategory = categoryFilter ? categoryFilter.value : '';
            const searchQuery = searchInput ? searchInput.value.trim() : '';
            
            const params = new URLSearchParams({
                action: 'get_videos',
                slug: slug,
                source: source,
                page: page,
                category: selectedCategory,
                search: searchQuery
            });
            
            fetch(`?${params.toString()}`)
                .then(response => {
                    console.log('📥 Video fetch response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('📋 Video data received:', data);
                    
                    if (data.success) {
                        currentVideos = data.videos || [];
                        currentPagination = data.pagination || null;
                        
                        if (currentVideos.length > 0) {
                            displayVideos(currentVideos);
                            if (noVideosMessage) noVideosMessage.style.display = 'none';
                        } else {
                            if (videoList) videoList.innerHTML = '';
                            if (noVideosMessage) noVideosMessage.style.display = 'block';
                        }
                    } else {
                        console.error('❌ Failed to fetch videos:', data.error);
                        if (videoList) {
                            videoList.innerHTML = '<li class="error">❌ Failed to load videos</li>';
                        }
                    }
                })
                .catch(error => {
                    console.error('❌ Video fetch error:', error);
                    if (videoList) {
                        videoList.innerHTML = '<li class="error">❌ Network error loading videos</li>';
                    }
                });
        }
        
        // Function to generate thumbnail URL from video link
        function generateThumbnail(video) {
            let thumbnailUrl = '/assets/icons/video.svg'; // Default fallback
            
            if (video.video_link) {
                const videoLink = video.video_link.toLowerCase();
                
                // YouTube thumbnail generation
                if (videoLink.includes('youtube.com') || videoLink.includes('youtu.be')) {
                    let videoId = '';
                    
                    // Extract video ID from different YouTube URL formats
                    if (videoLink.includes('youtube.com/watch?v=')) {
                        videoId = video.video_link.split('v=')[1]?.split('&')[0];
                    } else if (videoLink.includes('youtu.be/')) {
                        videoId = video.video_link.split('youtu.be/')[1]?.split('?')[0];
                    } else if (videoLink.includes('youtube.com/embed/')) {
                        videoId = video.video_link.split('/embed/')[1]?.split('?')[0];
                    }
                    
                    if (videoId) {
                        thumbnailUrl = `https://img.youtube.com/vi/${videoId}/hqdefault.jpg`;
                    }
                }
                // Vimeo thumbnail generation (would need API call, using placeholder for now)
                else if (videoLink.includes('vimeo.com')) {
                    thumbnailUrl = '/assets/icons/vimeo-placeholder.svg';
                }
            }
            
            return thumbnailUrl;
        }
        
        // Function to load video categories and populate dropdowns
        function loadVideoCategories() {
            console.log('📂 Loading video categories...');
            
            fetch('?action=fetch_categories')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.categories) {
                        console.log('✅ Categories loaded:', data.categories);
                        
                        // Populate category filter dropdown (video listing modal)
                        const categoryFilter = document.getElementById('category-filter');
                        if (categoryFilter) {
                            // Clear existing options except "All Categories"
                            const allCategoriesOption = categoryFilter.querySelector('option[value=""]');
                            categoryFilter.innerHTML = '';
                            if (allCategoriesOption) {
                                categoryFilter.appendChild(allCategoriesOption);
                            } else {
                                categoryFilter.innerHTML = '<option value="">All Categories</option>';
                            }
                            
                            // Add categories with "ION " prefix
                            data.categories.forEach(category => {
                                const option = document.createElement('option');
                                option.value = category;
                                option.textContent = `ION ${category}`;
                                categoryFilter.appendChild(option);
                            });
                            
                            console.log('✅ Category filter populated with', data.categories.length, 'categories');
                        }
                        
                        // Populate video category dropdown (Add Video modal)
                        const videoCategory = document.getElementById('video-category');
                        if (videoCategory) {
                            // Clear existing options except "Select Category"
                            const selectOption = videoCategory.querySelector('option[value=""]');
                            videoCategory.innerHTML = '';
                            if (selectOption) {
                                videoCategory.appendChild(selectOption);
                            } else {
                                videoCategory.innerHTML = '<option value="">Select Category</option>';
                            }
                            
                            // Add categories with "ION " prefix
                            data.categories.forEach(category => {
                                const option = document.createElement('option');
                                option.value = category;
                                option.textContent = `ION ${category}`;
                                videoCategory.appendChild(option);
                            });
                            
                            console.log('✅ Video category dropdown populated with', data.categories.length, 'categories');
                        }
                        
                    } else {
                        console.error('❌ Failed to load categories:', data.error || 'Unknown error');
                    }
                })
                .catch(error => {
                    console.error('❌ Categories fetch error:', error);
                });
        }
        
        // Function to delete video with confirmation
        function deleteVideo(videoId, videoTitle) {
            if (confirm(`Are you sure you want to delete the video "${videoTitle}"?\n\nThis action cannot be undone.`)) {
                console.log('🗑️ Deleting video ID:', videoId);
                
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete_video&video_id=${encodeURIComponent(videoId)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('✅ Video deleted successfully');
                        // Refresh the video list
                        fetchVideos(currentSlug, currentSource, currentPage);
                    } else {
                        console.error('❌ Failed to delete video:', data.error);
                        alert('Failed to delete video: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('❌ Delete video error:', error);
                    alert('Failed to delete video: ' + error.message);
                });
            }
        }
        
        // Function to display videos in the modal
        function displayVideos(videos) {
            const videoList = document.getElementById('video-list');
            const videoGrid = document.getElementById('video-grid');
            const videoCountDisplay = document.getElementById('video-count');
            const paginationInfo = document.getElementById('pagination-info-text');
            const paginationContainer = document.getElementById('video-pagination');
            
            if (!videoList || !videoGrid) return;
            
            // Clear both containers
            videoList.innerHTML = '';
            videoGrid.innerHTML = '';
            
            // Update video count display
            const totalVideos = currentPagination ? currentPagination.total_items : videos.length;
            if (videoCountDisplay) {
                videoCountDisplay.textContent = `${totalVideos} video${totalVideos !== 1 ? 's' : ''}`;
            }
            
            // Update pagination info
            if (paginationInfo && currentPagination) {
                const start = ((currentPagination.current_page - 1) * currentPagination.per_page) + 1;
                const end = Math.min(start + videos.length - 1, currentPagination.total_items);
                paginationInfo.textContent = `Showing ${start}-${end} of ${currentPagination.total_items}`;
            }
            
            // Check current view mode
            const cardViewBtn = document.getElementById('card-view-btn');
            const listViewBtn = document.getElementById('list-view-btn');
            const isCardView = cardViewBtn && cardViewBtn.classList.contains('active');
            
            videos.forEach(video => {
                const thumbnailUrl = generateThumbnail(video);
                const videoTitle = video.title || 'Untitled Video';
                const videoDescription = video.description || '';
                const videoDate = video.date_added ? new Date(video.date_added).toLocaleDateString() : '';
                const videoSource = video.videotype || currentSource;
                
                if (isCardView) {
                    // Create card view item
                    const cardDiv = document.createElement('div');
                    cardDiv.className = 'video-card';
                    cardDiv.innerHTML = `
                        <div class="video-card-thumbnail">
                            <img src="${thumbnailUrl}" alt="Video thumbnail" loading="lazy" 
                                 onerror="this.src='/assets/icons/video.svg'">
                            <div class="video-duration">${video.duration || ''}</div>
                        </div>
                        <div class="video-card-info">
                            <h4 class="video-card-title">${videoTitle}</h4>
                            <p class="video-card-description">${videoDescription}</p>
                            <div class="video-card-meta">
                                <span class="video-source">${videoSource}</span>
                                <span class="video-date">${videoDate}</span>
                                <button class="video-action-btn delete" onclick="deleteVideo('${video.id}', '${videoTitle.replace(/'/g, "\\'")}')" title="Delete Video">
                                    🗑️
                                </button>
                            </div>
                        </div>
                    `;
                    videoGrid.appendChild(cardDiv);
                } else {
                    // Create list view item
                    const li = document.createElement('li');
                    li.className = 'video-item';
                    li.innerHTML = `
                        <div class="video-thumbnail">
                            <img src="${thumbnailUrl}" alt="Video thumbnail" loading="lazy"
                                 onerror="this.src='/assets/icons/video.svg'">
                            <div class="video-duration">${video.duration || ''}</div>
                        </div>
                        <div class="video-info">
                            <h4 class="video-title">${videoTitle}</h4>
                            <p class="video-description">${videoDescription}</p>
                            <div class="video-meta">
                                <span class="video-source">${videoSource}</span>
                                <span class="video-date">${videoDate}</span>
                                <button class="video-action-btn delete" onclick="deleteVideo('${video.id}', '${videoTitle.replace(/'/g, "\\'")}')" title="Delete Video">
                                    🗑️
                                </button>
                            </div>
                        </div>
                    `;
                    videoList.appendChild(li);
                }
            });
            
            // Show/hide appropriate container
            if (isCardView) {
                videoList.style.display = 'none';
                videoGrid.style.display = 'grid';
            } else {
                videoList.style.display = 'block';
                videoGrid.style.display = 'none';
            }
            
            // Update pagination controls
            updatePaginationControls();
            
            console.log(`✅ Displayed ${videos.length} videos in ${isCardView ? 'card' : 'list'} view`);
        }
        
        // Function to update pagination controls
        function updatePaginationControls() {
            const paginationContainer = document.getElementById('video-pagination');
            const prevBtn = document.getElementById('prev-page-btn');
            const nextBtn = document.getElementById('next-page-btn');
            const paginationPages = document.getElementById('pagination-pages');
            
            if (!currentPagination || !paginationContainer) return;
            
            // Show pagination if there are multiple pages
            if (currentPagination.total_pages > 1) {
                paginationContainer.style.display = 'flex';
                
                // Update prev/next buttons
                if (prevBtn) {
                    prevBtn.disabled = !currentPagination.has_prev;
                    prevBtn.onclick = () => {
                        if (currentPagination.has_prev) {
                            fetchVideos(currentSlug, currentSource, currentPagination.prev_page);
                        }
                    };
                }
                
                if (nextBtn) {
                    nextBtn.disabled = !currentPagination.has_next;
                    nextBtn.onclick = () => {
                        if (currentPagination.has_next) {
                            fetchVideos(currentSlug, currentSource, currentPagination.next_page);
                        }
                    };
                }
                
                // Update page numbers
                if (paginationPages) {
                    paginationPages.innerHTML = '';
                    const currentPage = currentPagination.current_page;
                    const totalPages = currentPagination.total_pages;
                    
                    // Show page numbers (max 5 pages visible)
                    const startPage = Math.max(1, currentPage - 2);
                    const endPage = Math.min(totalPages, startPage + 4);
                    
                    for (let i = startPage; i <= endPage; i++) {
                        const pageBtn = document.createElement('button');
                        pageBtn.className = `pagination-page-btn ${i === currentPage ? 'active' : ''}`;
                        pageBtn.textContent = i;
                        pageBtn.onclick = () => fetchVideos(currentSlug, currentSource, i);
                        paginationPages.appendChild(pageBtn);
                    }
                }
            } else {
                paginationContainer.style.display = 'none';
            }
        }
        
        // Domain Management Event Handlers
        console.log('🔧 Setting up domain management event handlers');
        
        // Handle domain detach/remove functionality
        document.addEventListener('click', (e) => {
            if (e.target.closest('.remove-domain')) {
                console.log('🗑️ Remove domain clicked');
                e.preventDefault();
                e.stopPropagation();
                
                const removeDomainBtn = e.target.closest('.remove-domain');
                const slug = removeDomainBtn.getAttribute('data-slug');
                const domain = removeDomainBtn.getAttribute('data-domain');
                
                console.log('🗑️ Attempting to detach domain:', { slug, domain });
                
                if (confirm(`Are you sure you want to detach the domain "${domain}" from "${slug}"?`)) {
                    fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=detach_domain&slug=${encodeURIComponent(slug)}&domain=${encodeURIComponent(domain)}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            console.log('✅ Domain detached successfully');
                            location.reload();
                        } else {
                            console.error('❌ Failed to detach domain:', data.error);
                            alert('Failed to detach domain: ' + data.error);
                        }
                    })
                    .catch(error => {
                        console.error('❌ Domain detach error:', error);
                        alert('Failed to detach domain: ' + error.message);
                    });
                }
            }
        });
        
        // Handle domain connect functionality  
        document.addEventListener('click', (e) => {
            if (e.target.closest('.connect-domain')) {
                console.log('🔗 Connect domain clicked');
                e.preventDefault();
                e.stopPropagation();
                
                const connectDomainBtn = e.target.closest('.connect-domain');
                const slug = connectDomainBtn.getAttribute('data-slug');
                
                console.log('🔗 Connect domain data:', { slug });
                
                if (!slug) {
                    alert('Error: Missing slug information.');
                    return;
                }
                
                // Prompt for domain input
                const domain = prompt('Enter the domain you want to connect:\n\n(e.g., mysite.com)\n\nNote: https://, http://, and www. will be automatically removed');
                
                if (!domain || domain.trim() === '') {
                    return; // User cancelled or entered empty domain
                }
                
                const cleanDomain = sanitizeDomainInput(domain.trim().toLowerCase());
                
                // Basic domain validation
                const domainRegex = /^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)*$/i;
                if (!domainRegex.test(cleanDomain)) {
                    alert('Please enter a valid domain name (e.g., mysite.com)');
                    return;
                }
                
                // Show loading state
                connectDomainBtn.style.opacity = '0.5';
                connectDomainBtn.style.pointerEvents = 'none';
                
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=connect_domain&slug=' + encodeURIComponent(slug) + '&domain=' + encodeURIComponent(cleanDomain)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Domain connected successfully!');
                        // Refresh the page to update the UI
                        window.location.reload();
                    } else {
                        alert('Error: ' + (data.error || 'Failed to connect domain'));
                        // Restore button state
                        connectDomainBtn.style.opacity = '1';
                        connectDomainBtn.style.pointerEvents = 'auto';
                    }
                })
                .catch(error => {
                    console.error('Error connecting domain:', error);
                    alert('Error: Failed to connect domain');
                    // Restore button state
                    connectDomainBtn.style.opacity = '1';
                    connectDomainBtn.style.pointerEvents = 'auto';
                });
            }
        });
        
        // Add event handlers for video count elements
        const videoCountElements = document.querySelectorAll('.video-count');
        console.log('🎥 Found', videoCountElements.length, 'video count elements');
        
        videoCountElements.forEach(el => {
            el.addEventListener('click', () => {
                const slug = el.dataset.slug;
                const source = el.dataset.source;
                console.log('🎥 Fetching videos for:', slug, 'source:', source);
                
                // Set current slug and source for Add Video functionality
                currentSlug = slug;
                currentSource = source;
                console.log('🎥 Set currentSlug:', currentSlug, 'currentSource:', currentSource);
                
                if (!videoModal) {
                    console.error('❌ Video modal not found');
                    return;
                }
                
                // Reset to page 1 when opening a new video modal
                currentPage = 1;
                fetchVideos(slug, source, currentPage);
                
                videoModal.style.display = 'block';
                console.log('🎬 Video modal opened');
                
                // Load categories when modal opens
                loadVideoCategories();
            });
        });
        
        // Category filter functionality
        const categoryFilter = document.getElementById('category-filter');
        if (categoryFilter) {
            categoryFilter.addEventListener('change', function() {
                console.log('🔍 Category filter changed to:', this.value);
                // Refresh videos with current filter
                currentPage = 1; // Reset to page 1 when filtering
                fetchVideos(currentSlug, currentSource, currentPage);
            });
        }
        
        // Search filter functionality
        const searchInput = document.getElementById('video-search');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                console.log('🔍 Search query changed to:', this.value);
                // Debounce search to avoid too many API calls
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    currentPage = 1; // Reset to page 1 when searching
                    fetchVideos(currentSlug, currentSource, currentPage);
                }, 300); // Wait 300ms after user stops typing
            });
            
            // Clear search on Escape key
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    this.value = '';
                    currentPage = 1;
                    fetchVideos(currentSlug, currentSource, currentPage);
                }
            });
        }
        
        // Video view switching functionality
        function initVideoViewSwitching() {
            const cardViewBtn = document.getElementById('card-view-btn');
            const listViewBtn = document.getElementById('list-view-btn');
            
            if (cardViewBtn && listViewBtn) {
                cardViewBtn.addEventListener('click', () => {
                    cardViewBtn.classList.add('active');
                    listViewBtn.classList.remove('active');
                    
                    // Re-display videos in card view
                    if (currentVideos.length > 0) {
                        displayVideos(currentVideos);
                    }
                    console.log('🎯 Switched to card view');
                });
                
                listViewBtn.addEventListener('click', () => {
                    listViewBtn.classList.add('active');
                    cardViewBtn.classList.remove('active');
                    
                    // Re-display videos in list view
                    if (currentVideos.length > 0) {
                        displayVideos(currentVideos);
                    }
                    console.log('🎯 Switched to list view');
                });
                
                console.log('✅ Video view switching initialized');
            }
        }
        
        // Initialize view switching when DOM is ready
        initVideoViewSwitching();
        
        // Domain input sanitization functionality
        function sanitizeDomainInput(value) {
            if (!value) return value;
            
            let cleanDomain = value.trim();
            
            // Remove protocol prefixes
            cleanDomain = cleanDomain.replace(/^https?:\/\//, '');
            
            // Remove www. prefix
            cleanDomain = cleanDomain.replace(/^www\./, '');
            
            // Remove trailing slash if present
            cleanDomain = cleanDomain.replace(/\/$/, '');
            
            return cleanDomain;
        }
        
        // Mark field as cleaned for tooltip display
        function markDomainAsCleaned(field) {
            field.dataset.domainCleaned = 'true';
            console.log('🧹 Domain marked as cleaned for tooltip display');
        }
        
        // Apply domain sanitization to custom domain field
        function initDomainSanitization() {
            const customDomainField = document.getElementById('custom_domain');
            
            if (customDomainField) {
                // Clear cleaned flag when user types manually
                customDomainField.addEventListener('input', function() {
                    // Only clear if user is actively typing (not from programmatic changes)
                    if (!this.dataset.programmaticChange) {
                        this.dataset.domainCleaned = 'false';
                        
                        // Also clear any existing validation state to force fresh check
                        const validationStatus = document.getElementById('custom_domain_validation');
                        if (validationStatus) {
                            validationStatus.classList.remove('show', 'valid', 'duplicate');
                            validationStatus.removeAttribute('data-tooltip');
                        }
                        this.classList.remove('valid', 'duplicate');
                    }
                });

                // Sanitize on blur (when user leaves the field)
                customDomainField.addEventListener('blur', function() {
                    const original = this.value;
                    const sanitized = sanitizeDomainInput(original);
                    if (sanitized !== original && sanitized !== '') {
                        this.dataset.programmaticChange = 'true';
                        this.value = sanitized;
                        console.log('🧹 Domain sanitized:', original, '→', sanitized);
                        
                        // Mark as cleaned for tooltip
                        markDomainAsCleaned(this);
                        
                        // Trigger input event to update validation
                        this.dispatchEvent(new Event('input', { bubbles: true }));
                        this.dataset.programmaticChange = 'false';
                    } else {
                        // Clear cleaned flag if no change was made
                        this.dataset.domainCleaned = 'false';
                    }
                });
                
                // Also sanitize on paste
                customDomainField.addEventListener('paste', function() {
                    const field = this;
                    // Use setTimeout to allow paste to complete first
                    setTimeout(() => {
                        const original = field.value;
                        const sanitized = sanitizeDomainInput(original);
                        if (sanitized !== original && sanitized !== '') {
                            field.dataset.programmaticChange = 'true';
                            field.value = sanitized;
                            console.log('🧹 Domain sanitized on paste:', original, '→', sanitized);
                            markDomainAsCleaned(field);
                            field.dispatchEvent(new Event('input', { bubbles: true }));
                            field.dataset.programmaticChange = 'false';
                        } else {
                            field.dataset.domainCleaned = 'false';
                        }
                    }, 10);
                });
                
                console.log('✅ Domain sanitization initialized');
            }
        }
        
        // Initialize domain sanitization
        initDomainSanitization();
        
        // Add form submission sanitization for both add and edit forms
        function initFormSanitization() {
            const addForm = document.getElementById('add-ion-form');
            const editForm = document.querySelector('#add-ion-modal form'); // Edit uses same modal
            
            function sanitizeFormDomains(form) {
                const customDomainField = form.querySelector('#custom_domain');
                if (customDomainField) {
                    const sanitized = sanitizeDomainInput(customDomainField.value);
                    if (sanitized !== customDomainField.value) {
                        customDomainField.value = sanitized;
                        console.log('🧹 Domain sanitized on form submit:', sanitized);
                    }
                }
            }
            
            // Add event listeners for form submission
            if (addForm) {
                addForm.addEventListener('submit', function(e) {
                    sanitizeFormDomains(this);
                });
            }
            
            // For edit form (same modal, different mode)
            const modalForm = document.getElementById('add-ion-modal');
            if (modalForm) {
                modalForm.addEventListener('submit', function(e) {
                    const form = e.target.closest('form') || this.querySelector('form');
                    if (form) {
                        sanitizeFormDomains(form);
                    }
                });
            }
            
            console.log('✅ Form domain sanitization initialized');
        }
        
        // Initialize form sanitization
        initFormSanitization();
        
        // Avatar management functionality
        function initAvatarManagement() {
            const syncGoogleBtn = document.getElementById('sync-google-avatar');
            const updateManualBtn = document.getElementById('update-avatar-manual');
            
            if (syncGoogleBtn) {
                syncGoogleBtn.addEventListener('click', async () => {
                    console.log('🔄 Syncing Google avatar...');
                    
                    // Add loading state
                    syncGoogleBtn.classList.add('loading');
                    syncGoogleBtn.disabled = true;
                    
                    try {
                        // For now, we'll simulate the Google sync with a manual URL input
                        // In a real implementation, you'd get the access token from your OAuth flow
                        const confirmSync = confirm(
                            'Sync from Google Profile?\n\n' +
                            'This will only work if you don\'t already have a profile picture set.\n\n' +
                            'Continue?'
                        );
                        
                        if (confirmSync) {
                            // For demonstration, prompt for Google profile picture URL
                            const googleProfileUrl = prompt(
                                'Paste your Google profile picture URL:\n\n' +
                                '(Get this by right-clicking your Google profile picture and copying image address)'
                            );
                            
                            if (googleProfileUrl && googleProfileUrl.trim()) {
                                await syncFromGoogle(googleProfileUrl.trim());
                            }
                        }
                    } finally {
                        // Remove loading state
                        syncGoogleBtn.classList.remove('loading');
                        syncGoogleBtn.disabled = false;
                    }
                });
            }
            
            if (updateManualBtn) {
                updateManualBtn.addEventListener('click', async () => {
                    console.log('🖼️ Manual avatar update...');
                    
                    const currentAvatarUrl = document.getElementById('userAvatar')?.src || '';
                    const isGeneratedAvatar = currentAvatarUrl.includes('ui-avatars.com');
                    
                    const newUrl = prompt(
                        'Enter a new avatar URL:\n\n' +
                        '(Leave empty to remove current avatar and use generated initials)',
                        isGeneratedAvatar ? '' : currentAvatarUrl
                    );
                    
                    if (newUrl !== null) { // User didn't cancel
                        await updateAvatarUrl(newUrl.trim());
                    }
                });
            }
            
            console.log('✅ Avatar management initialized');
        }
        
        // Function to sync from Google (simplified version)
        async function syncFromGoogle(googleImageUrl) {
            try {
                const response = await fetch('?', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=sync_google_avatar&access_token=demo&google_image_url=${encodeURIComponent(googleImageUrl)}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    console.log('✅ Google avatar synced successfully');
                    updateAvatarDisplay(data.avatar_url);
                    alert('Google profile picture synced successfully!');
                    closeUserDropdown();
                } else {
                    console.log('ℹ️ Google sync blocked:', data.message);
                    if (data.error === 'Avatar already set') {
                        const forceUpdate = confirm(
                            data.message + '\n\n' +
                            'Would you like to update your existing picture anyway?'
                        );
                        if (forceUpdate) {
                            await updateAvatarUrl(googleImageUrl);
                        }
                    } else {
                        alert('Could not sync from Google: ' + (data.message || data.error));
                    }
                }
            } catch (error) {
                console.error('❌ Google sync error:', error);
                alert('Failed to sync from Google: ' + error.message);
            }
        }
        
        // Function to update avatar URL
        async function updateAvatarUrl(avatarUrl) {
            try {
                const response = await fetch('?', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=update_avatar_url&avatar_url=${encodeURIComponent(avatarUrl)}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    console.log('✅ Avatar updated successfully');
                    updateAvatarDisplay(avatarUrl);
                    alert('Avatar updated successfully!');
                    closeUserDropdown();
                } else {
                    console.error('❌ Avatar update failed:', data.error);
                    alert('Failed to update avatar: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('❌ Avatar update error:', error);
                alert('Failed to update avatar: ' + error.message);
            }
        }
        
        // Function to update avatar display
        function updateAvatarDisplay(avatarUrl) {
            const userAvatar = document.getElementById('userAvatar');
            if (userAvatar) {
                if (avatarUrl) {
                    userAvatar.src = avatarUrl;
                } else {
                    // Fallback to generated avatar
                    const userEmail = '<?= addslashes($_SESSION['user_email'] ?? '') ?>';
                    const initials = userEmail.substring(0, 2) || '??';
                    userAvatar.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(initials)}&background=6366f1&color=fff&rounded=false&size=32`;
                }
            }
        }
        
        // Function to close user dropdown
        function closeUserDropdown() {
            const userDropdown = document.getElementById('userDropdown');
            if (userDropdown) {
                userDropdown.style.display = 'none';
            }
        }
        
        // Initialize avatar management
        initAvatarManagement();
        
        // Dark mode detection and modal theme handling
        function applyModalTheme() {
            const isDarkMode = document.documentElement.classList.contains('dark') || 
                             document.body.classList.contains('dark-mode') ||
                             window.matchMedia('(prefers-color-scheme: dark)').matches;
            
            const videoModal = document.getElementById('video-modal');
            const addVideoModal = document.getElementById('add-video-modal');
            
            if (isDarkMode) {
                if (videoModal) videoModal.classList.add('dark-mode');
                if (addVideoModal) addVideoModal.classList.add('dark-mode');
                document.body.classList.add('dark-mode');
            } else {
                if (videoModal) videoModal.classList.remove('dark-mode');
                if (addVideoModal) addVideoModal.classList.remove('dark-mode');
                document.body.classList.remove('dark-mode');
            }
            
            console.log('🎨 Modal theme applied:', isDarkMode ? 'dark' : 'light');
        }
        
        // Apply theme on load and when system preference changes
        applyModalTheme();
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', applyModalTheme);
        
        // Enhanced Add Video modal handling
        function enhanceAddVideoModal() {
            const addVideoBtn = document.getElementById('add-video-btn');
            const addVideoModal = document.getElementById('add-video-modal');
            const closeAddVideoModal = document.getElementById('close-add-video-modal');
            
            if (addVideoBtn && addVideoModal) {
                addVideoBtn.addEventListener('click', () => {
                    console.log('🎬 Opening Add Video modal for:', currentSlug, currentSource);
                    
                    // Set the slug and source context for the new video
                    const slugInput = document.getElementById('video-slug');
                    const videotypeInput = document.getElementById('video-videotype');
                    const sourceInput = document.getElementById('video-source');
                    
                    if (slugInput) slugInput.value = currentSlug;
                    if (videotypeInput) videotypeInput.value = currentSource; // Channel type (e.g., "Muvi")
                    if (sourceInput) sourceInput.value = 'Youtube'; // Default source, will be auto-detected from URL
                    
                    // Reset tags when modal opens
                    const tagsList = document.getElementById('tags-list');
                    const hiddenTagsField = document.getElementById('video-tags');
                    const tagInput = document.getElementById('tag-input');
                    if (tagsList && hiddenTagsField && tagInput) {
                        tagsList.innerHTML = '';
                        hiddenTagsField.value = '';
                        tagInput.value = '';
                        console.log('🏷️ Tags reset for new video');
                    }
                    
                    // Apply current theme
                    applyModalTheme();
                    
                    // Show the modal with proper animation
                    addVideoModal.style.display = 'flex';
                    // Force reflow before adding show class
                    addVideoModal.offsetHeight;
                    addVideoModal.classList.add('show');
                    
                    // Prevent body scroll
                    document.body.style.overflow = 'hidden';
                    
                    // Load categories when Add Video modal opens
                    loadVideoCategories();
                    
                    console.log('✅ Add Video modal opened with animations');
                });
            }
            
            if (closeAddVideoModal && addVideoModal) {
                closeAddVideoModal.addEventListener('click', () => {
                    closeAddVideoModalWithAnimation();
                });
                
                // Close modal when clicking outside - improved version
                addVideoModal.addEventListener('click', (e) => {
                    // Only close if clicking directly on the modal overlay
                    if (e.target === addVideoModal) {
                        closeAddVideoModalWithAnimation();
                    }
                });
                
                // Close modal with Escape key
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape' && addVideoModal.style.display === 'flex') {
                        closeAddVideoModalWithAnimation();
                    }
                });
            }
            
            function closeAddVideoModalWithAnimation() {
                addVideoModal.classList.remove('show');
                setTimeout(() => {
                    addVideoModal.style.display = 'none';
                    document.body.style.overflow = '';
                }, 300);
                console.log('✅ Add Video modal closed with animations');
            }
            
            console.log('✅ Enhanced Add Video modal initialized');
        }
        
        // Initialize enhanced Add Video modal
        enhanceAddVideoModal();
        
        // Tags functionality for Add Video modal
        function initializeTagsInput() {
            const tagInput = document.getElementById('tag-input');
            const tagsList = document.getElementById('tags-list');
            const hiddenTagsField = document.getElementById('video-tags');
            
            if (!tagInput || !tagsList || !hiddenTagsField) {
                console.log('Tags elements not found, skipping initialization');
                return;
            }
            
            let tags = [];
            
            function updateTagsDisplay() {
                // Clear existing tags display
                tagsList.innerHTML = '';
                
                // Create visual tags
                tags.forEach((tag, index) => {
                    const tagElement = document.createElement('div');
                    tagElement.className = 'tag-item';
                    tagElement.innerHTML = `
                        <span>${tag}</span>
                        <button type="button" class="tag-remove" data-index="${index}">&times;</button>
                    `;
                    tagsList.appendChild(tagElement);
                });
                
                // Update hidden field with comma-separated values
                hiddenTagsField.value = tags.join(', ');
                console.log('Tags updated:', tags);
            }
            
            function addTag(tagText) {
                const tag = tagText.trim();
                if (tag && !tags.includes(tag)) {
                    tags.push(tag);
                    updateTagsDisplay();
                }
            }
            
            function removeTag(index) {
                tags.splice(index, 1);
                updateTagsDisplay();
            }
            
            // Handle tag input (Enter key and comma)
            tagInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ',') {
                    e.preventDefault();
                    addTag(this.value);
                    this.value = '';
                }
            });
            
            // Handle tag input on blur (when field loses focus)
            tagInput.addEventListener('blur', function() {
                if (this.value.trim()) {
                    addTag(this.value);
                    this.value = '';
                }
            });
            
            // Handle tag removal clicks
            tagsList.addEventListener('click', function(e) {
                if (e.target.classList.contains('tag-remove')) {
                    const index = parseInt(e.target.dataset.index);
                    removeTag(index);
                }
            });
            
            // Allow pasting comma-separated tags
            tagInput.addEventListener('paste', function(e) {
                setTimeout(() => {
                    const pastedText = this.value;
                    const pastedTags = pastedText.split(',').map(tag => tag.trim()).filter(tag => tag);
                    pastedTags.forEach(tag => addTag(tag));
                    this.value = '';
                }, 10);
            });
            
            console.log('✅ Tags input functionality initialized');
        }
        
        // Initialize tags functionality
        initializeTagsInput();
        
        // Make deleteVideo function globally accessible
        window.deleteVideo = deleteVideo;
        
        // SQL Debug Panel functionality
        function initSQLDebugPanel() {
            const debugTrigger = document.getElementById('sql-debug-trigger');
            const debugPanel = document.getElementById('sql-debug-panel');
            const closeDebugBtn = document.getElementById('close-debug-panel');
            
            if (!debugTrigger || !debugPanel) {
                return; // Debug elements not found
            }
            
            // Show debug panel
            debugTrigger.addEventListener('click', () => {
                console.log('🔍 SQL Debug panel opened', window.sqlDebugInfo);
                populateDebugPanel();
                debugPanel.style.display = 'block';
                document.body.style.overflow = 'hidden'; // Prevent background scroll
            });
            
            // Close debug panel
            if (closeDebugBtn) {
                closeDebugBtn.addEventListener('click', closeDebugPanel);
            }
            
            // Close panel when clicking outside
            debugPanel.addEventListener('click', (e) => {
                if (e.target === debugPanel) {
                    closeDebugPanel();
                }
            });
            
            // Close panel with Escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && debugPanel.style.display === 'block') {
                    closeDebugPanel();
                }
            });
            
            function closeDebugPanel() {
                debugPanel.style.display = 'none';
                document.body.style.overflow = ''; // Restore scroll
            }
            
            function populateDebugPanel() {
                const debugInfo = window.sqlDebugInfo || {};
                const totalPages = window.totalPages || 0;
                
                // Populate basic info
                const searchTerm = document.getElementById('debug-search-term');
                const totalFound = document.getElementById('debug-total-found');
                const resultsReturned = document.getElementById('debug-results-returned');
                const debugPage = document.getElementById('debug-page');
                const debugTotalPages = document.getElementById('debug-total-pages');
                const countQuery = document.getElementById('debug-count-query');
                const mainQuery = document.getElementById('debug-main-query');
                const whereClause = document.getElementById('debug-where-clause');
                
                if (searchTerm) searchTerm.textContent = debugInfo.search_term || 'None';
                if (totalFound) totalFound.textContent = debugInfo.total_found || '0';
                if (resultsReturned) resultsReturned.textContent = debugInfo.results_returned || '0';
                if (debugPage) debugPage.textContent = debugInfo.page || '1';
                if (debugTotalPages) debugTotalPages.textContent = totalPages;
                
                if (countQuery) countQuery.textContent = debugInfo.count_query || 'Not available';
                if (mainQuery) mainQuery.textContent = debugInfo.main_query || 'Not available';
                if (whereClause) whereClause.textContent = debugInfo.where_clause || 'Not available';
                
                // Add problem analysis
                if (debugInfo.total_found > 0 && debugInfo.results_returned === 0) {
                    const problemDiv = document.createElement('div');
                    problemDiv.className = 'debug-section';
                    problemDiv.innerHTML = `
                        <h4 style="color: #dc2626;">⚠️ Potential Issue Detected</h4>
                        <p style="color: #dc2626; font-weight: bold;">
                            Count query found ${debugInfo.total_found} results, but main query returned ${debugInfo.results_returned} results.
                            This suggests a discrepancy between the queries.
                        </p>
                        <p><strong>Possible causes:</strong></p>
                        <ul>
                            <li>Different WHERE clauses between count and main query</li>
                            <li>LIMIT/OFFSET issues</li>
                            <li>Database connection problems</li>
                            <li>Data type mismatches</li>
                        </ul>
                    `;
                    document.querySelector('.debug-content').appendChild(problemDiv);
                }
            }
            
            console.log('🔍 SQL Debug panel initialized');
        }
        
        // Initialize debug panel
        initSQLDebugPanel();
        
        // Video source detection function
        function detectVideoSource(url) {
            if (!url) return 'Youtube'; // Default
            
            url = url.toLowerCase();
            
            if (url.includes('youtube.com') || url.includes('youtu.be')) {
                return 'Youtube';
            } else if (url.includes('vimeo.com')) {
                return 'Vimeo';
            } else if (url.includes('muvi.com')) {
                return 'Muvi';
            } else if (url.includes('rumble.com')) {
                return 'Rumble';
            }
            
            return 'Youtube'; // Default fallback
        }
        
        // Add Video Form Submission Handler
        function initVideoFormSubmission() {
            const videoForm = document.getElementById('video-form');
            const submitBtn = document.getElementById('submit-video-btn');
            
            if (!videoForm || !submitBtn) {
                return; // Form elements not found
            }
            
            videoForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                console.log('🎬 Video form submitted');
                
                                 // Auto-detect and set video source before submission
                const urlField = document.getElementById('video-url');
                const sourceField = document.getElementById('video-source');
                if (urlField && sourceField) {
                    const detectedSource = detectVideoSource(urlField.value);
                    sourceField.value = detectedSource;
                    console.log('🎬 Auto-detected source:', detectedSource, 'for URL:', urlField.value);
                }
                
                // Get form data
                const formData = new FormData(videoForm);
                
                formData.append('action', 'add_video');
                
                // Get all form values for validation
                const title = formData.get('title');
                const url = formData.get('url');
                const slug = formData.get('slug');
                const videotype = formData.get('videotype');
                
                console.log('🎬 Form data:', {
                    title: title,
                    url: url,
                    slug: slug,
                    videotype: videotype
                });
                
                // Validate required fields
                if (!title) {
                    alert('Please enter a video title.');
                    return;
                }
                if (!url) {
                    alert('Please enter a video URL.');
                    return;
                }
                if (!slug) {
                    alert('Error: Missing slug context. Please close and reopen the video modal.');
                    return;
                }
                if (!videotype) {
                    alert('Error: Missing videotype context. Please close and reopen the video modal.');
                    return;
                }
                
                // Show loading state
                submitBtn.disabled = true;
                const submitIcon = document.getElementById('submit-video-icon');
                const submitText = document.getElementById('submit-video-text');
                const originalText = submitText.textContent;
                
                submitText.textContent = 'Adding Video...';
                if (submitIcon) submitIcon.textContent = '⏳';
                
                try {
                    console.log('🎬 Sending video to server...');
                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    });
                    
                    console.log('🎬 Response status:', response.status);
                    console.log('🎬 Response headers:', response.headers.get('content-type'));
                    
                    // Check if response is actually JSON
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        const textResponse = await response.text();
                        console.error('🎬 Non-JSON response received:', textResponse);
                        throw new Error('Server returned non-JSON response. Check browser console for details.');
                    }
                    
                    const result = await response.json();
                    console.log('🎬 Server response:', result);
                    
                    if (result.success) {
                        alert('Video added successfully!');
                        // Close the modal
                        const addVideoModal = document.getElementById('add-video-modal');
                        if (addVideoModal) {
                            addVideoModal.classList.remove('show');
                            setTimeout(() => {
                                addVideoModal.style.display = 'none';
                                document.body.style.overflow = '';
                            }, 300);
                        }
                        
                        // Reset form
                        videoForm.reset();
                        
                        // Refresh the video list if we're in a video modal
                        const videoModal = document.getElementById('video-modal');
                        if (videoModal && videoModal.style.display === 'block') {
                            // Refresh the current video list
                            if (window.currentSlug && window.currentSource) {
                                window.fetchVideos(window.currentSlug, window.currentSource, 1);
                            }
                        }
                        
                    } else {
                        console.error('🎬 Video save failed:', result.error);
                        alert('Failed to add video: ' + (result.error || 'Unknown error'));
                    }
                    
                } catch (error) {
                    console.error('🎬 Video submission error:', error);
                    alert('Failed to add video: ' + error.message);
                } finally {
                    // Restore button state
                    submitBtn.disabled = false;
                    submitText.textContent = originalText;
                    if (submitIcon) submitIcon.textContent = '📹';
                }
            });
            
            console.log('🎬 Video form submission handler initialized');
        }
        
        // Initialize video form submission
        initVideoFormSubmission();
        
    }); // End DOMContentLoaded

    // Global edit button click handler
    function handleEditClick(e) {
        e.preventDefault();
        const editBtn = e.currentTarget;
        const cityId = editBtn.dataset.cityId;
        console.log('📝 Edit button clicked for city ID:', cityId);

        const editModal = document.getElementById('add-ion-modal');
        if (!editModal) {
            console.error('❌ Add ION modal not found');
            return;
        }

        // Fetch city data for editing
        console.log('🔄 Fetching city data for ID:', cityId);
        fetch('add-ion-handler.php?action=get_city_data&city_id=' + cityId)
            .then(response => response.json())
            .then(data => {
                console.log('📋 City data received:', data);
                if (data.success) {
                    // Set modal to edit mode
                    editModal.dataset.mode = 'edit';
                    editModal.dataset.cityId = cityId;
                    console.log('✅ Modal set to edit mode');

                    // Update modal title and button FIRST
                    const modalTitle = document.getElementById('modal-title');

                    if (modalTitle) modalTitle.textContent = 'Edit ION Channel';

                    // Initialize wizard for edit mode
                    if (typeof initializeWizard === 'function') {
                        initializeWizard();
                    }

                    // Populate form with existing data
                    const cityData = data.city;
                    console.log('📝 Populating form with city data:', cityData);

                    // Handle case where city_name might be empty but channel_name has the info
                    let actualCityName = cityData.city_name;
                    if (!actualCityName && cityData.channel_name) {
                        // Try to extract city name from channel_name (e.g., "ION Tampa" -> "Tampa")
                        const channelMatch = cityData.channel_name.match(/ION\s+(.+?)(?:\s+\(|$)/);
                        if (channelMatch) {
                            actualCityName = channelMatch[1].trim();
                            console.log('📍 Extracted city name from channel_name:', actualCityName);
                        }
                    }

                    // Map database fields to form fields
                    const fieldMapping = {
                        'city_name': actualCityName || cityData.city_name,
                        'channel_name': cityData.channel_name,
                        'custom_domain': cityData.custom_domain,
                        'status': cityData.status,
                        'title': cityData.title,
                        'description': cityData.description,
                        'seo_title': cityData.seo_title,
                        'seo_meta_description': cityData.seo_description, // Note: database field is seo_description
                        'image_url': cityData.image_path || '', // Map database image_path to form image_url field
                        'latitude': cityData.latitude,
                        'longitude': cityData.longitude,
                        'population': cityData.population,
                        'country': cityData.country_code,
                        'state': cityData.state_code
                    };

                    console.log('🖼️ Image URL debug:', {
                        'database_image_path': cityData.image_path,
                        'mapped_image_url': cityData.image_path || '',
                        'field_exists': !!document.getElementById('image_url')
                    });

                    // Populate form fields
                    Object.keys(fieldMapping).forEach(fieldId => {
                        const field = document.getElementById(fieldId);
                        const value = fieldMapping[fieldId];
                        if (field && value !== null && value !== undefined) {
                            field.value = value;
                            console.log('✏️ Set ' + fieldId + ' = ' + value);
                            // Clear user-edited flags so auto-fill can work if user changes city
                            field.removeAttribute('data-user-edited');
                        } else if (field) {
                            field.value = '';
                            console.log('✏️ Cleared ' + fieldId);
                        }
                    });

                    // Clear validation states for edit mode to prevent false duplicate errors
                    console.log('🧹 Clearing validation states for edit mode');
                    setTimeout(() => {
                        const validationFields = ['city_name', 'channel_name', 'custom_domain'];
                        validationFields.forEach(fieldId => {
                            const validationStatus = document.getElementById(fieldId + '_validation');
                            const inputField = document.getElementById(fieldId);
                            if (validationStatus && inputField) {
                                validationStatus.classList.remove('show', 'duplicate', 'valid');
                                validationStatus.removeAttribute('data-tooltip');
                                inputField.classList.remove('duplicate', 'valid');
                                console.log(`✅ Cleared validation for ${fieldId} in edit mode`);
                            }
                        });
                    }, 100); // Small delay to ensure field population is complete

                    // Trigger URL preview if image URL was populated (with delay to ensure modal is fully loaded)
                    const imageUrlField = document.getElementById('image_url');
                    if (imageUrlField && imageUrlField.value.trim()) {
                        console.log('🖼️ Triggering URL preview for:', imageUrlField.value);

                        // Switch to URL tab and set image source to URL
                        const imageSourceField = document.getElementById('image_source');
                        if (imageSourceField) {
                            imageSourceField.value = 'url';
                        }

                        // Activate URL tab
                        const urlTab = document.querySelector('[data-tab="url"]');
                        if (urlTab) {
                            urlTab.click();
                        }

                        // Add delay to ensure modal is fully rendered and event listeners are attached
                        setTimeout(() => {
                            imageUrlField.dispatchEvent(new Event('input'));
                        }, 150);
                    }

                    // Load countries and set the country/state
                    if (typeof loadCountriesIntoDropdown === 'function') {
                        loadCountriesIntoDropdown();
                    }

                    // Set country and state after a short delay to ensure dropdowns are loaded
                    setTimeout(() => {
                        if (cityData.country_code) {
                            const countrySelect = document.getElementById('country');
                            if (countrySelect) {
                                countrySelect.value = cityData.country_code;
                                console.log('✏️ Set country to:', cityData.country_code);

                                // Update states for the selected country
                                const modalStateSelect = document.getElementById('state');
                                if (modalStateSelect && typeof updateStates === 'function') {
                                    updateStates(cityData.country_code, modalStateSelect, cityData.state_code || '');
                                    // Set state after states are loaded
                                    setTimeout(() => {
                                        if (cityData.state_code) {
                                            modalStateSelect.value = cityData.state_code;
                                            console.log('✏️ Set state to:', cityData.state_code);
                                        }
                                    }, 100);
                                }
                            }
                        }

                        // Update character counts AFTER all fields are populated
                        if (typeof updateAllCharacterCounts === 'function') {
                            updateAllCharacterCounts();
                            console.log('✏️ Updated all character counts');
                        }
                    }, 200);

                    // Show modal
                    console.log('🚀 Showing edit modal');
                    // Show modal using working ions pattern
                    editModal.style.display = 'flex';
                    setTimeout(() => editModal.classList.add('show'), 10);
                    console.log('✅ Edit modal should now be visible');

                } else {
                    console.error('❌ Failed to load city data:', data.error);
                    alert('Failed to load city data: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Edit city error:', error);
                alert('Failed to load city data: ' + error.message);
            });
    }

    // Global function to initialize event listeners for both initial and dynamic content
    function initializeDynamicEventListeners() {
        console.log('🔄 Re-initializing event listeners for dynamic content');
        
        // Use working ions pattern - simple direct event listeners
        const editModal = document.getElementById('add-ion-modal');
        const editButtons = document.querySelectorAll('.edit-card-btn');
        console.log('📝 Found', editButtons.length, 'edit buttons');
        
        editButtons.forEach(editBtn => {
            editBtn.addEventListener('click', (e) => {
                e.preventDefault();
                const cityId = editBtn.dataset.cityId;
                console.log('📝 Edit button clicked for city ID:', cityId);
                
                if (!editModal) {
                    console.error('❌ Add ION modal not found');
                    return;
                }

                // Fetch city data for editing
                console.log('🔄 Fetching city data for ID:', cityId);
                fetch('add-ion-handler.php?action=get_city_data&city_id=' + cityId)
                    .then(response => response.json())
                    .then(data => {
                        console.log('📋 City data received:', data);
                        if (data.success) {
                            // Set modal to edit mode
                            editModal.dataset.mode = 'edit';
                            editModal.dataset.cityId = cityId;
                            console.log('✅ Modal set to edit mode');

                            // Update modal title and button FIRST
                            const modalTitle = document.getElementById('modal-title');

                            if (modalTitle) modalTitle.textContent = 'Edit ION Channel';

                            // Initialize wizard for edit mode
                            if (typeof initializeWizard === 'function') {
                                initializeWizard();
                            }

                            // Populate form with existing data
                            const cityData = data.city;
                            console.log('📝 Populating form with city data:', cityData);

                            // Handle case where city_name might be empty but channel_name has the info
                            let actualCityName = cityData.city_name;
                            if (!actualCityName && cityData.channel_name) {
                                // Try to extract city name from channel_name (e.g., "ION Tampa" -> "Tampa")
                                const channelMatch = cityData.channel_name.match(/ION\s+(.+?)(?:\s+\(|$)/);
                                if (channelMatch) {
                                    actualCityName = channelMatch[1].trim();
                                    console.log('📍 Extracted city name from channel_name:', actualCityName);
                                }
                            }

                            // Map database fields to form fields
                            const fieldMapping = {
                                'city_name': actualCityName || cityData.city_name,
                                'channel_name': cityData.channel_name,
                                'custom_domain': cityData.custom_domain,
                                'status': cityData.status,
                                'title': cityData.title,
                                'description': cityData.description,
                                'seo_title': cityData.seo_title,
                                'seo_meta_description': cityData.seo_description, // Note: database field is seo_description
                                'image_url': cityData.image_path || '', // Map database image_path to form image_url field
                                'latitude': cityData.latitude,
                                'longitude': cityData.longitude,
                                'population': cityData.population,
                                'country': cityData.country_code,
                                'state': cityData.state_code
                            };

                            console.log('🖼️ Image URL debug:', {
                                'database_image_path': cityData.image_path,
                                'mapped_image_url': cityData.image_path || '',
                                'field_exists': !!document.getElementById('image_url')
                            });

                            // Populate form fields
                            Object.keys(fieldMapping).forEach(fieldId => {
                                const field = document.getElementById(fieldId);
                                const value = fieldMapping[fieldId];
                                if (field && value !== null && value !== undefined) {
                                    field.value = value;
                                    console.log('✏️ Set ' + fieldId + ' = ' + value);
                                    // Clear user-edited flags so auto-fill can work if user changes city
                                    field.removeAttribute('data-user-edited');
                                } else if (field) {
                                    field.value = '';
                                    console.log('✏️ Cleared ' + fieldId);
                                }
                            });

                            // Clear validation states for edit mode to prevent false duplicate errors
                            console.log('🧹 Clearing validation states for edit mode');
                            setTimeout(() => {
                                const validationFields = ['city_name', 'channel_name', 'custom_domain'];
                                validationFields.forEach(fieldId => {
                                    const validationStatus = document.getElementById(fieldId + '_validation');
                                    const inputField = document.getElementById(fieldId);
                                    if (validationStatus && inputField) {
                                        validationStatus.classList.remove('show', 'duplicate', 'valid');
                                        validationStatus.removeAttribute('data-tooltip');
                                        inputField.classList.remove('duplicate', 'valid');
                                        console.log(`✅ Cleared validation for ${fieldId} in edit mode`);
                                    }
                                });
                            }, 100); // Small delay to ensure field population is complete

                            // Trigger URL preview if image URL was populated (with delay to ensure modal is fully loaded)
                            const imageUrlField = document.getElementById('image_url');
                            if (imageUrlField && imageUrlField.value.trim()) {
                                console.log('🖼️ Triggering URL preview for:', imageUrlField.value);

                                // Switch to URL tab and set image source to URL
                                const imageSourceField = document.getElementById('image_source');
                                if (imageSourceField) {
                                    imageSourceField.value = 'url';
                                }

                                // Activate URL tab
                                const urlTab = document.querySelector('[data-tab="url"]');
                                if (urlTab) {
                                    urlTab.click();
                                }

                                // Add delay to ensure modal is fully rendered and event listeners are attached
                                setTimeout(() => {
                                    imageUrlField.dispatchEvent(new Event('input'));
                                }, 150);
                            }

                            // Load countries and set the country/state
                            if (typeof loadCountriesIntoDropdown === 'function') {
                                loadCountriesIntoDropdown();
                            }

                            // Set country and state after a short delay to ensure dropdowns are loaded
                            setTimeout(() => {
                                if (cityData.country_code) {
                                    const countrySelect = document.getElementById('country');
                                    if (countrySelect) {
                                        countrySelect.value = cityData.country_code;
                                        console.log('✏️ Set country to:', cityData.country_code);

                                        // Update states for the selected country
                                        const modalStateSelect = document.getElementById('state');
                                        if (modalStateSelect && typeof updateStates === 'function') {
                                            updateStates(cityData.country_code, modalStateSelect, cityData.state_code || '');
                                            // Set state after states are loaded
                                            setTimeout(() => {
                                                if (cityData.state_code) {
                                                    modalStateSelect.value = cityData.state_code;
                                                    console.log('✏️ Set state to:', cityData.state_code);
                                                }
                                            }, 100);
                                        }
                                    }
                                }

                                // Update character counts AFTER all fields are populated
                                if (typeof updateAllCharacterCounts === 'function') {
                                    updateAllCharacterCounts();
                                    console.log('✏️ Updated all character counts');
                                }
                            }, 200);

                            // Show modal
                            console.log('🚀 Showing edit modal');
                            // Show modal using working ions pattern
                            editModal.style.display = 'flex';
                            setTimeout(() => editModal.classList.add('show'), 10);
                            console.log('✅ Edit modal should now be visible');

                        } else {
                            console.error('❌ Failed to load city data:', data.error);
                            alert('Failed to load city data: ' + (data.error || 'Unknown error'));
                        }
                    })
                    .catch(error => {
                        console.error('Edit city error:', error);
                        alert('Failed to load city data: ' + error.message);
                    });
            });
        });
        
        // Simple debug function - ions pattern
        console.log('✅ Edit button event listeners attached to', editButtons.length, 'buttons');
        
        // Make debug function globally available
        window.debugEditButtons = function() {
            const editButtons = document.querySelectorAll('.edit-card-btn');
            console.log('🔍 Edit buttons found:', editButtons.length);
            const modal = document.getElementById('add-ion-modal');
            console.log('🔍 Add ION modal found:', !!modal);
            
            // Test if buttons have event listeners
            editButtons.forEach((btn, i) => {
                console.log(`🔍 Button ${i+1} city ID:`, btn.dataset.cityId);
            });
            
            return editButtons.length;
        };
        
        // Test edit button functionality
        window.testDirectoryEditButtons = function() {
            console.log('🧪 Testing directory edit buttons...');
            const editButtons = document.querySelectorAll('.edit-card-btn');
            console.log('🧪 Found', editButtons.length, 'edit buttons');
            
            if (editButtons.length > 0) {
                const firstBtn = editButtons[0];
                const cityId = firstBtn.dataset.cityId;
                console.log('🧪 Testing first button with city ID:', cityId);
                
                // Simulate click
                firstBtn.click();
                console.log('🧪 Click event triggered');
            } else {
                console.error('🧪 No edit buttons found on page');
            }
        };
        
        // Test modal visibility
        window.testModalVisibility = function() {
            const modal = document.getElementById('add-ion-modal');
            if (modal) {
                console.log('🧪 Modal found, current display:', modal.style.display);
                console.log('🧪 Modal classes:', modal.className);
                console.log('🧪 Modal computed style:', window.getComputedStyle(modal).display);
                return {
                    found: true,
                    display: modal.style.display,
                    classes: modal.className,
                    computed: window.getComputedStyle(modal).display
                };
            } else {
                console.error('🧪 Modal not found');
                return { found: false };
            }
        };
        
        // Manual test function for edit buttons
        window.testEditButton = function(cityId) {
            console.log('🧪 Testing edit button for city ID:', cityId);
            const btn = document.querySelector(`[data-city-id="${cityId}"]`);
            if (btn) {
                console.log('✅ Button found, simulating click...');
                btn.click();
            } else {
                console.error('❌ Button not found for city ID:', cityId);
            }
        };
        
        // Test if JavaScript errors are preventing edit buttons
        window.testEditButtonsWork = function() {
            console.log('🧪 Testing if edit buttons work after Leaflet errors...');
            const editButtons = document.querySelectorAll('.edit-card-btn');
            console.log('🔍 Found', editButtons.length, 'edit buttons');
            
            if (editButtons.length > 0) {
                const firstBtn = editButtons[0];
                const cityId = firstBtn.dataset.cityId;
                console.log('🧪 Testing first button with city ID:', cityId);
                
                try {
                    firstBtn.click();
                    console.log('✅ Edit button click successful');
                } catch (error) {
                    console.error('❌ Edit button click failed:', error);
                }
            } else {
                console.error('❌ No edit buttons found');
            }
        };
        
        // Re-initialize video count click handlers
        const videoCounts = document.querySelectorAll('.video-count');
        console.log('🎥 Found', videoCounts.length, 'video count elements in dynamic content');
        
        videoCounts.forEach(videoCount => {
            videoCount.addEventListener('click', function() {
                const slug = this.dataset.slug;
                const source = this.dataset.source;
                if (slug && source) {
                    const url = `video-manager.php?slug=${encodeURIComponent(slug)}&source=${encodeURIComponent(source)}`;
                    window.open(url, '_blank');
                }
            });
        });
        
        // Re-initialize any other dynamic event listeners as needed
        console.log('✅ Dynamic event listeners re-initialized');
    }

    // AJAX Search Implementation with Smart Debouncing
    function initAjaxSearch() {
        const searchInput = document.querySelector('input[name="q"]');
        const searchForm = searchInput ? searchInput.closest('form') : null;
        const resultsContainer = document.querySelector('.search-results-container');
        const paginationContainer = document.querySelector('.pagination');
        const searchInfo = document.querySelector('.results-controls .left strong');
        
        console.log('AJAX Search: Elements found:', {
            searchInput: !!searchInput,
            searchForm: !!searchForm,
            resultsContainer: !!resultsContainer
        });
        
        if (!searchInput) {
            console.log('AJAX Search: Search input not found');
            return;
        }
        
        if (!resultsContainer) {
            console.log('AJAX Search: Results container not found');
            return;
        }
        
        let searchTimeout;
        let isSearching = false;
        
        // Smart debouncing logic - COPIED FROM WORKING IONLOCALBLAST.PHP
        function isZipCode(input) {
            // Check if input contains only digits (1-5 digits)
            return /^\d{1,5}$/.test(input.trim());
        }
        
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
                z-index: 5000;
                margin-top: 4px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.2);
                max-width: 100%;
                word-wrap: break-word;
            `;
            // Insert after the search input and ensure parent has relative positioning
            if (searchInput) {
                const parentContainer = searchInput.parentNode;
                if (parentContainer) {
                    parentContainer.style.position = 'relative';
                    parentContainer.appendChild(hint);
                }
            }
        }
        
        function hideZipCodeHint() {
            const existingHint = document.getElementById('zip-code-hint');
            if (existingHint) {
                existingHint.remove();
            }
        }
        
        function performSearch() {
            console.log('AJAX Search: performSearch called');
            if (isSearching) {
                console.log('AJAX Search: Already searching, skipping');
                return;
            }
            
            const searchTerm = searchInput.value.trim();
            console.log('AJAX Search: Search term:', searchTerm);
            hideZipCodeHint();
            
            // Don't search for empty terms
            if (!searchTerm) {
                console.log('AJAX Search: Empty search term, skipping');
                return;
            }
            
            isSearching = true;
            
            // Store focus state before disabling
            const hadFocus = document.activeElement === searchInput;
            
            // Show loading state
            const originalValue = searchInput.value;
            searchInput.disabled = true;
            searchInput.style.opacity = '0.6';
            
            // Build search URL - start fresh to avoid carrying over unwanted parameters
            const url = new URL(window.location.origin + window.location.pathname);
            url.searchParams.set('q', searchTerm);
            url.searchParams.set('view', new URLSearchParams(window.location.search).get('view') || 'grid');
            
            // Add current filters - only if they have values
            const statusFilter = document.querySelector('select[name="status"]');
            const countryFilter = document.querySelector('select[name="country"]');
            const stateFilter = document.querySelector('select[name="state"]');
            const sortFilter = document.querySelector('select[name="sort"]');
            
            // Only add status filter if explicitly selected and not 'draft' (case-insensitive)
            if (statusFilter && statusFilter.value && statusFilter.value !== '' && statusFilter.value.toLowerCase() !== 'draft') {
                url.searchParams.set('status', statusFilter.value);
                console.log('AJAX Search: Status filter set to:', statusFilter.value);
            } else {
                console.log('AJAX Search: No status filter (empty, draft, or not found)');
            }
            if (countryFilter && countryFilter.value && countryFilter.value !== '') url.searchParams.set('country', countryFilter.value);
            if (stateFilter && stateFilter.value && stateFilter.value !== '') url.searchParams.set('state', stateFilter.value);
            if (sortFilter && sortFilter.value && sortFilter.value !== '') url.searchParams.set('sort', sortFilter.value);
            
            console.log('AJAX Search: Searching for:', searchTerm);
            console.log('AJAX Search: URL:', url.toString());
            
            fetch(url.toString(), {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('AJAX Search: Response received:', data);
                
                if (data.success) {
                    // Update results container
                    resultsContainer.innerHTML = data.html;
                    
                    // Re-initialize event listeners for dynamically loaded content
                    initializeDynamicEventListeners();
                    
                    // Update pagination
                    if (paginationContainer) {
                        updatePagination(data);
                    }
                    
                    // Update search info
                    if (searchInfo) {
                        updateSearchInfo(data);
                    }
                    
                    // Update URL without reload
                    window.history.pushState({}, '', url.toString());
                    
                    console.log('AJAX Search: Results updated successfully');
                } else {
                    console.error('AJAX Search: Server returned error:', data);
                    alert('Search failed: ' + (data.error || 'Unknown error'));
                }
                
                // Hide zip code hint when search completes
                hideZipCodeHint();
                
            })
            .catch(error => {
                console.error('AJAX Search: Fetch error:', error);
                alert('Search request failed: ' + error.message);
                hideZipCodeHint();
            })
            .finally(() => {
                // Restore search input state
                searchInput.disabled = false;
                searchInput.style.opacity = '1';
                
                // Restore focus if it was focused before
                if (hadFocus) {
                    searchInput.focus();
                    // Set cursor to end of input
                    searchInput.setSelectionRange(searchInput.value.length, searchInput.value.length);
                }
                
                isSearching = false;
                console.log('AJAX Search: Search completed');
            });
        }
        
        function searchPage(pageNum) {
            console.log('AJAX Search: Navigating to page:', pageNum);
            
            // Update the current page and perform search
            const url = new URL(window.location.href);
            url.searchParams.set('page', pageNum);
            
            // Update browser URL
            window.history.pushState({}, '', url.toString());
            
            // Perform the search with the new page
            performSearchWithPage(pageNum);
        }
        
        function performSearchWithPage(pageNum) {
            console.log('AJAX Search: performSearchWithPage called for page:', pageNum);
            if (isSearching) {
                console.log('AJAX Search: Already searching, skipping');
                return;
            }
            
            const searchTerm = searchInput.value.trim();
            console.log('AJAX Search: Search term for pagination:', searchTerm);
            
            isSearching = true;
            
            // Store focus state before disabling
            const hadFocus = document.activeElement === searchInput;
            
            // Show loading state
            searchInput.disabled = true;
            searchInput.style.opacity = '0.6';
            
            // Build search URL with page number
            const url = new URL(window.location.origin + window.location.pathname);
            url.searchParams.set('q', searchTerm);
            url.searchParams.set('page', pageNum);
            url.searchParams.set('view', new URLSearchParams(window.location.search).get('view') || 'grid');
            
            // Add current filters
            const statusFilter = document.querySelector('select[name="status"]');
            const countryFilter = document.querySelector('select[name="country"]');
            const stateFilter = document.querySelector('select[name="state"]');
            const sortFilter = document.querySelector('select[name="sort"]');
            
            if (statusFilter && statusFilter.value && statusFilter.value !== '' && statusFilter.value.toLowerCase() !== 'draft') {
                url.searchParams.set('status', statusFilter.value);
            }
            if (countryFilter && countryFilter.value && countryFilter.value !== '') url.searchParams.set('country', countryFilter.value);
            if (stateFilter && stateFilter.value && stateFilter.value !== '') url.searchParams.set('state', stateFilter.value);
            if (sortFilter && sortFilter.value && sortFilter.value !== '') url.searchParams.set('sort', sortFilter.value);
            
            console.log('AJAX Search: Pagination URL:', url.toString());
            
            fetch(url.toString(), {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('AJAX Search: Pagination response received:', data);
                
                if (data.success) {
                    // Update results container
                    resultsContainer.innerHTML = data.html;
                    
                    // Re-initialize event listeners for dynamically loaded content
                    initializeDynamicEventListeners();
                    
                    // Update pagination
                    if (paginationContainer) {
                        updatePagination(data);
                    }
                    
                    // Update search info
                    if (searchInfo) {
                        updateSearchInfo(data);
                    }
                    
                    console.log('AJAX Search: Pagination completed successfully');
                } else {
                    console.error('AJAX Search: Server returned error:', data);
                    alert('Pagination failed: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('AJAX Search: Pagination error:', error);
                alert('Pagination request failed: ' + error.message);
            })
            .finally(() => {
                // Restore search input state
                searchInput.disabled = false;
                searchInput.style.opacity = '1';
                
                // Restore focus if it was focused before
                if (hadFocus) {
                    searchInput.focus();
                    searchInput.setSelectionRange(searchInput.value.length, searchInput.value.length);
                }
                
                isSearching = false;
                console.log('AJAX Search: Pagination completed');
            });
        }
        
        function updatePagination(data) {
            // Enhanced pagination with proper page calculation
            if (paginationContainer) {
                let paginationHTML = '';
                
                // Fix the "1 of 0 pages" issue
                const totalPages = data.total_pages || 0;
                const currentPage = data.page || 1;
                
                if (totalPages > 0) {
                    if (data.has_prev) {
                        paginationHTML += `<a href="#" onclick="searchPage(1); return false;">First</a> `;
                        paginationHTML += `<a href="#" onclick="searchPage(${currentPage - 1}); return false;">Previous</a> `;
                    }
                    
                    // Show page numbers
                    for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
                        if (i === currentPage) {
                            paginationHTML += `<strong>${i}</strong> `;
                        } else {
                            paginationHTML += `<a href="#" onclick="searchPage(${i}); return false;">${i}</a> `;
                        }
                    }
                    
                    if (data.has_next) {
                        paginationHTML += `<a href="#" onclick="searchPage(${currentPage + 1}); return false;">Next</a> `;
                        paginationHTML += `<a href="#" onclick="searchPage(${totalPages}); return false;">Last</a>`;
                    }
                    
                    paginationHTML += `<br><small>Page ${currentPage} of ${totalPages}</small>`;
                } else {
                    paginationHTML = '<small>No pages to display</small>';
                }
                
                paginationContainer.innerHTML = paginationHTML;
                console.log('AJAX Search: Pagination updated - Page', currentPage, 'of', totalPages);
            }
        }
        
        function updateSearchInfo(data) {
            if (searchInfo) {
                searchInfo.textContent = `Showing ${data.showing_start}-${data.showing_end} of ${data.total} results`;
            }
        }
        
        // Set up event listeners
        searchInput.addEventListener('input', function(e) {
            const searchTerm = e.target.value.trim();
            console.log('AJAX Search: Input changed to:', searchTerm);
            
            // Clear any existing timeout
            clearTimeout(searchTimeout);
            
            // Check if it's a zip code
            if (isZipCode(searchTerm)) {
                if (searchTerm.length >= 3 && searchTerm.length < 5) {
                    showZipCodeHint('Keep typing for zip code search...', 'waiting');
                }
                
                if (searchTerm.length === 5) {
                    showZipCodeHint('Searching zip code: ' + searchTerm, 'info');
                    // Immediate search for complete zip codes
                    performSearch();
                }
                // For incomplete zip codes, don't search at all - just return
                return;
            }
            
            // For non-zip codes, use debounced search with longer delay
            if (searchTerm.length >= 3) {
                searchTimeout = setTimeout(() => {
                    performSearch();
                }, 1000); // 1 second delay to allow completion of typing
            } else if (searchTerm.length === 0) {
                // Clear results when search is empty
                searchTimeout = setTimeout(() => {
                    performSearch();
                }, 300);
            }
        });
        
        // Prevent form submission and use AJAX instead
        if (searchForm) {
            searchForm.addEventListener('submit', function(e) {
                e.preventDefault();
                clearTimeout(searchTimeout);
                performSearch();
            });
        }
        
        console.log('AJAX Search: Initialized successfully');
    }
    
    // Initialize AJAX search when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAjaxSearch);
    } else {
        initAjaxSearch();
    }
</script>

<!-- ION Pricing Display System -->
<script src="js/ion-pricing-card.js?v=<?php echo time(); ?>"></script>

</body>
</html>