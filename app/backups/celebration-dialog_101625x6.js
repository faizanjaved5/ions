// Celebration Dialog for ION Video Uploads
// This handles the success dialog with short links and share buttons

// Show celebration dialog with short link and share buttons
function showCelebrationDialog(result) {
    console.log('🎉 Showing celebration dialog for:', result);
    console.log('📋 Share template in result:', result.share_template ? 'YES ✅' : 'NO ❌');
    
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
    console.log('📤 Opening Enhanced ION Share for video:', videoId);
    
    // Call parent window's EnhancedIONShare (same as used in creators.php)
    if (window.parent && window.parent !== window) {
        if (window.parent.EnhancedIONShare && typeof window.parent.EnhancedIONShare.openFromTemplate === 'function') {
            window.parent.EnhancedIONShare.openFromTemplate(videoId);
        } else {
            console.error('❌ EnhancedIONShare not available in parent window');
            alert('Share system not available. Please try again.');
        }
    } else {
        console.error('❌ Not in iframe');
        alert('Share system not available. Please try again.');
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
