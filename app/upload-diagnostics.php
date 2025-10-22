<?php
/**
 * Upload System Diagnostics
 * Complete diagnostic of R2 upload system
 */

// Prevent output buffering
while (ob_get_level()) ob_end_clean();

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>ION Upload System Diagnostics</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: #1a1a1a;
            color: #fff;
        }
        .section {
            background: #2a2a2a;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            border-left: 4px solid #3b82f6;
        }
        .success { border-left-color: #10b981; }
        .error { border-left-color: #ef4444; }
        .warning { border-left-color: #f59e0b; }
        h1 { color: #3b82f6; }
        h2 { color: #60a5fa; margin-top: 0; }
        pre {
            background: #1a1a1a;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            border: 1px solid #404040;
        }
        .status { 
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-weight: bold;
            margin-right: 10px;
        }
        .status.ok { background: #10b981; color: white; }
        .status.fail { background: #ef4444; color: white; }
        .status.warn { background: #f59e0b; color: white; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #404040;
        }
        th { background: #1a1a1a; font-weight: 600; }
        .code { 
            background: #1a1a1a;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            color: #60a5fa;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background: #3b82f6;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 5px;
        }
        .button:hover { background: #2563eb; }
        .button.danger { background: #ef4444; }
        .button.danger:hover { background: #dc2626; }
    </style>
</head>
<body>
    <h1>🔍 ION Upload System Diagnostics</h1>
    <p>Complete diagnostic of R2 Multipart Upload system</p>

<?php
$issues = [];
$warnings = [];
$success = [];

// ============================================
// 1. CHECK PHP CONFIGURATION
// ============================================
echo '<div class="section">';
echo '<h2>1. PHP Configuration</h2>';

$phpConfig = [
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'max_execution_time' => ini_get('max_execution_time'),
    'memory_limit' => ini_get('memory_limit'),
    'max_input_time' => ini_get('max_input_time')
];

echo '<table>';
echo '<tr><th>Setting</th><th>Current Value</th><th>Required</th><th>Status</th></tr>';

$phpChecks = [
    'upload_max_filesize' => ['value' => '100M', 'min' => 104857600],
    'post_max_size' => ['value' => '100M', 'min' => 104857600],
    'max_execution_time' => ['value' => '300', 'min' => 300],
    'memory_limit' => ['value' => '512M', 'min' => 536870912],
    'max_input_time' => ['value' => '300', 'min' => 300]
];

foreach ($phpChecks as $key => $check) {
    $current = ini_get($key);
    $currentBytes = $key === 'max_execution_time' || $key === 'max_input_time' ? (int)$current : convertToBytes($current);
    $isOk = $currentBytes >= $check['min'];
    
    echo '<tr>';
    echo '<td><code>' . $key . '</code></td>';
    echo '<td>' . $current . '</td>';
    echo '<td>' . $check['value'] . '</td>';
    echo '<td>';
    if ($isOk) {
        echo '<span class="status ok">✓ OK</span>';
        $success[] = "$key is configured correctly";
    } else {
        echo '<span class="status fail">✗ FAIL</span>';
        $issues[] = "$key is too low: $current (need {$check['value']})";
    }
    echo '</td>';
    echo '</tr>';
}

echo '</table>';
echo '</div>';

// ============================================
// 2. CHECK CRITICAL FILES
// ============================================
echo '<div class="section">';
echo '<h2>2. Critical Files Check</h2>';

$files = [
    'ion-uploaderpro.js' => __DIR__ . '/ion-uploaderpro.js',  // Clean hyphenated name
    'ion-uploadermultipart.php' => __DIR__ . '/ion-uploadermultipart.php',
    'ion-uploader.js' => __DIR__ . '/ion-uploader.js',
    'ion-uploader.php' => __DIR__ . '/ion-uploader.php'
];

echo '<table>';
echo '<tr><th>File</th><th>Exists</th><th>Size</th><th>Modified</th><th>Status</th></tr>';

foreach ($files as $name => $path) {
    echo '<tr>';
    echo '<td><code>' . $name . '</code></td>';
    
    if (file_exists($path)) {
        $size = filesize($path);
        $mtime = filemtime($path);
        
        echo '<td><span class="status ok">✓ Yes</span></td>';
        echo '<td>' . number_format($size) . ' bytes</td>';
        echo '<td>' . date('Y-m-d H:i:s', $mtime) . '</td>';
        
        // Check specific requirements
        if ($name === 'ion-uploaderpro.js') {
            $content = file_get_contents($path);
            
            // Check for R2MultipartUploader class
            if (strpos($content, 'class R2MultipartUploader') !== false) {
                echo '<td><span class="status ok">✓ Valid</span></td>';
                
                // Check part size
                if (strpos($content, '100 * 1024 * 1024') !== false) {
                    $success[] = "ion-uploaderpro.js has 100MB part size (optimal)";
                } elseif (strpos($content, '50 * 1024 * 1024') !== false) {
                    $warnings[] = "ion-uploaderpro.js has 50MB part size (works, but 100MB is better for large files)";
                } else {
                    $issues[] = "ion-uploaderpro.js has unknown part size";
                }
            } else {
                echo '<td><span class="status fail">✗ Invalid</span></td>';
                $issues[] = "ion-uploaderpro.js is missing R2MultipartUploader class";
            }
        } else {
            echo '<td><span class="status ok">✓ OK</span></td>';
        }
        
    } else {
        echo '<td><span class="status fail">✗ No</span></td>';
        echo '<td>-</td><td>-</td>';
        echo '<td><span class="status fail">✗ MISSING</span></td>';
        $issues[] = "$name is MISSING from server!";
    }
    
    echo '</tr>';
}

echo '</table>';
echo '</div>';

// ============================================
// 3. CHECK R2 CONFIGURATION
// ============================================
echo '<div class="section">';
echo '<h2>3. R2 Configuration</h2>';

require_once __DIR__ . '/../config/config.php';
$config = require __DIR__ . '/../config/config.php';

if (isset($config['cloudflare_r2_api'])) {
    $r2 = $config['cloudflare_r2_api'];
    
    echo '<table>';
    echo '<tr><th>Setting</th><th>Value</th><th>Status</th></tr>';
    
    $r2Required = ['endpoint', 'bucket_name', 'access_key_id', 'secret_access_key'];
    
    foreach ($r2Required as $key) {
        echo '<tr>';
        echo '<td><code>' . $key . '</code></td>';
        
        if (!empty($r2[$key])) {
            $display = $key === 'secret_access_key' ? substr($r2[$key], 0, 8) . '...' : $r2[$key];
            echo '<td>' . $display . '</td>';
            echo '<td><span class="status ok">✓ Set</span></td>';
            $success[] = "R2 $key is configured";
        } else {
            echo '<td>-</td>';
            echo '<td><span class="status fail">✗ Missing</span></td>';
            $issues[] = "R2 $key is NOT configured!";
        }
        
        echo '</tr>';
    }
    
    echo '</table>';
} else {
    echo '<span class="status fail">✗ FAIL</span> R2 configuration not found!';
    $issues[] = "Cloudflare R2 API not configured in config.php";
}

echo '</div>';

// ============================================
// 4. CHECK OPCACHE
// ============================================
echo '<div class="section">';
echo '<h2>4. Opcache Status</h2>';

if (function_exists('opcache_get_status')) {
    $opcache = opcache_get_status();
    
    if ($opcache && $opcache['opcache_enabled']) {
        echo '<span class="status warn">⚠ Enabled</span>';
        echo '<p>Opcache is enabled. This can cause old cached versions of files to be served.</p>';
        
        echo '<table>';
        echo '<tr><th>Metric</th><th>Value</th></tr>';
        echo '<tr><td>Memory Used</td><td>' . round($opcache['memory_usage']['used_memory']/1024/1024, 2) . ' MB</td></tr>';
        echo '<tr><td>Cached Scripts</td><td>' . $opcache['opcache_statistics']['num_cached_scripts'] . '</td></tr>';
        echo '<tr><td>Hits</td><td>' . number_format($opcache['opcache_statistics']['hits']) . '</td></tr>';
        echo '<tr><td>Misses</td><td>' . number_format($opcache['opcache_statistics']['misses']) . '</td></tr>';
        echo '</table>';
        
        echo '<a href="?clear_opcache=1" class="button danger">Clear Opcache</a>';
        
        if (isset($_GET['clear_opcache'])) {
            opcache_reset();
            echo '<p style="color:#10b981;font-weight:bold;">✓ Opcache cleared! Refresh this page.</p>';
        }
        
        $warnings[] = "Opcache is enabled - may serve old files";
    } else {
        echo '<span class="status ok">✓ Disabled</span>';
        echo '<p>Opcache is disabled or not caching scripts.</p>';
        $success[] = "Opcache not interfering with file updates";
    }
} else {
    echo '<span class="status ok">✓ Not Available</span>';
    echo '<p>Opcache is not available.</p>';
    $success[] = "Opcache not installed";
}

echo '</div>';

// ============================================
// 5. TEST FILE ACCESSIBILITY
// ============================================
echo '<div class="section">';
echo '<h2>5. File Accessibility Test</h2>';

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
$ionuploaderpro_url = $baseUrl . $scriptDir . '/ion-uploaderpro.js';  // Check renamed file

echo '<p>Testing if <code>ion-uploaderpro.js</code> can be accessed via HTTP...</p>';
echo '<p>URL: <a href="' . $ionuploaderpro_url . '" target="_blank">' . $ionuploaderpro_url . '</a></p>';

// Try to fetch the file
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $ionuploaderpro_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200 && strlen($response) > 1000) {
    echo '<span class="status ok">✓ Accessible</span>';
    echo '<p>File loads successfully via HTTP. Size: ' . number_format(strlen($response)) . ' bytes</p>';
    
    if (strpos($response, 'R2MultipartUploader') !== false) {
        echo '<p><span class="status ok">✓</span> Contains R2MultipartUploader class</p>';
        $success[] = "ion-uploaderpro.js is accessible and valid";
    } else {
        echo '<p><span class="status fail">✗</span> Does NOT contain R2MultipartUploader class!</p>';
        $issues[] = "ion-uploaderpro.js is accessible but content is wrong";
    }
} else {
    echo '<span class="status fail">✗ NOT Accessible</span>';
    echo '<p>HTTP Code: ' . $httpCode . '</p>';
    echo '<p>Response length: ' . strlen($response) . ' bytes</p>';
    $issues[] = "ion-uploaderpro.js returns HTTP $httpCode (should be 200)";
}

echo '</div>';

// ============================================
// SUMMARY
// ============================================
echo '<div class="section ' . (empty($issues) ? 'success' : 'error') . '">';
echo '<h2>📊 Summary</h2>';

echo '<h3 style="color:#10b981;">✓ Working (' . count($success) . ')</h3>';
if (!empty($success)) {
    echo '<ul>';
    foreach ($success as $item) {
        echo '<li>' . $item . '</li>';
    }
    echo '</ul>';
} else {
    echo '<p>None</p>';
}

if (!empty($warnings)) {
    echo '<h3 style="color:#f59e0b;">⚠ Warnings (' . count($warnings) . ')</h3>';
    echo '<ul>';
    foreach ($warnings as $item) {
        echo '<li>' . $item . '</li>';
    }
    echo '</ul>';
}

if (!empty($issues)) {
    echo '<h3 style="color:#ef4444;">✗ Critical Issues (' . count($issues) . ')</h3>';
    echo '<ul>';
    foreach ($issues as $item) {
        echo '<li><strong>' . $item . '</strong></li>';
    }
    echo '</ul>';
    
    echo '<h3>🔧 Next Steps</h3>';
    echo '<ol>';
    
    if (in_array("ion-uploaderpro.js is MISSING from server!", $issues)) {
        echo '<li><strong>Upload ion-uploaderpro.js to the server</strong> in the /app/ directory</li>';
    }
    
    if (strpos(implode('', $issues), 'returns HTTP') !== false) {
        echo '<li>Check .htaccess file - ensure .js files are not blocked</li>';
        echo '<li>Check file permissions - should be 644</li>';
    }
    
    if (strpos(implode('', $issues), 'R2') !== false && strpos(implode('', $issues), 'configured') !== false) {
        echo '<li>Configure Cloudflare R2 API credentials in config/config.php</li>';
    }
    
    if (in_array("Opcache is enabled - may serve old files", $warnings)) {
        echo '<li>Clear Opcache using the button above</li>';
    }
    
    echo '</ol>';
} else {
    echo '<h3 style="color:#10b981;">✅ All Systems Operational!</h3>';
    echo '<p>R2 Multipart Upload system is properly configured and ready to use.</p>';
    echo '<p><a href="ionuploader.php" class="button">Open Uploader</a></p>';
}

echo '</div>';

// ============================================
// HELPER FUNCTIONS
// ============================================
function convertToBytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    
    switch($last) {
        case 'g': $val *= 1024;
        case 'm': $val *= 1024;
        case 'k': $val *= 1024;
    }
    
    return $val;
}
?>

</body>
</html>

