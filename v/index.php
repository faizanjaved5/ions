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
    
    // Skip if this is a direct PHP file request (like test files)
    if (preg_match('#/v/[^/]+\.php#', $request_path)) {
        error_log("Skipping slug extraction for PHP file: $request_path");
        return; // Let the PHP file execute normally
    }
    
    // Extract shortlink from URL (matches /v/abc123 or /v/abc123-title)
    if (preg_match('#/v/([^/?]+)#', $request_path, $matches)) {
        $slug = $matches[1];
        error_log("Extracted slug from path: $slug");
    }
}

// Clean the slug - extract just the shortcode part (remove any suffix after dash)
if (!empty($slug)) {
    // Extract just the alphanumeric shortcode (6-8 chars)
    if (preg_match('/^([a-zA-Z0-9]{6,8})(?:-.*)?$/i', $slug, $matches)) {
        $slug = strtolower($matches[1]); // Normalize to lowercase for case-insensitive lookup
        error_log("Cleaned slug: $slug");
    } else {
        error_log("Invalid slug format: $slug (expected 6-8 alphanumeric chars)");
        $slug = ''; // Reset if invalid format
    }
}

// If no valid slug provided, redirect to main page
if (empty($slug)) {
    error_log("No valid slug found, redirecting to home");
    header('Location: /');
    exit();
}

// Load dependencies
$root = dirname(__DIR__);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/shortlink-manager.php';
require_once __DIR__ . '/../share/enhanced-share-manager.php';
require_once __DIR__ . '/../includes/ioncategories.php'; // For fast category slug lookups

/**
 * Convert category/network value to proper slug for URL
 * Handles both existing slugs and legacy names
 */
function ensureSlug($value, $db) {
    if (empty($value)) return null;
    
    $value = trim($value);
    
    // Check if already a slug (lowercase, no spaces, hyphens only)
    if (preg_match('/^[a-z0-9-]+$/', $value)) {
        return $value; // Already a slug
    }
    
    // Has spaces or special characters - it's a name, convert to slug
    // Try fast array lookup first
    if (function_exists('get_ion_category_slug')) {
        $slug = get_ion_category_slug($value);
        if ($slug) {
            return $slug;
        }
    }
    
    // Try database lookup
    try {
        $pdo = $db->getPDO();
        $stmt = $pdo->prepare("SELECT slug FROM IONNetworks WHERE network_name = ? LIMIT 1");
        $stmt->execute([$value]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && !empty($result['slug'])) {
            return strtolower($result['slug']);
        }
    } catch (Exception $e) {
        error_log('Error looking up slug for: ' . $value . ' - ' . $e->getMessage());
    }
    
    // Fallback: create slug from value
    $slug = strtolower($value);
    $slug = str_replace(['™', '®', ' on ion', ' network'], ['', '', '', ''], $slug);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    
    return $slug;
}

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
        
        // Aliases for navbar embed compatibility
        $is_viewer_logged_in = $is_logged_in;
        $current_viewer = $current_user;
        
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
            // Google Drive preview - autoplay not fully supported but try
            $player_config['drive_id'] = $video->video_id;
            $player_type = 'drive';
            break;
            
        case 'muvi':
            // Ensure Muvi embed has proper parameters (autoplay on, muted required for browsers)
            $separator = (strpos($video_url, '?') !== false) ? '&' : '?';
            $player_config['iframe_src'] = $video_url . $separator . 'autoplay=1&muted=1';
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
            // Loom embed with proper parameters (autoplay enabled, muted required for browsers)
            $separator = (strpos($video_url, '?') !== false) ? '&' : '?';
            $player_config['iframe_src'] = $video_url . $separator . 'autoplay=1&muted=1&hide_owner=true&hide_share=true&hide_title=true&hideEmbedTopBar=true';
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
                // Generic iframe - try to add autoplay parameters
                $separator = (strpos($video_url, '?') !== false) ? '&' : '?';
                $player_config['iframe_src'] = $video_url . $separator . 'autoplay=1&muted=1';
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

// Detect theme (light or dark mode)
$theme = $_SESSION['theme'] ?? $_COOKIE['theme'] ?? $_GET['theme'] ?? 'dark';
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
        /* IMPORTANT: Scope resets to container only - DO NOT affect navbar/menu */
        .container, .container * {
            box-sizing: border-box;
        }
        
        body {
            background: #0f172a;
            color: #e2e8f0;
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 15px 20px;
        }
        
        .container h1, .container h2, .container h3, .container h4, .container h5, .container h6, .container p {
            margin: 0;
            padding: 0;
        }
        
        .video-header {
            margin-bottom: 15px;
        }
        
        .video-title {
            font-size: 1.75rem;
            font-weight: bold;
            margin-bottom: 12px;
            color: #e2e8f0;
            line-height: 1.3;
        }
        
        h3 {
            font-size: 1.3rem;
            margin-bottom: 15px;
            color: #e2e8f0;
        }
        
        .video-info {
            background: #1e293b;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 20px;
        }
        
        .video-description {
            color: #cbd5e1;
            line-height: 1.7;
            margin-bottom: 20px;
        }
        
        .video-description p {
            color: #94a3b8;
        }
        
        .share-section {
            background: #1e293b;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .share-title {
            margin-bottom: 20px;
            color: #e2e8f0;
        }
        
        /* Enhanced Share Button Styling */
        .share-section .ion-share-button {
            padding: 14px 32px !important;
            font-size: 16px !important;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
            border: none !important;
            border-radius: 8px !important;
            color: white !important;
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
        
        .video-player-container {
            position: relative;
            background: #1e293b;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
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
        
        .video-player video {
            width: 100%;
            height: 100%;
        }
        
        .video-meta-below {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
            padding: 0px 0;
            margin-bottom: 20px;
            font-size: 0.65rem;
            color: #94a3b8;
        }
        
        .video-meta-below span {
            white-space: nowrap;
        }
        
        .video-meta-below .separator {
            color: #475569;
            font-size: 0.6rem;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 30px;
            border-top: 1px solid #334155;
            text-align: center;
        }
        
        .footer p {
            font-size: 0.875rem;
            color: #94a3b8;
            margin: 0;
        }
        
        .video-breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.875rem;
            color: #94a3b8;
            flex-wrap: wrap;
        }
        
        .video-breadcrumb a {
            color: #b28254;
            text-decoration: underline;
            text-decoration-color: rgba(178, 130, 84, 0.3);
            text-underline-offset: 3px;
            transition: all 0.2s;
            white-space: nowrap;
        }
        
        .video-breadcrumb a:hover {
            color: #d4a574;
            text-decoration-color: #d4a574;
        }
        
        .video-breadcrumb .separator {
            color: #475569;
            font-weight: bold;
        }
        
        .video-meta {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            gap: 20px;
            font-size: 0.875rem;
            color: #94a3b8;
            width: 100%;
            padding: 15px 0;
        }
        
        /* Creator on left */
        .video-creator {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0; /* Allow flex items to shrink */
        }
        
        .video-meta .video-creator {
            justify-self: start;
        }
        
        .creator-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            flex-shrink: 0; /* Don't shrink avatar */
        }
        
        .creator-info {
            display: flex;
            flex-direction: column;
            min-width: 0; /* Allow text to wrap if needed */
        }
        
        .creator-label {
            font-size: 0.75rem;
            color: #64748b;
            margin-bottom: 2px;
        }
        
        .creator-link {
            color: #b28254;
            text-decoration: none;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .creator-link:hover {
            color: #d4a574;
        }
        
        /* Breadcrumb centered */
        .video-meta .video-breadcrumb {
            justify-self: center;
            text-align: center;
        }
        
        /* Reactions on right */
        .video-meta .video-reactions {
            justify-self: end;
        }
        
        /* Mobile responsive breakpoint */
        @media (max-width: 768px) {
            .video-meta {
                grid-template-columns: 1fr;
                grid-template-rows: auto auto auto;
                gap: 12px;
                padding: 12px 0;
            }
            
            /* Stack vertically on mobile */
            .video-meta .video-creator {
                justify-self: start;
                order: 1;
            }
            
            .video-meta .video-breadcrumb {
                justify-self: start;
                text-align: left;
                order: 2;
            }
            
            .video-meta .video-reactions {
                justify-self: start;
                order: 3;
            }
            
            .video-breadcrumb {
                font-size: 0.75rem;
            }
            
            .video-title {
                font-size: 1.35rem;
                margin-bottom: 10px;
            }
            
            .container {
                padding: 10px 15px;
            }
            
            .video-info, .share-section {
                padding: 15px;
            }
        }
        
        /* ============================================ */
        /* LIGHT MODE - Comprehensive Theme Override */
        /* MUST BE AT THE END TO OVERRIDE DARK MODE! */
        /* ============================================ */
        body[data-theme="light"] {
            background: #ffffff !important;
            color: #1a1a1a !important;
        }
        
        /* Text Elements */
        body[data-theme="light"] .video-title,
        body[data-theme="light"] h1,
        body[data-theme="light"] h2,
        body[data-theme="light"] h3,
        body[data-theme="light"] h4 {
            color: #1a1a1a !important;
        }
        
        body[data-theme="light"] .video-description p,
        body[data-theme="light"] .video-tags {
            color: #495057 !important;
        }
        
        /* Meta Information */
        body[data-theme="light"] .video-meta {
            color: #6c757d !important;
        }
        
        body[data-theme="light"] .video-meta span:not(:last-child)::after {
            color: #adb5bd !important;
        }
        
        /* Creator Section */
        body[data-theme="light"] .creator-label {
            color: #868e96 !important;
        }
        
        body[data-theme="light"] .creator-link {
            color: #b28254 !important;
        }
        
        body[data-theme="light"] .creator-link:hover {
            color: #8b5e3c !important;
        }
        
        body[data-theme="light"] .creator-link:hover span {
            color: #495057 !important;
        }
        
        /* Video Player Container */
        body[data-theme="light"] .video-player-container {
            background: #f1f3f5 !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08) !important;
            border: 1px solid #e9ecef !important;
        }
        
        /* Video Info Section */
        body[data-theme="light"] .video-info {
            background: #f8f9fa !important;
            border: 1px solid #dee2e6 !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05) !important;
        }
        
        body[data-theme="light"] .video-info h3 {
            color: #212529 !important;
        }
        
        body[data-theme="light"] .video-description {
            color: #495057 !important;
        }
        
        body[data-theme="light"] .video-tags {
            color: #6c757d !important;
        }
        
        body[data-theme="light"] .video-tags strong {
            color: #212529 !important;
        }
        
        /* Share Section */
        body[data-theme="light"] .share-section {
            background: #f8f9fa !important;
            border: 1px solid #dee2e6 !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05) !important;
        }
        
        body[data-theme="light"] .share-title {
            color: #212529 !important;
        }
        
        /* Reaction Buttons - CRITICAL for visibility */
        body[data-theme="light"] .reaction-btn {
            background: #ffffff !important;
            border: 2px solid #dee2e6 !important;
            color: #495057 !important;
        }
        
        body[data-theme="light"] .reaction-btn:hover {
            background: #e9ecef !important;
            border-color: #adb5bd !important;
        }
        
        body[data-theme="light"] .reaction-btn .like-icon,
        body[data-theme="light"] .reaction-btn .dislike-icon {
            color: #495057 !important;
        }
        
        body[data-theme="light"] .reaction-btn .like-count,
        body[data-theme="light"] .reaction-btn .dislike-count {
            color: #6c757d !important;
        }
        
        body[data-theme="light"] .reaction-btn.like-btn.active {
            background: #e7f5ff !important;
            border-color: #339af0 !important;
        }
        
        body[data-theme="light"] .reaction-btn.like-btn.active .like-icon,
        body[data-theme="light"] .reaction-btn.like-btn.active .like-count {
            color: #1971c2 !important;
        }
        
        body[data-theme="light"] .reaction-btn.dislike-btn.active {
            background: #ffe3e3 !important;
            border-color: #fa5252 !important;
        }
        
        body[data-theme="light"] .reaction-btn.dislike-btn.active .dislike-icon,
        body[data-theme="light"] .reaction-btn.dislike-btn.active .dislike-count {
            color: #c92a2a !important;
        }
        
        /* Video Meta Below */
        body[data-theme="light"] .video-meta-below {
            color: #495057 !important;
            border-bottom-color: #dee2e6 !important;
        }
        
        body[data-theme="light"] .video-meta-below .separator {
            color: #adb5bd !important;
        }
        
        /* Footer */
        body[data-theme="light"] .footer {
            border-top: 1px solid #dee2e6 !important;
            color: #868e96 !important;
        }
        
        /* Share Button (Enhanced) */
        body[data-theme="light"] .share-section .ion-share-button {
            background: linear-gradient(135deg, #339af0 0%, #1971c2 100%) !important;
            box-shadow: 0 4px 12px rgba(51, 154, 240, 0.3) !important;
        }
        
        body[data-theme="light"] .share-section .ion-share-button:hover {
            background: linear-gradient(135deg, #228be6 0%, #1864ab 100%) !important;
            box-shadow: 0 6px 20px rgba(51, 154, 240, 0.4) !important;
        }
        
        body[data-theme="light"] .share-section .ion-share-button span {
            color: white !important;
        }
        
        body[data-theme="light"] .share-section .ion-share-button svg {
            stroke: white !important;
        }
        
        /* Light mode breadcrumb */
        body[data-theme="light"] .video-breadcrumb {
            color: #6c757d !important;
        }
        
        body[data-theme="light"] .video-breadcrumb a {
            color: #b28254 !important;
        }
        
        body[data-theme="light"] .video-breadcrumb a:hover {
            color: #8b5e3c !important;
        }
        
        body[data-theme="light"] .video-breadcrumb .separator {
            color: #adb5bd !important;
        }
        
        body[data-theme="light"] .video-meta-below {
            color: #6c757d !important;
        }
        
        body[data-theme="light"] .video-meta-below span:not(:last-child)::after {
            color: #adb5bd !important;
        }
        
        /* Light mode mobile overrides */
        @media (max-width: 768px) {
            body[data-theme="light"] .video-title {
                color: #1a1a1a !important;
            }
        }
    </style>
    
    <!-- Theme Switcher JavaScript -->
    <script>
        // Initialize theme from session/cookie/query
        let currentTheme = '<?= $theme ?>';
        
        // Toggle theme function
        function toggleTheme() {
            // Toggle between light and dark
            currentTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            // Update body data-theme attribute
            document.body.setAttribute('data-theme', currentTheme);
            
            // Update theme icon in navbar
            if (typeof window.updateThemeIcon === 'function') {
                window.updateThemeIcon();
            }
            
            // Save preference to cookie
            document.cookie = `theme=${currentTheme}; path=/; max-age=31536000`; // 1 year
            
            // Optional: Save to session via AJAX
            fetch('/api/set-theme.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ theme: currentTheme })
            }).catch(err => console.log('Theme save to session failed (non-critical):', err));
        }
        
        // Make globally available (multiple ways for compatibility)
        window.toggleTheme = toggleTheme;
        window.IONToggleTheme = toggleTheme; // Alternative name
        window.switchTheme = toggleTheme; // Another alternative
        
        // Also expose on document for easy access
        document.toggleTheme = toggleTheme;
        
        // Watch for theme changes made by other systems (like navbar)
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'attributes' && mutation.attributeName === 'data-theme') {
                    const newTheme = document.body.getAttribute('data-theme');
                    if (newTheme && newTheme !== currentTheme) {
                        currentTheme = newTheme;
                    }
                }
                
                // Auto-correct if background doesn't match theme
                if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                    const bgColor = window.getComputedStyle(document.body).backgroundColor;
                    const rgb = bgColor.match(/\d+/g);
                    if (rgb) {
                        const brightness = (parseInt(rgb[0]) * 299 + parseInt(rgb[1]) * 587 + parseInt(rgb[2]) * 114) / 1000;
                        
                        if (brightness > 128 && currentTheme === 'dark') {
                            currentTheme = 'light';
                            document.body.setAttribute('data-theme', 'light');
                        } else if (brightness <= 128 && currentTheme === 'light') {
                            currentTheme = 'dark';
                            document.body.setAttribute('data-theme', 'dark');
                        }
                    }
                }
            });
        });
        
        // Start observing
        observer.observe(document.body, { 
            attributes: true, 
            attributeFilter: ['data-theme', 'style', 'class'] 
        });
    </script>
</head>
<body data-theme="<?= $theme ?>">
<?php 
// Ensure $root is defined (safety check from index2.php)
if (!isset($root)) {
    $root = dirname(__DIR__);
}
?>

<!-- ION Navbar Embed: fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap" rel="stylesheet">
<link rel="preload" as="style" href="/menu/ion-navbar.css">

<!-- ION Navbar (React component loaded via JavaScript) -->
<div id="ion-navbar-root"></div>

<!-- ION Navbar Embed: script setup -->
<script>
    // Minimal globals expected by some libraries
    window.process = window.process || {
        env: {
            NODE_ENV: 'production'
        }
    };
    window.global = window.global || window;
</script>
<script src="/menu/ion-navbar.iife.js"></script>
<script>
    (function() {
        if (window.IONNavbar && typeof window.IONNavbar.mount === 'function') {
            window.IONNavbar.mount('#ion-navbar-root', {
                useShadowDom: true,
                cssHref: '/menu/ion-navbar.css'
            });
        }
        
        // Theme toggle is handled by the navbar - no need for additional button
    })();
</script>

    <div class="container">
        <div class="video-header">
            <h1 class="video-title"><?= htmlspecialchars($video->title) ?></h1>
            
            <div class="video-header-meta">
                <div class="video-meta">
                    <!-- Creator Info (Left Column) - Always present to maintain grid alignment -->
                    <div class="video-creator">
                        <?php if (!empty($video->creator_handle)): ?>
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
                        <?php elseif (!empty($video->creator_name)): ?>
                            <div class="creator-avatar" style="background: linear-gradient(135deg, #b28254 0%, #8b6239 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 16px;">
                                <?= strtoupper(substr($video->creator_name, 0, 1)) ?>
                            </div>
                            <div class="creator-info">
                                <span class="creator-label">Published by</span>
                                <span class="creator-link" style="cursor: default;">
                                    <?= htmlspecialchars($video->creator_name) ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        <!-- Empty div maintains grid column even when no creator exists -->
                    </div>
                    
                    <!-- Breadcrumb Navigation (Center Column) -->
                    <div class="video-breadcrumb">
                        <?php if (!empty($video->ion_channel)): ?>
                            <?php 
                            // Get channel slug from video (already stored) - ensure lowercase
                            $channelSlug = strtolower($video->slug ?? 'ions');
                            ?>
                            <a href="/<?= htmlspecialchars($channelSlug) ?>"><?= htmlspecialchars($video->ion_channel) ?></a>
                        <?php endif; ?>
                        
                        <?php if (!empty($video->ion_category)): ?>
                            <?php if (!empty($video->ion_channel)): ?>
                                <span class="separator">|</span>
                            <?php endif; ?>
                            <?php 
                            // Convert category to slug (handles both slugs and names)
                            $categorySlug = ensureSlug($video->ion_category, $db);
                            $categoryDisplayName = $video->ion_category;
                            
                            // If the value was a slug, look up the display name
                            if ($categorySlug === $video->ion_category) {
                                // Try fast array lookup first
                                if (function_exists('get_ion_category_name_from_slug')) {
                                    $arrayName = get_ion_category_name_from_slug($categorySlug);
                                    if ($arrayName) {
                                        $categoryDisplayName = $arrayName;
                                    }
                                }
                                
                                // Fallback to database if not found in array
                                if ($categoryDisplayName === $video->ion_category) {
                                    try {
                                        $pdo = $db->getPDO();
                                        $stmt = $pdo->prepare("SELECT network_name FROM IONNetworks WHERE slug = ? LIMIT 1");
                                        $stmt->execute([$categorySlug]);
                                        $catResult = $stmt->fetch(PDO::FETCH_ASSOC);
                                        if ($catResult && !empty($catResult['network_name'])) {
                                            $categoryDisplayName = $catResult['network_name'];
                                        }
                                    } catch (Exception $e) {
                                        error_log('Error looking up category name: ' . $e->getMessage());
                                    }
                                }
                            }
                            // else: value was a name, display it as-is, but use slug for URL
                            ?>
                            <a href="/<?= htmlspecialchars($categorySlug) ?>"><?= htmlspecialchars($categoryDisplayName) ?></a>
                        <?php endif; ?>
                        
                        <?php 
                        // Only show network if it's different from category to avoid duplication
                        $showNetwork = !empty($video->ion_network);
                        if ($showNetwork && !empty($video->ion_category)) {
                            // Check if they're the same (compare slugs to handle both slug and name formats)
                            $catSlugCheck = ensureSlug($video->ion_category, $db);
                            $netSlugCheck = ensureSlug($video->ion_network, $db);
                            if (strtolower($catSlugCheck) === strtolower($netSlugCheck)) {
                                $showNetwork = false; // Skip network if it's the same as category
                            }
                        }
                        ?>
                        <?php if ($showNetwork): ?>
                            <?php if (!empty($video->ion_channel) || !empty($video->ion_category)): ?>
                                <span class="separator">|</span>
                            <?php endif; ?>
                            <?php 
                            // Convert network to slug (handles both slugs and names)
                            $networkSlug = ensureSlug($video->ion_network, $db);
                            $networkDisplayName = $video->ion_network;
                            
                            // If the value was a slug, look up the display name
                            if ($networkSlug === $video->ion_network) {
                                try {
                                    $pdo = $db->getPDO();
                                    $stmt = $pdo->prepare("SELECT network_name FROM IONNetworks WHERE slug = ? LIMIT 1");
                                    $stmt->execute([$networkSlug]);
                                    $netResult = $stmt->fetch(PDO::FETCH_ASSOC);
                                    if ($netResult && !empty($netResult['network_name'])) {
                                        $networkDisplayName = $netResult['network_name'];
                                    }
                                } catch (Exception $e) {
                                    error_log('Error looking up network name: ' . $e->getMessage());
                                }
                            }
                            // else: value was a name, display it as-is, but use slug for URL
                            ?>
                            <a href="/<?= htmlspecialchars($networkSlug) ?>"><?= htmlspecialchars($networkDisplayName) ?></a>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Like/Dislike Reactions (Right Column) -->
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
        
        <!-- Video Player -->
        <div class="video-player-container">
            <div class="video-player">
                <?php if ($player_type === 'videojs'): ?>
                    <!-- Video.js Player for uploaded files -->
                    <video
                        id="video-player"
                        class="video-js vjs-default-skin"
                        controls
                        preload="metadata"
                        autoplay
                        muted
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
                        src="https://www.youtube.com/embed/<?= htmlspecialchars($player_config['youtube_id']) ?>?autoplay=1&rel=0"
                        allowfullscreen
                        allow="autoplay; encrypted-media">
                    </iframe>
                    
                <?php elseif ($player_type === 'vimeo'): ?>
                    <!-- Vimeo Embed -->
                    <iframe 
                        src="https://player.vimeo.com/video/<?= htmlspecialchars($player_config['vimeo_id']) ?>?autoplay=1&muted=1"
                        allowfullscreen
                        allow="autoplay; fullscreen">
                    </iframe>
                    
                <?php elseif ($player_type === 'drive'): ?>
                    <!-- Google Drive Embed (autoplay limited by Google) -->
                    <iframe 
                        src="https://drive.google.com/file/d/<?= htmlspecialchars($player_config['drive_id']) ?>/preview?autoplay=1"
                        allowfullscreen
                        allow="autoplay; encrypted-media">
                    </iframe>
                    
                <?php elseif ($player_type === 'wistia'): ?>
                    <!-- Wistia Embed -->
                    <iframe 
                        src="https://fast.wistia.net/embed/iframe/<?= htmlspecialchars($player_config['wistia_id']) ?>?autoplay=1&muted=1&controls=1"
                        allowfullscreen
                        allow="autoplay; encrypted-media">
                    </iframe>
                    
                <?php elseif ($player_type === 'rumble'): ?>
                    <!-- Rumble Embed -->
                    <iframe 
                        src="https://rumble.com/embed/v<?= htmlspecialchars($player_config['rumble_id']) ?>/?autoplay=2"
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
        
        <!-- Published Date and Views (below video player) -->
        <div class="video-meta-below">
            <span>Published: <?= date('M j, Y', strtotime($video->date_added ?? $video->published_at ?? 'now')) ?></span>
            <?php if ($resolution['clicks'] > 0): ?>
                <span class="separator">•</span>
                <span>Views: <?= number_format($resolution['clicks']) ?></span>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($video->description)): ?>
            <div class="video-info">
                <h3>Description</h3>
                <div class="video-description">
                    <?= nl2br(htmlspecialchars($video->description)) ?>
                </div>
                
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
        
        <?php
        // Include the ION Footer
        $footer_path = $root . '/includes/ionfooter.php';
        if (file_exists($footer_path)) {
            require $footer_path;
        } else {
            echo '<div class="footer"><p>&copy; ' . date('Y') . ' <a href="/">ION Local Network</a>. All rights reserved.</p></div>';
        }
        ?>
    </div>
    
    <!-- Video.js JavaScript -->
    <script src="/player/video.min.js"></script>
    
    <!-- Enhanced Share System -->
    <script src="/share/enhanced-ion-share.js"></script>
    
    <!-- Video Reactions System -->
    <script src="/login/modal.js"></script>
    <script src="/app/video-reactions.js"></script>
    
    <!-- Debug Script -->
    <script>
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
        
        // Ensure autoplay works when Video.js is ready
        (function() {
            const player = videojs('video-player');
            
            player.ready(function() {
                // Try to autoplay (muted)
                const playPromise = this.play();
                
                if (playPromise !== undefined) {
                    playPromise.then(() => {
                        console.log('✅ Video autoplay started successfully');
                    }).catch(error => {
                        console.warn('⚠️ Autoplay was prevented by browser:', error);
                        // Show a play button overlay or notification if needed
                    });
                }
            });
        })();
    </script>
    <?php endif; ?>
    
    <script>
        // Enhanced share system is now loaded - no custom share functions needed
        // The EnhancedIONShare module handles all sharing functionality
    </script>
    
    <!-- Pollybot Chatbot Widget -->
    <script 
      src="https://pollybot.app/widget-packages.min.js"
      data-chatbot-id="cmgo2cfvr0003np2yvvozxdfb"
      data-widget-id="cmgo2cfvx0005np2ymynim8ad"
    ></script>
</body>
</html>