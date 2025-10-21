// Celebration Dialog for ION Video Uploads
// This handles the success dialog with short links and share buttons

// Show celebration dialog with short link and share buttons
function showCelebrationDialog(result) {
    console.log('🎉 Showing celebration dialog for:', result);
    
    const title = result.title || 'Video Imported Successfully!';
    const shortlink = result.shortlink;
    const thumbnail = result.thumbnail || '';
    
    // Create celebration dialog HTML
    const celebrationHTML = `
        <div style="text-align: center; padding: 20px; max-width: 600px; margin: 0 auto;">
            <h2 style="color: #10b981; margin-bottom: 10px;">🎉 Success!</h2>
            <p style="margin-bottom: 10px; font-size: 16px;">${title}</p>
            
            ${thumbnail ? `<div style="margin-bottom: 10px;">
                <img src="${thumbnail}" alt="Video thumbnail" style="max-width: 450px; width: 100%; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);">
            </div>` : ''}
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 8px; font-weight: bold;">Short Link:</label>
                <input type="text" value="${window.location.origin}/v/${shortlink}" readonly 
                       id="celebrationShortlink" style="width: 100%; max-width: 550px; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; text-align: center;">
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: nowrap;">
                <button onclick="copyCelebrationLink()" style="padding: 10px 20px; background: #3b82f6; color: white; border: none; border-radius: 6px; cursor: pointer; white-space: nowrap;">
                    📋 Copy
                </button>
                <button onclick="openCelebrationVideo()" style="padding: 10px 20px; background: #3b82f6; color: white; border: none; border-radius: 6px; cursor: pointer; white-space: nowrap;">
                    🔗 Open
                </button>
                <button onclick="shareCelebrationVideo()" style="padding: 10px 20px; background: #10b981; color: white; border: none; border-radius: 6px; cursor: pointer; white-space: nowrap;">
                    📤 Share
                </button>
                <button onclick="viewAllVideos()" style="padding: 10px 20px; background: #6366f1; color: white; border: none; border-radius: 6px; cursor: pointer; white-space: nowrap;">
                    ✅ Done
                </button>
            </div>
        </div>
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
    if (!window.celebrationData) {
        console.error('❌ No celebration data available');
        return;
    }
    
    const videoId = window.celebrationData.video_id || window.celebrationData.id;
    
    if (!videoId) {
        console.error('❌ No video ID available for sharing');
        alert('Unable to share: Video ID not found');
        return;
    }
    
    console.log('📤 Opening Enhanced ION Share modal for video:', videoId);
    
    // Try to access parent's EnhancedIONShare (since we're in an iframe)
    let EnhancedShare = null;
    
    // Check parent window first (most common case)
    if (window.parent && window.parent !== window && typeof window.parent.EnhancedIONShare !== 'undefined') {
        console.log('✅ Using parent window EnhancedIONShare');
        EnhancedShare = window.parent.EnhancedIONShare;
    }
    // Fall back to current window
    else if (typeof window.EnhancedIONShare !== 'undefined') {
        console.log('✅ Using current window EnhancedIONShare');
        EnhancedShare = window.EnhancedIONShare;
    }
    // Not available anywhere
    else {
        console.error('❌ Enhanced ION Share system not loaded in window or parent');
        alert('Share system not available. Please try again.');
        return;
    }
    
    // Check if template already exists in parent DOM
    const parentDoc = (window.parent && window.parent !== window) ? window.parent.document : document;
    const template = parentDoc.getElementById(`enhanced-share-template-${videoId}`);
    
    if (template) {
        // Template exists in parent DOM, use it directly
        console.log('✅ Share template found in parent DOM, opening modal');
        EnhancedShare.openFromTemplate(videoId);
    } else {
        // Template doesn't exist, fetch and inject it into PARENT document
        console.log('📥 Fetching share template from server...');
        
        // Construct proper URL (relative to parent page, not iframe)
        const fetchUrl = `${window.location.origin}/app/get-share-template.php?video_id=${videoId}`;
        console.log('📥 Fetch URL:', fetchUrl);
        
        fetch(fetchUrl)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.html) {
                    console.log('✅ Share template fetched, injecting into parent DOM');
                    
                    // Inject template into PARENT document
                    const templateContainer = parentDoc.createElement('div');
                    templateContainer.innerHTML = data.html;
                    parentDoc.body.appendChild(templateContainer);
                    
                    // Now open the modal using parent's EnhancedIONShare
                    EnhancedShare.openFromTemplate(videoId);
                } else {
                    console.error('❌ Failed to fetch share template:', data.error || data);
                    alert('Unable to load share options. Please try again.');
                }
            })
            .catch(error => {
                console.error('❌ Error fetching share template:', error);
                alert('Unable to load share options. Please try again.');
            });
    }
};

window.viewAllVideos = function() {
    // Close the modal first
    closeModal();
    
    // Then redirect to creators page
    setTimeout(() => {
        window.location.href = './creators.php';
    }, 100);
};

window.closeModal = function() {
    // Close any custom modal
    const modal = document.querySelector('.custom-modal');
    if (modal) {
        modal.remove();
    }
    
    // Also close parent window if in iframe
    if (window.parent && window.parent !== window) {
        window.parent.location.reload();
    }
};

// Make showCelebrationDialog globally available
window.showCelebrationDialog = showCelebrationDialog;
