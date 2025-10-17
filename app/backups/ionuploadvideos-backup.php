<?php
/**
 * ION Video Upload Endpoint
 * Handles ALL upload types: Single, Bulk, Local Files, Google Drive, Platform Imports
 * Consolidates: upload-video-handler.php + r2-multipart-handler.php + background-upload-handler.php
 */

// Start output buffering to prevent any stray output from breaking JSON responses
ob_start();

// Start session for user authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Disable error display to prevent HTML errors from breaking JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Set execution limits for large uploads
set_time_limit(0);
ini_set('memory_limit', '1G');
ini_set('max_execution_time', 0);

// Load configuration
$config_path = __DIR__ . '/../config/config.php';
if (!file_exists($config_path)) {
    die(json_encode(['error' => 'Config file not found at: ' . $config_path]));
}
$config = require_once $config_path;
if (!is_array($config)) {
    die(json_encode(['error' => 'Config file did not return an array']));
}

// Load ION Database
try {
    require_once __DIR__ . '/../config/IONDatabase.php';
} catch (Exception $e) {
    // IONDatabase might not exist, continue without it
}

try {
    require_once __DIR__ . '/../config/database.php';
} catch (Exception $e) {
    // database.php might not exist, continue without it
}

// CORS headers for AJAX requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Prevent caching of this endpoint
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Initialize database connection
try {
    $db = new IONDatabase($config);
    global $db;
    // Create $wpdb alias for compatibility
    $wpdb = $db;
} catch (Exception $e) {
    // If database connection fails, continue without it for now
    $db = null;
    $wpdb = null;
}

// Main upload router
try {
    $action = $_POST['action'] ?? $_GET['action'] ?? 'upload';
    error_log('ION Upload: Action received: "' . $action . '"');
    error_log('ION Upload: GET params: ' . json_encode($_GET));
    error_log('ION Upload: POST params: ' . json_encode($_POST));
    
    switch ($action) {
        case 'upload':
        case 'file':
            handleFileUpload();
            break;
            
        case 'multipart_init':
            handleR2MultipartInit();
            break;
            
        case 'multipart_complete':
            break;
            
        case 'multipart_abort':
            handleR2MultipartAbort();
            break;
            
        case 'import':
        case 'platform':
        case 'platform_import':
            handlePlatformImport();
            break;
            
        case 'test_db':
            error_log('ION Upload: test_db case matched!');
            testDatabaseConnection();
            break;
            
        case 'debug':
            global $config;
            ob_clean(); // Clear any previous output
            
            echo json_encode([
                'success' => true,
                'message' => 'Debug endpoint working - ' . date('Y-m-d H:i:s'),
                'timestamp' => time(),
                'config_loaded' => isset($config),
                'config_type' => gettype($config ?? null),
                'file_version' => 'v2.0'
            ]);
            exit; // Force exit to prevent any other output
            break;
            
        case 'cleanup_empty':
            cleanupEmptyVideos();
            break;
            
        case 'simple_upload':
            handleSimpleUpload();
            break;
            
        case 'google_drive':
            handleGoogleDriveUpload();
            break;
            
        case 'background_transfer':
            handleBackgroundTransfer();
            break;
            
        case 'delete_video':
            handleDeleteVideo();
            break;
            
        case 'update_video':
            handleUpdateVideo();
            break;
            
        default:
            throw new Exception('Invalid action: ' . $action);
    }
    
} catch (Exception $e) {
    error_log('[ION Upload] Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}

/**
 * Handle regular file uploads (local files)
 */
function handleFileUpload() {
    global $wpdb;
    
    if (!isset($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No valid file uploaded');
    }
    
    $file = $_FILES['video'];
    $metadata = extractUploadMetadata();
    
    // Upload to R2 storage
    $r2Result = uploadToR2($file);
    
    // Create database record
    $videoId = createVideoRecord($r2Result, $metadata);
    
    // Trigger optimization
    triggerOptimization($videoId, $r2Result['url'], $metadata);
    
    echo json_encode([
        'success' => true,
        'video_id' => $videoId,
        'url' => $r2Result['url'],
        'message' => 'Upload completed successfully'
    ]);
}

/**
 * Handle R2 multipart upload initialization
 */
function handleR2MultipartInit() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $fileName = $input['fileName'] ?? '';
    $fileSize = $input['fileSize'] ?? 0;
    $contentType = $input['contentType'] ?? 'application/octet-stream';
    
    // Generate unique upload ID
    $uploadId = uniqid('r2_', true);
    
    // Initialize R2 multipart upload
    $r2UploadId = initializeR2Multipart($fileName, $contentType);
    
    // Store upload session
    storeUploadSession($uploadId, $r2UploadId, $input);
    
    echo json_encode([
        'success' => true,
        'uploadId' => $uploadId,
        'r2UploadId' => $r2UploadId
    ]);
}

/**
 * Handle R2 multipart upload completion
 */
function handleR2MultipartComplete() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $uploadId = $input['uploadId'] ?? '';
    $parts = $input['parts'] ?? [];
    
    // Get upload session
    $session = getUploadSession($uploadId);
    
    // Complete R2 multipart upload
    $r2Result = completeR2Multipart($session['r2_upload_id'], $parts);
    
    // Create database record
    $metadata = json_decode($session['metadata'], true);
    $videoId = createVideoRecord($r2Result, $metadata);
    
    // Trigger optimization
    triggerOptimization($videoId, $r2Result['url'], $metadata);
    
    // Clean up session
    cleanupUploadSession($uploadId);
    
    echo json_encode([
        'success' => true,
        'video_id' => $videoId,
        'url' => $r2Result['url'],
        'message' => 'Multipart upload completed successfully'
    ]);
}

/**
 * Handle platform imports (YouTube, Vimeo, etc.)
 */
function handlePlatformImport() {
    try {
        error_log('ION Platform Import: Starting platform import');
        
        $url = $_POST['url'] ?? '';
        if (empty($url)) {
            throw new Exception('URL is required for platform import');
        }
        
        error_log('ION Platform Import: URL received: ' . $url);
        
        $metadata = extractUploadMetadata();
        error_log('ION Platform Import: Metadata extracted: ' . json_encode($metadata));
        
        // Extract platform info
        $platformInfo = extractPlatformInfo($url);
        if (!$platformInfo) {
            throw new Exception('Unable to extract platform information from URL');
        }
        
        error_log('ION Platform Import: Platform info: ' . json_encode($platformInfo));
        
        // Create database record for platform import
        error_log('ION Platform Import: About to create video record with data: ' . json_encode($platformInfo) . ' | ' . json_encode($metadata));
        $videoId = createPlatformVideoRecord($platformInfo, $metadata);
        error_log('ION Platform Import: createPlatformVideoRecord returned: ' . var_export($videoId, true));
        
        if (!$videoId) {
            error_log('ION Platform Import: Video creation failed - checking database connection');
            global $db;
            if ($db && $db->last_error) {
                error_log('ION Platform Import: Database error: ' . $db->last_error);
            }
            throw new Exception('Failed to create video record in database');
        }
        
        error_log('ION Platform Import: Video created with ID: ' . $videoId);
        
        // Generate short link for the video
        $shortLink = generateShortLink($videoId);
        if ($shortLink) {
            // Update the video record with the short link
            $db->update('IONLocalVideos', ['short_link' => $shortLink], ['id' => $videoId]);
            error_log('ION Platform Import: Short link generated: ' . $shortLink);
        }
        
        // Get the video data for the celebration dialog
        $videoData = $db->get_row("SELECT title, thumbnail, short_link FROM IONLocalVideos WHERE id = ?", $videoId);
        
        // Clean any output buffer before sending JSON
        ob_clean();
        echo json_encode([
            'success' => true,
            'video_id' => $videoId,
            'platform' => $platformInfo['platform'],
            'shortlink' => $shortLink,
            'title' => $videoData->title ?? '',
            'thumbnail' => $videoData->thumbnail ?? '',
            'message' => 'Platform import completed successfully',
            'celebration' => true // Flag to trigger celebration dialog
        ]);
        
    } catch (Exception $e) {
        error_log('ION Platform Import: Error - ' . $e->getMessage());
        // Clean any output buffer before sending JSON
        ob_clean();
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Handle Google Drive uploads
 */
function handleGoogleDriveUpload() {
    $fileId = $_POST['google_drive_file_id'] ?? '';
    $accessToken = $_POST['google_drive_access_token'] ?? '';
    $background = $_POST['background'] ?? false;
    $metadata = extractUploadMetadata();
    
    if ($background) {
        // Queue for background processing
        $videoId = createDriveVideoRecord($fileId, $metadata, 'transferring');
        queueBackgroundTransfer($videoId, $fileId, $accessToken, $metadata);
        
        echo json_encode([
            'success' => true,
            'video_id' => $videoId,
            'status' => 'transferring',
            'message' => 'Google Drive transfer queued for background processing'
        ]);
    } else {
        // Process immediately
        $r2Result = transferFromGoogleDrive($fileId, $accessToken);
        $videoId = createVideoRecord($r2Result, $metadata);
        triggerOptimization($videoId, $r2Result['url'], $metadata);
        
        echo json_encode([
            'success' => true,
            'video_id' => $videoId,
            'url' => $r2Result['url'],
            'message' => 'Google Drive upload completed successfully'
        ]);
    }
}

/**
 * Test database connection and table structure
 */
function testDatabaseConnection() {
    global $db;
    
    try {
        error_log('ION DB Test: Starting database connection test');
        
        // Test 1: Check if database object exists
        if (!$db) {
            throw new Exception('Database object is null');
        }
        
        error_log('ION DB Test: Database object exists: ' . get_class($db));
        
        // Test 2: Check if we can connect
        if (!$db->isConnected()) {
            throw new Exception('Database connection failed');
        }
        
        error_log('ION DB Test: Database connection successful');
        
        // Test 3: Check if IONLocalVideos table exists
        $tables = $db->get_results("SHOW TABLES LIKE 'IONLocalVideos'");
        if (empty($tables)) {
            throw new Exception('IONLocalVideos table does not exist');
        }
        
        error_log('ION DB Test: IONLocalVideos table exists');
        
        // Test 4: Check table structure
        $columns = $db->get_results("DESCRIBE IONLocalVideos");
        error_log('ION DB Test: Table structure: ' . json_encode($columns));
        
        // Show table structure in response for debugging
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Database connection successful',
            'table_structure' => $columns
        ]);
        return;
        
        // Test 5: Try a simple insert
        $testData = [
            'title' => 'Test Video',
            'description' => 'Test Description',
            'video_link' => 'https://test.com',
            'category' => 'Test',
            'tags' => 'test',
            'visibility' => 'public',
            'user_email' => 'test@test.com',
            'date_added' => current_time('mysql'),
            'status' => 'Test',
            'source' => 'test',
            'video_id' => 'test123'
        ];
        
        $result = $db->insert('IONLocalVideos', $testData);
        
        if ($result) {
            $insertId = $db->insert_id;
            error_log('ION DB Test: Test insert successful, ID: ' . $insertId);
            
            // Clean up test record
            $db->delete('IONLocalVideos', ['id' => $insertId]);
            error_log('ION DB Test: Test record cleaned up');
            
            ob_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Database test successful',
                'insert_id' => $insertId
            ]);
        } else {
            throw new Exception('Test insert failed: ' . $db->last_error);
        }
        
    } catch (Exception $e) {
        error_log('ION DB Test: ERROR - ' . $e->getMessage());
        ob_clean();
        echo json_encode([
            'success' => false,
            'error' => 'Database test failed: ' . $e->getMessage()
        ]);
    }
}

/**
 * Handle simple upload without R2 (fallback method)
 */
function handleSimpleUpload() {
    global $db;
    
    $metadata = extractUploadMetadata();
    
    if (!isset($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No valid file uploaded');
    }
    
    $file = $_FILES['video'];
    $uploadDir = __DIR__ . '/../../uploads/';
    
    // Create upload directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('video_', true) . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Failed to save uploaded file');
    }
    
    // Create database record
    $videoData = [
        'title' => $metadata['title'],
        'description' => $metadata['description'],
        'video_link' => '/uploads/' . $filename,
        'category' => $metadata['category'],
        'tags' => $metadata['tags'],
        'visibility' => $metadata['visibility'],
        'user_email' => $metadata['user_email'],
        'date_added' => current_time('mysql'),
        'status' => 'Approved',
        'source' => 'upload',
        'file_size' => $file['size'],
        'mime_type' => $file['type']
    ];
    
    $result = $db->insert('IONLocalVideos', $videoData);
    
    if (!$result) {
        throw new Exception('Failed to create database record: ' . $db->last_error);
    }
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'video_id' => $db->insert_id,
        'message' => 'Video uploaded successfully'
    ]);
}

/**
 * Extract upload metadata from request
 */
function extractUploadMetadata() {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $metadata = [
        'title' => $_POST['title'] ?? 'Untitled',
        'description' => $_POST['description'] ?? '',
        'category' => $_POST['category'] ?? 'General',
        'tags' => $_POST['tags'] ?? '',
        'visibility' => $_POST['visibility'] ?? 'public',
        'user_email' => $_SESSION['user_email'] ?? 'unknown@example.com'
    ];
    
    error_log('ION Upload: Extracted metadata: ' . json_encode($metadata));
    error_log('ION Upload: Session data: ' . json_encode($_SESSION));
    
    return $metadata;
}

/**
 * Upload file to R2 storage
 */
function uploadToR2($file) {
    // R2 upload logic here
    $config = include(__DIR__ . '/../config/config.php');
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('video_', true) . '.' . $extension;
    
    // Upload to R2 (implementation depends on your R2 setup)
    $r2Url = uploadFileToR2($file['tmp_name'], $filename, $file['type']);
    
    return [
        'url' => $r2Url,
        'filename' => $filename,
        'size' => $file['size'],
        'type' => $file['type']
    ];
}

/**
 * Create video database record
 */
function createVideoRecord($r2Result, $metadata) {
    global $wpdb;
    
    $videoData = [
        'title' => $metadata['title'],
        'description' => $metadata['description'],
        'video_link' => $r2Result['url'],
        'category' => $metadata['category'],
        'tags' => $metadata['tags'],
        'visibility' => $metadata['visibility'],
        'user_email' => $metadata['user_email'],
        'date_added' => current_time('mysql'),
        'status' => 'Approved',
        'optimization_status' => 'pending',
        'file_size' => $r2Result['size'] ?? 0,
        'mime_type' => $r2Result['type'] ?? 'video/mp4'
    ];
    
    $wpdb->insert('IONLocalVideos', $videoData);
    return $wpdb->insert_id;
}

/**
 * Trigger optimization processing
 */
function triggerOptimization($videoId, $r2Url, $metadata) {
    // Queue optimization job
    require_once __DIR__ . '/ionuploadoptimizer.php';
    
    $optimizer = new IONUploadOptimizer();
    $optimizer->queueOptimization($videoId, $r2Url, $metadata);
}

/**
 * Handle background transfer from Google Drive
 */
function handleBackgroundTransfer() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validate required fields
    $required = ['fileId', 'accessToken', 'fileName', 'fileSize', 'mimeType'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    global $wpdb, $config;
    $user_email = $_SESSION['user_email'] ?? '';
    
    // Get user information
    $user_data = $wpdb->get_row($wpdb->prepare("SELECT user_id, user_role FROM IONEERS WHERE email = %s", $user_email));
    
    if (!$user_data) {
        throw new Exception('User not found in database');
    }
    
    // Generate unique transfer ID
    $transferId = 'transfer_' . uniqid() . '_' . time();
    
    // Prepare metadata
    $metadata = $input['metadata'] ?? [];
    $title = $metadata['title'] ?? pathinfo($input['fileName'], PATHINFO_FILENAME);
    $description = $metadata['description'] ?? '';
    $category = $metadata['category'] ?? 'General';
    $tags = $metadata['tags'] ?? '';
    $visibility = $metadata['visibility'] ?? 'public';
    
    // Insert initial record with "transferring" status
    $video_id = $input['fileId'];
    $video_link = "https://drive.google.com/file/d/{$video_id}/view";
    
    $insert_result = $wpdb->query($wpdb->prepare("
        INSERT INTO IONLocalVideos (
            user_id, title, description, category, tags, visibility, 
            video_link, video_id, source, status, file_size, mime_type,
            transfer_id, date_added
        ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d, %s, %s, NOW())
    ", 
        $user_data->user_id, $title, $description, $category, $tags, $visibility,
        $video_link, $video_id, 'googledrive', 'transferring', 
        intval($input['fileSize']), $input['mimeType'], $transferId
    ));
    
    if (!$insert_result) {
        throw new Exception('Failed to create database record');
    }
    
    $record_id = $wpdb->insert_id;
    
    // Prepare Cloudflare Worker request
    $worker_url = $config['cloudflare_worker_url'] ?? 'https://drive-to-r2.your-subdomain.workers.dev';
    
    $worker_data = [
        'fileId' => $input['fileId'],
        'accessToken' => $input['accessToken'],
        'fileName' => $input['fileName'],
        'fileSize' => $input['fileSize'],
        'mimeType' => $input['mimeType'],
        'bucketName' => $config['r2_bucket_name'] ?? 'ions-r2',
        'accountId' => $config['r2_account_id'] ?? '',
        'accessKeyId' => $config['r2_access_key_id'] ?? '',
        'secretAccessKey' => $config['r2_secret_access_key'] ?? '',
        'region' => 'auto',
        'transferId' => $transferId,
        'recordId' => $record_id,
        'callbackUrl' => $config['base_url'] . '/app/ionuploadvideos.php?action=transfer_callback'
    ];
    
    // Make async request to Cloudflare Worker
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $worker_url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($worker_data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'User-Agent: ION-Upload-System/1.0'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $worker_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        error_log("Worker request failed with HTTP $http_code: $worker_response");
        $wpdb->query($wpdb->prepare("UPDATE IONLocalVideos SET status = 'failed' WHERE id = %d", $record_id));
        throw new Exception('Failed to initiate background transfer');
    }
    
    $worker_result = json_decode($worker_response, true);
    
    if (!$worker_result || !$worker_result['success']) {
        error_log("Worker returned error: " . ($worker_result['error'] ?? 'Unknown error'));
        $wpdb->query($wpdb->prepare("UPDATE IONLocalVideos SET status = 'failed' WHERE id = %d", $record_id));
        throw new Exception($worker_result['error'] ?? 'Background transfer failed');
    }
    
    echo json_encode([
        'success' => true,
        'transferId' => $transferId,
        'recordId' => $record_id,
        'status' => 'transferring',
        'message' => 'Background transfer initiated successfully',
        'estimated_time' => ceil($input['fileSize'] / (10 * 1024 * 1024)) . ' minutes'
    ]);
}

/**
 * Handle transfer callback from Cloudflare Worker
 */
function handleTransferCallback() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    if (!isset($input['transferId']) || !isset($input['recordId']) || !isset($input['success'])) {
        throw new Exception('Missing required fields');
    }
    
    global $wpdb;
    $transferId = $input['transferId'];
    $recordId = intval($input['recordId']);
    $success = $input['success'];
    
    error_log("Transfer callback received: Transfer ID $transferId, Record ID $recordId, Success: " . ($success ? 'true' : 'false'));
    
    if ($success) {
        // Transfer completed successfully
        $r2_url = $input['r2_url'] ?? '';
        $file_size = intval($input['file_size'] ?? 0);
        $duration = $input['duration'] ?? null;
        
        // Update database record
        $update_query = "
            UPDATE IONLocalVideos 
            SET status = 'active', 
                video_link = %s, 
                file_size = %d,
                transfer_completed_at = NOW(),
                optimization_status = 'pending'
        ";
        $params = [$r2_url, $file_size];
        
        if ($duration) {
            $update_query .= ", duration = %s";
            $params[] = $duration;
        }
        
        $update_query .= " WHERE id = %d AND transfer_id = %s";
        $params[] = $recordId;
        $params[] = $transferId;
        
        $result = $wpdb->query($wpdb->prepare($update_query, ...$params));
        
        if (!$result) {
            throw new Exception('Failed to update database record');
        }
        
        // Trigger optimization
        $metadata = ['title' => '', 'description' => ''];
        triggerOptimization($recordId, $r2_url, $metadata);
        
        error_log("Transfer completed successfully: Record ID $recordId updated with R2 URL: $r2_url");
        
    } else {
        // Transfer failed
        $error_message = $input['error'] ?? 'Unknown error';
        
        $wpdb->query($wpdb->prepare("
            UPDATE IONLocalVideos 
            SET status = 'failed', 
                error_message = %s,
                transfer_completed_at = NOW()
            WHERE id = %d AND transfer_id = %s
        ", $error_message, $recordId, $transferId));
        
        error_log("Transfer failed: Record ID $recordId, Error: $error_message");
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Callback processed successfully'
    ]);
}

/**
 * Check transfer status
 */
function handleTransferStatus() {
    $transferId = $_GET['transferId'] ?? '';
    
    if (empty($transferId)) {
        throw new Exception('Transfer ID is required');
    }
    
    global $wpdb;
    $record = $wpdb->get_row($wpdb->prepare("
        SELECT id, status, title, video_link, error_message, 
               transfer_completed_at, date_added, file_size
        FROM IONLocalVideos 
        WHERE transfer_id = %s
    ", $transferId));
    
    if (!$record) {
        throw new Exception('Transfer not found');
    }
    
    $response = [
        'success' => true,
        'transferId' => $transferId,
        'recordId' => $record->id,
        'status' => $record->status,
        'title' => $record->title
    ];
    
    // Add status-specific information
    switch ($record->status) {
        case 'completed':
        case 'active':
            $response['shortlink'] = $record->video_link;
            $response['completedAt'] = $record->transfer_completed_at;
            $response['progress'] = 100;
            break;
            
        case 'failed':
            $response['error'] = $record->error_message ?: 'Transfer failed';
            break;
            
        case 'transferring':
            // Calculate rough progress based on time elapsed
            $startTime = strtotime($record->date_added);
            $currentTime = time();
            $elapsed = $currentTime - $startTime;
            
            $fileSize = intval($record->file_size);
            $estimatedDuration = max(60, $fileSize / (5 * 1024 * 1024)); // 5MB/s estimate
            $progress = min(95, ($elapsed / $estimatedDuration) * 100); // Cap at 95% until complete
            
            $response['progress'] = round($progress);
            $response['estimatedTimeRemaining'] = max(0, $estimatedDuration - $elapsed);
            break;
            
        default:
            $response['progress'] = 0;
    }
    
    echo json_encode($response);
}

/**
 * Initialize R2 multipart upload
 */
function initializeR2Multipart($fileName, $contentType) {
    global $config;
    
    // Generate unique object key
    $extension = pathinfo($fileName, PATHINFO_EXTENSION);
    $objectKey = 'uploads/' . uniqid('video_', true) . '.' . $extension;
    
    // Check if R2 configuration is available
    $r2_config = $config['cloudflare_r2_api'] ?? [];
    error_log('ION R2 Upload: R2 config check - ' . json_encode([
        'has_account_id' => !empty($r2_config['account_id']),
        'has_bucket_name' => !empty($r2_config['bucket_name']),
        'has_access_key_id' => !empty($r2_config['access_key_id']),
        'config_keys' => array_keys($r2_config)
    ]));
    
    if (empty($r2_config['account_id']) || empty($r2_config['bucket_name']) || empty($r2_config['access_key_id'])) {
        $debug_info = [
            'function' => 'initializeR2Multipart',
            'config_loaded' => isset($config),
            'r2_config_exists' => isset($config['cloudflare_r2_api']),
            'has_account_id' => !empty($r2_config['account_id']),
            'has_bucket_name' => !empty($r2_config['bucket_name']),
            'has_access_key_id' => !empty($r2_config['access_key_id']),
            'config_keys' => array_keys($r2_config),
            'full_config_keys' => array_keys($config ?? [])
        ];
        throw new Exception('R2 storage configuration is not properly set up. Debug: ' . json_encode($debug_info));
    }
    
    // Initialize multipart upload with R2 API
    $endpoint = "https://{$r2_config['account_id']}.r2.cloudflarestorage.com/{$r2_config['bucket_name']}";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $endpoint . '/' . $objectKey . '?uploads',
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: ' . $contentType,
            'Authorization: Bearer ' . $r2_config['access_key_id']
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("Failed to initialize R2 multipart upload: HTTP $httpCode");
    }
    
    // Parse upload ID from XML response
    $xml = simplexml_load_string($response);
    $uploadId = (string)$xml->UploadId;
    
    return [
        'upload_id' => $uploadId,
        'object_key' => $objectKey
    ];
}

/**
 * Complete R2 multipart upload
 */
function completeR2Multipart($uploadId, $parts) {
    $config = include(__DIR__ . '/../config/config.php');
    
    // Build completion XML
    $xml = '<?xml version="1.0" encoding="UTF-8"?><CompleteMultipartUpload>';
    foreach ($parts as $part) {
        $xml .= "<Part><PartNumber>{$part['PartNumber']}</PartNumber><ETag>{$part['ETag']}</ETag></Part>";
    }
    $xml .= '</CompleteMultipartUpload>';
    
    // Check if R2 configuration is available
    $r2_config = $config['cloudflare_r2_api'] ?? [];
    if (empty($r2_config['account_id']) || empty($r2_config['bucket_name']) || empty($r2_config['access_key_id'])) {
        throw new Exception('R2 storage configuration is not properly set up');
    }
    
    // Complete the upload
    $endpoint = "https://{$r2_config['account_id']}.r2.cloudflarestorage.com/{$r2_config['bucket_name']}";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $endpoint . '/' . $objectKey . '?uploadId=' . $uploadId,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $xml,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/xml',
            'Authorization: Bearer ' . $r2_config['access_key_id'],
            'Content-Length: ' . strlen($xml)
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("Failed to complete R2 multipart upload: HTTP $httpCode");
    }
    
    return [
        'url' => "$endpoint/{$uploadId['object_key']}",
        'object_key' => $uploadId['object_key']
    ];
}

/**
 * Store upload session data
 */
function storeUploadSession($uploadId, $r2UploadId, $metadata) {
    global $wpdb;
    
    $wpdb->insert('IONUploadSessions', [
        'upload_id' => $uploadId,
        'r2_upload_id' => json_encode($r2UploadId),
        'metadata' => json_encode($metadata),
        'created_at' => current_time('mysql'),
        'expires_at' => date('Y-m-d H:i:s', strtotime('+24 hours'))
    ]);
}

/**
 * Get upload session data
 */
function getUploadSession($uploadId) {
    global $wpdb;
    
    $session = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM IONUploadSessions WHERE upload_id = %s AND expires_at > NOW()",
        $uploadId
    ));
    
    if (!$session) {
        throw new Exception('Upload session not found or expired');
    }
    
    return [
        'r2_upload_id' => json_decode($session->r2_upload_id, true),
        'metadata' => $session->metadata
    ];
}

/**
 * Clean up upload session
 */
function cleanupUploadSession($uploadId) {
    global $wpdb;
    $wpdb->delete('IONUploadSessions', ['upload_id' => $uploadId]);
}

/**
 * Handle R2 multipart abort
 */
function handleR2MultipartAbort() {
    $input = json_decode(file_get_contents('php://input'), true);
    $uploadId = $input['uploadId'] ?? '';
    
    if ($uploadId) {
        cleanupUploadSession($uploadId);
    }
    
    echo json_encode(['success' => true, 'message' => 'Upload aborted']);
}

/**
 * Extract platform information from URL
 */
function extractPlatformInfo($url) {
    $platforms = [
        'youtube.com' => 'youtube',
        'youtu.be' => 'youtube',
        'vimeo.com' => 'vimeo',
        'loom.com' => 'loom',
        'wistia.com' => 'wistia'
    ];
    
    foreach ($platforms as $domain => $platform) {
        if (strpos($url, $domain) !== false) {
            return [
                'platform' => $platform,
                'url' => $url,
                'video_id' => extractVideoId($url, $platform)
            ];
        }
    }
    
    throw new Exception('Unsupported platform');
}

/**
 * Extract video ID from platform URL
 */
function extractVideoId($url, $platform) {
    switch ($platform) {
        case 'youtube':
            if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\n?#]+)/', $url, $matches)) {
                return $matches[1];
            }
            break;
        case 'vimeo':
            if (preg_match('/vimeo\.com\/(\d+)/', $url, $matches)) {
                return $matches[1];
            }
            break;
    }
    
    return null;
}

/**
 * Create platform video record
 */
function createPlatformVideoRecord($platformInfo, $metadata) {
    global $db;
    
    error_log('ION Platform Import: createPlatformVideoRecord called');
    error_log('ION Platform Import: Database object: ' . (is_object($db) ? get_class($db) : 'NOT AN OBJECT'));
    
    if (!$db) {
        error_log('ION Platform Import: ERROR - Database object is null');
        return false;
    }
    
    // Ensure tags is a string
    $tags = is_array($metadata['tags']) ? implode(',', $metadata['tags']) : $metadata['tags'];
    
    // Get user_id from session email
    $user_id = null;
    if (!empty($metadata['user_email'])) {
        $user_data = $db->get_row("SELECT user_id FROM IONEERS WHERE email = ?", $metadata['user_email']);
        if ($user_data) {
            $user_id = $user_data->user_id;
        }
    }
    
    // Generate thumbnail URL based on platform
    $thumbnail = '';
    if ($platformInfo['platform'] === 'youtube' && !empty($platformInfo['video_id'])) {
        $thumbnail = "https://img.youtube.com/vi/{$platformInfo['video_id']}/maxresdefault.jpg";
    } elseif ($platformInfo['platform'] === 'vimeo' && !empty($platformInfo['video_id'])) {
        // For Vimeo, we'll need to fetch the thumbnail via API, but for now use a placeholder
        $thumbnail = "https://vumbnail.com/{$platformInfo['video_id']}.jpg";
    }
    
    $videoData = [
        'title' => $metadata['title'],
        'description' => $metadata['description'],
        'video_link' => $platformInfo['url'],
        'category' => $metadata['category'],
        'tags' => $tags,
        'visibility' => ucfirst($metadata['visibility']), // Table expects 'Public', 'Private', 'Unlisted'
        'user_id' => $user_id,
        'date_added' => current_time('mysql'),
        'status' => 'Approved',
        'source' => ucfirst($platformInfo['platform']), // Table expects 'Youtube', 'Vimeo', etc.
        'video_id' => $platformInfo['video_id'],
        'slug' => uniqid('video_', true), // Required field
        'thumbnail' => $thumbnail // YouTube/Vimeo thumbnail URL
    ];
    
    error_log('ION Platform Import: Video data prepared: ' . json_encode($videoData));
    
    $result = $db->insert('IONLocalVideos', $videoData);
    
    if ($result === false) {
        error_log('ION Platform Import: Database insert failed. Error: ' . $db->last_error);
        error_log('ION Platform Import: Video data: ' . print_r($videoData, true));
        return false;
    }
    
    $insertId = $db->insert_id;
    if (!$insertId) {
        error_log('ION Platform Import: Insert succeeded but no insert_id returned');
        return false;
    }
    
    return $insertId;
}

/**
 * Create Google Drive video record
 */
function createDriveVideoRecord($fileId, $metadata, $status = 'active') {
    global $wpdb;
    
    $videoData = [
        'title' => $metadata['title'],
        'description' => $metadata['description'],
        'video_link' => "https://drive.google.com/file/d/{$fileId}/view",
        'category' => $metadata['category'],
        'tags' => $metadata['tags'],
        'visibility' => $metadata['visibility'],
        'user_email' => $metadata['user_email'],
        'date_added' => current_time('mysql'),
        'status' => $status,
        'source' => 'googledrive',
        'video_id' => $fileId
    ];
    
    $wpdb->insert('IONLocalVideos', $videoData);
    return $wpdb->insert_id;
}

/**
 * Upload file directly to R2
 */
function uploadFileToR2($filePath, $filename, $mimeType) {
    global $config;
    
    // Check if R2 configuration is available
    $r2_config = $config['cloudflare_r2_api'] ?? [];
    error_log('ION R2 Direct Upload: R2 config check - ' . json_encode([
        'has_account_id' => !empty($r2_config['account_id']),
        'has_bucket_name' => !empty($r2_config['bucket_name']),
        'has_access_key_id' => !empty($r2_config['access_key_id']),
        'config_keys' => array_keys($r2_config)
    ]));
    
    if (empty($r2_config['account_id']) || empty($r2_config['bucket_name']) || empty($r2_config['access_key_id'])) {
        $debug_info = [
            'config_loaded' => isset($config),
            'r2_config_exists' => isset($config['cloudflare_r2_api']),
            'has_account_id' => !empty($r2_config['account_id']),
            'has_bucket_name' => !empty($r2_config['bucket_name']),
            'has_access_key_id' => !empty($r2_config['access_key_id']),
            'config_keys' => array_keys($r2_config),
            'full_config_keys' => array_keys($config ?? [])
        ];
        throw new Exception('R2 storage configuration is not properly set up. Debug: ' . json_encode($debug_info));
    }
    
    $objectKey = 'uploads/' . $filename;
    $endpoint = $r2_config['endpoint'] . '/' . $r2_config['bucket_name'];
    
    $fileHandle = fopen($filePath, 'r');
    $fileSize = filesize($filePath);
    
    // Generate AWS-style authentication
    $timestamp = gmdate('Ymd\THis\Z');
    $date = gmdate('Ymd');
    $region = $r2_config['region'];
    $service = 's3';
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $endpoint . '/' . $objectKey,
        CURLOPT_PUT => true,
        CURLOPT_INFILE => $fileHandle,
        CURLOPT_INFILESIZE => $fileSize,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: ' . $mimeType,
            'Host: ' . parse_url($endpoint, PHP_URL_HOST),
            'X-Amz-Date: ' . $timestamp,
            'X-Amz-Content-Sha256: UNSIGNED-PAYLOAD'
        ],
        CURLOPT_USERPWD => $r2_config['access_key_id'] . ':' . $r2_config['secret_access_key']
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("Failed to upload to R2: HTTP $httpCode");
    }
    
    return "$endpoint/$objectKey";
}

/**
 * Generate a unique short link for a video
 */
function generateShortLink($videoId) {
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
        $existing = $db->get_row("SELECT id FROM IONLocalVideos WHERE short_link = ?", $shortCode);
        if (!$existing) {
            error_log("ION Short Link: Generated unique short code: $shortCode for video ID: $videoId");
            return $shortCode;
        }
    }
    
    // Fallback: use video ID with prefix if we can't generate unique code
    $fallback = 'v' . $videoId;
    error_log("ION Short Link: Using fallback short code: $fallback for video ID: $videoId");
    return $fallback;
}

/**
 * Clean up empty videos (videos with empty or default titles)
 */
function cleanupEmptyVideos() {
    global $db;
    
    try {
        error_log('ION Cleanup: Starting cleanup of empty videos');
        
        // Find videos with empty titles or default ION titles
        $emptyVideos = $db->get_results("
            SELECT id, title, video_link 
            FROM IONLocalVideos 
            WHERE title = '' 
               OR title IS NULL 
               OR title LIKE 'ION%'
               OR title = 'Untitled'
            ORDER BY date_added DESC
        ");
        
        if (empty($emptyVideos)) {
            echo json_encode([
                'success' => true,
                'message' => 'No empty videos found to clean up',
                'deleted_count' => 0
            ]);
            return;
        }
        
        $deletedCount = 0;
        foreach ($emptyVideos as $video) {
            $result = $db->delete('IONLocalVideos', ['id' => $video->id]);
            if ($result) {
                $deletedCount++;
                error_log("ION Cleanup: Deleted empty video ID {$video->id} - Title: '{$video->title}'");
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => "Cleaned up {$deletedCount} empty videos",
            'deleted_count' => $deletedCount,
            'videos_found' => count($emptyVideos)
        ]);
        
    } catch (Exception $e) {
        error_log('ION Cleanup: Error - ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => 'Cleanup failed: ' . $e->getMessage()
        ]);
    }
}

/**
 * Handle video deletion
 */
function handleDeleteVideo() {
    // Set proper JSON header
    header('Content-Type: application/json');
    
    // Prevent any output before JSON
    ob_clean();
    
    global $wpdb, $db;
    // Ensure $wpdb is available as alias to $db
    if (!isset($wpdb)) {
        $wpdb = $db;
    }
    
    $video_id = $_POST['video_id'] ?? null;
    
    if (!$video_id) {
        echo json_encode(['success' => false, 'error' => 'Video ID is required']);
        return;
    }
    
    error_log('ION Delete Video: Attempting to delete video ID: ' . $video_id);
    
    // Delete from database
    $result = $wpdb->delete('IONLocalVideos', ['id' => $video_id]);
    
    if ($result === false) {
        error_log('ION Delete Video: Database delete failed. Error: ' . $wpdb->last_error);
        echo json_encode(['success' => false, 'error' => 'Failed to delete video from database: ' . $wpdb->last_error]);
        return;
    }
    
    if ($result === 0) {
        error_log('ION Delete Video: No rows affected - video not found or already deleted');
        echo json_encode(['success' => false, 'error' => 'Video not found']);
        return;
    }
    
    error_log('ION Delete Video: Successfully deleted video ID: ' . $video_id);
    
    echo json_encode([
        'success' => true,
        'message' => 'Video deleted successfully'
    ]);
}

/**
 * Handle video update
 */
function handleUpdateVideo() {
    // Turn off error reporting to prevent HTML output
    error_reporting(0);
    ini_set('display_errors', 0);
    
    // Clean any previous output
    if (ob_get_level()) {
        ob_end_clean();
    }
    ob_start();
    
    // Set proper JSON header
    header('Content-Type: application/json');
    
    try {
        // Add debugging to error log (not output)
        error_log('ION Update Video: Starting handleUpdateVideo');
        error_log('ION Update Video: POST data: ' . print_r($_POST, true));
        
        global $wpdb, $db;
        // Ensure $wpdb is available as alias to $db
        if (!isset($wpdb)) {
            $wpdb = $db;
        }
        
        $video_id = $_POST['video_id'] ?? null;
        error_log('ION Update Video: Video ID: ' . $video_id);
        
        if (!$video_id) {
            error_log('ION Update Video: ERROR - No video ID provided');
            echo json_encode(['success' => false, 'error' => 'Video ID is required']);
            exit;
        }
    
    // Get form data (using custom sanitization since we're not in WordPress)
    $title = trim(strip_tags($_POST['title'] ?? ''));
    $description = trim(strip_tags($_POST['description'] ?? '', '<br><p><strong><em><ul><li><ol>'));
    $category = trim(strip_tags($_POST['category'] ?? ''));
    $tags = trim(strip_tags($_POST['tags'] ?? ''));
    $visibility = trim(strip_tags($_POST['visibility'] ?? 'public'));
    $badges = trim(strip_tags($_POST['badges'] ?? ''));
    
    // Get user role for badge permissions
    $user_email = $_SESSION['user_email'] ?? '';
    $user_role = 'User'; // Default role
    
    if ($user_email) {
        $user_data = $wpdb->get_row("SELECT user_id, user_role FROM IONEERS WHERE email = ?", $user_email);
        if ($user_data) {
            $user_role = $user_data->user_role;
        }
    }
    
    // Debug: Log all received POST data
    error_log('ION Update Video: Received POST data - ' . json_encode($_POST));
    error_log('ION Update Video: Badges received: "' . $badges . '" (length: ' . strlen($badges) . ')');
    error_log('ION Update Video: User email: "' . $user_email . '", User role: "' . $user_role . '"');
    
        if (empty($title)) {
            error_log('ION Update Video: ERROR - Title is empty');
            echo json_encode(['success' => false, 'error' => 'Title is required']);
            exit;
        }
        
        error_log('ION Update Video: Updating database for video ID: ' . $video_id);
        
        // Update database
        $result = $wpdb->update(
            'IONLocalVideos',
            [
                'title' => $title,
                'description' => $description,
                'category' => $category,
                'tags' => $tags,
                'visibility' => $visibility
            ],
            ['id' => $video_id]
        );
        
        error_log('ION Update Video: Database update result: ' . ($result !== false ? 'SUCCESS' : 'FAILED'));
        
        if ($result === false) {
            $error = $wpdb->last_error;
            error_log('ION Update Video: Database error: ' . $error);
            echo json_encode(['success' => false, 'error' => 'Failed to update video in database: ' . $error]);
            exit;
        }
        
        // Handle badges separately using IONVideoBadges junction table
        error_log('ION Update Video: Badge processing - badges value: "' . $badges . '", user_role: "' . $user_role . '"');
        
        if (!empty($badges) && in_array($user_role, ['Owner', 'Admin'])) {
            error_log('ION Update Video: Processing badges for authorized user: ' . $badges);
            
            // First, check what badges exist in the database
            $available_badges = $wpdb->get_results("SELECT id, name FROM IONBadges ORDER BY name");
            error_log('ION Update Video: Available badges in database: ' . json_encode($available_badges));
            
            // Remove existing badges for this video
            $delete_result = $wpdb->delete('IONVideoBadges', ['video_id' => $video_id]);
            error_log('ION Update Video: Deleted existing badges, affected rows: ' . ($delete_result ? 'SUCCESS' : 'FAILED'));
            
            // Parse and insert new badges
            $badge_names = array_map('trim', explode(',', $badges));
            $badge_names = array_filter($badge_names); // Remove empty values
            error_log('ION Update Video: Parsed badge names: ' . json_encode($badge_names));
            
            foreach ($badge_names as $badge_name) {
                error_log("ION Update Video: Looking for badge: '$badge_name'");
                
                // Get badge ID from IONBadges table
                $badge = $wpdb->get_row("SELECT id, name FROM IONBadges WHERE name = ?", $badge_name);
                
                if ($badge) {
                    // Insert into junction table
                    $insert_result = $wpdb->insert(
                        'IONVideoBadges',
                        [
                            'video_id' => $video_id,
                            'badge_id' => $badge->id,
                            'assigned_by' => $_SESSION['user_id'] ?? null,
                            'assigned_at' => current_time('mysql')
                        ]
                    );
                    
                    if ($insert_result) {
                        error_log("ION Update Video: SUCCESS - Added badge '$badge_name' (ID: {$badge->id}) to video $video_id");
                    } else {
                        error_log("ION Update Video: FAILED to insert badge '$badge_name' - Error: " . $wpdb->last_error);
                    }
                } else {
                    error_log("ION Update Video: Badge '$badge_name' not found in IONBadges table");
                    // Let's also try a case-insensitive search
                    $badge_case_insensitive = $wpdb->get_row($wpdb->prepare(
                        "SELECT id, name FROM IONBadges WHERE LOWER(name) = LOWER(%s)", 
                        $badge_name
                    ));
                    if ($badge_case_insensitive) {
                        error_log("ION Update Video: Found badge with case-insensitive search: '{$badge_case_insensitive->name}'");
                    }
                }
            }
        } else if (!empty($badges)) {
            error_log('ION Update Video: Badge update skipped - user lacks permission. Role: ' . $user_role);
        } else {
            error_log('ION Update Video: No badges to process (empty badges value)');
        }
        
        error_log('ION Update Video: SUCCESS - Video updated successfully');
        echo json_encode([
            'success' => true,
            'message' => 'Video updated successfully'
        ]);
        
    } catch (Exception $e) {
        error_log('ION Update Video: EXCEPTION - ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    } catch (Error $e) {
        error_log('ION Update Video: FATAL ERROR - ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Fatal error: ' . $e->getMessage()]);
    }
    
    // Ensure output is flushed
    ob_end_flush();
}

// Add missing action handlers to the main switch
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    switch ($action) {
        case 'transfer_callback':
            handleTransferCallback();
            break;
            
        case 'transfer_status':
            handleTransferStatus();
            break;
    }
}

?>
