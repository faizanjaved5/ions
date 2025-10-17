<?php
/**
 * Quick script to generate missing shortlinks for IONCity videos
 * Run this once to populate shortlinks for all existing videos
 */

// Load necessary files
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Load ioncity functions
require_once __DIR__ . '/ioncity.php';

echo "<h2>🔗 IONCity Shortlink Generator</h2>\n";
echo "<pre>\n";

try {
    // Get all videos without shortlinks
    $stmt = $pdo->prepare("SELECT id, title, short_link FROM IONLocalVideos WHERE (short_link IS NULL OR short_link = '') LIMIT 50");
    $stmt->execute();
    $videos = $stmt->fetchAll(PDO::FETCH_OBJ);
    
    echo "Found " . count($videos) . " videos without shortlinks\n\n";
    
    $generated = 0;
    $errors = 0;
    
    foreach ($videos as $video) {
        echo "Processing Video ID {$video->id}: {$video->title}\n";
        
        $shortlink = generate_shortlink_pdo($video->id, $video->title, $pdo);
        
        if ($shortlink) {
            echo "  ✅ Generated: https://ions.com/v/{$shortlink}\n";
            $generated++;
        } else {
            echo "  ❌ Failed to generate shortlink\n";
            $errors++;
        }
        
        echo "\n";
    }
    
    echo "Results:\n";
    echo "✅ Successfully generated: {$generated} shortlinks\n";
    echo "❌ Errors: {$errors}\n";
    echo "🔗 Total processed: " . count($videos) . "\n\n";
    
    // Check how many videos now have shortlinks
    $stmt = $pdo->prepare("SELECT COUNT(*) as total, COUNT(short_link) as with_links FROM IONLocalVideos WHERE short_link IS NOT NULL AND short_link != ''");
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_OBJ);
    
    echo "Database Statistics:\n";
    echo "📊 Total videos: {$stats->total}\n";
    echo "🔗 Videos with shortlinks: {$stats->with_links}\n";
    echo "📈 Coverage: " . round(($stats->with_links / $stats->total) * 100, 1) . "%\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "</pre>\n";
echo "<p><a href='?'>🔄 Run Again</a> | <a href='../'>← Back to Site</a></p>\n";
?>
