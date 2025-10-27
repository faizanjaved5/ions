/**
 * ION Video Reactions Module
 * Handles like/unlike/dislike functionality for videos
 */

class VideoReactions {
    constructor() {
        this.apiEndpoint = '/api/video-reactions.php';
        this.processing = new Set(); // Track videos being processed to prevent double-clicks
    }

    /**
     * Initialize reaction buttons for a specific video
     */
    init(videoId, currentUserAction = null) {
        // CRITICAL FIX: Only select .video-reactions container
        const container = document.querySelector(`.video-reactions[data-video-id="${videoId}"]`);
        if (!container) {
            console.warn(`Video reactions container not found for video ${videoId}`);
            return;
        }

        // Get buttons
        const likeBtn = container.querySelector('.like-btn');
        const dislikeBtn = container.querySelector('.dislike-btn');

        if (!likeBtn || !dislikeBtn) {
            console.warn(`Like/dislike buttons not found for video ${videoId}`);
            return;
        }

        // Set initial state
        this.updateButtonStates(container, currentUserAction);

        // Add event listeners
        likeBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.handleLike(videoId, container);
        });

        dislikeBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.handleDislike(videoId, container);
        });
    }

    /**
     * Initialize all reaction buttons on the page
     */
    initAll() {
        // CRITICAL FIX: Only select .video-reactions containers, not all elements with data-video-id
        // (share buttons also have data-video-id but shouldn't be treated as reaction containers)
        const containers = document.querySelectorAll('.video-reactions[data-video-id]');
        console.log(`🚀 Initializing ${containers.length} video reaction containers`);
        containers.forEach(container => {
            const videoId = parseInt(container.dataset.videoId);
            // Get user action, treating empty string as null, normalize to lowercase
            const rawAction = container.dataset.userAction;
            let currentAction = null;
            if (rawAction && typeof rawAction === 'string' && rawAction.trim() !== '') {
                currentAction = rawAction.trim().toLowerCase();
            }
            console.log(`📹 Video ${videoId}: data-user-action="${rawAction}", processed as: "${currentAction}"`);
            this.init(videoId, currentAction);
        });
    }

    /**
     * Handle like action
     */
    async handleLike(videoId, container) {
        if (this.processing.has(videoId)) {
            return;
        }

        this.processing.add(videoId);

        const currentAction = container.dataset.userAction;
        const action = currentAction === 'like' ? 'unlike' : 'like';

        try {
            const result = await this.makeRequest(videoId, action);

            if (result.success) {
                // Update counts
                this.updateCounts(container, result.data.counts);
                
                // Update button states
                this.updateButtonStates(container, result.data.user_action);
                
                // Store new state
                container.dataset.userAction = result.data.user_action || '';
                
                // Show feedback
                this.showFeedback(container, action === 'like' ? 'Liked!' : 'Unliked');
            } else {
                this.showError(container, result.message);
            }
        } catch (error) {
            console.error('Error handling like:', error);
            this.showError(container, 'Failed to update. Please try again.');
        } finally {
            this.processing.delete(videoId);
        }
    }

    /**
     * Handle dislike action
     */
    async handleDislike(videoId, container) {
        if (this.processing.has(videoId)) {
            return;
        }

        this.processing.add(videoId);

        const currentAction = container.dataset.userAction;
        const action = currentAction === 'dislike' ? 'unlike' : 'dislike';

        try {
            const result = await this.makeRequest(videoId, action);

            if (result.success) {
                // Update counts
                this.updateCounts(container, result.data.counts);
                
                // Update button states
                this.updateButtonStates(container, result.data.user_action);
                
                // Store new state
                container.dataset.userAction = result.data.user_action || '';
                
                // Show feedback
                this.showFeedback(container, action === 'dislike' ? 'Disliked' : 'Removed dislike');
            } else {
                this.showError(container, result.message);
            }
        } catch (error) {
            console.error('Error handling dislike:', error);
            this.showError(container, 'Failed to update. Please try again.');
        } finally {
            this.processing.delete(videoId);
        }
    }

    /**
     * Make API request
     */
    async makeRequest(videoId, action) {
        const response = await fetch(this.apiEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                video_id: videoId,
                action: action
            })
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        return await response.json();
    }

    /**
     * Update button visual states
     */
    updateButtonStates(container, userAction) {
        const likeBtn = container.querySelector('.like-btn');
        const dislikeBtn = container.querySelector('.dislike-btn');

        if (!likeBtn || !dislikeBtn) {
            console.warn(`⚠️ Could not find like/dislike buttons for video ${container.dataset.videoId}`);
            return;
        }

        // Debug logging
        const videoId = container.dataset.videoId;
        console.log(`🎨 Updating button states for video ${videoId}, userAction: "${userAction}" (type: ${typeof userAction})`);

        // Reset both buttons
        likeBtn.classList.remove('active');
        dislikeBtn.classList.remove('active');

        // Normalize user action (handle empty strings, null, undefined, and case sensitivity)
        let normalizedAction = null;
        if (userAction && typeof userAction === 'string' && userAction.trim() !== '') {
            normalizedAction = userAction.trim().toLowerCase();
        }

        // Set active state based on user action
        if (normalizedAction === 'like') {
            likeBtn.classList.add('active');
            console.log(`✅ Added 'active' class to LIKE button for video ${videoId}`);
        } else if (normalizedAction === 'dislike') {
            dislikeBtn.classList.add('active');
            console.log(`✅ Added 'active' class to DISLIKE button for video ${videoId}`);
        } else {
            console.log(`ℹ️ No active state for video ${videoId} (normalizedAction: "${normalizedAction}", original: "${userAction}")`);
        }
    }

    /**
     * Update like/dislike counts
     */
    updateCounts(container, counts) {
        const likeCount = container.querySelector('.like-count');
        const dislikeCount = container.querySelector('.dislike-count');

        if (likeCount) {
            likeCount.textContent = this.formatCount(counts.likes);
        }

        if (dislikeCount) {
            dislikeCount.textContent = this.formatCount(counts.dislikes);
        }
    }

    /**
     * Format count for display
     */
    formatCount(count) {
        if (count === 0) return '';
        if (count >= 1000000) return (count / 1000000).toFixed(1) + 'M';
        if (count >= 1000) return (count / 1000).toFixed(1) + 'K';
        return count.toString();
    }

    /**
     * Show feedback message
     */
    showFeedback(container, message) {
        const feedback = container.querySelector('.reaction-feedback');
        if (feedback) {
            feedback.textContent = message;
            feedback.classList.add('show');
            setTimeout(() => {
                feedback.classList.remove('show');
            }, 2000);
        }
    }

    /**
     * Show error message
     */
    showError(container, message) {
        const feedback = container.querySelector('.reaction-feedback');
        if (feedback) {
            feedback.textContent = message;
            feedback.classList.add('show', 'error');
            setTimeout(() => {
                feedback.classList.remove('show', 'error');
            }, 3000);
        } else {
            alert(message);
        }
    }
}

// Initialize global instance (with both names for compatibility)
window.videoReactions = new VideoReactions();
window.VideoReactions = VideoReactions; // Export class constructor
window.IONVideoReactions = VideoReactions; // COMPATIBILITY: Also export as IONVideoReactions for legacy code

console.log('✅ VideoReactions loaded and exported as:', {
    videoReactions: typeof window.videoReactions,
    VideoReactions: typeof window.VideoReactions,
    IONVideoReactions: typeof window.IONVideoReactions
});

// Auto-initialize on DOMContentLoaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.videoReactions.initAll();
    });
} else {
    window.videoReactions.initAll();
}