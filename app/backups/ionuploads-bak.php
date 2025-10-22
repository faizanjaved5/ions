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
        'id'          => $_GET['video_id'] ?? '',
        'title'     => $_GET['title'] ?? '',
        'description' => $_GET['description'] ?? '',
        'category' => $_GET['category'] ?? '',
        'tags' => $_GET['tags'] ?? '',
        'badges' => $_GET['badges'] ?? '', // ION Badges (comma-separated)
        'channels' => $_GET['channels'] ?? '[]', // ION Channels (JSON array of slugs)
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
    <link rel="stylesheet" href="<?= $assetBase ?>ionuploads.css?v=200&t=<?= time() ?>&bust=<?= md5(time() . rand()) ?>">
    <link rel="stylesheet" href="../share/enhanced-ion-share.css?v=<?= time() ?>&bust=<?= rand() ?>">
    
    <!-- Force spacing fixes with inline styles -->
    <style>
        /* CRITICAL: Hide page scrollbar when modal is open */
        body.modal-open {
            overflow: hidden !important;
            padding-right: 0 !important; /* Prevent layout shift */
        }
        
        /* Force video preview column spacing */
        .video-preview-column {
            gap: 0px !important;
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
            padding: 10px 10px 16px 10px !important;
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
        
        /* ===================================== */
        /* IMPROVED BULK UPLOAD UI STYLES */
        /* ===================================== */
        
        /* CRITICAL: Bulk Step 2 Container Scrollbar */
        /* Note: display property is controlled by JavaScript to avoid overriding display: none */
        .bulk-step2-content {
            overflow-y: auto !important;
            max-height: calc(100vh - 100px) !important; /* CRITICAL: Use almost full viewport height */
            min-height: calc(100vh - 100px) !important; /* CRITICAL: Ensure container fills available space */
            height: calc(100vh - 100px) !important; /* CRITICAL: Fixed height for consistency */
            padding: 16px 32px 16px 32px !important; /* Minimal padding */
            scrollbar-width: thin !important;
            scrollbar-color: rgba(178, 130, 84, 0.6) rgba(30, 41, 59, 0.3) !important;
            flex-direction: column !important;
            /* display will be set by JavaScript to 'flex' when visible */
        }
        
        .bulk-step2-content::-webkit-scrollbar {
            width: 6px !important;
        }
        
        .bulk-step2-content::-webkit-scrollbar-track {
            background: rgba(30, 41, 59, 0.3) !important;
            border-radius: 3px !important;
        }
        
        .bulk-step2-content::-webkit-scrollbar-thumb {
            background: rgba(178, 130, 84, 0.6) !important;
            border-radius: 3px !important;
        }
        
        .bulk-step2-content::-webkit-scrollbar-thumb:hover {
            background: rgba(178, 130, 84, 0.8) !important;
        }
        
        /* Common Properties Section (Top) */
        .bulk-common-properties {
            background: rgba(30, 41, 59, 0.5);
            border: 1px solid #334155;
            border-radius: 8px;
            padding: 10px; /* Minimal padding */
            margin-bottom: 8px; /* Small margin for separation */
            flex-shrink: 0; /* CRITICAL: Don't shrink common properties */
        }
        
        .bulk-common-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px; /* Reduced gap to save space */
            margin-top: 8px; /* Reduced margin */
        }
        
        .bulk-common-field {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .bulk-common-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #e2e8f0;
        }
        
        .bulk-common-select,
        .bulk-common-input {
            background: #1e293b;
            border: 1px solid #334155;
            color: white;
            padding: 10px 14px;
            border-radius: 6px;
            font-size: 0.875rem;
            width: 100%;
        }
        
        .bulk-common-select:focus,
        .bulk-common-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
        }
        
        /* Helper Text (below common properties) */
        .bulk-helper-text {
            text-align: center;
            font-size: 0.75rem;
            color: #64748b;
            font-style: italic;
            flex-shrink: 0; /* CRITICAL: Don't shrink helper text */
        }
        
        /* Videos Header */
        .bulk-videos-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0; /* CRITICAL: Don't shrink header */
        }
        
        .btn-add-more:hover {
            text-decoration: underline;
        }
        
        /* Improved Video Cards Container */
        .bulk-file-list-improved {
            display: flex;
            flex-direction: column;
            gap: 8px; /* Reduced gap to pack cards tighter */
            flex: 1 1 auto; /* CRITICAL: Grow to fill remaining space */
            /* NO overflow-y - parent dialog scrollbar handles ALL scrolling */
            padding-right: 0;
            min-height: 0; /* Allow flex shrinking if needed */
        }
        
        /* Video Card (Improved Design) */
        .bulk-file-card-improved {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 8px;
            overflow: visible; /* CRITICAL: Allow expanded content to show */
            transition: all 0.2s ease;
            flex-shrink: 0; /* CRITICAL: Prevent cards from shrinking when siblings expand */
        }
        
        .bulk-file-card-improved:hover {
            border-color: #475569;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }
        
        /* Collapsed View */
        .bulk-card-collapsed {
            display: flex;
            align-items: center;
            padding: 12px;
            gap: 12px;
            min-height: 84px; /* CRITICAL: Fixed height prevents shrinking */
            cursor: pointer;
            transition: background 0.2s ease;
        }
        
        .bulk-card-collapsed:hover {
            background: rgba(59, 130, 246, 0.05);
        }
        
        .bulk-card-left {
            flex-shrink: 0;
        }
        
        .bulk-card-thumbnail {
            width: 80px;
            height: 60px;
            border-radius: 6px;
            overflow: hidden;
            background: #0f172a;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .thumbnail-placeholder {
            font-size: 24px;
            opacity: 0.5;
        }
        
        .bulk-card-center {
            flex: 1;
            min-width: 0;
        }
        
        /* Editable Title with Character Counter */
        .bulk-card-title-wrapper {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 4px;
        }
        
        .bulk-card-title-input {
            flex: 1;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid #334155;
            color: white;
            font-size: 0.95rem;
            font-weight: 600;
            padding: 6px 10px;
            border-radius: 4px;
            min-width: 0;
            transition: all 0.2s ease;
        }
        
        .bulk-card-title-input:focus {
            outline: none;
            border-color: #3b82f6;
            background: rgba(15, 23, 42, 0.8);
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
        }
        
        .bulk-card-title-input::placeholder {
            color: #64748b;
        }
        
        .bulk-card-char-count {
            font-size: 0.75rem;
            color: #94a3b8;
            white-space: nowrap;
            font-weight: 500;
            transition: color 0.2s ease;
        }
        
        .bulk-card-char-count span {
            font-weight: 600;
        }
        
        .bulk-card-filename {
            font-size: 0.8rem;
            color: #94a3b8;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .bulk-card-progress {
            /* Display is controlled by JavaScript (display: none or display: flex) */
            align-items: center;
            gap: 8px;
            margin-top: 8px;
        }
        
        /* When visible, ensure flex layout is applied */
        .bulk-card-progress:not([style*="display: none"]) {
            display: flex;
        }
        
        .bulk-card-progress-bar {
            flex: 1;
            height: 4px;
            background: rgba(51, 65, 85, 0.5);
            border-radius: 2px;
            overflow: hidden;
        }
        
        .bulk-card-progress-fill {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, #3b82f6, #2563eb);
            /* Transition disabled - controlled by JavaScript for immediate feedback */
            min-width: 0; /* Ensure it can start from 0 */
        }
        
        .bulk-card-progress-text {
            font-size: 0.75rem;
            font-weight: 600;
            color: #3b82f6;
            min-width: 40px;
            text-align: right;
        }
        
        .bulk-card-right {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }
        
        .bulk-card-btn-close,
        .bulk-card-btn-expand {
            background: transparent;
            border: 1px solid #334155;
            color: #94a3b8;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 14px;
        }
        
        .bulk-card-btn-close:hover {
            background: #ef4444;
            border-color: #ef4444;
            color: white;
        }
        
        .bulk-card-btn-expand:hover {
            background: #3b82f6;
            border-color: #3b82f6;
            color: white;
        }
        
        /* Expanded View */
        .bulk-card-expanded {
            padding: 16px;
            border-top: 1px solid #334155;
            background: rgba(15, 23, 42, 0.5);
            min-height: 400px; /* CRITICAL: Ensure enough space for all content */
            overflow: visible; /* CRITICAL: Allow dropdowns/modals to show */
        }
        
        .bulk-expanded-content {
            display: grid;
            grid-template-columns: 2fr 1fr 1.5fr;
            gap: 16px;
            min-height: 350px; /* CRITICAL: Ensure content areas are visible */
        }
        
        .bulk-expanded-left,
        .bulk-expanded-middle,
        .bulk-expanded-right {
            display: flex;
            flex-direction: column;
            min-height: 320px; /* CRITICAL: Ensure all columns are tall enough */
        }
        
        .bulk-expanded-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #94a3b8;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .bulk-expanded-video-preview {
            border-radius: 8px;
            overflow: hidden;
            background: #000;
            min-height: 250px; /* CRITICAL: Ensure video player is visible */
        }
        
        .bulk-preview-video {
            width: 100%;
            max-height: 300px;
            border-radius: 8px;
            background: #000;
        }
        
        .bulk-expanded-thumbnail {
            width: 100%;
            aspect-ratio: 16/9;
            border-radius: 8px;
            overflow: hidden;
            background: #0f172a;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
        }
        
        .thumbnail-placeholder-large {
            font-size: 48px;
            opacity: 0.3;
        }
        
        /* Bulk Thumbnail Controls */
        .bulk-thumbnail-controls {
            display: flex;
            gap: 8px;
            margin-bottom: 8px;
        }
        
        .bulk-thumbnail-btn {
            flex: 1;
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid #334155;
            color: #e2e8f0;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }
        
        .bulk-thumbnail-btn:hover {
            background: rgba(59, 130, 246, 0.2);
            border-color: #3b82f6;
            color: #3b82f6;
        }
        
        .bulk-thumbnail-url-input {
            display: flex;
            gap: 8px;
            margin-top: 8px;
        }
        
        .bulk-url-input {
            flex: 1;
            background: #0f172a;
            border: 1px solid #334155;
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.8rem;
        }
        
        .bulk-url-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
        }
        
        .bulk-url-apply-btn {
            background: #3b82f6;
            border: 1px solid #3b82f6;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .bulk-url-apply-btn:hover {
            background: #2563eb;
            border-color: #2563eb;
        }
        
        .bulk-expanded-description {
            background: #0f172a;
            border: 1px solid #334155;
            color: white;
            padding: 12px;
            border-radius: 6px;
            font-size: 0.875rem;
            resize: vertical;
            min-height: 300px; /* CRITICAL: Larger height to match video/thumbnail area */
            height: 100%; /* Allow it to fill available space */
        }
        
        .bulk-expanded-description:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
        }
        
        /* Bulk progress bar now in footer - no additional CSS needed (uses inline styles) */
    </style>
    <script>
        // Configuration for Google Drive - Expose to window for ionuploaderpro.js
        window.GOOGLE_CLIENT_ID = '<?= $config['google_drive_clientid'] ?? '' ?>';
        window.GOOGLE_API_KEY = '<?= $config['google_drive_api_key'] ?? '' ?>';
        
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
                        <!-- Upload Your Media -->
                        <div id="uploadOption" class="upload-option">
                            <div class="option-title">
                                <svg class="option-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    <polyline points="17,8 12,3 7,8"></polyline>
                                    <line x1="12" y1="3" x2="12" y2="15"></line>
                                </svg>
                                Upload Your Media
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
                        <!-- Import Your Media with Purple Glow -->
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
                                Import Your Media
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
                        <div class="form-group">
                            <label for="videoTitle">Media Title *
                                <span class="char-count"><span id="titleCount">0</span>/100 characters</span>
                            </label>
                            <input type="text" id="videoTitle" class="form-input" placeholder="Enter media title" maxlength="100" required value="<?= htmlspecialchars($editVideoData['title'] ?? '') ?>">
                        </div>
                       
                        <div class="form-group">
                            <label for="videoDescription">Media Description
                                <span class="char-count"><span id="descCount">0</span>/5000 characters</span>
                            </label>
                            <textarea id="videoDescription" class="form-input form-textarea" placeholder="Describe your media" maxlength="5000"><?= htmlspecialchars($editVideoData['description'] ?? '') ?></textarea>
                        </div>
                       
                        <!-- ION Category and Badges - Side by Side -->
                        <div class="form-row">
                            <div class="form-group">
                                <label for="videoCategory">ION Networks Category</label>
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
                                        <div class="badge-option" data-value="Featured">
                                            <span class="badge-icon">🔥</span> Featured
                                        </div>
                                        <div class="badge-option" data-value="Favorites">
                                            <span class="badge-icon">🌟</span> Favorites
                                        </div>
                                        <div class="badge-option" data-value="Trending">
                                            <span class="badge-icon">🚀</span> Trending
                                        </div>
                                        <div class="badge-option" data-value="Hidden Gem">
                                            <span class="badge-icon">💎</span> Hidden Gem
                                        </div>
                                        <div class="badge-option" data-value="Spotlight">
                                            <span class="badge-icon">📣</span> Spotlight
                                        </div>
                                        <div class="badge-option" data-value="Hall of Fame">
                                            <span class="badge-icon">🏅</span> Hall of Fame
                                        </div>
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
                    <!-- IMPROVED BULK UPLOAD UI - Common Properties at Top -->
                    <div class="bulk-common-properties">
                        <div class="bulk-common-grid">
                            <div class="bulk-common-field">
                                <label class="bulk-common-label">ION Networks Category *</label>
                                <select class="bulk-common-select" id="bulkCommonCategory">
                                    <option value="">Select category</option>
                                    <?php echo generate_ion_category_options('', false); ?>
                                </select>
                            </div>
                            
                            <div class="bulk-common-field">
                                <label class="bulk-common-label">ION Channel *</label>
                                <input type="text" 
                                       class="bulk-common-input" 
                                       id="bulkCommonChannel" 
                                       placeholder="Enter channel name"
                                       style="background: #1e293b; border: 1px solid #334155; color: white; padding: 10px 14px; border-radius: 6px; width: 100%;">
                            </div>
                            
                            <div class="bulk-common-field">
                                <label class="bulk-common-label">Visibility</label>
                                <select class="bulk-common-select" id="bulkCommonVisibility">
                                    <option value="public" selected>Public</option>
                                    <option value="unlisted">Unlisted</option>
                                    <option value="private">Private</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Helper Text (centered, small) -->
                    <div class="bulk-helper-text">
                        Default settings for all videos (can be overridden per video)
                    </div>
                    
                    <!-- Video List Header -->
                    <div class="bulk-videos-header">
                        <h3 style="margin: 0; font-size: 1rem; font-weight: 600;">
                            Videos (<span id="bulkFileCount">0</span>)
                        </h3>
                        <button type="button" 
                                class="btn-add-more" 
                                id="bulkAddMoreBtn"
                                style="background: transparent; color: #3b82f6; border: none; padding: 8px 16px; cursor: pointer; font-weight: 500; font-size: 0.875rem;">
                            Add More
                        </button>
                    </div>
                    
                    <!-- Video Cards Container -->
                    <div class="bulk-file-list-improved" id="bulkFileList">
                        <!-- Video cards will be dynamically inserted here -->
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
                    <!-- Upload Mode Footer: Close/Back, Progress Bar, Next/Upload -->
                    <!-- Close button for Step 1 -->
                    <button type="button" class="btn btn-secondary" id="closeBtn" style="display: flex;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px;">
                            <path d="M18 6L6 18M6 6l12 12"/>
                        </svg>
                        <span>Close</span>
                    </button>
                    <!-- Back button for Step 2 -->
                    <button type="button" class="btn btn-secondary" id="backBtn" style="display: none;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px;">
                            <path d="M19 12H5M12 19l-7-7 7-7"/>
                        </svg>
                        <span>Back</span>
                    </button>
                    
                    <!-- Single File Progress Bar Container - Clean Professional Design with Detailed Tooltip -->
                    <div id="uploadProgressContainer" style="display: none; flex: 1; max-width: 500px; margin: 0 24px; gap: 16px; position: relative;">
                        <!-- Flex container for horizontal layout -->
                        <div style="display: flex; align-items: center; gap: 16px; width: 100%;">
                            <!-- Status Text -->
                            <span id="uploadStatusText" style="font-size: 0.875rem; font-weight: 600; color: #3b82f6; white-space: nowrap;">Uploading...</span>
                            
                            <!-- Progress Bar with Hover Trigger -->
                            <div id="progressBarWrapper" style="flex: 1; position: relative; height: 8px; background: rgba(226, 232, 240, 0.5); border-radius: 4px; overflow: hidden; cursor: pointer;">
                                <div id="uploadProgressBar" style="height: 100%; width: 0%; transition: width 0.3s ease; border-radius: 4px; background: linear-gradient(90deg, #3b82f6, #2563eb);"></div>
                            </div>
                            
                            <!-- Percentage -->
                            <span id="uploadPercentageText" style="font-size: 0.875rem; font-weight: 700; color: #2563eb; min-width: 45px; text-align: right;">0%</span>
                        </div>
                        
                        <!-- Detailed Progress Tooltip (Hidden by default) -->
                        <div id="uploadProgressTooltip" style="
                            display: none;
                            position: absolute;
                            bottom: 100%;
                            left: 50%;
                            transform: translateX(-50%);
                            margin-bottom: 12px;
                            background: #1e293b;
                            color: #fff;
                            border-radius: 8px;
                            padding: 16px;
                            min-width: 400px;
                            max-width: 500px;
                            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
                            z-index: 10000;
                            font-size: 0.875rem;
                            line-height: 1.5;
                        ">
                            <!-- Tooltip Arrow -->
                            <div style="position: absolute; bottom: -6px; left: 50%; transform: translateX(-50%); width: 12px; height: 12px; background: #1e293b; transform: translateX(-50%) rotate(45deg);"></div>
                            
                            <!-- File Info -->
                            <div style="margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid rgba(255,255,255,0.1);">
                                <div style="font-weight: 600; margin-bottom: 4px; color: #3b82f6;">📁 <span id="tooltipFileName">File Name</span></div>
                                <div style="color: #94a3b8; font-size: 0.8125rem;">
                                    <span id="tooltipFileSize">0 MB</span> • 
                                    <span id="tooltipPartCount">0 parts</span> • 
                                    <span id="tooltipPartSize">0 MB per part</span>
                                </div>
                            </div>
                            
                            <!-- Upload Stats -->
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid rgba(255,255,255,0.1);">
                                <div>
                                    <div style="color: #94a3b8; font-size: 0.75rem; margin-bottom: 2px;">Upload Speed</div>
                                    <div style="font-weight: 600;" id="tooltipSpeed">-- MB/s</div>
                                </div>
                                <div>
                                    <div style="color: #94a3b8; font-size: 0.75rem; margin-bottom: 2px;">Time Remaining</div>
                                    <div style="font-weight: 600;" id="tooltipTimeRemaining">--:--</div>
                                </div>
                                <div>
                                    <div style="color: #94a3b8; font-size: 0.75rem; margin-bottom: 2px;">Uploaded</div>
                                    <div style="font-weight: 600;" id="tooltipUploaded">0 MB</div>
                                </div>
                                <div>
                                    <div style="color: #94a3b8; font-size: 0.75rem; margin-bottom: 2px;">Progress</div>
                                    <div style="font-weight: 600;" id="tooltipProgress">0%</div>
                                </div>
                            </div>
                            
                            <!-- Part Status -->
                            <div style="margin-bottom: 8px;">
                                <div style="color: #94a3b8; font-size: 0.75rem; margin-bottom: 6px; font-weight: 600;">Chunk Status</div>
                                <div style="display: flex; gap: 4px; align-items: center; margin-bottom: 6px;">
                                    <span style="color: #10b981;">●</span> <span style="font-size: 0.8125rem;"><span id="tooltipCompletedParts">0</span> completed</span>
                                    <span style="color: #3b82f6; margin-left: 12px;">●</span> <span style="font-size: 0.8125rem;"><span id="tooltipUploadingParts">0</span> uploading</span>
                                    <span style="color: #ef4444; margin-left: 12px;">●</span> <span style="font-size: 0.8125rem;"><span id="tooltipFailedParts">0</span> failed</span>
                                </div>
                            </div>
                            
                            <!-- Parts Grid (scrollable) -->
                            <div id="tooltipPartsGrid" style="
                                display: grid;
                                grid-template-columns: repeat(auto-fill, 24px);
                                gap: 4px;
                                max-height: 120px;
                                overflow-y: auto;
                                padding: 8px;
                                background: rgba(0,0,0,0.2);
                                border-radius: 4px;
                            ">
                                <!-- Parts will be dynamically added here -->
                            </div>
                            
                            <div style="margin-top: 8px; font-size: 0.75rem; color: #64748b; text-align: center;">
                                Hover over chunks to see details
                            </div>
                        </div>
                    </div>
                    
                    <!-- Bulk Upload Progress Bar Container (shown during bulk upload) -->
                    <div id="bulkProgressContainer" style="display: none; flex: 1; max-width: 500px; margin: 0 24px; position: relative; flex-direction: column; gap: 4px;">
                        <!-- Progress Bar and Percentage -->
                        <div id="bulkProgressBarWrapper" style="display: flex; align-items: center; gap: 12px; width: 100%; cursor: pointer;">
                            <!-- Progress Bar (wider now) -->
                            <div style="flex: 1; position: relative; height: 8px; background: rgba(226, 232, 240, 0.5); border-radius: 4px; overflow: hidden;">
                                <div id="bulkProgressBar" style="height: 100%; width: 0%; border-radius: 4px; background: linear-gradient(90deg, #3b82f6, #2563eb);"></div>
                            </div>
                            
                            <!-- Percentage -->
                            <span id="bulkProgressPercentage" style="font-size: 0.875rem; font-weight: 700; color: #2563eb; min-width: 45px; text-align: right;">0%</span>
                        </div>
                        
                        <!-- File Status Label (smaller, below progress bar) -->
                        <div id="bulkProgressLabel" style="font-size: 0.75rem; font-weight: 500; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; text-align: center;">Uploading files...</div>
                        
                        <!-- Bulk Multipart Progress Tooltip (Hidden by default) -->
                        <div id="bulkProgressTooltip" style="
                            display: none;
                            position: absolute;
                            bottom: 100%;
                            left: 50%;
                            transform: translateX(-50%);
                            margin-bottom: 12px;
                            background: #1e293b;
                            color: #fff;
                            border-radius: 8px;
                            padding: 16px;
                            min-width: 400px;
                            max-width: 500px;
                            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
                            z-index: 10000;
                            font-size: 0.875rem;
                            line-height: 1.5;
                        ">
                            <!-- Tooltip Arrow -->
                            <div style="position: absolute; bottom: -6px; left: 50%; transform: translateX(-50%); width: 12px; height: 12px; background: #1e293b; transform: translateX(-50%) rotate(45deg);"></div>
                            
                            <!-- File Info -->
                            <div style="margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid rgba(255,255,255,0.1);">
                                <div style="font-weight: 600; margin-bottom: 4px; color: #3b82f6;">📁 <span id="bulkTooltipFileName">File Name</span></div>
                                <div style="color: #94a3b8; font-size: 0.8125rem;">
                                    <span id="bulkTooltipFileSize">0 MB</span> • 
                                    <span id="bulkTooltipPartCount">0 parts</span> • 
                                    <span id="bulkTooltipPartSize">0 MB per part</span>
                                </div>
                            </div>
                            
                            <!-- Upload Stats -->
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid rgba(255,255,255,0.1);">
                                <div>
                                    <div style="color: #94a3b8; font-size: 0.75rem; margin-bottom: 2px;">Upload Speed</div>
                                    <div style="font-weight: 600;" id="bulkTooltipSpeed">-- MB/s</div>
                                </div>
                                <div>
                                    <div style="color: #94a3b8; font-size: 0.75rem; margin-bottom: 2px;">Time Remaining</div>
                                    <div style="font-weight: 600;" id="bulkTooltipTimeRemaining">--:--</div>
                                </div>
                                <div>
                                    <div style="color: #94a3b8; font-size: 0.75rem; margin-bottom: 2px;">Uploaded</div>
                                    <div style="font-weight: 600;" id="bulkTooltipUploaded">0 MB</div>
                                </div>
                                <div>
                                    <div style="color: #94a3b8; font-size: 0.75rem; margin-bottom: 2px;">Progress</div>
                                    <div style="font-weight: 600;" id="bulkTooltipProgress">0%</div>
                                </div>
                            </div>
                            
                            <!-- Part Status -->
                            <div style="margin-bottom: 8px;">
                                <div style="color: #94a3b8; font-size: 0.75rem; margin-bottom: 6px; font-weight: 600;">Chunk Status</div>
                                <div style="display: flex; gap: 4px; align-items: center; margin-bottom: 6px;">
                                    <span style="color: #10b981;">●</span> <span style="font-size: 0.8125rem;"><span id="bulkTooltipCompletedParts">0</span> completed</span>
                                    <span style="color: #3b82f6; margin-left: 12px;">●</span> <span style="font-size: 0.8125rem;"><span id="bulkTooltipUploadingParts">0</span> uploading</span>
                                    <span style="color: #ef4444; margin-left: 12px;">●</span> <span style="font-size: 0.8125rem;"><span id="bulkTooltipFailedParts">0</span> failed</span>
                                </div>
                            </div>
                            
                            <!-- Parts Grid (scrollable) -->
                            <div id="bulkTooltipPartsGrid" style="
                                display: grid;
                                grid-template-columns: repeat(auto-fill, 24px);
                                gap: 4px;
                                max-height: 120px;
                                overflow-y: auto;
                                padding: 8px;
                                background: rgba(0,0,0,0.2);
                                border-radius: 4px;
                            ">
                                <!-- Parts will be dynamically added here -->
                            </div>
                            
                            <div style="margin-top: 8px; font-size: 0.75rem; color: #64748b; text-align: center;">
                                Hover over chunks to see details
                            </div>
                        </div>
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
    // NUCLEAR CACHE BUSTING - Ensures fresh files on every load
    $nuclearBust = str_replace('.', '', microtime(true));
    
    // File modification time for versioning
    $uploaderJsPath = __DIR__ . '/ionuploads.js';
    $uploaderProJsPath = __DIR__ . '/ion-uploaderxs.js';  // RENAMED for cache bust
    $celebrationJsPath = __DIR__ . '/celebration-dialog.js';
    $channelSelectorJsPath = __DIR__ . '/channel-selector.js';
    
    $uploaderVersion = file_exists($uploaderJsPath) ? filemtime($uploaderJsPath) : time();
    $uploaderProVersion = file_exists($uploaderProJsPath) ? filemtime($uploaderProJsPath) : time();
    $celebrationVersion = file_exists($celebrationJsPath) ? filemtime($celebrationJsPath) : time();
    $channelSelectorVersion = file_exists($channelSelectorJsPath) ? filemtime($channelSelectorJsPath) : time();
    
    // No longer need time() - renamed file breaks cache naturally
    ?>
    <!-- CRITICAL: Load order matters! Pro features must load BEFORE core uses them -->
    <script src="<?= $assetBase ?>ion-uploaderxs.js?v=<?= $uploaderProVersion ?>&nc=<?= $nuclearBust ?>"></script>         <!-- Advanced features (MUST LOAD FIRST!) -->
    <script src="<?= $assetBase ?>progress-details.js?v=<?= time() ?>&nc=<?= $nuclearBust ?>"></script>                   <!-- Detailed progress tracking -->
    <script src="<?= $assetBase ?>ionuploads.js?v=<?= $uploaderVersion ?>&nc=<?= $nuclearBust ?>"></script>            <!-- Core functionality -->
    <script src="../share/enhanced-ion-share.js?v=<?= time() ?>&nc=<?= $nuclearBust ?>"></script>                          <!-- Enhanced Share System -->
    <script src="<?= $assetBase ?>celebration-dialog.js?v=<?= $celebrationVersion ?>&nc=<?= $nuclearBust ?>"></script>      <!-- Celebration dialog for successful uploads -->
    <script src="<?= $assetBase ?>channel-selector.js?v=<?= $channelSelectorVersion ?>&nc=<?= $nuclearBust ?>"></script>        <!-- Channel search and selection -->
    <script src="<?= $assetBase ?>ion-uploaderbackground.js?v=<?= time() ?>&nc=<?= $nuclearBust ?>"></script>               <!-- Background upload manager UI widget -->

    <!-- Diagnostic Check - MUST RUN BEFORE UPLOADER -->
    <script>
        // Immediate diagnostic (don't wait for load event)
        console.log('═══════════════════════════════════════════════════════════════');
        console.log('🚀 ION UPLOADER DIAGNOSTIC CHECK - <?= date("H:i:s") ?>');
        console.log('═══════════════════════════════════════════════════════════════');
        
        // Check what's loaded
        setTimeout(function() {
            const checks = {
                'R2MultipartUploader': typeof window.R2MultipartUploader !== 'undefined',
                'ChunkedUploader': typeof window.ChunkedUploader !== 'undefined',
                'processPlatformImport': typeof window.processPlatformImport !== 'undefined',
                'showGoogleDrivePicker': typeof window.showGoogleDrivePicker !== 'undefined',
                'EnhancedIONShare': typeof window.EnhancedIONShare !== 'undefined',
                'backgroundUploadManager': typeof window.backgroundUploadManager !== 'undefined'
            };
            
            let allLoaded = true;
            Object.keys(checks).forEach(key => {
                const status = checks[key] ? '✅ LOADED' : '❌ MISSING';
                console.log(`${status} - ${key}`);
                if (!checks[key]) allLoaded = false;
            });
            
            console.log('═══════════════════════════════════════════════════════════════');
            if (allLoaded) {
                console.log('✅ ALL FEATURES LOADED SUCCESSFULLY!');
            } else {
                console.error('❌ CRITICAL: SOME FEATURES MISSING!');
                console.error('⚠️  ionuploaderpro.js did not load properly!');
                console.error('⚠️  R2 Multipart Upload will NOT work!');
                console.error('⚠️  Platform imports will NOT work!');
            }
            console.log('═══════════════════════════════════════════════════════════════');
        }, 100);
    </script>
    
    <!-- Initialize Upload System -->
    <script>
        window.addEventListener('load', function() {
            console.log('🚀 ION Uploader initialization complete');
            
            // Initialize Pro features if available
            if (window.IONUploaderPro && window.IONUploaderPro.initializeBadgeInput) {
                window.IONUploaderPro.initializeBadgeInput();
                console.log('✅ Pro features initialized');
            }
            
            // Load channels in edit mode
            <?php if ($isEditMode && !empty($editVideoData['id'])): ?>
                console.log('📺 Edit mode detected, loading channels for video ID: <?= $editVideoData['id'] ?>');
                console.log('📺 Channels from URL params: <?= htmlspecialchars($editVideoData['channels'], ENT_QUOTES) ?>');
                
                // Wait for channelSelector to be ready, then load channels
                function loadEditModeChannels() {
                    if (!window.channelSelector) {
                        console.log('⏳ Waiting for channel selector to initialize...');
                        setTimeout(loadEditModeChannels, 100);
                        return;
                    }
                    
                    console.log('✅ Channel selector ready, loading channels from URL params...');
                    
                    try {
                        // Parse channels from URL parameters (JSON array of channel slugs)
                        const channelsJson = <?= json_encode($editVideoData['channels']) ?>;
                        const channelSlugs = JSON.parse(channelsJson);
                        
                        console.log('📺 Parsed channel slugs:', channelSlugs);
                        
                        if (channelSlugs && channelSlugs.length > 0) {
                            console.log('📺 Loading ' + channelSlugs.length + ' channel(s):', channelSlugs);
                            window.channelSelector.loadChannels(channelSlugs);
                        } else {
                            console.log('ℹ️ No channels to load for this video');
                        }
                    } catch (error) {
                        console.error('❌ Failed to parse channels:', error);
                        console.log('📺 Raw channels value: <?= htmlspecialchars($editVideoData['channels'], ENT_QUOTES) ?>');
                    }
                }
                // Start loading channels
                loadEditModeChannels();
            <?php endif; ?>
        });
    </script>
</body>
</html>