<?php
/**
 * Profile Wizard Component
 * Can be included in any page to show the profile completion wizard
 * Usage: include 'profile-wizard.php';
 */

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    return;
}

// Check if wizard should be shown
$show_wizard = !empty($_SESSION['show_profile_wizard']);

// Get user data for pre-filling
$user_email = $_SESSION['email'] ?? '';
$user_fullname = $_SESSION['fullname'] ?? '';
$user_id = $_SESSION['user_id'] ?? '';

// Generate CSRF token if needed
if (empty($_SESSION['wizard_csrf_token'])) {
    $_SESSION['wizard_csrf_token'] = bin2hex(random_bytes(32));
}
?>

<?php if ($show_wizard): ?>
<!-- Profile Wizard Modal -->
<div id="profile-wizard-overlay" class="wizard-overlay active">
    <div class="wizard-container">
        <div class="wizard-card">
            <div class="wizard-content">
                <!-- Header -->
                <div class="wizard-header">
                    <h1>Welcome to Your Profile</h1>
                    <p>Let's get you set up with a few quick steps</p>
                </div>

                <!-- Progress Bar -->
                <div class="progress-container">
                    <div class="progress-header">
                        <span class="step-counter">Step <span id="current-step">1</span> of 4</span>
                        <span class="step-title" id="step-title">Basic Info</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" id="progress-fill" style="width: 25%"></div>
                    </div>
                </div>

                <!-- Step Content -->
                <div class="step-content" id="step-content">
                    <!-- Step 1: Basic Info -->
                    <div id="step-1" class="step">
                        <div class="step-header">
                            <h2>Let's start with the basics</h2>
                            <p>Tell us your name and email to get started</p>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label" for="wizard-fullName">Full Name *</label>
                                <input type="text" id="wizard-fullName" class="form-input" 
                                       placeholder="Enter your full name" 
                                       value="<?= htmlspecialchars($user_fullname) ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="wizard-email">Email *</label>
                                <input type="email" id="wizard-email" class="form-input" 
                                       placeholder="your@email.com" 
                                       value="<?= htmlspecialchars($user_email) ?>" 
                                       readonly>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Profile Setup -->
                    <div id="step-2" class="step hidden">
                        <div class="step-header">
                            <h2>Set up your profile</h2>
                            <p>Choose how you want to be displayed to others</p>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label" for="wizard-profileName">Profile Name</label>
                                <input type="text" id="wizard-profileName" class="form-input" placeholder="Display name">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="wizard-profileHandle">Profile Handle</label>
                                <div class="input-with-icon">
                                    <span class="input-icon-left">@</span>
                                    <input type="text" id="wizard-profileHandle" 
                                           class="form-input input-with-left-icon input-with-right-icon" 
                                           placeholder="username">
                                    <div class="input-icon-right" id="handle-validation"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Contact Info -->
                    <div id="step-3" class="step hidden">
                        <div class="step-header">
                            <h2>Contact information</h2>
                            <p>Add your contact details (all optional)</p>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label" for="wizard-phone">Phone</label>
                                <input type="tel" id="wizard-phone" class="form-input" placeholder="Your phone number">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="wizard-dateOfBirth">Date of Birth</label>
                                <input type="date" id="wizard-dateOfBirth" class="form-input">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="wizard-location">Your Location (Public)</label>
                                <div class="input-with-icon">
                                    <input type="text" id="wizard-location" 
                                           class="form-input input-with-right-icon" 
                                           placeholder="City, Country">
                                    <div class="input-icon-right" id="location-validation"></div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="wizard-websiteUrl">Website URL</label>
                                <input type="url" id="wizard-websiteUrl" class="form-input" placeholder="https://example.com">
                            </div>
                        </div>
                    </div>

                    <!-- Step 4: About You -->
                    <div id="step-4" class="step hidden">
                        <div class="step-header">
                            <h2>Tell us about yourself</h2>
                            <p>Add a bio and profile photo to complete your profile</p>
                        </div>
                        <div class="bio-photo-container">
                            <div class="bio-section">
                                <div class="form-group">
                                    <label class="form-label" for="wizard-bio">Bio</label>
                                    <textarea id="wizard-bio" class="form-textarea" 
                                              placeholder="Tell us about yourself..."></textarea>
                                </div>
                            </div>
                            <div class="photo-section">
                                <label class="form-label">Profile Photo</label>
                                <div class="photo-preview">
                                    <div class="photo-preview-container" id="photo-preview">
                                        <svg class="icon icon-lg" viewBox="0 0 24 24">
                                            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                                            <circle cx="12" cy="13" r="4"></circle>
                                        </svg>
                                    </div>
                                </div>
                                <div class="photo-buttons">
                                    <button class="button button-outline dashed" onclick="uploadWizardPhoto()">
                                        <svg class="icon" viewBox="0 0 24 24">
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                            <polyline points="7,10 12,15 17,10"></polyline>
                                            <line x1="12" y1="15" x2="12" y2="3"></line>
                                        </svg>
                                        Upload Photo
                                    </button>
                                    <button class="button button-outline" onclick="useWizardPhotoUrl()">
                                        <svg class="icon" viewBox="0 0 24 24">
                                            <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                                            <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
                                        </svg>
                                        Use URL
                                    </button>
                                </div>
                                <input type="file" id="wizard-photo-upload" class="hidden" 
                                       accept="image/*" onchange="handleWizardFileUpload(event)">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navigation -->
                <div class="navigation">
                    <button class="button button-outline" id="wizard-prev-btn" onclick="wizardPreviousStep()" disabled>
                        <svg class="icon" viewBox="0 0 24 24">
                            <polyline points="15,18 9,12 15,6"></polyline>
                        </svg>
                        Previous
                    </button>

                    <div class="step-indicators">
                        <div class="step-dot active" data-step="1"></div>
                        <div class="step-dot inactive" data-step="2"></div>
                        <div class="step-dot inactive" data-step="3"></div>
                        <div class="step-dot inactive" data-step="4"></div>
                    </div>

                    <button class="button button-primary" id="wizard-next-btn" onclick="wizardNextStep()">
                        Next
                        <svg class="icon" viewBox="0 0 24 24">
                            <polyline points="9,18 15,12 9,6"></polyline>
                        </svg>
                    </button>
                </div>

                <!-- Skip button -->
                <button class="wizard-skip" onclick="skipWizard()">Skip for now</button>
            </div>
        </div>
    </div>
</div>

<!-- Profile Wizard Styles -->
<style>
    :root {
        --wizard-background: rgba(0, 0, 0, 0.9);
        --wizard-card-bg: #1a1a1a;
        --wizard-border: rgba(137, 105, 72, 0.3);
        --wizard-primary: #896948;
        --wizard-primary-hover: #a47e5a;
        --wizard-text: #ffffff;
        --wizard-text-muted: #999999;
        --wizard-input-bg: rgba(255, 255, 255, 0.05);
        --wizard-radius: 12px;
    }

    .wizard-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: var(--wizard-background);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
    }

    .wizard-overlay.active {
        opacity: 1;
        visibility: visible;
    }

    .wizard-container {
        width: 100%;
        max-width: 672px;
        max-height: 90vh;
        overflow-y: auto;
    }

    .wizard-card {
        background: var(--wizard-card-bg);
        border: 1px solid var(--wizard-border);
        border-radius: var(--wizard-radius);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
    }

    .wizard-content {
        padding: 40px;
        position: relative;
    }

    @media (max-width: 768px) {
        .wizard-content {
            padding: 30px 20px;
        }
    }

    .wizard-header {
        text-align: center;
        margin-bottom: 30px;
    }

    .wizard-header h1 {
        font-size: 30px;
        font-weight: bold;
        background: linear-gradient(135deg, var(--wizard-primary), var(--wizard-primary-hover));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-bottom: 8px;
    }

    .wizard-header p {
        color: var(--wizard-text-muted);
        font-size: 16px;
    }

    .progress-container {
        margin-bottom: 30px;
    }

    .progress-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }

    .progress-header .step-counter {
        font-size: 14px;
        font-weight: 500;
        color: var(--wizard-text);
    }

    .progress-header .step-title {
        font-size: 14px;
        color: var(--wizard-text-muted);
    }

    .progress-bar {
        width: 100%;
        height: 8px;
        background-color: rgba(255, 255, 255, 0.1);
        border-radius: var(--wizard-radius);
        overflow: hidden;
    }

    .progress-fill {
        height: 100%;
        background: linear-gradient(135deg, var(--wizard-primary), var(--wizard-primary-hover));
        transition: width 0.3s ease;
    }

    .step-content {
        min-height: 300px;
        margin-bottom: 30px;
        animation: fadeIn 0.3s ease-in-out;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .step {
        display: none;
    }

    .step:not(.hidden) {
        display: block;
    }

    .step-header {
        text-align: center;
        margin-bottom: 30px;
    }

    .step-header h2 {
        font-size: 24px;
        font-weight: 600;
        color: var(--wizard-text);
        margin-bottom: 8px;
    }

    .step-header p {
        color: var(--wizard-text-muted);
    }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 20px;
    }

    @media (min-width: 640px) {
        .form-grid {
            grid-template-columns: 1fr 1fr;
        }
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .form-label {
        font-size: 14px;
        font-weight: 500;
        color: var(--wizard-text);
    }

    .form-input {
        height: 48px;
        padding: 0 16px;
        background-color: var(--wizard-input-bg);
        border: 2px solid rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        color: var(--wizard-text);
        font-size: 16px;
        transition: all 0.3s ease;
    }

    .form-input:focus {
        outline: none;
        border-color: var(--wizard-primary);
        box-shadow: 0 0 0 3px rgba(137, 105, 72, 0.1);
    }

    .form-input[readonly] {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .form-textarea {
        min-height: 150px;
        padding: 12px 16px;
        background-color: var(--wizard-input-bg);
        border: 2px solid rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        color: var(--wizard-text);
        font-size: 16px;
        resize: vertical;
        font-family: inherit;
        transition: all 0.3s ease;
    }

    .form-textarea:focus {
        outline: none;
        border-color: var(--wizard-primary);
        box-shadow: 0 0 0 3px rgba(137, 105, 72, 0.1);
    }

    .input-with-icon {
        position: relative;
    }

    .input-icon-left {
        position: absolute;
        left: 16px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--wizard-text-muted);
    }

    .input-icon-right {
        position: absolute;
        right: 16px;
        top: 50%;
        transform: translateY(-50%);
    }

    .input-with-left-icon {
        padding-left: 40px;
    }

    .input-with-right-icon {
        padding-right: 48px;
    }

    .validation-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 24px;
        height: 24px;
        border-radius: 4px;
        border: 1px solid;
    }

    .validation-icon.success {
        background-color: rgba(16, 185, 129, 0.2);
        border-color: rgba(16, 185, 129, 0.3);
        color: #10b981;
    }

    .validation-icon.error {
        background-color: rgba(239, 68, 68, 0.2);
        border-color: rgba(239, 68, 68, 0.3);
        color: #ef4444;
    }

    .validation-icon.warning {
        background-color: rgba(245, 158, 11, 0.2);
        border-color: rgba(245, 158, 11, 0.3);
        color: #f59e0b;
    }

    .bio-photo-container {
        display: flex;
        flex-direction: column;
        gap: 30px;
    }

    @media (min-width: 768px) {
        .bio-photo-container {
            flex-direction: row;
        }
    }

    .bio-section {
        flex: 1;
    }

    .photo-section {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .photo-preview {
        display: flex;
        justify-content: center;
        margin-bottom: 15px;
    }

    .photo-preview-container {
        width: 150px;
        height: 150px;
        border: 2px solid var(--wizard-border);
        border-radius: 50%;
        overflow: hidden;
        background-color: rgba(255, 255, 255, 0.05);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .photo-preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .photo-buttons {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    .button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 10px 20px;
        font-size: 14px;
        font-weight: 500;
        border-radius: 8px;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
    }

    .button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .button-primary {
        background: linear-gradient(135deg, var(--wizard-primary), var(--wizard-primary-hover));
        color: white;
    }

    .button-primary:hover:not(:disabled) {
        opacity: 0.9;
        box-shadow: 0 8px 20px rgba(137, 105, 72, 0.3);
    }

    .button-outline {
        background-color: transparent;
        border: 1px solid var(--wizard-border);
        color: var(--wizard-text);
    }

    .button-outline:hover:not(:disabled) {
        background-color: rgba(137, 105, 72, 0.1);
        border-color: var(--wizard-primary);
    }

    .button-outline.dashed {
        border-style: dashed;
    }

    .navigation {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
    }

    @media (max-width: 480px) {
        .navigation {
            flex-wrap: wrap;
        }
        
        .step-indicators {
            order: -1;
            width: 100%;
            justify-content: center;
            margin-bottom: 20px;
        }
    }

    .step-indicators {
        display: flex;
        gap: 8px;
    }

    .step-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        transition: all 0.3s ease;
    }

    .step-dot.active {
        background-color: var(--wizard-primary);
        width: 24px;
        border-radius: 4px;
    }

    .step-dot.inactive {
        background-color: rgba(255, 255, 255, 0.2);
    }

    .wizard-skip {
        position: absolute;
        top: 20px;
        right: 20px;
        background: transparent;
        border: none;
        color: var(--wizard-text-muted);
        font-size: 14px;
        cursor: pointer;
        padding: 8px 16px;
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .wizard-skip:hover {
        background: rgba(255, 255, 255, 0.05);
        color: var(--wizard-text);
    }

    .hidden {
        display: none !important;
    }

    .icon {
        width: 16px;
        height: 16px;
        stroke: currentColor;
        fill: none;
        stroke-width: 2;
    }

    .icon-lg {
        width: 32px;
        height: 32px;
    }
</style>

<!-- Profile Wizard Script -->
<script>
    const wizardData = {
        currentStep: 1,
        totalSteps: 4,
        stepTitles: ['Basic Info', 'Profile Setup', 'Contact Info', 'About You'],
        formData: {
            fullName: '<?= htmlspecialchars($user_fullname) ?>',
            email: '<?= htmlspecialchars($user_email) ?>',
            profileName: '',
            profileHandle: '',
            phone: '',
            dateOfBirth: '',
            location: '',
            websiteUrl: '',
            bio: '',
            profilePhotoUrl: '',
            profilePhotoFile: null
        },
        userId: '<?= htmlspecialchars($user_id) ?>',
        csrfToken: '<?= htmlspecialchars($_SESSION['wizard_csrf_token']) ?>'
    };

    const knownLocations = [
        'new york', 'london', 'tokyo', 'paris', 'berlin', 'sydney', 'toronto', 'los angeles',
        'san francisco', 'chicago', 'boston', 'seattle', 'austin', 'miami', 'barcelona',
        'amsterdam', 'singapore', 'hong kong', 'dublin', 'zurich'
    ];

    function updateWizardProgress() {
        const progress = (wizardData.currentStep / wizardData.totalSteps) * 100;
        document.getElementById('progress-fill').style.width = progress + '%';
        document.getElementById('current-step').textContent = wizardData.currentStep;
        document.getElementById('step-title').textContent = wizardData.stepTitles[wizardData.currentStep - 1];
    }

    function showWizardStep(step) {
        // Hide all steps
        for (let i = 1; i <= wizardData.totalSteps; i++) {
            document.getElementById(`step-${i}`).classList.add('hidden');
        }
        
        // Show current step
        document.getElementById(`step-${step}`).classList.remove('hidden');
        
        // Update step indicators
        document.querySelectorAll('.step-dot').forEach((dot, index) => {
            if (index + 1 <= step) {
                dot.classList.remove('inactive');
                dot.classList.add('active');
            } else {
                dot.classList.remove('active');
                dot.classList.add('inactive');
            }
        });
        
        // Update navigation buttons
        document.getElementById('wizard-prev-btn').disabled = step === 1;
        
        const nextBtn = document.getElementById('wizard-next-btn');
        if (step === wizardData.totalSteps) {
            nextBtn.innerHTML = 'Complete Setup';
            nextBtn.onclick = completeWizardSetup;
        } else {
            nextBtn.innerHTML = 'Next<svg class="icon" viewBox="0 0 24 24"><polyline points="9,18 15,12 9,6"></polyline></svg>';
            nextBtn.onclick = wizardNextStep;
        }
    }

    function wizardNextStep() {
        if (wizardData.currentStep < wizardData.totalSteps) {
            updateWizardFormData();
            wizardData.currentStep++;
            updateWizardProgress();
            showWizardStep(wizardData.currentStep);
        }
    }

    function wizardPreviousStep() {
        if (wizardData.currentStep > 1) {
            updateWizardFormData();
            wizardData.currentStep--;
            updateWizardProgress();
            showWizardStep(wizardData.currentStep);
        }
    }

    function updateWizardFormData() {
        wizardData.formData.fullName = document.getElementById('wizard-fullName').value;
        wizardData.formData.email = document.getElementById('wizard-email').value;
        
        if (document.getElementById('wizard-profileName')) {
            wizardData.formData.profileName = document.getElementById('wizard-profileName').value;
            wizardData.formData.profileHandle = document.getElementById('wizard-profileHandle').value;
        }
        
        if (document.getElementById('wizard-phone')) {
            wizardData.formData.phone = document.getElementById('wizard-phone').value;
            wizardData.formData.dateOfBirth = document.getElementById('wizard-dateOfBirth').value;
            wizardData.formData.location = document.getElementById('wizard-location').value;
            wizardData.formData.websiteUrl = document.getElementById('wizard-websiteUrl').value;
        }
        
        if (document.getElementById('wizard-bio')) {
            wizardData.formData.bio = document.getElementById('wizard-bio').value;
        }
    }

    function completeWizardSetup() {
        updateWizardFormData();
        
        // Send data to server
        const formData = new FormData();
        formData.append('action', 'update_profile');
        formData.append('csrf_token', wizardData.csrfToken);
        formData.append('user_id', wizardData.userId);
        
        // Add all form fields
        for (const [key, value] of Object.entries(wizardData.formData)) {
            if (value !== null && value !== '') {
                formData.append(key, value);
            }
        }
        
        // Add photo file if exists
        if (wizardData.formData.profilePhotoFile) {
            formData.append('profilePhoto', wizardData.formData.profilePhotoFile);
        }
        
        // Show loading state
        const nextBtn = document.getElementById('wizard-next-btn');
        nextBtn.disabled = true;
        nextBtn.innerHTML = '<span class="spinner"></span> Saving...';
        
        // Submit via AJAX
        fetch('/api/profile-update.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Clear session flag
                fetch('/api/clear-wizard-flag.php', { method: 'POST' });
                
                // Close wizard with success animation
                const overlay = document.getElementById('profile-wizard-overlay');
                overlay.classList.remove('active');
                
                // Show success message
                if (typeof showNotification === 'function') {
                    showNotification('Profile updated successfully!', 'success');
                }
                
                // Reload page after animation
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            } else {
                // Show error
                alert('Error: ' + (data.message || 'Failed to update profile'));
                nextBtn.disabled = false;
                nextBtn.innerHTML = 'Complete Setup';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
            nextBtn.disabled = false;
            nextBtn.innerHTML = 'Complete Setup';
        });
    }

    function skipWizard() {
        if (confirm('You can complete your profile later. Skip for now?')) {
            // Clear session flag
            fetch('/api/clear-wizard-flag.php', { method: 'POST' });
            
            // Close wizard
            document.getElementById('profile-wizard-overlay').classList.remove('active');
        }
    }

    function validateWizardHandle() {
        const handle = document.getElementById('wizard-profileHandle').value.toLowerCase();
        const validation = document.getElementById('handle-validation');
        
        if (handle.length === 0) {
            validation.innerHTML = '';
            return;
        }
        
        // Check availability via AJAX
        fetch(`/api/check-handle.php?handle=${encodeURIComponent(handle)}`)
            .then(response => response.json())
            .then(data => {
                if (data.available) {
                    validation.innerHTML = '<div class="validation-icon success"><svg class="icon" viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"></polyline></svg></div>';
                } else {
                    validation.innerHTML = '<div class="validation-icon error"><svg class="icon" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></div>';
                }
            })
            .catch(() => {
                validation.innerHTML = '';
            });
    }

    function validateWizardLocation() {
        const location = document.getElementById('wizard-location').value.toLowerCase();
        const validation = document.getElementById('location-validation');
        
        if (location.length === 0) {
            validation.innerHTML = '';
            return;
        }
        
        const isKnown = knownLocations.some(loc => location.includes(loc));
        
        if (isKnown) {
            validation.innerHTML = '<div class="validation-icon success"><svg class="icon" viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"></polyline></svg></div>';
        } else {
            validation.innerHTML = '<div class="validation-icon warning"><svg class="icon" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg></div>';
        }
    }

    function uploadWizardPhoto() {
        document.getElementById('wizard-photo-upload').click();
    }

    function useWizardPhotoUrl() {
        const url = prompt('Enter photo URL:');
        if (url) {
            wizardData.formData.profilePhotoUrl = url;
            const preview = document.getElementById('photo-preview');
            preview.innerHTML = `<img src="${url}" alt="Profile preview">`;
        }
    }

    function handleWizardFileUpload(event) {
        const file = event.target.files[0];
        if (file) {
            wizardData.formData.profilePhotoFile = file;
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById('photo-preview');
                preview.innerHTML = `<img src="${e.target.result}" alt="Profile preview">`;
            };
            reader.readAsDataURL(file);
        }
    }

    // Add event listeners when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        // Only initialize if wizard is shown
        if (document.getElementById('profile-wizard-overlay')) {
            // Add event listeners
            const handleInput = document.getElementById('wizard-profileHandle');
            if (handleInput) {
                handleInput.addEventListener('input', validateWizardHandle);
            }
            
            const locationInput = document.getElementById('wizard-location');
            if (locationInput) {
                locationInput.addEventListener('input', validateWizardLocation);
            }
            
            // Initialize wizard
            updateWizardProgress();
            showWizardStep(1);
        }
    });
</script>

<?php
// Clear the flag after rendering
unset($_SESSION['show_profile_wizard']);
endif;
?>