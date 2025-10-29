<?php
/**
 * ION Featured Videos Carousel Component - COMPLETE VERSION v4.0
 * 
 * FULL FEATURED with both auto-scroll AND manual controls
 * 
 * Features:
 * - Smooth CSS animation auto-scrolling
 * - Manual arrow buttons (pause auto-scroll when clicked)
 * - Independent carousel button handlers
 * - Hover-to-play video previews
 * - Enhanced share manager integration
 * - Full light/dark theme support
 * - Complete responsive design
 * - All metadata and styling
 * 
 * @version 4.0 Complete
 */

// Helper function for HTML escaping if not already defined
if (!function_exists('h')) {
    function h($str) {
        return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Render featured videos carousel
 * 
 * @param PDO $pdo Database connection
 * @param string $filterType 'channel' or 'profile'
 * @param mixed $filterValue Channel slug or User ID
 */
function renderFeaturedVideosCarousel($pdo, $filterType, $filterValue) {
    try {
        // Check if viewer is logged in
        $current_user_id = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $is_viewer_logged_in = !empty($current_user_id);
        
        // Build query based on filter type
        if ($filterType === 'channel') {
            // For city pages - get videos distributed to this channel with Featured badge
            $sql = "
                SELECT DISTINCT
                    v.id,
                    v.slug,
                    v.short_link,
                    v.title,
                    v.thumbnail,
                    v.video_link,
                    v.published_at,
                    v.view_count,
                    v.user_id,
                    v.source,
                    v.videotype,
                    v.video_id,
                    v.likes,
                    v.dislikes,
                    u.handle as creator_handle,
                    u.fullname as creator_name,
                    u.photo_url as creator_photo,
                    " . ($is_viewer_logged_in ? "vl.action_type as user_reaction" : "NULL as user_reaction") . "
                FROM IONLocalVideos v
                LEFT JOIN IONEERS u ON v.user_id = u.user_id
                INNER JOIN IONVideoBadges vb ON v.id = vb.video_id
                INNER JOIN IONBadges b ON vb.badge_id = b.id
                LEFT JOIN IONLocalBlast lb ON v.id = lb.video_id
                LEFT JOIN IONLocalNetwork ln ON lb.channel_slug = ln.slug
                " . ($is_viewer_logged_in ? "LEFT JOIN IONVideoLikes vl ON v.id = vl.video_id AND vl.user_id = :viewer_id" : "") . "
                WHERE v.status = 'Approved'
                  AND v.visibility = 'Public'
                  AND LOWER(b.name) = 'featured'
                  AND (ln.slug = :filter_value OR v.slug = :filter_value)
                ORDER BY v.published_at DESC
                LIMIT 20
            ";
        } else {
            // For profile pages - get user's videos with Featured badge
            $sql = "
                SELECT DISTINCT
                    v.id,
                    v.slug,
                    v.short_link,
                    v.title,
                    v.thumbnail,
                    v.video_link,
                    v.published_at,
                    v.view_count,
                    v.user_id,
                    v.source,
                    v.videotype,
                    v.video_id,
                    v.likes,
                    v.dislikes,
                    u.handle as creator_handle,
                    u.fullname as creator_name,
                    u.photo_url as creator_photo,
                    " . ($is_viewer_logged_in ? "vl.action_type as user_reaction" : "NULL as user_reaction") . "
                FROM IONLocalVideos v
                LEFT JOIN IONEERS u ON v.user_id = u.user_id
                INNER JOIN IONVideoBadges vb ON v.id = vb.video_id
                INNER JOIN IONBadges b ON vb.badge_id = b.id
                " . ($is_viewer_logged_in ? "LEFT JOIN IONVideoLikes vl ON v.id = vl.video_id AND vl.user_id = :viewer_id" : "") . "
                WHERE v.status = 'Approved'
                  AND v.visibility = 'Public'
                  AND v.user_id = :filter_value
                  AND LOWER(b.name) = 'featured'
                ORDER BY v.published_at DESC
                LIMIT 20
            ";
        }
        
        $stmt = $pdo->prepare($sql);
        $params = [':filter_value' => $filterValue];
        if ($is_viewer_logged_in) {
            $params[':viewer_id'] = $current_user_id;
        }
        $stmt->execute($params);
        $featuredVideos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Don't render anything if no featured videos found
        if (empty($featuredVideos)) {
            error_log("Featured Videos: No videos found for {$filterType}={$filterValue}");
            return;
        }
        
        error_log("Featured Videos: Rendering " . count($featuredVideos) . " videos");
        
        // Load Enhanced Share Manager if not already loaded
        $root = dirname(dirname(__FILE__));
        if (!class_exists('EnhancedIONShareManager')) {
            $share_manager_path = $root . '/share/enhanced-share-manager.php';
            $db_config_path = $root . '/config/database.php';
            
            if (file_exists($share_manager_path) && file_exists($db_config_path)) {
                require_once $share_manager_path;
                require_once $db_config_path;
                global $db;
                $enhanced_share_manager = new EnhancedIONShareManager($db);
                error_log('Featured Carousel: Successfully loaded Enhanced Share Manager');
            } else {
                error_log('Featured Carousel: Share manager files not found');
                $enhanced_share_manager = null;
            }
        } else {
            global $enhanced_share_manager;
            if (!isset($enhanced_share_manager)) {
                if (file_exists($root . '/config/database.php')) {
                    require_once $root . '/config/database.php';
                    global $db;
                    $enhanced_share_manager = new EnhancedIONShareManager($db);
                } else {
                    $enhanced_share_manager = null;
                }
            }
        }
        
        // Determine if we need to repeat videos for infinite scrolling
        $videoCount = count($featuredVideos);
        $needsInfiniteScroll = $videoCount >= 4;
        
        // For infinite scrolling, we'll render each video 3 times
        $displayVideos = $needsInfiniteScroll ? array_merge($featuredVideos, $featuredVideos, $featuredVideos) : $featuredVideos;
        
        // Generate unique carousel ID
        $carouselId = 'featured-carousel-' . uniqid();
        
        ?>
        
        <!-- ION Featured Videos Carousel -->
        <section class="ion-featured-carousel">
            <div class="featured-header">
                <h2>
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline-block;vertical-align:middle;margin-right:8px;">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                    </svg>
                    ION Featured Videos
                </h2>
            </div>
            
            <div class="featured-carousel-container" data-no-scroll="<?= $needsInfiniteScroll ? 'false' : 'true' ?>">
                <?php if ($needsInfiniteScroll): ?>
                <!-- Arrow Navigation Buttons -->
                <button class="carousel-nav carousel-nav-prev" aria-label="Previous videos">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                </button>
                <button class="carousel-nav carousel-nav-next" aria-label="Next videos">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </button>
                <?php endif; ?>
                
                <div class="featured-carousel-wrapper">
                    <div id="<?= $carouselId ?>" class="featured-carousel-track" data-video-count="<?= $videoCount ?>">
                        <?php 
                        // Render each video card
                        foreach ($displayVideos as $iteration => $video):
                            $videoUrl = '/v/' . h($video['short_link']);
                            $thumbnailUrl = h($video['thumbnail']);
                            $title = h($video['title']);
                            $viewCount = number_format($video['view_count'] ?? 0);
                            $creatorHandle = h($video['creator_handle'] ?? 'Unknown');
                            $creatorName = h($video['creator_name'] ?? 'Unknown');
                            $creatorPhoto = h($video['creator_photo'] ?? '/default-avatar.png');
                            $publishedDate = !empty($video['published_at']) ? date('M j, Y', strtotime($video['published_at'])) : 'N/A';
                            
                            // Handle user reaction properly
                            $userReaction = '';
                            if ($is_viewer_logged_in && isset($video['user_reaction'])) {
                                $rawReaction = trim($video['user_reaction']);
                                if (!empty($rawReaction) && in_array(strtolower($rawReaction), ['like', 'dislike'])) {
                                    $userReaction = strtolower($rawReaction);
                                }
                            }
                            
                            // Build preview URL for hover-to-play
                            $previewUrl = '';
                            $useNativeVideo = false;
                            
                            if ($video['source'] === 'local') {
                                if (!empty($video['video_link'])) {
                                    $previewUrl = h($video['video_link']);
                                    $useNativeVideo = true;
                                }
                            } else {
                                $source = strtolower($video['source']);
                                $videoId = $video['video_id'];
                                
                                if ($source === 'youtube' && !empty($videoId)) {
                                    $previewUrl = "https://www.youtube.com/embed/{$videoId}?autoplay=1&mute=1&controls=0&modestbranding=1&rel=0";
                                } elseif ($source === 'vimeo' && !empty($videoId)) {
                                    $previewUrl = "https://player.vimeo.com/video/{$videoId}?autoplay=1&muted=1&controls=0&loop=1";
                                }
                            }
                            ?>
                            <div class="featured-video-card" 
                                 data-preview-url="<?= $previewUrl ?>" 
                                 data-use-native="<?= $useNativeVideo ? '1' : '0' ?>">
                                <a href="<?= $videoUrl ?>" class="featured-video-link">
                                    <div class="featured-video-thumb-container">
                                        <div class="featured-video-thumbnail">
                                            <img src="<?= $thumbnailUrl ?>" alt="<?= $title ?>" loading="lazy">
                                            <div class="featured-badge">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                                                </svg>
                                                Featured
                                            </div>
                                            <div class="featured-play-overlay">
                                                <svg width="64" height="64" viewBox="0 0 24 24" fill="rgba(255,255,255,0.9)">
                                                    <circle cx="12" cy="12" r="10" fill="rgba(0,0,0,0.6)"></circle>
                                                    <polygon points="10 8 16 12 10 16 10 8" fill="white"></polygon>
                                                </svg>
                                            </div>
                                            <div class="preview-iframe-container"></div>
                                        </div>
                                    </div>
                                    
                                    <div class="featured-video-info">
                                        <h3 class="featured-video-title"><?= $title ?></h3>
                                        <div class="featured-video-stats"><?= $viewCount ?> views</div>
                                        <?php if (!empty($creatorHandle)): ?>
                                            <div class="featured-creator">
                                                <img src="<?= $creatorPhoto ?>" alt="<?= $creatorHandle ?>" class="creator-avatar">
                                                <span class="creator-handle"><?= $creatorHandle ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </a>
                                
                                <!-- Reactions and Share (outside the link) -->
                                <div class="featured-share-actions">
                                    <div class="featured-actions-left">
                                        <!-- Like/Dislike Reactions - Independent carousel handlers -->
                                        <div class="carousel-reactions-container" 
                                             data-video-id="<?= $video['id'] ?>" 
                                             data-user-action="<?= $userReaction ?>"
                                             data-likes="<?= $video['likes'] ?? 0 ?>"
                                             data-dislikes="<?= $video['dislikes'] ?? 0 ?>">
                                            <?php if ($is_viewer_logged_in): ?>
                                                <button class="carousel-reaction-btn carousel-like-btn<?= $userReaction === 'like' ? ' active' : '' ?>" 
                                                        data-video-id="<?= $video['id'] ?>"
                                                        data-action="like" 
                                                        title="Like this video">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                        <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path>
                                                    </svg>
                                                    <span class="carousel-like-count"><?= ($video['likes'] ?? 0) > 0 ? number_format($video['likes']) : '' ?></span>
                                                </button>
                                                <button class="carousel-reaction-btn carousel-dislike-btn<?= $userReaction === 'dislike' ? ' active' : '' ?>" 
                                                        data-video-id="<?= $video['id'] ?>"
                                                        data-action="dislike" 
                                                        title="Dislike this video">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                        <path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3zm7-13h2.67A2.31 2.31 0 0 1 22 4v7a2.31 2.31 0 0 1-2.33 2H17"></path>
                                                    </svg>
                                                    <span class="carousel-dislike-count"><?= ($video['dislikes'] ?? 0) > 0 ? number_format($video['dislikes']) : '' ?></span>
                                                </button>
                                            <?php else: ?>
                                                <!-- Guest view - show counts only, link to login -->
                                                <a href="/login" 
                                                   class="carousel-reaction-btn carousel-like-btn" 
                                                   title="Login to like"
                                                   style="padding:6px 10px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:var(--muted);cursor:pointer;display:flex;align-items:center;gap:4px;">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" style="width:18px;height:18px;">
                                                        <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path>
                                                    </svg>
                                                    <span><?= ($video['likes'] ?? 0) > 0 ? number_format($video['likes']) : '' ?></span>
                                                </a>
                                                <a href="/login" 
                                                   class="carousel-reaction-btn carousel-dislike-btn" 
                                                   title="Login to dislike"
                                                   style="padding:6px 10px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:var(--muted);cursor:pointer;display:flex;align-items:center;gap:4px;">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" style="width:18px;height:18px;">
                                                        <path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3zm7-13h2.67A2.31 2.31 0 0 1 22 4v7a2.31 2.31 0 0 1-2.33 2H17"></path>
                                                    </svg>
                                                    <span><?= ($video['dislikes'] ?? 0) > 0 ? number_format($video['dislikes']) : '' ?></span>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Share Button -->
                                        <?php
                                        $shareUrl = 'https://' . $_SERVER['HTTP_HOST'] . $videoUrl;
                                        $shareTitle = $title;
                                        $shareDescription = "Watch {$title} on ION Local";
                                        
                                        if (isset($enhanced_share_manager)) {
                                            echo $enhanced_share_manager->renderShareButton(
                                                $video['id'],
                                                $shareUrl,
                                                $shareTitle,
                                                $shareDescription,
                                                $thumbnailUrl
                                            );
                                        } else {
                                            // Fallback basic share button
                                            echo '<button class="ion-share-button" 
                                                         data-video-id="' . h($video['id']) . '"
                                                         data-url="' . h($shareUrl) . '"
                                                         data-title="' . h($shareTitle) . '"
                                                         style="padding:6px 10px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:var(--muted);cursor:pointer;display:flex;align-items:center;gap:4px;"
                                                         title="Share this video">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" style="width:18px;height:18px;">
                                                        <circle cx="18" cy="5" r="3"></circle>
                                                        <circle cx="6" cy="12" r="3"></circle>
                                                        <circle cx="18" cy="19" r="3"></circle>
                                                        <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                                                        <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                                                    </svg>
                                                    Share
                                                </button>';
                                        }
                                        ?>
                                    </div>
                                    <div class="featured-actions-right">
                                        <span class="featured-video-date"><?= $publishedDate ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>
        
        <style>
        /* ION Featured Videos Carousel Styles */
        .ion-featured-carousel {
            margin: 0 0 3rem 0;
            padding: 1.5rem 0;
            background: linear-gradient(135deg, rgba(178, 130, 84, 0.05) 0%, rgba(178, 130, 84, 0.02) 100%);
            border-top: 2px solid rgba(178, 130, 84, 0.2);
            border-bottom: 2px solid rgba(178, 130, 84, 0.2);
            overflow: hidden;
            margin-top: 20px;
        }
        
        .featured-header {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        
        .featured-header h2 {
            font-size: 2rem;
            font-weight: 700;
            color: #b28254;
            margin: 0;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        /* LIGHT MODE: Adjust header */
        body[data-theme="light"] .featured-header h2 {
            color: #9a6e3a;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
        }
        
        .featured-carousel-container {
            position: relative;
            max-width: 100%;
        }
        
        .featured-carousel-wrapper {
            overflow: hidden;
            padding: 1rem 0;
        }
        
        /* SMOOTH CSS ANIMATION + Manual Control */
        .featured-carousel-track {
            display: flex;
            gap: 1.5rem;
            will-change: transform;
            transition: transform 0.5s cubic-bezier(0.4, 0.0, 0.2, 1);
        }
        
        /* Auto-scroll animation (will be paused when user interacts) */
        .featured-carousel-track.auto-scrolling {
            animation: smoothCarouselScroll 60s linear infinite;
        }
        
        .featured-carousel-track.auto-scrolling:hover {
            animation-play-state: paused;
        }
        
        @keyframes smoothCarouselScroll {
            0% {
                transform: translateX(0);
            }
            100% {
                transform: translateX(-33.333%);
            }
        }
        
        .featured-carousel-container[data-no-scroll="true"] .featured-carousel-track {
            justify-content: center;
        }
        
        .featured-carousel-container[data-no-scroll="true"] .carousel-nav {
            display: none;
        }
        
        /* Navigation Arrows */
        .carousel-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            z-index: 10;
            background: rgba(0, 0, 0, 0.7);
            border: 2px solid rgba(178, 130, 84, 0.5);
            color: #b28254;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            backdrop-filter: blur(8px);
        }
        
        .carousel-nav:hover {
            background: rgba(178, 130, 84, 0.9);
            color: #000;
            border-color: #b28254;
            transform: translateY(-50%) scale(1.1);
            box-shadow: 0 4px 12px rgba(178, 130, 84, 0.4);
        }
        
        body[data-theme="light"] .carousel-nav {
            background: rgba(255, 255, 255, 0.95);
            border-color: rgba(178, 130, 84, 0.4);
            color: #9a6e3a;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        body[data-theme="light"] .carousel-nav:hover {
            background: rgba(178, 130, 84, 0.95);
            color: #fff;
            border-color: #b28254;
            box-shadow: 0 4px 12px rgba(178, 130, 84, 0.3);
        }
        
        .carousel-nav:active {
            transform: translateY(-50%) scale(0.95);
        }
        
        .carousel-nav-prev {
            left: 10px;
        }
        
        .carousel-nav-next {
            right: 10px;
        }
        
        .carousel-nav svg {
            width: 24px;
            height: 24px;
        }
        
        /* Video Cards */
        .featured-video-card {
            flex: 0 0 320px;
            background: var(--panel, #1e293b);
            border-radius: 12px;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            border: 1px solid var(--ring, rgba(255, 255, 255, 0.1));
        }
        
        .featured-video-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 24px rgba(178, 130, 84, 0.3);
        }
        
        body[data-theme="light"] .featured-video-card {
            background: #ffffff;
            border-color: #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
        
        body[data-theme="light"] .featured-video-card:hover {
            box-shadow: 0 8px 20px rgba(178, 130, 84, 0.25);
        }
        
        .featured-video-link {
            text-decoration: none;
            color: inherit;
            display: block;
            flex: 1;
        }
        
        .featured-video-thumb-container {
            position: relative;
            overflow: hidden;
        }
        
        .featured-video-thumbnail {
            position: relative;
            width: 100%;
            padding-top: 56.25%;
            background: #000;
            overflow: hidden;
        }
        
        .featured-video-thumbnail img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .featured-video-card:hover .featured-video-thumbnail img {
            transform: scale(1.1);
        }
        
        .featured-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            color: #000;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 4px;
            box-shadow: 0 2px 8px rgba(251, 191, 36, 0.4);
            z-index: 3;
        }
        
        .featured-play-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            opacity: 1;
            transition: opacity 0.3s ease;
            z-index: 2;
            pointer-events: none;
        }
        
        .featured-video-thumb-container:hover .featured-play-overlay {
            opacity: 0;
        }
        
        .preview-iframe-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
            z-index: 3;
            background: var(--bg, #000);
        }
        
        .preview-iframe-container iframe,
        .preview-iframe-container video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        
        @media (max-width: 768px) {
            .preview-iframe-container {
                display: none !important;
            }
        }
        
        /* Video Info */
        .featured-video-info {
            padding: 1rem;
        }
        
        .featured-video-title {
            font-size: 1rem;
            font-weight: 600;
            margin: 0 0 0.5rem 0;
            color: var(--text, #e2e8f0);
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            min-height: 2.8em;
        }
        
        body[data-theme="light"] .featured-video-title {
            color: #0f172a;
        }
        
        .featured-video-stats {
            font-size: 0.85rem;
            color: var(--muted, #8a94a6);
            margin-bottom: 0.5rem;
        }
        
        body[data-theme="light"] .featured-video-stats {
            color: #64748b;
        }
        
        .featured-creator {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .creator-avatar {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .creator-handle {
            font-size: 0.85rem;
            color: #b28254;
            font-weight: 500;
        }
        
        body[data-theme="light"] .creator-handle {
            color: #9a6e3a;
        }
        
        /* Actions */
        .featured-share-actions {
            padding: 0 12px 12px 12px;
            display: flex;
            gap: 8px;
            justify-content: space-between;
            align-items: center;
        }
        
        .featured-actions-left {
            display: flex;
            gap: 8px;
            align-items: center;
            flex: 1;
        }
        
        .featured-actions-right {
            display: flex;
            align-items: center;
        }
        
        .featured-video-date {
            font-size: 0.75rem;
            color: var(--muted, #8a94a6);
            white-space: nowrap;
        }
        
        body[data-theme="light"] .featured-video-date {
            color: #64748b;
        }
        
        .carousel-reactions-container {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        /* Carousel Reaction Buttons - Independent styling */
        .ion-featured-carousel .carousel-reactions-container {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .ion-featured-carousel .carousel-reaction-btn {
            display: flex;
            align-items: center;
            gap: 4px;
            padding: 6px 10px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: var(--muted, #8a94a6);
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        
        body[data-theme="light"] .ion-featured-carousel .carousel-reaction-btn {
            background: rgba(0, 0, 0, 0.03);
            border-color: rgba(0, 0, 0, 0.1);
            color: #64748b;
        }
        
        .ion-featured-carousel .carousel-reaction-btn svg {
            width: 18px;
            height: 18px;
        }
        
        .ion-featured-carousel .carousel-reaction-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.2);
        }
        
        body[data-theme="light"] .ion-featured-carousel .carousel-reaction-btn:hover {
            background: rgba(0, 0, 0, 0.06);
            border-color: rgba(0, 0, 0, 0.15);
        }
        
        .ion-featured-carousel .carousel-like-btn.active {
            background: rgba(16, 185, 129, 0.15) !important;
            border-color: #10b981 !important;
            color: #10b981 !important;
        }
        
        .ion-featured-carousel .carousel-dislike-btn.active {
            background: rgba(239, 68, 68, 0.15) !important;
            border-color: #ef4444 !important;
            color: #ef4444 !important;
        }
        
        .ion-featured-carousel .carousel-like-btn.active svg {
            stroke: #10b981 !important;
        }
        
        .ion-featured-carousel .carousel-dislike-btn.active svg {
            stroke: #ef4444 !important;
        }
        
        .ion-featured-carousel .carousel-like-btn.active .carousel-like-count {
            color: #10b981 !important;
        }
        
        .ion-featured-carousel .carousel-dislike-btn.active .carousel-dislike-count {
            color: #ef4444 !important;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .featured-header h2 {
                font-size: 1.5rem;
            }
            
            .featured-video-card {
                flex: 0 0 280px;
            }
            
            .featured-carousel-track {
                gap: 1rem;
            }
            
            .carousel-nav {
                width: 40px;
                height: 40px;
            }
            
            .carousel-nav svg {
                width: 20px;
                height: 20px;
            }
            
            .featured-actions-left {
                flex-wrap: wrap;
            }
            
            .featured-video-date {
                font-size: 0.7rem;
            }
        }
        
        @media (max-width: 480px) {
            .ion-featured-carousel {
                margin: 2rem 0;
                padding: 1.5rem 0;
            }
            
            .featured-header h2 {
                font-size: 1.25rem;
            }
            
            .featured-video-card {
                flex: 0 0 240px;
            }
            
            .carousel-nav {
                width: 36px;
                height: 36px;
            }
            
            .carousel-nav-prev {
                left: 5px;
            }
            
            .carousel-nav-next {
                right: 5px;
            }
        }
        </style>
        
        <script>
        // Featured Carousel with BOTH auto-scroll AND manual control
        (function() {
            class FeaturedCarousel {
                constructor(carouselId) {
                    this.carousel = document.getElementById(carouselId);
                    if (!this.carousel) return;
                    
                    this.track = this.carousel;
                    this.container = this.carousel.closest('.featured-carousel-container');
                    this.prevBtn = this.container.querySelector('.carousel-nav-prev');
                    this.nextBtn = this.container.querySelector('.carousel-nav-next');
                    this.cards = Array.from(this.track.querySelectorAll('.featured-video-card'));
                    
                    this.currentIndex = 0;
                    this.cardWidth = 0;
                    this.gap = 24;
                    this.isScrolling = false;
                    this.userInteracted = false;
                    this.inactivityTimeout = null;
                    
                    const videoCount = parseInt(this.track.dataset.videoCount || '0');
                    this.hasEnoughVideos = videoCount >= 4;
                    
                    if (!this.hasEnoughVideos) {
                        this.container.setAttribute('data-no-scroll', 'true');
                        return;
                    }
                    
                    this.init();
                }
                
                init() {
                    this.updateDimensions();
                    this.setupEventListeners();
                    this.startAutoScroll();
                    
                    window.addEventListener('resize', () => this.updateDimensions());
                }
                
                updateDimensions() {
                    if (this.cards.length === 0) return;
                    const cardStyle = window.getComputedStyle(this.cards[0]);
                    this.cardWidth = this.cards[0].offsetWidth;
                    this.gap = parseInt(cardStyle.marginRight) || 24;
                }
                
                setupEventListeners() {
                    if (this.prevBtn) {
                        this.prevBtn.addEventListener('click', () => {
                            this.userInteracted = true;
                            this.stopAutoScroll();
                            this.scrollPrev();
                            this.setupInactivityTimer();
                        });
                    }
                    
                    if (this.nextBtn) {
                        this.nextBtn.addEventListener('click', () => {
                            this.userInteracted = true;
                            this.stopAutoScroll();
                            this.scrollNext();
                            this.setupInactivityTimer();
                        });
                    }
                    
                    // Pause auto-scroll on hover
                    this.carousel.addEventListener('mouseenter', () => {
                        if (!this.userInteracted) {
                            this.track.style.animationPlayState = 'paused';
                        }
                    });
                    
                    this.carousel.addEventListener('mouseleave', () => {
                        if (!this.userInteracted) {
                            this.track.style.animationPlayState = 'running';
                        }
                    });
                }
                
                setupInactivityTimer() {
                    clearTimeout(this.inactivityTimeout);
                    this.inactivityTimeout = setTimeout(() => {
                        this.userInteracted = false;
                        this.startAutoScroll();
                    }, 5000);
                }
                
                scrollPrev() {
                    if (this.isScrolling) return;
                    this.isScrolling = true;
                    
                    this.currentIndex--;
                    if (this.currentIndex < 0) {
                        this.currentIndex = this.cards.length - 1;
                    }
                    
                    this.scrollToIndex(this.currentIndex);
                    
                    setTimeout(() => {
                        this.isScrolling = false;
                    }, 600);
                }
                
                scrollNext() {
                    if (this.isScrolling) return;
                    this.isScrolling = true;
                    
                    this.currentIndex++;
                    if (this.currentIndex >= this.cards.length) {
                        this.currentIndex = 0;
                    }
                    
                    this.scrollToIndex(this.currentIndex);
                    
                    setTimeout(() => {
                        this.isScrolling = false;
                    }, 600);
                }
                
                scrollToIndex(index) {
                    const scrollAmount = -(index * (this.cardWidth + this.gap));
                    this.track.style.transform = `translateX(${scrollAmount}px)`;
                    this.track.style.transition = 'transform 0.5s cubic-bezier(0.4, 0.0, 0.2, 1)';
                }
                
                startAutoScroll() {
                    if (!this.hasEnoughVideos) return;
                    this.track.classList.add('auto-scrolling');
                    this.track.style.animation = 'smoothCarouselScroll 60s linear infinite';
                }
                
                stopAutoScroll() {
                    this.track.classList.remove('auto-scrolling');
                    this.track.style.animation = 'none';
                }
            }
            
            // Initialize carousel
            const carouselTracks = document.querySelectorAll('.featured-carousel-track');
            carouselTracks.forEach(track => {
                new FeaturedCarousel(track.id);
            });
        })();
        
        // INDEPENDENT Carousel Button Handler
        (function() {
            const API_ENDPOINT = '/api/video-reactions.php';
            
            function handleCarouselReaction(videoId, action, button) {
                const container = button.closest('.carousel-reactions-container');
                if (!container) return;
                
                fetch(API_ENDPOINT, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `video_id=${videoId}&action=${action}`,
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update ALL carousel instances of this video
                        document.querySelectorAll(`.carousel-reactions-container[data-video-id="${videoId}"]`).forEach(c => {
                            const likeBtn = c.querySelector('.carousel-like-btn');
                            const dislikeBtn = c.querySelector('.carousel-dislike-btn');
                            const likeCount = c.querySelector('.carousel-like-count');
                            const dislikeCount = c.querySelector('.carousel-dislike-count');
                            
                            if (likeBtn) {
                                likeBtn.classList.toggle('active', data.user_action === 'like');
                            }
                            if (dislikeBtn) {
                                dislikeBtn.classList.toggle('active', data.user_action === 'dislike');
                            }
                            if (likeCount) {
                                likeCount.textContent = data.likes > 0 ? data.likes.toLocaleString() : '';
                            }
                            if (dislikeCount) {
                                dislikeCount.textContent = data.dislikes > 0 ? data.dislikes.toLocaleString() : '';
                            }
                        });
                    } else if (data.error) {
                        console.error('Carousel reaction error:', data.error);
                    }
                })
                .catch(err => {
                    console.error('Carousel reaction request failed:', err);
                });
            }
            
            // Attach handlers to carousel buttons ONLY
            document.querySelectorAll('.ion-featured-carousel .carousel-reaction-btn[data-action]').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const videoId = this.getAttribute('data-video-id');
                    const action = this.getAttribute('data-action');
                    
                    if (videoId && action) {
                        handleCarouselReaction(videoId, action, this);
                    }
                });
            });
        })();
        
        // Hover-to-play video previews
        (function() {
            function initFeaturedVideoPreviews() {
                const cards = document.querySelectorAll('.ion-featured-carousel .featured-video-card');
                
                cards.forEach(container => {
                    const previewUrl = container.dataset.previewUrl;
                    const useNative = container.dataset.useNative === '1';
                    
                    if (!previewUrl) return;
                    
                    let hoverTimeout;
                    
                    container.addEventListener('mouseenter', function() {
                        const iframeContainer = this.querySelector('.preview-iframe-container');
                        
                        hoverTimeout = setTimeout(() => {
                            if (useNative) {
                                const video = document.createElement('video');
                                video.src = previewUrl;
                                video.autoplay = true;
                                video.muted = true;
                                video.loop = true;
                                video.playsInline = true;
                                video.style.width = '100%';
                                video.style.height = '100%';
                                video.style.objectFit = 'cover';
                                
                                iframeContainer.innerHTML = '';
                                iframeContainer.appendChild(video);
                                iframeContainer.style.opacity = '1';
                            } else {
                                const iframe = document.createElement('iframe');
                                iframe.src = previewUrl;
                                iframe.frameBorder = '0';
                                iframe.allow = 'autoplay; encrypted-media';
                                iframe.style.width = '100%';
                                iframe.style.height = '100%';
                                
                                iframeContainer.innerHTML = '';
                                iframeContainer.appendChild(iframe);
                                iframeContainer.style.opacity = '1';
                            }
                        }, 800);
                    });
                    
                    container.addEventListener('mouseleave', function() {
                        clearTimeout(hoverTimeout);
                        const iframeContainer = this.querySelector('.preview-iframe-container');
                        iframeContainer.style.opacity = '0';
                        
                        setTimeout(() => {
                            iframeContainer.innerHTML = '';
                        }, 300);
                    });
                });
            }
            
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initFeaturedVideoPreviews);
            } else {
                initFeaturedVideoPreviews();
            }
        })();
        
        // Share buttons initialization
        (function() {
            function initFeaturedShareButtons() {
                const shareButtons = document.querySelectorAll('.ion-featured-carousel .ion-share-trigger, .ion-featured-carousel [data-video-id][class*="share"]');
                
                if (shareButtons.length === 0) {
                    return false;
                }
                
                if (typeof window.IONShareManager !== 'undefined') {
                    if (typeof window.IONShareManager.init === 'function') {
                        window.IONShareManager.init();
                    } else if (typeof window.IONShareManager.initializeButtons === 'function') {
                        window.IONShareManager.initializeButtons();
                    } else if (typeof window.IONShareManager.reinit === 'function') {
                        window.IONShareManager.reinit();
                    }
                    
                    shareButtons.forEach(button => {
                        button.dataset.shareInitialized = 'true';
                    });
                    
                    return true;
                }
                
                if (typeof window.initIONShare === 'function') {
                    window.initIONShare();
                    return true;
                }
                
                const event = new CustomEvent('ion-share-reinit', {
                    detail: { container: '.ion-featured-carousel' }
                });
                document.dispatchEvent(event);
                
                return false;
            }
            
            const shareDelays = [100, 500, 1000, 2000];
            let initialized = false;
            
            shareDelays.forEach(delay => {
                setTimeout(() => {
                    if (!initialized) {
                        initialized = initFeaturedShareButtons();
                    }
                }, delay);
            });
            
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(() => {
                        shareDelays.forEach(delay => {
                            setTimeout(() => {
                                if (!initialized) {
                                    initialized = initFeaturedShareButtons();
                                }
                            }, delay);
                        });
                    }, 500);
                });
            }
        })();
        </script>
        <?php
        
    } catch (Exception $e) {
        error_log('Featured Videos Carousel Error: ' . $e->getMessage());
        return;
    }
}