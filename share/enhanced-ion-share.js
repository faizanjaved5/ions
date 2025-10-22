/**
 * Enhanced ION Share Module with Embed Support
 * Handles sharing, embedding, and modal interactions
 */

window.EnhancedIONShare = (function() {
    'use strict';
    
    let activeModal = null;
    let copyTimeout = null;
    let globalModal = null;
    let globalModalContent = null;
    
    // Initialize the module
    function init() {
        if (window.enhancedShareBound) return; // guard against duplicate bindings
        window.enhancedShareBound = true;
        // Remove any legacy share modals if they were rendered on this page
        cleanupLegacyModals();
        // Ensure any pre-rendered modals are attached directly to <body>
        try {
            const preRenderedModals = document.querySelectorAll('.enhanced-share-modal');
            preRenderedModals.forEach(function(modal) {
                if (modal && modal.parentNode !== document.body) {
                    document.body.appendChild(modal);
                }
                // Force fixed overlay geometry to avoid nesting/layout bugs
                modal.style.position = 'fixed';
                modal.style.left = '0';
                modal.style.top = '0';
                modal.style.right = '0';
                modal.style.bottom = '0';
            });
        } catch (err) {
            console.warn('Enhanced ION Share: failed to reparent pre-rendered modals', err);
        }
        // Add event listeners for modal backdrop clicks
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('enhanced-share-modal')) {
                closeModal(e.target.id);
            }
        });
        
        // Add escape key handler
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && activeModal) {
                closeModal(activeModal);
            }
        });
        
        // Add CSS for enhanced modals
        addEnhancedStyles();
        
        // Create a single global modal container that we reuse
        ensureGlobalModal();
        
        // Watch for any legacy modals that might be injected later and remove them immediately
        try {
            const observer = new MutationObserver((mutations) => {
                mutations.forEach((m) => {
                    m.addedNodes && m.addedNodes.forEach((node) => {
                        if (node && node.nodeType === 1) {
                            if (node.matches && (node.matches('.ion-share-modal') || (node.id && /^share-modal-/.test(node.id)))) {
                                node.parentNode && node.parentNode.removeChild(node);
                            } else if (node.querySelector) {
                                const legacy = node.querySelectorAll('.ion-share-modal, [id^="share-modal-"]');
                                legacy.forEach((el) => el.parentNode && el.parentNode.removeChild(el));
                            }
                        }
                    });
                });
            });
            observer.observe(document.body, { childList: true, subtree: true });
        } catch (e) {}
        
        console.log('Enhanced ION Share module initialized');
    }

    // Remove any legacy (old) share modals to prevent flicker
    function cleanupLegacyModals() {
        try {
            document.querySelectorAll('.ion-share-modal, [id^="share-modal-"]').forEach(function(m) {
                if (m && m.parentNode) m.parentNode.removeChild(m);
            });
        } catch (e) {}
    }
    
    // Ensure there is a single global modal container attached to body
    function ensureGlobalModal() {
        globalModal = document.getElementById('enhanced-share-modal-global');
        if (!globalModal) {
            globalModal = document.createElement('div');
            globalModal.id = 'enhanced-share-modal-global';
            globalModal.className = 'enhanced-share-modal';
            globalModal.style.cssText = 'display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 10000; align-items: center; justify-content: center;';
            globalModalContent = document.createElement('div');
            globalModalContent.className = 'enhanced-share-modal-content';
            globalModalContent.style.cssText = 'background: #1a1a1a; border-radius: 12px; padding: 0; max-width: 720px; width: 92%; max-height: 88vh; overflow-y: auto; color: white;';
            globalModal.appendChild(globalModalContent);
            document.body.appendChild(globalModal);
        } else {
            globalModalContent = globalModal.querySelector('.enhanced-share-modal-content');
        }
    }
    
    // Open share modal
    function openModal(modalId) {
        const sourceModal = document.getElementById(modalId);
        if (!sourceModal) {
            console.error('Enhanced share modal not found:', modalId);
            return;
        }
        
        ensureGlobalModal();
        
        // Close any existing modal first
        if (activeModal) {
            closeModal(activeModal);
        }
        
        // Clone inner content into the singleton global modal
        const sourceContent = sourceModal.querySelector('.enhanced-share-modal-content');
        if (!sourceContent) {
            console.error('Enhanced share modal content not found in:', modalId);
            return;
        }
        globalModalContent.innerHTML = sourceContent.innerHTML;
        
        // Hide any other (card-scoped) modals to prevent flicker
        document.querySelectorAll('.enhanced-share-modal').forEach(function(m) {
            if (m.id !== 'enhanced-share-modal-global') {
                m.style.display = 'none';
            }
        });
        // Also remove any legacy share modals if present
        cleanupLegacyModals();
        
        // Normalize close button(s) inside the global modal
        const closeBtns = globalModalContent.querySelectorAll('.enhanced-share-close');
        closeBtns.forEach(btn => {
            btn.onclick = function(e) {
                if (e && typeof e.stopPropagation === 'function') e.stopPropagation();
                closeModal('enhanced-share-modal-global');
            };
        });
        
        // Display the global modal only
        globalModal.style.display = 'flex';
        // Ensure CSS .show is applied for pointer-events and animation
        setTimeout(function(){ globalModal.classList.add('show'); }, 10);
        activeModal = 'enhanced-share-modal-global';
        
        // Reset to share tab by default
        const videoId = extractVideoIdFromModalId(modalId);
        if (videoId) {
            switchTab(videoId, 'share');
        }
        
        // Focus on the URL input within the global modal content
        const urlInput = globalModalContent.querySelector('input[type="text"]');
        if (urlInput) {
            setTimeout(() => {
                urlInput.select();
                urlInput.focus();
            }, 100);
        }
        
        // Prevent body scroll
        document.body.style.overflow = 'hidden';
        
        // Track modal open
        trackShareEvent('enhanced_modal_opened', videoId);
    }
    
    // Close share modal
    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300); // Match CSS animation duration
        }
        
        if (activeModal === modalId) {
            activeModal = null;
        }
        
        // Restore body scroll
        document.body.style.overflow = '';
        // Proactively remove any legacy share modals that might be present to avoid brief flash
        cleanupLegacyModals();
        
        // Track modal close
        const videoId = extractVideoIdFromModalId(modalId);
        trackShareEvent('enhanced_modal_closed', videoId);
    }
    
    // Switch between share and embed tabs
    function switchTab(videoId, tabName) {
        const shareTab = document.getElementById(`share-tab-${videoId}`);
        const embedTab = document.getElementById(`embed-tab-${videoId}`);
        
        // Find tab buttons within the visible modal (prefer the global modal)
        const modal = document.getElementById('enhanced-share-modal-global') || document.getElementById(`enhanced-share-modal-${videoId}`);
        if (!modal) return;
        
        const tabButtons = modal.querySelectorAll('.share-tab-btn');
        
        if (tabName === 'share') {
            // Show share tab
            if (shareTab) shareTab.style.display = 'block';
            if (embedTab) embedTab.style.display = 'none';
            
            // Update button states
            tabButtons.forEach(btn => {
                if (btn.getAttribute('data-tab') === 'share') {
                    btn.classList.add('active');
                    btn.style.color = 'white';
                    btn.style.borderBottomColor = '#3b82f6';
                } else {
                    btn.classList.remove('active');
                    btn.style.color = '#999';
                    btn.style.borderBottomColor = 'transparent';
                }
            });
            
            // Focus URL input
            const urlInput = shareTab.querySelector('input[type="text"]');
            if (urlInput) {
                setTimeout(() => urlInput.select(), 50);
            }
            
        } else if (tabName === 'embed') {
            // Show embed tab
            if (shareTab) shareTab.style.display = 'none';
            if (embedTab) embedTab.style.display = 'block';
            
            // Update button states
            tabButtons.forEach(btn => {
                if (btn.getAttribute('data-tab') === 'embed') {
                    btn.classList.add('active');
                    btn.style.color = 'white';
                    btn.style.borderBottomColor = '#3b82f6';
                } else {
                    btn.classList.remove('active');
                    btn.style.color = '#999';
                    btn.style.borderBottomColor = 'transparent';
                }
            });
        }
        
        trackShareEvent('tab_switched', videoId, tabName);
    }
    
    // Switch embed size
    function switchEmbedSize(videoId, size) {
        // Hide all embed sections
        const embedSections = document.querySelectorAll(`.embed-code-section`);
        embedSections.forEach(section => {
            if (section.id.includes(`-${videoId}`)) {
                section.style.display = 'none';
            }
        });
        
        // Show selected size
        const selectedSection = document.getElementById(`embed-${size}-${videoId}`);
        if (selectedSection) {
            selectedSection.style.display = 'block';
        }
        
        trackShareEvent('embed_size_changed', videoId, size);
    }
    
    // Copy text to clipboard
    function copyText(inputId) {
        const input = document.getElementById(inputId);
        if (!input) {
            console.error('Input element not found:', inputId);
            return;
        }
        
        // Clear any existing timeout
        if (copyTimeout) {
            clearTimeout(copyTimeout);
        }
        
        // Select the text
        if (input.tagName.toLowerCase() === 'textarea') {
            input.select();
        } else {
            input.select();
            input.setSelectionRange(0, 99999); // For mobile devices
        }
        
        try {
            // Try the legacy method first
            const successful = document.execCommand('copy');
            if (successful) {
                showCopySuccess(input, inputId);
            } else {
                throw new Error('Copy command failed');
            }
        } catch (err) {
            // Fallback to modern Clipboard API
            if (navigator.clipboard) {
                navigator.clipboard.writeText(input.value)
                    .then(() => {
                        showCopySuccess(input, inputId);
                    })
                    .catch(err => {
                        console.error('Clipboard API failed:', err);
                        showCopyError();
                    });
            } else {
                showCopyError();
            }
        }
    }
    
    // Show copy success feedback
    function showCopySuccess(input, inputId) {
        // Find the copy button
        let button = input.parentNode.querySelector('button');
        if (!button) {
            // Try finding button by looking for onclick attribute
            const buttons = input.parentNode.querySelectorAll('button');
            for (let btn of buttons) {
                if (btn.onclick && btn.onclick.toString().includes(inputId)) {
                    button = btn;
                    break;
                }
            }
        }
        
        if (button) {
            const originalText = button.textContent;
            button.textContent = 'Copied!';
            button.style.background = '#10b981';
            
            copyTimeout = setTimeout(() => {
                button.textContent = originalText;
                button.style.background = '#3b82f6';
            }, 2000);
        }
        
        // Show toast notification
        showToast('Copied to clipboard!', 'success');
        
        // Track the copy event
        const videoId = extractVideoIdFromInputId(inputId);
        const copyType = inputId.includes('embed-code') ? 'embed_code' : 'share_url';
        trackShareEvent('copied', videoId, copyType);
    }
    
    // Show copy error feedback
    function showCopyError() {
        showToast('Failed to copy. Please select and copy manually.', 'error');
    }
    
    // Show toast notification
    function showToast(message, type = 'info') {
        // Remove existing toast
        const existingToast = document.querySelector('.enhanced-share-toast');
        if (existingToast) {
            existingToast.remove();
        }
        
        // Create toast
        const toast = document.createElement('div');
        toast.className = `enhanced-share-toast enhanced-share-toast-${type}`;
        
        const bgColor = type === 'success' ? '#10b981' : 
                       type === 'error' ? '#ef4444' : '#3b82f6';
        
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${bgColor};
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            font-size: 14px;
            font-weight: 500;
            z-index: 10002;
            animation: enhanced-toast-slide-in 0.3s ease-out;
            max-width: 300px;
            backdrop-filter: blur(8px);
        `;
        
        toast.textContent = message;
        document.body.appendChild(toast);
        
        // Auto remove
        setTimeout(() => {
            if (toast.parentNode) {
                toast.style.animation = 'enhanced-toast-slide-out 0.3s ease-in forwards';
                setTimeout(() => toast.remove(), 300);
            }
        }, 3000);
    }
    
    // Track share events
    function trackShare(platform, videoId) {
        trackShareEvent('platform_shared', videoId, platform);
    }
    
    // Generic event tracking
    function trackShareEvent(action, videoId, extra = null) {
        const eventData = {
            action: action,
            video_id: videoId,
            extra: extra,
            timestamp: new Date().toISOString(),
            url: window.location.href
        };
        
        console.log('Enhanced Share Event:', eventData);
        
        // Send to Google Analytics if available
        if (window.gtag) {
            window.gtag('event', 'enhanced_share', {
                'event_category': 'video_sharing_enhanced',
                'event_label': action,
                'custom_parameter_video_id': videoId,
                'custom_parameter_extra': extra
            });
        }
        
        // Send to custom analytics endpoint (optional) - Disabled to prevent 404 errors
        // TODO: Implement analytics endpoint if needed
        // if (window.location.hostname !== 'localhost') {
        //     fetch('/api/analytics/enhanced-share', {
        //         method: 'POST',
        //         headers: {
        //             'Content-Type': 'application/json',
        //         },
        //         body: JSON.stringify(eventData)
        //     }).catch(e => console.log('Analytics send failed:', e));
        // }
    }

    // Compose helpers: open platform with message text merged
    function getDefaultMessage(videoId) {
        const urlInput = document.getElementById(`enhanced-share-url-${videoId}`);
        const meta = document.getElementById(`share-meta-${videoId}`);
        const title = meta ? meta.getAttribute('data-title') : '';
        const url = urlInput ? urlInput.value : window.location.href;
        const parts = [];
        if (title) parts.push(title);
        parts.push(url);
        return parts.join('\n');
    }

    function updateShareMessage(videoId) {
        const textarea = document.getElementById(`share-message-${videoId}`);
        const counter = document.getElementById(`share-message-count-${videoId}`);
        if (!textarea || !counter) return;
        const length = textarea.value.length;
        counter.textContent = `${length}`;
    }

    function openPlatform(videoId, platform, baseUrl) {
        try {
            const textarea = document.getElementById(`share-message-${videoId}`);
            let message = textarea && textarea.value.trim() ? textarea.value : getDefaultMessage(videoId);
            const enc = encodeURIComponent(message);
            let finalUrl = baseUrl;
            // Always override the message for platforms that support it
            if (platform === 'twitter') {
                // X (formerly Twitter) share intent
                finalUrl = `https://twitter.com/intent/tweet?text=${enc}`;
            } else if (platform === 'whatsapp') {
                finalUrl = `https://wa.me/?text=${enc}`;
            } else if (platform === 'telegram') {
                finalUrl = `https://t.me/share/url?text=${enc}`;
            } else if (platform === 'email') {
                const subject = encodeURIComponent('Check this out');
                finalUrl = `mailto:?subject=${subject}&body=${enc}`;
            } else if (platform === 'reddit') {
                // Reddit supports text posts using body parameter; preserve any existing url/title params
                try {
                    const u = new URL(baseUrl);
                    // Force correct host format
                    if (!/reddit\.com$/.test(u.hostname)) {
                        u.href = `https://www.reddit.com/submit`;
                    }
                    // Set text post parameters
                    u.searchParams.set('type', 'TEXT');
                    u.searchParams.set('body', message);
                    finalUrl = u.toString();
                } catch (e) {
                    finalUrl = `https://www.reddit.com/submit?type=TEXT&body=${enc}`;
                }
            } else if (platform === 'facebook' || platform === 'linkedin' || platform === 'pinterest') {
                // Open as-is for platforms that ignore custom text
            }
            window.open(finalUrl, '_blank');
            trackShare(platform, videoId);
        } catch (e) {
            console.error('openPlatform failed', e);
        }
    }
    
    // Extract video ID from modal ID
    function extractVideoIdFromModalId(modalId) {
        const match = modalId.match(/enhanced-share-modal-(\d+)/);
        return match ? match[1] : null;
    }
    
    // Extract video ID from input ID
    function extractVideoIdFromInputId(inputId) {
        // Handle different input ID formats
        let match = inputId.match(/enhanced-share-url-(\d+)/);
        if (match) return match[1];
        
        match = inputId.match(/embed-code-[^-]+-(\d+)/);
        if (match) return match[1];
        
        return null;
    }
    
    // Add enhanced styles
    function addEnhancedStyles() {
        if (document.querySelector('#enhanced-share-styles')) return;
        
        const style = document.createElement('style');
        style.id = 'enhanced-share-styles';
        style.textContent = `
            @keyframes enhanced-toast-slide-in {
                from { 
                    transform: translateX(100%); 
                    opacity: 0; 
                }
                to { 
                    transform: translateX(0); 
                    opacity: 1; 
                }
            }
            
            @keyframes enhanced-toast-slide-out {
                to { 
                    transform: translateX(100%); 
                    opacity: 0; 
                }
            }
            
            .enhanced-share-modal {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
            }
            
            .enhanced-share-modal-content {
                box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
                border: 1px solid #333;
            }
            
            .share-tab-btn {
                transition: all 0.2s ease;
            }
            
            .share-tab-btn:hover {
                background: rgba(255, 255, 255, 0.05) !important;
            }
            
            .share-platform-btn:hover {
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            }
            
            .enhanced-share-button:hover {
                transform: translateY(-1px);
            }
            
            /* Scrollbar styling for textareas */
            .enhanced-share-modal textarea::-webkit-scrollbar {
                width: 8px;
            }
            
            .enhanced-share-modal textarea::-webkit-scrollbar-track {
                background: #333;
                border-radius: 4px;
            }
            
            .enhanced-share-modal textarea::-webkit-scrollbar-thumb {
                background: #666;
                border-radius: 4px;
            }
            
            .enhanced-share-modal textarea::-webkit-scrollbar-thumb:hover {
                background: #777;
            }
            
            /* Loading state for copy buttons */
            .copy-loading {
                opacity: 0.7;
                pointer-events: none;
            }
            
            /* Responsive adjustments */
            @media (max-width: 768px) {
                .enhanced-share-modal-content {
                    width: 96% !important;
                    max-height: 90vh !important;
                    margin: 20px !important;
                }
                
                .share-platforms-grid {
                    grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)) !important;
                    gap: 8px !important;
                }
                
                .share-platform-btn {
                    padding: 8px !important;
                    font-size: 12px !important;
                }
                
                .enhanced-share-header h3 {
                    font-size: 16px !important;
                }
                
                .share-tab-btn {
                    padding: 10px 12px !important;
                    font-size: 14px !important;
                }
                /* Stack preview and message on small screens */
                .share-compose {
                    flex-direction: column !important;
                    gap: 12px !important;
                }
                .share-compose > div {
                    width: 100% !important;
                    flex: 0 0 auto !important;
                }
            }
        `;
        
        document.head.appendChild(style);
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    // Public API
    return {
        openModal: openModal,
        closeModal: closeModal,
        switchTab: switchTab,
        switchEmbedSize: switchEmbedSize,
        copyText: copyText,
        trackShare: trackShare,
        trackShareEvent: trackShareEvent,
        openPlatform: openPlatform,
        updateShareMessage: updateShareMessage,
        openFromTemplate: function(videoId) {
            try {
                ensureGlobalModal();
                const tpl = document.getElementById(`enhanced-share-template-${videoId}`);
                if (!tpl) {
                    console.error('Enhanced share template not found for video:', videoId);
                    return;
                }
                globalModalContent.innerHTML = tpl.innerHTML;
                // Normalize close buttons
                const closeBtns = globalModalContent.querySelectorAll('.enhanced-share-close');
                closeBtns.forEach(btn => btn.onclick = function(){ closeModal('enhanced-share-modal-global'); });
                // Show
                // globalModal.style.display = 'flex';
                // activeModal = 'enhanced-share-modal-global';
                // document.body.style.overflow = 'hidden';
                
                // Show using the pattern that fixes visibility issues - force with !important
                globalModal.style.setProperty('display', 'flex', 'important');
            globalModal.style.setProperty('opacity', '1', 'important');
            // Force above any uploader/iframe overlays
            globalModal.style.setProperty('z-index', '2147483647', 'important');
                globalModal.style.setProperty('position', 'fixed', 'important');
                globalModal.style.setProperty('top', '0', 'important');
                globalModal.style.setProperty('left', '0', 'important');
                globalModal.style.setProperty('width', '100%', 'important');
                globalModal.style.setProperty('height', '100%', 'important');
                globalModal.style.setProperty('background', 'rgba(0,0,0,0.8)', 'important');
                globalModal.style.setProperty('pointer-events', 'auto', 'important');
                globalModal.classList.add('show');
                activeModal = 'enhanced-share-modal-global';
                document.body.style.overflow = 'hidden';
                console.log('Modal forced visible with !important CSS');

                switchTab(videoId, 'share');
                // Initialize message counter
                try { updateShareMessage(videoId); } catch (e) {}
                trackShareEvent('enhanced_modal_opened', videoId);
            } catch (e) {
                console.error('openFromTemplate failed:', e);
            }
        }
    };
})();

// Legacy compatibility - redirect old IONShare calls to enhanced version
if (typeof window.IONShare === 'undefined') {
    window.IONShare = {
        openModal: function(modalId) {
            // Check if it's an enhanced modal
            if (modalId.includes('enhanced-share-modal-')) {
                window.EnhancedIONShare.openModal(modalId);
            } else {
                console.warn('Legacy ION Share modal not found, use Enhanced version');
            }
        },
        closeModal: function(modalId) {
            window.EnhancedIONShare.closeModal(modalId);
        },
        copyUrl: function(inputId) {
            window.EnhancedIONShare.copyText(inputId);
        }
    };
}

// Initialization log
console.log('✅ Enhanced ION Share module initialized');
console.log('🔗 window.EnhancedIONShare:', typeof window.EnhancedIONShare);