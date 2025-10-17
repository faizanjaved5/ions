<?php
/**
 * Get Share Template API
 * Returns the enhanced share modal template HTML for a video
 */

// Start output buffering
ob_start();

header('Content-Type: application/json');

try {
    // Load dependencies
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../login/session.php';
    require_once __DIR__ . '/../share/enhanced-share-manager.php';
    
    // Clear any buffered output from includes
    ob_clean();
    
    // Get video ID
    $video_id = intval($_GET['video_id'] ?? 0);
    
    if ($video_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid video ID']);
        exit();
    }
    
    // Verify video exists in database (security check)
    $video_check = $db->get_row($db->prepare(
        "SELECT id, user_id, status FROM IONLocalVideos WHERE id = %d",
        $video_id
    ));
    
    if (!$video_check) {
        echo json_encode(['success' => false, 'error' => 'Video not found']);
        exit();
    }
    
    // Optional: Check if video is shareable (not deleted, etc.)
    // We allow sharing even for pending/private videos since user just uploaded it
    
    // Initialize Enhanced Share Manager
    $share_manager = new EnhancedIONShareManager($db);
    
    // Get share data for this video
    $share_data = $share_manager->getShareData($video_id);
    
    if (!$share_data) {
        echo json_encode(['success' => false, 'error' => 'Video not found or share data unavailable']);
        exit();
    }
    
    // Generate the share template HTML
    // This mimics what renderShareButton does but returns just the template
    $modal_id = 'enhanced-share-modal-' . $video_id;
    
    // Default prefilled share message
    $prefill_message = "Check out this video!\n\n\"" . ($share_data['title'] ?? 'Awesome video') . "\"\n" . ($share_data['url'] ?? '');
    
    // Start building the template HTML
    ob_start();
    ?>
    <script type="text/template" id="enhanced-share-template-<?= $video_id ?>">
        <div class="enhanced-share-modal-content" style="position: relative; background: linear-gradient(135deg, #1a1a1a 0%, #0a0a0a 100%); border-radius: 16px; max-width: 600px; width: 90vw; max-height: 90vh; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.8); border: 1px solid #333;">
            <button class="enhanced-share-close" style="position: absolute; top: 16px; right: 16px; background: rgba(255,255,255,0.1); border: none; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 10; transition: all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                    <path d="M18 6L6 18M6 6l12 12"/>
                </svg>
            </button>
            
            <div class="share-tab-content" id="share-tab-<?= $video_id ?>" style="padding: 24px;">
                <h3 style="margin: 0 0 20px 0; font-size: 20px; font-weight: 700; color: #e5e5e5;">Share This Video</h3>
                
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
                
                <div class="share-platforms-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; margin-bottom: 20px;">
                    <?php 
                    $platforms = ['facebook', 'twitter', 'whatsapp', 'linkedin', 'telegram', 'reddit', 'email'];
                    foreach ($platforms as $platform): 
                        if (isset($share_data['platforms'][$platform])):
                            $purl = htmlspecialchars($share_data['platforms'][$platform]);
                    ?>
                        <a href="javascript:void(0)" data-platform="<?= $platform ?>" class="share-platform-btn" onclick="EnhancedIONShare.openPlatform(<?= $video_id ?>, '<?= $platform ?>', '<?= $purl ?>')" style="display: flex; align-items: center; gap: 8px; padding: 10px 12px; background: #2a2a2a; border: 1px solid #444; border-radius: 8px; color: white; text-decoration: none; font-size: 13px; transition: all 0.2s;" onmouseover="this.style.background='#333'" onmouseout="this.style.background='#2a2a2a'">
                            <?= $share_manager->getPlatformIcon($platform) ?>
                            <span><?= $share_manager->getPlatformLabel($platform) ?></span>
                        </a>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>
                
                <div id="share-meta-<?= $video_id ?>" data-title="<?= htmlspecialchars($share_data['title'], ENT_QUOTES) ?>" style="display:none"></div>
            </div>
        </div>
    </script>
    <?php
    
    $template_html = ob_get_clean();
    
    // Return success with HTML
    echo json_encode([
        'success' => true,
        'html' => $template_html,
        'video_id' => $video_id
    ]);
    
} catch (Exception $e) {
    error_log("Get share template error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}
?>

