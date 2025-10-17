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
        <div style="text-align: center; padding: 20px;">
            <h2 style="color: #10b981; margin-bottom: 10px;">🎉 Success!</h2>
            <p style="margin-bottom: 20px; font-size: 16px;">${title}</p>
            
            ${thumbnail ? `<div style="margin-bottom: 20px;">
                <img src="${thumbnail}" alt="Video thumbnail" style="max-width: 200px; border-radius: 8px;">
            </div>` : ''}
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: bold;">Short Link:</label>
                <div style="display: flex; gap: 8px; justify-content: center;">
                    <input type="text" value="${window.location.origin}/v/${shortlink}" readonly 
                           id="celebrationShortlink" style="flex: 1; max-width: 300px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <button onclick="copyCelebrationLink()" style="padding: 8px 12px; background: #3b82f6; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        📋 Copy
                    </button>
                </div>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                <button onclick="shareCelebrationVideo()" style="padding: 10px 20px; background: #10b981; color: white; border: none; border-radius: 6px; cursor: pointer;">
                    🔗 Share
                </button>
                <button onclick="viewAllVideos()" style="padding: 10px 20px; background: #6366f1; color: white; border: none; border-radius: 6px; cursor: pointer;">
                    📁 View Videos
                </button>
                <button onclick="closeModal()" style="padding: 10px 20px; background: #6b7280; color: white; border: none; border-radius: 6px; cursor: pointer;">
                    ✅ Done
                </button>
            </div>
        </div>
    `;
    
    // Show the celebration dialog
    showCustomAlert('', celebrationHTML);
    
    // Store data for helper functions
    window.celebrationData = {
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

window.shareCelebrationVideo = function() {
    if (window.celebrationData && window.celebrationData.shortlink) {
        const shareUrl = `${window.location.origin}/v/${window.celebrationData.shortlink}`;
        
        if (navigator.share) {
            navigator.share({
                title: window.celebrationData.title,
                url: shareUrl
            });
        } else {
            navigator.clipboard.writeText(shareUrl).then(() => {
                showCustomAlert('Shared', 'Link copied to clipboard!');
            });
        }
    }
};

window.viewAllVideos = function() {
    window.location.href = './creators.php';
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
