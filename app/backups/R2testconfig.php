<?php
/**
 * R2 Configuration Test
 * Simple test to verify R2 config is correct
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: text/html; charset=UTF-8');

echo '<pre style="background: #000; color: #0f0; padding: 20px; font-family: monospace; font-size: 14px;">';
echo "═══════════════════════════════════════════════════════════\n";
echo "🔬 R2 CONFIGURATION TEST\n";
echo "═══════════════════════════════════════════════════════════\n\n";

// Load config
$config = require __DIR__ . '/../config/config.php';

// Check R2 config
echo "📋 R2 CONFIGURATION:\n";
if (isset($config['cloudflare_r2_api'])) {
    $r2Config = $config['cloudflare_r2_api'];
    echo "   ✅ R2 config found\n";
    echo "   Endpoint: " . ($r2Config['endpoint'] ?? 'NOT SET') . "\n";
    echo "   Bucket: " . ($r2Config['bucket_name'] ?? 'NOT SET') . "\n";
    echo "   Access Key ID: " . (isset($r2Config['access_key_id']) && !empty($r2Config['access_key_id']) ? 'SET (' . strlen($r2Config['access_key_id']) . ' chars)' : 'NOT SET') . "\n";
    echo "   Secret Key: " . (isset($r2Config['secret_access_key']) && !empty($r2Config['secret_access_key']) ? 'SET (' . strlen($r2Config['secret_access_key']) . ' chars)' : 'NOT SET') . "\n\n";
    
    // Parse endpoint
    echo "📊 ENDPOINT ANALYSIS:\n";
    $urlParts = parse_url($r2Config['endpoint']);
    echo "   Host: " . ($urlParts['host'] ?? 'INVALID') . "\n";
    echo "   Scheme: " . ($urlParts['scheme'] ?? 'INVALID') . "\n";
    
    // Expected R2 bucket URL format
    $expectedHost = $r2Config['bucket_name'] . '.' . $urlParts['host'];
    echo "   Expected bucket host: $expectedHost\n\n";
    
    // Test URL generation
    echo "🔗 TEST PRESIGNED URL GENERATION:\n";
    $testObjectKey = 'test-uploads/test-' . time() . '.mp4';
    $testUploadId = 'test-upload-id-12345';
    $testPartNumber = 1;
    
    $host = $expectedHost;
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
    
    echo "   Test URL (first 150 chars):\n";
    echo "   " . substr($testUrl, 0, 150) . "...\n\n";
    
    echo "   URL Components:\n";
    echo "   - Protocol: https://\n";
    echo "   - Host: $host\n";
    echo "   - Path: /$testObjectKey\n";
    echo "   - Query params: " . substr($canonicalQueryString, 0, 80) . "...\n\n";
    
} else {
    echo "   ❌ R2 config NOT found in config.php\n\n";
}

echo "═══════════════════════════════════════════════════════════\n";
echo "✅ CONFIG TEST COMPLETE\n";
echo "\nNEXT STEPS:\n";
echo "1. Check browser console for detailed 404 error\n";
echo "2. Look in error log for: '🔗 Generated presigned URL'\n";
echo "3. Compare actual URL with expected format above\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "</pre>";
?>

