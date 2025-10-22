// ION Uploader Pro - Platform Import + Google Drive Integration + R2 Multipart Upload
// This file provides Pro features for ionuploader.js

// ============================================
// R2 MULTIPART UPLOADER CLASS
// ============================================

/**
 * Direct R2 Multipart Uploader
 * Uploads large files directly to Cloudflare R2 using presigned URLs
 */
class R2MultipartUploader {
    constructor(options = {}) {
        this.partSize = options.partSize || 100 * 1024 * 1024; // 100MB default
        this.maxConcurrentUploads = options.maxConcurrentUploads || 3;
        this.maxRetries = options.maxRetries || 3;
        this.retryDelay = options.retryDelay || 1000; // 1 second
        this.endpoint = options.endpoint || './ionuploadermultipart.php';
        
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

            const multipartThreshold = 100 * 1024 * 1024; // 100MB
            
            if (file.size <= multipartThreshold) {
                return await this.regularUpload(file, metadata);
            }

            console.log(`🚀 Starting R2 multipart upload for ${this.formatFileSize(file.size)} file`);
            
            // Show initial progress immediately
            this.onProgress({
                loaded: 0,
                total: file.size,
                percentage: 0,
                speed: 0,
                timeRemaining: 0
            });
            
            this.parts = this.createParts(file);
            console.log(`📦 Created ${this.parts.length} parts of ${this.formatFileSize(this.partSize)} each`);

            await this.initializeMultipartUpload(file, metadata);
            console.log(`✅ Initialized multipart upload: ${this.uploadId}`);

            const presignedUrls = await this.getPresignedUrls();
            console.log(`🔗 Generated ${presignedUrls.length} presigned URLs`);

            await this.uploadParts(presignedUrls);
            console.log(`⬆️ All parts uploaded successfully`);

            const result = await this.completeMultipartUpload();
            console.log(`🎉 Upload completed: Video ID ${result.video_id}`);

            this.onSuccess(result);
            return result;

        } catch (error) {
            console.error('❌ Upload failed:', error);
            this.onError(error);
            
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
        console.log(`📤 Using regular upload for ${this.formatFileSize(file.size)} file`);
        
        const formData = new FormData();
        formData.append('action', 'upload');
        formData.append('video', file);
        
        Object.keys(metadata).forEach(key => {
            if (key !== 'thumbnail' && metadata[key]) {
                formData.append(key, metadata[key]);
            }
        });

        if (metadata.thumbnail) {
            formData.append('thumbnail', metadata.thumbnail);
        }

        const response = await fetch('./ionuploadvideos.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            signal: this.abortController.signal
        });

        if (!response.ok) {
            throw new Error(`Upload failed: ${response.status}`);
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
                partNumber: i + 1,
                start: start,
                end: end,
                size: end - start,
                blob: file.slice(start, end)
            });
        }
        
        return parts;
    }

    async initializeMultipartUpload(file, metadata) {
        const formData = new FormData();
        formData.append('action', 'init');
        formData.append('fileName', file.name);
        formData.append('fileSize', file.size.toString());
        formData.append('contentType', file.type || 'video/mp4');
        
        formData.append('title', metadata.title || '');
        formData.append('description', metadata.description || '');
        formData.append('category', metadata.category || '');
        formData.append('visibility', metadata.visibility || 'public');
        formData.append('tags', metadata.tags || '');
        formData.append('badges', metadata.badges || '');

        const response = await fetch(this.endpoint, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            signal: this.abortController.signal
        });

        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'Failed to initialize multipart upload');
        }

        this.uploadId = result.uploadId;
        this.r2UploadId = result.r2UploadId;
        this.objectKey = result.objectKey;
        
        return result;
    }

    async getPresignedUrls() {
        const formData = new FormData();
        formData.append('action', 'get-presigned-urls');
        formData.append('uploadId', this.uploadId);
        formData.append('partCount', this.parts.length.toString());

        const response = await fetch(this.endpoint, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            signal: this.abortController.signal
        });

        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'Failed to get presigned URLs');
        }

        return result.presignedUrls;
    }

    async uploadParts(presignedUrls) {
        const uploadPromises = [];
        const semaphore = new Semaphore(this.maxConcurrentUploads);
        
        for (let i = 0; i < this.parts.length; i++) {
            const part = this.parts[i];
            const presignedUrl = presignedUrls.find(url => url.partNumber === part.partNumber);
            
            if (!presignedUrl) {
                throw new Error(`Missing presigned URL for part ${part.partNumber}`);
            }
            
            uploadPromises.push(
                semaphore.acquire().then(async (release) => {
                    try {
                        return await this.uploadPart(part, presignedUrl.url);
                    } finally {
                        release();
                    }
                })
            );
        }
        
        await Promise.all(uploadPromises);
    }

    async uploadPart(part, presignedUrl, retryCount = 0) {
        try {
            if (this.isPaused) {
                await this.waitForResume();
            }

            console.log(`⬆️ Uploading part ${part.partNumber}/${this.parts.length} (${this.formatFileSize(part.size)})`);
            console.log(`🔗 Presigned URL for part ${part.partNumber}:`, presignedUrl);

            // CRITICAL FIX: Use XMLHttpRequest for real-time upload progress tracking
            const etag = await new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                
                // Track bytes uploaded for this part
                let partBytesUploaded = 0;
                
                xhr.upload.addEventListener('progress', (e) => {
                    if (e.lengthComputable) {
                        partBytesUploaded = e.loaded;
                        
                        // Calculate total bytes uploaded across all parts
                        const previousPartsBytes = this.completedParts.length * this.partSize;
                        const totalUploaded = previousPartsBytes + partBytesUploaded;
                        const percentage = (totalUploaded / this.file.size) * 100;
                        
                        // Update progress in real-time
                        this.onProgress({
                            loaded: totalUploaded,
                            total: this.file.size,
                            percentage: percentage,
                            speed: this.calculateSpeed(),
                            timeRemaining: this.estimateTimeRemaining()
                        });
                        
                        console.log(`📊 Part ${part.partNumber} progress: ${((e.loaded / e.total) * 100).toFixed(1)}% | Overall: ${percentage.toFixed(1)}%`);
                    }
                });
                
                xhr.addEventListener('load', () => {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        const etag = xhr.getResponseHeader('ETag');
                        if (!etag) {
                            reject(new Error(`Missing ETag for part ${part.partNumber}`));
                        } else {
                            resolve(etag);
                        }
                    } else {
                        console.error(`❌ Part ${part.partNumber} upload failed:`);
                        console.error(`   Status: ${xhr.status} ${xhr.statusText}`);
                        console.error(`   URL: ${presignedUrl}`);
                        console.error(`   Response: ${xhr.responseText}`);
                        reject(new Error(`Part ${part.partNumber} upload failed: ${xhr.status}`));
                    }
                });
                
                xhr.addEventListener('error', () => {
                    console.error(`❌ Network error uploading part ${part.partNumber}`);
                    reject(new Error(`Network error uploading part ${part.partNumber}`));
                });
                
                xhr.addEventListener('abort', () => {
                    reject(new Error(`Upload aborted for part ${part.partNumber}`));
                });
                
                xhr.open('PUT', presignedUrl);
                xhr.send(part.blob);
            });

            const completedPart = {
                PartNumber: part.partNumber,
                ETag: etag.replace(/"/g, '')
            };

            this.completedParts.push(completedPart);
            
            this.onPartProgress({
                partNumber: part.partNumber,
                totalParts: this.parts.length,
                completedParts: this.completedParts.length
            });

            console.log(`✅ Part ${part.partNumber} uploaded successfully (${((this.completedParts.length / this.parts.length) * 100).toFixed(1)}% complete)`);
            
            return completedPart;

        } catch (error) {
            if (retryCount < this.maxRetries && !this.abortController.signal.aborted) {
                console.warn(`⚠️ Retrying part ${part.partNumber} (attempt ${retryCount + 1}/${this.maxRetries})`);
                await this.delay(this.retryDelay * (retryCount + 1));
                return this.uploadPart(part, presignedUrl, retryCount + 1);
            }
            
            console.error(`❌ Part ${part.partNumber} failed after ${this.maxRetries} retries:`, error);
            throw error;
        }
    }

    async completeMultipartUpload() {
        this.completedParts.sort((a, b) => a.PartNumber - b.PartNumber);

        const formData = new FormData();
        formData.append('action', 'complete');
        formData.append('uploadId', this.uploadId);
        formData.append('parts', JSON.stringify(this.completedParts));

        const response = await fetch(this.endpoint, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            signal: this.abortController.signal
        });

        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'Failed to complete multipart upload');
        }

        return result;
    }

    async abortMultipartUpload() {
        if (!this.uploadId) return;

        try {
            const formData = new FormData();
            formData.append('action', 'abort');
            formData.append('uploadId', this.uploadId);

            await fetch(this.endpoint, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });
        } catch (error) {
            console.warn('Failed to abort multipart upload:', error);
        }
    }

    pause() {
        this.isPaused = true;
        console.log('⏸️ Upload paused');
    }

    resume() {
        this.isPaused = false;
        console.log('▶️ Upload resumed');
    }

    async cancel() {
        console.log('🛑 Cancelling upload');
        
        if (this.abortController) {
            this.abortController.abort();
        }
        
        await this.abortMultipartUpload();
        
        this.isUploading = false;
        this.isPaused = false;
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
        
        const elapsed = (Date.now() - this.startTime) / 1000;
        const uploaded = this.completedParts.length * this.partSize;
        return uploaded / elapsed;
    }

    estimateTimeRemaining() {
        const speed = this.calculateSpeed();
        if (speed === 0) return 0;
        
        const remaining = this.file.size - (this.completedParts.length * this.partSize);
        return remaining / speed;
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

/**
 * Semaphore for limiting concurrent uploads
 */
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

// Expose R2MultipartUploader globally
window.R2MultipartUploader = R2MultipartUploader;

// ============================================
// CHUNKED UPLOADER CLASS (Alternative to R2Multipart)
// ============================================

/**
 * Chunked File Uploader
 * Alternative upload method with smaller chunks (5MB)
 * Uses chunked-upload-handler.php backend
 */
class ChunkedUploader {
    constructor(options = {}) {
        this.chunkSize = options.chunkSize || 5 * 1024 * 1024; // 5MB default
        this.maxRetries = options.maxRetries || 3;
        this.retryDelay = options.retryDelay || 1000;
        this.endpoint = options.endpoint || './ionuploaderchunked.php';
        
        this.onProgress = options.onProgress || (() => {});
        this.onSuccess = options.onSuccess || (() => {});
        this.onError = options.onError || (() => {});
        this.onChunkProgress = options.onChunkProgress || (() => {});
        
        this.uploadId = null;
        this.file = null;
        this.chunks = [];
        this.uploadedChunks = new Set();
        this.isUploading = false;
        this.isPaused = false;
        this.abortController = null;
    }

    async upload(file, metadata = {}) {
        try {
            this.file = file;
            this.isUploading = true;
            this.isPaused = false;
            this.uploadedChunks.clear();
            this.abortController = new AbortController();

            const regularUploadLimit = 100 * 1024 * 1024; // 100MB
            
            if (file.size <= regularUploadLimit) {
                return await this.regularUpload(file, metadata);
            }

            this.chunks = this.createChunks(file);
            const initResult = await this.initializeUpload(file, metadata);
            this.uploadId = initResult.uploadId;

            await this.uploadChunks();
            const result = await this.completeUpload();
            
            this.onSuccess(result);
            return result;

        } catch (error) {
            this.onError(error);
            throw error;
        } finally {
            this.isUploading = false;
        }
    }

    async regularUpload(file, metadata) {
        const formData = new FormData();
        formData.append('action', 'upload');
        formData.append('video', file);
        
        Object.keys(metadata).forEach(key => {
            if (key !== 'thumbnail' && metadata[key]) {
                formData.append(key, metadata[key]);
            }
        });

        if (metadata.thumbnail) {
            formData.append('thumbnail', metadata.thumbnail);
        }

        const response = await fetch('./ionuploadvideos.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            signal: this.abortController.signal
        });

        if (!response.ok) {
            throw new Error(`Upload failed: ${response.status}`);
        }

        const result = await response.json();
        if (!result.success) {
            throw new Error(result.error || 'Upload failed');
        }

        return result;
    }

    createChunks(file) {
        const chunks = [];
        const totalChunks = Math.ceil(file.size / this.chunkSize);
        
        for (let i = 0; i < totalChunks; i++) {
            const start = i * this.chunkSize;
            const end = Math.min(start + this.chunkSize, file.size);
            chunks.push({
                index: i,
                start: start,
                end: end,
                blob: file.slice(start, end)
            });
        }
        
        return chunks;
    }

    async initializeUpload(file, metadata) {
        const formData = new FormData();
        formData.append('action', 'init');
        formData.append('fileName', file.name);
        formData.append('fileSize', file.size.toString());
        formData.append('chunkSize', this.chunkSize.toString());
        formData.append('totalChunks', this.chunks.length.toString());
        formData.append('title', metadata.title || '');
        formData.append('description', metadata.description || '');
        formData.append('category', metadata.category || '');
        formData.append('visibility', metadata.visibility || 'public');

        const response = await fetch(this.endpoint, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            signal: this.abortController.signal
        });

        const result = await response.json();
        if (!result.success) {
            throw new Error(result.error || 'Failed to initialize upload');
        }

        return result;
    }

    async uploadChunks() {
        const concurrentUploads = 3;
        const uploadPromises = [];
        
        for (let i = 0; i < this.chunks.length; i += concurrentUploads) {
            const batch = this.chunks.slice(i, i + concurrentUploads);
            
            for (const chunk of batch) {
                if (this.isPaused) {
                    await this.waitForResume();
                }
                uploadPromises.push(this.uploadChunk(chunk));
            }
            
            await Promise.all(uploadPromises.splice(0, batch.length));
        }
    }

    async uploadChunk(chunk, retryCount = 0) {
        try {
            const formData = new FormData();
            formData.append('action', 'chunk');
            formData.append('uploadId', this.uploadId);
            formData.append('chunkIndex', chunk.index.toString());
            formData.append('totalChunks', this.chunks.length.toString());
            formData.append('chunk', chunk.blob);

            const response = await fetch(this.endpoint, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                signal: this.abortController.signal
            });

            const result = await response.json();
            if (!result.success) {
                throw new Error(result.error || 'Chunk upload failed');
            }

            this.uploadedChunks.add(chunk.index);
            
            this.onChunkProgress({
                chunkIndex: chunk.index,
                totalChunks: this.chunks.length,
                uploadedChunks: this.uploadedChunks.size
            });
            
            this.onProgress({
                loaded: this.uploadedChunks.size * this.chunkSize,
                total: this.file.size,
                percentage: (this.uploadedChunks.size / this.chunks.length) * 100
            });

            return result;

        } catch (error) {
            if (retryCount < this.maxRetries && !this.abortController.signal.aborted) {
                await this.delay(this.retryDelay * (retryCount + 1));
                return this.uploadChunk(chunk, retryCount + 1);
            }
            throw error;
        }
    }

    async completeUpload() {
        const formData = new FormData();
        formData.append('action', 'complete');
        formData.append('uploadId', this.uploadId);

        const response = await fetch(this.endpoint, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            signal: this.abortController.signal
        });

        const result = await response.json();
        if (!result.success) {
            throw new Error(result.error || 'Failed to complete upload');
        }

        return result;
    }

    pause() {
        this.isPaused = true;
    }

    resume() {
        this.isPaused = false;
    }

    async cancel() {
        if (this.abortController) {
            this.abortController.abort();
        }
        
        if (this.uploadId) {
            try {
                const formData = new FormData();
                formData.append('action', 'cancel');
                formData.append('uploadId', this.uploadId);

                await fetch(this.endpoint, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });
            } catch (error) {
                console.warn('Failed to cancel upload session:', error);
            }
        }
        
        this.isUploading = false;
        this.isPaused = false;
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
}

// Expose ChunkedUploader globally
window.ChunkedUploader = ChunkedUploader;

// ============================================
// PLATFORM IMPORT FUNCTION
// ============================================

// Platform import function - called from ionuploader.js
window.processPlatformImport = async function(metadata) {
    console.log('🌐 [Pro] Processing platform import with metadata:', metadata);
    
    const urlInput = document.getElementById('urlInput');
    if (!urlInput) {
        console.error('❌ URL input element not found');
        alert('Upload Error: URL input not found.');
        return;
    }
    
    const url = urlInput.value.trim();
    console.log('🌐 [Pro] URL from input:', url);
    console.log('🌐 [Pro] Current source:', currentSource);
    
    if (!url) {
        console.error('❌ No URL provided');
        alert('Upload Error: Missing video URL for platform import.');
        return;
    }
    
    if (!currentSource) {
        console.error('❌ No source platform selected');
        alert('Upload Error: Please select a platform (YouTube, Vimeo, etc.)');
        return;
    }
    
    console.log('✅ URL validation passed, preparing import...');
    
    // Show progress
    const progressContainer = document.getElementById('uploadProgressContainer');
    if (progressContainer) {
        progressContainer.style.display = 'flex';
    }
    const progressText = document.getElementById('uploadStatusText');
    if (progressText) {
        progressText.textContent = 'Importing from ' + currentSource + '...';
    }
    
    // Prepare form data
    const formData = new FormData();
    formData.append('action', 'platform_import');
    formData.append('source', currentSource);
    formData.append('url', url);
    formData.append('title', metadata.title || '');
    formData.append('description', metadata.description || '');
    formData.append('category', metadata.category || 'General');
    formData.append('tags', (metadata.tags || []).join(','));
    formData.append('visibility', metadata.visibility || 'public');
    formData.append('badges', metadata.badges || '');
    
    // Add thumbnail if captured
    if (metadata.thumbnailBlob) {
        console.log('📦 Including captured thumbnail:', metadata.thumbnailBlob.size, 'bytes');
        formData.append('thumbnail', metadata.thumbnailBlob, 'thumbnail.jpg');
    }
    
    console.log('📦 Sending import request to server...');
    
    try {
        const response = await fetch('./ionuploadvideos.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        
        console.log('💾 Response status:', response.status);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const responseText = await response.text();
        console.log('💾 Raw response:', responseText.substring(0, 500));
        
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (parseError) {
            console.error('❌ JSON parse error:', parseError);
            throw new Error('Invalid JSON response from server');
        }
        
        console.log('📦 Parsed result:', result);
        
        if (result.success) {
            console.log('✅ Import successful!', result);
            
            // Hide progress
            if (progressContainer) {
                progressContainer.style.display = 'none';
            }
            
            // Call the main showUploadSuccess function from ionuploader.js
            if (typeof showUploadSuccess === 'function') {
                console.log('🎉 Calling showUploadSuccess with result:', result);
                showUploadSuccess(result);
            } else if (typeof window.showUploadSuccess === 'function') {
                console.log('🎉 Calling window.showUploadSuccess with result:', result);
                window.showUploadSuccess(result);
            } else {
                console.warn('⚠️ showUploadSuccess not found, waiting and retrying...');
                setTimeout(() => {
                    if (typeof showUploadSuccess === 'function') {
                        showUploadSuccess(result);
                    } else if (typeof window.showUploadSuccess === 'function') {
                        window.showUploadSuccess(result);
                    } else {
                        console.error('❌ showUploadSuccess still not found, using basic alert');
                        alert('Success! Video imported successfully.');
                        location.reload();
                    }
                }, 500);
            }
        } else {
            throw new Error(result.error || 'Import failed');
        }
    } catch (error) {
        console.error('❌ Platform import failed:', error);
        
        // Hide progress
        if (progressContainer) {
            progressContainer.style.display = 'none';
        }
        
        // Show error
        alert('Import Error: ' + error.message);
    }
};

// ============================================
// GOOGLE DRIVE INTEGRATION
// ============================================

// Google Drive configuration (should be set in ionuploader.php)
const CLIENT_ID = window.GOOGLE_CLIENT_ID || '';
const API_KEY = window.GOOGLE_API_KEY || '';
const SCOPES = 'https://www.googleapis.com/auth/drive.readonly';

// Global variables for Google Drive
let accessToken = null;
let tokenClient = null;
let pickerApiLoaded = false;

// LocalStorage functions for connections
function getStoredConnections() {
    try {
        const stored = localStorage.getItem('googleDriveConnections');
        return stored ? JSON.parse(stored) : [];
    } catch (e) {
        console.error('Error reading stored connections:', e);
        return [];
    }
}

function saveConnection(email, token) {
    const connections = getStoredConnections();
    const existing = connections.findIndex(c => c.email === email);
    
    if (existing >= 0) {
        connections[existing] = { email, token, timestamp: Date.now() };
    } else {
        connections.push({ email, token, timestamp: Date.now() });
    }
    
    localStorage.setItem('googleDriveConnections', JSON.stringify(connections));
    updateConnectedDrivesUI();
}

function updateConnectedDrivesUI() {
    const connectedDrives = document.getElementById('connectedDrives');
    if (!connectedDrives) return;
    
    const connections = getStoredConnections();
    console.log('🔄 Updating connected drives dropdown:', connections.length, 'connections');
    
    if (connections.length === 0) {
        connectedDrives.innerHTML = '<div class="dropdown-item no-drives" style="padding: 8px 12px; color: #888;">No drives connected</div>';
        const arrow = document.getElementById('googleDriveArrow');
        if (arrow) arrow.style.display = 'none';
        return;
    }
    
    let html = '';
    connections.forEach((conn, index) => {
        const initial = conn.email.charAt(0).toUpperCase();
        html += `
            <div class="dropdown-item drive-item" data-email="${conn.email}" style="padding: 8px 12px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                <div style="width: 24px; height: 24px; border-radius: 50%; background: linear-gradient(135deg, #4285f4, #34a853); color: white; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold;">${initial}</div>
                <span style="flex: 1; font-size: 13px;">${conn.email}</span>
            </div>
        `;
    });
    
    html += '<div class="dropdown-divider" style="margin: 4px 0; border-top: 1px solid #333;"></div>';
    html += '<div class="dropdown-item" data-action="add-drive" style="padding: 8px 12px; cursor: pointer; color: #3b82f6; font-size: 13px;">➕ Add New Drive</div>';
    html += '<div class="dropdown-item" data-action="clear-connections" style="padding: 8px 12px; cursor: pointer; color: #ef4444; font-size: 13px;">🗑️ Clear All</div>';
    
    connectedDrives.innerHTML = html;
    
    const arrow = document.getElementById('googleDriveArrow');
    if (arrow) arrow.style.display = connections.length > 0 ? 'inline-block' : 'none';
}

// Google Drive button click handler
function handleGoogleDriveButtonClick(event) {
    console.log('💾 Google Drive button clicked');
    event.stopPropagation();
    
    console.log('🔍 Google APIs available:', typeof google !== 'undefined' && typeof gapi !== 'undefined');
    console.log('🔍 CLIENT_ID:', CLIENT_ID);
    console.log('🔍 API_KEY:', API_KEY);
    
    const connections = getStoredConnections();
    console.log('🔍 Stored connections:', connections.length);
    
    if (connections.length > 0) {
        console.log('✅ Showing existing connections dropdown');
        showGoogleDriveDropdown();
    } else {
        console.log('➕ Adding new Google Drive connection');
        addNewGoogleDrive();
    }
}

let dropdownHideTimeout = null;

function showGoogleDriveDropdown() {
    const dropdown = document.getElementById('googleDriveDropdown');
    if (dropdown) {
        // Clear any pending hide timeout
        if (dropdownHideTimeout) {
            clearTimeout(dropdownHideTimeout);
            dropdownHideTimeout = null;
        }
        
        dropdown.style.display = 'block';
        console.log('✅ Google Drive dropdown shown');
        
        // Add mouseleave handler to hide dropdown with delay
        dropdown.onmouseleave = function() {
            dropdownHideTimeout = setTimeout(() => {
                hideGoogleDriveDropdown();
            }, 300); // 300ms delay before hiding
        };
        
        // Keep dropdown open on mouseenter
        dropdown.onmouseenter = function() {
            if (dropdownHideTimeout) {
                clearTimeout(dropdownHideTimeout);
                dropdownHideTimeout = null;
            }
        };
    }
}

function hideGoogleDriveDropdown() {
    const dropdown = document.getElementById('googleDriveDropdown');
    if (dropdown) {
        dropdown.style.display = 'none';
        console.log('✅ Google Drive dropdown hidden');
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

// Handle Google Drive dropdown item clicks
document.addEventListener('click', function(e) {
    const dropdownItem = e.target.closest('.google-drive-dropdown .dropdown-item');
    if (dropdownItem) {
        e.preventDefault();
        e.stopPropagation();
        const action = dropdownItem.getAttribute('data-action');
        console.log('🎯 Dropdown action clicked:', action);
        
        switch(action) {
            case 'add-drive':
            case 'switch-account':
                addNewGoogleDrive();
                break;
            case 'clear-connections':
                clearAllConnections();
                break;
            default:
                const driveEmail = dropdownItem.getAttribute('data-email');
                if (driveEmail) {
                    selectDrive(driveEmail);
                }
        }
    }
});

function selectDrive(email) {
    console.log('📂 Selecting drive:', email);
    const connections = getStoredConnections();
    const connection = connections.find(c => c.email === email);
    
    if (connection) {
        console.log('✅ Found connection for:', email);
        accessToken = connection.token;
        
        hideGoogleDriveDropdown();
        
        if (typeof showPicker === 'function') {
            showPicker();
        } else if (typeof showGoogleDrivePicker === 'function') {
            showGoogleDrivePicker();
        } else {
            console.error('❌ Picker function not available');
        }
    } else {
        console.error('❌ Connection not found for:', email);
    }
}

function clearAllConnections() {
    if (confirm('Are you sure you want to clear all Google Drive connections?')) {
        localStorage.removeItem('googleDriveConnections');
        console.log('🗑️ All Google Drive connections cleared');
        updateConnectedDrivesUI();
        hideGoogleDriveDropdown();
    }
}

function loadGoogleAPIs() {
    console.log('🔄 Loading Google APIs...');
    
    if (!CLIENT_ID || !API_KEY) {
        console.error('❌ Google Drive credentials missing!');
        alert('Google Drive integration is not configured. Please contact your administrator.');
        return;
    }

    if (typeof google !== 'undefined' && google.accounts && typeof gapi !== 'undefined' && google.picker) {
        console.log('✅ Google APIs already loaded, initializing auth...');
        initializeGoogleAuth();
        return;
    }
    
    window.googleApisLoading = true;
    
    const gisScript = document.createElement('script');
    gisScript.src = 'https://accounts.google.com/gsi/client';
    gisScript.async = true;
    gisScript.onload = () => {
        console.log('✅ Google Identity Services script loaded');
        
        const gapiScript = document.createElement('script');
        gapiScript.src = 'https://apis.google.com/js/api.js';
        gapiScript.async = true;
        gapiScript.onload = () => {
            console.log('✅ Google API script loaded, loading client:picker...');
            
            if (typeof gapi !== 'undefined') {
                gapi.load('client:picker', () => {
                    console.log('✅ Google API client and picker loaded, initializing auth...');
                    window.googleApisLoading = false;
                    initializeGoogleAuth();
                });
            } else {
                console.error('❌ gapi not defined after loading script');
                window.googleApisLoading = false;
            }
        };
        gapiScript.onerror = (error) => {
            console.error('❌ Failed to load Google API script:', error);
            window.googleApisLoading = false;
            alert('Failed to load Google API. Please check your internet connection.');
        };
        document.head.appendChild(gapiScript);
    };
    gisScript.onerror = (error) => {
        console.error('❌ Failed to load Google Identity Services script:', error);
        window.googleApisLoading = false;
        alert('Failed to load Google authentication. Please check your internet connection.');
    };
    document.head.appendChild(gisScript);
}

function initializeGoogleAuth() {
    console.log('🔐 Starting Google Auth initialization...');
    
    if (typeof gapi === 'undefined') {
        console.error('❌ gapi is not defined');
        return;
    }
    
    if (typeof google === 'undefined' || !google.accounts) {
        console.error('❌ google.accounts is not defined');
        return;
    }
    
    try {
        gapi.client.init({
            apiKey: API_KEY,
            discoveryDocs: ['https://www.googleapis.com/discovery/v1/apis/drive/v3/rest']
        }).then(() => {
            console.log('✅ GAPI client initialized');
            
            try {
                tokenClient = google.accounts.oauth2.initTokenClient({
                    client_id: CLIENT_ID,
                    scope: SCOPES,
                    callback: (response) => {
                        console.log('🎫 Token response received:', response);
                        if (response.access_token) {
                            accessToken = response.access_token;
                            
                            gapi.client.request({
                                path: 'https://www.googleapis.com/oauth2/v1/userinfo',
                                method: 'GET',
                                headers: { 'Authorization': 'Bearer ' + accessToken }
                            }).then((userInfo) => {
                                const email = userInfo.result.email;
                                console.log('👤 User authenticated:', email);
                                saveConnection(email, accessToken);
                                showPicker();
                            }).catch((error) => {
                                console.error('❌ Failed to get user info:', error);
                                showPicker();
                            });
                        } else if (response.error) {
                            console.error('❌ Token error:', response.error);
                        }
                    }
                });
                console.log('✅ Token client initialized');
                console.log('✅ Google Auth initialized successfully');
            } catch (error) {
                console.error('❌ Error initializing token client:', error);
            }
        }).catch((error) => {
            console.error('❌ Error initializing GAPI client:', error);
        });
    } catch (error) {
        console.error('❌ Error in initializeGoogleAuth:', error);
    }
}

function authenticateAndShowPicker() {
    console.log('🔑 Starting authentication flow...');
    
    if (!tokenClient) {
        console.error('❌ Token client not initialized');
        alert('Google Drive authentication not ready. Please try again.');
        return;
    }
    
    console.log('Requesting new token with account selection...');
    tokenClient.requestAccessToken({ prompt: 'select_account' });
}

/**
 * Validate Google Drive access token by making a test request
 * Returns true if valid, false if expired/invalid
 */
async function validateGoogleDriveToken() {
    if (!accessToken) {
        return false;
    }
    
    try {
        const response = await gapi.client.request({
            path: 'https://www.googleapis.com/oauth2/v1/tokeninfo?access_token=' + accessToken,
            method: 'GET'
        });
        
        console.log('✅ Token is valid');
        return true;
    } catch (error) {
        console.log('⚠️ Token validation failed:', error);
        return false;
    }
}

/**
 * Handle token expiration gracefully
 */
async function handleTokenExpiration(userEmail) {
    console.log('🔄 Token expired for:', userEmail);
    
    // Clear expired connection automatically
    const connections = getStoredConnections();
    const updatedConnections = connections.filter(c => c.email !== userEmail);
    localStorage.setItem('googleDriveConnections', JSON.stringify(updatedConnections));
    console.log('🗑️ Removed expired connection for:', userEmail);
    
    // Update UI
    updateConnectedDrivesUI();
    
    // Show user-friendly message with auto re-auth
    alert(
        `Your Google Drive session for ${userEmail} has expired.\n\n` +
        `Click OK to reconnect.`
    );
    
    // Automatically trigger re-authentication
    console.log('🔐 Auto-initiating re-authentication...');
    addNewGoogleDrive();
}

async function showPicker() {
    console.log('📂 Creating Google Picker...');
    
    if (!accessToken) {
        console.error('❌ No access token available');
        alert('Please authenticate with Google Drive first.');
        return;
    }
    
    if (typeof google === 'undefined' || !google.picker) {
        console.error('❌ Google Picker API not loaded');
        alert('Google Picker is not ready. Please try again.');
        return;
    }
    
    // Validate token before showing picker
    console.log('🔍 Validating Google Drive token...');
    const isValid = await validateGoogleDriveToken();
    
    if (!isValid) {
        // Token is expired, find which user and trigger re-auth
        const connections = getStoredConnections();
        const currentConnection = connections.find(c => c.token === accessToken);
        const userEmail = currentConnection ? currentConnection.email : 'your account';
        
        await handleTokenExpiration(userEmail);
        return;
    }
    
    try {
        const picker = new google.picker.PickerBuilder()
            .setOAuthToken(accessToken)
            .setDeveloperKey(API_KEY)
            .setCallback(pickerCallback)
            .addView(new google.picker.DocsView()
                .setIncludeFolders(true)
                .setMimeTypes('video/mp4,video/webm,video/quicktime,video/x-msvideo,video/x-flv,video/x-matroska')
            )
            .addView(new google.picker.DocsUploadView())
            .setTitle('Select a video from Google Drive')
            .build();
        
        picker.setVisible(true);
        console.log('✅ Google Picker shown');
    } catch (error) {
        console.error('❌ Error creating picker:', error);
        
        // Check if it's an auth error
        if (error.message && error.message.includes('401')) {
            const connections = getStoredConnections();
            const currentConnection = connections.find(c => c.token === accessToken);
            await handleTokenExpiration(currentConnection ? currentConnection.email : 'your account');
        } else {
            alert('Failed to show Google Drive picker. Please try again.');
        }
    }
}

function pickerCallback(data) {
    console.log('📂 Picker callback:', data);
    
    if (data[google.picker.Response.ACTION] === google.picker.Action.PICKED) {
        const doc = data[google.picker.Response.DOCUMENTS][0];
        const fileId = doc[google.picker.Document.ID];
        const fileName = doc[google.picker.Document.NAME];
        const fileUrl = doc[google.picker.Document.URL];
        
        console.log('✅ File selected:', fileName, fileId);
        
        // Store selected file info
        selectedFile = {
            id: fileId,
            name: fileName,
            url: fileUrl,
            source: 'googledrive'
        };
        
        // Set current upload type and source
        currentUploadType = 'file';
        currentSource = 'googledrive';
        
        // Proceed to Step 2
        if (typeof window.proceedToStep2 === 'function') {
            window.proceedToStep2();
        } else if (typeof proceedToStep2 === 'function') {
            proceedToStep2();
        } else {
            console.error('❌ proceedToStep2 function not found');
        }
    } else if (data[google.picker.Response.ACTION] === google.picker.Action.CANCEL) {
        console.log('📂 Picker cancelled');
    }
}

// Expose showGoogleDrivePicker to global scope
window.showGoogleDrivePicker = function() {
    showPicker();
};

// Initialize on DOM load
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔄 Initializing Google Drive connections...');
    updateConnectedDrivesUI();
});

console.log('✅ ION Uploader Pro initialized with FULL Feature Set!');
console.log('  📦 R2MultipartUploader (100MB chunks for large files)');
console.log('  📦 ChunkedUploader (5MB chunks alternative)');
console.log('  🌐 Platform Import (YouTube, Vimeo, etc.)');
console.log('  📂 Google Drive Integration');
console.log('✅ window.R2MultipartUploader is available');
console.log('✅ window.ChunkedUploader is available');
console.log('✅ window.processPlatformImport is available');
console.log('✅ window.showGoogleDrivePicker is available');

