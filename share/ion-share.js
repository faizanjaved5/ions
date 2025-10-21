/**
 * ION Share Module JavaScript
 * Handles all sharing functionality and modal interactions
 */

window.IONShare = (function() {
    'use strict';
    
    let activeModal = null;
    
    // Initialize the module
    function init() {
        // Add event listeners for modal backdrop clicks
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('ion-share-modal')) {
                closeModal(e.target.id);
            }
        });
        
        // Add escape key handler
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && activeModal) {
                closeModal(activeModal);
            }
        });
        
        console.log('ION Share module initialized');
    }
    
    // Open share modal
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) {
            console.error('Share modal not found:', modalId);
            return;
        }
        
        // Close any existing modal
        if (activeModal) {
            closeModal(activeModal);
        }
        
        modal.style.display = 'flex';
        activeModal = modalId;
        
        // Focus on the URL input
        const urlInput = modal.querySelector('input[type="text"]');
        if (urlInput) {
            setTimeout(() => urlInput.select(), 100);
        }
        
        // Prevent body scroll
        document.body.style.overflow = 'hidden';
        
        // Track modal open
        trackShareEvent('modal_opened', getVideoIdFromModal(modalId));
    }
    
    // Close share modal
    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
        }
        
        if (activeModal === modalId) {
            activeModal = null;
        }
        
        // Restore body scroll
        document.body.style.overflow = '';
        
        // Track modal close
        trackShareEvent('modal_closed', getVideoIdFromModal(modalId));
    }
    
    // Copy URL to clipboard
    function copyUrl(inputId) {
        const input = document.getElementById(inputId);
        if (!input) {
            console.error('URL input not found:', inputId);
            return;
        }
        
        // Select and copy
        input.select();
        input.setSelectionRange(0, 99999); // For mobile devices
        
        try {
            const successful = document.execCommand('copy');
            if (successful) {
                showCopySuccess(input);
                
                // Track copy event
                const videoId = getVideoIdFromInput(inputId);
                trackShareEvent('url_copied', videoId);
            } else {
                throw new Error('Copy command failed');
            }
        } catch (err) {
            console.error('Copy failed:', err);
            
            // Fallback: try modern clipboard API
            if (navigator.clipboard) {
                navigator.clipboard.writeText(input.value)
                    .then(() => {
                        showCopySuccess(input);
                        const videoId = getVideoIdFromInput(inputId);
                        trackShareEvent('url_copied', videoId);
                    })
                    .catch(err => {
                        console.error('Clipboard API failed:', err);
                        showCopyError(input);
                    });
            } else {
                showCopyError(input);
            }
        }
    }
    
    // Show copy success feedback
    function showCopySuccess(input) {
        const button = input.parentNode.querySelector('button');
        if (button) {
            const originalText = button.textContent;
            button.textContent = 'Copied!';
            button.classList.add('copied');
            
            setTimeout(() => {
                button.textContent = originalText;
                button.classList.remove('copied');
            }, 2000);
        }
        
        // Show temporary success message
        showToast('Link copied to clipboard!', 'success');
    }
    
    // Show copy error feedback
    function showCopyError(input) {
        showToast('Failed to copy link. Please select and copy manually.', 'error');
    }
    
    // Show temporary toast message
    function showToast(message, type = 'info') {
        // Remove existing toast
        const existingToast = document.querySelector('.ion-share-toast');
        if (existingToast) {
            existingToast.remove();
        }
        
        // Create new toast
        const toast = document.createElement('div');
        toast.className = `ion-share-toast ion-share-toast-${type}`;
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
            color: white;
            padding: 12px 16px;
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            font-size: 14px;
            font-weight: 500;
            z-index: 10001;
            animation: ion-toast-slide-in 0.3s ease-out;
            max-width: 300px;
        `;
        toast.textContent = message;
        
        document.body.appendChild(toast);
        
        // Auto remove after 3 seconds
        setTimeout(() => {
            if (toast.parentNode) {
                toast.style.animation = 'ion-toast-slide-out 0.3s ease-in forwards';
                setTimeout(() => toast.remove(), 300);
            }
        }, 3000);
    }
    
    // Track sharing events (can be extended for analytics)
    function trackShareEvent(action, videoId, platform = null) {
        const eventData = {
            action: action,
            video_id: videoId,
            platform: platform,
            timestamp: new Date().toISOString()
        };
        
        console.log('Share event:', eventData);
        
        // Send to analytics if available
        if (window.gtag) {
            window.gtag('event', 'share', {
                'event_category': 'video_sharing',
                'event_label': action,
                'custom_parameter_video_id': videoId,
                'custom_parameter_platform': platform
            });
        }
        
        // Can also send to your own analytics endpoint
        // fetch('/api/analytics/share', { method: 'POST', body: JSON.stringify(eventData) });
    }
    
    // Extract video ID from modal ID
    function getVideoIdFromModal(modalId) {
        const match = modalId.match(/share-modal-(\d+)/);
        return match ? match[1] : null;
    }
    
    // Extract video ID from input ID
    function getVideoIdFromInput(inputId) {
        const match = inputId.match(/share-url-(\d+)/);
        return match ? match[1] : null;
    }
    
    // Handle platform share clicks
    function handlePlatformShare(platform, url, videoId) {
        trackShareEvent('platform_share', videoId, platform);
        
        // Platform-specific handling
        switch (platform) {
            case 'copy':
                // Handle copy action
                const activeInput = document.querySelector(`#share-url-${videoId}`);
                if (activeInput) {
                    copyUrl(`share-url-${videoId}`);
                }
                break;
                
            default:
                // External platform - will open in new tab
                break;
        }
    }
    
    // Get share data for a video (AJAX call)
    function getShareData(videoId, callback) {
        fetch(`/share/get-share-data.php?video_id=${videoId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    callback(null, data.data);
                } else {
                    callback(data.error || 'Failed to load share data');
                }
            })
            .catch(error => {
                console.error('Share data fetch error:', error);
                callback('Network error');
            });
    }
    
    // Dynamic share modal creation (for programmatic use)
    function createShareModal(videoId, options = {}) {
        getShareData(videoId, function(error, shareData) {
            if (error) {
                console.error('Failed to create share modal:', error);
                showToast('Failed to load sharing options', 'error');
                return;
            }
            
            const modalId = `share-modal-${videoId}`;
            const existingModal = document.getElementById(modalId);
            
            if (existingModal) {
                openModal(modalId);
                return;
            }
            
            // Create modal HTML
            const modalHtml = generateModalHtml(videoId, shareData, options);
            
            // Insert into DOM
            const modalContainer = document.createElement('div');
            modalContainer.innerHTML = modalHtml;
            document.body.appendChild(modalContainer.firstElementChild);
            
            // Open the modal
            openModal(modalId);
        });
    }
    
    // Generate modal HTML (helper function)
    function generateModalHtml(videoId, shareData, options) {
        const platforms = options.platforms || ['facebook', 'twitter', 'whatsapp', 'linkedin', 'copy'];
        
        let platformsHtml = '';
        platforms.forEach(platform => {
            if (shareData.platforms[platform]) {
                const isExternal = platform !== 'copy';
                platformsHtml += `
                    <a href="${shareData.platforms[platform]}" 
                       ${isExternal ? 'target="_blank"' : 'href="javascript:void(0)" onclick="IONShare.copyUrl(\'share-url-' + videoId + '\')"'}
                       class="ion-share-platform ion-share-${platform}"
                       onclick="IONShare.handlePlatformShare('${platform}', '${shareData.url}', ${videoId})">
                        ${getPlatformIcon(platform)}
                        <span>${platform.charAt(0).toUpperCase() + platform.slice(1)}</span>
                    </a>
                `;
            }
        });
        
        return `
            <div class="ion-share-modal" id="share-modal-${videoId}" style="display: none;">
                <div class="ion-share-modal-content">
                    <div class="ion-share-header">
                        <h3>Share this video</h3>
                        <button class="ion-share-close" onclick="IONShare.closeModal('share-modal-${videoId}')">&times;</button>
                    </div>
                    
                    <div class="ion-share-url">
                        <input type="text" value="${shareData.url}" readonly id="share-url-${videoId}">
                        <button onclick="IONShare.copyUrl('share-url-${videoId}')">Copy</button>
                    </div>
                    
                    <div class="ion-share-platforms">
                        ${platformsHtml}
                    </div>
                    
                    ${shareData.clicks > 0 ? `
                        <div class="ion-share-stats">
                            Views: ${shareData.clicks.toLocaleString()}
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
    }
    
    // Platform icon helper (simplified version)
    function getPlatformIcon(platform) {
        const icons = {
            facebook: '📘',
            twitter: '🐦',
            whatsapp: '💬',
            linkedin: '💼',
            telegram: '✈️',
            reddit: '🤖',
            pinterest: '📌',
            email: '📧',
            copy: '📋'
        };
        return `<span style="font-size: 16px;">${icons[platform] || '🔗'}</span>`;
    }
    
    // Add toast animations to document
    function addToastStyles() {
        if (!document.querySelector('#ion-share-toast-styles')) {
            const style = document.createElement('style');
            style.id = 'ion-share-toast-styles';
            style.textContent = `
                @keyframes ion-toast-slide-in {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                @keyframes ion-toast-slide-out {
                    to { transform: translateX(100%); opacity: 0; }
                }
            `;
            document.head.appendChild(style);
        }
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            init();
            addToastStyles();
        });
    } else {
        init();
        addToastStyles();
    }
    
    // Public API
    return {
        openModal: openModal,
        closeModal: closeModal,
        copyUrl: copyUrl,
        createShareModal: createShareModal,
        handlePlatformShare: handlePlatformShare,
        getShareData: getShareData
    };
})();
