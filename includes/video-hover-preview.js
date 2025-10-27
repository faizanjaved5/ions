/**
 * Video Hover Preview System
 * Provides hover-to-preview functionality for video thumbnails
 * Works across all video types: YouTube, Vimeo, Wistia, Rumble, Muvi, Loom, and local videos
 * 
 * Usage: Include this file and call initializeVideoHoverPreviews() on page load
 */

(function() {
    'use strict';
    
    // Configuration
    const config = {
        hoverDelay: 50,         // ms before preview starts (nearly instant)
        minHoverDuration: 100,  // ms minimum hover to trigger
        debug: false            // Enable console logging
    };
    
    // Log helper
    function log(...args) {
        if (config.debug) {
            console.log('[VideoHoverPreview]', ...args);
        }
    }
    
    /**
     * Initialize hover preview for all video thumbnails
     * Looks for .video-thumb-container elements with data-preview-url attribute
     */
    function initializeVideoHoverPreviews() {
        const videoContainers = document.querySelectorAll('.video-thumb-container[data-preview-url]');
        log(`Found ${videoContainers.length} video containers with preview URLs`);
        
        videoContainers.forEach((container, index) => {
            const previewUrl = container.dataset.previewUrl;
            
            if (!previewUrl || previewUrl === '') {
                log(`Container ${index}: No preview URL, skipping`);
                return;
            }
            
            const thumb = container.querySelector('.thumb, .video-thumbnail, img');
            if (!thumb) {
                log(`Container ${index}: No thumbnail image found, skipping`);
                return;
            }
            
            log(`Container ${index}: Setting up preview for`, previewUrl.substring(0, 50) + '...');
            
            let hoverTimeout = null;
            let previewElement = null;
            let hoverStartTime = 0;
            
            // Mouse enter - start timer
            container.addEventListener('mouseenter', () => {
                hoverStartTime = Date.now();
                
                hoverTimeout = setTimeout(() => {
                    createPreview(container, previewUrl);
                }, config.hoverDelay);
            });
            
            // Mouse leave - cancel timer and remove preview
            container.addEventListener('mouseleave', () => {
                const hoverDuration = Date.now() - hoverStartTime;
                
                clearTimeout(hoverTimeout);
                
                if (previewElement) {
                    // Only remove if minimum hover duration was met
                    if (hoverDuration >= config.minHoverDuration) {
                        removePreview();
                    }
                }
            });
            
            // Create preview element
            function createPreview(container, url) {
                // Don't create if already exists
                if (previewElement) {
                    log('Preview already exists, skipping creation');
                    return;
                }
                
                log('Creating preview for:', url.substring(0, 50) + '...');
                
                // Local video preview (direct video file)
                if (url.startsWith('local:')) {
                    const videoUrl = url.substring(6); // Remove 'local:' prefix
                    log('Creating local video preview');
                    
                    previewElement = document.createElement('video');
                    previewElement.style.cssText = 'position:absolute;top:0;left:0;width:100%;height:100%;border:0;z-index:10;object-fit:cover;border-radius:inherit;pointer-events:none;';
                    previewElement.muted = true;
                    previewElement.loop = true;
                    previewElement.playsInline = true;
                    previewElement.preload = 'auto'; // Load video immediately for instant playback
                    previewElement.autoplay = true;  // Set autoplay attribute
                    previewElement.src = videoUrl;
                    
                    // Ensure container has relative positioning
                    if (getComputedStyle(container).position === 'static') {
                        container.style.position = 'relative';
                    }
                    
                    container.appendChild(previewElement);
                    
                    // Try to play immediately
                    const playPromise = previewElement.play();
                    if (playPromise !== undefined) {
                        playPromise.catch(e => {
                            log('Initial autoplay prevented:', e.message);
                            // Fallback: wait for canplay event
                            previewElement.addEventListener('canplay', () => {
                                log('Video can play, attempting play');
                                previewElement.play().catch(err => {
                                    log('Canplay autoplay prevented:', err.message);
                                });
                            }, { once: true });
                        });
                    }
                    
                    // Error handling
                    previewElement.addEventListener('error', (e) => {
                        log('Video preview error:', e);
                        removePreview();
                    });
                    
                } else {
                    // External platform preview (YouTube, Vimeo, etc.)
                    log('Creating iframe preview');
                    
                    previewElement = document.createElement('iframe');
                    previewElement.src = url;
                    previewElement.allow = 'autoplay; encrypted-media';
                    previewElement.style.cssText = 'position:absolute;top:0;left:0;width:100%;height:100%;border:0;z-index:10;border-radius:inherit;pointer-events:none;';
                    previewElement.frameBorder = '0';
                    
                    // Ensure container has relative positioning
                    if (getComputedStyle(container).position === 'static') {
                        container.style.position = 'relative';
                    }
                    
                    container.appendChild(previewElement);
                    
                    log('Iframe preview created and appended');
                }
            }
            
            // Remove preview element
            function removePreview() {
                if (previewElement) {
                    log('Removing preview');
                    
                    // Pause video if it's a video element
                    if (previewElement.tagName === 'VIDEO') {
                        previewElement.pause();
                    }
                    
                    previewElement.remove();
                    previewElement = null;
                }
            }
        });
        
        log('Hover preview initialization complete');
    }
    
    /**
     * Re-initialize hover previews (for dynamically loaded content)
     */
    function reinitialize() {
        log('Re-initializing hover previews');
        initializeVideoHoverPreviews();
    }
    
    // Expose API
    window.VideoHoverPreview = {
        init: initializeVideoHoverPreviews,
        reinit: reinitialize,
        setDebug: (enabled) => {
            config.debug = enabled;
            log('Debug mode:', enabled);
        },
        setHoverDelay: (ms) => {
            config.hoverDelay = ms;
            log('Hover delay set to:', ms);
        }
    };
    
    // Auto-initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeVideoHoverPreviews);
    } else {
        // DOM already loaded
        initializeVideoHoverPreviews();
    }
    
    // Watch for dynamically added content (for pages that load videos via AJAX)
    const observer = new MutationObserver((mutations) => {
        let shouldReinit = false;
        
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node.nodeType === 1) { // Element node
                    if (node.matches && node.matches('.video-thumb-container[data-preview-url]')) {
                        shouldReinit = true;
                    } else if (node.querySelector && node.querySelector('.video-thumb-container[data-preview-url]')) {
                        shouldReinit = true;
                    }
                }
            });
        });
        
        if (shouldReinit) {
            log('New video containers detected, re-initializing');
            setTimeout(reinitialize, 100); // Slight delay to ensure DOM is settled
        }
    });
    
    // Start observing
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
    
    log('Video hover preview system loaded');
    
})();
