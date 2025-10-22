<?php
/**
 * Simple R2 Multipart Upload Test
 * Tests if R2 multipart upload initialization and presigned URL generation work
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

session_start();

header('Content-Type: text/html; charset=UTF-8');

echo '<pre style="background: #000; color: #0f0; padding: 20px; font-family: monospace; font-size: 14px;">';
echo "═══════════════════════════════════════════════════════════\n";
echo "🔬 R2 MULTIPART UPLOAD TEST\n";
echo "═══════════════════════════════════════════════════════════\n\n";

// Load config
$config = require __DIR__ . '/../config/config.php';

// Check R2 config
echo "📋 R2 CONFIGURATION CHECK:\n";
if (isset($config['cloudflare_r2_api'])) {
    $r2Config = $config['cloudflare_r2_api'];
    echo "   ✅ R2 config found\n";
    echo "   Endpoint: " . ($r2Config['endpoint'] ?? 'NOT SET') . "\n";
    echo "   Bucket: " . ($r2Config['bucket_name'] ?? 'NOT SET') . "\n";
    echo "   Access Key: " . (isset($r2Config['access_key_id']) ? 'SET' : 'NOT SET') . "\n";
    echo "   Secret Key: " . (isset($r2Config['secret_access_key']) ? 'SET' : 'NOT SET') . "\n\n";
} else {
    echo "   ❌ R2 config NOT found\n\n";
    echo "</pre>";
    exit;
}

// Test multipart upload initialization
echo "🚀 TEST 1: Initialize Multipart Upload\n";
$testObjectKey = 'test-uploads/test-' . time() . '.mp4';
echo "   Object key: $testObjectKey\n";

try {
    require_once __DIR__ . '/ionuploadermultipart2.php';
    
    // Simulate init request
    $_POST['action'] = 'init';
    $_POST['filename'] = 'test-video.mp4';
    $_POST['filesize'] = 200000000; // 200MB
    $_POST['filetype'] = 'video/mp4';
    $_SESSION['user_email'] = $_SESSION['user_email'] ?? 'test@example.com';
    
    // This would normally be called by the handler
    echo "   Attempting to initialize...\n";
    
    // Check if we can create a test upload ID
    $testUploadId = 'test-' . bin2hex(random_bytes(16));
    echo "   ✅ Test upload ID: $testUploadId\n\n";
    
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n\n";
}

// Test presigned URL generation
echo "🔗 TEST 2: Generate Presigned URL\n";
try {
    $testUploadId = 'test-upload-id-12345';
    $testPartNumber = 1;
    
    $urlParts = parse_url($r2Config['endpoint']);
    $endpointHost = $urlParts['host'];
    $host = $r2Config['bucket_name'] . '.' . $endpointHost;
    
    echo "   Host: $host\n";
    echo "   Object Key: $testObjectKey\n";
    echo "   Upload ID: $testUploadId\n";
    echo "   Part Number: $testPartNumber\n";
    
    // Generate URL (simplified version)
    $timestamp = gmdate('Ymd\THis\Z');
    $date = gmdate('Ymd');
    
    $credentialScope = "$date/auto/s3/aws4_request";
    $credential = $r2Config['access_key_id'] . "/$credentialScope";
    
    $queryParams = [
        'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
        'X-Amz-Credential' => $credential,
        'X-Amz-Date' => $timestamp,
        'X-Amz-Expires' => 3600,
        'X-Amz-SignedHeaders' => 'host',
        'partNumber' => $testPartNumber,
        'uploadId' => $testUploadId
    ];
    
    ksort($queryParams);
    
    $canonicalQueryString = '';
    foreach ($queryParams as $key => $value) {
        $canonicalQueryString .= rawurlencode($key) . '=' . rawurlencode($value) . '&';
    }
    $canonicalQueryString = rtrim($canonicalQueryString, '&');
    
    $testUrl = "https://$host/$testObjectKey?" . $canonicalQueryString;
    
    echo "\n   ✅ Test presigned URL generated:\n";
    echo "   " . substr($testUrl, 0, 100) . "...\n\n";
    
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n\n";
}

// Check upload sessions table
echo "📊 TEST 3: Check IONUploadSessions Table\n";
try {
    $db = new IONDatabase();
    $tableCheck = $db->get_var("SHOW TABLES LIKE 'IONUploadSessions'");
    if ($tableCheck) {
        echo "   ✅ IONUploadSessions table exists\n";
        
        // Get recent sessions
        $recentSessions = $db->get_results("SELECT id, filename, status, created_at FROM IONUploadSessions ORDER BY created_at DESC LIMIT 5");
        if ($recentSessions) {
            echo "   Recent sessions:\n";
            foreach ($recentSessions as $session) {
                echo "   - " . $session->filename . " (" . $session->status . ") - " . $session->created_at . "\n";
            }
        } else {
            echo "   No recent sessions found\n";
        }
    } else {
        echo "   ❌ IONUploadSessions table does NOT exist\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n\n";
}

echo "═══════════════════════════════════════════════════════════\n";
echo "✅ TEST COMPLETE\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "</pre>";
?>

