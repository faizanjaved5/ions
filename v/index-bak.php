<?php
/**
 * Video Shortlink Resolver
 * Handles friendly video URLs like: /v/abc123-my-awesome-video
 */

// Include ad management system
require_once __DIR__ . '/../app/VideoAdIntegration.php';

// Get the shortlink slug - prioritize .htaccess rewrite parameter
$slug = $_GET['link'] ?? '';

// Fallback: extract from URL path if rewrite parameter not available
if (empty($slug)) {
    $request_path = $_SERVER['REQUEST_URI'];
    if (preg_match('#/v/([^/?]+)#', $request_path, $matches)) {
        $slug = $matches[1];
    }
}

// Clean the slug - extract just the shortcode part (remove any suffix after dash)
if (!empty($slug)) {
    // Extract just the alphanumeric shortcode (6-8 chars)
    if (preg_match('/^([a-zA-Z0-9]{6,8})(?:-.*)?$/i', $slug, $matches)) {
        $slug = strtolower($matches[1]); // Normalize to lowercase for case-insensitive lookup
    }
}

// If no valid slug provided, redirect to main page
if (empty($slug)) {
    header('Location: /');
    exit();
}

// Load dependencies
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/shortlink-manager.php';

// Initialize shortlink manager
$shortlink_manager = new VideoShortlinkManager($db);

// Resolve the shortlink
$resolution = $shortlink_manager->resolveShortlink($slug);

if (!$resolution) {
    // Shortlink not found - show 404 or redirect
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Video Not Found - ION</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                margin: 0;
                padding: 0;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
            }
            .error-container {
                text-align: center;
                max-width: 500px;
                padding: 2rem;
            }
            .error-code {
                font-size: 6rem;
                font-weight: bold;
                margin-bottom: 1rem;
                opacity: 0.8;
            }
            .error-message {
                font-size: 1.5rem;
                margin-bottom: 2rem;
                opacity: 0.9;
            }
            .error-description {
                font-size: 1rem;
                margin-bottom: 2rem;
                opacity: 0.7;
                line-height: 1.6;
            }
            .back-button {
                display: inline-block;
                padding: 12px 24px;
                background: rgba(255,255,255,0.2);
                color: white;
                text-decoration: none;
                border-radius: 8px;
                transition: background 0.3s ease;
                font-weight: 500;
            }
            .back-button:hover {
                background: rgba(255,255,255,0.3);
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-code">404</div>
            <div class="error-message">Video Not Found</div>
            <div class="error-description">
                The video link you're looking for doesn't exist or has been removed.
                <br>Please check the URL and try again.
            </div>
            <a href="/" class="back-button">← Back to Home</a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Get video from resolution (already includes full video data)
$video = $resolution['video'];

if (!$video) {
    // Video not found
    http_response_code(410); // Gone
    echo "This video is no longer available.";
    exit();
}

// Initialize ad management system
$user = $_SESSION['user'] ?? null; // Assuming user is stored in session
$channel = $video->channel ?? null; // Assuming channel info is available
$adIntegration = new VideoAdIntegration($user, $video, $channel);

// Check if video is publicly accessible
// For now, we'll allow public access to shared links
// You can add privacy checks here if needed

// Determine video player type based on source and URL
$player_config = [
    'title' => $video->title,
    'description' => $video->description,
    'poster' => $video->thumbnail ?: '',
    'sources' => []
];

// Function to detect video format from URL
function getVideoTypeFromUrl($url) {
    $path = parse_url($url, PHP_URL_PATH);
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    
    // Video file extensions that should use Video.js
    $video_extensions = ['mp4', 'mov', 'webm', 'avi', 'm4v', 'mkv', 'ogg'];
    
    if (in_array($extension, $video_extensions)) {
        return [
            'type' => 'video/' . ($extension === 'mov' ? 'mp4' : $extension),
            'player' => 'videojs'
        ];
    }
    
    return null;
}

// Function to check if URL is from Cloudflare R2 or other self-hosted sources
function isSelfHostedVideo($url) {
    $domains = ['vid.ions.com', 'ions.com', 'r2.cloudflarestorage.com'];
    $host = parse_url($url, PHP_URL_HOST);
    
    foreach ($domains as $domain) {
        if (strpos($host, $domain) !== false) {
            return true;
        }
    }
    
    return false;
}

// Detect player type with enhanced logic
$video_url = $video->video_link;
$video_type_info = getVideoTypeFromUrl($video_url);
$is_self_hosted = isSelfHostedVideo($video_url);

// Enhanced source detection
$source_type = strtolower($video->source ?? $video->videotype ?? '');

if ($video_type_info && ($source_type === 'upload' || $is_self_hosted)) {
    // Use Video.js for self-hosted videos (R2, local uploads, etc.)
    $player_config['sources'][] = [
        'src' => $video_url,
        'type' => $video_type_info['type']
    ];
    $player_type = 'videojs';
} else {
    // Handle external platform videos
    switch ($source_type) {
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
            
        case 'muvi':
            $player_config['iframe_src'] = $video_url;
            $player_type = 'iframe';
            break;
            
        case 'wistia':
            $player_config['wistia_id'] = $video->video_id;
            $player_type = 'wistia';
            break;
            
        case 'rumble':
            $player_config['rumble_id'] = $video->video_id;
            $player_type = 'rumble';
            break;
            
        default:
            // Final fallback: check if it looks like a video file
            if ($video_type_info) {
                $player_config['sources'][] = [
                    'src' => $video_url,
                    'type' => $video_type_info['type']
                ];
                $player_type = 'videojs';
            } else {
                $player_config['iframe_src'] = $video_url;
                $player_type = 'iframe';
            }
            break;
    }
}

// Set page meta data for sharing
$page_title = htmlspecialchars($video->title) . ' - ION';
$page_description = !empty($video->description) 
    ? htmlspecialchars(substr($video->description, 0, 160)) 
    : 'Watch ' . htmlspecialchars($video->title) . ' on ION';
$page_image = htmlspecialchars($video->thumbnail ?: '/assets/default/ionthumbnail.png');
$page_url = 'https://ions.com/v/' . $resolution['shortlink'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <meta name="description" content="<?= $page_description ?>">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="video">
    <meta property="og:url" content="<?= $page_url ?>">
    <meta property="og:title" content="<?= htmlspecialchars($video->title) ?>">
    <meta property="og:description" content="<?= $page_description ?>">
    <meta property="og:image" content="<?= $page_image ?>">
    
    <!-- Twitter -->
    <meta property="twitter:card" content="player">
    <meta property="twitter:url" content="<?= $page_url ?>">
    <meta property="twitter:title" content="<?= htmlspecialchars($video->title) ?>">
    <meta property="twitter:description" content="<?= $page_description ?>">
    <meta property="twitter:image" content="<?= $page_image ?>">
    
    <!-- Video.js CSS -->
    <link href="/player/video-js.css" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .video-header {
            margin-bottom: 20px;
        }
        
        .video-title {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 10px;
            color: #f1f5f9;
        }
        
        .video-meta {
            display: flex;
            align-items: center;
            gap: 20px;
            font-size: 0.9rem;
            color: #94a3b8;
            margin-bottom: 15px;
        }
        
        .video-player-container {
            background: #1e293b;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }
        
        .video-player {
            width: 100%;
            aspect-ratio: 16/9;
            background: #000;
        }
        
        .video-player iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
        
        .video-js {
            width: 100%;
            height: 100%;
        }
        
        .video-info {
            background: #1e293b;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 20px;
        }
        
        .video-info h3 {
            color: #f1f5f9;
            margin-bottom: 15px;
            font-size: 1.3rem;
        }
        
        .video-description {
            color: #cbd5e1;
            line-height: 1.7;
            margin-bottom: 20px;
        }
        
        .video-tags {
            color: #94a3b8;
        }
        
        .share-section {
            background: #1e293b;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
        }
        
        .share-title {
            color: #f1f5f9;
            margin-bottom: 15px;
            font-size: 1.2rem;
        }
        
        .share-url {
            background: #0f172a;
            padding: 12px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 0.9rem;
            color: #64748b;
            border: 1px solid #334155;
            margin-bottom: 15px;
            word-break: break-all;
        }
        
        .copy-button {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background 0.3s ease;
        }
        
        .copy-button:hover {
            background: #2563eb;
        }
        
        .copy-button.copied {
            background: #10b981;
        }
        
        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #334155;
            color: #64748b;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .video-title {
                font-size: 1.5rem;
            }
            
            .video-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
            
            .video-info, .share-section {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="video-header">
            <h1 class="video-title"><?= htmlspecialchars($video->title) ?></h1>
            <div class="video-meta">
                <span>Published: <?= date('M j, Y', strtotime($video->date_added ?? $video->published_at ?? 'now')) ?></span>
                <span>Category: <?= htmlspecialchars($video->category ?? 'General') ?></span>
                <?php if ($resolution['clicks'] > 0): ?>
                    <span>Views: <?= number_format($resolution['clicks']) ?></span>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="video-player-container">
            <div class="video-player">
                <?php if ($player_type === 'videojs'): ?>
                    <!-- Video.js Player for uploaded files -->
                    <video
                        id="video-player"
                        class="video-js vjs-default-skin"
                        controls
                        preload="metadata"
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
                        allowfullscreen
                        allow="autoplay; encrypted-media">
                    </iframe>
                    
                <?php elseif ($player_type === 'vimeo'): ?>
                    <!-- Vimeo Embed -->
                    <iframe 
                        src="https://player.vimeo.com/video/<?= htmlspecialchars($player_config['vimeo_id']) ?>?autoplay=0"
                        allowfullscreen
                        allow="autoplay; fullscreen">
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
                        allowfullscreen
                        allow="autoplay; encrypted-media">
                    </iframe>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (!empty($video->description) || !empty($video->tags)): ?>
            <div class="video-info">
                <?php if (!empty($video->description)): ?>
                    <h3>Description</h3>
                    <div class="video-description">
                        <?= nl2br(htmlspecialchars($video->description)) ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($video->tags)): ?>
                    <div class="video-tags">
                        <strong>Tags:</strong> <?= htmlspecialchars($video->tags) ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="share-section">
            <h3 class="share-title">Share this video</h3>
            <div class="share-url" id="shareUrl"><?= $page_url ?></div>
            <button class="copy-button" onclick="copyShareUrl()">Copy Link</button>
        </div>
        
        <div class="footer">
            <p>&copy; <?= date('Y') ?> ION. Powered by ION Video Platform.</p>
        </div>
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
            'preload' => 'metadata',
            'html5' => [
                'vhs' => [
                    'overrideNative' => true
                ],
                'nativeVideoTracks' => false,
                'nativeAudioTracks' => false,
                'nativeTextTracks' => false
            ]
        ]) ?>
    </script>
    <?php endif; ?>
    
    <script>
        function copyShareUrl() {
            const shareUrl = document.getElementById('shareUrl');
            const button = document.querySelector('.copy-button');
            
            // Create temporary input
            const temp = document.createElement('input');
            temp.value = shareUrl.textContent;
            document.body.appendChild(temp);
            temp.select();
            document.execCommand('copy');
            document.body.removeChild(temp);
            
            // Update button state
            button.textContent = 'Copied!';
            button.classList.add('copied');
            
            setTimeout(() => {
                button.textContent = 'Copy Link';
                button.classList.remove('copied');
            }, 2000);
        }
    </script>
</body>
</html>
