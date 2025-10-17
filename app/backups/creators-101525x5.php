<?php
require_once __DIR__ . '/../login/session.php';

error_log('creators.php: Execution started.');

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/ioncategories.php';
require_once __DIR__ . '/../share/share-manager.php';
$wpdb = $db;

// Badge Management Functions
function renderVideoBadges($video_id) {
    global $wpdb;
    
    if (empty($video_id)) {
        return '';
    }
    
    // Get badges for this video from junction table
    $badges = $wpdb->get_results($wpdb->prepare(
        "SELECT b.name, b.icon, b.color FROM IONBadges b 
         JOIN IONVideoBadges vb ON b.id = vb.badge_id 
         WHERE vb.video_id = %d ORDER BY b.name",
        $video_id
    ));
    
    if (empty($badges)) {
        return '';
    }
    
    $html = '<div class="video-badges">';
    
    foreach ($badges as $badge) {
        $badge_class = 'badge-' . strtolower(str_replace(' ', '-', $badge->name));
        $html .= '<span class="badge ' . $badge_class . '" title="' . htmlspecialchars($badge->name) . '">' . 
                 $badge->icon . ' ' . htmlspecialchars($badge->name) . '</span>';
    }
    
    $html .= '</div>';
    return $html;
}

function hasBadgeForVideo($video_id, $badge_name) {
    global $wpdb;
    
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM IONBadges b 
         JOIN IONVideoBadges vb ON b.id = vb.badge_id 
         WHERE vb.video_id = %d AND b.name = %s",
        $video_id, $badge_name
    ));
    
    return $count > 0;
}

function getVideoBadges($video_id) {
    global $wpdb;
    
    $badges = $wpdb->get_results($wpdb->prepare(
        "SELECT b.name, b.icon, b.color FROM IONBadges b 
         JOIN IONVideoBadges vb ON b.id = vb.badge_id 
         WHERE vb.video_id = %d ORDER BY b.name",
        $video_id
    ));
    
    return $badges;
}

function getVideoBadgeNames($video_id) {
    $badges = getVideoBadges($video_id);
    return array_map(function($b) { return $b->name; }, $badges);
}

// Initialize enhanced share manager with error handling and fallback to original
try {
    require_once __DIR__ . '/../share/enhanced-share-manager.php';
    if (class_exists('EnhancedIONShareManager')) {
        $enhanced_share_manager = new EnhancedIONShareManager($db);
        $share_manager = $enhanced_share_manager; // Use enhanced as primary
    } else {
        throw new Exception('EnhancedIONShareManager class not found, using original');
    }
} catch (Exception $e) {
    error_log("Enhanced share manager initialization error: " . $e->getMessage());
    // Fall back to original share manager
    try {
        if (class_exists('IONShareManager')) {
            $share_manager = new IONShareManager($db);
        } else {
            throw new Exception('IONShareManager class not found');
        }
    } catch (Exception $e2) {
        error_log("Original share manager initialization error: " . $e2->getMessage());
        // Create a minimal fallback share manager
        $share_manager = new class {
            public function renderShareButton($video_id, $options = []) {
                return '<span class="share-unavailable" style="color: #888; font-size: 12px;" title="Share feature temporarily unavailable">📤</span>';
            }
        };
    }
}

// SECURITY CHECK: Ensure user is authenticated

if (!isset($_SESSION['user_email']) || empty($_SESSION['user_email'])) {
    error_log('SECURITY: Unauthenticated user redirected to login from creators.php');
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: /login/index.php');
    exit();
}

// Fetch user data from IONEERS table (using same query as upload-video-handler.php)
$user_email = $_SESSION['user_email'];
$user_data = $wpdb->get_row($wpdb->prepare("SELECT user_id, user_role, email, fullname, photo_url, preferences FROM IONEERS WHERE email = %s", $user_email));

// SECURITY CHECK: Ensure user exists in database
if (!$user_data) {
    error_log("SECURITY: User {$user_email} not found in database");
    session_unset();
    session_destroy();
    header('Location: /login/index.php?error=unauthorized');
    exit();
}

// Use the same user_id that upload-video-handler.php uses
$user_unique_id = $user_data->user_id;

// Validate that we have a proper user_id
if (empty($user_unique_id)) {
    error_log("CRITICAL ERROR: No user_id found for user $user_email in IONEERS table");
    session_unset();
    session_destroy();
    header('Location: /login/index.php?error=user_data_missing');
    exit();
}

$user_photo_url = $user_data->photo_url ?? null;
$user_role = $user_data->user_role ?? 'Guest';
$user_preferences_json = $user_data->preferences ?? null;
$user_fullname = $user_data->fullname ?? null;

// Debug user information
error_log("ION VIDS DEBUG: User email = $user_email, User ID = $user_unique_id, Role = $user_role");
error_log("ION VIDS DEBUG: Raw user_data object: " . print_r($user_data, true));

// DIRECT TEST: Query exactly what you showed me
$direct_test = $wpdb->get_results("SELECT * FROM IONLocalVideos WHERE user_id = 1000010");
error_log("ION VIDS DEBUG: Direct query for user_id 1000010: " . count($direct_test) . " videos found");

// Test if our user_unique_id matches
$our_query_test = $wpdb->get_results($wpdb->prepare("SELECT * FROM IONLocalVideos WHERE user_id = %s", $user_unique_id));
error_log("ION VIDS DEBUG: Our query for user_id $user_unique_id: " . count($our_query_test) . " videos found");

// Parse user preferences
$user_preferences = $user_preferences_json ? json_decode($user_preferences_json, true) : [];
$user_preferences = array_merge([
    'ButtonColor' => '#8a6948',
    'Background' => ['#101728', '#101728']
], $user_preferences);

// Helper function for escaping output
function h($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// Include role-based access control
require_once '../login/roles.php';

// SECURITY CHECK: Ensure user has proper role to access video management
if (!IONRoles::canAccessSection($user_role, 'ION_VIDS')) {
    error_log("SECURITY: User {$user_email} with role {$user_role} denied access to creators.php");
    header('Location: /login/index.php?error=unauthorized');
    exit();
}

error_log("ACCESS GRANTED: User {$user_email} with role {$user_role} accessing ION VIDS section");

// Parse user preferences with defaults
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

// Handle video upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'upload_video_OLD_DISABLED') { // DISABLED - Now using upload-video-handler.php
        header('Content-Type: application/json');
        
        // Validate required fields
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? 'Other');
        
        if (empty($title)) {
            echo json_encode(['success' => false, 'error' => 'Title is required']);
            exit;
        }
        
        // Check if file was uploaded
        error_log("UPLOAD DEBUG: POST data received. Action: " . ($_POST['action'] ?? 'none'));
        error_log("UPLOAD DEBUG: FILES array keys: " . implode(', ', array_keys($_FILES)));
        
        if (!isset($_FILES['video_file'])) {
            error_log("UPLOAD ERROR: No video_file in _FILES array. Available keys: " . implode(', ', array_keys($_FILES)));
            echo json_encode(['success' => false, 'error' => 'No video file uploaded. Please select a file and try again.']);
            exit;
        }
        
        error_log("UPLOAD DEBUG: video_file found. Name: " . $_FILES['video_file']['name'] . ", Size: " . $_FILES['video_file']['size'] . ", Error: " . $_FILES['video_file']['error']);
        
        if ($_FILES['video_file']['error'] !== UPLOAD_ERR_OK) {
            $upload_errors = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
            ];
            $error_message = $upload_errors[$_FILES['video_file']['error']] ?? 'Unknown upload error';
            error_log("UPLOAD ERROR: " . $error_message . " (Code: " . $_FILES['video_file']['error'] . ")");
            echo json_encode(['success' => false, 'error' => 'Upload failed: ' . $error_message]);
            exit;
        }
        
        $uploaded_file = $_FILES['video_file'];
        
        // Validate file type - be more lenient with MIME type detection
        $allowed_extensions = ['mp4', 'webm', 'avi', 'mov'];
        $file_extension = strtolower(pathinfo($uploaded_file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, $allowed_extensions)) {
            echo json_encode(['success' => false, 'error' => 'Invalid file type. Only MP4, WebM, AVI, and MOV files are allowed.']);
            exit;
        }
        
        // Log file details for debugging
        error_log("UPLOAD DEBUG: File name: " . $uploaded_file['name'] . ", Size: " . $uploaded_file['size'] . ", Type: " . $uploaded_file['type'] . ", Extension: " . $file_extension);
        
        // Check file size (100MB limit)
        $max_size = 100 * 1024 * 1024;
        if ($uploaded_file['size'] > $max_size) {
            echo json_encode(['success' => false, 'error' => 'File size too large. Maximum 100MB allowed.']);
            exit;
        }
        
        // Create videos directory if it doesn't exist
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/assets/videos/';
        if (!file_exists($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                echo json_encode(['success' => false, 'error' => 'Failed to create videos directory']);
                exit;
            }
        }
        
        // Generate unique filename
        $file_extension = pathinfo($uploaded_file['name'], PATHINFO_EXTENSION);
        $unique_filename = 'video_' . time() . '_' . uniqid() . '.' . $file_extension;
        $full_path = $upload_dir . $unique_filename;
        
        // Move uploaded file
        if (move_uploaded_file($uploaded_file['tmp_name'], $full_path)) {
            $video_url = '/assets/videos/' . $unique_filename;
            
            // Generate unique video_id for uploaded file
            $video_id = 'upload_' . time() . '_' . uniqid();
            
            // Generate thumbnail for uploaded video
            $thumbnail_url = generateVideoThumbnail($full_path, $unique_filename);
            
            // Determine status based on user role (Owner/Admin = auto-approved)
            $isAdminOrOwner = in_array($user_role, ['Owner', 'Admin']);
            $videoStatus = $isAdminOrOwner ? 'Approved' : 'Pending';
            
            // Insert video data into database
            $insert_data = [
                'title' => $title,
                'description' => $description,
                'video_link' => $video_url,
                'slug' => 'ions', // Default slug for all uploads
                'category' => $category,
                'video_id' => $video_id,
                'thumbnail' => $thumbnail_url,
                'videotype' => 'Upload',
                'source' => 'Upload',
                'status' => $videoStatus, // Auto-approved for Owner/Admin, Pending for others
                'visibility' => 'Public',
                'format' => 'Wide', // Default to Wide for uploads
                'layout' => 'Wide',
                'age' => 'Everyone',
                'geo' => 'None',
                'date_added' => date('Y-m-d H:i:s'),
                'user_id' => $user_unique_id // Use the correct user_id field for proper relationship
            ];
            
            $result = $wpdb->insert('IONLocalVideos', $insert_data);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Video uploaded successfully and submitted for review']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to save video information to database']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to save uploaded file']);
        }
        exit;
    }
    
    if ($_POST['action'] === 'import_video') {
        header('Content-Type: application/json');
        
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $video_url = trim($_POST['video_url'] ?? '');
        $category = trim($_POST['category'] ?? 'Other');
        
        if (empty($title) || empty($video_url)) {
            echo json_encode(['success' => false, 'error' => 'Title and video URL are required']);
            exit;
        }
        
        // Parse video ID and detect source
        $video_id = '';
        $source = 'Youtube'; // Default
        $thumbnail = '';
        
        if (strpos($video_url, 'youtube.com') !== false || strpos($video_url, 'youtu.be') !== false) {
            $source = 'Youtube';
            // Parse YouTube video ID
            if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $video_url, $matches)) {
                $video_id = $matches[1];
                $thumbnail = "https://i.ytimg.com/vi/{$video_id}/mqdefault.jpg";
            }
        } elseif (strpos($video_url, 'vimeo.com') !== false) {
            $source = 'Vimeo';
            // Parse Vimeo video ID
            if (preg_match('/vimeo\.com\/(\d+)/', $video_url, $matches)) {
                $video_id = $matches[1];
                $thumbnail = "https://vumbnail.com/{$video_id}.jpg";
            }
        } elseif (strpos($video_url, 'rumble.com') !== false) {
            $source = 'Rumble';
            // Parse Rumble video ID
            if (preg_match('/rumble\.com\/v([a-zA-Z0-9]+)/', $video_url, $matches)) {
                $video_id = $matches[1];
                // Rumble thumbnails are more complex, use generic approach
                $thumbnail = '';
            }
        } elseif (strpos($video_url, 'muvi.com') !== false) {
            $source = 'Muvi';
            // Parse Muvi video ID (this may vary based on Muvi implementation)
            if (preg_match('/muvi\.com\/.*\/([a-zA-Z0-9_-]+)/', $video_url, $matches)) {
                $video_id = $matches[1];
                $thumbnail = '';
            }
        }
        
        // If we couldn't parse video_id, generate a fallback
        if (empty($video_id)) {
            $video_id = 'import_' . time() . '_' . uniqid();
        }
        
        // Determine status based on user role (Owner/Admin = auto-approved)
        $isAdminOrOwner = in_array($user_role, ['Owner', 'Admin']);
        $videoStatus = $isAdminOrOwner ? 'Approved' : 'Pending';
        
        // Insert video data into database
        $insert_data = [
            'title' => $title,
            'description' => $description,
            'video_link' => $video_url,
            'slug' => 'ions', // Default slug for all imports
            'category' => $category,
            'video_id' => $video_id,
            'thumbnail' => $thumbnail,
            'videotype' => $source,
            'source' => $source,
            'status' => $videoStatus, // Auto-approved for Owner/Admin, Pending for others
            'visibility' => 'Public',
            'format' => 'Wide', // Default to Wide
            'layout' => 'Wide',
            'age' => 'Everyone',
            'geo' => 'None',
            'date_added' => date('Y-m-d H:i:s'),
            'user_id' => $user_unique_id // Use the correct user_id field for proper relationship
        ];
        
        // Check if video already exists to prevent duplicate entry error
        $existing_video = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM IONLocalVideos WHERE video_id = %s",
            $insert_data['video_id']
        ));
        
        if ($existing_video) {
            error_log("DUPLICATE VIDEO IMPORT: Video {$insert_data['video_id']} already exists with ID: {$existing_video->id}");
            
            // Update existing record instead of creating duplicate
            $result = $wpdb->update('IONLocalVideos', $insert_data, ['id' => $existing_video->id]);
            
            if ($result !== false) {
                echo json_encode(['success' => true, 'message' => 'Video updated successfully (was previously imported)']);
            } else {
                error_log("IMPORT UPDATE FAILED: " . $wpdb->last_error);
                echo json_encode(['success' => false, 'error' => 'Failed to update existing video: ' . $wpdb->last_error]);
            }
        } else {
            // Insert new record
            $result = $wpdb->insert('IONLocalVideos', $insert_data);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Video imported successfully and submitted for review']);
            } else {
                error_log("IMPORT INSERT FAILED: " . $wpdb->last_error);
                
                // Check if error is specifically about duplicate entry
                if (strpos($wpdb->last_error, 'Duplicate entry') !== false) {
                    echo json_encode(['success' => false, 'error' => 'This video has already been imported. If you recently deleted it, please wait a moment and try again.']);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to save video information to database: ' . $wpdb->last_error]);
                }
            }
        }
        exit;
    }
}

// Cloudflare R2 delete function
if (!function_exists('deleteFromCloudflareR2')) {
    function deleteFromCloudflareR2($video_url) {
        global $config;
        $r2_config = $config['cloudflare_r2_api'];
        
        // Validate configuration
        if (empty($r2_config['access_key_id']) || empty($r2_config['secret_access_key']) || empty($r2_config['bucket_name'])) {
            return ['success' => false, 'error' => 'Cloudflare R2 credentials not configured'];
        }
        
        // Extract filename from R2 URL (support both old and new URL formats)
        $is_old_r2_url = strpos($video_url, 'r2.cloudflarestorage.com') !== false;
        $is_new_r2_url = strpos($video_url, 'vid.ions.com') !== false;
        
        if (!$is_old_r2_url && !$is_new_r2_url) {
            return ['success' => false, 'error' => 'Not an R2 URL: ' . $video_url];
        }
        
        // Parse the key from URL (after bucket name)
        $bucket = $r2_config['bucket_name'];
        $endpoint = rtrim($r2_config['endpoint'], '/');
        
        // Extract key from URL - handle multiple R2 URL formats
        $key = null;
        
        // Try different URL patterns
        $patterns = [
            // Pattern 1: https://[account].r2.cloudflarestorage.com/[bucket]/[key]
            '/' . preg_quote($bucket, '/') . '\/(.+)$/',
            // Pattern 2: https://pub-[hash].r2.dev/[key] (custom domain without bucket in path)
            '/^https:\/\/pub-[^\/]+\.r2\.dev\/(.+)$/',
            // Pattern 3: Custom domain with bucket: https://cdn.domain.com/[bucket]/[key]
            '/' . preg_quote($bucket, '/') . '\/(.+)$/',
            // Pattern 4: NEW - vid.ions.com domain: https://vid.ions.com/[key]
            '/^https:\/\/vid\.ions\.com\/(.+)$/',
            // Pattern 5: Direct key extraction after last slash if other patterns fail
            '/\/([^\/]+\.(mp4|mov|avi|webm|mkv|flv|wmv))$/i'
        ];
        
        error_log("🔍 R2 URL PARSING: Trying to extract key from URL: $video_url");
        error_log("🔍 R2 URL PARSING: Bucket name: $bucket");
        
        foreach ($patterns as $index => $pattern) {
            if (preg_match($pattern, $video_url, $matches)) {
                $key = urldecode($matches[1]);
                error_log("✅ R2 URL PARSING: Pattern " . ($index + 1) . " matched, extracted key: $key");
                break;
            }
        }
        
        if (!$key) {
            error_log("❌ R2 URL PARSING: Could not extract key from URL: $video_url");
            return ['success' => false, 'error' => 'Could not extract key from R2 URL: ' . $video_url];
        }
        
        try {
            // Prepare DELETE request
            $url = "{$endpoint}/{$bucket}/" . rawurlencode($key);
            
            // Create AWS v4 signature for DELETE
            $date = gmdate('Ymd\THis\Z');
            $shortDate = gmdate('Ymd');
            $region = $r2_config['region'];
            $service = 's3';
            
            $scope = "{$shortDate}/{$region}/{$service}/aws4_request";
            $headers = [
                'Host' => parse_url($endpoint, PHP_URL_HOST),
                'X-Amz-Date' => $date,
                'X-Amz-Content-Sha256' => hash('sha256', '')
            ];
            
            // Create canonical request
            $canonical_headers = '';
            $signed_headers = '';
            ksort($headers);
            foreach ($headers as $name => $value) {
                $canonical_headers .= strtolower($name) . ':' . $value . "\n";
                $signed_headers .= strtolower($name) . ';';
            }
            $signed_headers = rtrim($signed_headers, ';');
            
            $canonical_request = "DELETE\n/{$bucket}/" . rawurlencode($key) . "\n\n{$canonical_headers}\n{$signed_headers}\n" . hash('sha256', '');
            
            // Create signature
            $string_to_sign = "AWS4-HMAC-SHA256\n{$date}\n{$scope}\n" . hash('sha256', $canonical_request);
            $signature_key = hash_hmac('sha256', 'aws4_request', hash_hmac('sha256', $service, hash_hmac('sha256', $region, hash_hmac('sha256', $shortDate, 'AWS4' . $r2_config['secret_access_key'], true), true), true), true);
            $signature = hash_hmac('sha256', $string_to_sign, $signature_key);
            
            // Authorization header
            $authorization = "AWS4-HMAC-SHA256 Credential={$r2_config['access_key_id']}/{$scope}, SignedHeaders={$signed_headers}, Signature={$signature}";
            
            // Execute DELETE request
            error_log("🗑️ R2 DELETE: Attempting to delete: $url");
            error_log("🗑️ R2 DELETE: Key extracted: $key");
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'DELETE',
                    'header' => [
                        "Host: " . parse_url($endpoint, PHP_URL_HOST),
                        "X-Amz-Date: {$date}",
                        "X-Amz-Content-Sha256: " . hash('sha256', ''),
                        "Authorization: {$authorization}"
                    ]
                ]
            ]);
            
            $result = file_get_contents($url, false, $context);
            
            // Check HTTP response headers
            $http_response_header_string = isset($http_response_header) ? implode(', ', $http_response_header) : 'No headers';
            error_log("🗑️ R2 DELETE RESPONSE: " . $http_response_header_string);
            
            if ($result === false) {
                $error = error_get_last();
                error_log("❌ R2 DELETE FAILED: " . ($error['message'] ?? 'Unknown error'));
                return ['success' => false, 'error' => 'Failed to delete from R2: ' . ($error['message'] ?? 'Unknown error')];
            }
            
            // Check if we got a 204 No Content (successful delete) or 404 (already deleted)
            $status_line = isset($http_response_header[0]) ? $http_response_header[0] : '';
            if (strpos($status_line, '204') !== false || strpos($status_line, '404') !== false) {
                error_log("✅ R2 DELETE SUCCESS: $status_line for key: $key");
                return ['success' => true, 'message' => 'File deleted from R2 successfully'];
            } else {
                error_log("⚠️ R2 DELETE UNEXPECTED RESPONSE: $status_line for key: $key");
                return ['success' => true, 'message' => 'File deletion request sent to R2 (response: ' . $status_line . ')'];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'R2 delete error: ' . $e->getMessage()];
        }
    }
}

// Handle video deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_video') {
    header('Content-Type: application/json');
    
    $video_id = intval($_POST['video_id'] ?? 0);
    
    if ($video_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid video ID']);
        exit;
    }
    
    // Get video details first
    $video = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM IONLocalVideos WHERE id = %d",
        $video_id
    ));
    
    if (!$video) {
        echo json_encode(['success' => false, 'error' => 'Video not found']);
        exit;
    }
    
    // Check delete permissions based on role
    $can_delete = false;
    if (in_array($user_role, ['Owner', 'Admin'])) {
        // Owners and Admins can delete ANY video
        $can_delete = true;
        error_log("DELETE PERMISSION: $user_role can delete any video (video_id: $video_id, owner: {$video->user_id})");
    } else if ($video->user_id == $user_unique_id) {
        // Other users can only delete their own videos
        $can_delete = true;
        error_log("DELETE PERMISSION: $user_role can delete own video (video_id: $video_id, user_id: $user_unique_id)");
    } else {
        error_log("DELETE PERMISSION DENIED: $user_role cannot delete video (video_id: $video_id, owner: {$video->user_id}, current_user: $user_unique_id)");
    }
    
    if (!$can_delete) {
        echo json_encode(['success' => false, 'error' => 'You do not have permission to delete this video']);
        exit;
    }
    
    // Delete from Cloudflare R2 if it's stored there
    error_log("🔍 DELETE DEBUG: video_id=$video_id, video_link='{$video->video_link}', source='{$video->source}'");
    
    if (!empty($video->video_link)) {
        // Check for various R2 URL patterns (updated for new vid.ions.com domain)
        $is_r2_url = strpos($video->video_link, 'r2.cloudflarestorage.com') !== false;
        $is_r2_custom_domain = strpos($video->video_link, 'pub-') !== false && strpos($video->video_link, '.r2.dev') !== false;
        $is_r2_cdn = strpos($video->video_link, 'cdn.') !== false; // Custom R2 domains
        $is_r2_public_domain = strpos($video->video_link, 'vid.ions.com') !== false; // NEW: Our public R2 domain
        $has_video_extension = preg_match('/\.(mp4|mov|avi|webm|mkv|flv|wmv)$/i', $video->video_link);
        
        // Exclude YouTube and other external platforms
        $is_youtube = strpos($video->video_link, 'youtube.com') !== false || strpos($video->video_link, 'youtu.be') !== false;
        $is_external_platform = strpos($video->video_link, 'vimeo.com') !== false || strpos($video->video_link, 'rumble.com') !== false;
        
        // Only consider it an R2 video if it's definitely stored on R2
        $is_r2_video = ($is_r2_url || $is_r2_custom_domain || $is_r2_cdn || $is_r2_public_domain) && !$is_youtube && !$is_external_platform;
        
        error_log("🔍 R2 URL CHECK: is_r2_url=$is_r2_url, is_youtube=$is_youtube, is_external_platform=$is_external_platform, is_r2_video=$is_r2_video");
        
        if ($is_r2_video) {
            error_log("🗑️ ATTEMPTING R2 DELETION for: {$video->video_link}");
            $r2_result = deleteFromCloudflareR2($video->video_link);
            
            error_log("🗑️ R2 DELETION RESULT: " . print_r($r2_result, true));
            
            if (!$r2_result['success']) {
                error_log("❌ R2 deletion failed for video {$video_id}: " . $r2_result['error']);
                // For true R2 videos, if deletion fails, we should report the error
                // But continue with database deletion anyway to avoid orphaned records
            } else {
                error_log("✅ R2 file deleted successfully for video {$video_id}");
            }
        } else {
            error_log("⚠️ Video not stored on R2, skipping R2 deletion: {$video->video_link}");
        }
    } else {
        error_log("⚠️ No video_link found for video {$video_id}");
    }
    
    // Delete local physical file if it exists (legacy uploads)
    if ($video->source === 'Upload' && !empty($video->video_link) && strpos($video->video_link, 'r2.cloudflarestorage.com') === false) {
        $file_path = $_SERVER['DOCUMENT_ROOT'] . $video->video_link;
        if (file_exists($file_path)) {
            unlink($file_path);
            error_log("🗑️ Local video file deleted: $file_path");
        }
    }
    
    // Delete thumbnail file (both local and R2)
    if (!empty($video->thumbnail)) {
        error_log("🗑️ Attempting to delete thumbnail: {$video->thumbnail}");
        
        // Check if thumbnail is on R2
        $is_r2_thumbnail = (
            strpos($video->thumbnail, 'r2.cloudflarestorage.com') !== false ||
            strpos($video->thumbnail, '.r2.dev') !== false ||
            strpos($video->thumbnail, 'vid.ions.com') !== false
        );
        
        if ($is_r2_thumbnail) {
            // Delete from R2
            error_log("🗑️ Deleting R2 thumbnail: {$video->thumbnail}");
            $thumb_r2_result = deleteFromCloudflareR2($video->thumbnail);
            if ($thumb_r2_result['success']) {
                error_log("✅ R2 thumbnail deleted successfully");
            } else {
                error_log("⚠️ R2 thumbnail deletion failed: " . $thumb_r2_result['error']);
            }
        } else {
            // Delete local thumbnail file
            $thumbnail_path = $_SERVER['DOCUMENT_ROOT'] . $video->thumbnail;
            if (file_exists($thumbnail_path)) {
                if (unlink($thumbnail_path)) {
                    error_log("✅ Local thumbnail file deleted: $thumbnail_path");
                } else {
                    error_log("⚠️ Failed to delete local thumbnail: $thumbnail_path");
                }
            } else {
                error_log("⚠️ Thumbnail file not found: $thumbnail_path");
            }
        }
    } else {
        error_log("⚠️ No thumbnail found for video {$video_id}");
    }
    
    // Delete from database
    $result = $wpdb->delete('IONLocalVideos', ['id' => $video_id], ['%d']);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Video deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to delete video']);
    }
    exit;
}

// Handle changing video creator
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_video_creator') {
    header('Content-Type: application/json');
    
    $video_id = intval($_POST['video_id'] ?? 0);
    $new_handle = trim($_POST['new_handle'] ?? '');
    
    if ($video_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid video ID']);
        exit;
    }
    
    if (empty($new_handle)) {
        echo json_encode(['success' => false, 'error' => 'Creator handle is required']);
        exit;
    }
    
    // Only Owners and Admins can change video creators
    if (!in_array($user_role, ['Owner', 'Admin'])) {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        exit;
    }
    
    // Get current video data for debugging
    $current_video = $wpdb->get_row($wpdb->prepare(
        "SELECT id, title, user_id FROM IONLocalVideos WHERE id = %d",
        $video_id
    ));
    
    if (!$current_video) {
        echo json_encode(['success' => false, 'error' => 'Video not found']);
        exit;
    }
    
    error_log("🔍 BEFORE UPDATE: Video {$video_id} ('{$current_video->title}') current user_id = {$current_video->user_id}");
    
    // Find the user by handle
    $new_user = $wpdb->get_row($wpdb->prepare(
        "SELECT user_id, handle, fullname, email FROM IONEERS WHERE handle = %s",
        $new_handle
    ));
    
    if (!$new_user) {
        error_log("❌ User lookup failed for handle: @{$new_handle}");
        echo json_encode(['success' => false, 'error' => "User with handle '@{$new_handle}' not found"]);
        exit;
    }
    
    error_log("✅ Found user: {$new_user->user_id} (@{$new_user->handle}, {$new_user->fullname})");
    
    // Update the video's user_id
    $result = $wpdb->update(
        'IONLocalVideos',
        ['user_id' => $new_user->user_id],
        ['id' => $video_id],
        ['%d'],
        ['%d']
    );
    
    error_log("🔄 Update result: " . var_export($result, true));
    error_log("🔍 WP Last Error: " . $wpdb->last_error);
    
    // Verify the update
    $updated_video = $wpdb->get_row($wpdb->prepare(
        "SELECT id, title, user_id FROM IONLocalVideos WHERE id = %d",
        $video_id
    ));
    
    error_log("🔍 AFTER UPDATE: Video {$video_id} user_id = {$updated_video->user_id}");
    
    if ($result !== false && $updated_video->user_id == $new_user->user_id) {
        error_log("✅ Video $video_id successfully reassigned from user {$current_video->user_id} to user {$new_user->user_id} (@{$new_user->handle})");
        echo json_encode([
            'success' => true,
            'message' => "Video reassigned to @{$new_user->handle}",
            'creator_handle' => $new_user->handle,
            'creator_name' => $new_user->fullname,
            'old_user_id' => $current_video->user_id,
            'new_user_id' => $new_user->user_id
        ]);
    } else {
        error_log("❌ Video reassignment failed! Result: $result, Expected user_id: {$new_user->user_id}, Actual user_id: {$updated_video->user_id}");
        echo json_encode(['success' => false, 'error' => 'Failed to update video creator. Database error: ' . $wpdb->last_error]);
    }
    exit;
}

// Function to generate video thumbnail
function generateVideoThumbnail($video_path, $filename) {
    // Create thumbs directory if it doesn't exist
    $thumbs_dir = $_SERVER['DOCUMENT_ROOT'] . '/assets/thumbs/';
    if (!file_exists($thumbs_dir)) {
        mkdir($thumbs_dir, 0755, true);
    }
    
    $thumbnail_filename = 'thumb_' . pathinfo($filename, PATHINFO_FILENAME) . '.jpg';
    $thumbnail_path = $thumbs_dir . $thumbnail_filename;
    $thumbnail_url = '/assets/thumbs/' . $thumbnail_filename;
    
    // Try to generate thumbnail using FFmpeg if available
    $ffmpeg_cmd = "ffmpeg -i " . escapeshellarg($video_path) . " -ss 00:00:01 -vframes 1 -f image2 " . escapeshellarg($thumbnail_path) . " 2>/dev/null";
    
    // Execute FFmpeg command (if exec is available)
    $output = [];
    $return_code = 1; // Default to failure
    if (function_exists('exec')) {
        exec($ffmpeg_cmd, $output, $return_code);
    } else {
        error_log("THUMBNAIL: exec() function not available for FFmpeg");
    }
    
    // If FFmpeg succeeded and thumbnail exists, return the URL
    if ($return_code === 0 && file_exists($thumbnail_path)) {
        error_log("THUMBNAIL: Generated thumbnail for $filename using FFmpeg");
        return $thumbnail_url;
    }
    
    // Fallback: Try ImageMagick if available
    $convert_cmd = "convert " . escapeshellarg($video_path . "[0]") . " " . escapeshellarg($thumbnail_path) . " 2>/dev/null";
    if (function_exists('exec')) {
        exec($convert_cmd, $output, $return_code);
    } else {
        error_log("THUMBNAIL: exec() function not available for ImageMagick");
        $return_code = 1; // Set to failure
    }
    
    if ($return_code === 0 && file_exists($thumbnail_path)) {
        error_log("THUMBNAIL: Generated thumbnail for $filename using ImageMagick");
        return $thumbnail_url;
    }
    
    // Final fallback: Create a placeholder thumbnail with video info
    createPlaceholderThumbnail($thumbnail_path, pathinfo($filename, PATHINFO_FILENAME));
    
    if (file_exists($thumbnail_path)) {
        error_log("THUMBNAIL: Created placeholder thumbnail for $filename");
        return $thumbnail_url;
    }
    
    // Return default thumbnail if all methods fail
    error_log("THUMBNAIL: Using default thumbnail for $filename");
    return 'https://iblog.bz/assets/ionthumbnail.png';
}

// Function to create a placeholder thumbnail
function createPlaceholderThumbnail($thumbnail_path, $video_name) {
    $width = 320;
    $height = 180;
    
    // Create image
    $image = imagecreate($width, $height);
    
    // Define colors
    $bg_color = imagecolorallocate($image, 45, 55, 72);  // Dark background
    $text_color = imagecolorallocate($image, 255, 255, 255); // White text
    $accent_color = imagecolorallocate($image, 178, 130, 84); // ION accent color
    
    // Fill background
    imagefill($image, 0, 0, $bg_color);
    
    // Add border
    imagerectangle($image, 0, 0, $width-1, $height-1, $accent_color);
    imagerectangle($image, 1, 1, $width-2, $height-2, $accent_color);
    
    // Add play icon (triangle)
    $triangle = [
        $width/2 - 20, $height/2 - 15,  // Top point
        $width/2 + 15, $height/2        // Right point
    ];
    imagefilledpolygon($image, $triangle, 3, $text_color);
    
    // Add "VIDEO" text
    $font_size = 3;
    $text = "VIDEO";
    $text_width = imagefontwidth($font_size) * strlen($text);
    $text_x = ($width - $text_width) / 2;
    $text_y = $height/2 + 25;
    imagestring($image, $font_size, $text_x, $text_y, $text, $text_color);
    
    // Save image
    imagejpeg($image, $thumbnail_path, 85);
    imagedestroy($image);
}

// Pagination settings
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 12; // Videos per page
$offset = ($page - 1) * $per_page;

// Fetch video statistics with STRICT role-based security
if (in_array($user_role, ['Owner', 'Admin'])) {
    // ONLY Owners and Admins see ALL videos - show system-wide stats
    $video_stats = [
        'total'     => $wpdb->get_var("SELECT COUNT(*) FROM IONLocalVideos"),
        'approved'  => $wpdb->get_var("SELECT COUNT(*) FROM IONLocalVideos WHERE status = 'Approved'"),
        'rejected'  => $wpdb->get_var("SELECT COUNT(*) FROM IONLocalVideos WHERE status = 'Rejected'"),
        'pending'   => $wpdb->get_var("SELECT COUNT(*) FROM IONLocalVideos WHERE status = 'Pending'"),
        'paused'    => $wpdb->get_var("SELECT COUNT(*) FROM IONLocalVideos WHERE status = 'Paused'"),
        'uploaders' => $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM IONLocalVideos")
    ];
    error_log("SECURITY: Admin/Owner $user_email sees all videos - Total: " . $video_stats['total']);
} else if (in_array($user_role, ['Creator', 'Member'])) {
    // Creators and Members see only their own videos - show personal stats
    $user_total = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM IONLocalVideos WHERE user_id = %s",
        $user_unique_id
    ));
    
    $video_stats = [
        'total'     => intval($user_total),
        'approved'  => intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM IONLocalVideos WHERE user_id = %s AND status = 'Approved'", $user_unique_id))),
        'rejected'  => intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM IONLocalVideos WHERE user_id = %s AND status = 'Rejected'", $user_unique_id))),
        'pending'   => intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM IONLocalVideos WHERE user_id = %s AND status = 'Pending'", $user_unique_id))),
        'paused'    => intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM IONLocalVideos WHERE user_id = %s AND status = 'Paused'", $user_unique_id))),
        'uploaders' => 1
    ];
    error_log("SECURITY: Creator/Member $user_email (role: $user_role) sees only own videos - Total: " . $video_stats['total']);
} else {
    // Any other role gets zero stats
    $video_stats = [
        'total'     => 0,
        'live'      => 0,
        'rejected'  => 0,
        'pending'   => 0,
        'awaiting'  => 0,
        'uploaders' => 0
    ];
    error_log("SECURITY: Unknown role $user_email (role: " . ($user_role ?? 'NULL') . ") gets zero stats");
}

// Handle search functionality - UPDATED WITH PROVEN LOGIC
$search_term = trim($_GET['q'] ?? $_GET['search'] ?? '');
$sort_option = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$uploader_filter = isset($_GET['uploader']) ? $_GET['uploader'] : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$source_filter = $_GET['source'] ?? '';
$category_filter = $_GET['category'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$date_field = $_GET['date_field'] ?? '';

// Initialize search arrays
$where_conditions = [];
$params = [];

// Advanced search term parsing (from search-test.php)
if (!empty($search_term)) {
    $search_conditions = [];
    $search_params = [];
    
    // Check for @username search pattern FIRST (before other search logic)
    if (preg_match('/^@(\w+)$/i', $search_term, $username_match)) {
        // Extract username (everything after @)
        $username = $username_match[1];
        
        // Look up user by handle, fullname, or email in IONEERS table
        $target_user = $wpdb->get_row($wpdb->prepare(
            "SELECT user_id FROM IONEERS WHERE handle = %s OR fullname LIKE %s OR email LIKE %s LIMIT 1",
            $username,
            '%' . $username . '%',
            '%' . $username . '%'
        ));
        
        if ($target_user && !empty($target_user->user_id)) {
            // Found the user - filter videos by their user_id
            $where_conditions[] = "v.user_id = %s";
            $params[] = $target_user->user_id;
            error_log("SEARCH: @username search for '$username' found user_id: {$target_user->user_id}");
        } else {
            // User not found - return no results by adding impossible condition
            $where_conditions[] = "1 = 0";
            error_log("SEARCH: @username search for '$username' - user not found");
        }
        
        // Skip the rest of the search logic since we're doing a user-specific search
        $search_term = ''; // Clear search term to skip normal search logic below
    }
    
    // Define search fields for IONLocalVideos (with table alias)
    $search_fields = ['v.video_id', 'v.slug', 'v.title', 'v.video_link', 'v.description', 'v.transcript', 'v.tags'];
    
    // Parse search term based on type (only if not already handled by @username)
    if (!empty($search_term) && preg_match('/^"(.+)"$/', $search_term, $matches)) {
        // EXACT PHRASE: "premium chalk"
        $exact_phrase = $matches[1];
        $field_conditions = [];
        foreach ($search_fields as $field) {
            $field_conditions[] = "LOWER($field) LIKE LOWER(%s)";
            $search_params[] = '%' . $exact_phrase . '%';
        }
        $search_conditions[] = '(' . implode(' OR ', $field_conditions) . ')';
        
    } elseif (!empty($search_term) && stripos($search_term, ' AND ') !== false) {
        // AND SEARCH: premium AND chalk
        $and_terms = preg_split('/\s+AND\s+/i', $search_term);
        foreach ($and_terms as $term) {
            $term = trim($term);
            if (!empty($term)) {
                $field_conditions = [];
                foreach ($search_fields as $field) {
                    $field_conditions[] = "LOWER($field) LIKE LOWER(%s)";
                    $search_params[] = '%' . $term . '%';
                }
                $search_conditions[] = '(' . implode(' OR ', $field_conditions) . ')';
            }
        }
        
    } elseif (!empty($search_term)) {
        // OR SEARCH: premium chalk (either word)
        $or_terms = preg_split('/\s+/', $search_term);
        $all_field_conditions = [];
        foreach ($or_terms as $term) {
            $term = trim($term);
            if (!empty($term)) {
                foreach ($search_fields as $field) {
                    $all_field_conditions[] = "LOWER($field) LIKE LOWER(%s)";
                    $search_params[] = '%' . $term . '%';
                }
            }
        }
        if (!empty($all_field_conditions)) {
            $search_conditions[] = '(' . implode(' OR ', $all_field_conditions) . ')';
        }
    }
    
    // Add search conditions to main WHERE clause
    if (!empty($search_conditions)) {
        $where_conditions[] = implode(' AND ', $search_conditions);
        $params = array_merge($params, $search_params);
    }
}

// Source filter
if (!empty($source_filter)) {
    $where_conditions[] = "v.source = %s";
    $params[] = $source_filter;
}

// Category filter
if (!empty($category_filter)) {
    $where_conditions[] = "v.category = %s";
    $params[] = $category_filter;
}

// Status filter
if (!empty($status_filter)) {
    switch ($status_filter) {
        case 'approved':
            $where_conditions[] = "v.status = 'Approved'";
            break;
        case 'pending':
            $where_conditions[] = "v.status = 'Pending'";
            break;
        case 'rejected':
            $where_conditions[] = "v.status = 'Rejected'";
            break;
        case 'paused':
            $where_conditions[] = "v.status = 'Paused'";
            break;
        default:
            $where_conditions[] = "v.status = %s";
            $params[] = $status_filter;
            break;
    }
}

// Uploader filter
if (!empty($uploader_filter)) {
    $where_conditions[] = "v.user_id = %s";
    $params[] = $uploader_filter;
}

// Type filter
if (!empty($type_filter)) {
    if ($type_filter === 'upload') {
        $where_conditions[] = "v.source = 'Upload'";
    } elseif ($type_filter === 'import') {
        $where_conditions[] = "v.source != 'Upload'";
    }
}

// Date range filter
if (!empty($date_from) || !empty($date_to)) {
    if (!empty($date_field)) {
        $date_conditions = [];
        
        // Add table prefix if not already present
        $prefixed_date_field = (strpos($date_field, '.') === false) ? "v.$date_field" : $date_field;
        
        if (!empty($date_from)) {
            $date_conditions[] = "$prefixed_date_field >= %s";
            $params[] = $date_from . ' 00:00:00';
        }
        
        if (!empty($date_to)) {
            $date_conditions[] = "$prefixed_date_field <= %s";
            $params[] = $date_to . ' 23:59:59';
        }
        
        if (!empty($date_conditions)) {
            $where_conditions[] = '(' . implode(' AND ', $date_conditions) . ')';
        }
    }
}

// 🔐 CRITICAL SECURITY: User restriction for video access
if (!in_array($user_role, ['Owner', 'Admin'])) {
    // Use the same user_id that videos are stored with
    $where_conditions[] = "v.user_id = %s";
    $params[] = $user_unique_id;
    error_log("🔒 SECURITY: Restricting videos to user_id = $user_unique_id (Role: $user_role)");
} else {
    error_log("👑 ADMIN ACCESS: User $user_email has $user_role role - showing all videos");
}

// Build WHERE clause
$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get total count with the SAME filters as display - UPDATED WITH PROVEN METHOD
$count_query = "SELECT COUNT(*) FROM IONLocalVideos v LEFT JOIN IONEERS u ON v.user_id = u.user_id $where_clause";

if (!empty($params)) {
    $prepared_count = call_user_func_array(array($wpdb, 'prepare'), array_merge(array($count_query), $params));
    $total_videos = $wpdb->get_var($prepared_count);
} else {
    $total_videos = $wpdb->get_var($count_query);
}

// Build ORDER BY clause
$order_clause = 'ORDER BY ';
switch ($sort_option) {
    case 'oldest':
        $order_clause .= 'v.date_added ASC';
        break;
    case 'title':
        $order_clause .= 'v.title ASC';
        break;
    case 'status':
        $order_clause .= 'v.status ASC, v.date_added DESC';
        break;
    default: // newest
        $order_clause .= 'v.date_added DESC';
        break;
}

// Execute query with search - UPDATED WITH PROVEN METHOD
// IMPORTANT: Include ALL videos regardless of upload_status for editing purposes
// JOIN with IONEERS to get creator handle AND IONVideoLikes to get user reactions
$videos_query = "SELECT v.*, u.handle as creator_handle, u.fullname as creator_name, u.email as creator_email,
                 vl.action_type as user_reaction
                 FROM IONLocalVideos v 
                 LEFT JOIN IONEERS u ON v.user_id = u.user_id 
                 LEFT JOIN IONVideoLikes vl ON v.id = vl.video_id AND vl.user_id = $user_unique_id
                 $where_clause $order_clause LIMIT $per_page OFFSET $offset";

// DEBUG: Log the actual query being executed
error_log("CREATORS QUERY DEBUG: WHERE clause: " . $where_clause);
error_log("CREATORS QUERY DEBUG: Full query: " . $videos_query);

if (!empty($params)) {
    $prepared_videos = call_user_func_array(array($wpdb, 'prepare'), array_merge(array($videos_query), $params));
    $user_videos = $wpdb->get_results($prepared_videos);
} else {
    $user_videos = $wpdb->get_results($videos_query);
}

// VERIFICATION: Log the actual result for confirmation
error_log("FINAL RESULT: Found " . count($user_videos ?? []) . " videos for role: " . $user_role);

// DEBUG: Check for videos with different upload_status values
$upload_status_debug = $wpdb->get_results("SELECT upload_status, COUNT(*) as count FROM IONLocalVideos GROUP BY upload_status");
if ($upload_status_debug) {
    foreach ($upload_status_debug as $status_row) {
        error_log("UPLOAD STATUS DEBUG: " . ($status_row->upload_status ?? 'NULL') . " = " . $status_row->count . " videos");
    }
} else {
    error_log("UPLOAD STATUS DEBUG: No upload_status column or no data");
}

// Basic error check
if ($wpdb->last_error) {
    error_log("ION VIDS ERROR: " . $wpdb->last_error);
}

// Calculate pagination info
$total_pages = ceil($total_videos / $per_page);
$start_item = count($user_videos) > 0 ? $offset + 1 : 0;
$end_item = $offset + count($user_videos);

// Generate improved pagination display text
function generate_pagination_text($total_videos, $start_item, $end_item, $total_pages) {
    if ($total_videos == 0) {
        return "Showing 0 videos";
    } else if ($total_pages == 1) {
        // Single page - no pagination needed
        if ($total_videos == 1) {
            return "Showing 1 of 1 video";
        } else if ($total_videos <= 20) {
            return "Showing all " . number_format($total_videos) . " videos";
        } else {
            return "Showing all " . number_format($total_videos) . " videos";
        }
    } else {
        // Multiple pages - use range format
        return "Showing " . number_format($start_item) . "-" . number_format($end_item) . " of " . number_format($total_videos) . " videos";
    }
}

$pagination_text = generate_pagination_text($total_videos, $start_item, $end_item, $total_pages);

error_log("User: $user_email (UID: $user_unique_id), Role: $user_role, Videos: " . count($user_videos));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ION Video Management</title>
    <link rel="stylesheet" href="directory.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="creators.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="/share/enhanced-ion-share.css?v=<?php echo time(); ?>">
    
    <!-- Video Reactions System -->
    <link href="/app/video-reactions.css?v=<?= time() ?>" rel="stylesheet">
    
    <style>
    /* CRITICAL: Inline styles to ensure active states work (overrides any conflicts) */
    .reaction-btn.like-btn.active {
        color: #10b981 !important;
        background: rgba(16, 185, 129, 0.15) !important;
        border-color: rgba(16, 185, 129, 0.4) !important;
    }
    
    .reaction-btn.like-btn.active svg {
        stroke: #10b981 !important;
        fill: none !important;
    }
    
    .reaction-btn.like-btn.active .like-count {
        color: #10b981 !important;
    }
    
    .reaction-btn.dislike-btn.active {
        color: #ef4444 !important;
        background: rgba(239, 68, 68, 0.15) !important;
        border-color: rgba(239, 68, 68, 0.4) !important;
    }
    
    .reaction-btn.dislike-btn.active svg {
        stroke: #ef4444 !important;
        fill: none !important;
    }
    
    .reaction-btn.dislike-btn.active .dislike-count {
        color: #ef4444 !important;
    }
    
    /* Reaction button hover states */
    .reaction-btn:hover {
        opacity: 0.8;
        transform: translateY(-1px);
    }
    </style>
    <style>
    /* Ensure share buttons are clickable and visible */
    .enhanced-share-button,
    .share-button,
    [class*="share"] {
        pointer-events: auto !important;
        z-index: 10 !important;
        position: relative !important;
    }
    
    /* Fix any potential overlay issues */
    .video-card .action-buttons {
        position: relative;
        z-index: 5;
    }
    
    .video-card .action-buttons button {
        pointer-events: auto;
        cursor: pointer;
    }
    
    /* List View - Matching User's Design */
    .videos-table-container {
        display: none;
        background: transparent;
        gap: 12px;
        overflow: visible; /* Remove all overflow restrictions to prevent double scroll */
        width: 100%;
        max-width: 100%;
        height: auto; /* Ensure natural height */
    }
    
    .videos-table {
        width: 100%;
        max-width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        background: transparent;
        table-layout: fixed; /* Force table to respect container width */
        height: auto; /* Ensure natural height */
        overflow: visible; /* Prevent table from creating scroll */
    }
    
    .videos-table thead {
        display: none; /* Hide table headers to match user's design */
    }
    
    .videos-table tbody tr {
        background: var(--bg-secondary);
        border-radius: 12px;
        margin-bottom: 12px;
        display: block;
        padding: 16px;
        transition: all 0.2s ease;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        position: relative; /* ensure absolutely positioned dropdowns are contained */
        overflow: visible; /* allow dropdown to overflow the row block */
        z-index: 0; /* base stacking context below global modal */
    }
    
    .videos-table tbody tr:hover {
        background: rgba(255, 255, 255, 0.02);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }
    
    .videos-table td {
        display: inline-block;
        vertical-align: top;
        border: none;
        padding: 0;
    }
    
    /* Row Layout - Horizontal */
    .video-row {
        display: flex !important;
        align-items: center;
        gap: 12px; /* Reduced from 16px to 12px */
        padding: 8px 16px !important; /* Reduced vertical padding from 16px to 8px */
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
        overflow: hidden; /* Prevent content from overflowing */
    }
    
    /* Thumbnail Column - Larger as requested */
    .col-thumbnail {
        flex-shrink: 0;
        padding: 0; /* Remove any default padding */
        margin: 0; /* Remove any default margins */
    }
    
    .table-thumbnail-container {
        position: relative;
        width: 150px; /* Increased from 120px to 150px */
        height: 100px; /* Increased from 80px to 100px */
        border-radius: 8px;
        overflow: hidden;
        margin: 0; /* Remove any default margins */
    }
    
    .table-video-thumb {
        display: block;
        width: 100%;
        height: 100%;
        position: relative;
    }
    
    .table-video-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 6px;
    }
    
    .table-play-overlay {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: rgba(0, 0, 0, 0.7);
        border-radius: 50%;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.2s ease;
    }
    
    .table-video-thumb:hover .table-play-overlay {
        opacity: 1;
    }
    
    .table-preview-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        border-radius: 8px;
        overflow: hidden;
    }
    
    /* Title and Content Column - Flexible */
    .col-title {
        flex: 1;
        min-width: 0;
        overflow: hidden; /* Prevent text overflow */
    }
    
    .table-title-content {
        min-width: 0;
        overflow: hidden;
    }
    
    .table-video-title {
        font-weight: 600;
        font-size: 16px;
        color: #f1f5f9;
        margin-bottom: 6px;
        line-height: 1.4;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    .table-video-description {
        font-size: 14px;
        color: #94a3b8;
        line-height: 1.5;
        margin-bottom: 8px;
        overflow: hidden;
        text-overflow: ellipsis;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        white-space: normal;
    }
    
    /* Date Column - Right side */
    .col-date {
        flex-shrink: 0;
        text-align: right;
        margin-right: 16px;
    }
    
    .table-date-time {
        text-align: right;
    }
    
    .table-date {
        font-size: 14px;
        font-weight: 500;
        color: #e2e8f0;
        margin-bottom: 4px;
    }
    
    .table-time {
        font-size: 12px;
        color: #94a3b8;
    }
    
    /* Status Column - Right side */
    .col-status {
        flex-shrink: 0;
        margin-right: 16px;
    }
    
    .table-status-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        cursor: pointer;
        transition: all 0.2s ease;
        position: relative;
        z-index: 1; /* align with cards so it stays under the modal */
    }
    
    .table-status-badge.clickable:hover {
        transform: scale(1.05);
    }
    
    .status-options-table {
        position: absolute;
        top: 100%;
        left: 0;
        background: #0b1220; /* solid background to prevent underlying row bleed-through (match Cards) */
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.45);
        z-index: 4000; /* above list rows; video modal still higher */
        min-width: 200px; /* Reduce min-width to prevent overflow */
        max-width: 90vw; /* Constrain to viewport width */
        padding: 8px; /* match Cards inner padding */
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px);
        transition: all 0.2s ease;
        right: auto; /* Allow left positioning */
    }
    
    .status-options-table.show {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }
    
    /* Ensure two-column grid styling is identical across views */
    .status-options-grid {
        display: grid;
        grid-template-columns: repeat(1, 1fr);
        gap: 14px 16px; /* spacing parity with Cards */
        padding: 2px; /* inner gutter handled by parent padding */
    }
    .status-option-inline {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 14px 18px;
        border-radius: 10px;
        font-weight: 600;
        background: rgba(255,255,255,0.03);
        border: 1px solid rgba(255,255,255,0.06);
        cursor: pointer;
        transition: background 0.2s ease, transform 0.15s ease;
        user-select: none;
    }
    .status-option-inline:hover { background: rgba(255,255,255,0.08); transform: translateY(-1px); }
    
    /* Actions Column - Right side */
    .col-actions {
        flex-shrink: 0;
        min-width: 250px; /* Ensure enough space for all buttons */
        width: 250px;
        position: relative; /* Allow dropdowns to position relative to this */
        overflow: visible !important; /* Override any hidden overflow */
    }
    
    .table-action-buttons {
        display: flex;
        align-items: center;
        gap: 5px;
        flex-wrap: nowrap; /* Prevent wrapping to avoid height issues */
        max-width: 250px; /* Increased to accommodate all buttons */
        overflow: visible; /* Show all buttons */
        justify-content: flex-end; /* Align buttons to the right */
    }
    
    /* Use existing card view button styles for consistency */
    .table-action-buttons .action-btn {
        background: none;
        border: none;
        cursor: pointer;
        padding: 4px 6px;
        border-radius: 6px;
        transition: all 0.2s ease;
    }
    
    .table-action-buttons .action-btn:hover {
        background: rgba(255, 255, 255, 0.1);
        transform: translateY(-1px);
    }
    
    /* Hover tooltip styles for action buttons */
    .hover-label::after {
        content: attr(data-label);
        position: absolute;
        bottom: 100%;
        left: 50%;
        transform: translateX(-50%);
        margin-bottom: 8px;
        padding: 6px 10px;
        font-size: 11px;
        font-weight: 500;
        white-space: nowrap;
        border-radius: 6px;
        opacity: 0;
        visibility: hidden;
        transition: all 0.2s ease;
        z-index: 1000;
        pointer-events: none;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }
    
    /* Tooltip arrow */
    .hover-label::before {
        content: '';
        position: absolute;
        bottom: 100%;
        left: 50%;
        transform: translateX(-50%);
        margin-bottom: 2px;
        width: 0;
        height: 0;
        border-left: 5px solid transparent;
        border-right: 5px solid transparent;
        opacity: 0;
        visibility: hidden;
        transition: all 0.2s ease;
        z-index: 1001;
        pointer-events: none;
    }
    
    .hover-label:hover::after,
    .hover-label:hover::before {
        opacity: 1;
        visibility: visible;
    }
    
    .hover-label:hover {
        transform: translateY(-1px);
    }
    
    /* Delete button tooltip - Red theme */
    .delete-icon.hover-label::after {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
        border: 1px solid rgba(239, 68, 68, 0.3);
    }
    
    .delete-icon.hover-label::before {
        border-top: 5px solid rgba(239, 68, 68, 0.3);
    }
    
    .delete-icon:hover {
        background: rgba(239, 68, 68, 0.1);
    }
    
    /* Edit button tooltip - Purple theme */
    .edit-icon.hover-label::after {
        background: rgba(79, 70, 229, 0.1);
        color: #4f46e5;
        border: 1px solid rgba(79, 70, 229, 0.3);
    }
    
    .edit-icon.hover-label::before {
        border-top: 5px solid rgba(79, 70, 229, 0.3);
    }
    
    .edit-icon:hover {
        background: rgba(79, 70, 229, 0.1);
    }
    
    /* Featured button tooltip - Gold theme */
    .featured-icon.hover-label::after {
        background: rgba(251, 191, 36, 0.1);
        color: #fbbf24;
        border: 1px solid rgba(251, 191, 36, 0.3);
    }
    
    .featured-icon.hover-label::before {
        border-top: 5px solid rgba(251, 191, 36, 0.3);
    }
    
    .featured-icon:hover {
        background: rgba(251, 191, 36, 0.1);
    }
    
    /* Featured button active/inactive states */
    .featured-active {
        opacity: 1;
    }
    
    .featured-inactive {
        opacity: 0.7;
    }
    
    .featured-inactive:hover {
        opacity: 1;
    }
    
    /* Blast button tooltip - Purple theme */
    .blast-icon.hover-label::after,
    .blast-btn.hover-label::after {
        background: rgba(139, 92, 246, 0.1);
        color: #8b5cf6;
        border: 1px solid rgba(139, 92, 246, 0.3);
    }
    
    .blast-icon.hover-label::before,
    .blast-btn.hover-label::before {
        border-top: 5px solid rgba(139, 92, 246, 0.3);
    }
    
    .blast-icon:hover,
    .blast-btn:hover {
        background: rgba(139, 92, 246, 0.1);
    }
    
    /* Hide text in share buttons - make them icon only */
    .enhanced-share-button span,
    .share-button span,
    .share-btn span {
        display: none !important;
    }
    
    /* Add hover labels to share buttons */
    .enhanced-share-button,
    .share-button,
    .share-btn {
        position: relative;
    }
    
    /* Share button tooltip - Blue theme */
    .enhanced-share-button::after,
    .share-button::after {
        content: "Share";
        position: absolute;
        bottom: 100%;
        left: 50%;
        transform: translateX(-50%);
        margin-bottom: 8px;
        padding: 6px 10px;
        background: rgba(59, 130, 246, 0.1);
        color: #3b82f6;
        border: 1px solid rgba(59, 130, 246, 0.3);
        font-size: 11px;
        font-weight: 500;
        white-space: nowrap;
        border-radius: 6px;
        opacity: 0;
        visibility: hidden;
        transition: all 0.2s ease;
        z-index: 1000;
        pointer-events: none;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }
    
    /* Share button tooltip arrow */
    .enhanced-share-button::before,
    .share-button::before {
        content: '';
        position: absolute;
        bottom: 100%;
        left: 50%;
        transform: translateX(-50%);
        margin-bottom: 2px;
        width: 0;
        height: 0;
        border-left: 5px solid transparent;
        border-right: 5px solid transparent;
        border-top: 5px solid rgba(59, 130, 246, 0.3);
        opacity: 0;
        visibility: hidden;
        transition: all 0.2s ease;
        z-index: 1001;
        pointer-events: none;
    }
    
    .enhanced-share-button:hover::after,
    .share-button:hover::after,
    .enhanced-share-button:hover::before,
    .share-button:hover::before {
        opacity: 1;
        visibility: visible;
    }
    
    .enhanced-share-button:hover,
    .share-button:hover {
        background: rgba(59, 130, 246, 0.1);
        transform: translateY(-1px);
    }
    
    /* Prevent date wrapping in table view */
    .table-date-time {
        white-space: nowrap;
        min-width: 100px;
    }
    
    .table-date,
    .table-time {
        white-space: nowrap;
    }
    
    /* Badge System Styles */
    .video-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        margin: 6px 0 4px 0; /* Below subtitle, above creator handle */
    }
    
    .badge {
        font-size: 10px;
        padding: 2px 6px;
        border-radius: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        white-space: nowrap;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
    }
    
    .badge-featured {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
        border: 1px solid rgba(239, 68, 68, 0.3);
    }
    
    .badge-favorites {
        background: linear-gradient(135deg, #fbbf24, #f59e0b);
        color: #1f2937;
        border: 1px solid rgba(251, 191, 36, 0.3);
    }
    
    .badge-trending {
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
        border: 1px solid rgba(16, 185, 129, 0.3);
    }
    
    .badge-hidden-gem {
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        color: white;
        border: 1px solid rgba(139, 92, 246, 0.3);
    }
    
    .badge-spotlight {
        background: linear-gradient(135deg, #f97316, #ea580c);
        color: white;
        border: 1px solid rgba(249, 115, 22, 0.3);
    }
    
    .badge-hall-of-fame {
        background: linear-gradient(135deg, #facc15, #eab308);
        color: #1f2937;
        border: 1px solid rgba(250, 204, 21, 0.3);
        box-shadow: 0 0 8px rgba(250, 204, 21, 0.3);
    }
    
    /* Compact badges for table view */
    .table-view .badge {
        font-size: 9px;
        padding: 1px 4px;
    }
    
    /* Badge Management Dropdown Styles */
    .badge-dropdown {
        position: relative;
        display: inline-block;
    }
    
    /* Smart positioning - dropdown appears below by default */
    .badge-dropdown-content {
        position: absolute;
        top: 100%;
        left: 50%;
        transform: translateX(-50%);
        background: rgba(30, 41, 59, 0.98);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        padding: 12px;
        min-width: 200px;
        margin-top: 8px;
        opacity: 0;
        visibility: hidden;
        transition: all 0.2s ease;
        z-index: 1000000; /* Higher than all other elements including modals (99999) and notifications (10001) */
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.6);
        overflow: visible;
        white-space: nowrap;
    }
    
    /* When dropdown should appear above (near bottom of screen) */
    .badge-dropdown.position-above .badge-dropdown-content {
        top: auto;
        bottom: 100%;
        margin-top: 0;
        margin-bottom: 8px;
    }
    
    .badge-dropdown:hover .badge-dropdown-content,
    .badge-dropdown.active .badge-dropdown-content {
        opacity: 1;
        visibility: visible;
        transform: translateX(-50%) translateY(4px);
    }
    
    /* Elevate parent card when badge dropdown is active */
    .badge-dropdown:hover,
    .badge-dropdown.active {
        z-index: 999999;
        position: relative;
    }
    
    /* Elevate entire video card when badge dropdown is active */
    .video-card:has(.badge-dropdown:hover),
    .video-card:has(.badge-dropdown.active) {
        z-index: 999997 !important;
        position: relative;
    }
    
    /* Fallback for browsers that don't support :has() */
    .video-card.dropdown-active {
        z-index: 999997 !important;
        position: relative;
    }
    
    /* Ensure entire video row is elevated when dropdown is active */
    .videos-table tbody tr:has(.badge-dropdown:hover),
    .videos-table tbody tr:has(.badge-dropdown.active) {
        z-index: 999997 !important;
        position: relative;
    }
    
    /* Fallback for table rows */
    .videos-table tbody tr.dropdown-active {
        z-index: 999997 !important;
        position: relative;
    }
    
    .badge-dropdown.position-above:hover .badge-dropdown-content,
    .badge-dropdown.position-above.active .badge-dropdown-content {
        transform: translateX(-50%) translateY(-4px);
    }
    
    /* Specific overrides for table view badge dropdowns */
    .table-action-buttons .badge-dropdown-content {
        z-index: 1000000 !important; /* Even higher z-index for table view */
        /* position: fixed !important; */ /* DISABLED: Use fixed positioning to escape table constraints */
        transform: none !important; /* Remove transform since we're using fixed positioning */
        left: auto !important; /* Will be set by JavaScript */
        top: auto !important; /* Will be set by JavaScript */
        margin: 0 !important; /* Remove margins for fixed positioning */
    }
    
    .table-action-buttons .badge-dropdown:hover .badge-dropdown-content,
    .table-action-buttons .badge-dropdown.active .badge-dropdown-content {
        opacity: 1;
        visibility: visible;
        transform: none !important; /* Remove transform animations for fixed positioning */
    }
    
    
    .badge-dropdown-header {
        font-size: 11px;
        font-weight: 600;
        color: #fbbf24;
        padding: 8px 0 4px 0;
        margin-bottom: 8px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        position: relative;
        z-index: 1000000;
        margin-bottom: 6px;
        text-align: center;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        padding-bottom: 4px;
    }
    
    .badge-option {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 6px 8px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 11px;
        color: #e5e7eb;
        transition: all 0.2s ease;
        margin: 2px 0;
    }
    
    .badge-option:hover {
        background: rgba(255, 255, 255, 0.1);
        transform: translateX(2px);
    }
    
    .badge-option.active {
        background: rgba(16, 185, 129, 0.2);
        color: #10b981;
        border-left: 3px solid #10b981;
        padding-left: 5px;
    }
    
    .badge-option .badge-icon {
        font-size: 14px;
        width: 16px;
        text-align: center;
    }
    
    .badge-option .badge-name {
        flex: 1;
        font-weight: 500;
    }
    
    .badge-option .badge-description {
        font-size: 9px;
        color: #9ca3af;
        margin-top: 1px;
    }
    
    /* Enhanced star button */
    .star-badge-button {
        position: relative;
        background: none;
        border: none;
        cursor: pointer;
        padding: 4px 6px;
        border-radius: 6px;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        font-size: 12px;
    }
    
    .star-badge-button:hover {
        background: rgba(251, 191, 36, 0.1);
        transform: translateY(-1px);
    }
    
    .star-badge-button.has-badges {
        color: #fbbf24;
    }
    
    .star-badge-button:not(.has-badges) {
        color: #6b7280;
    }
    
    .star-badge-button:not(.has-badges):hover {
        color: #fbbf24;
    }
    
    /* Status management in table view */
    .table-action-buttons .status-management {
        position: relative;
        margin-left: 8px;
        z-index: 1; /* keep near base layer so global modals sit above */
    }
    
    /* Ensure no double scroll bars at page level */
    html, body {
        overflow-x: hidden; /* Prevent horizontal scroll */
        height: auto !important; /* Allow natural height */
        max-height: none !important; /* Remove any height restrictions */
    }
    
    /* Mobile Responsive */
    @media (max-width: 768px) {
        .videos-table-container {
            padding: 0 0.5rem; /* Add padding to prevent edge overflow */
        }
        
        .video-row {
            flex-direction: column;
            align-items: flex-start;
            gap: 12px;
            padding: 12px !important; /* Reduce padding on mobile */
        }
        
        .col-thumbnail {
            align-self: center;
        }
        
        .table-thumbnail-container {
            width: 120px; /* Increased from 100px to maintain proportions */
            height: 80px; /* Increased from 60px to maintain proportions */
        }
        
        .table-action-buttons {
            max-width: 100%; /* Allow full width on mobile */
            flex-wrap: wrap; /* Allow wrapping on mobile */
            overflow: visible; /* Ensure buttons are visible on mobile */
            justify-content: center; /* Center buttons on mobile */
        }
        
        .status-options-table {
            min-width: 250px; /* Smaller on mobile */
            right: 0; /* Align to right edge */
            left: auto;
        }
        
        .col-title {
            order: 2;
        }
        
        .table-video-title {
            font-size: 15px;
        }
        
        .table-video-description {
            font-size: 13px;
        }
        
        .col-date {
            order: 3;
            text-align: left;
            margin-right: 0;
        }
        
        .col-status {
            order: 4;
            margin-right: 0;
        }
        
        .col-actions {
            order: 5;
            min-width: auto; /* Reset min-width on mobile */
            width: auto; /* Reset width on mobile */
            align-self: stretch;
        }
        
        .table-action-buttons {
            justify-content: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .table-action-buttons .action-btn {
            padding: 6px 8px;
        }
    }
    
    /* Creator Handle Hover Effects */
    .creator-handle-container:hover .edit-creator-btn {
        display: inline-flex !important;
        opacity: 1 !important;
    }
    
    /* Prevent date wrapping in card view */
    .video-date {
        white-space: nowrap !important;
    }
    </style>
    <style>
    :root {
        --primary-color: #8a6948;
        --bg-gradient: linear-gradient(135deg, #101728, #101728);
    }
    </style>
    
<script>
function openEditUserDialog(userData, isSelfEdit = false) {
    // Temporary simple implementation for debugging
    alert('Profile dialog clicked - debugging mode');
    console.log('Profile dialog called with:', userData, isSelfEdit);
}

// Profile dialog functions are now handled by the external profile-dialog.js file
</script>    
    
</head>
<body style="background: <?= h($user_preferences['Background'][0] ?? '#0f172a') ?>;">
<?php
// Configure header for ION Video Directory
$header_config = [
    'title'               => 'ION Video Directory',
    'search_placeholder'  => 'Search videos or creators',
    'search_value'        => $_GET['q'] ?? $_GET['search'] ?? '',
    'active_tab'          => 'ION_VIDS',
    'button_text'         => '+ Upload Videos',
    'button_id'           => 'upload-videos-btn',
    'button_onclick'      => 'openIONVideoUploader()',
    'button_class'        => '',
    'show_button'         => true,
    'mobile_button_text'  => 'Upload Videos'
];
include 'headers.php';
?>

<!-- Enhanced Toolbar with Full-Width Layout -->
<div class="videos-header" style="background: var(--card-bg); padding: 0.20rem 0.5rem; border-radius: 6px; margin-bottom: 1rem; border: 1px solid var(--border-color); box-shadow: 0 1px 4px rgba(0,0,0,0.05); width: 100%;">
    <div class="toolbar-container" style="display: flex; align-items: center; width: 100%; min-height: 60px;">
        <!-- Left Block: Video Count & Summary -->
        <div class="toolbar-left" style="flex: 0 0 auto; min-width: 200px;">
            <h2 style="margin: 0; color: var(--text-primary); font-size: 1.1rem; font-weight: 600; white-space: nowrap;">
                <?= in_array($user_role, ['Owner', 'Admin']) ? 'All Videos' : 'Your Videos' ?> (<?= number_format($total_videos) ?>)
            </h2>
            <p style="color: var(--text-secondary); margin: 0.25rem 0 0 0; font-size: 0.85rem; white-space: nowrap;">
                <?= $pagination_text ?>
            </p>
        </div>
        
        <!-- Center Block: Filters -->
        <div class="toolbar-center" style="flex: 1; display: flex; justify-content: center; padding: 0 2rem;">
            <div class="filters-container" style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; justify-content: center;">
                <form action="" method="get" id="filter-form" style="display: contents;">
                    <input type="hidden" name="view" id="view-input" value="grid">

                    <select name="sort" onchange="this.form.submit()" class="filter-select" style="padding: 0.4rem 0.6rem; border: 1px solid var(--border-color); border-radius: 4px; background: white; color: #333; font-size: 0.825rem; min-width: 120px;">
                        <option value="newest" <?= $sort_option === 'newest' ? 'selected' : '' ?>>Sort by Newest</option>
                        <option value="oldest" <?= $sort_option === 'oldest' ? 'selected' : '' ?>>Sort by Oldest</option>
                        <option value="title" <?= $sort_option === 'title' ? 'selected' : '' ?>>Sort by Title</option>
                        <option value="status" <?= $sort_option === 'status' ? 'selected' : '' ?>>Sort by Status</option>
                    </select>
                    
                    <select name="status" onchange="this.form.submit()" class="filter-select" style="padding: 0.4rem 0.6rem; border: 1px solid var(--border-color); border-radius: 4px; background: white; color: #333; font-size: 0.825rem; min-width: 100px;">
                        <option value="">All Status</option>
                        <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Approved (<?= number_format($video_stats['approved']) ?>)</option>
                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending (<?= number_format($video_stats['pending']) ?>)</option>
                        <option value="paused" <?= $status_filter === 'paused' ? 'selected' : '' ?>>Paused (<?= number_format($video_stats['paused']) ?>)</option>
                        <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejected (<?= number_format($video_stats['rejected']) ?>)</option>
                    </select>
                    
                    <select name="category" onchange="this.form.submit()" class="filter-select" style="padding: 0.4rem 0.6rem; border: 1px solid var(--border-color); border-radius: 4px; background: white; color: #333; font-size: 0.825rem; min-width: 80px; max-width: 80px;">
                        <?= generate_ion_category_options($category_filter, true) ?>
                    </select>
                    
                    <?php if (in_array($user_role, ['Owner', 'Admin'])): ?>
                    <select name="uploader" onchange="this.form.submit()" class="filter-select" style="padding: 0.4rem 0.6rem; border: 1px solid var(--border-color); border-radius: 4px; background: white; color: #333; font-size: 0.825rem; min-width: 110px; position: relative; z-index: 10000;">
                        <option value="">All Creators</option>
                        <option value="<?= $user_unique_id ?>" <?= $uploader_filter === $user_unique_id ? 'selected' : '' ?>>My Videos</option>
                    </select>
                    <?php endif; ?>
                    
                    <select name="type" onchange="this.form.submit()" class="filter-select" style="padding: 0.4rem 0.6rem; border: 1px solid var(--border-color); border-radius: 4px; background: white; color: #333; font-size: 0.825rem; min-width: 100px;">
                        <option value="">All Types</option>
                        <option value="upload" <?= $type_filter === 'upload' ? 'selected' : '' ?>>Uploaded</option>
                        <option value="import" <?= $type_filter === 'import' ? 'selected' : '' ?>>Imported</option>
                    </select>
                </form>
            </div>
        </div>
        
        <!-- Right Block: View Toggle -->
        <div class="toolbar-right" style="flex: 0 0 auto;">
            <div class="view-toggle" style="display: flex; background: var(--secondary-bg); border-radius: 4px; padding: 2px; border: 1px solid var(--border-color);">
                <button class="view-btn active" id="cardViewBtn" onclick="toggleView('card')" style="display: flex; align-items: center; gap: 0.4rem; padding: 0.4rem 0.6rem; border: none; background: var(--primary-color); color: white; border-radius: 3px; font-size: 0.8rem; cursor: pointer; transition: all 0.2s;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M3 3h7v7H3V3zm0 11h7v7H3v-7zm11-11h7v7h-7V3zm0 11h7v7h-7v-7z"/>
                    </svg>
                    Cards
                </button>
                <button class="view-btn" id="listViewBtn" onclick="toggleView('list')" style="display: flex; align-items: center; gap: 0.4rem; padding: 0.4rem 0.6rem; border: none; background: transparent; color: var(--text-secondary); border-radius: 3px; font-size: 0.8rem; cursor: pointer; transition: all 0.2s;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z"/>
                    </svg>
                    List
                </button>
            </div>
        </div>
    </div>
</div>

<div class="container" style="overflow-x: hidden; max-width: 100%; box-sizing: border-box; height: auto; overflow-y: visible;">
    <!-- Video Statistics -->
    <div class="stats-grid">
        <div class="stat-card videos clickable" onclick="filterByStatus('')">
            <h3><?= number_format($video_stats['total']) ?></h3>
            <p>📹 Videos</p>
        </div>
<div class="stat-card approved clickable" onclick="filterByStatus('approved')">
            <h3><?= number_format($video_stats['approved']) ?></h3>
            <p>🟢 Approved</p>
        </div>
<div class="stat-card rejected clickable" onclick="filterByStatus('rejected')">
            <h3><?= number_format($video_stats['rejected']) ?></h3>
            <p>🔴 Rejected</p>
        </div>
<div class="stat-card pending clickable" onclick="filterByStatus('pending')">
            <h3><?= number_format($video_stats['pending']) ?></h3>
            <p>🟡 Pending</p>
        </div>
<div class="stat-card paused clickable" onclick="filterByStatus('paused')">
            <h3><?= number_format($video_stats['paused']) ?></h3>
            <p>⏸️ Paused</p>
        </div>
                <div class="stat-card uploaders clickable" onclick="filterByCreators()">
                    <h3><?= number_format($video_stats['uploaders']) ?></h3>
                    <p>🟠 Creators</p>
        </div>
            </div>
            
        <!-- Upload/Import Actions - Only show when user has no videos -->
        <?php if (empty($user_videos)): ?>
        <div class="upload-actions" style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin: 2rem 0;">
            <div class="upload-card" style="background: rgba(255, 255, 255, 0.1); border-radius: 12px; padding: 2rem; text-align: center; border: 1px solid rgba(255, 255, 255, 0.2);">
                <div style="font-size: 3rem; margin-bottom: 1rem;">
                    📤
                </div>
                <h3 style="margin-bottom: 1rem; font-size: 1.5rem;">Upload Video</h3>
                <p style="margin-bottom: 2rem; opacity: 0.8; line-height: 1.6;">Upload your own video files directly to our platform. Supported formats: MP4, WebM, AVI, MOV</p>
                <button onclick="openIONVideoUploader()" style="background: hsl(var(--primary)); color: white; border: none; padding: 0.75rem 2rem; border-radius: 8px; font-weight: 600; cursor: pointer; transition: opacity 0.2s;">📤 Upload Video</button>
            </div>
            
            <div class="import-card" style="background: rgba(255, 255, 255, 0.1); border-radius: 12px; padding: 2rem; text-align: center; border: 1px solid rgba(255, 255, 255, 0.2);">
                <div style="font-size: 3rem; margin-bottom: 1rem;">
                    🔗
                </div>
                <h3 style="margin-bottom: 1rem; font-size: 1.5rem;">Import from Platform</h3>
                <p style="margin-bottom: 2rem; opacity: 0.8; line-height: 1.6;">Import videos from YouTube, Vimeo, Rumble, Muvi and other platforms using their URL</p>
                <button onclick="openIONVideoUploader()" style="background: hsl(var(--primary)); color: white; border: none; padding: 0.75rem 2rem; border-radius: 8px; font-weight: 600; cursor: pointer; transition: opacity 0.2s;">🔗 Import Video</button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Search Results Info -->
        <?php if (!empty($search_term) || !empty($status_filter) || !empty($uploader_filter) || !empty($type_filter)): ?>
            <div class="search-results-info" style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2); border-radius: 8px; padding: 12px; margin: 1rem 0; color: var(--text-primary); font-size: 14px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="m21 21-4.35-4.35"></path>
                    </svg>
                    <span>
                        Found <?= number_format($total_videos ?? 0) ?> video(s)
                        <?php if (!empty($search_term)): ?>
                            matching "<strong><?= htmlspecialchars($search_term) ?></strong>"
                        <?php endif; ?>
                        <?php if (!empty($status_filter) || !empty($uploader_filter) || !empty($type_filter)): ?>
                            with applied filters
                        <?php endif; ?>
                    </span>
                    <a href="creators.php" style="margin-left: auto; color: inherit; text-decoration: underline; font-size: 13px;">Clear all filters</a>
                </div>
            </div>
        <?php endif; ?>

        <!-- User's Videos -->
        <div class="videos-section">
            
            <?php if (empty($user_videos)): ?>
                <div class="empty-state">
                    <div class="empty-icon">🎦</div>
                    <h3>No videos yet</h3>
                    <p>Start by uploading your first video or importing from a platform</p>
                </div>
            <?php else: ?>
                <!-- Card View -->
                <div class="videos-grid" id="cardView">
                    <?php foreach ($user_videos as $video): 
                        // Determine video info for modal and preview
                        $video_type = strtolower($video->source ?? 'youtube');
                        $video_id = $video->video_id ?? '';
                        $thumbnail = $video->thumbnail ?? 'https://ions.com/assets/default/processing.png';
                        $video_url = $video->video_link ?? '';
                        
                        // Generate preview URL for hover
                        $preview_url = '';
                        if ($video_type === 'youtube' && $video_id) {
                            $preview_url = 'https://www.youtube.com/embed/' . htmlspecialchars($video_id, ENT_QUOTES, 'UTF-8') . '?autoplay=1&mute=1&controls=0&loop=1&playlist=' . htmlspecialchars($video_id, ENT_QUOTES, 'UTF-8');
                        } elseif ($video_type === 'vimeo' && $video_id) {
                            $preview_url = 'https://player.vimeo.com/video/' . htmlspecialchars($video_id, ENT_QUOTES, 'UTF-8') . '?autoplay=1&muted=1&background=1';
                        } elseif ($video_type === 'wistia' && $video_id) {
                            $preview_url = 'https://fast.wistia.net/embed/iframe/' . htmlspecialchars($video_id, ENT_QUOTES, 'UTF-8') . '?autoplay=1&muted=1&controls=0';
                        } elseif ($video_type === 'rumble' && $video_id) {
                            $preview_url = 'https://rumble.com/embed/v' . htmlspecialchars($video_id, ENT_QUOTES, 'UTF-8') . '/?autoplay=1&muted=1';
                        } elseif ($video_type === 'muvi' && $video_id) {
                            $preview_url = 'https://embed.muvi.com/embed/' . htmlspecialchars($video_id, ENT_QUOTES, 'UTF-8') . '?autoplay=1';
                        } elseif ($video_type === 'local' && $video_url) {
                            // For local/uploaded videos, we'll use a data attribute that JavaScript will handle
                            // Store the video URL to be used by Video.js on hover
                            $preview_url = 'local:' . htmlspecialchars($video_url, ENT_QUOTES, 'UTF-8');
                        }
                    ?>
                        <div class="carousel-item">
                            <a href="#" class="video-thumb" onclick="return openVideoInModal(event, this)"
                               data-video-id="<?= htmlspecialchars($video_id, ENT_QUOTES, 'UTF-8') ?>" 
                               data-video-type="<?= htmlspecialchars($video_type, ENT_QUOTES, 'UTF-8') ?>"
                               data-video-url="<?= htmlspecialchars($video->video_link, ENT_QUOTES, 'UTF-8') ?>"
                               data-video-title="<?= htmlspecialchars($video->title, ENT_QUOTES, 'UTF-8') ?>"
                               <?= $video_type === 'local' ? 'data-video-format="' . htmlspecialchars(pathinfo($video->video_link, PATHINFO_EXTENSION), ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                                <img class="video-thumbnail" src="<?= htmlspecialchars($thumbnail, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($video->title, ENT_QUOTES, 'UTF-8') ?>" onerror="this.onerror=null; this.src='https://ions.com/assets/default/processing.png';">
                                <div class="play-icon-overlay">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="rgba(255,255,255,0.8)">
                                        <path d="M8 5v14l11-7z"></path>
                                    </svg>
                                </div>
                                <?php if (!empty($preview_url)): ?>
                                <div class="preview-iframe-container" data-preview-url="<?= htmlspecialchars($preview_url, ENT_QUOTES, 'UTF-8') ?>" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; pointer-events: none; transition: opacity 0.3s ease;"></div>
                                <?php endif; ?>
                            </a>
                            <div class="video-card-info">
                                <p><?= html_entity_decode($video->title, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></p>
                                <?php if (!empty($video->description)): ?>
                                    <small style="color: #a4b3d0; display: block; margin-top: 0.35rem; line-height: 1.3;">
                                        <?= htmlspecialchars(substr($video->description, 0, 80)) ?><?= strlen($video->description) > 80 ? '...' : '' ?>
                                    </small>
                                <?php endif; ?>
                                
                                <!-- Badges moved here (below subtitle, above creator) -->
                                <?= renderVideoBadges($video->id) ?>
                                
                                <!-- Creator Handle with Likes/Dislikes -->
                                <div class="creator-handle-container" style="display: flex; align-items: center; justify-content: space-between; min-height: 24px;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <?php if (!empty($video->creator_handle)): ?>
                                            <a href="/@<?= htmlspecialchars($video->creator_handle, ENT_QUOTES, 'UTF-8') ?>" 
                                               style="color: #3b82f6; text-decoration: none; font-size: 0.875rem;"
                                               onclick="window.open(this.href, '_blank'); return false;"
                                               title="View <?= htmlspecialchars($video->creator_name ?? $video->creator_handle, ENT_QUOTES, 'UTF-8') ?>'s profile">
                                                <span>@<?= htmlspecialchars($video->creator_handle, ENT_QUOTES, 'UTF-8') ?></span>
                                            </a>
                                        <?php else: ?>
                                            <span style="color: #64748b; font-size: 0.875rem;">@unknown</span>
                                        <?php endif; ?>
                                        
                                        <?php if (in_array($user_role, ['Owner', 'Admin'])): ?>
                                            <button class="edit-creator-btn" 
                                                    onclick="editVideoCreator(<?= $video->id ?>, '<?= htmlspecialchars($video->creator_handle ?? '', ENT_QUOTES, 'UTF-8') ?>', <?= $video->user_id ?>)" 
                                                    style="background: none; border: none; cursor: pointer; color: #64748b; padding: 2px 4px; display: none; opacity: 0; transition: opacity 0.2s ease;"
                                                    title="Change video creator">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                                </svg>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Likes/Dislikes Engagement -->
                                    <div class="video-reactions" data-video-id="<?= $video->id ?>" data-user-action="<?= htmlspecialchars($video->user_reaction ?? '', ENT_QUOTES, 'UTF-8') ?>" style="display: flex; align-items: center; gap: 8px; flex-shrink: 0;">
                                        <button class="reaction-btn like-btn <?= ($video->user_reaction ?? '') === 'like' ? 'active' : '' ?>" data-action="like" style="background: none; border: 1px solid rgba(100, 116, 139, 0.3); border-radius: 6px; padding: 4px 8px; cursor: pointer; color: #64748b; font-size: 0.75rem; display: flex; align-items: center; gap: 4px; transition: all 0.2s;">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M7 22V11M2 13V20C2 21.1046 2.89543 22 4 22H17.4262C18.907 22 20.1662 20.9197 20.3914 19.4562L21.4683 12.4562C21.7479 10.6389 20.3418 9 18.5032 9H15V4C15 2.89543 14.1046 2 13 2H12.5C12.2239 2 12 2.22386 12 2.5C12 3.19838 11.8052 3.88237 11.4391 4.47463L8.5 9.5"></path>
                                            </svg>
                                            <span class="like-count"><?= intval($video->likes ?? 0) ?></span>
                                        </button>
                                        <button class="reaction-btn dislike-btn <?= ($video->user_reaction ?? '') === 'dislike' ? 'active' : '' ?>" data-action="dislike" style="background: none; border: 1px solid rgba(100, 116, 139, 0.3); border-radius: 6px; padding: 4px 8px; cursor: pointer; color: #64748b; font-size: 0.75rem; display: flex; align-items: center; gap: 4px; transition: all 0.2s;">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M17 2V13M22 11V4C22 2.89543 21.1046 2 20 2H6.57383C5.09299 2 3.83379 3.08025 3.60865 4.54377L2.53172 11.5438C2.25207 13.3611 3.65819 15 5.49666 15H9V20C9 21.1046 9.89543 22 11 22H11.5C11.7761 22 12 21.7761 12 21.5C12 20.8016 12.1948 20.1176 12.5609 19.5254L15.5 14.5"></path>
                                            </svg>
                                            <span class="dislike-count"><?= intval($video->dislikes ?? 0) ?></span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="video-meta" style="display: flex; justify-content: space-between; align-items: center; padding: 0 0.75rem 0.75rem;">
                                <div style="display: flex; align-items: center;">
                                    <span class="video-date"><?= date('M j, Y', strtotime($video->date_added)) ?></span>
                                    
                                    <!-- Video Action Buttons -->
                                    <div class="video-actions" style="display: flex; align-items: center; gap: 0px; margin-left: 5px;">
                                        <?php 
                                        $can_edit_video = in_array($user_role, ['Owner', 'Admin']) || ($video->user_id == $user_unique_id);
                                        ?>
                                        
                                        <!-- Badge Management Dropdown -->
                                        <?php if (in_array($user_role, ['Owner', 'Admin'])): 
                                            $video_badges = getVideoBadgeNames($video->id);
                                            $has_badges = !empty($video_badges);
                                        ?>
                                            <div class="badge-dropdown">
                                                <button class="star-badge-button <?= $has_badges ? 'has-badges' : '' ?>" 
                                                        title="Manage badges">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                                        <path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                                </svg>
                                            </button>
                                                <div class="badge-dropdown-content">
                                                    <div class="badge-dropdown-header">Manage Badges</div>
                                                    
                                                    <div class="badge-option <?= in_array('Featured', $video_badges) ? 'active' : '' ?>" 
                                                         onclick="toggleBadge(<?= $video->id ?>, 'Featured')">
                                                        <span class="badge-icon">🔥</span>
                                                        <div>
                                                            <div class="badge-name">Featured</div>
                                                            <div class="badge-description">Editor-selected best content</div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="badge-option <?= in_array('Favorites', $video_badges) ? 'active' : '' ?>" 
                                                         onclick="toggleBadge(<?= $video->id ?>, 'Favorites')">
                                                        <span class="badge-icon">🌟</span>
                                                        <div>
                                                            <div class="badge-name">Favorites</div>
                                                            <div class="badge-description">Community-loved content</div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="badge-option <?= in_array('Trending', $video_badges) ? 'active' : '' ?>" 
                                                         onclick="toggleBadge(<?= $video->id ?>, 'Trending')">
                                                        <span class="badge-icon">🚀</span>
                                                        <div>
                                                            <div class="badge-name">Trending</div>
                                                            <div class="badge-description">Rapidly gaining engagement</div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="badge-option <?= in_array('Hidden Gem', $video_badges) ? 'active' : '' ?>" 
                                                         onclick="toggleBadge(<?= $video->id ?>, 'Hidden Gem')">
                                                        <span class="badge-icon">💎</span>
                                                        <div>
                                                            <div class="badge-name">Hidden Gem</div>
                                                            <div class="badge-description">Underrated quality content</div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="badge-option <?= in_array('Spotlight', $video_badges) ? 'active' : '' ?>" 
                                                         onclick="toggleBadge(<?= $video->id ?>, 'Spotlight')">
                                                        <span class="badge-icon">📣</span>
                                                        <div>
                                                            <div class="badge-name">Spotlight</div>
                                                            <div class="badge-description">Temporarily promoted</div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="badge-option <?= in_array('Hall of Fame', $video_badges) ? 'active' : '' ?>" 
                                                         onclick="toggleBadge(<?= $video->id ?>, 'Hall of Fame')">
                                                        <span class="badge-icon">🏅</span>
                                                        <div>
                                                            <div class="badge-name">Hall of Fame</div>
                                                            <div class="badge-description">Evergreen classic content</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php elseif (!empty($video_badges)): 
                                            // Show badges for non-admin users (view only)
                                        ?>
                                            <div class="star-badge-button has-badges" title="Video has badges">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                                </svg>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Enhanced Share Button -->
                                        <?php
                                        if (isset($enhanced_share_manager) && method_exists($enhanced_share_manager, 'renderShareButton')) {
                                            echo $enhanced_share_manager->renderShareButton($video->id, [
                                                'size' => 'small',
                                                'style' => 'icon',
                                                'platforms' => ['facebook', 'twitter', 'whatsapp', 'linkedin', 'telegram', 'reddit', 'email', 'copy'],
                                                'show_embed' => true
                                            ]);
                                        } else {
                                            echo $share_manager->renderShareButton($video->id, [
                                                'size' => 'medium',
                                                'style' => 'icon',
                                                'platforms' => ['facebook', 'twitter', 'whatsapp', 'copy'],
                                                'trigger' => 'click'
                                            ]);
                                        }
                                        ?>
                                        
                                        <!-- Blast Button -->
                                        <button class="blast-icon action-btn hover-label" onclick="openBlastDialog('<?= $video->id ?>', '<?= htmlspecialchars($video->title, ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars($thumbnail, ENT_QUOTES, 'UTF-8') ?>')" title="Blast this video" data-label="Blast" style="background: none; border: none; cursor: pointer; padding: 4px 6px; color: #8b5cf6; border-radius: 6px; transition: all 0.2s; display: flex; align-items: center; font-size: 12px; position: relative;">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.25 4.75l7.5 7.5-7.5 7.5m-6-15l7.5 7.5-7.5 7.5"/>
                                            </svg>
                                        </button>
                                        
                                        <?php if ($can_edit_video): ?>
                                            <button class="edit-icon action-btn hover-label" onclick="editVideo(<?= $video->id ?>)" title="Edit video details" data-label="Edit" style="background: none; border: none; cursor: pointer; padding: 4px 6px; color: #4f46e5; border-radius: 6px; transition: all 0.2s; display: flex; align-items: center; font-size: 12px; position: relative;">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                                </svg>
                                            </button>
                                            
                                            <!-- Delete Button (Last) -->
                                            <button class="delete-icon action-btn hover-label" data-video-id="<?= $video->id ?>" data-video-title="<?= htmlspecialchars($video->title, ENT_QUOTES, 'UTF-8') ?>" data-video-info="ID:<?= $video->id ?>,VID:<?= $video->video_id ?? 'none' ?>" title="Delete video" data-label="Delete" style="background: none; border: none; cursor: pointer; padding: 4px 6px; color: #ef4444; border-radius: 6px; transition: all 0.2s; display: flex; align-items: center; font-size: 12px; position: relative;">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if (in_array($user_role, ['Owner', 'Admin'])): ?>
                                    <div class="status-management">
                                        <span class="status-badge status-<?= str_replace(' ', '-', strtolower($video->status)) ?> clickable" onclick="toggleStatusOptions(<?= $video->id ?>)" id="status-badge-<?= $video->id ?>">
                                            <?= htmlspecialchars($video->status) ?>
                                            <svg class="status-arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="6,9 12,15 18,9"></polyline>
                                            </svg>
                                        </span>
                                        <div class="status-options-inline" id="status-options-<?= $video->id ?>">
                                            <div class="status-options-grid">
                                                <div class="status-option-inline" data-status="Approved" onclick="changeVideoStatus(<?= $video->id ?>, 'Approved')">✅ Approved</div>
                                                <div class="status-option-inline" data-status="Pending" onclick="changeVideoStatus(<?= $video->id ?>, 'Pending')">🟡 Pending</div>
                                                <div class="status-option-inline" data-status="Paused" onclick="changeVideoStatus(<?= $video->id ?>, 'Paused')">⏸️ Paused</div>
                                                <div class="status-option-inline" data-status="Rejected" onclick="changeVideoStatus(<?= $video->id ?>, 'Rejected')">🔴 Rejected</div>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="status-badge status-<?= str_replace(' ', '-', strtolower($video->status)) ?>"><?= htmlspecialchars($video->status) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- List View -->
                <div class="videos-table-container" id="listView">
                    <table class="videos-table">
                        <thead>
                            <tr>
                                <th class="col-thumbnail">Video</th>
                                <th class="col-title">Title</th>
                                <th class="col-date">Date/Time</th>
                                <th class="col-status">Status</th>
                                <th class="col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($user_videos as $video): 
                                $video_type = strtolower($video->source ?? 'youtube');
                                $video_id = $video->video_id ?? '';
                                $thumbnail = $video->thumbnail ?? 'https://ions.com/assets/default/processing.png';
                                $video_url = $video->video_link ?? '';
                                
                                // Generate preview URL for hover
                                $preview_url = '';
                                if ($video_type === 'youtube' && $video_id) {
                                    $preview_url = 'https://www.youtube.com/embed/' . htmlspecialchars($video_id, ENT_QUOTES, 'UTF-8') . '?autoplay=1&mute=1&controls=0&loop=1&playlist=' . htmlspecialchars($video_id, ENT_QUOTES, 'UTF-8');
                                } elseif ($video_type === 'vimeo' && $video_id) {
                                    $preview_url = 'https://player.vimeo.com/video/' . htmlspecialchars($video_id, ENT_QUOTES, 'UTF-8') . '?autoplay=1&muted=1&background=1';
                                } elseif ($video_type === 'wistia' && $video_id) {
                                    $preview_url = 'https://fast.wistia.net/embed/iframe/' . htmlspecialchars($video_id, ENT_QUOTES, 'UTF-8') . '?autoPlay=true&muted=true';
                                }
                            ?>
                            <tr class="video-row" data-video-id="<?= $video->id ?>">
                                <td class="col-thumbnail">
                                    <div class="table-thumbnail-container">
                                        <a href="#" class="table-video-thumb" 
                                           onclick="return openVideoInModal(event, this)" 
                                           data-video-id="<?= htmlspecialchars($video_id, ENT_QUOTES, 'UTF-8') ?>" 
                                           data-video-type="<?= htmlspecialchars($video_type, ENT_QUOTES, 'UTF-8') ?>" 
                                           data-video-url="<?= htmlspecialchars($video->video_link, ENT_QUOTES, 'UTF-8') ?>" 
                                           data-video-title="<?= htmlspecialchars($video->title, ENT_QUOTES, 'UTF-8') ?>"
                                           <?= $preview_url ? 'data-preview-url="' . htmlspecialchars($preview_url, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                                           <?= $video_type === 'local' ? 'data-video-format="' . htmlspecialchars(pathinfo($video->video_link, PATHINFO_EXTENSION), ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                                            <img src="<?= htmlspecialchars($thumbnail, ENT_QUOTES, 'UTF-8') ?>" 
                                                 alt="<?= htmlspecialchars($video->title, ENT_QUOTES, 'UTF-8') ?>" 
                                                 onerror="this.onerror=null; this.src='https://ions.com/assets/default/processing.png';">
                                            <div class="table-play-overlay">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="rgba(255,255,255,0.9)">
                                                    <path d="M8 5v14l11-7z"></path>
                                                </svg>
                                            </div>
                                            <div class="table-preview-overlay" style="display: none;">
                                                <!-- Preview iframe will be inserted here on hover -->
                                            </div>
                                        </a>
                                    </div>
                                </td>
                                <td class="col-title">
                                    <div class="table-title-content">
                                        <div class="table-video-title"><?= html_entity_decode($video->title, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></div>
                                        <?php if (!empty($video->description)): ?>
                                            <div class="table-video-description">
                                                <?= htmlspecialchars(substr($video->description, 0, 80)) ?><?= strlen($video->description) > 80 ? '...' : '' ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Creator Handle -->
                                        <div class="creator-handle-container" style="display: flex; align-items: center; gap: 8px; margin-top: 6px;">
                                            <?php if (!empty($video->creator_handle)): ?>
                                                <a href="/@<?= htmlspecialchars($video->creator_handle, ENT_QUOTES, 'UTF-8') ?>" 
                                                   style="color: #3b82f6; text-decoration: none; font-size: 0.875rem;"
                                                   onclick="window.open(this.href, '_blank'); return false;"
                                                   title="View <?= htmlspecialchars($video->creator_name ?? $video->creator_handle, ENT_QUOTES, 'UTF-8') ?>'s profile">
                                                    <span>@<?= htmlspecialchars($video->creator_handle, ENT_QUOTES, 'UTF-8') ?></span>
                                                </a>
                                            <?php else: ?>
                                                <span style="color: #64748b; font-size: 0.875rem;">@unknown</span>
                                            <?php endif; ?>
                                            
                                            <?php if (in_array($user_role, ['Owner', 'Admin'])): ?>
                                                <button class="edit-creator-btn" 
                                                        onclick="editVideoCreator(<?= $video->id ?>, '<?= htmlspecialchars($video->creator_handle ?? '', ENT_QUOTES, 'UTF-8') ?>', <?= $video->user_id ?>)" 
                                                        style="background: none; border: none; cursor: pointer; color: #64748b; padding: 2px 4px; display: none; opacity: 0; transition: opacity 0.2s ease;"
                                                        title="Change video creator">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                                    </svg>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?= renderVideoBadges($video->id) ?>
                                    </div>
                                </td>
                                <td class="col-date">
                                    <div class="table-date-time">
                                        <div class="table-date"><?= date('M j, Y', strtotime($video->date_added)) ?></div>
                                        <div class="table-time"><?= date('g:i A', strtotime($video->date_added)) ?></div>
                                    </div>
                                </td>
                                <td class="col-status">
                                    <!-- Status moved to actions column -->
                                </td>
                                <td class="col-actions">
                                    <div class="table-action-buttons">
                                        <?php $can_edit_video = in_array($user_role, ['Owner', 'Admin']) || ($video->user_id == $user_unique_id); ?>
                                        
                                        <!-- Badge Management Dropdown (Table View) -->
                                        <?php if (in_array($user_role, ['Owner', 'Admin'])): 
                                            $video_badges = getVideoBadgeNames($video->id);
                                            $has_badges = !empty($video_badges);
                                        ?>
                                            <div class="badge-dropdown">
                                                <button class="star-badge-button <?= $has_badges ? 'has-badges' : '' ?>" 
                                                        title="Manage badges">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                                        <path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                                    </svg>
                                                </button>
                                                <div class="badge-dropdown-content">
                                                    <div class="badge-dropdown-header">Manage Badges</div>
                                                    
                                                    <div class="badge-option <?= in_array('Featured', $video_badges) ? 'active' : '' ?>" 
                                                         onclick="toggleBadge(<?= $video->id ?>, 'Featured')">
                                                        <span class="badge-icon">🔥</span>
                                                        <div>
                                                            <div class="badge-name">Featured</div>
                                                            <div class="badge-description">Editor-selected best content</div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="badge-option <?= in_array('Favorites', $video_badges) ? 'active' : '' ?>" 
                                                         onclick="toggleBadge(<?= $video->id ?>, 'Favorites')">
                                                        <span class="badge-icon">🌟</span>
                                                        <div>
                                                            <div class="badge-name">Favorites</div>
                                                            <div class="badge-description">Community-loved content</div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="badge-option <?= in_array('Trending', $video_badges) ? 'active' : '' ?>" 
                                                         onclick="toggleBadge(<?= $video->id ?>, 'Trending')">
                                                        <span class="badge-icon">🚀</span>
                                                        <div>
                                                            <div class="badge-name">Trending</div>
                                                            <div class="badge-description">Rapidly gaining engagement</div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="badge-option <?= in_array('Hidden Gem', $video_badges) ? 'active' : '' ?>" 
                                                         onclick="toggleBadge(<?= $video->id ?>, 'Hidden Gem')">
                                                        <span class="badge-icon">💎</span>
                                                        <div>
                                                            <div class="badge-name">Hidden Gem</div>
                                                            <div class="badge-description">Underrated quality content</div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="badge-option <?= in_array('Spotlight', $video_badges) ? 'active' : '' ?>" 
                                                         onclick="toggleBadge(<?= $video->id ?>, 'Spotlight')">
                                                        <span class="badge-icon">📣</span>
                                                        <div>
                                                            <div class="badge-name">Spotlight</div>
                                                            <div class="badge-description">Temporarily promoted</div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="badge-option <?= in_array('Hall of Fame', $video_badges) ? 'active' : '' ?>" 
                                                         onclick="toggleBadge(<?= $video->id ?>, 'Hall of Fame')">
                                                        <span class="badge-icon">🏅</span>
                                                        <div>
                                                            <div class="badge-name">Hall of Fame</div>
                                                            <div class="badge-description">Evergreen classic content</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php elseif (!empty($video_badges)): 
                                            // Show badges for non-admin users (view only)
                                        ?>
                                            <div class="star-badge-button has-badges" title="Video has badges">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                                </svg>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Share Button -->
                                        <?php
                                        if (isset($enhanced_share_manager) && method_exists($enhanced_share_manager, 'renderShareButton')) {
                                            echo $enhanced_share_manager->renderShareButton($video->id, [
                                                'size' => 'small', 
                                                'style' => 'icon', 
                                                'platforms' => ['facebook', 'twitter', 'whatsapp', 'linkedin', 'telegram', 'reddit', 'email', 'copy'], 
                                                'show_embed' => true
                                            ]);
                                        } else {
                                            echo $share_manager->renderShareButton($video->id, [
                                                'size' => 'medium', 
                                                'style' => 'icon', 
                                                'platforms' => ['facebook', 'twitter', 'whatsapp', 'copy'], 
                                                'trigger' => 'click'
                                            ]);
                                        }
                                        ?>
                                        
                                        <!-- Blast Button (Same icon as Cards View) -->
                                        <?php if (in_array($user_role, ['Owner', 'Admin'])): ?>
                                            <button class="blast-icon action-btn hover-label" 
                                                    onclick="openBlastModal('<?= $video->id ?>', '<?= htmlspecialchars($video->title, ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars($thumbnail, ENT_QUOTES, 'UTF-8') ?>')" 
                                                    title="Blast to Channels"
                                                    data-label="Blast"
                                                    style="background: none; border: none; cursor: pointer; padding: 4px 6px; color: #8b5cf6; border-radius: 6px; position: relative;">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.25 4.75l7.5 7.5-7.5 7.5m-6-15l7.5 7.5-7.5 7.5"/>
                                                </svg>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <!-- Edit Button -->
                                        <?php if ($can_edit_video): ?>
                                            <button class="edit-icon action-btn hover-label" 
                                                    onclick="editVideo(<?= $video->id ?>)" 
                                                    title="Edit video details"
                                                    data-label="Edit"
                                                    style="background: none; border: none; cursor: pointer; padding: 4px 6px; color: #4f46e5; border-radius: 6px; position: relative;">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                                </svg>
                                            </button>
                                        
                                            <!-- Delete Button (Last) -->
                                            <button class="delete-icon action-btn hover-label" 
                                                    data-video-id="<?= $video->id ?>" 
                                                    data-video-title="<?= htmlspecialchars($video->title, ENT_QUOTES, 'UTF-8') ?>" 
                                                    title="Delete video"
                                                    data-label="Delete"
                                                    style="background: none; border: none; cursor: pointer; padding: 4px 6px; color: #ef4444; border-radius: 6px; position: relative;">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <!-- Status/Approval Button - Same as Cards View -->
                                        <?php if (in_array($user_role, ['Owner', 'Admin'])): ?>
                                            <div class="status-management">
                                                <span class="status-badge status-<?= str_replace(' ', '-', strtolower($video->status)) ?> clickable" 
                                                      onclick="toggleStatusOptions(<?= $video->id ?>)" 
                                                      id="status-badge-table-<?= $video->id ?>">
                                                    <?= htmlspecialchars($video->status) ?>
                                                    <svg class="status-arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <polyline points="6,9 12,15 18,9"></polyline>
                                                    </svg>
                                                </span>
                                                <div class="status-options-table" id="status-options-table-<?= $video->id ?>">
                                                    <div class="status-options-grid">
                                                        <div class="status-option-inline" data-status="Approved" onclick="changeVideoStatus(<?= $video->id ?>, 'Approved')">✅ Approved</div>
                                                        <div class="status-option-inline" data-status="Pending" onclick="changeVideoStatus(<?= $video->id ?>, 'Pending')">🟡 Pending</div>
                                                        <div class="status-option-inline" data-status="Paused" onclick="changeVideoStatus(<?= $video->id ?>, 'Paused')">⏸️ Paused</div>
                                                        <div class="status-option-inline" data-status="Rejected" onclick="changeVideoStatus(<?= $video->id ?>, 'Rejected')">🔴 Rejected</div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="status-badge status-<?= str_replace(' ', '-', strtolower($video->status)) ?>">
                                                <?= htmlspecialchars($video->status) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination" style="margin-top: 2rem;">
                        <div class="pagination-info">
                            <p>Page <?= $page ?> of <?= $total_pages ?></p>
                        </div>
                        <div class="pagination-controls">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>" class="pagination-btn">← Previous</a>
                            <?php endif; ?>
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            if ($start_page > 1): ?>
                                <a href="?page=1" class="pagination-btn">1</a>
                                <?php if ($start_page > 2): ?>
                                    <span class="pagination-ellipsis">...</span>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <a href="?page=<?= $i ?>" class="pagination-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                            <?php endfor; ?>
                            <?php if ($end_page < $total_pages): ?>
                                <?php if ($end_page < $total_pages - 1): ?>
                                    <span class="pagination-ellipsis">...</span>
                                <?php endif; ?>
                                <a href="?page=<?= $total_pages ?>" class="pagination-btn"><?= $total_pages ?></a>
                            <?php endif; ?>
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?= $page + 1 ?>" class="pagination-btn">Next →</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

<?php
// Video modal will be included at the end of the file for proper rendering
?>

    <!-- Old Uploader Modal Removed - Now using ionuploader.php -->

    <script>
        // Video modal functionality is now handled by includes/video-modal.php

        // View Toggle Functions
        function toggleView(viewType) {
            const cardView = document.getElementById('cardView');
            const listView = document.getElementById('listView');
            const cardBtn = document.getElementById('cardViewBtn');
            const listBtn = document.getElementById('listViewBtn');
            const viewInput = document.getElementById('view-input');
            
            if (viewType === 'list') {
                // Update views
                cardView.style.display = 'none';
                listView.style.display = 'block';
                
                // Update button styles
                cardBtn.classList.remove('active');
                listBtn.classList.add('active');
                cardBtn.style.background = 'transparent';
                cardBtn.style.color = 'var(--text-secondary)';
                listBtn.style.background = 'var(--primary-color)';
                listBtn.style.color = 'white';
                
                // Update form input
                if (viewInput) viewInput.value = 'list';
                
                localStorage.setItem('videoView', 'list');
                initializeTableHoverPreviews();
            } else {
                // Update views
                cardView.style.display = 'grid';
                listView.style.display = 'none';
                
                // Update button styles
                cardBtn.classList.add('active');
                listBtn.classList.remove('active');
                cardBtn.style.background = 'var(--primary-color)';
                cardBtn.style.color = 'white';
                listBtn.style.background = 'transparent';
                listBtn.style.color = 'var(--text-secondary)';
                
                // Update form input
                if (viewInput) viewInput.value = 'grid';
                
                localStorage.setItem('videoView', 'card');
            }
        }
        
        // Initialize table hover previews for list view (disabled to ensure modal opens)
        function initializeTableHoverPreviews() {
            // Intentionally no-op to keep List behavior identical to Cards (modal only)
        }
        
        // Creator Search removed - now using simple dropdown
        
        // Edit Video Creator
        function editVideoCreator(videoId, currentHandle, currentUserId) {
            const newHandle = prompt(`Change video creator\n\nCurrent: @${currentHandle || 'unknown'}\n\nEnter new creator handle (without @):`);
            
            if (!newHandle || newHandle.trim() === '') {
                return; // User cancelled
            }
            
            const handle = newHandle.trim().replace('@', '');
            
            // Confirm the change
            if (!confirm(`Reassign this video to @${handle}?`)) {
                return;
            }
            
            // Make AJAX request to update
            fetch('creators.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=change_video_creator&video_id=${videoId}&new_handle=${encodeURIComponent(handle)}`
            })
            .then(response => response.json())
            .then(data => {
                console.log('Creator update response:', data);
                if (data.success) {
                    console.log('✅ Video reassigned:', {
                        from_user: data.old_user_id,
                        to_user: data.new_user_id,
                        new_handle: data.creator_handle,
                        new_name: data.creator_name
                    });
                    alert(`Video successfully reassigned to @${data.creator_handle}!`);
                    location.reload(); // Reload to show updated creator
                } else {
                    console.error('❌ Update failed:', data.error);
                    alert('Error: ' + (data.error || 'Failed to update creator'));
                }
            })
            .catch(error => {
                console.error('❌ Network/Parse error:', error);
                alert('An error occurred while updating the creator');
            });
        }
        
        // Restore saved view on page load
        document.addEventListener('DOMContentLoaded', function() {
            const savedView = localStorage.getItem('videoView') || 'card';
            toggleView(savedView);
            
            // Initialize hover previews for all videos
            initializeLazyPreviews();
            
            // Disable hover/lazy previews to avoid intercepting clicks; modal-only
            
            // Initialize table hover previews if in list view
            if (savedView === 'list') {
                initializeTableHoverPreviews();
            }
            
            // Creator search simplified to dropdown - no initialization needed
            
            // Add global error handler for better debugging
            window.addEventListener('error', function(e) {
                console.error('Global error caught:', e.error);
            });
            
            // Validate that key functions are available
            const functionsToCheck = ['editVideo', 'deleteVideo', 'openIONVideoUploader'];
            const missingFunctions = functionsToCheck.filter(fn => typeof window[fn] !== 'function');
            
            if (missingFunctions.length > 0) {
                console.warn('⚠️ Missing functions:', missingFunctions);
            } else {
                console.log('✅ Creators page loaded successfully - all functionality restored');
                console.log('✅ Available functions: Edit, Delete, Upload, Preview loading');
            }
        });
        
        // Lazy loading for video previews to avoid YouTube preload warnings
        function initializeLazyPreviews() {
            // Get both card view and list view thumbnails
            const videoThumbs = document.querySelectorAll('.video-thumb, .table-video-thumb');
            
            // Use Intersection Observer to only initialize previews for visible videos
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        initializePreviewForThumb(entry.target);
                        observer.unobserve(entry.target); // Stop observing once initialized
                    }
                });
            }, {
                rootMargin: '50px' // Start loading when video is 50px away from viewport
            });
            
            videoThumbs.forEach(thumb => {
                observer.observe(thumb);
            });
        }
        
        // Initialize preview functionality for a single video thumbnail
        function initializePreviewForThumb(thumb) {
            // Check for both card view (.preview-iframe-container) and list view (.table-preview-overlay)
            let previewContainer = thumb.querySelector('.preview-iframe-container');
            let isTableView = false;
            
            if (!previewContainer) {
                previewContainer = thumb.querySelector('.table-preview-overlay');
                isTableView = true;
            }
            
            // If no preview container exists, check if we have a preview URL and create one
            if (!previewContainer) {
                const previewUrl = thumb.getAttribute('data-preview-url') || thumb.dataset.previewUrl;
                if (!previewUrl) return; // No preview available
                
                // Create preview container dynamically
                previewContainer = document.createElement('div');
                previewContainer.className = 'dynamic-preview-container';
                previewContainer.setAttribute('data-preview-url', previewUrl);
                previewContainer.style.cssText = 'position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; pointer-events: none; transition: opacity 0.3s ease; z-index: 2;';
                thumb.style.position = 'relative';
                thumb.appendChild(previewContainer);
            }
            
            let previewLoaded = false;
            let hoverTimeout = null;
            
            // Load preview on hover (with slight delay to avoid loading on quick mouse-overs)
            thumb.addEventListener('mouseenter', function() {
                hoverTimeout = setTimeout(() => {
                    if (!previewLoaded) {
                        loadPreviewIframe(previewContainer);
                        previewLoaded = true;
                    }
                    // Show the preview
                    previewContainer.style.display = 'block';
                    previewContainer.style.opacity = '1';
                }, 300); // 300ms delay to avoid loading on quick mouse-overs
            });
            
            // Hide preview on mouse leave
            thumb.addEventListener('mouseleave', function() {
                if (hoverTimeout) {
                    clearTimeout(hoverTimeout);
                    hoverTimeout = null;
                }
                if (previewContainer) {
                    previewContainer.style.opacity = '0';
                }
            });
        }
        
        // Function to load preview iframe on demand
        function loadPreviewIframe(container) {
            const previewUrl = container.getAttribute('data-preview-url');
            if (!previewUrl || container.querySelector('iframe, video')) return;
            
            console.log('🎥 Loading preview on demand:', previewUrl);
            
            // Check if this is a local video (starts with "local:")
            if (previewUrl.startsWith('local:')) {
                const videoUrl = previewUrl.substring(6); // Remove "local:" prefix
                console.log('📹 Creating local video preview for:', videoUrl);
                
                const video = document.createElement('video');
                video.src = videoUrl;
                video.muted = true;
                video.autoplay = true;
                video.loop = true;
                video.playsInline = true;
                video.style.cssText = 'width: 100%; height: 100%; object-fit: cover; border-radius: inherit;';
                
                // Attempt to play
                video.play().catch(err => {
                    console.log('⚠️ Local video autoplay failed:', err);
                });
                
                container.appendChild(video);
            } else {
                // Standard iframe for other platforms
                console.log('🖼️ Creating iframe preview for:', previewUrl);
                
                const iframe = document.createElement('iframe');
                iframe.src = previewUrl;
                iframe.frameBorder = '0';
                iframe.allow = 'autoplay; encrypted-media';
                iframe.allowFullscreen = true;
                iframe.loading = 'lazy';
                iframe.style.cssText = 'width: 100%; height: 100%; border: none; border-radius: inherit;';
                
                container.appendChild(iframe);
            }
        }

        // Delete video function (simplified to match creators-old.php)
        async function deleteVideo(videoId, title) {
            // Validate video ID
            if (!videoId || videoId == 0 || videoId === '0') {
                alert('❌ Cannot delete this video\n\nThis video has an invalid ID (likely imported incorrectly).\n\n✅ Solution: Try refreshing the page and delete it again, or contact support if the issue persists.');
                return;
            }
            
            if (!confirm(`Are you sure you want to delete "${title}"? This action cannot be undone.`)) {
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'delete_video');
                formData.append('video_id', videoId);
                
                const response = await fetch('creators.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Video deleted successfully');
                    location.reload();
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (error) {
                console.error('Delete error:', error);
                alert('An error occurred while deleting the video');
            }
        }

        // Update video count after deletion
        function updateVideoCount() {
            const videoCards = document.querySelectorAll('.carousel-item, .video-list-item');
            const totalElement = document.querySelector('.videos-header h2');
            if (totalElement) {
                totalElement.textContent = totalElement.textContent.replace(/\(\d+\)/, `(${videoCards.length})`);
            }
        }

        // Add event listeners for delete buttons using data attributes
        document.addEventListener('click', function(e) {
            if (e.target.closest('.delete-icon')) {
                e.preventDefault();
                e.stopPropagation();
                
                const button = e.target.closest('.delete-icon');
                const videoId = button.getAttribute('data-video-id');
                const videoTitle = button.getAttribute('data-video-title');
                const videoInfo = button.getAttribute('data-video-info');
                
                console.log('🗑️ Delete clicked - Video Info:', videoInfo, 'ID:', videoId, 'Title:', videoTitle);
                
                if (videoId && videoTitle) {
                    deleteVideo(videoId, videoTitle);
                }
            }
        });

        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('show');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
            // Reset forms
            if (modalId === 'uploadModal') {
                document.getElementById('uploadForm').reset();
            document.getElementById('uploadLoading').style.display = 'none';
            } else if (modalId === 'importModal') {
                document.getElementById('importForm').reset();
                document.getElementById('importLoading').style.display = 'none';
            }
        }
        
        // Video modal functionality (matching creators-old.php)
        function openVideoInModal(event, element) {
            event.preventDefault();
            event.stopPropagation();
            
            const videoType = element.getAttribute("data-video-type");
            const videoId = element.getAttribute("data-video-id");
            const videoUrl = element.getAttribute("data-video-url");
            const videoTitle = element.getAttribute("data-video-title");
            let videoFormat = element.getAttribute("data-video-format");
            
            // Handle self-hosted/local videos
            const isLocalVideo = videoType === 'local' || videoType === 'upload' || videoType === 'self-hosted';
            const effectiveVideoType = isLocalVideo ? 'local' : videoType;
            
            // Extract format from URL if not provided for local videos
            if (isLocalVideo && !videoFormat && videoUrl) {
                const urlParts = videoUrl.split('.');
                videoFormat = urlParts[urlParts.length - 1].toLowerCase();
            }
            
            console.log('Opening video modal:', {
                type: effectiveVideoType,
                id: videoId,
                url: videoUrl,
                format: videoFormat,
                title: videoTitle
            });
            
            // Use the global modal function from video-modal.php (match working ions/app version)
            if (typeof openVideoModal === 'function') {
                console.log('🎬 Using openVideoModal function');
                openVideoModal(effectiveVideoType, videoId, videoUrl, videoFormat);
            } else if (window.VideoModal && typeof window.VideoModal.open === 'function') {
                console.log('🎬 Using VideoModal.open function');
                window.VideoModal.open(effectiveVideoType, videoId, videoUrl, videoFormat);
            } else {
                console.error('Video modal not available, falling back to new tab');
                console.error('Available functions:', {
                    VideoModal: typeof window.VideoModal,
                    openVideoModal: typeof openVideoModal
                });
                window.open(videoUrl, '_blank');
            }
            
            return false;
        }

        // Close modals when clicking outside
        window.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                closeModal(e.target.id);
            }
        });
        
    </script>
    
    <!-- Additional CSS for preview optimization -->
    <style>
    .video-thumb {
        position: relative;
        overflow: hidden;
    }
    
    .preview-iframe-container {
        z-index: 2;
    }
    
    .play-icon-overlay {
        z-index: 3;
        pointer-events: none;
    }
    
    /* Hide preview on mobile to save bandwidth */
    @media (max-width: 768px) {
        .preview-iframe-container {
            display: none !important;
        }
    }
    </style>

    <!-- Uploader Modal JavaScript -->
    <script>
        // Function to open the new ION Video Uploader in optimized iframe
        function openIONVideoUploader() {
            // Create modal overlay with proper sizing
            const modalOverlay = document.createElement('div');
            modalOverlay.id = 'ionVideoUploaderModal';
            modalOverlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
                backdrop-filter: blur(8px);
                z-index: 99999;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
                overflow: hidden;
            `;
            
            // Create iframe with exact dimensions
            const iframe = document.createElement('iframe');
            // Get the current directory path and construct the uploader URL
            const currentPath = window.location.pathname;
            const basePath = currentPath.substring(0, currentPath.lastIndexOf('/'));
            iframe.src = basePath + '/ionuploader.php?v=' + Date.now();
            iframe.style.cssText = `
                width: 1200px;
                max-width: 95vw;
                height: 90vh;
                max-height: 900px;
                border: none;
                border-radius: 16px;
                background: #0f172a;
                overflow: hidden;
            `;
            iframe.allow = 'camera; microphone; fullscreen';
            
            modalOverlay.appendChild(iframe);
            document.body.appendChild(modalOverlay);
            
            // Disable page scrolling when modal is open
            document.body.style.overflow = 'hidden';
            
            // Close functionality
            const closeModal = () => {
                // Restore page scrolling
                document.body.style.overflow = '';
                modalOverlay.remove();
                location.reload(); // Refresh the video list
            };
            
            // Close on overlay click
            modalOverlay.addEventListener('click', (e) => {
                if (e.target === modalOverlay) {
                    closeModal();
                }
            });
            
            // Close on escape key
            const escapeHandler = (e) => {
                if (e.key === 'Escape') {
                    closeModal();
                    document.removeEventListener('keydown', escapeHandler);
                }
            };
            document.addEventListener('keydown', escapeHandler);
            
            // Listen for completion message from iframe
            const messageHandler = (e) => {
                if (e.origin !== window.location.origin) return;
                
                // Handle both string and object message formats
                const messageType = typeof e.data === 'string' ? e.data : e.data?.type;
                
                if (messageType === 'upload_complete' || messageType === 'close_modal' || messageType === 'video_deleted') {
                    closeModal();
                    window.removeEventListener('message', messageHandler);
                }
            };
            window.addEventListener('message', messageHandler);
        }
        
        function closeIONVideoUploaderModal() {
            const modal = document.getElementById('ionVideoUploaderModal');
            if (modal) {
                modal.remove();
                location.reload(); // Refresh the video list
            }
        }
        
        // Function to open ION Local Blast with pre-selected video (cards)
        function openBlastDialog(videoId, videoTitle, videoThumbnail) {
            console.log('🚀 Opening Blast dialog for video:', videoId, videoTitle);
            
            // Create modal overlay
            const modalOverlay = document.createElement('div');
            modalOverlay.id = 'ionBlastModal';
            modalOverlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
                backdrop-filter: blur(8px);
                z-index: 99999;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
                overflow: hidden;
            `;
            
            // Create iframe for ION Local Blast
            const iframe = document.createElement('iframe');
            // Pass video data as URL parameters
            const params = new URLSearchParams({
                preselected_video_id: videoId,
                preselected_video_title: videoTitle,
                preselected_video_thumbnail: videoThumbnail
            });
            iframe.src = 'ionlocalblast.php?' + params.toString();
            iframe.style.cssText = `
                width: 99vw;
                max-width: 2000px;
                height: 95vh;
                max-height: 1000px;
                border: none;
                border-radius: 16px;
                background: #0f172a;
                overflow: auto;
            `;
            iframe.allow = 'camera; microphone; fullscreen';
            
            modalOverlay.appendChild(iframe);
            document.body.appendChild(modalOverlay);
            
            // Disable page scrolling when modal is open
            document.body.style.overflow = 'hidden';
            
            // Close functionality
            const closeModal = () => {
                document.body.style.overflow = '';
                modalOverlay.remove();
            };
            
            // Close on overlay click
            modalOverlay.addEventListener('click', (e) => {
                if (e.target === modalOverlay) {
                    closeModal();
                }
            });
            
            // Close on escape key
            const escapeHandler = (e) => {
                if (e.key === 'Escape') {
                    closeModal();
                    document.removeEventListener('keydown', escapeHandler);
                }
            };
            document.addEventListener('keydown', escapeHandler);
            
            // Listen for completion/close message from iframe
            const messageHandler = (e) => {
                if (e.origin !== window.location.origin) return;
                
                const messageType = typeof e.data === 'string' ? e.data : e.data?.type;
                
                if (messageType === 'blast_complete' || messageType === 'close_blast_modal') {
                    closeModal();
                    window.removeEventListener('message', messageHandler);
                }
            };
            window.addEventListener('message', messageHandler);
        }

        // Alias used by List view buttons so both views share the same behavior
        function openBlastModal(videoId, videoTitle, videoThumbnail) {
            return openBlastDialog(videoId, videoTitle, videoThumbnail);
        }

        // Legacy function redirects (for compatibility)
        function openImportModal() {
            openIONVideoUploader();
        }

        function openUploadModal() {
            openIONVideoUploader();
        }

        // Status Management Functions
        let activeDropdown = null;

        function toggleStatusOptions(videoId) {
            console.log('📊 toggleStatusOptions called for video ID:', videoId);
            
            const listViewEl = document.getElementById('listView');
            const isListActive = listViewEl && getComputedStyle(listViewEl).display !== 'none';
            
            // Prefer elements from the currently visible view to avoid toggling hidden ones
            let options = null;
            let badge = null;
            if (isListActive) {
                options = document.getElementById('status-options-table-' + videoId) ||
                          document.getElementById('status-options-list-' + videoId) ||
                          document.getElementById('status-options-' + videoId);
                badge = document.getElementById('status-badge-table-' + videoId) ||
                        document.getElementById('status-badge-list-' + videoId) ||
                        document.getElementById('status-badge-' + videoId);
            } else {
                options = document.getElementById('status-options-' + videoId) ||
                          document.getElementById('status-options-list-' + videoId) ||
                          document.getElementById('status-options-table-' + videoId);
                badge = document.getElementById('status-badge-' + videoId) ||
                        document.getElementById('status-badge-list-' + videoId) ||
                        document.getElementById('status-badge-table-' + videoId);
            }
            
            console.log('📊 Options element found:', !!options, options?.id);
            console.log('📊 Badge element found:', !!badge, badge?.id);
            
            // Safety: if in list view and options not found yet, try nearest dropdown under the same row
            if (!options && badge) {
                const row = badge.closest('tr');
                const fallback = row ? row.querySelector('.status-options-table') : null;
                if (fallback) {
                    options = fallback;
                }
            }

            // Close any other open options and reset any elevated z-index
            if (activeDropdown && activeDropdown !== options) {
                activeDropdown.classList.remove('show');
                const activeBadge = activeDropdown.parentElement.querySelector('.status-badge');
                if (activeBadge) activeBadge.classList.remove('open');
                try {
                    const activeRow = activeDropdown.closest('tr');
                    if (activeRow) activeRow.style.zIndex = '';
                } catch (e) {}
            }
            
            // Toggle current options
            if (options && options.classList.contains('show')) {
                options.classList.remove('show');
                badge.classList.remove('open');
                activeDropdown = null;
                try { const row = badge ? badge.closest('tr') : null; if (row) row.style.zIndex = ''; } catch (e) {}
            } else {
                if (options) {
                    options.classList.add('show');
                    if (badge) badge.classList.add('open');
                    activeDropdown = options;
                    // Elevate this row and dropdown above siblings but below global modals
                    try {
                        const row = badge ? badge.closest('tr') : null;
                        if (row) row.style.zIndex = '3000';
                        options.style.zIndex = '4000';
                    } catch (e) {}
                }
                // Ensure dropdown positions correctly relative to the badge in table view
                try {
                    if (badge && options) {
                        const rect = badge.getBoundingClientRect();
                        options.style.minWidth = Math.max(140, rect.width) + 'px';
                    }
                } catch (e) {}
            }
        }

        function changeVideoStatus(videoId, newStatus) {
            const listViewEl = document.getElementById('listView');
            const isListActive = listViewEl && getComputedStyle(listViewEl).display !== 'none';
            
            let badge = null;
            let options = null;
            if (isListActive) {
                badge = document.getElementById('status-badge-table-' + videoId) ||
                        document.getElementById('status-badge-list-' + videoId) ||
                        document.getElementById('status-badge-' + videoId);
                options = document.getElementById('status-options-table-' + videoId) || 
                          document.getElementById('status-options-list-' + videoId) ||
                          document.getElementById('status-options-' + videoId);
            } else {
                badge = document.getElementById('status-badge-' + videoId) || 
                        document.getElementById('status-badge-list-' + videoId) ||
                        document.getElementById('status-badge-table-' + videoId);
                options = document.getElementById('status-options-' + videoId) || 
                          document.getElementById('status-options-list-' + videoId) ||
                          document.getElementById('status-options-table-' + videoId);
            }
            
            if (!badge) {
                console.error('DEBUG: Badge not found for video ID:', videoId);
                alert('Error: Could not find video status badge');
                return;
            }
            
            if (!options) {
                console.error('DEBUG: Options not found for video ID:', videoId);
                alert('Error: Could not find video status options');
                return;
            }
            
            // Close options first
            options.classList.remove('show');
            badge.classList.remove('open');
            activeDropdown = null;
            
            // Add loading state
            badge.classList.add('loading');
            
            // Prepare form data
            const formData = new FormData();
            formData.append('video_id', videoId);
            formData.append('status', newStatus);
            
            // Debug logging
            console.log('DEBUG: Changing video status for ID:', videoId, 'to status:', newStatus);
            console.log('DEBUG: FormData contents:', {
                video_id: formData.get('video_id'),
                status: formData.get('status')
            });
            
            // Send AJAX request
            fetch('/api/update-video-status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                badge.classList.remove('loading');
                
                if (data.success) {
                    // Update badge appearance
                    badge.className = 'status-badge clickable ' + data.status_class;
                    badge.innerHTML = newStatus + 
                        '<svg class="status-arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
                        '<polyline points="6,9 12,15 18,9"></polyline>' +
                        '</svg>';
                    
                    // Show success notification
                    showStatusNotification('✅ Status updated to ' + newStatus, 'success');
                } else {
                    console.error('DEBUG: Server returned error:', data.error);
                    showStatusNotification('❌ Error: ' + data.error, 'error');
                }
            })
            .catch(error => {
                badge.classList.remove('loading');
                console.error('DEBUG: Network/parsing error:', error);
                showStatusNotification('❌ Network error: ' + error.message, 'error');
            });
        }

        function showStatusNotification(message, type) {
            // Remove any existing notification
            const existing = document.querySelector('.status-notification');
            if (existing) existing.remove();
            
            // Create notification
            const notification = document.createElement('div');
            notification.className = `status-notification ${type}`;
            notification.innerHTML = message;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${type === 'success' ? '#22c55e' : '#ef4444'};
                color: white;
                padding: 12px 20px;
                border-radius: 8px;
                font-weight: 500;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
                z-index: 10001;
                animation: slideInRight 0.3s ease-out;
            `;
            
            document.body.appendChild(notification);
            
            // Auto-remove after 3 seconds
            setTimeout(() => {
                notification.style.animation = 'slideOutRight 0.3s ease-in';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Close options when clicking outside
        document.addEventListener('click', function(e) {
            if (activeDropdown && !e.target.closest('.status-management')) {
                activeDropdown.classList.remove('show');
                const activeBadge = activeDropdown.parentElement.querySelector('.status-badge');
                if (activeBadge) activeBadge.classList.remove('open');
                activeDropdown = null;
            }
        });

        // Add notification animations
        if (!document.querySelector('#statusNotificationStyles')) {
            const style = document.createElement('style');
            style.id = 'statusNotificationStyles';
            style.textContent = `
/* Enhanced Header Styles */
.videos-header {
    width: 100% !important;
    box-sizing: border-box;
}

.toolbar-container {
    display: flex !important;
    align-items: center !important;
    width: 100% !important;
    min-height: 60px;
}

.toolbar-left {
    flex: 0 0 auto !important;
    min-width: 200px;
}

.toolbar-center {
    flex: 1 !important;
    display: flex !important;
    justify-content: center !important;
    padding: 0 2rem;
}

.toolbar-right {
    flex: 0 0 auto !important;
}

.filter-select {
    background: white !important;
    color: #333 !important;
    border: 1px solid #ddd !important;
    position: relative !important;
    z-index: 10000 !important;
}

.filter-select:hover {
    border-color: var(--primary-color) !important;
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.1) !important;
}

.filter-select option {
    background: white !important;
    color: #333 !important;
}

/* Creator search removed - using simple dropdown */

.view-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.view-btn.active {
    box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3);
}

/* Tablet Responsive */
@media (max-width: 1200px) {
    .toolbar-center {
        padding: 0 1rem !important;
    }
    
    .filters-container {
        gap: 0.4rem !important;
    }
    
    .filter-select {
        min-width: 90px !important;
        font-size: 0.8rem !important;
    }
}

@media (max-width: 1024px) {
    .toolbar-left {
        min-width: 180px !important;
    }
    
    .toolbar-center {
        padding: 0 0.5rem !important;
    }
    
    .filters-container {
        flex-wrap: wrap !important;
        gap: 0.1rem !important;
    }
    
    .filter-select {
        min-width: 85px !important;
        font-size: 0.75rem !important;
        padding: 0.35rem 0.5rem !important;
    }
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .videos-header {
        padding: 0.20rem 0.5rem !important;
    }
    
    .toolbar-container {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 0.75rem !important;
        min-height: auto !important;
    }
    
    .toolbar-left {
        min-width: auto !important;
        text-align: center;
        order: 1;
    }
    
    .toolbar-right {
        order: 2;
        display: flex !important;
        justify-content: center !important;
    }
    
    .toolbar-center {
        order: 3;
        padding: 0 !important;
    }
    
    .filters-container {
        justify-content: center !important;
        gap: 0.4rem !important;
        flex-wrap: wrap !important;
    }
    
    .filter-select {
        min-width: 80px !important;
        font-size: 0.7rem !important;
        padding: 0.3rem 0.4rem !important;
        flex: 1 1 auto;
        max-width: 120px;
    }
    
    /* Creator search container removed */
    
    .toolbar-left h2 {
        font-size: 1rem !important;
        white-space: normal !important;
    }
    
    .toolbar-left p {
        font-size: 0.75rem !important;
        white-space: normal !important;
    }
}

/* Small Mobile */
@media (max-width: 480px) {
    .videos-header {
        padding: 0.20rem 0.5rem !important;
    }
    
    .filters-container {
        gap: 0.3rem !important;
    }
    
    .filter-select {
        min-width: 70px !important;
        font-size: 0.65rem !important;
        padding: 0.25rem 0.3rem !important;
        max-width: 100px;
    }
    
    .view-btn {
        padding: 0.3rem 0.4rem !important;
        font-size: 0.7rem !important;
        gap: 0.2rem !important;
    }
    
    .view-btn svg {
        width: 12px !important;
        height: 12px !important;
    }
}
                
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
            `;
            document.head.appendChild(style);
        }

        // Counter click functions
        function filterByStatus(status) {
            console.log('Filtering by status:', status);
            const url = new URL(window.location);
            
            // Clear existing filters and reset page
            url.searchParams.delete('status');
            url.searchParams.delete('uploader');
            url.searchParams.delete('page');
            
            // Set new status filter (use 'status' to match PHP $_GET['status'])
            if (status) {
                url.searchParams.set('status', status);
            }
            
            // Preserve current search term if any
            const currentSearch = url.searchParams.get('q') || url.searchParams.get('search');
            if (currentSearch) {
                url.searchParams.set('q', currentSearch);
            }
            
            // Preserve current category filter if any
            const currentCategory = url.searchParams.get('category');
            if (currentCategory) {
                url.searchParams.set('category', currentCategory);
            }
            
            // Preserve current uploader filter if any
            const currentUploader = url.searchParams.get('uploader');
            if (currentUploader) {
                url.searchParams.set('uploader', currentUploader);
            }
            
            // Preserve current view mode
            const currentView = localStorage.getItem('videoView') || 'card';
            url.searchParams.set('view', currentView === 'list' ? 'list' : 'grid');
            
            window.location.href = url.toString();
        }

        function filterByCreators() {
            console.log('Filtering by uploaders');
            const url = new URL(window.location);
            
            // Clear existing filters and reset page
            url.searchParams.delete('status');
            url.searchParams.delete('uploader');
            url.searchParams.delete('page');
            
            // Set uploader filter to current user (use 'uploader' to match PHP $_GET['uploader'])
            url.searchParams.set('uploader', '<?= $user_unique_id ?>');
            
            // Preserve current search term if any
            const currentSearch = url.searchParams.get('q') || url.searchParams.get('search');
            if (currentSearch) {
                url.searchParams.set('q', currentSearch);
            }
            
            // Preserve current category filter if any
            const currentCategory = url.searchParams.get('category');
            if (currentCategory) {
                url.searchParams.set('category', currentCategory);
            }
            
            // Preserve current uploader filter if any
            const currentUploader = url.searchParams.get('uploader');
            if (currentUploader) {
                url.searchParams.set('uploader', currentUploader);
            }
            
            // Preserve current view mode
            const currentView = localStorage.getItem('videoView') || 'card';
            url.searchParams.set('view', currentView === 'list' ? 'list' : 'grid');
            
            window.location.href = url.toString();
        }

        // Badge Management Helper Functions
        function hasBadge(video, badge) {
            if (!video.badges) return false;
            return video.badges.split('|').includes(badge);
        }
        
        // Smart positioning for badge dropdowns
        function adjustDropdownPosition() {
            const dropdowns = document.querySelectorAll('.badge-dropdown');
            dropdowns.forEach(dropdown => {
                const rect = dropdown.getBoundingClientRect();
                const windowHeight = window.innerHeight;
                const spaceBelow = windowHeight - rect.bottom;
                const spaceAbove = rect.top;
                const isInTable = dropdown.closest('.table-action-buttons');
                
                // For table dropdowns, use fixed positioning and calculate absolute position
                if (isInTable) {
                    const dropdownContent = dropdown.querySelector('.badge-dropdown-content');
                    if (dropdownContent) {
                        // Position the dropdown relative to the button's screen position
                        const buttonRect = dropdown.getBoundingClientRect();
                        
                        if (spaceBelow < 300 && spaceAbove > 300) {
                            // Position above
                            dropdownContent.style.top = (buttonRect.top - 250) + 'px'; // Approximate dropdown height
                            dropdown.classList.add('position-above');
                        } else {
                            // Position below
                            dropdownContent.style.top = (buttonRect.bottom + 8) + 'px';
                            dropdown.classList.remove('position-above');
                        }
                        
                        // Center horizontally
                        dropdownContent.style.left = (buttonRect.left + buttonRect.width / 2 - 100) + 'px'; // Approximate half dropdown width
                    }
                } else {
                    // Regular positioning for card view dropdowns
                    if (spaceBelow < 300 && spaceAbove > 300) {
                        dropdown.classList.add('position-above');
                    } else {
                        dropdown.classList.remove('position-above');
                    }
                }
            });
        }
        
        // Adjust dropdown positions on scroll and resize
        window.addEventListener('scroll', adjustDropdownPosition);
        window.addEventListener('resize', adjustDropdownPosition);
        
        // Initial positioning check
        document.addEventListener('DOMContentLoaded', () => {
            adjustDropdownPosition();
            
            // Add hover listeners to adjust position just before showing
            const dropdowns = document.querySelectorAll('.badge-dropdown');
            dropdowns.forEach(dropdown => {
                dropdown.addEventListener('mouseenter', () => {
                    adjustDropdownPosition();
                    
                    // Add dropdown-active class to parent video card for z-index elevation
                    const videoCard = dropdown.closest('.video-card, .videos-table tbody tr');
                    if (videoCard) {
                        videoCard.classList.add('dropdown-active');
                    }
                });
                
                dropdown.addEventListener('mouseleave', () => {
                    // Remove dropdown-active class from parent video card
                    const videoCard = dropdown.closest('.video-card, .videos-table tbody tr');
                    if (videoCard) {
                        videoCard.classList.remove('dropdown-active');
                    }
                });
            });
        });
        
        function getBadges(video) {
            if (!video.badges) return [];
            return video.badges.split('|').filter(b => b.length > 0);
        }
        
        function addBadgeToString(badgesString, badge) {
            const badges = badgesString ? badgesString.split('|').filter(b => b.length > 0) : [];
            if (!badges.includes(badge)) {
                badges.push(badge);
            }
            return badges.join('|');
        }
        
        function removeBadgeFromString(badgesString, badge) {
            if (!badgesString) return '';
            const badges = badgesString.split('|').filter(b => b.length > 0 && b !== badge);
            return badges.join('|');
        }

        // Toggle any badge function (replaces old toggleFeatured)
        async function toggleBadge(videoId, badgeType) {
            try {
                console.log(`🌟 Toggling ${badgeType} badge for video:`, videoId);
                
                // Make API call to toggle badge in database
                
                // Create form data for API call
                const formData = new FormData();
                formData.append('action', 'toggle_badge');
                formData.append('video_id', videoId);
                formData.append('badge', badgeType);
                
                const response = await fetch('/iblog/api/manage-badges.php', {
                    method: 'POST',
                    body: formData
                });
                
                // Log response for debugging
                console.log('API Response status:', response.status);
                console.log('API Response headers:', response.headers);
                
                // Get response text first to see what we're actually getting
                const responseText = await response.text();
                console.log('API Response text:', responseText);
                
                // Try to parse as JSON
                let result;
                try {
                    result = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('JSON Parse Error:', parseError);
                    console.error('Response was:', responseText);
                    throw new Error('API returned invalid JSON. Check console for details.');
                }
                
                if (!result.success) {
                    throw new Error(result.error || 'Failed to update badge');
                }
                
                // Update UI based on API response
                const isActive = result.action_taken === 'added';
                
                // Update dropdown options for this badge
                const dropdowns = document.querySelectorAll(`[onclick*="toggleBadge(${videoId}, '${badgeType}')"]`);
                dropdowns.forEach(option => {
                    if (isActive) {
                        option.classList.add('active');
                    } else {
                        option.classList.remove('active');
                    }
                });
                
                // Update star button states based on whether video has any badges
                const starButtons = document.querySelectorAll(`.star-badge-button`);
                starButtons.forEach(btn => {
                    const container = btn.closest('.video-actions, .table-action-buttons');
                    if (container && container.innerHTML.includes(`toggleBadge(${videoId},`)) {
                        if (result.badges && result.badges.length > 0) {
                            btn.classList.add('has-badges');
                        } else {
                            btn.classList.remove('has-badges');
                        }
                    }
                });
                
                console.log(`✅ Video ${videoId} ${badgeType} badge ${result.action_taken}`);
                
                // Show notification and refresh badges
                const message = isActive ? `${badgeType} badge added!` : `${badgeType} badge removed!`;
                showNotification(message, 'success');
                
                // Refresh page to show updated badge display
                setTimeout(() => location.reload(), 1500);
                
            } catch (error) {
                console.error(`Error toggling ${badgeType} badge:`, error);
                showNotification(`Error updating ${badgeType} badge: ` + error.message, 'error');
            }
        }
        
        // Helper function to show notifications
        function showNotification(message, type = 'info') {
            // Create a simple notification
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 12px 20px;
                background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
                color: white;
                border-radius: 8px;
                font-size: 14px;
                font-weight: 500;
                z-index: 10000;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
                transition: all 0.3s ease;
                transform: translateX(400px);
            `;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            // Animate in
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 10);
            
            // Remove after 3 seconds
            setTimeout(() => {
                notification.style.transform = 'translateX(400px)';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, 3000);
        }

        // Edit Video Function (simplified to match creators-old.php)
        async function editVideo(videoId) {
            try {
                console.log('🎬 EDIT: Starting edit for video ID:', videoId);
                
                // Validate video ID
                if (!videoId || videoId == 0 || videoId === '0') {
                    alert('❌ Cannot edit this video\n\nThis video has an invalid ID (likely imported incorrectly).\n\n✅ Solution: Delete this video and re-import it.');
                    return;
                }
                
                // Fetch video data
                const url = `/iblog/app/get-video-data.php?id=${videoId}`;
                console.log('🎬 EDIT: Fetching from URL:', url);
                
                const response = await fetch(url);
                console.log('🎬 EDIT: Response status:', response.status, response.statusText);
                console.log('🎬 EDIT: Response headers:', [...response.headers.entries()]);
                
                const responseText = await response.text();
                console.log('🎬 EDIT: Raw response text:', responseText);
                
                let videoData;
                try {
                    videoData = JSON.parse(responseText);
                    console.log('🎬 EDIT: Parsed JSON:', videoData);
                } catch (parseError) {
                    console.error('🎬 EDIT: JSON parse error:', parseError);
                    console.error('🎬 EDIT: Response was not valid JSON:', responseText);
                    alert('Server returned invalid response: ' + responseText.substring(0, 200));
                    return;
                }
                
                if (!videoData.success) {
                    console.error('🎬 EDIT: Server error:', videoData.error);
                    alert('Error loading video data: ' + videoData.error);
                    return;
                }
                
                console.log('🎬 EDIT: Success! Opening uploader with data:', videoData.video);
                
                // Open ionuploader in edit mode with video data
                openIONVideoUploaderEdit(videoData.video);
                
            } catch (error) {
                console.error('🎬 EDIT: Fetch error:', error);
                alert('Network error loading video data: ' + error.message);
            }
        }

        // Function to open ION Video Uploader in edit mode with FIXED PATHS
        function openIONVideoUploaderEdit(videoData) {
            // Create modal overlay with proper sizing
            const modalOverlay = document.createElement('div');
            modalOverlay.id = 'ionVideoUploaderModal';
            modalOverlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.85);
                backdrop-filter: blur(8px);
                z-index: 99999;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
                overflow: hidden;
            `;
           
            // Create iframe with exact dimensions - pass edit mode and video data
            const iframe = document.createElement('iframe');
            const editParams = new URLSearchParams({
                edit_mode: '1',
                video_id: videoData.id,
                title: videoData.title || '',
                description: videoData.description || '',
                category: videoData.category || '',
                tags: videoData.tags || '',
                badges: videoData.badges || '',
                visibility: videoData.privacy || videoData.visibility || 'public',
                thumbnail: videoData.thumbnail || '',
                source: videoData.source || '',
                video_link: videoData.video_link || videoData.video_url || '',
                provider_video_id: videoData.video_id || '',
                v: Date.now()
            });
           
            // Get the current directory path and construct the uploader URL
            const currentPath = window.location.pathname;
            const basePath = currentPath.substring(0, currentPath.lastIndexOf('/'));
            iframe.src = basePath + '/ionuploader.php?' + editParams.toString();
            iframe.style.cssText = `
                width: 1200px;
                max-width: 95vw;
                height: 90vh;
                max-height: 900px;
                border: none;
                border-radius: 16px;
                background: #0f172a;
                overflow: hidden;
            `;
            iframe.allow = 'camera; microphone; fullscreen';
           
            modalOverlay.appendChild(iframe);
            document.body.appendChild(modalOverlay);
           
            // Disable page scrolling when modal is open
            document.body.style.overflow = 'hidden';
           
            // Close functionality
            const closeModal = () => {
                // Restore page scrolling
                document.body.style.overflow = '';
                modalOverlay.remove();
                location.reload(); // Refresh the video list to show updates
            };
           
            // Close on overlay click
            modalOverlay.addEventListener('click', (e) => {
                if (e.target === modalOverlay) {
                    closeModal();
                }
            });
           
            // Listen for messages from iframe
            const editMessageHandler = function(event) {
                if (event.origin !== window.location.origin) return;
               
                // Handle both string and object message formats
                const messageType = typeof event.data === 'string' ? event.data : event.data?.type;
               
                if (messageType === 'upload_complete' || messageType === 'edit_complete' || messageType === 'close_modal' || messageType === 'video_deleted') {
                    closeModal();
                    window.removeEventListener('message', editMessageHandler);
                }
            };
            window.addEventListener('message', editMessageHandler);
           
            // Close on escape key
            const handleEscape = (e) => {
                if (e.key === 'Escape') {
                    closeModal();
                    document.removeEventListener('keydown', handleEscape);
                }
            };
            document.addEventListener('keydown', handleEscape);
        }
    </script>

<style>
.stat-card.clickable {
    cursor: pointer;
}

/* Responsive video actions */
@media (max-width: 768px) {
    .video-actions {
        flex-wrap: nowrap !important;
        overflow-x: auto;
        gap: 3px !important;
        margin-left: 3px !important;
    }
    
    .list-video-actions {
        flex-wrap: nowrap !important;
        overflow-x: auto;
        gap: 3px !important;
        margin-left: 3px !important;
    }
    
    .action-btn {
        padding: 3px 5px !important;
        font-size: 11px !important;
        flex-shrink: 0;
    }
    
    .action-btn span {
        display: none;
    }
    
    .video-meta {
        flex-wrap: nowrap;
        align-items: flex-start;
    }
    
    .status-badge {
        margin-right: 4px !important;
        font-size: 0.7rem !important;
    }
    
    .status-badge.clickable {
        cursor: pointer;
        user-select: none;
        transition: all 0.2s ease;
    }
    
    .status-badge.clickable:hover {
        transform: scale(1.05);
        opacity: 0.9;
    }
    
    .status-arrow {
        margin-left: 4px;
        opacity: 0.7;
        transition: transform 0.2s ease;
    }
    
    .status-badge.open .status-arrow {
        transform: rotate(180deg);
    }
}

.stat-card.clickable:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

/* Video modal styles are now handled by includes/video-modal.php */
</style>

<!-- Load the video modal script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('VideoModal available:', typeof window.VideoModal);
    console.log('VideoModal.open function available:', window.VideoModal ? typeof window.VideoModal.open : 'VideoModal not found');
    console.log('Legacy openVideoModal function available:', typeof window.openVideoModal);
    
    // Test if the modal system is working
    if (window.VideoModal) {
        console.log('VideoModal object found:', window.VideoModal);
        console.log('VideoModal methods:', Object.keys(window.VideoModal));
    }
    
    // Debug: Check if modal HTML exists and is properly positioned
    setTimeout(() => {
        const modal = document.getElementById('videoModal');
        if (modal) {
            console.log('🎬 Modal element found:', modal);
            console.log('🎬 Modal computed styles:', {
                position: getComputedStyle(modal).position,
                zIndex: getComputedStyle(modal).zIndex,
                display: getComputedStyle(modal).display,
                top: getComputedStyle(modal).top,
                left: getComputedStyle(modal).left
            });
        } else {
            console.error('❌ Modal element not found!');
        }
    }, 1000);
});    
</script>

<!-- Load Enhanced Share JavaScript -->
<script src="/share/enhanced-ion-share.js?v=2"></script>

<!-- Load Video Reactions JavaScript -->
<script src="/app/video-reactions.js?v=<?= time() ?>"></script>

<script>
// Debug: Ensure enhanced share system is loaded
console.log('🔍 Enhanced Share System Check:');
console.log('- EnhancedIONShare available:', typeof window.EnhancedIONShare);
console.log('- IONShare available:', typeof window.IONShare);

// Initialize Video Reactions
if (typeof IONVideoReactions !== 'undefined') {
    const reactions = new IONVideoReactions();
    reactions.initAll();
    console.log('✅ Video Reactions initialized');
} else {
    console.error('❌ IONVideoReactions not loaded');
}

// Re-initialize enhanced share system after page load
window.addEventListener('load', function() {
    setTimeout(function() {
        if (window.EnhancedIONShare && typeof window.EnhancedIONShare.init === 'function') {
            console.log('🔄 Re-initializing Enhanced Share System');
            window.EnhancedIONShare.init();
        }
    }, 100);
});

// Debug function to test share buttons
function testShareButton(videoId) {
    console.log('🧪 Testing share button for video:', videoId);
    
    if (window.EnhancedIONShare && window.EnhancedIONShare.openFromTemplate) {
        console.log('🧪 Using openFromTemplate method');
        window.EnhancedIONShare.openFromTemplate(videoId);
    } else if (window.EnhancedIONShare && window.EnhancedIONShare.openModal) {
        console.log('🧪 Using openModal method');
        const modalId = 'enhanced-share-modal-' + videoId;
        window.EnhancedIONShare.openModal(modalId);
    } else {
        console.error('❌ Enhanced share system not available');
    }
    
    // Check if modal was created
    setTimeout(() => {
        const globalModal = document.getElementById('enhanced-share-modal-global');
        if (globalModal) {
            console.log('🧪 Global modal found:', {
                display: globalModal.style.display,
                visibility: window.getComputedStyle(globalModal).visibility,
                zIndex: window.getComputedStyle(globalModal).zIndex
            });
        } else {
            console.error('🧪 Global modal not found');
        }
    }, 100);
}

// Debug function to test all functionality
function debugAllFunctions() {
    console.log('🔍 Testing all critical functions:');
    
    // Test share buttons
    const shareButtons = document.querySelectorAll('.enhanced-share-button');
    console.log('📤 Share buttons found:', shareButtons.length);
    shareButtons.forEach((btn, i) => {
        console.log(`📤 Share button ${i+1}:`, btn.onclick ? 'Has onclick' : 'Missing onclick', btn);
    });
    
    // Test status dropdowns
    const statusBadges = document.querySelectorAll('.status-badge.clickable');
    console.log('📊 Status badges found:', statusBadges.length);
    statusBadges.forEach((badge, i) => {
        const videoId = badge.id.replace('status-badge-', '').replace('status-badge-table-', '');
        const options = document.getElementById('status-options-' + videoId) || 
                       document.getElementById('status-options-table-' + videoId);
        console.log(`📊 Status ${i+1}:`, badge.onclick ? 'Has onclick' : 'Missing onclick', options ? 'Has options' : 'Missing options');
    });
    
    // Test edit buttons
    const editButtons = document.querySelectorAll('.edit-icon');
    console.log('✏️ Edit buttons found:', editButtons.length);
    
    // Test enhanced share system
    console.log('🔗 EnhancedIONShare available:', typeof window.EnhancedIONShare);
    if (window.EnhancedIONShare) {
        console.log('🔗 EnhancedIONShare.openModal:', typeof window.EnhancedIONShare.openModal);
        console.log('🔗 EnhancedIONShare.openFromTemplate:', typeof window.EnhancedIONShare.openFromTemplate);
    }
}

// Quick test function for share modal
window.testShareModal = function(videoId = 51272) {
    console.log('🧪 Testing share modal for video:', videoId);
    
    // Try to find a share button first
    const shareBtn = document.querySelector(`[onclick*="${videoId}"]`);
    if (shareBtn) {
        console.log('🧪 Found share button, clicking it:', shareBtn);
        shareBtn.click();
    } else {
        console.log('🧪 No share button found, trying direct method');
        testShareButton(videoId);
    }
};

// Run debug on page load
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(debugAllFunctions, 1000); // Wait 1 second for everything to load
});
</script>

<?php 
// Include the reusable video player modal for in-page video playback
$require_auth = true; // Admin page - authentication required
include __DIR__ . '/../includes/video-modal.php';
?>

</body>
</html>