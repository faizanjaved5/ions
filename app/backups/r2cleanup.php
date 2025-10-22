<?php
/**
 * R2 Storage Cleanup Script
 * 
 * This script:
 * 1. Aborts abandoned multipart uploads
 * 2. Deletes R2 files for videos that don't exist in database
 * 3. Deletes R2 files for deleted/inactive videos
 * 4. Generates cleanup report
 * 
 * Usage:
 * - Run from command line: php r2-cleanup.php
 * - Or access via browser with admin authentication: r2-cleanup.php?action=run&key=YOUR_SECRET_KEY
 * 
 * Safety Features:
 * - Dry-run mode by default (set $dryRun = false to actually delete)
 * - Detailed logging of all actions
 * - Skips files uploaded in last 24 hours (in case video record is still being created)
 */

// Only allow CLI or authenticated web access
if (php_sapi_name() !== 'cli') {
    session_start();
    
    // Check for admin authentication or secret key
    $secretKey = 'ION_R2_CLEANUP_2025'; // Change this to something secure!
    $providedKey = $_GET['key'] ?? '';
    
    if (!isset($_SESSION['user_email']) && $providedKey !== $secretKey) {
        die('❌ Unauthorized. This script requires authentication or secret key.');
    }
    
    // Set headers for browser viewing
    header('Content-Type: text/plain; charset=utf-8');
}

// Configuration
$dryRun = true; // Set to false to actually delete files
$keepFilesNewerThanHours = 24; // Don't delete files uploaded in last 24 hours
$maxFilesToProcess = 1000; // Safety limit

// Load dependencies
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Load configuration
$config = require __DIR__ . '/../config/config.php';
$r2Config = $config['cloudflare_r2_api'] ?? null;

if (!$r2Config) {
    die("❌ R2 configuration not found in config.php\n");
}

$endpoint = $r2Config['endpoint'];
$bucket = $r2Config['bucket_name'];
$accessKey = $r2Config['access_key_id'];
$secretKey = $r2Config['secret_access_key'];

// Initialize database
$db = new IONDatabase();

// Statistics
$stats = [
    'multipart_aborted' => 0,
    'orphaned_deleted' => 0,
    'deleted_video_files_removed' => 0,
    'inactive_video_files_removed' => 0,
    'errors' => 0,
    'skipped_recent' => 0,
    'total_space_freed' => 0
];

echo "═══════════════════════════════════════════════════════════\n";
echo "🧹 R2 STORAGE CLEANUP SCRIPT\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "Mode: " . ($dryRun ? "DRY RUN (no actual deletions)" : "LIVE (will delete files)") . "\n";
echo "Bucket: $bucket\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "═══════════════════════════════════════════════════════════\n\n";

// ============================================
// STEP 1: Abort Incomplete Multipart Uploads
// ============================================
echo "📋 STEP 1: Checking for incomplete multipart uploads...\n\n";

$multipartUploads = listIncompleteMultipartUploads($bucket, $endpoint, $accessKey, $secretKey);

if (empty($multipartUploads)) {
    echo "✅ No incomplete multipart uploads found.\n\n";
} else {
    echo "Found " . count($multipartUploads) . " incomplete multipart upload(s):\n\n";
    
    foreach ($multipartUploads as $upload) {
        $key = $upload['Key'];
        $uploadId = $upload['UploadId'];
        $initiated = $upload['Initiated'];
        
        echo "  📦 Key: $key\n";
        echo "     Upload ID: $uploadId\n";
        echo "     Started: $initiated\n";
        
        if (!$dryRun) {
            if (abortMultipartUpload($bucket, $key, $uploadId, $endpoint, $accessKey, $secretKey)) {
                echo "     ✅ Aborted\n\n";
                $stats['multipart_aborted']++;
            } else {
                echo "     ❌ Failed to abort\n\n";
                $stats['errors']++;
            }
        } else {
            echo "     🔍 Would abort (dry run)\n\n";
            $stats['multipart_aborted']++;
        }
    }
}

// ============================================
// STEP 2: Get All R2 Objects
// ============================================
echo "📋 STEP 2: Listing all objects in R2 bucket...\n\n";

$r2Objects = listAllR2Objects($bucket, $endpoint, $accessKey, $secretKey, $maxFilesToProcess);
echo "Found " . count($r2Objects) . " object(s) in R2\n\n";

// ============================================
// STEP 3: Get Active Videos from Database
// ============================================
echo "📋 STEP 3: Loading active videos from database...\n\n";

$activeVideos = $db->get_results("
    SELECT video_id, video_file_url, created_at, status
    FROM videos
    WHERE status != 'deleted'
");

echo "Found " . count($activeVideos) . " active video(s) in database\n\n";

// Build lookup of active R2 keys
$activeR2Keys = [];
foreach ($activeVideos as $video) {
    if (preg_match('/r2\.cloudflarestorage\.com\/(.+)$/', $video->video_file_url, $matches)) {
        $activeR2Keys[$matches[1]] = $video->video_id;
    }
}

echo "Extracted " . count($activeR2Keys) . " R2 key(s) from active videos\n\n";

// ============================================
// STEP 4: Identify and Delete Orphaned Files
// ============================================
echo "📋 STEP 4: Identifying orphaned files...\n\n";

$cutoffTime = time() - ($keepFilesNewerThanHours * 3600);

foreach ($r2Objects as $object) {
    $key = $object['Key'];
    $size = $object['Size'];
    $lastModified = strtotime($object['LastModified']);
    
    // Skip if file is too recent (might be mid-upload or record being created)
    if ($lastModified > $cutoffTime) {
        $stats['skipped_recent']++;
        continue;
    }
    
    // Check if this file is associated with an active video
    if (!isset($activeR2Keys[$key])) {
        echo "🗑️  Orphaned file: $key\n";
        echo "    Size: " . formatBytes($size) . "\n";
        echo "    Last Modified: " . date('Y-m-d H:i:s', $lastModified) . "\n";
        
        if (!$dryRun) {
            if (deleteR2Object($bucket, $key, $endpoint, $accessKey, $secretKey)) {
                echo "    ✅ Deleted\n\n";
                $stats['orphaned_deleted']++;
                $stats['total_space_freed'] += $size;
            } else {
                echo "    ❌ Failed to delete\n\n";
                $stats['errors']++;
            }
        } else {
            echo "    🔍 Would delete (dry run)\n\n";
            $stats['orphaned_deleted']++;
            $stats['total_space_freed'] += $size;
        }
    }
}

// ============================================
// STEP 5: Clean Up Deleted Videos
// ============================================
echo "📋 STEP 5: Checking for deleted videos with R2 files...\n\n";

$deletedVideos = $db->get_results("
    SELECT video_id, video_file_url, created_at
    FROM videos
    WHERE status = 'deleted'
    AND video_file_url LIKE '%r2.cloudflarestorage.com%'
");

if (empty($deletedVideos)) {
    echo "✅ No deleted videos with R2 files found.\n\n";
} else {
    echo "Found " . count($deletedVideos) . " deleted video(s) with R2 files:\n\n";
    
    foreach ($deletedVideos as $video) {
        if (preg_match('/r2\.cloudflarestorage\.com\/(.+)$/', $video->video_file_url, $matches)) {
            $key = $matches[1];
            
            echo "  🗑️  Deleted video: {$video->video_id}\n";
            echo "     Key: $key\n";
            echo "     Original upload: {$video->created_at}\n";
            
            if (!$dryRun) {
                if (deleteR2Object($bucket, $key, $endpoint, $accessKey, $secretKey)) {
                    echo "     ✅ R2 file deleted\n\n";
                    $stats['deleted_video_files_removed']++;
                } else {
                    echo "     ❌ Failed to delete\n\n";
                    $stats['errors']++;
                }
            } else {
                echo "     🔍 Would delete (dry run)\n\n";
                $stats['deleted_video_files_removed']++;
            }
        }
    }
}

// ============================================
// FINAL REPORT
// ============================================
echo "═══════════════════════════════════════════════════════════\n";
echo "📊 CLEANUP SUMMARY\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "Mode: " . ($dryRun ? "DRY RUN" : "LIVE") . "\n";
echo "Incomplete multipart uploads aborted: {$stats['multipart_aborted']}\n";
echo "Orphaned files deleted: {$stats['orphaned_deleted']}\n";
echo "Deleted video files removed: {$stats['deleted_video_files_removed']}\n";
echo "Files skipped (too recent): {$stats['skipped_recent']}\n";
echo "Errors encountered: {$stats['errors']}\n";
echo "Total space freed: " . formatBytes($stats['total_space_freed']) . "\n";
echo "═══════════════════════════════════════════════════════════\n";

if ($dryRun) {
    echo "\n⚠️  This was a DRY RUN. No files were actually deleted.\n";
    echo "To perform actual cleanup, set \$dryRun = false in the script.\n";
}

echo "\n✅ Cleanup completed at " . date('Y-m-d H:i:s') . "\n";

// ============================================
// HELPER FUNCTIONS
// ============================================

/**
 * List incomplete multipart uploads
 */
function listIncompleteMultipartUploads($bucket, $endpoint, $accessKey, $secretKey) {
    $urlParts = parse_url($endpoint);
    $host = "$bucket.{$urlParts['host']}";
    $url = "https://$host/?uploads";
    
    $headers = signR2Request('GET', $url, [], '', $accessKey, $secretKey);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        echo "⚠️  Failed to list multipart uploads: HTTP $httpCode\n";
        return [];
    }
    
    $xml = simplexml_load_string($response);
    if (!$xml) {
        return [];
    }
    
    $uploads = [];
    foreach ($xml->Upload as $upload) {
        $uploads[] = [
            'Key' => (string)$upload->Key,
            'UploadId' => (string)$upload->UploadId,
            'Initiated' => (string)$upload->Initiated
        ];
    }
    
    return $uploads;
}

/**
 * Abort a multipart upload
 */
function abortMultipartUpload($bucket, $key, $uploadId, $endpoint, $accessKey, $secretKey) {
    $urlParts = parse_url($endpoint);
    $host = "$bucket.{$urlParts['host']}";
    $url = "https://$host/$key?uploadId=" . urlencode($uploadId);
    
    $headers = signR2Request('DELETE', $url, [], '', $accessKey, $secretKey);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode === 204;
}

/**
 * List all objects in R2 bucket
 */
function listAllR2Objects($bucket, $endpoint, $accessKey, $secretKey, $maxKeys = 1000) {
    $urlParts = parse_url($endpoint);
    $host = "$bucket.{$urlParts['host']}";
    $url = "https://$host/?list-type=2&max-keys=$maxKeys";
    
    $headers = signR2Request('GET', $url, [], '', $accessKey, $secretKey);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        echo "⚠️  Failed to list R2 objects: HTTP $httpCode\n";
        return [];
    }
    
    $xml = simplexml_load_string($response);
    if (!$xml) {
        return [];
    }
    
    $objects = [];
    foreach ($xml->Contents as $content) {
        $objects[] = [
            'Key' => (string)$content->Key,
            'Size' => (int)$content->Size,
            'LastModified' => (string)$content->LastModified
        ];
    }
    
    return $objects;
}

/**
 * Delete an R2 object
 */
function deleteR2Object($bucket, $key, $endpoint, $accessKey, $secretKey) {
    $urlParts = parse_url($endpoint);
    $host = "$bucket.{$urlParts['host']}";
    
    // Encode key path segments
    $pathSegments = explode('/', $key);
    $encodedSegments = array_map('rawurlencode', $pathSegments);
    $encodedKey = implode('/', $encodedSegments);
    
    $url = "https://$host/$encodedKey";
    
    $headers = signR2Request('DELETE', $url, [], '', $accessKey, $secretKey);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode === 204;
}

/**
 * Sign R2 request using AWS Signature V4
 */
function signR2Request($method, $url, $headers, $body, $accessKey, $secretKey) {
    $urlParts = parse_url($url);
    $host = $urlParts['host'];
    $path = $urlParts['path'] ?? '/';
    $query = $urlParts['query'] ?? '';
    
    $timestamp = gmdate('Ymd\THis\Z');
    $date = gmdate('Ymd');
    
    $service = 's3';
    $region = 'auto';
    
    $hashedPayload = hash('sha256', $body);
    
    $canonicalHeaders = [
        'host' => $host,
        'x-amz-content-sha256' => $hashedPayload,
        'x-amz-date' => $timestamp
    ];
    
    if (isset($headers['Content-Type'])) {
        $canonicalHeaders['content-type'] = $headers['Content-Type'];
    }
    
    ksort($canonicalHeaders);
    
    $canonicalHeadersStr = '';
    $signedHeadersStr = '';
    foreach ($canonicalHeaders as $key => $value) {
        $canonicalHeadersStr .= strtolower($key) . ':' . trim($value) . "\n";
        $signedHeadersStr .= strtolower($key) . ';';
    }
    $signedHeadersStr = rtrim($signedHeadersStr, ';');
    
    $canonicalQueryString = '';
    if (!empty($query)) {
        parse_str($query, $queryParams);
        ksort($queryParams);
        $canonicalParts = [];
        foreach ($queryParams as $key => $value) {
            if ($value === '') {
                $canonicalParts[] = rawurlencode($key) . '=';
            } else {
                $canonicalParts[] = rawurlencode($key) . '=' . rawurlencode($value);
            }
        }
        $canonicalQueryString = implode('&', $canonicalParts);
    }
    
    $canonicalRequest = "$method\n$path\n$canonicalQueryString\n$canonicalHeadersStr\n$signedHeadersStr\n$hashedPayload";
    $hashedCanonicalRequest = hash('sha256', $canonicalRequest);
    
    $credentialScope = "$date/$region/$service/aws4_request";
    $stringToSign = "AWS4-HMAC-SHA256\n$timestamp\n$credentialScope\n$hashedCanonicalRequest";
    
    $kDate = hash_hmac('sha256', $date, 'AWS4' . $secretKey, true);
    $kRegion = hash_hmac('sha256', $region, $kDate, true);
    $kService = hash_hmac('sha256', $service, $kRegion, true);
    $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
    $signature = hash_hmac('sha256', $stringToSign, $kSigning);
    
    $authorizationHeader = "AWS4-HMAC-SHA256 Credential=$accessKey/$credentialScope, SignedHeaders=$signedHeadersStr, Signature=$signature";
    
    $requestHeaders = [
        "Host: $host",
        "x-amz-content-sha256: $hashedPayload",
        "x-amz-date: $timestamp",
        "Authorization: $authorizationHeader"
    ];
    
    if (isset($headers['Content-Type'])) {
        $requestHeaders[] = "Content-Type: {$headers['Content-Type']}";
    }
    
    return $requestHeaders;
}

/**
 * Format bytes to human readable
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

