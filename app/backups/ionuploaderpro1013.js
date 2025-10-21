// ION Video Uploader - Advanced/Pro Functionality
// Advanced classes, bulk selection, Google Drive, thumbnails
// Requires ionuploader.js core functionality
// Consolidates: enhanced-stream-thumbnails.php + enhanced-video-thumbs.php + upload-video-thumbs.php

// ============================================
// ADVANCED CLASSES (Consolidated: R2MultipartUploader + ChunkedUploader + BackgroundUploader)
// ============================================
class R2MultipartUploader {
    constructor(options = {}) {
        this.partSize = options.partSize || 100 * 1024 * 1024; // 100MB default
        this.maxConcurrentUploads = options.maxConcurrentUploads || 10;
        this.maxRetries = options.maxRetries || 3;
        this.retryDelay = options.retryDelay || 1000; // 1 second
        this.endpoint = options.endpoint || './ionuploadvideos.php';
        
        // Callbacks
        this.onProgress = options.onProgress || (() => {});
        this.onSuccess = options.onSuccess || (() => {});
        this.onError = options.onError || (() => {});
        this.onPartProgress = options.onPartProgress || (() => {});
        
        // State
        this.uploadId = null;
        this.r2UploadId = null;
        this.file = null;
        this.parts = [];
        this.completedParts = [];
        this.isUploading = false;
        this.isPaused = false;
        this.abortController = null;
        this.startTime = null;
    }

    async upload(file, metadata = {}) {
        try {
            this.file = file;
            this.isUploading = true;
            this.isPaused = false;
            this.completedParts = [];
            this.abortController = new AbortController();
            this.startTime = Date.now();

            // Check if file should use multipart upload
            const multipartThreshold = 100 * 1024 * 1024; // 100MB
            
            if (file.size <= multipartThreshold) {
                // Use regular upload for smaller files
                return await this.regularUpload(file, metadata);
            }

            console.log(`ðŸš€ Starting R2 multipart upload for ${this.formatFileSize(file.size)} file`);

            // Create parts
            this.parts = this.createParts(file);
            console.log(`ðŸ“¦ Created ${this.parts.length} parts of ${this.formatFileSize(this.partSize)} each`);

            // Initialize multipart upload
            await this.initializeMultipartUpload(file, metadata);
            console.log(`âœ… Initialized multipart upload: ${this.uploadId}`);

            // Get presigned URLs
            const presignedUrls = await this.getPresignedUrls();
            console.log(`ðŸ”— Generated ${presignedUrls.length} presigned URLs`);

            // Upload parts directly to R2
            await this.uploadParts(presignedUrls);
            console.log(`â¬†ï¸ All parts uploaded successfully`);

            // Complete multipart upload
            const result = await this.completeMultipartUpload();
            console.log(`ðŸŽ‰ Upload completed: Video ID ${result.video_id}`);

            this.onSuccess(result);
            return result;

        } catch (error) {
            console.error('âŒ Upload failed:', error);
            this.onError(error);
            
            // Try to abort the multipart upload
            if (this.uploadId) {
                try {
                    await this.abortMultipartUpload();
                } catch (abortError) {
                    console.warn('Failed to abort upload:', abortError);
                }
            }
            
            throw error;
        } finally {
            this.isUploading = false;
        }
    }

    async regularUpload(file, metadata) {
        console.log(`ðŸ“¤ Using regular upload for ${this.formatFileSize(file.size)} file`);
        const formData = new FormData();
        formData.append('upload_type', 'file');
        formData.append('background_mode', 'true');
        formData.append('video', file);
        
        // Add metadata
        Object.keys(metadata).forEach(key => {
            if (key === 'customThumbnailFile') {
                // Handle custom thumbnail file separately
                formData.append('thumbnail', metadata[key]);
                console.log('ðŸ–¼ï¸ Adding custom thumbnail to R2 upload:', metadata[key].name);
            } else if (key === 'thumbnailBlob') {
                // Handle captured thumbnail blob
                formData.append('thumbnail', metadata[key], 'captured-thumbnail.jpg');
                console.log('ðŸ–¼ï¸ Adding captured thumbnail to R2 upload');
            } else {
                formData.append(key, metadata[key]);
            }
        });
        
        console.log('ðŸ“¡ Starting fetch request to:', this.endpoint);
        console.log('ðŸ“¦ FormData contents:', Array.from(formData.entries()).map(([key, value]) => `${key}: ${value instanceof File ? `File(${value.name}, ${value.size} bytes)` : value}`));
        
        // Add timeout to prevent hanging
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 30000); // 30 second timeout
        
        let response;
        try {
            response = await fetch(this.endpoint, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                signal: controller.signal
            });
            
            clearTimeout(timeoutId);
            console.log('âœ… Fetch response received:', response.status, response.statusText);
        } catch (error) {
            clearTimeout(timeoutId);
            if (error.name === 'AbortError') {
                throw new Error('Upload timeout - request took longer than 30 seconds');
            }
            throw error;
        }
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${await response.text()}`);
        }
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'Upload failed');
        }
        
        return result;
    }

    createParts(file) {
        const parts = [];
        const totalParts = Math.ceil(file.size / this.partSize);
        
        for (let i = 0; i < totalParts; i++) {
            const start = i * this.partSize;
            const end = Math.min(start + this.partSize, file.size);
            parts.push({
                number: i + 1,
                start,
                end
            });
        }
        
        return parts;
    }

    async initializeMultipartUpload(file, metadata) {
        const response = await fetch(this.endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'init',
                fileName: file.name,
                fileSize: file.size,
                contentType: file.type || 'application/octet-stream',
                ...metadata
            }),
            credentials: 'same-origin',
            signal: this.abortController.signal
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const result = await response.json();
        
        if (!result.success || !result.uploadId) {
            throw new Error(result.error || 'Failed to initialize upload');
        }

        this.uploadId = result.uploadId;
        this.r2UploadId = result.r2UploadId;
    }

    async getPresignedUrls() {
        const response = await fetch(this.endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'get-presigned-urls',
                uploadId: this.uploadId,
                partNumbers: this.parts.map(p => p.number)
            }),
            credentials: 'same-origin',
            signal: this.abortController.signal
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const result = await response.json();
        
        if (!result.success || !result.urls) {
            throw new Error(result.error || 'Failed to get presigned URLs');
        }

        return result.urls;
    }

    async uploadParts(presignedUrls) {
        const semaphore = new Semaphore(this.maxConcurrentUploads);
        
        const uploadPromises = this.parts.map(async (part, index) => {
            const release = await semaphore.acquire();
            
            try {
                await this.waitForResume();
                
                const url = presignedUrls.find(u => u.partNumber === part.number).url;
                const chunk = this.file.slice(part.start, part.end);
                
                await this.uploadPartDirect(url, chunk, part.number);
                
                return {
                    PartNumber: part.number,
                    ETag: 'placeholder-etag' // Actual ETag from response if available
                };
            } finally {
                release();
            }
        });

        this.completedParts = await Promise.all(uploadPromises);
    }

    async uploadPartDirect(url, chunk, partNumber) {
        let attempt = 0;
        
        while (attempt <= this.maxRetries) {
            try {
                const xhr = new XMLHttpRequest();
                xhr.open('PUT', url, true);
                
                xhr.upload.onprogress = (e) => {
                    if (e.lengthComputable) {
                        const partProgress = (e.loaded / e.total) * 100;
                        this.onPartProgress({
                            partNumber,
                            progress: partProgress
                        });
                        
                        // Calculate overall progress
                        const totalUploaded = this.completedParts.length * this.partSize + (partProgress / 100 * chunk.size);
                        const overallProgress = (totalUploaded / this.file.size) * 100;
                        this.onProgress(overallProgress);
                    }
                };

                const promise = new Promise((resolve, reject) => {
                    xhr.onload = () => {
                        if (xhr.status >= 200 && xhr.status < 300) {
                            resolve();
                        } else {
                            reject(new Error(`HTTP ${xhr.status}`));
                        }
                    };
                    xhr.onerror = reject;
                    xhr.onabort = reject;
                });

                xhr.send(chunk);
                await promise;

                return; // Success

            } catch (error) {
                attempt++;
                if (attempt > this.maxRetries) {
                    throw error;
                }
                await this.delay(this.retryDelay * Math.pow(2, attempt - 1));
            }
        }
    }

    async completeMultipartUpload() {
        const response = await fetch(this.endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'complete',
                uploadId: this.uploadId,
                parts: this.completedParts
            }),
            credentials: 'same-origin',
            signal: this.abortController.signal
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'Failed to complete upload');
        }

        return result;
    }

    async abortMultipartUpload() {
        const formData = new FormData();
        formData.append('action', 'abort');
        formData.append('uploadId', this.uploadId);

        await fetch(this.endpoint, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
    }

    pause() {
        this.isPaused = true;
        console.log('â¸ï¸ Upload paused');
    }

    resume() {
        this.isPaused = false;
        console.log('â–¶ï¸ Upload resumed');
    }

    async cancel() {
        console.log('ðŸ›‘ Cancelling upload');
        
        if (this.abortController) {
            this.abortController.abort();
        }
        
        await this.abortMultipartUpload();
        
        this.isUploading = false;
        this.isPaused = false;
    }

    async getStatus() {
        if (!this.uploadId) return null;

        try {
            const response = await fetch(`${this.endpoint}?action=status&uploadId=${this.uploadId}`, {
                credentials: 'same-origin'
            });

            const result = await response.json();
            return result.success ? result : null;
        } catch (error) {
            console.warn('Failed to get upload status:', error);
            return null;
        }
    }

    delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    waitForResume() {
        return new Promise(resolve => {
            const checkPause = () => {
                if (!this.isPaused) {
                    resolve();
                } else {
                    setTimeout(checkPause, 100);
                }
            };
            checkPause();
        });
    }

    calculateSpeed() {
        if (!this.startTime) return 0;
        
        const elapsed = (Date.now() - this.startTime) / 1000; // seconds
        const uploaded = this.completedParts.length * this.partSize;
        return uploaded / elapsed; // bytes per second
    }

    estimateTimeRemaining() {
        const speed = this.calculateSpeed();
        if (speed === 0) return 0;
        
        const remaining = this.file.size - (this.completedParts.length * this.partSize);
        return remaining / speed; // seconds
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    formatTime(seconds) {
        if (seconds < 60) return `${Math.round(seconds)}s`;
        if (seconds < 3600) return `${Math.round(seconds / 60)}m ${Math.round(seconds % 60)}s`;
        return `${Math.round(seconds / 3600)}h ${Math.round((seconds % 3600) / 60)}m`;
    }
}

class Semaphore {
    constructor(count) {
        this.count = count;
        this.waiting = [];
    }

    async acquire() {
        return new Promise(resolve => {
            if (this.count > 0) {
                this.count--;
                resolve(() => this.release());
            } else {
                this.waiting.push(resolve);
            }
        });
    }

    release() {
        if (this.waiting.length > 0) {
            const resolve = this.waiting.shift();
            resolve(() => this.release());
        } else {
            this.count++;
        }
    }
}

// Note: R2UploadManager is now provided by ionuploader-advanced.js
// Removed duplicate class to avoid conflicts
// VideoProcessor class is now provided by ionuploader-advanced.js to avoid conflicts

// FormValidator class is now provided by ionuploader-advanced.js to avoid conflicts

// Validators class is now provided by ionuploader-advanced.js to avoid conflicts

// ProgressTracker class is now provided by ionuploader-advanced.js to avoid conflicts

// UploadAnalytics class is now provided by ionuploader-advanced.js to avoid conflicts

// PlatformHandlers is now provided by ionuploader-advanced.js to avoid conflicts

// UploadQueue class moved to ionuploader-advanced.js to avoid duplicate declarations
// Access via: window.IONUploadAdvanced.UploadQueue

// RetryHandler class moved to ionuploader-advanced.js to avoid duplicate declarations
// Access via: window.IONUploadAdvanced.RetryHandler

// StorageManager class moved to ionuploader-advanced.js to avoid duplicate declarations
// Access via: window.IONUploadAdvanced.StorageManager

// BULK SELECTION AND ACTIONS FUNCTIONALITY
// ============================================

let selectedFileIndices = new Set();
let isAllSelected = false;

// Initialize bulk selection functionality
function initializeBulkSelection() {
    console.log('ðŸ”§ Initializing bulk selection functionality');
    
    const selectAllBtn = document.getElementById('selectAllToggleBtn');
    const clearSelectionBtn = document.getElementById('clearSelectionBtn');
    
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', toggleSelectAll);
        console.log('âœ… Select All button initialized');
    } else {
        console.error('âŒ selectAllToggleBtn not found');
    }
    
    if (clearSelectionBtn) {
        clearSelectionBtn.addEventListener('click', clearAllSelections);
        console.log('âœ… Clear Selection button initialized');
    } else {
        console.warn('âš ï¸ clearSelectionBtn not found (will be available after selection)');
    }
    
    // Initialize bulk action buttons
    initializeBulkActions();
}

// Toggle select all/deselect all
function toggleSelectAll() {
    const selectAllBtn = document.getElementById('selectAllToggleBtn');
    const selectIcon = selectAllBtn.querySelector('.select-icon');
    const selectText = selectAllBtn.querySelector('.select-text');
    
    if (isAllSelected) {
        // Deselect all
        deselectAllFiles();
        selectIcon.textContent = 'â˜';
        selectText.textContent = 'Select All';
        selectAllBtn.classList.remove('selected');
        isAllSelected = false;
    } else {
        // Select all
        selectAllFiles();
        selectIcon.textContent = 'â˜‘';
        selectText.textContent = 'Deselect All';
        selectAllBtn.classList.add('selected');
        isAllSelected = true;
    }
    
    updateBulkActionsPanel();
}

// Select all files
function selectAllFiles() {
    selectedFileIndices.clear();
    
    // Add checkboxes to enhanced rows if they don't exist
    selectedFiles.forEach((file, index) => {
        selectedFileIndices.add(index);
        addCheckboxToRow(index);
        updateRowSelection(index, true);
    });
    
    console.log(`âœ… Selected all ${selectedFiles.length} files`);
}

// Deselect all files
function deselectAllFiles() {
    selectedFileIndices.forEach(index => {
        updateRowSelection(index, false);
    });
    selectedFileIndices.clear();
    console.log('âŒ Deselected all files');
}

// Add checkbox to enhanced row
function addCheckboxToRow(index) {
    const row = document.querySelector(`[data-file-index="${index}"]`);
    if (!row || row.querySelector('.file-checkbox')) return;
    
    const thumbnailContainer = row.querySelector('.file-thumbnail-container');
    if (thumbnailContainer) {
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.className = 'file-checkbox';
        checkbox.dataset.index = index;
        checkbox.addEventListener('change', (e) => handleFileSelection(index, e.target.checked));
        
        const checkboxContainer = document.createElement('div');
        checkboxContainer.className = 'checkbox-container';
        checkboxContainer.appendChild(checkbox);
        
        thumbnailContainer.appendChild(checkboxContainer);
    }
}

// Handle individual checkbox selection
function handleIndividualSelection(event) {
    const checkbox = event.target;
    const index = parseInt(checkbox.dataset.index);
    const isSelected = checkbox.checked;
    
    handleFileSelection(index, isSelected);
}

// Update row selection state
function updateRowSelection(index, isSelected) {
    const row = document.querySelector(`[data-file-index="${index}"]`);
    const checkbox = row?.querySelector('.video-card-checkbox');
    
    if (checkbox) {
        checkbox.checked = isSelected;
    }
    
    if (row) {
        if (isSelected) {
            row.classList.add('selected');
        } else {
            row.classList.remove('selected');
        }
    }
}

// Handle individual file selection
function handleFileSelection(index, isSelected) {
    if (isSelected) {
        selectedFileIndices.add(index);
    } else {
        selectedFileIndices.delete(index);
        // Update select all button if not all selected
        if (isAllSelected) {
            const selectAllBtn = document.getElementById('selectAllToggleBtn');
            const selectIcon = selectAllBtn.querySelector('.select-icon');
            const selectText = selectAllBtn.querySelector('.select-text');
            
            selectIcon.textContent = 'â˜';
            selectText.textContent = 'Select All';
            selectAllBtn.classList.remove('selected');
            isAllSelected = false;
        }
    }
    
    updateRowSelection(index, isSelected);
    updateBulkActionsPanel();
}

// Update bulk actions panel visibility and counts
function updateBulkActionsPanel() {
    const bulkActionsPanel = document.getElementById('bulkActionsPanel');
    const selectedCount = document.getElementById('selectedCount');
    const selectedCountFooter = document.getElementById('selectedCountFooter');
    
    const count = selectedFileIndices.size;
    
    if (count > 0) {
        bulkActionsPanel.style.display = 'block';
        selectedCount.textContent = count;
        selectedCountFooter.textContent = count;
    } else {
        bulkActionsPanel.style.display = 'none';
    }
}

// Clear all selections
function clearAllSelections() {
    deselectAllFiles();
    
    const selectAllBtn = document.getElementById('selectAllToggleBtn');
    const selectIcon = selectAllBtn.querySelector('.select-icon');
    const selectText = selectAllBtn.querySelector('.select-text');
    
    selectIcon.textContent = 'â˜';
    selectText.textContent = 'Select All';
    selectAllBtn.classList.remove('selected');
    isAllSelected = false;
    
    updateBulkActionsPanel();
}

// Initialize bulk action buttons
function initializeBulkActions() {
    // Make Public button
    const makePublicBtn = document.getElementById('makePublicBtn');
    if (makePublicBtn) {
        makePublicBtn.addEventListener('click', () => applyBulkVisibility('public'));
    }
    
    // Make Private button
    const makePrivateBtn = document.getElementById('makePrivateBtn');
    if (makePrivateBtn) {
        makePrivateBtn.addEventListener('click', () => applyBulkVisibility('private'));
    }
    
    // Category select
    const bulkCategorySelect = document.getElementById('bulkCategorySelect');
    if (bulkCategorySelect) {
        bulkCategorySelect.addEventListener('change', (e) => {
            if (e.target.value) {
                applyBulkCategory(e.target.value);
                e.target.value = ''; // Reset selection
            }
        });
    }
    
    // Visibility select
    const bulkVisibilitySelect = document.getElementById('bulkVisibilitySelect');
    if (bulkVisibilitySelect) {
        bulkVisibilitySelect.addEventListener('change', (e) => {
            if (e.target.value) {
                applyBulkVisibility(e.target.value);
                e.target.value = ''; // Reset selection
            }
        });
    }
    
    // Tags input
    const bulkTagsInput = document.getElementById('bulkTagsInput');
    if (bulkTagsInput) {
        bulkTagsInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && e.target.value.trim()) {
                applyBulkTags(e.target.value.trim());
                e.target.value = ''; // Clear input
            }
        });
    }
}

// Apply bulk visibility change
function applyBulkVisibility(visibility) {
    const count = selectedFileIndices.size;
    if (count === 0) return;
    
    selectedFileIndices.forEach(index => {
        const visibilitySelect = document.querySelector(`[data-field="visibility"][data-index="${index}"]`);
        if (visibilitySelect) {
            visibilitySelect.value = visibility;
        }
    });
    
    const visibilityText = visibility === 'public' ? 'Public' : visibility === 'private' ? 'Private' : 'Unlisted';
    showCustomAlert('Bulk Update', `Set ${count} files to ${visibilityText}`);
}

// Apply bulk category change
function applyBulkCategory(category) {
    const count = selectedFileIndices.size;
    if (count === 0) return;
    
    selectedFileIndices.forEach(index => {
        const categorySelect = document.querySelector(`[data-field="category"][data-index="${index}"]`);
        if (categorySelect) {
            categorySelect.value = category;
        }
    });
    
    showCustomAlert('Bulk Update', `Set ${count} files to category: ${category}`);
}

// Apply bulk tags
function applyBulkTags(tags) {
    const count = selectedFileIndices.size;
    if (count === 0) return;
    
    selectedFileIndices.forEach(index => {
        const titleInput = document.querySelector(`[data-field="title"][data-index="${index}"]`);
        if (titleInput) {
            // Add tags to title if not already present
            const currentTitle = titleInput.value;
            const tagArray = tags.split(',').map(tag => tag.trim());
            
            // Simple implementation: append tags to title
            const newTitle = currentTitle + (currentTitle ? ' ' : '') + tagArray.join(' ');
            titleInput.value = newTitle;
        }
    });
    
    showCustomAlert('Bulk Update', `Added tags to ${count} files: ${tags}`);
}

// Upload individual file from bulk interface
async function uploadSingleFile(index) {
    const file = selectedFiles[index];
    if (!file) return;
    
    console.log(`ðŸš€ Starting upload for file ${index}: ${file.name}`);
    
    // Get form data for this file
    const metadata = getFileMetadata(index);
    
    // Update UI to show uploading state
    updateFileUploadStatus(index, 'uploading', 'Uploading...');
    
    // Disable upload button
    const uploadBtn = document.getElementById(`upload-btn-${index}`);
    if (uploadBtn) {
        uploadBtn.disabled = true;
        uploadBtn.innerHTML = 'â³';
        console.log(`âœ… Upload button found for index ${index}`);
    } else {
        console.error(`âŒ Upload button element not found for index ${index}! ID: upload-btn-${index}`);
        console.log('ðŸ” Available upload buttons:', document.querySelectorAll('[id^="upload-btn-"]'));
        console.log('ðŸ” All elements with upload-btn class:', document.querySelectorAll('.upload-btn'));
    }
    
    // Show progress bar
    const progressContainer = document.getElementById(`progress-${index}`);
    if (progressContainer) {
        progressContainer.style.display = 'block';
    }
    
    try {
        // Create R2 uploader with progress tracking
        const uploader = new R2MultipartUploader({
            onProgress: (progress) => updateFileProgress(index, progress),
            onSuccess: (result) => handleFileUploadSuccess(index, result),
            onError: (error) => handleFileUploadError(index, error)
        });
        
        // Start upload
        await uploader.upload(file, metadata);
        
    } catch (error) {
        console.error(`âŒ Upload failed for ${file.name}:`, error);
        handleFileUploadError(index, error);
    }
}

/**
 * Capture thumbnail from a video file
 * @param {File} file - Video file
 * @param {number} position - Position in video (0.0 to 1.0, e.g., 0.10 = 10%)
 * @returns {Promise<Blob|null>} Thumbnail blob or null if failed
 */
async function captureThumbnailFromFile(file, position = 0.25) {
    return new Promise((resolve) => {
        if (!file.type.startsWith('video/')) {
            console.warn('⚠️ File is not a video:', file.type);
            resolve(null);
            return;
        }

        console.log('🎬 Capturing thumbnail from file:', file.name, 'at position:', position);

        const video = document.createElement('video');
        video.preload = 'metadata';
        video.muted = true;
        video.crossOrigin = 'anonymous'; // CRITICAL for canvas capture
        
        const timeoutId = setTimeout(() => {
            console.error('❌ Thumbnail capture timed out for:', file.name);
            if (video.src) URL.revokeObjectURL(video.src);
            resolve(null);
        }, 15000); // 15 second timeout

        video.onloadedmetadata = function() {
            const seekTime = Math.max(0, Math.min(video.duration * position, video.duration - 0.1));
            console.log('🎬 Video duration:', video.duration, 'seeking to:', seekTime);
            video.currentTime = seekTime;
        };

        video.onseeked = function() {
            clearTimeout(timeoutId);
            
            try {
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                
                if (!ctx) {
                    console.error('❌ Failed to get canvas context');
                    if (video.src) URL.revokeObjectURL(video.src);
                    resolve(null);
                    return;
                }

                canvas.width = video.videoWidth || 640;
                canvas.height = video.videoHeight || 480;
                
                console.log('🖼️ Capturing frame at dimensions:', canvas.width, 'x', canvas.height);
                
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                
                canvas.toBlob((blob) => {
                    if (video.src) URL.revokeObjectURL(video.src);
                    
                    if (blob) {
                        console.log('✅ Thumbnail captured, size:', blob.size, 'bytes');
                        resolve(blob);
                    } else {
                        console.error('❌ Failed to create blob');
                        resolve(null);
                    }
                }, 'image/jpeg', 0.85);
                
            } catch (error) {
                console.error('❌ Error capturing thumbnail:', error);
                if (video.src) URL.revokeObjectURL(video.src);
                resolve(null);
            }
        };

        video.onerror = function(e) {
            console.error('❌ Video load error:', e);
            clearTimeout(timeoutId);
            if (video.src) URL.revokeObjectURL(video.src);
            resolve(null);
        };

        try {
            video.src = URL.createObjectURL(file);
        } catch (error) {
            console.error('❌ Failed to create object URL:', error);
            clearTimeout(timeoutId);
            resolve(null);
        }
    });
}

// Upload individual file from bulk interface using simple upload (not R2 multipart)
window.uploadBulkFile = async function(index) {
    const file = selectedFiles[index];
    if (!file) return;
    
    console.log(`ðŸš€ Starting bulk upload for file ${index}: ${file.name}`);
    
    // Get metadata from form fields
    const titleField = document.querySelector(`input[data-field="title"][data-index="${index}"]`);
    const descField = document.querySelector(`textarea[data-field="description"][data-index="${index}"]`);
    const categoryField = document.querySelector(`select[data-field="category"][data-index="${index}"]`);
    const tagsField = document.querySelector(`input[data-field="tags"][data-index="${index}"]`);
    const visibilityField = document.querySelector(`select[data-field="visibility"][data-index="${index}"]`);
    
    const metadata = {
        title: titleField ? titleField.value : file.name.replace(/\.[^/.]+$/, ''),
        description: descField ? descField.value : '',
        category: categoryField ? categoryField.value : 'General',
        tags: tagsField ? tagsField.value : '',
        visibility: visibilityField ? visibilityField.value : 'public'
    };
    
    // Update UI to show uploading state
    updateFileUploadStatus(index, 'uploading', 'Uploading...');
    
    // Disable upload button
    const uploadBtn = document.getElementById(`upload-btn-${index}`);
    if (uploadBtn) {
        uploadBtn.disabled = true;
        uploadBtn.innerHTML = 'â³';
    }
    
    // Show progress container
    const progressContainer = document.getElementById(`progress-${index}`);
    if (progressContainer) {
        progressContainer.style.display = 'block';
    }
    
    try {
        // Optionally capture a thumbnail frame from the video file before uploading
        let capturedThumbBlob = null;
        if (getMediaType(file) === 'video') {
            try {
                console.log('ðŸŽ¬ Capturing thumbnail frame for', file.name);
                capturedThumbBlob = await captureThumbnailFromFile(file, 0.10); // 10% position
                if (capturedThumbBlob) {
                    console.log('ðŸ–¼ï¸ Captured thumbnail size:', capturedThumbBlob.size, 'bytes');
                    // Update preview in UI immediately
                    const thumbUrl = URL.createObjectURL(capturedThumbBlob);
                    const thumbContainer = document.getElementById(`thumbnail-${index}`);
                    if (thumbContainer) {
                        thumbContainer.innerHTML = `<img src="${thumbUrl}" alt="thumb" style="width:100px;height:60px;object-fit:cover;border-radius:8px;"/>`;
                    }
                    // Revoke later
                    setTimeout(() => URL.revokeObjectURL(thumbUrl), 30000);
                }
            } catch (e) {
                console.warn('âš ï¸ Failed to capture thumbnail for', file.name, e);
            }
        }

        // Use EXACT same FormData as bulk upload (simple upload method)
        const formData = new FormData();
        formData.append('video', file);
        formData.append('title', metadata.title || 'Untitled');
        formData.append('description', metadata.description || '');
        formData.append('category', metadata.category || 'General');
        formData.append('tags', metadata.tags || '');
        formData.append('visibility', metadata.visibility || 'public');
        formData.append('upload_type', 'file');
        // Attach captured thumbnail if available (server supports $_FILES['thumbnail'])
        if (capturedThumbBlob) {
            formData.append('thumbnail', capturedThumbBlob, 'captured-thumbnail.jpg');
        }
        
        console.log('ðŸ“¡ Individual bulk upload FormData for', file.name, ':', {
            title: metadata.title,
            category: metadata.category,
            visibility: metadata.visibility,
            upload_type: 'file',
            has_thumbnail: !!capturedThumbBlob
        });
        
        // Update progress
        updateFileProgress(index, 10);
        
        const response = await fetch('./ionuploadvideos.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        
        updateFileProgress(index, 70);
        
        if (!response.ok) {
            const errorText = await response.text();
            throw new Error(`HTTP ${response.status}: ${errorText}`);
        }
        
        const result = await response.json();
        console.log('ðŸ“ Individual bulk upload result for', file.name, ':', result);
        
        if (result.success) {
            updateFileProgress(index, 100);
            updateFileUploadStatus(index, 'completed', 'Upload complete!');
            
            // Update upload button
            if (uploadBtn) {
                uploadBtn.innerHTML = 'âœ…';
                uploadBtn.style.background = '#10b981';
            }
            
            console.log('âœ… Individual bulk upload successful for:', file.name, 'Shortlink:', result.shortlink);
        } else {
            throw new Error(result.error || 'Upload failed');
        }
        
    } catch (error) {
        console.error(`âŒ Individual bulk upload failed for ${file.name}:`, error);
        updateFileUploadStatus(index, 'error', 'Upload failed');
        
        // Reset upload button
        if (uploadBtn) {
            uploadBtn.disabled = false;
            uploadBtn.innerHTML = 'â¬†ï¸';
            uploadBtn.style.background = '';
        }
        
        showCustomAlert('Upload Failed', `Failed to upload ${file.name}: ${error.message}`);
    }
};

// Get metadata for a specific file
function getFileMetadata(index) {
    const titleInput = document.querySelector(`[data-field="title"][data-index="${index}"]`);
    const descriptionInput = document.querySelector(`[data-field="description"][data-index="${index}"]`);
    const categorySelect = document.querySelector(`[data-field="category"][data-index="${index}"]`);
    const visibilitySelect = document.querySelector(`[data-field="visibility"][data-index="${index}"]`);
    const tagsInput = document.querySelector(`[data-field="tags"][data-index="${index}"]`);
    
    return {
        title: titleInput ? titleInput.value.trim() : '',
        description: descriptionInput ? descriptionInput.value.trim() : '',
        category: categorySelect ? categorySelect.value : 'Business',
        visibility: visibilitySelect ? visibilitySelect.value : 'public',
        tags: tagsInput ? processTags(tagsInput.value) : ''
        // REMOVED: upload_type and background_mode to match single upload behavior
    };
}

// Update file upload status
function updateFileUploadStatus(index, status, message) {
    const statusElement = document.getElementById(`status-${index}`);
    if (statusElement) {
        statusElement.textContent = message;
        statusElement.className = `upload-status ${status}`;
    }
}

// Update file upload progress
function updateFileProgress(index, progress) {
    const progressFill = document.querySelector(`#progress-${index} .progress-fill-small`);
    const progressText = document.querySelector(`#progress-${index} .progress-text-small`);
    
    if (progressFill && progressText) {
        progressFill.style.width = `${progress}%`;
        progressText.textContent = `${Math.round(progress)}%`;
    }
}

// Handle successful file upload
function handleFileUploadSuccess(index, result) {
    console.log(`âœ… Upload completed for file ${index}:`, result);
    
    updateFileUploadStatus(index, 'completed', 'Upload complete!');
    
    // Update upload button
    const uploadBtn = document.getElementById(`upload-btn-${index}`);
    if (uploadBtn) {
        uploadBtn.innerHTML = 'âœ…';
        uploadBtn.disabled = true;
        uploadBtn.style.background = 'var(--success-color)';
    }
    
    // Hide progress bar after a delay
    setTimeout(() => {
        const progressContainer = document.getElementById(`progress-${index}`);
        if (progressContainer) {
            progressContainer.style.display = 'none';
        }
    }, 2000);
    
    // Show success message with reload option
    showCustomAlert('Upload Complete', `${selectedFiles[index].name} has been uploaded successfully!`, [
        {
            text: 'View Videos',
            className: 'btn btn-primary',
            onClick: () => {
                hideModal();
                // Reload the parent page to show new videos
                if (window.parent && window.parent !== window) {
                    window.parent.location.reload();
                } else {
                    window.location.reload();
                }
            }
        }
    ]);
}

// Handle file upload error
function handleFileUploadError(index, error) {
    console.error(`âŒ Upload error for file ${index}:`, error);
    
    updateFileUploadStatus(index, 'error', 'Upload failed');
    
    // Reset upload button
    const uploadBtn = document.getElementById(`upload-btn-${index}`);
    if (uploadBtn) {
        uploadBtn.disabled = false;
        uploadBtn.innerHTML = 'â¬†ï¸';
        uploadBtn.style.background = 'var(--primary-color)';
    }
    
    // Hide progress bar
    const progressContainer = document.getElementById(`progress-${index}`);
    if (progressContainer) {
        progressContainer.style.display = 'none';
    }
    
    // Show error message
    showCustomAlert('Upload Failed', `Failed to upload ${selectedFiles[index].name}: ${error.message || 'Unknown error'}`);
}

function createBulkFileRow(file, index) {
    const row = document.createElement('div');
    row.className = 'bulk-file-row';
    row.dataset.fileIndex = index;
    
    const mediaType = getMediaType(file);
    const icon = getMediaIcon(file);
    const typeDisplay = getMediaTypeDisplay(file);
    const fileSize = formatFileSize(file.size);
    
    row.innerHTML = `
        <input type="checkbox" class="file-checkbox" checked data-index="${index}">
        <div class="file-info">
            <span class="file-icon">${icon}</span>
            <div class="file-details">
                <div class="file-name" title="${file.name}">${file.name}</div>
                <div class="file-meta">${typeDisplay} â€¢ ${fileSize}</div>
            </div>
        </div>
        <input type="text" class="field-input" placeholder="Enter title" data-field="title" data-index="${index}" value="${getDefaultTitle(file)}">
        <select class="field-select" data-field="category" data-index="${index}">
            <option value="Business">Business</option>
            <option value="Technology">Technology</option>
            <option value="Marketing">Marketing</option>
            <option value="Education">Education</option>
            <option value="Entertainment">Entertainment</option>
        </select>
        <select class="field-select" data-field="visibility" data-index="${index}">
            <option value="public">Public</option>
            <option value="unlisted">Unlisted</option>
            <option value="private">Private</option>
        </select>
        <div class="file-status status-ready" data-index="${index}">Ready</div>
        <div class="file-actions">
            <button type="button" class="action-btn edit" title="Edit" onclick="editFileRow(${index})">
                âœï¸
            </button>
        </div>
    `;
    
    return row;
}

function getDefaultTitle(file) {
    // Remove file extension and clean up the name
    const nameWithoutExt = file.name.replace(/\.[^/.]+$/, '');
    // Replace underscores and hyphens with spaces, capitalize words
    return nameWithoutExt
        .replace(/[_-]/g, ' ')
        .replace(/\b\w/g, l => l.toUpperCase());
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function editFileRow(index) {
    console.log('Editing file row:', index);
    
    const row = document.querySelector(`[data-file-index="${index}"]`);
    if (!row) return;
    
    // Toggle edit mode
    if (row.classList.contains('editing')) {
        saveFileRow(index);
    } else {
        enterEditMode(index);
    }
}

function enterEditMode(index) {
    const row = document.querySelector(`[data-file-index="${index}"]`);
    if (!row) return;
    
    row.classList.add('editing');
    
    // Change edit button to save button
    const editBtn = row.querySelector('.action-btn.edit');
    if (editBtn) {
        editBtn.innerHTML = 'ðŸ’¾';
        editBtn.title = 'Save';
        editBtn.classList.remove('edit');
        editBtn.classList.add('save');
    }
    
    // Add cancel button
    const actionsContainer = row.querySelector('.file-actions');
    if (actionsContainer && !actionsContainer.querySelector('.cancel')) {
        const cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className = 'action-btn cancel';
        cancelBtn.title = 'Cancel';
        cancelBtn.innerHTML = 'âŒ';
        cancelBtn.onclick = () => cancelEditMode(index);
        actionsContainer.appendChild(cancelBtn);
    }
    
    // Store original values for cancel functionality
    const titleInput = row.querySelector('[data-field="title"]');
    const categorySelect = row.querySelector('[data-field="category"]');
    const visibilitySelect = row.querySelector('[data-field="visibility"]');
    
    if (titleInput) titleInput.dataset.originalValue = titleInput.value;
    if (categorySelect) categorySelect.dataset.originalValue = categorySelect.value;
    if (visibilitySelect) visibilitySelect.dataset.originalValue = visibilitySelect.value;
    
    // Focus on title input
    if (titleInput) {
        titleInput.focus();
        titleInput.select();
    }
}

function saveFileRow(index) {
    const row = document.querySelector(`[data-file-index="${index}"]`);
    if (!row) return;
    
    // Validate required fields
    const titleInput = row.querySelector('[data-field="title"]');
    if (titleInput && !titleInput.value.trim()) {
        showCustomAlert('Validation Error', 'Title is required.');
        titleInput.focus();
        return;
    }
    
    // Exit edit mode
    exitEditMode(index);
    
    console.log('âœ… Saved changes for file row:', index);
    showCustomAlert('Saved', 'Changes saved successfully!');
}

function cancelEditMode(index) {
    const row = document.querySelector(`[data-file-index="${index}"]`);
    if (!row) return;
    
    // Restore original values
    const titleInput = row.querySelector('[data-field="title"]');
    const categorySelect = row.querySelector('[data-field="category"]');
    const visibilitySelect = row.querySelector('[data-field="visibility"]');
    
    if (titleInput && titleInput.dataset.originalValue !== undefined) {
        titleInput.value = titleInput.dataset.originalValue;
        delete titleInput.dataset.originalValue;
    }
    
    if (categorySelect && categorySelect.dataset.originalValue !== undefined) {
        categorySelect.value = categorySelect.dataset.originalValue;
        delete categorySelect.dataset.originalValue;
    }
    
    if (visibilitySelect && visibilitySelect.dataset.originalValue !== undefined) {
        visibilitySelect.value = visibilitySelect.dataset.originalValue;
        delete visibilitySelect.dataset.originalValue;
    }
    
    // Exit edit mode
    exitEditMode(index);
    
    console.log('âŒ Cancelled editing for file row:', index);
}

function exitEditMode(index) {
    const row = document.querySelector(`[data-file-index="${index}"]`);
    if (!row) return;
    
    row.classList.remove('editing');
    
    // Change save button back to edit button
    const saveBtn = row.querySelector('.action-btn.save');
    if (saveBtn) {
        saveBtn.innerHTML = 'âœï¸';
        saveBtn.title = 'Edit';
        saveBtn.classList.remove('save');
        saveBtn.classList.add('edit');
    }
    
    // Remove cancel button
    const cancelBtn = row.querySelector('.action-btn.cancel');
    if (cancelBtn) {
        cancelBtn.remove();
    }
}

// Bulk selection handlers
function selectAllFiles() {
    const checkboxes = document.querySelectorAll('.file-checkbox');
    checkboxes.forEach(cb => cb.checked = true);
}

function deselectAllFiles() {
    const checkboxes = document.querySelectorAll('.file-checkbox');
    checkboxes.forEach(cb => cb.checked = false);
}

function proceedWithBulkUpload() {
    console.log('ðŸš€ Starting bulk upload process');
    
    // Debug: Check what checkboxes exist
    const allCheckboxes = document.querySelectorAll('.file-checkbox');
    const selectedCheckboxes = document.querySelectorAll('.file-checkbox:checked');
    console.log('ðŸ” Total checkboxes found:', allCheckboxes.length);
    console.log('ðŸ” Selected checkboxes found:', selectedCheckboxes.length);
    console.log('ðŸ” selectedFileIndices Set:', selectedFileIndices);
    console.log('ðŸ” selectedFiles array length:', selectedFiles ? selectedFiles.length : 'undefined');
    
    // Debug: Log each checkbox
    allCheckboxes.forEach((cb, i) => {
        console.log(`ðŸ” Checkbox ${i}:`, cb.checked, 'data-index:', cb.dataset.index, 'element:', cb);
    });
    
    const filesToUpload = [];
    
    selectedCheckboxes.forEach(checkbox => {
        const index = parseInt(checkbox.dataset.index);
        const file = selectedFiles[index];
        const row = checkbox.closest('.bulk-file-row');
        
        console.log('ðŸ” Processing checkbox index:', index, 'file:', file?.name, 'selectedFiles.length:', selectedFiles.length);
        
        if (!file) {
            console.error('âŒ No file found at index:', index, 'in selectedFiles array');
            return;
        }
        
        if (!row) {
            console.error('âŒ No row found for checkbox at index:', index);
            return;
        }
        
        // Get metadata from the row - with fallback values
        const titleField = row.querySelector('[data-field="title"]');
        const categoryField = row.querySelector('[data-field="category"]');
        const visibilityField = row.querySelector('[data-field="visibility"]');
        
        const title = titleField ? titleField.value : '';
        const category = categoryField ? categoryField.value : 'General';
        const visibility = visibilityField ? visibilityField.value : 'public';
        
        console.log('ðŸ” File metadata:', {title, category, visibility, fileName: file.name});
        
        filesToUpload.push({
            file: file,
            metadata: {
                title: title || getDefaultTitle(file),
                category: category,
                visibility: visibility,
                description: '', // Could add description field later
                tags: '' // Could add tags field later
            },
            index: index
        });
    });
    
    console.log('ðŸ” Final filesToUpload array:', filesToUpload.length, 'files');
    filesToUpload.forEach((item, i) => {
        console.log(`ðŸ” File ${i}:`, item.file.name, 'metadata:', item.metadata);
    });
    
    if (filesToUpload.length === 0) {
        console.error('âŒ No files to upload! This means files were not found in selectedFiles array or form fields were missing.');
        showCustomAlert('No Files Selected', 'Please select at least one file to upload.');
        return;
    }
    
    // Start the bulk upload process
    startBulkUpload(filesToUpload);
}

function startBulkUpload(filesToUpload) {
    // Use uploadQueue if no parameter passed (called from button handler)
    if (!filesToUpload) {
        filesToUpload = uploadQueue;
        console.log('ðŸ"¦ Using uploadQueue:', uploadQueue.length, 'files');
    }
    
    console.log('ðŸ"¦ Starting upload for', filesToUpload.length, 'files');
    console.log('ðŸ“¦ DEBUG: Files to upload:', filesToUpload.map(f => f.file.name));
    console.log('ðŸ"¦ DEBUG: First file metadata:', filesToUpload[0]?.metadata);
    
    if (!filesToUpload || filesToUpload.length === 0) {
        console.error('âŒ No files in filesToUpload array! This means the bulk upload process failed to collect files.');
        onBulkUploadComplete();
        return;
    }
    
    // Show progress
    const bulkProgress = document.getElementById('bulkProgress');
    if (bulkProgress) {
        bulkProgress.style.display = 'block';
    }
    
    // Update button state
    const nextBtn = document.getElementById('nextBtn');
    if (nextBtn) {
        nextBtn.disabled = true;
        nextBtn.textContent = 'Uploading...';
    }
    
    // Upload files sequentially
    console.log('ðŸ“¦ Calling uploadFilesSequentially with', filesToUpload.length, 'files');
    console.log('ðŸ“¦ DEBUG: About to call uploadFilesSequentially function');
    
    try {
        uploadFilesSequentially(filesToUpload, 0);
        console.log('ðŸ“¦ DEBUG: uploadFilesSequentially called successfully');
    } catch (error) {
        console.error('âŒ Error calling uploadFilesSequentially:', error);
        onBulkUploadComplete();
    }
}

function uploadFilesSequentially(filesToUpload, currentIndex) {
    console.log('ðŸ“¦ DEBUG: uploadFilesSequentially function START - Index:', currentIndex, 'Total:', filesToUpload.length);
    console.log('ðŸ“¦ uploadFilesSequentially called with currentIndex:', currentIndex, 'filesToUpload.length:', filesToUpload.length);
    console.log('ðŸ“¦ DEBUG: filesToUpload array:', filesToUpload);
    
    if (currentIndex >= filesToUpload.length) {
        // All uploads complete
        console.log('ðŸ“¦ âœ… All uploads complete, calling onBulkUploadComplete');
        onBulkUploadComplete();
        return;
    }
    
    if (!filesToUpload[currentIndex]) {
        console.error('âŒ No file data at index:', currentIndex);
        console.log('ðŸ“¦ Skipping to next file...');
        uploadFilesSequentially(filesToUpload, currentIndex + 1);
        return;
    }
    
    const fileData = filesToUpload[currentIndex];
    console.log('ðŸ“¦ Processing file at index', currentIndex, ':', fileData.file.name);
    const progress = ((currentIndex) / filesToUpload.length) * 100;
    
    // Update overall progress
    updateBulkProgress(progress, `Uploading ${currentIndex + 1} of ${filesToUpload.length}`);
    
    // Update file row status
    updateFileRowStatus(fileData.index, 'uploading', 'Uploading...');
    
    // Upload single file (this would need to be implemented based on your upload API)
    console.log('ðŸ"¦ Calling uploadSingleFileFromBulk for:', fileData.file.name);
    
    // Set current index for progress tracking
    window.currentBulkIndex = fileData.index;
    
    uploadSingleFileFromBulk(fileData)
        .then(result => {
            console.log('âœ… Upload completed successfully for:', fileData.file.name, 'Result:', result);
            console.log('ðŸ“¦ SUCCESS HANDLER: About to update file row status and continue to next file');
            updateFileRowStatus(fileData.index, 'completed', 'Completed');
            
            console.log('ðŸ“¦ SUCCESS: Moving to next file. Current index:', currentIndex, 'Next index:', currentIndex + 1);
            console.log('ðŸ“¦ SUCCESS: filesToUpload.length:', filesToUpload.length, 'Will continue?', (currentIndex + 1) < filesToUpload.length);
            
            // Add small delay before next upload to prevent server overload
            console.log('ðŸ“¦ SUCCESS: Setting timeout for next file...');
            setTimeout(() => {
                console.log('ðŸ“¦ TIMEOUT EXECUTED: Calling uploadFilesSequentially for next file, index:', currentIndex + 1);
                try {
                    uploadFilesSequentially(filesToUpload, currentIndex + 1);
                    console.log('ðŸ“¦ TIMEOUT: uploadFilesSequentially call completed');
                } catch (timeoutError) {
                    console.error('âŒ TIMEOUT ERROR: Failed to call uploadFilesSequentially:', timeoutError);
                }
            }, 1000); // 1 second delay
        })
        .catch(error => {
            console.error('âŒ Upload failed for file:', fileData.file.name, 'Error:', error);
            console.error('âŒ Error details:', error.message, error.stack);
            console.log('ðŸ“¦ CATCH HANDLER: About to update file row status and continue to next file');
            updateFileRowStatus(fileData.index, 'error', 'Failed');
            
            console.log('ðŸ“¦ ERROR: Moving to next file despite error. Current index:', currentIndex, 'Next index:', currentIndex + 1);
            console.log('ðŸ“¦ ERROR: filesToUpload.length:', filesToUpload.length, 'Will continue?', (currentIndex + 1) < filesToUpload.length);
            
            // Continue with next file even if this one failed, with delay
            console.log('ðŸ“¦ ERROR: Setting timeout for next file...');
            setTimeout(() => {
                console.log('ðŸ“¦ ERROR TIMEOUT EXECUTED: Calling uploadFilesSequentially for next file after error, index:', currentIndex + 1);
                try {
                    uploadFilesSequentially(filesToUpload, currentIndex + 1);
                    console.log('ðŸ“¦ ERROR TIMEOUT: uploadFilesSequentially call completed');
                } catch (timeoutError) {
                    console.error('âŒ ERROR TIMEOUT ERROR: Failed to call uploadFilesSequentially:', timeoutError);
                }
            }, 1000); // 1 second delay
        });
}

function updateBulkProgress(percentage, text) {
    const progressBar = document.getElementById('bulkProgressBar');
    const progressText = document.getElementById('bulkProgressText');
    
    if (progressBar) {
        progressBar.style.width = `${percentage}%`;
    }
    
    if (progressText) {
        progressText.textContent = text;
    }
}

function updateFileRowStatus(index, status, text) {
    const statusElement = document.querySelector(`[data-index="${index}"].file-status`);
    if (statusElement) {
        statusElement.className = `file-status status-${status}`;
        statusElement.textContent = text;
    }
    
    const row = document.querySelector(`[data-file-index="${index}"]`);
    if (row) {
        row.className = `bulk-file-row ${status}`;
    }
}

function onBulkUploadComplete() {
    console.log('âœ… Bulk upload completed');
    
    updateBulkProgress(100, 'All uploads completed!');
    
    const nextBtn = document.getElementById('nextBtn');
    if (nextBtn) {
        nextBtn.textContent = 'Close';
        nextBtn.disabled = false;
        nextBtn.onclick = closeModal;
    }
    
    showCustomAlert('Upload Complete', 'All selected files have been uploaded successfully!', [
        {
            text: 'View Console & Close',
            className: 'btn btn-primary',
            onClick: () => {
                console.log('ðŸ” TESTING MODE: Check console logs above before closing');
                console.log('ðŸ” Click "View Videos" to reload and see uploaded videos');
                // hideModal();
                // Temporarily disabled auto-reload for testing
                // if (window.parent && window.parent !== window) {
                //     window.parent.location.reload();
                // } else {
                //     window.location.reload();
                // }
            }
        },
        {
            text: 'View Videos (Reload)',
            className: 'btn btn-secondary',
            onClick: () => {
                hideModal();
                // Reload the parent page to show new videos
                if (window.parent && window.parent !== window) {
                    window.parent.location.reload();
                } else {
                    window.location.reload();
                }
            }
        }
    ]);
}

// Upload single file for bulk upload - uses the same working logic as single upload
async function uploadSingleFileFromBulk(fileData) {
    console.log('ðŸ“¤ uploadSingleFile called for:', fileData.file.name, 'with metadata:', fileData.metadata);
    console.log('ðŸ“¤ File object:', fileData.file);
    console.log('ðŸ“¤ Metadata object:', fileData.metadata);
    
    try {
        // Use the same working upload logic as single file upload
        console.log('ðŸ“¤ Calling fallbackToSimpleUpload...');
        await fallbackToSimpleUpload(fileData.file, fileData.metadata);
        
        console.log('âœ… Bulk file upload successful:', fileData.file.name);
        return { success: true, filename: fileData.file.name };
        
    } catch (error) {
        console.error('âŒ Bulk file upload failed:', fileData.file.name, error);
        throw error;
    }
}

function generateBulkList(files) {
    const listContainer = document.getElementById('bulkFileList');
    if (listContainer) {
        listContainer.innerHTML = '';
        
        // Initialize uploadQueue for regular files with proper metadata
        uploadQueue = [];
        console.log('ðŸ“‹ Initializing uploadQueue for', files.length, 'regular files in generateBulkList');
        
        files.forEach((file, index) => {
            const item = createBulkItem(file, index);
            listContainer.appendChild(item);
            
            // Add file to uploadQueue with proper metadata for database saving
            uploadQueue.push({
                file: file,
                index: index,
                status: 'pending',
                progress: 0,
                metadata: {
                    title: file.name.replace(/\.[^/.]+$/, ''),
                    description: '',
                    category: 'General',
                    tags: '',
                    visibility: 'public'
                },
                googleDrive: false
            });
            console.log('ðŸ“ Added file to uploadQueue in generateBulkList:', file.name, 'at index', index);
        });
        
        console.log('ðŸš€ uploadQueue initialized with', uploadQueue.length, 'items');
    }
}

function createBulkItem(file, index) {
    const div = document.createElement('div');
    div.className = `bulk-file-item ${currentView}`;
    div.dataset.index = index;
    
    const type = getMediaType(file);
    let previewHtml = '';
    if (type === 'video' || type === 'audio') {
        previewHtml = `<${type} controls src="${URL.createObjectURL(file)}" style="width: 100%; height: 100px;"></${type}>`;
    } else if (type === 'image') {
        previewHtml = `<img src="${URL.createObjectURL(file)}" alt="preview" style="width: 100px; height: 100px; object-fit: cover;">`;
    }
    
    div.innerHTML = `
        <div class="file-preview">
            ${previewHtml}
            <div class="file-status">
                <span class="status-text">Ready</span>
            </div>
        </div>
        
        <div class="file-details">
            <input type="text" class="file-title" placeholder="Title" 
                   value="${extractTitleFromFilename(file.name)}" 
                   data-field="title" data-index="${index}">
            
            <textarea class="file-description" placeholder="Description" 
                      rows="2" data-field="description" data-index="${index}"></textarea>
            
            <div class="file-meta">
                <select class="file-category" data-field="category" data-index="${index}">
                    <option value="">Select Category</option>
                    <option value="Entertainment">Entertainment</option>
                    <option value="Education">Education</option>
                    <option value="Gaming">Gaming</option>
                    <option value="Music">Music</option>
                    <option value="News">News</option>
                    <option value="Sports">Sports</option>
                    <option value="Technology">Technology</option>
                    <option value="Other">Other</option>
                </select>
                
                <select class="file-visibility" data-field="visibility" data-index="${index}">
                    <option value="public">Public</option>
                    <option value="unlisted">Unlisted</option>
                    <option value="private">Private</option>
                </select>
            </div>
            
            <input type="text" class="file-tags" placeholder="Tags (comma separated)" 
                   data-field="tags" data-index="${index}">
            
            <div class="file-info">
                <span class="file-name">${file.name}</span>
                <span class="file-size">${formatFileSize(file.size)}</span>
                <span class="file-type">${type.toUpperCase()}</span>
            </div>
            
            <!-- Individual Progress Bar for this video -->
            <div class="individual-progress-container" id="progress-container-${index}" style="display: none;">
                <div class="individual-progress-bar">
                    <div class="individual-progress-fill" id="progress-fill-${index}" style="width: 0%;"></div>
                </div>
                <div class="individual-progress-text" id="progress-text-${index}">Uploading 0%</div>
            </div>
        </div>
        
        <button type="button" class="remove-file-btn" onclick="removeBulkFile(${index})">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    // Add change listeners
    div.querySelectorAll('input, textarea, select').forEach(input => {
        input.addEventListener('change', (e) => updateBulkMetadata(index, e.target));
    });
    
    return div;
}

function getMediaType(file) {
    const ext = file.name.split('.').pop().toLowerCase();
    for (const type in MEDIA_TYPES) {
        if (MEDIA_TYPES[type].extensions.includes(ext)) {
            return type;
        }
    }
    return 'unknown';
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function extractTitleFromFilename(name) {
    return name.replace(/\.[^/.]+$/, "").replace(/[-_]/g, ' ');
}

function removeBulkFile(index) {
    uploadQueue.splice(index, 1);
    selectedFiles.splice(index, 1);
    generateBulkFileRowsWithThumbnails();
    if (uploadQueue.length === 0) {
        resetBulkMode();
    }
}

function updateBulkMetadata(index, target) {
    const field = target.dataset.field;
    uploadQueue[index].metadata[field] = target.value;
}

function toggleBulkView() {
    currentView = currentView === 'grid' ? 'list' : 'grid';
    const list = document.getElementById('bulkFileList');
    if (list) {
        list.classList.toggle('list', currentView === 'list');
        list.classList.toggle('grid', currentView === 'grid');
    }
    // Update button icon/text if needed
}

async function processUpload() {
    console.log('ðŸ“¦ processUpload() called');
    if (!validateForm()) {
        console.log('âŒ Form validation failed in processUpload');
        return;
    }
    
    console.log('âœ… Form validation passed, bulkMode:', bulkMode);
    
    if (bulkMode) {
        console.log('ðŸ“¦ Processing bulk upload');
        await processBulkQueue();
    } else {
        console.log('ðŸ“¦ Processing single upload');
        await processSingleUpload();
    }
}

async function processSingleUpload() {
    const metadata = getFormMetadata();
    
    console.log('ðŸ“¦ processSingleUpload - currentUploadType:', currentUploadType);
    console.log('ðŸ“¦ processSingleUpload - selectedFile:', !!selectedFile);
    console.log('ðŸ“¦ processSingleUpload - currentVideoData:', window.currentVideoData);
    
    // Handle platform imports (YouTube, Vimeo, etc.) - but NOT Google Drive
    if (currentUploadType === 'import' && currentSource !== 'googledrive' && window.currentVideoData) {
        console.log('ðŸ“¦ Processing platform import');
        await processPlatformImport(metadata);
        return;
    }
    
    // Handle file uploads - check selectedFile, selectedGoogleDriveFile, and currentVideoData.file
    console.log('ðŸ” DEBUG: selectedFile:', selectedFile);
    console.log('ðŸ” DEBUG: selectedGoogleDriveFile:', selectedGoogleDriveFile);
    console.log('ðŸ” DEBUG: window.currentVideoData:', window.currentVideoData);
    console.log('ðŸ” DEBUG: currentUploadType:', currentUploadType);
    console.log('ðŸ” DEBUG: currentSource:', currentSource);
    
    let fileToUpload = selectedFile;
    if (!fileToUpload && selectedGoogleDriveFile) {
        fileToUpload = selectedGoogleDriveFile;
        console.log('ðŸ“ Using Google Drive file:', fileToUpload.name);
    }
    if (!fileToUpload && window.currentVideoData && window.currentVideoData.file) {
        fileToUpload = window.currentVideoData.file;
        console.log('ðŸ“ Using file from currentVideoData:', fileToUpload.name);
    }
    
    console.log('ðŸ” DEBUG: Final fileToUpload:', fileToUpload);
    
    if (!fileToUpload) {
        console.error('âŒ DEBUG: No file found! selectedFile:', selectedFile, 'selectedGoogleDriveFile:', selectedGoogleDriveFile, 'currentVideoData:', window.currentVideoData);
        showCustomAlert('Upload Error', 'Invalid upload type or missing file/URL.');
        return;
    }
    
    // Use R2 multipart for files > 100MB, simple upload for smaller files
    const multipartThreshold = 100 * 1024 * 1024; // 100MB
    
    if (fileToUpload.size > multipartThreshold) {
        console.log('📦 Using R2 multipart upload for large file:', formatFileSize(fileToUpload.size));
        await useR2MultipartUpload(fileToUpload, metadata);
    } else {
        console.log('📤 Using simple upload for small file:', formatFileSize(fileToUpload.size));
        await fallbackToSimpleUpload(fileToUpload, metadata);
    }
}

// Use R2 multipart upload for large files
async function useR2MultipartUpload(file, metadata) {
    // Check if R2MultipartUploader class is available
    if (typeof R2MultipartUploader === 'undefined') {
        console.error('R2MultipartUploader class not available, falling back to simple upload');
        return await fallbackToSimpleUpload(file, metadata);
    }
    
    try {
        const uploader = new R2MultipartUploader({
            endpoint: './ionuploadvideos.php', // Use new consolidated endpoint
            onProgress: (progress) => {
                updateProgressBar(progress);
                console.log(`R2 upload progress: ${progress.toFixed(1)}%`);
            },
            onSuccess: (result) => {
                console.log('✅ R2 multipart upload completed:', result);
                onUploadComplete(result);
            },
            onError: (error) => {
                console.error('❌ R2 multipart upload failed:', error);
                // Fallback to simple upload
                fallbackToSimpleUpload(file, metadata);
            }
        });
        
        // Start the multipart upload
        await uploader.upload(file, metadata);
        
    } catch (error) {
        console.error('R2 multipart upload error:', error);
        // Fallback to simple upload
        await fallbackToSimpleUpload(file, metadata);
    }
}

// Handle platform imports (YouTube, Vimeo, etc.) - Using working ions pattern
async function processPlatformImport(metadata) {
    console.log('🌐 Processing platform import with metadata:', metadata);
    
    const urlInput = document.getElementById('urlInput');
    if (!urlInput) {
        showCustomAlert('Upload Error', 'URL input not found.');
        return;
    }
    
    const url = urlInput.value.trim();
    if (!url) {
        showCustomAlert('Upload Error', 'Missing video URL for platform import.');
        return;
    }
    
    // Extract video ID based on platform (using working ions logic)
    let videoId = '';
    if (currentSource === 'youtube') {
        videoId = extractYouTubeId(url);
        if (!videoId) {
            showCustomAlert('Upload Error', 'Invalid YouTube URL');
            return;
        }
    } else if (currentSource === 'vimeo') {
        const vimeoMatch = url.match(/vimeo\.com\/(?:channels\/[^\/]+\/|groups\/[^\/]+\/videos\/|album\/[^\/]+\/video\/|video\/|)(\d+)/);
        videoId = vimeoMatch ? vimeoMatch[1] : '';
        if (!videoId) {
            showCustomAlert('Upload Error', 'Invalid Vimeo URL');
            return;
        }
    } else if (currentSource === 'muvi') {
        const muviMatch = url.match(/embed\.muvi\.com\/embed\/([a-zA-Z0-9]+)/);
        videoId = muviMatch ? muviMatch[1] : '';
        if (!videoId) {
            showCustomAlert('Upload Error', 'Invalid Muvi URL. Please use the embed URL format: https://embed.muvi.com/embed/...');
            return;
        }
    } else if (currentSource === 'loom') {
        const loomMatch = url.match(/loom\.com\/share\/([a-zA-Z0-9]+)/);
        videoId = loomMatch ? loomMatch[1] : '';
        if (!videoId) {
            showCustomAlert('Upload Error', 'Invalid Loom URL');
            return;
        }
    } else if (currentSource === 'wistia') {
        const wistiaMatch = url.match(/(?:wistia\.(?:com|net)\/(?:medias|embed)\/|wi\.st\/)([a-zA-Z0-9]+)/);
        videoId = wistiaMatch ? wistiaMatch[1] : '';
        if (!videoId) {
            showCustomAlert('Upload Error', 'Invalid Wistia URL');
            return;
        }
    }
    
    console.log('ðŸŒ Extracted video ID:', videoId, 'from URL:', url);
    
    // Prepare form data using working ions pattern
    const formData = new FormData();
    formData.append('action', 'platform_import'); // Use correct action for backend routing
    formData.append('url', url); // Backend expects 'url' parameter
    formData.append('video_id', videoId);
    formData.append('platform', currentSource);
    formData.append('title', metadata.title || '');
    formData.append('description', metadata.description || '');
    formData.append('category', metadata.category || 'General');
    formData.append('visibility', metadata.visibility || 'public');
    formData.append('tags', metadata.tags ? metadata.tags.join(', ') : '');
    formData.append('badges', metadata.badges || '');
    
    // Add thumbnail if captured
    if (metadata.thumbnailBlob) {
        formData.append('thumbnail', metadata.thumbnailBlob, 'thumbnail.jpg');
    } else if (metadata.customThumbnailFile) {
        formData.append('thumbnail', metadata.customThumbnailFile);
    }
    
    try {
        setUploadButtonState('uploading');
        showProgress('Importing video...', 0);
        
        const response = await fetch('./ionuploadvideos.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const result = await response.json();
        console.log('🌐 Platform import server response:', result);
        
        if (result.success) {
            showProgress('Import complete!', 100);
            setTimeout(() => {
                hideProgress();
                setUploadButtonState('normal');
                
                // Check if we should show celebration dialog
                if (result.celebration && result.shortlink) {
                    showCelebrationDialog(result);
                } else {
                    showCustomAlert('Success', result.message || 'Import completed successfully!');
                }
            }, 500);
        } else {
            hideProgress();
            setUploadButtonState('normal');
            showCustomAlert('Import Error', result.error || 'Failed to import video.');
        }
    } catch (error) {
        hideProgress();
        setUploadButtonState('normal');
        console.error('Platform import error:', error);
        showCustomAlert('Import Error', 'Failed to import video: ' + error.message);
    }
}

// Expose function to global scope for access from main ionuploader.js
window.processPlatformImport = processPlatformImport;

// Fallback to working ionuploadvideos.php (from old version)
async function fallbackToSimpleUpload(fileToUpload, metadata) {
    console.log('ðŸ” DEBUG fallbackToSimpleUpload called with:', fileToUpload, metadata);
    
    if (!fileToUpload) {
        console.error('âŒ fallbackToSimpleUpload: No file provided');
        showCustomAlert('Error', 'No file selected');
        return;
    }
    
    // Set upload button to uploading state
    setUploadButtonState('uploading');
    
    console.log('ðŸ“¦ Creating FormData for simple upload');
    const formData = new FormData();
    
    // Handle Google Drive files differently
    if (currentSource === 'googledrive' && fileToUpload.id) {
        console.log('ðŸ” Creating FormData for Google Drive upload');
        formData.append('action', 'google_drive'); // Add missing action for Google Drive uploads
        formData.append('google_drive_file_id', fileToUpload.id);
        formData.append('google_drive_access_token', accessToken);
        formData.append('source', 'googledrive');
        formData.append('video_id', fileToUpload.id);
        formData.append('video_link', `https://drive.google.com/file/d/${fileToUpload.id}/view`);
    } else {
        console.log('ðŸ” Creating FormData for regular file upload');
        formData.append('action', 'upload'); // Add missing action for regular file uploads
        formData.append('video', fileToUpload);  // PHP expects 'video' not 'video_file'
    }
    
    formData.append('title', metadata.title || fileToUpload.name?.replace(/\.[^/.]+$/, '') || 'Untitled');
    formData.append('description', metadata.description || '');
    formData.append('category', metadata.category || 'General');
    formData.append('tags', metadata.tags || '');
    formData.append('visibility', metadata.visibility || 'public');
    // Action parameter is now set above based on upload type
    
    console.log('ðŸ“¦ FormData prepared with:', {
        file: fileToUpload.name,
        size: fileToUpload.size,
        title: metadata.title,
        upload_type: 'file'
    });
    
    // Add thumbnail if available (captured or custom)
    if (window.capturedThumbnailBlob) {
        formData.append('thumbnail', window.capturedThumbnailBlob, 'captured-thumbnail.jpg');
        console.log('ðŸ–¼ï¸ Adding captured thumbnail to upload, size:', window.capturedThumbnailBlob.size, 'bytes');
    } else if (window.customThumbnailFile) {
        formData.append('thumbnail', window.customThumbnailFile);
        console.log('ðŸ–¼ï¸ Adding custom thumbnail to upload:', window.customThumbnailFile.name);
    }
    
    try {
        // Use XMLHttpRequest for proper progress tracking
        const result = await new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            
            xhr.upload.onprogress = (e) => {
                if (e.lengthComputable) {
                    const percentComplete = (e.loaded / e.total) * 100;
                    console.log(`📊 Upload progress: ${percentComplete.toFixed(1)}%`);
                    
                    // For bulk uploads, update per-file progress (if we have the index)
                    if (typeof window.currentBulkIndex !== 'undefined') {
                        const idx = window.currentBulkIndex;
                        const progressContainer = document.getElementById(`progress-container-${idx}`);
                        const progressFill = document.getElementById(`progress-fill-${idx}`);
                        const progressText = document.getElementById(`progress-text-${idx}`);
                        
                        if (progressContainer) progressContainer.style.display = 'block';
                        if (progressFill) progressFill.style.width = percentComplete + '%';
                        if (progressText) progressText.textContent = `Uploading ${Math.round(percentComplete)}%`;
                    } else {
                        // Single upload - use main progress bar
                        const progressBar = document.getElementById('uploadProgressBar');
                        const progressText = document.getElementById('uploadProgressText');
                        if (progressBar) progressBar.style.width = percentComplete + '%';
                        if (progressText) progressText.textContent = `Uploading... ${Math.round(percentComplete)}%`;
                    }
                }
            };
            
            xhr.onload = () => {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        resolve(response);
                    } catch (e) {
                        reject(new Error('Invalid JSON response'));
                    }
                } else {
                    reject(new Error(`HTTP ${xhr.status}`));
                }
            };
            
            xhr.onerror = () => reject(new Error('Network error'));
            xhr.ontimeout = () => reject(new Error('Upload timeout'));
            
            xhr.open('POST', './ionuploadvideos.php', true);
            xhr.send(formData);
        });
        
        if (result.success) {
            console.log('✅ Upload successful for bulk!');
            return { success: true, filename: fileToUpload.name };
        } else {
            hideProgress();
            setUploadButtonState('normal');
            console.error('âŒ Upload failed:', result.error);
            showCustomAlert('Upload Error', result.error || 'Upload failed');
        }
    } catch (error) {
        hideProgress();
        setUploadButtonState('normal');
        console.error('âŒ Simple upload error:', error);
        showCustomAlert('Upload Error', 'Upload failed: ' + error.message);
    }
}

async function processBulkQueue() {
    // Hide wasted header and footer space
    const bulkHeader = document.querySelector('.bulk-header');
    const uploadProgressContainer = document.getElementById('uploadProgressContainer');
    
    if (bulkHeader) {
        bulkHeader.style.display = 'none';
        console.log('ðŸ“ Hiding bulk header to save space');
    }
    if (uploadProgressContainer) {
        uploadProgressContainer.style.display = 'none';
        console.log('ðŸ“ Hiding footer progress bar - using inline progress instead');
    }
    
    let errors = [];
    const concurrentLimit = 10; // Increased from 3 to 10 for better performance
    let active = 0;
    
    async function processNext() {
        while (uploadQueue.length > 0) {
            const item = uploadQueue.shift();
            active++;
            try {
                await processBulkItem(item);
            } catch (error) {
                errors.push({ file: item.file.name, err: error });
            }
            active--;
        }
    }
    
    const promises = [];
    for (let i = 0; i < concurrentLimit; i++) {
        promises.push(processNext());
    }
    await Promise.all(promises);
    
    showBulkSummary(errors);
}

async function processBulkItem(item) {
    item.status = 'uploading';
    item.progress = 0;
    updateBulkItemUI(item);
    
    // Get metadata from uploadQueue item or form fields
    let metadata = item.metadata || {};
    
    // Try to get updated metadata from form fields if they exist
    const titleField = document.querySelector(`input[data-field="title"][data-index="${item.index}"]`);
    const descField = document.querySelector(`textarea[data-field="description"][data-index="${item.index}"]`);
    const categoryField = document.querySelector(`select[data-field="category"][data-index="${item.index}"]`);
    const tagsField = document.querySelector(`input[data-field="tags"][data-index="${item.index}"]`);
    const visibilityField = document.querySelector(`select[data-field="visibility"][data-index="${item.index}"]`);
    
    if (titleField) metadata.title = titleField.value || metadata.title;
    if (descField) metadata.description = descField.value || metadata.description;
    if (categoryField) metadata.category = categoryField.value || metadata.category;
    if (tagsField) metadata.tags = tagsField.value || metadata.tags;
    if (visibilityField) metadata.visibility = visibilityField.value || metadata.visibility;
    
    console.log('ðŸ“ Bulk item metadata for', item.file.name, ':', metadata);
    
    try {
        // Check if this is a Google Drive item
        if (item.googleDrive && item.driveFileId) {
            console.log('ðŸš€ Processing Google Drive item:', item.file.name);
            
            // Use background transfer for Google Drive files
            const result = await initiateDriveBackgroundTransfer(item.driveFileId, metadata);
            
            if (result.status === 'transferring') {
                item.status = 'transferring';
                item.transferId = result.transferId;
                item.message = result.message;
                updateBulkItemUI(item);
                
                // Start polling for transfer status
                pollTransferStatus(item);
            } else {
                item.status = 'completed';
                item.shortlink = result.shortlink;
                updateBulkItemUI(item);
            }
            
        } else {
            console.log('ðŸ’¾ Processing local file:', item.file.name);
            
            // TEMPORARY: Use simple upload for bulk files (same as single file fix)
            console.log('âš ï¸ Using simple upload for bulk file:', item.file.name);
            
            try {
                // Capture thumbnail for video files BEFORE uploading (like single upload)
                let capturedThumbBlob = null;
                if (getMediaType(item.file) === 'video') {
                    try {
                        console.log('ðŸŽ¬ Capturing thumbnail frame for bulk upload:', item.file.name);
                        capturedThumbBlob = await captureThumbnailFromFile(item.file, 0.10); // 10% position
                        if (capturedThumbBlob) {
                            console.log('ðŸ–¼ï¸ Captured thumbnail size:', capturedThumbBlob.size, 'bytes');
                            // Update preview in UI immediately
                            const thumbUrl = URL.createObjectURL(capturedThumbBlob);
                            const thumbContainer = document.getElementById(`thumbnail-${item.index}`);
                            if (thumbContainer) {
                                thumbContainer.innerHTML = `<img src="${thumbUrl}" alt="thumb" style="width:100%;height:100%;object-fit:cover;border-radius:8px;"/>`;
                            }
                            // Revoke later
                            setTimeout(() => URL.revokeObjectURL(thumbUrl), 30000);
                        }
                    } catch (e) {
                        console.warn('âš ï¸ Failed to capture thumbnail for bulk upload:', item.file.name, e);
                    }
                }

                // Use EXACT same FormData as single upload (fallbackToSimpleUpload)
                const formData = new FormData();
                formData.append('video', item.file);  // PHP expects 'video' not 'video_file'
                formData.append('title', metadata.title || item.file.name.replace(/\.[^/.]+$/, '') || 'Untitled');
                formData.append('description', metadata.description || '');
                formData.append('category', metadata.category || 'General');
                formData.append('tags', metadata.tags || '');
                formData.append('visibility', metadata.visibility || 'public');
                formData.append('upload_type', 'file');  // Same as single upload
                
                // Attach captured thumbnail if available (server supports $_FILES['thumbnail'])
                if (capturedThumbBlob) {
                    formData.append('thumbnail', capturedThumbBlob, 'captured-thumbnail.jpg');
                    console.log('ðŸ–¼ï¸ Attached captured thumbnail to bulk upload FormData');
                }
                
                console.log('ðŸ“¡ Bulk upload FormData for', item.file.name, ':', {
                    title: metadata.title || item.file.name.replace(/\.[^/.]+$/, ''),
                    description: metadata.description || '',
                    category: metadata.category || 'General',
                    tags: metadata.tags || '',
                    visibility: metadata.visibility || 'public',
                    upload_type: 'file'
                });
                // Update progress
                item.progress = 10;
                updateBulkItemUI(item);
                
                console.log('ðŸ“¡ Bulk upload: Starting fetch to ./ionuploadvideos.php for:', item.file.name);
                const response = await fetch('./ionuploadvideos.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });
                
                item.progress = 70;
                updateBulkItemUI(item);
                console.log('âœ… Bulk upload response for', item.file.name, ':', response.status, response.statusText);
                
                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`HTTP ${response.status}: ${errorText}`);
                }
                
                const result = await response.json();
                console.log('ðŸ“ Bulk upload result for', item.file.name, ':', result);
                
                // Check if server returned a thumbnail URL
                if (result.thumbnail_url) {
                    console.log('ðŸ–¼ï¸ Server generated thumbnail:', result.thumbnail_url);
                    // Update the thumbnail in the UI
                    const thumbnailContainer = document.getElementById(`thumbnail-${item.index}`);
                    if (thumbnailContainer) {
                        thumbnailContainer.innerHTML = `<img src="${result.thumbnail_url}" alt="Video thumbnail" style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;">`;
                        console.log('âœ… Updated thumbnail in UI for:', item.file.name);
                    }
                } else {
                    console.log('âš ï¸ No thumbnail URL returned from server for:', item.file.name);
                }
                
                if (result.success) {
                    item.progress = 100;
                    item.status = 'completed';
                    item.shortlink = result.shortlink;
                    item.message = 'Upload successful!';
                    updateBulkItemUI(item);
                    console.log('âœ… Bulk upload successful for:', item.file.name, 'Shortlink:', result.shortlink);
                } else if (result.error) {
                    console.error('âŒ Bulk upload failed for', item.file.name, ':', result.error);
                    throw new Error(result.error || 'Upload failed');
                } else {
                    throw new Error('Upload failed');
                }
                
            } catch (error) {
                console.error('âŒ Bulk upload failed for', item.file.name, ':', error);
                item.status = 'failed';
                item.error = error.message;
                item.progress = 0;
                updateBulkItemUI(item);
                throw error;
            }
        }
        
    } catch (error) {
        console.error('âŒ Bulk item processing failed:', error);
        item.status = 'failed';
        item.error = error.message;
        updateBulkItemUI(item);
    }
}

function updateBulkItemUI(item) {
    const itemElem = document.querySelector(`[data-index="${item.index}"]`);
    if (!itemElem) {
        console.warn('Could not find item element for index:', item.index);
        return;
    }
    
    // Update status text in the simple row layout
    const statusText = itemElem.querySelector('.status-text');
    if (statusText) {
        let displayText = item.status.charAt(0).toUpperCase() + item.status.slice(1);
        if (item.progress > 0 && item.status === 'uploading') {
            displayText = `Uploading ${item.progress}%`;
        }
        statusText.textContent = displayText;
        
        // Color coding for status
        if (item.status === 'completed') {
            statusText.style.color = '#10b981'; // Green
            itemElem.style.borderColor = '#10b981';
        } else if (item.status === 'failed') {
            statusText.style.color = '#ef4444'; // Red
            itemElem.style.borderColor = '#ef4444';
        } else if (item.status === 'uploading') {
            statusText.style.color = '#3b82f6'; // Blue
            itemElem.style.borderColor = '#3b82f6';
        } else {
            statusText.style.color = '#6b7280'; // Gray
            itemElem.style.borderColor = '#6b7280';
        }
    }
    
    // Show/hide and update individual progress bar
    const progressContainer = itemElem.querySelector(`#progress-container-${item.index}`);
    const progressFill = itemElem.querySelector(`#progress-fill-${item.index}`);
    const progressTextElem = itemElem.querySelector(`#progress-text-${item.index}`);
    
    if (progressContainer && progressFill && progressTextElem) {
        if (item.status === 'uploading' && item.progress > 0) {
            progressContainer.style.display = 'block';
            progressFill.style.width = `${item.progress}%`;
            progressFill.style.transition = 'width 0.3s ease';
            progressTextElem.textContent = `Uploading ${item.progress}%`;
        } else if (item.status === 'completed') {
            progressContainer.style.display = 'block';
            progressFill.style.width = '100%';
            progressFill.style.backgroundColor = '#10b981';
            progressTextElem.textContent = 'Upload Complete!';
        } else if (item.status === 'failed') {
            progressContainer.style.display = 'block';
            progressFill.style.width = '0%';
            progressFill.style.backgroundColor = '#ef4444';
            progressTextElem.textContent = 'Upload Failed';
        } else {
            progressContainer.style.display = 'none';
        }
    }
    
    console.log(`ðŸ“Š Updated UI for ${item.file.name}: ${item.status} ${item.progress}%`);
}

function updateBulkProgress(item) {
    const itemElem = document.querySelector(`.bulk-file-item[data-index="${item.index}"]`);
    if (itemElem) {
        const progress = itemElem.querySelector('.progress-bar');
        progress.style.width = `${item.progress}%`;
    }
}

function showBulkSummary(errors) {
    let message = `Bulk upload complete!\nSuccess: ${selectedFiles.length - errors.length}\n`;
    if (errors.length) {
        message += 'Errors:\n' + errors.map(e => `${e.file}: ${e.err.message}`).join('\n');
    }
    showCustomAlert('Bulk Summary', message);
    resetBulkMode();
}

function resetBulkMode() {
    bulkMode = false;
    uploadQueue = [];
    selectedFiles = [];
    selectedGoogleDriveFiles = [];
    document.getElementById('bulkStep2').style.display = 'none';
    document.getElementById('step1').style.display = 'block';
    const nextBtn = document.getElementById('nextBtn');
    if (nextBtn) {
        nextBtn.textContent = 'Next';
    }
}

// ============================================
// GOOGLE DRIVE HANDLING (Single/Bulk, Background)
// ============================================
function handleGoogleDriveButtonClick(event) {
    // From live.js
    console.log('ðŸ’¾ Google Drive button clicked');
    event.stopPropagation();
    
    // Debug Google APIs availability
    console.log('ðŸ” Google APIs available:', typeof google !== 'undefined' && typeof gapi !== 'undefined');
    console.log('ðŸ” CLIENT_ID:', CLIENT_ID);
    console.log('ðŸ” API_KEY:', API_KEY);
    
    const connections = getStoredConnections();
    console.log('ðŸ” Stored connections:', connections.length);
    
    if (connections.length > 0) {
        console.log('âœ… Showing existing connections dropdown');
        showGoogleDriveDropdown();
    } else {
        console.log('âž• Adding new Google Drive connection');
        addNewGoogleDrive();
    }
}

function showGoogleDriveDropdown() {
    // From live.js, with connections list
    const dropdown = document.getElementById('googleDriveDropdown');
    if (dropdown) {
        dropdown.style.display = 'block';
        console.log('âœ… Google Drive dropdown shown');
    }
}

function hideGoogleDriveDropdown() {
    const dropdown = document.getElementById('googleDriveDropdown');
    if (dropdown) {
        dropdown.style.display = 'none';
        console.log('âœ… Google Drive dropdown hidden');
    }
}

function addNewGoogleDrive() {
    console.log('Adding new Google Drive connection...');
    hideGoogleDriveDropdown();
    loadGoogleAPIs();
    setTimeout(() => {
        authenticateAndShowPicker();
    }, 500);
}

function handleGoogleSignIn(tokenResponse) {
    // From live.js, store connection, accessToken = tokenResponse.access_token
    accessToken = tokenResponse.access_token;
    fetchUserEmail(accessToken).then(email => {
        storeConnection(email, accessToken);
        showPicker();
    });
}

function fetchUserEmail(token) {
    return fetch('https://www.googleapis.com/drive/v3/about?fields=user', {
        headers: {
            Authorization: `Bearer ${token}`
        }
    }).then(res => res.json()).then(data => data.user.emailAddress);
}

function storeConnection(email, token) {
    let connections = getStoredConnections();
    connections = connections.filter(c => c.email !== email);
    connections.push({ email, token });
    localStorage.setItem('googleDriveConnections', JSON.stringify(connections));
}

function getStoredConnections() {
    const stored = localStorage.getItem('googleDriveConnections');
    return stored ? JSON.parse(stored) : [];
}

function updateConnectedDrivesDropdown() {
    const connections = getStoredConnections();
    const connectedDrivesContainer = document.querySelector('.connected-drives');
    const noDrivesMessage = document.querySelector('.no-drives-message');
    
    if (connectedDrivesContainer) {
        if (connections.length > 0) {
            // Show connected drives
            if (noDrivesMessage) noDrivesMessage.style.display = 'none';
            
            // Update the connected drives list
            let drivesHTML = '';
            connections.forEach((conn, index) => {
                drivesHTML += `
                    <div class="connected-drive-item" data-index="${index}" data-email="${conn.email}" onclick="useStoredConnection(${index})">
                        <span class="drive-email">${conn.email}</span>
                        <button class="use-drive-btn" onclick="event.stopPropagation(); useStoredConnection(${index})">
                            <i class="fas fa-check"></i> Use
                        </button>
                        <button class="remove-drive-btn" onclick="event.stopPropagation(); removeConnection(${index})">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
            });
            connectedDrivesContainer.innerHTML = drivesHTML;
            connectedDrivesContainer.style.display = 'block';
        } else {
            // Show no drives message
            if (noDrivesMessage) noDrivesMessage.style.display = 'block';
            connectedDrivesContainer.style.display = 'none';
        }
    }
    
    console.log('ðŸ”„ Connected drives dropdown updated:', connections.length, 'connections');
}

async function useStoredConnection(index) {
    const connections = getStoredConnections();
    if (connections[index]) {
        const connection = connections[index];
        console.log('ðŸ” Testing stored token for:', connection.email);
        
        // Test if the stored token is still valid
        try {
            const testResponse = await fetch('https://www.googleapis.com/drive/v3/about?fields=user', {
                headers: {
                    'Authorization': `Bearer ${connection.token}`
                }
            });
            
            if (testResponse.ok) {
                // Token is valid, use it
                accessToken = connection.token;
                oauthToken = connection.token;
                console.log('âœ… Using valid stored connection for:', connection.email);
                showGoogleDrivePicker();
            } else {
                // Token is expired or invalid
                console.warn('âš ï¸ Stored token expired for:', connection.email);
                showCustomAlert('Token Expired', `Your Google Drive connection for ${connection.email} has expired. Please reconnect.`);
                
                // Remove the expired connection
                removeConnection(index);
            }
        } catch (error) {
            console.error('âŒ Error testing stored token:', error);
            showCustomAlert('Connection Error', `Failed to validate Google Drive connection for ${connection.email}. Please reconnect.`);
            
            // Remove the problematic connection
            removeConnection(index);
        }
    }
}

function removeConnection(index) {
    const connections = getStoredConnections();
    if (connections[index]) {
        console.log('ðŸ—‘ï¸ Removing connection for:', connections[index].email);
        connections.splice(index, 1);
        localStorage.setItem('googleDriveConnections', JSON.stringify(connections));
        updateConnectedDrivesDropdown();
    }
}

function clearAllConnections() {
    localStorage.removeItem('googleDriveConnections');
    showCustomAlert('Success', 'All Google Drive connections cleared.');
}

function useConnection(email) {
    console.log('ðŸ”— Using stored connection for:', email);
    const connections = getStoredConnections();
    const connection = connections.find(c => c.email === email);
    if (connection) {
        console.log('âœ… Connection found, setting access token');
        accessToken = connection.token;
        
        // Hide the dropdown immediately
        hideGoogleDriveDropdown();
        
        // Test the token before showing picker
        testTokenAndShowPicker(connection);
    } else {
        console.error('âŒ Connection not found for email:', email);
        showCustomAlert('Error', 'Connection not found. Please reconnect your Google Drive.');
    }
}

async function testTokenAndShowPicker(connection) {
    try {
        console.log('ðŸ” Testing stored token validity...');
        const testResponse = await fetch('https://www.googleapis.com/drive/v3/about?fields=user', {
            headers: {
                'Authorization': `Bearer ${connection.token}`
            }
        });
        
        if (testResponse.ok) {
            console.log('âœ… Token is valid, showing picker');
            showGoogleDrivePicker();
        } else {
            console.warn('âš ï¸ Token expired, removing connection and prompting for new auth');
            // Remove expired connection
            const connections = getStoredConnections();
            const updatedConnections = connections.filter(c => c.email !== connection.email);
            localStorage.setItem('googleDriveConnections', JSON.stringify(updatedConnections));
            updateConnectedDrivesDropdown();
            
            showCustomAlert('Token Expired', `Your Google Drive connection for ${connection.email} has expired. Please reconnect.`);
            // Automatically start new connection process
            setTimeout(() => {
                addNewGoogleDrive();
            }, 1000);
        }
    } catch (error) {
        console.error('âŒ Error testing token:', error);
        showCustomAlert('Connection Error', 'Failed to validate Google Drive connection. Please try reconnecting.');
        // Automatically start new connection process
        setTimeout(() => {
            addNewGoogleDrive();
        }, 1000);
    }
}

function showPicker() {
    gapi.client.load('picker').then(() => {
        const picker = new google.picker.PickerBuilder()
            .addView(new google.picker.DocsView(google.picker.ViewId.DOCS).setMimeTypes(getAllSupportedMimeTypes()))
            .setOAuthToken(accessToken)
            .setCallback(handleGoogleDriveSelection)
            .enableFeature(google.picker.Feature.MULTISELECT_ENABLED)
            .setOrigin(window.location.protocol + '//' + window.location.host)
            .setDeveloperKey(API_KEY)
            .setSize(1051, 650)
            .build();
        picker.setVisible(true);
    });
}

function getAllSupportedMimeTypes() {
    const mimeTypes = [];
    for (const config of Object.values(MEDIA_TYPES)) {
        mimeTypes.push(...config.mimeTypes);
    }
    return mimeTypes.join(',');
}

function handleGoogleDriveSelection(data) {
    console.log('Google Drive picker callback:', data);
    
    if (data.action === google.picker.Action.PICKED && data.docs.length > 0) {
        if (data.docs.length > 1) {
            // Multiple files selected - bulk mode
            console.log('ðŸ“ Multiple Google Drive files selected:', data.docs.length);
            bulkMode = true;
            selectedGoogleDriveFiles = data.docs;
            
            // Set upload type and source
            selectUploadType('import');
            currentSource = 'googledrive';
            currentUploadType = 'import';
            
            // Show bulk interface for Google Drive files
            showGoogleDriveBulkInterface(data.docs);
            
            // No need to check Next button - bulk mode has different workflow
            console.log('âœ… Google Drive bulk interface ready');
            
        } else {
            // Single file selected
            const file = data.docs[0];
            console.log('ðŸ“ Single Google Drive file selected:', file);
            
            bulkMode = false;
            selectedGoogleDriveFile = {
                id: file.id,
                name: file.name,
                size: file.sizeBytes || 0,
                mimeType: file.mimeType
            };
            selectedFile = selectedGoogleDriveFile; // CRITICAL: Set selectedFile for Google Drive single uploads
            
            // Set upload type and source - treat Google Drive as file upload, not platform import
            currentSource = 'googledrive';
            currentUploadType = 'file'; // Google Drive files are direct file uploads, not URL-based platform imports
            
            // Update UI elements
            const fileNameEl = document.getElementById('selectedFileName');
            const fileSizeEl = document.getElementById('selectedFileSize');
            const fileInfoEl = document.getElementById('selectedFileInfo');
            
            if (fileNameEl) fileNameEl.textContent = file.name;
            if (fileSizeEl) fileSizeEl.textContent = formatFileSize(file.sizeBytes || 0);
            if (fileInfoEl) fileInfoEl.style.display = 'block';
            
            // Select import option visually
            const importOption = document.getElementById('importOption');
            if (importOption) {
                importOption.classList.add('selected');
            }
            
            // Store video data for form processing
            window.currentVideoData = {
                source: 'googledrive',
                file_id: file.id,
                name: file.name,
                size: file.sizeBytes || 0,
                mimeType: file.mimeType
            };
            
            console.log('âœ… Google Drive file processed, UI updated');
            
            // Enable Next button for single file
            checkNextButton();
            
            // Auto-progress to step 2 for Google Drive files (like the working ions version)
            setTimeout(() => {
                console.log('ðŸš€ Auto-progressing to step 2 for Google Drive file');
                proceedToStep2();
                
                // Populate the title field with the file name (without extension)
                setTimeout(() => {
                    const titleField = document.getElementById('videoTitle');
                    if (titleField && !titleField.value.trim()) {
                        const cleanTitle = file.name.replace(/\.[^/.]+$/, '').replace(/[_-]/g, ' ');
                        titleField.value = cleanTitle;
                        console.log('âœ… Auto-populated title field with:', cleanTitle);
                        
                        // Update character count if it exists
                        const titleCount = document.getElementById('titleCount');
                        if (titleCount) {
                            titleCount.textContent = cleanTitle.length;
                        }
                    }
                    
                    // Show file information in the preview area
                    showGoogleDrivePreview(file);
                }, 100);
            }, 500);
        }
    }
}

function showGoogleDrivePreview(file) {
    console.log('ðŸ“º Showing Google Drive file preview for:', file.name);
    
    const container = document.getElementById('videoPlayerContainer');
    if (!container) {
        console.warn('Video player container not found');
        return;
    }
    
    // Create a preview display for Google Drive files
    const fileSize = file.sizeBytes ? formatFileSize(file.sizeBytes) : 'Unknown size';
    const fileType = file.mimeType || 'Unknown type';
    
    container.innerHTML = `
        <div class="google-drive-preview" style="
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            border-radius: 12px;
            color: white;
            text-align: center;
            padding: 2rem;
        ">
            <div style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.9;">
                <i class="fab fa-google-drive"></i>
            </div>
            <h3 style="margin: 0 0 0.5rem 0; font-size: 1.2rem; font-weight: 600;">
                ${file.name}
            </h3>
            <div style="opacity: 0.8; font-size: 0.9rem; margin-bottom: 1rem;">
                <div>${fileSize}</div>
                <div>${fileType}</div>
            </div>
            <div style="
                background: rgba(255,255,255,0.1);
                padding: 0.5rem 1rem;
                border-radius: 20px;
                font-size: 0.8rem;
                display: flex;
                align-items: center;
                gap: 0.5rem;
            ">
                <i class="fas fa-cloud"></i>
                Ready to import from Google Drive
            </div>
        </div>
    `;
    
    console.log('âœ… Google Drive preview displayed');
}

// Show Google Drive bulk interface
function showGoogleDriveBulkInterface(files) {
    console.log('ðŸŽ¬ Showing Google Drive bulk interface for', files.length, 'files');
    
    // Hide step 1 and show bulk step 2
    const step1 = document.getElementById('step1');
    const step2 = document.getElementById('step2');
    const bulkStep2 = document.getElementById('bulkStep2');
    
    if (step1) step1.style.display = 'none';
    if (step2) step2.style.display = 'none';
    if (bulkStep2) {
        bulkStep2.style.display = 'flex';
        console.log('âœ… Showing bulkStep2 for Google Drive');
    } else {
        console.error('âŒ bulkStep2 element not found!');
        return;
    }
    
    // Update file count
    const fileCountElement = document.getElementById('bulkFileCount');
    if (fileCountElement) {
        fileCountElement.textContent = `${files.length} Google Drive files selected`;
        console.log('âœ… Updated Google Drive file count');
    }
    
    // Hide the Next button since bulk mode uses individual upload buttons
    const nextBtn = document.getElementById('nextBtn');
    if (nextBtn) {
        nextBtn.style.display = 'none';
        console.log('âœ… Hidden Next button for bulk mode');
    }
    
    // Generate the Google Drive file list
    generateGoogleDriveBulkList(files);
    
    // Initialize bulk selection functionality
    initializeBulkSelection();
}

function generateGoogleDriveBulkList(files) {
    const listContainer = document.getElementById('bulkFileList');
    if (!listContainer) return;
    
    listContainer.innerHTML = '';
    uploadQueue = []; // Reset upload queue for Google Drive files
    
    files.forEach((file, index) => {
        const div = document.createElement('div');
        div.className = 'bulk-file-item google-drive-item ' + currentView;
        div.dataset.index = index;
        div.dataset.googleDrive = 'true';
        
        div.innerHTML = `
            <!-- Checkbox for selection -->
            <div class="video-card-checkbox-container">
                <input type="checkbox" class="file-checkbox video-card-checkbox" data-index="${index}" checked>
            </div>
            
            <!-- Thumbnail container -->
            <div class="video-card-thumbnail" id="thumbnail-${index}">
                <div class="thumbnail-placeholder" id="placeholder-${index}">
                    <i class="fas fa-cloud" style="font-size: 2rem; color: #4285f4;"></i>
                    <div style="font-size: 0.8rem; margin-top: 0.5rem;">Loading...</div>
                </div>
                <div class="media-preview" id="preview-${index}">
                    <i class="fab fa-google-drive" style="font-size: 2rem; color: #4285f4;"></i>
                    <span class="status-text">Ready</span>
                </div>
            </div>
            
            <!-- File details and form -->
            <div class="video-card-form">
                <div class="video-form-row">
                    <div class="video-form-group">
                        <label class="video-form-label">TITLE</label>
                        <input type="text" class="video-form-input file-title" placeholder="Enter video title" 
                               value="${extractTitleFromFilename(file.name)}" 
                               data-field="title" data-index="${index}">
                    </div>
                    <div class="video-form-group">
                        <label class="video-form-label">CATEGORY</label>
                        <select class="video-form-select file-category" data-field="category" data-index="${index}">
                            <option value="">Select Category</option>
                            <option value="Entertainment">Entertainment</option>
                            <option value="Education">Education</option>
                            <option value="Gaming">Gaming</option>
                            <option value="Music">Music</option>
                            <option value="News">News</option>
                            <option value="Sports">Sports</option>
                            <option value="Technology">Technology</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
                
                <div class="video-form-row">
                    <div class="video-form-group full-width">
                        <label class="video-form-label">DESCRIPTION</label>
                        <textarea class="video-form-textarea file-description" placeholder="Enter video description" 
                                  rows="3" data-field="description" data-index="${index}"></textarea>
                    </div>
                </div>
                
                <div class="video-form-row">
                    <div class="video-form-group">
                        <label class="video-form-label">TAGS</label>
                        <input type="text" class="video-form-input file-tags" placeholder="Tags (comma separated)" 
                               data-field="tags" data-index="${index}">
                    </div>
                    <div class="video-form-group">
                        <label class="video-form-label">VISIBILITY</label>
                        <select class="video-form-select file-visibility" data-field="visibility" data-index="${index}">
                            <option value="public">Public</option>
                            <option value="unlisted">Unlisted</option>
                            <option value="private">Private</option>
                        </select>
                    </div>
                </div>
                
                <div class="video-card-info">
                    <span class="file-name">${file.name}</span>
                    <span class="file-size">${formatFileSize(file.sizeBytes || 0)}</span>
                    <span class="file-type">Google Drive</span>
                </div>
            </div>
            
            <!-- Action buttons -->
            <div class="video-card-actions">
                <button type="button" class="card-action-btn edit-btn" onclick="toggleEditMode(${index})">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                    Edit
                </button>
                <button type="button" class="card-action-btn upload-btn" onclick="uploadGoogleDriveFile(${index})" id="upload-btn-${index}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="17,8 12,3 7,8"></polyline>
                        <line x1="12" y1="3" x2="12" y2="15"></line>
                    </svg>
                    â¬†ï¸
                </button>
                <button type="button" class="card-action-btn remove-btn" onclick="removeGoogleDriveFile(${index})">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                    Remove
                </button>
            </div>
            
            <!-- Progress bar -->
            <div class="video-card-progress">
                <div class="progress-bar" id="progress-${index}" style="width: 0%;"></div>
                <div class="progress-text" id="progress-text-${index}">Ready to upload</div>
            </div>
        `;
        
        div.querySelectorAll('input, textarea, select').forEach(input => {
            input.addEventListener('change', (e) => updateBulkMetadata(index, e.target));
        });
        
        listContainer.appendChild(div);
        
        // Add to upload queue with Google Drive metadata
        uploadQueue.push({
            file: { name: file.name, size: file.sizeBytes || 0 },
            index: index,
            status: 'pending',
            progress: 0,
            metadata: {},
            googleDrive: true,
            driveFileId: file.id
        });
        
        // Generate thumbnail for Google Drive file
        generateGoogleDriveThumbnail(file, index);
    });
    
    console.log('âœ… Generated Google Drive bulk list with', files.length, 'files and started thumbnail generation');
}

function removeGoogleDriveFile(index) {
    selectedGoogleDriveFiles.splice(index, 1);
    generateGoogleDriveBulkList(selectedGoogleDriveFiles);
    if (selectedGoogleDriveFiles.length === 0) {
        resetBulkMode();
    }
}

// Helper function to get Google Drive file information
async function getGoogleDriveFileInfo(fileId) {
    const response = await gapi.client.drive.files.get({
        fileId: fileId,
        fields: 'id,name,size,mimeType,createdTime,modifiedTime'
    });
    return response.result;
}

// Upload individual Google Drive file
async function uploadGoogleDriveFile(index) {
    console.log('ðŸš€ Starting Google Drive file upload for index:', index);
    
    const file = selectedGoogleDriveFiles[index];
    if (!file) {
        console.error('âŒ Google Drive file not found at index:', index);
        return;
    }
    
    // Get metadata from form fields
    const metadata = getGoogleDriveFileMetadata(index);
    
    // Update UI to show uploading state
    updateGoogleDriveUploadStatus(index, 'uploading', 'Starting upload...');
    
    // Disable upload button
    const uploadBtn = document.getElementById(`upload-btn-${index}`);
    if (uploadBtn) {
        uploadBtn.disabled = true;
        uploadBtn.innerHTML = 'â³';
    }
    
    try {
        // Use background transfer for Google Drive files
        const result = await initiateDriveBackgroundTransfer(file.id, metadata);
        
        if (result.status === 'transferring') {
            updateGoogleDriveUploadStatus(index, 'completed', 'Upload initiated successfully!');
            
            // Update upload button
            if (uploadBtn) {
                uploadBtn.innerHTML = 'âœ…';
                uploadBtn.disabled = true;
            }
            
            console.log('âœ… Google Drive file upload initiated:', result);
        } else {
            throw new Error(result.message || 'Upload failed');
        }
    } catch (error) {
        console.error('âŒ Google Drive file upload failed:', error);
        
        updateGoogleDriveUploadStatus(index, 'error', 'Upload failed: ' + error.message);
        
        // Reset upload button
        if (uploadBtn) {
            uploadBtn.disabled = false;
            uploadBtn.innerHTML = 'â¬†ï¸';
        }
    }
}

// Get metadata from Google Drive file form fields
function getGoogleDriveFileMetadata(index) {
    const title = document.querySelector(`[data-field="title"][data-index="${index}"]`)?.value || '';
    const description = document.querySelector(`[data-field="description"][data-index="${index}"]`)?.value || '';
    const category = document.querySelector(`[data-field="category"][data-index="${index}"]`)?.value || '';
    const tags = document.querySelector(`[data-field="tags"][data-index="${index}"]`)?.value || '';
    const visibility = document.querySelector(`[data-field="visibility"][data-index="${index}"]`)?.value || 'public';
    
    return {
        title: title.trim(),
        description: description.trim(),
        category: category,
        tags: tags.trim(),
        visibility: visibility
    };
}

// Update Google Drive upload status
function updateGoogleDriveUploadStatus(index, status, message) {
    const progressBar = document.getElementById(`progress-${index}`);
    const progressText = document.getElementById(`progress-text-${index}`);
    const statusElement = document.querySelector(`[data-index="${index}"] .status-text`);
    
    if (progressText) {
        progressText.textContent = message;
    }
    
    if (statusElement) {
        statusElement.textContent = status.charAt(0).toUpperCase() + status.slice(1);
    }
    
    if (progressBar) {
        switch (status) {
            case 'uploading':
                progressBar.style.width = '50%';
                progressBar.style.background = '#3b82f6';
                break;
            case 'completed':
                progressBar.style.width = '100%';
                progressBar.style.background = '#10b981';
                break;
            case 'error':
                progressBar.style.width = '100%';
                progressBar.style.background = '#ef4444';
                break;
        }
    }
}

// Function to poll transfer status for background transfers
function pollTransferStatus(item) {
    if (!item.transferId) return;
    
    const pollInterval = setInterval(async () => {
        try {
            const response = await fetch(`./ionuploadvideos.php?action=transfer_status&transferId=${item.transferId}`);
            const result = await response.json();
            
            if (result.success) {
                if (result.status === 'completed') {
                    item.status = 'completed';
                    item.progress = 100;
                    item.shortlink = result.shortlink;
                    updateBulkItemUI(item);
                    clearInterval(pollInterval);
                    console.log('âœ… Background transfer completed for:', item.file.name);
                    
                } else if (result.status === 'failed') {
                    item.status = 'failed';
                    item.error = result.error || 'Transfer failed';
                    updateBulkItemUI(item);
                    clearInterval(pollInterval);
                    console.error('âŒ Background transfer failed for:', item.file.name, result.error);
                    
                } else if (result.status === 'transferring') {
                    // Update progress if available
                    if (result.progress) {
                        item.progress = result.progress;
                        updateBulkProgress(item);
                    }
                }
            }
        } catch (error) {
            console.error('Error polling transfer status:', error);
            // Don't clear interval on network errors, keep trying
        }
    }, 5000); // Poll every 5 seconds
    
    // Set a maximum polling time (30 minutes)
    setTimeout(() => {
        clearInterval(pollInterval);
        if (item.status === 'transferring') {
            item.status = 'timeout';
            item.error = 'Transfer timeout - please check manually';
            updateBulkItemUI(item);
        }
    }, 30 * 60 * 1000);
}

async function initiateDriveBackgroundTransfer(fileId, metadata = {}) {
    console.log('ðŸš€ Initiating background Google Drive to R2 transfer for file:', fileId);
    
    if (!accessToken) {
        console.error('âŒ No access token available for Google Drive');
        throw new Error('Google Drive access token not available');
    }
    
    try {
        // Get file metadata from Google Drive
        const fileInfo = await getGoogleDriveFileInfo(fileId);
        console.log('ðŸ“ Google Drive file info:', fileInfo);
        
        // Prepare the background transfer request
        const transferData = {
            fileId: fileId,
            accessToken: accessToken,
            fileName: fileInfo.name,
            fileSize: parseInt(fileInfo.size),
            mimeType: fileInfo.mimeType,
            bucketName: 'ions-r2', // Your R2 bucket name
            metadata: metadata
        };
        
        // Start the background transfer using the Cloudflare Worker
        const response = await fetch('./ionuploadvideos.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(transferData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            console.log('âœ… Background transfer initiated successfully');
            return {
                transferId: result.transferId,
                status: 'transferring',
                message: 'File is being transferred to R2 in the background'
            };
        } else {
            throw new Error(result.error || 'Failed to initiate background transfer');
        }
        
    } catch (error) {
        console.error('âŒ Background transfer initiation failed:', error);
        // Fallback to the old method
        console.log('ðŸ”„ Falling back to direct upload method');
        return await uploadGoogleDriveFile();
    }
}

// Google Drive upload function (from working old version)
function uploadGoogleDriveFile() {
    if (!selectedGoogleDriveFile) {
        console.error('âŒ No Google Drive file selected');
        showCustomAlert('Error', 'Please select a Google Drive file first.');
        return;
    }
    
    console.log('âœ… Starting Google Drive upload process');
    showProgress('Importing from Google Drive...');
    
    const title = document.getElementById('videoTitle').value.trim() || selectedGoogleDriveFile.name.replace(/\.[^/.]+$/, '');
    const description = document.getElementById('videoDescription').value.trim();
    const category = document.getElementById('videoCategory').value;
    const tags = document.getElementById('videoTags').value.trim();
    const visibility = document.getElementById('videoVisibility').value;
    
    const formData = new FormData();
    formData.append('action', 'import_google_drive');
    formData.append('google_drive_file_id', selectedGoogleDriveFile.id);
    formData.append('google_drive_access_token', accessToken);
    formData.append('title', title);
    formData.append('description', description);
    formData.append('category', category);
    formData.append('tags', tags);
    formData.append('visibility', visibility);
    formData.append('source', 'googledrive');
    formData.append('video_id', selectedGoogleDriveFile.id);
    formData.append('video_link', `https://drive.google.com/file/d/${selectedGoogleDriveFile.id}/view`);
    
    submitGoogleDriveUpload(formData);
}

async function submitGoogleDriveUpload(formData) {
    try {
        const response = await fetch('./ionuploadvideos.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const result = await response.json();
        
        if (result.success) {
            showCustomAlert('Success', result.message || 'Google Drive upload completed successfully!');
            // Update the video status in the bulk list
            const videoIndex = bulkVideos.findIndex(video => video.id === result.video_id);
            if (videoIndex !== -1) {
                bulkVideos[videoIndex].status = 'completed';
                updateBulkItemUI(bulkVideos[videoIndex]);
            }
        } else {
            showCustomAlert('Upload Error', result.error || 'Google Drive upload failed');
        }
    } catch (error) {
        console.error('Google Drive upload error:', error);
        showCustomAlert('Upload Error', error.message || 'Google Drive upload failed');
    }
}

// For bulk: In processBulkQueue, for Drive items, call initiateDriveBackgroundTransfer with per-item metadata, track in item.status
// ============================================
// THUMBNAIL HANDLING
// ============================================
function generateThumbnails() {
    console.log('ðŸŽ¬ Starting thumbnail capture from video player');
    
    const playerContainer = document.getElementById('videoPlayerContainer');
    if (!playerContainer) {
        console.error('Video player container not found');
        showCustomAlert('Error', 'Video player not found. Please ensure a video is loaded.');
        return;
    }
    
    // For LOCAL video files, directly find the video element
    const video = playerContainer.querySelector('video');
    console.log('ðŸ” Video element found:', !!video);
    
    if (video) {
        if (video.readyState < 2) {
            showCustomAlert('Video Not Ready', 'Video is still loading. Please wait and try again.');
            return;
        }
        
        console.log('ðŸŽ¬ Local video found, capturing frame directly...');
        captureCurrentFrame(video);
        return;
    }
    
    // Only for platform imports (YouTube, Vimeo, etc.) - not local files
    console.log('ðŸ” No direct video element, checking if this is a platform import...');
    const iframe = playerContainer.querySelector('iframe');
    
    if (iframe) {
        console.log('ðŸ” Platform import detected (iframe), cannot capture frame from external players');
        showCustomAlert('Error', 'Cannot capture frame from external video players (YouTube, Vimeo, etc.). Please use a direct video upload for thumbnail capture.');
        return;
    }
    
    console.error('No video element found');
    showCustomAlert('Error', 'No video found to capture frame from.');
}

function captureIframeAsImage(iframe) {
    console.log('ðŸŽ¬ Attempting to capture iframe as image...');
    
    // Use html2canvas library if available, otherwise use a simpler approach
    if (typeof html2canvas !== 'undefined') {
        html2canvas(iframe).then(canvas => {
            canvas.toBlob((blob) => {
                if (blob) {
                    console.log('âœ… Iframe captured successfully using html2canvas');
                    handleCapturedThumbnail(blob);
                } else {
                    console.error('âŒ Failed to create blob from iframe capture');
                    showCustomAlert('Error', 'Failed to capture frame from video player.');
                }
            }, 'image/jpeg', 0.8);
        }).catch(error => {
            console.error('âŒ html2canvas failed:', error);
            showCustomAlert('Error', 'Cannot capture frame due to CORS restrictions. Try using a direct video file upload.');
        });
    } else {
        // Fallback: Create a canvas and try to draw the iframe (will likely fail due to CORS)
        console.log('ðŸ” html2canvas not available, trying manual canvas approach...');
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        
        canvas.width = iframe.offsetWidth || 640;
        canvas.height = iframe.offsetHeight || 360;
        
        // This will likely fail due to CORS, but worth trying
        try {
            ctx.drawImage(iframe, 0, 0, canvas.width, canvas.height);
            canvas.toBlob((blob) => {
                if (blob) {
                    console.log('âœ… Iframe captured successfully using manual approach');
                    handleCapturedThumbnail(blob);
                } else {
                    console.error('âŒ Failed to create blob from iframe');
                    showCustomAlert('Error', 'Cannot capture frame due to CORS restrictions.');
                }
            }, 'image/jpeg', 0.8);
        } catch (error) {
            console.error('âŒ Manual iframe capture failed:', error);
            showCustomAlert('Error', 'Cannot capture frame from cross-origin video player. The video is hosted on a different domain which blocks frame capture for security reasons.');
        }
    }
}

function handleCapturedThumbnail(blob) {
    // Store the blob for later upload
    window.capturedThumbnailBlob = blob;
    
    // Update the Capture Frame button to show success
    const captureBtn = document.getElementById('generateThumbnailBtn');
    if (captureBtn) {
        captureBtn.innerHTML = 'âœ… Thumbnail Captured';
        captureBtn.style.backgroundColor = '#10b981';
        captureBtn.style.color = 'white';
    }
    
    // Show preview
    const thumbnailContainer = document.getElementById('thumbnailContainer');
    if (thumbnailContainer) {
        const img = document.createElement('img');
        img.src = URL.createObjectURL(blob);
        img.style.maxWidth = '200px';
        img.style.maxHeight = '150px';
        img.style.borderRadius = '8px';
        
        thumbnailContainer.innerHTML = '';
        thumbnailContainer.appendChild(img);
    }
    
    showCustomAlert('Success', 'Thumbnail captured successfully! It will be used when you save the video.');
}

function captureFrameFromVideo(video) {
    try {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        
        canvas.width = video.videoWidth || video.clientWidth;
        canvas.height = video.videoHeight || video.clientHeight;
        
        console.log('ðŸŽ¬ Canvas dimensions:', canvas.width, 'x', canvas.height);
        
        // Draw the current frame
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        
        // Convert to blob
        canvas.toBlob((blob) => {
            if (blob) {
                console.log('âœ… Thumbnail captured successfully from video element');
                handleCapturedThumbnail(blob);
            } else {
                console.error('Failed to create thumbnail blob');
                showCustomAlert('Error', 'Failed to capture thumbnail. Please try again.');
            }
        }, 'image/jpeg', 0.8);
        
    } catch (error) {
        console.error('Error capturing thumbnail:', error);
        showCustomAlert('Error', 'Failed to capture thumbnail: ' + error.message);
    }
}

// Automatically load YouTube thumbnail preview
function loadYouTubeThumbnailPreview(videoData) {
    console.log('ðŸ–¼ï¸ Auto-loading YouTube thumbnail for video ID:', videoData.video_id);
    
    if (!videoData.video_id) {
        console.warn('No YouTube video ID found for thumbnail preview');
        return;
    }
    
    // Try to load the default thumbnail (medium quality)
    const thumbnailUrl = `https://i.ytimg.com/vi/${videoData.video_id}/mqdefault.jpg`;
    
    const img = new Image();
    img.crossOrigin = 'anonymous';
    
    img.onload = function() {
        console.log('âœ… YouTube thumbnail loaded successfully for preview');
        
        // Display the thumbnail in the thumbnail preview section
        const thumbnailPreview = document.getElementById('thumbnailPreview');
        if (thumbnailPreview) {
            thumbnailPreview.src = thumbnailUrl;
            thumbnailPreview.style.display = 'block';
            
            // Hide the placeholder
            const placeholder = document.getElementById('thumbnailPlaceholder');
            if (placeholder) {
                placeholder.style.display = 'none';
            }
            
            console.log('ðŸ–¼ï¸ YouTube thumbnail displayed in preview');
        }
    };
    
    img.onerror = function() {
        console.warn('âš ï¸ Failed to load YouTube thumbnail for preview, trying high quality...');
        
        // Try high quality thumbnail as fallback
        const hqThumbnailUrl = `https://i.ytimg.com/vi/${videoData.video_id}/hqdefault.jpg`;
        
        const hqImg = new Image();
        hqImg.crossOrigin = 'anonymous';
        
        hqImg.onload = function() {
            console.log('âœ… YouTube HQ thumbnail loaded successfully for preview');
            
            const thumbnailPreview = document.getElementById('thumbnailPreview');
            if (thumbnailPreview) {
                thumbnailPreview.src = hqThumbnailUrl;
                thumbnailPreview.style.display = 'block';
                
                const placeholder = document.getElementById('thumbnailPlaceholder');
                if (placeholder) {
                    placeholder.style.display = 'none';
                }
            }
        };
        
        hqImg.onerror = function() {
            console.warn('âš ï¸ Could not load any YouTube thumbnail for preview');
        };
        
        hqImg.src = hqThumbnailUrl;
    };
    
    img.src = thumbnailUrl;
}

// Automatically load Vimeo thumbnail preview
function loadVimeoThumbnailPreview(videoData) {
    console.log('ðŸ–¼ï¸ Auto-loading Vimeo thumbnail for video ID:', videoData.video_id);
    
    if (!videoData.video_id) {
        console.warn('No Vimeo video ID found for thumbnail preview');
        return;
    }
    
    // Use vumbnail.com service for Vimeo thumbnails
    const thumbnailUrl = `https://vumbnail.com/${videoData.video_id}.jpg`;
    
    const img = new Image();
    img.crossOrigin = 'anonymous';
    
    img.onload = function() {
        console.log('âœ… Vimeo thumbnail loaded successfully for preview');
        
        // Display the thumbnail in the thumbnail preview section
        const thumbnailPreview = document.getElementById('thumbnailPreview');
        if (thumbnailPreview) {
            thumbnailPreview.src = thumbnailUrl;
            thumbnailPreview.style.display = 'block';
            
            // Hide the placeholder
            const placeholder = document.getElementById('thumbnailPlaceholder');
            if (placeholder) {
                placeholder.style.display = 'none';
            }
            
            console.log('ðŸ–¼ï¸ Vimeo thumbnail displayed in preview');
        }
    };
    
    img.onerror = function() {
        console.warn('âš ï¸ Could not load Vimeo thumbnail for preview');
    };
    
    img.src = thumbnailUrl;
}

// YouTube thumbnail capture using YouTube's thumbnail API
function captureYouTubeThumbnail(videoData) {
    console.log('ðŸ“· Capturing YouTube thumbnail for video ID:', videoData.video_id);
    
    if (!videoData.video_id) {
        showCustomAlert('Error', 'YouTube video ID not found. Please check the URL.');
        return;
    }
    
    // YouTube provides multiple thumbnail sizes
    const thumbnailOptions = [
        {
            url: `https://i.ytimg.com/vi/${videoData.video_id}/maxresdefault.jpg`,
            name: 'High Quality (1280x720)',
            quality: 'maxres'
        },
        {
            url: `https://i.ytimg.com/vi/${videoData.video_id}/hqdefault.jpg`,
            name: 'Medium Quality (480x360)',
            quality: 'hq'
        },
        {
            url: `https://i.ytimg.com/vi/${videoData.video_id}/mqdefault.jpg`,
            name: 'Standard Quality (320x180)',
            quality: 'mq'
        }
    ];
    
    // Try to load the highest quality thumbnail first
    loadYouTubeThumbnail(thumbnailOptions, 0);
}

function loadYouTubeThumbnail(thumbnailOptions, index) {
    if (index >= thumbnailOptions.length) {
        showCustomAlert('Error', 'Could not load YouTube thumbnail. Please try again.');
        return;
    }
    
    const option = thumbnailOptions[index];
    console.log('ðŸ“· Trying YouTube thumbnail:', option.name, option.url);
    
    const img = new Image();
    img.crossOrigin = 'anonymous';
    
    img.onload = function() {
        console.log('âœ… YouTube thumbnail loaded successfully:', option.name);
        
        // Create canvas to convert image to blob
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        
        canvas.width = img.width;
        canvas.height = img.height;
        
        ctx.drawImage(img, 0, 0);
        
        canvas.toBlob((blob) => {
            if (blob) {
                applyThumbnailFromBlob(blob, `YouTube thumbnail (${option.name})`);
            } else {
                showCustomAlert('Error', 'Failed to process YouTube thumbnail.');
            }
        }, 'image/jpeg', 0.8);
    };
    
    img.onerror = function() {
        console.warn('âš ï¸ YouTube thumbnail failed:', option.name, 'trying next option...');
        // Try next quality option
        loadYouTubeThumbnail(thumbnailOptions, index + 1);
    };
    
    img.src = option.url;
}

// Vimeo thumbnail capture using Vimeo's API
function captureVimeoThumbnail(videoData) {
    console.log('ðŸ“· Capturing Vimeo thumbnail for video ID:', videoData.video_id);
    
    if (!videoData.video_id) {
        showCustomAlert('Error', 'Vimeo video ID not found. Please check the URL.');
        return;
    }
    
    // Use vumbnail.com service for Vimeo thumbnails
    const thumbnailUrl = `https://vumbnail.com/${videoData.video_id}.jpg`;
    
    console.log('ðŸ“· Loading Vimeo thumbnail from:', thumbnailUrl);
    
    const img = new Image();
    img.crossOrigin = 'anonymous';
    
    img.onload = function() {
        console.log('âœ… Vimeo thumbnail loaded successfully');
        
        // Create canvas to convert image to blob
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        
        canvas.width = img.width;
        canvas.height = img.height;
        
        ctx.drawImage(img, 0, 0);
        
        canvas.toBlob((blob) => {
            if (blob) {
                applyThumbnailFromBlob(blob, 'Vimeo thumbnail');
            } else {
                showCustomAlert('Error', 'Failed to process Vimeo thumbnail.');
            }
        }, 'image/jpeg', 0.8);
    };
    
    img.onerror = function() {
        console.error('âŒ Failed to load Vimeo thumbnail');
        showCustomAlert('Error', 'Could not load Vimeo thumbnail. The video may be private or the URL may be incorrect.');
    };
    
    img.src = thumbnailUrl;
}

// Loom thumbnail capture using Loom's thumbnail API
function captureLoomThumbnail(videoData) {
    console.log('ðŸ“· Capturing Loom thumbnail for video ID:', videoData.video_id);
    
    if (!videoData.video_id) {
        showCustomAlert('Error', 'Loom video ID not found. Please check the URL.');
        return;
    }
    
    // Loom provides thumbnails at a predictable URL pattern
    const thumbnailUrl = `https://cdn.loom.com/sessions/thumbnails/${videoData.video_id}-00001.jpg`;
    
    console.log('ðŸ“· Loading Loom thumbnail from:', thumbnailUrl);
    
    const img = new Image();
    img.crossOrigin = 'anonymous';
    
    img.onload = function() {
        console.log('âœ… Loom thumbnail loaded successfully');
        
        // Create canvas to convert image to blob
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        
        canvas.width = img.width;
        canvas.height = img.height;
        
        ctx.drawImage(img, 0, 0);
        
        canvas.toBlob((blob) => {
            if (blob) {
                applyThumbnailFromBlob(blob, 'Loom thumbnail');
            } else {
                showCustomAlert('Error', 'Failed to process Loom thumbnail.');
            }
        }, 'image/jpeg', 0.8);
    };
    
    img.onerror = function() {
        console.warn('âš ï¸ Loom thumbnail failed, trying alternative URL...');
        // Try alternative thumbnail URL pattern
        const altThumbnailUrl = `https://cdn.loom.com/sessions/thumbnails/${videoData.video_id}-thumb.jpg`;
        
        const altImg = new Image();
        altImg.crossOrigin = 'anonymous';
        
        altImg.onload = function() {
            console.log('âœ… Loom alternative thumbnail loaded successfully');
            
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            
            canvas.width = altImg.width;
            canvas.height = altImg.height;
            
            ctx.drawImage(altImg, 0, 0);
            
            canvas.toBlob((blob) => {
                if (blob) {
                    applyThumbnailFromBlob(blob, 'Loom thumbnail (alternative)');
                } else {
                    showCustomAlert('Error', 'Failed to process Loom thumbnail.');
                }
            }, 'image/jpeg', 0.8);
        };
        
        altImg.onerror = function() {
            console.error('âŒ Failed to load Loom thumbnail from both URLs');
            showCustomAlert('Error', 'Could not load Loom thumbnail. The video may be private or the URL may be incorrect.');
        };
        
        altImg.src = altThumbnailUrl;
    };
    
    img.src = thumbnailUrl;
}

// Muvi thumbnail capture - Muvi doesn't provide a public thumbnail API
// so we'll show a message that manual thumbnail upload is recommended
function captureMuviThumbnail(videoData) {
    console.log('ðŸ“· Muvi thumbnail capture requested for video ID:', videoData.video_id);
    
    if (!videoData.video_id) {
        showCustomAlert('Error', 'Muvi video ID not found. Please check the URL.');
        return;
    }
    
    // Muvi doesn't provide a public thumbnail API like YouTube or Vimeo
    // Show a helpful message to the user
    showCustomAlert(
        'Muvi Thumbnail', 
        'Muvi videos don\'t support automatic thumbnail capture. Please use the "Upload Custom" button to add a thumbnail for your video.',
        [{
            text: 'Upload Custom Thumbnail',
            className: 'btn btn-primary',
            onClick: () => {
                const uploadBtn = document.getElementById('uploadThumbnailBtn');
                if (uploadBtn) {
                    uploadBtn.click();
                }
            }
        }]
    );
}

// Helper function to apply thumbnail from blob
function applyThumbnailFromBlob(blob, source) {
    console.log('âœ… Applying thumbnail from', source, 'size:', blob.size, 'bytes');
    
    // Create object URL for preview
    const thumbnailUrl = URL.createObjectURL(blob);
    
    // Update thumbnail preview
    const preview = document.getElementById('thumbnailPreview');
    const placeholder = document.getElementById('thumbnailPlaceholder');
    
    if (preview && placeholder) {
        preview.src = thumbnailUrl;
        preview.style.display = 'block';
        placeholder.style.display = 'none';
        
        // Store the blob for later upload
        window.capturedThumbnailBlob = blob;
        
        // Update the Capture Frame button to show success
        const captureBtn = document.getElementById('generateThumbnailBtn');
        if (captureBtn) {
            captureBtn.innerHTML = 'âœ… Thumbnail Captured';
            captureBtn.style.backgroundColor = '#10b981';
            captureBtn.style.color = 'white';
            captureBtn.title = 'Click again to capture a different thumbnail';
        }
        
        console.log('âœ… Thumbnail preview updated');
        showCustomAlert('Success', `Thumbnail captured from ${source}! The thumbnail will be saved when you save the video.`);
    } else {
        console.error('Thumbnail preview elements not found');
        showCustomAlert('Error', 'Could not update thumbnail preview.');
    }
}
function captureCurrentFrame(videoElement) {
    try {
        console.log('ðŸ“· Capturing frame at time:', videoElement.currentTime);
        
        // Create canvas to capture the frame
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        
        // Set canvas dimensions to match video
        canvas.width = videoElement.videoWidth || 640;
        canvas.height = videoElement.videoHeight || 360;
        
        console.log('ðŸ“· Canvas dimensions:', canvas.width, 'x', canvas.height);
        
        // Draw current video frame to canvas
        ctx.drawImage(videoElement, 0, 0, canvas.width, canvas.height);
        
        // Convert canvas to blob
        canvas.toBlob((blob) => {
            if (!blob) {
                console.error('Failed to create thumbnail blob');
                showCustomAlert('Error', 'Failed to capture thumbnail. Please try again.');
                return;
            }
            
            console.log('âœ… Thumbnail captured successfully, size:', blob.size, 'bytes');
            
            // Create object URL for preview
            const thumbnailUrl = URL.createObjectURL(blob);
            
            // Update thumbnail preview
            const preview = document.getElementById('thumbnailPreview');
            const placeholder = document.getElementById('thumbnailPlaceholder');
            
            if (preview && placeholder) {
                preview.src = thumbnailUrl;
                preview.style.display = 'block';
                placeholder.style.display = 'none';
                
                // Store the blob for later upload
                window.capturedThumbnailBlob = blob;
                
                // Update the Capture Frame button to show success
                const captureBtn = document.getElementById('generateThumbnailBtn');
                if (captureBtn) {
                    captureBtn.innerHTML = 'âœ… Frame Captured';
                    captureBtn.style.backgroundColor = '#10b981';
                    captureBtn.style.color = 'white';
                    captureBtn.title = 'Click again to capture a different frame';
                }
                
                console.log('âœ… Thumbnail preview updated');
            } else {
                console.error('Thumbnail preview elements not found');
                showCustomAlert('Error', 'Could not update thumbnail preview.');
            }
            
        }, 'image/jpeg', 0.8);
        
    } catch (error) {
        console.error('âŒ Error capturing frame:', error);
        showCustomAlert('Error', 'Failed to capture thumbnail: ' + error.message);
    }
}

// Function to reset the capture button state
function resetCaptureButtonState() {
    const captureBtn = document.getElementById('generateThumbnailBtn');
    if (captureBtn) {
        captureBtn.innerHTML = 'ðŸŽ¬ Capture Frame';
        captureBtn.style.backgroundColor = '';
        captureBtn.style.color = '';
    }
    
    // Clear the stored thumbnail blob
    if (window.capturedThumbnailBlob) {
        URL.revokeObjectURL(window.capturedThumbnailBlob);
        delete window.capturedThumbnailBlob;
    }
}

function showThumbnailOptions(thumbnails) {
    const dialog = document.getElementById('thumbnailDialog');
    const options = document.getElementById('thumbnailOptions');
    options.innerHTML = '';
    thumbnails.forEach((thumb, index) => {
        const img = document.createElement('img');
        img.src = thumb.url;
        img.onclick = () => selectThumbnail(thumb);
        options.appendChild(img);
    });
    dialog.style.display = 'flex';
}

function selectThumbnail(thumb) {
    const preview = document.getElementById('thumbnailPreview');
    preview.src = thumb.url;
    // Store for upload
}

function applySelectedThumbnail() {
    // Upload selected thumb to server
    closeThumbnailDialog();
}

function closeThumbnailDialog() {
    const dialog = document.getElementById('thumbnailDialog');
    if (dialog) {
        dialog.style.display = 'none';
        console.log('âœ… Thumbnail dialog closed');
    } else {
        console.error('âŒ Thumbnail dialog element not found');
    }
}

function showThumbnailUploadDialog() {
    document.getElementById('thumbnailInput').click();
}

// Handle custom thumbnail file upload
function handleThumbnailUpload(event) {
    const file = event.target.files[0];
    if (!file) return;
    
    console.log('ðŸ–¼ï¸ Processing custom thumbnail upload:', file.name);
    
    // Validate file type
    if (!file.type.startsWith('image/')) {
        showCustomAlert('Invalid File', 'Please select an image file for the thumbnail.');
        return;
    }
    
    // Validate file size (max 5MB)
    const maxSize = 5 * 1024 * 1024; // 5MB
    if (file.size > maxSize) {
        showCustomAlert('File Too Large', 'Thumbnail image must be smaller than 5MB.');
        return;
    }
    
    // Create object URL for preview
    const imageUrl = URL.createObjectURL(file);
    
    // Display the thumbnail preview
    const thumbnailPreview = document.getElementById('thumbnailPreview');
    const thumbnailPlaceholder = document.getElementById('thumbnailPlaceholder');
    
    if (thumbnailPreview) {
        thumbnailPreview.src = imageUrl;
        thumbnailPreview.style.display = 'block';
        
        // Hide placeholder
        if (thumbnailPlaceholder) {
            thumbnailPlaceholder.style.display = 'none';
        }
        
        console.log('âœ… Custom thumbnail preview loaded successfully');
        
        // Store the file for later upload
        window.customThumbnailFile = file;
        window.customThumbnailUrl = imageUrl;
        
        // Show success message
        showCustomAlert('Thumbnail Updated', 'Custom thumbnail has been set. It will be uploaded with your media.');
    }
    
    // Clear the input so the same file can be selected again if needed
    event.target.value = '';
}


function showThumbnailUrlDialog() {
    document.getElementById('thumbnailUrlOverlay').style.display = 'flex';
}

function closeThumbnailUrlDialog() {
    document.getElementById('thumbnailUrlOverlay').style.display = 'none';
}

function fetchThumbnailFromUrl() {
    const url = document.getElementById('thumbnailUrlInput').value;
    if (window.IONUploadAdvanced && window.IONUploadAdvanced.Validators && window.IONUploadAdvanced.Validators.url()(url) === null) {
        const processor = new window.IONUploadAdvanced.VideoProcessor();
        processor.generateThumbnailFromUrl(url).then(thumb => {
            const preview = document.getElementById('thumbnailPreview');
            preview.src = thumb.url;
            closeThumbnailUrlDialog();
        }).catch(err => showCustomAlert('Error', 'Failed to fetch thumbnail.'));
    } else {
        showCustomAlert('Invalid URL', 'Please enter a valid image URL.');
    }
}

// Helper function to close bulk upload and return to creators page
function closeBulkUpload() {
    // Send message to parent window to close modal and refresh
    if (window.parent && window.parent !== window) {
        window.parent.postMessage({
            type: 'bulk_upload_complete',
            message: 'Bulk upload completed successfully'
        }, '*');
    }
}

// Helper function to show bulk upload summary
function showBulkSummary(errors) {
    const bulkProgress = document.getElementById('bulkProgress');
    const bulkSummary = document.getElementById('bulkSummary');
    const successCount = document.getElementById('successCount');
    const errorCount = document.getElementById('errorCount');
    
    if (bulkProgress) bulkProgress.style.display = 'none';
    if (bulkSummary) bulkSummary.style.display = 'block';
    
    const totalFiles = uploadQueue.length;
    const failedFiles = errors.length;
    const successFiles = totalFiles - failedFiles;
    
    if (successCount) successCount.textContent = `${successFiles} successful`;
    if (errorCount) errorCount.textContent = `${failedFiles} failed`;
    
    console.log(`ðŸ“Š Bulk upload summary: ${successFiles} successful, ${failedFiles} failed`);
}

// ============================================
// ENHANCED THUMBNAIL GENERATION SYSTEM
// Consolidates: enhanced-stream-thumbnails.php + enhanced-video-thumbs.php + upload-video-thumbs.php
// ============================================

class IONThumbnailGenerator {
    constructor() {
        this.maxWaitTime = 120; // 2 minutes max wait for Stream processing
        this.retryDelay = 5000; // 5 seconds between retries
        this.thumbnailCache = new Map();
    }

    /**
     * Generate thumbnail for video file (immediate local generation)
     */
    async generateVideoThumbnail(file, timestampPercent = 10) {
        return new Promise((resolve, reject) => {
            const video = document.createElement('video');
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            
            video.onloadedmetadata = () => {
                canvas.width = 320;
                canvas.height = 180;
                const seekTime = (video.duration * timestampPercent) / 100;
                video.currentTime = seekTime;
            };
            
            video.onseeked = () => {
                try {
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                    canvas.toBlob((blob) => {
                        if (blob) {
                            const thumbnailUrl = URL.createObjectURL(blob);
                            console.log(`✅ Generated video thumbnail at ${timestampPercent}%`);
                            resolve({
                                url: thumbnailUrl,
                                blob: blob,
                                width: canvas.width,
                                height: canvas.height
                            });
                        } else {
                            reject(new Error('Failed to generate thumbnail blob'));
                        }
                    }, 'image/jpeg', 0.8);
                } catch (error) {
                    reject(error);
                }
            };
            
            video.onerror = () => reject(new Error('Failed to load video for thumbnail'));
            video.src = URL.createObjectURL(file);
            video.load();
        });
    }

    /**
     * Generate thumbnail for image file
     */
    async generateImageThumbnail(file, maxWidth = 320, maxHeight = 180) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            
            img.onload = () => {
                let { width, height } = this.calculateThumbnailDimensions(
                    img.width, img.height, maxWidth, maxHeight
                );
                
                canvas.width = width;
                canvas.height = height;
                ctx.drawImage(img, 0, 0, width, height);
                
                canvas.toBlob((blob) => {
                    if (blob) {
                        const thumbnailUrl = URL.createObjectURL(blob);
                        console.log(`✅ Generated image thumbnail ${width}x${height}`);
                        resolve({
                            url: thumbnailUrl,
                            blob: blob,
                            width: width,
                            height: height
                        });
                    } else {
                        reject(new Error('Failed to generate image thumbnail'));
                    }
                }, 'image/jpeg', 0.8);
            };
            
            img.onerror = () => reject(new Error('Failed to load image for thumbnail'));
            img.src = URL.createObjectURL(file);
        });
    }

    /**
     * Calculate thumbnail dimensions maintaining aspect ratio
     */
    calculateThumbnailDimensions(originalWidth, originalHeight, maxWidth, maxHeight) {
        let width = originalWidth;
        let height = originalHeight;
        
        if (width > maxWidth) {
            height = (height * maxWidth) / width;
            width = maxWidth;
        }
        
        if (height > maxHeight) {
            width = (width * maxHeight) / height;
            height = maxHeight;
        }
        
        return { width: Math.round(width), height: Math.round(height) };
    }

    /**
     * Batch generate thumbnails for multiple files
     */
    async generateBatchThumbnails(files, onProgress = null) {
        const results = [];
        const batchSize = 3; // Process 3 at a time
        
        for (let i = 0; i < files.length; i += batchSize) {
            const batch = files.slice(i, i + batchSize);
            const batchPromises = batch.map(async (file, index) => {
                try {
                    let thumbnail = null;
                    
                    if (file.type.startsWith('video/')) {
                        thumbnail = await this.generateVideoThumbnail(file);
                    } else if (file.type.startsWith('image/')) {
                        thumbnail = await this.generateImageThumbnail(file);
                    }
                    
                    if (onProgress) {
                        onProgress(i + index + 1, files.length, file.name);
                    }
                    
                    return { file, thumbnail, success: true };
                    
                } catch (error) {
                    console.error(`Failed to generate thumbnail for ${file.name}:`, error);
                    
                    if (onProgress) {
                        onProgress(i + index + 1, files.length, file.name, error);
                    }
                    
                    return { file, thumbnail: null, success: false, error };
                }
            });
            
            const batchResults = await Promise.all(batchPromises);
            results.push(...batchResults);
            
            // Small delay between batches
            if (i + batchSize < files.length) {
                await new Promise(resolve => setTimeout(resolve, 100));
            }
        }
        
        return results;
    }

    /**
     * Clean up thumbnail URLs to prevent memory leaks
     */
    cleanupThumbnails(thumbnailUrls) {
        thumbnailUrls.forEach(url => {
            if (url && url.startsWith('blob:')) {
                URL.revokeObjectURL(url);
            }
        });
    }
}

// Global thumbnail generator instance
window.IONThumbnailGenerator = new IONThumbnailGenerator();
