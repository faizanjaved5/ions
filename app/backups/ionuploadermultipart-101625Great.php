<?php
/**
 * Cloudflare R2 Multipart Upload Handler
 * Handles direct-to-R2 uploads using presigned URLs
 */

session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Load dependencies
$config = require __DIR__ . '/../config/config.php';  // Capture returned config array
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/shortlink-manager.php';

// Set content type for JSON responses
header('Content-Type: application/json');

// Enable CORS if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Only POST method allowed']);
    exit();
}

// Authentication check
if (!isset($_SESSION['user_email']) || empty($_SESSION['user_email'])) {
    echo json_encode(['success' => false, 'error' => 'User not authenticated']);
    exit();
}

$user_email = $_SESSION['user_email'];

try {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'init':
            handleInitMultipartUpload();
            break;
            
        case 'get-presigned-urls':
            handleGetPresignedUrls();
            break;
            
        case 'complete':
            handleCompleteMultipartUpload();
            break;
            
        case 'abort':
            handleAbortMultipartUpload();
            break;
            
        case 'status':
            handleGetUploadStatus();
            break;
            
        case 'debug':
            echo json_encode([
                'success' => true, 
                'message' => 'R2 handler is working',
                'php_version' => PHP_VERSION,
                'user_email' => $user_email ?? 'not_set',
                'config_loaded' => isset($config),
                'db_loaded' => isset($db),
                'post_data' => $_POST
            ]);
            exit();
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            exit();
    }
    
} catch (Exception $e) {
    error_log("R2 MULTIPART ERROR: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Upload failed: ' . $e->getMessage()]);
    exit();
}

/**
 * Initialize multipart upload
 */
function handleInitMultipartUpload() {
    global $db, $user_email, $config;
    
    $fileName = $_POST['fileName'] ?? '';
    $fileSize = intval($_POST['fileSize'] ?? 0);
    $contentType = $_POST['contentType'] ?? 'video/mp4';
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $category = $_POST['category'] ?? '';
    $visibility = $_POST['visibility'] ?? 'public';
    
    // Validate required fields
    if (empty($fileName) || $fileSize <= 0) {
        echo json_encode(['success' => false, 'error' => 'Missing required upload parameters']);
        exit();
    }
    
    // Check file size limits (20GB max)
    $maxSize = 20 * 1024 * 1024 * 1024; // 20GB
    if ($fileSize > $maxSize) {
        echo json_encode(['success' => false, 'error' => 'File size exceeds 20GB limit']);
        exit();
    }
    
    // Get user data
    $user_data = $db->get_row($db->prepare("SELECT user_id FROM IONEERS WHERE email = %s", $user_email));
    if (!$user_data) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit();
    }
    
    // Generate unique file key
    $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
    $uniqueId = uniqid('video_', true);
    $uniqueFileName = $uniqueId . '.' . $fileExtension;
    $dateFolder = date('Y/m/d');
    $objectKey = $dateFolder . '/' . uniqid() . '_' . $uniqueFileName;
    
    // Get R2 configuration
    if (!isset($config['cloudflare_r2_api'])) {
        echo json_encode(['success' => false, 'error' => 'R2 configuration not found']);
        exit();
    }
    
    $r2Config = $config['cloudflare_r2_api'];
    
    // Validate R2 configuration
    $requiredKeys = ['endpoint', 'bucket_name', 'access_key_id', 'secret_access_key'];
    foreach ($requiredKeys as $key) {
        if (empty($r2Config[$key])) {
            echo json_encode(['success' => false, 'error' => "Missing R2 configuration: $key"]);
            exit();
        }
    }
    
    // Initialize multipart upload with R2
    try {
        $multipartData = initializeR2MultipartUpload($objectKey, $contentType, $r2Config);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit();
    }
    
    // Store upload session in database
    $uploadId = uniqid('r2upload_', true);
    
    $result = $db->insert('IONUploadSessions', [
        'id' => $uploadId,
        'file_name' => $fileName,
        'status' => 'uploading',
        'upload_type' => 'r2_multipart',
        'r2_upload_id' => $multipartData['UploadId'],
        'r2_object_key' => $objectKey,
        'content_type' => $contentType,
        'final_url' => ''
    ]);
    
    if (!$result) {
        // Abort the R2 multipart upload since DB insert failed
        abortR2MultipartUpload($objectKey, $multipartData['UploadId'], $r2Config);
        echo json_encode(['success' => false, 'error' => 'Failed to initialize upload session']);
        exit();
    }
    
    echo json_encode([
        'success' => true,
        'uploadId' => $uploadId,
        'r2UploadId' => $multipartData['UploadId'],
        'objectKey' => $objectKey,
        'message' => 'Multipart upload initialized'
    ]);
}

/**
 * Get presigned URLs for upload parts
 */
function handleGetPresignedUrls() {
    global $db, $config;
    
    $uploadId = $_POST['uploadId'] ?? '';
    $partCount = intval($_POST['partCount'] ?? 0);
    
    if (empty($uploadId) || $partCount <= 0) {
        echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
        exit();
    }
    
    // Get upload session
    $session = $db->get_row($db->prepare("SELECT * FROM IONUploadSessions WHERE id = %s", $uploadId));
    if (!$session) {
        echo json_encode(['success' => false, 'error' => 'Upload session not found']);
        exit();
    }
    
    if ($session->status !== 'uploading') {
        echo json_encode(['success' => false, 'error' => 'Upload session is not active']);
        exit();
    }
    
    // Generate presigned URLs for each part
    $r2Config = $config['cloudflare_r2_api'];
    $presignedUrls = [];
    
    for ($partNumber = 1; $partNumber <= $partCount; $partNumber++) {
        $presignedUrls[] = [
            'partNumber' => $partNumber,
            'url' => generateR2PresignedUrl($session->r2_object_key, $session->r2_upload_id, $partNumber, $r2Config)
        ];
    }
    
    echo json_encode([
        'success' => true,
        'presignedUrls' => $presignedUrls,
        'message' => 'Presigned URLs generated'
    ]);
}

/**
 * Complete multipart upload
 */
function handleCompleteMultipartUpload() {
    global $db, $config;
    
    $uploadId = $_POST['uploadId'] ?? '';
    $parts = json_decode($_POST['parts'] ?? '[]', true);
    
    if (empty($uploadId) || empty($parts)) {
        echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
        exit();
    }
    
    // Get upload session
    $session = $db->get_row($db->prepare("SELECT * FROM IONUploadSessions WHERE id = %s", $uploadId));
    if (!$session) {
        echo json_encode(['success' => false, 'error' => 'Upload session not found']);
        exit();
    }
    
    // Update status to assembling
    $db->update('IONUploadSessions', ['status' => 'assembling'], ['id' => $uploadId]);
    
    try {
        // Complete multipart upload with R2
        $r2Config = $config['cloudflare_r2_api'];
        completeR2MultipartUpload($session->r2_object_key, $session->r2_upload_id, $parts, $r2Config);
        
        // Generate final URL
        $publicUrl = generateR2PublicUrl($session->r2_object_key, $r2Config);
        
        // Create video entry in database
        $videoId = createVideoEntry($session, $publicUrl);
        
        // Generate shortlink for the video
        $shortlink_manager = new VideoShortlinkManager($db);
        $shortlink_result = $shortlink_manager->generateShortlink($videoId, $session->title);
        $share_url = $shortlink_result ? $shortlink_result['url'] : null;
        
        // Upload to Cloudflare Stream for processing (background)
        scheduleStreamUpload($publicUrl, $videoId);
        
        // Update session status to completed
        $db->update('IONUploadSessions', [
            'status' => 'completed',
            'final_url' => $publicUrl
        ], ['id' => $uploadId]);
        
        $response = [
            'success' => true,
            'video_id' => $videoId,
            'url' => $publicUrl,
            'message' => 'Upload completed successfully'
        ];
        
        // Add share URL if shortlink was generated
        if ($share_url) {
            $response['share_url'] = $share_url;
        }
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        // Update session status to failed
        $db->update('IONUploadSessions', [
            'status' => 'failed'
        ], ['id' => $uploadId]);
        
        throw $e;
    }
}

/**
 * Abort multipart upload
 */
function handleAbortMultipartUpload() {
    global $db, $config;
    
    $uploadId = $_POST['uploadId'] ?? '';
    
    if (empty($uploadId)) {
        echo json_encode(['success' => false, 'error' => 'Missing upload ID']);
        exit();
    }
    
    $session = $db->get_row($db->prepare("SELECT * FROM IONUploadSessions WHERE id = %s", $uploadId));
    if (!$session) {
        echo json_encode(['success' => false, 'error' => 'Upload session not found']);
        exit();
    }
    
    // Abort R2 multipart upload
    $r2Config = $config['cloudflare_r2_api'];
    abortR2MultipartUpload($session->r2_object_key, $session->r2_upload_id, $r2Config);
    
    // Update session status
    $db->update('IONUploadSessions', [
        'status' => 'cancelled'
    ], ['id' => $uploadId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Upload cancelled successfully'
    ]);
}

/**
 * Get upload status
 */
function handleGetUploadStatus() {
    global $db;
    
    $uploadId = $_POST['uploadId'] ?? $_GET['uploadId'] ?? '';
    
    if (empty($uploadId)) {
        echo json_encode(['success' => false, 'error' => 'Missing upload ID']);
        exit();
    }
    
    $session = $db->get_row($db->prepare("SELECT * FROM IONUploadSessions WHERE id = %s", $uploadId));
    if (!$session) {
        echo json_encode(['success' => false, 'error' => 'Upload session not found']);
        exit();
    }
    
    echo json_encode([
        'success' => true,
        'uploadId' => $session->id,
        'status' => $session->status,
        'fileName' => $session->file_name,
        'fileSize' => $session->file_size,
        'video_id' => $session->video_id,
        'final_url' => $session->final_url
    ]);
}

/**
 * Initialize R2 multipart upload
 */
function initializeR2MultipartUpload($objectKey, $contentType, $r2Config) {
    $endpoint = $r2Config['endpoint'];
    $bucket = $r2Config['bucket_name'];
    $accessKey = $r2Config['access_key_id'];
    $secretKey = $r2Config['secret_access_key'];
    
    // CRITICAL: Use virtual-hosted-style URL to match presigned URLs
    // Format: https://bucket.account-id.r2.cloudflarestorage.com/object-key?uploads
    $urlParts = parse_url($endpoint);
    $endpointHost = $urlParts['host'];
    $host = "$bucket.$endpointHost";
    $url = "https://$host/$objectKey?uploads";
    
    error_log("🚀 Initializing R2 multipart upload:");
    error_log("   Object Key: $objectKey");
    error_log("   URL: $url");
    
    // For empty POST body, don't include Content-Type in headers
    $headers = [];
    
    $signedHeaders = signR2Request('POST', $url, $headers, '', $accessKey, $secretKey);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => '', // Explicitly set empty body
        CURLOPT_HTTPHEADER => $signedHeaders,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($response === false || !empty($curlError)) {
        throw new Exception("Failed to initialize multipart upload: CURL Error - $curlError");
    }
    
    if ($httpCode !== 200) {
        throw new Exception("Failed to initialize multipart upload: HTTP $httpCode - $response");
    }
    
    $xml = simplexml_load_string($response);
    if (!$xml) {
        throw new Exception("Invalid XML response from R2");
    }
    
    return [
        'UploadId' => (string)$xml->UploadId,
        'Bucket' => (string)$xml->Bucket,
        'Key' => (string)$xml->Key
    ];
}

/**
 * Generate presigned URL for upload part using AWS Signature V4
 */
function generateR2PresignedUrl($objectKey, $uploadId, $partNumber, $r2Config) {
    $endpoint = $r2Config['endpoint'];
    $bucket = $r2Config['bucket_name'];
    $accessKey = $r2Config['access_key_id'];
    $secretKey = $r2Config['secret_access_key'];
    
    $urlParts = parse_url($endpoint);
    $endpointHost = $urlParts['host'];
    
    // R2 uses bucket.account-id.r2.cloudflarestorage.com format
    $host = "$bucket.$endpointHost";
    
    $timestamp = gmdate('Ymd\THis\Z');
    $date = gmdate('Ymd');
    
    $service = 's3';
    $region = 'auto';
    $expires = 3600; // 1 hour
    
    $credentialScope = "$date/$region/$service/aws4_request";
    $credential = "$accessKey/$credentialScope";
    
    // Build canonical query string
    $queryParams = [
        'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
        'X-Amz-Credential' => $credential,
        'X-Amz-Date' => $timestamp,
        'X-Amz-Expires' => $expires,
        'X-Amz-SignedHeaders' => 'host',
        'partNumber' => $partNumber,
        'uploadId' => $uploadId
    ];
    
    ksort($queryParams);
    
    $canonicalQueryString = '';
    foreach ($queryParams as $key => $value) {
        $canonicalQueryString .= rawurlencode($key) . '=' . rawurlencode($value) . '&';
    }
    $canonicalQueryString = rtrim($canonicalQueryString, '&');
    
    // Build canonical request
    $canonicalHeaders = "host:$host\n";
    $signedHeaders = 'host';
    $hashedPayload = 'UNSIGNED-PAYLOAD';
    
    // Canonical URI is just the object key (bucket is in host)
    $canonicalUri = "/$objectKey";
    
    $canonicalRequest = "PUT\n" .
                       $canonicalUri . "\n" .
                       $canonicalQueryString . "\n" .
                       $canonicalHeaders . "\n" .
                       $signedHeaders . "\n" .
                       $hashedPayload;
    
    $hashedCanonicalRequest = hash('sha256', $canonicalRequest);
    
    // Build string to sign
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
    
    // Build presigned URL with bucket.endpoint host format
    $presignedUrl = "https://$host/$objectKey?" . $canonicalQueryString . "&X-Amz-Signature=$signature";
    
    // Debug logging
    error_log("🔗 Generated presigned URL for part $partNumber:");
    error_log("   Host: $host");
    error_log("   Object Key: $objectKey");
    error_log("   Upload ID: $uploadId");
    error_log("   URL: $presignedUrl");
    
    return $presignedUrl;
}

/**
 * Complete R2 multipart upload
 */
function completeR2MultipartUpload($objectKey, $uploadId, $parts, $r2Config) {
    $endpoint = $r2Config['endpoint'];
    $bucket = $r2Config['bucket_name'];
    $accessKey = $r2Config['access_key_id'];
    $secretKey = $r2Config['secret_access_key'];
    
    // CRITICAL: Use virtual-hosted-style URL to match presigned URLs and initialization
    // Format: https://bucket.account-id.r2.cloudflarestorage.com/object-key?uploadId=...
    $urlParts = parse_url($endpoint);
    $endpointHost = $urlParts['host'];
    $host = "$bucket.$endpointHost";
    $url = "https://$host/$objectKey?uploadId=" . urlencode($uploadId);
    
    // Build XML payload
    $xml = '<CompleteMultipartUpload>';
    foreach ($parts as $part) {
        $xml .= '<Part>';
        $xml .= '<PartNumber>' . $part['PartNumber'] . '</PartNumber>';
        $xml .= '<ETag>' . $part['ETag'] . '</ETag>';
        $xml .= '</Part>';
    }
    $xml .= '</CompleteMultipartUpload>';
    
    $headers = [
        'Content-Type' => 'application/xml',
        'Content-Length' => strlen($xml)
    ];
    
    $signedHeaders = signR2Request('POST', $url, $headers, $xml, $accessKey, $secretKey);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $xml,
        CURLOPT_HTTPHEADER => $signedHeaders,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("Failed to complete multipart upload: HTTP $httpCode - $response");
    }
    
    return true;
}

/**
 * Abort R2 multipart upload
 */
function abortR2MultipartUpload($objectKey, $uploadId, $r2Config) {
    $endpoint = $r2Config['endpoint'];
    $bucket = $r2Config['bucket_name'];
    $accessKey = $r2Config['access_key_id'];
    $secretKey = $r2Config['secret_access_key'];
    
    // CRITICAL: Use virtual-hosted-style URL to match presigned URLs and initialization
    // Format: https://bucket.account-id.r2.cloudflarestorage.com/object-key?uploadId=...
    $urlParts = parse_url($endpoint);
    $endpointHost = $urlParts['host'];
    $host = "$bucket.$endpointHost";
    $url = "https://$host/$objectKey?uploadId=" . urlencode($uploadId);
    
    $headers = ['Content-Length' => '0'];
    $signedHeaders = signR2Request('DELETE', $url, $headers, '', $accessKey, $secretKey);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_HTTPHEADER => $signedHeaders,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30
    ]);
    
    curl_exec($ch);
    curl_close($ch);
    
    return true;
}

/**
 * Sign R2 request using AWS Signature Version 4
 */
function signR2Request($method, $url, $headers, $body, $accessKey, $secretKey) {
    $urlParts = parse_url($url);
    $host = $urlParts['host'];
    $path = $urlParts['path'];
    $query = $urlParts['query'] ?? '';
    
    // AWS SigV4 requires specific date format
    $timestamp = gmdate('Ymd\THis\Z');
    $date = gmdate('Ymd');
    
    $service = 's3';
    $region = 'auto'; // R2 uses 'auto' as region
    
    // Hash the payload first (needed for headers)
    $hashedPayload = hash('sha256', $body);
    
    // Prepare headers
    $canonicalHeaders = [
        'host' => $host,
        'x-amz-content-sha256' => $hashedPayload,
        'x-amz-date' => $timestamp
    ];
    
    if (isset($headers['Content-Type'])) {
        $canonicalHeaders['content-type'] = $headers['Content-Type'];
    }
    
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
    
    // Canonicalize query string (AWS SigV4 requirement)
    $canonicalQueryString = '';
    if (!empty($query)) {
        parse_str($query, $queryParams);
        ksort($queryParams);
        $canonicalParts = [];
        foreach ($queryParams as $key => $value) {
            if ($value === '') {
                // Query params without values (like "uploads") should be "uploads="
                $canonicalParts[] = rawurlencode($key) . '=';
            } else {
                $canonicalParts[] = rawurlencode($key) . '=' . rawurlencode($value);
            }
        }
        $canonicalQueryString = implode('&', $canonicalParts);
    }
    
    // Build canonical request
    $canonicalRequest = $method . "\n" .
                       $path . "\n" .
                       $canonicalQueryString . "\n" .
                       $canonicalHeadersStr . "\n" .
                       $signedHeadersStr . "\n" .
                       $hashedPayload;
    
    $hashedCanonicalRequest = hash('sha256', $canonicalRequest);
    
    // Build string to sign
    $credentialScope = $date . '/' . $region . '/' . $service . '/aws4_request';
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
    
    // Return headers array for CURL
    $signedHeaders = [
        "Host: $host",
        "x-amz-content-sha256: $hashedPayload",
        "x-amz-date: $timestamp",
        "Authorization: $authorization"
    ];
    
    if (isset($headers['Content-Type'])) {
        $signedHeaders[] = "Content-Type: " . $headers['Content-Type'];
    }
    
    if (isset($headers['Content-Length'])) {
        $signedHeaders[] = "Content-Length: " . $headers['Content-Length'];
    }
    
    return $signedHeaders;
}

/**
 * Generate R2 public URL
 */
function generateR2PublicUrl($objectKey, $r2Config) {
    return rtrim($r2Config['public_url_base'], '/') . '/' . $objectKey;
}

/**
 * Create video entry in database
 */
function createVideoEntry($session, $publicUrl) {
    global $db;
    
    $slug = sanitizeSlug($session->title);
    
    $video_data = [
        'slug' => $slug,
        'category' => $session->category ?: 'General',
        'video_id' => basename($session->r2_object_key),
        'title' => $session->title ?: 'Uploaded Video - ' . date('Y-m-d H:i'),
        'thumbnail' => 'https://ions.com/assets/default/processing.png',
        'video_link' => $publicUrl,
        'published_at' => date('Y-m-d H:i:s'),
        'description' => $session->description ?: '',
        'tags' => '',
        'videotype' => 'Upload',
        'video_length' => 0,
        'upload_status' => 'Completed',
        'visibility' => $session->visibility,
        'user_id' => $session->user_id
    ];
    
    $result = $db->insert('IONLocalVideos', $video_data);
    
    if (!$result) {
        throw new Exception('Failed to create video entry: ' . $db->last_error);
    }
    
    return $db->insert_id;
}

/**
 * Schedule Cloudflare Stream upload (background)
 */
function scheduleStreamUpload($publicUrl, $videoId) {
    // This could be implemented as a background job
    // For now, just log it
    error_log("STREAM UPLOAD SCHEDULED: Video ID $videoId, URL: $publicUrl");
}

/**
 * Sanitize slug
 */
function sanitizeSlug($title) {
    $slug = strtolower(trim($title));
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    return trim($slug, '-') ?: 'video-' . uniqid();
}

?>
