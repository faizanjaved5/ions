<?php
/**
 * Quick diagnostic to check if ion-uploaderpro.js was uploaded correctly
 */

$file = __DIR__ . '/ion-uploaderpro.js';  // Clean hyphenated name

echo "<!DOCTYPE html><html><head><title>File Check</title></head><body>";
echo "<h1>File Diagnostic - ion-uploaderpro.js</h1>";
echo "<pre>";

// Check if file exists
if (file_exists($file)) {
    echo "✅ File exists: $file\n\n";
    
    // File size
    $size = filesize($file);
    echo "📊 File size: " . number_format($size) . " bytes (" . round($size/1024, 2) . " KB)\n\n";
    
    // Last modified
    $mtime = filemtime($file);
    echo "🕐 Last modified: " . date('Y-m-d H:i:s', $mtime) . "\n";
    echo "   Timestamp: $mtime\n\n";
    
    // Read first 1000 characters to check content
    $content = file_get_contents($file, false, null, 0, 2000);
    echo "📄 First 2000 characters:\n";
    echo "---\n";
    echo htmlspecialchars($content);
    echo "\n---\n\n";
    
    // Check for the critical line
    $fullContent = file_get_contents($file);
    if (strpos($fullContent, '50 * 1024 * 1024') !== false) {
        echo "✅ CORRECT VERSION: Contains '50 * 1024 * 1024' (50MB)\n";
    } elseif (strpos($fullContent, '100 * 1024 * 1024') !== false) {
        echo "❌ OLD VERSION: Contains '100 * 1024 * 1024' (100MB)\n";
        echo "⚠️  The file on the server is still the OLD version!\n";
    } else {
        echo "⚠️  Could not find part size definition\n";
    }
    
    // Check specific line
    $lines = file($file);
    if (isset($lines[15])) {
        echo "\n📍 Line 16 (where partSize is defined):\n";
        echo htmlspecialchars($lines[15]);
    }
    
} else {
    echo "❌ File NOT found: $file\n";
    echo "Current directory: " . __DIR__ . "\n";
}

echo "</pre>";

// Show opcache status
if (function_exists('opcache_get_status')) {
    $opcache = opcache_get_status();
    echo "<h2>Opcache Status</h2><pre>";
    echo "Enabled: " . ($opcache['opcache_enabled'] ? 'YES' : 'NO') . "\n";
    if ($opcache['opcache_enabled']) {
        echo "Memory used: " . round($opcache['memory_usage']['used_memory']/1024/1024, 2) . " MB\n";
        echo "Cached scripts: " . $opcache['opcache_statistics']['num_cached_scripts'] . "\n";
        
        echo "\n<a href='?clear_opcache=1' style='display:inline-block;padding:10px 20px;background:#e74c3c;color:white;text-decoration:none;border-radius:5px;'>Clear Opcache</a>\n";
    }
    echo "</pre>";
}

// Clear opcache if requested
if (isset($_GET['clear_opcache'])) {
    if (function_exists('opcache_reset')) {
        opcache_reset();
        echo "<p style='color:green;font-weight:bold;'>✅ Opcache cleared! Refresh this page.</p>";
    }
}

echo "</body></html>";

