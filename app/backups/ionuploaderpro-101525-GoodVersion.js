// ION Uploader Pro - Minimal working version
// This file provides Pro features for ionuploader.js

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
            // This handles celebration dialog, confetti, and everything else
            if (typeof showUploadSuccess === 'function') {
                console.log('🎉 Calling showUploadSuccess with result:', result);
                showUploadSuccess(result);
            } else if (typeof window.showUploadSuccess === 'function') {
                console.log('🎉 Calling window.showUploadSuccess with result:', result);
                window.showUploadSuccess(result);
            } else {
                // Fallback if function not found yet
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

console.log('✅ ION Uploader Pro initialized (Minimal version)');
console.log('✅ window.processPlatformImport is available');
