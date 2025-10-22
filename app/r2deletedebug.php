<?php
/**
 * Debug R2 Deletion for a Specific Video
 */

require_once __DIR__ . '/../config/database.php';
$config = require __DIR__ . '/../config/config.php';
require_once __DIR__ . '/creators.php';

// Test video ID or URL
$test_video_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$test_url = isset($_GET['url']) ? $_GET['url'] : '';

?>
<!DOCTYPE html>
<html>
<head>
    <title>R2 Delete Debug</title>
    <style>
        body { font-family: Arial; max-width: 1000px; margin: 20px auto; padding: 20px; }
        .info { background: #d1ecf1; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .success { background: #d4edda; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .error { background: #f8d7da; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .code { background: #f5f5f5; padding: 10px; font-family: monospace; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>🔍 R2 Delete Debug Tool</h1>
    
    <?php if ($test_video_id > 0): ?>
        <h2>Testing Video ID: <?= $test_video_id ?></h2>
        <?php
        $video = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM IONLocalVideos WHERE id = %d",
            $test_video_id
        ));
        
        if (!$video) {
            echo "<div class='error'>❌ Video not found in database</div>";
        } else {
            echo "<div class='info'>";
            echo "<strong>Video Details:</strong><br>";
            echo "ID: {$video->id}<br>";
            echo "Title: {$video->title}<br>";
            echo "Video Link: {$video->video_link}<br>";
            echo "Source: {$video->source}<br>";
            echo "</div>";
            
            // Check R2 detection
            $is_r2_url = strpos($video->video_link, 'r2.cloudflarestorage.com') !== false;
            $is_r2_public_domain = strpos($video->video_link, 'vid.ions.com') !== false;
            
            echo "<div class='info'>";
            echo "<strong>R2 Detection:</strong><br>";
            echo "Contains 'r2.cloudflarestorage.com': " . ($is_r2_url ? '✅ YES' : '❌ NO') . "<br>";
            echo "Contains 'vid.ions.com': " . ($is_r2_public_domain ? '✅ YES' : '❌ NO') . "<br>";
            echo "Is R2 Video: " . (($is_r2_url || $is_r2_public_domain) ? '✅ YES' : '❌ NO') . "<br>";
            echo "</div>";
            
            if ($is_r2_url || $is_r2_public_domain) {
                echo "<div class='info'><strong>🗑️ Attempting R2 Deletion...</strong></div>";
                
                $result = deleteFromCloudflareR2($video->video_link);
                
                if ($result['success']) {
                    echo "<div class='success'>";
                    echo "✅ <strong>SUCCESS!</strong><br>";
                    echo "Message: " . htmlspecialchars($result['message']) . "<br>";
                    echo "</div>";
                } else {
                    echo "<div class='error'>";
                    echo "❌ <strong>FAILED!</strong><br>";
                    echo "Error: " . htmlspecialchars($result['error']) . "<br>";
                    echo "</div>";
                }
                
                echo "<div class='code'>";
                echo "<strong>Full Result:</strong><br>";
                echo "<pre>" . htmlspecialchars(print_r($result, true)) . "</pre>";
                echo "</div>";
            } else {
                echo "<div class='error'>⚠️ Not recognized as R2 video, deletion skipped</div>";
            }
        }
        ?>
        
    <?php elseif (!empty($test_url)): ?>
        <h2>Testing URL: <?= htmlspecialchars($test_url) ?></h2>
        <?php
        echo "<div class='info'><strong>🗑️ Attempting R2 Deletion...</strong></div>";
        
        $result = deleteFromCloudflareR2($test_url);
        
        if ($result['success']) {
            echo "<div class='success'>";
            echo "✅ <strong>SUCCESS!</strong><br>";
            echo "Message: " . htmlspecialchars($result['message']) . "<br>";
            echo "</div>";
        } else {
            echo "<div class='error'>";
            echo "❌ <strong>FAILED!</strong><br>";
            echo "Error: " . htmlspecialchars($result['error']) . "<br>";
            echo "</div>";
        }
        
        echo "<div class='code'>";
        echo "<strong>Full Result:</strong><br>";
        echo "<pre>" . htmlspecialchars(print_r($result, true)) . "</pre>";
        echo "</div>";
        ?>
        
    <?php else: ?>
        <div class="info">
            <strong>Usage:</strong><br>
            Test by Video ID: <code>?id=VIDEO_ID</code><br>
            Test by URL: <code>?url=FULL_R2_URL</code>
        </div>
        
        <form method="get">
            <h3>Test by Video ID:</h3>
            <input type="number" name="id" placeholder="Video ID" style="padding: 8px; width: 200px;">
            <button type="submit" style="padding: 8px 16px;">Test</button>
        </form>
        
        <form method="get" style="margin-top: 20px;">
            <h3>Test by URL:</h3>
            <input type="text" name="url" placeholder="https://vid.ions.com/..." style="padding: 8px; width: 500px;">
            <button type="submit" style="padding: 8px 16px;">Test</button>
        </form>
        
        <div class="info" style="margin-top: 30px;">
            <strong>Quick Test Links:</strong><br>
            <a href="?url=https://vid.ions.com/2025/10/18/68f3f497c2feb_video_68f3f497c2fdb4.68005153.mp4">Test the orphaned file</a>
        </div>
    <?php endif; ?>
    
</body>
</html>

