// ION Uploader Pro - Platform Import + Google Drive Integration
// This file provides Pro features for ionuploader.js

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

function showGoogleDriveDropdown() {
    const dropdown = document.getElementById('googleDriveDropdown');
    if (dropdown) {
        dropdown.style.display = 'block';
        console.log('✅ Google Drive dropdown shown');
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

function showPicker() {
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
        alert('Failed to show Google Drive picker. Please try again.');
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

console.log('✅ ION Uploader Pro initialized with Platform Import + Google Drive');
console.log('✅ window.processPlatformImport is available');
console.log('✅ window.showGoogleDrivePicker is available');

