<?php
/**
 * Check Video URLs in Database
 * Shows what URLs are actually stored and if they'll be detected as R2
 */

require_once __DIR__ . '/../config/database.php';

// Use the global $db instance
global $db;

?>
<!DOCTYPE html>
<html>
<head>
    <title>Check Video URLs</title>
    <style>
        body { font-family: Arial; max-width: 1200px; margin: 20px auto; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #f5f5f5; }
        .r2-yes { background: #d4edda; }
        .r2-no { background: #f8d7da; }
        .url { font-family: monospace; font-size: 12px; word-break: break-all; }
    </style>
</head>
<body>
    <h1>🔍 Video URL Detection Check</h1>
    <p>Shows all videos and whether their URLs will be detected as R2 for deletion</p>
    
    <?php
    $videos = $db->get_results("SELECT id, title, video_link, source, thumbnail FROM IONLocalVideos ORDER BY id DESC LIMIT 20");
    
    if (!$videos) {
        echo "<p>No videos found</p>";
    } else {
        echo "<table>";
        echo "<tr>
                <th>ID</th>
                <th>Title</th>
                <th>Source</th>
                <th>Video URL</th>
                <th>R2 Detection</th>
                <th>Thumbnail URL</th>
                <th>Thumb R2</th>
              </tr>";
        
        foreach ($videos as $video) {
            // R2 detection logic (same as in delete function)
            $is_r2_url = strpos($video->video_link, 'r2.cloudflarestorage.com') !== false;
            $is_r2_public_domain = strpos($video->video_link, 'vid.ions.com') !== false;
            $is_youtube = strpos($video->video_link, 'youtube.com') !== false || strpos($video->video_link, 'youtu.be') !== false;
            $is_external_platform = strpos($video->video_link, 'vimeo.com') !== false || strpos($video->video_link, 'rumble.com') !== false;
            
            $is_r2_video = ($is_r2_url || $is_r2_public_domain) && !$is_youtube && !$is_external_platform;
            
            // Thumbnail detection
            $is_r2_thumbnail = (
                strpos($video->thumbnail, 'r2.cloudflarestorage.com') !== false ||
                strpos($video->thumbnail, '.r2.dev') !== false ||
                strpos($video->thumbnail, 'vid.ions.com') !== false
            );
            
            $rowClass = $is_r2_video ? 'r2-yes' : 'r2-no';
            $thumbClass = $is_r2_thumbnail ? 'r2-yes' : 'r2-no';
            
            echo "<tr>";
            echo "<td>{$video->id}</td>";
            echo "<td>" . htmlspecialchars($video->title) . "</td>";
            echo "<td>" . htmlspecialchars($video->source) . "</td>";
            echo "<td class='url'>" . htmlspecialchars($video->video_link) . "</td>";
            echo "<td class='$rowClass'>" . ($is_r2_video ? '✅ YES - Will delete from R2' : '❌ NO - Will skip R2 deletion') . "</td>";
            echo "<td class='url'>" . htmlspecialchars($video->thumbnail) . "</td>";
            echo "<td class='$thumbClass'>" . ($is_r2_thumbnail ? '✅ R2' : '❌ Local/Other') . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        
        echo "<h3>Detection Rules:</h3>";
        echo "<ul>";
        echo "<li>✅ Contains 'vid.ions.com'</li>";
        echo "<li>✅ Contains 'r2.cloudflarestorage.com'</li>";
        echo "<li>✅ Contains '.r2.dev'</li>";
        echo "<li>❌ Excludes 'youtube.com', 'youtu.be', 'vimeo.com', 'rumble.com'</li>";
        echo "</ul>";
    }
    ?>
    
    <h3>Test Delete Function:</h3>
    <p>Enter a video ID to see what would happen if you deleted it:</p>
    <form method="get">
        <input type="number" name="test_id" placeholder="Video ID" style="padding: 8px;">
        <button type="submit" style="padding: 8px 16px;">Test</button>
    </form>
    
    <?php
    if (isset($_GET['test_id'])) {
        $test_id = intval($_GET['test_id']);
        $test_video = $db->get_row("SELECT * FROM IONLocalVideos WHERE id = ?", $test_id);
        
        if ($test_video) {
            echo "<div style='background: #f5f5f5; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
            echo "<h4>Test Results for Video ID: $test_id</h4>";
            echo "<p><strong>Title:</strong> " . htmlspecialchars($test_video->title) . "</p>";
            echo "<p><strong>Video URL:</strong> " . htmlspecialchars($test_video->video_link) . "</p>";
            
            $is_r2_url = strpos($test_video->video_link, 'r2.cloudflarestorage.com') !== false;
            $is_r2_public_domain = strpos($test_video->video_link, 'vid.ions.com') !== false;
            $is_youtube = strpos($test_video->video_link, 'youtube.com') !== false;
            $is_r2_video = ($is_r2_url || $is_r2_public_domain) && !$is_youtube;
            
            echo "<p><strong>R2 Detection:</strong></p>";
            echo "<ul>";
            echo "<li>Contains 'r2.cloudflarestorage.com': " . ($is_r2_url ? '✅ YES' : '❌ NO') . "</li>";
            echo "<li>Contains 'vid.ions.com': " . ($is_r2_public_domain ? '✅ YES' : '❌ NO') . "</li>";
            echo "<li>Is YouTube: " . ($is_youtube ? '⚠️ YES' : '✅ NO') . "</li>";
            echo "<li><strong>Will attempt R2 deletion: " . ($is_r2_video ? '✅ YES' : '❌ NO') . "</strong></li>";
            echo "</ul>";
            
            if ($is_r2_video) {
                echo "<p style='color: green;'><strong>✅ This video WILL have its R2 file deleted</strong></p>";
            } else {
                echo "<p style='color: red;'><strong>❌ This video will NOT have R2 deletion attempted</strong></p>";
                echo "<p>Reason: URL doesn't match R2 patterns or is an external platform</p>";
            }
            echo "</div>";
        } else {
            echo "<p style='color: red;'>Video ID $test_id not found</p>";
        }
    }
    ?>
    
</body>
</html>

