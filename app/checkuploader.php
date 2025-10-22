<?php
/**
 * Check Current Uploader Configuration
 * Verifies which version of JavaScript files are being served
 */

header('Content-Type: application/json');

$checks = [
    'ion-uploader.js' => __DIR__ . '/ion-uploader.js',
    'ion-uploaderpro.js' => __DIR__ . '/ion-uploaderpro.js',
    'ion-uploadermultipart.php' => __DIR__ . '/ion-uploadermultipart.php'
];

$results = [];

foreach ($checks as $name => $path) {
    if (!file_exists($path)) {
        $results[$name] = [
            'exists' => false,
            'error' => 'File not found'
        ];
        continue;
    }
    
    $content = file_get_contents($path);
    $size = filesize($path);
    $modified = date('Y-m-d H:i:s', filemtime($path));
    
    $info = [
        'exists' => true,
        'size' => $size,
        'modified' => $modified
    ];
    
    // Check for 50MB or 100MB part size
    if (strpos($name, '.js') !== false) {
        if (preg_match('/partSize.*?(\d+)\s*\*\s*1024\s*\*\s*1024/', $content, $matches)) {
            $partSizeMB = (int)$matches[1];
            $info['partSize'] = $partSizeMB . 'MB';
            $info['partSize_status'] = $partSizeMB === 50 ? 'CORRECT' : 'NEEDS_UPDATE';
        }
        
        // Check maxConcurrentUploads
        if (preg_match('/maxConcurrentUploads.*?(\d+)/', $content, $matches)) {
            $concurrent = (int)$matches[1];
            $info['maxConcurrentUploads'] = $concurrent;
            $info['concurrent_status'] = $concurrent === 1 ? 'CORRECT' : 'NEEDS_UPDATE';
        }
        
        // Check maxRetries
        if (preg_match('/this\.maxRetries.*?(\d+)/', $content, $matches)) {
            $retries = (int)$matches[1];
            $info['maxRetries'] = $retries;
            $info['retries_status'] = $retries === 5 ? 'CORRECT' : 'NEEDS_UPDATE';
        }
    }
    
    // For PHP file, check version
    if (strpos($name, '.php') !== false) {
        if (preg_match("/define\('R2_MULTIPART_VERSION',\s*'([^']+)'/", $content, $matches)) {
            $info['version'] = $matches[1];
        }
        if (preg_match("/define\('R2_MULTIPART_HAS_ENCODING_FIX',\s*(true|false)/", $content, $matches)) {
            $info['has_encoding_fix'] = $matches[1] === 'true';
        }
    }
    
    $results[$name] = $info;
}

// Overall status
$needsUpdate = false;
foreach ($results as $name => $info) {
    if (isset($info['partSize_status']) && $info['partSize_status'] === 'NEEDS_UPDATE') {
        $needsUpdate = true;
        break;
    }
    if (isset($info['concurrent_status']) && $info['concurrent_status'] === 'NEEDS_UPDATE') {
        $needsUpdate = true;
        break;
    }
    if (isset($info['retries_status']) && $info['retries_status'] === 'NEEDS_UPDATE') {
        $needsUpdate = true;
        break;
    }
}

echo json_encode([
    'timestamp' => date('Y-m-d H:i:s'),
    'files' => $results,
    'overall_status' => $needsUpdate ? 'NEEDS_UPDATE' : 'OK',
    'message' => $needsUpdate 
        ? '⚠️ Files need to be updated to production' 
        : '✅ All files are up to date with 50MB parts'
], JSON_PRETTY_PRINT);

