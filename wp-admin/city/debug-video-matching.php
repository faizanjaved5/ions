<?php
/**
 * Debug script to help understand video matching issues
 * Shows what's in database vs what's coming from search results
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

echo "<h2>🔍 Video Matching Debug</h2>";
echo "<style>body{font-family:monospace; background:#0f172a; color:#e2e8f0; padding:20px;} .video{background:#1e293b; padding:10px; margin:10px 0; border-radius:8px;} .found{border-left:4px solid #10b981;} .missing{border-left:4px solid #ef4444;} .shortlink{color:#10b981; font-weight:bold;}</style>";

// Get some videos from database
echo "<h3>📊 Database Videos (Sample)</h3>";
try {
    $stmt = $pdo->prepare("SELECT id, title, video_id, video_link, short_link, clicks FROM IONLocalVideos LIMIT 10");
    $stmt->execute();
    $db_videos = $stmt->fetchAll(PDO::FETCH_OBJ);
    
    foreach ($db_videos as $video) {
        $shortlink_status = !empty($video->short_link) ? 
            "<span class='shortlink'>✅ https://ions.com/v/{$video->short_link}</span>" : 
            "<span style='color:#ef4444;'>❌ No shortlink</span>";
            
        echo "<div class='video'>";
        echo "<strong>ID:</strong> {$video->id}<br>";
        echo "<strong>Title:</strong> " . htmlspecialchars($video->title) . "<br>";
        echo "<strong>Video ID:</strong> {$video->video_id}<br>";
        echo "<strong>Video Link:</strong> " . htmlspecialchars($video->video_link) . "<br>";
        echo "<strong>Shortlink:</strong> {$shortlink_status}<br>";
        echo "<strong>Clicks:</strong> " . ($video->clicks ?: 0);
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:#ef4444;'>Database error: " . $e->getMessage() . "</p>";
}

// Test URL matching
echo "<h3>🧪 URL Matching Test</h3>";
echo "<p>Testing different URL formats for the same video:</p>";

$test_youtube_id = "N6VYsNjUm1c"; // From your example
$test_urls = [
    "https://www.youtube.com/watch?v={$test_youtube_id}",
    "https://youtu.be/{$test_youtube_id}",
    "https://www.youtube.com/embed/{$test_youtube_id}",
    "https://www.youtube.com/v/{$test_youtube_id}",
];

foreach ($test_urls as $url) {
    echo "<div class='video'>";
    echo "<strong>Testing URL:</strong> " . htmlspecialchars($url) . "<br>";
    
    try {
        // Test exact match
        $stmt = $pdo->prepare("SELECT id, short_link FROM IONLocalVideos WHERE video_link = ? LIMIT 1");
        $stmt->execute([$url]);
        $exact_match = $stmt->fetch(PDO::FETCH_OBJ);
        
        if ($exact_match) {
            echo "<span class='shortlink'>✅ Exact match found: ID {$exact_match->id}, shortlink: {$exact_match->short_link}</span><br>";
        } else {
            echo "<span style='color:#ef4444;'>❌ No exact match</span><br>";
            
            // Test video_id match
            $stmt2 = $pdo->prepare("SELECT id, short_link, video_link FROM IONLocalVideos WHERE video_id = ? LIMIT 1");
            $stmt2->execute([$test_youtube_id]);
            $id_match = $stmt2->fetch(PDO::FETCH_OBJ);
            
            if ($id_match) {
                echo "<span style='color:#f59e0b;'>⚠️ Found by video_id: ID {$id_match->id}, shortlink: {$id_match->short_link}</span><br>";
                echo "<span style='color:#94a3b8;'>   Stored URL: " . htmlspecialchars($id_match->video_link) . "</span><br>";
            } else {
                echo "<span style='color:#ef4444;'>❌ No video_id match for {$test_youtube_id}</span><br>";
            }
        }
        
    } catch (Exception $e) {
        echo "<span style='color:#ef4444;'>Error: " . $e->getMessage() . "</span><br>";
    }
    
    echo "</div>";
}

// Check shortlink coverage
echo "<h3>📈 Shortlink Coverage</h3>";
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            COUNT(short_link) as with_shortlinks,
            COUNT(CASE WHEN short_link IS NOT NULL AND short_link != '' THEN 1 END) as active_shortlinks
        FROM IONLocalVideos
    ");
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_OBJ);
    
    $coverage = $stats->total > 0 ? round(($stats->active_shortlinks / $stats->total) * 100, 1) : 0;
    
    echo "<div class='video'>";
    echo "<strong>Total videos:</strong> {$stats->total}<br>";
    echo "<strong>Videos with shortlinks:</strong> {$stats->active_shortlinks}<br>";
    echo "<strong>Coverage:</strong> {$coverage}%<br>";
    
    if ($coverage < 100) {
        echo "<br><a href='generate-missing-shortlinks.php' style='color:#3b82f6;'>🔧 Generate Missing Shortlinks</a>";
    }
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p style='color:#ef4444;'>Stats error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='../'>← Back to Site</a></p>";
?>
