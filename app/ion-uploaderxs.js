// ION Uploader Pro - Platform Import + Google Drive Integration + R2 Multipart Upload
// This file provides Pro features for ionuploader.js
// Last updated: 2025-10-17 - Fixed 404 errors with presigned URL encoding

// ============================================
// R2 MULTIPART UPLOADER CLASS
// ============================================

/**
 * Direct R2 Multipart Uploader
 * Uploads large files directly to Cloudflare R2 using presigned URLs
 */
class R2MultipartUploader {
    constructor(options = {}) {
        // OPTIMIZED 2025-10-17: 50MB parts for better reliability
        // - Smaller parts = faster uploads = less likely to timeout
        // - 1GB file = 20 parts
        // - 5GB file = 100 parts  
        this.partSize = options.partSize || 50 * 1024 * 1024; // 50MB parts - reliable for unstable connections
        this.maxConcurrentUploads = options.maxConcurrentUploads || 1; // Upload 1 part at a time for reliability
        this.maxRetries = options.maxRetries || 5; // 5 retry attempts for network reliability
        this.retryDelay = options.retryDelay || 2000; // 2 seconds between retries
        this.uploadTimeout = options.uploadTimeout || 1800000; // 30 minutes per part (allows slower connections)
        this.endpoint = options.endpoint || './ion-uploadermulti.php'; // RENAMED endpoint
        this.activeRetries = 0; // Track number of parts currently retrying
        
        // Version check for debugging
        console.log('✅ R2MultipartUploader initialized - Part size: ' + (this.partSize / 1024 / 1024) + 'MB');
        console.log('📍 Backend endpoint: ' + this.endpoint);
        
        // Verify backend version (async, won't block initialization)
        fetch('./check-multipart-version.php')
            .then(r => r.json())
            .then(data => {
                if (data.overall_status === 'PASS') {
                    console.log('%c✅ Backend Version Check: ' + (data.message || 'OK'), 'color: green; font-weight: bold');
                } else {
                    console.warn('%c⚠️ Backend Version Check: ' + (data.message || 'Some checks failed'), 'color: orange; font-weight: bold');
                    if (data.checks) console.log('Details:', data.checks);
                }
            })
            .catch(err => console.warn('⚠️ Could not verify backend version:', err));
        
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
        this.partProgress = {}; // FIXED: Track bytes uploaded per part for accurate overall progress
        this.maxProgressSeen = 0; // CRITICAL FIX: Never let progress go backwards
        this.maxUploadedSeen = 0; // CRITICAL FIX: Never let uploaded bytes go backwards
        this.isUploading = false;
        this.isPaused = false;
        this.abortController = null;
        this.startTime = null;
    }

    async upload(file, metadata = {}) {
        try {
            this.file = file;
            this.metadata = metadata; // Store metadata for later use
            this.isUploading = true;
            this.isPaused = false;
            this.completedParts = [];
            this.partProgress = {}; // Reset per-part progress
            this.maxProgressSeen = 0; // Reset max progress for new upload
            this.maxUploadedSeen = 0; // Reset max uploaded bytes for new upload
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
            
            // Initialize detailed progress tooltip
            if (window.updateProgressTooltip) {
                window.updateProgressTooltip({
                    fileName: file.name,
                    fileSize: file.size,
                    partSize: this.partSize,
                    totalParts: this.parts.length,
                    completedParts: 0,
                    uploadingParts: 0,
                    failedParts: 0,
                    parts: this.parts.map(p => ({ partNumber: p.partNumber, status: 'pending', progress: 0 })),
                    speed: 0,
                    loaded: 0,
                    timeRemaining: 0
                });
            }

            await this.initializeMultipartUpload(file, metadata);
            console.log(`✅ Initialized multipart upload: ${this.uploadId}`);

            // SIMPLIFIED: Upload parts one at a time (no concurrent uploads)
            // Generate presigned URL on-demand for each part to keep session fresh
            await this.uploadPartsSequentially();
            console.log(`⬆️ All parts uploaded successfully`);

            const result = await this.completeMultipartUpload();
            console.log(`🎉 Upload completed: Video ID ${result.video_id}`);

            this.onSuccess(result);
            return result;

        } catch (error) {
            console.error('❌ Upload failed:', error);
            this.onError(error);
            
            // Only abort if:
            // 1. We have an upload ID
            // 2. Upload wasn't manually aborted
            // 3. No active retries in progress (prevents aborting during retry)
            if (this.uploadId && !this.abortController.signal.aborted && this.activeRetries === 0) {
                console.log('🛑 Aborting R2 multipart upload after fatal error');
                try {
                    await this.abortMultipartUpload();
                } catch (abortError) {
                    console.warn('Failed to abort upload:', abortError);
                }
            } else if (this.activeRetries > 0) {
                console.log(`⏳ Not aborting - ${this.activeRetries} retries still in progress`);
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
        console.log('🔧 Initializing multipart upload...');
        console.log('   File:', file.name, `(${(file.size / 1024 / 1024).toFixed(2)} MB)`);
        console.log('   Endpoint:', this.endpoint);
        
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
        formData.append('selected_channels', metadata.selected_channels || ''); // CRITICAL: Include selected channels
        
        // Add thumbnail if available
        if (metadata.thumbnail) {
            formData.append('thumbnail', metadata.thumbnail, 'thumbnail.jpg');
            console.log('📸 Adding thumbnail to init:', metadata.thumbnail.size, 'bytes');
        }

        const response = await fetch(this.endpoint, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            signal: this.abortController.signal
        });

        console.log('📡 Backend response status:', response.status, response.statusText);
        
        const result = await response.json();
        console.log('📦 Backend response:', result);
        
        if (!result.success) {
            console.error('❌ Backend returned error:', result.error);
            throw new Error(result.error || 'Failed to initialize multipart upload');
        }

        this.uploadId = result.uploadId;
        this.r2UploadId = result.r2UploadId;
        this.objectKey = result.objectKey;
        
        console.log('✅ Upload session created:');
        console.log('   Upload ID:', this.uploadId);
        console.log('   R2 Upload ID:', this.r2UploadId);
        console.log('   Object Key:', this.objectKey);
        
        return result;
    }

    async getPresignedUrls() {
        console.log('🔗 Requesting presigned URLs...');
        console.log('   Upload ID:', this.uploadId);
        console.log('   Part Count:', this.parts.length);
        
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

        console.log('📡 Presigned URLs response:', response.status);
        
        const result = await response.json();
        console.log('📦 Presigned URLs result:', result.success ? 'Success' : 'Failed');
        
        if (!result.success) {
            console.error('❌ Failed to get presigned URLs:', result.error);
            throw new Error(result.error || 'Failed to get presigned URLs');
        }

        console.log('✅ Got', result.presignedUrls.length, 'presigned URLs');
        return result.presignedUrls;
    }

    async uploadPartsSequentially() {
        console.log(`🔄 Starting sequential upload of ${this.parts.length} parts`);
        
        for (let i = 0; i < this.parts.length; i++) {
            const part = this.parts[i];
            
            if (this.isPaused) {
                await this.waitForResume();
            }
            
            console.log(`\n📦 Processing part ${part.partNumber}/${this.parts.length}`);
            
            // Get fresh presigned URL for THIS part only
            const presignedUrl = await this.getPresignedUrlForPart(part.partNumber);
            console.log(`🔗 Got fresh presigned URL for part ${part.partNumber}`);
            
            // Upload this part
            await this.uploadPart(part, presignedUrl);
            console.log(`✅ Part ${part.partNumber}/${this.parts.length} completed\n`);
        }
        
        console.log(`✅ All ${this.parts.length} parts uploaded successfully`);
    }
    
    async getPresignedUrlForPart(partNumber) {
        console.log(`   Requesting presigned URL for part ${partNumber}...`);
        
        const formData = new FormData();
        formData.append('action', 'get-presigned-urls');
        formData.append('uploadId', this.uploadId);
        formData.append('partCount', '1'); // Request just one URL
        formData.append('startPart', partNumber.toString()); // Which part we want

        const response = await fetch(this.endpoint, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            signal: this.abortController.signal
        });

        const result = await response.json();
        
        if (!result.success) {
            console.error(`❌ Failed to get presigned URL for part ${partNumber}:`, result.error);
            throw new Error(result.error || `Failed to get presigned URL for part ${partNumber}`);
        }

        // Return just the URL string (backend returns array, we want first one)
        return result.presignedUrls[0]?.url || result.presignedUrls[0];
    }

    async uploadPart(part, presignedUrl, retryCount = 0) {
        try {
            if (this.isPaused) {
                await this.waitForResume();
            }

            console.log(`⬆️ Uploading part ${part.partNumber}/${this.parts.length} (${this.formatFileSize(part.size)})`);
            console.log(`🔗 Presigned URL for part ${part.partNumber}:`, presignedUrl);
            
            // Update tooltip: Mark part as uploading
            if (window.uploadProgressDetails) {
                const partIndex = window.uploadProgressDetails.parts.findIndex(p => p.partNumber === part.partNumber);
                if (partIndex >= 0) {
                    window.uploadProgressDetails.parts[partIndex].status = 'uploading';
                }
                window.uploadProgressDetails.uploadingParts = window.uploadProgressDetails.parts.filter(p => p.status === 'uploading').length;
                if (window.updateProgressTooltip) window.updateProgressTooltip();
            }

            // CRITICAL FIX: Use XMLHttpRequest for real-time upload progress tracking
            const etag = await new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                
                // Set timeout to prevent hanging uploads
                xhr.timeout = this.uploadTimeout;
                
                // Initialize progress tracking for this part
                this.partProgress[part.partNumber] = 0;
                
                xhr.upload.addEventListener('progress', (e) => {
                    if (e.lengthComputable) {
                        // Update progress for this specific part
                        this.partProgress[part.partNumber] = e.loaded;
                        
                        // FIXED: Calculate total bytes uploaded across ALL parts (completed + in-progress)
                        let totalUploaded = 0;
                        
                        // Add bytes from completed parts
                        for (const completedPart of this.completedParts) {
                            const partIndex = this.parts.findIndex(p => p.partNumber === completedPart.PartNumber);
                            if (partIndex >= 0) {
                                totalUploaded += this.parts[partIndex].size;
                            }
                        }
                        
                        // Add bytes from parts currently uploading
                        for (const [partNum, bytes] of Object.entries(this.partProgress)) {
                            // Don't double-count completed parts
                            if (!this.completedParts.find(p => p.PartNumber === parseInt(partNum))) {
                                totalUploaded += bytes;
                            }
                        }
                        
                        // CRITICAL FIX: Never let uploaded bytes go backwards
                        if (totalUploaded < this.maxUploadedSeen) {
                            totalUploaded = this.maxUploadedSeen;
                        } else {
                            this.maxUploadedSeen = totalUploaded;
                        }
                        
                        let percentage = (totalUploaded / this.file.size) * 100;
                        
                        // CRITICAL FIX: Never let progress percentage go backwards
                        if (percentage < this.maxProgressSeen) {
                            percentage = this.maxProgressSeen;
                        } else {
                            this.maxProgressSeen = percentage;
                        }
                        
                        // Update progress in real-time
                        const speed = this.calculateSpeed();
                        const timeRemaining = this.estimateTimeRemaining();
                        
                        this.onProgress({
                            loaded: totalUploaded,
                            total: this.file.size,
                            percentage: percentage,
                            speed: speed,
                            timeRemaining: timeRemaining
                        });
                        
                        // Update tooltip with real-time data
                        if (window.uploadProgressDetails) {
                            const partIndex = window.uploadProgressDetails.parts.findIndex(p => p.partNumber === part.partNumber);
                            if (partIndex >= 0) {
                                window.uploadProgressDetails.parts[partIndex].progress = Math.round((e.loaded / e.total) * 100);
                            }
                            window.uploadProgressDetails.speed = speed;
                            window.uploadProgressDetails.loaded = totalUploaded;
                            window.uploadProgressDetails.timeRemaining = timeRemaining;
                            if (window.updateProgressTooltip) window.updateProgressTooltip();
                        }
                        
                        console.log(`📊 Part ${part.partNumber}/${this.parts.length}: ${((e.loaded / e.total) * 100).toFixed(1)}% | Overall: ${percentage.toFixed(1)}% (${this.formatFileSize(totalUploaded)} / ${this.formatFileSize(this.file.size)})`);
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
                        console.error(`   Presigned URL: ${presignedUrl.substring(0, 100)}...`);
                        console.error(`   Upload ID: ${this.r2UploadId}`);
                        console.error(`   Part size: ${part.blob.size} bytes`);
                        console.error(`   Response: ${xhr.responseText}`);
                        console.error(`   All response headers:`, xhr.getAllResponseHeaders());
                        reject(new Error(`Part ${part.partNumber} upload failed: ${xhr.status}`));
                    }
                });
                
                xhr.addEventListener('error', () => {
                    console.error(`❌ Network error uploading part ${part.partNumber}`);
                    console.error(`   Upload was interrupted. Will retry if attempts remain.`);
                    console.error(`   Part size: ${part.blob.size} bytes (${(part.blob.size / 1024 / 1024).toFixed(2)} MB)`);
                    reject(new Error(`Network error uploading part ${part.partNumber}`));
                });
                
                xhr.addEventListener('timeout', () => {
                    console.error(`❌ Timeout uploading part ${part.partNumber} (exceeded ${this.uploadTimeout / 1000}s)`);
                    reject(new Error(`Timeout uploading part ${part.partNumber}`));
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
            
            // Clear progress tracking for this completed part
            delete this.partProgress[part.partNumber];
            
            // Update tooltip: Mark part as completed
            if (window.uploadProgressDetails) {
                const partIndex = window.uploadProgressDetails.parts.findIndex(p => p.partNumber === part.partNumber);
                if (partIndex >= 0) {
                    window.uploadProgressDetails.parts[partIndex].status = 'completed';
                    window.uploadProgressDetails.parts[partIndex].progress = 100;
                }
                window.uploadProgressDetails.completedParts = this.completedParts.length;
                window.uploadProgressDetails.uploadingParts = window.uploadProgressDetails.parts.filter(p => p.status === 'uploading').length;
                if (window.updateProgressTooltip) window.updateProgressTooltip();
            }
            
            this.onPartProgress({
                partNumber: part.partNumber,
                totalParts: this.parts.length,
                completedParts: this.completedParts.length
            });

            console.log(`✅ Part ${part.partNumber}/${this.parts.length} uploaded successfully (${((this.completedParts.length / this.parts.length) * 100).toFixed(1)}% of parts complete)`);
            
            return completedPart;

        } catch (error) {
            // Clear progress for failed part
            delete this.partProgress[part.partNumber];
            
            // Retry logic with exponential backoff
            if (retryCount < this.maxRetries && !this.abortController.signal.aborted) {
                this.activeRetries++; // Track that we're in a retry
                const waitTime = this.retryDelay * Math.pow(2, retryCount); // Exponential backoff
                console.warn(`⚠️ Retrying part ${part.partNumber} in ${(waitTime/1000).toFixed(1)}s (attempt ${retryCount + 1}/${this.maxRetries})`);
                console.warn(`   Error: ${error.message}`);
                console.warn(`   Part size: ${(part.blob.size / 1024 / 1024).toFixed(2)} MB`);
                console.warn(`   Active retries: ${this.activeRetries}`);
                await this.delay(waitTime);
                console.log(`🔄 Starting retry for part ${part.partNumber}...`);
                try {
                    return await this.uploadPart(part, presignedUrl, retryCount + 1);
                } finally {
                    this.activeRetries--; // Decrement when retry completes (success or failure)
                }
            }
            
            console.error(`❌ Part ${part.partNumber} FAILED after ${this.maxRetries} retries:`, error.message);
            console.error(`   Upload cannot continue. All retry attempts exhausted.`);
            
            // Update tooltip: Mark part as failed
            if (window.uploadProgressDetails) {
                const partIndex = window.uploadProgressDetails.parts.findIndex(p => p.partNumber === part.partNumber);
                if (partIndex >= 0) {
                    window.uploadProgressDetails.parts[partIndex].status = 'failed';
                }
                window.uploadProgressDetails.failedParts = window.uploadProgressDetails.parts.filter(p => p.status === 'failed').length;
                window.uploadProgressDetails.uploadingParts = window.uploadProgressDetails.parts.filter(p => p.status === 'uploading').length;
                if (window.updateProgressTooltip) window.updateProgressTooltip();
            }
            
            throw error;
        }
    }

    async completeMultipartUpload() {
        this.completedParts.sort((a, b) => a.PartNumber - b.PartNumber);

        const formData = new FormData();
        formData.append('action', 'complete');
        formData.append('uploadId', this.uploadId);
        formData.append('parts', JSON.stringify(this.completedParts));
        
        // Pass selected_channels to backend for proper channel distribution
        if (this.metadata && this.metadata.selected_channels) {
            formData.append('selected_channels', this.metadata.selected_channels);
            console.log('📺 Sending selected_channels for distribution:', this.metadata.selected_channels);
        }

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
const SCOPES = 'https://www.googleapis.com/auth/drive.file';

// ============================================
// FEATURE FLAG: Google Drive Download Method
// ============================================
const DRIVE_DOWNLOAD_METHOD = 'fetch'; // Using fetch with Token Client session tokens
// ============================================

// ============================================
// DRIVE.FILE SCOPE STRATEGY (Current Implementation)
// ============================================
// 
// With drive.file scope, Google ONLY grants access to files during an active OAuth session.
// This means we CANNOT use stored tokens - each Picker session requires a FRESH token.
//
// IMPLEMENTATION:
// 1. User clicks Connect → Token Client popup (NOT OAuth redirect)
// 2. User grants permission → Immediate fresh token
// 3. Token used to show Picker → Files selected in THIS session are accessible
// 4. File downloaded using the SAME fresh token → Success!
//
// KEY DIFFERENCES vs OAuth Redirect Flow:
// ✅ Token Client (popup) → Works with drive.file
// ❌ OAuth redirect → Stored tokens don't work with drive.file for Picker files
//
// LIMITATION: User must re-authenticate each time (no persistent refresh tokens)
// FUTURE: Once drive.readonly is approved, switch to OAuth redirect for persistent access

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

// Google Drive button click handler - SMART BUTTON BEHAVIOR
function handleGoogleDriveButtonClick(event) {
    console.log('💾 Google Drive button clicked');
    event.stopPropagation();
    
    // Check if arrow was clicked (show dropdown)
    const isArrowClick = event.target.id === 'googleDriveArrow' || 
                         event.target.closest('#googleDriveArrow');
    
    const connections = getStoredConnections();
    console.log('🔍 Stored connections:', connections.length);
    console.log('🔍 Arrow clicked:', isArrowClick);
    
    // SMART BEHAVIOR:
    // 1. If arrow clicked → Always show dropdown
    // 2. If 0 connections → Open OAuth
    // 3. If 1 connection → Open picker directly
    // 4. If 2+ connections → Show dropdown
    
    if (isArrowClick) {
        // Arrow clicked - show dropdown
        console.log('▼ Arrow clicked - showing dropdown');
        showGoogleDriveDropdown();
    } else if (connections.length === 0) {
        // No connections - open OAuth
        console.log('➕ No connections - opening OAuth');
        addNewGoogleDrive();
    } else if (connections.length === 1) {
        // 1 connection - open picker directly
        console.log('📂 One connection - opening picker directly');
        selectDrive(connections[0].email);
    } else {
        // Multiple connections - show dropdown
        console.log('📋 Multiple connections - showing dropdown');
        showGoogleDriveDropdown();
    }
}

let dropdownHideTimeout = null;
let googleDriveWrapperInitialized = false;

// Initialize Google Drive wrapper hover behavior (call once on page load)
function initializeGoogleDriveHover() {
    if (googleDriveWrapperInitialized) return;
    
    const wrapper = document.querySelector('.gd-wrapper');
    const dropdown = document.getElementById('googleDriveDropdown');
    
    if (!wrapper) {
        console.warn('⚠️ Google Drive wrapper not found');
        return;
    }
    
    // Track mouse leaving the entire Google Drive area (button + dropdown)
    wrapper.addEventListener('mouseleave', function(e) {
        // Only hide if we're actually leaving the wrapper area
        // (not just moving between button and dropdown)
        dropdownHideTimeout = setTimeout(() => {
            hideGoogleDriveDropdown();
        }, 500); // 500ms delay before hiding (increased for better UX)
    });
    
    // Cancel hide timeout when mouse enters the area
    wrapper.addEventListener('mouseenter', function() {
        if (dropdownHideTimeout) {
            clearTimeout(dropdownHideTimeout);
            dropdownHideTimeout = null;
        }
    });
    
    // Extra safeguard: Cancel hide when directly hovering dropdown
    if (dropdown) {
        dropdown.addEventListener('mouseenter', function() {
            if (dropdownHideTimeout) {
                clearTimeout(dropdownHideTimeout);
                dropdownHideTimeout = null;
            }
        });
        
        dropdown.addEventListener('mouseleave', function() {
            // Only start hide timer if we're leaving to outside the wrapper
            dropdownHideTimeout = setTimeout(() => {
                hideGoogleDriveDropdown();
            }, 500);
        });
    }
    
    googleDriveWrapperInitialized = true;
    console.log('✅ Google Drive hover behavior initialized');
}

function showGoogleDriveDropdown() {
    const dropdown = document.getElementById('googleDriveDropdown');
    const wrapper = document.querySelector('.gd-wrapper');
    if (!dropdown || !wrapper) return;
    
    // Clear any pending hide timeout
    if (dropdownHideTimeout) {
        clearTimeout(dropdownHideTimeout);
        dropdownHideTimeout = null;
    }
    
    // Use class instead of inline style to allow CSS hover to work
    wrapper.classList.add('open');
    dropdown.classList.add('visible');
    console.log('✅ Google Drive dropdown shown');
    
    // Initialize hover behavior if not already done
    initializeGoogleDriveHover();
}

function hideGoogleDriveDropdown() {
    const dropdown = document.getElementById('googleDriveDropdown');
    const wrapper = document.querySelector('.gd-wrapper');
    if (!dropdown || !wrapper) return;
    
    // Safety check: Don't hide if mouse is currently over the wrapper
    if (wrapper.matches(':hover')) {
        console.log('⚠️ Prevented hide - mouse still over wrapper');
        return;
    }
    
    wrapper.classList.remove('open');
    dropdown.classList.remove('visible');
    console.log('✅ Google Drive dropdown hidden');
}

// ============================================
// NEW: OAuth Flow with Refresh Tokens
// ============================================

/**
 * Request Google Drive access using Token Client (in-session authentication)
 * This approach works with drive.file scope for Picker-selected files
 */
function addNewGoogleDrive() {
    console.log('🔑 Requesting Google Drive access via Token Client...');
    hideGoogleDriveDropdown();
    
    if (!tokenClient) {
        console.warn('⚠️ Token client not initialized yet, loading APIs...');
        loadGoogleAPIs();
        
        // Wait a bit and try again
        setTimeout(() => {
            if (tokenClient) {
                console.log('✅ Token client ready, requesting access...');
                tokenClient.requestAccessToken({ prompt: 'select_account' });
            } else {
                console.error('❌ Token client still not initialized after loading');
                alert('Google Drive is still loading. Please wait a moment and try again.');
            }
        }, 2000);
        return;
    }
    
    // Request access token via popup (no redirect)
    // This creates a token that's valid for Picker-selected file access
    console.log('📋 Opening Google consent popup...');
    tokenClient.requestAccessToken({ prompt: 'select_account' });
    
    console.log('✅ Token request initiated');
}

// Listen for OAuth success message from popup
window.addEventListener('message', (event) => {
    // Security: Verify origin if needed
    // if (event.origin !== window.location.origin) return;
    
    if (event.data.type === 'google_drive_connected' && event.data.success) {
        console.log('✅ Google Drive connected message received:', event.data.email);
        console.log('🔄 Has refresh token:', event.data.hasRefreshToken);
        console.log('📍 Message received in window:', window.location.href);
        console.log('📧 Email from OAuth:', event.data.email);
        console.log('👤 Session user_id:', window.currentUserId || 'UNKNOWN');
        
        // CRITICAL: Wait 2 seconds for database write to complete before fetching token
        console.log('⏳ Waiting 2 seconds for database write to complete...');
        setTimeout(() => {
            console.log('✅ Wait complete, fetching access token from server');
            console.log('📧 Fetching for email:', event.data.email);
            
            // Fetch the access token from the server
            fetchGoogleDriveAccessToken(event.data.email).then(result => {
            if (result.success && result.access_token) {
                console.log('✅ Access token fetched from server');
                
                // Save connection locally for quick access
                saveConnection(event.data.email, result.access_token);
                console.log('✅ Connection saved to localStorage');
                
                // Update UI
                updateConnectedDrivesUI();
                console.log('✅ Dropdown UI updated');
                
                // Automatically open picker for newly connected drive
                setTimeout(() => {
                    console.log('📂 Opening Google Drive Picker...');
                    loadGoogleAPIsAndShowPicker(event.data.email, result.access_token);
                }, 500);
            } else {
                console.error('❌ Failed to fetch access token from server:', result.error);
                console.error('📧 Email that was used:', event.data.email);
                console.error('👤 Check PHP error logs for database INSERT/UPDATE status');
                
                alert('Connected successfully, but failed to fetch access token.\n\n' + 
                      'Error: ' + result.error + '\n\n' +
                      'This usually means:\n' +
                      '1. Database write didn\'t complete\n' +
                      '2. Table "IONGoogleDriveTokens" doesn\'t exist\n' +
                      '3. Session user_id mismatch\n\n' +
                      'Please check:\n' +
                      '- PHP error logs for database errors\n' +
                      '- Browser console (F12) for details\n' +
                      '- Database table exists and has correct schema');
            }
        }).catch(error => {
            console.error('❌ Error in OAuth success handler:', error);
            alert('Connected successfully, but an error occurred:\n\n' + error.message + '\n\nPlease check the browser console and refresh the page.');
        });
        }, 2000); // Wait 2 seconds for database write to complete
    }
});

// IMPORTANT: If we're in a parent page, relay messages to iframe
// This handles the case where uploader is in iframe/modal
if (window.self === window.top) {
    // We're in the parent page, not in iframe
    window.addEventListener('message', (event) => {
        if (event.data.type === 'google_drive_connected') {
            console.log('🔁 Parent received message, relaying to uploader iframe if exists');
            
            // Find uploader iframe and relay message
            const uploaderIframe = document.querySelector('iframe[src*="ionuploader"]') ||
                                  document.querySelector('#ionVideoUploaderModal iframe');
            
            if (uploaderIframe && uploaderIframe.contentWindow) {
                console.log('🔁 Relaying message to uploader iframe');
                uploaderIframe.contentWindow.postMessage(event.data, '*');
            }
        }
    });
}

/**
 * Fetch access token from server (stored in database)
 */
async function fetchGoogleDriveAccessToken(email) {
    try {
        console.log('🔑 Fetching access token for:', email);
        console.log('🔑 Email type:', typeof email);
        console.log('🔑 Email length:', email ? email.length : 'NULL');
        console.log('🔑 Encoded email:', encodeURIComponent(email));
        
        const response = await fetch('/api/get-google-drive-token.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `email=${encodeURIComponent(email)}`
        });
        
        console.log('📡 Response status:', response.status);
        
        const responseText = await response.text();
        console.log('📄 Response text:', responseText);
        
        let result;
        try {
            result = JSON.parse(responseText);
            console.log('📦 Parsed result:', result);
        } catch (parseError) {
            console.error('❌ JSON parse error:', parseError);
            console.error('❌ Response was not valid JSON. Raw response:', responseText);
            return { success: false, error: 'Server returned invalid JSON: ' + responseText.substring(0, 200) };
        }
        
        if (result.success) {
            console.log('✅ Access token retrieved successfully');
            return { success: true, access_token: result.access_token };
        } else {
            console.error('❌ Failed to fetch access token:', result.error);
            return { success: false, error: result.error || 'Unknown error' };
        }
    } catch (error) {
        console.error('❌ Error fetching access token:', error);
        return { success: false, error: error.message };
    }
}

/**
 * Get valid access token for email (auto-refresh if expired)
 */
async function getValidGoogleDriveToken(email) {
    console.log('🔍 Getting valid token for:', email);
    
    // Check if token exists in localStorage
    const connections = getStoredConnections();
    const connection = connections.find(c => c.email === email);
    
    if (!connection) {
        throw new Error('No connection found for ' + email);
    }
    
    // Check if token is potentially expired (assume 55 min lifespan for safety)
    const tokenAge = Date.now() - connection.timestamp;
    const TOKEN_LIFESPAN = 55 * 60 * 1000; // 55 minutes
    
    if (tokenAge < TOKEN_LIFESPAN) {
        // Token is likely still valid
        console.log('✅ Token is still valid (age: ' + Math.round(tokenAge / 60000) + ' mins)');
        return connection.token;
    }
    
    // Token might be expired, refresh it
    console.log('🔄 Token potentially expired (age: ' + Math.round(tokenAge / 60000) + ' mins), refreshing...');
    
    try {
        const response = await fetch('/api/refresh-google-drive-token.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `email=${encodeURIComponent(email)}`
        });
        
        const result = await response.json();
        
        if (result.success) {
            console.log('✅ Token refreshed successfully');
            // Update localStorage with new token
            saveConnection(email, result.access_token);
            return result.access_token;
        } else {
            console.error('❌ Token refresh failed:', result.error);
            
            // Check if error indicates revoked access
            if (result.error.includes('revoked') || result.error.includes('reconnect')) {
                // Remove invalid connection
                removeConnection(email);
                throw new Error('TOKEN_REVOKED');
            }
            
            throw new Error('TOKEN_EXPIRED');
        }
    } catch (error) {
        console.error('❌ Error refreshing token:', error);
        throw error;
    }
}

/**
 * Remove a connection from localStorage
 */
function removeConnection(email) {
    const connections = getStoredConnections();
    const filtered = connections.filter(c => c.email !== email);
    localStorage.setItem('googleDriveConnections', JSON.stringify(filtered));
    updateConnectedDrivesUI();
}

/**
 * Load Google APIs and show picker with auto-refreshed token
 */
async function loadGoogleAPIsAndShowPicker(email, accessTokenOverride = null) {
    try {
        // Get valid token (auto-refresh if needed)
        const token = accessTokenOverride || await getValidGoogleDriveToken(email);
        accessToken = token;
        
        // Load Google APIs if not already loaded
        if (typeof google === 'undefined' || typeof gapi === 'undefined') {
            console.log('📚 Loading Google APIs...');
            await loadGoogleAPIs();
        }
        
        // Show picker
        console.log('📂 Opening Google Drive Picker...');
        showPicker();
        
    } catch (error) {
        console.error('❌ Error loading picker:', error);
        
        if (error.message === 'TOKEN_EXPIRED' || error.message === 'TOKEN_REVOKED') {
            const reauth = confirm(
                'Your Google Drive access has expired or been revoked.\n\n' +
                'Would you like to reconnect?'
            );
            
            if (reauth) {
                addNewGoogleDrive();
            }
        } else {
            alert('Failed to open Google Drive: ' + error.message);
        }
    }
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
    hideGoogleDriveDropdown();
    
    // With drive.file scope, we need a fresh token for each Picker session
    // Request new token via Token Client
    if (!tokenClient) {
        console.warn('⚠️ Token client not initialized yet, loading APIs...');
        loadGoogleAPIs();
        
        // Wait a bit and try again
        setTimeout(() => {
            if (tokenClient) {
                console.log('✅ Token client ready, requesting fresh token...');
                tokenClient.requestAccessToken({ hint: email, prompt: '' });
            } else {
                console.error('❌ Token client still not initialized after loading');
                alert('Google Drive is still loading. Please wait a moment and try again.');
            }
        }, 2000);
        return;
    }
    
    console.log('🔑 Requesting fresh token for Picker session...');
    tokenClient.requestAccessToken({ hint: email, prompt: '' });
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

    // Check if APIs are fully loaded
    if (typeof google !== 'undefined' && google.accounts && typeof gapi !== 'undefined' && gapi.client) {
        console.log('✅ Google APIs already loaded, initializing auth...');
        initializeGoogleAuth();
        return;
    }
    
    // If scripts are loading (from ionuploads.php), wait for them
    if (window.googleApisLoading) {
        console.log('⏳ Google APIs already loading, waiting...');
        return;
    }
    
    // Check if gapi exists but client not loaded yet (scripts loaded, but gapi.load not called)
    if (typeof gapi !== 'undefined' && typeof google !== 'undefined' && google.accounts) {
        console.log('✅ Scripts loaded, initializing client:picker...');
        gapi.load('client:picker', () => {
            console.log('✅ Client:picker loaded, initializing auth...');
            initializeGoogleAuth();
        });
        return;
    }
    
    console.log('📚 Scripts not loaded yet, creating script tags...');
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
    console.log('🔑 Current accessToken when file selected:', accessToken ? 'Present (length: ' + accessToken.length + ')' : 'MISSING');
    console.log('🔑 Token preview:', accessToken ? accessToken.substring(0, 50) + '...' : 'N/A');
    
    if (data[google.picker.Response.ACTION] === google.picker.Action.PICKED) {
        const doc = data[google.picker.Response.DOCUMENTS][0];
        const fileId = doc[google.picker.Document.ID];
        const fileName = doc[google.picker.Document.NAME];
        const fileUrl = doc[google.picker.Document.URL];
        const mimeType = doc[google.picker.Document.MIME_TYPE] || 'video/mp4';
        
        console.log('✅ File selected via Picker:', fileName);
        console.log('📄 File ID:', fileId);
        console.log('📄 MIME Type:', mimeType);
        console.log('📄 File URL:', fileUrl);
        console.log('🔐 About to attempt download with Picker session token');
        
        // IMPORTANT: With drive.file scope, the file should be accessible because
        // the user just "opened" it through the official Google Picker.
        // We MUST use the SAME token that was used to show the Picker.
        
        console.log('⬇️ Downloading file from Google Drive using Picker session token...');
        downloadGoogleDriveFile(fileId, fileName, mimeType);
        
    } else if (data[google.picker.Response.ACTION] === google.picker.Action.CANCEL) {
        console.log('📂 Picker cancelled');
    }
}

/**
 * Download Google Drive file using current session access token
 * This works with drive.file scope during the active Picker session
 */
async function downloadGoogleDriveFile(fileId, fileName, mimeType) {
    try {
        console.log('📥 Fetching file from Google Drive API...');
        console.log('   File ID:', fileId);
        console.log('   File Name:', fileName);
        console.log('   MIME Type:', mimeType);
        console.log('   Access Token:', accessToken ? 'Available (length: ' + accessToken.length + ')' : 'Missing');
        
        if (!accessToken) {
            throw new Error('No access token available');
        }
        
        // Show loading indicator
        const uploadZone = document.getElementById('uploadZone');
        if (uploadZone) {
            uploadZone.innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <div class="spinner" style="margin: 0 auto 20px;"></div>
                    <p style="color: #94a3b8; font-size: 14px;">Downloading from Google Drive...</p>
                    <p style="color: #64748b; font-size: 12px; margin-top: 8px;">${fileName}</p>
                </div>
            `;
        }
        
        // Choose download method based on feature flag
        console.log('🔍 Download method:', DRIVE_DOWNLOAD_METHOD);
        console.log('📄 File ID:', fileId);
        console.log('🔑 Token preview:', accessToken ? accessToken.substring(0, 20) + '...' : 'MISSING');
        
        let useGapiClient = false;
        
        // Determine which method to use
        if (DRIVE_DOWNLOAD_METHOD === 'gapi') {
            // Try to use gapi.client (better for drive.file scope)
            if (typeof gapi === 'undefined' || !gapi.client) {
                console.warn('⚠️ gapi.client requested but not available - falling back to fetch');
                useGapiClient = false;
            } else {
                useGapiClient = true;
                
                // Load Drive API client if not already loaded
                if (!gapi.client.drive) {
                    console.log('⚙️ Loading gapi.client.drive...');
                    try {
                        await gapi.client.load('drive', 'v3');
                        console.log('✅ gapi.client.drive loaded successfully');
                    } catch (error) {
                        console.error('❌ Failed to load gapi.client.drive:', error);
                        console.error('❌ Full error object:', error);
                        useGapiClient = false;
                    }
                } else {
                    console.log('✅ gapi.client.drive already loaded');
                }
            }
        } else {
            // Use fetch method with fresh OAuth token from Token Client
            console.log('📡 Using fetch method with Token Client session token');
            useGapiClient = false;
        }
        
        let metadata;
        
        if (useGapiClient) {
            // METHOD 1: Use gapi.client (Picker-aware, recommended for drive.file scope)
            console.log('🔍 Method 1: Using gapi.client.drive.files.get...');
            
            // CRITICAL: Set access token for gapi.client
            console.log('🔑 Setting access token for gapi.client...');
            gapi.client.setToken({ access_token: accessToken });
            console.log('✅ Token set for gapi.client');
            
            try {
                const metadataResponse = await gapi.client.drive.files.get({
                    fileId: fileId,
                    fields: 'id,name,mimeType,size'
                });
                metadata = metadataResponse.result;
                console.log('✅ gapi.client metadata retrieved:', metadata);
            } catch (error) {
                console.error('❌ gapi.client metadata failed');
                console.error('❌ Error object:', error);
                console.error('❌ Error status:', error.status);
                console.error('❌ Error result:', error.result);
                console.error('❌ Error message:', error.message);
                
                // Try to extract detailed error info
                const errorCode = error.status || error.code;
                const errorMessage = error.result?.error?.message || 
                                   error.body ? JSON.parse(error.body).error?.message : 
                                   error.message || 'Unknown error';
                const errorDetails = error.result?.error?.errors || [];
                
                console.error('❌ Parsed - Code:', errorCode, 'Message:', errorMessage);
                console.error('❌ Error details array:', errorDetails);
                
                if (errorCode === 404) {
                    throw new Error(
                        `File not accessible (404) with drive.file scope.\n\n` +
                        `LIMITATION: drive.file only grants access to:\n` +
                        `• Files created by this app\n` +
                        `• Files opened during current OAuth session\n\n` +
                        `The Picker-selected file cannot be accessed with stored tokens.\n\n` +
                        `SOLUTION: drive.readonly scope (pending Google approval)\n` +
                        `WORKAROUND: Upload file directly or use URL import`
                    );
                } else if (errorCode === 403) {
                    throw new Error(`Access forbidden (403): ${errorMessage}\n\nCheck: Drive API enabled, OAuth scope sufficient`);
                } else if (errorCode === 401) {
                    throw new Error(`Authentication failed (401): ${errorMessage}\n\nTry reconnecting Google Drive`);
                } else {
                    throw new Error(`Drive API Error (${errorCode || 'UNKNOWN'}): ${errorMessage}`);
                }
            }
        } else {
            // METHOD 2: Direct fetch (Works with OAuth token from Picker session)
            console.log('🔍 Method 2: Using direct fetch API with OAuth token');
            
            const metadataResponse = await fetch(`https://www.googleapis.com/drive/v3/files/${fileId}?fields=id,name,mimeType,size`, {
                headers: {
                    'Authorization': `Bearer ${accessToken}`
                }
            });
            
            console.log('📊 Fetch metadata status:', metadataResponse.status);
            console.log('📊 Response headers:', Object.fromEntries(metadataResponse.headers.entries()));
            
            if (!metadataResponse.ok) {
                const errorText = await metadataResponse.text();
                console.error('❌ Fetch metadata failed:', errorText);
                
                let errorDetails;
                try {
                    errorDetails = JSON.parse(errorText);
                    console.error('❌ Parsed error:', errorDetails);
                } catch (e) {
                    console.error('❌ Could not parse error response');
                }
                
                if (metadataResponse.status === 404) {
                    throw new Error(
                        `File not found (404).\n\n` +
                        `Possible causes:\n` +
                        `• File ID is incorrect\n` +
                        `• File has been deleted\n` +
                        `• OAuth session has expired\n\n` +
                        `Please try selecting the file again through Google Drive.`
                    );
                } else if (metadataResponse.status === 403) {
                    throw new Error(`Access forbidden (403): ${errorDetails?.error?.message || 'Check OAuth scope'}`);
                } else if (metadataResponse.status === 401) {
                    throw new Error(`Authentication failed (401): Token may be expired`);
                } else {
                    throw new Error(`HTTP ${metadataResponse.status}: ${errorDetails?.error?.message || 'Failed to access file'}`);
                }
            }
            
            metadata = await metadataResponse.json();
            console.log('✅ Fetch metadata retrieved:', metadata);
        }
        
        // Now download the actual file content
        console.log('⬇️ Step 2: Downloading file content...');
        const response = await fetch(`https://www.googleapis.com/drive/v3/files/${fileId}?alt=media`, {
            headers: {
                'Authorization': `Bearer ${accessToken}`
            }
        });
        
        console.log('📊 Download response status:', response.status);
        
        if (!response.ok) {
            const errorText = await response.text();
            console.error('❌ Download failed:', errorText);
            
            let errorMessage = `Failed to download file (${response.status})`;
            try {
                const errorData = JSON.parse(errorText);
                errorMessage = errorData.error?.message || errorMessage;
            } catch (e) {
                // Keep default message
            }
            
            throw new Error(`Download failed: ${errorMessage}\n\nPlease try again or upload the file directly.`);
        }
        
        console.log('✅ File downloaded successfully');
        
        // Convert response to Blob
        const blob = await response.blob();
        console.log('📦 Blob created:', blob.size, 'bytes');
        
        // Create File object from Blob
        const file = new File([blob], fileName, { type: mimeType });
        console.log('📁 File object created:', file.name, file.size, 'bytes');
        
        // Store as selectedFile (like a regular upload)
        selectedFile = file;
        currentUploadType = 'file';
        currentSource = 'upload'; // Treat as regular upload (not 'googledrive')
        
        // Update UI to show file selected
        if (uploadZone) {
            uploadZone.innerHTML = `
                <div style="text-align: center; padding: 30px;">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" style="margin-bottom: 20px;">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    <p style="color: #10b981; font-weight: 600; font-size: 16px; margin-bottom: 8px;">File Downloaded!</p>
                    <p style="color: #94a3b8; font-size: 14px;">${fileName}</p>
                    <p style="color: #64748b; font-size: 12px; margin-top: 8px;">${(file.size / 1024 / 1024).toFixed(2)} MB</p>
                </div>
            `;
        }
        
        // Proceed to Step 2
        setTimeout(() => {
            if (typeof window.proceedToStep2 === 'function') {
                window.proceedToStep2();
            } else if (typeof proceedToStep2 === 'function') {
                proceedToStep2();
            } else {
                console.error('❌ proceedToStep2 function not found');
            }
        }, 500);
        
    } catch (error) {
        console.error('❌ Failed to download Google Drive file:', error);
        
        // Show error message
        const uploadZone = document.getElementById('uploadZone');
        if (uploadZone) {
            uploadZone.innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" style="margin-bottom: 20px;">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="15" y1="9" x2="9" y2="15"></line>
                        <line x1="9" y1="9" x2="15" y2="15"></line>
                    </svg>
                    <p style="color: #ef4444; font-weight: 600; font-size: 16px; margin-bottom: 8px;">Download Failed</p>
                    <p style="color: #94a3b8; font-size: 14px;">${error.message}</p>
                    <button onclick="location.reload()" style="margin-top: 20px; padding: 10px 20px; background: #3b82f6; color: white; border: none; border-radius: 6px; cursor: pointer;">Try Again</button>
                </div>
            `;
        }
        
        alert('Failed to download file from Google Drive: ' + error.message);
    }
}

// Expose showGoogleDrivePicker to global scope
// This is called when Google Drive button is clicked from ionuploads.js
window.showGoogleDrivePicker = function(isArrowClick = false) {
    console.log('💾 showGoogleDrivePicker called (global function)');
    console.log('🔍 Arrow clicked:', isArrowClick);
    
    const connections = getStoredConnections();
    console.log('🔍 Stored connections:', connections.length);
    
    // SMART BEHAVIOR:
    // 1. If arrow clicked → Always show dropdown
    // 2. If 0 connections → Open OAuth
    // 3. If 1 connection → Open picker directly
    // 4. If 2+ connections → Show dropdown
    
    if (isArrowClick) {
        // Arrow clicked - always show dropdown (even if 0 connections)
        console.log('▼ Arrow clicked - showing dropdown');
        showGoogleDriveDropdown();
    } else if (connections.length === 0) {
        // No connections - open OAuth
        console.log('➕ No connections - opening OAuth');
        addNewGoogleDrive();
    } else if (connections.length === 1) {
        // 1 connection - open picker directly
        console.log('📂 One connection - opening picker directly');
        selectDrive(connections[0].email);
    } else {
        // Multiple connections - show dropdown
        console.log('📋 Multiple connections - showing dropdown');
        showGoogleDriveDropdown();
    }
};

// Initialize on DOM load
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔄 Initializing Google Drive connections...');
    updateConnectedDrivesUI();
    initializeGoogleDriveHover(); // Initialize hover behavior
    
    // Initialize Google APIs and Token Client (with retry for async script loading)
    console.log('🔄 Loading Google APIs for Token Client...');
    
    // Try immediately
    loadGoogleAPIs();
    
    // If not loaded yet (scripts still loading), retry after 1 second
    setTimeout(() => {
        if (!tokenClient) {
            console.log('🔄 Retrying Google API initialization...');
            loadGoogleAPIs();
        }
    }, 1000);
    
    // Final retry after 3 seconds
    setTimeout(() => {
        if (!tokenClient) {
            console.log('🔄 Final retry for Google API initialization...');
            loadGoogleAPIs();
        }
    }, 3000);
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