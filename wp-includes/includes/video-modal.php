<?php
/**
 * Reusable Video Player Modal Component
 * Extracted from city/ioncity.php for use in admin pages
 * Supports: YouTube, Vimeo, Muvi, Rumble, and local videos with Video.js
 * Now integrates with embeddable video-modal.js for dynamic rendering
 */

// Determine if we need session check (for admin pages)
$require_auth = isset($require_auth) ? $require_auth : false;

if ($require_auth) {
    session_start();
    if (!isset($_SESSION['user_email']) || empty($_SESSION['user_email'])) {
        return; // Don't render if not authenticated
    }
}

// Include getVideoType function
// Note: getvideotype.php not needed for this simplified modal

?>

<!-- Video.js CDN (only load if not already loaded) -->
<script>
if (!window.videojs) {
    document.head.insertAdjacentHTML('beforeend', '<link href="https://vjs.zencdn.net/8.6.1/video-js.css" rel="stylesheet">');
    document.head.insertAdjacentHTML('beforeend', '<script src="https://vjs.zencdn.net/8.6.1/video.min.js"><\/script>');
}
</script>

<!-- Video Modal HTML -->
<div id="videoModal" class="video-modal">
    <div class="video-modal-content">
        <span class="video-close">&times;</span>
        <div id="videoContainer"></div>
    </div>
</div>

<!-- Video Modal CSS -->
<style>
/* Video Modal Styles */
.video-modal {
    display: none;
    position: fixed;
    z-index: 10000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(10px);
    opacity: 0;
    transition: opacity 0.3s ease, visibility 0.3s ease;
    visibility: hidden;
}

.video-modal.is-visible {
    display: flex !important;
    align-items: center;
    justify-content: center;
    opacity: 1;
    visibility: visible;
}

.video-modal-content {
    position: relative;
    width: 90%;
    max-width: 1200px;
    height: 80%;
    max-height: 800px;
    background: rgba(20, 20, 20, 0.95);
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 10px 50px rgba(0, 0, 0, 0.5);
    border: 1px solid rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(20px);
}

.video-close {
    position: absolute;
    top: 15px;
    right: 25px;
    color: #fff;
    font-size: 35px;
    font-weight: bold;
    cursor: pointer;
    z-index: 10001;
    transition: color 0.2s ease;
    background: rgba(0, 0, 0, 0.5);
    border-radius: 50%;
    width: 45px;
    height: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
}

.video-close:hover {
    color: #ff4444;
    background: rgba(0, 0, 0, 0.8);
}

#videoContainer {
    width: 100%;
    height: 100%;
    border-radius: 12px;
    overflow: hidden;
}

#videoContainer iframe {
    width: 100%;
    height: 100%;
    border: none;
    border-radius: 12px;
}

/* Video.js customizations */
.video-modal-content .video-js {
    width: 100%;
    height: 100%;
    border-radius: 12px;
}

.video-js {
    width: 100%;
    height: 100%;
}

.vjs-big-play-button {
    font-size: 3em;
    line-height: 1.5em;
    height: 1.5em;
    width: 3em;
    display: block;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    padding: 0;
    cursor: pointer;
    opacity: 1;
    border: 0.06666em solid #fff;
    background-color: rgba(43, 51, 63, 0.7);
    border-radius: 0.3em;
    transition: all 0.4s;
}

.vjs-big-play-button:hover {
    background-color: rgba(43, 51, 63, 0.9);
}

.video-js .vjs-control-bar {
    display: flex;
    width: 100%;
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 3em;
    background-color: rgba(43, 51, 63, 0.7);
}

.video-js:hover .vjs-control-bar {
    background-color: rgba(43, 51, 63, 0.9);
}

/* Prevent body scroll when modal is open */
body.modal-open {
    overflow: hidden;
}

/* Responsive design */
@media (max-width: 768px) {
    .video-modal-content {
        width: 95%;
        height: 90%;
        padding: 15px;
        border-radius: 12px;
    }
    
    .video-modal-content .video-js,
    #videoContainer iframe {
        border-radius: 8px;
    }
    
    .video-close {
        top: 10px;
        right: 15px;
        font-size: 28px;
        width: 35px;
        height: 35px;
    }
}

@media (max-width: 480px) {
    .video-modal-content {
        width: 98%;
        height: 95%;
        padding: 10px;
    }
}
</style>

<!-- Video Modal JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById("videoModal");
    const videoContainer = document.getElementById("videoContainer");
    const closeBtn = document.querySelector(".video-close");
    let currentModalPlayer = null;

    function openVideoModal(videoType, videoId, videoUrl, videoFormat) {
        console.log('🎬 WORKING MODAL: Opening video modal:', { videoType, videoId, videoUrl, videoFormat });
        
        // Clear previous content
        videoContainer.innerHTML = '';
        
        // Handle streaming platforms
        const iframe = document.createElement('iframe');
        iframe.setAttribute('width', '100%');
        iframe.setAttribute('height', '100%');
        iframe.setAttribute('frameborder', '0');
        iframe.setAttribute('allowfullscreen', '');
        
        let videoSrc = '';
        switch (videoType) {
            case "youtube":
                videoSrc = `https://www.youtube.com/embed/${videoId}?autoplay=1&rel=0&modestbranding=1&mute=0`;
                iframe.setAttribute('allow', 'autoplay; fullscreen; picture-in-picture; encrypted-media');
                break;
            case "vimeo":
                const vimeoId = videoUrl.split('/').pop();
                videoSrc = `https://player.vimeo.com/video/${vimeoId}?autoplay=1&title=0&byline=0&portrait=0&muted=0`;
                iframe.setAttribute('allow', 'autoplay; fullscreen; picture-in-picture');
                break;
            case "muvi":
                videoSrc = `https://embed.muvi.com/embed/${videoId}?autoplay=1`;
                iframe.setAttribute('allow', 'autoplay; fullscreen; picture-in-picture; encrypted-media');
                break;
            default:
                videoSrc = videoUrl;
                break;
        }
        
        iframe.src = videoSrc;
        videoContainer.appendChild(iframe);
        
        // Show modal with animation
        modal.classList.add('is-visible');
        document.body.classList.add('modal-open');
    }

    function closeVideoModal() {
        modal.classList.remove('is-visible');
        document.body.classList.remove('modal-open');
        
        // Clean up Video.js player if it exists
        if (currentModalPlayer) {
            currentModalPlayer.dispose();
            currentModalPlayer = null;
        }
        
        // Clear container
        videoContainer.innerHTML = '';
    }

    // Make functions globally available
    window.openVideoModal = openVideoModal;
    window.closeVideoModal = closeVideoModal;
    
    console.log('✅ WORKING MODAL: Functions exposed globally');

    // Modal event listeners
    if (closeBtn) {
        closeBtn.addEventListener('click', closeVideoModal);
    }
    
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeVideoModal();
            }
        });
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal && modal.classList.contains('is-visible')) {
            closeVideoModal();
        }
    });
});
</script>