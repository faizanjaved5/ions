// Core functions for single/bulk upload, media validation, video player, edit mode
// Works with ionuploaderpro.js for advanced features

// ============================================
// GLOBAL VARIABLES
// ============================================
let currentUploadType = null;
let currentSource = null;
let selectedFile = null;
let selectedFiles = [];
let bulkMode = false;
let selectedGoogleDriveFile = null;
let selectedGoogleDriveFiles = []; // For bulk Drive
// accessToken and tokenClient declared in ionuploaderpro.js (loaded first)
let currentView = 'grid'; // grid/list toggle for bulk
let uploadQueue = [];
let currentUploadIndex = 0;

// Media type configs (from live, extended for audio/image)
const MEDIA_TYPES = {
    video: {
        extensions: ['mp4', 'webm', 'mov', 'ogg', 'avi'],
        maxSize: 20 * 1024 * 1024 * 1024, // 20GB
        mimeTypes: ['video/mp4', 'video/webm', 'video/quicktime', 'video/ogg', 'video/x-msvideo']
    },
    audio: {
        extensions: ['mp3', 'wav', 'flac', 'aac', 'm4a', 'ogg'],
        maxSize: 500 * 1024 * 1024, // 500MB
        mimeTypes: ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/wave', 'audio/x-wav', 'audio/flac', 'audio/aac', 'audio/x-aac', 'audio/x-m4a', 'audio/mp4', 'audio/m4a', 'audio/ogg']
    },
    image: {
        extensions: ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'],
        mimeTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/x-windows-bmp', 'image/svg+xml']
    }
};

// ============================================
// CORE CLASSES - R2MultipartUploader moved to ionuploaderpro.js to eliminate duplication
// ============================================
// Note: R2MultipartUploader class is now defined in ionuploaderpro.js only
// This eliminates the 95% overlap between the two files
// Use: new R2MultipartUploader() (available globally from ionuploaderpro.js)

// ============================================
// MEDIA VALIDATION & TYPE DETECTION
// ============================================
function validateMedia(file) {
    const validTypes = [
        // Video
        'video/mp4', 'video/webm', 'video/ogg', 'video/avi', 'video/mov', 'video/quicktime',
        // Audio
        'audio/mp3', 'audio/wav', 'audio/flac', 'audio/aac', 'audio/m4a', 'audio/ogg', 'audio/mpeg',
        // Image
        'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/svg+xml'
    ];
    
    if (!validTypes.includes(file.type)) {
        showCustomAlert('Invalid File Type', `File type ${file.type} is not supported. Please select a video, audio, or image file.`);
        return false;
    }
    
    // Check file size based on type
    const mediaType = getMediaType(file);
    const maxSize = MEDIA_TYPES[mediaType]?.maxSize || MEDIA_TYPES.video.maxSize;
    
    if (file.size > maxSize) {
        const maxSizeMB = Math.round(maxSize / (1024 * 1024));
        showCustomAlert('File Too Large', `File size exceeds ${maxSizeMB}MB limit for ${mediaType} files.`);
        return false;
    }
    
    return true;
}

// Enhanced media type detection
function getMediaType(file) {
    const videoTypes = ['video/mp4', 'video/webm', 'video/ogg', 'video/avi', 'video/mov', 'video/quicktime'];
    const audioTypes = ['audio/mp3', 'audio/wav', 'audio/flac', 'audio/aac', 'audio/m4a', 'audio/ogg', 'audio/mpeg'];
    const imageTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/svg+xml'];
    
    if (videoTypes.includes(file.type)) return 'video';
    if (audioTypes.includes(file.type)) return 'audio';
    if (imageTypes.includes(file.type)) return 'image';
    
    // Fallback to extension check
    const ext = file.name.split('.').pop().toLowerCase();
    for (const type in MEDIA_TYPES) {
        if (MEDIA_TYPES[type].extensions.includes(ext)) {
            return type;
        }
    }
    
    return 'video'; // Default
}

// Get media icon based on type
function getMediaIcon(file) {
    const mediaType = getMediaType(file);
    const icons = {
        video: '🎬',
        audio: '🎵',
        image: '🖼️'
    };
    return icons[mediaType] || '📁';
}

// Get media type display name
function getMediaTypeDisplay(file) {
    const mediaType = getMediaType(file);
    const displays = {
        video: 'Video',
        audio: 'Audio',
        image: 'Image'
    };
    return displays[mediaType] || 'Media';
}

// ============================================
// FILE SELECTION HANDLERS
// ============================================
function handleFileSelect(event) {
    console.log('📁 handleFileSelect called with event:', event);
    const files = Array.from(event.target.files);
    console.log('📁 Files from input:', files);
    
    if (files.length === 0) {
        console.log('📁 No files selected');
        return;
    }
    
    console.log(`📁 Selected ${files.length} file(s):`, files.map(f => f.name));
    
    // Validate all files
    const validFiles = files.filter(validateMedia);
    console.log('📁 Valid files after filtering:', validFiles.length);
    if (validFiles.length === 0) {
        console.log('📁 No valid files found');
        return;
    }
    
    if (validFiles.length === 1) {
        selectedFile = validFiles[0];
        currentUploadType = 'file'; // Critical: Set upload type when file is selected
        currentSource = 'local';
        bulkMode = false;
        
        console.log('📁 Single file selected:', selectedFile.name);
        
        // Show file selection feedback
        const uploadZone = document.getElementById('uploadZone');
        if (uploadZone) {
            uploadZone.innerHTML = `
                <div style="text-align: center; padding: 20px; cursor: pointer;" title="Click to select a different file">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22,4 12,14.01 9,11.01"></polyline>
                    </svg>
                    <h3 style="color: #10b981; margin: 12px 0 8px 0;">File Selected!</h3>
                    <p style="color: #cbd5e1; margin: 0 0 4px 0;">${selectedFile.name}</p>
                    <p style="color: #64748b; font-size: 13px; margin: 0;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                        Click to change file
                    </p>
                </div>
            `;
        }
        
        checkNextButton(); // Enable Next button
        
        // Auto-populate title field with file name (without extension)
        autoPopulateTitle(validFiles[0].name);
        
        console.log('📁 Single file selected, auto-proceeding to step 2');
        setTimeout(() => {
            proceedToStep2();
            
            // Generate auto thumbnail after step 2 is loaded (DOM elements are ready)
            setTimeout(() => {
                generateAutoThumbnail();
            }, 100); // Small delay to ensure DOM is ready
        }, 500); // Small delay to let step transition complete
    } else {
        // Multiple files - bulk upload
        selectedFiles = validFiles;
        currentUploadType = 'file'; // Critical: Set upload type for bulk files too
        currentSource = 'local';
        bulkMode = true;
        
        console.log('📁 Multiple files selected:', selectedFiles.length);
        checkNextButton(); // Enable Next button
        showBulkUploadInterface();
    }
}

// ============================================
// UPLOAD PROCESSING
// ============================================
async function processSingleUpload() {
    if (!selectedFile && !selectedGoogleDriveFile) {
        showCustomAlert('Error', 'No file selected for upload');
        return;
    }
    
    const fileToUpload = selectedFile || selectedGoogleDriveFile;
    console.log('🚀 Starting single upload for:', fileToUpload.name || fileToUpload.id);
    
    try {
        // Show progress
        showProgress(0, 'Preparing upload...');
        
        // Prepare metadata
        const metadata = {
            title: document.getElementById('videoTitle')?.value || fileToUpload.name || 'Untitled',
            description: document.getElementById('videoDescription')?.value || '',
            category: document.getElementById('videoCategory')?.value || 'General',
            tags: document.getElementById('videoTags')?.value || '',
            visibility: document.getElementById('videoVisibility')?.value || 'public',
            selected_channels: document.getElementById('selectedChannels')?.value || '' // CRITICAL: Include selected channels
        };
        
        // Try R2 multipart upload first (if available)
        if (window.R2MultipartUploader && fileToUpload.size > 100 * 1024 * 1024) { // 100MB+
            console.log('📤 Using R2 multipart upload for large file');
            await uploadWithR2Multipart(fileToUpload, metadata);
        } else {
            // Fallback to simple upload
            console.log('📤 Using simple upload');
            await uploadWithSimpleHandler(fileToUpload, metadata);
        }
        
    } catch (error) {
        console.error('❌ Upload failed:', error);
        showCustomAlert('Upload Failed', error.message || 'Unknown error occurred');
        hideProgress();
    }
}

async function uploadWithSimpleHandler(file, metadata) {
    const formData = new FormData();
    
    // Handle different file sources
    if (currentSource === 'googledrive' && file.id) {
        formData.append('action', 'google_drive');
        formData.append('google_drive_file_id', file.id);
        formData.append('google_drive_access_token', accessToken);
        formData.append('source', 'googledrive');
        formData.append('video_id', file.id);
        formData.append('video_link', `https://drive.google.com/file/d/${file.id}/view`);
    } else {
        formData.append('action', 'upload'); // Add missing action for file uploads
        formData.append('video', file);
    }
    
    // Add metadata
    Object.keys(metadata).forEach(key => {
        formData.append(key, metadata[key]);
    });
    
    // CRITICAL: Add thumbnail blob if available (must be separate from metadata)
    if (window.capturedThumbnailBlob) {
        formData.append('thumbnail', window.capturedThumbnailBlob, 'thumbnail.jpg');
        console.log('📸 Added thumbnail to upload:', window.capturedThumbnailBlob.size, 'bytes');
    } else {
        console.log('⚠️ No thumbnail blob available for upload');
    }
    
    const response = await fetch('./ionuploadvideos.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    });
    
    if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }
    
    const result = await response.json();
    
    if (!result.success) {
        throw new Error(result.error || 'Upload failed');
    }
    
    console.log('✅ Upload completed successfully:', result);
    showCustomAlert('Success', 'Upload completed successfully!');
    hideProgress();
    
    // Close modal or redirect
    if (window.parent && window.parent !== window) {
        window.parent.postMessage({
            type: 'upload_complete',
            result: result
        }, '*');
    }
}

async function uploadWithR2Multipart(file, metadata) {
    if (!window.R2MultipartUploader) {
        throw new Error('R2MultipartUploader not available');
    }
    
    const uploader = new window.R2MultipartUploader({
        endpoint: './ionuploadvideos.php',
        onProgress: (progress) => {
            showProgress(progress, `Uploading... ${Math.round(progress)}%`);
        }
    });
    
    const result = await uploader.upload(file, metadata);
    
    console.log('✅ R2 multipart upload completed:', result);
    showCustomAlert('Success', 'Upload completed successfully!');
    hideProgress();
    
    // Close modal or redirect
    if (window.parent && window.parent !== window) {
        window.parent.postMessage({
            type: 'upload_complete',
            result: result
        }, '*');
    }
}

// ============================================
// PLATFORM IMPORTS
// ============================================

/**
 * Check if a video already exists by URL (early duplicate detection)
 */
async function checkDuplicateVideo(url, platform) {
    console.log('🔍 Checking duplicate for:', url, platform);
    
    const formData = new FormData();
    formData.append('action', 'check_platform_url');
    formData.append('url', url);
    formData.append('platform', platform);
    
    const response = await fetch('./check-duplicate-video.php', {
        method: 'POST',
        body: formData
    });
    
    if (!response.ok) {
        throw new Error('Duplicate check request failed');
    }
    
    const result = await response.json();
    
    if (!result.success) {
        throw new Error(result.error || 'Duplicate check failed');
    }
    
    return result; // Returns { exists: true/false, message: '...', video_id?: ..., ... }
}

function validatePlatformUrl(url, platform) {
    if (!url || !platform) return false;
    
    // Try advanced handlers first
    if (window.IONUploadAdvanced && window.IONUploadAdvanced.PlatformHandlers) {
        const handler = window.IONUploadAdvanced.PlatformHandlers[platform];
        if (handler && handler.validate) {
            return handler.validate(url);
        }
    }
    
    // Fallback validation patterns
    return validatePlatformUrlFallback(url, platform);
}

function validatePlatformUrlFallback(url, platform) {
    const patterns = {
        youtube: /(?:youtube\.com\/(?:watch\?.*v=|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/,
        vimeo: /vimeo\.com\/(?:channels\/[^\/]+\/|groups\/[^\/]+\/videos\/|album\/[^\/]+\/video\/|video\/|)(\d+)/,
        wistia: /(?:wistia\.(?:com|net)\/(?:medias|embed)\/|wi\.st\/)([a-zA-Z0-9]{10})/,
        loom: /(?:loom\.com\/share\/)([a-zA-Z0-9]+)/,
        muvi: /muvi/i
    };
    
    const pattern = patterns[platform];
    if (!pattern) return false;
    
    try {
        return pattern.test(url);
    } catch (error) {
        console.warn('Pattern validation error:', error);
        return false;
    }
}

// ============================================
// UI HELPERS
// ============================================

function showCustomAlert(title, message) {
    console.log('🎯 showCustomAlert called with HTML support - Cache cleared!', {title, message});
    
    // Create a proper modal for HTML content matching uploader theme
    const modal = document.createElement('div');
    modal.style.cssText = `
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.8); z-index: 99999; display: flex;
        align-items: center; justify-content: center; padding: 20px;
    `;
    
    const content = document.createElement('div');
    content.style.cssText = `
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        border-radius: 12px; max-width: 500px; width: 100%;
        max-height: 80vh; overflow-y: auto; position: relative;
        border: 1px solid rgba(178, 130, 84, 0.3);
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
    `;
    
    // If message contains HTML tags, use innerHTML, otherwise use textContent
    if (message.includes('<')) {
        content.innerHTML = message;
    } else {
        // Determine if this is an error or success message
        const isError = title && (title.toLowerCase().includes('error') || title.toLowerCase().includes('failed'));
        const titleColor = isError ? '#ef4444' : '#b28254';
        const buttonBg = isError ? '#ef4444' : '#b28254';
        
        content.innerHTML = `
            <div style="padding: 32px; text-align: center;">
                ${title ? `<h3 style="margin: 0 0 16px 0; color: ${titleColor}; font-size: 20px; font-weight: 600;">${title}</h3>` : ''}
                <p style="margin: 0; color: #cbd5e1; font-size: 15px; line-height: 1.6;">${message}</p>
                <button onclick="this.closest('.custom-modal').remove()" 
                        style="margin-top: 24px; padding: 12px 32px; background: ${buttonBg}; color: white; 
                               border: none; border-radius: 6px; cursor: pointer; font-size: 15px; font-weight: 500;
                               transition: all 0.2s ease; box-shadow: 0 4px 12px rgba(0,0,0,0.2);"
                        onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 16px rgba(0,0,0,0.3)';"
                        onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.2)';">
                    OK
                </button>
            </div>
        `;
    }
    
    modal.className = 'custom-modal';
    modal.appendChild(content);
    document.body.appendChild(modal);
    
    // Close on background click
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.remove();
        }
    });
    
    return modal;
}

function proceedToStep2() {
    console.log('➡️ Proceeding to step 2');
    
    // Hide step 1, show step 2 with FLEX layout
    const step1 = document.getElementById('step1');
    const step2 = document.getElementById('step2');
    
    if (step1) {
        step1.style.display = 'none';
        console.log('✅ Step 1 hidden');
    }
    
    if (step2) {
        // IMPORTANT: Reset ALL visibility properties that were set in goBackToStep1()
        step2.style.visibility = 'visible';
        step2.style.position = 'relative';
        step2.style.left = '0';
        step2.style.display = 'block';
        step2.classList.add('step2-content');
        
        // Force grid layout with inline styles as backup
        step2.style.setProperty('display', 'grid', 'important');
        step2.style.setProperty('grid-template-columns', '1fr 1fr', 'important');
        step2.style.setProperty('gap', '32px', 'important');
        step2.style.setProperty('padding', '12px 0 12px 12px', 'important');
        
        console.log('✅ Step 2 shown with grid layout and ALL visibility properties reset');
        console.log('🔍 Step 2 classes:', step2.className);
        console.log('🔍 Step 2 computed display:', window.getComputedStyle(step2).display);
        console.log('🔍 Step 2 computed grid-template-columns:', window.getComputedStyle(step2).gridTemplateColumns);
    }
    
    // Update button text for step 2 AND enable it
    const nextBtn = document.getElementById('nextBtn');
    const nextBtnText = document.getElementById('nextBtnText');
    if (nextBtnText) {
        if (currentUploadType === 'import') {
            nextBtnText.textContent = 'Import Media';
            console.log('✅ Button text updated to "Import Media"');
        } else {
            nextBtnText.textContent = 'Upload Media';
            console.log('✅ Button text updated to "Upload Media"');
        }
    }
    
    // Enable the button for Step 2 (user is ready to upload/import)
    if (nextBtn) {
        nextBtn.disabled = false;
        console.log('✅ Upload/Import button ENABLED for Step 2');
    }
    
    // Change back button to "< Back" button for step 2
    const backBtn = document.getElementById('backBtn');
    if (backBtn) {
        backBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px;"><polyline points="15,18 9,12 15,6"></polyline></svg> Back';
        backBtn.onclick = goBackToStep1;
        console.log('✅ Back button changed to "< Back"');
    }
    
    // Setup preview for files or imports
    if (selectedFile || (currentUploadType === 'import' && window.importedVideoUrl)) {
        setupVideoPreview();
    }
}

function goBackToStep1() {
    console.log('⬅️ Going back to step 1');
    
    // Clear all upload state
    selectedFile = null;
    selectedFiles = [];
    currentUploadType = null;
    currentSource = null;
    window.capturedThumbnailBlob = null;
    window.customThumbnailSource = null;
    
    // Clear URL input
    const urlInput = document.getElementById('urlInput');
    if (urlInput) {
        urlInput.value = '';
    }
    
    // Clear file input
    const fileInput = document.getElementById('fileInput');
    if (fileInput) {
        fileInput.value = '';
    }
    
    // Hide URL section
    const urlSection = document.getElementById('urlInputSection');
    if (urlSection) {
        urlSection.style.display = 'none';
    }
    
    // Clear video player
    const playerContainer = document.getElementById('videoPlayerContainer');
    if (playerContainer) {
        playerContainer.innerHTML = '';
    }
    
    // Remove selected state from platform buttons
    document.querySelectorAll('.source-btn').forEach(btn => {
        btn.classList.remove('selected');
    });
    
    // Hide step 2, show step 1
    const step1 = document.getElementById('step1');
    const step2 = document.getElementById('step2');
    const bulkStep2 = document.getElementById('bulkStep2');
    
    // IMPORTANT: Hide step 2 completely
    if (step2) {
        step2.style.display = 'none !important';
        step2.style.visibility = 'hidden';
        step2.style.position = 'absolute';
        step2.style.left = '-9999px';
        step2.classList.remove('step2-content');
        console.log('✅ Step 2 completely hidden');
    }
    
    // Also hide bulk step 2 if visible
    if (bulkStep2) {
        bulkStep2.style.display = 'none';
        console.log('✅ Bulk Step 2 hidden');
    }
    
    // Show step 1
    if (step1) {
        step1.style.display = 'block';
        step1.style.visibility = 'visible';
        step1.style.position = 'relative';
        step1.style.left = '0';
        console.log('✅ Step 1 shown and visible');
    }
    
    // Reset button text for step 1
    const nextBtnText = document.getElementById('nextBtnText');
    if (nextBtnText) {
        nextBtnText.textContent = 'Next';
        console.log('✅ Button text reset to "Next"');
    }
    
    // Reset back button to "Cancel" for step 1
    const backBtn = document.getElementById('backBtn');
    if (backBtn) {
        backBtn.textContent = 'Cancel';
        // Remove all event listeners by cloning the button
        const newBackBtn = backBtn.cloneNode(true);
        backBtn.parentNode.replaceChild(newBackBtn, backBtn);
        // Set new click handler
        newBackBtn.onclick = handleDiscardAndClose;
        console.log('✅ Back button reset to "Cancel" with close handler');
    }
    
    // Disable Next button until new selection
    const nextBtn = document.getElementById('nextBtn');
    if (nextBtn) {
        nextBtn.disabled = true;
    }
    
    console.log('✅ Upload state reset, ready for new selection');
}

function setupVideoPreview() {
    console.log('🎬 Setting up video preview');
    
    const container = document.getElementById('videoPlayerContainer');
    
    // Handle imported videos
    if (currentUploadType === 'import' && window.importedVideoUrl) {
        console.log('🎬 Setting up imported video preview:', currentSource);
        setupImportedVideoPreview(container, window.importedVideoUrl);
        
        // Update capture button visibility for imported videos
        updateCaptureButtonVisibility();
        return;
    }
    if (!container) {
        console.error('Video player container not found');
        return;
    }
    
    // For edit mode, use editVideoData
    if (window.editVideoData && window.editVideoData.video_link) {
        console.log('🎬 Loading video in edit mode:', window.editVideoData);
        renderVideoPlayer(container, window.editVideoData);
        
        // Update capture button visibility for edit mode
        updateCaptureButtonVisibility();
        return;
    }
    
    // Also check the global editVideoData variable
    if (typeof editVideoData !== 'undefined' && editVideoData && editVideoData.video_link) {
        console.log('🎬 Loading video in edit mode (global var):', editVideoData);
        window.editVideoData = editVideoData; // Store for other functions
        renderVideoPlayer(container, editVideoData);
        
        // Update capture button visibility for edit mode
        updateCaptureButtonVisibility();
        return;
    }
    
    // For new uploads, use selectedFile
    if (selectedFile) {
        // Handle Google Drive files specially
        if (selectedFile.source === 'googledrive') {
            console.log('🎬 Setting up Google Drive file preview:', selectedFile.name);
            
            // Auto-populate title from Google Drive file name
            const titleInput = document.getElementById('videoTitle');
            if (titleInput && !titleInput.value) {
                // Remove file extension and clean up the name
                let cleanTitle = selectedFile.name.replace(/\.[^/.]+$/, ''); // Remove extension
                cleanTitle = cleanTitle.replace(/[_-]/g, ' '); // Replace underscores/dashes with spaces
                cleanTitle = cleanTitle.replace(/\s+/g, ' ').trim(); // Clean up multiple spaces
                
                // Capitalize first letter of each word
                cleanTitle = cleanTitle.split(' ').map(word => 
                    word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()
                ).join(' ');
                
                titleInput.value = cleanTitle;
                console.log('📝 Title auto-populated from Google Drive file:', cleanTitle);
            }
            
            // Show Google Drive placeholder (file will be fetched server-side on upload)
            container.innerHTML = `
                <div style="width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; background: rgba(0,0,0,0.3); border-radius: 8px; padding: 20px; text-align: center;">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-bottom: 16px; opacity: 0.6;">
                        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                    </svg>
                    <div style="font-size: 16px; font-weight: 600; margin-bottom: 8px; color: rgba(255,255,255,0.9);">Google Drive File</div>
                    <div style="font-size: 14px; color: rgba(255,255,255,0.6); margin-bottom: 4px;">${selectedFile.name}</div>
                    <div style="font-size: 12px; color: rgba(255,255,255,0.4);">File will be imported from Google Drive</div>
                </div>
            `;
            
            // Update capture button visibility for Google Drive (hide it)
            updateCaptureButtonVisibility();
            return;
        }
        
        // Handle local file uploads
        console.log('🎬 Setting up video preview for new file:', selectedFile.name);
        const fileUrl = URL.createObjectURL(selectedFile);
        const videoData = {
            video_link: fileUrl,
            source: 'upload',
            title: selectedFile.name
        };
        renderVideoPlayer(container, videoData);
        
        // Update capture button visibility for uploaded files
        updateCaptureButtonVisibility();
        return;
    }
    
    // For platform imports, use currentVideoData
    if (window.currentVideoData) {
        console.log('🎬 Setting up video preview for platform import:', window.currentVideoData);
        renderVideoPlayer(container, window.currentVideoData);
        
        // Update capture button visibility for platform imports
        updateCaptureButtonVisibility();
        return;
    }
    
    console.warn('🎬 No video data available for preview');
}

// ============================================
// EDIT MODE UI SETUP
// ============================================
function setupEditModeUI() {
    console.log('🎨 Setting up edit mode UI');
    
    // Show the Update button
    const updateBtn = document.getElementById('updateBtn');
    if (updateBtn) {
        updateBtn.style.display = 'block';
        console.log('✅ Update button shown');
    }
    
    // Hide upload-specific elements
    const step1 = document.getElementById('step1');
    if (step1) {
        step1.style.display = 'none';
    }
    
    // Show step2 (the form) with FLEX layout
    const step2 = document.getElementById('step2');
    if (step2) {
        step2.style.display = 'block';
        step2.classList.add('step2-content');
        
        // Force grid layout with inline styles as backup
        step2.style.setProperty('display', 'grid', 'important');
        step2.style.setProperty('grid-template-columns', '1fr 1fr', 'important');
        step2.style.setProperty('gap', '32px', 'important');
        step2.style.setProperty('padding', '12px 0 12px 12px', 'important');
        
        console.log('✅ Step 2 shown with grid layout in edit mode');
        console.log('🔍 Edit mode - Step 2 classes:', step2.className);
        console.log('🔍 Edit mode - Step 2 computed display:', window.getComputedStyle(step2).display);
        console.log('🔍 Edit mode - Step 2 computed grid-template-columns:', window.getComputedStyle(step2).gridTemplateColumns);
    }
    
    // Populate form fields with existing data
    if (window.editVideoData) {
        populateFormFields(window.editVideoData);
    }
}

function populateFormFields(videoData) {
    console.log('📝 Populating form fields with:', videoData);
    console.log('📝 Badges value:', videoData.badges);
    console.log('📝 All keys:', Object.keys(videoData));
    
    // Title
    const titleInput = document.getElementById('videoTitle');
    if (titleInput && videoData.title) {
        titleInput.value = videoData.title;
        updateCharCount('titleCount', titleInput.value.length);
        
        // Add input event listener for real-time updates
        titleInput.addEventListener('input', function() {
            updateCharCount('titleCount', this.value.length);
        });
    }
    
    // Description
    const descInput = document.getElementById('videoDescription');
    if (descInput && videoData.description) {
        descInput.value = videoData.description;
        updateCharCount('descCount', descInput.value.length);
        
        // Add input event listener for real-time updates
        descInput.addEventListener('input', function() {
            updateCharCount('descCount', this.value.length);
        });
    }
    
    // Category
    const categorySelect = document.getElementById('videoCategory');
    if (categorySelect && videoData.category) {
        categorySelect.value = videoData.category;
    }
    
    // Tags
    const tagsInput = document.getElementById('videoTags');
    if (tagsInput && videoData.tags) {
        tagsInput.value = videoData.tags;
    }
    
    // Visibility
    const visibilitySelect = document.getElementById('videoVisibility');
    if (visibilitySelect && videoData.visibility) {
        // Convert visibility format if needed
        const visibility = videoData.visibility.toLowerCase();
        visibilitySelect.value = visibility;
    }
    
    // Badges
    const badgesInput = document.getElementById('videoBadges');
    if (badgesInput && videoData.badges) {
        badgesInput.value = videoData.badges;
        // Trigger badge display update if the Pro functionality is available
        if (window.IONUploaderPro && window.IONUploaderPro.updateBadgeDisplay) {
            window.IONUploaderPro.updateBadgeDisplay();
        }
    }
    
    console.log('✅ Form fields populated');
}

function setupBadgeHandlers() {
    console.log('🏷️ Setting up badge handlers');
    
    // Check if user has permission to manage badges
    if (typeof canManageBadges !== 'undefined' && !canManageBadges) {
        console.log('🏷️ User does not have permission to manage badges, skipping setup');
        return;
    }
    
    const badgeInput = document.getElementById('badgeInput');
    const badgeDropdown = document.getElementById('badgeDropdown');
    
    if (badgeInput && badgeDropdown) {
        // Make badge input clickable
        badgeInput.addEventListener('click', (e) => {
            e.preventDefault();
            console.log('🏷️ Badge input clicked');
            
            // Toggle dropdown visibility
            if (badgeDropdown.style.display === 'none' || !badgeDropdown.style.display) {
                badgeDropdown.style.display = 'block';
                console.log('🏷️ Badge dropdown shown');
            } else {
                badgeDropdown.style.display = 'none';
                console.log('🏷️ Badge dropdown hidden');
            }
        });
        
        // Handle badge option clicks
        const badgeOptions = badgeDropdown.querySelectorAll('.badge-option');
        badgeOptions.forEach(option => {
            option.addEventListener('click', (e) => {
                e.preventDefault();
                const badgeValue = option.getAttribute('data-value');
                const badgeText = option.textContent;
                
                console.log('🏷️ Badge selected:', badgeValue);
                
                // Add badge to input display
                addBadgeToInput(badgeValue, badgeText);
                
                // Hide dropdown
                badgeDropdown.style.display = 'none';
            });
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!badgeInput.contains(e.target) && !badgeDropdown.contains(e.target)) {
                badgeDropdown.style.display = 'none';
            }
        });
    } else {
        console.log('🏷️ Badge elements not found, checking for Pro initialization');
        
        // Fallback: Try to initialize Pro badges after a delay
        setTimeout(() => {
            if (window.IONUploaderPro && window.IONUploaderPro.initializeBadgeInput) {
                console.log('🏷️ Initializing Pro badge functionality');
                window.IONUploaderPro.initializeBadgeInput();
            }
        }, 500);
    }
}

function addBadgeToInput(value, text) {
    // Check if user has permission to manage badges
    if (typeof canManageBadges !== 'undefined' && !canManageBadges) {
        console.log('🏷️ User does not have permission to add badges');
        return;
    }
    
    const badgeInput = document.getElementById('badgeInput');
    const hiddenInput = document.getElementById('videoBadges');
    
    if (badgeInput && hiddenInput) {
        // Get current badges
        const currentBadges = hiddenInput.value ? hiddenInput.value.split(',') : [];
        
        // Don't add duplicate badges
        if (!currentBadges.includes(value)) {
            currentBadges.push(value);
            
            // Update hidden input
            hiddenInput.value = currentBadges.join(',');
            
            // Create badge element
            const badgeElement = document.createElement('span');
            badgeElement.className = 'selected-badge';
            badgeElement.innerHTML = `
                ${text}
                <button type="button" class="remove-badge" data-value="${value}">&times;</button>
            `;
            
            // Add to display
            badgeInput.appendChild(badgeElement);
            
            // Handle remove button
            const removeBtn = badgeElement.querySelector('.remove-badge');
            removeBtn.addEventListener('click', (e) => {
                e.preventDefault();
                removeBadgeFromInput(value);
            });
            
            console.log('🏷️ Badge added:', value);
        }
    }
}

function removeBadgeFromInput(value) {
    // Check if user has permission to manage badges
    if (typeof canManageBadges !== 'undefined' && !canManageBadges) {
        console.log('🏷️ User does not have permission to remove badges');
        return;
    }
    
    const badgeInput = document.getElementById('badgeInput');
    const hiddenInput = document.getElementById('videoBadges');
    
    if (badgeInput && hiddenInput) {
        // Update hidden input
        const currentBadges = hiddenInput.value ? hiddenInput.value.split(',') : [];
        const updatedBadges = currentBadges.filter(badge => badge !== value);
        hiddenInput.value = updatedBadges.join(',');
        
        // Remove from display
        const badgeElement = badgeInput.querySelector(`[data-value="${value}"]`);
        if (badgeElement && badgeElement.parentElement) {
            badgeElement.parentElement.remove();
        }
        
        console.log('🏷️ Badge removed:', value);
    }
}

function updateCharCount(counterId, length) {
    const counter = document.getElementById(counterId);
    if (counter) {
        counter.textContent = length;
    }
}

function getFormMetadata() {
    console.log('📝 Getting form metadata');
    
    const formData = new FormData();
    
    // Get form values
    const title = document.getElementById('videoTitle')?.value?.trim() || '';
    const description = document.getElementById('videoDescription')?.value?.trim() || '';
    const category = document.getElementById('videoCategory')?.value || '';
    const tags = document.getElementById('videoTags')?.value?.trim() || '';
    const visibility = document.getElementById('videoVisibility')?.value || 'public';
    const badges = document.getElementById('videoBadges')?.value || '';
    
    console.log('📝 Form values:', { title, description, category, tags, visibility, badges });
    
    // Validate required fields
    if (!title) {
        alert('Title is required');
        return null;
    }
    
    // Add to FormData
    formData.append('title', title);
    formData.append('description', description);
    formData.append('category', category);
    formData.append('tags', tags);
    formData.append('visibility', visibility);
    formData.append('badges', badges);
    
    // CRITICAL: Include selected channels
    const selectedChannelsValue = document.getElementById('selectedChannels')?.value || '';
    if (selectedChannelsValue) {
        formData.append('selected_channels', selectedChannelsValue);
        console.log('📝 Selected channels:', selectedChannelsValue);
    }
    
    // Add thumbnail if available (CRITICAL: must be named 'thumbnail' to match backend)
    if (window.capturedThumbnailBlob) {
        formData.append('thumbnail', window.capturedThumbnailBlob, 'thumbnail.jpg');
        console.log('📝 Added captured thumbnail blob:', window.capturedThumbnailBlob.size, 'bytes');
    } else {
        console.log('⚠️ No thumbnail blob available');
    }
    
    console.log('📝 FormData prepared successfully');
    return formData;
}

// ============================================
// EDIT MODE BUTTON HANDLERS
// ============================================
function handleDeleteVideo() {
    if (!window.editVideoData || !window.editVideoData.id) {
        console.error('❌ No video data available for deletion');
        return;
    }
    
    const videoTitle = window.editVideoData.title || 'this video';
    
    if (confirm(`Are you sure you want to delete "${videoTitle}"? This action cannot be undone.`)) {
        console.log('🗑️ Deleting video ID:', window.editVideoData.id);
        
        // Create form data for deletion
        const formData = new FormData();
        formData.append('action', 'delete_video');
        formData.append('video_id', window.editVideoData.id);
        
        // Show loading state
        const deleteBtn = document.getElementById('deleteBtn');
        if (deleteBtn) {
            deleteBtn.disabled = true;
            deleteBtn.innerHTML = '<span>Deleting...</span>';
        }
        
        // Send delete request
        fetch('./ionuploadvideos.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('✅ Video deleted successfully');
                alert('Video deleted successfully!');
                // Close modal and refresh parent page
                if (window.parent) {
                    window.parent.location.reload();
                } else {
                    window.location.href = './creators.php';
                }
            } else {
                console.error('❌ Delete failed:', data.error);
                alert('Failed to delete video: ' + (data.error || 'Unknown error'));
                // Restore button state
                if (deleteBtn) {
                    deleteBtn.disabled = false;
                    deleteBtn.innerHTML = '<span>Delete</span>';
                }
            }
        })
        .catch(error => {
            console.error('❌ Delete request failed:', error);
            alert('Failed to delete video: ' + error.message);
            // Restore button state
            if (deleteBtn) {
                deleteBtn.disabled = false;
                deleteBtn.innerHTML = '<span>Delete</span>';
            }
        });
    }
}

function handleDiscardAndClose() {
    console.log('🚪 Discarding changes and closing modal');
    closeModal();
}

function closeModal() {
    const urlParams = new URLSearchParams(window.location.search);
    const isIframe = urlParams.get('direct') !== '1';
    
    if (isIframe && window.parent && window.parent.postMessage) {
        window.parent.postMessage({ type: 'close_modal' }, '*');
    } else {
        window.location.href = '/app/creators.php';
    }
}

function handleUpdateVideo() {
    console.log('💾 handleUpdateVideo called');
    
    // Prevent double-clicks
    const updateBtn = document.getElementById('updateBtn');
    if (updateBtn && updateBtn.disabled) {
        console.log('💾 Update already in progress, ignoring');
        return;
    }
    
    if (!window.editVideoData || !window.editVideoData.id) {
        console.error('❌ No video data available for update');
        alert('Error: No video data available for update');
        return;
    }
    
    console.log('💾 Updating video ID:', window.editVideoData.id);
    
    // Get form data
    const formData = getFormMetadata();
    if (!formData) {
        console.error('❌ Failed to get form data');
        return;
    }
    
    console.log('💾 Form data obtained, proceeding with update');
    
    // Add video ID and action
    formData.append('action', 'update_video');
    formData.append('video_id', window.editVideoData.id);
    
    // Show loading state (reuse the updateBtn variable from above)
    if (updateBtn) {
        updateBtn.disabled = true;
        updateBtn.innerHTML = '<span>Updating...</span>';
    }
    
    // Send update request
    fetch('./ionuploadvideos.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => {
        console.log('💾 Response status:', response.status);
        console.log('💾 Response headers:', response.headers);
        
        // First get the raw response text to debug
        return response.text().then(text => {
            console.log('💾 Raw response text:', text);
            console.log('💾 Response length:', text.length);
            
            if (!text || text.trim() === '') {
                throw new Error('Server returned empty response');
            }
            
            // Check if it looks like JSON
            if (!text.trim().startsWith('{') && !text.trim().startsWith('[')) {
                console.error('❌ Server response is not JSON:', text.substring(0, 500));
                throw new Error('Server returned non-JSON response: ' + text.substring(0, 200));
            }
            
            try {
                return JSON.parse(text);
            } catch (parseError) {
                console.error('❌ JSON parse error:', parseError);
                console.error('❌ Failed to parse:', text.substring(0, 500));
                throw new Error('Invalid JSON response: ' + parseError.message);
            }
        });
    })
    .then(data => {
        if (data.success) {
            console.log('✅ Video updated successfully');
            alert('Video updated successfully!');
            // Close modal and refresh parent page
            if (window.parent) {
                window.parent.location.reload();
            } else {
                window.location.href = './creators.php';
            }
        } else {
            console.error('❌ Update failed:', data.error);
            alert('Failed to update video: ' + (data.error || 'Unknown error'));
            // Restore button state
            if (updateBtn) {
                updateBtn.disabled = false;
                updateBtn.innerHTML = '<span>Update Media</span>';
            }
        }
    })
    .catch(error => {
        console.error('❌ Update request failed:', error);
        console.error('❌ Error details:', {
            message: error.message,
            stack: error.stack,
            name: error.name
        });
        
        // More detailed error message
        let errorMsg = 'Failed to update video';
        if (error.message) {
            errorMsg += ': ' + error.message;
        }
        
        alert(errorMsg);
        
        // Restore button state
        if (updateBtn) {
            updateBtn.disabled = false;
            updateBtn.innerHTML = '<span>Update Media</span>';
        }
    });
}

function renderVideoPlayer(container, videoData) {
    if (!container) {
        console.error('❌ Video player container not found');
        return;
    }
    
    if (!videoData || !videoData.video_link) {
        console.error('❌ No video data or video_link provided');
        container.innerHTML = '<div style="display: flex; align-items: center; justify-content: center; height: 300px; color: #ef4444;">No video data available</div>';
        return;
    }
    
    console.log('🎬 Rendering video player for:', videoData);
    
    const source = videoData.source || 'upload';
    const videoUrl = videoData.video_link;
    
    try {
        console.log('🎬 Determining player type - source:', source, 'videoUrl:', videoUrl);
        
        if (source === 'youtube' || videoUrl.includes('youtube.com') || videoUrl.includes('youtu.be')) {
            console.log('🎬 → Using YouTube player');
            renderYouTubePlayer(container, videoData);
        } else if (source === 'vimeo' || videoUrl.includes('vimeo.com')) {
            console.log('🎬 → Using Vimeo player');
            renderVimeoPlayer(container, videoData);
        } else if (source === 'upload' || source === 'drive' || videoUrl.startsWith('blob:') || videoUrl.startsWith('http')) {
            console.log('🎬 → Using direct video player');
            renderDirectVideoPlayer(container, videoData);
        } else {
            console.warn('🎬 → Unknown video source, using direct player as fallback');
            renderDirectVideoPlayer(container, videoData);
        }
        
        // Update thumbnail preview if available
        updateThumbnailPreview(videoData.thumbnail);
        
    } catch (error) {
        console.error('❌ Error rendering video player:', error);
        container.innerHTML = `<div style="display: flex; align-items: center; justify-content: center; height: 300px; color: #ef4444;">Error loading video: ${error.message}</div>`;
    }
}

function renderYouTubePlayer(container, videoData) {
    const videoId = extractVideoId(videoData.video_link, 'youtube');
    if (!videoId) {
        throw new Error('Invalid YouTube URL');
    }
    
    console.log('🎬 Rendering YouTube player for ID:', videoId);
    
    // Use HTML5 video with YouTube video source (via youtube-dl or proxy)
    // For direct access, we'll use a proxy video element approach
    container.innerHTML = `
        <div class="youtube-player-wrapper" style="position: relative; width: 100%; height: 300px; background: #000; border-radius: 8px;">
            <video 
                id="youtubeProxyVideo"
                class="video-js" 
                controls 
                preload="metadata"
                crossorigin="anonymous"
                poster="https://img.youtube.com/vi/${videoId}/maxresdefault.jpg"
                style="width: 100%; height: 100%; border-radius: 8px; display: none;">
                <!-- Fallback to iframe if video source doesn't work -->
            </video>
            <iframe 
                id="youtubeIframePlayer"
                width="100%" 
                height="100%" 
                src="https://www.youtube.com/embed/${videoId}?rel=0&modestbranding=1&enablejsapi=1" 
                frameborder="0" 
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                allowfullscreen
                style="border-radius: 8px; display: block;">
            </iframe>
        </div>
    `;
    
    // Store video ID for frame capture fallback
    container.dataset.youtubeId = videoId;
    container.dataset.videoType = 'youtube';
}

function renderVimeoPlayer(container, videoData) {
    console.log('renderVimeoPlayer called with:', videoData);
    const videoId = extractVideoId(videoData.video_link, 'vimeo');
    console.log('Extracted Vimeo ID:', videoId, 'from URL:', videoData.video_link);

    if (!videoId) {
        console.error('Failed to extract Vimeo ID from URL:', videoData.video_link);
        throw new Error('Invalid Vimeo URL');
    }

    console.log('Rendering Vimeo player for ID:', videoId);

    container.innerHTML = `
        <div class="vimeo-player-wrapper" style="position: relative; width: 100%; height: 300px; background: #000; border-radius: 8px;">
            <iframe 
                id="vimeoIframePlayer"
                width="100%" 
                height="100%" 
                src="https://player.vimeo.com/video/${videoId}" 
                frameborder="0" 
                allow="autoplay; fullscreen; picture-in-picture" 
                allowfullscreen
                style="border-radius: 8px;">
            </iframe>
        </div>
    `;

    // Store video ID for frame capture fallback
    container.dataset.vimeoId = videoId;
    container.dataset.videoType = 'vimeo';
}

function renderDirectVideoPlayer(container, videoData) {
    console.log('Rendering direct video player for:', videoData.video_link);

    container.innerHTML = `
        <video 
            width="100%" 
            height="300" 
            controls 
            preload="metadata"
            crossorigin="anonymous"
            style="border-radius: 8px; background: #000;"
            onloadedmetadata="console.log('🎬 Video metadata loaded')"
            onerror="console.error('🎬 Video load error')">
            <source src="${videoData.video_link}" type="video/mp4">
            <source src="${videoData.video_link}" type="video/webm">
            <source src="${videoData.video_link}" type="video/ogg">
            Your browser does not support the video tag.
        </video>
    `;
}

function loadVideoPlayer() {
    console.log('🎬 Loading video player in edit mode');
    
    // Check if we have edit video data
    if (typeof editVideoData !== 'undefined' && editVideoData && editVideoData.video_link) {
        console.log('✅ Using existing edit video data:', editVideoData);
        // Store in window for other functions to access
        window.editVideoData = editVideoData;
        setupVideoPreview();
        return;
    }
    
    // Try to load video data via AJAX if we have an ID but no data
    if (typeof editVideoId !== 'undefined' && editVideoId && (!editVideoData || !editVideoData.video_link)) {
        console.log('🔄 Loading video data for ID:', editVideoId);
        
        fetch('./get-video-data.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `video_id=${encodeURIComponent(editVideoId)}`,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data && data.success && data.video) {
                window.editVideoData = data.video;
                console.log('✅ Loaded video data via AJAX:', window.editVideoData);
                setupVideoPreview();
            } else {
                console.error('❌ Failed to load video data:', data);
                showVideoLoadError(data?.error || 'Unknown error');
            }
        })
        .catch(error => {
            console.error('❌ AJAX error loading video data:', error);
            showVideoLoadError(error.message);
        });
    }
}

function showVideoLoadError(message) {
    const container = document.getElementById('videoPlayerContainer');
    if (container) {
        container.innerHTML = `<div style="display: flex; align-items: center; justify-content: center; height: 300px; color: #ef4444;">Failed to load video: ${message}</div>`;
    }
}

function updateCaptureButtonVisibility() {
    console.log('🎬 Updating capture button visibility');
    const generateBtn = document.getElementById('generateThumbnailBtn');
    if (!generateBtn) {
        console.log('🎬 Capture button not found in DOM');
        return;
    }
    
    let shouldShow = true;
    let reason = 'default visible';
    
    // Hide for Google Drive (can't preview/capture before upload)
    if (currentSource === 'googledrive') {
        shouldShow = false;
        reason = 'Google Drive - no preview available';
    }
    // Hide in edit mode if embedded video without capture permission
    else if (window.editVideoData && window.editVideoData.can_capture === false) {
        shouldShow = false;
        reason = 'edit mode without capture permission';
    }
    // Show for YouTube/Vimeo (user can try, CORS errors will be handled)
    
    // Apply visibility
    if (shouldShow) {
        generateBtn.style.display = 'block';
        console.log('✅ Capture button shown:', reason);
    } else {
        generateBtn.style.display = 'none';
        console.log('❌ Capture button hidden:', reason);
    }
    
    // Note: The captureVideoFrame() function will show appropriate error messages
    // for embedded videos (YouTube, Vimeo) that can't be captured due to CORS
}

function showBulkUploadInterface() {
    console.log('📦 Showing bulk upload interface for', selectedFiles.length, 'files');
    
    // Check if Pro version's bulk upload is available
    if (typeof generateBulkList === 'function') {
        console.log('✅ Using Pro version bulk upload (generateBulkList)');
        
        // Hide step 1, show bulk step 2
        const step1 = document.getElementById('step1');
        const step2 = document.getElementById('step2');
        const bulkStep2 = document.getElementById('bulkStep2');
        
        if (step1) {
            step1.style.display = 'none';
            console.log('✅ Step 1 hidden');
        }
        if (step2) {
            step2.style.display = 'none';
            console.log('✅ Step 2 hidden');
        }
        if (bulkStep2) {
            bulkStep2.style.display = 'block';
            console.log('✅ Bulk interface shown (Pro)');
        }
        
        // Update file count
        const fileCountElement = document.getElementById('bulkFileCount');
        if (fileCountElement) {
            fileCountElement.textContent = `${selectedFiles.length} files selected`;
        }
        
        // Update button text
        const nextBtnText = document.getElementById('nextBtnText');
        if (nextBtnText) {
            nextBtnText.textContent = 'Upload All Files';
            console.log('✅ Button text updated to "Upload All Files"');
        }
        
        // Change back button to "< Back" for bulk upload
        const backBtn = document.getElementById('backBtn');
        if (backBtn) {
            backBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px;"><polyline points="15,18 9,12 15,6"></polyline></svg> Back';
            backBtn.onclick = goBackToStep1;
            console.log('✅ Back button changed to "< Back" (bulk mode)');
        }
        
        // Use Pro version's bulk list generator
        generateBulkList(selectedFiles);
        return;
    }
    
    // Fallback to basic bulk upload interface (if Pro not loaded)
    console.log('⚠️ Pro version not available, using basic bulk upload');
    
    const step1 = document.getElementById('step1');
    const step2 = document.getElementById('step2');
    const bulkStep2 = document.getElementById('bulkStep2');
    
    if (step1) {
        step1.style.display = 'none';
        console.log('✅ Step 1 hidden');
    }
    if (step2) {
        step2.style.display = 'none';
        console.log('✅ Step 2 hidden');
    }
    if (bulkStep2) {
        bulkStep2.style.display = 'block';
        console.log('✅ Bulk interface shown (basic)');
        
        // Update file count
        const fileCountElement = document.getElementById('bulkFileCount');
        if (fileCountElement) {
            fileCountElement.textContent = `${selectedFiles.length} files selected`;
        }
        
        // Update button text
        const nextBtnText = document.getElementById('nextBtnText');
        if (nextBtnText) {
            nextBtnText.textContent = 'Upload All Files';
            console.log('✅ Button text updated to "Upload All Files"');
        }
        
        // Change back button to "< Back" for bulk upload (basic)
        const backBtn = document.getElementById('backBtn');
        if (backBtn) {
            backBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px;"><polyline points="15,18 9,12 15,6"></polyline></svg> Back';
            backBtn.onclick = goBackToStep1;
            console.log('✅ Back button changed to "< Back" (basic bulk mode)');
        }
        
        // Populate basic file list
        populateBulkFileList();
    } else {
        console.error('❌ bulkStep2 element not found');
    }
}

function populateBulkFileList() {
    const fileListContainer = document.getElementById('bulkFileList');
    if (!fileListContainer) {
        console.error('❌ bulkFileList container not found');
        return;
    }
    
    console.log('📋 Populating bulk file list with', selectedFiles.length, 'files');
    
    // Clear existing content
    fileListContainer.innerHTML = '';
    
    // Create file cards
    selectedFiles.forEach((file, index) => {
        const fileCard = createBulkFileCard(file, index);
        fileListContainer.appendChild(fileCard);
    });
    
    console.log('✅ Bulk file list populated');
}

function createBulkFileCard(file, index) {
    const card = document.createElement('div');
    card.className = 'bulk-file-card';
    card.dataset.fileIndex = index;
    
    // Store the selected file globally
    selectedFiles[index] = file;
    console.log('📁 File selected:', file.name, 'Size:', formatFileSize(file.size));
    
    // Create card HTML
    card.innerHTML = `
        <div class="file-preview">
            <div class="file-icon">🎬</div>
            <div class="file-status">
                <span class="status-text">Ready</span>
            </div>
        </div>
        <div class="file-details">
            <div class="file-name">${file.name}</div>
            <div class="file-size">${formatFileSize(file.size)}</div>
        </div>
    `;
    
    return card;
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function getFileTypeIcon(mimeType) {
    if (mimeType.startsWith('video/')) return '🎬';
    if (mimeType.startsWith('audio/')) return '🎵';
    if (mimeType.startsWith('image/')) return '🖼️';
    return '📄';
}

function removeFileFromBulk(index) {
    console.log('🗑️ Removing file at index:', index);
    
    // Remove from selectedFiles array
    selectedFiles.splice(index, 1);
    
    // Update file count
    const fileCountElement = document.getElementById('bulkFileCount');
    if (fileCountElement) {
        fileCountElement.textContent = `${selectedFiles.length} files selected`;
    }
    
    // If no files left, go back to step 1
    if (selectedFiles.length === 0) {
        console.log('📁 No files remaining, returning to step 1');
        const step1 = document.getElementById('step1');
        const bulkStep2 = document.getElementById('bulkStep2');
        
        if (step1) step1.style.display = 'block';
        if (bulkStep2) bulkStep2.style.display = 'none';
        
        // Reset back button to "Cancel"
        const backBtn = document.getElementById('backBtn');
        if (backBtn) {
            backBtn.textContent = 'Cancel';
            backBtn.onclick = handleDiscardAndClose;
            console.log('✅ Back button reset to "Cancel" (bulk cleanup)');
        }
        
        // Reset upload type
        currentUploadType = null;
        checkNextButton();
        return;
    }
    
    // Re-populate the file list
    populateBulkFileList();
}

// ============================================
// UI EVENT HANDLERS
// ============================================
function selectUploadType(type) {
    currentUploadType = type;
    console.log('📋 Upload type selected:', type);
    
    // Update visual selection state
    document.querySelectorAll('.upload-option').forEach(option => {
        option.classList.remove('selected');
    });
    
    if (type === 'file') {
        document.getElementById('uploadOption')?.classList.add('selected');
        // Trigger file input when file upload is selected
        const fileInput = document.getElementById('fileInput');
        if (fileInput) {
            console.log('📁 Triggering file input dialog');
            fileInput.click();
        }
    } else if (type === 'import') {
        document.getElementById('importOption')?.classList.add('selected');
    }
    
    checkNextButton();
}

function selectPlatform(platform) {
    currentSource = platform;
    console.log('🎯 Platform selected:', platform);
    
    // Update platform selection UI
    document.querySelectorAll('.source-btn').forEach(btn => {
        btn.classList.remove('selected');
    });
    
    const selectedBtn = document.querySelector(`[data-source="${platform}"]`);
    if (selectedBtn) {
        selectedBtn.classList.add('selected');
        console.log('✅ Platform button selected:', platform);
    }
    
    // Show URL input section
    const urlSection = document.getElementById('urlInputSection');
    if (urlSection) {
        urlSection.style.display = 'block';
        urlSection.classList.remove('hidden');
        console.log('✅ URL section shown');
    }
    
    // Update URL input placeholder
    const urlInput = document.getElementById('urlInput');
    if (urlInput) {
        urlInput.placeholder = getPlaceholderForSource(platform);
        urlInput.focus();
        console.log('✅ URL input focused with placeholder:', getPlaceholderForSource(platform));
    }
    
    checkNextButton();
}

function setUploadButtonState(state) {
    const nextBtn = document.getElementById('nextBtn');
    const nextBtnText = document.getElementById('nextBtnText');
    
    if (!nextBtn || !nextBtnText) return;
    
    switch (state) {
        case 'uploading':
        case 'importing':
            nextBtn.disabled = true;
            nextBtnText.textContent = state === 'uploading' ? 'Uploading...' : 'Importing...';
            break;
        case 'normal':
        default:
            nextBtn.disabled = false;
            // Restore original text based on upload type
            if (currentUploadType === 'import') {
                nextBtnText.textContent = 'Import Media';
            } else {
                nextBtnText.textContent = 'Upload Media';
            }
            break;
    }
    
    console.log('🔘 Upload button state set to:', state);
}

function checkNextButton() {
    const nextBtn = document.getElementById('nextBtn');
    if (!nextBtn) return;
    
    let shouldEnable = false;
    
    console.log('🔘 Checking Next button state:', {
        currentUploadType,
        currentSource,
        selectedFile: !!selectedFile,
        selectedFiles: selectedFiles?.length || 0
    });
    
    if (currentUploadType === 'file') {
        // Enable if file is selected
        shouldEnable = selectedFile || (selectedFiles && selectedFiles.length > 0);
        console.log('🔘 File mode - shouldEnable:', shouldEnable);
    } else if (currentUploadType === 'import') {
        // Enable if platform and URL are valid
        const urlInput = document.getElementById('urlInput');
        if (urlInput && currentSource) {
            const url = urlInput.value.trim();
            shouldEnable = url && validatePlatformUrl(url, currentSource);
            console.log('🔘 Import mode - URL:', url, 'shouldEnable:', shouldEnable);
        } else {
            console.log('🔘 Import mode - missing urlInput or currentSource');
        }
    }
    
    nextBtn.disabled = !shouldEnable;
    console.log('🔘 Next button:', shouldEnable ? 'enabled' : 'disabled');
}

function handleNextButtonClick() {
    const nextBtnText = document.getElementById('nextBtnText');
    const currentText = nextBtnText?.textContent || '';
    
    console.log('🔘 Next button clicked, current text:', currentText);
    
    if (currentText === 'Upload Media' || currentText === 'Import Media' || currentText === 'Upload All Files') {
        // We're in step 2 or bulk mode, start upload/import
        console.log('📤 Starting upload/import process');
        
        if (currentText === 'Upload All Files') {
            // Bulk upload mode
            console.log('📦 Starting bulk upload for', selectedFiles.length, 'files');
            startBulkUpload();
        } else {
            // Single file upload/import
            startUpload();
        }
    } else {
        // We're in step 1, proceed to step 2
        console.log('➡️ Proceeding to next step, type:', currentUploadType);
        proceedToNextStep();
    }
}

async function proceedToNextStep() {
    console.log('➡️ Proceeding to next step, type:', currentUploadType);
    
    if (currentUploadType === 'file') {
        proceedToStep2();
    } else if (currentUploadType === 'import') {
        // Validate and check for duplicates before proceeding
        await validateAndProceedToStep2ForImport();
    }
}

// This function is only for Step 1 -> Step 2 transition
// The actual upload is handled by window.processPlatformImport from ionuploaderpro.js
async function validateAndProceedToStep2ForImport() {
    const urlInput = document.getElementById('urlInput');
    if (!urlInput) return;
    
    const url = urlInput.value.trim();
    if (!validatePlatformUrl(url, currentSource)) {
        showCustomAlert('Error', 'Please enter a valid URL for the selected platform');
        return;
    }
    
    console.log('🔗 Validating platform URL:', currentSource, url);
    
    // Show loading indicator
    const nextBtn = document.getElementById('nextBtn');
    const nextBtnText = document.getElementById('nextBtnText');
    const originalBtnText = nextBtnText?.textContent || 'Next';
    
    if (nextBtnText) nextBtnText.textContent = 'Checking...';
    if (nextBtn) nextBtn.disabled = true;
    
    // Check for duplicate video before proceeding
    console.log('🔍 Checking for duplicate video...');
    try {
        const duplicateCheck = await checkDuplicateVideo(url, currentSource);
        
        // Restore button
        if (nextBtnText) nextBtnText.textContent = originalBtnText;
        if (nextBtn) nextBtn.disabled = false;
        
        if (duplicateCheck.exists) {
            console.log('⚠️ Duplicate video detected:', duplicateCheck);
            showCustomAlert('Duplicate Video', duplicateCheck.message);
            return; // Stop here, don't proceed to Step 2
        }
        console.log('✅ No duplicate found, proceeding...');
    } catch (error) {
        console.warn('⚠️ Duplicate check failed, proceeding anyway:', error);
        // Restore button even on error
        if (nextBtnText) nextBtnText.textContent = originalBtnText;
        if (nextBtn) nextBtn.disabled = false;
        // If duplicate check fails, we'll let them proceed and catch it on the backend
    }
    
    // Store the URL for later use
    window.importedVideoUrl = url;
    
    // Proceed to step 2 to show video preview and form
    proceedToStep2();
}

// Extract YouTube ID from URL (supports regular videos and Shorts)
function extractYouTubeId(url) {
    const match = url.match(/(?:youtube\.com\/(?:watch\?.*v=|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/);
    return match ? match[1] : null;
}

// Extract video ID from URL for any platform
function extractVideoId(url, platform) {
    console.log('🎯 Extracting video ID from:', url, 'platform:', platform);
    
    const patterns = {
        youtube: /(?:youtube\.com\/(?:watch\?.*v=|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/,
        vimeo: /vimeo\.com\/(?:channels\/[^\/]+\/|groups\/[^\/]+\/videos\/|album\/[^\/]+\/video\/|video\/|)(\d+)/,
        muvi: /(?:embed\.muvi\.com\/embed\/)([a-zA-Z0-9]+)/,
        wistia: /(?:wistia\.(?:com|net)\/(?:medias|embed)\/|wi\.st\/)([a-zA-Z0-9]+)/,
        loom: /(?:loom\.com\/share\/)([a-zA-Z0-9]+)/
    };
    
    const pattern = patterns[platform];
    if (!pattern) {
        console.log('🎯 No pattern found for platform:', platform);
        return null;
    }
    
    const match = url.match(pattern);
    const videoId = match ? match[1] : null;
    console.log('🎯 Extracted video ID:', videoId);
    
    return videoId;
}

// Get placeholder text for URL input based on platform
function getPlaceholderForSource(source) {
    const placeholders = {
        'youtube': 'https://www.youtube.com/watch?v=...',
        'vimeo': 'https://vimeo.com/...',
        'muvi': 'https://embed.muvi.com/embed/...',
        'wistia': 'https://company.wistia.com/medias/...',
        'loom': 'https://www.loom.com/share/...'
    };
    return placeholders[source] || 'Enter video URL...';
}

// Generate YouTube thumbnail with fallback
function generateYouTubeThumbnail(videoId) {
    // Try different quality levels in order
    const qualities = ['maxresdefault', 'sddefault', 'hqdefault', 'mqdefault'];
    let currentIndex = 0;
    
    function tryNextQuality() {
        if (currentIndex >= qualities.length) {
            console.error('❌ All YouTube thumbnail qualities failed');
            return;
        }
        
        const quality = qualities[currentIndex];
        const thumbnailUrl = `https://img.youtube.com/vi/${videoId}/${quality}.jpg`;
        
        // Test if the thumbnail exists and convert to blob
        fetch(thumbnailUrl, { mode: 'cors' })
            .then(response => {
                if (!response.ok) throw new Error('Thumbnail not found');
                return response.blob();
            })
            .then(blob => {
                console.log(`✅ YouTube thumbnail found (${quality}):`, thumbnailUrl);
                
                // CRITICAL: Store the blob for upload
                window.capturedThumbnailBlob = blob;
                console.log('✅ YouTube thumbnail blob stored:', blob.size, 'bytes');
                
                // Update preview
                const url = URL.createObjectURL(blob);
                updateThumbnailPreview(url);
            })
            .catch(error => {
                console.log(`⚠️ YouTube thumbnail not found (${quality}), trying next...`);
                currentIndex++;
                tryNextQuality();
            });
    }
    
    tryNextQuality();
}

// Generate Vimeo thumbnail
function generateVimeoThumbnail(videoId) {
    const thumbnailUrl = `https://vumbnail.com/${videoId}.jpg`;
    
    // Fetch and convert to blob for upload
    fetch(thumbnailUrl, { mode: 'cors' })
        .then(response => {
            if (!response.ok) throw new Error('Vimeo thumbnail not found');
            return response.blob();
        })
        .then(blob => {
            console.log('✅ Vimeo thumbnail fetched:', blob.size, 'bytes');
            
            // CRITICAL: Store the blob for upload
            window.capturedThumbnailBlob = blob;
            console.log('✅ Vimeo thumbnail blob stored');
            
            // Update preview
            const url = URL.createObjectURL(blob);
            updateThumbnailPreview(url);
        })
        .catch(error => {
            console.error('❌ Vimeo thumbnail fetch failed:', error);
            // Fallback: just show the URL (won't upload)
            updateThumbnailPreview(thumbnailUrl);
        });
    
    console.log('🖼️ Vimeo thumbnail requested:', thumbnailUrl);
}

// Setup imported video preview
function setupImportedVideoPreview(container, videoUrl) {
    if (!container) return;
    
    console.log('🎬 Setting up imported video preview for:', currentSource, videoUrl);
    
    if (currentSource === 'youtube') {
        const youtubeId = extractYouTubeId(videoUrl);
        if (youtubeId) {
            container.innerHTML = `<iframe src="https://www.youtube.com/embed/${youtubeId}?rel=0&modestbranding=1" 
                style="width: 100%; height: 100%;" frameborder="0" 
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                allowfullscreen></iframe>`;
            
            // Auto-generate YouTube thumbnail
            generateYouTubeThumbnail(youtubeId);
        }
    } else if (currentSource === 'vimeo') {
        const vimeoMatch = videoUrl.match(/vimeo\.com\/(?:channels\/[^\/]+\/|groups\/[^\/]+\/videos\/|album\/[^\/]+\/video\/|video\/|)(\d+)/);
        const vimeoId = vimeoMatch ? vimeoMatch[1] : null;
        
        if (vimeoId) {
            container.innerHTML = `<iframe src="https://player.vimeo.com/video/${vimeoId}?byline=0&portrait=0" 
                style="width: 100%; height: 100%;" frameborder="0" 
                allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>`;
            
            // Auto-generate Vimeo thumbnail (same as YouTube)
            generateVimeoThumbnail(vimeoId);
        }
    } else if (currentSource === 'muvi') {
        const muviMatch = videoUrl.match(/embed\.muvi\.com\/embed\/([a-zA-Z0-9]+)/);
        const muviId = muviMatch ? muviMatch[1] : '';
        
        if (muviId) {
            container.innerHTML = `<iframe src="https://embed.muvi.com/embed/${muviId}" 
                style="width: 100%; height: 100%;" frameborder="0" 
                allow="encrypted-media" allowfullscreen></iframe>`;
        }
    } else if (currentSource === 'wistia') {
        const wistiaMatch = videoUrl.match(/medias\/([a-zA-Z0-9]+)/);
        const wistiaId = wistiaMatch ? wistiaMatch[1] : '';
        
        if (wistiaId) {
            container.innerHTML = `<iframe src="https://fast.wistia.net/embed/iframe/${wistiaId}" 
                style="width: 100%; height: 100%;" frameborder="0" 
                allow="autoplay; fullscreen" allowfullscreen></iframe>`;
        }
    } else if (currentSource === 'loom') {
        const loomMatch = videoUrl.match(/loom\.com\/share\/([a-zA-Z0-9]+)/);
        const loomId = loomMatch ? loomMatch[1] : '';
        
        if (loomId) {
            container.innerHTML = `<iframe src="https://www.loom.com/embed/${loomId}" 
                style="width: 100%; height: 100%;" frameborder="0" 
                allow="autoplay; fullscreen" allowfullscreen></iframe>`;
        }
    }
    
    console.log('✅ Imported video preview setup complete');
}

// Main upload function
function startUpload() {
    console.log('📤 Starting upload process');
    console.log('   currentUploadType:', currentUploadType);
    console.log('   currentSource:', currentSource);
    console.log('   selectedFile:', selectedFile);
    
    if (currentUploadType === 'import') {
        // Import from external source - use Pro functionality
        const urlInput = document.getElementById('urlInput');
        const url = urlInput?.value?.trim();
        
        console.log('📥 Import mode detected');
        console.log('   URL:', url);
        console.log('   Source:', currentSource);
        
        if (url && currentSource) {
            console.log('✅ Valid import data, processing...');
            
            // Collect form metadata for platform import
            const metadata = {
                title: document.getElementById('videoTitle')?.value || '',
                description: document.getElementById('videoDescription')?.value || '',
                category: document.getElementById('videoCategory')?.value || 'General',
                tags: document.getElementById('videoTags')?.value?.split(',').map(t => t.trim()).filter(t => t) || [],
                visibility: document.getElementById('videoVisibility')?.value || 'public',
                badges: document.getElementById('videoBadges')?.value || '',
                thumbnailBlob: window.capturedThumbnailBlob || null
            };
            
            console.log('📦 Metadata prepared:', metadata);
            
            // Call the Pro import function
            if (typeof window.processPlatformImport === 'function') {
                console.log('✅ Calling window.processPlatformImport...');
                window.processPlatformImport(metadata);
            } else {
                console.error('❌ window.processPlatformImport is not a function:', typeof window.processPlatformImport);
                alert('Platform import functionality not available. Please check if Pro features are loaded.');
            }
        } else {
            console.error('❌ Missing import data:', { url, currentSource });
            alert('Please enter a valid URL for platform import');
        }
    } else if (selectedFile) {
        // Upload local file
        console.log('📤 Starting file upload for:', selectedFile.name);
        startFileUpload();
    } else {
        console.error('❌ No file or import URL available');
        console.error('   currentUploadType:', currentUploadType);
        console.error('   selectedFile:', selectedFile);
        alert('Please select a file or import URL first');
    }
}

function startFileUpload() {
    console.log('📤 Starting file upload for:', selectedFile.name);
    
    if (!selectedFile) {
        console.error('❌ No file selected');
        return;
    }
    
    // Handle Google Drive files specially
    if (selectedFile.source === 'googledrive') {
        console.log('📂 Google Drive file detected, uploading via Drive API');
        startGoogleDriveUpload();
        return;
    }
    
    console.log('📊 File size:', selectedFile.size, 'bytes (', formatFileSize(selectedFile.size), ')');
    
    // CRITICAL DEBUG: Check thumbnail status at upload time
    console.log('🔍 THUMBNAIL DEBUG AT UPLOAD:');
    console.log('  - capturedThumbnailBlob exists:', !!window.capturedThumbnailBlob);
    if (window.capturedThumbnailBlob) {
        console.log('  - Thumbnail size:', window.capturedThumbnailBlob.size, 'bytes');
        console.log('  - Thumbnail type:', window.capturedThumbnailBlob.type);
        console.log('  - Thumbnail source:', window.customThumbnailSource);
    } else {
        console.error('  ❌ NO THUMBNAIL BLOB FOUND - This will use auto-generated thumbnail!');
    }
    
    // Get form data
    const title = document.getElementById('videoTitle')?.value || selectedFile.name.replace(/\.[^/.]+$/, "");
    const description = document.getElementById('videoDescription')?.value || '';
    const category = document.getElementById('videoCategory')?.value || 'General';
    const tags = document.getElementById('videoTags')?.value || '';
    const visibility = document.getElementById('videoVisibility')?.value || 'public';
    const badges = document.getElementById('videoBadges')?.value || '';
    
    // Prepare metadata
    const metadata = {
        title: title,
        description: description,
        category: category,
        tags: tags,
        visibility: visibility,
        badges: badges,
        thumbnail: window.capturedThumbnailBlob || null
    };
    
    // Check file size - use R2MultipartUploader for large files (>100MB)
    const LARGE_FILE_THRESHOLD = 100 * 1024 * 1024; // 100MB
    
    if (selectedFile.size > LARGE_FILE_THRESHOLD && typeof window.R2MultipartUploader !== 'undefined') {
        console.log('🚀 Using R2 Multipart Upload for large file (', formatFileSize(selectedFile.size), ')');
        startR2MultipartUpload(selectedFile, metadata);
    } else {
        console.log('📤 Using regular upload for file (', formatFileSize(selectedFile.size), ')');
        startRegularUpload(selectedFile, metadata);
    }
}

/**
 * Start Google Drive Upload
 * Sends file ID and access token to backend, which downloads from Google Drive
 */
function startGoogleDriveUpload() {
    console.log('📂 Starting Google Drive upload...');
    console.log('   File ID:', selectedFile.id);
    console.log('   File name:', selectedFile.name);
    
    // Get form data
    const title = document.getElementById('videoTitle')?.value || selectedFile.name.replace(/\.[^/.]+$/, "");
    const description = document.getElementById('videoDescription')?.value || '';
    const category = document.getElementById('videoCategory')?.value || 'General';
    const tags = document.getElementById('videoTags')?.value || '';
    const visibility = document.getElementById('videoVisibility')?.value || 'public';
    const badges = document.getElementById('videoBadges')?.value || '';
    
    // Get selected channels
    const selectedChannels = window.channelSelector ? window.channelSelector.getSelectedChannels() : [];
    const channelIds = selectedChannels.map(ch => ch.channel_id || ch.id).join(',');
    
    // Prepare form data
    const formData = new FormData();
    formData.append('action', 'import_google_drive'); // Tell backend this is a Google Drive import
    formData.append('google_drive_file_id', selectedFile.id);
    formData.append('google_drive_file_name', selectedFile.name);
    formData.append('google_drive_access_token', accessToken || '');
    formData.append('title', title);
    formData.append('description', description);
    formData.append('category', category);
    formData.append('tags', tags);
    formData.append('visibility', visibility);
    formData.append('badges', badges);
    formData.append('channels', channelIds);
    formData.append('source', 'googledrive');
    
    // Add thumbnail if available
    if (window.capturedThumbnailBlob) {
        formData.append('thumbnail', window.capturedThumbnailBlob, 'thumbnail.jpg');
        console.log('✅ Custom thumbnail added to upload');
    }
    
    // Show progress
    const progressContainer = document.getElementById('uploadProgressContainer');
    const progressBar = document.getElementById('uploadProgressBar');
    const progressText = document.getElementById('uploadProgressText');
    
    if (progressContainer) progressContainer.style.display = 'block';
    if (progressText) progressText.textContent = 'Importing from Google Drive...';
    if (progressBar) progressBar.style.width = '0%';
    
    // Update button state
    setUploadButtonState('uploading');
    
    // Send request
    fetch('./ionuploadvideos.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        // Check for 403 Forbidden (expired token)
        if (response.status === 403) {
            throw new Error('TOKEN_EXPIRED');
        }
        return response.json();
    })
    .then(data => {
        console.log('✅ Google Drive upload response:', data);
        
        if (data.success) {
            // Show success celebration
            if (typeof showCelebrationDialog === 'function') {
                showCelebrationDialog(data.video_id, data.shortlink || '');
            } else {
                alert('Video imported successfully from Google Drive!');
                window.location.reload();
            }
        } else {
            // Check if error is related to authentication
            if (data.error && (data.error.includes('403') || data.error.includes('Forbidden') || data.error.includes('authentication'))) {
                throw new Error('TOKEN_EXPIRED');
            }
            throw new Error(data.error || 'Upload failed');
        }
    })
    .catch(error => {
        console.error('❌ Google Drive upload failed:', error);
        
        // Handle token expiration gracefully
        if (error.message === 'TOKEN_EXPIRED') {
            const reauth = confirm(
                'Your Google Drive session has expired.\n\n' +
                'Click OK to reconnect and try again.'
            );
            
            if (reauth) {
                // Clear progress UI
                setUploadButtonState('normal');
                if (progressContainer) progressContainer.style.display = 'none';
                
                // Trigger re-authentication
                if (typeof window.addNewGoogleDrive === 'function') {
                    window.addNewGoogleDrive();
                } else {
                    alert('Please reconnect your Google Drive from the import menu and try again.');
                }
            } else {
                setUploadButtonState('normal');
                if (progressContainer) progressContainer.style.display = 'none';
            }
        } else {
            // Show regular error message
            alert('Failed to import from Google Drive: ' + error.message);
            setUploadButtonState('normal');
            if (progressContainer) progressContainer.style.display = 'none';
        }
    });
}

/**
 * Start R2 Multipart Upload for large files (>100MB)
 */
function startR2MultipartUpload(file, metadata) {
    console.log('🚀 Initializing R2 Multipart Upload...');
    
    // Show progress bar
    const progressContainer = document.getElementById('uploadProgressContainer');
    const progressBar = document.getElementById('uploadProgressBar');
    const progressText = document.getElementById('uploadProgressText');
    const percentageText = document.getElementById('uploadPercentageText');
    
    if (progressContainer) progressContainer.style.display = 'flex';
    if (progressBar) progressBar.style.width = '0%';
    if (progressText) progressText.textContent = 'Initializing multipart upload...';
    if (percentageText) percentageText.textContent = '0%';
    
    // Create R2 Multipart Uploader instance
    const uploader = new window.R2MultipartUploader({
        partSize: 100 * 1024 * 1024, // 100MB chunks
        maxConcurrentUploads: 3,
        maxRetries: 3,
        endpoint: './ionuploadermultipart.php', // CRITICAL: Multipart upload backend handler
        
        onProgress: (progressData) => {
            const percentage = Math.round(progressData.percentage);
            console.log(`📊 Upload progress: ${percentage}% (${formatFileSize(progressData.loaded)} / ${formatFileSize(progressData.total)})`);
            
            if (progressBar) progressBar.style.width = percentage + '%';
            if (percentageText) percentageText.textContent = percentage + '%';
            
            // Calculate speed and ETA
            const speed = progressData.speed || 0;
            const timeRemaining = progressData.timeRemaining || 0;
            
            let statusText = `Uploading... ${percentage}%`;
            if (speed > 0) {
                statusText += ` • ${formatFileSize(speed)}/s`;
            }
            if (timeRemaining > 0 && timeRemaining < 3600) {
                statusText += ` • ${Math.round(timeRemaining / 60)}m remaining`;
            }
            
            if (progressText) progressText.textContent = statusText;
        },
        
        onPartProgress: (partData) => {
            console.log(`📦 Part ${partData.partNumber}/${partData.totalParts} uploaded (${partData.completedParts} completed)`);
        },
        
        onSuccess: (result) => {
            console.log('✅ R2 Multipart Upload successful!', result);
            hideUploadProgress();
            showUploadSuccess(result);
        },
        
        onError: (error) => {
            console.error('❌ R2 Multipart Upload failed:', error);
            hideUploadProgress();
            showCustomAlert('Upload Error', error.message || 'Upload failed. Please try again.');
        }
    });
    
    // Start the upload
    uploader.upload(file, metadata)
        .then(result => {
            console.log('🎉 Upload completed successfully:', result);
        })
        .catch(error => {
            console.error('❌ Upload error:', error);
        });
}

/**
 * Start regular upload for smaller files (<=100MB)
 */
function startRegularUpload(file, metadata) {
    console.log('📤 Starting regular upload...');
    
    // Show progress bar
    const progressContainer = document.getElementById('uploadProgressContainer');
    const progressBar = document.getElementById('uploadProgressBar');
    const progressText = document.getElementById('uploadProgressText');
    
    if (progressContainer) progressContainer.style.display = 'flex';
    if (progressBar) progressBar.style.width = '0%';
    if (progressText) progressText.textContent = 'Preparing upload... 0%';
    
    // Create form data
    const formData = new FormData();
    formData.append('action', 'upload');
    formData.append('video', file);
    formData.append('title', metadata.title);
    formData.append('description', metadata.description);
    formData.append('category', metadata.category);
    formData.append('tags', metadata.tags);
    formData.append('visibility', metadata.visibility);
    formData.append('badges', metadata.badges);
    
    // Add thumbnail if available
    if (metadata.thumbnail) {
        formData.append('thumbnail', metadata.thumbnail, 'thumbnail.jpg');
        console.log('✅ THUMBNAIL ADDED TO FORMDATA:', metadata.thumbnail.size, 'bytes');
    } else {
        console.error('⚠️⚠️⚠️ NO THUMBNAIL - BACKEND WILL GENERATE DEFAULT! ⚠️⚠️⚠️');
    }
    
    // Upload file using XMLHttpRequest for progress tracking
    const xhr = new XMLHttpRequest();
    
    // Track upload progress
    xhr.upload.addEventListener('progress', (e) => {
        if (e.lengthComputable) {
            const percentComplete = (e.loaded / e.total) * 100;
            console.log(`📊 Upload progress: ${percentComplete.toFixed(1)}%`);
            showUploadProgress(percentComplete, 'Uploading...', e.loaded, e.total);
        }
    });
    
    // Handle completion
    xhr.addEventListener('load', () => {
        console.log('💾 Response status:', xhr.status);
        
        const responseText = xhr.responseText;
        console.log('💾 Raw response text:', responseText);
        console.log('💾 Response length:', responseText.length);
        
        if (!responseText || responseText.trim() === '') {
            hideUploadProgress();
            showCustomAlert('Upload Error', 'Server returned empty response');
            return;
        }
        
        // Check if it looks like JSON
        if (!responseText.trim().startsWith('{') && !responseText.trim().startsWith('[')) {
            console.error('❌ Server response is not JSON:', responseText.substring(0, 500));
            hideUploadProgress();
            showCustomAlert('Upload Error', 'Server returned non-JSON response: ' + responseText.substring(0, 200));
            return;
        }
        
        try {
            const data = JSON.parse(responseText);
        console.log('📦 Server response data:', data);
            
        if (data.success) {
            console.log('✅ Upload successful');
                showUploadProgress(100, 'Complete!', 1, 1);
                setTimeout(() => {
                    hideUploadProgress();
            showUploadSuccess(data);
                }, 500);
        } else {
            // Show detailed error information
            let errorMsg = data.error || 'Upload failed';
            if (data.debug_info) {
                errorMsg += '\n\nDebug Info:\n' + JSON.stringify(data.debug_info, null, 2);
            }
            if (data.trace) {
                console.error('Server trace:', data.trace);
            }
                hideUploadProgress();
                showCustomAlert('Upload Error', errorMsg);
            }
        } catch (parseError) {
            console.error('❌ JSON parse error:', parseError);
            console.error('❌ Failed to parse:', responseText.substring(0, 500));
            hideUploadProgress();
            showCustomAlert('Upload Error', 'Invalid JSON response: ' + parseError.message);
        }
    });
    
    // Handle errors
    xhr.addEventListener('error', () => {
        console.error('❌ Upload failed: Network error');
        hideUploadProgress();
        showCustomAlert('Upload Error', 'Network error occurred during upload');
    });
    
    // Handle abort
    xhr.addEventListener('abort', () => {
        console.log('⚠️ Upload aborted');
        hideUploadProgress();
        showCustomAlert('Upload Cancelled', 'Upload was cancelled');
    });
    
    // Send request
    xhr.open('POST', './ionuploadvideos.php');
    xhr.send(formData);
}

function startImportProcess() {
    console.log('📥 Starting import process for:', currentSource, window.importedVideoUrl);
    
    // Get form data
    const title = document.getElementById('videoTitle')?.value || 'Imported Video';
    const description = document.getElementById('videoDescription')?.value || '';
    const category = document.getElementById('videoCategory')?.value || 'General';
    const tags = document.getElementById('videoTags')?.value || '';
    const visibility = document.getElementById('videoVisibility')?.value || 'public';
    const badges = document.getElementById('videoBadges')?.value || '';
    
    // Show progress
    showProgress(0, 'Importing video...');
    
    // Create form data for import
    const formData = new FormData();
    formData.append('import_url', window.importedVideoUrl);
    formData.append('source', currentSource);
    formData.append('title', title);
    formData.append('description', description);
    formData.append('category', category);
    formData.append('tags', tags);
    formData.append('visibility', visibility);
    formData.append('badges', badges);
    
    // Add thumbnail if available
    if (window.capturedThumbnailBlob) {
        formData.append('thumbnail', window.capturedThumbnailBlob, 'thumbnail.jpg');
    }
    
    // Import video
    fetch('./ionuploadvideos.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('✅ Import successful');
            hideProgress();
            showUploadSuccess(data);
        } else {
            throw new Error(data.error || 'Import failed');
        }
    })
    .catch(error => {
        console.error('❌ Import failed:', error);
        hideProgress();
        alert('Import failed: ' + error.message);
    });
}

function showProgress(percent, message) {
    console.log(`📊 showProgress called: ${percent}% - ${message}`);
    
    // Try multiple possible progress container IDs
    const progressContainers = [
        'progressContainer',
        'uploadProgressContainer', 
        'uploadProgress'
    ];
    
    const progressBars = [
        'uploadProgressBar',
        'progressBar'
    ];
    
    const progressTexts = [
        'uploadProgressText',
        'progressText'
    ];
    
    // Show progress container
    let containerFound = false;
    for (const containerId of progressContainers) {
        const container = document.getElementById(containerId);
        if (container) {
            container.style.display = 'block';
            containerFound = true;
            console.log(`📊 Progress container found: ${containerId}`);
            break;
        }
    }
    
    if (!containerFound) {
        console.log('📊 No progress container found, creating fallback');
        // Create a fallback progress display
        const fallbackProgress = document.createElement('div');
        fallbackProgress.id = 'fallbackProgress';
        fallbackProgress.style.cssText = `
            position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%);
            background: rgba(0,0,0,0.8); color: white; padding: 20px; border-radius: 8px;
            z-index: 10000; text-align: center; min-width: 300px;
        `;
        fallbackProgress.innerHTML = `
            <div style="margin-bottom: 10px;">${message}</div>
            <div style="background: #333; height: 20px; border-radius: 10px; overflow: hidden;">
                <div id="fallbackProgressBar" style="background: #3b82f6; height: 100%; width: ${percent}%; transition: width 0.3s;"></div>
            </div>
            <div style="margin-top: 10px; font-size: 14px;">${Math.round(percent)}%</div>
        `;
        document.body.appendChild(fallbackProgress);
        return;
    }
    
    // Update progress bar
    for (const barId of progressBars) {
        const bar = document.getElementById(barId);
        if (bar) {
            bar.style.width = percent + '%';
            console.log(`📊 Progress bar updated: ${barId} - ${percent}%`);
            break;
        }
    }
    
    // Update progress text
    for (const textId of progressTexts) {
        const text = document.getElementById(textId);
        if (text) {
            text.textContent = message;
            console.log(`📊 Progress text updated: ${textId} - ${message}`);
            break;
        }
    }
}

function hideProgress() {
    console.log('📊 hideProgress called');
    
    // Hide all possible progress containers
    const progressContainers = [
        'progressContainer',
        'uploadProgressContainer', 
        'uploadProgress',
        'fallbackProgress'
    ];
    
    for (const containerId of progressContainers) {
        const container = document.getElementById(containerId);
        if (container) {
            container.style.display = 'none';
            console.log(`📊 Progress container hidden: ${containerId}`);
            
            // Remove fallback progress if it exists
            if (containerId === 'fallbackProgress') {
                container.remove();
            }
        }
    }
}

function showUploadSuccess(data) {
    // Check if we should show celebration dialog
    if (data.celebration && data.shortlink) {
        console.log('🎉 Showing celebration dialog with data:', data);
        
        // Check if showCelebrationDialog function exists
        if (typeof showCelebrationDialog === 'function') {
            showCelebrationDialog(data);
        } else if (typeof window.showCelebrationDialog === 'function') {
            window.showCelebrationDialog(data);
        } else {
            // Function not loaded yet, wait a bit and try again
            console.log('🎉 Celebration dialog function not ready, waiting...');
            setTimeout(() => {
                if (typeof showCelebrationDialog === 'function') {
                    showCelebrationDialog(data);
                } else if (typeof window.showCelebrationDialog === 'function') {
                    window.showCelebrationDialog(data);
                } else {
                    console.error('❌ showCelebrationDialog function not found, using fallback');
                    alert(`🎉 ${data.message || 'Upload successful!'}\n\nShort link: ${window.location.origin}/v/${data.shortlink}`);
                }
            }, 500);
        }
        
        // DON'T auto-reload! Let the user close the celebration dialog manually
        // The closeModal() and viewAllVideos() functions in celebration-dialog.js handle navigation
    } else {
        // Fallback to simple alert
        alert(data.message || 'Upload successful!');
    
        // Only auto-reload for non-celebration success (shouldn't happen normally)
    if (window.parent) {
        window.parent.location.reload();
    } else {
        window.location.href = './creators.php';
        }
    }
}

// Bulk upload functionality
function startBulkUpload() {
    console.log('📦 Starting bulk upload for', selectedFiles.length, 'files');
    
    if (!selectedFiles || selectedFiles.length === 0) {
        console.error('❌ No files selected for bulk upload');
        return;
    }
    
    // Show progress
    const progressContainer = document.getElementById('bulkProgressContainer');
    const progressBar = document.getElementById('bulkProgressBar');
    const progressText = document.getElementById('bulkProgressText');
    
    if (progressContainer) {
        progressContainer.style.display = 'block';
    }
    
    // Upload files one by one
    uploadFilesSequentially(selectedFiles, 0);
}

async function uploadFilesSequentially(files, index) {
    if (index >= files.length) {
        console.log('✅ All files uploaded successfully');
        showBulkUploadComplete();
        return;
    }
    
    const file = files[index];
    const progressText = document.getElementById('bulkProgressText');
    const progressBar = document.getElementById('bulkProgressBar');
    
    if (progressText) {
        progressText.textContent = `Uploading ${file.name} (${index + 1}/${files.length})`;
    }
    
    if (progressBar) {
        const progress = ((index) / files.length) * 100;
        progressBar.style.width = `${progress}%`;
    }
    
    try {
        // Upload single file
        await uploadSingleFile(file);
        
        // Move to next file
        uploadFilesSequentially(files, index + 1);
    } catch (error) {
        console.error('❌ Failed to upload file:', file.name, error);
        
        // Continue with next file or stop based on user preference
        if (confirm(`Failed to upload ${file.name}. Continue with remaining files?`)) {
            uploadFilesSequentially(files, index + 1);
        } else {
            showBulkUploadError(`Upload stopped at ${file.name}`);
        }
    }
}

async function uploadSingleFile(file) {
    // Use the existing upload logic but for a single file
    const formData = new FormData();
    formData.append('action', 'upload');
    formData.append('video', file);
    formData.append('title', file.name.replace(/\.[^/.]+$/, "")); // Remove extension
    formData.append('description', '');
    formData.append('category', 'General');
    formData.append('tags', '');
    formData.append('visibility', 'public');
    
    const response = await fetch('./ionuploadvideos.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    });
    
    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }
    
    const result = await response.json();
    if (!result.success) {
        throw new Error(result.error || 'Upload failed');
    }
    
    return result;
}

function showBulkUploadComplete() {
    const progressText = document.getElementById('bulkProgressText');
    const progressBar = document.getElementById('bulkProgressBar');
    
    if (progressText) {
        progressText.textContent = 'All files uploaded successfully!';
    }
    
    if (progressBar) {
        progressBar.style.width = '100%';
    }
    
    // Show success message and close modal after delay
    setTimeout(() => {
        alert('All files uploaded successfully!');
        if (window.parent) {
            window.parent.location.reload();
        } else {
            window.location.href = './creators.php';
        }
    }, 1000);
}

function showBulkUploadError(message) {
    const progressText = document.getElementById('bulkProgressText');
    if (progressText) {
        progressText.textContent = `Error: ${message}`;
        progressText.style.color = '#ef4444';
    }
}

// ============================================
// INITIALIZATION
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 ION Uploader Core initialized');
    
    // Setup file input handlers
    const fileInput = document.getElementById('fileInput');
    if (fileInput) {
        console.log('📁 Attaching change event to fileInput');
        fileInput.addEventListener('change', handleFileSelect);
    } else {
        console.error('❌ fileInput element not found!');
    }
    
    // Setup upload option selection
    const uploadOption = document.getElementById('uploadOption');
    if (uploadOption) {
        uploadOption.addEventListener('click', () => selectUploadType('file'));
    }
    
    const importOption = document.getElementById('importOption');
    if (importOption) {
        importOption.addEventListener('click', () => selectUploadType('import'));
    }
    
    // Setup next button
    const nextBtn = document.getElementById('nextBtn');
    if (nextBtn) {
        nextBtn.addEventListener('click', handleNextButtonClick);
    }
    
    // Setup character counters
    setupCharacterCounters();
    
    // Setup platform buttons (source buttons)
    const sourceButtons = document.querySelectorAll('.source-btn');
    console.log(`📺 Found ${sourceButtons.length} source buttons`);
    sourceButtons.forEach(btn => {
        console.log(`  - Source button: ${btn.dataset.source}`);
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const source = e.target.closest('.source-btn').dataset.source;
            console.log(`🎯 Source button clicked: ${source}`);
            if (source && source !== 'googledrive') {
                selectPlatform(source);
            } else if (source === 'googledrive') {
                console.log('🔵 Google Drive button clicked (handled separately)');
            }
        });
    });
    
    // Setup URL input validation
    const urlInput = document.getElementById('urlInput');
    if (urlInput) {
        urlInput.addEventListener('input', checkNextButton);
        urlInput.addEventListener('paste', () => {
            setTimeout(checkNextButton, 100); // Delay to allow paste to complete
        });
    }
    
    // Setup edit mode buttons
    const deleteBtn = document.getElementById('deleteBtn');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', handleDeleteVideo);
    }
    
    // NOTE: Back button click handler is set dynamically in proceedToStep2() and goBackToStep1()
    // Don't set a static handler here as it conflicts with the dynamic behavior
    
    const updateBtn = document.getElementById('updateBtn');
    if (updateBtn) {
        updateBtn.addEventListener('click', handleUpdateVideo);
    }
    
    // Setup close button (X) - try multiple selectors
    const closeBtns = document.querySelectorAll('.modal-close, .close-btn, [data-action="close"], .close, .modal-header .close');
    closeBtns.forEach(btn => {
        btn.addEventListener('click', handleDiscardAndClose);
    });
    
    // Setup cancel button
    const cancelBtn = document.getElementById('cancelBtn') || document.querySelector('[data-action="cancel"]');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', handleDiscardAndClose);
    }
    
    // Setup thumbnail functionality
    setupThumbnailHandlers();
    
    // Setup badge functionality
    setupBadgeHandlers();
    
    // Setup drag and drop
    const uploadZone = document.getElementById('uploadZone');
    if (uploadZone) {
        uploadZone.addEventListener('dragover', handleDragOver);
        uploadZone.addEventListener('dragleave', handleDragLeave);
        uploadZone.addEventListener('drop', handleDrop);
        
        // Also add click handler to upload zone as backup
        uploadZone.addEventListener('click', (e) => {
            // Prevent if clicking on the file input itself
            if (e.target.id === 'fileInput') return;
            
            // Prevent event bubbling to avoid conflicts
            e.stopPropagation();
            
            console.log('📁 Upload zone clicked');
            const fileInput = document.getElementById('fileInput');
            if (fileInput) {
                // Use setTimeout to prevent freezing
                setTimeout(() => {
                    fileInput.click();
                }, 10);
            }
        });
    }
    
    console.log('✅ Event handlers attached successfully');
    
    // Initialize edit mode if we're in edit mode
    if (typeof isEditMode !== 'undefined' && isEditMode && typeof editVideoData !== 'undefined' && editVideoData) {
        console.log('🎬 Initializing edit mode with data:', editVideoData);
        setTimeout(() => {
            loadVideoPlayer();
            setupEditModeUI();
        }, 100); // Small delay to ensure DOM is ready
    } else {
        console.log('🎬 Edit mode check:', {
            isEditMode: typeof isEditMode !== 'undefined' ? isEditMode : 'undefined',
            editVideoData: typeof editVideoData !== 'undefined' ? !!editVideoData : 'undefined'
        });
    }
});

// Drag and drop handlers
function handleDragOver(event) {
    event.preventDefault();
    event.stopPropagation();
    event.target.closest('.upload-zone').classList.add('drag-over');
}

function handleDragLeave(event) {
    event.preventDefault();
    event.stopPropagation();
    event.target.closest('.upload-zone').classList.remove('drag-over');
}

function handleDrop(event) {
    event.preventDefault();
    event.stopPropagation();
    event.target.closest('.upload-zone').classList.remove('drag-over');
    
    const files = Array.from(event.dataTransfer.files);
    if (files.length > 0) {
        // Simulate file input change
        handleFileSelect({ target: { files: files } });
    }
}

// ============================================
// THUMBNAIL FUNCTIONALITY
// ============================================
function setupThumbnailHandlers() {
    console.log('🖼️ Setting up thumbnail handlers');
    
    // Upload Custom button
    const uploadThumbnailBtn = document.getElementById('uploadThumbnailBtn');
    const thumbnailInput = document.getElementById('thumbnailInput');
    
    if (uploadThumbnailBtn && thumbnailInput) {
        uploadThumbnailBtn.addEventListener('click', () => {
            console.log('🖼️ Upload thumbnail clicked');
            thumbnailInput.click();
        });
        
        thumbnailInput.addEventListener('change', handleThumbnailUpload);
    }
    
    // From URL button
    const urlThumbnailBtn = document.getElementById('urlThumbnailBtn');
    if (urlThumbnailBtn) {
        urlThumbnailBtn.addEventListener('click', showThumbnailUrlDialog);
    }
    
    // Capture Frame button
    const generateThumbnailBtn = document.getElementById('generateThumbnailBtn');
    if (generateThumbnailBtn) {
        generateThumbnailBtn.addEventListener('click', generateThumbnail);
        console.log('✅ Capture Frame button handler attached');
    }
    
    // Thumbnail container click
    const thumbnailContainer = document.getElementById('thumbnailContainer');
    if (thumbnailContainer) {
        thumbnailContainer.addEventListener('click', () => {
            console.log('🖼️ Thumbnail container clicked');
            if (thumbnailInput) {
                thumbnailInput.click();
            }
        });
    }
}

function handleThumbnailUpload(event) {
    console.log('🖼️ Thumbnail file selected');
    const file = event.target.files[0];
    if (!file) return;
    
    if (!file.type.startsWith('image/')) {
        alert('Please select an image file');
        return;
    }
    
    console.log('🖼️ CUSTOM THUMBNAIL UPLOAD STARTING');
    console.log('  - Previous thumbnail source:', window.customThumbnailSource);
    console.log('  - Previous thumbnail size:', window.capturedThumbnailBlob?.size || 'none');
    
    // CRITICAL FIX: Store the blob AND show preview
    window.capturedThumbnailBlob = file;
    window.customThumbnailSource = 'upload'; // Track that this is user-uploaded
    console.log('✅ Thumbnail blob stored:', file.name, file.size, 'bytes');
    console.log('✅ Custom thumbnail source set to: upload');
    
    const reader = new FileReader();
    reader.onload = function(e) {
        updateThumbnailPreview(e.target.result);
        showThumbnailConfirmation('Custom thumbnail uploaded', 'custom');
        console.log('✅ Custom thumbnail preview updated and badge shown');
    };
    reader.readAsDataURL(file);
}

function showThumbnailUrlDialog() {
    console.log('🖼️ Show thumbnail URL dialog');
    const overlay = document.getElementById('thumbnailUrlOverlay');
    if (overlay) {
        overlay.style.display = 'flex';
        
        // Setup URL dialog handlers if not already done
        const applyBtn = document.getElementById('applyThumbnailBtn');
        const cancelBtn = document.getElementById('cancelThumbnailBtn');
        const closeBtn = document.getElementById('closeThumbnailDialogBtn');
        const urlInput = document.getElementById('thumbnailUrlInput');
        
        // Clear previous input
        if (urlInput) {
            urlInput.value = '';
            urlInput.focus();
        }
        
        // Apply button handler
        if (applyBtn) {
            applyBtn.onclick = (e) => {
                e.preventDefault();
                const url = urlInput?.value?.trim();
                if (url) {
                    console.log('🖼️ Applying thumbnail URL:', url);
                    applyThumbnailFromUrl(url, applyBtn, overlay);
                } else {
                    alert('Please enter a valid URL');
                }
            };
        }
        
        // Cancel button handler
        if (cancelBtn) {
            cancelBtn.onclick = (e) => {
                e.preventDefault();
                console.log('🖼️ Canceling thumbnail URL dialog');
                overlay.style.display = 'none';
            };
        }
        
        // Close button handler
        if (closeBtn) {
            closeBtn.onclick = (e) => {
                e.preventDefault();
                console.log('🖼️ Closing thumbnail URL dialog');
                overlay.style.display = 'none';
            };
        }
        
        // Enter key handler
        if (urlInput) {
            urlInput.onkeypress = (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    applyBtn.click();
                }
            };
        }
    }
}

// Simple function to generate thumbnail from current video frame
function generateThumbnail() {
    console.log('🎬 Generating thumbnail from current video frame');
    
    const playerContainer = document.getElementById('videoPlayerContainer');
    if (!playerContainer) {
        alert('Video player not found. Please load a video first.');
        return;
    }
    
    const video = playerContainer.querySelector('video');
    if (video) {
        try {
            if (video.readyState < 2) {
                alert('Video is still loading. Please wait and try again.');
                return;
            }
            
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            
            canvas.width = video.videoWidth || video.clientWidth;
            canvas.height = video.videoHeight || video.clientHeight;
            
            if (canvas.width === 0 || canvas.height === 0) {
                alert('Unable to determine video dimensions. Please try again when video is fully loaded.');
                return;
            }
            
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            
            let dataURL;
            try {
                dataURL = canvas.toDataURL('image/jpeg', 0.8);
            } catch (e) {
                if (e.name === 'SecurityError') {
                    console.error('❌ CORS error capturing frame:', e);
                    alert('Cannot capture frame from this video source due to security restrictions.\n\nFor YouTube/Vimeo videos, the platform auto-generates a thumbnail. You can also:\n• Upload a custom image\n• Use a URL to an image');
                    return;
    } else {
                    throw e;
                }
            }
            
            const thumbnailPreview = document.getElementById('thumbnailPreview');
            const thumbnailPlaceholder = document.getElementById('thumbnailPlaceholder');
            
            if (thumbnailPreview) {
                thumbnailPreview.src = dataURL;
                thumbnailPreview.style.display = 'block';
            }
            
            if (thumbnailPlaceholder) {
                thumbnailPlaceholder.style.display = 'none';
            }
            
            // Store the blob for upload
            canvas.toBlob((blob) => {
                if (blob) {
                    window.capturedThumbnailBlob = blob;
                    window.customThumbnailSource = 'frame';
                    console.log('✅ Thumbnail captured:', blob.size, 'bytes');
                    
                    // Show confirmation
                    showThumbnailConfirmation('Frame captured', 'custom');
                }
            }, 'image/jpeg', 0.8);
            
            // Show success feedback (removed undefined showUploadStatus call)
            setTimeout(() => {
                const uploadStatus = document.getElementById('uploadStatus');
                if (uploadStatus) uploadStatus.style.display = 'none';
            }, 2000);
            
        } catch (error) {
            console.error('❌ Error generating thumbnail:', error);
            alert('Failed to generate thumbnail: ' + error.message);
        }
    } else {
        const iframe = playerContainer.querySelector('iframe');
        if (iframe) {
            alert('Thumbnail capture is not available for embedded videos (YouTube, Vimeo, etc.).\n\nPlease use "Upload Custom" or "From URL" to set a thumbnail.');
        } else {
            alert('No video found to capture frame from. Please load a video first.');
        }
    }
}

function applyThumbnailFromUrl(url, applyBtn, overlay) {
    // Validate URL
    if (!url || !url.startsWith('http')) {
        alert('Please enter a valid HTTP/HTTPS URL');
        return;
    }
    
    // Disable button and show loading state
    const originalText = applyBtn.textContent;
    applyBtn.disabled = true;
    applyBtn.textContent = 'Loading...';
    
    console.log('🖼️ Fetching thumbnail from URL:', url);
    
    // Use fetch API to get the image (better CORS handling)
    fetch(url, {
        mode: 'cors',
        cache: 'no-cache'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.blob();
    })
    .then(blob => {
        console.log('✅ Thumbnail fetched successfully:', blob.size, 'bytes');
        
        // Validate it's an image
        if (!blob.type.startsWith('image/')) {
            throw new Error('URL does not point to a valid image');
        }
        
        // Create object URL for preview
        const blobUrl = URL.createObjectURL(blob);
        updateThumbnailPreview(blobUrl);
        
        // Store the blob for upload
                window.capturedThumbnailBlob = blob;
        window.customThumbnailUrl = url;
        window.customThumbnailSource = 'url';
        
        // Close dialog
        overlay.style.display = 'none';
                
                // Show success message
        showThumbnailConfirmation('Thumbnail loaded from URL', 'custom');
                const successMsg = document.createElement('div');
                successMsg.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #10b981; color: white; padding: 12px 20px; border-radius: 8px; z-index: 10000; font-size: 14px;';
        successMsg.textContent = '✅ Thumbnail loaded successfully!';
                document.body.appendChild(successMsg);
                setTimeout(() => successMsg.remove(), 3000);
        
        // Re-enable button
        applyBtn.disabled = false;
        applyBtn.textContent = originalText;
    })
    .catch(error => {
        console.error('❌ Failed to fetch thumbnail:', error);
        
        // Try fallback with Image element (for CORS-enabled images)
        console.log('🔄 Trying fallback approach with crossOrigin...');
        
        const testImg = new Image();
        testImg.crossOrigin = 'anonymous';
        
        testImg.onload = function() {
            console.log('✅ Fallback: Image loaded via crossOrigin');
            
            // Convert to blob via canvas
            const canvas = document.createElement('canvas');
            canvas.width = testImg.naturalWidth;
            canvas.height = testImg.naturalHeight;
            const ctx = canvas.getContext('2d');
            
            try {
                ctx.drawImage(testImg, 0, 0);
                
                canvas.toBlob((blob) => {
                    if (blob) {
                        const blobUrl = URL.createObjectURL(blob);
                        updateThumbnailPreview(blobUrl);
                        
                        window.capturedThumbnailBlob = blob;
                        window.customThumbnailUrl = url;
                        window.customThumbnailSource = 'url';
                        
                        overlay.style.display = 'none';
                        showThumbnailConfirmation('Thumbnail loaded from URL', 'custom');
                        
                        const successMsg = document.createElement('div');
                        successMsg.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #10b981; color: white; padding: 12px 20px; border-radius: 8px; z-index: 10000; font-size: 14px;';
                        successMsg.textContent = '✅ Thumbnail loaded successfully!';
                        document.body.appendChild(successMsg);
                        setTimeout(() => successMsg.remove(), 3000);
                    } else {
                        alert('Failed to convert image to blob. Please try uploading the image file directly.');
                    }
                    
                    applyBtn.disabled = false;
                    applyBtn.textContent = originalText;
                }, 'image/jpeg', 0.9);
            } catch (e) {
                console.error('Canvas tainted by CORS:', e);
                alert('CORS Error: Cannot load image from this URL.\n\nPlease:\n1. Download the image and upload it directly\n2. Use an image from the same domain\n3. Use a CORS-enabled CDN');
                
                applyBtn.disabled = false;
                applyBtn.textContent = originalText;
                overlay.style.display = 'none';
            }
        };
        
        testImg.onerror = function() {
            console.error('❌ Fallback also failed');
            alert('Failed to load image from URL.\n\nError: ' + error.message + '\n\nPlease:\n1. Check if the URL is correct\n2. Download the image and upload it directly\n3. Try a different image host');
            
            applyBtn.disabled = false;
            applyBtn.textContent = originalText;
            overlay.style.display = 'none';
        };
        
        testImg.src = url;
    });
}

function showThumbnailConfirmation(message, type = 'auto') {
    // Add a visual badge on the thumbnail preview
    const preview = document.getElementById('thumbnailPreview');
    const container = preview ? preview.parentElement : document.querySelector('.thumbnail-section');
    
    if (container) {
        // Remove any existing badge
        const existingBadge = container.querySelector('.custom-thumbnail-badge');
        if (existingBadge) {
            existingBadge.remove();
        }
        
        // Determine badge colors based on type
        const isCustom = (type === 'custom' || message.includes('Custom') || message.includes('Frame') || message.includes('URL'));
        const bgGradient = isCustom 
            ? 'linear-gradient(135deg, #10b981 0%, #059669 100%)' // Green for custom
            : 'linear-gradient(135deg, #3b82f6 0%, #2563eb 100%)'; // Blue for auto
        
        // Add new badge
        const badge = document.createElement('div');
        badge.className = 'custom-thumbnail-badge';
        badge.dataset.type = isCustom ? 'custom' : 'auto';
        badge.innerHTML = `<span>✓</span> ${message}`;
        badge.style.cssText = `
            position: absolute;
            top: 8px;
            left: 8px;
            background: ${bgGradient};
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
            z-index: 10;
            animation: slideInBadge 0.3s ease;
        `;
        
        // Add animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideInBadge {
                from { opacity: 0; transform: translateY(-10px); }
                to { opacity: 1; transform: translateY(0); }
            }
        `;
        if (!document.getElementById('badge-animation-style')) {
            style.id = 'badge-animation-style';
            document.head.appendChild(style);
        }
        
        // Position container relatively if not already
        if (container.style.position !== 'relative' && container.style.position !== 'absolute') {
            container.style.position = 'relative';
        }
        
        container.appendChild(badge);
        console.log('✅ Thumbnail confirmation badge added:', message, '(type:', type, ')');
        
        // Auto-hide badge after 3 seconds ONLY for custom thumbnails
        if (isCustom) {
            setTimeout(() => {
                if (badge && badge.parentElement) {
                    badge.style.transition = 'opacity 0.3s ease';
                    badge.style.opacity = '0';
                    setTimeout(() => {
                        badge.remove();
                        console.log('✅ Custom thumbnail badge auto-hidden');
                    }, 300);
                }
            }, 3000);
        }
    }
}

function updateThumbnailPreview(src) {
    console.log('🖼️ Updating thumbnail preview:', src);
    
    const preview = document.getElementById('thumbnailPreview');
    const placeholder = document.getElementById('thumbnailPlaceholder');
    
    if (preview) {
        // Add error handling for broken images
        preview.onerror = function() {
            console.error('❌ Thumbnail image failed to load:', src);
            // Show placeholder again if image fails
            if (placeholder) {
                placeholder.style.display = 'block';
                preview.style.display = 'none';
            }
        };
        
        preview.onload = function() {
            console.log('✅ Thumbnail image loaded successfully');
            if (placeholder) {
                placeholder.style.display = 'none';
            }
        };
        
        preview.src = src;
        preview.style.display = 'block';
        console.log('🖼️ Thumbnail preview updated');
        
    } else {
        console.error('❌ thumbnailPreview element not found');
        
        // Try to find thumbnail container and create preview if missing
        const thumbnailSection = document.querySelector('.thumbnail-section');
        if (thumbnailSection) {
            console.log('🔧 Creating missing thumbnail preview element');
            const img = document.createElement('img');
            img.id = 'thumbnailPreview';
            img.src = src;
            img.style.cssText = 'width: 100%; height: 200px; object-fit: cover; border-radius: 8px; display: block;';
            
            // Add error handling for dynamically created image
            img.onerror = function() {
                console.error('❌ Dynamic thumbnail image failed to load:', src);
                img.style.display = 'none';
                if (placeholder) {
                    placeholder.style.display = 'block';
                }
            };
            
            // Insert before placeholder if it exists
            if (placeholder) {
                thumbnailSection.insertBefore(img, placeholder);
                placeholder.style.display = 'none';
            } else {
                thumbnailSection.appendChild(img);
            }
        }
    }
}

function generateAutoThumbnail() {
    console.log('🖼️ ========== GENERATE AUTO THUMBNAIL CALLED ==========');
    console.log('🖼️ Current thumbnail state:');
    console.log('  - capturedThumbnailBlob exists:', !!window.capturedThumbnailBlob);
    console.log('  - customThumbnailSource:', window.customThumbnailSource || 'none');
    if (window.capturedThumbnailBlob) {
        console.log('  - Existing thumbnail size:', window.capturedThumbnailBlob.size, 'bytes');
    }
    
    // CRITICAL: Don't overwrite user's custom thumbnail!
    if (window.capturedThumbnailBlob) {
        console.log('✅ Custom thumbnail already exists, skipping auto-generation');
        console.log('📸 Thumbnail source:', window.customThumbnailSource);
        console.log('📸 Thumbnail size:', window.capturedThumbnailBlob.size, 'bytes');
        console.log('🖼️ ========== AUTO THUMBNAIL SKIPPED ==========');
        return;
    }
    
    if (!selectedFile) {
        console.log('🖼️ No file selected for thumbnail generation');
        console.log('🖼️ ========== AUTO THUMBNAIL ABORTED (no file) ==========');
        return;
    }
    
    if (!selectedFile.type.startsWith('video/')) {
        console.log('🖼️ Selected file is not a video:', selectedFile.type);
        console.log('🖼️ ========== AUTO THUMBNAIL ABORTED (not video) ==========');
        return;
    }
    
    console.log('🖼️ Creating video element for thumbnail generation');
    console.log('🖼️ Video file:', selectedFile.name, selectedFile.size, 'bytes');
    
    // For video files, we can generate a thumbnail
    const video = document.createElement('video');
    video.preload = 'metadata';
    video.muted = true; // Required for autoplay in some browsers
    video.crossOrigin = 'anonymous'; // For canvas drawing
    
    // Add timeout to prevent hanging
    const timeoutId = setTimeout(() => {
        console.error('❌ Thumbnail generation timed out');
        if (video.src && video.src.startsWith('blob:')) {
            URL.revokeObjectURL(video.src);
        }
    }, 10000); // 10 second timeout
    
    video.onloadedmetadata = function() {
        console.log('🎬 Video metadata loaded, duration:', video.duration, 'dimensions:', video.videoWidth, 'x', video.videoHeight);
        
        if (video.duration && video.duration > 0) {
            const seekTime = Math.min(5, video.duration / 4); // Seek to 25% or 5 seconds
            console.log('🎬 Seeking to time:', seekTime);
            video.currentTime = seekTime;
        } else {
            console.error('❌ Invalid video duration:', video.duration);
            clearTimeout(timeoutId);
        }
    };
    
    video.onseeked = function() {
        console.log('🎬 Video seeked successfully, capturing frame');
        clearTimeout(timeoutId);
        
        try {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            
            if (!ctx) {
                console.error('❌ Failed to get canvas context');
                return;
            }
            
            canvas.width = video.videoWidth || 640;
            canvas.height = video.videoHeight || 480;
            
            console.log('🖼️ Canvas dimensions:', canvas.width, 'x', canvas.height);
            
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            
            canvas.toBlob((blob) => {
                if (blob) {
                    const url = URL.createObjectURL(blob);
                    console.log('🖼️ Thumbnail blob created, size:', blob.size, 'bytes');
                    
                    updateThumbnailPreview(url);
                    window.capturedThumbnailBlob = blob;
                    window.customThumbnailSource = 'auto'; // Track that this is auto-generated
                    
                    // Store the URL to prevent it from being revoked
                    window.currentThumbnailUrl = url;
                    
                    console.log('🖼️ Auto thumbnail generated successfully');
                    showThumbnailConfirmation('Auto-generated from video', 'auto');
                } else {
                    console.error('❌ Failed to generate thumbnail blob');
                }
                
                // Clean up video element
                if (video.src && video.src.startsWith('blob:')) {
                    URL.revokeObjectURL(video.src);
                }
            }, 'image/jpeg', 0.8);
            
        } catch (error) {
            console.error('❌ Error during thumbnail capture:', error);
            clearTimeout(timeoutId);
        }
    };
    
    video.onerror = function(e) {
        console.error('❌ Error loading video for thumbnail generation:', e);
        clearTimeout(timeoutId);
        if (video.src && video.src.startsWith('blob:')) {
            URL.revokeObjectURL(video.src);
        }
    };
    
    try {
        video.src = URL.createObjectURL(selectedFile);
        console.log('🎬 Video source set, waiting for metadata...');
    } catch (error) {
        console.error('❌ Error creating video object URL:', error);
        clearTimeout(timeoutId);
    }
}

function autoPopulateTitle(fileName) {
    console.log('📝 Auto-populating title from file name:', fileName);
    
    // Remove file extension and clean up the name
    const nameWithoutExtension = fileName.replace(/\.[^/.]+$/, '');
    
    // Clean up the title: replace underscores/dashes with spaces, capitalize words
    const cleanTitle = nameWithoutExtension
        .replace(/[_-]/g, ' ')           // Replace underscores and dashes with spaces
        .replace(/\s+/g, ' ')            // Replace multiple spaces with single space
        .trim()                          // Remove leading/trailing spaces
        .split(' ')                      // Split into words
        .map(word => {                   // Capitalize each word
            if (word.length === 0) return word;
            return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
        })
        .join(' ');                      // Join back together
    
    // Set the title field value
    const titleInput = document.getElementById('videoTitle');
    if (titleInput && cleanTitle) {
        titleInput.value = cleanTitle;
        
        // Update character counter if it exists
        const titleCount = document.getElementById('titleCount');
        if (titleCount) {
            titleCount.textContent = cleanTitle.length;
            
            // Update counter color based on length
            const charCountSpan = titleCount.parentElement;
            if (cleanTitle.length > 90) {
                charCountSpan.style.color = '#ef4444'; // Red warning
            } else if (cleanTitle.length > 70) {
                charCountSpan.style.color = '#f59e0b'; // Orange warning
            } else {
                charCountSpan.style.color = '#6b7280'; // Default gray
            }
        }
        
        console.log('📝 Title auto-populated:', cleanTitle);
    } else {
        console.log('📝 Title input not found or clean title is empty');
    }
}

function setupCharacterCounters() {
    console.log('📊 Setting up character counters');
    
    // Title counter (0/100)
    const titleInput = document.getElementById('videoTitle');
    const titleCount = document.getElementById('titleCount');
    
    if (titleInput && titleCount) {
        // Update counter function
        const updateTitleCount = () => {
            const currentLength = titleInput.value.length;
            titleCount.textContent = currentLength;
            
            // Add visual feedback
            const charCountSpan = titleCount.parentElement;
            if (currentLength > 90) {
                charCountSpan.style.color = '#ef4444'; // Red warning
            } else if (currentLength > 70) {
                charCountSpan.style.color = '#f59e0b'; // Orange warning
            } else {
                charCountSpan.style.color = '#6b7280'; // Default gray
            }
        };
        
        // Add event listeners
        titleInput.addEventListener('input', updateTitleCount);
        titleInput.addEventListener('keyup', updateTitleCount);
        titleInput.addEventListener('paste', () => setTimeout(updateTitleCount, 10));
        
        // Initialize count
        updateTitleCount();
        console.log('📊 Title character counter initialized');
    }
    
    // Description counter (0/3000)
    const descInput = document.getElementById('videoDescription');
    const descCount = document.getElementById('descCount');
    
    if (descInput && descCount) {
        // Update counter function
        const updateDescCount = () => {
            const currentLength = descInput.value.length;
            descCount.textContent = currentLength;
            
            // Add visual feedback
            const charCountSpan = descCount.parentElement;
            if (currentLength > 2700) { // 90% of 3000
                charCountSpan.style.color = '#ef4444'; // Red warning
            } else if (currentLength > 2250) { // 75% of 3000
                charCountSpan.style.color = '#f59e0b'; // Orange warning
            } else {
                charCountSpan.style.color = '#6b7280'; // Default gray
            }
        };
        
        // Add event listeners
        descInput.addEventListener('input', updateDescCount);
        descInput.addEventListener('keyup', updateDescCount);
        descInput.addEventListener('paste', () => setTimeout(updateDescCount, 10));
        
        // Initialize count
        updateDescCount();
        console.log('📊 Description character counter initialized');
    }
}

// Progress bar functions
function showUploadProgress(percentage, message, uploadedBytes, totalBytes) {
    const progressContainer = document.getElementById('uploadProgressContainer');
    const progressBar = document.getElementById('uploadProgressBar');
    const statusText = document.getElementById('uploadStatusText');
    const percentageText = document.getElementById('uploadPercentageText');
    
    if (progressContainer) {
        progressContainer.style.display = 'flex';
    }
    
    // Update progress bar width and color based on percentage
    if (progressBar) {
        progressBar.style.width = percentage + '%';
        
        // Change progress bar color based on progress
        if (percentage < 30) {
            progressBar.style.background = 'linear-gradient(90deg, #3b82f6, #2563eb)'; // blue
        } else if (percentage < 70) {
            progressBar.style.background = 'linear-gradient(90deg, #f59e0b, #d97706)'; // amber
        } else if (percentage < 100) {
            progressBar.style.background = 'linear-gradient(90deg, #22c55e, #16a34a)'; // green
        } else {
            progressBar.style.background = 'linear-gradient(90deg, #10b981, #059669)'; // emerald
        }
    }

    if (statusText) {
        statusText.textContent = message || 'Uploading...';
        // Color code based on progress
        if (percentage < 30) {
            statusText.style.color = '#3b82f6'; // blue
        } else if (percentage < 70) {
            statusText.style.color = '#f59e0b'; // amber
        } else if (percentage < 100) {
            statusText.style.color = '#22c55e'; // green
        } else {
            statusText.style.color = '#10b981'; // emerald
            statusText.textContent = 'Complete!';
        }
    }

    if (percentageText) {
        percentageText.textContent = Math.round(percentage) + '%';
        // Color code based on progress
        if (percentage < 30) {
            percentageText.style.color = '#2563eb'; // blue-600
        } else if (percentage < 70) {
            percentageText.style.color = '#d97706'; // amber-600
        } else if (percentage < 100) {
            percentageText.style.color = '#16a34a'; // green-600
        } else {
            percentageText.style.color = '#059669'; // emerald-600
        }
    }
    
    console.log(`📊 Upload progress: ${percentage}% - ${message}`);
}

function hideUploadProgress() {
    const progressContainer = document.getElementById('uploadProgressContainer');
    if (progressContainer) {
        progressContainer.style.display = 'none';
    }
}

// Expose critical functions to global scope for ionuploaderpro.js
window.checkNextButton = checkNextButton;
window.proceedToStep2 = proceedToStep2;

console.log('✅ ION Uploader Core loaded successfully');