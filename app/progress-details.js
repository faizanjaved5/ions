/**
 * Detailed Progress Manager
 * Manages the detailed progress tooltip with file-by-file tracking
 */

// Global upload tracking
window.uploadTracking = {
    files: new Map(),
    startTime: null,
    totalSize: 0,
    uploadedSize: 0,
    lastUpdate: Date.now()
};

/**
 * Initialize upload tracking
 */
function initializeUploadTracking(files) {
    window.uploadTracking.files.clear();
    window.uploadTracking.startTime = Date.now();
    window.uploadTracking.totalSize = 0;
    window.uploadTracking.uploadedSize = 0;
    
    // Handle single file or array of files
    const fileArray = Array.isArray(files) ? files : [files];
    
    fileArray.forEach((file, index) => {
        const fileId = `file_${index}_${Date.now()}`;
        window.uploadTracking.files.set(fileId, {
            id: fileId,
            name: file.name,
            size: file.size,
            uploaded: 0,
            progress: 0,
            status: 'waiting',
            speed: 0
        });
        window.uploadTracking.totalSize += file.size;
    });
    
    // Update summary
    updateProgressSummary();
    
    // Render file list
    renderFileProgressList();
    
    console.log('📊 Upload tracking initialized:', window.uploadTracking);
}

/**
 * Update file progress
 */
function updateFileProgress(fileId, uploaded, total, status = 'uploading') {
    const file = window.uploadTracking.files.get(fileId);
    if (!file) return;
    
    const previousUploaded = file.uploaded;
    file.uploaded = uploaded;
    file.progress = total > 0 ? (uploaded / total) * 100 : 0;
    file.status = status;
    
    // Calculate speed
    const now = Date.now();
    const timeDiff = (now - window.uploadTracking.lastUpdate) / 1000; // seconds
    const sizeDiff = uploaded - previousUploaded;
    file.speed = timeDiff > 0 ? sizeDiff / timeDiff : 0;
    window.uploadTracking.lastUpdate = now;
    
    // Update total uploaded size
    window.uploadTracking.uploadedSize = Array.from(window.uploadTracking.files.values())
        .reduce((sum, f) => sum + f.uploaded, 0);
    
    // Update UI
    updateProgressSummary();
    updateFileProgressItem(fileId);
}

/**
 * Update progress summary
 */
function updateProgressSummary() {
    const totalFiles = window.uploadTracking.files.size;
    const totalSize = window.uploadTracking.totalSize;
    const uploadedSize = window.uploadTracking.uploadedSize;
    
    // Calculate overall speed
    const elapsed = (Date.now() - window.uploadTracking.startTime) / 1000;
    const speed = elapsed > 0 ? uploadedSize / elapsed : 0;
    
    // Calculate time remaining
    const remaining = totalSize - uploadedSize;
    const timeRemaining = speed > 0 ? remaining / speed : 0;
    
    // Update DOM
    const totalFilesEl = document.getElementById('totalFilesCount');
    const totalSizeEl = document.getElementById('totalFileSize');
    const uploadedSizeEl = document.getElementById('uploadedSize');
    const uploadSpeedEl = document.getElementById('uploadSpeed');
    const timeRemainingEl = document.getElementById('timeRemaining');
    
    if (totalFilesEl) totalFilesEl.textContent = totalFiles;
    if (totalSizeEl) totalSizeEl.textContent = formatFileSize(totalSize);
    if (uploadedSizeEl) uploadedSizeEl.textContent = formatFileSize(uploadedSize);
    if (uploadSpeedEl) uploadSpeedEl.textContent = formatFileSize(speed) + '/s';
    if (timeRemainingEl) {
        if (timeRemaining > 0 && timeRemaining < Infinity) {
            timeRemainingEl.textContent = formatTime(timeRemaining);
        } else {
            timeRemainingEl.textContent = 'Calculating...';
        }
    }
}

/**
 * Render file progress list
 */
function renderFileProgressList() {
    const listEl = document.getElementById('fileProgressList');
    if (!listEl) return;
    
    listEl.innerHTML = '';
    
    window.uploadTracking.files.forEach((file, fileId) => {
        const fileItem = createFileProgressItem(file);
        listEl.appendChild(fileItem);
    });
}

/**
 * Create file progress item element
 */
function createFileProgressItem(file) {
    const div = document.createElement('div');
    div.className = 'file-progress-item';
    div.id = `file-item-${file.id}`;
    
    const statusClass = file.status || 'waiting';
    const statusText = {
        'waiting': '⏳ Waiting',
        'uploading': '⬆️ Uploading',
        'completed': '✅ Complete',
        'failed': '❌ Failed',
        'cancelled': '🚫 Cancelled'
    }[statusClass] || '⏳ Waiting';
    
    div.innerHTML = `
        <div class="file-progress-header">
            <div class="file-name" title="${file.name}">${file.name}</div>
            <div class="file-size">${formatFileSize(file.size)}</div>
            ${file.status === 'uploading' ? `
                <button class="cancel-file-btn" onclick="cancelFileUpload('${file.id}')">
                    Cancel
                </button>
            ` : `
                <span class="file-status ${statusClass}">${statusText}</span>
            `}
        </div>
        <div class="file-progress-bar-container">
            <div class="file-progress-bar-fill" style="width: ${file.progress}%"></div>
        </div>
        <div class="file-progress-stats">
            <span>${Math.round(file.progress)}%</span>
            <span>${file.speed > 0 ? formatFileSize(file.speed) + '/s' : ''}</span>
        </div>
    `;
    
    return div;
}

/**
 * Update single file progress item
 */
function updateFileProgressItem(fileId) {
    const file = window.uploadTracking.files.get(fileId);
    if (!file) return;
    
    const itemEl = document.getElementById(`file-item-${fileId}`);
    if (!itemEl) {
        // Render if not exists
        renderFileProgressList();
        return;
    }
    
    // Update progress bar
    const progressBar = itemEl.querySelector('.file-progress-bar-fill');
    if (progressBar) {
        progressBar.style.width = `${file.progress}%`;
    }
    
    // Update stats
    const stats = itemEl.querySelector('.file-progress-stats');
    if (stats) {
        const speedText = file.speed > 0 ? formatFileSize(file.speed) + '/s' : '';
        stats.innerHTML = `
            <span>${Math.round(file.progress)}%</span>
            <span>${speedText}</span>
        `;
    }
    
    // Update status/cancel button
    const header = itemEl.querySelector('.file-progress-header');
    if (header && file.status !== 'uploading') {
        const statusClass = file.status || 'waiting';
        const statusText = {
            'waiting': '⏳ Waiting',
            'uploading': '⬆️ Uploading',
            'completed': '✅ Complete',
            'failed': '❌ Failed',
            'cancelled': '🚫 Cancelled'
        }[statusClass] || '⏳ Waiting';
        
        // Replace cancel button with status
        const cancelBtn = header.querySelector('.cancel-file-btn');
        if (cancelBtn) {
            cancelBtn.outerHTML = `<span class="file-status ${statusClass}">${statusText}</span>`;
        }
    }
}

/**
 * Cancel file upload
 */
function cancelFileUpload(fileId) {
    console.log('🚫 Cancelling upload for file:', fileId);
    
    const file = window.uploadTracking.files.get(fileId);
    if (!file) return;
    
    file.status = 'cancelled';
    updateFileProgressItem(fileId);
    
    // TODO: Implement actual upload cancellation logic
    // This would need to abort the XHR/fetch request for this specific file
    
    showCustomAlert('Upload Cancelled', `Upload cancelled for: ${file.name}`);
}

/**
 * Hide progress details tooltip
 */
function hideProgressDetails() {
    const detailsEl = document.getElementById('uploadProgressDetails');
    if (detailsEl) {
        detailsEl.style.opacity = '0';
        detailsEl.style.visibility = 'hidden';
    }
}

/**
 * Format file size
 */
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

/**
 * Format time (seconds to human readable)
 */
function formatTime(seconds) {
    if (seconds < 60) {
        return Math.round(seconds) + 's';
    } else if (seconds < 3600) {
        const minutes = Math.floor(seconds / 60);
        const secs = Math.round(seconds % 60);
        return `${minutes}m ${secs}s`;
    } else {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        return `${hours}h ${minutes}m`;
    }
}

// Expose functions globally
window.initializeUploadTracking = initializeUploadTracking;
window.updateFileProgress = updateFileProgress;
window.updateProgressSummary = updateProgressSummary;
window.cancelFileUpload = cancelFileUpload;
window.hideProgressDetails = hideProgressDetails;

console.log('✅ Progress Details Manager loaded');

