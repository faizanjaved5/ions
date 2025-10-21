/**
 * ION Modal Login Handler
 * Handles popup login for public pages without redirecting
 */

class IONModalLogin {
    constructor() {
        this.modal = null;
        this.overlay = null;
        this.returnUrl = window.location.href;
        this.onSuccessCallback = null;
        this.init();
    }

    init() {
        // Create modal HTML if it doesn't exist
        if (!document.getElementById('ion-login-modal-overlay')) {
            this.createModal();
        }

        this.modal = document.querySelector('.ion-login-modal');
        this.overlay = document.getElementById('ion-login-modal-overlay');
        
        this.attachEventListeners();
    }

    createModal() {
        const modalHTML = `
            <div id="ion-login-modal-overlay" class="ion-login-modal-overlay">
                <div class="ion-login-modal">
                    <div class="ion-login-modal-header">
                        <h2>
                            <span class="icon">🔐</span>
                            Login to Continue
                        </h2>
                        <button type="button" class="ion-login-modal-close" aria-label="Close">&times;</button>
                    </div>
                    
                    <div class="ion-login-modal-body">
                        <div class="ion-login-modal-message">
                            Please login to interact with videos. You'll return to this page after logging in.
                        </div>
                        
                        <div id="ion-login-alert" class="ion-login-alert"></div>
                        
                        <form id="ion-modal-login-form" method="post">
                            <div class="ion-login-form-group">
                                <label for="ion-modal-email">Email Address</label>
                                <input 
                                    type="email" 
                                    id="ion-modal-email" 
                                    name="email" 
                                    placeholder="your@email.com"
                                    required
                                    autocomplete="email"
                                >
                            </div>
                            
                            <div class="ion-login-form-group">
                                <label for="ion-modal-password">Password</label>
                                <input 
                                    type="password" 
                                    id="ion-modal-password" 
                                    name="password" 
                                    placeholder="Enter your password"
                                    required
                                    autocomplete="current-password"
                                >
                            </div>
                            
                            <div class="ion-login-remember">
                                <input type="checkbox" id="ion-modal-remember" name="remember" value="1">
                                <label for="ion-modal-remember">Remember me</label>
                            </div>
                            
                            <button type="submit" class="ion-login-submit">
                                <span class="spinner"></span>
                                <span class="text">Login</span>
                            </button>
                        </form>
                    </div>
                    
                    <div class="ion-login-modal-footer">
                        <p>Don't have an account? <a href="/join/">Sign up</a></p>
                        <p style="margin-top: 10px;"><a href="/login/forgot-password.php">Forgot Password?</a></p>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    attachEventListeners() {
        // Close button
        const closeBtn = this.modal.querySelector('.ion-login-modal-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => this.close());
        }

        // Click outside to close
        this.overlay.addEventListener('click', (e) => {
            if (e.target === this.overlay) {
                this.close();
            }
        });

        // Escape key to close
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.overlay.classList.contains('active')) {
                this.close();
            }
        });

        // Form submission
        const form = document.getElementById('ion-modal-login-form');
        if (form) {
            form.addEventListener('submit', (e) => this.handleSubmit(e));
        }
    }

    show(returnUrl = null, onSuccess = null) {
        if (returnUrl) {
            this.returnUrl = returnUrl;
        }
        
        if (onSuccess && typeof onSuccess === 'function') {
            this.onSuccessCallback = onSuccess;
        }
        
        this.overlay.classList.add('active');
        document.body.classList.add('ion-modal-open');
        
        // Focus email field
        setTimeout(() => {
            const emailField = document.getElementById('ion-modal-email');
            if (emailField) emailField.focus();
        }, 300);
    }

    close() {
        this.overlay.classList.remove('active');
        document.body.classList.remove('ion-modal-open');
        this.clearAlert();
        
        // Reset form
        const form = document.getElementById('ion-modal-login-form');
        if (form) form.reset();
    }

    showAlert(message, type = 'error') {
        const alert = document.getElementById('ion-login-alert');
        if (alert) {
            alert.textContent = message;
            alert.className = `ion-login-alert ${type} show`;
        }
    }

    clearAlert() {
        const alert = document.getElementById('ion-login-alert');
        if (alert) {
            alert.className = 'ion-login-alert';
        }
    }

    async handleSubmit(e) {
        e.preventDefault();
        
        const form = e.target;
        const submitBtn = form.querySelector('.ion-login-submit');
        const email = form.querySelector('[name="email"]').value;
        const password = form.querySelector('[name="password"]').value;
        const remember = form.querySelector('[name="remember"]').checked;
        
        // Clear previous alerts
        this.clearAlert();
        
        // Disable submit button
        submitBtn.disabled = true;
        submitBtn.classList.add('loading');
        
        try {
            const formData = new FormData();
            formData.append('email', email);
            formData.append('password', password);
            formData.append('remember', remember ? '1' : '0');
            formData.append('ajax_login', '1'); // Flag for AJAX login
            
            const response = await fetch('/login/process-login.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showAlert('Login successful! Reloading page...', 'success');
                
                // Call success callback if provided
                if (this.onSuccessCallback) {
                    this.onSuccessCallback(data);
                }
                
                // Wait a moment then reload the page
                setTimeout(() => {
                    window.location.href = this.returnUrl;
                }, 1000);
                
            } else {
                this.showAlert(data.message || 'Login failed. Please check your credentials.', 'error');
                submitBtn.disabled = false;
                submitBtn.classList.remove('loading');
            }
            
        } catch (error) {
            console.error('Login error:', error);
            this.showAlert('An error occurred. Please try again.', 'error');
            submitBtn.disabled = false;
            submitBtn.classList.remove('loading');
        }
    }
}

// Initialize modal login globally
let ionModalLogin;

document.addEventListener('DOMContentLoaded', () => {
    ionModalLogin = new IONModalLogin();
    
    // Make it globally accessible
    window.IONModalLogin = IONModalLogin;
    window.ionModalLogin = ionModalLogin;
});

// Helper function to show login modal
window.showLoginModal = function(returnUrl = null, onSuccess = null) {
    if (window.ionModalLogin) {
        window.ionModalLogin.show(returnUrl, onSuccess);
    } else {
        // Fallback to regular login page
        window.location.href = '/login/?return_to=' + encodeURIComponent(returnUrl || window.location.href);
    }
};

