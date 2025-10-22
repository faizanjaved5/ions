<?php
/**
 * Video Shortlink Resolver
 * Handles friendly video URLs like: /v/abc123-my-awesome-video
 */

// Start session for authentication checks
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
require_once __DIR__ . '/../share/enhanced-share-manager.php';

// Initialize shortlink manager
$shortlink_manager = new VideoShortlinkManager($db);

// Initialize enhanced share manager
$enhanced_share_manager = new EnhancedIONShareManager($db);

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

// Get user's reaction to this video (if logged in)
$user_reaction = null;
$is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$current_user = null;

if ($is_logged_in) {
    $user_id = (int)$_SESSION['user_id'];
    try {
        $pdo = $db->getPDO();
        
        // Get user profile data for menu
        $stmt = $pdo->prepare("SELECT user_id, handle, fullname, email, photo_url FROM IONEERS WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $current_user = $stmt->fetch(PDO::FETCH_OBJ);
        
        // Get user's reaction to video
        $video_id = (int)$video->id; // Ensure integer type
        $stmt = $pdo->prepare("SELECT action_type FROM IONVideoLikes WHERE video_id = ? AND user_id = ?");
        $stmt->execute([$video_id, $user_id]);
        $reaction = $stmt->fetch(PDO::FETCH_ASSOC);
        $user_reaction = $reaction ? $reaction['action_type'] : null;
        
        // Debug logging
        error_log("REACTION QUERY: video_id={$video_id}, user_id={$user_id}");
        error_log("REACTION RESULT: " . ($reaction ? "action_type='{$reaction['action_type']}'" : "NO REACTION FOUND"));
        error_log("FINAL user_reaction: " . ($user_reaction ? "'{$user_reaction}'" : "NULL"));
        error_log("HTML OUTPUT will be: data-user-action=\"" . htmlspecialchars($user_reaction ?? '') . "\"");
    } catch (Exception $e) {
        error_log('Error fetching user data: ' . $e->getMessage());
        $user_reaction = null;
    }
}

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
            // Ensure Muvi embed has proper parameters (autoplay off, but allow user control)
            $separator = (strpos($video_url, '?') !== false) ? '&' : '?';
            $player_config['iframe_src'] = $video_url . $separator . 'autoplay=0&muted=0';
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
            
        case 'loom':
            // Loom embed with proper parameters
            $separator = (strpos($video_url, '?') !== false) ? '&' : '?';
            $player_config['iframe_src'] = $video_url . $separator . 'autoplay=0&muted=0&hide_owner=true&hide_share=true&hide_title=true&hideEmbedTopBar=true';
            $player_type = 'iframe';
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
    
    <!-- Enhanced Share System -->
    <link href="/share/enhanced-ion-share.css" rel="stylesheet">
    
    <!-- Video Reactions System -->
    <link href="/app/video-reactions.css?v=<?= time() ?>" rel="stylesheet">
    <link href="/login/modal.css?v=<?= time() ?>" rel="stylesheet">
    
    <style>
        /* CRITICAL: Inline styles to ensure active states work (overrides any conflicts) */
        .reaction-btn.like-btn.active {
            color: #10b981 !important;
            background: rgba(16, 185, 129, 0.15) !important;
            border-color: rgba(16, 185, 129, 0.4) !important;
        }
        
        .reaction-btn.like-btn.active svg {
            stroke: #10b981 !important;
            fill: none !important;
        }
        
        .reaction-btn.like-btn.active .like-count {
            color: #10b981 !important;
        }
        
        .reaction-btn.dislike-btn.active {
            color: #ef4444 !important;
            background: rgba(239, 68, 68, 0.15) !important;
            border-color: rgba(239, 68, 68, 0.4) !important;
        }
        
        .reaction-btn.dislike-btn.active svg {
            stroke: #ef4444 !important;
            fill: none !important;
        }
        
        .reaction-btn.dislike-btn.active .dislike-count {
            color: #ef4444 !important;
        }
    </style>
    
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
            padding: 15px 20px;
        }
        
        .ion-logo-header {
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .ion-logo-header img {
            height: 50px;
            transition: opacity 0.3s ease;
        }
        
        .ion-logo-header img:hover {
            opacity: 0.8;
        }
        
        /* User Menu Styles */
        .ion-user-menu {
            position: relative;
        }
        
        .ion-user-avatar-btn {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            border: 2px solid rgba(245, 158, 11, 0.3);
            background: transparent;
            padding: 0;
            cursor: pointer;
            transition: all 0.2s ease;
            overflow: hidden;
        }
        
        .ion-user-avatar-btn:hover {
            border-color: #f59e0b;
            transform: scale(1.05);
        }
        
        .ion-user-avatar-btn img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .ion-user-avatar-btn .avatar-placeholder {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 16px;
        }
        
        .ion-user-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            background: rgba(26, 29, 41, 0.98);
            backdrop-filter: blur(12px);
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
            min-width: 240px;
            padding: 12px;
            display: none;
            z-index: 1000;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .ion-user-dropdown.active {
            display: block;
            animation: slideDown 0.2s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .ion-user-info {
            padding: 12px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            margin-bottom: 8px;
        }
        
        .ion-user-name {
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 4px;
            font-size: 15px;
        }
        
        .ion-user-email {
            color: rgba(255, 255, 255, 0.6);
            font-size: 13px;
        }
        
        .ion-user-menu-divider {
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
            margin: 8px 0;
        }
        
        .ion-user-menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s ease;
            font-size: 14px;
        }
        
        .ion-user-menu-item:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
        }
        
        .ion-user-menu-item.ion-logout:hover {
            background: rgba(239, 68, 68, 0.1);
            color: #fca5a5;
        }
        
        .ion-user-menu-item svg {
            flex-shrink: 0;
        }
        
        .video-header {
            margin-bottom: 15px;
        }
        
        .video-title {
            font-size: 1.75rem;
            font-weight: bold;
            margin-bottom: 12px;
            color: #f1f5f9;
            line-height: 1.3;
        }
        
        .video-header-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            padding: 10px 0;
        }
        
        .video-meta {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.875rem;
            color: #94a3b8;
            flex-wrap: wrap;
        }
        
        .video-meta span:not(:last-child)::after {
            content: '•';
            margin-left: 12px;
            color: #475569;
        }
        
        .video-creator {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .creator-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #b28254;
            flex-shrink: 0;
        }
        
        .creator-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .creator-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        
        .creator-label {
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .creator-link {
            font-size: 1rem;
            color: #b28254;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .creator-link:hover {
            color: #d4a574;
        }
        
        .creator-link:hover span {
            color: #94a3b8;
        }
        
        .video-player-container {
            background: #1e293b;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 20px;
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
        
        /* Enhanced Share Button Styling */
        .share-section .ion-share-button {
            padding: 14px 32px !important;
            font-size: 16px !important;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
            color: white !important;
            border-radius: 10px !important;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
            transition: all 0.3s ease !important;
        }
        
        .share-section .ion-share-button:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.5) !important;
            background: linear-gradient(135deg, #059669 0%, #047857 100%) !important;
        }
        
        .share-section .ion-share-button svg {
            width: 20px !important;
            height: 20px !important;
            stroke: white !important;
        }
        
        .share-section .ion-share-button span {
            font-weight: 600;
            color: white !important;
        }
        
        /* Old share styles removed - using Enhanced Share System now */
        
        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #334155;
            color: #64748b;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 10px 15px;
            }
            
            .ion-logo-header img {
                height: 40px;
            }
            
            .video-title {
                font-size: 1.35rem;
                margin-bottom: 10px;
            }
            
            .video-header-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
                padding: 12px 0;
            }
            
            .video-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 6px;
                width: 100%;
            }
            
            .video-info, .share-section {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="ion-logo-header">
            <a href="https://ions.com"><img src="https://ions.com/menu/ion-logo-gold.png" alt="ION Logo"></a>
            
            <?php if ($is_logged_in && $current_user): ?>
                <div class="ion-user-menu">
                    <button class="ion-user-avatar-btn" onclick="toggleUserMenu()" title="Account menu">
                        <?php if (!empty($current_user->photo_url)): ?>
                            <img src="<?= htmlspecialchars($current_user->photo_url) ?>" alt="<?= htmlspecialchars($current_user->fullname ?: $current_user->handle) ?>">
                        <?php else: ?>
                            <div class="avatar-placeholder">
                                <?= strtoupper(substr($current_user->handle ?: $current_user->email, 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                    </button>
                    <div class="ion-user-dropdown" id="ionUserDropdown">
                        <div class="ion-user-info">
                            <div class="ion-user-name"><?= htmlspecialchars($current_user->fullname ?: $current_user->handle) ?></div>
                            <div class="ion-user-email"><?= htmlspecialchars($current_user->email) ?></div>
                        </div>
                        <div class="ion-user-menu-divider"></div>
                        <a href="/@<?= htmlspecialchars($current_user->handle) ?>" class="ion-user-menu-item">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            <span>View Profile</span>
                        </a>
                        <a href="/app/creators.php" class="ion-user-menu-item">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                                <path d="M2 17l10 5 10-5"></path>
                                <path d="M2 12l10 5 10-5"></path>
                            </svg>
                            <span>Creator Dashboard</span>
                        </a>
                        <a href="/app/directory.php" class="ion-user-menu-item">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                <polyline points="9 22 9 12 15 12 15 22"></polyline>
                            </svg>
                            <span>My Videos</span>
                        </a>
                        <div class="ion-user-menu-divider"></div>
                        <a href="/login/logout.php?return_to=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="ion-user-menu-item ion-logout">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                <polyline points="16 17 21 12 16 7"></polyline>
                                <line x1="21" y1="12" x2="9" y2="12"></line>
                            </svg>
                            <span>Log Out</span>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="video-header">
            <h1 class="video-title"><?= htmlspecialchars($video->title) ?></h1>
            
            <div class="video-header-meta">
                <?php if (!empty($video->creator_handle)): ?>
                    <div class="video-creator">
                        <?php if (!empty($video->creator_photo)): ?>
                            <img src="<?= htmlspecialchars($video->creator_photo) ?>" alt="<?= htmlspecialchars($video->creator_name ?: $video->creator_handle) ?>" class="creator-avatar">
                        <?php else: ?>
                            <div class="creator-avatar" style="background: linear-gradient(135deg, #b28254 0%, #8b6239 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 16px;">
                                <?= strtoupper(substr($video->creator_handle, 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        <div class="creator-info">
                            <span class="creator-label">Published by</span>
                            <a href="/@<?= htmlspecialchars($video->creator_handle) ?>" class="creator-link" title="View <?= htmlspecialchars($video->creator_name ?: '@' . $video->creator_handle) ?>'s profile">
                                <?php if (!empty($video->creator_name)): ?>
                                    <?= htmlspecialchars($video->creator_name) ?> <span style="color: #64748b;">@<?= htmlspecialchars($video->creator_handle) ?></span>
                                <?php else: ?>
                                    @<?= htmlspecialchars($video->creator_handle) ?>
                                <?php endif; ?>
                            </a>
                        </div>
                    </div>
                <?php elseif (!empty($video->creator_name)): ?>
                    <div class="video-creator">
                        <div class="creator-avatar" style="background: linear-gradient(135deg, #b28254 0%, #8b6239 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 16px;">
                            <?= strtoupper(substr($video->creator_name, 0, 1)) ?>
                        </div>
                        <div class="creator-info">
                            <span class="creator-label">Published by</span>
                            <span class="creator-link" style="cursor: default;">
                                <?= htmlspecialchars($video->creator_name) ?>
                            </span>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="video-meta">
                    <span>Published: <?= date('M j, Y', strtotime($video->date_added ?? $video->published_at ?? 'now')) ?></span>
                    <span>Category: <?= htmlspecialchars($video->category ?? 'General') ?></span>
                    <?php if ($resolution['clicks'] > 0): ?>
                        <span>Views: <?= number_format($resolution['clicks']) ?></span>
                    <?php endif; ?>
                    
                    <!-- Like/Dislike Reactions -->
                    <span class="reactions-separator"></span>
                    <?php 
                    // Debug: Log what we're outputting to HTML
                    error_log("HTML OUTPUT: data-video-id='{$video->id}' data-user-action='" . htmlspecialchars($user_reaction ?? '') . "'");
                    ?>
                    <div class="video-reactions inline" data-video-id="<?= $video->id ?>" data-user-action="<?= htmlspecialchars($user_reaction ?? '') ?>">
                        <?php if ($is_logged_in): ?>
                            <button class="reaction-btn like-btn" title="Like this video">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path>
                                </svg>
                                <span class="like-count"><?= $video->likes > 0 ? number_format($video->likes) : '' ?></span>
                            </button>
                            <button class="reaction-btn dislike-btn" title="Dislike this video">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3zm7-13h2.67A2.31 2.31 0 0 1 22 4v7a2.31 2.31 0 0 1-2.33 2H17"></path>
                                </svg>
                                <span class="dislike-count"><?= ($video->dislikes ?? 0) > 0 ? number_format($video->dislikes ?? 0) : '' ?></span>
                            </button>
                        <?php else: ?>
                            <button class="reaction-btn like-btn" title="Login to like this video" onclick="showLoginModal()">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path>
                                </svg>
                                <span class="like-count"><?= $video->likes > 0 ? number_format($video->likes) : '' ?></span>
                            </button>
                            <button class="reaction-btn dislike-btn" title="Login to dislike this video" onclick="showLoginModal()">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3zm7-13h2.67A2.31 2.31 0 0 1 22 4v7a2.31 2.31 0 0 1-2.33 2H17"></path>
                                </svg>
                                <span class="dislike-count"><?= ($video->dislikes ?? 0) > 0 ? number_format($video->dislikes ?? 0) : '' ?></span>
                            </button>
                        <?php endif; ?>
                        <div class="reaction-feedback"></div>
                    </div>
                </div>
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
            <div style="display: flex; justify-content: center; align-items: center; gap: 20px; margin-top: 20px;">
                <?= $enhanced_share_manager->renderShareButton($video->id, [
                    'size' => 'large',
                    'style' => 'both',
                    'platforms' => ['facebook', 'twitter', 'whatsapp', 'linkedin', 'telegram', 'reddit', 'email', 'copy'],
                    'show_embed' => true
                ]) ?>
            </div>
        </div>
        
        <div class="footer">
            <p>&copy; <?= date('Y') ?> ION Local Network.</p>
        </div>
    </div>
    
    <!-- Video.js JavaScript -->
    <script src="/player/video.min.js"></script>
    
    <!-- Enhanced Share System -->
    <script src="/share/enhanced-ion-share.js"></script>
    
    <!-- Video Reactions System -->
    <script src="/login/modal.js"></script>
    <script src="/app/video-reactions.js"></script>
    
    <!-- User Menu Toggle -->
    <script>
        function toggleUserMenu() {
            const dropdown = document.getElementById('ionUserDropdown');
            dropdown.classList.toggle('active');
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            const userMenu = document.querySelector('.ion-user-menu');
            const dropdown = document.getElementById('ionUserDropdown');
            if (userMenu && dropdown && !userMenu.contains(e.target)) {
                dropdown.classList.remove('active');
            }
        });
        
        // Debug: Comprehensive reaction button state verification
        window.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                console.log('\n========== REACTION BUTTON DEBUG REPORT ==========');
                
                // Check all reaction containers
                const containers = document.querySelectorAll('.video-reactions[data-video-id]');
                console.log(`📦 Found ${containers.length} reaction container(s)`);
                
                containers.forEach(container => {
                    const videoId = container.dataset.videoId;
                    const userAction = container.dataset.userAction;
                    console.log(`\n🎬 Video ID: ${videoId}`);
                    console.log(`   data-user-action: "${userAction}" (length: ${userAction ? userAction.length : 0})`);
                    
                    const likeBtn = container.querySelector('.like-btn');
                    const dislikeBtn = container.querySelector('.dislike-btn');
                    
                    if (likeBtn) {
                        const likeActive = likeBtn.classList.contains('active');
                        const likeStyle = window.getComputedStyle(likeBtn);
                        console.log(`   👍 LIKE button:`, {
                            hasActiveClass: likeActive,
                            allClasses: likeBtn.className,
                            computedColor: likeStyle.color,
                            computedBackground: likeStyle.backgroundColor,
                            computedBorder: likeStyle.borderColor
                        });
                    }
                    
                    if (dislikeBtn) {
                        const dislikeActive = dislikeBtn.classList.contains('active');
                        const dislikeStyle = window.getComputedStyle(dislikeBtn);
                        console.log(`   👎 DISLIKE button:`, {
                            hasActiveClass: dislikeActive,
                            allClasses: dislikeBtn.className,
                            computedColor: dislikeStyle.color,
                            computedBackground: dislikeStyle.backgroundColor,
                            computedBorder: dislikeStyle.borderColor
                        });
                    }
                });
                
                // Check if CSS is loaded
                const testDiv = document.createElement('div');
                testDiv.className = 'reaction-btn like-btn active';
                testDiv.style.display = 'none';
                document.body.appendChild(testDiv);
                const testStyle = window.getComputedStyle(testDiv);
                console.log(`\n🧪 CSS TEST (should be green if working):`, {
                    color: testStyle.color,
                    background: testStyle.backgroundColor
                });
                document.body.removeChild(testDiv);
                
                console.log('================================================\n');
            }, 1500);
        });
    </script>
    
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
        // Enhanced share system is now loaded - no custom share functions needed
        // The EnhancedIONShare module handles all sharing functionality
    </script>
</body>
</html>
