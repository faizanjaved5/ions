<?php
/**
 * ION Video Share Manager
 * Reusable sharing module for all video sharing functionality
 */

require_once __DIR__ . '/../app/shortlink-manager.php';

class IONShareManager {
    private $db;
    private $shortlink_manager;
    private $base_url;
    
    public function __construct($db, $base_url = null) {
        $this->db = $db;
        $this->shortlink_manager = new VideoShortlinkManager($db);
        $this->base_url = $base_url ?: 'https://ions.com';
    }
    
    /**
     * Get or generate share data for a video
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
                'platforms' => $this->generatePlatformUrls($share_url, $title, $description, $thumbnail)
            ];
            
        } catch (Exception $e) {
            error_log("Share data error: " . $e->getMessage());
            return false;
        }
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
            'reddit' => "https://reddit.com/submit?url={$encoded_url}&title={$encoded_title}",
            'pinterest' => "https://pinterest.com/pin/create/button/?url={$encoded_url}&description={$encoded_title}&media={$encoded_thumbnail}",
            'email' => "mailto:?subject={$encoded_title}&body=Check%20out%20this%20video:%20{$encoded_url}",
            'copy' => $url
        ];
    }
    
    /**
     * Generate shortlinks for all videos that don't have them
     */
    public function generateMissingShortlinks($limit = 100) {
        try {
            // Get videos without shortlinks
            $videos = $this->db->get_results($this->db->prepare(
                "SELECT id, title FROM IONLocalVideos 
                 WHERE (short_link IS NULL OR short_link = '') 
                 AND status != 'deleted' 
                 ORDER BY id DESC 
                 LIMIT %d", 
                $limit
            ));
            
            if (!$videos) {
                return ['success' => true, 'processed' => 0, 'message' => 'No videos need shortlinks'];
            }
            
            $processed = 0;
            $errors = 0;
            
            foreach ($videos as $video) {
                $result = $this->shortlink_manager->generateShortlink($video->id, $video->title);
                if ($result) {
                    $processed++;
                } else {
                    $errors++;
                }
            }
            
            return [
                'success' => true,
                'processed' => $processed,
                'errors' => $errors,
                'message' => "Generated shortlinks for {$processed} videos" . ($errors ? " ({$errors} errors)" : "")
            ];
            
        } catch (Exception $e) {
            error_log("Bulk shortlink generation error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get share statistics
     */
    public function getShareStats($video_id = null) {
        try {
            if ($video_id) {
                // Stats for specific video
                $video = $this->db->get_row($this->db->prepare(
                    "SELECT id, title, short_link, clicks FROM IONLocalVideos WHERE id = %d",
                    $video_id
                ));
                
                return $video ? [
                    'video_id' => $video->id,
                    'title' => $video->title,
                    'has_shortlink' => !empty($video->short_link),
                    'clicks' => $video->clicks ?: 0
                ] : false;
            } else {
                // Overall stats
                $stats = $this->db->get_row(
                    "SELECT 
                        COUNT(*) as total_videos,
                        COUNT(short_link) as videos_with_links,
                        SUM(clicks) as total_clicks
                     FROM IONLocalVideos 
                     WHERE status != 'deleted'"
                );
                
                return [
                    'total_videos' => $stats->total_videos,
                    'videos_with_links' => $stats->videos_with_links,
                    'videos_without_links' => $stats->total_videos - $stats->videos_with_links,
                    'total_clicks' => $stats->total_clicks ?: 0,
                    'percentage_with_links' => $stats->total_videos > 0 ? 
                        round(($stats->videos_with_links / $stats->total_videos) * 100, 1) : 0
                ];
            }
            
        } catch (Exception $e) {
            error_log("Share stats error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Render share button HTML
     */
    public function renderShareButton($video_id, $options = []) {
        $default_options = [
            'size' => 'medium',  // small, medium, large
            'style' => 'icon',   // icon, text, both
            'platforms' => ['facebook', 'twitter', 'whatsapp', 'copy'], // default platforms
            'trigger' => 'click' // click, hover
        ];
        
        $options = array_merge($default_options, $options);
        
        $share_data = $this->getShareData($video_id);
        if (!$share_data) {
            return '<span class="share-error">Share unavailable</span>';
        }
        
        $button_id = 'share-btn-' . $video_id;
        $modal_id = 'share-modal-' . $video_id;
        
        ob_start();
        ?>
        <button class="ion-share-button ion-share-<?= $options['size'] ?>" 
                id="<?= $button_id ?>" 
                data-video-id="<?= $video_id ?>"
                title="Share this video">
            <?php if ($options['style'] === 'icon' || $options['style'] === 'both'): ?>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92s2.92-1.31 2.92-2.92S19.61 16.08 18 16.08z"/>
                </svg>
            <?php endif; ?>
            <?php if ($options['style'] === 'text' || $options['style'] === 'both'): ?>
                <span class="share-text">Share</span>
            <?php endif; ?>
        </button>
        
        <!-- Share Modal -->
        <div class="ion-share-modal" id="<?= $modal_id ?>" style="display: none;">
            <div class="ion-share-modal-content">
                <div class="ion-share-header">
                    <h3>Share this video</h3>
                    <button class="ion-share-close" onclick="IONShare.closeModal('<?= $modal_id ?>')">&times;</button>
                </div>
                
                <div class="ion-share-url">
                    <input type="text" value="<?= htmlspecialchars($share_data['url']) ?>" readonly id="share-url-<?= $video_id ?>">
                    <button onclick="IONShare.copyUrl('share-url-<?= $video_id ?>')">Copy</button>
                </div>
                
                <div class="ion-share-platforms">
                    <?php foreach ($options['platforms'] as $platform): ?>
                        <?php if (isset($share_data['platforms'][$platform])): ?>
                            <a href="<?= htmlspecialchars($share_data['platforms'][$platform]) ?>" 
                               target="_blank" 
                               class="ion-share-platform ion-share-<?= $platform ?>">
                                <?= $this->getPlatformIcon($platform) ?>
                                <span><?= ucfirst($platform) ?></span>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($share_data['clicks'] > 0): ?>
                    <div class="ion-share-stats">
                        Views: <?= number_format($share_data['clicks']) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
            document.getElementById('<?= $button_id ?>').addEventListener('click', function() {
                IONShare.openModal('<?= $modal_id ?>');
            });
        </script>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Get platform icon SVG
     */
    private function getPlatformIcon($platform) {
        $icons = [
            'facebook' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="#1877f2"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>',
            'twitter' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="#1da1f2"><path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/></svg>',
            'whatsapp' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="#25d366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893A11.821 11.821 0 0020.893 3.085"/></svg>',
            'linkedin' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="#0077b5"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>',
            'telegram' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="#0088cc"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>',
            'reddit' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="#ff4500"><path d="M12 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0zm5.01 4.744c.688 0 1.25.561 1.25 1.249a1.25 1.25 0 0 1-2.498.056l-2.597-.547-.8 3.747c1.824.07 3.48.632 4.674 1.488.308-.309.73-.491 1.207-.491.968 0 1.754.786 1.754 1.754 0 .716-.435 1.333-1.01 1.614a3.111 3.111 0 0 1 .042.52c0 2.694-3.13 4.87-7.004 4.87-3.874 0-7.004-2.176-7.004-4.87 0-.183.015-.366.043-.534A1.748 1.748 0 0 1 4.028 12c0-.968.786-1.754 1.754-1.754.463 0 .898.196 1.207.49 1.207-.883 2.878-1.43 4.744-1.487l.885-4.182a.342.342 0 0 1 .14-.197.35.35 0 0 1 .238-.042l2.906.617a1.214 1.214 0 0 1 1.108-.701zM9.25 12C8.561 12 8 12.562 8 13.25c0 .687.561 1.248 1.25 1.248.687 0 1.248-.561 1.248-1.249 0-.688-.561-1.249-1.249-1.249zm5.5 0c-.687 0-1.248.561-1.248 1.25 0 .687.561 1.248 1.249 1.248.688 0 1.249-.561 1.249-1.249 0-.687-.562-1.249-1.25-1.249zm-5.466 3.99a.327.327 0 0 0-.231.094.33.33 0 0 0 0 .463c.842.842 2.484.913 2.961.913.477 0 2.105-.056 2.961-.913a.361.361 0 0 0 .029-.463.33.33 0 0 0-.464 0c-.547.533-1.684.73-2.512.73-.828 0-1.979-.196-2.512-.73a.326.326 0 0 0-.232-.095z"/></svg>',
            'pinterest' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="#bd081c"><path d="M12.017 0C5.396 0 .029 5.367.029 11.987c0 5.079 3.158 9.417 7.618 11.174-.105-.949-.199-2.403.041-3.439.219-.937 1.404-5.965 1.404-5.965s-.359-.72-.359-1.781c0-1.663.967-2.911 2.168-2.911 1.024 0 1.518.769 1.518 1.688 0 1.029-.653 2.567-.992 3.992-.285 1.193.6 2.165 1.775 2.165 2.128 0 3.768-2.245 3.768-5.487 0-2.861-2.063-4.869-5.008-4.869-3.41 0-5.409 2.562-5.409 5.199 0 1.033.394 2.143.889 2.741.099.12.112.225.085.345-.09.375-.293 1.199-.334 1.363-.053.225-.172.271-.402.165-1.495-.69-2.433-2.878-2.433-4.646 0-3.776 2.748-7.252 7.92-7.252 4.158 0 7.392 2.967 7.392 6.923 0 4.135-2.607 7.462-6.233 7.462-1.214 0-2.357-.629-2.75-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146C9.57 23.812 10.763 24.009 12.017 24c6.624 0 11.99-5.367 11.99-11.987C24.007 5.367 18.641.001 12.017.001z"/></svg>',
            'email' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="#666"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>',
            'copy' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="#666"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>'
        ];
        
        return $icons[$platform] ?? '';
    }
}
?>
