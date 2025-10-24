<?php
/**
 * ION Featured Videos Carousel Component
 * 
 * Displays a horizontal auto-scrolling carousel of featured videos
 * Filters by channel (for city pages) or user (for profile pages) 
 * Only shows videos with "Featured" badge
 * 
 * Usage:
 *   include 'includes/featured-videos-carousel.php';
 *   renderFeaturedVideosCarousel($pdo, 'channel', $channelSlug);
 *   renderFeaturedVideosCarousel($pdo, 'profile', $userId);
 */

/**
 * Render featured videos carousel
 * 
 * @param PDO $pdo Database connection
 * @param string $filterType 'channel' or 'profile'
 * @param mixed $filterValue Channel slug or User ID
 */
function renderFeaturedVideosCarousel($pdo, $filterType, $filterValue) {
    try {
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
                    u.handle as creator_handle,
                    u.fullname as creator_name,
                    u.photo_url as creator_photo
                FROM IONLocalVideos v
                LEFT JOIN IONEERS u ON v.user_id = u.user_id
                INNER JOIN IONVideoBadges vb ON v.id = vb.video_id
                INNER JOIN IONBadges b ON vb.badge_id = b.id
                LEFT JOIN IONLocalBlast lb ON v.id = lb.video_id
                LEFT JOIN IONLocalNetwork ln ON lb.channel_slug = ln.slug
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
                    u.handle as creator_handle,
                    u.fullname as creator_name,
                    u.photo_url as creator_photo
                FROM IONLocalVideos v
                LEFT JOIN IONEERS u ON v.user_id = u.user_id
                INNER JOIN IONVideoBadges vb ON v.id = vb.video_id
                INNER JOIN IONBadges b ON vb.badge_id = b.id
                WHERE v.status = 'Approved'
                  AND v.visibility = 'Public'
                  AND v.user_id = :filter_value
                  AND LOWER(b.name) = 'featured'
                ORDER BY v.published_at DESC
                LIMIT 20
            ";
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':filter_value' => $filterValue]);
        $featuredVideos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Debug logging
        error_log("Featured Videos: Filter type={$filterType}, Value={$filterValue}, Count=" . count($featuredVideos));
        
        // Don't render anything if no featured videos found
        if (empty($featuredVideos)) {
            error_log("Featured Videos: No videos found for {$filterType}={$filterValue}");
            return;
        }
        
        error_log("Featured Videos: Rendering " . count($featuredVideos) . " videos");
        
        ?>
        <section class="ion-featured-carousel">
            <div class="featured-header">
                <h2>🌟 ION Featured Videos</h2>
            </div>
            
            <div class="featured-carousel-wrapper">
                <div class="featured-carousel-track">
                    <?php foreach ($featuredVideos as $video): 
                        $videoUrl = !empty($video['short_link']) 
                            ? '/v/' . htmlspecialchars($video['short_link']) 
                            : '/watch/' . htmlspecialchars($video['slug']);
                        $thumbnail = htmlspecialchars($video['thumbnail'] ?: '/assets/default/processing.png');
                        $title = htmlspecialchars($video['title']);
                        $viewCount = number_format((int)$video['view_count']);
                        $creatorHandle = $video['creator_handle'] ? '@' . htmlspecialchars($video['creator_handle']) : '';
                        $creatorPhoto = htmlspecialchars($video['creator_photo'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($video['creator_name'] ?: 'User'));
                    ?>
                        <div class="featured-video-card">
                            <a href="<?= $videoUrl ?>" class="featured-video-link">
                                <div class="featured-video-thumbnail">
                                    <img src="<?= $thumbnail ?>" alt="<?= $title ?>" loading="lazy">
                                    <div class="featured-badge">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                        </svg>
                                        Featured
                                    </div>
                                    <div class="play-overlay">
                                        <svg width="48" height="48" viewBox="0 0 24 24" fill="white">
                                            <path d="M8 5v14l11-7z"/>
                                        </svg>
                                    </div>
                                    <div class="view-count"><?= $viewCount ?> views</div>
                                </div>
                                <div class="featured-video-info">
                                    <h3 class="featured-video-title"><?= $title ?></h3>
                                    <?php if ($creatorHandle): ?>
                                        <div class="featured-creator">
                                            <img src="<?= $creatorPhoto ?>" alt="<?= $creatorHandle ?>" class="creator-avatar">
                                            <span class="creator-handle"><?= $creatorHandle ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- Duplicate items for seamless loop -->
                    <?php foreach ($featuredVideos as $video): 
                        $videoUrl = !empty($video['short_link']) 
                            ? '/v/' . htmlspecialchars($video['short_link']) 
                            : '/watch/' . htmlspecialchars($video['slug']);
                        $thumbnail = htmlspecialchars($video['thumbnail'] ?: '/assets/default/processing.png');
                        $title = htmlspecialchars($video['title']);
                        $viewCount = number_format((int)$video['view_count']);
                        $creatorHandle = $video['creator_handle'] ? '@' . htmlspecialchars($video['creator_handle']) : '';
                        $creatorPhoto = htmlspecialchars($video['creator_photo'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($video['creator_name'] ?: 'User'));
                    ?>
                        <div class="featured-video-card">
                            <a href="<?= $videoUrl ?>" class="featured-video-link">
                                <div class="featured-video-thumbnail">
                                    <img src="<?= $thumbnail ?>" alt="<?= $title ?>" loading="lazy">
                                    <div class="featured-badge">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                        </svg>
                                        Featured
                                    </div>
                                    <div class="play-overlay">
                                        <svg width="48" height="48" viewBox="0 0 24 24" fill="white">
                                            <path d="M8 5v14l11-7z"/>
                                        </svg>
                                    </div>
                                    <div class="view-count"><?= $viewCount ?> views</div>
                                </div>
                                <div class="featured-video-info">
                                    <h3 class="featured-video-title"><?= $title ?></h3>
                                    <?php if ($creatorHandle): ?>
                                        <div class="featured-creator">
                                            <img src="<?= $creatorPhoto ?>" alt="<?= $creatorHandle ?>" class="creator-avatar">
                                            <span class="creator-handle"><?= $creatorHandle ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        
        <style>
        .ion-featured-carousel {
            margin: 0 0 3rem 0;
            padding: 1.5rem 0;
            background: linear-gradient(135deg, rgba(178, 130, 84, 0.05) 0%, rgba(178, 130, 84, 0.02) 100%);
            border-top: 2px solid rgba(178, 130, 84, 0.2);
            border-bottom: 2px solid rgba(178, 130, 84, 0.2);
            overflow: hidden;
        }
        
        .featured-header {
            text-align: center;
            margin-bottom: 1.5rem;
            padding: 0 2rem;
        }
        
        .featured-header h2 {
            font-size: 2rem;
            margin: 0;
            color: var(--primary-gold, #b28254);
            font-weight: 700;
        }
        
        .featured-subtitle {
            color: var(--text-muted, #94a3b8);
            font-size: 0.95rem;
            margin: 0;
        }
        
        .featured-carousel-wrapper {
            position: relative;
            width: 100%;
            overflow: hidden;
            padding: 1rem 0;
        }
        
        .featured-carousel-track {
            display: flex;
            gap: 1.5rem;
            animation: scroll-carousel 60s linear infinite;
            will-change: transform;
        }
        
        .featured-carousel-track:hover {
            animation-play-state: paused;
        }
        
        @keyframes scroll-carousel {
            0% {
                transform: translateX(0);
            }
            100% {
                transform: translateX(-50%);
            }
        }
        
        .featured-video-card {
            flex: 0 0 320px;
            background: var(--card-bg, #1e293b);
            border-radius: 12px;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .featured-video-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 24px rgba(178, 130, 84, 0.3);
        }
        
        .featured-video-link {
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .featured-video-thumbnail {
            position: relative;
            width: 100%;
            padding-top: 56.25%; /* 16:9 aspect ratio */
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
            z-index: 2;
        }
        
        .play-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 1;
            pointer-events: none;
        }
        
        .featured-video-card:hover .play-overlay {
            opacity: 1;
        }
        
        .view-count {
            position: absolute;
            bottom: 12px;
            left: 12px;
            background: rgba(0, 0, 0, 0.8);
            color: #fff;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            z-index: 2;
        }
        
        .featured-video-info {
            padding: 1rem;
        }
        
        .featured-video-title {
            font-size: 1rem;
            font-weight: 600;
            margin: 0 0 0.75rem 0;
            color: var(--text-primary, #e2e8f0);
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            min-height: 2.8em;
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
            color: var(--primary-gold, #b28254);
            font-weight: 500;
        }
        
        /* Responsive adjustments */
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
        }
        </style>
        
        <script>
        // Optional: Add pause on visibility change to save resources
        document.addEventListener('DOMContentLoaded', function() {
            const carousel = document.querySelector('.featured-carousel-track');
            if (!carousel) return;
            
            // Pause animation when page is hidden
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    carousel.style.animationPlayState = 'paused';
                } else {
                    carousel.style.animationPlayState = 'running';
                }
            });
        });
        </script>
        <?php
        
    } catch (Exception $e) {
        error_log('Featured Videos Carousel Error: ' . $e->getMessage());
        // Silently fail - don't show error to users
        return;
    }
}
