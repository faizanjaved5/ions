<?php
/**
 * Simple test page for share functionality debugging
 */

// Load dependencies
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/share-manager.php';

echo "<h2>🧪 Share System Test</h2>";
echo "<style>body{font-family:monospace; background:#0f172a; color:#e2e8f0; padding:20px;} .test{background:#1e293b; padding:15px; margin:10px 0; border-radius:8px;} .success{border-left:4px solid #10b981;} .error{border-left:4px solid #ef4444;}</style>";

// Test 1: Share Manager Initialization
echo "<div class='test'>";
echo "<h3>1. Share Manager Initialization</h3>";
try {
    $share_manager = new IONShareManager($db);
    echo "<span style='color:#10b981;'>✅ IONShareManager created successfully</span><br>";
} catch (Exception $e) {
    echo "<span style='color:#ef4444;'>❌ Failed to create IONShareManager: " . htmlspecialchars($e->getMessage()) . "</span><br>";
    exit();
}
echo "</div>";

// Test 2: Get a sample video
echo "<div class='test'>";
echo "<h3>2. Sample Video Lookup</h3>";
try {
    $stmt = $db->prepare("SELECT id, title, short_link FROM IONLocalVideos ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $sample_video = $stmt->fetch();
    
    if ($sample_video) {
        echo "<span style='color:#10b981;'>✅ Found sample video:</span><br>";
        echo "ID: {$sample_video['id']}<br>";
        echo "Title: " . htmlspecialchars($sample_video['title']) . "<br>";
        echo "Shortlink: " . ($sample_video['short_link'] ? $sample_video['short_link'] : 'None') . "<br>";
        $test_video_id = $sample_video['id'];
    } else {
        echo "<span style='color:#ef4444;'>❌ No videos found in database</span><br>";
        exit();
    }
} catch (Exception $e) {
    echo "<span style='color:#ef4444;'>❌ Database error: " . htmlspecialchars($e->getMessage()) . "</span><br>";
    exit();
}
echo "</div>";

// Test 3: Share Data Generation
echo "<div class='test'>";
echo "<h3>3. Share Data Generation</h3>";
try {
    $share_data = $share_manager->getShareData($test_video_id);
    
    if ($share_data) {
        echo "<span style='color:#10b981;'>✅ Share data generated:</span><br>";
        echo "URL: " . htmlspecialchars($share_data['url']) . "<br>";
        echo "Title: " . htmlspecialchars($share_data['title']) . "<br>";
        echo "Clicks: " . $share_data['clicks'] . "<br>";
        echo "Platforms: " . count($share_data['platforms']) . " available<br>";
    } else {
        echo "<span style='color:#ef4444;'>❌ Failed to generate share data</span><br>";
    }
} catch (Exception $e) {
    echo "<span style='color:#ef4444;'>❌ Share data error: " . htmlspecialchars($e->getMessage()) . "</span><br>";
}
echo "</div>";

// Test 4: Share Button Rendering
echo "<div class='test'>";
echo "<h3>4. Share Button Rendering</h3>";
try {
    $share_button = $share_manager->renderShareButton($test_video_id, ['size' => 'small', 'platforms' => ['facebook', 'twitter', 'whatsapp', 'copy']]);
    
    if ($share_button) {
        echo "<span style='color:#10b981;'>✅ Share button rendered:</span><br>";
        echo "<div style='background:#0f172a; padding:10px; margin:10px 0; border-radius:4px;'>";
        echo $share_button;
        echo "</div>";
        echo "<textarea readonly style='width:100%; height:100px; background:#0f172a; color:#e2e8f0; border:1px solid #334155; padding:5px; font-family:monospace; font-size:11px;'>" . htmlspecialchars($share_button) . "</textarea>";
    } else {
        echo "<span style='color:#ef4444;'>❌ Share button returned empty</span><br>";
    }
} catch (Exception $e) {
    echo "<span style='color:#ef4444;'>❌ Share button error: " . htmlspecialchars($e->getMessage()) . "</span><br>";
}
echo "</div>";

// Test 5: CSS and JS Files
echo "<div class='test'>";
echo "<h3>5. Required Files Check</h3>";
$files_to_check = [
    '/share/get-share-data.php' => __DIR__ . '/get-share-data.php',
    '/share/enhanced-ion-share.js' => __DIR__ . '/enhanced-ion-share.js',
    '/share/enhanced-ion-share.css' => __DIR__ . '/enhanced-ion-share.css'
];

foreach ($files_to_check as $url => $path) {
    if (file_exists($path)) {
        echo "<span style='color:#10b981;'>✅ {$url}</span><br>";
    } else {
        echo "<span style='color:#ef4444;'>❌ {$url} (missing)</span><br>";
    }
}
echo "</div>";

// Test 6: API Endpoint Test
echo "<div class='test'>";
echo "<h3>6. API Endpoint Test</h3>";
echo "<p>Test the share data API manually:</p>";
echo "<a href='/share/get-share-data.php?video_id={$test_video_id}' target='_blank' style='color:#3b82f6;'>Test API: /share/get-share-data.php?video_id={$test_video_id}</a><br>";
echo "</div>";

// Include CSS and JS for testing (enhanced)
echo "<link rel='stylesheet' href='/share/enhanced-ion-share.css'>";
echo "<script src='/share/enhanced-ion-share.js?v=2'></script>";

echo "<p><a href='../app/creators.php' style='color:#3b82f6;'>← Back to Creators</a></p>";
?>
