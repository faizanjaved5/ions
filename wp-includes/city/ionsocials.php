<?php
/**
 * ION Social Sharing Component
 * Generates social media share buttons for the current page
 */

// Get current page URL and title
$share_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
$share_title = isset($city) ? "ION " . $city->city_name . " - Your Local Channel" : "ION Local Network";
$share_description = isset($city) ? "Discover local content, news, and events from " . $city->city_name . " on the ION Local Network." : "ION Local Network - Connecting Communities Through Local Content";

// URL encode for sharing
$encoded_url = urlencode($share_url);
$encoded_title = urlencode($share_title);
$encoded_description = urlencode($share_description);

// Social share URLs
$facebook_url = "https://www.facebook.com/sharer/sharer.php?u={$encoded_url}";
$twitter_url = "https://twitter.com/intent/tweet?url={$encoded_url}&text={$encoded_title}";
$linkedin_url = "https://www.linkedin.com/sharing/share-offsite/?url={$encoded_url}";
$whatsapp_url = "https://api.whatsapp.com/send?text={$encoded_title}%20{$encoded_url}";
$email_url = "mailto:?subject={$encoded_title}&body={$encoded_description}%20{$encoded_url}";
?>

<div class="footer-socials ion-share-buttons">
    <a href="<?= esc_url($facebook_url) ?>" 
       target="_blank" 
       rel="noopener noreferrer" 
       aria-label="Share on Facebook"
       onclick="window.open(this.href, 'facebook-share', 'width=580,height=400'); return false;">
        <svg><use href="#icon-facebook"/></svg>
    </a>
    
    <a href="<?= esc_url($twitter_url) ?>" 
       target="_blank" 
       rel="noopener noreferrer" 
       aria-label="Share on X (Twitter)"
       onclick="window.open(this.href, 'twitter-share', 'width=580,height=400'); return false;">
        <svg><use href="#icon-x"/></svg>
    </a>
    
    <a href="<?= esc_url($linkedin_url) ?>" 
       target="_blank" 
       rel="noopener noreferrer" 
       aria-label="Share on LinkedIn"
       onclick="window.open(this.href, 'linkedin-share', 'width=580,height=400'); return false;">
        <svg><use href="#icon-linkedin"/></svg>
    </a>
    
    <a href="<?= esc_url($whatsapp_url) ?>" 
       target="_blank" 
       rel="noopener noreferrer" 
       aria-label="Share on WhatsApp"
       data-action="share/whatsapp/share">
        <svg><use href="#icon-whatsapp"/></svg>
    </a>
    
    <a href="<?= esc_url($email_url) ?>" 
       aria-label="Share via Email">
        <svg><use href="#icon-email"/></svg>
    </a>
</div>

<style>
/* Social share button enhancements */
.ion-share-buttons a {
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}

.ion-share-buttons a::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
}

.ion-share-buttons a:hover::before {
    width: 50px;
    height: 50px;
}

/* Tooltip for share buttons */
.ion-share-buttons a::after {
    content: attr(aria-label);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%) translateY(-5px);
    background: #333;
    color: #fff;
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 12px;
    white-space: nowrap;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s, transform 0.3s;
}

.ion-share-buttons a:hover::after {
    opacity: 1;
    transform: translateX(-50%) translateY(-10px);
}

/* Mobile adjustments */
@media (max-width: 768px) {
    .ion-share-buttons a::after {
        display: none;
    }
}
</style>

<script>
// Enhanced social sharing with analytics
document.addEventListener('DOMContentLoaded', function() {
    const shareButtons = document.querySelectorAll('.ion-share-buttons a');
    
    shareButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const platform = this.getAttribute('aria-label').replace('Share on ', '').replace('Share via ', '');
            
            // Track share event (integrate with your analytics)
            if (typeof gtag !== 'undefined') {
                gtag('event', 'share', {
                    'method': platform,
                    'content_type': 'page',
                    'item_id': window.location.href
                });
            }
            
            // Log to console for debugging
            console.log('Shared on:', platform);
        });
    });
    
    // Copy URL functionality (optional - add a copy link button)
    const copyLinkButton = document.querySelector('.copy-link-button');
    if (copyLinkButton) {
        copyLinkButton.addEventListener('click', function(e) {
            e.preventDefault();
            
            const url = window.location.href;
            
            // Modern way to copy to clipboard
            if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(() => {
                    // Show success message
                    this.textContent = 'Copied!';
                    setTimeout(() => {
                        this.textContent = 'Copy Link';
                    }, 2000);
                }).catch(err => {
                    console.error('Failed to copy:', err);
                });
            } else {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = url;
                textArea.style.position = 'fixed';
                textArea.style.left = '-999999px';
                document.body.appendChild(textArea);
                textArea.select();
                
                try {
                    document.execCommand('copy');
                    this.textContent = 'Copied!';
                    setTimeout(() => {
                        this.textContent = 'Copy Link';
                    }, 2000);
                } catch (err) {
                    console.error('Failed to copy:', err);
                }
                
                document.body.removeChild(textArea);
            }
        });
    }
});
</script>