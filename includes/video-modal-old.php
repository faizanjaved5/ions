<?php
/**
 * Reusable Video Player Modal Component
 * Extracted from city/ioncity.php for use in admin pages
 * Supports: YouTube, Vimeo, Muvi, Rumble, and local videos with Video.js
 */

// Determine if we need session check (for admin pages)
$require_auth = isset($require_auth) ? $require_auth : false;

if ($require_auth) {
    session_start();
    if (!isset($_SESSION['user_email']) || empty($_SESSION['user_email'])) {
        return; // Don't render modal if not authenticated
    }
}

// Enhanced function to detect video type
function getVideoType($url) {
    $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
    $local_video_extensions = ['mp4', 'webm', 'ogg', 'mov', 'avi', 'm4v', 'mkv'];
    
    // Check platform-specific URLs first
    if (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false) {
        return ['type' => 'youtube'];
    } elseif (strpos($url, 'vimeo.com') !== false) {
        return ['type' => 'vimeo'];
    } elseif (strpos($url, 'muvi.com') !== false) {
        return ['type' => 'muvi'];
    } elseif (strpos($url, 'wistia.com') !== false || strpos($url, 'wi.st') !== false) {
        return ['type' => 'wistia'];
    } elseif (strpos($url, 'rumble.com') !== false) {
        return ['type' => 'rumble'];
    }
    
    // Check if it's a video file by extension (for any URL)
    if (in_array($ext, $local_video_extensions)) {
        return ['type' => 'local', 'format' => $ext];
    }
    
    // Check if it's from known video hosting sources (and likely has video extension in query params)
    if (strpos($url, '/uploads/') !== false || 
        strpos($url, 'ions.com') !== false || 
        strpos($url, 'r2.cloudflarestorage.com') !== false || 
        strpos($url, '.r2.dev') !== false ||
        strpos($url, 'vid.ions.com') !== false ||
        strpos($url, 'cloudflare') !== false) {
        
        // For URLs without clear extensions, check if it looks like a video file
        // Extract filename from URL (handle query parameters)
        $parsedUrl = parse_url($url);
        $path = $parsedUrl['path'] ?? '';
        $filename = basename($path);
        
        // Check if filename has video extension
        $fileExt = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (in_array($fileExt, $local_video_extensions)) {
            return ['type' => 'local', 'format' => $fileExt];
        }
        
        // Fallback: if it's from a known video source, assume it's mp4
        return ['type' => 'local', 'format' => 'mp4'];
    }
    
    return ['type' => 'unknown'];
}
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
    // Video Modal Functions
    const modal = document.getElementById("videoModal");
    const videoContainer = document.getElementById("videoContainer");
    const closeBtn = document.querySelector(".video-close");
    let currentModalPlayer = null;

    function openVideoModal(videoType, videoId, videoUrl, videoFormat) {
        // Clear previous content
        videoContainer.innerHTML = '';
        
        // Normalize video type - treat 'self-hosted' as 'local'
        const normalizedType = (videoType === 'self-hosted' || videoType === 'local') ? 'local' : videoType;
        
        if (normalizedType === 'local') {
            // Create Video.js player for local videos
            const videoElement = document.createElement('video');
            videoElement.className = 'video-js vjs-default-skin vjs-big-play-centered';
            videoElement.setAttribute('controls', '');
            videoElement.setAttribute('preload', 'auto');
            videoElement.style.width = '100%';
            videoElement.style.height = '100%';
            
            const sourceElement = document.createElement('source');
            sourceElement.src = videoUrl;
            sourceElement.type = `video/${videoFormat || 'mp4'}`;
            
            videoElement.appendChild(sourceElement);
            videoContainer.appendChild(videoElement);
            
            // Wait for Video.js to be available
            const initializeVideoJS = () => {
                if (window.videojs) {
                    // Initialize Video.js without autoplay to have full control
                    currentModalPlayer = videojs(videoElement, {
                        controls: true,
                        autoplay: false,
                        preload: 'auto',
                        fluid: true,
                        responsive: true,
                        muted: false
                    });
                    
                    // Attempt autoplay with audio after player is ready and modal is visible
                    currentModalPlayer.ready(() => {
                        console.log('Video.js player ready, attempting autoplay with audio');
                        
                        // Small delay to ensure modal is fully visible
                        setTimeout(() => {
                            // First try to play with audio
                            const playPromise = currentModalPlayer.play();
                            
                            if (playPromise !== undefined) {
                                playPromise.then(() => {
                                    console.log('Autoplay with audio successful');
                                }).catch(error => {
                                    console.log('Autoplay with audio failed, trying muted:', error);
                                    // If audio autoplay fails, try muted autoplay
                                    currentModalPlayer.muted(true);
                                    currentModalPlayer.play().then(() => {
                                        console.log('Autoplay muted successful');
                                    }).catch(err => {
                                        console.log('All autoplay attempts failed:', err);
                                    });
                                });
                            }
                        }, 500); // 500ms delay
                    });
                } else {
                    setTimeout(initializeVideoJS, 100);
                }
            };
            initializeVideoJS();
            
        } else {
            // Handle streaming platforms
            const iframe = document.createElement('iframe');
            iframe.setAttribute('width', '100%');
            iframe.setAttribute('height', '100%');
            iframe.setAttribute('frameborder', '0');
            iframe.setAttribute('allowfullscreen', '');
            
            let videoSrc = '';
            switch (normalizedType) {
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
                case "wistia":
                    videoSrc = `https://fast.wistia.net/embed/iframe/${videoId}?autoplay=1&controls=1&muted=0`;
                    iframe.setAttribute('allow', 'autoplay; fullscreen; picture-in-picture; encrypted-media');
                    break;
                case "rumble":
                    videoSrc = `https://rumble.com/embed/v${videoId}/?autoplay=1`;
                    iframe.setAttribute('allow', 'autoplay; fullscreen; picture-in-picture; encrypted-media');
                    break;
                default:
                    // For unknown types, try to handle as local video if it looks like a video file
                    const videoExtensions = ['mp4', 'webm', 'ogg', 'mov', 'avi', 'm4v', 'mkv'];
                    const urlExt = videoUrl.split('.').pop().toLowerCase().split('?')[0];
                    
                    if (videoExtensions.includes(urlExt)) {
                        // Handle as local video with Video.js
                        const videoElement = document.createElement('video');
                        videoElement.className = 'video-js vjs-default-skin';
                        videoElement.setAttribute('controls', '');
                        videoElement.setAttribute('preload', 'auto');
                        videoElement.style.width = '100%';
                        videoElement.style.height = '100%';
                        
                        const sourceElement = document.createElement('source');
                        sourceElement.src = videoUrl;
                        sourceElement.type = `video/${urlExt}`;
                        
                        videoElement.appendChild(sourceElement);
                        videoContainer.appendChild(videoElement);
                        
                        // Initialize Video.js
                        const initializeVideoJS = () => {
                            if (window.videojs) {
                                currentModalPlayer = videojs(videoElement, {
                                    controls: true,
                                    autoplay: false,
                                    preload: 'auto',
                                    fluid: true,
                                    responsive: true,
                                    muted: false
                                });
                                
                                // Attempt autoplay with audio after player is ready and modal is visible
                                currentModalPlayer.ready(() => {
                                    console.log('Video.js player ready (fallback), attempting autoplay with audio');
                                    
                                    // Small delay to ensure modal is fully visible
                                    setTimeout(() => {
                                        // First try to play with audio
                                        const playPromise = currentModalPlayer.play();
                                        
                                        if (playPromise !== undefined) {
                                            playPromise.then(() => {
                                                console.log('Autoplay with audio successful (fallback)');
                                            }).catch(error => {
                                                console.log('Autoplay with audio failed (fallback), trying muted:', error);
                                                // If audio autoplay fails, try muted autoplay
                                                currentModalPlayer.muted(true);
                                                currentModalPlayer.play().then(() => {
                                                    console.log('Autoplay muted successful (fallback)');
                                                }).catch(err => {
                                                    console.log('All autoplay attempts failed (fallback):', err);
                                                });
                                            });
                                        }
                                    }, 500); // 500ms delay
                                });
                            } else {
                                setTimeout(initializeVideoJS, 100);
                            }
                        };
                        initializeVideoJS();
                        
                        // Show modal and return early
                        modal.classList.add('is-visible');
                        document.body.classList.add('modal-open');
                        return;
                    } else {
                        // Only open in new tab if it's truly not a video file
                        window.open(videoUrl, '_blank');
                        return;
                    }
            }
            
            iframe.src = videoSrc;
            videoContainer.appendChild(iframe);
        }
        
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

    // Auto-setup video thumbnail click handlers
    function setupVideoThumbnails() {
        document.querySelectorAll(".video-thumb").forEach(thumb => {
            thumb.addEventListener("click", function (e) {
                e.preventDefault();
                e.stopPropagation();
                
                const videoType = this.getAttribute("data-video-type");
                const videoId = this.getAttribute("data-video-id");
                const videoUrl = this.getAttribute("data-video-url") || this.href;
                const videoFormat = this.getAttribute("data-video-format");
                
                // Log interaction for tracking
                if (window.trackVideoInteraction) {
                    window.trackVideoInteraction('play', {
                        type: videoType,
                        id: videoId,
                        url: videoUrl,
                        format: videoFormat
                    });
                }
                
                openVideoModal(videoType, videoId, videoUrl, videoFormat);
                return false;
            });
        });
    }

    // Initial setup
    setupVideoThumbnails();
    
    // Re-setup when new content is loaded (for dynamic content)
    if (window.MutationObserver) {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.addedNodes.length > 0) {
                    setupVideoThumbnails();
                }
            });
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
});
</script>
