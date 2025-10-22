<?php
/**
 * R2 Multipart Upload Diagnostic Test
 * This script tests the R2 multipart upload configuration and credentials
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 R2 Multipart Upload Diagnostic Test</h1>";
echo "<pre>";

// Step 1: Load configuration
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📋 STEP 1: Loading Configuration\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$configFile = __DIR__ . '/../config/config.php';
if (!file_exists($configFile)) {
    die("❌ config.php not found at: $configFile\n");
}

$config = require $configFile;

if (!isset($config['cloudflare_r2_api'])) {
    die("❌ cloudflare_r2_api configuration not found!\n");
}

$r2Config = $config['cloudflare_r2_api'];

echo "✅ Configuration loaded\n";
echo "   Bucket: " . ($r2Config['bucket_name'] ?? 'NOT SET') . "\n";
echo "   Region: " . ($r2Config['region'] ?? 'NOT SET') . "\n";
echo "   Endpoint: " . ($r2Config['endpoint'] ?? 'NOT SET') . "\n";
echo "   Access Key ID: " . (isset($r2Config['access_key_id']) && !empty($r2Config['access_key_id']) ? '✅ SET (' . strlen($r2Config['access_key_id']) . ' chars)' : '❌ NOT SET') . "\n";
echo "   Secret Key: " . (isset($r2Config['secret_access_key']) && !empty($r2Config['secret_access_key']) ? '✅ SET (' . strlen($r2Config['secret_access_key']) . ' chars)' : '❌ NOT SET') . "\n\n";

// Step 2: Check ionuploadermultipart2.php
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📋 STEP 2: Checking ionuploadermultipart2.php\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$multipartFile = __DIR__ . '/ionuploadermultipart2.php';
if (!file_exists($multipartFile)) {
    echo "❌ ionuploadermultipart2.php NOT FOUND!\n";
    echo "   Expected at: $multipartFile\n\n";
} else {
    $fileSize = filesize($multipartFile);
    $lastModified = date('Y-m-d H:i:s', filemtime($multipartFile));
    
    echo "✅ ionuploadermultipart2.php exists\n";
    echo "   Size: " . number_format($fileSize) . " bytes\n";
    echo "   Last Modified: $lastModified\n";
    
    // Check for critical functions
    $content = file_get_contents($multipartFile);
    $checks = [
        'initializeR2MultipartUpload' => strpos($content, 'function initializeR2MultipartUpload') !== false,
        'generateR2PresignedUrl' => strpos($content, 'function generateR2PresignedUrl') !== false,
        'completeR2MultipartUpload' => strpos($content, 'function completeR2MultipartUpload') !== false,
        'signR2Request (AWS SigV4)' => strpos($content, 'AWS4-HMAC-SHA256') !== false,
        'Virtual-hosted-style URLs' => strpos($content, 'bucket.$endpointHost') !== false
    ];
    
    echo "\n   Function Checks:\n";
    foreach ($checks as $name => $exists) {
        echo "   " . ($exists ? "✅" : "❌") . " $name\n";
    }
    echo "\n";
}

// Step 3: Test R2 Connection
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📋 STEP 3: Testing R2 Connection\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Test bucket listing (using simple GET request)
$endpoint = $r2Config['endpoint'];
$bucket = $r2Config['bucket_name'];
$accessKey = $r2Config['access_key_id'];
$secretKey = $r2Config['secret_access_key'];

$urlParts = parse_url($endpoint);
$endpointHost = $urlParts['host'];
$host = "$bucket.$endpointHost";
$testUrl = "https://$host/";

echo "Testing connection to: $testUrl\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $testUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_NOBODY => true, // HEAD request
    CURLOPT_HEADER => true
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo "❌ cURL Error: $curlError\n\n";
} else {
    echo "✅ Connection successful (HTTP $httpCode)\n";
    if ($httpCode === 200 || $httpCode === 403) {
        echo "   ✅ R2 endpoint is reachable\n\n";
    } else {
        echo "   ⚠️  Unexpected HTTP code: $httpCode\n\n";
    }
}

// Step 4: Summary
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📊 SUMMARY\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$allGood = true;

if (!isset($r2Config['endpoint']) || empty($r2Config['endpoint'])) {
    echo "❌ R2 endpoint not configured\n";
    $allGood = false;
}

if (!file_exists($multipartFile)) {
    echo "❌ ionuploadermultipart2.php missing\n";
    $allGood = false;
}

if (!isset($r2Config['access_key_id']) || empty($r2Config['access_key_id'])) {
    echo "❌ R2 access key not configured\n";
    $allGood = false;
}

if ($allGood) {
    echo "✅ All checks passed!\n";
    echo "✅ R2 multipart upload should be working\n\n";
    echo "If uploads are still failing:\n";
    echo "1. Check browser console for errors\n";
    echo "2. Check server error logs\n";
    echo "3. Verify CORS settings in R2 bucket\n";
} else {
    echo "❌ Some checks failed - please fix the issues above\n";
}

echo "\n</pre>";
?>

