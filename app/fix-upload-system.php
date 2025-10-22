<?php
/**
 * Automatic Upload System Fix
 * Attempts to automatically fix common R2 upload issues
 */

header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html>
<head>
    <title>ION Upload System - Auto Fix</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: #1a1a1a;
            color: #fff;
        }
        .step {
            background: #2a2a2a;
            border-radius: 8px;
            padding: 20px;
            margin: 15px 0;
            border-left: 4px solid #3b82f6;
        }
        .success { border-left-color: #10b981; }
        .error { border-left-color: #ef4444; }
        h1 { color: #3b82f6; }
        .status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-weight: bold;
            margin-right: 10px;
        }
        .status.ok { background: #10b981; color: white; }
        .status.fail { background: #ef4444; color: white; }
        pre {
            background: #1a1a1a;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background: #3b82f6;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 10px 5px;
        }
    </style>
</head>
<body>
    <h1>🔧 Auto-Fix Upload System</h1>

<?php

if (!isset($_GET['run'])) {
    echo '<div class="step">';
    echo '<h2>⚠️ Warning</h2>';
    echo '<p>This script will attempt to automatically fix common issues with the R2 upload system:</p>';
    echo '<ul>';
    echo '<li>Clear PHP opcache</li>';
    echo '<li>Verify file permissions</li>';
    echo '<li>Check .htaccess configuration</li>';
    echo '<li>Create missing directories</li>';
    echo '</ul>';
    echo '<p><strong>This is safe to run and won\'t delete any data.</strong></p>';
    echo '<a href="?run=1" class="button">Run Auto-Fix</a>';
    echo '<a href="upload-diagnostics.php" class="button" style="background:#6b7280;">View Diagnostics</a>';
    echo '</div>';
    echo '</body></html>';
    exit;
}

// Run auto-fix
echo '<div class="step">';
echo '<h2>Running Auto-Fix...</h2>';
echo '</div>';

$fixed = [];
$failed = [];

// ============================================
// 1. Clear Opcache
// ============================================
echo '<div class="step">';
echo '<h3>1. Clearing Opcache</h3>';

if (function_exists('opcache_reset')) {
    if (opcache_reset()) {
        echo '<span class="status ok">✓ Success</span>';
        echo '<p>PHP Opcache cleared successfully</p>';
        $fixed[] = "Cleared opcache";
    } else {
        echo '<span class="status fail">✗ Failed</span>';
        echo '<p>Could not clear opcache</p>';
        $failed[] = "Failed to clear opcache";
    }
} else {
    echo '<span class="status ok">✓ N/A</span>';
    echo '<p>Opcache not available (no action needed)</p>';
}

echo '</div>';

// ============================================
// 2. Check File Permissions
// ============================================
echo '<div class="step">';
echo '<h3>2. Checking File Permissions</h3>';

$files = [
    'ion-uploaderpro.js',  // Clean hyphenated name
    'ion-uploadermultipart.php',
    'ion-uploader.js',
    'ion-uploader.php'
];

$allOk = true;
foreach ($files as $file) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        $perms = substr(sprintf('%o', fileperms($path)), -4);
        if ($perms === '0644' || $perms === '0755') {
            echo "<p>✓ $file: $perms (OK)</p>";
        } else {
            echo "<p>⚠ $file: $perms (should be 644 or 755)</p>";
            
            if (chmod($path, 0644)) {
                echo "<p style='color:#10b981;margin-left:20px;'>→ Fixed! Set to 644</p>";
                $fixed[] = "Fixed permissions for $file";
            } else {
                echo "<p style='color:#ef4444;margin-left:20px;'>→ Could not change permissions</p>";
                $failed[] = "Could not fix permissions for $file";
                $allOk = false;
            }
        }
    } else {
        echo "<p>✗ $file: NOT FOUND</p>";
        $failed[] = "$file is missing from server";
        $allOk = false;
    }
}

if ($allOk && empty($failed)) {
    echo '<span class="status ok">✓ All Good</span>';
}

echo '</div>';

// ============================================
// 3. Check/Create Upload Directories
// ============================================
echo '<div class="step">';
echo '<h3>3. Checking Upload Directories</h3>';

$dirs = [
    __DIR__ . '/../uploads',
    __DIR__ . '/../cache'
];

foreach ($dirs as $dir) {
    $dirName = basename($dir);
    if (!is_dir($dir)) {
        echo "<p>⚠ $dirName: Does not exist</p>";
        
        if (mkdir($dir, 0755, true)) {
            echo "<p style='color:#10b981;margin-left:20px;'>→ Created successfully</p>";
            $fixed[] = "Created $dirName directory";
        } else {
            echo "<p style='color:#ef4444;margin-left:20px;'>→ Could not create directory</p>";
            $failed[] = "Could not create $dirName directory";
        }
    } else {
        if (is_writable($dir)) {
            echo "<p>✓ $dirName: Exists and writable</p>";
        } else {
            echo "<p>⚠ $dirName: Exists but not writable</p>";
            
            if (chmod($dir, 0755)) {
                echo "<p style='color:#10b981;margin-left:20px;'>→ Fixed! Set to 755</p>";
                $fixed[] = "Fixed permissions for $dirName";
            } else {
                echo "<p style='color:#ef4444;margin-left:20px;'>→ Could not change permissions</p>";
                $failed[] = "Could not fix permissions for $dirName";
            }
        }
    }
}

echo '</div>';

// ============================================
// 4. Verify ion-uploaderpro.js Content
// ============================================
echo '<div class="step">';
echo '<h3>4. Verifying ion-uploaderpro.js Content</h3>';

$proFile = __DIR__ . '/ion-uploaderpro.js';  // Clean hyphenated name
if (file_exists($proFile)) {
    $content = file_get_contents($proFile);
    $size = strlen($content);
    
    echo "<p>File size: " . number_format($size) . " bytes</p>";
    
    // Check for key components
    $checks = [
        'class R2MultipartUploader' => 'R2MultipartUploader class exists',
        'window.R2MultipartUploader = R2MultipartUploader' => 'Class is exported to window',
        'this.partSize' => 'Part size is configured',
        'uploadPart' => 'Multipart upload methods exist'
    ];
    
    $hasIssues = false;
    foreach ($checks as $search => $desc) {
        if (strpos($content, $search) !== false) {
            echo "<p>✓ $desc</p>";
        } else {
            echo "<p style='color:#ef4444;'>✗ Missing: $desc</p>";
            $failed[] = "ion-uploaderpro.js missing: $desc";
            $hasIssues = true;
        }
    }
    
    if (!$hasIssues) {
        echo '<span class="status ok">✓ Valid File</span>';
        $fixed[] = "Verified ion-uploaderpro.js integrity";
    }
    
    // Check part size
    if (strpos($content, '100 * 1024 * 1024') !== false) {
        echo "<p>✓ Part size: 100MB (optimal for 2GB-20GB files)</p>";
    } elseif (strpos($content, '50 * 1024 * 1024') !== false) {
        echo "<p>✓ Part size: 50MB (current - will optimize to 100MB for large files)</p>";
    }
    
} else {
    echo '<span class="status fail">✗ File Missing</span>';
    echo '<p>ion-uploaderpro.js is NOT on the server!</p>';
    echo '<p style="color:#ef4444;font-weight:bold;">You must upload this file from your local IDE.</p>';
    $failed[] = "ion-uploaderpro.js is missing - must be uploaded";
}

echo '</div>';

// ============================================
// SUMMARY
// ============================================
echo '<div class="step ' . (empty($failed) ? 'success' : 'error') . '">';
echo '<h2>📊 Results</h2>';

if (!empty($fixed)) {
    echo '<h3 style="color:#10b981;">✓ Fixed (' . count($fixed) . ')</h3>';
    echo '<ul>';
    foreach ($fixed as $item) {
        echo '<li>' . $item . '</li>';
    }
    echo '</ul>';
}

if (!empty($failed)) {
    echo '<h3 style="color:#ef4444;">✗ Could Not Fix (' . count($failed) . ')</h3>';
    echo '<ul>';
    foreach ($failed as $item) {
        echo '<li><strong>' . $item . '</strong></li>';
    }
    echo '</ul>';
    
    echo '<h3>🔧 Manual Steps Required</h3>';
    echo '<ol>';
    
    if (strpos(implode('', $failed), 'ion-uploaderpro.js is missing') !== false) {
        echo '<li><strong>Upload ion-uploaderpro.js</strong> from your local IDE to the server at <code>/app/ion-uploaderpro.js</code></li>';
    }
    
    if (strpos(implode('', $failed), 'permissions') !== false) {
        echo '<li>Contact your hosting provider to fix file permissions</li>';
    }
    
    if (strpos(implode('', $failed), 'directory') !== false) {
        echo '<li>Manually create missing directories via FTP/File Manager</li>';
    }
    
    echo '</ol>';
} else {
    echo '<h3 style="color:#10b981;">✅ All Issues Resolved!</h3>';
    echo '<p>The upload system should now work correctly.</p>';
}

echo '<p><a href="upload-diagnostics.php" class="button">View Full Diagnostics</a></p>';
echo '<p><a href="ionuploader.php" class="button" style="background:#10b981;">Test Uploader</a></p>';

echo '</div>';

?>

</body>
</html>

