<?php
/**
 * Test if data-user-action is actually in the HTML
 * Visit a video page, then View Source and search for "data-user-action"
 */

session_start();
require_once __DIR__ . '/config/database.php';

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    die("Please login first");
}

// Get a video you've liked
$pdo = $db->getPDO();
$stmt = $pdo->prepare("
    SELECT v.id, v.title, vl.action_type 
    FROM IONVideoLikes vl
    JOIN IONLocalVideos v ON vl.video_id = v.id
    WHERE vl.user_id = ?
    LIMIT 1
");
$stmt->execute([$user_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$result) {
    die("No reactions found. Like a video first!");
}

$video_id = $result['id'];
$action_type = $result['action_type'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>HTML Output Test</title>
    <link rel="stylesheet" href="/app/video-reactions.css">
    <style>
        body { font-family: Arial; padding: 40px; background: #1a1a1a; color: #fff; }
        .test-box { background: #2a2a2a; padding: 20px; margin: 20px 0; border-radius: 8px; }
        pre { background: #0a0a0a; padding: 15px; border-radius: 4px; overflow-x: auto; }
        .success { color: #10b981; }
        .error { color: #ef4444; }
    </style>
</head>
<body>
    <h1>🧪 HTML Output Test</h1>
    
    <div class="test-box">
        <h2>Test Data:</h2>
        <p><strong>Video ID:</strong> <?= $video_id ?></p>
        <p><strong>Your Action:</strong> <span style="color: <?= $action_type === 'like' ? '#10b981' : '#ef4444' ?>"><?= $action_type ?></span></p>
    </div>
    
    <div class="test-box">
        <h2>Actual HTML Output:</h2>
        <p><strong>This is what the browser receives:</strong></p>
        
        <!-- THIS IS THE ACTUAL COMPONENT -->
        <div class="video-reactions inline" data-video-id="<?= $video_id ?>" data-user-action="<?= htmlspecialchars($action_type ?? '') ?>">
            <button class="reaction-btn like-btn" title="Like this video">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" style="width:18px;height:18px;">
                    <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path>
                </svg>
                <span class="like-count">5</span>
            </button>
            <button class="reaction-btn dislike-btn" title="Dislike this video">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" style="width:18px;height:18px;">
                    <path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3zm7-13h2.67A2.31 2.31 0 0 1 22 4v7a2.31 2.31 0 0 1-2.33 2H17"></path>
                </svg>
                <span class="dislike-count">2</span>
            </button>
        </div>
        
        <p style="margin-top: 20px;"><strong>Raw HTML (what you'd see in View Source):</strong></p>
        <pre>&lt;div class="video-reactions inline" data-video-id="<?= $video_id ?>" data-user-action="<?= htmlspecialchars($action_type ?? '') ?>"&gt;
    &lt;button class="reaction-btn like-btn"&gt;...&lt;/button&gt;
    &lt;button class="reaction-btn dislike-btn"&gt;...&lt;/button&gt;
&lt;/div&gt;</pre>
    </div>
    
    <div class="test-box">
        <h2>What JavaScript Should See:</h2>
        <pre id="js-output">Loading...</pre>
    </div>
    
    <div class="test-box">
        <h2>Instructions:</h2>
        <ol>
            <li>Look at the button above - is it <span class="success">GREEN</span> (if liked) or <span class="error">RED</span> (if disliked)?</li>
            <li>Open browser console (F12) and look for debug messages</li>
            <li>Right-click the button → Inspect Element → Check if it has "active" class</li>
            <li>View Page Source (Ctrl+U) and search for "data-user-action" - does it have the value?</li>
        </ol>
    </div>
    
    <script src="/app/video-reactions.js"></script>
    <script>
        // Wait for page load
        setTimeout(() => {
            const container = document.querySelector('[data-video-id="<?= $video_id ?>"]');
            const likeBtn = container.querySelector('.like-btn');
            const dislikeBtn = container.querySelector('.dislike-btn');
            
            const output = document.getElementById('js-output');
            output.textContent = `
Container found: ${!!container}
data-video-id: ${container?.dataset?.videoId}
data-user-action: "${container?.dataset?.userAction}"

Like button found: ${!!likeBtn}
Like button classes: ${likeBtn?.className}
Like button has 'active' class: ${likeBtn?.classList?.contains('active')}

Dislike button found: ${!!dislikeBtn}
Dislike button classes: ${dislikeBtn?.className}
Dislike button has 'active' class: ${dislikeBtn?.classList?.contains('active')}

Expected: ${<?= json_encode($action_type) ?>} button should have 'active' class
`;
            
            // Check colors
            if (likeBtn) {
                const color = window.getComputedStyle(likeBtn).color;
                output.textContent += `\nLike button color: ${color}`;
                output.textContent += `\nExpected green: rgb(16, 185, 129)`;
            }
        }, 1000);
    </script>
</body>
</html>

