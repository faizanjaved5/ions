<?php
/**
 * Quick debug script to check if reactions are being saved
 * Access this at: http://yoursite.com/check-reactions-debug.php
 */

session_start();
require_once __DIR__ . '/config/database.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reactions Debug</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1a1a1a; color: #fff; }
        .section { margin: 20px 0; padding: 15px; background: #2a2a2a; border-radius: 8px; }
        .success { color: #10b981; }
        .error { color: #ef4444; }
        .warning { color: #f59e0b; }
        pre { background: #0a0a0a; padding: 10px; border-radius: 4px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; border: 1px solid #444; }
        th { background: #333; }
    </style>
</head>
<body>
    <h1>🔍 Video Reactions Debug</h1>
    
    <div class="section">
        <h2>1. Session Check</h2>
        <?php if (isset($_SESSION['user_id'])): ?>
            <p class="success">✅ User is logged in</p>
            <pre>User ID: <?= $_SESSION['user_id'] ?>
Email: <?= $_SESSION['user_email'] ?? 'Not set' ?></pre>
        <?php else: ?>
            <p class="error">❌ User is NOT logged in</p>
            <p>You need to be logged in to see your reactions.</p>
        <?php endif; ?>
    </div>
    
    <div class="section">
        <h2>2. Database Connection</h2>
        <?php
        try {
            $pdo = $db->getPDO();
            echo '<p class="success">✅ Database connected</p>';
        } catch (Exception $e) {
            echo '<p class="error">❌ Database connection failed: ' . htmlspecialchars($e->getMessage()) . '</p>';
            exit;
        }
        ?>
    </div>
    
    <div class="section">
        <h2>3. IONVideoLikes Table Check</h2>
        <?php
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'IONVideoLikes'");
            $tableExists = $stmt->fetch();
            
            if ($tableExists) {
                echo '<p class="success">✅ IONVideoLikes table exists</p>';
                
                // Get table structure
                $stmt = $pdo->query("DESCRIBE IONVideoLikes");
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo '<h3>Table Structure:</h3>';
                echo '<table><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>';
                foreach ($columns as $col) {
                    echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Key']}</td><td>{$col['Default']}</td></tr>";
                }
                echo '</table>';
                
                // Count total reactions
                $stmt = $pdo->query("SELECT COUNT(*) as total FROM IONVideoLikes");
                $count = $stmt->fetch(PDO::FETCH_ASSOC);
                echo "<p>Total reactions in database: <strong>{$count['total']}</strong></p>";
                
            } else {
                echo '<p class="error">❌ IONVideoLikes table does NOT exist</p>';
                echo '<p class="warning">You need to run the SQL migration: _db/add_video_likes_system.sql</p>';
            }
        } catch (Exception $e) {
            echo '<p class="error">❌ Error checking table: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
        ?>
    </div>
    
    <?php if (isset($_SESSION['user_id'])): ?>
    <div class="section">
        <h2>4. Your Reactions</h2>
        <?php
        try {
            $user_id = (int)$_SESSION['user_id'];
            $stmt = $pdo->prepare("
                SELECT vl.*, v.title, v.slug 
                FROM IONVideoLikes vl
                LEFT JOIN IONLocalVideos v ON vl.video_id = v.id
                WHERE vl.user_id = ?
                ORDER BY vl.updated_at DESC
                LIMIT 20
            ");
            $stmt->execute([$user_id]);
            $reactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($reactions) > 0) {
                echo '<p class="success">✅ Found ' . count($reactions) . ' reaction(s)</p>';
                echo '<table>';
                echo '<tr><th>Video ID</th><th>Title</th><th>Action</th><th>Created</th><th>Updated</th></tr>';
                foreach ($reactions as $r) {
                    $actionColor = $r['action_type'] === 'like' ? '#10b981' : '#ef4444';
                    $actionIcon = $r['action_type'] === 'like' ? '👍' : '👎';
                    echo "<tr>";
                    echo "<td>{$r['video_id']}</td>";
                    echo "<td>" . htmlspecialchars($r['title'] ?? 'Unknown') . "</td>";
                    echo "<td style='color: {$actionColor}'>{$actionIcon} {$r['action_type']}</td>";
                    echo "<td>{$r['created_at']}</td>";
                    echo "<td>{$r['updated_at']}</td>";
                    echo "</tr>";
                }
                echo '</table>';
            } else {
                echo '<p class="warning">⚠️ No reactions found for your account</p>';
                echo '<p>Try liking or disliking a video first.</p>';
            }
        } catch (Exception $e) {
            echo '<p class="error">❌ Error fetching reactions: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
        ?>
    </div>
    
    <div class="section">
        <h2>5. Sample Video Reactions Query Test</h2>
        <?php
        try {
            // Get a recent video
            $stmt = $pdo->query("SELECT id, title FROM IONLocalVideos ORDER BY id DESC LIMIT 1");
            $video = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($video) {
                echo "<p>Testing query for video: <strong>{$video['title']}</strong> (ID: {$video['id']})</p>";
                
                // Query like the actual code does
                $user_id = (int)$_SESSION['user_id'];
                $stmt = $pdo->prepare("SELECT action_type FROM IONVideoLikes WHERE video_id = ? AND user_id = ?");
                $stmt->execute([$video['id'], $user_id]);
                $reaction = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($reaction) {
                    $actionColor = $reaction['action_type'] === 'like' ? '#10b981' : '#ef4444';
                    echo "<p class='success' style='color: {$actionColor}'>✅ Found reaction: <strong>{$reaction['action_type']}</strong></p>";
                    echo "<p>This should appear as <code>data-user-action=\"{$reaction['action_type']}\"</code> in the HTML</p>";
                } else {
                    echo "<p class='warning'>⚠️ No reaction found for this video</p>";
                    echo "<p>Expected result: <code>data-user-action=\"\"</code> (empty)</p>";
                }
            }
        } catch (Exception $e) {
            echo '<p class="error">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
        ?>
    </div>
    <?php endif; ?>
    
    <div class="section">
        <h2>6. JavaScript Check</h2>
        <p>Open your browser console (F12) and check for these logs:</p>
        <pre>🚀 Initializing N video reaction containers
📹 Video [ID]: data-user-action="[action]", processed as: "[action]"
🎨 Updating button states for video [ID], userAction: "[action]"
✅ Added 'active' class to like button for video [ID]</pre>
        
        <p>If you don't see these logs, the JavaScript isn't running.</p>
    </div>
    
    <div class="section">
        <h2>7. Quick Actions</h2>
        <ul>
            <li><a href="/" style="color: #3b82f6">← Back to Home</a></li>
            <li><a href="/profile/" style="color: #3b82f6">View Profile Page</a></li>
            <li><a href="?clear" style="color: #ef4444">Clear Browser Cache & Reload</a></li>
        </ul>
    </div>
    
    <script>
        console.log('🔍 Reactions Debug Script Loaded');
        console.log('Session User ID:', <?= json_encode($_SESSION['user_id'] ?? null) ?>);
        
        // Test if video-reactions.js is loaded
        if (typeof window.videoReactions !== 'undefined') {
            console.log('✅ video-reactions.js is loaded');
        } else {
            console.error('❌ video-reactions.js is NOT loaded');
        }
    </script>
</body>
</html>

