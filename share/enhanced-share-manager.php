<?php
/**
 * Enhanced ION Video Share Manager with Embed Support
 * Reusable sharing module for all video sharing functionality including embed codes
 */

require_once __DIR__ . '/../app/shortlink-manager.php';

class EnhancedIONShareManager {
    private $db;
    private $shortlink_manager;
    private $base_url;
    private $embed_domain;
    
    public function __construct($db, $base_url = null, $embed_domain = null) {
        $this->db = $db;
        $this->shortlink_manager = new VideoShortlinkManager($db);
        $this->base_url = $base_url ?: 'https://ions.com';
        $this->embed_domain = $embed_domain ?: 'https://ions.com';
    }
    
    /**
     * Get or generate share data for a video including embed codes
     */
    public function getShareData($video_id) {
        try {
            // Get video details
            $video = $this->db->get_row($this->db->prepare(
                "SELECT * FROM IONLocalVideos WHERE id = %d", 
                $video_id
            ));
            
            if (!$video) {
                return false;
            }
            
            // Get or generate shortlink
            $shortlink_data = $this->shortlink_manager->getVideoShortlink($video_id);
            
            if (!$shortlink_data) {
                // Generate shortlink if it doesn't exist
                $shortlink_data = $this->shortlink_manager->generateShortlink($video_id, $video->title);
            }
            
            if (!$shortlink_data) {
                return false;
            }
            
            // Prepare share data
            $share_url = $shortlink_data['url'];
            $title = $video->title ?: 'Watch this video';
            $description = $video->description ? 
                substr(strip_tags($video->description), 0, 160) : 
                "Watch {$title} on ION";
            $thumbnail = $video->thumbnail ?: $this->base_url . '/assets/default/ionthumbnail.png';
            
            return [
                'video_id' => $video_id,
                'title' => $title,
                'description' => $description,
                'url' => $share_url,
                'thumbnail' => $thumbnail,
                'clicks' => $shortlink_data['clicks'] ?? 0,
                'video_data' => $video,
                'platforms' => $this->generatePlatformUrls($share_url, $title, $description, $thumbnail),
                'embed_codes' => $this->generateEmbedCodes($video, $share_url, $title, $thumbnail)
            ];
            
        } catch (Exception $e) {
            error_log("Share data error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate embed codes for different platforms and sizes
     */
    private function generateEmbedCodes($video, $share_url, $title, $thumbnail) {
        $video_url = $video->video_link;
        $video_type = strtolower($video->source ?? 'local');
        $video_id = $video->video_id;
        
        // Base embed configuration
        $embed_configs = [
            'small' => ['width' => 320, 'height' => 180],
            'medium' => ['width' => 560, 'height' => 315],
            'large' => ['width' => 853, 'height' => 480],
            'responsive' => ['width' => '100%', 'height' => 'auto']
        ];
        
        $embed_codes = [];
        
        foreach ($embed_configs as $size => $dimensions) {
            $width = $dimensions['width'];
            $height = $dimensions['height'];
            
            // Generate the embed code based on the template from embed.php
            $embed_code = $this->generateVideoEmbedCode($video, $width, $height, $size === 'responsive');
            
            $embed_codes[$size] = [
                'name' => ucfirst($size) . ($size === 'responsive' ? ' (Responsive)' : " ({$width}x{$height})"),
                'width' => $width,
                'height' => $height,
                'code' => $embed_code
            ];
        }
        
        return $embed_codes;
    }
    
    /**
     * Generate the actual embed code HTML
     */
    private function generateVideoEmbedCode($video, $width, $height, $responsive = false) {
        $video_url = htmlspecialchars($video->video_link, ENT_QUOTES);
        $video_type = strtolower($video->source ?? 'local');
        $video_id = htmlspecialchars($video->video_id, ENT_QUOTES);
        $title = htmlspecialchars($video->title, ENT_QUOTES);
        $thumbnail = htmlspecialchars($video->thumbnail ?: $this->base_url . '/assets/default/ionthumbnail.png', ENT_QUOTES);
        $date_added = htmlspecialchars($video->date_added, ENT_QUOTES);
        
        // Determine video format for local videos
        $video_format = 'mp4';
        if ($video_type === 'local' || $video_type === 'upload') {
            $extension = pathinfo($video_url, PATHINFO_EXTENSION);
            $video_format = $extension ?: 'mp4';
            $video_type = 'local';
        }
        
        // Generate responsive wrapper if needed
        $wrapper_style = '';
        $container_style = '';
        
        if ($responsive) {
            $wrapper_style = 'style="position: relative; width: 100%; height: 0; padding-bottom: 56.25%; /* 16:9 aspect ratio */"';
            $container_style = 'style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;"';
            $width = '100%';
            $height = '100%';
        } else {
            $container_style = "style=\"width: {$width}px; height: {$height}px;\"";
        }
        
        $embed_code = <<<HTML
<!-- ION Video Player Embed -->
<div class="ion-video-embed" {$wrapper_style}>
    <div {$container_style}>
        <script>
            // Set video modal configuration
            window.VideoModalConfig = {
                autoplay: true,
                muted: false,
                theme: 'dark',
                playerTheme: 'flat',
                modalSize: 'medium',
                videoFormat: 'wide',
                showControls: true,
                requireAuth: false,
                loadingSpinner: true,
                trapFocus: true,
                customPlayers: {},
                cssVariables: {
                    '--modal-bg': 'rgba(20, 20, 20, 0.95)',
                    '--close-color': '#fff'
                }
            };
        </script>
        <script src="{$this->embed_domain}/includes/video-modal.js" data-auto-init="true"></script>
        
        <a class="video-thumb" 
           href="javascript:void(0)" 
           data-video-url="{$video_url}"
           data-video-type="{$video_type}" 
           data-video-format="{$video_format}" 
           data-video-id="{$video_id}"
           data-player-theme="flat"
           data-title="{$title}"
           data-published-date="{$date_added}"
           style="display: block; position: relative; width: 100%; height: 100%; text-decoration: none; border-radius: 8px; overflow: hidden;">
            <img src="{$thumbnail}" 
                 alt="{$title}" 
                 style="width: 100%; height: 100%; object-fit: cover; display: block;">
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(0,0,0,0.7); border-radius: 50%; width: 60px; height: 60px; display: flex; align-items: center; justify-content: center;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="white" style="margin-left: 2px;">
                    <path d="M8 5v14l11-7z"></path>
                </svg>
            </div>
            <div style="position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(transparent, rgba(0,0,0,0.8)); padding: 20px 10px 10px; color: white;">
                <p style="margin: 0; font-size: 14px; font-weight: 600; line-height: 1.3;">{$title}</p>
            </div>
        </a>
        <noscript>
            <div style="margin-top:8px;text-align:center;">
                <a href="{$video_url}" target="_blank" rel="noopener noreferrer" style="color:#3b82f6;">Open video</a>
            </div>
        </noscript>
    </div>
</div>
HTML;

        return $embed_code;
    }
    
    /**
     * Generate sharing URLs for all major platforms
     */
    private function generatePlatformUrls($url, $title, $description, $thumbnail) {
        $encoded_url = urlencode($url);
        $encoded_title = urlencode($title);
        $encoded_description = urlencode($description);
        $encoded_thumbnail = urlencode($thumbnail);
        
        return [
            'facebook' => "https://www.facebook.com/sharer/sharer.php?u={$encoded_url}",
            'twitter' => "https://twitter.com/intent/tweet?url={$encoded_url}&text={$encoded_title}",
            'linkedin' => "https://www.linkedin.com/sharing/share-offsite/?url={$encoded_url}",
            'whatsapp' => "https://wa.me/?text={$encoded_title}%20{$encoded_url}",
            'telegram' => "https://t.me/share/url?url={$encoded_url}&text={$encoded_title}",
            // Provide a Reddit submit URL with type=TEXT and empty body by default; JS fills body
            'reddit' => "https://www.reddit.com/submit?url={$encoded_url}&title={$encoded_title}&type=TEXT",
            'pinterest' => "https://pinterest.com/pin/create/button/?url={$encoded_url}&description={$encoded_title}&media={$encoded_thumbnail}",
            'email' => "mailto:?subject={$encoded_title}&body=Check%20out%20this%20video:%20{$encoded_url}",
            'copy' => $url
        ];
    }
    
    /**
     * Render enhanced share button with embed functionality
     */
    public function renderShareButton($video_id, $options = []) {
        $default_options = [
            'size' => 'medium',
            'style' => 'both',
            'platforms' => ['facebook', 'twitter', 'whatsapp', 'linkedin', 'telegram', 'reddit', 'email', 'copy'],
            'show_embed' => true,
            'trigger' => 'click'
        ];
        
        $options = array_merge($default_options, $options);
        
        $share_data = $this->getShareData($video_id);
        if (!$share_data) {
            return '<span class="share-error" style="color: #ef4444; font-size: 12px;">Share unavailable</span>';
        }
        
        $button_id = 'share-btn-' . $video_id;
        $modal_id = 'enhanced-share-modal-' . $video_id;
        
        // Default prefilled share message
        $prefill_message = "Check out this video!\n\n\"" . ($share_data['title'] ?? 'Awesome video') . "\"\n" . ($share_data['url'] ?? '');

        ob_start();
        ?>
        <button class="ion-share-button enhanced-share-button" 
                id="<?= $button_id ?>" 
                data-video-id="<?= $video_id ?>"
                title="Share & Embed this video"
                onclick="window.EnhancedIONShare.openFromTemplate(<?= $video_id ?>)"
                style="background: none; border: none; cursor: pointer; padding: 6px 8px; color: #3b82f6; border-radius: 6px; transition: all 0.2s; display: flex; align-items: center; gap: 4px; font-size: 12px;"
                onmouseover="this.style.backgroundColor='rgba(59, 130, 246, 0.1)'" 
                onmouseout="this.style.backgroundColor='transparent'">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.367 2.684 3 3 0 00-5.367-2.684z"/>
            </svg>
            <span>Share</span>
        </button>
        
        <!-- Per-video modal content stored as a template to avoid rendering inside cards -->
        <script type="text/template" id="enhanced-share-template-<?= $video_id ?>">
            <div class="enhanced-share-header" style="padding: 20px 24px; border-bottom: 1px solid #333; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 18px; font-weight: 600;">Share & Embed Video</h3>
                <button class="enhanced-share-close" style="background: none; border: none; color: #999; font-size: 24px; cursor: pointer; padding: 0; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 6px;" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='none'">&times;</button>
            </div>
            
            <div class="share-tabs" style="display: flex; border-bottom: 1px solid #333;">
                <button class="share-tab-btn active" onclick="EnhancedIONShare.switchTab(<?= $video_id ?>, 'share')" data-tab="share" style="flex: 1; padding: 12px 16px; background: none; border: none; color: white; cursor: pointer; border-bottom: 2px solid #3b82f6; transition: all 0.2s;">Share</button>
                <button class="share-tab-btn" onclick="EnhancedIONShare.switchTab(<?= $video_id ?>, 'embed')" data-tab="embed" style="flex: 1; padding: 12px 16px; background: none; border: none; color: #999; cursor: pointer; border-bottom: 2px solid transparent; transition: all 0.2s;">Embed</button>
            </div>
            
            <div class="share-tab-content" id="share-tab-<?= $video_id ?>" style="padding: 24px;">
                <div class="share-url-section" style="margin-bottom: 24px;">
                    <label style="display: block; margin-bottom: 8px; font-size: 16px; font-weight: 700; color: #e5e5e5;">Share Link</label>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <input type="text" value="<?= htmlspecialchars($share_data['url']) ?>" readonly id="enhanced-share-url-<?= $video_id ?>" style="flex: 1; padding: 8px 12px; background: #2a2a2a; border: 1px solid #444; border-radius: 6px; color: white; font-size: 14px;">
                        <button onclick="window.open('<?= htmlspecialchars($share_data['url'], ENT_QUOTES) ?>', '_blank')" title="Open link in new tab" style="padding: 8px 12px; background: #2a2a2a; color: #3b82f6; border: 1px solid #444; border-radius: 6px; cursor: pointer; font-size: 14px; white-space: nowrap; display: flex; align-items: center; transition: all 0.2s;" onmouseover="this.style.background='#333'; this.style.borderColor='#3b82f6'" onmouseout="this.style.background='#2a2a2a'; this.style.borderColor='#444'">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                <polyline points="15 3 21 3 21 9"></polyline>
                                <line x1="10" y1="14" x2="21" y2="3"></line>
                            </svg>
                        </button>
                        <button onclick="EnhancedIONShare.copyText('enhanced-share-url-<?= $video_id ?>')" style="padding: 8px 16px; background: #3b82f6; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; white-space: nowrap;">Copy</button>
                    </div>
                </div>
                
                <div class="share-platforms-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 12px;">
                    <?php foreach ($options['platforms'] as $platform): ?>
                        <?php if (isset($share_data['platforms'][$platform])): ?>
                            <?php if ($platform === 'copy'): ?>
                                <button type="button" class="share-platform-btn" onclick="EnhancedIONShare.switchTab(<?= $video_id ?>, 'embed')" style="display: flex; align-items: center; gap: 8px; padding: 10px 12px; background: #2a2a2a; border: 1px solid #444; border-radius: 8px; color: white; font-size: 13px; transition: all 0.2s;" onmouseover="this.style.background='#333'" onmouseout="this.style.background='#2a2a2a'">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="#bbb"><path d="M8 5l-7 7 7 7v-4h4v-6H8V5zm8 0v4h-4v6h4v4l7-7-7-7z"/></svg>
                                    <span>Embed</span>
                                </button>
                            <?php else: ?>
                                <?php $purl = htmlspecialchars($share_data['platforms'][$platform]); ?>
                                <a href="javascript:void(0)" data-platform="<?= $platform ?>" class="share-platform-btn" onclick="EnhancedIONShare.openPlatform(<?= $video_id ?>, '<?= $platform ?>', '<?= $purl ?>')" style="display: flex; align-items: center; gap: 8px; padding: 10px 12px; background: #2a2a2a; border: 1px solid #444; border-radius: 8px; color: white; text-decoration: none; font-size: 13px; transition: all 0.2s;" onmouseover="this.style.background='#333'" onmouseout="this.style.background='#2a2a2a'">
                                    <?= $this->getPlatformIcon($platform) ?>
                                    <span><?= $this->getPlatformLabel($platform) ?></span>
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <!-- Hidden meta for JS -->
                <div id="share-meta-<?= $video_id ?>" data-title="<?= htmlspecialchars($share_data['title'], ENT_QUOTES) ?>" style="display:none"></div>
                <!-- Compose area -->
                <div class="share-compose" style="margin-top: 25px; display: flex; gap: 16px; align-items: center;">
                    <div style="width: 240px; flex: 0 0 240px; background: #0f0f0f; border: 1px solid #333; border-radius: 6px; overflow: hidden; display: flex; align-items: center; justify-content: center; min-height: 160px;">
                        <img src="<?= htmlspecialchars($share_data['thumbnail']) ?>" alt="Preview" style="width: 100%; height: auto; display: block;">
                    </div>
                    <div style="flex: 1;">
                        <div style="display:flex; justify-content: space-between; align-items:center; margin-bottom:6px;">
                            <label style="font-size: 14px; font-weight: 500; color: #ccc;">Message</label>
                            <span id="share-message-count-<?= $video_id ?>" style="font-size: 12px; color: #999;">0</span>
                        </div>
                        <textarea id="share-message-<?= $video_id ?>" placeholder="Write a message to share with your link..." oninput="EnhancedIONShare.updateShareMessage(<?= $video_id ?>)" style="width: 100%; min-height: 160px; padding: 12px 14px; background: #0f0f0f; border: 1px solid #444; border-radius: 6px; color: #ddd; font-family: 'Monaco','Menlo',monospace; font-size: 13px; line-height: 1.6; resize: vertical;"><?= htmlspecialchars($prefill_message) ?></textarea>
                    </div>
                </div>
                
            </div>
            
            <div class="share-tab-content" id="embed-tab-<?= $video_id ?>" style="display: none; padding: 24px;">
                <!-- Compact header: title + info, centered size dropdown, right-aligned copy button -->
                <div class="embed-header" style="display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 10px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 14px; font-weight: 500; color: #ccc;">Embed Code</span>
                        <button title="How to use" onclick="var x=document.getElementById('embed-help-<?= $video_id ?>'); x.style.display = (x.style.display==='block'?'none':'block');" style="width: 22px; height: 22px; border-radius: 50%; border: 1px solid #555; background: #2a2a2a; color: #bbb; font-size: 13px; line-height: 20px; text-align: center; cursor: pointer;">i</button>
                    </div>
                    <div style="flex: 1; display: flex; justify-content: center;">
                        <select id="embed-size-select-<?= $video_id ?>" onchange="EnhancedIONShare.switchEmbedSize(<?= $video_id ?>, this.value)" aria-label="Embed Size" style="height: 36px; padding: 6px 12px; background: #2a2a2a; border: 1px solid #444; border-radius: 6px; color: white; font-size: 14px; min-width: 230px; text-align: center;">
                            <?php foreach ($share_data['embed_codes'] as $size => $embed_data): ?>
                                <option value="<?= $size ?>" <?= $size === 'medium' ? 'selected' : '' ?>><?= $embed_data['name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <button onclick="(function(){var sel=document.getElementById('embed-size-select-<?= $video_id ?>'); if(!sel) return; var id='embed-code-'+sel.value+'-<?= $video_id ?>'; EnhancedIONShare.copyText(id);})()" style="height: 36px; padding: 0 16px; background: #3b82f6; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 13px;">Copy Code</button>
                    </div>
                </div>
                <!-- Help panel (collapsed by default) -->
                <div id="embed-help-<?= $video_id ?>" style="display: none; background: #111827; border: 1px solid #2f2f2f; color: #ddd; border-radius: 8px; padding: 12px 14px; margin-bottom: 12px; font-size: 13px; line-height: 1.5;">
                    <div style="font-weight: 600; margin-bottom: 6px;">How to use this embed</div>
                    <div style="margin-bottom: 8px;">
                        <div style="font-weight: 600;">Any website (HTML):</div>
                        <div>1) Click "Copy Code" above.</div>
                        <div>2) Paste the code into your page where you want the player to appear.</div>
                        <div>3) Save and publish. The player will open in a modal when clicked.</div>
                    </div>
                    <div style="margin-bottom: 8px;">
                        <div style="font-weight: 600;">WordPress (Block Editor / Gutenberg):</div>
                        <div>1) Add a "Custom HTML" block.</div>
                        <div>2) Paste the copied embed code into the block.</div>
                        <div>3) Update the post/page and preview.</div>
                    </div>
                    <div>
                        <div style="font-weight: 600;">WordPress (Classic Editor):</div>
                        <div>1) Switch the editor to the "Text" tab.</div>
                        <div>2) Paste the embed code where you want the player.</div>
                        <div>3) Switch back to "Visual" if needed and publish.</div>
                    </div>
                </div>
                <?php foreach ($share_data['embed_codes'] as $size => $embed_data): ?>
                    <div class="embed-code-section embed-<?= $size ?>" id="embed-<?= $size ?>-<?= $video_id ?>" style="<?= $size !== 'medium' ? 'display: none;' : '' ?>">
                        <textarea id="embed-code-<?= $size ?>-<?= $video_id ?>" readonly style="width: 100%; height: 140px; padding: 12px; background: #0f0f0f; border: 1px solid #444; border-radius: 6px; color: #a0a0a0; font-family: 'Monaco', 'Menlo', monospace; font-size: 12px; line-height: 1.5; resize: vertical;"><?= htmlspecialchars($embed_data['code']) ?></textarea>
                        <div style="margin-top: 12px;">
                            <label style="display: block; margin-bottom: 8px; font-size: 14px; font-weight: 500; color: #ccc;">Preview</label>
                            <div style="background: #f5f5f5; border-radius: 6px; padding: 16px; <?= !$embed_data['width'] || $embed_data['width'] === '100%' ? '' : 'max-width: ' . $embed_data['width'] . 'px;' ?>">
                                <div style="background: #ddd; border-radius: 4px; <?= $embed_data['width'] === '100%' ? 'aspect-ratio: 16/9;' : 'width: ' . ($embed_data['width']/2) . 'px; height: ' . ($embed_data['height']/2) . 'px;' ?> display: flex; align-items: center; justify-content: center; color: #666; font-size: 12px; position: relative;">
                                    <img src="<?= htmlspecialchars($share_data['thumbnail']) ?>" alt="Preview" style="width: 100%; height: 100%; object-fit: cover; border-radius: 4px; opacity: 0.8;">
                                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(0,0,0,0.7); border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="white"><path d="M8 5v14l11-7z"></path></svg>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </script>
        
        <!-- Click binding handled via inline onclick attribute above to avoid duplicates -->
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get platform icon SVG
     */
    private function getPlatformIcon($platform) {
        $icons = [
            'facebook' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="#1877f2"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>',
            // X (formerly Twitter) logo
            'twitter' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="#fff" xmlns="http://www.w3.org/2000/svg"><path d="M18.244 2H21l-6.977 7.975L22.5 22h-5.873l-4.59-5.48L6.784 22H4l7.45-8.508L1.5 2h5.987l4.157 5.02L18.244 2Zm-1.03 18h1.62L7.863 4h-1.64l10.99 16Z"/></svg>',
            'whatsapp' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="#25d366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893A11.821 11.821 0 0020.893 3.085"/></svg>',
            'linkedin' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="#0077b5"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>',
            'telegram' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="#0088cc"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>',
            'reddit' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="#ff4500"><path d="M12 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0zm5.01 4.744c.688 0 1.25.561 1.25 1.249a1.25 1.25 0 0 1-2.498.056l-2.597-.547-.8 3.747c1.824.07 3.48.632 4.674 1.488.308-.309.73-.491 1.207-.491.968 0 1.754.786 1.754 1.754 0 .716-.435 1.333-1.01 1.614a3.111 3.111 0 0 1 .042.52c0 2.694-3.13 4.87-7.004 4.87-3.874 0-7.004-2.176-7.004-4.87 0-.183.015-.366.043-.534A1.748 1.748 0 0 1 4.028 12c0-.968.786-1.754 1.754-1.754.463 0 .898.196 1.207.49 1.207-.883 2.878-1.43 4.744-1.487l.885-4.182a.342.342 0 0 1 .14-.197.35.35 0 0 1 .238-.042l2.906.617a1.214 1.214 0 0 1 1.108-.701zM9.25 12C8.561 12 8 12.562 8 13.25c0 .687.561 1.248 1.25 1.248.687 0 1.248-.561 1.248-1.249 0-.688-.561-1.249-1.249-1.249zm5.5 0c-.687 0-1.248.561-1.248 1.25 0 .687.561 1.248 1.249 1.248.688 0 1.249-.561 1.249-1.249 0-.687-.562-1.249-1.25-1.249zm-5.466 3.99a.327.327 0 0 0-.231.094.33.33 0 0 0 0 .463c.842.842 2.484.913 2.961.913.477 0 2.105-.056 2.961-.913a.361.361 0 0 0 .029-.463.33.33 0 0 0-.464 0c-.547.533-1.684.73-2.512.73-.828 0-1.979-.196-2.512-.73a.326.326 0 0 0-.232-.095z"/></svg>',
            'pinterest' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="#bd081c"><path d="M12.017 0C5.396 0 .029 5.367.029 11.987c0 5.079 3.158 9.417 7.618 11.174-.105-.949-.199-2.403.041-3.439.219-.937 1.404-5.965 1.404-5.965s-.359-.72-.359-1.781c0-1.663.967-2.911 2.168-2.911 1.024 0 1.518.769 1.518 1.688 0 1.029-.653 2.567-.992 3.992-.285 1.193.6 2.165 1.775 2.165 2.128 0 3.768-2.245 3.768-5.487 0-2.861-2.063-4.869-5.008-4.869-3.41 0-5.409 2.562-5.409 5.199 0 1.033.394 2.143.889 2.741.099.12.112.225.085.345-.09.375-.293 1.199-.334 1.363-.053.225-.172.271-.402.165-1.495-.69-2.433-2.878-2.433-4.646 0-3.776 2.748-7.252 7.92-7.252 4.158 0 7.392 2.967 7.392 6.923 0 4.135-2.607 7.462-6.233 7.462-1.214 0-2.357-.629-2.75-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146C9.57 23.812 10.763 24.009 12.017 24c6.624 0 11.99-5.367 11.99-11.987C24.007 5.367 18.641.001 12.017.001z"/></svg>',
            'email' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="#666"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>',
            'copy' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="#666"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>'
        ];
        
        return $icons[$platform] ?? '';
    }

    private function getPlatformLabel($platform) {
        if ($platform === 'twitter') {
            return 'X.com';
        }
        return ucfirst($platform);
    }
}
?>