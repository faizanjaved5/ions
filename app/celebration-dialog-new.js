// Celebration Dialog for ION Video Uploads
// This handles the success dialog with short links and share buttons

// Show celebration dialog with short link and share buttons
function showCelebrationDialog(result) {
    console.log('🎉 Showing celebration dialog for:', result);
    console.log('📋 Share template in result:', result.share_template ? 'YES ✅' : 'NO ❌');
    
    // Change Upload button to "Close Window" state
    if (typeof setUploadButtonState === 'function') {
        setUploadButtonState('success');
        console.log('✅ Upload button changed to "Close Window"');
    } else if (window.parent && typeof window.parent.setUploadButtonState === 'function') {
        window.parent.setUploadButtonState('success');
        console.log('✅ Parent upload button changed to "Close Window"');
    }
    
    // 🔄 BACKGROUND UPDATE: Add new video to parent page without refreshing
    // This keeps the celebration dialog open while video appears in the background
    if (window.parent && window.parent !== window && result.video_id) {
        console.log('🔄 Adding new video to page in background...');
        
        // Use postMessage to tell parent to add the video
        try {
            window.parent.postMessage({
                type: 'video_uploaded',
                video_id: result.video_id,
                shortlink: result.shortlink,
                action: 'add_video_to_list'
            }, '*');
            console.log('✅ Background update message sent to parent');
        } catch (e) {
            console.error('❌ Failed to send background update message:', e);
        }
    }
    
    const title = result.title || 'Video Imported Successfully!';
    const shortlink = result.shortlink;
    const thumbnail = result.thumbnail || '';
    
    // Create celebration dialog HTML with mobile responsive design
    const celebrationHTML = `
        <div class="celebration-dialog-container" style="position: relative; text-align: center; padding: 32px 24px 24px; max-width: 600px; margin: 0 auto;">
            <!-- Close button (X) in top right corner -->
            <button onclick="viewAllVideos()" class="celebration-close-btn" style="position: absolute; top: 12px; right: 12px; width: 32px; height: 32px; background: rgba(71, 85, 105, 0.2); border: none; border-radius: 6px; color: #94a3b8; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 20px; line-height: 1; transition: all 0.2s ease; padding: 0;">
                ×
            </button>
            
            <h2 style="color: #10b981; margin: 0 0 12px 0; font-size: 22px; display: flex; align-items: center; justify-content: center; gap: 8px;">
                <span style="font-size: 24px;">🎉</span> Success!
            </h2>
            <p style="margin: 0 0 16px 0; font-size: 15px; color: #cbd5e1;">${title}</p>
            
            ${thumbnail ? `<div style="margin-bottom: 16px;">
                <img src="${thumbnail}" alt="Video thumbnail" class="celebration-thumbnail" style="max-width: 100%; width: 100%; max-height: 240px; object-fit: cover; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);">
            </div>` : ''}
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 13px; color: #94a3b8;">Short Link:</label>
                <input type="text" value="${window.location.origin}/v/${shortlink}" readonly 
                       id="celebrationShortlink" class="celebration-link-input" style="width: 100%; max-width: 100%; padding: 12px; background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(100, 116, 139, 0.3); border-radius: 8px; font-size: 13px; text-align: center; color: #e2e8f0; font-family: monospace;">
            </div>
            
            <div class="celebration-buttons" style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                <button onclick="copyCelebrationLink()" class="celebration-btn celebration-btn-copy" style="flex: 1; min-width: 100px; padding: 12px 20px; background: #3b82f6; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; font-size: 14px; display: flex; align-items: center; justify-content: center; gap: 6px; transition: all 0.2s ease;">
                    📋 Copy
                </button>
                <button onclick="openCelebrationVideo()" class="celebration-btn celebration-btn-open" style="flex: 1; min-width: 100px; padding: 12px 20px; background: #3b82f6; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; font-size: 14px; display: flex; align-items: center; justify-content: center; gap: 6px; transition: all 0.2s ease;">
                    🔗 Open
                </button>
                <button onclick="shareCelebrationVideo()" class="celebration-btn celebration-btn-share" style="flex: 1; min-width: 100px; padding: 12px 20px; background: #10b981; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; font-size: 14px; display: flex; align-items: center; justify-content: center; gap: 6px; transition: all 0.2s ease;">
                    📤 Share
                </button>
                <button onclick="viewAllVideos()" class="celebration-btn celebration-btn-done celebration-desktop-only" style="flex: 1; min-width: 100px; padding: 12px 20px; background: #6366f1; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; font-size: 14px; display: flex; align-items: center; justify-content: center; gap: 6px; transition: all 0.2s ease;">
                    ✅ Done
                </button>
            </div>
        </div>
        
        <style>
            /* Celebration Dialog Mobile Responsive Styles */
            .celebration-close-btn:hover {
                background: rgba(71, 85, 105, 0.4) !important;
                color: #e2e8f0 !important;
                transform: scale(1.05);
            }
            
            .celebration-close-btn:active {
                transform: scale(0.95);
            }
            
            .celebration-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            }
            
            .celebration-btn:active {
                transform: translateY(0);
            }
            
            .celebration-link-input:focus {
                outline: 2px solid #3b82f6;
                outline-offset: 2px;
            }
            
            /* Mobile Responsive - Hide Done button, show only 3 buttons */
            @media (max-width: 768px) {
                .celebration-dialog-container {
                    padding: 24px 16px 20px !important;
                }
                
                .celebration-close-btn {
                    width: 36px !important;
                    height: 36px !important;
                    font-size: 24px !important;
                    top: 8px !important;
                    right: 8px !important;
                }
                
                .celebration-thumbnail {
                    max-height: 200px !important;
                }
                
                .celebration-buttons {
                    gap: 8px !important;
                }
                
                .celebration-btn {
                    flex: 1 1 calc(33.333% - 6px) !important;
                    min-width: 0 !important;
                    padding: 12px 8px !important;
                    font-size: 13px !important;
                }
                
                /* Hide Done button on mobile - use X instead */
                .celebration-desktop-only {
                    display: none !important;
                }
                
                .celebration-link-input {
                    font-size: 12px !important;
                    padding: 10px !important;
                }
            }
            
            @media (max-width: 480px) {
                .celebration-btn {
                    font-size: 12px !important;
                    padding: 10px 6px !important;
                    gap: 4px !important;
                }
            }
        </style>
    `;
    
    // Show the celebration dialog
    showCustomAlert('', celebrationHTML);
    
    // Store data for helper functions
    window.celebrationData = {
        video_id: result.video_id || result.id, // Store video ID for share functionality
        shortlink: shortlink,
        title: title,
        thumbnail: thumbnail
    };
    
    // Inject share template into parent DOM if provided
    if (result.share_template && window.parent && window.parent !== window) {
        console.log('📝 Injecting share template for video:', window.celebrationData.video_id);
        console.log('📦 Template length:', result.share_template.length);
        
        const tempDiv = window.parent.document.createElement('div');
        tempDiv.innerHTML = result.share_template;
        
        const templateElement = tempDiv.firstElementChild;
        console.log('🎯 Template element:', templateElement);
        console.log('🆔 Template ID:', templateElement ? templateElement.id : 'NO ELEMENT');
        
        if (templateElement) {
            window.parent.document.body.appendChild(templateElement);
            console.log('✅ Share template injected successfully');
            
            // Verify it's in the DOM
            const templateId = `enhanced-share-template-${window.celebrationData.video_id}`;
            const verified = window.parent.document.getElementById(templateId);
            console.log('🔍 Verification check:', verified ? 'FOUND ✅' : 'NOT FOUND ❌');
        } else {
            console.error('❌ No template element to inject!');
        }
    } else {
        console.warn('⚠️ Share template conditions not met:', {
            has_template: !!result.share_template,
            has_parent: window.parent && window.parent !== window
        });
    }
}

// Helper functions for celebration dialog
window.copyCelebrationLink = function() {
    const input = document.getElementById('celebrationShortlink');
    if (input) {
        input.select();
        document.execCommand('copy');
        
        const button = event.target;
        const originalText = button.textContent;
        button.textContent = '✅ Copied!';
        button.style.background = '#10b981';
        
        setTimeout(() => {
            button.textContent = originalText;
            button.style.background = '#3b82f6';
        }, 2000);
    }
};

window.openCelebrationVideo = function() {
    if (window.celebrationData && window.celebrationData.shortlink) {
        const videoUrl = `${window.location.origin}/v/${window.celebrationData.shortlink}`;
        window.open(videoUrl, '_blank');
    }
};

window.shareCelebrationVideo = function() {
    if (!window.celebrationData || !window.celebrationData.video_id) {
        alert('Unable to share: Video ID not found');
        return;
    }
    
    const videoId = window.celebrationData.video_id;
    console.log('📤 Share button clicked for video:', videoId);
    
    // We're in an iframe - close uploader modal first, then open share
    if (window.parent && window.parent !== window) {
        try {
            if (window.parent.EnhancedIONShare && typeof window.parent.EnhancedIONShare.openFromTemplate === 'function') {
                console.log('✅ Closing uploader modal and opening share...');
                
                // Close the uploader modal first
                const uploaderModal = window.parent.document.getElementById('ionVideoUploaderModal');
                if (uploaderModal) {
                    uploaderModal.remove();
                    console.log('✅ Uploader modal closed');
                }
                
                // Small delay to ensure modal is closed, then open share
                setTimeout(() => {
                    console.log('✅ Opening share modal for video:', videoId);
                    window.parent.EnhancedIONShare.openFromTemplate(videoId);
                }, 100);
            } else {
                console.error('❌ EnhancedIONShare not available on parent window');
                window.parent.location.href = `/v/${window.celebrationData.shortlink}`;
            }
        } catch (error) {
            console.error('❌ Share error:', error);
            window.parent.location.href = `/v/${window.celebrationData.shortlink}`;
        }
    } else {
        console.log('Not in iframe, opening video page');
        window.location.href = `/v/${window.celebrationData.shortlink}`;
    }
};

window.viewAllVideos = function() {
    console.log('✅ Done button clicked - closing and refreshing...');
    
    // If in iframe, use postMessage to tell parent to close modal and refresh
    if (window.parent && window.parent !== window) {
        console.log('📤 Sending close_modal message to parent (will trigger refresh)');
        
        try {
            // Send message to parent to close modal and refresh
            window.parent.postMessage({
                type: 'close_modal',
                action: 'close_and_refresh'
            }, '*');
            
            console.log('✅ close_modal message sent to parent');
        } catch (e) {
            console.error('❌ Error sending close message:', e);
            
            // Fallback: Try direct manipulation if postMessage fails
            try {
                const modal = window.parent.document.getElementById('ionVideoUploaderModal');
                if (modal) modal.remove();
                window.parent.location.reload();
            } catch (e2) {
                console.error('❌ Fallback also failed:', e2);
            }
        }
    } else {
        // Not in iframe, redirect to creators page
        console.log('🔄 Not in iframe, redirecting to creators.php');
        window.location.href = './creators.php';
    }
};

window.closeModal = function() {
    // Close any custom modal
    const modal = document.querySelector('.custom-modal');
    if (modal) {
        modal.remove();
    }
    
    // Also close parent window if in iframe and refresh to show new video
    if (window.parent && window.parent !== window) {
        console.log('📤 Sending close_modal message to parent (will trigger refresh)');
        
        try {
            window.parent.postMessage({
                type: 'close_modal',
                action: 'close_and_refresh'
            }, '*');
        } catch (e) {
            console.error('❌ Error sending close message:', e);
            
            // Fallback: Try direct reload
            try {
                window.parent.location.reload();
            } catch (e2) {
                console.error('❌ Fallback reload failed:', e2);
            }
        }
    }
};

// Make showCelebrationDialog globally available
window.showCelebrationDialog = showCelebrationDialog;

// Fix z-index conflict: Ensure share modal appears ABOVE uploader modal
document.addEventListener('DOMContentLoaded', function() {
    // Watch for share modal opening and force it to top
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            mutation.addedNodes.forEach(function(node) {
                if (node.id === 'enhanced-share-modal-global') {
                    console.log('🔝 Forcing share modal to top (z-index: 999999)');
                    node.style.zIndex = '999999';
                    node.style.position = 'fixed';
                    node.style.top = '0';
                    node.style.left = '0';
                    node.style.width = '100%';
                    node.style.height = '100%';
                }
            });
        });
    });
    
    // Observe both iframe body and parent body
    if (window.parent && window.parent !== window) {
        observer.observe(window.parent.document.body, { childList: true });
    }
    observer.observe(document.body, { childList: true });
});
