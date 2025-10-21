<?php
session_start();

// Include authentication
require_once '../config/config.php';
require_once '../login/roles.php';

// Include ad management system
require_once 'VideoAdIntegration.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header('Location: /login/');
    exit();
}

$user_role = $_SESSION['user_role'];

// Check if user can access videos
if (!IONRoles::canAccessSection($user_role, 'ION_VIDS')) {
    header('Location: /login/?error=unauthorized');
    exit();
}

// Get video ID from URL
$video_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$video_id) {
    header('Location: creators.php');
    exit();
}

// Get video details
$video = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM IONLocalVideos WHERE id = %d",
    $video_id
));

if (!$video) {
    header('Location: creators.php?error=video_not_found');
    exit();
}

// Check if user can view this video (owners/admins can see all, others only their own)
if (!in_array($user_role, ['Owner', 'Admin']) && $video->user_id !== $_SESSION['user_unique_id']) {
    header('Location: creators.php?error=access_denied');
    exit();
}

// Initialize ad management system
$user_obj = (object)['id' => $_SESSION['user_id'], 'role' => $user_role];
$channel = null; // Channel info could be added here if available
$adIntegration = new VideoAdIntegration($user_obj, $video, $channel);

// Determine video player type based on source
$player_config = [
    'title' => $video->title,
    'poster' => $video->thumbnail ?: '',
    'sources' => []
];

switch ($video->source) {
    case 'upload':
    case 'Upload':
        // Direct file upload - use Video.js with direct URL
        $player_config['sources'][] = [
            'src' => $video->video_link,
            'type' => $video->file_type ?: 'video/mp4'
        ];
        $player_type = 'videojs';
        break;
        
    case 'youtube':
        $player_config['youtube_id'] = $video->video_id;
        $player_type = 'youtube';
        break;
        
    case 'vimeo':
        $player_config['vimeo_id'] = $video->video_id;
        $player_type = 'vimeo';
        break;
        
    case 'drive':
        $player_config['drive_id'] = $video->video_id;
        $player_type = 'drive';
        break;
        
    case 'Muvi':
        // Muvi videos use iframe embedding
        $player_config['iframe_src'] = $video->video_link;
        $player_type = 'iframe';
        break;
        
    case 'Wistia':
        $player_config['wistia_id'] = $video->video_id;
        $player_type = 'wistia';
        break;
        
    case 'Rumble':
        $player_config['rumble_id'] = $video->video_id;
        $player_type = 'rumble';
        break;
        
    default:
        $player_config['iframe_src'] = $video->video_link;
        $player_type = 'iframe';
        break;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($video->title) ?> - ION Video Player</title>
    
    <!-- Video.js CSS -->
    <link href="/player/video-js.min.css" rel="stylesheet">
    
    <style>
        body {
            margin: 0;
            padding: 20px;
            background: #000;
            color: #fff;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .player-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .video-header {
            margin-bottom: 20px;
        }
        
        .video-title {
            font-size: 24px;
            font-weight: 600;
            margin: 0 0 10px 0;
        }
        
        .video-meta {
            color: #ccc;
            font-size: 14px;
        }
        
        .video-player {
            position: relative;
            width: 100%;
            background: #000;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .video-js {
            width: 100%;
            height: auto;
        }
        
        .video-player iframe {
            width: 100%;
            height: 500px;
            border: none;
            border-radius: 8px;
        }
        
        .video-info {
            margin-top: 20px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
        }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            padding: 10px 16px;
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            text-decoration: none;
            border-radius: 6px;
            transition: background 0.2s;
        }
        
        .back-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        @media (max-width: 768px) {
            body { padding: 10px; }
            .video-title { font-size: 20px; }
            .video-player iframe { height: 300px; }
        }
    </style>
</head>
<body>
    <div class="player-container">
        <a href="creators.php" class="back-btn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 12H5M12 19l-7-7 7-7"></path>
            </svg>
            Back to Videos
        </a>
        
        <div class="video-header">
            <h1 class="video-title"><?= htmlspecialchars($video->title) ?></h1>
            <div class="video-meta">
                Uploaded: <?= date('M j, Y g:i A', strtotime($video->date_added)) ?>
                | Category: <?= htmlspecialchars($video->category) ?>
                | Status: <?= htmlspecialchars($video->status) ?>
            </div>
        </div>
        
        <div class="video-player">
            <?php if ($player_type === 'videojs'): ?>
                <!-- Video.js Player for uploaded files -->
                <video
                    id="video-player"
                    class="video-js vjs-default-skin"
                    controls
                    preload="auto"
                    data-setup='{"responsive": true, "fluid": true}'
                    poster="<?= htmlspecialchars($player_config['poster']) ?>"
                >
                    <?php foreach ($player_config['sources'] as $source): ?>
                        <source src="<?= htmlspecialchars($source['src']) ?>" type="<?= htmlspecialchars($source['type']) ?>">
                    <?php endforeach; ?>
                    <p class="vjs-no-js">
                        To view this video please enable JavaScript, and consider upgrading to a web browser that
                        <a href="https://videojs.com/html5-video-support/" target="_blank">supports HTML5 video</a>.
                    </p>
                </video>
                
            <?php elseif ($player_type === 'youtube'): ?>
                <!-- YouTube Embed -->
                <iframe 
                    src="https://www.youtube.com/embed/<?= htmlspecialchars($player_config['youtube_id']) ?>?autoplay=0&rel=0"
                    allowfullscreen>
                </iframe>
                
            <?php elseif ($player_type === 'vimeo'): ?>
                <!-- Vimeo Embed -->
                <iframe 
                    src="https://player.vimeo.com/video/<?= htmlspecialchars($player_config['vimeo_id']) ?>?autoplay=0"
                    allowfullscreen>
                </iframe>
                
            <?php elseif ($player_type === 'drive'): ?>
                <!-- Google Drive Embed -->
                <iframe 
                    src="https://drive.google.com/file/d/<?= htmlspecialchars($player_config['drive_id']) ?>/preview"
                    allowfullscreen>
                </iframe>
                
            <?php elseif ($player_type === 'wistia'): ?>
                <!-- Wistia Embed -->
                <iframe 
                    src="https://fast.wistia.net/embed/iframe/<?= htmlspecialchars($player_config['wistia_id']) ?>?autoplay=0&controls=1"
                    allowfullscreen
                    allow="autoplay; encrypted-media">
                </iframe>
                
            <?php elseif ($player_type === 'rumble'): ?>
                <!-- Rumble Embed -->
                <iframe 
                    src="https://rumble.com/embed/v<?= htmlspecialchars($player_config['rumble_id']) ?>/?autoplay=0"
                    allowfullscreen
                    allow="autoplay; encrypted-media">
                </iframe>
                
            <?php else: ?>
                <!-- Generic iframe -->
                <iframe 
                    src="<?= htmlspecialchars($player_config['iframe_src']) ?>"
                    allowfullscreen>
                </iframe>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($video->description)): ?>
            <div class="video-info">
                <h3>Description</h3>
                <p><?= nl2br(htmlspecialchars($video->description)) ?></p>
                
                <?php if (!empty($video->tags)): ?>
                    <div style="margin-top: 15px;">
                        <strong>Tags:</strong> <?= htmlspecialchars($video->tags) ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Video.js JavaScript -->
    <script src="/player/video.min.js"></script>
    
    <!-- Ad System Includes -->
    <?= $adIntegration->getAdSystemIncludes() ?>
    
    <!-- Ad Configuration -->
    <?= $adIntegration->getAdConfigScript() ?>
    
    <?php if ($player_type === 'videojs'): ?>
    <script>
        // Set video context for ad targeting
        window.IONVideoId = '<?= $video->id ?? '' ?>';
        window.IONChannelId = '<?= $channel->id ?? '' ?>';
        
        <?= $adIntegration->getPlayerInitScript('video-player', [
            'responsive' => true,
            'fluid' => true,
            'playbackRates' => [0.5, 1, 1.25, 1.5, 2],
            'controls' => true,
            'preload' => 'metadata'
        ]) ?>
    </script>
    <?php endif; ?>
</body>
</html>