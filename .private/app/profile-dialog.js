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
                        
                        <div class="form-row">
                            <div class="form-field">
                                <label for="profile_fullname">Full Name *</label>
                                <input type="text" id="profile_fullname" name="fullname" value="${fullname || ''}" required>
                            </div>
                            <div class="form-field">
                                <label for="profile_email">Email *</label>
                                <input type="email" id="profile_email" name="email" value="${email || ''}" required ${isSelfEdit ? 'readonly' : ''}>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-field">
                                <label for="profile_profile_name">Profile Name</label>
                                <input type="text" id="profile_profile_name" name="profile_name" value="${profileName || ''}">
                            </div>
                            <div class="form-field">
                                <label for="profile_handle">Profile Handle</label>
                                <div class="handle-input-container">
                                    <span class="handle-prefix">@</span>
                                    <input type="text" id="profile_handle" name="handle" value="${handle || ''}" placeholder="username" pattern="[a-zA-Z0-9._-]+" title="Only letters, numbers, dots, underscores, and hyphens allowed">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-field">
                                <label for="profile_phone">Phone</label>
                                <input type="tel" id="profile_phone" name="phone" value="${phone || ''}">
                            </div>
                            <div class="form-field">
                                <label for="profile_dob">Date of Birth</label>
                                <input type="text" id="profile_dob" name="dob" value="${dob || ''}" placeholder="MM/DD/YYYY" pattern="\\d{2}/\\d{2}/\\d{4}" title="Please enter date in MM/DD/YYYY format">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-field">
                                <label for="profile_location">Your Location (Public)</label>
                                <input type="text" id="profile_location" name="location" value="${location || ''}" placeholder="City, Country">
                            </div>
                            <div class="form-field">
                                <label for="profile_user_url">Website URL</label>
                                <input type="url" id="profile_user_url" name="user_url" value="${userUrl || ''}" placeholder="https://example.com">
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
                            <div class="form-field" style="flex: 1;">
                                <label for="profile_photo_option">Profile Photo</label>
                                <div class="photo-option-container">
                                    <div class="photo-option">
                                        <input type="radio" id="profile_photo_url_option" name="photo_option" value="url" checked>
                                        <label for="profile_photo_url_option">Use URL</label>
                                        <input type="url" id="profile_photo_url" name="photo_url" value="${photoUrl || ''}" placeholder="https://example.com/photo.jpg" class="photo-input">
                                    </div>
                                    <div class="photo-option">
                                        <input type="radio" id="profile_photo_upload_option" name="photo_option" value="upload">
                                        <label for="profile_photo_upload_option">Upload File</label>
                                        <input type="file" id="profile_photo_file" name="photo_file" accept="image/*" class="photo-input" style="display: none;">
                                        <div class="file-upload-area" id="profile_file_upload_area" style="display: none;">
                                            <div class="upload-placeholder">
                                                <span>📁</span>
                                                <p>Click to select image or drag & drop</p>
                                                <small>Supports: JPG, PNG, GIF, WebP (Max 5MB)</small>
                                            </div>
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
            if (data.success) {
                alert(isSelfEdit ? 'Profile updated successfully!' : 'User updated successfully!');
                this.close();
                location.reload();
            } else {
                alert(data.error || 'Failed to update user');
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
        const photoUrlOption = document.getElementById('profile_photo_url_option');
        const photoUploadOption = document.getElementById('profile_photo_upload_option');
        const photoUrlInput = document.getElementById('profile_photo_url');
        const photoFileInput = document.getElementById('profile_photo_file');
        const fileUploadArea = document.getElementById('profile_file_upload_area');
        
        if (photoUrlOption && photoUploadOption) {
            photoUrlOption.addEventListener('change', function() {
                if (this.checked) {
                    photoUrlInput.style.display = 'block';
                    photoFileInput.style.display = 'none';
                    fileUploadArea.style.display = 'none';
                }
            });
            
            photoUploadOption.addEventListener('change', function() {
                if (this.checked) {
                    photoUrlInput.style.display = 'none';
                    photoFileInput.style.display = 'block';
                    fileUploadArea.style.display = 'block';
                }
            });
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
            
            .handle-input-container {
                display: flex;
                align-items: center;
            }
            
            .handle-prefix {
                background: #2a2a2a;
                border: 1px solid #444;
                border-right: none;
                padding: 10px 8px;
                border-radius: 6px 0 0 6px;
                color: #999;
                font-size: 14px;
            }
            
            .handle-input-container input {
                border-radius: 0 6px 6px 0;
                border-left: none;
            }
            
            .bio-input-container {
                position: relative;
            }
            
            .bio-editor {
                min-height: 80px;
                padding: 10px 12px;
                background: #2a2a2a;
                border: 1px solid #444;
                border-radius: 6px;
                color: #fff;
                font-size: 14px;
                line-height: 1.5;
            }
            
            .bio-editor:focus {
                outline: none;
                border-color: #3b82f6;
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            }
            
            .photo-option-container {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }
            
            .photo-option {
                display: flex;
                flex-direction: column;
                gap: 6px;
            }
            
            .photo-option label {
                font-size: 13px;
                color: #bbb;
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
