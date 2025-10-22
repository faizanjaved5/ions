<?php
/**
 * ION Video Upload Endpoint - Working Version
 */

// Start output buffering and session
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error handling
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Load configuration
$config_path = __DIR__ . '/../config/config.php';
if (!file_exists($config_path)) {
    die(json_encode(['error' => 'Config file not found']));
}
$config = require_once $config_path;

// Load database class
$database_path = __DIR__ . '/../config/database.php';
if (!file_exists($database_path)) {
    die(json_encode(['error' => 'Database file not found']));
}
require_once $database_path;

// Headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Initialize database connection
try {
    if (class_exists('IONDatabase')) {
        // Extract database config for IONDatabase constructor
        $db_config = [
            'host' => $config['host'],
            'dbname' => $config['dbname'],
            'username' => $config['username'],
            'password' => $config['password'],
            'charset' => $config['charset'] ?? 'utf8mb4'
        ];
        $db = new IONDatabase($db_config);
        $wpdb = $db; // Create alias for compatibility
        global $db, $wpdb;
    } else {
        throw new Exception('IONDatabase class not found');
    }
} catch (Exception $e) {
    error_log('Database initialization failed: ' . $e->getMessage());
    die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
}

// Log file version for debugging
error_log('🔧 ionuploadvideos.php version: 4.0 - with ID fix and Upload source type');

// Main router
try {
    $action = $_POST['action'] ?? $_GET['action'] ?? 'upload';
    
    switch ($action) {
        case 'debug':
            echo json_encode([
                'success' => true,
                'message' => 'Working upload handler - ' . date('Y-m-d H:i:s'),
                'timestamp' => time(),
                'config_loaded' => isset($config),
                'r2_config_exists' => isset($config['cloudflare_r2_api']),
                'file_version' => 'v3.1',
                'session_data' => [
                    'user_email' => $_SESSION['user_email'] ?? 'not set',
                    'user_id' => $_SESSION['user_id'] ?? 'not set',
                    'session_id' => session_id()
                ],
                'database_connected' => $db->isConnected()
            ]);
            break;
            
        case 'upload':
            handleFileUpload();
            break;
            
        case 'platform_import':
            handlePlatformImport();
            break;
            
        case 'import_google_drive':
            handleGoogleDriveImport();
            break;
            
        case 'update_video':
            handleVideoUpdate();
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action: ' . $action]);
    }
    
} catch (Exception $e) {
    ob_clean();
    error_log('🚨 Upload exception: ' . $e->getMessage());
    error_log('🚨 Upload trace: ' . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'debug_info' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
}

/**
 * Handle file upload
 */
function handleFileUpload() {
    global $config, $db;
    
    // Debug: Log that we're starting
    error_log('🎬 handleFileUpload called');
    error_log('🎬 POST data: ' . json_encode(array_keys($_POST)));
    error_log('🎬 FILES data: ' . json_encode(array_keys($_FILES)));
    
    // Check for file in different possible field names
    $file = null;
    if (isset($_FILES['video'])) {
        $file = $_FILES['video'];
        error_log('🎬 File found in $_FILES[video]');
    } elseif (isset($_FILES['file'])) {
        $file = $_FILES['file'];
        error_log('🎬 File found in $_FILES[file]');
    } else {
        error_log('❌ No file found in $_FILES');
        throw new Exception('No file uploaded. Available fields: ' . implode(', ', array_keys($_FILES)));
    }
    
    // Validate file upload
    if (isset($file['error']) && $file['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive in HTML form',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
        ];
        $errorMsg = $uploadErrors[$file['error']] ?? 'Unknown upload error';
        error_log('❌ File upload error: ' . $errorMsg);
        throw new Exception('File upload failed: ' . $errorMsg);
    }
    
    error_log('✅ File validation passed: ' . $file['name']);
    $metadata = [
        'title' => $_POST['title'] ?? $file['name'],
        'description' => $_POST['description'] ?? '',
        'category' => $_POST['category'] ?? 'General',
        'tags' => $_POST['tags'] ?? '',
        'visibility' => $_POST['visibility'] ?? 'public',
        'badges' => $_POST['badges'] ?? '',
        'user_email' => $_SESSION['user_email'] ?? 'test@example.com'
    ];
    
    // Debug: Log received badges
    error_log('🏷️ ION Badges received: ' . ($metadata['badges'] ?: 'none'));
    
    // Handle thumbnail if provided
    $thumbnailUrl = '';
    error_log('🔍 DEBUG: Checking for thumbnail...');
    error_log('🔍 DEBUG: $_FILES[thumbnail] exists: ' . (isset($_FILES['thumbnail']) ? 'YES' : 'NO'));
    if (isset($_FILES['thumbnail'])) {
        error_log('🔍 DEBUG: $_FILES[thumbnail][error]: ' . ($_FILES['thumbnail']['error'] ?? 'NOT SET'));
        error_log('🔍 DEBUG: $_FILES[thumbnail][name]: ' . ($_FILES['thumbnail']['name'] ?? 'NOT SET'));
        error_log('🔍 DEBUG: $_FILES[thumbnail][size]: ' . ($_FILES['thumbnail']['size'] ?? 'NOT SET'));
    }
    error_log('🔍 DEBUG: $_POST[thumbnail_data] exists: ' . (!empty($_POST['thumbnail_data']) ? 'YES (length: ' . strlen($_POST['thumbnail_data']) . ')' : 'NO'));
    
    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
        error_log('📸 Thumbnail file uploaded: ' . $_FILES['thumbnail']['name'] . ' (' . $_FILES['thumbnail']['size'] . ' bytes)');
        $thumbnailUrl = handleThumbnailUpload($_FILES['thumbnail']);
        error_log('📸 Thumbnail saved at: ' . ($thumbnailUrl ?: 'FAILED'));
    } elseif (!empty($_POST['thumbnail_data'])) {
        // Base64 encoded thumbnail from video frame capture
        error_log('📸 Thumbnail data (base64) provided, length: ' . strlen($_POST['thumbnail_data']));
        $thumbnailUrl = handleThumbnailFromData($_POST['thumbnail_data'], $file['name']);
        error_log('📸 Thumbnail saved at: ' . ($thumbnailUrl ?: 'FAILED'));
    } else {
        error_log('⚠️ No thumbnail provided for this upload');
    }
    
    // Get user info from session
    $userEmail = $_SESSION['user_email'] ?? 'test@example.com';
    
    // Look up user ID from email - try multiple tables
    global $db;
    $user = null;
    
    // Try IONEERS table first (note: IONEERS uses user_id, not id)
    $user = $db->get_row("SELECT user_id as id, user_role as role FROM IONEERS WHERE email = ?", $userEmail);
    
    // If not found, try other common user tables
    if (!$user) {
        $user = $db->get_row("SELECT id, 'user' as role FROM users WHERE email = ?", $userEmail);
    }
    
    // If still not found, create a default user entry or use session ID
    if (!$user) {
        // Use session-based approach as fallback
        $userId = $_SESSION['user_id'] ?? 1; // Default to user ID 1 if no session
        $user = (object)['id' => $userId, 'role' => 'user'];
        error_log("⚠️ User lookup failed for email: $userEmail, using fallback user ID: $userId");
    }
    
    // Generate short link
    $shortLink = generateShortLink();
    error_log('🔗 Generated short link: ' . $shortLink);
    
    // CRITICAL: Determine channel slug - MUST be from IONLocalNetwork, NEVER from video title
    // Priority: 1) Selected channels from channel selector, 2) User's primary channel, 3) Default 'ions'
    $channelSlug = 'ions'; // Safe default
    $channelId = null;
    $channelTitle = null;
    
    // DEBUG: Log what we received
    error_log('🔍 DEBUG selected_channels POST: ' . ($_POST['selected_channels'] ?? 'NOT SET'));
    error_log('🔍 DEBUG $_POST keys: ' . implode(', ', array_keys($_POST)));
    
    // First check if user selected specific channels via channel selector
    if (isset($_POST['selected_channels']) && !empty($_POST['selected_channels'])) {
        $selectedChannels = json_decode($_POST['selected_channels'], true);
        error_log('🔍 DEBUG decoded channels: ' . print_r($selectedChannels, true));
        
        if (!empty($selectedChannels) && is_array($selectedChannels)) {
            $channelSlug = $selectedChannels[0]; // First channel = primary
            error_log('✅ File upload - Using selected channel: ' . $channelSlug);
        } else {
            error_log('⚠️ DEBUG: selected_channels is empty or not an array after decode');
        }
    } else {
        error_log('⚠️ DEBUG: selected_channels not set or empty in POST');
        
        // Fallback: Try to get user's primary channel from IONLocalNetwork or channels table
        $channel = $db->get_row(
            "SELECT id, slug, title FROM channels WHERE user_id = %d AND is_primary = 1 LIMIT 1",
            $user->id
        );
        
        if ($channel && !empty($channel->slug)) {
            $channelSlug = $channel->slug;
            $channelId = $channel->id;
            $channelTitle = $channel->title;
            error_log('📺 File upload - Using user primary channel: ' . $channelSlug);
        } else {
            error_log('📺 File upload - No channel found, using default: ions');
        }
    }
    
    error_log('📺 FINAL Channel determined - Slug: ' . $channelSlug . ', ID: ' . ($channelId ?? 'null'));
    
    // Get category ID from categories table
    $categoryId = null;
    if (!empty($metadata['category'])) {
        $categoryData = $db->get_row(
            "SELECT id FROM categories WHERE title = %s OR slug = %s LIMIT 1",
            $metadata['category'],
            strtolower(str_replace(' ', '-', $metadata['category']))
        );
        $categoryId = $categoryData->id ?? null;
    }
    
    // Generate unique video_id for uploaded files
    $videoIdField = 'upload_' . time() . '_' . uniqid();
    
    // Determine status based on user role (Owner/Admin = auto-approved)
    $isAdminOrOwner = isset($user->role) && in_array(strtolower($user->role), ['owner', 'admin', 'administrator', 'super_admin']);
    $videoStatus = $isAdminOrOwner ? 'Approved' : 'Pending';
    $publishedAt = $isAdminOrOwner ? date('Y-m-d H:i:s') : null; // Publish immediately if Owner/Admin
    
    error_log('👤 User role: ' . ($user->role ?? 'unknown') . ', Status: ' . $videoStatus);
    
    // Prepare comprehensive video data with all fields properly populated
    $videoData = [
        // Core identification
        'slug'              => $channelSlug,              // Channel slug (not random!)
        'video_id'          => $videoIdField,             // Unique ID for uploaded file
        'title'             => $metadata['title'],
        'description'       => $metadata['description'] ?: '',
        'short_link'        => $shortLink,
        
        // User & Channel info
        'user_id'           => $user->id,
        'channel_id'        => $channelId,
        'channel_title'     => $channelTitle,
        
        // Category
        'category'          => $metadata['category'] ?: 'General',
        'category_id'       => $categoryId,
        'tags'              => $metadata['tags'] ?: '',
        
        // Media URLs (will be updated after upload)
        'video_link'        => '',                        // Will be set after R2 upload
        'thumbnail'         => $thumbnailUrl ?: '',       // User-selected or captured thumbnail
        'optimized_url'     => null,                      // Will be set after optimization
        'hls_manifest_url'  => null,                      // For adaptive streaming
        'dash_manifest_url' => null,                      // For adaptive streaming
        
        // Video type & source (CRITICAL for player detection)
        'source'            => 'Upload',                  // Upload vs Youtube/Vimeo/etc
        'videotype'         => 'Upload',                  // Upload vs Youtube/Vimeo/etc
        
        // Display settings
        'visibility'        => ucfirst($metadata['visibility']) ?: 'Public',
        'layout'            => 'Wide',                    // Will auto-detect from dimensions
        'format'            => 'Video',                   // Video vs Short
        'age'               => 'Everyone',                // Age rating
        'geo'               => 'None',                    // Geographic restrictions
        
        // Status & Publishing
        'status'            => $videoStatus,              // Approved (admin) or Pending (creator)
        'upload_status'     => 'Transmitting',            // Will update to Completed/Failed
        'optimization_status' => 'pending',               // Will process in background
        'date_added'        => date('Y-m-d H:i:s'),
        'published_at'      => $publishedAt,              // Set if admin, null if pending
        
        // Engagement metrics (defaults)
        'view_count'        => 0,                         // View count
        'clicks'            => 0,                         // Short link clicks
        
        // Streaming & optimization (defaults for new uploads)
        'adaptive_streaming' => 0,                        // Will be 1 after transcoding
        'stream_ready'      => 0,                         // Will be 1 when ready
        'poster_time'       => 5,                         // Default thumbnail at 5 seconds
        
        // Optional fields (will be populated later)
        'stream_id'         => null,                      // Cloudflare Stream ID
        'optimization_started_at' => null,
        'optimization_completed_at' => null,
        'optimization_data' => null,                      // JSON with optimization details
        'thumbnails_json'   => null,                      // Multiple thumbnails
        'transcript'        => null                       // Video transcript
    ];
    
    // Test database connection and table
    $tableExists = $db->get_var("SHOW TABLES LIKE 'IONLocalVideos'");
    error_log('🗄️ Table IONLocalVideos exists: ' . ($tableExists ? 'YES' : 'NO'));
    
    // Check table structure for auto-increment
    if ($tableExists) {
        $createTable = $db->get_row("SHOW CREATE TABLE IONLocalVideos");
        if ($createTable && isset($createTable->{'Create Table'})) {
            $hasAutoIncrement = strpos($createTable->{'Create Table'}, 'AUTO_INCREMENT') !== false;
            error_log('🗄️ Table has AUTO_INCREMENT: ' . ($hasAutoIncrement ? 'YES' : 'NO'));
            if (!$hasAutoIncrement) {
                error_log('⚠️ WARNING: IONLocalVideos table does not have AUTO_INCREMENT on id column!');
            }
        }
    }
    
    // Ping database to ensure connection is alive (prevent "MySQL server has gone away")
    try {
        $db->get_var("SELECT 1");
    } catch (Exception $e) {
        error_log("⚠️ Database connection check failed, will auto-reconnect");
    }
    
    // Insert into database first
    error_log('🎬 Attempting to insert video data: ' . json_encode($videoData));
    $insertResult = $db->insert('IONLocalVideos', $videoData);
    
    if (!$insertResult) {
        $dbError = $db->last_error ?? 'Unknown database error';
        error_log('❌ Database insert failed: ' . $dbError);
        
        // Check if this is a duplicate entry error
        if (strpos($dbError, 'Duplicate entry') !== false || strpos($dbError, '1062') !== false) {
            throw new Exception('This video has already been uploaded. Please check your uploaded videos or try uploading a different video.');
        }
        
        throw new Exception('Failed to save video to database: ' . $dbError);
    }
    
    // Get the auto-increment ID from insert_id property
    $videoId = $db->insert_id;
    error_log('🎬 Database insert_id from PDO: ' . $videoId);
    
    // CRITICAL FIX: If insert_id is 0 or null, query the database directly
    if (!$videoId || $videoId == 0 || $videoId === '0') {
        error_log('⚠️ insert_id is 0, querying database directly for the last inserted record');
        
        // Get the last inserted record for this user with matching short_link
        $videoId = $db->get_var(
            "SELECT id FROM IONLocalVideos WHERE short_link = %s ORDER BY date_added DESC LIMIT 1",
            $shortLink
        );
        
        error_log('🎬 Database query result - Video ID: ' . $videoId);
    
    if (!$videoId || $videoId == 0) {
            error_log('❌ Failed to retrieve video ID even after direct query');
            error_log('❌ Short link used: ' . $shortLink);
            error_log('❌ Last DB error: ' . ($db->last_error ?? 'none'));
            throw new Exception('Failed to get video ID after database insert. The record may not have been saved.');
        }
    }
    
    // Cast to integer to ensure it's a proper number
    $videoId = (int)$videoId;
    error_log('✅ Final Video ID (cast to int): ' . $videoId);
    
    error_log('✅ Video saved successfully with ID: ' . $videoId);
    
    // Handle badges if provided (using junction table)
    try {
        if (!empty($metadata['badges'])) {
            error_log('🏷️ Processing badges: ' . $metadata['badges']);
            handleVideoBadges($videoId, $metadata['badges'], $user->id);
            error_log('✅ Badges processed successfully');
        }
    } catch (Exception $e) {
        error_log('❌ Badge processing failed: ' . $e->getMessage());
        // Don't fail the whole upload if just badges fail
    }
    
    // Handle multi-channel distribution
    try {
        $selectedChannels = isset($_POST['selected_channels']) ? json_decode($_POST['selected_channels'], true) : [];
        error_log('🔍 DEBUG: selected_channels POST value: ' . ($_POST['selected_channels'] ?? 'NOT SET'));
        error_log('🔍 DEBUG: decoded selectedChannels: ' . print_r($selectedChannels, true));
        
        if (!empty($selectedChannels) && is_array($selectedChannels)) {
            error_log('📺 Processing channel distribution for video ID ' . $videoId . ' with ' . count($selectedChannels) . ' channels');
            error_log('📺 Channel list: ' . implode(', ', $selectedChannels));
            
            // First channel = Primary (update video's slug)
            $primaryChannelSlug = $selectedChannels[0];
            
            $updateResult = $db->update('IONLocalVideos', 
                ['slug' => $primaryChannelSlug],
                ['id' => $videoId]
            );
            error_log('✅ Primary channel update for video ' . $videoId . ': ' . ($updateResult ? 'SUCCESS' : 'FAILED') . ' - Slug: ' . $primaryChannelSlug);
            
            // Verify the update
            $verifySlug = $db->get_var("SELECT slug FROM IONLocalVideos WHERE id = ?", $videoId);
            error_log('🔍 Verified slug in database: ' . ($verifySlug ?? 'NULL'));
            
            // Remaining channels = Distribute to IONLocalBlast
            if (count($selectedChannels) > 1) {
                $distributionChannels = array_slice($selectedChannels, 1);
                error_log('📺 Distributing to ' . count($distributionChannels) . ' additional channels: ' . implode(', ', $distributionChannels));
                
                $category = $metadata['category'] ?: 'General';
                $publishedAt = $publishedAt ?: date('Y-m-d H:i:s');
                
                foreach ($distributionChannels as $channelSlug) {
                    error_log('📺 Processing distribution channel: ' . $channelSlug);
                    // Verify channel exists in IONLocalNetwork
                    $channelExists = $db->get_var(
                        "SELECT slug FROM IONLocalNetwork WHERE slug = %s LIMIT 1",
                        $channelSlug
                    );
                    
                    if (!$channelExists) {
                        error_log('⚠️ Channel not found: ' . $channelSlug);
                        continue;
                    }
                    
                    // Insert into IONLocalBlast (with duplicate key update)
                    $db->query($db->prepare(
                        "INSERT INTO IONLocalBlast (
                            video_id, channel_slug, category, published_at, 
                            status, priority, added_at
                        ) VALUES (
                            %d, %s, %s, %s, 
                            'active', 0, NOW()
                        ) ON DUPLICATE KEY UPDATE
                            published_at = VALUES(published_at),
                            status = 'active'",
                        $videoId,
                        $channelSlug,
                        $category,
                        $publishedAt
                    ));
                    
                    error_log('✅ Video distributed to channel: ' . $channelSlug);
                }
                
                // Verify what was saved
                $savedChannels = $db->get_results("SELECT channel_slug, status FROM IONLocalBlast WHERE video_id = ?", $videoId);
                error_log('✅ Multi-channel distribution completed for video ID: ' . $videoId . ' - Saved ' . ($savedChannels ? count($savedChannels) : 0) . ' distributed channels');
            } else {
                error_log('📺 No additional channels to distribute (only primary channel selected)');
            }
        } else {
            error_log('ℹ️ No additional channels selected for distribution');
        }
    } catch (Exception $e) {
        error_log('❌ Channel distribution failed: ' . $e->getMessage());
        // Don't fail the whole upload if distribution fails
    }
    
    // Upload file based on configured mode
    try {
        $uploadMode = $config['video_upload_mode'] ?? 'r2_basic';
        error_log('📤 Upload mode: ' . $uploadMode . ' for video ID: ' . $videoId);
        
        $uploadResult = routeVideoUpload($file, $videoId, $uploadMode, $config);
        
        // Update video record with upload results
        if ($uploadResult['success']) {
            $updateData = [
                'video_link' => $uploadResult['video_link'],
                'upload_status' => 'Completed'
            ];
            
            // Add optional fields if available
            if (!empty($uploadResult['stream_id'])) {
                $updateData['stream_id'] = $uploadResult['stream_id'];
            }
            if (!empty($uploadResult['hls_manifest_url'])) {
                $updateData['hls_manifest_url'] = $uploadResult['hls_manifest_url'];
            }
            if (!empty($uploadResult['dash_manifest_url'])) {
                $updateData['dash_manifest_url'] = $uploadResult['dash_manifest_url'];
            }
            if (!empty($uploadResult['optimized_url'])) {
                $updateData['optimized_url'] = $uploadResult['optimized_url'];
            }
            
            // CRITICAL: Preserve the user's custom thumbnail (don't overwrite it)
            // Only update thumbnail if upload result provides a new one (e.g., from Cloudflare Stream)
            if (!empty($uploadResult['thumbnail']) && empty($thumbnailUrl)) {
                $updateData['thumbnail'] = $uploadResult['thumbnail'];
            }
            // If we already have a user thumbnail, keep it (don't add to updateData)
            
            // Set optimization status based on mode
            if ($uploadMode === 'cloudflare_stream') {
                $updateData['optimization_status'] = 'completed';  // Stream does it automatically
                $updateData['stream_ready'] = 1;
                $updateData['adaptive_streaming'] = 1;
            } elseif ($uploadMode === 'r2_optimized') {
                $updateData['optimization_status'] = 'pending';  // Will be processed by queue
            } else {
                $updateData['optimization_status'] = 'not_needed';  // Basic R2, no optimization
            }
            
            $db->update('IONLocalVideos', $updateData, ['id' => $videoId]);
            $videoData['video_link'] = $uploadResult['video_link'];
            $r2Url = $uploadResult['video_link'];
        } else {
            // Upload failed
            $db->update('IONLocalVideos', [
                'upload_status' => 'Failed'
            ], ['id' => $videoId]);
        $r2Url = null;
            throw new Exception($uploadResult['error'] ?? 'Upload failed');
        }
    } catch (Exception $e) {
        error_log('❌ Upload failed: ' . $e->getMessage());
        $r2Url = null;
        // Update status to failed
        $db->update('IONLocalVideos', [
            'upload_status' => 'Failed'
        ], ['id' => $videoId]);
    }
    
    error_log('✅ Upload process completed, sending response');
    
    // Generate Enhanced Share template for this video
    $shareTemplate = generateShareTemplate($videoId, $shortLink, $metadata['title'], $db);
    
    echo json_encode([
        'success'       => true,
        'message'       => 'Video uploaded successfully',
        'video_id'      => $videoId,
        'filename'      => $file['name'],
        'size'          => $file['size'],
        'title'         => $metadata['title'],
        'thumbnail'     => $thumbnailUrl ?: '', // Return the actual thumbnail URL
        'shortlink'     => $shortLink,
        'video_url'     => $r2Url ?? '',
        'celebration'   => true, // This triggers the celebration dialog
        'share_template' => $shareTemplate, // Enhanced ION Share template for this video
        'debug_info'    => [
            'user_id'             => $user->id,
            'user_role'           => $user->role ?? 'unknown',
            'user_email'          => $userEmail,
            'upload_status'       => 'Completed',
            'optimization_status' => 'pending',
            'r2_upload'           => $r2Url ? 'success' : 'failed',
            'thumbnail_saved'     => $thumbnailUrl ? 'yes' : 'no',
            'badges_saved'        => $metadata['badges']
        ]
    ]);
    
    error_log('✅ Response sent successfully');
}

/**
 * Route video upload to appropriate handler based on mode
 */
function routeVideoUpload($file, $videoId, $mode, $config) {
    switch ($mode) {
        case 'r2_basic':
            return uploadToR2Basic($file, $videoId, $config);
            
        case 'r2_optimized':
            return uploadToR2Optimized($file, $videoId, $config);
            
        case 'cloudflare_stream':
            return uploadToCloudflareStream($file, $videoId, $config);
            
        default:
            error_log('⚠️ Unknown upload mode: ' . $mode . ', falling back to r2_basic');
            return uploadToR2Basic($file, $videoId, $config);
    }
}

/**
 * MODE A: Upload file to R2 storage (Basic - No Optimization)
 */
function uploadToR2Basic($file, $videoId, $config) {
    // Check if R2 is configured
    if (!isset($config['cloudflare_r2_api'])) {
        error_log('❌ R2 not configured');
        return ['success' => false, 'error' => 'R2 storage not configured'];
    }
    
    try {
        $r2Config = $config['cloudflare_r2_api'];
        
        // Validate R2 config
        if (empty($r2Config['access_key_id']) || empty($r2Config['secret_access_key']) || empty($r2Config['bucket_name'])) {
            error_log('❌ R2 credentials incomplete');
            return ['success' => false, 'error' => 'R2 credentials incomplete'];
        }
        
        // Generate organized filename with date path
        $fileExt = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = date('Y/m/d/') . $videoId . '_' . uniqid() . '.' . $fileExt;
        $filePath = $file['tmp_name'];
        
        $bucket = $r2Config['bucket_name'];
        $key = $fileName;
        $endpoint = rtrim($r2Config['endpoint'], '/');
        $publicBase = rtrim($r2Config['public_url_base'], '/');
        
        error_log("📤 Starting R2 upload: {$key}");
        
        // Prepare S3 PUT URL (encode path segments individually)
        $keyParts = explode('/', $key);
        $encodedKey = implode('/', array_map('rawurlencode', $keyParts));
        $url = "{$endpoint}/{$bucket}/{$encodedKey}";
        
        // Create date headers for AWS signature v4
        $date = gmdate('Ymd\THis\Z');
        $shortDate = gmdate('Ymd');
        $region = $r2Config['region'];
        $service = 's3';
        
        // Calculate payload hash
        $payloadHash = hash_file('sha256', $filePath);
        
        // Get host from endpoint
        $host = parse_url($endpoint, PHP_URL_HOST);
        
        // Create canonical request
        $canonicalHeaders = "host:{$host}\nx-amz-content-sha256:{$payloadHash}\nx-amz-date:{$date}\n";
        $signedHeaders = "host;x-amz-content-sha256;x-amz-date";
        $canonicalRequest = "PUT\n/{$bucket}/{$key}\n\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";
        
        // Create string to sign
        $algorithm = 'AWS4-HMAC-SHA256';
        $credentialScope = "{$shortDate}/{$region}/{$service}/aws4_request";
        $stringToSign = "{$algorithm}\n{$date}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);
        
        // Calculate signature
        $kDate = hash_hmac('sha256', $shortDate, "AWS4" . $r2Config['secret_access_key'], true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', "aws4_request", $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);
        
        // Create authorization header
        $authorization = "{$algorithm} Credential={$r2Config['access_key_id']}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";
        
        // Upload via cURL
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_PUT => true,
            CURLOPT_INFILE => fopen($filePath, 'rb'),
            CURLOPT_INFILESIZE => $file['size'],
            CURLOPT_HTTPHEADER => [
                "Authorization: {$authorization}",
                "x-amz-content-sha256: {$payloadHash}",
                "x-amz-date: {$date}",
                "Content-Type: " . mime_content_type($filePath),
            ],
            CURLOPT_RETURNTRANSFER => true,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            $publicUrl = "{$publicBase}/{$key}";
            error_log("✅ R2 upload successful: {$publicUrl}");
            return [
                'success' => true,
                'video_link' => $publicUrl,
                'storage_type' => 'r2',
                'file_size' => $file['size']
            ];
        } else {
            error_log("❌ R2 upload failed (HTTP {$httpCode}): {$response}");
            if ($curlError) {
                error_log("❌ cURL error: {$curlError}");
            }
            
            // Check if we should fall back to local storage
            $failToLocal = $r2Config['fail_to_local'] ?? false;
            if ($failToLocal) {
                error_log("⚠️ Falling back to local storage");
                return saveVideoLocally($file, $videoId);
            } else {
                // Fail-fast: Don't fall back, return error
                return [
                    'success' => false,
                    'error' => "R2 upload failed (HTTP {$httpCode})",
                    'details' => $response
                ];
            }
        }
        
    } catch (Exception $e) {
        error_log('❌ R2 upload exception: ' . $e->getMessage());
        
        // Check if we should fall back to local storage
        $failToLocal = $r2Config['fail_to_local'] ?? false;
        if ($failToLocal) {
            error_log("⚠️ Falling back to local storage");
            return saveVideoLocally($file, $videoId);
        } else {
            // Fail-fast: Don't fall back, return error
            return [
                'success' => false,
                'error' => 'R2 upload exception: ' . $e->getMessage()
            ];
        }
    }
}

/**
 * Save video file locally as fallback (only used if fail_to_local = true)
 */
function saveVideoLocally($file, $videoId) {
    try {
        // Create upload directory if it doesn't exist
        $uploadDir = __DIR__ . '/../uploads/videos/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Generate unique filename
        $fileExt = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = $videoId . '_' . uniqid() . '.' . $fileExt;
        $filePath = $uploadDir . $fileName;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            $publicUrl = '/uploads/videos/' . $fileName;
            error_log("✅ Video saved locally: {$publicUrl}");
            return [
                'success' => true,
                'video_link' => $publicUrl,
                'storage_type' => 'local',
                'file_size' => filesize($filePath)
            ];
        } else {
            error_log("❌ Failed to save video locally");
            return [
                'success' => false,
                'error' => 'Failed to save video to local storage'
            ];
        }
    } catch (Exception $e) {
        error_log('❌ Local save exception: ' . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Local save exception: ' . $e->getMessage()
        ];
    }
}

/**
 * MODE B: Upload to R2 + Queue for Optimization
 */
function uploadToR2Optimized($file, $videoId, $config) {
    // First, upload to R2
    $r2Result = uploadToR2Basic($file, $videoId, $config);
    
    if (!$r2Result['success']) {
        return $r2Result;  // Return the error
    }
    
    // Then, queue for optimization
    try {
        $optConfig = $config['video_optimization'] ?? [];
        
        if (!($optConfig['enabled'] ?? false)) {
            error_log('⚠️ Optimization is not enabled in config');
            // Return R2 upload result, will be marked as pending in calling function
            return $r2Result;
        }
        
        $method = $optConfig['method'] ?? 'local';
        error_log('🎨 Queuing video for optimization (method: ' . $method . ')');
        
        // Queue the optimization job
        $queueResult = queueVideoOptimization($videoId, $r2Result['video_link'], $method, $optConfig);
        
        if ($queueResult) {
            error_log('✅ Video queued for optimization');
            $r2Result['optimization_queued'] = true;
        } else {
            error_log('⚠️ Failed to queue optimization, but upload succeeded');
            $r2Result['optimization_queued'] = false;
        }
        
        return $r2Result;
        
    } catch (Exception $e) {
        error_log('❌ Optimization queue failed: ' . $e->getMessage());
        // Return success for R2 upload, optimization can be retried later
        $r2Result['optimization_queued'] = false;
        return $r2Result;
    }
}

/**
 * MODE C: Upload to Cloudflare Stream
 */
function uploadToCloudflareStream($file, $videoId, $config) {
    if (!isset($config['cloudflare_stream_api'])) {
        error_log('❌ Cloudflare Stream not configured');
        return ['success' => false, 'error' => 'Cloudflare Stream not configured'];
    }
    
    try {
        $streamConfig = $config['cloudflare_stream_api'];
        
        if (empty($streamConfig['account_id']) || empty($streamConfig['api_token'])) {
            error_log('❌ Stream credentials incomplete');
            return ['success' => false, 'error' => 'Stream credentials incomplete'];
        }
        
        $accountId = $streamConfig['account_id'];
        $apiToken = $streamConfig['api_token'];
        $uploadUrl = "https://api.cloudflare.com/client/v4/accounts/{$accountId}/stream";
        
        error_log('📤 Uploading to Cloudflare Stream...');
        
        // Upload to Stream API
        $ch = curl_init($uploadUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'file' => new CURLFile($file['tmp_name'], mime_content_type($file['tmp_name']), $file['name'])
            ],
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiToken,
            ],
            CURLOPT_RETURNTRANSFER => true,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            $result = json_decode($response, true);
            
            if ($result['success'] && isset($result['result'])) {
                $videoData = $result['result'];
                $streamId = $videoData['uid'];
                
                error_log("✅ Stream upload successful - Stream ID: {$streamId}");
                
                return [
                    'success' => true,
                    'video_link' => "https://customer-{$accountId}.cloudflarestream.com/{$streamId}/manifest/video.m3u8",
                    'stream_id' => $streamId,
                    'storage_type' => 'cloudflare_stream',
                    'hls_manifest_url' => $videoData['playback']['hls'] ?? null,
                    'dash_manifest_url' => $videoData['playback']['dash'] ?? null,
                    'thumbnail' => $videoData['thumbnail'] ?? null,
                    'duration' => $videoData['duration'] ?? null,
                    'file_size' => $file['size']
                ];
            } else {
                $errorMsg = $result['errors'][0]['message'] ?? 'Unknown Stream API error';
                error_log("❌ Stream API error: {$errorMsg}");
                return [
                    'success' => false,
                    'error' => 'Stream API error: ' . $errorMsg
                ];
            }
        } else {
            error_log("❌ Stream upload failed (HTTP {$httpCode}): {$response}");
            if ($curlError) {
                error_log("❌ cURL error: {$curlError}");
            }
            return [
                'success' => false,
                'error' => "Stream upload failed (HTTP {$httpCode})",
                'details' => $response
            ];
        }
        
    } catch (Exception $e) {
        error_log('❌ Stream upload exception: ' . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Stream upload exception: ' . $e->getMessage()
        ];
    }
}

/**
 * Queue video for background optimization
 */
function queueVideoOptimization($videoId, $videoUrl, $method, $optConfig) {
    // This is a placeholder - implement based on your queue system
    // Options: Redis Queue, RabbitMQ, Database queue, Cron job
    
    error_log("📋 Queue optimization for video {$videoId} using method: {$method}");
    
    // Example: Save to database queue table
    global $db;
    
    $queueData = [
        'video_id' => $videoId,
        'video_url' => $videoUrl,
        'method' => $method,
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s'),
        'config' => json_encode($optConfig)
    ];
    
    // Try to insert into optimization queue (if table exists)
    try {
        $result = $db->insert('IONOptimizationQueue', $queueData);
        return $result !== false;
    } catch (Exception $e) {
        error_log('⚠️ Optimization queue table may not exist: ' . $e->getMessage());
        // Return true to not block upload - optimization can be added later
        return true;
    }
}

/**
 * Handle platform import
 */
function handlePlatformImport() {
    global $db;
    
    // Debug: Log all incoming data
    error_log('🎬 Platform import - POST data: ' . json_encode($_POST));
    error_log('🎬 Platform import - Session data: ' . json_encode($_SESSION));
    
    $url = $_POST['url'] ?? '';
    $metadata = [
        'title' => $_POST['title'] ?? 'Imported Video',
        'description' => $_POST['description'] ?? '',
        'category' => $_POST['category'] ?? 'General',
        'tags' => $_POST['tags'] ?? '',
        'visibility' => $_POST['visibility'] ?? 'public',
        'badges' => $_POST['badges'] ?? '',
        'user_email' => $_SESSION['user_email'] ?? 'test@example.com'
    ];
    
    error_log('🎬 Platform import - Processed metadata: ' . json_encode($metadata));
    
    if (empty($url)) {
        throw new Exception('URL is required for platform import');
    }
    
    // Extract platform and video ID
    $platformInfo = extractPlatformInfo($url);
    
    // Handle custom thumbnail upload first (user-uploaded takes priority)
    $thumbnail = '';
    error_log('🔍 PLATFORM IMPORT - Checking for custom thumbnail...');
    error_log('🔍 PLATFORM IMPORT - $_FILES[thumbnail] exists: ' . (isset($_FILES['thumbnail']) ? 'YES' : 'NO'));
    error_log('🔍 PLATFORM IMPORT - $_POST[thumbnail_data] exists: ' . (!empty($_POST['thumbnail_data']) ? 'YES' : 'NO'));
    error_log('🔍 PLATFORM IMPORT - $_POST[thumbnail_url] exists: ' . (!empty($_POST['thumbnail_url']) ? 'YES' : 'NO'));
    
    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
        error_log('📸 PLATFORM IMPORT - Custom thumbnail file uploaded: ' . $_FILES['thumbnail']['name'] . ' (' . $_FILES['thumbnail']['size'] . ' bytes)');
        $thumbnail = handleThumbnailUpload($_FILES['thumbnail']);
        error_log('📸 PLATFORM IMPORT - Custom thumbnail saved at: ' . ($thumbnail ?: 'FAILED'));
    } elseif (!empty($_POST['thumbnail_data'])) {
        error_log('📸 PLATFORM IMPORT - Custom thumbnail data (base64) provided, length: ' . strlen($_POST['thumbnail_data']));
        $thumbnail = handleThumbnailFromData($_POST['thumbnail_data'], $metadata['title']);
        error_log('📸 PLATFORM IMPORT - Custom thumbnail saved at: ' . ($thumbnail ?: 'FAILED'));
    } elseif (!empty($_POST['thumbnail_url'])) {
        // CORS fallback: Fetch thumbnail from URL on server-side (no CORS restriction)
        error_log('📸 PLATFORM IMPORT - Thumbnail URL provided (CORS fallback): ' . $_POST['thumbnail_url']);
        $thumbnail = handleThumbnailFromUrl($_POST['thumbnail_url'], $metadata['title']);
        error_log('📸 PLATFORM IMPORT - Thumbnail fetched and saved at: ' . ($thumbnail ?: 'FAILED'));
    }
    
    // Fallback: Generate auto thumbnail from platform if no custom thumbnail provided
    if (empty($thumbnail)) {
        error_log('📸 PLATFORM IMPORT - No custom thumbnail, generating auto thumbnail from platform');
        if ($platformInfo['platform'] === 'youtube') {
            $thumbnail = "https://img.youtube.com/vi/{$platformInfo['video_id']}/maxresdefault.jpg";
        } elseif ($platformInfo['platform'] === 'vimeo') {
            $thumbnail = "https://vumbnail.com/{$platformInfo['video_id']}.jpg";
        }
        error_log('📸 PLATFORM IMPORT - Auto thumbnail URL: ' . ($thumbnail ?: 'NONE'));
    }
    
    // Get user information
    $userEmail = $metadata['user_email'];
    error_log('🎬 Platform import - Looking up user with email: ' . $userEmail);
    $user = $db->get_row("SELECT * FROM IONEERS WHERE email = ?", $userEmail);
    
    if (!$user) {
        error_log('❌ Platform import - User not found for email: ' . $userEmail);
        throw new Exception('User not found: ' . $userEmail);
    }
    
    error_log('✅ Platform import - User found: ' . $user->user_id . ' (' . $user->email . ')');
    
    // Generate unique short link
    $shortLink = generateShortLink();
    
    // CRITICAL: Determine channel slug - MUST be from IONLocalNetwork, NEVER from video title
    // Priority: 1) Selected channels from channel selector, 2) User's primary channel, 3) Default 'ions'
    $channelSlug = 'ions'; // Safe default
    $channelId = null;
    $channelTitle = null;
    
    // First check if user selected specific channels via channel selector
    if (isset($_POST['selected_channels']) && !empty($_POST['selected_channels'])) {
        $selectedChannels = json_decode($_POST['selected_channels'], true);
        if (!empty($selectedChannels) && is_array($selectedChannels)) {
            $channelSlug = $selectedChannels[0]; // First channel = primary
            error_log('📺 Platform import - Using selected channel: ' . $channelSlug);
        }
    } else {
        // Fallback: Try to get user's primary channel from IONLocalNetwork or channels table
        $channel = $db->get_row(
            "SELECT id, slug, title FROM channels WHERE user_id = %d AND is_primary = 1 LIMIT 1",
            $user->user_id
        );
        
        if ($channel && !empty($channel->slug)) {
            $channelSlug = $channel->slug;
            $channelId = $channel->id;
            $channelTitle = $channel->title;
            error_log('📺 Platform import - Using user primary channel: ' . $channelSlug);
        } else {
            error_log('📺 Platform import - No channel found, using default: ions');
        }
    }
    
    error_log('📺 Channel determined - Slug: ' . $channelSlug . ', ID: ' . ($channelId ?? 'null'));
    
    // Get category ID from categories table
    $categoryId = null;
    if (!empty($metadata['category'])) {
        $categoryData = $db->get_row(
            "SELECT id FROM categories WHERE title = %s OR slug = %s LIMIT 1",
            $metadata['category'],
            strtolower(str_replace(' ', '-', $metadata['category']))
        );
        $categoryId = $categoryData->id ?? null;
    }
    
    // Determine status based on user role (Owner/Admin = auto-approved)
    $isAdminOrOwner = isset($user->user_role) && in_array(strtolower($user->user_role), ['owner', 'admin', 'administrator', 'super_admin']);
    $videoStatus = $isAdminOrOwner ? 'Approved' : 'Pending';
    $publishedAt = $isAdminOrOwner ? date('Y-m-d H:i:s') : null;
    
    // Prepare comprehensive video data for platform imports
    $videoData = [
        // Core identification
        'slug'              => $channelSlug,
        'video_id'          => $platformInfo['video_id'],    // Actual platform video ID
        'title'             => $metadata['title'],
        'description'       => $metadata['description'],
        'short_link'        => $shortLink,
        
        // User & Channel info
        'user_id'           => $user->user_id,
        'channel_id'        => $channelId,
        'channel_title'     => $channelTitle,
        
        // Category
        'category'          => $metadata['category'],
        'category_id'       => $categoryId,
        'tags'              => $metadata['tags'],
        
        // Media URLs
        'video_link'        => $url,                         // Platform URL (YouTube, Vimeo, etc)
        'thumbnail'         => $thumbnail,                   // Platform thumbnail
        'optimized_url'     => null,                         // Not applicable for imports
        'hls_manifest_url'  => null,                         // Not applicable for imports
        'dash_manifest_url' => null,                         // Not applicable for imports
        
        // Video type & source (platform name)
        'source'            => ucfirst($platformInfo['platform']),
        'videotype'         => ucfirst($platformInfo['platform']),
        
        // Display settings
        'visibility'        => ucfirst($metadata['visibility']),
        'layout'            => 'Wide',                       // Default
        'format'            => 'Video',                      // Default
        'age'               => 'Everyone',
        'geo'               => 'None',
        
        // Status & Publishing
        'status'            => $videoStatus,
        'upload_status'     => 'Completed',                  // No upload needed for imports
        'optimization_status' => 'not_needed',               // Hosted on external platform
        'date_added'        => date('Y-m-d H:i:s'),
        'published_at'      => $publishedAt,
        
        // Engagement metrics
        'view_count'        => 0,
        'clicks'            => 0,
        
        // Streaming & optimization (not applicable for imports)
        'adaptive_streaming' => 0,
        'stream_ready'      => 0,
        'poster_time'       => null,
        'stream_id'         => null,
        'optimization_started_at' => null,
        'optimization_completed_at' => null,
        'optimization_data' => null,
        'thumbnails_json'   => null,
        'transcript'        => null
    ];
    
    // Ping database to ensure connection is alive (prevent "MySQL server has gone away")
    try {
        $db->get_var("SELECT 1");
    } catch (Exception $e) {
        error_log("⚠️ Database connection check failed, will auto-reconnect");
    }
    
    // Insert video into database
    error_log('🎬 Platform import - Attempting to insert video data: ' . json_encode($videoData));
    $insertResult = $db->insert('IONLocalVideos', $videoData);
    
    if (!$insertResult) {
        $dbError = $db->last_error ?? 'Unknown database error';
        error_log('❌ Platform import - Database insert failed: ' . $dbError);
        error_log('❌ Platform import - Failed data: ' . json_encode($videoData));
        
        // Check if this is a duplicate entry error
        if (strpos($dbError, 'Duplicate entry') !== false || strpos($dbError, '1062') !== false) {
            throw new Exception('This video has already been imported. Please check your uploaded videos or try importing a different video.');
        }
        
        throw new Exception('Failed to save video to database: ' . $dbError);
    }
    
    // Get the auto-increment ID from insert_id property
    $videoId = $db->insert_id;
    error_log('🎬 Platform import - Database insert_id from PDO: ' . $videoId);
    
    // CRITICAL FIX: If insert_id is 0 or null, query the database directly
    if (!$videoId || $videoId == 0 || $videoId === '0') {
        error_log('⚠️ Platform import - insert_id is 0, querying database directly');
        
        // Get the last inserted record with matching short_link
        $videoId = $db->get_var(
            "SELECT id FROM IONLocalVideos WHERE short_link = %s ORDER BY date_added DESC LIMIT 1",
            $shortLink
        );
        
        error_log('🎬 Platform import - Database query result - Video ID: ' . $videoId);
    
    if (!$videoId || $videoId == 0) {
            error_log('❌ Platform import - Failed to retrieve video ID even after direct query');
            error_log('❌ Platform import - Short link used: ' . $shortLink);
        throw new Exception('Failed to get video ID after database insert');
        }
    }
    
    // Cast to integer
    $videoId = (int)$videoId;
    error_log('✅ Platform import - Final Video ID (cast to int): ' . $videoId);
    
    error_log('✅ Platform import - Video saved successfully with ID: ' . $videoId);
    
    // Handle badges if provided
    if (!empty($metadata['badges'])) {
        handleVideoBadges($videoId, $metadata['badges'], $user->user_id);
    }
    
    // Handle multi-channel distribution (CRITICAL FIX: was missing for platform imports!)
    try {
        $selectedChannels = isset($_POST['selected_channels']) ? json_decode($_POST['selected_channels'], true) : [];
        error_log('🔍 PLATFORM IMPORT - selected_channels POST value: ' . ($_POST['selected_channels'] ?? 'NOT SET'));
        error_log('🔍 PLATFORM IMPORT - decoded selectedChannels: ' . print_r($selectedChannels, true));
        
        if (!empty($selectedChannels) && is_array($selectedChannels)) {
            error_log('📺 PLATFORM IMPORT - Processing channel distribution for video ID ' . $videoId . ' with ' . count($selectedChannels) . ' channels');
            error_log('📺 PLATFORM IMPORT - Channel list: ' . implode(', ', $selectedChannels));
            
            // First channel = Primary (update video's slug)
            $primaryChannelSlug = $selectedChannels[0];
            
            $updateResult = $db->update('IONLocalVideos', 
                ['slug' => $primaryChannelSlug],
                ['id' => $videoId]
            );
            error_log('✅ PLATFORM IMPORT - Primary channel update for video ' . $videoId . ': ' . ($updateResult ? 'SUCCESS' : 'FAILED') . ' - Slug: ' . $primaryChannelSlug);
            
            // Remaining channels = Distribute to IONLocalBlast
            foreach ($selectedChannels as $index => $channelSlug) {
                $channelSlug = trim($channelSlug);
                if (empty($channelSlug)) continue;
                
                error_log('📺 PLATFORM IMPORT - Processing channel ' . ($index + 1) . ': ' . $channelSlug);
                
                // Get channel details from IONLocalNetwork
                $channelData = $db->get_row(
                    "SELECT id, name, slug FROM IONLocalNetwork WHERE slug = %s LIMIT 1",
                    $channelSlug
                );
                
                if (!$channelData) {
                    error_log('⚠️ PLATFORM IMPORT - Channel not found in IONLocalNetwork: ' . $channelSlug);
                    continue;
                }
                
                // Insert into IONLocalBlast for distribution (even for primary channel for consistency)
                $blastData = [
                    'video_id' => $videoId,
                    'channel_slug' => $channelSlug,
                    'channel_id' => $channelData->id,
                    'category' => $metadata['category'],
                    'published_at' => date('Y-m-d H:i:s'),
                    'status' => 'active',
                    'priority' => ($index === 0) ? 10 : 5 // Primary channel gets higher priority
                ];
                
                $blastResult = $db->insert('IONLocalBlast', $blastData);
                
                if ($blastResult) {
                    error_log('✅ PLATFORM IMPORT - Channel ' . $channelSlug . ' distributed to IONLocalBlast (ID: ' . $blastResult . ')');
                } else {
                    error_log('❌ PLATFORM IMPORT - Failed to distribute channel: ' . $channelSlug);
                }
            }
            
            error_log('✅ PLATFORM IMPORT - Multi-channel distribution complete for video ' . $videoId);
        } else {
            error_log('📺 PLATFORM IMPORT - No channels selected, video assigned to slug: ' . $channelSlug);
        }
    } catch (Exception $e) {
        error_log('❌ PLATFORM IMPORT - Channel distribution failed: ' . $e->getMessage());
        // Don't fail the whole import if just channel distribution fails
    }
    
    // Generate Enhanced Share template for this video
    $shareTemplate = generateShareTemplate($videoId, $shortLink, $metadata['title'], $db);
    
    echo json_encode([
        'success' => true,
        'message' => 'Platform import completed successfully',
        'platform' => $platformInfo['platform'],
        'video_id' => $videoId,  // Database ID, NOT the YouTube/Vimeo ID!
        'provider_video_id' => $platformInfo['video_id'],  // YouTube/Vimeo ID for reference
        'title' => $metadata['title'],
        'thumbnail' => $thumbnail,
        'shortlink' => $shortLink,
        'celebration' => true,
        'share_template' => $shareTemplate, // Enhanced ION Share template for this video
        'debug_info' => [
            'user_id' => $user->user_id,
            'user_email' => $userEmail,
            'database_id' => $videoId,
            'short_link' => $shortLink
        ]
    ]);
}

/**
 * Handle video badges assignment
 */
function handleVideoBadges($videoId, $badges, $assignedBy) {
    global $db;
    
    if (empty($badges)) {
        return;
    }
    
    // Parse badges (could be comma-separated string or array)
    $badgeList = is_array($badges) ? $badges : explode(',', $badges);
    
    foreach ($badgeList as $badgeName) {
        $badgeName = trim($badgeName);
        if (empty($badgeName)) continue;
        
        // Find or create badge
        $badge = $db->get_row("SELECT id FROM IONBadges WHERE name = %s", $badgeName);
        
        if (!$badge) {
            // Create new badge if it doesn't exist
            $badgeId = $db->insert('IONBadges', [
                'name' => $badgeName,
                'description' => 'Auto-created badge',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            if (!$badgeId) {
                error_log('❌ Failed to create badge: ' . $badgeName);
                continue;
            }
            
            error_log('✅ Created new badge: ' . $badgeName . ' (ID: ' . $badgeId . ')');
        } else {
            $badgeId = $badge->id;
        }
        
        // Assign badge to video
        $assignment = $db->insert('IONVideoBadges', [
            'video_id' => $videoId,
            'badge_id' => $badgeId,
            'assigned_by' => $assignedBy,
            'assigned_at' => date('Y-m-d H:i:s')
        ]);
        
        if ($assignment) {
            error_log('✅ Assigned badge "' . $badgeName . '" to video ' . $videoId);
        } else {
            error_log('❌ Failed to assign badge "' . $badgeName . '" to video ' . $videoId);
        }
    }
}

/**
 * Extract platform info from URL
 */
function extractPlatformInfo($url) {
    // YouTube (supports regular videos, Shorts, and youtu.be links)
    if (preg_match('/(?:youtube\.com\/(?:watch\?v=|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $matches)) {
        return [
            'platform' => 'youtube',
            'video_id' => $matches[1],
            'url' => $url
        ];
    }
    
    // Vimeo - support multiple URL formats
    if (preg_match('/vimeo\.com\/(?:channels\/[^\/]+\/|groups\/[^\/]+\/videos\/|album\/[^\/]+\/video\/|video\/|)(\d+)/', $url, $matches)) {
        return [
            'platform' => 'vimeo',
            'video_id' => $matches[1],
            'url' => $url
        ];
    }
    
    // Muvi - support embed URLs
    if (preg_match('/(?:embed\.)?muvi\.com\/embed\/([a-zA-Z0-9]+)/', $url, $matches)) {
        return [
            'platform' => 'muvi',
            'video_id' => $matches[1],
            'url' => $url
        ];
    }
    
    error_log('❌ extractPlatformInfo - Could not extract platform info from URL: ' . $url);
    throw new Exception('Unsupported platform or invalid URL format. Supported: YouTube (including Shorts), Vimeo, Muvi');
}

/**
 * Generate unique slug from title
 */
function generateUniqueSlug($title) {
    global $db;
    
    // Create base slug from title
    $slug = strtolower(trim($title));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');
    
    // Limit length
    if (strlen($slug) > 200) {
        $slug = substr($slug, 0, 200);
    }
    
    // Ensure uniqueness
    $originalSlug = $slug;
    $counter = 1;
    
    while (true) {
        $existing = $db->get_row("SELECT id FROM IONLocalVideos WHERE slug = %s", $slug);
        if (!$existing) {
            return $slug;
        }
        $slug = $originalSlug . '-' . $counter;
        $counter++;
        
        // Prevent infinite loop
        if ($counter > 1000) {
            return $originalSlug . '-' . time();
        }
    }
}

/**
 * Generate unique short link
 */
function generateShortLink() {
    global $db;
    
    // Generate a random short code
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $maxAttempts = 10;
    
    for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
        $shortCode = '';
        for ($i = 0; $i < 8; $i++) {
            $shortCode .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        // Check if this short code already exists
        $existing = $db->get_row("SELECT id FROM IONLocalVideos WHERE short_link = %s", $shortCode);
        if (!$existing) {
            return $shortCode;
        }
    }
    
    // Fallback: use timestamp-based code if we can't generate unique code
    return 'v' . time() . rand(100, 999);
}

/**
 * Handle uploaded thumbnail file
 */
function handleThumbnailUpload($thumbnailFile) {
    try {
        // Create thumbs directory if it doesn't exist
        $uploadDir = __DIR__ . '/../uploads/thumbs/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $fileType = mime_content_type($thumbnailFile['tmp_name']);
        
        if (!in_array($fileType, $allowedTypes)) {
            error_log('❌ Invalid thumbnail type: ' . $fileType);
            return '';
        }
        
        // Generate unique filename
        $fileExt = pathinfo($thumbnailFile['name'], PATHINFO_EXTENSION);
        $fileName = 'thumb_' . uniqid() . '.' . $fileExt;
        $filePath = $uploadDir . $fileName;
        
        // Move uploaded file
        if (move_uploaded_file($thumbnailFile['tmp_name'], $filePath)) {
            $publicUrl = '/uploads/thumbs/' . $fileName;
            error_log("✅ Thumbnail saved: {$publicUrl}");
            return $publicUrl;
        } else {
            error_log("❌ Failed to save thumbnail");
            return '';
        }
    } catch (Exception $e) {
        error_log('❌ Thumbnail upload exception: ' . $e->getMessage());
        return '';
    }
}

/**
 * Handle thumbnail from base64 data (video frame capture)
 */
function handleThumbnailFromData($base64Data, $videoName) {
    try {
        // Remove data URI prefix if present
        if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $matches)) {
            $imageType = $matches[1];
            $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
        } else {
            $imageType = 'png'; // Default
        }
        
        // Decode base64
        $imageData = base64_decode($base64Data);
        if ($imageData === false) {
            error_log('❌ Failed to decode thumbnail data');
            return '';
        }
        
        // Create thumbs directory if it doesn't exist
        $uploadDir = __DIR__ . '/../uploads/thumbs/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Generate unique filename
        $fileName = 'thumb_' . uniqid() . '.' . $imageType;
        $filePath = $uploadDir . $fileName;
        
        // Save image
        if (file_put_contents($filePath, $imageData)) {
            $publicUrl = '/uploads/thumbs/' . $fileName;
            error_log("✅ Thumbnail saved from data: {$publicUrl}");
            return $publicUrl;
        } else {
            error_log("❌ Failed to save thumbnail from data");
            return '';
        }
    } catch (Exception $e) {
        error_log('❌ Thumbnail data exception: ' . $e->getMessage());
        return '';
    }
}

/**
 * Handle thumbnail from URL (CORS fallback - fetch on server-side)
 */
function handleThumbnailFromUrl($thumbnailUrl, $videoName = 'video') {
    try {
        error_log('📥 Fetching thumbnail from URL: ' . $thumbnailUrl);
        
        // Fetch image from URL using cURL (no CORS restrictions server-side)
        $ch = curl_init($thumbnailUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Allow HTTPS
        $imageData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        
        if ($httpCode !== 200 || empty($imageData)) {
            error_log('❌ Failed to fetch thumbnail: HTTP ' . $httpCode);
            return '';
        }
        
        // Determine file extension from content type
        $fileExt = 'jpg'; // Default
        if (strpos($contentType, 'png') !== false) $fileExt = 'png';
        elseif (strpos($contentType, 'gif') !== false) $fileExt = 'gif';
        elseif (strpos($contentType, 'webp') !== false) $fileExt = 'webp';
        
        // Create thumbs directory if it doesn't exist
        $uploadDir = __DIR__ . '/../uploads/thumbs/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Generate unique filename
        $fileName = 'thumb_' . uniqid() . '.' . $fileExt;
        $filePath = $uploadDir . $fileName;
        
        // Save image
        if (file_put_contents($filePath, $imageData)) {
            $publicUrl = '/uploads/thumbs/' . $fileName;
            error_log("✅ Thumbnail fetched and saved from URL: {$publicUrl}");
            return $publicUrl;
        } else {
            error_log("❌ Failed to save thumbnail from URL");
            return '';
        }
    } catch (Exception $e) {
        error_log('❌ Thumbnail URL fetch exception: ' . $e->getMessage());
        return '';
    }
}

/**
 * Handle video update/edit
 */
function handleVideoUpdate() {
    global $db;
    
    try {
        $videoId = intval($_POST['video_id'] ?? 0);
        
        if ($videoId <= 0) {
            throw new Exception('Invalid video ID');
        }
        
        // Build update data
        $updateData = [];
        
        if (isset($_POST['title'])) $updateData['title'] = trim($_POST['title']);
        if (isset($_POST['description'])) $updateData['description'] = trim($_POST['description']);
        if (isset($_POST['category'])) $updateData['category'] = trim($_POST['category']);
        if (isset($_POST['tags'])) $updateData['tags'] = trim($_POST['tags']);
        if (isset($_POST['visibility'])) $updateData['visibility'] = trim($_POST['visibility']);
        
        // Handle thumbnail upload if provided
        error_log('🔍 UPDATE DEBUG: Checking for thumbnail...');
        error_log('🔍 UPDATE DEBUG: $_FILES[thumbnail] exists: ' . (isset($_FILES['thumbnail']) ? 'YES' : 'NO'));
        if (isset($_FILES['thumbnail'])) {
            error_log('🔍 UPDATE DEBUG: $_FILES[thumbnail][error]: ' . ($_FILES['thumbnail']['error'] ?? 'NOT SET'));
            error_log('🔍 UPDATE DEBUG: $_FILES[thumbnail][size]: ' . ($_FILES['thumbnail']['size'] ?? 'NOT SET'));
        }
        error_log('🔍 UPDATE DEBUG: $_POST[thumbnail_data] exists: ' . (!empty($_POST['thumbnail_data']) ? 'YES' : 'NO'));
        
        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
            error_log('📸 UPDATE: Thumbnail file uploaded: ' . $_FILES['thumbnail']['name'] . ' (' . $_FILES['thumbnail']['size'] . ' bytes)');
            $thumbnailUrl = handleThumbnailUpload($_FILES['thumbnail']);
            if ($thumbnailUrl) {
                $updateData['thumbnail'] = $thumbnailUrl;
                error_log('✅ UPDATE: Thumbnail saved at: ' . $thumbnailUrl);
            } else {
                error_log('❌ UPDATE: Thumbnail save failed');
            }
        } elseif (isset($_POST['thumbnail_data']) && !empty($_POST['thumbnail_data'])) {
            // Handle base64 thumbnail data (from frame capture)
            error_log('📸 UPDATE: Thumbnail data (base64) provided, length: ' . strlen($_POST['thumbnail_data']));
            $thumbnailUrl = handleThumbnailFromData($_POST['thumbnail_data']);
            if ($thumbnailUrl) {
                $updateData['thumbnail'] = $thumbnailUrl;
                error_log('✅ UPDATE: Thumbnail saved at: ' . $thumbnailUrl);
            } else {
                error_log('❌ UPDATE: Thumbnail save from data failed');
            }
        } else {
            error_log('⚠️ UPDATE: No thumbnail provided');
        }
        
        // Handle channel updates
        if (isset($_POST['selected_channels'])) {
            $selectedChannels = json_decode($_POST['selected_channels'], true);
            
            if (!empty($selectedChannels) && is_array($selectedChannels)) {
                error_log('📺 Updating channels for video ' . $videoId);
                
                // First channel = Primary (update slug)
                $primaryChannelSlug = $selectedChannels[0];
                $updateData['slug'] = $primaryChannelSlug;
                error_log('✅ Primary channel updated to: ' . $primaryChannelSlug);
                
                // Remove all existing distributed channels for this video
                $db->query($db->prepare(
                    "DELETE FROM IONLocalBlast WHERE video_id = %d",
                    $videoId
                ));
                
                // Add new distributed channels (all except first)
                if (count($selectedChannels) > 1) {
                    $distributionChannels = array_slice($selectedChannels, 1);
                    $category = $updateData['category'] ?? 'General';
                    
                    foreach ($distributionChannels as $channelSlug) {
                        // Verify channel exists
                        $channelExists = $db->get_var(
                            "SELECT slug FROM IONLocalNetwork WHERE slug = %s LIMIT 1",
                            $channelSlug
                        );
                        
                        if (!$channelExists) {
                            error_log('⚠️ Channel not found: ' . $channelSlug);
                            continue;
                        }
                        
                        // Insert into IONLocalBlast
                        $db->query($db->prepare(
                            "INSERT INTO IONLocalBlast (
                                video_id, channel_slug, category, published_at, 
                                status, priority, added_at
                            ) VALUES (
                                %d, %s, %s, NOW(), 
                                'active', 0, NOW()
                            )",
                            $videoId,
                            $channelSlug,
                            $category
                        ));
                        
                        error_log('✅ Video distributed to channel: ' . $channelSlug);
                    }
                }
            }
        }
        
        // Update the video
        if (!empty($updateData)) {
            $result = $db->update(
                'IONLocalVideos',
                $updateData,
                ['id' => $videoId]
            );
            
            if ($result === false) {
                throw new Exception('Database update failed: ' . ($db->last_error ?? 'Unknown error'));
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Video updated successfully',
            'video_id' => $videoId
        ]);
        
    } catch (Exception $e) {
        error_log('❌ Video update error: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Handle Google Drive import - download from Google Drive and upload to R2
 */
function handleGoogleDriveImport() {
    global $config, $db;
    
    error_log('🎬 handleGoogleDriveImport called');
    error_log('🎬 POST data: ' . json_encode($_POST));
    
    // Verify user is logged in
    if (empty($_SESSION['user_id'])) {
        throw new Exception('User not authenticated');
    }
    
    $userId = (int)$_SESSION['user_id'];
    $userEmail = $_SESSION['user_email'] ?? '';
    
    // Get Google Drive file info from POST
    $fileId = $_POST['google_drive_file_id'] ?? '';
    $accessToken = $_POST['google_drive_access_token'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? 'General';
    $tags = $_POST['tags'] ?? '';
    $visibility = $_POST['visibility'] ?? 'public';
    
    if (empty($fileId) || empty($accessToken)) {
        throw new Exception('Missing Google Drive file ID or access token');
    }
    
    if (empty($title)) {
        throw new Exception('Video title is required');
    }
    
    error_log("📂 Importing Google Drive file: $fileId for user $userId");
    
    // Get file metadata from Google Drive
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://www.googleapis.com/drive/v3/files/$fileId?fields=name,size,mimeType");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $accessToken"
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        error_log("❌ Google Drive API error: HTTP $httpCode - $response");
        
        // Handle authentication errors gracefully
        if ($httpCode === 401 || $httpCode === 403) {
            http_response_code(403);
            throw new Exception('Your Google Drive session has expired. Please reconnect your account.');
        }
        
        throw new Exception('Failed to get file info from Google Drive');
    }
    
    $fileInfo = json_decode($response, true);
    $fileName = $fileInfo['name'] ?? 'video.mp4';
    $fileSize = $fileInfo['size'] ?? 0;
    $mimeType = $fileInfo['mimeType'] ?? 'video/mp4';
    
    error_log("📄 File info - Name: $fileName, Size: $fileSize, Type: $mimeType");
    
    // Generate unique filename for R2
    $extension = pathinfo($fileName, PATHINFO_EXTENSION);
    $uniqueFileName = uniqid('gd_' . $userId . '_', true) . '.' . $extension;
    
    // Create a temporary file to download the Google Drive file
    $tempFilePath = sys_get_temp_dir() . '/' . $uniqueFileName;
    error_log("💾 Downloading to temp file: $tempFilePath");
    
    // Download file from Google Drive
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://www.googleapis.com/drive/v3/files/$fileId?alt=media");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $accessToken"
    ]);
    
    $fp = fopen($tempFilePath, 'w+');
    curl_setopt($ch, CURLOPT_FILE, $fp);
    
    $success = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);
    
    if (!$success || $httpCode !== 200) {
        if (file_exists($tempFilePath)) unlink($tempFilePath);
        
        // Handle authentication errors gracefully
        if ($httpCode === 401 || $httpCode === 403) {
            http_response_code(403);
            throw new Exception('Your Google Drive session has expired. Please reconnect your account.');
        }
        
        throw new Exception("Failed to download file from Google Drive (HTTP $httpCode)");
    }
    
    error_log("✅ File downloaded successfully: " . filesize($tempFilePath) . " bytes");
    
    // Upload to R2 using direct HTTP/S3 API (no SDK needed)
    error_log("☁️ Uploading to Cloudflare R2...");
    
    $r2Config = $config['cloudflare_r2_api'];
    $objectKey = 'videos/' . date('Y/m/d') . '/' . $uniqueFileName;
    
    try {
        // Upload file directly to R2
        $uploaded = uploadFileToR2($tempFilePath, $objectKey, $mimeType, $r2Config);
        
        if (!$uploaded) {
            throw new Exception('Failed to upload file to R2');
        }
        
        // Generate public URL
        $videoUrl = rtrim($r2Config['public_url_base'], '/') . '/' . $objectKey;
        
        error_log("✅ R2 upload successful: $videoUrl");
        
    } catch (Exception $e) {
        if (file_exists($tempFilePath)) unlink($tempFilePath);
        error_log("❌ R2 upload failed: " . $e->getMessage());
        throw new Exception('Failed to upload to cloud storage: ' . $e->getMessage());
    }
    
    // Clean up temp file
    if (file_exists($tempFilePath)) {
        unlink($tempFilePath);
        error_log("🗑️ Temp file deleted");
    }
    
    // Generate slug and short link
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
    if (empty($slug)) $slug = 'video-' . uniqid();
    
    $shortLink = substr(md5($slug . time()), 0, 8);
    
    // Handle custom thumbnail upload (user-uploaded takes priority)
    $thumbnail = '';
    error_log('🔍 GOOGLE DRIVE - Checking for custom thumbnail...');
    error_log('🔍 GOOGLE DRIVE - $_FILES[thumbnail] exists: ' . (isset($_FILES['thumbnail']) ? 'YES' : 'NO'));
    error_log('🔍 GOOGLE DRIVE - $_POST[thumbnail_data] exists: ' . (!empty($_POST['thumbnail_data']) ? 'YES' : 'NO'));
    
    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
        error_log('📸 GOOGLE DRIVE - Custom thumbnail file uploaded: ' . $_FILES['thumbnail']['name'] . ' (' . $_FILES['thumbnail']['size'] . ' bytes)');
        $thumbnail = handleThumbnailUpload($_FILES['thumbnail']);
        error_log('📸 GOOGLE DRIVE - Custom thumbnail saved at: ' . ($thumbnail ?: 'FAILED'));
    } elseif (!empty($_POST['thumbnail_data'])) {
        error_log('📸 GOOGLE DRIVE - Custom thumbnail data (base64) provided, length: ' . strlen($_POST['thumbnail_data']));
        $thumbnail = handleThumbnailFromData($_POST['thumbnail_data'], $title);
        error_log('📸 GOOGLE DRIVE - Custom thumbnail saved at: ' . ($thumbnail ?: 'FAILED'));
    } else {
        error_log('📸 GOOGLE DRIVE - No custom thumbnail provided, will be auto-generated later');
    }
    
    // CRITICAL: Reconnect to database if connection timed out during long upload
    // Google Drive downloads can take several minutes, causing "MySQL server has gone away"
    error_log("🔄 Checking database connection...");
    try {
        $db->get_var("SELECT 1"); // Ping database
        error_log("✅ Database connection alive");
    } catch (Exception $e) {
        error_log("⚠️ Database connection lost, reconnecting...");
        // Database will auto-reconnect on next query
    }
    
    // Save to database
    $insertData = [
        'user_id' => $userId,
        'title' => $title,
        'slug' => $slug,
        'short_link' => $shortLink,
        'description' => $description,
        'video_link' => $videoUrl,
        'thumbnail' => $thumbnail, // Use custom thumbnail if provided
        'source' => 'googledrive',
        'video_id' => $fileId,
        'category' => $category,
        'tags' => $tags,
        'visibility' => $visibility,
        'status' => 'Approved',  // Changed from 'Published' to match enum
        'videotype' => 'video',
        'layout' => 'Wide',  // Changed from 'default' to match enum ('Wide','Tall','Short')
        'published_at' => date('Y-m-d H:i:s')
        // Note: 'date_added' auto-fills with current_timestamp(), don't include it
    ];
    
    error_log("💾 Inserting video into database...");
    $videoId = $db->insert('IONLocalVideos', $insertData);
    
    if (!$videoId) {
        $dbError = $db->last_error ?? 'Unknown database error';
        error_log('❌ Google Drive import - Database insert failed: ' . $dbError);
        
        // Check if this is a duplicate entry error
        if (strpos($dbError, 'Duplicate entry') !== false || strpos($dbError, '1062') !== false) {
            throw new Exception('This video has already been imported from Google Drive. Please check your uploaded videos or try a different video.');
        }
        
        throw new Exception('Failed to save video to database: ' . $dbError);
    }
    
    error_log("✅ Google Drive import complete - Video ID: $videoId");
    
    // Generate Enhanced Share template for this video
    $shareTemplate = generateShareTemplate($videoId, $shortLink, $title, $db);
    
    echo json_encode([
        'success' => true,
        'message' => 'Video imported from Google Drive successfully',
        'video_id' => $videoId,
        'video_url' => $videoUrl,
        'slug' => $slug,
        'short_link' => $shortLink,
        'shortlink' => $shortLink, // Add this for consistency with other uploads
        'title' => $title,
        'thumbnail' => $thumbnail, // Use the thumbnail we saved
        'celebration' => true, // Trigger celebration dialog
        'share_template' => $shareTemplate // Enhanced ION Share template for this video
    ]);
}

/**
 * Upload file to R2 using direct S3 API (AWS Signature V4)
 */
function uploadFileToR2($filePath, $objectKey, $contentType, $r2Config) {
    $endpoint = $r2Config['endpoint'];
    $bucket = $r2Config['bucket_name'];
    $accessKey = $r2Config['access_key_id'];
    $secretKey = $r2Config['secret_access_key'];
    
    $url = "$endpoint/$bucket/$objectKey";
    $urlParts = parse_url($url);
    $host = $urlParts['host'];
    $path = $urlParts['path'];
    
    // Read file content
    $fileContent = file_get_contents($filePath);
    $contentLength = strlen($fileContent);
    
    // AWS SigV4 requires specific date format
    $timestamp = gmdate('Ymd\THis\Z');
    $date = gmdate('Ymd');
    
    $service = 's3';
    $region = 'auto';
    
    // Prepare headers
    $canonicalHeaders = [
        'content-type' => $contentType,
        'host' => $host,
        'x-amz-content-sha256' => hash('sha256', $fileContent),
        'x-amz-date' => $timestamp
    ];
    
    // Sort headers alphabetically
    ksort($canonicalHeaders);
    
    // Build canonical request
    $canonicalHeadersStr = '';
    $signedHeadersStr = '';
    foreach ($canonicalHeaders as $key => $value) {
        $canonicalHeadersStr .= strtolower($key) . ':' . trim($value) . "\n";
        $signedHeadersStr .= strtolower($key) . ';';
    }
    $signedHeadersStr = rtrim($signedHeadersStr, ';');
    
    $hashedPayload = hash('sha256', $fileContent);
    
    // Build canonical request
    $canonicalRequest = "PUT\n" .
                       $path . "\n" .
                       "\n" . // query string
                       $canonicalHeadersStr . "\n" .
                       $signedHeadersStr . "\n" .
                       $hashedPayload;
    
    $hashedCanonicalRequest = hash('sha256', $canonicalRequest);
    
    // Build string to sign
    $credentialScope = "$date/$region/$service/aws4_request";
    $stringToSign = "AWS4-HMAC-SHA256\n" .
                   $timestamp . "\n" .
                   $credentialScope . "\n" .
                   $hashedCanonicalRequest;
    
    // Calculate signature
    $kDate = hash_hmac('sha256', $date, 'AWS4' . $secretKey, true);
    $kRegion = hash_hmac('sha256', $region, $kDate, true);
    $kService = hash_hmac('sha256', $service, $kRegion, true);
    $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
    $signature = hash_hmac('sha256', $stringToSign, $kSigning);
    
    // Build authorization header
    $authorization = "AWS4-HMAC-SHA256 Credential=$accessKey/$credentialScope, " .
                    "SignedHeaders=$signedHeadersStr, " .
                    "Signature=$signature";
    
    // Upload file
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_HTTPHEADER => [
            "Host: $host",
            "Content-Type: $contentType",
            "Content-Length: $contentLength",
            "x-amz-content-sha256: " . hash('sha256', $fileContent),
            "x-amz-date: $timestamp",
            "Authorization: $authorization"
        ],
        CURLOPT_POSTFIELDS => $fileContent,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 300 // 5 minutes for large files
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        error_log("❌ R2 upload CURL error: $curlError");
        throw new Exception("R2 upload failed: $curlError");
    }
    
    if ($httpCode !== 200) {
        error_log("❌ R2 upload HTTP error $httpCode: $response");
        throw new Exception("R2 upload failed: HTTP $httpCode");
    }
    
    return true;
}

/**
 * Generate Enhanced ION Share template for a video (same as /share/)
 */
function generateShareTemplate($videoId, $shortLink, $title, $db) {
    try {
        // Generate video URL
        $videoUrl = "https://iblog.bz/v/" . $shortLink;
        
        // Simple template HTML (same structure as Enhanced Share Manager)
        $template = '<script type="text/template" id="enhanced-share-template-' . $videoId . '">
        <div class="enhanced-share-modal-content" style="position: relative; background: linear-gradient(135deg, #1a1a1a 0%, #0a0a0a 100%); border-radius: 16px; max-width: 600px; width: 90vw; max-height: 90vh; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.8); border: 1px solid #333;">
            <button class="enhanced-share-close" onclick="window.EnhancedIONShare.closeModal(\'enhanced-share-modal-global\')" style="position: absolute; top: 16px; right: 16px; background: rgba(255,255,255,0.1); border: none; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 10; transition: all 0.2s;" onmouseover="this.style.background=\'rgba(255,255,255,0.2)\'" onmouseout="this.style.background=\'rgba(255,255,255,0.1)\'">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                    <path d="M18 6L6 18M6 6l12 12"/>
                </svg>
            </button>
            
            <div class="share-tab-content" id="share-tab-' . $videoId . '" style="padding: 24px;">
                <h3 style="margin: 0 0 20px 0; font-size: 20px; font-weight: 700; color: #e5e5e5;">Share This Video</h3>
                
                <div class="share-url-section" style="margin-bottom: 24px;">
                    <label style="display: block; margin-bottom: 8px; font-size: 16px; font-weight: 700; color: #e5e5e5;">Share Link</label>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <input type="text" value="' . htmlspecialchars($videoUrl) . '" readonly id="enhanced-share-url-' . $videoId . '" style="flex: 1; padding: 8px 12px; background: #2a2a2a; border: 1px solid #444; border-radius: 6px; color: white; font-size: 14px;">
                        <button onclick="window.open(\'' . htmlspecialchars($videoUrl, ENT_QUOTES) . '\', \'_blank\')" title="Open link in new tab" style="padding: 8px 12px; background: #2a2a2a; color: #3b82f6; border: 1px solid #444; border-radius: 6px; cursor: pointer; font-size: 14px; white-space: nowrap; display: flex; align-items: center; transition: all 0.2s;" onmouseover="this.style.background=\'#333\'; this.style.borderColor=\'#3b82f6\'" onmouseout="this.style.background=\'#2a2a2a\'; this.style.borderColor=\'#444\'">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                <polyline points="15 3 21 3 21 9"></polyline>
                                <line x1="10" y1="14" x2="21" y2="3"></line>
                            </svg>
                        </button>
                        <button onclick="EnhancedIONShare.copyText(\'enhanced-share-url-' . $videoId . '\')" style="padding: 8px 16px; background: #3b82f6; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; white-space: nowrap;">Copy</button>
                    </div>
                </div>
                
                <div class="share-platforms-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; margin-bottom: 20px;">
                    <a href="https://www.facebook.com/sharer/sharer.php?u=' . urlencode($videoUrl) . '" target="_blank" class="share-platform-btn" style="display: flex; align-items: center; gap: 8px; padding: 10px 12px; background: #2a2a2a; border: 1px solid #444; border-radius: 8px; color: white; text-decoration: none; font-size: 13px; transition: all 0.2s;" onmouseover="this.style.background=\'#333\'" onmouseout="this.style.background=\'#2a2a2a\'">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="#1877f2"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                        <span>Facebook</span>
                    </a>
                    <a href="https://twitter.com/intent/tweet?url=' . urlencode($videoUrl) . '&text=' . urlencode($title) . '" target="_blank" class="share-platform-btn" style="display: flex; align-items: center; gap: 8px; padding: 10px 12px; background: #2a2a2a; border: 1px solid #444; border-radius: 8px; color: white; text-decoration: none; font-size: 13px; transition: all 0.2s;" onmouseover="this.style.background=\'#333\'" onmouseout="this.style.background=\'#2a2a2a\'">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="#1da1f2"><path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/></svg>
                        <span>Twitter</span>
                    </a>
                    <a href="https://wa.me/?text=' . urlencode($title . ' ' . $videoUrl) . '" target="_blank" class="share-platform-btn" style="display: flex; align-items: center; gap: 8px; padding: 10px 12px; background: #2a2a2a; border: 1px solid #444; border-radius: 8px; color: white; text-decoration: none; font-size: 13px; transition: all 0.2s;" onmouseover="this.style.background=\'#333\'" onmouseout="this.style.background=\'#2a2a2a\'">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="#25d366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                        <span>WhatsApp</span>
                    </a>
                    <a href="https://www.linkedin.com/sharing/share-offsite/?url=' . urlencode($videoUrl) . '" target="_blank" class="share-platform-btn" style="display: flex; align-items: center; gap: 8px; padding: 10px 12px; background: #2a2a2a; border: 1px solid #444; border-radius: 8px; color: white; text-decoration: none; font-size: 13px; transition: all 0.2s;" onmouseover="this.style.background=\'#333\'" onmouseout="this.style.background=\'#2a2a2a\'">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="#0077b5"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                        <span>LinkedIn</span>
                    </a>
                </div>
            </div>
        </div>
    </script>';
        
        return $template;
    } catch (Exception $e) {
        error_log('❌ Error generating share template: ' . $e->getMessage());
        return ''; // Return empty string if generation fails
    }
}
?>
