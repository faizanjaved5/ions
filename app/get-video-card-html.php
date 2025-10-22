<?php
/**
 * Get Video Card HTML
 * Returns the HTML for a single video card to be injected into the page
 */

// Disable error display, enable logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Start output buffering
ob_start();

header('Content-Type: application/json');

try {
    // Load dependencies
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../login/session.php';
    require_once __DIR__ . '/../includes/render-video-badges.php';
    
    // Clear any buffered output
    ob_clean();

    // Security check
    if (!isset($_SESSION['user_email']) || empty($_SESSION['user_email'])) {
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit();
    }

    // Get video ID
    $video_id = $_GET['video_id'] ?? '';
    
    if (empty($video_id)) {
        echo json_encode(['success' => false, 'error' => 'Video ID required']);
        exit();
    }

    // Fetch video data
    $video = $db->get_row(
        "SELECT * FROM IONLocalVideos WHERE id = ?",
        intval($video_id)
    );

    if (!$video) {
        echo json_encode(['success' => false, 'error' => 'Video not found']);
        exit();
    }

    // Get current user data for creator handle display
    $user_email = $_SESSION['user_email'];
    $current_user = $db->get_row(
        "SELECT user_id, user_role, username FROM IONEERS WHERE email = ?",
        $user_email
    );

    // Get video creator info
    $creator = $db->get_row(
        "SELECT username, handle FROM IONEERS WHERE user_id = ?",
        $video->user_id
    );

    // Determine video info
    $video_type = strtolower($video->source ?? 'youtube');
    $video_id_external = $video->video_id ?? '';
    $thumbnail = $video->thumbnail ?? 'https://ions.com/assets/default/processing.png';
    $video_url = $video->video_link ?? '';
    
    // Generate preview URL for hover
    $preview_url = '';
    if ($video_type === 'youtube' && $video_id_external) {
        $preview_url = 'https://www.youtube.com/embed/' . htmlspecialchars($video_id_external, ENT_QUOTES, 'UTF-8') . '?autoplay=1&mute=1&controls=0&loop=1&playlist=' . htmlspecialchars($video_id_external, ENT_QUOTES, 'UTF-8');
    } elseif ($video_type === 'vimeo' && $video_id_external) {
        $preview_url = 'https://player.vimeo.com/video/' . htmlspecialchars($video_id_external, ENT_QUOTES, 'UTF-8') . '?autoplay=1&muted=1&background=1';
    } elseif ($video_type === 'wistia' && $video_id_external) {
        $preview_url = 'https://fast.wistia.net/embed/iframe/' . htmlspecialchars($video_id_external, ENT_QUOTES, 'UTF-8') . '?autoplay=1&muted=1&controls=0';
    } elseif ($video_type === 'rumble' && $video_id_external) {
        $preview_url = 'https://rumble.com/embed/v' . htmlspecialchars($video_id_external, ENT_QUOTES, 'UTF-8') . '/?autoplay=1&muted=1';
    } elseif ($video_type === 'muvi' && $video_id_external) {
        $preview_url = 'https://embed.muvi.com/embed/' . htmlspecialchars($video_id_external, ENT_QUOTES, 'UTF-8') . '?autoplay=1&muted=1';
    } elseif ($video_type === 'loom' && $video_id_external) {
        $preview_url = 'https://www.loom.com/embed/' . htmlspecialchars($video_id_external, ENT_QUOTES, 'UTF-8') . '?autoplay=1&muted=1&controls=0';
    } elseif (($video_type === 'local' || $video_type === 'upload') && !empty($video_url)) {
        $preview_url = $video_url; // MP4 URL
    }

    // Start building HTML
    ob_start();
    ?>
    <div class="carousel-item new-video-highlight" data-video-id="<?= $video->id ?>">
        <a href="#" class="video-thumb" onclick="return openVideoInModal(event, this)"
           data-video-id="<?= htmlspecialchars($video_id_external, ENT_QUOTES, 'UTF-8') ?>" 
           data-video-type="<?= htmlspecialchars($video_type, ENT_QUOTES, 'UTF-8') ?>"
           data-video-url="<?= htmlspecialchars($video->video_link, ENT_QUOTES, 'UTF-8') ?>"
           data-video-title="<?= htmlspecialchars($video->title, ENT_QUOTES, 'UTF-8') ?>"
           <?= ($video_type === 'local' || $video_type === 'upload') ? 'data-video-format="' . htmlspecialchars(pathinfo($video->video_link, PATHINFO_EXTENSION), ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
            <img class="video-thumbnail" src="<?= htmlspecialchars($thumbnail, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($video->title, ENT_QUOTES, 'UTF-8') ?>" onerror="this.onerror=null; this.src='https://ions.com/assets/default/processing.png';">
            <div class="play-icon-overlay">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="rgba(255,255,255,0.8)">
                    <path d="M8 5v14l11-7z"></path>
                </svg>
            </div>
            <?php if (!empty($preview_url)): ?>
            <div class="preview-iframe-container" data-preview-url="<?= htmlspecialchars($preview_url, ENT_QUOTES, 'UTF-8') ?>" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; pointer-events: none; transition: opacity 0.3s ease;"></div>
            <?php endif; ?>
        </a>
        <div class="video-card-info">
            <h4 class="video-card-title" style="margin: 0.5rem 0 0.25rem;">
                <a href="/v/<?= htmlspecialchars($video->slug ?? '', ENT_QUOTES, 'UTF-8') ?>" style="color: inherit; text-decoration: none;">
                    <?= htmlspecialchars($video->title) ?>
                </a>
            </h4>
            
            <?php if (!empty($video->description)): ?>
                <small style="color: #a4b3d0; display: block; margin-top: 0.35rem; line-height: 1.3;">
                    <?= htmlspecialchars(substr($video->description, 0, 80)) ?><?= strlen($video->description) > 80 ? '...' : '' ?>
                </small>
            <?php endif; ?>
            
            <?= renderVideoBadges($video->id) ?>
            
            <div class="creator-handle-container" style="display: flex; align-items: center; justify-content: space-between; min-height: 24px;">
                <small style="color: #a4b3d0; margin-top: 0.35rem;">
                    @<?= htmlspecialchars($creator->handle ?? $creator->username ?? 'unknown', ENT_QUOTES, 'UTF-8') ?>
                </small>
                
                <div class="video-reactions" data-video-id="<?= $video->id ?>" data-user-action="<?= htmlspecialchars($video->user_reaction ?? '', ENT_QUOTES, 'UTF-8') ?>" style="display: flex; align-items: center; gap: 8px; flex-shrink: 0;">
                    <button class="reaction-btn like-btn <?= ($video->user_reaction ?? '') === 'like' ? 'active' : '' ?>" 
                            onclick="handleReaction(<?= $video->id ?>, 'like')"
                            title="Like this video">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path>
                        </svg>
                        <span class="like-count"><?= $video->likes ?? 0 ?></span>
                    </button>
                    <button class="reaction-btn dislike-btn <?= ($video->user_reaction ?? '') === 'dislike' ? 'active' : '' ?>" 
                            onclick="handleReaction(<?= $video->id ?>, 'dislike')"
                            title="Dislike this video">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3zm7-13h2.67A2.31 2.31 0 0 1 22 4v7a2.31 2.31 0 0 1-2.33 2H17"></path>
                        </svg>
                        <span class="dislike-count"><?= $video->dislikes ?? 0 ?></span>
                    </button>
                </div>
            </div>
            
            <div class="video-meta" style="margin-top: 0.5rem;">
                <small class="video-views"><?= number_format($video->views ?? 0) ?> views</small>
                <small class="video-date"><?= date('M d, Y', strtotime($video->date_added)) ?></small>
            </div>
        </div>
        
        <div class="video-card-actions">
            <button class="action-btn" onclick="shareVideo(<?= $video->id ?>)" title="Share">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="18" cy="5" r="3"></circle>
                    <circle cx="6" cy="12" r="3"></circle>
                    <circle cx="18" cy="19" r="3"></circle>
                    <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                    <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                </svg>
            </button>
            <button class="action-btn" onclick="editVideo(<?= $video->id ?>)" title="Edit">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                </svg>
            </button>
            <button class="action-btn delete-btn" onclick="deleteVideo(<?= $video->id ?>)" title="Delete">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                </svg>
            </button>
        </div>
    </div>
    <?php
    
    $html = ob_get_clean();
    
    echo json_encode([
        'success' => true,
        'html' => $html,
        'video_id' => $video->id
    ]);
    
} catch (Exception $e) {
    error_log('Get video card HTML error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to generate video card HTML'
    ]);
}

