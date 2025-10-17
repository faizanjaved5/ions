/**
 * ION Profile Dialog - Shared Component
 * Provides user profile editing functionality across the ION platform
 */

class IONProfileDialog {
    constructor() {
        this.modal = null;
        this.isInitialized = false;
    }
    
    /**
     * HTML escape helper
     */
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Open the profile editing dialog
     * @param {Object|string} userData - User data object or user ID
     * @param {boolean} isSelfEdit - Whether this is self-editing mode
     */
    open(userData, isSelfEdit = false) {
        // Handle different parameter formats
        let userId, fullname, email, profileName, handle, phone, dob, location, userUrl, about, photoUrl, userRole, status;
        
        if (typeof userData === 'object' && userData !== null) {
            // Object format from user menu
            userId = userData.user_id;
            fullname = userData.fullname;
            email = userData.email;
            profileName = userData.profile_name;
            handle = userData.handle;
            phone = userData.phone;
            dob = userData.dob;
            location = userData.location;
            userUrl = userData.user_url;
            about = userData.about;
            photoUrl = userData.photo_url;
            userRole = userData.user_role;
            status = userData.status;
        } else {
            // Individual parameters format
            userId = arguments[0];
            fullname = arguments[1];
            email = arguments[2];
            profileName = arguments[3];
            handle = arguments[4];
            phone = arguments[5];
            dob = arguments[6];
            location = arguments[7];
            userUrl = arguments[8];
            about = arguments[9];
            photoUrl = arguments[10];
            userRole = arguments[11];
            status = arguments[12];
            isSelfEdit = arguments[13] || false;
        }

        this.createModal(userId, fullname, email, profileName, handle, phone, dob, location, userUrl, about, photoUrl, userRole, status, isSelfEdit);
    }

    /**
     * Create and display the modal dialog
     */
    createModal(userId, fullname, email, profileName, handle, phone, dob, location, userUrl, about, photoUrl, userRole, status, isSelfEdit) {
        // Remove existing modal if present
        this.close();

        // Create modal overlay
        this.modal = document.createElement('div');
        this.modal.className = 'ion-profile-modal-overlay';
        
        const modalTitle = isSelfEdit ? 'Update My Profile' : 'Edit ION User';
        const roleStatusDisplay = isSelfEdit ? `
            <div class="header-controls">
                <div class="role-display">
                    <span class="user-role role-${userRole.toLowerCase()}">${userRole}</span>
                </div>
                <div class="status-display">
                    <span class="user-status status-${status}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>
                </div>
            </div>
        ` : `
            <div class="header-controls">
                <div class="role-dropdown-container">
                    <div class="user-role role-member clickable" onclick="IONProfile.toggleRoleDropdown()" id="profile-role-display">
                        ${userRole}
                        <svg class="role-arrow" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6,9 12,15 18,9"></polyline>
                        </svg>
                    </div>
                    <div class="role-dropdown" id="profile-role-dropdown" style="display: none;">
                        <div class="role-option" onclick="IONProfile.selectRole('Owner')">Owner</div>
                        <div class="role-option" onclick="IONProfile.selectRole('Admin')">Admin</div>
                        <div class="role-option" onclick="IONProfile.selectRole('Member')">Member</div>
                        <div class="role-option" onclick="IONProfile.selectRole('Viewer')">Viewer</div>
                        <div class="role-option" onclick="IONProfile.selectRole('Creator')">Creator</div>
                        <div class="role-option" onclick="IONProfile.selectRole('Guest')">Guest</div>
                    </div>
                </div>
                <div class="status-container">
                    <div class="user-status status-active clickable" onclick="IONProfile.toggleStatusDropdown()" id="profile-status-display">
                        ${status.charAt(0).toUpperCase() + status.slice(1)}
                        <svg class="status-arrow" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6,9 12,15 18,9"></polyline>
                        </svg>
                    </div>
                    <div class="status-dropdown" id="profile-status-dropdown" style="display: none;">
                        <div class="status-option" onclick="IONProfile.selectStatus('active')">Active</div>
                        <div class="status-option" onclick="IONProfile.selectStatus('inactive')">Inactive</div>
                        <div class="status-option" onclick="IONProfile.selectStatus('blocked')">Blocked</div>
                    </div>
                </div>
            </div>
        `;

        this.modal.innerHTML = `
            <div class="ion-profile-modal">
                <div class="ion-profile-modal-header">
                    <h2>${modalTitle}</h2>
                    ${roleStatusDisplay}
                    <button class="close-btn" onclick="IONProfile.close()">&times;</button>
                </div>
                <div class="ion-profile-modal-body">
                    <form id="ion-profile-form">
                        <input type="hidden" id="profile_user_id" name="edit_user_id" value="${userId}">
                        <input type="hidden" id="profile_is_self_edit" name="is_self_edit" value="${isSelfEdit ? '1' : '0'}">
                        <input type="hidden" id="profile_user_role" name="user_role" value="${userRole}" required>
                        <input type="hidden" id="profile_status" name="status" value="${status}" required>
                        <input type="hidden" id="profile_photo_option" name="photo_option" value="url">
                        
                        <div class="form-row">
                            <div class="form-field">
                                <label for="profile_fullname">Full Name *</label>
                                <input type="text" id="profile_fullname" name="fullname" value="${this.escapeHtml(fullname)}" required>
                            </div>
                            <div class="form-field">
                                <label for="profile_email">Email *</label>
                                <input type="email" id="profile_email" name="email" value="${this.escapeHtml(email)}" required ${isSelfEdit ? 'readonly' : ''}>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-field">
                                <label for="profile_profile_name">Profile Name</label>
                                <input type="text" id="profile_profile_name" name="profile_name" value="${this.escapeHtml(profileName)}">
                            </div>
                            <div class="form-field">
                                <label for="profile_handle">Profile Handle</label>
                                <div class="handle-input-container">
                                    <span class="handle-prefix">@</span>
                                    <input type="text" id="profile_handle" name="handle" value="${this.escapeHtml(handle)}" placeholder="username" pattern="[a-zA-Z0-9._\\-]+" title="Only letters, numbers, dots, underscores, and hyphens allowed">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-field">
                                <label for="profile_phone">Phone</label>
                                <input type="tel" id="profile_phone" name="phone" value="${this.escapeHtml(phone)}">
                            </div>
                            <div class="form-field">
                                <label for="profile_dob">Date of Birth</label>
                                <input type="date" id="profile_dob" name="dob" value="${this.escapeHtml(dob)}" placeholder="Select date" max="${this.getMaxBirthDate()}" min="${this.getMinBirthDate()}">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-field">
                                <label for="profile_location">Your Location (Public)</label>
                                <div class="location-search-container">
                                    <input type="text" id="profile_locationSearch" class="form-input" placeholder="Search for your city..." autocomplete="off" value="${this.escapeHtml(location)}">
                                    <div id="profile_locationSearchResults" class="location-search-results"></div>
                                </div>
                                <input type="hidden" id="profile_location" name="location" value="${this.escapeHtml(location)}">
                            </div>
                            <div class="form-field">
                                <label for="profile_user_url">Website URL</label>
                                <input type="url" id="profile_user_url" name="user_url" value="${this.escapeHtml(userUrl)}" placeholder="https://example.com">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-field" style="flex: 2;">
                                <label for="profile_about">Bio</label>
                                <div class="bio-input-container">
                                    <div class="bio-toolbar" id="profile-bio-toolbar" style="display: none;">
                                        <button type="button" class="toolbar-btn" data-command="bold" title="Bold"><strong>B</strong></button>
                                        <button type="button" class="toolbar-btn" data-command="italic" title="Italic"><em>I</em></button>
                                        <button type="button" class="toolbar-btn" data-command="underline" title="Underline"><u>U</u></button>
                                        <button type="button" class="toolbar-btn" data-command="insertUnorderedList" title="Bullet List">•</button>
                                        <button type="button" class="toolbar-btn" data-command="insertOrderedList" title="Numbered List">1.</button>
                                        <button type="button" class="toolbar-btn" data-command="createLink" title="Insert Link">🔗</button>
                                        <button type="button" class="edit-btn" data-command="removeFormat" title="Clear Formatting">🗑️</button>
                                    </div>
                                    <div class="bio-editor-container">
                                        <div id="profile-bio-editor" class="bio-editor" contenteditable="true" placeholder="Tell us about yourself..." data-placeholder="Tell us about yourself..."></div>
                                        <textarea id="profile_about" name="about" style="display: none;">${about || ''}</textarea>
                                    </div>
                                    <div class="bio-edit-icon">✏️</div>
                                </div>
                            </div>
                            <div class="form-field photo-field" style="flex: 1;">
                                <label for="profile_photo_option">Profile Photo</label>
                                <div class="photo-preview-container">
                                    ${photoUrl ? `<img src="${this.escapeHtml(photoUrl)}" alt="Profile Photo" class="photo-preview" id="profile_photo_preview">` : '<div class="photo-preview-placeholder" id="profile_photo_preview"><span>👤</span></div>'}
                                </div>
                                <div class="photo-tabs">
                                    <button type="button" class="photo-tab active" data-tab="url" id="photo_tab_url">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                                            <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
                                        </svg>
                                        <span>Use URL</span>
                                    </button>
                                    <button type="button" class="photo-tab" data-tab="upload" id="photo_tab_upload">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                            <polyline points="17 8 12 3 7 8"></polyline>
                                            <line x1="12" y1="3" x2="12" y2="15"></line>
                                        </svg>
                                        <span>Upload File</span>
                                    </button>
                                </div>
                                <div class="photo-option-content">
                                    <div class="photo-option active" id="photo_content_url">
                                        <input type="url" id="profile_photo_url" name="photo_url" value="${this.escapeHtml(photoUrl)}" placeholder="https://example.com/photo.jpg" class="photo-url-input">
                                        <button type="button" class="photo-url-load-btn" onclick="IONProfile.loadPhotoFromUrl()">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="23 4 23 10 17 10"></polyline>
                                                <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                                            </svg>
                                            Load
                                        </button>
                                    </div>
                                    <div class="photo-option" id="photo_content_upload">
                                        <input type="file" id="profile_photo_file" name="photo_file" accept="image/jpeg,image/png,image/gif,image/webp" style="display: none;">
                                        <div class="file-upload-area" id="profile_file_upload_area">
                                            <svg class="upload-icon" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                                <polyline points="17 8 12 3 7 8"></polyline>
                                                <line x1="12" y1="3" x2="12" y2="15"></line>
                                            </svg>
                                            <p class="upload-text">Click to select image or drag & drop</p>
                                            <p class="upload-hint">Supports: JPG, PNG, GIF, WebP (Max 5MB)</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="ion-profile-modal-footer">
                    <button type="button" class="btn-secondary" onclick="IONProfile.close()">Cancel</button>
                    <button type="button" class="btn-primary" onclick="IONProfile.submit()">${isSelfEdit ? 'Update My Profile' : 'Update User'}</button>
                </div>
            </div>
        `;
        
        // Add styles if not already present
        this.addStyles();
        
        // Add modal to page
        document.body.appendChild(this.modal);
        
        // Initialize interactive elements
        this.initializeBioEditor();
        this.initializePhotoOptions();
        this.initializeLocationSelector(location);
        
        // Set initial bio content
        const bioEditor = document.getElementById('profile-bio-editor');
        if (bioEditor && about) {
            bioEditor.innerHTML = about;
        }
    }

    /**
     * Close the modal dialog
     */
    close() {
        if (this.modal) {
            this.modal.remove();
            this.modal = null;
        }
    }

    /**
     * Get maximum birth date (18 years ago from today)
     */
    getMaxBirthDate() {
        const today = new Date();
        today.setFullYear(today.getFullYear() - 18);
        return today.toISOString().split('T')[0];
    }

    /**
     * Get minimum birth date (100 years ago from today)
     */
    getMinBirthDate() {
        const today = new Date();
        today.setFullYear(today.getFullYear() - 100);
        return today.toISOString().split('T')[0];
    }

    /**
     * Submit the form
     */
    submit() {
        const form = document.getElementById('ion-profile-form');
        const submitBtn = document.querySelector('.ion-profile-modal-footer .btn-primary');
        const originalText = submitBtn.textContent;
        const editUserId = document.getElementById('profile_user_id').value;
        const isSelfEdit = document.getElementById('profile_is_self_edit').value === '1';
        
        // Validate form
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        // Sync bio content
        const bioEditor = document.getElementById('profile-bio-editor');
        const hiddenTextarea = document.getElementById('profile_about');
        if (bioEditor && hiddenTextarea) {
            hiddenTextarea.value = bioEditor.innerHTML;
        }
        
        const formData = new FormData(form);
        
        // Debug: Log form data
        console.log('Submitting form data:');
        for (let [key, value] of formData.entries()) {
            console.log(`${key}: ${value}`);
        }
        
        // Show loading state
        submitBtn.textContent = isSelfEdit ? 'Updating Profile...' : 'Updating User...';
        submitBtn.disabled = true;
        
        // Send request
        fetch('/app/edit-user.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            console.log('Server response:', data);
            if (data.success) {
                alert(isSelfEdit ? 'Profile updated successfully!' : 'User updated successfully!');
                this.close();
                location.reload();
            } else {
                alert(data.error || 'Failed to update user');
                console.error('Update failed:', data.error);
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while updating the profile');
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        });
    }

    /**
     * Initialize bio editor functionality
     */
    initializeBioEditor() {
        const bioEditor = document.getElementById('profile-bio-editor');
        const hiddenTextarea = document.getElementById('profile_about');
        
        if (bioEditor && hiddenTextarea) {
            // Update hidden textarea when editor content changes
            bioEditor.addEventListener('input', function() {
                hiddenTextarea.value = bioEditor.innerHTML;
            });
        }
    }

    /**
     * Initialize photo options functionality
     */
    initializePhotoOptions() {
        const photoTabUrl = document.getElementById('photo_tab_url');
        const photoTabUpload = document.getElementById('photo_tab_upload');
        const photoContentUrl = document.getElementById('photo_content_url');
        const photoContentUpload = document.getElementById('photo_content_upload');
        const photoFileInput = document.getElementById('profile_photo_file');
        const fileUploadArea = document.getElementById('profile_file_upload_area');
        
        console.log('🔧 Initializing photo options', {
            photoTabUrl: !!photoTabUrl,
            photoTabUpload: !!photoTabUpload,
            photoFileInput: !!photoFileInput,
            fileUploadArea: !!fileUploadArea
        });
        
        // Tab switching
        if (photoTabUrl && photoTabUpload) {
            const photoOptionInput = document.getElementById('profile_photo_option');
            
            photoTabUrl.addEventListener('click', (e) => {
                e.preventDefault();
                console.log('📸 Switching to URL tab');
                photoTabUrl.classList.add('active');
                photoTabUpload.classList.remove('active');
                photoContentUrl.classList.add('active');
                photoContentUpload.classList.remove('active');
                if (photoOptionInput) photoOptionInput.value = 'url';
            });
            
            photoTabUpload.addEventListener('click', (e) => {
                e.preventDefault();
                console.log('📸 Switching to Upload tab');
                photoTabUpload.classList.add('active');
                photoTabUrl.classList.remove('active');
                photoContentUpload.classList.add('active');
                photoContentUrl.classList.remove('active');
                if (photoOptionInput) photoOptionInput.value = 'upload';
            });
        }
        
        // File upload area click
        if (fileUploadArea && photoFileInput) {
            console.log('✅ Setting up file upload area click handler');
            
            fileUploadArea.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                console.log('🖱️ File upload area clicked, triggering file input');
                photoFileInput.click();
            });
            
            // File selection
            photoFileInput.addEventListener('change', (e) => {
                console.log('📁 File input changed', e.target.files.length);
                const file = e.target.files[0];
                if (file) {
                    console.log('📷 File selected:', file.name, file.size, 'bytes');
                    this.handlePhotoFile(file);
                } else {
                    console.log('⚠️ No file selected');
                }
            });
            
            // Drag and drop
            fileUploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                fileUploadArea.classList.add('drag-over');
            });
            
            fileUploadArea.addEventListener('dragleave', () => {
                fileUploadArea.classList.remove('drag-over');
            });
            
            fileUploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                fileUploadArea.classList.remove('drag-over');
                const file = e.dataTransfer.files[0];
                if (file && file.type.startsWith('image/')) {
                    this.handlePhotoFile(file);
                }
            });
        }
    }

    /**
     * Initialize location selector
     */
    initializeLocationSelector(initialLocation) {
        // Check if IONLocationSelector is available
        if (typeof IONLocationSelector === 'undefined') {
            console.warn('IONLocationSelector not loaded');
            return;
        }

        // Initialize the location selector
        this.locationSelector = new IONLocationSelector(
            'profile_locationSearch',
            'profile_locationSearchResults',
            'profile_location'
        );

        // Set initial location if provided
        if (initialLocation) {
            this.locationSelector.setLocation(initialLocation);
        }

        console.log('✅ Location selector initialized for profile dialog');
    }

    /**
     * Handle photo file selection
     */
    handlePhotoFile(file) {
        // Validate file size (5MB max)
        if (file.size > 5 * 1024 * 1024) {
            alert('File size must be less than 5MB');
            return;
        }
        
        // Preview the image
        const reader = new FileReader();
        reader.onload = (e) => {
            this.updatePhotoPreview(e.target.result);
        };
        reader.readAsDataURL(file);
    }

    /**
     * Load photo from URL
     */
    loadPhotoFromUrl() {
        const urlInput = document.getElementById('profile_photo_url');
        const url = urlInput.value.trim();
        
        if (!url) {
            alert('Please enter a valid URL');
            return;
        }
        
        // Test if image loads
        const img = new Image();
        img.onload = () => {
            this.updatePhotoPreview(url);
        };
        img.onerror = () => {
            alert('Failed to load image from URL. Please check the URL and try again.');
        };
        img.src = url;
    }

    /**
     * Update photo preview
     */
    updatePhotoPreview(src) {
        const preview = document.getElementById('profile_photo_preview');
        if (preview) {
            if (preview.tagName === 'DIV') {
                // Replace placeholder with image
                preview.outerHTML = `<img src="${src}" alt="Profile Photo" class="photo-preview" id="profile_photo_preview">`;
            } else {
                // Update existing image
                preview.src = src;
            }
        }
    }

    /**
     * Toggle role dropdown (admin mode only)
     */
    toggleRoleDropdown() {
        const dropdown = document.getElementById('profile-role-dropdown');
        if (dropdown) {
            dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
        }
    }

    /**
     * Select role (admin mode only)
     */
    selectRole(role) {
        const display = document.getElementById('profile-role-display');
        const input = document.getElementById('profile_user_role');
        const dropdown = document.getElementById('profile-role-dropdown');
        
        if (display && input) {
            display.innerHTML = `${role} <svg class="role-arrow" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6,9 12,15 18,9"></polyline></svg>`;
            input.value = role;
        }
        
        if (dropdown) {
            dropdown.style.display = 'none';
        }
    }

    /**
     * Toggle status dropdown (admin mode only)
     */
    toggleStatusDropdown() {
        const dropdown = document.getElementById('profile-status-dropdown');
        if (dropdown) {
            dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
        }
    }

    /**
     * Select status (admin mode only)
     */
    selectStatus(status) {
        const display = document.getElementById('profile-status-display');
        const input = document.getElementById('profile_status');
        const dropdown = document.getElementById('profile-status-dropdown');
        
        if (display && input) {
            display.innerHTML = `${status.charAt(0).toUpperCase() + status.slice(1)} <svg class="status-arrow" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6,9 12,15 18,9"></polyline></svg>`;
            input.value = status;
        }
        
        if (dropdown) {
            dropdown.style.display = 'none';
        }
    }

    /**
     * Add CSS styles for the modal
     */
    addStyles() {
        if (document.getElementById('ion-profile-dialog-styles')) {
            return; // Styles already added
        }

        const styles = document.createElement('style');
        styles.id = 'ion-profile-dialog-styles';
        styles.textContent = `
            .ion-profile-modal-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.8);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 10000;
                backdrop-filter: blur(5px);
            }
            
            .ion-profile-modal {
                background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
                border-radius: 12px;
                width: 90%;
                max-width: 800px;
                max-height: 90vh;
                overflow-y: auto;
                box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
                color: white;
            }
            
            .ion-profile-modal-header {
                padding: 20px 24px;
                border-bottom: 1px solid #333;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .ion-profile-modal-header h2 {
                margin: 0;
                font-size: 18px;
                font-weight: 600;
            }
            
            .ion-profile-modal-body {
                padding: 24px;
            }
            
            .ion-profile-modal-footer {
                padding: 20px 24px;
                border-top: 1px solid #333;
                display: flex;
                justify-content: flex-end;
                gap: 12px;
            }
            
            .form-row {
                display: flex;
                gap: 16px;
                margin-bottom: 16px;
            }
            
            .form-field {
                flex: 1;
            }
            
            .form-field label {
                display: block;
                margin-bottom: 6px;
                color: #ccc;
                font-weight: 500;
                font-size: 14px;
            }
            
            .form-field input,
            .form-field textarea {
                width: 100%;
                padding: 10px 12px;
                background: #2a2a2a;
                border: 1px solid #444;
                border-radius: 6px;
                color: #fff;
                font-size: 14px;
                box-sizing: border-box;
            }
            
            .form-field input:focus,
            .form-field textarea:focus {
                outline: none;
                border-color: #3b82f6;
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            }
            
            .form-field input:read-only {
                background: #1a1a1a;
                cursor: not-allowed;
                opacity: 0.7;
            }
            
            /* Date picker styling */
            .form-field input[type="date"] {
                position: relative;
                color-scheme: dark;
            }
            
            .form-field input[type="date"]::-webkit-calendar-picker-indicator {
                cursor: pointer;
                background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>') no-repeat center;
                background-size: 16px 16px;
                padding: 8px;
                filter: invert(1);
            }
            
            .handle-input-container {
                display: flex;
                align-items: center;
                width: 100%;
            }
            
            .handle-prefix {
                background: #2a2a2a;
                border: 1px solid #444;
                border-right: none;
                padding: 10px 8px;
                border-radius: 6px 0 0 6px;
                color: #999;
                font-size: 14px;
                flex-shrink: 0;
            }
            
            .handle-input-container input {
                border-radius: 0 6px 6px 0;
                border-left: none;
                flex: 1;
                min-width: 0;
            }
            
            .bio-input-container {
                position: relative;
            }
            
            .bio-editor {
                min-height: 180px;
                max-height: 300px;
                padding: 10px 12px;
                background: #2a2a2a;
                border: 1px solid #444;
                border-radius: 6px;
                color: #fff;
                font-size: 14px;
                line-height: 1.5;
                overflow-y: auto;
            }
            
            .bio-editor:focus {
                outline: none;
                border-color: #3b82f6;
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            }
            
            /* Photo Upload Section */
            .photo-preview-container {
                width: 200px;
                height: 200px;
                max-width: 100%;
                background: #1a1a1a;
                border: 2px dashed #444;
                border-radius: 8px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 12px auto;
                overflow: hidden;
                aspect-ratio: 1 / 1;
            }
            
            .photo-preview {
                width: 100%;
                height: 100%;
                object-fit: cover;
                border-radius: 6px;
            }
            
            .photo-preview-placeholder {
                font-size: 48px;
                color: #555;
                display: flex;
                align-items: center;
                justify-content: center;
                width: 100%;
                height: 100%;
            }
            
            .photo-tabs {
                display: flex;
                gap: 8px;
                margin-bottom: 12px;
            }
            
            .photo-tab {
                flex: 1;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 6px;
                padding: 8px 12px;
                background: #2a2a2a;
                border: 1px solid #444;
                border-radius: 6px;
                color: #999;
                cursor: pointer;
                font-size: 13px;
                transition: all 0.2s ease;
            }
            
            .photo-tab:hover {
                border-color: #555;
                background: #333;
            }
            
            .photo-tab.active {
                background: #3b82f6;
                border-color: #3b82f6;
                color: white;
            }
            
            .photo-tab svg {
                stroke: currentColor;
            }
            
            .photo-option-content {
                position: relative;
            }
            
            .photo-option {
                display: none;
            }
            
            .photo-option.active {
                display: block;
            }
            
            .photo-url-input {
                width: 100%;
                padding: 10px 80px 10px 12px;
                background: #2a2a2a;
                border: 1px solid #444;
                border-radius: 6px;
                color: #fff;
                font-size: 14px;
            }
            
            .photo-url-load-btn {
                position: absolute;
                right: 4px;
                top: 4px;
                padding: 6px 12px;
                background: #3b82f6;
                border: none;
                border-radius: 4px;
                color: white;
                cursor: pointer;
                font-size: 13px;
                display: flex;
                align-items: center;
                gap: 4px;
                transition: all 0.2s ease;
            }
            
            .photo-url-load-btn:hover {
                background: #2563eb;
            }
            
            .file-upload-area {
                padding: 40px 20px;
                background: #2a2a2a;
                border: 2px dashed #444;
                border-radius: 8px;
                text-align: center;
                cursor: pointer;
                transition: all 0.2s ease;
                user-select: none;
                -webkit-user-select: none;
            }
            
            .file-upload-area:hover,
            .file-upload-area.drag-over {
                border-color: #3b82f6;
                background: rgba(59, 130, 246, 0.05);
                transform: translateY(-2px);
            }
            
            .file-upload-area:active {
                transform: translateY(0);
            }
            
            .upload-icon {
                stroke: #666;
                margin-bottom: 12px;
            }
            
            .upload-text {
                margin: 0 0 6px 0;
                color: #ccc;
                font-size: 14px;
                font-weight: 500;
            }
            
            .upload-hint {
                margin: 0;
                color: #999;
                font-size: 12px;
            }
            
            .btn-primary,
            .btn-secondary {
                padding: 10px 20px;
                border: none;
                border-radius: 6px;
                cursor: pointer;
                font-weight: 500;
                font-size: 14px;
                transition: all 0.2s ease;
            }
            
            .btn-primary {
                background: #3b82f6;
                color: white;
            }
            
            .btn-primary:hover {
                background: #2563eb;
            }
            
            .btn-primary:disabled {
                background: #6b7280;
                cursor: not-allowed;
            }
            
            .btn-secondary {
                background: #6b7280;
                color: white;
            }
            
            .btn-secondary:hover {
                background: #5b6470;
            }
            
            .close-btn {
                background: none;
                border: none;
                color: #999;
                font-size: 24px;
                cursor: pointer;
                padding: 0;
                width: 32px;
                height: 32px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 6px;
                transition: all 0.2s ease;
            }
            
            .close-btn:hover {
                background: rgba(255, 255, 255, 0.1);
                color: #fff;
            }
            
            .header-controls {
                display: flex;
                gap: 12px;
                align-items: center;
            }
            
            .role-display,
            .status-display {
                display: flex;
                align-items: center;
                gap: 8px;
            }
            
            .user-role,
            .user-status {
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 12px;
                font-weight: 500;
                text-transform: uppercase;
            }
            
            .role-owner { background: #f59e0b; color: #000; }
            .role-admin { background: #ef4444; color: #fff; }
            .role-member { background: #3b82f6; color: #fff; }
            .role-creator { background: #10b981; color: #fff; }
            .role-viewer { background: #6b7280; color: #fff; }
            .role-guest { background: #9ca3af; color: #000; }
            
            .status-active { background: #10b981; color: #fff; }
            .status-inactive { background: #f59e0b; color: #000; }
            .status-blocked { background: #ef4444; color: #fff; }
            
            .clickable {
                cursor: pointer;
                transition: all 0.2s ease;
            }
            
            .clickable:hover {
                opacity: 0.8;
            }
            
            .role-dropdown,
            .status-dropdown {
                position: absolute;
                top: 100%;
                left: 0;
                background: #2a2a2a;
                border: 1px solid #444;
                border-radius: 6px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
                z-index: 1000;
                min-width: 120px;
            }
            
            .role-option,
            .status-option {
                padding: 8px 12px;
                cursor: pointer;
                font-size: 13px;
                transition: background 0.2s ease;
            }
            
            .role-option:hover,
            .status-option:hover {
                background: #3a3a3a;
            }
            
            .role-option:first-child,
            .status-option:first-child {
                border-radius: 6px 6px 0 0;
            }
            
            .role-option:last-child,
            .status-option:last-child {
                border-radius: 0 0 6px 6px;
            }
            
            /* Location Search Styles */
            .location-search-container {
                position: relative;
            }
            
            .location-search-results {
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: #1a1a1a;
                border: 1px solid #444;
                border-radius: 0 0 8px 8px;
                max-height: 300px;
                overflow-y: auto;
                z-index: 1000;
                display: none;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
                margin-top: -1px;
            }
            
            .location-search-results.show {
                display: block;
            }
            
            .location-search-result-item {
                padding: 12px 16px;
                cursor: pointer;
                transition: background 0.2s ease;
                border-bottom: 1px solid #2a2a2a;
            }
            
            .location-search-result-item:last-child {
                border-bottom: none;
            }
            
            .location-search-result-item:hover {
                background: #2a2a2a;
            }
            
            .location-result-name {
                color: #fff;
                font-size: 14px;
                font-weight: 500;
                margin-bottom: 4px;
            }
            
            .location-result-details {
                color: #999;
                font-size: 12px;
            }
            
            .location-search-empty {
                padding: 16px;
                text-align: center;
                color: #999;
                font-size: 14px;
            }
            
            @media (max-width: 768px) {
                .ion-profile-modal {
                    width: 95%;
                    margin: 20px;
                }
                
                .form-row {
                    flex-direction: column;
                    gap: 12px;
                }
                
                .ion-profile-modal-header {
                    padding: 16px 20px;
                }
                
                .ion-profile-modal-body {
                    padding: 20px;
                }
                
                .ion-profile-modal-footer {
                    padding: 16px 20px;
                }
            }
        `;
        
        document.head.appendChild(styles);
    }
}

// Create global instance
window.IONProfile = new IONProfileDialog();

// Backward compatibility function
window.openEditUserDialog = function(...args) {
    window.IONProfile.open(...args);
};
