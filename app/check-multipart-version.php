<?php
/**
 * R2 Multipart Upload Version Checker
 * Verifies that the correct version of ion-uploadermultipart.php is loaded
 * 
 * Usage: https://iblog.bz/app/check-multipart-version.php
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$result = [
    'timestamp' => date('Y-m-d H:i:s'),
    'checks' => []
];

// Check 1: Does ion-uploadermultipart.php exist?
$multipartFile = __DIR__ . '/ion-uploadermultipart.php';
if (!file_exists($multipartFile)) {
    $result['checks'][] = [
        'name' => 'File Exists',
        'status' => 'FAIL',
        'message' => 'ion-uploadermultipart.php NOT FOUND',
        'critical' => true
    ];
    $result['overall_status'] = 'FAIL';
    echo json_encode($result, JSON_PRETTY_PRINT);
    exit;
}

$result['checks'][] = [
    'name' => 'File Exists',
    'status' => 'PASS',
    'message' => 'ion-uploadermultipart.php found',
    'file_size' => filesize($multipartFile),
    'last_modified' => date('Y-m-d H:i:s', filemtime($multipartFile))
];

// Check 2: Read file contents and look for encoding fix
$content = file_get_contents($multipartFile);

// Check for version constant
if (strpos($content, "define('R2_MULTIPART_VERSION', '3.0')") !== false) {
    $result['checks'][] = [
        'name' => 'Version Constant',
        'status' => 'PASS',
        'message' => 'Version 3.0 detected'
    ];
} else {
    $result['checks'][] = [
        'name' => 'Version Constant',
        'status' => 'FAIL',
        'message' => 'Version 3.0 constant NOT FOUND',
        'critical' => true
    ];
}

// Check 3: Look for the critical encoding fix in initialization
if (strpos($content, 'CRITICAL FIX: Encode object key path the SAME WAY as presigned URLs') !== false) {
    $result['checks'][] = [
        'name' => 'Initialization Encoding Fix',
        'status' => 'PASS',
        'message' => 'Encoding fix comment found'
    ];
} else {
    $result['checks'][] = [
        'name' => 'Initialization Encoding Fix',
        'status' => 'FAIL',
        'message' => 'Encoding fix comment NOT FOUND',
        'critical' => true
    ];
}

// Check 4: Look for the encoding code itself
if (strpos($content, 'rawurlencode') !== false && 
    strpos($content, 'array_map') !== false) {
    $result['checks'][] = [
        'name' => 'Encoding Code Present',
        'status' => 'PASS',
        'message' => 'rawurlencode implementation found'
    ];
} else {
    $result['checks'][] = [
        'name' => 'Encoding Code Present',
        'status' => 'FAIL',
        'message' => 'rawurlencode implementation NOT FOUND',
        'critical' => true
    ];
}

// Check 5: Look for version constant definitions in file content
if (strpos($content, "define('R2_MULTIPART_VERSION', '3.0')") !== false) {
    $result['checks'][] = [
        'name' => 'Version 3.0 Constant Defined',
        'status' => 'PASS',
        'message' => 'Version 3.0 constant found in file'
    ];
} else {
    $result['checks'][] = [
        'name' => 'Version 3.0 Constant Defined',
        'status' => 'FAIL',
        'message' => 'Version 3.0 constant NOT found',
        'critical' => true
    ];
}

// Check 6: Look for encoding fix constant
if (strpos($content, "define('R2_MULTIPART_HAS_ENCODING_FIX', true)") !== false) {
    $result['checks'][] = [
        'name' => 'Encoding Fix Flag',
        'status' => 'PASS',
        'message' => 'Encoding fix flag set to true'
    ];
} else {
    $result['checks'][] = [
        'name' => 'Encoding Fix Flag',
        'status' => 'FAIL',
        'message' => 'Encoding fix flag NOT found',
        'critical' => true
    ];
}

// Check 7: PHP Opcache status
if (function_exists('opcache_get_status')) {
    $opcache = opcache_get_status(false);
    if ($opcache && isset($opcache['opcache_enabled'])) {
        $result['checks'][] = [
            'name' => 'PHP Opcache Status',
            'status' => 'INFO',
            'message' => 'Opcache is ' . ($opcache['opcache_enabled'] ? 'ENABLED' : 'disabled'),
            'opcache_enabled' => $opcache['opcache_enabled'],
            'memory_usage' => isset($opcache['memory_usage']) ? $opcache['memory_usage'] : null
        ];
    }
}

// Determine overall status
$critical_failures = array_filter($result['checks'], function($check) {
    return $check['status'] === 'FAIL' && isset($check['critical']) && $check['critical'];
});

if (count($critical_failures) > 0) {
    $result['overall_status'] = 'FAIL';
    $result['message'] = '❌ CRITICAL: Encoding fix is NOT active! Old version is still loaded.';
    $result['action_required'] = 'Upload ion-uploadermultipart.php and clear PHP opcache';
} else {
    $result['overall_status'] = 'PASS';
    $result['message'] = '✅ SUCCESS: Version 3.0 with encoding fix is active!';
}

// Output result
echo json_encode($result, JSON_PRETTY_PRINT);
?>

