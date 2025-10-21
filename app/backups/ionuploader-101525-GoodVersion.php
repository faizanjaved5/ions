<?php
// ION Media Upload Modal Component
// Supports Video, Audio, and Image uploads with Bulk functionality
// Load config file (fixed path)
$config = require_once __DIR__ . '/../config/config.php';

// Load ION categories early (needed for JavaScript config)
require_once __DIR__ . '/../includes/ioncategories.php';

session_start();
$theme = $_SESSION['theme'] ?? $_COOKIE['theme'] ?? $_GET['theme'] ?? 'dark';

// Get user role for access control
$user_role = $_SESSION['user_role'] ?? 'Guest';
$canManageBadges = in_array($user_role, ['Owner', 'Admin']);
// Compute asset base path for CSS/JS (resolves correctly even in iframes/includes)
$documentRoot = rtrim(str_replace('\\','/', $_SERVER['DOCUMENT_ROOT']), '/');
$currentDir = rtrim(str_replace('\\','/', __DIR__), '/');
$assetBase = rtrim(str_replace($documentRoot, '', $currentDir), '/') . '/';
// Check if this is being loaded directly (not in iframe)
$isDirect = isset($_GET['direct']) && $_GET['direct'] === '1';
$isIframe = !$isDirect;
// Check for edit mode
$isEditMode = isset($_GET['edit_mode']) && $_GET['edit_mode'] === '1';
$editVideoData = [];
if ($isEditMode) {
    // Get edit data from URL parameters - include all parameters that creators.php passes
    $editVideoData = [
        'id' => $_GET['video_id'] ?? '',
        'title' => $_GET['title'] ?? '',
        'description' => $_GET['description'] ?? '',
        'category' => $_GET['category'] ?? '',
        'tags' => $_GET['tags'] ?? '',
        'visibility' => $_GET['visibility'] ?? 'public',
        'thumbnail' => $_GET['thumbnail'] ?? '',
        'source' => $_GET['source'] ?? '',
        'video_link' => $_GET['video_link'] ?? '',
        'video_id' => $_GET['provider_video_id'] ?? '', // Map provider_video_id to video_id for player
        'provider_video_id' => $_GET['provider_video_id'] ?? ''
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ION Media Uploader</title>
    <!-- Styles -->
    <link rel="stylesheet" href="<?= $assetBase ?>ionuploader.css?v=101&t=<?= time() ?>&bust=<?= md5(time() . rand()) ?>">
    
    <!-- Force spacing fixes with inline styles -->
    <style>
        /* Force video preview column spacing */
        .video-preview-column {
            gap: 10px !important;
        }
        
        /* Force form header spacing */
        .form-header h3 {
            margin: 0 0 2px 0 !important;
        }
        
        /* Force video player container spacing */
        .video-player-container {
            margin: 0 !important;
            padding: 0 !important;
            border-radius: 10px !important;
        }
        
        /* Force thumbnail section spacing */
        .thumbnail-section {
            padding: 10px 16px 16px 16px !important;
        }
        
        .thumbnail-container {
            margin-bottom: 8px !important;
        }
        
        /* Force scrollbar visibility */
        #step1 {
            padding: 32px !important;
            flex: 1 !important;
            overflow-y: auto !important;
            scrollbar-width: thin !important;
            scrollbar-color: rgba(178, 130, 84, 0.6) rgba(30, 41, 59, 0.3) !important;
        }
        
        #step2 {
            overflow-y: auto !important;
            scrollbar-width: thin !important;
            scrollbar-color: rgba(178, 130, 84, 0.6) rgba(30, 41, 59, 0.3) !important;
        }
        
        /* Webkit scrollbar styles */
        #step1::-webkit-scrollbar,
        #step2::-webkit-scrollbar {
            width: 6px !important;
        }
        
        #step1::-webkit-scrollbar-track,
        #step2::-webkit-scrollbar-track {
            background: rgba(30, 41, 59, 0.3) !important;
            border-radius: 3px !important;
        }
        
        #step1::-webkit-scrollbar-thumb,
        #step2::-webkit-scrollbar-thumb {
            background: rgba(178, 130, 84, 0.6) !important;
            border-radius: 3px !important;
        }
        
        #step1::-webkit-scrollbar-thumb:hover,
        #step2::-webkit-scrollbar-thumb:hover {
            background: rgba(178, 130, 84, 0.8) !important;
        }
        
        /* Import Sources Card - Purple Glow Effect */
        .import-sources-card {
            background: rgba(var(--card-rgb, 30, 41, 59), 0.8);
        }
        
        .import-sources-card:hover {
            border-color: rgb(168, 85, 247) !important; /* purple-500 */
            background: rgba(168, 85, 247, 0.05) !important; /* purple-500/5 */
            box-shadow: 0 0 0 1px hsl(270.9, 100%, 50%) !important; /* purple ring */
        }
        
        /* Force modal footer height - adjusted to fit buttons properly */
        .modal-footer {
            padding: 12px 16px !important;
            height: 50px !important;
            min-height: 50px !important;
        }
        
        /* Remove outer modal scrollbar - only content areas should scroll */
        .modal-container {
            overflow: hidden !important;
        }
        
        .modal-content {
            overflow: hidden !important;
        }
        
        /* Ensure proper height for modal to prevent outer scrolling */
        .modal {
            overflow: hidden !important;
        }
        
        /* Remove wasted space from video preview section */
        .video-preview-section {
            flex: unset !important;
        }
        
        /* Apply custom scrollbars to textareas and description fields */
        textarea,
        .form-textarea,
        .video-form-textarea,
        .field-textarea {
            scrollbar-width: thin !important;
            scrollbar-color: rgba(178, 130, 84, 0.6) rgba(30, 41, 59, 0.3) !important;
        }
        
        /* Webkit scrollbar styles for textareas */
        textarea::-webkit-scrollbar,
        .form-textarea::-webkit-scrollbar,
        .video-form-textarea::-webkit-scrollbar,
        .field-textarea::-webkit-scrollbar {
            width: 6px !important;
        }
        
        textarea::-webkit-scrollbar-track,
        .form-textarea::-webkit-scrollbar-track,
        .video-form-textarea::-webkit-scrollbar-track,
        .field-textarea::-webkit-scrollbar-track {
            background: rgba(30, 41, 59, 0.3) !important;
            border-radius: 3px !important;
        }
        
        textarea::-webkit-scrollbar-thumb,
        .form-textarea::-webkit-scrollbar-thumb,
        .video-form-textarea::-webkit-scrollbar-thumb,
        .field-textarea::-webkit-scrollbar-thumb {
            background: rgba(178, 130, 84, 0.6) !important;
            border-radius: 3px !important;
        }
        
        textarea::-webkit-scrollbar-thumb:hover,
        .form-textarea::-webkit-scrollbar-thumb:hover,
        .video-form-textarea::-webkit-scrollbar-thumb:hover,
        .field-textarea::-webkit-scrollbar-thumb:hover {
            background: rgba(178, 130, 84, 0.8) !important;
        }
    </style>
    <script>
        // Configuration for Google Drive
        const CLIENT_ID = '<?= $config['google_drive_clientid'] ?? '' ?>';
        const API_KEY = '<?= $config['google_drive_api_key'] ?? '' ?>';
        
        // Edit mode configuration
        const isEditMode = <?= $isEditMode ? 'true' : 'false' ?>;
        let editVideoId = '<?= $editVideoData['id'] ?? '' ?>';
        let editVideoData = <?= json_encode($editVideoData) ?>;
        
        // User role configuration
        const userRole = '<?= $user_role ?>';
        const canManageBadges = <?= $canManageBadges ? 'true' : 'false' ?>;
        
        // ION CATEGORIES - Centralized category list from ioncategories.php
        const ION_CATEGORIES = <?php 
            $ion_categories_js = [];
            foreach ($ion_categories as $value => $display_name) {
                $ion_categories_js[] = ['value' => $value, 'label' => $display_name];
            }
            echo json_encode($ion_categories_js); 
        ?>;
        
        console.log('📁 ION Categories loaded:', ION_CATEGORIES.length, 'categories');
    </script>
    <!-- Google APIs -->
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <script src="https://apis.google.com/js/api.js" async defer onload="initGapi()"></script>
    <script src="https://apis.google.com/js/api.js?onload=onApiLoad" async defer></script>
    <script src="https://www.gstatic.com/driveembed/views/picker.js" async defer></script>
    <script>
        function initGapi() {
            gapi.load('client:picker', () => {
                console.log('GAPI loaded');
            });
        }
    </script>
</head>
<body data-theme="<?= $theme ?>" <?= $isIframe ? 'style="margin: 0; padding: 0;"' : '' ?>>
    <?php if ($isIframe): ?>
        <!-- Iframe mode - no overlay, direct container -->
        <div class="modal-container" style="width: 100vw; height: 100vh; border-radius: 0; margin: 0; overflow-y: auto;">
    <?php else: ?>
        <!-- Direct mode - with overlay -->
        <div class="modal-overlay">
            <div class="modal-container">
    <?php endif; ?>
            <!-- Header -->
            <div class="modal-header">
                <div>
                    <h1 class="modal-title-text"><?= $isEditMode ? 'EDIT MEDIA' : 'UP YOURS!' ?></h1>
                </div>
                <div class="ion-logo">
                    <img src="https://ions.com/menu/ion-logo-gold.png" alt="ION Network" class="h-[70px] w-auto" style="margin-right: 100px;margin-bottom: -15px;margin-top: -15px;height: 70px;">
                </div>
                <button class="close-btn" id="closeModalBtn">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
           
            <!-- Upload Status -->
            <div id="uploadStatus" style="display: none;"></div>
            <!-- Body -->
            <div class="modal-body">
                <!-- Step 1: Source Selection -->
                <div id="step1" class="step1-content" <?= $isEditMode ? 'style="display: none;"' : '' ?>>
                    <div class="upload-options">
                        <!-- Upload from Computer -->
                        <div id="uploadOption" class="upload-option">
                            <div class="option-title">
                                <svg class="option-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    <polyline points="17,8 12,3 7,8"></polyline>
                                    <line x1="12" y1="3" x2="12" y2="15"></line>
                                </svg>
                                Upload from Computer
                            </div>
                            <div class="upload-zone" id="uploadZone">
                                <input type="file" id="fileInput" class="file-input" multiple accept="video/*,audio/*,image/*,.pdf,.doc,.docx,.txt" />
                                <svg class="upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    <polyline points="17,8 12,3 7,8"></polyline>
                                    <line x1="12" y1="3" x2="12" y2="15"></line>
                                </svg>
                                <div class="upload-text">Drop media files or click to select</div>
                                <div class="upload-subtext">Video, Audio, Image, Documents • Max sizes vary by type</div>
                            </div>
                           
                            <div class="upload-requirements">
                                <h4>Media Requirements:</h4>
                                <ul>
                                    <li>• <strong>Video:</strong> MP4, WebM, MOV, OGG, AVI • Max 20GB</li>
                                    <li>• <strong>Audio:</strong> MP3, WAV, FLAC, AAC, M4A, OGG • Max 500MB</li>
                                    <li>• <strong>Images:</strong> JPG, PNG, GIF, WebP, BMP, SVG • Max 50MB</li>
                                    <li>• Hold Ctrl/Cmd to select multiple files</li>
                                </ul>
                            </div>
                        </div>
                        <!-- Import from Sources with Purple Glow -->
                        <div class="import-sources-wrapper" style="position: relative; display: flex; flex-direction: column; height: 100%;">
                            <!-- Purple glow background -->
                            <div class="purple-glow" style="position: absolute; inset: -0.125rem; background: linear-gradient(to right, rgba(168, 85, 247, 0.2), rgba(236, 72, 153, 0.2)); border-radius: 1rem; filter: blur(8px); opacity: 0.3; pointer-events: none;"></div>
                            
                            <!-- Main card with hover effects -->
                            <div id="importOption" class="upload-option import-sources-card" style="position: relative; backdrop-filter: blur(4px); border: 1px solid rgba(168, 85, 247, 0.2); border-radius: 1rem; transition: all 0.2s; display: flex; flex-direction: column; height: 100%;">
                                <div class="option-title">
                                <svg class="option-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <path d="M2 12h20"></path>
                                    <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                                </svg>
                                Import from Sources
                            </div>
                           
                            <div class="import-sources">
                                <div class="source-btn" data-source="youtube">
                                    <span class="source-emoji"><img src="../../assets/icons/youtube.svg" alt="YouTube Icon" width="20" height="20"></span>
                                    YouTube
                                </div>
                               
                                <div class="gd-wrapper" style="position: relative;">
                                    <div class="source-btn google-drive-btn" data-source="googledrive">
                                        <span class="source-emoji"><img src="../../assets/icons/google-drive.svg" alt="Google Drive Icon" width="20" height="20"></span>
                                        Google Drive
                                        <span class="dropdown-arrow" id="googleDriveArrow" style="display: none;">▼</span>
                                    </div>
                                    <div class="google-drive-dropdown" id="googleDriveDropdown" style="display: none;">
                                        <div class="dropdown-header">Connected Drives</div>
                                        <div class="connected-drives" id="connectedDrives">
                                            <div class="no-drives">No drives connected</div>
                                        </div>
                                        <div class="dropdown-divider"></div>
                                        <div class="dropdown-item" data-action="add-drive">
                                            <span class="dropdown-icon">➕</span>
                                            Add New Drive
                                        </div>
                                        <div class="dropdown-item" data-action="clear-connections" style="color: var(--error-color);">
                                            <span class="dropdown-icon">🗑️</span>
                                            Clear All Connections
                                        </div>
                                        <div class="dropdown-divider"></div>
                                        <div class="dropdown-item" data-action="debug-status" style="font-size: 12px; color: var(--text-muted);">
                                            <span class="dropdown-icon">🛠</span>
                                            Debug Status
                                        </div>
                                    </div>
                                </div>
                               
                                <div class="source-btn" data-source="muvi">
                                    <span class="source-emoji"><img src="../../assets/icons/muvi.svg" alt="Muvi Icon" width="20" height="20"></span>
                                    Muvi
                                </div>
                               
                                <div class="source-btn" data-source="vimeo">
                                    <span class="source-emoji"><img src="../../assets/icons/vimeo.svg" alt="Vimeo Icon" width="20" height="20"></span>
                                    Vimeo
                                </div>
                               
                                <div class="source-btn" data-source="wistia">
                                    <span class="source-emoji"><img src="../../assets/icons/wistia.svg" alt="Wistia Icon" width="20" height="20"></span>
                                    Wistia
                                </div>
                               
                                <div class="source-btn" data-source="loom">
                                    <span class="source-emoji"><img src="../../assets/icons/loom.svg" alt="Loom Icon" width="20" height="20"></span>
                                    Loom
                                </div>
                            </div>
                           
                            <div class="url-input-section hidden" id="urlInputSection">
                                <label for="urlInput" class="input-label">Enter URL</label>
                                <input type="url" id="urlInput" class="url-input" placeholder="Enter URL" />
                            </div>
                           
                            <div class="import-info">
                                <h4>Import Options:</h4>
                                <p>• <strong>YouTube, Vimeo, Muvi, Wistia, Loom:</strong> Video URL only<br>
                                • <strong>Google Drive:</strong> All supported media types<br>
                                • <strong>Multi-select:</strong> Available for Google Drive imports<br>
                                • Media will be optimized for streaming</p>
                            </div>
                           
                            <!-- Google Drive specific elements -->
                            <div class="selected-file-info" id="selectedFileInfo" style="display: none; margin-top: 16px; padding: 12px; background: rgba(59, 130, 246, 0.1); border-radius: 8px; font-size: 13px;">
                                <div class="file-name" id="selectedFileName" style="font-weight: 500;"></div>
                                <div class="file-size" id="selectedFileSize" style="opacity: 0.7; margin-top: 4px;"></div>
                            </div>
                            </div> <!-- Close import-sources-card -->
                        </div> <!-- Close import-sources-wrapper -->
                    </div>
                </div>
                
                
                <!-- Step 2: Media Details -->
                <div id="step2" class="step2-content" <?= $isEditMode ? 'style="display: grid;"' : 'style="display: none;"' ?>>
                    <!-- Left Column: Media Preview & Thumbnail -->
                    <div class="video-preview-column">
                        <div class="video-preview-section">
                            <div class="video-player-container" id="videoPlayerContainer">
                                <!-- Media player will be inserted here -->
                            </div>
                        </div>
                       
                        <div class="thumbnail-section">
                            <h4>Thumbnail</h4>
                            <div class="thumbnail-container" id="thumbnailContainer">
                                <div class="thumbnail-placeholder" id="thumbnailPlaceholder">
                                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                        <path d="M21 15l-5-5L5 21"></path>
                                    </svg>
                                    <div>Click to upload custom thumbnail</div>
                                    <small>or use auto-generated</small>
                                </div>
                                <img id="thumbnailPreview" class="thumbnail-preview" style="display: none;" />
                            </div>
                            <div class="thumbnail-controls">
                                <button type="button" class="btn-thumbnail" id="uploadThumbnailBtn">
                                    📷 Upload Custom
                                </button>
                                <button type="button" class="btn-thumbnail" id="urlThumbnailBtn">
                                    🔗 From URL
                                </button>
                                <button type="button" class="btn-thumbnail" id="generateThumbnailBtn" style="display: none;">
                                    🎬 Capture Frame
                                </button>
                            </div>
                            <input type="file" id="thumbnailInput" accept="image/*" style="display: none;">
                           
                            <!-- Thumbnail URL Dialog Overlay -->
                            <div class="thumbnail-url-overlay" id="thumbnailUrlOverlay" style="display: none;">
                                <div class="thumbnail-url-dialog">
                                    <div class="dialog-header">
                                        <h4>🔗 Thumbnail from URL</h4>
                                        <button type="button" class="close-btn" id="closeThumbnailDialogBtn">&times;</button>
                                    </div>
                                    <div class="dialog-content">
                                        <label for="thumbnailUrlInput">Enter image URL:</label>
                                        <input type="url"
                                               id="thumbnailUrlInput"
                                               class="thumbnail-url-input"
                                               placeholder="https://example.com/image.jpg"
                                               autocomplete="off">
                                        <div class="supported-formats">
                                            <small>Supported formats: .jpeg, .jpg, .png, .webp, .avif, .svg, .tiff</small>
                                        </div>
                                        <div class="dialog-actions">
                                            <button type="button" class="btn-secondary" id="cancelThumbnailBtn">Cancel</button>
                                            <button type="button" class="btn-primary" id="applyThumbnailBtn">Apply Thumbnail</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="video-info" id="videoInfo">
                            <!-- Media info will be inserted here -->
                        </div>
                    </div>
                   
                    <!-- Right Column: Metadata Form -->
                    <div class="metadata-form-column">
                        <div class="form-header">
                            <h3>Enter Media Details</h3>
                        </div>
                       
                        <div class="form-group">
                            <label for="videoTitle">Media Title *
                                <span class="char-count"><span id="titleCount">0</span>/100 characters</span>
                            </label>
                            <input type="text" id="videoTitle" class="form-input" placeholder="Enter media title" maxlength="100" required value="<?= htmlspecialchars($editVideoData['title'] ?? '') ?>">
                        </div>
                       
                        <div class="form-group">
                            <label for="videoDescription">Description
                                <span class="char-count"><span id="descCount">0</span>/2000 characters</span>
                            </label>
                            <textarea id="videoDescription" class="form-input form-textarea" placeholder="Describe your media" maxlength="2000"><?= htmlspecialchars($editVideoData['description'] ?? '') ?></textarea>
                        </div>
                       
                        <!-- ION Category and Badges - Side by Side -->
                        <div class="form-row">
                            <div class="form-group">
                                <label for="videoCategory">ION Category</label>
                                <select id="videoCategory" class="form-input">
                                    <option value="">Select category...</option>
                                    <?php
                                    // ioncategories.php already loaded at top of file
                                    echo generate_ion_category_options($editVideoData['category'] ?? '', false);
                                    ?>
                                </select>
                            </div>
                            
                            <?php if ($canManageBadges): ?>
                            <div class="form-group">
                                <label for="videoBadges">ION Badges</label>
                                <div class="badge-input-container">
                                    <div class="badge-input" id="badgeInput" contenteditable="false" data-placeholder="Click to add badges...">
                                        <!-- Selected badges will appear here -->
                                    </div>
                                    <div class="badge-dropdown" id="badgeDropdown" style="display: none;">
                                        <div class="badge-option" data-value="featured">Featured</div>
                                        <div class="badge-option" data-value="favorites">Favorites</div>
                                        <div class="badge-option" data-value="editor-choice">Editor Choice</div>
                                        <div class="badge-option" data-value="spotlight">Spotlight</div>
                                    </div>
                                </div>
                                <input type="hidden" id="videoBadges" name="videoBadges" value="">
                            </div>
                            <?php endif; ?>
                        </div>
                       
                        <div class="form-group">
                            <label for="channelSearch">
                                ION Channels 
                                <span class="tooltip" title="Search and select channels where this video will appear. First channel = Primary.">?</span>
                            </label>
                            <div class="channel-search-container">
                                <input type="text" 
                                       id="channelSearch" 
                                       class="form-input" 
                                       placeholder="Search channels by city, state, or zip..."
                                       autocomplete="off">
                                <div id="channelSearchResults" class="channel-search-results"></div>
                            </div>
                            
                            <!-- Selected Channels List -->
                            <div id="selectedChannelsList" class="selected-channels-list" style="margin-top: 12px;">
                                <!-- Selected channels will appear here -->
                            </div>
                            
                            <!-- Hidden input to store selected channels for form submission -->
                            <input type="hidden" id="selectedChannels" name="selected_channels" value="">
                        </div>
                       
                        <div class="form-group">
                            <label for="videoTags">Tags</label>
                            <input type="text" id="videoTags" class="form-input" placeholder="Enter tags separated by commas" value="<?= htmlspecialchars($editVideoData['tags'] ?? '') ?>">
                        </div>
                       
                        <div class="form-group">
                            <label for="videoVisibility">Visibility <span class="tooltip" title="Public media can be viewed by anyone. Private media requires authentication.">?</span></label>
                            <select id="videoVisibility" class="form-input">
                                <option value="public" <?= ($editVideoData['visibility'] ?? 'public') === 'public' ? 'selected' : '' ?>>🌐 Public - Anyone can view this media</option>
                                <option value="private" <?= ($editVideoData['visibility'] ?? 'public') === 'private' ? 'selected' : '' ?>>🔒 Private - Only you and admins can view this media</option>
                                <option value="unlisted" <?= ($editVideoData['visibility'] ?? 'public') === 'unlisted' ? 'selected' : '' ?>>🔗 Unlisted - Public but only accessible with unique link</option>
                            </select>
                        </div>
                       
                        <!-- Progress bar for upload -->
                        <div class="progress-container" id="progressContainer" style="display: none;">
                            <div class="progress-bar">
                                <div class="progress-fill" id="progressBar"></div>
                            </div>
                            <div class="progress-text" id="progressText">Preparing upload...</div>
                        </div>
                    </div>
                </div>
                
                <!-- Step 2B: Bulk Upload Interface -->
                <div id="bulkStep2" class="bulk-step2-content" style="display: none;">
                    <div class="bulk-header">
                        <div class="bulk-title">
                            <h3>Upload Multiple Files</h3>
                            <span class="file-count" id="bulkFileCount">0 files selected</span>
                        </div>
                        <div class="bulk-actions">
                            <button type="button" class="btn btn-select-all" id="selectAllToggleBtn">
                                <span class="select-icon">☐</span>
                                <span class="select-text">Select All</span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Bulk Actions Panel -->
                    <div id="bulkActionsPanel" class="bulk-actions-panel" style="display: none;">
                        <div class="bulk-actions-header">
                            <div class="bulk-actions-info">
                                <span id="selectedCount">0</span> selected
                                <span class="bulk-actions-title">Bulk Actions</span>
                            </div>
                            <button type="button" class="btn btn-clear" id="clearSelectionBtn">
                                ✕ Clear Selection
                            </button>
                        </div>
                        
                        <div class="bulk-actions-content">
                            <div class="bulk-actions-grid">
                                <!-- Visibility Actions -->
                                <div class="bulk-action-group">
                                    <button type="button" class="bulk-action-btn" id="makePublicBtn">
                                        <span class="action-icon">👁️</span>
                                        Make Public
                                    </button>
                                    <button type="button" class="bulk-action-btn" id="makePrivateBtn">
                                        <span class="action-icon">🔒</span>
                                        Make Private
                                    </button>
                                </div>
                                
                                <!-- Category Action -->
                                <div class="bulk-action-group">
                                    <div class="bulk-field-group">
                                        <label class="bulk-field-label">
                                            <span class="action-icon">📁</span>
                                            ION Category
                                        </label>
                                        <select class="bulk-field-select" id="bulkCategorySelect">
                                            <option value="">Choose category...</option>
                                            <?php echo generate_ion_category_options('', false); ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <!-- Tags Action -->
                                <div class="bulk-action-group">
                                    <div class="bulk-field-group">
                                        <label class="bulk-field-label">
                                            <span class="action-icon">🏷️</span>
                                            Tags
                                        </label>
                                        <input type="text" class="bulk-field-input" id="bulkTagsInput" placeholder="Add tags (comma separated)">
                                    </div>
                                </div>
                                
                                <!-- Visibility Dropdown -->
                                <div class="bulk-action-group">
                                    <div class="bulk-field-group">
                                        <label class="bulk-field-label">
                                            <span class="action-icon">👁️</span>
                                            Visibility
                                        </label>
                                        <select class="bulk-field-select" id="bulkVisibilitySelect">
                                            <option value="">Choose visibility...</option>
                                            <option value="public" selected>🌐 Public - Anyone can view</option>
                                            <option value="unlisted">🔗 Unlisted - Only with link</option>
                                            <option value="private">🔒 Private - Only you and admins</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bulk-actions-footer">
                                <div class="bulk-apply-note">
                                    Changes will be applied to all <span id="selectedCountFooter">0</span> selected videos
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bulk-file-list" id="bulkFileList">
                        <!-- Video cards will be dynamically inserted here -->
                    </div>
                    
                    <div class="bulk-footer">
                        <div class="bulk-progress" id="bulkProgress" style="display: none;">
                            <div class="progress-bar">
                                <div class="progress-fill" id="bulkProgressBar"></div>
                            </div>
                            <div class="progress-text" id="bulkProgressText">Uploading files...</div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Footer -->
            <div class="modal-footer">
                <?php if ($isEditMode): ?>
                    <!-- Edit Mode Footer: Delete, Discard, Update -->
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <button type="button" class="btn btn-danger" id="deleteBtn" style="background: #6b7280; color: white; border: 1px solid #6b7280;" onmouseover="this.style.backgroundColor='#ef4444'; this.style.borderColor='#ef4444';" onmouseout="this.style.backgroundColor='#6b7280'; this.style.borderColor='#6b7280';">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                                <line x1="10" y1="11" x2="10" y2="17"/>
                                <line x1="14" y1="11" x2="14" y2="17"/>
                            </svg>
                            <span>Delete</span>
                        </button>
                        <button type="button" class="btn btn-secondary" id="backBtn">
                            Discard and close
                        </button>
                    </div>
                    <button type="button" class="btn btn-primary" id="updateBtn" style="display: none;">
                        <span>Update Media</span>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/>
                            <polyline points="17,21 17,13 7,13 7,21"/>
                            <polyline points="7,3 7,8 15,8"/>
                        </svg>
                    </button>
                <?php else: ?>
                    <!-- Upload Mode Footer: Cancel, Progress Bar, Next -->
                    <button type="button" class="btn btn-secondary" id="backBtn">
                        Cancel
                    </button>
                    
                    <!-- Progress Bar Container - Clean Professional Design -->
                    <div id="uploadProgressContainer" style="display: none; flex: 1; max-width: 500px; margin: 0 24px; align-items: center; gap: 16px;">
                        <!-- Status Text -->
                        <span id="uploadStatusText" style="font-size: 0.875rem; font-weight: 600; color: #3b82f6; white-space: nowrap;">Uploading...</span>
                        
                        <!-- Progress Bar -->
                        <div style="flex: 1; position: relative; height: 8px; background: rgba(226, 232, 240, 0.5); border-radius: 4px; overflow: hidden;">
                            <div id="uploadProgressBar" style="height: 100%; width: 0%; transition: width 0.3s ease; border-radius: 4px; background: linear-gradient(90deg, #3b82f6, #2563eb);"></div>
                        </div>
                        
                        <!-- Percentage -->
                        <span id="uploadPercentageText" style="font-size: 0.875rem; font-weight: 700; color: #2563eb; min-width: 45px; text-align: right;">0%</span>
                    </div>
                    
                    <button type="button" class="btn btn-primary" id="nextBtn" disabled>
                        <span id="nextBtnText">Next</span>
                        <svg id="nextBtnIcon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="9,18 15,12 9,6"></polyline>
                        </svg>
                    </button>
                <?php endif; ?>
            </div>
        </div> <!-- Close modal-container -->
    <?php if (!$isIframe): ?>
        </div> <!-- Close modal-overlay -->
    <?php endif; ?>
    <!-- Custom Modal Overlay (replaces native alerts/confirms) -->
    <div class="custom-modal-overlay" id="customModalOverlay">
        <div class="custom-modal-dialog">
            <div class="dialog-header">
                <h4 id="customModalTitle"></h4>
            </div>
            <div class="dialog-content">
                <p id="customModalMessage"></p>
            </div>
            <div class="dialog-actions" id="customModalActions">
                <!-- Buttons will be injected here by JavaScript -->
            </div>
        </div>
    </div>
    <!-- Load JavaScript files at the end of body -->
    <?php 
    // Auto-incrementing cache buster based on file modification time
    $uploaderJsPath = __DIR__ . '/ionuploader.js';
    $uploaderProJsPath = __DIR__ . '/ionuploaderpro.js';
    $celebrationJsPath = __DIR__ . '/celebration-dialog.js';
    $channelSelectorJsPath = __DIR__ . '/channel-selector.js';
    
    $uploaderVersion = file_exists($uploaderJsPath) ? filemtime($uploaderJsPath) : time();
    $uploaderProVersion = file_exists($uploaderProJsPath) ? filemtime($uploaderProJsPath) : time();
    $celebrationVersion = file_exists($celebrationJsPath) ? filemtime($celebrationJsPath) : time();
    $channelSelectorVersion = file_exists($channelSelectorJsPath) ? filemtime($channelSelectorJsPath) : time();
    ?>
    <script src="<?= $assetBase ?>ionuploader.js?v=<?= $uploaderVersion ?>"></script>            <!-- Core functionality -->
    <script src="<?= $assetBase ?>ionuploaderpro.js?v=<?= $uploaderProVersion ?>"></script>         <!-- Advanced features (includes R2MultipartUploader) -->
    <script src="<?= $assetBase ?>celebration-dialog.js?v=<?= $celebrationVersion ?>"></script>      <!-- Celebration dialog for successful uploads -->
    <script src="<?= $assetBase ?>channel-selector.js?v=<?= $channelSelectorVersion ?>"></script>        <!-- Channel search and selection -->

    <!-- Initialize Upload System -->
    <script>
        window.addEventListener('load', function() {
            console.log('🚀 ION Uploader Core + Pro initialized');
            
            // Initialize Pro features if available
            if (window.IONUploaderPro && window.IONUploaderPro.initializeBadgeInput) {
                window.IONUploaderPro.initializeBadgeInput();
                console.log('✅ Pro features initialized');
            }
            
            // Load channels in edit mode
            <?php if ($isEditMode && !empty($editVideoData['id'])): ?>
                console.log('📺 Edit mode detected, loading channels for video ID: <?= $editVideoData['id'] ?>');
                
                // Wait for channelSelector to be ready, then load channels
                function loadEditModeChannels() {
                    if (!window.channelSelector) {
                        console.log('⏳ Waiting for channel selector to initialize...');
                        setTimeout(loadEditModeChannels, 100);
                        return;
                    }
                    
                    console.log('✅ Channel selector ready, fetching channels...');
                    
                    // Fetch channels for this video
                    fetch('/app/get-video-channels.php?video_id=<?= $editVideoData['id'] ?>')
                        .then(response => response.json())
                        .then(data => {
                            console.log('📺 API Response:', data);
                            
                            if (data.success && data.channels && data.channels.length > 0) {
                                console.log('📺 Loading ' + data.channels.length + ' channels:', data.channels);
                                window.channelSelector.loadChannels(data.channels);
                            } else {
                                console.log('ℹ️ No channels found for this video');
                            }
                        })
                        .catch(error => {
                            console.error('❌ Failed to load channels:', error);
                        });
                }
                
                // Start loading channels
                loadEditModeChannels();
            <?php endif; ?>
        });
    </script>
</body>
</html>