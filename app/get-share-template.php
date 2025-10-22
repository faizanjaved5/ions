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
    // Do not include session.php here to avoid redirects during XHR fetch
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
        <div class="enhanced-share-modal-content" style="position: relative; background: linear-gradient(135deg, #1a1a1a 0%, #0a0a0a 100%); border-radius: 16px; max-width: 720px; width: 92vw; max-height: 90vh; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.8); border: 1px solid #333;">
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
                
                <div class="share-platforms-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px;">
                    <?php
                    $platformOrder = ['facebook', 'twitter', 'whatsapp', 'linkedin', 'telegram', 'reddit', 'email', 'copy'];
                    foreach ($platformOrder as $platform):
                        if (!isset($share_data['platforms'][$platform])) continue;
                        if ($platform === 'copy') {
                    ?>
                        <button type="button" class="share-platform-btn" onclick="EnhancedIONShare.switchTab(<?= $video_id ?>, 'embed')" style="display: flex; align-items: center; gap: 8px; padding: 10px 12px; background: #2a2a2a; border: 1px solid #444; border-radius: 8px; color: white; font-size: 13px; transition: all 0.2s;" onmouseover="this.style.background='#333'" onmouseout="this.style.background='#2a2a2a'">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="#bbb"><path d="M8 5l-7 7 7 7v-4h4v-6H8V5zm8 0v4h-4v6h4v4l7-7-7-7z"/></svg>
                            <span>Embed</span>
                        </button>
                    <?php } else {
                            $purl = htmlspecialchars($share_data['platforms'][$platform]); ?>
                        <a href="javascript:void(0)" data-platform="<?= $platform ?>" class="share-platform-btn" onclick="EnhancedIONShare.openPlatform(<?= $video_id ?>, '<?= $platform ?>', '<?= $purl ?>')" style="display: flex; align-items: center; gap: 8px; padding: 10px 12px; background: #2a2a2a; border: 1px solid #444; border-radius: 8px; color: white; text-decoration: none; font-size: 13px; transition: all 0.2s;" onmouseover="this.style.background='#333'" onmouseout="this.style.background='#2a2a2a'">
                            <?= $share_manager->getPlatformIcon($platform) ?>
                            <span><?= $share_manager->getPlatformLabel($platform) ?></span>
                        </a>
                    <?php }
                    endforeach; ?>
                </div>
                
                <div id="share-meta-<?= $video_id ?>" data-title="<?= htmlspecialchars($share_data['title'], ENT_QUOTES) ?>" style="display:none"></div>
                
                <div class="share-compose" style="margin-top: 25px; display: flex; gap: 16px; align-items: center;">
                    <div style="width: 240px; flex: 0 0 240px; background: #0f0f0f; border: 1px solid #333; border-radius: 6px; overflow: hidden; display: flex; align-items: center; justify-content: center; min-height: 160px;">
                        <img src="<?= htmlspecialchars($share_data['thumbnail']) ?>" alt="Preview" style="width: 100%; height: auto; display: block;">
                    </div>
                    <div style="flex: 1;">
                        <div style="display:flex; justify-content: space-between; align-items:center; margin-bottom:6px;">
                            <label style="font-size: 14px; font-weight: 500; color: #ccc;">Message</label>
                            <span id="share-message-count-<?= $video_id ?>" style="font-size: 12px; color: #999;">0</span>
                        </div>
                        <textarea id="share-message-<?= $video_id ?>" placeholder="Write a message to share with your link..." oninput="EnhancedIONShare.updateShareMessage(<?= $video_id ?>)" style="width: 100%; min-height: 160px; padding: 12px 14px; background: #0f0f0f; border: 1px solid #444; border-radius: 6px; color: #ddd; font-family: 'Monaco','Menlo',monospace; font-size: 13px; line-height: 1.6; resize: vertical;"><?= htmlspecialchars("Check out this video!\n\n\"" . ($share_data['title'] ?? 'Awesome video') . "\"\n" . ($share_data['url'] ?? '')) ?></textarea>
                    </div>
                </div>
            </div>
            
            <div class="share-tab-content" id="embed-tab-<?= $video_id ?>" style="display: none; padding: 24px;">
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
                                <div style="background: #ddd; border-radius: 4px; <?= $embed_data['width'] === '100%' ? 'aspect-ratio: 16/9;' : 'width: ' . ($embed_data['width'] / 2) . 'px; height: ' . ($embed_data['height'] / 2) . 'px;' ?> display: flex; align-items: center; justify-content: center; color: #666; font-size: 12px; position: relative;">
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