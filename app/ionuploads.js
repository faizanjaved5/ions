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

// CSV Bulk Import State
let csvImportMode = false;
let csvData = [];
let csvFileName = '';

// Upload cancellation tracking
let uploadInProgress = false;
let currentUploadController = null; // AbortController for fetch API cancellation
let currentXHR = null; // XMLHttpRequest for cancellation (used in multipart uploads)
let currentR2Uploader = null; // R2MultipartUploader instance for cancellation

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
// CSV BULK IMPORT MODULE
// ============================================

/**
 * Check if file is a CSV
 */
function isCSVFile(file) {
    const isCSV = file.name.toLowerCase().endsWith('.csv') || file.type === 'text/csv';
    console.log(`📊 CSV Check: ${file.name} - isCSV: ${isCSV}, type: ${file.type}`);
    return isCSV;
}

/**
 * Parse CSV file content
 */
async function parseCSVFile(file) {
    console.log('📊 Parsing CSV file:', file.name);
    
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            try {
                const content = e.target.result;
                const rows = parseCSVContent(content);
                console.log(`📊 CSV parsed: ${rows.length} rows found`);
                resolve(rows);
            } catch (error) {
                console.error('❌ CSV parsing error:', error);
                reject(error);
            }
        };
        
        reader.onerror = function() {
            reject(new Error('Failed to read CSV file'));
        };
        
        reader.readAsText(file);
    });
}

/**
 * Normalize column name for flexible matching
 * Makes case-insensitive and removes spaces, dashes, underscores
 */
function normalizeColumnName(name) {
    return name.toLowerCase().replace(/[\s\-_]/g, '');
}

/**
 * Map normalized column name to canonical field name
 */
function getCanonicalFieldName(normalizedName) {
    const mapping = {
        'importurl': 'ImportURL',
        'importtitle': 'ImportTitle',
        'ioneer': 'IONEER',
        'ionnetwork': 'ION Network',
        'ionchannels': 'ION Channels',
        'visibility': 'Visibility',
        'thumbnail': 'Thumbnail',
        'thumbnailurl': 'ThumbnailURL'
    };
    
    return mapping[normalizedName] || null;
}

/**
 * Parse CSV content string into array of objects
 * Simple CSV parser that handles quoted fields
 * Now supports case-insensitive headers with flexible formatting
 */
function parseCSVContent(content) {
    const lines = content.split('\n').filter(line => line.trim());
    if (lines.length < 2) {
        throw new Error('CSV must have at least a header row and one data row');
    }
    
    // Parse header row
    const rawHeaders = parseCSVLine(lines[0]);
    console.log('📊 CSV Headers (raw):', rawHeaders);
    
    // Normalize headers and create mapping
    const headerMapping = [];
    rawHeaders.forEach((rawHeader, index) => {
        const normalized = normalizeColumnName(rawHeader.trim());
        const canonical = getCanonicalFieldName(normalized);
        
        if (canonical) {
            headerMapping.push({ index, canonical, raw: rawHeader.trim() });
            console.log(`📊 Mapped "${rawHeader.trim()}" → "${canonical}"`);
        } else {
            console.warn(`⚠️ Unknown column: "${rawHeader.trim()}" (normalized: "${normalized}")`);
        }
    });
    
    // Parse data rows
    const data = [];
    for (let i = 1; i < lines.length; i++) {
        const values = parseCSVLine(lines[i]);
        if (values.length === 0 || values.every(v => !v)) continue; // Skip empty rows
        
        const row = {};
        headerMapping.forEach(({ index, canonical }) => {
            row[canonical] = values[index] ? values[index].trim() : '';
        });
        
        data.push(row);
    }
    
    console.log(`📊 Parsed ${data.length} data rows with normalized column names`);
    return data;
}

/**
 * Parse a single CSV line handling quoted fields
 */
function parseCSVLine(line) {
    const result = [];
    let current = '';
    let inQuotes = false;
    
    for (let i = 0; i < line.length; i++) {
        const char = line[i];
        const nextChar = line[i + 1];
        
        if (char === '"') {
            if (inQuotes && nextChar === '"') {
                // Escaped quote
                current += '"';
                i++; // Skip next quote
            } else {
                // Toggle quote mode
                inQuotes = !inQuotes;
            }
        } else if (char === ',' && !inQuotes) {
            // Field separator
            result.push(current);
            current = '';
        } else {
            current += char;
        }
    }
    
    // Add last field
    result.push(current);
    
    return result;
}

/**
 * Validate CSV row structure
 */
function validateCSVRow(row, index) {
    const errors = [];
    const warnings = [];
    
    // Check required field: ImportURL
    if (!row.ImportURL || !row.ImportURL.trim()) {
        errors.push(`Row ${index + 1}: ImportURL is required`);
    } else {
        // Validate URL format
        const url = row.ImportURL.trim().toLowerCase();
        const validPlatforms = ['youtube.com', 'youtu.be', 'vimeo.com', 'muvi.com', 'loom.com', 'rumble.com', 'wistia.com'];
        const isValidPlatform = validPlatforms.some(platform => url.includes(platform));
        
        if (!isValidPlatform) {
            errors.push(`Row ${index + 1}: ImportURL must be from YouTube, Vimeo, Muvi, Loom, Rumble, or Wistia`);
        }
    }
    
    // Check ImportTitle length if provided
    if (row.ImportTitle && row.ImportTitle.length > 100) {
        warnings.push(`Row ${index + 1}: ImportTitle exceeds 100 characters (will be truncated)`);
    }
    
    // Validate IONEER format (strip @ if present)
    if (row.IONEER) {
        const ioneer = row.IONEER.trim();
        if (ioneer.startsWith('@')) {
            row.IONEER = ioneer.substring(1); // Strip @
        }
    }
    
    // Validate Visibility values
    if (row.Visibility) {
        const validVisibility = ['Public', 'Private', 'Unlisted'];
        if (!validVisibility.includes(row.Visibility)) {
            warnings.push(`Row ${index + 1}: Invalid Visibility "${row.Visibility}", will default to Public`);
            row.Visibility = 'Public';
        }
    } else {
        row.Visibility = 'Public'; // Default
    }
    
    return {
        valid: errors.length === 0,
        errors,
        warnings,
        row
    };
}

/**
 * Validate entire CSV data
 */
function validateCSVData(data) {
    console.log('📊 Validating CSV data...');
    
    const results = data.map((row, index) => validateCSVRow(row, index));
    
    const validRows = results.filter(r => r.valid);
    const invalidRows = results.filter(r => !r.valid);
    const allWarnings = results.flatMap(r => r.warnings);
    
    console.log(`📊 Validation complete: ${validRows.length} valid, ${invalidRows.length} invalid`);
    
    return {
        valid: invalidRows.length === 0,
        validRows: validRows.map(r => r.row),
        invalidRows: invalidRows.map(r => ({ row: r.row, errors: r.errors })),
        warnings: allWarnings,
        totalRows: data.length
    };
}

/**
 * Handle CSV file import
 */
async function handleCSVImport(file) {
    console.log('📊 Starting CSV import for:', file.name);
    
    try {
        // Parse CSV file
        const parsedData = await parseCSVFile(file);
        
        // Validate CSV data
        const validation = validateCSVData(parsedData);
        
        if (validation.invalidRows.length > 0) {
            // Show validation errors
            showCSVValidationErrors(validation);
            return;
        }
        
        // Store CSV data globally
        csvImportMode = true;
        csvData = validation.validRows;
        csvFileName = file.name;
        bulkMode = false; // CSV import is different from bulk file upload
        
        console.log(`📊 CSV validated: ${csvData.length} valid videos to import`);
        
        // SECURITY CHECK: Warn regular users if IONEER column is present
        const hasIONEERColumn = validation.validRows.some(row => row.IONEER && row.IONEER.trim());
        const isAdminOrOwner = typeof userRole !== 'undefined' && 
            ['Owner', 'Admin', 'Administrator', 'Super Admin'].includes(userRole);
        
        if (hasIONEERColumn && !isAdminOrOwner) {
            console.warn('🔒 IONEER column detected but user is not Admin/Owner - values will be ignored');
            alert('⚠️ Security Notice:\n\nYour CSV contains an "IONEER" column, but only Admins and Owners can assign videos to other users.\n\nAll imported videos will be assigned to YOUR profile instead.');
        }
        
        // Show CSV preview in Step 1
        showCSVPreview(file, validation);
        
        // Enable next button
        checkNextButton();
        
        // Auto-proceed to CSV Step 2 after a moment
        setTimeout(() => {
            proceedToCSVStep2();
        }, 800);
        
    } catch (error) {
        console.error('❌ CSV import error:', error);
        alert(`CSV Import Error:\n\n${error.message}\n\nPlease check your CSV file format.`);
    }
}

/**
 * Show CSV validation errors
 */
function showCSVValidationErrors(validation) {
    const errorMessages = validation.invalidRows.map(item => {
        return item.errors.join('\n');
    }).join('\n\n');
    
    alert(`CSV Validation Failed:\n\n${errorMessages}\n\nPlease fix these errors and try again.`);
}

/**
 * Show CSV preview in Step 1
 */
function showCSVPreview(file, validation) {
    console.log('📊 Showing CSV preview');
    
    const uploadZone = document.getElementById('uploadZone');
    if (!uploadZone) return;
    
    const fileSize = (file.size / 1024).toFixed(2);
    const warningsHTML = validation.warnings.length > 0 
        ? `<div style="margin-top: 12px; padding: 10px; background: rgba(251, 191, 36, 0.1); border: 1px solid rgba(251, 191, 36, 0.3); border-radius: 6px;">
            <div style="color: #f59e0b; font-size: 13px; font-weight: 600; margin-bottom: 6px;">⚠️ Warnings:</div>
            <div style="color: #fbbf24; font-size: 12px; line-height: 1.6;">${validation.warnings.join('<br>')}</div>
           </div>`
        : '';
    
    uploadZone.innerHTML = `
        <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" style="margin-bottom: 12px;">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
            <polyline points="14 2 14 8 20 8"></polyline>
            <line x1="12" y1="18" x2="12" y2="12"></line>
            <line x1="9" y1="15" x2="15" y2="15"></line>
        </svg>
        <h3 style="color: #10b981; margin: 0 0 8px 0; font-size: 20px;">📊 CSV File Detected!</h3>
        <p style="color: #e2e8f0; margin: 0 0 8px 0; font-size: 15px; font-weight: 500;">${file.name}</p>
        <p style="color: #94a3b8; font-size: 13px; margin: 0 0 12px 0;">
            ${fileSize} KB &bull; ${validation.validRows.length} video${validation.validRows.length !== 1 ? 's' : ''} to import
        </p>
        <div style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: rgba(59, 130, 246, 0.15); border-radius: 8px; margin-bottom: 8px;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="7 10 12 15 17 10"></polyline>
                <line x1="12" y1="15" x2="12" y2="3"></line>
            </svg>
            <span style="color: #3b82f6; font-size: 14px; font-weight: 600;">Bulk Import Mode</span>
        </div>
        ${warningsHTML}
        <p style="color: #64748b; font-size: 12px; margin: 12px 0 0 0;">
            Click "Next" to review and import videos
        </p>
    `;
}

/**
 * Proceed to CSV Step 2
 */
function proceedToCSVStep2() {
    console.log('📊 Proceeding to CSV Step 2');
    
    // Hide Step 1 and bulkStep2
    const step1 = document.getElementById('step1');
    const step2 = document.getElementById('step2');
    const bulkStep2 = document.getElementById('bulkStep2');
    const csvStep2 = document.getElementById('csvStep2');
    
    if (step1) step1.style.display = 'none';
    if (step2) step2.style.display = 'none';
    if (bulkStep2) bulkStep2.style.display = 'none';
    
    // Show CSV Step 2
    if (csvStep2) {
        csvStep2.style.display = 'block';
        console.log('✅ CSV Step 2 visible');
    }
    
    // Update footer buttons
    const closeBtn = document.getElementById('closeBtn');
    const backBtn = document.getElementById('backBtn');
    const nextBtn = document.getElementById('nextBtn');
    const nextBtnText = document.getElementById('nextBtnText');
    
    if (closeBtn) closeBtn.style.display = 'none';
    if (backBtn) backBtn.style.display = 'flex';
    if (nextBtn) {
        nextBtn.style.display = 'flex';
        nextBtn.disabled = false;
    }
    if (nextBtnText) nextBtnText.textContent = 'Import All Videos';
    
    // Populate CSV Step 2 UI
    populateCSVStep2();
}

/**
 * Populate CSV Step 2 with data
 */
function populateCSVStep2() {
    console.log('📊 Populating CSV Step 2 with', csvData.length, 'videos');
    
    // Update header info
    const csvFileNameDisplay = document.getElementById('csvFileNameDisplay');
    const csvTotalCount = document.getElementById('csvTotalCount');
    const csvImportCount = document.getElementById('csvImportCount');
    
    if (csvFileNameDisplay) csvFileNameDisplay.textContent = csvFileName;
    if (csvTotalCount) csvTotalCount.textContent = csvData.length;
    if (csvImportCount) csvImportCount.textContent = csvData.length;
    
    // Populate import list
    const csvImportList = document.getElementById('csvImportList');
    if (!csvImportList) {
        console.error('❌ csvImportList element not found');
        return;
    }
    
    csvImportList.innerHTML = csvData.map((row, index) => createCSVImportCard(row, index)).join('');
    
    // Setup character counters for title inputs
    csvData.forEach((row, index) => {
        const titleInput = document.getElementById(`csvTitle_${index}`);
        const titleCount = document.getElementById(`csvTitleCount_${index}`);
        
        if (titleInput && titleCount) {
            const updateCharCount = () => {
                const currentLength = titleInput.value.length;
                titleCount.textContent = currentLength;
                
                // Color feedback
                const charCountSpan = titleCount.parentElement;
                if (currentLength > 90) {
                    charCountSpan.style.color = '#ef4444'; // Red warning
                } else if (currentLength > 70) {
                    charCountSpan.style.color = '#f59e0b'; // Orange warning
                } else {
                    charCountSpan.style.color = '#94a3b8'; // Default gray
                }
            };
            
            titleInput.addEventListener('input', updateCharCount);
            titleInput.addEventListener('keyup', updateCharCount);
            titleInput.addEventListener('paste', () => setTimeout(updateCharCount, 10));
            
            updateCharCount();
        }
    });
    
    console.log('✅ CSV import list populated with character counters');
    
    // Fetch thumbnails for all CSV rows
    fetchCSVThumbnails();
}

/**
 * Fetch thumbnails for CSV import rows
 */
async function fetchCSVThumbnails() {
    console.log('🖼️ Fetching thumbnails for CSV imports...');
    
    for (let index = 0; index < csvData.length; index++) {
        const row = csvData[index];
        const url = row.ImportURL;
        const thumbnailURL = row.ThumbnailURL || row.Thumbnail; // Support both column names
        
        try {
            let thumbnailUrl = null;
            
            // Try ThumbnailURL first if provided
            if (thumbnailURL && thumbnailURL.trim()) {
                console.log(`🖼️ [${index}] Fetching custom thumbnail from:`, thumbnailURL);
                thumbnailUrl = await fetchThumbnailFromURL(thumbnailURL, index);
            }
            
            // If no custom thumbnail or fetch failed, get from platform
            if (!thumbnailUrl) {
                console.log(`🖼️ [${index}] Fetching thumbnail from platform for:`, url);
                thumbnailUrl = await fetchPlatformThumbnail(url, index);
            }
            
            if (thumbnailUrl) {
                updateCSVThumbnail(index, thumbnailUrl);
                // Store the thumbnail URL with the CSV row data
                csvData[index].fetchedThumbnailUrl = thumbnailUrl;
            }
        } catch (error) {
            console.error(`❌ Failed to fetch thumbnail for row ${index}:`, error);
        }
    }
    
    console.log('✅ CSV thumbnail fetching complete');
}

/**
 * Fetch thumbnail from custom URL
 */
async function fetchThumbnailFromURL(thumbnailURL, index) {
    try {
        // Test if the URL is accessible by trying to load it as an image
        const img = new Image();
        
        return new Promise((resolve, reject) => {
            img.onload = () => {
                console.log(`✅ [${index}] Custom thumbnail loaded successfully`);
                resolve(thumbnailURL);
            };
            
            img.onerror = () => {
                console.warn(`⚠️ [${index}] Custom thumbnail failed to load, will try platform`);
                resolve(null); // Return null to trigger platform fallback
            };
            
            // Set timeout for loading
            setTimeout(() => {
                console.warn(`⚠️ [${index}] Custom thumbnail load timeout`);
                resolve(null);
            }, 5000);
            
            img.src = thumbnailURL;
        });
    } catch (error) {
        console.error(`❌ [${index}] Error fetching custom thumbnail:`, error);
        return null;
    }
}

/**
 * Fetch thumbnail from platform (YouTube, Vimeo, etc.)
 */
async function fetchPlatformThumbnail(url, index) {
    const platform = detectPlatform(url);
    
    try {
        if (platform === 'YouTube') {
            // Extract YouTube video ID
            const videoId = extractYouTubeId(url);
            if (videoId) {
                // Try maxresdefault first, fall back to hqdefault
                const maxResUrl = `https://img.youtube.com/vi/${videoId}/maxresdefault.jpg`;
                const hqUrl = `https://img.youtube.com/vi/${videoId}/hqdefault.jpg`;
                
                // Test maxresdefault
                const isMaxResValid = await testImageUrl(maxResUrl);
                return isMaxResValid ? maxResUrl : hqUrl;
            }
        } else if (platform === 'Vimeo') {
            // Extract Vimeo video ID and fetch from API
            const videoId = extractVimeoId(url);
            if (videoId) {
                const response = await fetch(`https://vimeo.com/api/v2/video/${videoId}.json`);
                if (response.ok) {
                    const data = await response.json();
                    return data[0]?.thumbnail_large || data[0]?.thumbnail_medium;
                }
            }
        }
        // Add more platforms as needed
        
        return null;
    } catch (error) {
        console.error(`❌ [${index}] Error fetching platform thumbnail:`, error);
        return null;
    }
}

/**
 * Test if an image URL is valid
 */
async function testImageUrl(url) {
    return new Promise((resolve) => {
        const img = new Image();
        img.onload = () => resolve(true);
        img.onerror = () => resolve(false);
        setTimeout(() => resolve(false), 3000);
        img.src = url;
    });
}

/**
 * Extract YouTube video ID from URL
 */
function extractYouTubeId(url) {
    const patterns = [
        /(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\?\/]+)/,
        /youtube\.com\/embed\/([^&\?\/]+)/,
        /youtube\.com\/v\/([^&\?\/]+)/
    ];
    
    for (const pattern of patterns) {
        const match = url.match(pattern);
        if (match && match[1]) {
            return match[1];
        }
    }
    return null;
}

/**
 * Extract Vimeo video ID from URL
 */
function extractVimeoId(url) {
    const match = url.match(/vimeo\.com\/(\d+)/);
    return match ? match[1] : null;
}

/**
 * Update CSV thumbnail in UI
 */
function updateCSVThumbnail(index, thumbnailUrl) {
    // Update collapsed view thumbnail
    const thumbnailImage = document.getElementById(`csvThumbnailImage_${index}`);
    const thumbnailPlaceholder = document.getElementById(`csvThumbnailPlaceholder_${index}`);
    
    if (thumbnailImage && thumbnailUrl) {
        thumbnailImage.src = thumbnailUrl;
        thumbnailImage.style.display = 'block';
        if (thumbnailPlaceholder) {
            thumbnailPlaceholder.style.display = 'none';
        }
    }
    
    // Update expanded view thumbnail
    const expandedImage = document.getElementById(`csvExpandedThumbnailImage_${index}`);
    const expandedPlaceholder = document.getElementById(`csvExpandedThumbnailPlaceholder_${index}`);
    
    if (expandedImage && thumbnailUrl) {
        expandedImage.src = thumbnailUrl;
        expandedImage.style.display = 'block';
        if (expandedPlaceholder) {
            expandedPlaceholder.style.display = 'none';
        }
    }
    
    // Update status text
    const statusText = document.getElementById(`csvThumbnailStatus_${index}`);
    if (statusText) {
        statusText.textContent = '✅ Thumbnail loaded';
        statusText.style.color = '#10b981';
    }
    
    console.log(`✅ [${index}] Thumbnail updated in UI`);
}

/**
 * Toggle CSV card expand/collapse
 */
function toggleCSVCardExpand(index) {
    const card = document.querySelector(`[data-csv-index="${index}"]`);
    if (!card) return;
    
    const isExpanded = card.dataset.expanded === 'true';
    const collapsedView = document.getElementById(`csvCardCollapsed_${index}`);
    const expandedView = document.getElementById(`csvCardExpanded_${index}`);
    const expandArrow = document.getElementById(`csvExpandArrow_${index}`);
    
    if (isExpanded) {
        // Collapse
        card.dataset.expanded = 'false';
        if (expandedView) expandedView.style.display = 'none';
        if (expandArrow) expandArrow.textContent = '▼';
    } else {
        // Expand
        card.dataset.expanded = 'true';
        if (expandedView) expandedView.style.display = 'block';
        if (expandArrow) expandArrow.textContent = '▲';
    }
}

// Make function globally available
window.toggleCSVCardExpand = toggleCSVCardExpand;

/**
 * Remove CSV row from import list
 */
function removeCSVRow(index) {
    console.log('📊 Removing CSV row', index);
    
    // Remove from csvData array
    csvData.splice(index, 1);
    
    // Update count
    const csvTotalCount = document.getElementById('csvTotalCount');
    const csvImportCount = document.getElementById('csvImportCount');
    if (csvTotalCount) csvTotalCount.textContent = csvData.length;
    if (csvImportCount) csvImportCount.textContent = csvData.length;
    
    // Re-populate list (this will re-index everything correctly)
    populateCSVStep2();
    
    console.log('✅ CSV row removed, list updated');
}

// Make function globally available
window.removeCSVRow = removeCSVRow;

/**
 * Create a CSV import card matching bulk upload UI
 */
function createCSVImportCard(row, index) {
    const url = row.ImportURL || '';
    const title = row.ImportTitle || extractTitleFromURL(url);
    const ioneer = row.IONEER || '';
    const network = row['ION Network'] || '';
    const channels = row['ION Channels'] || '';
    const visibility = row.Visibility || 'Public';
    const thumbnailURL = row.ThumbnailURL || row.Thumbnail || ''; // Support both column names
    
    // Determine platform from URL
    const platform = detectPlatform(url);
    const platformColor = {
        'YouTube': '#ff0000',
        'Vimeo': '#1ab7ea',
        'Muvi': '#7c3aed',
        'Loom': '#625df5',
        'Rumble': '#85c742',
        'Wistia': '#54bbff'
    }[platform] || '#64748b';
    
    const platformIcon = {
        'YouTube': '▶',
        'Vimeo': 'V',
        'Muvi': 'M',
        'Loom': 'L',
        'Rumble': 'R',
        'Wistia': 'W'
    }[platform] || '📹';
    
    return `
        <div class="bulk-file-card-improved" data-csv-index="${index}" data-expanded="false">
            <!-- Collapsed View -->
            <div class="bulk-card-collapsed" id="csvCardCollapsed_${index}">
                <div class="bulk-card-left">
                    <div class="bulk-card-thumbnail" id="csvThumbnail_${index}">
                        <div class="thumbnail-placeholder" style="background: ${platformColor}; color: white; font-size: 24px;" id="csvThumbnailPlaceholder_${index}">
                            ${platformIcon}
                        </div>
                        <img src="" alt="Thumbnail" class="thumbnail-image" id="csvThumbnailImage_${index}" style="display: none; width: 100%; height: 100%; object-fit: cover; border-radius: 8px;">
                    </div>
                </div>
                
                <div class="bulk-card-center">
                    <div class="bulk-card-title-wrapper">
                        <input type="text" 
                               class="bulk-card-title-input csv-field-input" 
                               id="csvTitle_${index}" 
                               data-csv-index="${index}"
                               data-csv-field="ImportTitle"
                               value="${title}"
                               maxlength="100"
                               placeholder="Enter video title">
                        <span class="bulk-card-char-count">
                            <span id="csvTitleCount_${index}">${title.length}</span>/100
                        </span>
                    </div>
                    <div class="bulk-card-filename">
                        <span style="color: ${platformColor}; font-weight: 600;">${platform}</span>
                        ${ioneer ? ` • @${ioneer}` : ''}
                        ${channels ? ` • ${channels.split(',').length} channel(s)` : ''}
                    </div>
                    
                    <!-- Import progress (shown during import) -->
                    <div class="bulk-card-progress" id="csvImportProgress_${index}" style="display: none;">
                        <div class="bulk-card-progress-bar">
                            <div class="bulk-card-progress-fill" id="csvImportProgressBar_${index}"></div>
                        </div>
                        <span class="bulk-card-progress-text" id="csvImportProgressText_${index}">Importing...</span>
                    </div>
                </div>
                
                <div class="bulk-card-right">
                    <button type="button" 
                            class="bulk-card-btn-close" 
                            onclick="removeCSVRow(${index})"
                            title="Remove from import">
                        ✕
                    </button>
                    <button type="button" 
                            class="bulk-card-btn-expand" 
                            onclick="toggleCSVCardExpand(${index})"
                            title="Expand details">
                        <span class="expand-arrow" id="csvExpandArrow_${index}">▼</span>
                    </button>
                </div>
            </div>
            
            <!-- Expanded View -->
            <div class="bulk-card-expanded" id="csvCardExpanded_${index}" style="display: none;">
                <div class="bulk-expanded-content">
                    <div class="bulk-expanded-left">
                        <div class="bulk-expanded-label">Import URL</div>
                        <input type="text" 
                               class="csv-field-input" 
                               data-csv-index="${index}" 
                               data-csv-field="ImportURL"
                               value="${url}"
                               placeholder="https://..."
                               style="width: 100%; background: #1e293b; border: 1px solid #334155; color: #e2e8f0; padding: 10px 14px; border-radius: 6px; font-size: 0.875rem; margin-bottom: 16px;">
                        
                        <div class="bulk-expanded-label">ION Network Category</div>
                        <input type="text" 
                               class="csv-field-input" 
                               data-csv-index="${index}" 
                               data-csv-field="ION Network"
                               value="${network}" 
                               placeholder="e.g. AVL on ION, ION Sports Network"
                               style="width: 100%; background: #1e293b; border: 1px solid #334155; color: #e2e8f0; padding: 10px 14px; border-radius: 6px; font-size: 0.875rem;">
                    </div>
                    
                    <div class="bulk-expanded-middle">
                        <div class="bulk-expanded-label">Video Thumbnail</div>
                        <div class="bulk-expanded-thumbnail" id="csvExpandedThumbnail_${index}">
                            <div class="thumbnail-placeholder-large" id="csvExpandedThumbnailPlaceholder_${index}" style="background: ${platformColor}; color: white; font-size: 48px; display: flex; align-items: center; justify-content: center;">
                                ${platformIcon}
                            </div>
                            <img src="" alt="Thumbnail" class="thumbnail-image-large" id="csvExpandedThumbnailImage_${index}" style="display: none; width: 100%; height: 100%; object-fit: cover; border-radius: 8px;">
                        </div>
                        <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 8px; text-align: center;" id="csvThumbnailStatus_${index}">
                            ${thumbnailURL ? 'Fetching custom thumbnail...' : 'Fetching thumbnail from ' + platform + '...'}
                        </div>
                    </div>
                    
                    <div class="bulk-expanded-right">
                        <div class="bulk-expanded-label">ION Channels (Comma-separated)</div>
                        <input type="text" 
                               class="csv-field-input" 
                               data-csv-index="${index}" 
                               data-csv-field="ION Channels"
                               value="${channels}" 
                               placeholder="e.g. ion-orlando, ion-manhattan-beach"
                               style="width: 100%; background: #1e293b; border: 1px solid #334155; color: #e2e8f0; padding: 10px 14px; border-radius: 6px; font-size: 0.875rem; margin-bottom: 16px;">
                        
                        <div class="bulk-expanded-label">Visibility</div>
                        <select class="csv-field-input" 
                                data-csv-index="${index}" 
                                data-csv-field="Visibility"
                                style="width: 100%; background: #1e293b; border: 1px solid #334155; color: #e2e8f0; padding: 10px 14px; border-radius: 6px; font-size: 0.875rem; margin-bottom: 16px;">
                            <option value="Public" ${visibility === 'Public' ? 'selected' : ''}>Public</option>
                            <option value="Private" ${visibility === 'Private' ? 'selected' : ''}>Private</option>
                            <option value="Unlisted" ${visibility === 'Unlisted' ? 'selected' : ''}>Unlisted</option>
                        </select>
                        
                        ${ioneer ? `
                        <div class="bulk-expanded-label">Assigned to IONEER</div>
                        <div style="padding: 10px 14px; background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 6px; color: #3b82f6; font-weight: 500;">
                            @${ioneer}
                        </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        </div>
    `;
}

/**
 * Extract title from URL (fallback)
 */
function extractTitleFromURL(url) {
    try {
        const urlObj = new URL(url);
        const path = urlObj.pathname;
        const parts = path.split('/').filter(p => p);
        return parts[parts.length - 1] || 'Imported Video';
    } catch {
        return 'Imported Video';
    }
}

/**
 * Detect platform from URL
 */
function detectPlatform(url) {
    const lowerUrl = url.toLowerCase();
    if (lowerUrl.includes('youtube.com') || lowerUrl.includes('youtu.be')) return 'YouTube';
    if (lowerUrl.includes('vimeo.com')) return 'Vimeo';
    if (lowerUrl.includes('muvi.com')) return 'Muvi';
    if (lowerUrl.includes('loom.com')) return 'Loom';
    if (lowerUrl.includes('rumble.com')) return 'Rumble';
    if (lowerUrl.includes('wistia.com')) return 'Wistia';
    return 'Unknown';
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
    
    // CHECK FOR CSV FILE - Handle CSV imports separately
    if (files.length === 1 && isCSVFile(files[0])) {
        console.log('📊 CSV file detected, switching to CSV import mode');
        handleCSVImport(files[0]);
        return;
    }
    
    // Validate all files
    const validFiles = files.filter(validateMedia);
    console.log('📁 Valid files after filtering:', validFiles.length);
    if (validFiles.length === 0) {
        console.log('📁 No valid files found');
        return;
    }
    
    // CRITICAL: If already in bulk mode, append new files instead of switching to single mode
    if (bulkMode && selectedFiles.length > 0) {
        console.log('📁 Already in bulk mode - appending new files to existing selection');
        selectedFiles = [...selectedFiles, ...validFiles];
        console.log(`📁 Total files now: ${selectedFiles.length}`);
        
        // Refresh the bulk file list to show new files
        showBulkUploadInterface();
        return;
    }
    
    if (validFiles.length === 1) {
        selectedFile = validFiles[0];
        currentUploadType = 'file'; // Critical: Set upload type when file is selected
        currentSource = 'local';
        bulkMode = false;
        
        // CRITICAL FIX: Clear any cached thumbnail from previous uploads
        window.capturedThumbnailBlob = null;
        window.customThumbnailSource = null;
        window.currentThumbnailUrl = null;
        console.log('🧹 Cleared cached thumbnail for fresh upload');
        
        // Also clear thumbnail preview UI to prevent cached image from showing
        const thumbnailPreview = document.getElementById('thumbnailPreview');
        const thumbnailPlaceholder = document.getElementById('thumbnailPlaceholder');
        if (thumbnailPreview) {
            thumbnailPreview.src = '';
            thumbnailPreview.style.display = 'none';
            console.log('🧹 Cleared thumbnail preview image');
        }
        if (thumbnailPlaceholder) {
            thumbnailPlaceholder.style.display = 'block';
            console.log('🧹 Showing thumbnail placeholder');
        }
        
        console.log('📁 Single file selected:', selectedFile.name);
        
        // Show file selection feedback
        const uploadZone = document.getElementById('uploadZone');
        if (uploadZone) {
            // CRITICAL: Preserve the file input element before replacing
            const fileInput = document.getElementById('fileInput');
            
            // CRITICAL: Clone and replace the element to remove ALL old event listeners
            const newUploadZone = uploadZone.cloneNode(false);
            uploadZone.parentNode.replaceChild(newUploadZone, uploadZone);
            
            // Apply green success styling directly to uploadZone (no nested container)
            newUploadZone.style.border = '2px dashed #10b981';
            newUploadZone.style.borderRadius = '12px';
            newUploadZone.style.background = 'rgba(16, 185, 129, 0.05)';
            newUploadZone.style.transition = 'all 0.2s ease';
            newUploadZone.style.cursor = 'pointer';
            newUploadZone.style.padding = '24px';
            newUploadZone.title = 'Click to select a different file';
            
            // Set content without nested div wrapper
            newUploadZone.innerHTML = `
                <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" style="margin-bottom: 12px;">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22,4 12,14.01 9,11.01"></polyline>
                </svg>
                <h3 style="color: #10b981; margin: 0 0 8px 0; font-size: 20px;">✅ File Selected!</h3>
                <p style="color: #e2e8f0; margin: 0 0 8px 0; font-size: 15px; font-weight: 500;">${selectedFile.name}</p>
                <p style="color: #94a3b8; font-size: 13px; margin: 0 0 12px 0;">
                    ${(selectedFile.size / (1024 * 1024)).toFixed(2)} MB
                </p>
                <div style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; background: rgba(16, 185, 129, 0.15); border-radius: 8px; margin-bottom: 8px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                    <span style="color: #10b981; font-size: 13px; font-weight: 500;">Click here to change file</span>
                </div>
                <p style="color: #64748b; font-size: 12px; margin: 8px 0 0 0;">
                    Or use "Back" button from Step 2 to select a different file
                </p>
            `;
            
            // CRITICAL: Re-add the file input element (it was removed when we replaced uploadZone)
            if (fileInput) {
                newUploadZone.insertBefore(fileInput, newUploadZone.firstChild);
                console.log('✅ File input element preserved and re-inserted');
            }
            
            // Add hover effects with fresh listeners
            newUploadZone.addEventListener('mouseenter', () => {
                newUploadZone.style.background = 'rgba(16, 185, 129, 0.1)';
                newUploadZone.style.borderColor = '#059669';
            });
            newUploadZone.addEventListener('mouseleave', () => {
                newUploadZone.style.background = 'rgba(16, 185, 129, 0.05)';
                newUploadZone.style.borderColor = '#10b981';
            });
            
            // Attach fresh click handler to open file selector
            newUploadZone.addEventListener('click', (e) => {
                // Don't trigger if clicking on the file input itself
                if (e.target.id === 'fileInput') return;
                
                e.preventDefault();
                e.stopPropagation();
                console.log('📁 Green area clicked - opening file selector to change file');
                
                if (fileInput) {
                    // CRITICAL: Reset the file input value so user can select the same file again
                    fileInput.value = '';
                    console.log('🔄 File input reset - triggering click');
                    
                    // Trigger click
                    fileInput.click();
                    console.log('✅ File input click() called');
                } else {
                    console.error('❌ File input not found!');
                }
            });
            
            console.log('✅ Upload zone replaced with fresh element and click handler attached');
        }
        
        checkNextButton(); // Enable Next button
        
        // Auto-populate title field with file name (without extension)
        autoPopulateTitle(validFiles[0].name);
        
        // Auto-proceed to Step 2 after file selection
        console.log('📁 Single file selected, auto-proceeding to step 2');
        setTimeout(() => {
            proceedToStep2();
            
            // Generate auto thumbnail after step 2 is loaded (DOM elements are ready)
            setTimeout(() => {
                console.log('🖼️ Attempting to generate auto thumbnail for uploaded file');
                generateAutoThumbnail();
            }, 1000); // Delay to ensure video element is loaded
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
        
        // Auto-proceed to bulk Step 2 after file selection
        console.log('📁 Multiple files selected, auto-proceeding to bulk step 2');
        setTimeout(() => {
            proceedToNextStep(); // This will detect bulkMode and call proceedToBulkStep2()
        }, 800); // Slightly longer delay to ensure bulk thumbnails are captured
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
            selected_channels: document.getElementById('selectedChannels')?.value || '', // CRITICAL: Include selected channels
            selected_networks: getSelectedNetworksString() // Include selected networks
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
    hideProgress();
    
    // DON'T send upload_complete - let celebration dialog handle it
    showUploadSuccess(result);
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
    hideProgress();
    
    // DON'T send upload_complete - let celebration dialog handle it
    showUploadSuccess(result);
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

// Auto-detect platform from URL
function detectPlatformFromUrl(url) {
    if (!url || url.trim() === '') return null;
    
    const patterns = {
        youtube: /(?:youtube\.com\/(?:watch\?.*v=|shorts\/)|youtu\.be\/)/,
        vimeo: /vimeo\.com/,
        wistia: /(?:wistia\.(?:com|net)\/|wi\.st\/)/,
        loom: /loom\.com\/share\//,
        muvi: /muvi/i
    };
    
    // Check each platform pattern
    for (const [platform, pattern] of Object.entries(patterns)) {
        if (pattern.test(url)) {
            console.log(`✅ Auto-detected platform: ${platform} from URL:`, url);
            return platform;
        }
    }
    
    console.log('❌ No platform detected from URL:', url);
    return null;
}

// Handle URL input change with auto-platform detection
function handleUrlInputChange() {
    const urlInput = document.getElementById('urlInput');
    if (!urlInput) return;
    
    const url = urlInput.value.trim();
    console.log('🔗 URL input changed:', url);
    
    // Auto-detect platform from URL
    const detectedPlatform = detectPlatformFromUrl(url);
    
    if (detectedPlatform) {
        // Auto-select the detected platform
        console.log('🎯 Auto-selecting platform:', detectedPlatform);
        selectPlatform(detectedPlatform);
        
        // CRITICAL: Also auto-select import type when platform is detected
        if (currentUploadType !== 'import') {
            console.log('📥 Auto-selecting import type due to URL entry');
            currentUploadType = 'import';
            document.getElementById('importOption')?.classList.add('selected');
            document.getElementById('uploadOption')?.classList.remove('selected');
        }
    } else if (url === '') {
        // Clear selection if URL is cleared
        currentSource = null;
        currentUploadType = null;
        
        // Remove selected state from all buttons
        document.querySelectorAll('.source-btn').forEach(btn => {
            btn.classList.remove('selected');
        });
        document.querySelectorAll('.upload-option').forEach(option => {
            option.classList.remove('selected');
        });
        console.log('✅ Cleared upload type and platform selection');
    }
    
    // Check if Next button should be enabled
    checkNextButton();
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
    console.log('📍 DEBUG proceedToStep2: currentUploadType =', currentUploadType);
    console.log('📍 DEBUG proceedToStep2: currentSource =', currentSource);
    console.log('📍 DEBUG proceedToStep2: importedVideoUrl =', window.importedVideoUrl);
    
    // Notify parent that user is entering data (warn if modal closes)
    if (window.parent && window.parent !== window) {
        window.parent.postMessage({ type: 'data_entered' }, '*');
    }
    
    // CRITICAL: Scroll modal to top when transitioning to Step 2 (prevents Step 1 from peeking through)
    const modalBody = document.querySelector('.modal-body');
    if (modalBody) {
        modalBody.scrollTop = 0;
        console.log('✅ Modal scrolled to top');
    }
    
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
        
        // Check if mobile or desktop
        const isMobile = window.innerWidth <= 768;
        
        if (isMobile) {
            // Mobile: Use flexbox single column layout
            console.log('📱 Mobile layout detected - using flex column');
            step2.style.setProperty('display', 'flex', 'important');
            step2.style.setProperty('flex-direction', 'column', 'important');
            step2.style.setProperty('gap', '0', 'important');
            step2.style.setProperty('padding', '0', 'important');
            
        // Initialize mobile tabs
        setTimeout(() => {
            console.log('📱 Initializing mobile tabs for Step 2');
            forceMobileLayout();
            initializeMobileTabs();
            
            // CRITICAL: Ensure thumbnail buttons are visible after mobile setup
            setTimeout(() => {
                console.log('📱 Mobile: Ensuring thumbnail UI is ready');
                ensureThumbnailButtonsVisible();
                setupThumbnailHandlers();
                
                // Force thumbnail section to be visible
                const thumbnailSection = document.querySelector('.thumbnail-section');
                const mobileThumbnailView = document.querySelector('.mobile-thumbnail-view');
                if (thumbnailSection) {
                    thumbnailSection.style.display = 'block';
                    console.log('📱 Mobile: Thumbnail section forced visible');
                }
                if (mobileThumbnailView) {
                    mobileThumbnailView.style.display = 'block';
                    console.log('📱 Mobile: Mobile thumbnail view forced visible');
                }
                
                // CRITICAL: Generate auto thumbnail for mobile after a longer delay
                // Mobile needs MORE time for video element to be ready
                setTimeout(() => {
                    console.log('📱 Mobile: Attempting to generate auto thumbnail (with extended delay)');
                    
                    // Extra check to ensure video player is ready
                    const videoPlayer = document.querySelector('.video-player-container video');
                    if (videoPlayer) {
                        console.log('📱 Mobile: Video player found, generating thumbnail');
                    } else {
                        console.log('⚠️ Mobile: Video player not found yet, will retry');
                    }
                    
                    generateAutoThumbnail();
                }, 1500); // INCREASED delay for mobile (1.5 seconds)
            }, 400); // INCREASED initial delay
        }, 200); // INCREASED initialization delay
        } else {
            // Desktop: Use grid layout
            console.log('🖥️ Desktop layout detected - using grid');
            step2.style.setProperty('display', 'grid', 'important');
            step2.style.setProperty('grid-template-columns', '1fr 1fr', 'important');
            step2.style.setProperty('gap', '32px', 'important');
            step2.style.setProperty('padding', '12px 0 12px 12px', 'important');
            
            // CRITICAL: Ensure thumbnail buttons are visible on desktop
            setTimeout(() => {
                ensureThumbnailButtonsVisible();
                setupThumbnailHandlers();
                
                // CRITICAL: Generate auto thumbnail for desktop after DOM is ready
                setTimeout(() => {
                    console.log('🖥️ Desktop: Attempting to generate auto thumbnail');
                    generateAutoThumbnail();
                }, 500); // Delay to ensure video element is loaded
            }, 100);
        }
        
        console.log('✅ Step 2 shown with grid layout and ALL visibility properties reset');
        console.log('🔍 Step 2 classes:', step2.className);
        console.log('🔍 Step 2 computed display:', window.getComputedStyle(step2).display);
        console.log('🔍 Step 2 computed grid-template-columns:', window.getComputedStyle(step2).gridTemplateColumns);
    }
    
    // CRITICAL: Reset button to normal upload state (green background, upload action)
    // This ensures button is not stuck in "cancel" mode from previous upload
    setUploadButtonState('normal');
    
    // Update button text for step 2 AND enable it
    const nextBtn = document.getElementById('nextBtn');
    const nextBtnText = document.getElementById('nextBtnText');
    
    // CRITICAL: Force onclick handler to be the upload handler, not cancel handler
    if (nextBtn) {
        nextBtn.onclick = handleNextButtonClick;
        console.log('✅ Button onclick handler FORCED to handleNextButtonClick (not cancelUpload)');
    }
    
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
        nextBtn.style.opacity = '1';
        nextBtn.style.cursor = 'pointer';
        nextBtn.style.background = ''; // Reset to default green
        console.log('✅ Upload/Import button ENABLED for Step 2 in normal (green) mode');
        console.log('   Button state:', {
            disabled: nextBtn.disabled,
            text: nextBtnText?.textContent,
            onclick: nextBtn.onclick === handleNextButtonClick ? 'handleNextButtonClick' : 'OTHER',
            opacity: nextBtn.style.opacity,
            cursor: nextBtn.style.cursor,
            background: nextBtn.style.background
        });
    }
    
    // Hide Close button and show Back button on Step 2
    const closeBtn = document.getElementById('closeBtn');
    if (closeBtn) {
        closeBtn.style.display = 'none';
        console.log('✅ Close button hidden on Step 2');
    }
    
    const backBtn = document.getElementById('backBtn');
    if (backBtn) {
        backBtn.style.display = 'flex'; // Show Back button on Step 2
        backBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px;"><polyline points="15,18 9,12 15,6"></polyline></svg> Back';
        backBtn.onclick = goBackToStep1;
        console.log('✅ Back button shown and changed to "< Back"');
    }
    
    // Setup preview for files or imports
    if (selectedFile || (currentUploadType === 'import' && window.importedVideoUrl)) {
        setupVideoPreview();
    }
    
    // Initialize mobile tabs for responsive view (on mobile devices)
    setTimeout(() => {
        if (window.innerWidth <= 768) {
            forceMobileLayout();
            initializeMobileTabs();
        }
    }, 100);
}

function goBackToStep1() {
    console.log('⬅️ Going back to step 1');
    
    // CRITICAL: Reset upload state and button FIRST
    uploadInProgress = false;
    currentUploadController = null;
    currentXHR = null;
    currentR2Uploader = null;
    
    // Reset button to proper state (green, not red)
    let resetBtn = document.getElementById('nextBtn');
    let resetBtnText = document.getElementById('nextBtnText');
    if (resetBtn && resetBtnText) {
        resetBtn.disabled = false;
        resetBtn.style.background = 'linear-gradient(135deg, #10b981, #059669)'; // Green
        resetBtn.style.cursor = 'pointer';
        resetBtn.onclick = handleNextButtonClick; // Reset to normal handler, NOT cancel handler
        
        // Reset text to "Next" for Step 1
        resetBtnText.textContent = 'Next';
        console.log('✅ Button reset to green "Next" state');
        console.log('📍 DEBUG: Button text after reset:', resetBtnText.textContent);
    } else {
        console.error('❌ Could not find button elements for reset');
        console.log('   resetBtn:', !!resetBtn, 'resetBtnText:', !!resetBtnText);
    }
    
    // Notify parent that data is being cleared (modal can close safely now)
    if (window.parent && window.parent !== window) {
        window.parent.postMessage({ type: 'data_cleared' }, '*');
    }
    
    // Clear all upload state
    selectedFile = null;
    selectedFiles = [];
    currentUploadType = null;
    currentSource = null;
    window.capturedThumbnailBlob = null;
    window.customThumbnailSource = null;
    window.importedVideoUrl = null; // CRITICAL: Clear imported URL to prevent stale data
    
    // Clear CSV import state
    csvImportMode = false;
    csvData = [];
    csvFileName = '';
    console.log('✅ CSV import state cleared');
    
    // Clear URL input
    const urlInput = document.getElementById('urlInput');
    if (urlInput) {
        urlInput.value = '';
        console.log('✅ URL input cleared');
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
    
    // Clean up mobile tabs if they exist
    const mobileTabs = document.querySelector('.mobile-preview-tabs');
    const mobileThumbnailView = document.querySelector('.mobile-thumbnail-view');
    if (mobileTabs) {
        mobileTabs.remove();
        console.log('✅ Mobile tabs removed');
    }
    if (mobileThumbnailView) {
        mobileThumbnailView.remove();
        console.log('✅ Mobile thumbnail view removed');
    }
    
    // Restore original sections
    const thumbnailSection = document.querySelector('.thumbnail-section');
    const videoPreviewSection = document.querySelector('.video-preview-section');
    if (thumbnailSection) {
        thumbnailSection.style.display = '';
        thumbnailSection.classList.remove('mobile-tab-content');
    }
    if (videoPreviewSection) {
        videoPreviewSection.style.display = '';
        videoPreviewSection.classList.remove('mobile-tab-content', 'active');
    }
    
    // Remove selected state from platform buttons
    document.querySelectorAll('.source-btn').forEach(btn => {
        btn.classList.remove('selected');
    });
    
    // Hide step 2, show step 1
    const step1 = document.getElementById('step1');
    const step2 = document.getElementById('step2');
    const bulkStep2 = document.getElementById('bulkStep2');
    const csvStep2 = document.getElementById('csvStep2');
    
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
    
    // Also hide CSV step 2 if visible
    if (csvStep2) {
        csvStep2.style.display = 'none';
        console.log('✅ CSV Step 2 hidden');
    }
    
    // Show step 1
    if (step1) {
        step1.style.display = 'block';
        step1.style.visibility = 'visible';
        step1.style.position = 'relative';
        step1.style.left = '0';
        console.log('✅ Step 1 shown and visible');
    }
    
    // CRITICAL: Reset button text for step 1 (re-query to ensure we have the element)
    const stepBtnText = document.getElementById('nextBtnText');
    const stepBtn = document.getElementById('nextBtn');
    if (stepBtnText) {
        stepBtnText.textContent = 'Next';
        console.log('✅ Button text reset to "Next" via #nextBtnText');
    }
    // Also reset any .button-text class elements (fallback)
    if (stepBtn) {
        const buttonTextElement = stepBtn.querySelector('.button-text');
        if (buttonTextElement) {
            buttonTextElement.textContent = 'Next';
            console.log('✅ Button text also reset via .button-text class');
        }
    }
    
    // Hide Back button and show Close button on Step 1
    const backBtn = document.getElementById('backBtn');
    if (backBtn) {
        backBtn.style.display = 'none';
        console.log('✅ Back button hidden on Step 1');
    }
    
    // On mobile, Close button is hidden (X icon in header is sufficient)
    // On desktop, show Close button
    const isMobile = window.innerWidth <= 768;
    const closeBtn = document.getElementById('closeBtn');
    if (closeBtn && !isMobile) {
        closeBtn.style.display = 'flex';
        console.log('✅ Close button shown on Step 1 (desktop)');
    } else if (closeBtn && isMobile) {
        closeBtn.style.display = 'none';
        console.log('✅ Close button hidden on Step 1 (mobile - X icon in header is sufficient)');
    }
    
    // Reset upload button state to normal (in case it was in 'success' state)
    setUploadButtonState('normal');
    
    // Disable Next button until new selection
    if (resetBtn) {
        resetBtn.disabled = true;
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
    
    // Initialize mobile tabs for responsive view (on mobile devices)
    setTimeout(() => {
        if (window.innerWidth <= 768) {
            forceMobileLayout();
            initializeMobileTabs();
        }
    }, 100);
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
    const badgeInputDisplay = document.getElementById('badgeInput');
    
    if (badgesInput && videoData.badges && videoData.badges.trim()) {
        console.log('🏷️ Populating badges:', videoData.badges);
        badgesInput.value = videoData.badges;
        
        // Display badges visually by parsing comma-separated string
        const badgesList = videoData.badges.split(',').map(b => b.trim()).filter(b => b);
        console.log('🏷️ Badge list:', badgesList);
        
        // Clear existing badges first
        if (badgeInputDisplay) {
            const existingBadges = badgeInputDisplay.querySelectorAll('.badge-pill');
            existingBadges.forEach(badge => badge.remove());
            
            // Add each badge to the visual display
            badgesList.forEach(badgeName => {
                addBadgeToInput(badgeName, badgeName);
                console.log('🏷️ Added badge:', badgeName);
            });
        } else {
            console.warn('⚠️ Badge input display element not found');
        }
    } else {
        console.log('ℹ️ No badges to populate:', videoData.badges);
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
    
    // Include selected networks
    const selectedNetworksValue = getSelectedNetworksString();
    if (selectedNetworksValue) {
        formData.append('selected_networks', selectedNetworksValue);
        console.log('📡 Selected networks:', selectedNetworksValue);
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
// UPLOAD CANCELLATION
// ============================================
function cancelUpload() {
    console.log('🛑 Cancel Upload button clicked - showing confirmation...');
    
    // Show confirmation dialog before cancelling
    const confirmed = confirm('Are you sure you want to cancel the upload? This action cannot be undone.');
    
    if (!confirmed) {
        // User clicked "Cancel" on the dialog - they DON'T want to cancel the upload
        console.log('✅ User chose NOT to cancel - upload will continue');
        
        // CRITICAL: Ensure button stays in RED "Cancel Upload" mode
        const nextBtn = document.getElementById('nextBtn');
        const nextBtnText = document.getElementById('nextBtnText');
        if (nextBtn && nextBtnText) {
            // Force red button to stay red
            nextBtn.style.background = 'linear-gradient(135deg, #ef4444, #dc2626)';
            nextBtn.style.cursor = 'pointer';
            nextBtn.disabled = false;
            nextBtnText.textContent = 'Cancel Upload';
            
            // Ensure cancel handler stays attached
            nextBtn.onclick = function(e) {
                e.preventDefault();
                cancelUpload();
            };
            
            console.log('✅ Button remains RED "Cancel Upload" - upload continuing');
        }
        
        // Do NOT clear video, thumbnail, or metadata
        // Do NOT abort upload - it should continue
        return; // Exit without doing anything else
    }
    
    // User clicked "OK" on the dialog - they DO want to cancel the upload
    console.log('✅ User confirmed upload cancellation - aborting upload');
    
    // Set flag to prevent abort event handlers from showing duplicate alerts
    window.isIntentionalCancel = true;
    
    // Abort fetch request if exists
    if (currentUploadController) {
        currentUploadController.abort();
        console.log('✅ Fetch request aborted');
    }
    
    // Abort XHR request if exists (multipart uploads)
    if (currentXHR) {
        currentXHR.abort();
        console.log('✅ XHR request aborted');
    }
    
    // Abort R2 Multipart Uploader if exists
    if (currentR2Uploader && typeof currentR2Uploader.abort === 'function') {
        currentR2Uploader.abort();
        console.log('✅ R2 Multipart Uploader aborted');
    }
    
    // Reset the flag after a short delay
    setTimeout(() => {
        window.isIntentionalCancel = false;
    }, 100);
    
    // Reset upload state (but DON'T clear file/thumbnail)
    uploadInProgress = false;
    currentUploadController = null;
    currentXHR = null;
    currentR2Uploader = null;
    
    // Hide progress indicators
    hideUploadProgress();
    hideBulkProgress();
    
    // CRITICAL: Explicitly restore button to GREEN "Upload Media" state
    const nextBtn = document.getElementById('nextBtn');
    const nextBtnText = document.getElementById('nextBtnText');
    if (nextBtn && nextBtnText) {
        uploadInProgress = false;
        
        // CRITICAL: Force GREEN button with MAXIMUM specificity
        // Method 1: Remove existing property
        nextBtn.style.removeProperty('background');
        
        // Method 2: Force with !important using setProperty
        nextBtn.style.setProperty('background', 'linear-gradient(135deg, #10b981, #059669)', 'important');
        
        // Method 3: Direct assignment as fallback
        nextBtn.style.background = 'linear-gradient(135deg, #10b981, #059669)';
        
        // Method 4: Use cssText to override everything
        const currentCss = nextBtn.style.cssText;
        nextBtn.style.cssText = currentCss.replace(/background:[^;]+;?/gi, '') + 
            'background: linear-gradient(135deg, #10b981, #059669) !important;';
        
        nextBtn.style.cursor = 'pointer';
        nextBtn.disabled = false;
        
        console.log('🟢 Button background FORCED to green. Final value:', nextBtn.style.background);
        
        // Restore original text based on mode
        if (currentUploadType === 'import') {
            nextBtnText.textContent = 'Import Media';
        } else if (bulkMode) {
            nextBtnText.textContent = 'Upload All Files';
        } else {
            nextBtnText.textContent = 'Upload Media';
        }
        
        // Restore normal click handler (not cancel handler)
        nextBtn.onclick = handleNextButtonClick;
        
        console.log('✅ Button restored to GREEN state with text:', nextBtnText.textContent);
        console.log('✅ Button background color:', nextBtn.style.background);
    }
    
    // Notify user (only once)
    showCustomAlert('Upload Cancelled', 'The upload has been cancelled. You can modify your settings and try uploading again.');
    
    // Notify parent that upload was cancelled
    if (window.parent && window.parent !== window) {
        window.parent.postMessage({ type: 'upload_cancelled' }, '*');
    }
    
    // CRITICAL: Preserve all video/thumbnail/metadata
    // Do NOT call goBackToStep1() or clear any content
    console.log('✅ Video, thumbnail, and metadata preserved after cancellation');
}

// Update button appearance during upload
function setUploadButtonToCancelMode() {
    const nextBtn = document.getElementById('nextBtn');
    const nextBtnText = document.getElementById('nextBtnText');
    
    if (nextBtn && nextBtnText) {
        uploadInProgress = true;
        
        // Change to red cancel button
        nextBtn.disabled = false; // Keep enabled so user can click to cancel
        nextBtn.style.background = 'linear-gradient(135deg, #ef4444, #dc2626)'; // Red gradient
        nextBtn.style.cursor = 'pointer';
        nextBtnText.textContent = 'Cancel Upload';
        
        // Update click handler to cancel
        nextBtn.onclick = function(e) {
            e.preventDefault();
            cancelUpload();
        };
        
        console.log('✅ Upload button changed to Cancel mode (red)');
    }
}

// Restore button to normal upload state
function restoreUploadButtonFromCancelMode() {
    const nextBtn = document.getElementById('nextBtn');
    const nextBtnText = document.getElementById('nextBtnText');
    
    if (nextBtn && nextBtnText) {
        uploadInProgress = false;
        currentUploadController = null;
        currentXHR = null;
        currentR2Uploader = null;
        
        // CRITICAL: Force green button color (override any red styling)
        nextBtn.disabled = false;
        nextBtn.style.background = 'linear-gradient(135deg, #10b981, #059669)'; // Explicit green gradient
        nextBtn.style.cursor = 'pointer';
        
        // Restore original text based on mode
        if (currentUploadType === 'import') {
            nextBtnText.textContent = 'Import Media';
        } else if (bulkMode) {
            nextBtnText.textContent = 'Upload All Files';
        } else {
            nextBtnText.textContent = 'Upload Media';
        }
        
        // Restore normal click handler
        nextBtn.onclick = handleNextButtonClick;
        
        console.log('✅ Upload button restored to normal mode (GREEN)');
    }
}

// ============================================
// PLATFORM IMPORT FALLBACK FUNCTION
// ============================================
// Fallback function for when ionuploaderpro.js is not loaded
window.processPlatformImport = async function(metadata) {
    console.log('🌐 [Fallback] Processing platform import with metadata:', metadata);
    
    const urlInput = document.getElementById('urlInput');
    if (!urlInput) {
        console.error('❌ URL input element not found');
        alert('Upload Error: URL input not found.');
        return;
    }
    
    const url = urlInput.value.trim();
    console.log('🌐 [Fallback] URL from input:', url);
    console.log('🌐 [Fallback] Current source:', currentSource);
    
    if (!url) {
        console.error('❌ No URL provided');
        alert('Upload Error: Missing video URL for platform import.');
        return;
    }
    
    if (!currentSource) {
        console.error('❌ No source platform selected');
        alert('Upload Error: No platform selected for import.');
        return;
    }
    
    // Store the URL for later use
    window.importedVideoUrl = url;
    
    // FIXED: Actually perform the import
    console.log('📥 [Fallback] Starting platform import...');
    startImportProcess();
};

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
        
        // Send delete request to creators.php (that's where the delete endpoint is)
        fetch('./creators.php', {
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
    // CRITICAL: Restore page scrollbar before closing
    try {
        document.body.classList.remove('modal-open');
        if (window.parent && window.parent !== window) {
            window.parent.document.body.classList.remove('modal-open');
        }
        console.log('✅ Removed modal-open class - page scrollbar restored');
    } catch (e) {
        console.log('ℹ️ Could not access parent document (cross-origin):', e.message);
    }
    
    const urlParams = new URLSearchParams(window.location.search);
    const isIframe = urlParams.get('direct') !== '1';
    
    if (isIframe && window.parent && window.parent.postMessage) {
        // Send close message
        window.parent.postMessage({ type: 'close_modal' }, '*');
        
        // CRITICAL: Refresh parent page to show newly uploaded videos
        console.log('🔄 Refreshing parent page to show new videos');
        setTimeout(() => {
            if (window.parent && window.parent !== window) {
                try {
                    window.parent.location.reload();
                } catch (e) {
                    console.error('❌ Could not reload parent page:', e);
                }
            }
        }, 100); // Small delay to ensure modal closes first
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
    console.log('📦 Preparing bulk upload interface for', selectedFiles.length, 'files');
    
    // CRITICAL: Don't show bulkStep2 yet - just prepare it
    // It will be shown when user clicks "Next" button
    
    // Check if Pro version's bulk upload is available
    if (typeof generateBulkList === 'function') {
        console.log('✅ Using Pro version bulk upload (generateBulkList)');
        
        // Update file count
        const fileCountElement = document.getElementById('bulkFileCount');
        if (fileCountElement) {
            fileCountElement.textContent = selectedFiles.length;
        }
        
        // Use Pro version's bulk list generator (prepare but don't show)
        generateBulkList(selectedFiles);
        console.log('✅ Bulk file list prepared (Pro) - will show on Next click');
        return;
    }
    
    // Fallback to basic bulk upload interface (if Pro not loaded)
    console.log('⚠️ Pro version not available, using basic bulk upload');
    
    // Update file count
    const fileCountElement = document.getElementById('bulkFileCount');
    if (fileCountElement) {
        fileCountElement.textContent = selectedFiles.length;
    }
    
    // Populate basic file list (prepare but don't show)
    populateBulkFileList();
    console.log('✅ Bulk file list prepared (basic) - will show on Next click');
}

// NEW: Function to actually show the bulk Step 2 when user clicks Next
function proceedToBulkStep2() {
    console.log('📦 Proceeding to bulk upload Step 2');
    
    const step1 = document.getElementById('step1');
    const step2 = document.getElementById('step2');
    const bulkStep2 = document.getElementById('bulkStep2');
    
    // Hide step 1 and regular step 2
    if (step1) {
        step1.style.display = 'none';
        console.log('✅ Step 1 hidden');
    }
    if (step2) {
        step2.style.display = 'none';
        console.log('✅ Step 2 hidden');
    }
    
    // Show bulk step 2
    if (bulkStep2) {
        bulkStep2.style.display = 'flex'; // Use flex to match CSS flex-direction
        console.log('✅ Bulk Step 2 shown');
        }
        
        // Update button text
        const nextBtnText = document.getElementById('nextBtnText');
        if (nextBtnText) {
            nextBtnText.textContent = 'Upload All Files';
            console.log('✅ Button text updated to "Upload All Files"');
        }
        
    // Hide Close button and show Back button for bulk upload
        const closeBtn = document.getElementById('closeBtn');
        if (closeBtn) {
            closeBtn.style.display = 'none';
            console.log('✅ Close button hidden (bulk mode)');
        }
        
        const backBtn = document.getElementById('backBtn');
        if (backBtn) {
            backBtn.style.display = 'flex';
            backBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px;"><polyline points="15,18 9,12 15,6"></polyline></svg> Back';
            backBtn.onclick = goBackToStep1;
        console.log('✅ Back button changed to "< Back" (bulk mode)');
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
        
        // CRITICAL: Setup title character counter for this card
        const titleInput = document.getElementById(`bulkTitle_${index}`);
        const titleCount = document.getElementById(`bulkTitleCount_${index}`);
        
        if (titleInput && titleCount) {
            const updateCharCount = () => {
                const currentLength = titleInput.value.length;
                titleCount.textContent = currentLength;
                
                // Color feedback like single upload
                const charCountSpan = titleCount.parentElement;
                if (currentLength > 90) {
                    charCountSpan.style.color = '#ef4444'; // Red warning
                } else if (currentLength > 70) {
                    charCountSpan.style.color = '#f59e0b'; // Orange warning
                } else {
                    charCountSpan.style.color = '#94a3b8'; // Default gray
                }
            };
            
            // Add event listeners
            titleInput.addEventListener('input', updateCharCount);
            titleInput.addEventListener('keyup', updateCharCount);
            titleInput.addEventListener('paste', () => setTimeout(updateCharCount, 10));
            
            // Initialize count
            updateCharCount();
        }
        
        // CRITICAL: Automatically capture thumbnail for video files (like single upload)
        if (file.type.startsWith('video/')) {
            console.log(`📸 Auto-capturing thumbnail for video ${index}:`, file.name);
            // Use setTimeout to ensure DOM is ready
            setTimeout(() => {
                captureBulkThumbnail(index);
            }, 100 * index); // Stagger captures to avoid overwhelming the browser
        }
    });
    
    console.log('✅ Bulk file list populated with auto-thumbnail capture and character counters');
}

function createBulkFileCard(file, index) {
    const card = document.createElement('div');
    card.className = 'bulk-file-card-improved';
    card.dataset.fileIndex = index;
    card.dataset.expanded = 'false';
    
    // Store the selected file globally
    selectedFiles[index] = file;
    console.log('📁 File selected:', file.name, 'Size:', formatFileSize(file.size));
    
    // Auto-generate title from filename (remove extension)
    const autoTitle = file.name.replace(/\.[^/.]+$/, "").replace(/[_-]/g, ' ');
    
    // Create improved card HTML matching the screenshot design
    card.innerHTML = `
        <div class="bulk-card-collapsed" id="bulkCardCollapsed_${index}">
            <div class="bulk-card-left">
                <div class="bulk-card-thumbnail" id="bulkThumbnail_${index}">
                    <div class="thumbnail-placeholder">🎬</div>
            </div>
        </div>
            
            <div class="bulk-card-center">
                <div class="bulk-card-title-wrapper">
                    <input type="text" 
                           class="bulk-card-title-input" 
                           id="bulkTitle_${index}" 
                           value="${autoTitle}"
                           maxlength="100"
                           placeholder="Enter video title">
                    <span class="bulk-card-char-count">
                        <span id="bulkTitleCount_${index}">${autoTitle.length}</span>/100
                    </span>
                </div>
                <div class="bulk-card-filename">${file.name} (${formatFileSize(file.size)})</div>
                
                <!-- Per-file progress bar (shown during upload) -->
                <div class="bulk-card-progress" id="bulkFileProgress_${index}" style="display: none;">
                    <div class="bulk-card-progress-bar">
                        <div class="bulk-card-progress-fill" id="bulkFileProgressBar_${index}"></div>
                    </div>
                    <span class="bulk-card-progress-text" id="bulkFileProgressText_${index}">0%</span>
                </div>
            </div>
            
            <div class="bulk-card-right">
                <button type="button" 
                        class="bulk-card-btn-close" 
                        onclick="removeFileFromBulk(${index})"
                        title="Remove file">
                    ✕
                </button>
                <button type="button" 
                        class="bulk-card-btn-expand" 
                        onclick="toggleBulkCardExpand(${index})"
                        title="Expand details">
                    <span class="expand-arrow" id="bulkExpandArrow_${index}">▼</span>
                </button>
            </div>
        </div>
        
        <div class="bulk-card-expanded" id="bulkCardExpanded_${index}" style="display: none;">
            <div class="bulk-expanded-content">
                <div class="bulk-expanded-left">
                    <div class="bulk-expanded-label">Video Preview</div>
                    <div class="bulk-expanded-video-preview" id="bulkVideoPreview_${index}">
                        <video class="bulk-preview-video" 
                               id="bulkPreviewVideo_${index}"
                               controls 
                               style="width: 100%; max-height: 250px; border-radius: 8px; background: #000;">
                        </video>
                    </div>
                </div>
                
                <div class="bulk-expanded-middle">
                    <div class="bulk-expanded-label">Thumbnail</div>
                    <div class="bulk-expanded-thumbnail" id="bulkExpandedThumbnail_${index}">
                        <div class="thumbnail-placeholder-large">📸</div>
                    </div>
                    
                    <!-- Thumbnail Controls (like single upload) -->
                    <div class="bulk-thumbnail-controls">
                        <button type="button" 
                                class="bulk-thumbnail-btn" 
                                onclick="captureBulkFrameThumbnail(${index})"
                                title="Capture current frame from video">
                            🎬 Capture Frame
                        </button>
                        <button type="button" 
                                class="bulk-thumbnail-btn" 
                                onclick="document.getElementById('bulkThumbnailInput_${index}').click()"
                                title="Upload custom image">
                            📷 Upload
                        </button>
                        <button type="button" 
                                class="bulk-thumbnail-btn bulk-thumbnail-url-btn" 
                                onclick="toggleBulkThumbnailUrl(${index})"
                                title="Load from URL">
                            🔗 URL
                        </button>
                    </div>
                    
                    <!-- URL Input (inline, simpler than modal) -->
                    <div class="bulk-thumbnail-url-input" id="bulkThumbnailUrlInput_${index}" style="display: none;">
                        <input type="url" 
                               class="bulk-url-input" 
                               id="bulkThumbnailUrl_${index}"
                               placeholder="https://example.com/image.jpg">
                        <button type="button" 
                                class="bulk-url-apply-btn" 
                                onclick="applyBulkThumbnailUrl(${index})">
                            Apply
                        </button>
                    </div>
                    
                    <!-- Hidden file input for custom upload -->
                    <input type="file" 
                           id="bulkThumbnailInput_${index}" 
                           accept="image/*" 
                           style="display: none;"
                           onchange="handleBulkThumbnailUpload(event, ${index})">
                </div>
                
                <div class="bulk-expanded-right">
                    <div class="bulk-expanded-label">Description</div>
                    <textarea class="bulk-expanded-description" 
                              id="bulkDescription_${index}" 
                              rows="10"
                              placeholder="Enter video description"></textarea>
                </div>
            </div>
            
            <!-- Hidden inputs for other metadata (will use common properties by default) -->
            <input type="hidden" id="bulkCategory_${index}" value="">
            <input type="hidden" id="bulkSelectedChannels_${index}" value="">
            <input type="hidden" id="bulkTags_${index}" value="">
            <input type="hidden" id="bulkVisibility_${index}" value="">
        </div>
    `;
    
    return card;
}

// ============================================
// BULK UPLOAD METADATA HELPERS
// ============================================

function generateCategoryOptions() {
    // Use the same categories as single upload
    const categorySelect = document.getElementById('categorySelect');
    if (categorySelect) {
        return categorySelect.innerHTML;
    }
    // Fallback categories if step2 isn't loaded yet
    return `
        <option value="">Choose category...</option>
        <option value="General">General</option>
        <option value="Sports">Sports</option>
        <option value="Entertainment">Entertainment</option>
        <option value="Education">Education</option>
        <option value="News">News</option>
        <option value="Music">Music</option>
        <option value="Gaming">Gaming</option>
        <option value="Technology">Technology</option>
    `;
}

function generateChannelOptions(fileIndex) {
    // Get channels from the main channel selector
    const mainChannelContainer = document.getElementById('channelCheckboxes');
    if (!mainChannelContainer) {
        return '<div class="no-channels">No channels available</div>';
    }
    
    const checkboxes = mainChannelContainer.querySelectorAll('input[type="checkbox"]');
    if (checkboxes.length === 0) {
        return '<div class="no-channels">No channels available</div>';
    }
    
    let html = '';
    checkboxes.forEach((checkbox, idx) => {
        const label = checkbox.nextElementSibling;
        const channelSlug = checkbox.value;
        const channelName = label ? label.textContent.trim() : channelSlug;
        
        html += `
            <label class="dropdown-option">
                <input type="checkbox" 
                       value="${channelSlug}" 
                       onchange="updateBulkSelectedChannels(${fileIndex})">
                ${channelName}
            </label>
        `;
    });
    
    return html;
}

// NEW: Toggle expand/collapse for improved bulk cards
function toggleBulkCardExpand(index) {
    const card = document.querySelector(`[data-file-index="${index}"]`);
    const collapsedSection = document.getElementById(`bulkCardCollapsed_${index}`);
    const expandedSection = document.getElementById(`bulkCardExpanded_${index}`);
    const expandArrow = document.getElementById(`bulkExpandArrow_${index}`);
    
    if (!card || !collapsedSection || !expandedSection) {
        console.error('❌ Card elements not found for index:', index);
        return;
    }
    
    const isExpanded = card.dataset.expanded === 'true';
    
    if (isExpanded) {
        // Collapse
        expandedSection.style.display = 'none';
        expandArrow.textContent = '▼';
        card.dataset.expanded = 'false';
        console.log(`📕 Card ${index} collapsed`);
    } else {
        // Expand
        expandedSection.style.display = 'block';
        expandArrow.textContent = '▲';
        card.dataset.expanded = 'true';
        
        // Load video preview if not already loaded
        const video = document.getElementById(`bulkPreviewVideo_${index}`);
        const file = selectedFiles[index];
        if (video && file && !video.src) {
            const objectUrl = URL.createObjectURL(file);
            video.src = objectUrl;
            console.log(`🎥 Video preview loaded for file ${index}`);
        }
        
        console.log(`📖 Card ${index} expanded`);
    }
}

function toggleBulkChannelDropdown(index) {
    const dropdown = document.getElementById(`bulkChannelOptions_${index}`);
    if (dropdown) {
        const isHidden = dropdown.style.display === 'none';
        dropdown.style.display = isHidden ? 'block' : 'none';
        
        console.log(`${isHidden ? '📂' : '📁'} Channel dropdown for file ${index}:`, isHidden ? 'opened' : 'closed');
    }
}

function updateBulkSelectedChannels(fileIndex) {
    const optionsContainer = document.getElementById(`bulkChannelOptions_${fileIndex}`);
    if (!optionsContainer) return;
    
    const checkboxes = optionsContainer.querySelectorAll('input[type="checkbox"]:checked');
    const selectedChannels = Array.from(checkboxes).map(cb => cb.value);
    
    // Update hidden input
    const hiddenInput = document.getElementById(`bulkSelectedChannels_${fileIndex}`);
    if (hiddenInput) {
        hiddenInput.value = JSON.stringify(selectedChannels);
    }
    
    // Update display text
    const displayText = document.getElementById(`bulkSelectedChannelText_${fileIndex}`);
    if (displayText) {
        if (selectedChannels.length === 0) {
            displayText.textContent = 'Select channels...';
            displayText.style.color = '#999';
        } else {
            displayText.textContent = `${selectedChannels.length} channel${selectedChannels.length > 1 ? 's' : ''} selected`;
            displayText.style.color = '#333';
        }
    }
    
    console.log(`📺 File ${fileIndex} channels updated:`, selectedChannels);
}

async function captureBulkThumbnail(index) {
    const file = selectedFiles[index];
    if (!file) {
        console.error('❌ File not found at index:', index);
        return;
    }
    
    console.log(`📸 Capturing thumbnail for file ${index}:`, file.name);
    
    const thumbnailContainer = document.getElementById(`bulkThumbnail_${index}`);
    const expandedThumbnailContainer = document.getElementById(`bulkExpandedThumbnail_${index}`);
    
    if (!thumbnailContainer && !expandedThumbnailContainer) {
        console.error('❌ Thumbnail containers not found');
        return;
    }
    
    try {
        // Create video element
        const video = document.createElement('video');
        video.preload = 'metadata';
        video.muted = true;
        
        const objectUrl = URL.createObjectURL(file);
        video.src = objectUrl;
        
        // Wait for video to load
        await new Promise((resolve, reject) => {
            video.onloadedmetadata = resolve;
            video.onerror = reject;
        });
        
        // Seek to 1 second (or 10% of duration)
        const seekTime = Math.min(1, video.duration * 0.1);
        video.currentTime = seekTime;
        
        await new Promise((resolve) => {
            video.onseeked = resolve;
        });
        
        // Capture frame
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        
        // Convert to blob
        const thumbnailBlob = await new Promise((resolve) => {
            canvas.toBlob(resolve, 'image/jpeg', 0.85);
        });
        
        // Store thumbnail in global array
        if (!window.bulkThumbnails) {
            window.bulkThumbnails = {};
        }
        window.bulkThumbnails[index] = thumbnailBlob;
        
        // Display thumbnail in both collapsed and expanded views
        const thumbnailUrl = URL.createObjectURL(thumbnailBlob);
        
        // Update collapsed view thumbnail (small)
        if (thumbnailContainer) {
            thumbnailContainer.innerHTML = `
                <img src="${thumbnailUrl}" alt="Thumbnail" style="width: 100%; height: 100%; object-fit: cover; border-radius: 6px;">
            `;
        }
        
        // Update expanded view thumbnail (large)
        if (expandedThumbnailContainer) {
            expandedThumbnailContainer.innerHTML = `
                <img src="${thumbnailUrl}" alt="Thumbnail" style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;">
            `;
        }
        
        // Cleanup
        URL.revokeObjectURL(objectUrl);
        
        console.log(`✅ Thumbnail captured for file ${index} (${(thumbnailBlob.size / 1024).toFixed(1)}KB)`);
        
    } catch (error) {
        console.error(`❌ Failed to capture thumbnail for file ${index}:`, error);
        alert(`Failed to capture thumbnail: ${error.message}`);
    }
}

/**
 * Capture thumbnail from the CURRENT FRAME of the video player (like single file upload)
 * This captures whatever frame is currently showing in the video player
 */
function captureBulkFrameThumbnail(index) {
    console.log(`🎬 Capturing current frame from video player for file ${index}`);
    
    // Get the video player element
    const video = document.getElementById(`bulkPreviewVideo_${index}`);
    if (!video) {
        alert('Video player not found. Please expand the video card first.');
        return;
    }
    
    // Check if video is loaded and ready
    if (!video.src) {
        alert('Video not loaded. Please expand the video card to load the preview first.');
        return;
    }
    
    if (video.readyState < 2) {
        alert('Video is still loading. Please wait and try again.');
        return;
    }
    
    try {
        // Create canvas and capture current frame
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        
        canvas.width = video.videoWidth || video.clientWidth;
        canvas.height = video.videoHeight || video.clientHeight;
        
        if (canvas.width === 0 || canvas.height === 0) {
            alert('Unable to determine video dimensions. Please try again when video is fully loaded.');
            return;
        }
        
        // Draw current video frame to canvas
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        
        // Convert canvas to blob
        canvas.toBlob((blob) => {
            if (!blob) {
                alert('Failed to capture thumbnail');
                return;
            }
            
            // Store thumbnail in global array
            if (!window.bulkThumbnails) {
                window.bulkThumbnails = {};
            }
            window.bulkThumbnails[index] = blob;
            
            // Display thumbnail in both collapsed and expanded views
            const thumbnailUrl = URL.createObjectURL(blob);
            
            // Update collapsed view thumbnail (small)
            const thumbnailContainer = document.getElementById(`bulkThumbnail_${index}`);
            if (thumbnailContainer) {
                thumbnailContainer.innerHTML = `
                    <img src="${thumbnailUrl}" alt="Thumbnail" style="width: 100%; height: 100%; object-fit: cover; border-radius: 6px;">
                `;
            }
            
            // Update expanded view thumbnail (large)
            const expandedThumbnailContainer = document.getElementById(`bulkExpandedThumbnail_${index}`);
            if (expandedThumbnailContainer) {
                expandedThumbnailContainer.innerHTML = `
                    <img src="${thumbnailUrl}" alt="Thumbnail" style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;">
                `;
            }
            
            console.log(`✅ Frame captured for file ${index} at time ${video.currentTime.toFixed(2)}s (${(blob.size / 1024).toFixed(1)}KB)`);
            
        }, 'image/jpeg', 0.85);
        
    } catch (error) {
        console.error(`❌ Failed to capture frame for file ${index}:`, error);
        if (error.name === 'SecurityError') {
            alert('Cannot capture frame due to security restrictions (CORS).');
        } else {
            alert(`Failed to capture frame: ${error.message}`);
        }
    }
}

// Legacy function name for backwards compatibility
function recaptureBulkThumbnail(index) {
    console.log(`🔄 Recapturing thumbnail for file ${index} (legacy method - redirecting to frame capture)`);
    captureBulkFrameThumbnail(index);
}

// Handle custom thumbnail upload
function handleBulkThumbnailUpload(event, index) {
    const file = event.target.files[0];
    if (!file) return;
    
    console.log(`📷 Custom thumbnail uploaded for file ${index}:`, file.name);
    
    // Validate image file
    if (!file.type.startsWith('image/')) {
        alert('Please select an image file');
        return;
    }
    
    // Check file size (max 5MB)
    if (file.size > 5 * 1024 * 1024) {
        alert('Image size must be less than 5MB');
        return;
    }
    
    // Store thumbnail blob
    if (!window.bulkThumbnails) {
        window.bulkThumbnails = {};
    }
    window.bulkThumbnails[index] = file;
    
    // Display thumbnail
    const reader = new FileReader();
    reader.onload = (e) => {
        const thumbnailUrl = e.target.result;
        
        // Update collapsed view
        const thumbnailContainer = document.getElementById(`bulkThumbnail_${index}`);
        if (thumbnailContainer) {
            thumbnailContainer.innerHTML = `
                <img src="${thumbnailUrl}" alt="Thumbnail" style="width: 100%; height: 100%; object-fit: cover; border-radius: 6px;">
            `;
        }
        
        // Update expanded view
        const expandedThumbnailContainer = document.getElementById(`bulkExpandedThumbnail_${index}`);
        if (expandedThumbnailContainer) {
            expandedThumbnailContainer.innerHTML = `
                <img src="${thumbnailUrl}" alt="Thumbnail" style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;">
            `;
        }
        
        console.log(`✅ Custom thumbnail applied for file ${index}`);
    };
    
    reader.readAsDataURL(file);
}

// Toggle URL input visibility
function toggleBulkThumbnailUrl(index) {
    const urlInput = document.getElementById(`bulkThumbnailUrlInput_${index}`);
    if (urlInput) {
        const isHidden = urlInput.style.display === 'none';
        urlInput.style.display = isHidden ? 'flex' : 'none';
        
        if (isHidden) {
            // Focus the input
            const input = document.getElementById(`bulkThumbnailUrl_${index}`);
            if (input) {
                setTimeout(() => input.focus(), 100);
            }
        }
    }
}

// Apply thumbnail from URL
async function applyBulkThumbnailUrl(index) {
    const input = document.getElementById(`bulkThumbnailUrl_${index}`);
    if (!input || !input.value) {
        alert('Please enter an image URL');
        return;
    }
    
    const imageUrl = input.value.trim();
    console.log(`🔗 Loading thumbnail from URL for file ${index}:`, imageUrl);
    
    try {
        // Fetch the image
        const response = await fetch(imageUrl);
        if (!response.ok) {
            throw new Error(`Failed to load image: ${response.status}`);
        }
        
        const blob = await response.blob();
        
        // Validate it's an image
        if (!blob.type.startsWith('image/')) {
            throw new Error('URL does not point to a valid image');
        }
        
        // Store thumbnail blob
        if (!window.bulkThumbnails) {
            window.bulkThumbnails = {};
        }
        window.bulkThumbnails[index] = blob;
        
        // Display thumbnail
        const thumbnailUrl = URL.createObjectURL(blob);
        
        // Update collapsed view
        const thumbnailContainer = document.getElementById(`bulkThumbnail_${index}`);
        if (thumbnailContainer) {
            thumbnailContainer.innerHTML = `
                <img src="${thumbnailUrl}" alt="Thumbnail" style="width: 100%; height: 100%; object-fit: cover; border-radius: 6px;">
            `;
        }
        
        // Update expanded view
        const expandedThumbnailContainer = document.getElementById(`bulkExpandedThumbnail_${index}`);
        if (expandedThumbnailContainer) {
            expandedThumbnailContainer.innerHTML = `
                <img src="${thumbnailUrl}" alt="Thumbnail" style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;">
            `;
        }
        
        // Hide URL input
        toggleBulkThumbnailUrl(index);
        
        // Clear input
        input.value = '';
        
        console.log(`✅ Thumbnail from URL applied for file ${index}`);
        
    } catch (error) {
        console.error(`❌ Failed to load thumbnail from URL for file ${index}:`, error);
        alert(`Failed to load image: ${error.message}`);
    }
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
    
    // Get file size before removal for progress calculation
    const removedFileSize = selectedFiles[index] ? selectedFiles[index].size : 0;
    
    // Remove from selectedFiles array
    selectedFiles.splice(index, 1);
    
    // Remove from thumbnails array if exists
    if (window.bulkThumbnails && window.bulkThumbnails[index]) {
        delete window.bulkThumbnails[index];
    }
    
    // Update file count
    const fileCountElement = document.getElementById('bulkFileCount');
    if (fileCountElement) {
        fileCountElement.textContent = `${selectedFiles.length} files selected`;
    }
    
    // Recalculate total size
    window.bulkUploadTotalSize = selectedFiles.reduce((total, file) => total + file.size, 0);
    console.log(`📊 Recalculated total size: ${formatFileSize(window.bulkUploadTotalSize)} (${selectedFiles.length} files)`);
    
    // If no files left, go back to step 1 and reset progress
    if (selectedFiles.length === 0) {
        console.log('📁 No files remaining, returning to step 1');
        
        // Hide bulk progress bar
        const bulkProgressContainer = document.getElementById('bulkProgressContainer');
        if (bulkProgressContainer) {
            bulkProgressContainer.style.display = 'none';
            console.log('✅ Bulk progress bar hidden');
        }
        
        // Reset progress tracking variables
        window.bulkUploadTotalSize = 0;
        window.bulkUploadCompletedSize = 0;
        window.bulkFileProgress = {};
        console.log('✅ Progress tracking variables reset');
        
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
    
    // Re-populate the file list (this will re-index everything correctly)
    populateBulkFileList();
    
    // Recalculate combined progress if upload is in progress
    if (window.bulkUploadTotalSize > 0) {
        // Reset file progress tracking for proper re-indexing
        const oldProgress = window.bulkFileProgress || {};
        window.bulkFileProgress = {};
        
        // Re-map progress to new indices
        Object.keys(oldProgress).forEach(oldIndex => {
            const newIndex = parseInt(oldIndex);
            if (newIndex < index) {
                // Files before deleted file keep their index
                window.bulkFileProgress[newIndex] = oldProgress[oldIndex];
            } else if (newIndex > index) {
                // Files after deleted file shift down by 1
                window.bulkFileProgress[newIndex - 1] = oldProgress[oldIndex];
            }
            // Skip the deleted file's index
        });
        
        console.log('📊 Progress tracking re-indexed after file removal');
    }
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
        case 'success':
            // Upload complete - change to "Close Window" with gray styling
            nextBtn.disabled = false;
            nextBtn.style.background = '#6b7280'; // Gray color
            nextBtn.style.cursor = 'pointer';
            nextBtnText.textContent = 'Close Window';
            // Update click handler to close and refresh parent
            nextBtn.onclick = function() {
                console.log('🔴 Close Window clicked - refreshing parent');
                closeUploaderAndRefreshParent();
            };
            break;
        case 'normal':
        default:
            // CRITICAL: Reset upload state flags
            uploadInProgress = false;
            currentUploadController = null;
            currentXHR = null;
            currentR2Uploader = null;
            
            nextBtn.disabled = false;
            nextBtn.style.background = ''; // Reset to default
            nextBtn.style.cursor = '';
            nextBtn.onclick = handleNextButtonClick; // Reset to default handler
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

// Close uploader and refresh parent window (creators.php)
function closeUploaderAndRefreshParent() {
    console.log('🔄 Closing uploader...');
    
    // CRITICAL: Restore page scrollbar before closing
    try {
        document.body.classList.remove('modal-open');
        if (window.parent && window.parent !== window) {
            window.parent.document.body.classList.remove('modal-open');
        }
        console.log('✅ Removed modal-open class - page scrollbar restored');
    } catch (e) {
        console.log('ℹ️ Could not access parent document (cross-origin):', e.message);
    }
    
    // If in iframe/modal
    if (window.parent && window.parent !== window) {
        try {
            // Close the modal
            const modal = window.parent.document.getElementById('ionVideoUploaderModal');
            if (modal) {
                modal.remove();
                console.log('✅ Modal removed');
            }
            
            // CRITICAL: Refresh parent page to show newly uploaded videos
            console.log('🔄 Refreshing parent page to show new videos');
            window.parent.location.reload();
        } catch (e) {
            console.error('❌ Error closing modal:', e);
        }
    } else {
        // Not in iframe, redirect to creators page
        window.location.href = './creators.php';
    }
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
    console.log('🔘🔘🔘 NEXT BUTTON CLICKED!!! 🔘🔘🔘');
    
    const nextBtnText = document.getElementById('nextBtnText');
    const nextBtn = document.getElementById('nextBtn');
    const currentText = nextBtnText?.textContent?.trim() || '';
    
    // CRITICAL: Determine which step we're on by checking visibility
    const step1 = document.getElementById('step1');
    const step2 = document.getElementById('step2');
    const bulkStep2 = document.getElementById('bulkStep2');
    const csvStep2 = document.getElementById('csvStep2');
    
    const step1Visible = step1 && window.getComputedStyle(step1).display !== 'none';
    const step2Visible = step2 && window.getComputedStyle(step2).display !== 'none';
    const bulkStep2Visible = bulkStep2 && window.getComputedStyle(bulkStep2).display !== 'none';
    const csvStep2Visible = csvStep2 && window.getComputedStyle(csvStep2).display !== 'none';
    
    console.log('🔘 Button state at click:', {
        text: currentText,
        disabled: nextBtn?.disabled,
        opacity: nextBtn?.style.opacity,
        cursor: nextBtn?.style.cursor,
        currentUploadType,
        selectedFile: !!selectedFile,
        step1Visible,
        step2Visible,
        bulkStep2Visible
    });
    
    // CRITICAL: Prevent double-click submission
    if (nextBtn && nextBtn.disabled) {
        console.log('⚠️ Button is DISABLED - ignoring click');
        console.log('   Button disabled status:', nextBtn.disabled);
        console.log('   Button computed style:', window.getComputedStyle(nextBtn).opacity);
        return;
    }
    
    // CRITICAL FIX: Always prioritize Step 1 visibility over button text or Step 2 visibility
    // The button text can be stale, and Step 2 might be "visible" due to CSS issues
    if (step1Visible) {
        // We're on step 1, ALWAYS proceed to step 2 (ignore button text)
        console.log('➡️ Step 1 is visible, proceeding to next step');
        console.log('   currentUploadType:', currentUploadType);
        console.log('   Button text was:', currentText, '(ignored - Step 1 is visible)');
        console.log('   Step 2 visible:', step2Visible, '(ignored - Step 1 takes priority)');
        proceedToNextStep();
    } else if (step2Visible || bulkStep2Visible || csvStep2Visible) {
        // We're on step 2 or bulk step 2, start upload/import
        console.log('📤 Starting upload/import (Step 2/Bulk visible, Step 1 NOT visible)');
        console.log('   Button text:', currentText);
        
        // CRITICAL: Disable button during upload to prevent double-click
        if (nextBtn) {
            nextBtn.disabled = true;
            nextBtn.style.opacity = '0.6';
            nextBtn.style.cursor = 'not-allowed';
            console.log('🔒 Upload button disabled to prevent double-submission');
        }
        
        if (csvStep2Visible || currentText === 'Import All Videos') {
            // CSV bulk import mode
            console.log('📊 Starting CSV bulk import for', csvData.length, 'videos');
            startCSVImport();
        } else if (bulkStep2Visible || currentText === 'Upload All Files') {
            // Bulk upload mode
            console.log('📦 Starting bulk upload for', selectedFiles.length, 'files');
            startBulkUpload();
        } else {
            // Single file upload/import
            startUpload();
        }
    } else {
        // Fallback: Neither step is visible? This shouldn't happen, but proceed to step 2
        console.error('⚠️ WARNING: Neither Step 1 nor Step 2 is visible! Proceeding to next step anyway.');
        console.log('   Button text:', currentText);
        proceedToNextStep();
    }
}

async function proceedToNextStep() {
    console.log('➡️ Proceeding to next step, type:', currentUploadType, 'bulkMode:', bulkMode);
    console.log('📍 DEBUG: Current source:', currentSource);
    console.log('📍 DEBUG: Imported video URL:', window.importedVideoUrl);
    console.log('📍 DEBUG: Selected file:', selectedFile);
    console.log('📍 DEBUG: URL input value:', document.getElementById('urlInput')?.value);
    
    // CRITICAL: Check if bulk mode - show bulk Step 2 instead of regular Step 2
    if (bulkMode && selectedFiles.length > 0) {
        console.log('📦 Bulk mode detected - showing bulk Step 2');
        proceedToBulkStep2();
        return;
    }
    
    // CRITICAL: Validate state before proceeding
    if (!currentUploadType) {
        console.error('❌ Cannot proceed: currentUploadType is not set!');
        console.error('   This usually means upload type (file/import) was not selected');
        alert('Please select an upload type (file upload or import)');
        return;
    }
    
    if (currentUploadType === 'file') {
        console.log('📍 DEBUG: Taking file upload path');
        if (!selectedFile) {
            console.error('❌ Cannot proceed: No file selected!');
            alert('Please select a file to upload');
            return;
        }
        proceedToStep2();
    } else if (currentUploadType === 'import') {
        console.log('📍 DEBUG: Taking import path - will validate and check duplicates');
        if (!currentSource) {
            console.error('❌ Cannot proceed: No platform selected!');
            alert('Please select a platform (YouTube, Vimeo, etc.)');
            return;
        }
        const urlInput = document.getElementById('urlInput');
        if (!urlInput || !urlInput.value.trim()) {
            console.error('❌ Cannot proceed: No URL entered!');
            alert('Please enter a video URL to import');
            return;
        }
        // Validate and check for duplicates before proceeding
        await validateAndProceedToStep2ForImport();
    } else {
        console.error('❌ Unknown upload type:', currentUploadType);
        alert('Invalid upload type selected');
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
            
            // Show warning but allow user to proceed
            const proceed = confirm(
                `⚠️ Duplicate Video Detected\n\n` +
                `${duplicateCheck.message}\n\n` +
                `This video may already exist in your library.\n\n` +
                `Do you want to import it anyway?`
            );
            
            if (!proceed) {
                console.log('❌ User chose not to import duplicate video');
                return; // User cancelled
            }
            
            console.log('✅ User chose to proceed with duplicate import');
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
    console.log('📍 DEBUG: About to call proceedToStep2() after validation');
    console.log('📍 DEBUG: URL stored as:', window.importedVideoUrl);
    proceedToStep2();
    console.log('📍 DEBUG: proceedToStep2() completed');
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

// Generate YouTube thumbnail (auto-fetch highest quality)
function generateYouTubeThumbnail(videoId) {
    console.log('📷 Fetching YouTube thumbnail (highest quality) for video:', videoId);
    
    const preview = document.getElementById('thumbnailPreview');
    const placeholder = document.getElementById('thumbnailPlaceholder');
    
    // Show loading state
    if (preview) {
        preview.style.opacity = '0.5';
        preview.style.filter = 'blur(4px)';
    }
    
    // Try highest quality first, fallback to lower if not available
    const qualities = ['maxresdefault', 'sddefault', 'hqdefault', 'mqdefault'];
    let currentIndex = 0;
    
    function tryNextQuality() {
        if (currentIndex >= qualities.length) {
            console.error('❌ All YouTube thumbnail qualities failed');
            if (preview) {
                preview.style.opacity = '1';
                preview.style.filter = 'none';
            }
            showSuccessToast('❌ Failed to fetch YouTube thumbnail');
            return;
        }
        
        const quality = qualities[currentIndex];
        const thumbnailUrl = `https://img.youtube.com/vi/${videoId}/${quality}.jpg`;
        
        fetch(thumbnailUrl, { mode: 'cors' })
            .then(response => {
                if (!response.ok) throw new Error('Thumbnail not found');
                return response.blob();
            })
            .then(blob => {
                console.log(`✅ YouTube thumbnail found (${quality}):`, blob.size, 'bytes');
                
                // CRITICAL: Store the blob for upload
                window.capturedThumbnailBlob = blob;
                window.customThumbnailSource = 'youtube';
                
                // Update preview
                const url = URL.createObjectURL(blob);
                
                if (preview) {
                    const currentSrc = preview.src;
                    
                    // Revoke old URL
                    if (currentSrc && currentSrc.startsWith('blob:')) {
                        URL.revokeObjectURL(currentSrc);
                    }
                    
                    preview.src = url;
                    preview.style.display = 'block';
                    preview.style.opacity = '1';
                    preview.style.filter = 'none';
                    
                    if (placeholder) {
                        placeholder.style.display = 'none';
                    }
                    
                    console.log('🖼️ YouTube thumbnail updated');
                }
                
                // Show confirmation
                showThumbnailConfirmation('YouTube thumbnail', 'custom');
            })
            .catch(error => {
                console.log(`⚠️ YouTube thumbnail not found (${quality}), trying next...`);
                currentIndex++;
                tryNextQuality();
            });
    }
    
    tryNextQuality();
}

// Generate Vimeo thumbnail (using vumbnail.com - works even for protected videos)
function generateVimeoThumbnail(videoId) {
    console.log('📷 Fetching Vimeo thumbnail for video:', videoId);
    
    const preview = document.getElementById('thumbnailPreview');
    const placeholder = document.getElementById('thumbnailPlaceholder');
    
    // Show loading state
    if (preview) {
        preview.style.opacity = '0.5';
        preview.style.filter = 'blur(4px)';
    }
    
    // Use vumbnail.com - a service that can fetch Vimeo thumbnails even for protected videos
    const thumbnailUrl = `https://vumbnail.com/${videoId}.jpg`;
    console.log('🖼️ Vimeo thumbnail requested:', thumbnailUrl);
    
    // Try to fetch as blob (for direct upload)
    fetch(thumbnailUrl, { mode: 'cors' })
        .then(response => {
            if (!response.ok) throw new Error('Vimeo thumbnail not found');
            return response.blob();
        })
        .then(blob => {
            console.log('✅ Vimeo thumbnail fetched as blob:', blob.size, 'bytes');
            
            // CRITICAL: Store the blob for upload
            window.capturedThumbnailBlob = blob;
            window.customThumbnailSource = 'vimeo';
            
            // Update preview
            const url = URL.createObjectURL(blob);
            updateThumbnailPreview(url);
            
            showThumbnailConfirmation('Vimeo thumbnail', 'custom');
        })
        .catch(error => {
            console.log('⚠️ CORS blocked blob fetch, using fallback (direct URL display)');
            
            // Fallback: Show the image directly (works despite CORS for display)
            // The backend will fetch and save it server-side
            window.capturedThumbnailUrl = thumbnailUrl; // Store URL instead of blob
            window.customThumbnailSource = 'vimeo';
            window.capturedThumbnailBlob = null; // Clear any existing blob
            
            // Display the image (browsers allow cross-origin images in <img> tags)
            updateThumbnailPreview(thumbnailUrl);
            
            console.log('✅ Vimeo thumbnail URL stored (backend will fetch):', thumbnailUrl);
        });
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
        // DO NOT change button to cancel mode for imports - they are fast server-side operations
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
                selected_channels: document.getElementById('selectedChannels')?.value || '', // CRITICAL: Include selected channels
                selected_networks: getSelectedNetworksString(), // Include selected networks
                thumbnailBlob: window.capturedThumbnailBlob || null
            };
            
            console.log('📦 Metadata prepared:', metadata);
            console.log('📺 Selected channels for import:', metadata.selected_channels);
            
            // Call the Pro import function
            if (typeof window.processPlatformImport === 'function') {
                console.log('✅ Calling window.processPlatformImport...');
                window.processPlatformImport(metadata);
            } else {
                console.error('❌ window.processPlatformImport is not a function:', typeof window.processPlatformImport);
                // Fallback: proceed to Step 2 for URL import instead of direct upload
                console.log('🔄 Falling back to Step 2 for URL import...');
                proceedToStep2();
            }
        } else {
            console.error('❌ Missing import data:', { url, currentSource });
            alert('Please enter a valid URL for platform import');
        }
    } else if (selectedFile) {
        // Upload local file - ONLY HERE do we enable cancel mode
        console.log('📤 Starting file upload for:', selectedFile.name);
        
        // Change button to red "Cancel Upload" mode ONLY for actual file uploads
        setUploadButtonToCancelMode();
        
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
    
    // Notify parent that upload is starting (prevents accidental modal close)
    if (window.parent && window.parent !== window) {
        window.parent.postMessage({ type: 'upload_started' }, '*');
    }
    
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
        thumbnail: window.capturedThumbnailBlob || null,
        selected_channels: document.getElementById('selectedChannels')?.value || '', // CRITICAL: Include selected channels
        selected_networks: getSelectedNetworksString() // Include selected networks
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
    const statusText = document.getElementById('uploadStatusText');
    const percentageText = document.getElementById('uploadPercentageText');
    
    if (progressContainer) progressContainer.style.display = 'block';
    if (statusText) statusText.textContent = 'Importing from Google Drive...';
    if (percentageText) percentageText.textContent = '0%';
    if (progressBar) progressBar.style.width = '0%';
    
    // Update button state
    setUploadButtonState('uploading');
    
    // Send request with XMLHttpRequest for real-time progress tracking
    const xhr = new XMLHttpRequest();
    
    // Track upload progress
    xhr.upload.addEventListener('progress', (e) => {
        if (e.lengthComputable) {
            const percentComplete = (e.loaded / e.total) * 100;
            console.log(`📊 Google Drive upload progress: ${percentComplete.toFixed(1)}%`);
            
            if (progressBar) {
                progressBar.style.width = percentComplete + '%';
            }
            if (statusText) {
                statusText.textContent = 'Importing from Google Drive...';
            }
            if (percentageText) {
                percentageText.textContent = `${percentComplete.toFixed(0)}%`;
            }
        }
    });
    
    // Handle successful completion
    xhr.addEventListener('load', () => {
        if (xhr.status >= 200 && xhr.status < 300) {
            try {
                const data = JSON.parse(xhr.responseText);
                console.log('✅ Google Drive upload response:', data);
                
                if (data.success) {
                    // Show success celebration with full data object
                    if (typeof showCelebrationDialog === 'function') {
                        showCelebrationDialog(data);
                    } else {
                        alert('Video imported successfully from Google Drive!');
                        window.location.reload();
                    }
                } else {
                    // Check if error is related to authentication
                    if (data.error && (data.error.includes('403') || data.error.includes('Forbidden') || data.error.includes('authentication'))) {
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
                        return;
                    }
                    
                    // Show regular error message
                    alert('Failed to import from Google Drive: ' + (data.error || 'Unknown error'));
                    setUploadButtonState('normal');
                    if (progressContainer) progressContainer.style.display = 'none';
                }
            } catch (parseError) {
                console.error('❌ Failed to parse response:', parseError);
                console.error('Response text:', xhr.responseText);
                alert('Upload Error: Failed to parse server response');
                setUploadButtonState('normal');
                if (progressContainer) progressContainer.style.display = 'none';
            }
        } else if (xhr.status === 403) {
            console.warn('⚠️ Google Drive token expired (403 Forbidden)');
            
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
            console.error('❌ Google Drive upload failed:', xhr.status, xhr.statusText);
            alert(`Upload Error: ${xhr.status} ${xhr.statusText}`);
            setUploadButtonState('normal');
            if (progressContainer) progressContainer.style.display = 'none';
        }
    });
    
    // Handle network errors
    xhr.addEventListener('error', () => {
        console.error('❌ Network error during Google Drive upload');
        alert('Upload Error: Network error occurred');
        setUploadButtonState('normal');
        if (progressContainer) progressContainer.style.display = 'none';
    });
    
    // Handle upload abort
    xhr.addEventListener('abort', () => {
        console.warn('⚠️ Google Drive upload aborted');
        
        // Only show alert if NOT intentionally cancelled (to prevent duplicate alerts)
        if (!window.isIntentionalCancel) {
            alert('Upload was cancelled');
        }
        
        setUploadButtonState('normal');
        if (progressContainer) progressContainer.style.display = 'none';
    });
    
    // Send the request
    xhr.open('POST', './ionuploadvideos.php');
    xhr.send(formData);
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
        partSize: 50 * 1024 * 1024, // 50MB chunks - faster, more reliable
        maxConcurrentUploads: 1, // Upload 1 part at a time for network reliability
        maxRetries: 5, // More retries for unstable connections
        endpoint: './ion-uploadermulti.php', // RENAMED: Multipart upload backend handler
        
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
            
            // DON'T send upload_complete yet - let the celebration dialog handle it
            // The celebration dialog will send 'video_uploaded' message to refresh the parent page
            // and will only close when user clicks Close or View All Videos
            
            hideUploadProgress();
            showUploadSuccess(result);
        },
        
        onError: (error) => {
            console.error('❌ R2 Multipart Upload failed:', error);
            
            // Notify parent that upload failed (allows modal to close)
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({ type: 'upload_error' }, '*');
            }
            
            hideUploadProgress();
            showCustomAlert('Upload Error', error.message || 'Upload failed. Please try again.');
        }
    });
    
    // CRITICAL: Store uploader reference for cancellation
    currentR2Uploader = uploader;
    console.log('✅ R2 Uploader reference stored for cancellation');
    
    // Start the upload
    uploader.upload(file, metadata)
        .then(result => {
            console.log('🎉 Upload completed successfully:', result);
            // Clear reference after completion
            currentR2Uploader = null;
        })
        .catch(error => {
            console.error('❌ Upload error:', error);
            // Clear reference after error
            currentR2Uploader = null;
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
    
    // CRITICAL: Add selected channels
    if (metadata.selected_channels) {
        formData.append('selected_channels', metadata.selected_channels);
        console.log('📺 CHANNELS ADDED TO FORMDATA:', metadata.selected_channels);
    } else {
        console.log('📺 No channels selected for this upload');
    }
    
    // Add selected networks
    if (metadata.selected_networks) {
        formData.append('selected_networks', metadata.selected_networks);
        console.log('📡 NETWORKS ADDED TO FORMDATA:', metadata.selected_networks);
    } else {
        console.log('📡 No networks selected for this upload');
    }
    
    // Add thumbnail if available
    if (metadata.thumbnail) {
        formData.append('thumbnail', metadata.thumbnail, 'thumbnail.jpg');
        console.log('✅ THUMBNAIL ADDED TO FORMDATA:', metadata.thumbnail.size, 'bytes');
    } else {
        console.error('⚠️⚠️⚠️ NO THUMBNAIL - BACKEND WILL GENERATE DEFAULT! ⚠️⚠️⚠️');
    }
    
    // Upload file using XMLHttpRequest for progress tracking
    const xhr = new XMLHttpRequest();
    
    // CRITICAL: Store XHR reference for cancellation
    currentXHR = xhr;
    console.log('✅ XHR reference stored for cancellation');
    
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
        
        // Only show alert if NOT intentionally cancelled (to prevent duplicate alerts)
        if (!window.isIntentionalCancel) {
            showCustomAlert('Upload Cancelled', 'Upload was cancelled');
        }
    });
    
    // Send request
    xhr.open('POST', './ionuploadvideos.php');
    xhr.send(formData);
}

function startImportProcess() {
    console.log('📥 Starting import process for:', currentSource, window.importedVideoUrl);
    console.log('📍 DEBUG: startImportProcess called from:', new Error().stack);
    
    // Get form data
    const title = document.getElementById('videoTitle')?.value || 'Imported Video';
    const description = document.getElementById('videoDescription')?.value || '';
    const category = document.getElementById('videoCategory')?.value || 'General';
    const tags = document.getElementById('videoTags')?.value || '';
    const visibility = document.getElementById('videoVisibility')?.value || 'public';
    const badges = document.getElementById('videoBadges')?.value || '';
    const selectedChannels = document.getElementById('selectedChannels')?.value || '';
    
    // Show progress
    showProgress(0, 'Importing video...');
    
    // Create form data for import
    const formData = new FormData();
    formData.append('action', 'platform_import'); // CRITICAL: Tell server this is a platform import
    formData.append('url', window.importedVideoUrl); // Server expects 'url' not 'import_url'
    formData.append('source', currentSource);
    formData.append('title', title);
    formData.append('description', description);
    formData.append('category', category);
    formData.append('tags', tags);
    formData.append('visibility', visibility);
    formData.append('badges', badges);
    formData.append('selected_channels', selectedChannels); // CRITICAL: Include selected channels
    
    console.log('📺 Import channels being sent:', selectedChannels);
    
    // Include selected networks
    const selectedNetworks = getSelectedNetworksString();
    formData.append('selected_networks', selectedNetworks);
    console.log('📡 Import networks being sent:', selectedNetworks);
    
    // Add thumbnail if available (blob or URL)
    if (window.capturedThumbnailBlob) {
        formData.append('thumbnail', window.capturedThumbnailBlob, 'thumbnail.jpg');
        console.log('📸 Sending thumbnail as blob:', window.capturedThumbnailBlob.size, 'bytes');
    } else if (window.capturedThumbnailUrl) {
        // CORS fallback: Send URL and let backend fetch it server-side
        formData.append('thumbnail_url', window.capturedThumbnailUrl);
        console.log('📸 Sending thumbnail as URL (backend will fetch):', window.capturedThumbnailUrl);
    }
    
    // Notify parent that upload is starting (prevents accidental modal close)
    if (window.parent && window.parent !== window) {
        window.parent.postMessage({ type: 'upload_started' }, '*');
    }
    
    // Import video
    fetch('./ionuploadvideos.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => {
        console.log('📥 Import response status:', response.status);
        return response.text();
    })
    .then(text => {
        console.log('📥 Import raw response:', text.substring(0, 500));
        try {
            const data = JSON.parse(text);
            console.log('📥 Import parsed data:', data);
            
            if (data.success) {
                console.log('✅ Import successful');
                console.log('🎉 Celebration data:', {
                    celebration: data.celebration,
                    shortlink: data.shortlink,
                    video_id: data.video_id
                });
                hideProgress();
                
                // DON'T send upload_complete yet - let the celebration dialog handle it
                // The celebration dialog will send 'video_uploaded' message to refresh the parent page
                // and will only close when user clicks Close or View All Videos
                
                showUploadSuccess(data);
            } else {
                throw new Error(data.error || 'Import failed');
            }
        } catch (parseError) {
            console.error('❌ Failed to parse response:', parseError);
            console.error('❌ Raw response:', text);
            throw new Error('Invalid JSON response: ' + parseError.message);
        }
    })
    .catch(error => {
        console.error('❌ Import failed:', error);
        hideProgress();
        
        // Notify parent that upload failed
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({ type: 'upload_error' }, '*');
        }
        
        // Re-enable the button
        const nextBtn = document.getElementById('nextBtn');
        if (nextBtn) {
            nextBtn.disabled = false;
            nextBtn.style.opacity = '1';
            nextBtn.style.cursor = 'pointer';
        }
        
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
        
        // CRITICAL: Reset form to clear "unsaved changes" warning
        // This prevents the browser from showing "You have entered information that hasn't been uploaded yet"
        const uploadForm = document.getElementById('uploadForm');
        if (uploadForm && typeof uploadForm.reset === 'function') {
            uploadForm.reset();
            console.log('✅ Form reset - unsaved changes cleared');
        }
        
        // Also clear the selected file reference
        if (window.selectedFile) {
            window.selectedFile = null;
        }
        
        // CRITICAL: Notify parent window that data is cleared (prevents "Unsaved Changes" warning)
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({ type: 'data_cleared' }, '*');
            console.log('✅ Sent data_cleared message to parent window');
        }
        
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
        
        // Clear unsaved data flag
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({ type: 'data_cleared' }, '*');
        }
    
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
    
    // Calculate total size for combined progress
    const totalSize = selectedFiles.reduce((sum, file) => sum + file.size, 0);
    window.bulkUploadTotalSize = totalSize;
    window.bulkUploadCompletedSize = 0;
    
    // CRITICAL: Initialize array to track uploaded video results for celebration dialog
    window.bulkUploadedVideos = [];
    
    console.log(`📊 Total size to upload: ${formatFileSize(totalSize)}`);
    
    // Show combined progress
    const progressContainer = document.getElementById('bulkProgress');
    if (progressContainer) {
        progressContainer.style.display = 'block';
    }
    
    // Upload files one by one using R2 multipart
    uploadFilesSequentiallyR2(selectedFiles, 0);
}

async function uploadFilesSequentiallyR2(files, index) {
    if (index >= files.length) {
        console.log('✅ All files uploaded successfully');
        showBulkUploadComplete();
        return;
    }
    
    const file = files[index];
    console.log(`\n📤 Uploading file ${index + 1}/${files.length}:`, file.name);
    
    // Update main progress text
    const progressText = document.getElementById('bulkProgressText');
    if (progressText) {
        progressText.textContent = `Uploading ${file.name} (${index + 1}/${files.length})`;
    }
    
    // Show per-file progress
    const fileProgress = document.getElementById(`bulkFileProgress_${index}`);
    if (fileProgress) {
        fileProgress.style.display = 'flex'; // Use flex to match CSS layout
        console.log(`✅ Showing progress for file ${index}`);
    } else {
        console.error(`❌ Progress container not found for file ${index}: bulkFileProgress_${index}`);
    }
    
    // Update file status
    const fileStatus = document.getElementById(`bulkStatus_${index}`);
    if (fileStatus) {
        fileStatus.innerHTML = '<span class="status-text status-uploading">Uploading...</span>';
    }
    
    try {
        // Upload using R2 multipart (same as working single upload)
        const uploadResult = await uploadSingleFileR2(file, index);
        
        // CRITICAL: Store upload result for celebration dialog
        if (uploadResult && uploadResult.success) {
            window.bulkUploadedVideos.push({
                ...uploadResult,
                fileName: file.name
            });
            console.log(`📦 Stored upload result for ${file.name}:`, uploadResult);
        }
        
        // Mark as complete
        if (fileStatus) {
            fileStatus.innerHTML = '<span class="status-text status-success">✅ Complete</span>';
        }
        
        console.log(`✅ File ${index + 1}/${files.length} uploaded successfully`);
        
        // Move to next file
        uploadFilesSequentiallyR2(files, index + 1);
        
    } catch (error) {
        console.error(`❌ Failed to upload file ${index + 1}:`, file.name, error);
        
        // Mark as failed
        if (fileStatus) {
            fileStatus.innerHTML = '<span class="status-text status-error">❌ Failed</span>';
        }
        
        // Ask user if they want to continue
        if (confirm(`Failed to upload "${file.name}"\n\nError: ${error.message}\n\nContinue with remaining files?`)) {
            uploadFilesSequentiallyR2(files, index + 1);
        } else {
            showBulkUploadError(`Upload stopped at file ${index + 1}`);
        }
    }
}

async function uploadSingleFileR2(file, index) {
    // Collect metadata for this file
    // IMPROVED: Use common properties as defaults, then per-file overrides
    const commonCategory = document.getElementById('bulkCommonCategory')?.value || 'General';
    const commonChannel = document.getElementById('bulkCommonChannel')?.value || '';
    const commonVisibility = document.getElementById('bulkCommonVisibility')?.value || 'public';
    
    // Get per-file properties (from hidden inputs or direct inputs)
    const title = document.getElementById(`bulkTitle_${index}`)?.value || file.name.replace(/\.[^/.]+$/, "");
    const description = document.getElementById(`bulkDescription_${index}`)?.value || '';
    const category = document.getElementById(`bulkCategory_${index}`)?.value || commonCategory;
    const tags = document.getElementById(`bulkTags_${index}`)?.value || '';
    const visibility = document.getElementById(`bulkVisibility_${index}`)?.value || commonVisibility;
    
    // For channels, use common channel as default
    const selectedChannelsJson = document.getElementById(`bulkSelectedChannels_${index}`)?.value || 
                                  (commonChannel ? JSON.stringify([commonChannel]) : '[]');
    
    // For networks, get common networks (from global network search if used)
    const commonNetworks = getSelectedNetworksString(); // Get currently selected networks from UI
    const selectedNetworksJson = document.getElementById(`bulkSelectedNetworks_${index}`)?.value || commonNetworks || '[]';
    
    const badges = ''; // Bulk upload doesn't have badge selector yet
    
    const metadata = {
        title: title,
        description: description,
        category: category,
        tags: tags,
        visibility: visibility,
        badges: badges,
        thumbnail: window.bulkThumbnails?.[index] || null,
        selected_channels: selectedChannelsJson,
        selected_networks: selectedNetworksJson
    };
    
    console.log(`📝 Metadata for file ${index}:`, {
        title,
        category,
        visibility,
        hasThumbnail: !!metadata.thumbnail,
        channels: selectedChannelsJson,
        fileSize: formatFileSize(file.size),
        usingCommonProperties: {
            category: category === commonCategory,
            visibility: visibility === commonVisibility
        }
    });
    
    // CRITICAL FIX: Check file size - use appropriate upload method (same logic as single file upload)
    const LARGE_FILE_THRESHOLD = 100 * 1024 * 1024; // 100MB
    
    if (file.size > LARGE_FILE_THRESHOLD && typeof window.R2MultipartUploader !== 'undefined') {
        // Large file (>100MB) - use R2 multipart chunked upload
        console.log(`🚀 File ${index} (${file.name}): Using R2 Multipart Upload (${formatFileSize(file.size)})`);
        return await uploadBulkFileWithMultipart(file, index, metadata);
    } else {
        // Small file (≤100MB) - use regular upload
        console.log(`📤 File ${index} (${file.name}): Using Regular Upload (${formatFileSize(file.size)})`);
        return await uploadBulkFileWithRegular(file, index, metadata);
    }
}

/**
 * Upload a bulk file using R2 Multipart (for files > 100MB)
 */
async function uploadBulkFileWithMultipart(file, index, metadata) {
    console.log(`🚀 Starting multipart upload for bulk file ${index}`);
    
    // Initialize bulk progress details for tooltip
    window.bulkUploadProgressDetails = {
        fileName: file.name,
        fileSize: file.size,
        partSize: 50 * 1024 * 1024,
        totalParts: Math.ceil(file.size / (50 * 1024 * 1024)),
        completedParts: 0,
        uploadingParts: 0,
        failedParts: 0,
        parts: [],
        speed: 0,
        loaded: 0,
        timeRemaining: 0
    };
    
    // Initialize parts array
    for (let i = 1; i <= window.bulkUploadProgressDetails.totalParts; i++) {
        window.bulkUploadProgressDetails.parts.push({
            partNumber: i,
            status: 'pending',
            progress: 0
        });
    }
    
    // Initialize tooltip on first use
    if (!window.bulkTooltipInitialized) {
        setTimeout(() => {
            initBulkProgressTooltip();
            window.bulkTooltipInitialized = true;
        }, 100);
    }
    
    // Create R2 Multipart Uploader instance
    const uploader = new window.R2MultipartUploader({
        partSize: 50 * 1024 * 1024, // 50MB chunks
        maxConcurrentUploads: 1,
        maxRetries: 5,
        endpoint: './ion-uploadermulti.php',
        
        onProgress: (progressData) => {
            // Update per-file progress bar
            const fileProgressBar = document.getElementById(`bulkFileProgressBar_${index}`);
            const fileProgressText = document.getElementById(`bulkFileProgressText_${index}`);
            
            // CRITICAL: Ensure percentage is a valid number
            let percentage = progressData.percentage || 0;
            if (isNaN(percentage)) {
                console.warn(`⚠️ Invalid percentage for file ${index}:`, progressData);
                percentage = 0;
            }
            percentage = Math.round(Math.min(100, Math.max(0, percentage)));
            
            console.log(`📊 Multipart File ${index} (${file.name}) progress: ${percentage}% (${formatFileSize(progressData.loaded)} / ${formatFileSize(progressData.total)})`);
            
            if (fileProgressBar) {
                fileProgressBar.style.width = percentage + '%';
                fileProgressBar.style.transition = 'none';
                // Color code based on progress
                if (percentage < 10) {
                    fileProgressBar.style.background = 'linear-gradient(90deg, #64748b, #475569)'; // gray
                } else if (percentage < 30) {
                    fileProgressBar.style.background = 'linear-gradient(90deg, #3b82f6, #2563eb)'; // blue
                } else if (percentage < 70) {
                    fileProgressBar.style.background = 'linear-gradient(90deg, #f59e0b, #d97706)'; // amber
                } else if (percentage < 100) {
                    fileProgressBar.style.background = 'linear-gradient(90deg, #22c55e, #16a34a)'; // green
                } else {
                    fileProgressBar.style.background = 'linear-gradient(90deg, #10b981, #059669)'; // emerald
                }
                fileProgressBar.offsetHeight;
            }
            
            if (fileProgressText) {
                fileProgressText.textContent = `${percentage}%`;
                // Color code text based on progress
                if (percentage < 10) {
                    fileProgressText.style.color = '#64748b'; // gray
                } else if (percentage < 30) {
                    fileProgressText.style.color = '#2563eb'; // blue
                } else if (percentage < 70) {
                    fileProgressText.style.color = '#d97706'; // amber
                } else if (percentage < 100) {
                    fileProgressText.style.color = '#16a34a'; // green
                } else {
                    fileProgressText.style.color = '#059669'; // emerald
                }
            }
            
            // Update bulk progress details for tooltip
            window.bulkUploadProgressDetails.loaded = progressData.loaded;
            window.bulkUploadProgressDetails.speed = progressData.speed || 0;
            window.bulkUploadProgressDetails.timeRemaining = progressData.timeRemaining || 0;
            
            // Update tooltip if it exists
            if (window.updateBulkProgressTooltip) {
                window.updateBulkProgressTooltip();
            }
            
            // Update combined progress bar
            updateCombinedProgress(index, progressData.loaded);
        },
        
        onSuccess: (result) => {
            console.log(`✅ Multipart file ${index} uploaded successfully:`, result);
            window.bulkUploadCompletedSize = (window.bulkUploadCompletedSize || 0) + file.size;
        },
        
        onError: (error) => {
            console.error(`❌ Multipart file ${index} upload failed:`, error);
            throw error;
        }
    });
    
    // Store reference to uploader for progress tracking
    uploader.file = file;
    uploader.metadata = metadata;
    
    // Start the upload and wait for completion
    const result = await uploader.upload(file, metadata);
    return result;
}

/**
 * Upload a bulk file using regular upload (for files ≤ 100MB)
 */
async function uploadBulkFileWithRegular(file, index, metadata) {
    console.log(`📤 Starting regular upload for bulk file ${index}`);
    
    return new Promise((resolve, reject) => {
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
        formData.append('selected_channels', metadata.selected_channels);
        formData.append('selected_networks', metadata.selected_networks || '');
        
        // Add thumbnail if available
        if (metadata.thumbnail) {
            formData.append('thumbnail', metadata.thumbnail, 'thumbnail.jpg');
            console.log(`✅ Thumbnail added for file ${index}:`, metadata.thumbnail.size, 'bytes');
        }
        
        // Upload file using XMLHttpRequest for progress tracking
        const xhr = new XMLHttpRequest();
        
        // Track upload progress
        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                const percentage = Math.round((e.loaded / e.total) * 100);
                console.log(`📊 Regular File ${index} progress: ${percentage}% (${formatFileSize(e.loaded)} / ${formatFileSize(e.total)})`);
                
                // Update per-file progress bar
                const fileProgressBar = document.getElementById(`bulkFileProgressBar_${index}`);
                const fileProgressText = document.getElementById(`bulkFileProgressText_${index}`);
                
                if (fileProgressBar) {
                    fileProgressBar.style.width = percentage + '%';
                    fileProgressBar.style.transition = 'none';
                    // Color code based on progress
                    if (percentage < 10) {
                        fileProgressBar.style.background = 'linear-gradient(90deg, #64748b, #475569)'; // gray
                    } else if (percentage < 30) {
                        fileProgressBar.style.background = 'linear-gradient(90deg, #3b82f6, #2563eb)'; // blue
                    } else if (percentage < 70) {
                        fileProgressBar.style.background = 'linear-gradient(90deg, #f59e0b, #d97706)'; // amber
                    } else if (percentage < 100) {
                        fileProgressBar.style.background = 'linear-gradient(90deg, #22c55e, #16a34a)'; // green
                    } else {
                        fileProgressBar.style.background = 'linear-gradient(90deg, #10b981, #059669)'; // emerald
                    }
                    fileProgressBar.offsetHeight;
                }
                
                if (fileProgressText) {
                    fileProgressText.textContent = `${percentage}%`;
                    // Color code text based on progress
                    if (percentage < 10) {
                        fileProgressText.style.color = '#64748b'; // gray
                    } else if (percentage < 30) {
                        fileProgressText.style.color = '#2563eb'; // blue
                    } else if (percentage < 70) {
                        fileProgressText.style.color = '#d97706'; // amber
                    } else if (percentage < 100) {
                        fileProgressText.style.color = '#16a34a'; // green
                    } else {
                        fileProgressText.style.color = '#059669'; // emerald
                    }
                }
                
                // Update combined progress bar
                updateCombinedProgress(index, e.loaded);
            }
        });
        
        // Handle completion
        xhr.addEventListener('load', () => {
            console.log(`💾 Regular upload ${index} response status:`, xhr.status);
            
            try {
                const data = JSON.parse(xhr.responseText);
                console.log(`📦 Regular upload ${index} response:`, data);
                
                if (data.success) {
                    console.log(`✅ Regular file ${index} uploaded successfully`);
                    window.bulkUploadCompletedSize = (window.bulkUploadCompletedSize || 0) + file.size;
                    resolve(data);
                } else {
                    console.error(`❌ Regular file ${index} upload failed:`, data.error);
                    reject(new Error(data.error || 'Upload failed'));
                }
            } catch (error) {
                console.error(`❌ Failed to parse response for file ${index}:`, error);
                reject(error);
            }
        });
        
        // Handle errors
        xhr.addEventListener('error', () => {
            console.error(`❌ Network error uploading file ${index}`);
            reject(new Error('Network error'));
        });
        
        xhr.addEventListener('abort', () => {
            console.error(`❌ Upload aborted for file ${index}`);
            
            // Only reject if NOT intentionally cancelled
            if (!window.isIntentionalCancel) {
                reject(new Error('Upload aborted'));
            } else {
                console.log(`✅ File ${index} abort was intentional, not rejecting`);
            }
        });
        
        // Send the request
        xhr.open('POST', './ionuploadvideos.php');
        xhr.send(formData);
    });
}

function updateCombinedProgress(currentFileIndex, currentFileLoaded) {
    const totalSize = window.bulkUploadTotalSize || 0;
    const completedSize = window.bulkUploadCompletedSize || 0;
    
    // Track current file progress for this specific file
    if (!window.bulkFileProgress) {
        window.bulkFileProgress = {};
    }
    window.bulkFileProgress[currentFileIndex] = currentFileLoaded;
    
    // Calculate combined loaded from all files
    let totalLoaded = completedSize;
    Object.keys(window.bulkFileProgress).forEach(fileIndex => {
        const fileLoaded = window.bulkFileProgress[fileIndex] || 0;
        const file = selectedFiles[parseInt(fileIndex)];
        if (file) {
            // Only add if not already completed
            const isCompleted = window.bulkUploadCompletedSize >= file.size;
            if (!isCompleted) {
                totalLoaded += fileLoaded;
            }
        }
    });
    
    // CRITICAL FIX: Cap percentage at 100% to prevent showing 101%+
    let combinedPercentage = totalSize > 0 ? (totalLoaded / totalSize) * 100 : 0;
    combinedPercentage = Math.min(100, combinedPercentage); // Never exceed 100%
    
    console.log(`📊 Combined Progress: ${Math.round(combinedPercentage)}% (${formatFileSize(totalLoaded)} / ${formatFileSize(totalSize)})`);
    
    // Show total progress container in footer (same as single file upload)
    const totalProgressContainer = document.getElementById('bulkProgressContainer');
    if (totalProgressContainer && totalProgressContainer.style.display === 'none') {
        totalProgressContainer.style.display = 'flex';
        console.log('✅ Showing bulk progress container in footer');
        
        // Setup mobile click handler when progress bar is first shown (only on mobile)
        if (window.innerWidth <= 768 && !totalProgressContainer.dataset.mobileHandlerAttached) {
            setTimeout(() => setupMobileProgressClickHandlers(), 100);
            totalProgressContainer.dataset.mobileHandlerAttached = 'true';
        }
    }
    
    // Update total progress bar in footer
    const progressBar = document.getElementById('bulkProgressBar');
    const progressLabel = document.getElementById('bulkProgressLabel');
    const progressPercentage = document.getElementById('bulkProgressPercentage');
    
    // Get current file name for display
    const currentFile = selectedFiles[currentFileIndex];
    const currentFileName = currentFile ? currentFile.name : 'Unknown';
    
    if (progressBar) {
        // Set width directly without transition delay
        progressBar.style.width = combinedPercentage + '%';
        progressBar.style.transition = 'none'; // Disable transition for immediate update
        // Color code based on progress
        if (combinedPercentage < 10) {
            progressBar.style.background = 'linear-gradient(90deg, #64748b, #475569)'; // gray
        } else if (combinedPercentage < 30) {
            progressBar.style.background = 'linear-gradient(90deg, #3b82f6, #2563eb)'; // blue
        } else if (combinedPercentage < 70) {
            progressBar.style.background = 'linear-gradient(90deg, #f59e0b, #d97706)'; // amber
        } else if (combinedPercentage < 100) {
            progressBar.style.background = 'linear-gradient(90deg, #22c55e, #16a34a)'; // green
        } else {
            progressBar.style.background = 'linear-gradient(90deg, #10b981, #059669)'; // emerald
        }
        // Force immediate repaint
        progressBar.offsetHeight;
        console.log(`✅ Combined progress bar updated: ${Math.round(combinedPercentage)}%`);
    }
    
    if (progressLabel) {
        const currentFile = selectedFiles[currentFileIndex];
        const currentFileName = currentFile ? currentFile.name : 'Uploading...';
        const fileNumber = currentFileIndex + 1;
        const totalFiles = selectedFiles.length;
        progressLabel.textContent = `${currentFileName} (${fileNumber}/${totalFiles})`;
        // Color code label text based on progress
        if (combinedPercentage < 10) {
            progressLabel.style.color = '#64748b'; // gray
        } else if (combinedPercentage < 30) {
            progressLabel.style.color = '#2563eb'; // blue
        } else if (combinedPercentage < 70) {
            progressLabel.style.color = '#d97706'; // amber
        } else if (combinedPercentage < 100) {
            progressLabel.style.color = '#16a34a'; // green
        } else {
            progressLabel.style.color = '#059669'; // emerald
        }
    }
    
    if (progressPercentage) {
        progressPercentage.textContent = `${Math.round(combinedPercentage)}%`;
        // Color code percentage text based on progress
        if (combinedPercentage < 10) {
            progressPercentage.style.color = '#64748b'; // gray
        } else if (combinedPercentage < 30) {
            progressPercentage.style.color = '#2563eb'; // blue
        } else if (combinedPercentage < 70) {
            progressPercentage.style.color = '#d97706'; // amber
        } else if (combinedPercentage < 100) {
            progressPercentage.style.color = '#16a34a'; // green
        } else {
            progressPercentage.style.color = '#059669'; // emerald
        }
    }
}

function showBulkUploadComplete() {
    console.log('✅ All bulk uploads complete - showing celebration dialog');
    
    // Update progress bar to 100% and show success message
    const progressBar = document.getElementById('bulkProgressBar');
    const progressLabel = document.getElementById('bulkProgressLabel');
    const progressPercentage = document.getElementById('bulkProgressPercentage');
    const nextBtn = document.getElementById('nextBtn');
    
    if (progressBar) {
        progressBar.style.width = '100%';
    }
    
    if (progressLabel) {
        progressLabel.textContent = 'All files uploaded successfully!';
        progressLabel.style.color = '#10b981'; // Green color
    }
    
    if (progressPercentage) {
        progressPercentage.textContent = '100%';
    }
    
    // CRITICAL: Keep button visible but disabled (don't hide it to prevent layout shift)
    if (nextBtn) {
        nextBtn.disabled = true;
        nextBtn.style.opacity = '0.5';
        nextBtn.style.cursor = 'not-allowed';
        console.log('✅ Upload button disabled (but still visible) to prevent layout shift');
    }
    
    // Show bulk celebration dialog with all uploaded video links
    showBulkCelebrationDialog();
}

function showBulkUploadError(message) {
    const progressLabel = document.getElementById('bulkProgressLabel');
    const nextBtn = document.getElementById('nextBtn');
    
    if (progressLabel) {
        progressLabel.textContent = `Error: ${message}`;
        progressLabel.style.color = '#ef4444'; // Red color for error
    }
    
    // Re-enable button after error
    if (nextBtn) {
        nextBtn.disabled = false;
        nextBtn.style.opacity = '1';
        nextBtn.style.cursor = 'pointer';
        console.log('🔓 Upload button re-enabled after error');
    }
}

// ============================================
// CSV BULK IMPORT PROCESSOR
// ============================================

/**
 * Start CSV bulk import with parallel batch processing
 */
async function startCSVImport() {
    console.log('📊 Starting CSV PARALLEL import for', csvData.length, 'videos');
    
    // Collect global settings
    const globalSettings = {
        category: document.getElementById('csvGlobalCategory')?.value || '',
        channels: document.getElementById('csvGlobalChannels')?.value || '',
        visibility: document.getElementById('csvGlobalVisibility')?.value || ''
    };
    
    console.log('📊 Global settings:', globalSettings);
    
    // Update csvData with any user edits from the UI
    updateCSVDataFromUI();
    
    // Validate we have videos to import
    if (!csvData || csvData.length === 0) {
        alert('No videos to import!');
        return;
    }
    
    // Initialize results tracking
    window.csvImportResults = {
        total: csvData.length,
        successful: [],
        failed: [],
        current: 0,
        inProgress: 0
    };
    
    // Show progress UI
    showCSVImportProgress();
    
    // Disable import button during processing
    const nextBtn = document.getElementById('nextBtn');
    if (nextBtn) {
        nextBtn.disabled = true;
        nextBtn.style.opacity = '0.6';
    }
    
    // PARALLEL BATCH PROCESSING
    const BATCH_SIZE = 5; // Process 5 videos at a time
    const batches = [];
    
    // Split csvData into batches
    for (let i = 0; i < csvData.length; i += BATCH_SIZE) {
        batches.push(csvData.slice(i, i + BATCH_SIZE).map((row, index) => ({
            row,
            originalIndex: i + index
        })));
    }
    
    console.log(`📊 Processing ${csvData.length} videos in ${batches.length} batches of ${BATCH_SIZE}`);
    
    // Process each batch
    for (let batchIndex = 0; batchIndex < batches.length; batchIndex++) {
        const batch = batches[batchIndex];
        console.log(`📦 Processing batch ${batchIndex + 1}/${batches.length} (${batch.length} videos)`);
        
        // Process all videos in this batch in parallel
        const batchPromises = batch.map(async ({ row, originalIndex }) => {
            const videoIndex = originalIndex + 1; // 1-based for display
            
            try {
                window.csvImportResults.inProgress++;
                console.log(`📊 [Batch ${batchIndex + 1}] Starting video ${videoIndex}/${csvData.length}:`, row.ImportTitle || row.ImportURL);
                
                // Merge row data with global settings
                const importData = mergeCSVRowWithGlobals(row, globalSettings);
                
                // Validate import data
                if (!importData.url || !importData.url.trim()) {
                    throw new Error('Missing video URL');
                }
                
                // Import the video with timeout protection
                const result = await Promise.race([
                    importCSVVideo(importData, videoIndex),
                    new Promise((_, reject) => 
                        setTimeout(() => reject(new Error('Import timeout (30s)')), 30000)
                    )
                ]);
                
                window.csvImportResults.successful.push({
                    index: videoIndex,
                    title: row.ImportTitle || 'Video ' + videoIndex,
                    url: row.ImportURL,
                    result: result
                });
                
                console.log(`✅ [Batch ${batchIndex + 1}] Video ${videoIndex} imported successfully`);
                
            } catch (error) {
                console.error(`❌ [Batch ${batchIndex + 1}] Failed to import video ${videoIndex}:`, error);
                
                window.csvImportResults.failed.push({
                    index: videoIndex,
                    title: row.ImportTitle || 'Video ' + videoIndex,
                    url: row.ImportURL,
                    error: error.message || 'Unknown error'
                });
            } finally {
                window.csvImportResults.inProgress--;
                window.csvImportResults.current++;
                
                // Update progress after each video completes
                updateCSVImportProgress();
            }
        });
        
        // Wait for all videos in this batch to complete before moving to next batch
        await Promise.allSettled(batchPromises);
        
        console.log(`✅ Batch ${batchIndex + 1}/${batches.length} complete`);
        
        // Small delay between batches to prevent overwhelming the server
        if (batchIndex < batches.length - 1) {
            await new Promise(resolve => setTimeout(resolve, 1000));
        }
    }
    
    // Re-enable button
    if (nextBtn) {
        nextBtn.disabled = false;
        nextBtn.style.opacity = '1';
    }
    
    console.log('✅ All batches complete!');
    console.log(`   Successful: ${window.csvImportResults.successful.length}`);
    console.log(`   Failed: ${window.csvImportResults.failed.length}`);
    
    // Show completion dialog
    showCSVImportComplete();
}

/**
 * Update CSV data from UI edits
 */
function updateCSVDataFromUI() {
    console.log('📊 Updating CSV data from UI edits');
    
    document.querySelectorAll('.csv-field-input').forEach(input => {
        const index = parseInt(input.dataset.csvIndex);
        const field = input.dataset.csvField;
        const value = input.value.trim();
        
        if (csvData[index]) {
            csvData[index][field] = value;
        }
    });
}

/**
 * Merge CSV row with global settings
 */
function mergeCSVRowWithGlobals(row, globals) {
    return {
        url: row.ImportURL,
        title: row.ImportTitle || '',
        ioneer: row.IONEER || '',
        network: row['ION Network'] || globals.category,
        channels: row['ION Channels'] || globals.channels,
        visibility: row.Visibility || globals.visibility || 'Public',
        thumbnailUrl: row.fetchedThumbnailUrl || '' // Include fetched thumbnail URL
    };
}

/**
 * Import a single video from CSV - Direct backend call
 */
async function importCSVVideo(data, index) {
    console.log(`📊 Importing video ${index}:`, data.title || data.url);
    
    if (!data.url || !data.url.trim()) {
        throw new Error('Missing video URL');
    }
    
    // Detect platform from URL
    const url = data.url.trim();
    const platform = detectPlatformFromURL(url);
    
    if (!platform) {
        throw new Error(`Unsupported platform for URL: ${url}`);
    }
    
    console.log(`📊 Detected platform: ${platform} for video ${index}`);
    
    // Prepare form data for backend
    const formData = new FormData();
    formData.append('action', 'platform_import');
    formData.append('source', platform);
    formData.append('url', url);
    formData.append('title', data.title || '');
    formData.append('description', '');
    formData.append('category', data.network || 'General');
    formData.append('tags', '');
    formData.append('visibility', data.visibility.toLowerCase());
    formData.append('badges', '');
    
    // Add channels if specified
    if (data.channels) {
        const channels = data.channels.split(',').map(c => c.trim()).filter(c => c);
        formData.append('selected_channels', JSON.stringify(channels));
    }
    
    // Add networks if specified
    if (data.networks) {
        const networks = data.networks.split(',').map(n => n.trim()).filter(n => n);
        formData.append('selected_networks', JSON.stringify(networks));
    }
    
    // Add IONEER if specified (assign video to specific user)
    if (data.ioneer) {
        formData.append('ioneer', data.ioneer);
        console.log(`📊 Assigning video ${index} to IONEER: @${data.ioneer}`);
    }
    
    // Add thumbnail URL if available
    if (data.thumbnailUrl) {
        formData.append('thumbnail_url', data.thumbnailUrl);
        console.log(`📊 Including thumbnail URL for video ${index}:`, data.thumbnailUrl);
    }
    
    // Send to backend
    try {
        const response = await fetch('/app/ionuploadvideos.php', {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (result.success) {
            console.log(`✅ Video ${index} imported successfully:`, result);
            return result;
        } else {
            throw new Error(result.error || result.message || 'Import failed');
        }
    } catch (error) {
        console.error(`❌ Import failed for video ${index}:`, error);
        throw error;
    }
}

/**
 * Detect platform from URL
 */
function detectPlatformFromURL(url) {
    const lowerUrl = url.toLowerCase();
    if (lowerUrl.includes('youtube.com') || lowerUrl.includes('youtu.be')) return 'YouTube';
    if (lowerUrl.includes('vimeo.com')) return 'Vimeo';
    if (lowerUrl.includes('muvi.com')) return 'Muvi';
    if (lowerUrl.includes('loom.com')) return 'Loom';
    if (lowerUrl.includes('rumble.com')) return 'Rumble';
    if (lowerUrl.includes('wistia.com')) return 'Wistia';
    return null;
}

/**
 * Show CSV import progress UI
 */
function showCSVImportProgress() {
    const bulkProgressContainer = document.getElementById('bulkProgressContainer');
    if (bulkProgressContainer) {
        bulkProgressContainer.style.display = 'flex';
    }
    
    const bulkProgressLabel = document.getElementById('bulkProgressLabel');
    if (bulkProgressLabel) {
        bulkProgressLabel.textContent = `Importing video 1/${csvData.length}...`;
    }
    
    const bulkProgressBar = document.getElementById('bulkProgressBar');
    if (bulkProgressBar) {
        bulkProgressBar.style.width = '0%';
    }
}

/**
 * Update CSV import progress
 */
function updateCSVImportProgress() {
    const results = window.csvImportResults;
    const progress = Math.round((results.current / results.total) * 100);
    
    const bulkProgressLabel = document.getElementById('bulkProgressLabel');
    if (bulkProgressLabel) {
        bulkProgressLabel.textContent = `Importing video ${results.current}/${results.total}... (${results.successful.length} successful, ${results.failed.length} failed)`;
    }
    
    const bulkProgressBar = document.getElementById('bulkProgressBar');
    if (bulkProgressBar) {
        bulkProgressBar.style.width = `${progress}%`;
    }
    
    const bulkProgressPercentage = document.getElementById('bulkProgressPercentage');
    if (bulkProgressPercentage) {
        bulkProgressPercentage.textContent = `${progress}%`;
    }
}

/**
 * Show CSV import completion
 */
function showCSVImportComplete() {
    console.log('✅ CSV import complete');
    console.log('   Successful:', window.csvImportResults.successful.length);
    console.log('   Failed:', window.csvImportResults.failed.length);
    
    const bulkProgressLabel = document.getElementById('bulkProgressLabel');
    if (bulkProgressLabel) {
        bulkProgressLabel.textContent = `Import complete! ${window.csvImportResults.successful.length} successful, ${window.csvImportResults.failed.length} failed`;
        bulkProgressLabel.style.color = window.csvImportResults.failed.length > 0 ? '#f59e0b' : '#10b981';
    }
    
    // Show celebration dialog
    showCSVCelebrationDialog();
}

/**
 * Show CSV celebration dialog
 */
function showCSVCelebrationDialog() {
    const results = window.csvImportResults;
    
    // Build message
    let message = `Successfully imported ${results.successful.length} out of ${results.total} videos from CSV!`;
    
    if (results.failed.length > 0) {
        message += `\n\n${results.failed.length} videos failed to import:`;
        results.failed.forEach(item => {
            message += `\n• ${item.title}: ${item.error}`;
        });
    }
    
    // Show using custom alert or celebration dialog
    if (typeof showBulkCelebrationDialog === 'function' && results.failed.length === 0) {
        // Use bulk celebration dialog for successful imports
        showBulkCelebrationDialog();
    } else {
        // Show simple alert
        alert(message);
        
        // Close uploader and refresh parent
        setTimeout(() => {
            closeUploaderAndRefreshParent();
        }, 1000);
    }
}

// ============================================
// MOBILE RESPONSIVE HELPERS
// ============================================

/**
 * Force mobile layout by removing conflicting inline styles
 */
function forceMobileLayout() {
    if (window.innerWidth > 768) return;
    
    console.log('📱 Forcing mobile layout...');
    
    // Remove padding/margin from modal overlay
    const modalOverlay = document.querySelector('.modal-overlay');
    if (modalOverlay) {
        modalOverlay.style.padding = '0';
        modalOverlay.style.margin = '0';
    }
    
    // Force full screen on modal container
    const modalContainer = document.querySelector('.modal-container');
    if (modalContainer) {
        modalContainer.style.maxWidth = '100vw';
        modalContainer.style.maxHeight = '100vh';
        modalContainer.style.height = '100vh';
        modalContainer.style.width = '100vw';
        modalContainer.style.borderRadius = '0';
        modalContainer.style.margin = '0';
        modalContainer.style.padding = '0';
    }
    
    // Remove padding from modal body
    const modalBody = document.querySelector('.modal-body');
    if (modalBody) {
        modalBody.style.padding = '0';
        modalBody.style.margin = '0';
    }
    
    // Remove padding from step containers
    const step1 = document.getElementById('step1');
    if (step1) {
        step1.style.padding = '8px';
    }
    
    const step2 = document.getElementById('step2');
    if (step2) {
        step2.style.padding = '0';
        step2.style.margin = '0';
    }
    
    console.log('✅ Mobile layout forced');
}

/**
 * Initialize mobile tabs for video/thumbnail switching
 */
function initializeMobileTabs() {
    // Only initialize on mobile devices (width <= 768px)
    if (window.innerWidth > 768) {
        console.log('📱 Desktop view detected, skipping mobile tabs');
        return;
    }
    
    console.log('📱 Mobile view detected, initializing mobile tabs');
    
    // Force mobile layout first
    forceMobileLayout();
    
    // Create mobile tabs structure
    const videoPreviewColumn = document.querySelector('.video-preview-column');
    if (!videoPreviewColumn) {
        console.log('⚠️ Video preview column not found');
        return;
    }
    
    // Check if tabs already exist
    if (document.querySelector('.mobile-preview-tabs')) {
        console.log('✅ Mobile tabs already initialized');
        return;
    }
    
    // Create tabs container
    const tabsContainer = document.createElement('div');
    tabsContainer.className = 'mobile-preview-tabs';
    tabsContainer.innerHTML = `
        <button class="mobile-tab active" data-tab="video">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px;">
                <polygon points="23 7 16 12 23 17 23 7"></polygon>
                <rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>
            </svg>
            Video Preview
        </button>
        <button class="mobile-tab" data-tab="thumbnail">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px;">
                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                <path d="M21 15l-5-5L5 21"></path>
            </svg>
            Thumbnail
        </button>
    `;
    
    // Insert tabs at the top of video preview column
    videoPreviewColumn.insertBefore(tabsContainer, videoPreviewColumn.firstChild);
    
    // Wrap video preview and thumbnail sections
    const videoPreviewSection = document.querySelector('.video-preview-section');
    const thumbnailSection = document.querySelector('.thumbnail-section');
    
    if (videoPreviewSection) {
        videoPreviewSection.classList.add('mobile-tab-content', 'active');
        videoPreviewSection.setAttribute('data-tab-content', 'video');
        // Ensure video section doesn't show thumbnail content
        videoPreviewSection.style.display = 'block';
    }
    
    if (thumbnailSection) {
        // IMPORTANT: Use the ORIGINAL thumbnail section, not a clone
        // This ensures the auto-capture functionality works properly
        thumbnailSection.classList.add('mobile-tab-content', 'mobile-thumbnail-view');
        thumbnailSection.setAttribute('data-tab-content', 'thumbnail');
        thumbnailSection.style.display = 'none'; // Hidden by default
        
        // Move thumbnail section to video preview column for mobile
        videoPreviewColumn.appendChild(thumbnailSection);
        
        console.log('✅ Mobile thumbnail section moved and configured');
        
        // Re-attach thumbnail button handlers after moving to mobile
        // This ensures all click events work properly on mobile
        setupMobileThumbnailHandlers();
    }
    
    // Setup tab switching with proper event handling
    const tabs = tabsContainer.querySelectorAll('.mobile-tab');
    console.log(`📱 Setting up ${tabs.length} mobile tabs`);
    
    tabs.forEach((tab, index) => {
        const tabName = tab.getAttribute('data-tab');
        console.log(`📱 Tab ${index}: ${tabName}`);
        
        tab.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            
            const clickedTabName = tab.getAttribute('data-tab');
            console.log(`📱 Tab clicked: ${clickedTabName}`);
            
            // Update active tab
            tabs.forEach(t => {
                t.classList.remove('active');
            });
            tab.classList.add('active');
            
            // Update visible content
            const allTabContents = videoPreviewColumn.querySelectorAll('.mobile-tab-content');
            console.log(`📱 Found ${allTabContents.length} tab content sections`);
            
            allTabContents.forEach(content => {
                const contentTabName = content.getAttribute('data-tab-content');
                console.log(`📱 Content section: ${contentTabName}, matching: ${contentTabName === clickedTabName}`);
                
                if (contentTabName === clickedTabName) {
                    content.style.display = 'block';
                    content.classList.add('active');
                    console.log(`📱 ✅ Showing ${contentTabName} content`);
                } else {
                    content.style.display = 'none';
                    content.classList.remove('active');
                    console.log(`📱 ❌ Hiding ${contentTabName} content`);
                }
            });
            
            console.log(`📱 ✅ Switched to ${clickedTabName} tab`);
        });
    });
    
    console.log('✅ Mobile tabs initialized successfully with event listeners');
    
    // MOBILE FIX: After tabs are created, ensure thumbnail handlers are properly attached
    setTimeout(() => {
        console.log('📱 Mobile: Re-attaching thumbnail handlers after tab initialization');
        setupMobileThumbnailHandlers();
        
        // MOBILE FIX: Ensure capture button visibility is updated
        updateCaptureButtonVisibility();
    }, 300);
}

/**
 * Setup or re-attach thumbnail button handlers for mobile view
 */
function setupMobileThumbnailHandlers() {
    console.log('📱 Setting up mobile thumbnail handlers');
    
    // Upload thumbnail button
    const uploadThumbnailBtn = document.getElementById('uploadThumbnailBtn');
    const thumbnailInput = document.getElementById('thumbnailInput');
    
    console.log('📱 Mobile button elements found:', {
        uploadThumbnailBtn: !!uploadThumbnailBtn,
        thumbnailInput: !!thumbnailInput
    });
    
    if (uploadThumbnailBtn && thumbnailInput) {
        // Ensure button is visible
        uploadThumbnailBtn.style.display = 'flex';
        
        // Remove existing listeners by cloning and replacing (clean slate)
        const newUploadBtn = uploadThumbnailBtn.cloneNode(true);
        uploadThumbnailBtn.parentNode.replaceChild(newUploadBtn, uploadThumbnailBtn);
        
        newUploadBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            console.log('🖼️ Mobile: Upload thumbnail clicked');
            thumbnailInput.click();
        });
        console.log('✅ Mobile upload thumbnail handler attached');
    } else {
        console.error('❌ Mobile: Upload thumbnail button or input not found');
    }
    
    // Re-attach file input handler
    if (thumbnailInput) {
        const newInput = thumbnailInput.cloneNode(true);
        thumbnailInput.parentNode.replaceChild(newInput, thumbnailInput);
        
        newInput.addEventListener('change', handleThumbnailUpload);
        console.log('✅ Mobile thumbnail input handler attached');
    }
    
    // From URL button
    const urlThumbnailBtn = document.getElementById('urlThumbnailBtn');
    if (urlThumbnailBtn) {
        // Ensure button is visible
        urlThumbnailBtn.style.display = 'flex';
        
        const newUrlBtn = urlThumbnailBtn.cloneNode(true);
        urlThumbnailBtn.parentNode.replaceChild(newUrlBtn, urlThumbnailBtn);
        
        newUrlBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            console.log('🖼️ Mobile: URL thumbnail clicked');
            showThumbnailUrlDialog();
        });
        console.log('✅ Mobile URL thumbnail handler attached');
    } else {
        console.error('❌ Mobile: From URL button not found');
    }
    
    // Capture Frame button
    const generateThumbnailBtn = document.getElementById('generateThumbnailBtn');
    if (generateThumbnailBtn) {
        console.log('📱 DEBUG: Found generateThumbnailBtn, cloning and replacing');
        const newCaptureBtn = generateThumbnailBtn.cloneNode(true);
        generateThumbnailBtn.parentNode.replaceChild(newCaptureBtn, generateThumbnailBtn);
        
        newCaptureBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            console.log('📍 DEBUG: Mobile Capture Frame button CLICKED');
            generateThumbnail();
        });
        console.log('✅ Mobile capture frame handler attached');
        
        // Update visibility
        updateCaptureButtonVisibility();
    } else {
        console.error('❌ Mobile: Capture Frame button not found');
    }
    
    console.log('✅ Mobile thumbnail handlers setup complete');
}

/**
 * Setup mobile-specific progress bar click handlers to show/hide details
 */
function setupMobileProgressClickHandlers() {
    if (window.innerWidth > 768) return; // Only on mobile
    
    console.log('📱 Setting up mobile progress click handlers');
    
    // Single upload progress bar - use the existing tooltip as inline panel
    const progressBarWrapper = document.getElementById('progressBarWrapper');
    const uploadTooltip = document.getElementById('uploadProgressTooltip');
    
    if (progressBarWrapper && uploadTooltip) {
        // Remove any existing click listeners by cloning
        const newProgressWrapper = progressBarWrapper.cloneNode(true);
        progressBarWrapper.parentNode.replaceChild(newProgressWrapper, progressBarWrapper);
        
        newProgressWrapper.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            console.log('📱 Progress bar clicked');
            
            const isVisible = uploadTooltip.classList.contains('visible');
            if (isVisible) {
                uploadTooltip.classList.remove('visible');
                uploadTooltip.style.display = 'none';
                console.log('📱 Hiding details panel');
            } else {
                uploadTooltip.classList.add('visible');
                uploadTooltip.style.display = 'block';
                console.log('📱 Showing details panel');
            }
        });
        console.log('✅ Single upload progress click handler attached');
    }
    
    // Bulk upload progress bar - use the existing tooltip as inline panel
    const bulkProgressBarWrapper = document.getElementById('bulkProgressBarWrapper');
    const bulkTooltip = document.getElementById('bulkProgressTooltip');
    
    if (bulkProgressBarWrapper && bulkTooltip) {
        // Remove any existing click listeners by cloning
        const newBulkWrapper = bulkProgressBarWrapper.cloneNode(true);
        bulkProgressBarWrapper.parentNode.replaceChild(newBulkWrapper, bulkProgressBarWrapper);
        
        newBulkWrapper.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            console.log('📱 Bulk progress bar clicked');
            
            const isVisible = bulkTooltip.classList.contains('visible');
            if (isVisible) {
                bulkTooltip.classList.remove('visible');
                bulkTooltip.style.display = 'none';
                console.log('📱 Hiding bulk details panel');
            } else {
                bulkTooltip.classList.add('visible');
                bulkTooltip.style.display = 'block';
                console.log('📱 Showing bulk details panel');
            }
        });
        console.log('✅ Bulk upload progress click handler attached');
    }
}

/**
 * Reinitialize mobile tabs when window is resized
 */
window.addEventListener('resize', () => {
    const isMobile = window.innerWidth <= 768;
    const tabsExist = document.querySelector('.mobile-preview-tabs');
    const step2 = document.getElementById('step2');
    
    if (isMobile) {
        forceMobileLayout();
        if (!tabsExist) {
            initializeMobileTabs();
        }
        
        // Force mobile layout for Step 2
        if (step2 && step2.style.display !== 'none') {
            step2.style.setProperty('display', 'flex', 'important');
            step2.style.setProperty('flex-direction', 'column', 'important');
            step2.style.setProperty('gap', '0', 'important');
            step2.style.setProperty('padding', '0', 'important');
            step2.style.setProperty('grid-template-columns', '1fr', 'important');
        }
    } else if (!isMobile && tabsExist) {
        // Remove mobile tabs on desktop
        const tabs = document.querySelector('.mobile-preview-tabs');
        const mobileThumbnail = document.querySelector('.mobile-thumbnail-view');
        if (tabs) tabs.remove();
        if (mobileThumbnail) mobileThumbnail.remove();
        
        // Restore original thumbnail section visibility
        const thumbnailSection = document.querySelector('.thumbnail-section');
        if (thumbnailSection) {
            thumbnailSection.style.display = '';
            thumbnailSection.classList.remove('mobile-tab-content');
        }
        
        // Restore video preview section
        const videoPreviewSection = document.querySelector('.video-preview-section');
        if (videoPreviewSection) {
            videoPreviewSection.style.display = '';
            videoPreviewSection.classList.remove('mobile-tab-content', 'active');
        }
        
        // Restore desktop grid layout for Step 2
        if (step2 && step2.style.display !== 'none') {
            step2.style.setProperty('display', 'grid', 'important');
            step2.style.setProperty('grid-template-columns', '1fr 1fr', 'important');
            step2.style.setProperty('gap', '32px', 'important');
            step2.style.setProperty('padding', '12px 0 12px 12px', 'important');
        }
    }
});

// ============================================
// INITIALIZATION
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 ION Uploader Core initialized');
    
    // CRITICAL: Reset bulk mode state on init
    bulkMode = false;
    selectedFiles = [];
    
    // CRITICAL: Ensure bulk Step 2 starts hidden
    const bulkStep2 = document.getElementById('bulkStep2');
    if (bulkStep2) {
        bulkStep2.style.display = 'none';
        console.log('✅ Bulk Step 2 explicitly hidden on init');
    }
    
    // CRITICAL: Ensure Step 1 is visible on init
    const step1 = document.getElementById('step1');
    if (step1) {
        step1.style.display = 'block';
        console.log('✅ Step 1 explicitly shown on init');
    }
    
    // CRITICAL: Hide page scrollbar when modal is open
    // Add class to both the iframe body/html and parent page body/html (if in iframe)
    try {
        // Add to current document
        document.body.classList.add('modal-open');
        document.documentElement.classList.add('modal-open');
        console.log('✅ Added modal-open class to iframe body and html');
        
        // If in iframe, also add to parent
        if (window.parent && window.parent !== window) {
            window.parent.document.body.classList.add('modal-open');
            window.parent.document.documentElement.classList.add('modal-open');
            console.log('✅ Added modal-open class to parent body and html');
        }
    } catch (e) {
        console.log('ℹ️ Could not access parent document (cross-origin):', e.message);
    }
    
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
        // CRITICAL: Use onclick property assignment (NOT addEventListener) 
        // so we can easily replace the handler when switching to/from cancel mode
        nextBtn.onclick = handleNextButtonClick;
        console.log('✅ Next button onclick handler set to handleNextButtonClick');
        
        // CRITICAL: Set initial disabled state
        checkNextButton();
        console.log('✅ Next button initial state set');
        
        // CRITICAL: Ensure button starts in green state
        nextBtn.style.background = 'linear-gradient(135deg, #10b981, #059669)'; // Explicit green gradient
        console.log('✅ Initial button state set to GREEN');
    }
    
    // CRITICAL: Hide Back button and show Close button on Step 1
    const backBtn = document.getElementById('backBtn');
    if (backBtn) {
        backBtn.style.display = 'none';
        console.log('✅ Back button hidden on Step 1');
    }
    
    // On mobile, Close button is hidden (X icon in header is sufficient)
    // On desktop, show Close button
    const isMobile = window.innerWidth <= 768;
    const closeBtn = document.getElementById('closeBtn');
    if (closeBtn && !isMobile) {
        closeBtn.style.display = 'flex';
        closeBtn.addEventListener('click', closeModal);
        console.log('✅ Close button shown on Step 1 with click handler (desktop)');
    } else if (closeBtn && isMobile) {
        closeBtn.style.display = 'none';
        console.log('✅ Close button hidden on Step 1 (mobile - X icon in header is sufficient)');
    }
    
    // CRITICAL: Setup "Add More" button for bulk upload
    const addMoreBtn = document.getElementById('bulkAddMoreBtn');
    if (addMoreBtn) {
        addMoreBtn.addEventListener('click', function() {
            console.log('📁 Add More button clicked - opening file selector');
            const fileInput = document.getElementById('fileInput');
            if (fileInput) {
                // Trigger file input to add more files
                fileInput.click();
            }
        });
        console.log('✅ Add More button handler attached');
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
    
    // Setup URL input validation with auto-platform detection
    const urlInput = document.getElementById('urlInput');
    if (urlInput) {
        urlInput.addEventListener('input', handleUrlInputChange);
        urlInput.addEventListener('paste', () => {
            setTimeout(handleUrlInputChange, 100); // Delay to allow paste to complete
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
    
    
    // Initialize mobile tabs for video/thumbnail switching
    setTimeout(() => {
        if (window.innerWidth <= 768) {
            forceMobileLayout();
            initializeMobileTabs();
        }
    }, 100);
    
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
            
            console.log('📁 Upload zone clicked - allowing file selection/reselection');
            const fileInput = document.getElementById('fileInput');
            if (fileInput) {
                // CRITICAL: Reset file input value to allow selecting the same file again
                fileInput.value = '';
                console.log('🔄 File input reset - user can select any file (including same file)');
                
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
// THUMBNAIL BUTTON VISIBILITY HELPER
// ============================================
function ensureThumbnailButtonsVisible() {
    console.log('🖼️ Ensuring thumbnail buttons are visible');
    
    const uploadBtn = document.getElementById('uploadThumbnailBtn');
    const urlBtn = document.getElementById('urlThumbnailBtn');
    const captureBtn = document.getElementById('generateThumbnailBtn');
    const thumbnailControls = document.querySelector('.thumbnail-controls');
    
    // Force thumbnail controls container to be visible
    if (thumbnailControls) {
        thumbnailControls.style.display = 'flex';
        thumbnailControls.style.gap = '12px';
        thumbnailControls.style.marginTop = '8px';
        console.log('✅ Thumbnail controls container made visible');
    }
    
    if (uploadBtn) {
        uploadBtn.style.display = 'flex';
        uploadBtn.style.flex = '1';
        uploadBtn.style.padding = '10px 16px';
        uploadBtn.style.background = 'var(--bg-primary)';
        uploadBtn.style.border = '1px solid var(--border-color)';
        uploadBtn.style.borderRadius = '6px';
        uploadBtn.style.fontSize = '13px';
        uploadBtn.style.cursor = 'pointer';
        uploadBtn.style.color = 'var(--text-primary)';
        uploadBtn.style.alignItems = 'center';
        uploadBtn.style.justifyContent = 'center';
        uploadBtn.style.gap = '4px';
        console.log('✅ Upload Custom button made visible and styled');
    }
    
    if (urlBtn) {
        urlBtn.style.display = 'flex';
        urlBtn.style.flex = '1';
        urlBtn.style.padding = '10px 16px';
        urlBtn.style.background = 'var(--bg-primary)';
        urlBtn.style.border = '1px solid var(--border-color)';
        urlBtn.style.borderRadius = '6px';
        urlBtn.style.fontSize = '13px';
        urlBtn.style.cursor = 'pointer';
        urlBtn.style.color = 'var(--text-primary)';
        urlBtn.style.alignItems = 'center';
        urlBtn.style.justifyContent = 'center';
        urlBtn.style.gap = '4px';
        console.log('✅ From URL button made visible and styled');
    }
    
    if (captureBtn) {
        // Update visibility based on current state
        updateCaptureButtonVisibility();
        console.log('✅ Capture Frame button visibility updated');
    }
    
    console.log('🖼️ Thumbnail button visibility check complete');
}

// ============================================
// THUMBNAIL FUNCTIONALITY
// ============================================
function setupThumbnailHandlers() {
    console.log('🖼️ Setting up thumbnail handlers');
    
    // Upload Custom button
    const uploadThumbnailBtn = document.getElementById('uploadThumbnailBtn');
    const thumbnailInput = document.getElementById('thumbnailInput');
    
    console.log('🖼️ Button elements found:', {
        uploadThumbnailBtn: !!uploadThumbnailBtn,
        thumbnailInput: !!thumbnailInput
    });
    
    if (uploadThumbnailBtn && thumbnailInput) {
        // Ensure button is visible
        uploadThumbnailBtn.style.display = 'flex';
        uploadThumbnailBtn.addEventListener('click', () => {
            console.log('🖼️ Upload thumbnail clicked');
            thumbnailInput.click();
        });
        
        thumbnailInput.addEventListener('change', handleThumbnailUpload);
        console.log('✅ Upload Custom button handler attached');
    } else {
        console.error('❌ Upload Custom button or input not found');
    }
    
    // From URL button
    const urlThumbnailBtn = document.getElementById('urlThumbnailBtn');
    if (urlThumbnailBtn) {
        // Ensure button is visible
        urlThumbnailBtn.style.display = 'flex';
        urlThumbnailBtn.addEventListener('click', showThumbnailUrlDialog);
        console.log('✅ From URL button handler attached');
    } else {
        console.error('❌ From URL button not found');
    }
    
    // Capture Frame button
    const generateThumbnailBtn = document.getElementById('generateThumbnailBtn');
    if (generateThumbnailBtn) {
        generateThumbnailBtn.addEventListener('click', (e) => {
            console.log('📍 DEBUG: Capture Frame button CLICKED');
            e.preventDefault();
            e.stopPropagation();
            generateThumbnail();
        });
        console.log('✅ Capture Frame button handler attached');
        
        // Update visibility based on current state
        updateCaptureButtonVisibility();
    } else {
        console.error('❌ Capture Frame button not found');
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
    
    console.log('✅ All thumbnail handlers setup complete');
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
    console.log('📍 DEBUG: generateThumbnail() called');
    
    const playerContainer = document.getElementById('videoPlayerContainer');
    console.log('📍 DEBUG: playerContainer found:', !!playerContainer);
    
    if (!playerContainer) {
        alert('Video player not found. Please load a video first.');
        return;
    }
    
    const video = playerContainer.querySelector('video');
    const iframe = playerContainer.querySelector('iframe');
    console.log('📍 DEBUG: Elements found - video:', !!video, 'iframe:', !!iframe);
    
    if (video) {
        console.log('📍 DEBUG: Found VIDEO element, attempting frame capture');
        console.log('📍 DEBUG: video.readyState:', video.readyState);
        console.log('📍 DEBUG: video.videoWidth:', video.videoWidth);
        console.log('📍 DEBUG: video.videoHeight:', video.videoHeight);
        try {
            if (video.readyState < 2) {
                showSuccessToast('⏳ Video is still loading. Please wait a moment and try again.');
                return;
            }
            
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            
            canvas.width = video.videoWidth || video.clientWidth;
            canvas.height = video.videoHeight || video.clientHeight;
            
            if (canvas.width === 0 || canvas.height === 0) {
                showSuccessToast('⏳ Video not ready. Please wait for the video to load completely.');
                return;
            }
            
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            
            let dataURL;
            try {
                dataURL = canvas.toDataURL('image/jpeg', 0.8);
            } catch (e) {
                if (e.name === 'SecurityError') {
                    console.log('ℹ️ Cannot capture frame due to CORS (expected for external videos)');
                    // Silently skip - the default thumbnail will be used automatically
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
                    
                    // MOBILE FIX: Update preview again to ensure it's visible
                    const isMobile = window.innerWidth <= 768;
                    if (isMobile) {
                        console.log('📱 Mobile: Re-updating thumbnail preview after capture');
                        const preview = document.getElementById('thumbnailPreview');
                        if (preview && preview.src) {
                            preview.style.display = 'block';
                            console.log('📱 Mobile: Thumbnail preview forced visible');
                        }
                    }
                    
                    // Show confirmation with toast notification
                    showThumbnailConfirmation('Frame captured from video', 'custom');
                    
                    // Show success toast
                    showSuccessToast('✅ Video frame captured successfully!');
                }
            }, 'image/jpeg', 0.8);
            
        } catch (error) {
            console.error('❌ Error generating thumbnail:', error);
            showSuccessToast('❌ Failed to capture frame: ' + error.message);
        }
    } else {
        // Check if it's a YouTube or Vimeo iframe - we can fetch thumbnails for these
        const iframe = playerContainer.querySelector('iframe');
        if (iframe && iframe.src) {
            console.log('🎬 Detected iframe video source, attempting to fetch thumbnail');
            
            // Try YouTube
            const youtubeMatch = iframe.src.match(/youtube\.com\/embed\/([a-zA-Z0-9_-]+)/);
            if (youtubeMatch) {
                const videoId = youtubeMatch[1];
                console.log('📷 Fetching YouTube thumbnail for video ID:', videoId);
                generateYouTubeThumbnail(videoId);
                return;
            }
            
            // Try Vimeo
            const vimeoMatch = iframe.src.match(/player\.vimeo\.com\/video\/(\d+)/);
            if (vimeoMatch) {
                const videoId = vimeoMatch[1];
                console.log('📷 Fetching Vimeo thumbnail for video ID:', videoId);
                generateVimeoThumbnail(videoId);
                return;
            }
            
            // If we get here, it's an iframe but not YouTube/Vimeo
            console.log('ℹ️ Cannot capture frames from this iframe source (not YouTube/Vimeo)');
            showSuccessToast('💡 For this video source, please upload a custom thumbnail or use URL option.');
        } else {
            console.log('ℹ️ No video found to capture frame from');
            showSuccessToast('⚠️ No video loaded. Please import or upload a video first.');
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

// Show success toast notification
function showSuccessToast(message) {
    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        padding: 16px 24px;
        border-radius: 12px;
        box-shadow: 0 8px 24px rgba(16, 185, 129, 0.4);
        z-index: 100000;
        font-size: 15px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 10px;
        animation: slideInRight 0.3s ease-out;
    `;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    // Auto-remove after 3 seconds
    setTimeout(() => {
        toast.style.animation = 'slideOutRight 0.3s ease-in';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

function showThumbnailConfirmation(message, type = 'auto') {
    console.log('🔔 Showing thumbnail confirmation:', message, 'Type:', type);
    
    // MOBILE FIX: Show toast notification on mobile
    const isMobile = window.innerWidth <= 768;
    if (isMobile) {
        const toast = document.createElement('div');
        toast.style.cssText = `
            position: fixed;
            top: 80px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            z-index: 100000;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
            display: flex;
            align-items: center;
            gap: 8px;
            animation: slideDown 0.3s ease;
        `;
        toast.innerHTML = `<span style="font-size: 18px;">✓</span> ${message}`;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 2000);
        
        console.log('📱 Mobile: Toast notification shown');
        return; // On mobile, only show toast, not the header badge
    }
    
    // DESKTOP: Show small badge next to "Thumbnail" heading (not on the thumbnail itself)
    const statusBadge = document.getElementById('thumbnailStatusBadge');
    const statusText = document.getElementById('thumbnailStatusText');
    
    if (statusBadge && statusText) {
        // Determine badge colors based on type
        const isCustom = (type === 'custom' || message.includes('Custom') || message.includes('Frame') || message.includes('URL'));
        
        // Set badge style based on type
        if (isCustom) {
            statusBadge.style.color = '#10b981'; // Green for custom
            statusBadge.style.background = 'rgba(16, 185, 129, 0.1)';
            statusBadge.style.border = '1px solid rgba(16, 185, 129, 0.3)';
        } else {
            statusBadge.style.color = '#3b82f6'; // Blue for auto
            statusBadge.style.background = 'rgba(59, 130, 246, 0.1)';
            statusBadge.style.border = '1px solid rgba(59, 130, 246, 0.3)';
        }
        
        // Update text and show badge
        statusText.textContent = message;
        statusBadge.style.display = 'flex';
        
        console.log('✅ Thumbnail status badge updated:', message, '(type:', type, ')');
        
        // Auto-hide badge after 4 seconds ONLY for custom thumbnails
        if (isCustom) {
            setTimeout(() => {
                statusBadge.style.opacity = '0';
                statusBadge.style.transition = 'opacity 0.3s ease';
                setTimeout(() => {
                    statusBadge.style.display = 'none';
                    statusBadge.style.opacity = '1'; // Reset for next time
                    console.log('✅ Custom thumbnail badge auto-hidden');
                }, 300);
            }, 4000);
        }
        // Auto-generated thumbnails keep the badge visible
    } else {
        console.warn('⚠️ Thumbnail status badge elements not found');
    }
}

function updateThumbnailPreview(src) {
    console.log('🖼️ Updating thumbnail preview:', src);
    
    const preview = document.getElementById('thumbnailPreview');
    const placeholder = document.getElementById('thumbnailPlaceholder');
    const isMobile = window.innerWidth <= 768;
    
    // MOBILE FIX: On mobile, ensure thumbnail container is properly set up
    if (isMobile) {
        const thumbnailContainer = document.getElementById('thumbnailContainer');
        if (thumbnailContainer) {
            // Ensure container is visible and has proper styling
            thumbnailContainer.style.display = 'block';
            thumbnailContainer.style.position = 'relative';
            thumbnailContainer.style.width = '100%';
            thumbnailContainer.style.height = '240px';
            thumbnailContainer.style.maxHeight = '240px';
            thumbnailContainer.style.borderRadius = '8px';
            thumbnailContainer.style.overflow = 'hidden';
            thumbnailContainer.style.background = 'var(--bg-primary, #1e293b)';
            thumbnailContainer.style.border = '1px solid var(--border-color, #334155)';
            console.log('📱 Mobile: Thumbnail container styled');
        }
    }
    
    if (preview) {
        // Add error handling for broken images
        preview.onerror = function() {
            console.error('❌ Thumbnail image failed to load:', src);
            // Show placeholder again if image fails
            if (placeholder) {
                placeholder.style.display = 'flex';
                preview.style.display = 'none';
            }
        };
        
        preview.onload = function() {
            console.log('✅ Thumbnail image loaded successfully');
            if (placeholder) {
                placeholder.style.display = 'none';
            }
            
            // MOBILE FIX: Ensure thumbnail is visible and properly styled on mobile tabs
            if (isMobile) {
                console.log('📱 Mobile: Ensuring thumbnail is visible in tab');
                // Force display on mobile
                preview.style.display = 'block';
                preview.style.width = '100%';
                preview.style.height = '100%';
                preview.style.maxHeight = '240px';
                preview.style.objectFit = 'contain';
                preview.style.borderRadius = '8px';
                
                // CRITICAL: Make sure the thumbnail tab container is visible
                const thumbnailSection = document.querySelector('.thumbnail-section');
                const mobileThumbnailView = document.querySelector('.mobile-thumbnail-view');
                const targetSection = mobileThumbnailView || thumbnailSection;
                
                if (targetSection) {
                    // Ensure the thumbnail section has the image visible
                    console.log('📱 Mobile: Forcing thumbnail section visibility');
                    targetSection.style.visibility = 'visible';
                    targetSection.style.opacity = '1';
                }
                
                // Switch to thumbnail tab automatically on mobile after upload
                const thumbnailTab = document.querySelector('.mobile-tab[data-tab="thumbnail"]');
                if (thumbnailTab) {
                    console.log('📱 Mobile: Auto-switching to Thumbnail tab');
                    setTimeout(() => {
                        thumbnailTab.click();
                    }, 300);
                }
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
    const isMobile = window.innerWidth <= 768;
    console.log('📱 Mobile device:', isMobile);
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
    
    if (selectedFile.type && !selectedFile.type.startsWith('video/')) {
        console.log('🖼️ Selected file is not a video:', selectedFile.type);
        console.log('🖼️ ========== AUTO THUMBNAIL ABORTED (not video) ==========');
        return;
    }
    
    // Skip auto-thumbnail for Google Drive and platform imports (they don't have local files)
    if (currentSource === 'googledrive' || currentUploadType === 'import') {
        console.log('🖼️ Skipping auto-thumbnail for', currentSource || 'platform import');
        console.log('🖼️ ========== AUTO THUMBNAIL SKIPPED (external source) ==========');
        return;
    }
    
    console.log('🖼️ Creating video element for thumbnail generation');
    console.log('🖼️ Video file:', selectedFile.name, selectedFile.size, 'bytes');
    
    // For video files, we can generate a thumbnail
    const video = document.createElement('video');
    video.preload = 'metadata';
    video.muted = true; // Required for autoplay in some browsers
    video.playsInline = true; // MOBILE FIX: Required for iOS
    video.crossOrigin = 'anonymous'; // For canvas drawing
    
    // MOBILE FIX: Longer timeout for mobile devices (slower processing)
    const timeoutDuration = isMobile ? 15000 : 10000;
    const timeoutId = setTimeout(() => {
        console.error('❌ Thumbnail generation timed out');
        if (video.src && video.src.startsWith('blob:')) {
            URL.revokeObjectURL(video.src);
        }
    }, timeoutDuration);
    
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
                    
                    // CRITICAL: Force thumbnail visibility on mobile
                    const thumbnailPreview = document.getElementById('thumbnailPreview');
                    const thumbnailPlaceholder = document.getElementById('thumbnailPlaceholder');
                    
                    if (thumbnailPreview) {
                        thumbnailPreview.style.display = 'block';
                        thumbnailPreview.style.maxWidth = '100%';
                        thumbnailPreview.style.height = 'auto';
                        console.log('✅ Thumbnail preview forced visible');
                        
                        // MOBILE FIX: On mobile, ensure thumbnail container is visible
                        if (isMobile) {
                            const thumbnailContainer = document.getElementById('thumbnailContainer');
                            if (thumbnailContainer) {
                                thumbnailContainer.style.display = 'block';
                                thumbnailContainer.style.visibility = 'visible';
                                console.log('📱 Mobile: Thumbnail container forced visible');
                            }
                            
                            // Switch to thumbnail tab to show the result
                            setTimeout(() => {
                                const thumbnailTab = document.querySelector('.mobile-tab[data-tab="thumbnail"]');
                                if (thumbnailTab) {
                                    console.log('📱 Mobile: Auto-switching to thumbnail tab');
                                    thumbnailTab.click();
                                }
                            }, 500);
                        }
                    }
                    
                    if (thumbnailPlaceholder) {
                        thumbnailPlaceholder.style.display = 'none';
                        console.log('✅ Thumbnail placeholder hidden');
                    }
                    
                    console.log('🖼️ Auto thumbnail generated successfully');
                    
                    // MOBILE FIX: Ensure confirmation is shown on both web and mobile
                    setTimeout(() => {
                        showThumbnailConfirmation('Auto-generated from video', 'auto');
                        console.log('📱 Mobile: Thumbnail confirmation shown');
                    }, 100); // Small delay to ensure DOM is ready
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
        
        // Setup mobile click handler when progress bar is first shown (only on mobile)
        if (window.innerWidth <= 768 && !progressContainer.dataset.mobileHandlerAttached) {
            setTimeout(() => setupMobileProgressClickHandlers(), 100);
            progressContainer.dataset.mobileHandlerAttached = 'true';
        }
    }
    
    // Update progress bar width and color based on percentage
    if (progressBar) {
        progressBar.style.width = percentage + '%';
        
        // Change progress bar color based on progress
        if (percentage < 10) {
            progressBar.style.background = 'linear-gradient(90deg, #64748b, #475569)'; // gray
        } else if (percentage < 30) {
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
        if (percentage < 10) {
            statusText.style.color = '#64748b'; // gray
        } else if (percentage < 30) {
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
        if (percentage < 10) {
            percentageText.style.color = '#64748b'; // gray-500
        } else if (percentage < 30) {
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

function hideBulkProgress() {
    const progressContainer = document.getElementById('bulkProgressContainer');
    if (progressContainer) {
        progressContainer.style.display = 'none';
        console.log('✅ Bulk progress container hidden');
    }
}

// ============================================
// DETAILED PROGRESS TOOLTIP SYSTEM
// ============================================

// Global object to track detailed upload progress (single file)
window.uploadProgressDetails = {
    fileName: '',
    fileSize: 0,
    partSize: 0,
    totalParts: 0,
    completedParts: 0,
    uploadingParts: 0,
    failedParts: 0,
    parts: [], // Array of {partNumber, status: 'pending'|'uploading'|'completed'|'failed', progress: 0-100}
    speed: 0,
    loaded: 0,
    timeRemaining: 0
};

// Global object to track detailed bulk upload progress (for multipart uploads in bulk mode)
window.bulkUploadProgressDetails = {
    fileName: '',
    fileSize: 0,
    partSize: 0,
    totalParts: 0,
    completedParts: 0,
    uploadingParts: 0,
    failedParts: 0,
    parts: [],
    speed: 0,
    loaded: 0,
    timeRemaining: 0
};

/**
 * Initialize the progress tooltip hover listeners
 */
function initProgressTooltip() {
    const progressBarWrapper = document.getElementById('progressBarWrapper');
    const tooltip = document.getElementById('uploadProgressTooltip');
    
    if (!progressBarWrapper || !tooltip) return;
    
    let hideTimeout;
    
    // Show tooltip on hover
    progressBarWrapper.addEventListener('mouseenter', () => {
        clearTimeout(hideTimeout);
        tooltip.style.display = 'block';
    });
    
    // Hide tooltip when mouse leaves
    progressBarWrapper.addEventListener('mouseleave', () => {
        hideTimeout = setTimeout(() => {
            tooltip.style.display = 'none';
        }, 200);
    });
    
    // Keep tooltip visible when hovering over it
    tooltip.addEventListener('mouseenter', () => {
        clearTimeout(hideTimeout);
    });
    
    tooltip.addEventListener('mouseleave', () => {
        hideTimeout = setTimeout(() => {
            tooltip.style.display = 'none';
        }, 200);
    });
}

/**
 * Initialize hover tooltip for BULK upload progress bar (shows detailed multipart info)
 */
function initBulkProgressTooltip() {
    const bulkProgressBarWrapper = document.getElementById('bulkProgressBarWrapper');
    const bulkTooltip = document.getElementById('bulkProgressTooltip');
    
    if (!bulkProgressBarWrapper || !bulkTooltip) {
        console.log('⚠️ Bulk progress tooltip elements not found (will initialize when needed)');
        return;
    }
    
    let hideTimeout;
    
    // Show tooltip on hover
    bulkProgressBarWrapper.addEventListener('mouseenter', () => {
        clearTimeout(hideTimeout);
        bulkTooltip.style.display = 'block';
    });
    
    // Hide tooltip when mouse leaves
    bulkProgressBarWrapper.addEventListener('mouseleave', () => {
        hideTimeout = setTimeout(() => {
            bulkTooltip.style.display = 'none';
        }, 200);
    });
    
    // Keep tooltip visible when hovering over it
    bulkTooltip.addEventListener('mouseenter', () => {
        clearTimeout(hideTimeout);
    });
    
    bulkTooltip.addEventListener('mouseleave', () => {
        hideTimeout = setTimeout(() => {
            bulkTooltip.style.display = 'none';
        }, 200);
    });
    
    console.log('✅ Bulk progress tooltip hover handlers initialized');
}

/**
 * Update the detailed progress tooltip with current upload data
 */
function updateProgressTooltip(details) {
    // Update the global progress details
    if (details) {
        Object.assign(window.uploadProgressDetails, details);
    }
    
    const data = window.uploadProgressDetails;
    
    // Update file info
    document.getElementById('tooltipFileName').textContent = data.fileName || 'Unknown';
    document.getElementById('tooltipFileSize').textContent = formatFileSize(data.fileSize);
    document.getElementById('tooltipPartCount').textContent = `${data.totalParts} parts`;
    document.getElementById('tooltipPartSize').textContent = formatFileSize(data.partSize) + ' per part';
    
    // Update upload stats
    document.getElementById('tooltipSpeed').textContent = formatSpeed(data.speed);
    document.getElementById('tooltipTimeRemaining').textContent = formatTime(data.timeRemaining);
    document.getElementById('tooltipUploaded').textContent = formatFileSize(data.loaded);
    document.getElementById('tooltipProgress').textContent = Math.round((data.loaded / data.fileSize) * 100) + '%';
    
    // Update part counts
    document.getElementById('tooltipCompletedParts').textContent = data.completedParts;
    document.getElementById('tooltipUploadingParts').textContent = data.uploadingParts;
    document.getElementById('tooltipFailedParts').textContent = data.failedParts;
    
    // Update parts grid
    updatePartsGrid(data.parts);
}

/**
 * Update the visual parts grid showing each chunk's status
 */
function updatePartsGrid(parts) {
    const grid = document.getElementById('tooltipPartsGrid');
    if (!grid) return;
    
    // Clear existing parts
    grid.innerHTML = '';
    
    // Create a visual square for each part
    parts.forEach((part, index) => {
        const partDiv = document.createElement('div');
        partDiv.style.cssText = `
            width: 24px;
            height: 24px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        `;
        
        // Set color based on status
        if (part.status === 'completed') {
            partDiv.style.background = '#10b981'; // green
        } else if (part.status === 'uploading') {
            partDiv.style.background = `linear-gradient(to right, #3b82f6 ${part.progress}%, #334155 ${part.progress}%)`;
        } else if (part.status === 'failed') {
            partDiv.style.background = '#ef4444'; // red
        } else {
            partDiv.style.background = '#334155'; // gray (pending)
        }
        
        // Add tooltip for each part
        partDiv.title = `Part ${part.partNumber}: ${part.status} ${part.progress > 0 ? '(' + part.progress + '%)' : ''}`;
        
        // Add hover effect
        partDiv.addEventListener('mouseenter', () => {
            partDiv.style.transform = 'scale(1.2)';
            partDiv.style.zIndex = '10';
        });
        
        partDiv.addEventListener('mouseleave', () => {
            partDiv.style.transform = 'scale(1)';
            partDiv.style.zIndex = '1';
        });
        
        grid.appendChild(partDiv);
    });
}

/**
 * Update the detailed BULK upload progress tooltip with current multipart upload data
 */
function updateBulkProgressTooltip(details) {
    // Update the global bulk progress details
    if (details) {
        Object.assign(window.bulkUploadProgressDetails, details);
    }
    
    const data = window.bulkUploadProgressDetails;
    
    // Update file info
    const fileNameEl = document.getElementById('bulkTooltipFileName');
    const fileSizeEl = document.getElementById('bulkTooltipFileSize');
    const partCountEl = document.getElementById('bulkTooltipPartCount');
    const partSizeEl = document.getElementById('bulkTooltipPartSize');
    
    if (fileNameEl) fileNameEl.textContent = data.fileName || 'Unknown';
    if (fileSizeEl) fileSizeEl.textContent = formatFileSize(data.fileSize);
    if (partCountEl) partCountEl.textContent = `${data.totalParts} parts`;
    if (partSizeEl) partSizeEl.textContent = formatFileSize(data.partSize) + ' per part';
    
    // Update upload stats
    const speedEl = document.getElementById('bulkTooltipSpeed');
    const timeEl = document.getElementById('bulkTooltipTimeRemaining');
    const uploadedEl = document.getElementById('bulkTooltipUploaded');
    const progressEl = document.getElementById('bulkTooltipProgress');
    
    if (speedEl) speedEl.textContent = formatSpeed(data.speed);
    if (timeEl) timeEl.textContent = formatTime(data.timeRemaining);
    if (uploadedEl) uploadedEl.textContent = formatFileSize(data.loaded);
    if (progressEl) progressEl.textContent = Math.round((data.loaded / data.fileSize) * 100) + '%';
    
    // Update part counts
    const completedEl = document.getElementById('bulkTooltipCompletedParts');
    const uploadingEl = document.getElementById('bulkTooltipUploadingParts');
    const failedEl = document.getElementById('bulkTooltipFailedParts');
    
    if (completedEl) completedEl.textContent = data.completedParts;
    if (uploadingEl) uploadingEl.textContent = data.uploadingParts;
    if (failedEl) failedEl.textContent = data.failedParts;
    
    // Update parts grid
    updateBulkPartsGrid(data.parts);
}

/**
 * Update the visual BULK parts grid showing each chunk's status
 */
function updateBulkPartsGrid(parts) {
    const grid = document.getElementById('bulkTooltipPartsGrid');
    if (!grid) return;
    
    // Clear existing parts
    grid.innerHTML = '';
    
    // Create a visual square for each part
    parts.forEach((part, index) => {
        const partDiv = document.createElement('div');
        partDiv.style.cssText = `
            width: 24px;
            height: 24px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        `;
        
        // Set color based on status
        if (part.status === 'completed') {
            partDiv.style.background = '#10b981'; // green
        } else if (part.status === 'uploading') {
            partDiv.style.background = `linear-gradient(to right, #3b82f6 ${part.progress}%, #334155 ${part.progress}%)`;
        } else if (part.status === 'failed') {
            partDiv.style.background = '#ef4444'; // red
        } else {
            partDiv.style.background = '#334155'; // gray (pending)
        }
        
        // Add tooltip for each part
        partDiv.title = `Part ${part.partNumber}: ${part.status} ${part.progress > 0 ? '(' + part.progress + '%)' : ''}`;
        
        // Add hover effect
        partDiv.addEventListener('mouseenter', () => {
            partDiv.style.transform = 'scale(1.2)';
            partDiv.style.zIndex = '10';
        });
        
        partDiv.addEventListener('mouseleave', () => {
            partDiv.style.transform = 'scale(1)';
            partDiv.style.zIndex = '1';
        });
        
        grid.appendChild(partDiv);
    });
}

/**
 * Format speed for display (MB/s or KB/s)
 */
function formatSpeed(bytesPerSecond) {
    if (!bytesPerSecond || bytesPerSecond === 0) return '-- MB/s';
    
    const mbps = bytesPerSecond / (1024 * 1024);
    if (mbps >= 1) {
        return mbps.toFixed(2) + ' MB/s';
    } else {
        return (bytesPerSecond / 1024).toFixed(2) + ' KB/s';
    }
}

/**
 * Format time remaining for display (mm:ss)
 */
function formatTime(seconds) {
    if (!seconds || seconds === 0 || !isFinite(seconds)) return '--:--';
    
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    
    if (mins > 60) {
        const hours = Math.floor(mins / 60);
        const remainingMins = mins % 60;
        return `${hours}h ${remainingMins}m`;
    }
    
    return `${mins}:${secs.toString().padStart(2, '0')}`;
}

// ============================================
// BULK CELEBRATION DIALOG
// ============================================
function showBulkCelebrationDialog() {
    console.log('🎉 Showing bulk celebration dialog');
    console.log('📦 Uploaded videos:', window.bulkUploadedVideos);
    
    const uploadedVideos = window.bulkUploadedVideos || [];
    const videoCount = uploadedVideos.length;
    
    if (videoCount === 0) {
        console.error('❌ No uploaded videos to show');
        // Fallback to simple alert and reload
        alert('All files uploaded successfully!');
        if (window.parent) {
            window.parent.location.reload();
        }
        return;
    }
    
    // Create modal
    const modal = document.createElement('div');
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 100000;
        padding: 20px;
    `;
    
    const dialog = document.createElement('div');
    dialog.style.cssText = `
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        border-radius: 16px;
        max-width: 600px;
        width: 100%;
        max-height: 80vh;
        display: flex;
        flex-direction: column;
        border: 1px solid rgba(178, 130, 84, 0.3);
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
    `;
    
    // Header
    const header = document.createElement('div');
    header.style.cssText = `
        padding: 24px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        display: flex;
        align-items: center;
        justify-content: space-between;
    `;
    
    header.innerHTML = `
        <div style="display: flex; align-items: center; gap: 12px;">
            <div style="font-size: 32px;">🎉</div>
            <h2 style="margin: 0; color: #10b981; font-size: 24px; font-weight: 700;">Success!</h2>
        </div>
        <button class="bulk-close-btn" style="background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 24px; padding: 4px; line-height: 1;">×</button>
    `;
    
    // Video count
    const videoCountDiv = document.createElement('div');
    videoCountDiv.style.cssText = `
        padding: 0 24px 16px 24px;
        color: #cbd5e1;
        font-size: 16px;
    `;
    videoCountDiv.textContent = `${videoCount} video${videoCount > 1 ? 's' : ''} uploaded successfully`;
    
    // Videos container (scrollable)
    const videosContainer = document.createElement('div');
    videosContainer.style.cssText = `
        flex: 1;
        overflow-y: auto;
        padding: 0 24px;
        max-height: 400px;
    `;
    
    // Add each video
    uploadedVideos.forEach((video, index) => {
        const videoRow = document.createElement('div');
        videoRow.style.cssText = `
            display: flex;
            gap: 16px;
            padding: 16px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            margin-bottom: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        `;
        
        const shortLink = video.short_link || video.shortlink || '';
        const videoUrl = shortLink ? `${window.location.origin}/v/${shortLink}` : '';
        const title = video.title || video.fileName || `Video ${index + 1}`;
        const thumbnail = video.thumbnail || `${window.location.origin}/assets/default/processing.png`;
        
        videoRow.innerHTML = `
            <div style="flex-shrink: 0;">
                <img src="${thumbnail}" alt="${title}" style="width: 100px; height: 75px; object-fit: cover; border-radius: 8px;">
            </div>
            <div style="flex: 1; min-width: 0;">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                    <input type="text" value="${videoUrl}" readonly 
                           style="flex: 1; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.2); 
                                  color: #10b981; padding: 8px 12px; border-radius: 6px; font-size: 13px; 
                                  font-family: monospace; cursor: text;"
                           onclick="this.select()">
                    <button class="copy-btn" data-url="${videoUrl}" 
                            style="background: rgba(16, 185, 129, 0.2); border: 1px solid #10b981; color: #10b981; 
                                   padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 500;
                                   transition: all 0.2s; white-space: nowrap;"
                            onmouseover="this.style.background='rgba(16, 185, 129, 0.3)'"
                            onmouseout="this.style.background='rgba(16, 185, 129, 0.2)'">
                        📋 Copy
                    </button>
                    <a href="${videoUrl}" target="_blank"
                       style="background: rgba(59, 130, 246, 0.2); border: 1px solid #3b82f6; color: #3b82f6; 
                              padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 500;
                              text-decoration: none; transition: all 0.2s; white-space: nowrap; display: inline-block;"
                       onmouseover="this.style.background='rgba(59, 130, 246, 0.3)'"
                       onmouseout="this.style.background='rgba(59, 130, 246, 0.2)'">
                        🔗 Open
                    </a>
                </div>
                <div style="color: #e2e8f0; font-size: 14px; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${title}">
                    ${title}
                </div>
            </div>
        `;
        
        videosContainer.appendChild(videoRow);
    });
    
    // Footer with close button
    const footer = document.createElement('div');
    footer.style.cssText = `
        padding: 24px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        display: flex;
        justify-content: center;
    `;
    
    footer.innerHTML = `
        <button class="bulk-done-btn" 
                style="background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; 
                       padding: 14px 32px; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer;
                       transition: all 0.2s; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);"
                onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 16px rgba(16, 185, 129, 0.4)'"
                onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(16, 185, 129, 0.3)'">
            ✓ Done
        </button>
    `;
    
    // Assemble dialog
    dialog.appendChild(header);
    dialog.appendChild(videoCountDiv);
    dialog.appendChild(videosContainer);
    dialog.appendChild(footer);
    modal.appendChild(dialog);
    document.body.appendChild(modal);
    
    // Add event listeners
    const closeBtns = modal.querySelectorAll('.bulk-close-btn, .bulk-done-btn');
    closeBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            modal.remove();
            // Refresh parent page
            if (window.parent && window.parent !== window) {
                console.log('📤 Sending close_modal message to parent (will trigger refresh)');
                try {
                    window.parent.postMessage({
                        type: 'close_modal',
                        action: 'close_and_refresh'
                    }, '*');
                } catch (e) {
                    console.error('❌ Error sending close message:', e);
                    window.parent.location.reload();
                }
            } else {
                window.location.href = './creators.php';
            }
        });
    });
    
    // Copy button functionality
    modal.querySelectorAll('.copy-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const url = btn.getAttribute('data-url');
            navigator.clipboard.writeText(url).then(() => {
                const originalText = btn.textContent;
                btn.textContent = '✓ Copied!';
                btn.style.background = 'rgba(16, 185, 129, 0.4)';
                setTimeout(() => {
                    btn.textContent = originalText;
                    btn.style.background = 'rgba(16, 185, 129, 0.2)';
                }, 2000);
            }).catch(err => {
                console.error('Failed to copy:', err);
                alert('Failed to copy URL');
            });
        });
    });
    
    console.log('✅ Bulk celebration dialog shown');
}

// Initialize tooltip on page load
document.addEventListener('DOMContentLoaded', () => {
    initProgressTooltip();
    // Setup mobile progress click handlers (only on mobile)
    if (window.innerWidth <= 768) {
        setTimeout(() => setupMobileProgressClickHandlers(), 500); // Delay to ensure elements are ready
    }
    
    // MOBILE FIX: Set up event delegation for upload options and source buttons
    setupUploadInterfaceHandlers();
});

/**
 * Set up event handlers for upload interface (upload options and source buttons)
 * Uses event delegation for better mobile compatibility
 */
function setupUploadInterfaceHandlers() {
    console.log('🔧 Setting up upload interface event handlers');
    
    // Handle upload option clicks (Upload Your Media vs Import Your Media)
    const uploadOption = document.getElementById('uploadOption');
    const importOption = document.getElementById('importOption');
    
    if (uploadOption) {
        uploadOption.addEventListener('click', (e) => {
            // Don't trigger if clicking on the file input itself
            if (e.target.id === 'fileInput') return;
            
            console.log('📤 Upload option clicked');
            selectUploadType('file');
        });
    }
    
    if (importOption) {
        importOption.addEventListener('click', (e) => {
            // Don't trigger if clicking on a source button
            if (e.target.closest('.source-btn')) return;
            
            console.log('📥 Import option clicked');
            selectUploadType('import');
        });
    }
    
    // Handle source button clicks using event delegation
    document.body.addEventListener('click', (e) => {
        const sourceBtn = e.target.closest('.source-btn');
        if (!sourceBtn) return;
        
        e.preventDefault();
        e.stopPropagation();
        
        const platform = sourceBtn.getAttribute('data-source');
        if (!platform) {
            console.log('⚠️ Source button clicked but no data-source attribute found');
            return;
        }
        
        console.log('🎬 Platform button clicked:', platform);
        
        // Set upload type to import
        if (currentUploadType !== 'import') {
            console.log('📥 Auto-selecting import type');
            currentUploadType = 'import';
            document.getElementById('importOption')?.classList.add('selected');
            document.getElementById('uploadOption')?.classList.remove('selected');
        }
        
        // Handle Google Drive separately (has special picker)
        if (platform === 'googledrive') {
            console.log('📁 Google Drive selected - showing picker');
            
            // Check if arrow was clicked (show dropdown instead)
            const isArrowClick = e.target.id === 'googleDriveArrow' || 
                               e.target.closest('#googleDriveArrow');
            
            if (typeof showGoogleDrivePicker === 'function') {
                // Pass arrow click information
                showGoogleDrivePicker(isArrowClick);
            } else {
                console.error('❌ Google Drive picker function not found');
                alert('Google Drive picker is not available. Please use the URL import option.');
            }
            return;
        }
        
        // For other platforms, select and show URL input
        selectPlatform(platform);
    });
    
    console.log('✅ Upload interface event handlers set up');
}

// ==================== ION NETWORKS SEARCH & MANAGEMENT ====================
// Array to store selected networks (first = featured)
let selectedNetworks = [];

/**
 * Initialize Network Search functionality
 */
function initNetworkSearch() {
    const networkSearch = document.getElementById('networkSearch');
    const networkSearchResults = document.getElementById('networkSearchResults');
    
    if (!networkSearch || !networkSearchResults) {
        console.log('⚠️ Network search elements not found');
        return;
    }
    
    console.log('🔍 Initializing Network Search with', ION_NETWORKS.length, 'networks');
    
    // Input event - search as user types
    networkSearch.addEventListener('input', function(e) {
        const query = e.target.value.trim().toLowerCase();
        
        if (query.length === 0) {
            networkSearchResults.classList.remove('show');
            networkSearchResults.innerHTML = '';
            return;
        }
        
        // Search networks by name, slug, or description
        const results = ION_NETWORKS.filter(network => {
            return network.name.toLowerCase().includes(query) ||
                   network.slug.toLowerCase().includes(query) ||
                   (network.description && network.description.toLowerCase().includes(query)) ||
                   (network.hierarchy && network.hierarchy.toLowerCase().includes(query));
        });
        
        displayNetworkSearchResults(results);
    });
    
    // Click outside to close
    document.addEventListener('click', function(e) {
        if (!networkSearch.contains(e.target) && !networkSearchResults.contains(e.target)) {
            networkSearchResults.classList.remove('show');
        }
    });
    
    // Focus to show all results if empty
    networkSearch.addEventListener('focus', function() {
        if (networkSearch.value.trim().length === 0) {
            displayNetworkSearchResults(ION_NETWORKS);
        }
    });
    
    console.log('✅ Network search initialized');
}

/**
 * Display network search results
 */
function displayNetworkSearchResults(results) {
    const networkSearchResults = document.getElementById('networkSearchResults');
    
    if (results.length === 0) {
        networkSearchResults.innerHTML = '<div class="network-search-result-item" style="color: #94a3b8; cursor: default;">No networks found</div>';
        networkSearchResults.classList.add('show');
        return;
    }
    
    networkSearchResults.innerHTML = results.map(network => {
        // Check if already selected
        const isSelected = selectedNetworks.some(n => n.key === network.key);
        const selectedStyle = isSelected ? 'opacity: 0.5; pointer-events: none;' : '';
        
        return `
            <div class="network-search-result-item" 
                 data-network-key="${network.key}"
                 style="${selectedStyle}"
                 onclick="addNetwork('${network.key}')">
                <div class="network-result-name">
                    ${network.icon ? `<span class="network-result-icon">${network.icon}</span>` : ''}
                    <span>${network.name}</span>
                </div>
                ${network.hierarchy ? `<div class="network-result-hierarchy">${network.hierarchy}</div>` : ''}
                ${network.description ? `<div class="network-result-description">${network.description}</div>` : ''}
            </div>
        `;
    }).join('');
    
    networkSearchResults.classList.add('show');
}

/**
 * Add network to selected list
 */
function addNetwork(networkKey) {
    const network = ION_NETWORKS.find(n => n.key === networkKey);
    
    if (!network) {
        console.error('❌ Network not found:', networkKey);
        return;
    }
    
    // Check if already added
    if (selectedNetworks.some(n => n.key === networkKey)) {
        console.log('⚠️ Network already selected:', network.name);
        return;
    }
    
    // Add to selected networks
    selectedNetworks.push(network);
    console.log('✅ Added network:', network.name, '(Total:', selectedNetworks.length + ')');
    
    // Clear search
    const networkSearch = document.getElementById('networkSearch');
    if (networkSearch) {
        networkSearch.value = '';
    }
    const networkSearchResults = document.getElementById('networkSearchResults');
    if (networkSearchResults) {
        networkSearchResults.classList.remove('show');
    }
    
    // Update display
    updateSelectedNetworksDisplay();
}

/**
 * Remove network from selected list
 */
function removeNetwork(networkKey) {
    const index = selectedNetworks.findIndex(n => n.key === networkKey);
    
    if (index === -1) {
        console.error('❌ Network not found in selected list:', networkKey);
        return;
    }
    
    const removed = selectedNetworks.splice(index, 1)[0];
    console.log('🗑️ Removed network:', removed.name);
    
    // Update display
    updateSelectedNetworksDisplay();
}

/**
 * Move network up in the list (higher priority)
 */
function moveNetworkUp(networkKey) {
    const index = selectedNetworks.findIndex(n => n.key === networkKey);
    
    if (index <= 0) return; // Already at top or not found
    
    // Swap with previous item
    [selectedNetworks[index - 1], selectedNetworks[index]] = 
    [selectedNetworks[index], selectedNetworks[index - 1]];
    
    updateSelectedNetworksDisplay();
}

/**
 * Move network down in the list (lower priority)
 */
function moveNetworkDown(networkKey) {
    const index = selectedNetworks.findIndex(n => n.key === networkKey);
    
    if (index === -1 || index === selectedNetworks.length - 1) return; // Not found or already at bottom
    
    // Swap with next item
    [selectedNetworks[index], selectedNetworks[index + 1]] = 
    [selectedNetworks[index + 1], selectedNetworks[index]];
    
    updateSelectedNetworksDisplay();
}

/**
 * Update the display of selected networks
 */
function updateSelectedNetworksDisplay() {
    const container = document.getElementById('selectedNetworks');
    
    if (!container) {
        console.error('❌ Selected networks container not found');
        return;
    }
    
    if (selectedNetworks.length === 0) {
        container.innerHTML = '';
        return;
    }
    
    container.innerHTML = selectedNetworks.map((network, index) => {
        return `
            <div class="selected-network-item" data-network-key="${network.key}">
                <div class="network-item-info">
                    ${network.icon ? `<span class="network-item-icon">${network.icon}</span>` : ''}
                    <span class="network-item-name">${network.name}</span>
                </div>
                <div class="network-item-actions">
                    ${index > 0 ? `<button type="button" class="network-item-move" onclick="moveNetworkUp('${network.key}')" title="Move up">↑</button>` : ''}
                    ${index < selectedNetworks.length - 1 ? `<button type="button" class="network-item-move" onclick="moveNetworkDown('${network.key}')" title="Move down">↓</button>` : ''}
                    <button type="button" class="network-item-remove" onclick="removeNetwork('${network.key}')" title="Remove">✕</button>
                </div>
            </div>
        `;
    }).join('');
}

/**
 * Get selected network keys as array (for form submission)
 */
function getSelectedNetworkKeys() {
    return selectedNetworks.map(n => n.key);
}

/**
 * Get selected network keys as JSON string (for backend compatibility)
 */
function getSelectedNetworksString() {
    return JSON.stringify(selectedNetworks.map(n => n.key));
}

// Expose network functions to global scope
window.addNetwork = addNetwork;
window.removeNetwork = removeNetwork;
window.moveNetworkUp = moveNetworkUp;
window.moveNetworkDown = moveNetworkDown;
window.getSelectedNetworkKeys = getSelectedNetworkKeys;
window.getSelectedNetworksString = getSelectedNetworksString;

// Initialize network search when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initNetworkSearch);
} else {
    initNetworkSearch();
}

// ==================== END ION NETWORKS ====================

// Expose critical functions to global scope for ionuploaderpro.js
window.checkNextButton = checkNextButton;
window.proceedToStep2 = proceedToStep2;
window.updateProgressTooltip = updateProgressTooltip;
window.updateBulkProgressTooltip = updateBulkProgressTooltip;
window.initBulkProgressTooltip = initBulkProgressTooltip;

console.log('✅ ION Uploader Core loaded successfully');