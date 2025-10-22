/**
 * Background Upload Manager
 * Visual widget for managing multiple uploads
 * Shows progress in a minimizable bottom-right widget
 */
class BackgroundUploadManager {
    constructor() {
        this.uploads = new Map();
        this.maxConcurrentUploads = 3;
        this.currentUploads = 0;
        this.pollInterval = null;
        this.initializeUI();
    }

    initializeUI() {
        // Check if already initialized
        if (document.getElementById('backgroundUploadManager')) {
            console.warn('BackgroundUploadManager already initialized');
            return;
        }

        // Create upload manager UI
        const uploadManager = document.createElement('div');
        uploadManager.id = 'backgroundUploadManager';
        uploadManager.className = 'background-upload-manager';
        uploadManager.innerHTML = `
            <div class="upload-manager-header">
                <h3>📤 Background Uploads</h3>
                <button class="minimize-btn" onclick="window.backgroundUploadManager.toggle()">−</button>
            </div>
            <div class="upload-list" id="uploadList"></div>
        `;

        // Add CSS
        const style = document.createElement('style');
        style.id = 'backgroundUploadManagerStyles';
        style.textContent = `
            .background-upload-manager {
                position: fixed;
                bottom: 20px;
                right: 20px;
                width: 380px;
                max-height: 450px;
                background: var(--bg-primary, #1e293b);
                border: 1px solid var(--border-color, #334155);
                border-radius: 12px;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
                z-index: 10000;
                display: none;
                overflow: hidden;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            }
            .background-upload-manager.visible {
                display: block;
            }
            .background-upload-manager.minimized .upload-list {
                display: none;
            }
            .upload-manager-header {
                padding: 14px 16px;
                background: var(--bg-secondary, #0f172a);
                border-bottom: 1px solid var(--border-color, #334155);
                display: flex;
                justify-content: space-between;
                align-items: center;
                cursor: move;
            }
            .upload-manager-header h3 {
                margin: 0;
                font-size: 14px;
                font-weight: 600;
                color: var(--text-primary, #f1f5f9);
            }
            .minimize-btn {
                background: none;
                border: none;
                color: var(--text-secondary, #94a3b8);
                cursor: pointer;
                font-size: 20px;
                padding: 0;
                width: 24px;
                height: 24px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 4px;
                transition: all 0.2s;
            }
            .minimize-btn:hover {
                background: var(--bg-tertiary, #1e293b);
                color: var(--text-primary, #f1f5f9);
            }
            .upload-list {
                max-height: 360px;
                overflow-y: auto;
                padding: 12px;
                scrollbar-width: thin;
                scrollbar-color: rgba(100, 116, 139, 0.5) transparent;
            }
            .upload-list::-webkit-scrollbar {
                width: 6px;
            }
            .upload-list::-webkit-scrollbar-track {
                background: transparent;
            }
            .upload-list::-webkit-scrollbar-thumb {
                background: rgba(100, 116, 139, 0.5);
                border-radius: 3px;
            }
            .upload-item {
                background: var(--bg-secondary, #0f172a);
                border-radius: 8px;
                padding: 12px;
                margin-bottom: 10px;
                border: 1px solid var(--border-color, #334155);
                transition: all 0.2s;
            }
            .upload-item:hover {
                border-color: var(--primary-color, #3b82f6);
            }
            .upload-item:last-child {
                margin-bottom: 0;
            }
            .upload-item-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 10px;
            }
            .upload-title {
                font-size: 13px;
                color: var(--text-primary, #f1f5f9);
                font-weight: 500;
                flex: 1;
                margin-right: 10px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .upload-status {
                font-size: 10px;
                color: var(--text-secondary, #94a3b8);
                text-transform: uppercase;
                font-weight: 700;
                letter-spacing: 0.5px;
                padding: 3px 8px;
                border-radius: 4px;
                background: var(--bg-tertiary, #1e293b);
            }
            .upload-status.uploading {
                color: #10b981;
                background: rgba(16, 185, 129, 0.1);
            }
            .upload-status.processing {
                color: #3b82f6;
                background: rgba(59, 130, 246, 0.1);
            }
            .upload-status.completed {
                color: #10b981;
                background: rgba(16, 185, 129, 0.1);
            }
            .upload-status.failed {
                color: #ef4444;
                background: rgba(239, 68, 68, 0.1);
            }
            .upload-status.cancelled {
                color: #94a3b8;
                background: rgba(148, 163, 184, 0.1);
            }
            .upload-progress {
                width: 100%;
                height: 5px;
                background: var(--bg-tertiary, #1e293b);
                border-radius: 3px;
                overflow: hidden;
                margin-bottom: 8px;
            }
            .upload-progress-bar {
                height: 100%;
                background: linear-gradient(90deg, #10b981, #059669);
                transition: width 0.3s ease;
                border-radius: 3px;
            }
            .upload-details {
                font-size: 11px;
                color: var(--text-muted, #64748b);
                display: flex;
                justify-content: space-between;
                margin-bottom: 6px;
            }
            .upload-speed {
                font-size: 11px;
                color: var(--text-secondary, #94a3b8);
            }
            .upload-actions {
                display: flex;
                gap: 6px;
                margin-top: 8px;
            }
            .upload-action-btn {
                background: none;
                border: 1px solid var(--border-color, #334155);
                color: var(--text-secondary, #94a3b8);
                padding: 5px 10px;
                border-radius: 4px;
                font-size: 11px;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.2s;
            }
            .upload-action-btn:hover {
                background: var(--bg-tertiary, #1e293b);
                color: var(--text-primary, #f1f5f9);
                border-color: var(--primary-color, #3b82f6);
            }
            .upload-action-btn.cancel {
                border-color: rgba(239, 68, 68, 0.5);
                color: #ef4444;
            }
            .upload-action-btn.cancel:hover {
                background: rgba(239, 68, 68, 0.1);
                border-color: #ef4444;
            }
            .upload-error {
                color: #ef4444;
                font-size: 11px;
                margin-top: 6px;
                padding: 6px 8px;
                background: rgba(239, 68, 68, 0.1);
                border-radius: 4px;
                border-left: 2px solid #ef4444;
            }
        `;
        
        if (!document.getElementById('backgroundUploadManagerStyles')) {
            document.head.appendChild(style);
        }
        
        document.body.appendChild(uploadManager);
        console.log('✅ Background Upload Manager UI initialized');
    }

    show() {
        const manager = document.getElementById('backgroundUploadManager');
        if (manager) {
            manager.classList.add('visible');
        }
    }

    hide() {
        const manager = document.getElementById('backgroundUploadManager');
        if (manager && this.uploads.size === 0) {
            manager.classList.remove('visible');
        }
    }

    toggle() {
        const manager = document.getElementById('backgroundUploadManager');
        if (manager) {
            manager.classList.toggle('minimized');
            const btn = manager.querySelector('.minimize-btn');
            if (btn) {
                btn.textContent = manager.classList.contains('minimized') ? '+' : '−';
            }
        }
    }

    addUpload(uploadId, file, metadata = {}) {
        const upload = {
            id: uploadId,
            file: file,
            metadata: metadata,
            status: 'uploading',
            progress: 0,
            startTime: Date.now(),
            speed: 0,
            error: null
        };

        this.uploads.set(uploadId, upload);
        this.currentUploads++;
        this.show();
        this.updateUploadUI(upload);
        
        return upload;
    }

    updateUpload(uploadId, updates) {
        const upload = this.uploads.get(uploadId);
        if (!upload) return;

        Object.assign(upload, updates);
        
        // Calculate speed if progress provided
        if (updates.loaded && updates.total) {
            const elapsed = (Date.now() - upload.startTime) / 1000;
            upload.speed = updates.loaded / elapsed;
        }

        this.updateUploadUI(upload);

        // Auto-remove completed uploads after delay
        if (upload.status === 'completed') {
            setTimeout(() => this.removeUpload(uploadId), 10000);
        }
    }

    updateUploadUI(upload) {
        const uploadList = document.getElementById('uploadList');
        if (!uploadList) return;

        let uploadElement = document.getElementById(`upload-${upload.id}`);

        if (!uploadElement) {
            uploadElement = document.createElement('div');
            uploadElement.id = `upload-${upload.id}`;
            uploadElement.className = 'upload-item';
            uploadList.appendChild(uploadElement);
        }

        const fileName = upload.metadata.title || upload.file.name;
        const fileSize = this.formatFileSize(upload.file.size);
        const speedText = upload.speed > 0 ? `${this.formatFileSize(upload.speed)}/s` : '';
        const percentage = Math.round(upload.progress);

        uploadElement.innerHTML = `
            <div class="upload-item-header">
                <div class="upload-title" title="${fileName}">${fileName}</div>
                <div class="upload-status ${upload.status}">${upload.status}</div>
            </div>
            <div class="upload-progress">
                <div class="upload-progress-bar" style="width: ${percentage}%"></div>
            </div>
            <div class="upload-details">
                <span>${percentage}% • ${fileSize}</span>
                ${speedText ? `<span class="upload-speed">${speedText}</span>` : ''}
            </div>
            ${upload.status === 'uploading' ? `
                <div class="upload-actions">
                    <button class="upload-action-btn cancel" onclick="window.backgroundUploadManager.cancelUpload('${upload.id}')">❌ Cancel</button>
                </div>
            ` : ''}
            ${upload.error ? `<div class="upload-error">⚠️ ${upload.error}</div>` : ''}
        `;
    }

    async cancelUpload(uploadId) {
        const upload = this.uploads.get(uploadId);
        if (!upload) return;

        upload.status = 'cancelled';
        this.updateUploadUI(upload);
        this.currentUploads--;

        // Note: Actual upload cancellation should be handled by the uploader instance
        console.log('🛑 Upload cancelled:', uploadId);
    }

    removeUpload(uploadId) {
        const uploadElement = document.getElementById(`upload-${uploadId}`);
        if (uploadElement) {
            uploadElement.style.opacity = '0';
            uploadElement.style.transform = 'translateX(20px)';
            setTimeout(() => uploadElement.remove(), 300);
        }
        
        this.uploads.delete(uploadId);
        this.currentUploads--;

        if (this.uploads.size === 0) {
            setTimeout(() => this.hide(), 500);
        }
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }
}

// Initialize global instance
if (typeof window !== 'undefined') {
    window.backgroundUploadManager = new BackgroundUploadManager();
    console.log('✅ BackgroundUploadManager initialized globally');
}

