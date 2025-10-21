/**
 * ION Login Modal - Frameless centered dialog
 * - OTP login works entirely in modal
 * - OAuth opens minimal popup (Google blocks OAuth in iframes)
 */

let loginPopup = null;
let currentReturnUrl = null;

function showLoginModal(returnUrl = null) {
    currentReturnUrl = returnUrl || window.location.href;
    sessionStorage.setItem('login_return_url', currentReturnUrl);
    
    // Create modal overlay
    const overlay = document.createElement('div');
    overlay.id = 'ion-login-modal-overlay';
    overlay.className = 'ion-login-modal-overlay active';
    
    overlay.innerHTML = `
        <div class="ion-login-modal">
            <button class="ion-login-close" onclick="closeLoginModal()">&times;</button>
            <div class="ion-login-content">
                <div class="ion-login-logo">
                    <svg width="120" height="50" viewBox="0 0 200 80" fill="none">
                        <rect x="10" y="20" width="180" height="40" rx="20" fill="url(#gradient)" />
                        <text x="100" y="52" font-family="system-ui" font-size="28" font-weight="bold" fill="white" text-anchor="middle">ION</text>
                        <defs>
                            <linearGradient id="gradient" x1="10" y1="20" x2="190" y2="60">
                                <stop offset="0%" stop-color="#f59e0b" />
                                <stop offset="100%" stop-color="#d97706" />
                            </linearGradient>
                        </defs>
                    </svg>
                </div>
                <h2 class="ion-login-title">Welcome back!</h2>
                
                <button class="ion-login-google-btn" onclick="handleGoogleLogin()">
                    <svg width="18" height="18" viewBox="0 0 18 18">
                        <path fill="#4285F4" d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844c-.209 1.125-.843 2.078-1.796 2.717v2.258h2.908c1.702-1.567 2.684-3.874 2.684-6.615z"/>
                        <path fill="#34A853" d="M9 18c2.43 0 4.467-.806 5.956-2.184l-2.908-2.258c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332C2.438 15.983 5.482 18 9 18z"/>
                        <path fill="#FBBC05" d="M3.964 10.707c-.18-.54-.282-1.117-.282-1.707s.102-1.167.282-1.707V4.961H.957C.347 6.175 0 7.55 0 9s.348 2.825.957 4.039l3.007-2.332z"/>
                        <path fill="#EA4335" d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0 5.482 0 2.438 2.017.957 4.961L3.964 7.29C4.672 5.163 6.656 3.58 9 3.58z"/>
                    </svg>
                    <span>Continue with Google</span>
                </button>
                
                <div class="ion-login-divider">
                    <span>or</span>
                </div>
                
                <form id="ion-otp-form" onsubmit="handleOTPRequest(event)">
                    <div class="ion-login-input-group">
                        <input type="email" id="ion-email-input" placeholder="Enter your email" required />
                    </div>
                    <button type="submit" class="ion-login-submit-btn">
                        <span>🔐</span> Continue with Email
                    </button>
                </form>
                
                <div id="ion-otp-section" class="ion-otp-section" style="display: none;">
                    <p class="ion-otp-sent-msg">We sent a code to <strong id="ion-email-display"></strong></p>
                    <form id="ion-verify-form" onsubmit="handleOTPVerify(event)">
                        <div class="ion-otp-inputs">
                            <input type="text" maxlength="1" class="ion-otp-digit" data-index="0" />
                            <input type="text" maxlength="1" class="ion-otp-digit" data-index="1" />
                            <input type="text" maxlength="1" class="ion-otp-digit" data-index="2" />
                            <input type="text" maxlength="1" class="ion-otp-digit" data-index="3" />
                            <input type="text" maxlength="1" class="ion-otp-digit" data-index="4" />
                            <input type="text" maxlength="1" class="ion-otp-digit" data-index="5" />
                        </div>
                        <button type="submit" class="ion-login-submit-btn">Verify Code</button>
                        <button type="button" class="ion-login-back-btn" onclick="backToEmail()">← Back</button>
                    </form>
                </div>
                
                <div id="ion-login-error" class="ion-login-error"></div>
            </div>
        </div>
    `;
    
    document.body.appendChild(overlay);
    document.body.style.overflow = 'hidden';
    
    // Setup OTP digit inputs auto-advance
    setupOTPInputs();
}

function closeLoginModal() {
    const overlay = document.getElementById('ion-login-modal-overlay');
    if (overlay) {
        overlay.remove();
        document.body.style.overflow = '';
    }
}

function handleGoogleLogin() {
    // OAuth must open in popup (Google blocks iframes)
    const width = 500;
    const height = 600;
    const left = (window.screen.width - width) / 2;
    const top = (window.screen.height - height) / 2;
    
    loginPopup = window.open(
        `/login/?return_to=${encodeURIComponent(currentReturnUrl)}`,
        'IONOAuth',
        `width=${width},height=${height},left=${left},top=${top},toolbar=no,menubar=no,location=no`
    );
    
    if (loginPopup) loginPopup.focus();
}

async function handleOTPRequest(e) {
    e.preventDefault();
    const email = document.getElementById('ion-email-input').value;
    const errorDiv = document.getElementById('ion-login-error');
    
    try {
        const response = await fetch('/login/sendotp.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `email=${encodeURIComponent(email)}`
        });
        
        const result = await response.json();
        
        if (result.status === 'success') {
            document.getElementById('ion-email-display').textContent = email;
            document.getElementById('ion-otp-form').style.display = 'none';
            document.getElementById('ion-otp-section').style.display = 'block';
            document.querySelector('.ion-login-google-btn').style.display = 'none';
            document.querySelector('.ion-login-divider').style.display = 'none';
            document.querySelector('.ion-otp-digit').focus();
            errorDiv.textContent = '';
        } else {
            errorDiv.textContent = result.message || 'Failed to send code';
        }
    } catch (error) {
        errorDiv.textContent = 'Network error. Please try again.';
    }
}

async function handleOTPVerify(e) {
    e.preventDefault();
    const digits = document.querySelectorAll('.ion-otp-digit');
    const otp = Array.from(digits).map(d => d.value).join('');
    const email = document.getElementById('ion-email-input').value;
    const errorDiv = document.getElementById('ion-login-error');
    
    if (otp.length !== 6) {
        errorDiv.textContent = 'Please enter the 6-digit code';
        return;
    }
    
    try {
        const response = await fetch('/login/verifyotp.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `email=${encodeURIComponent(email)}&otp=${otp}`
        });
        
        const text = await response.text();
        
        // Success - reload page
        if (text.includes('login-success') || response.redirected) {
            closeLoginModal();
            window.location.reload();
        } else {
            errorDiv.textContent = 'Invalid code. Please try again.';
            digits.forEach(d => d.value = '');
            digits[0].focus();
        }
    } catch (error) {
        errorDiv.textContent = 'Network error. Please try again.';
    }
}

function backToEmail() {
    document.getElementById('ion-otp-section').style.display = 'none';
    document.getElementById('ion-otp-form').style.display = 'block';
    document.querySelector('.ion-login-google-btn').style.display = 'flex';
    document.querySelector('.ion-login-divider').style.display = 'flex';
    document.querySelectorAll('.ion-otp-digit').forEach(d => d.value = '');
}

function setupOTPInputs() {
    const inputs = document.querySelectorAll('.ion-otp-digit');
    inputs.forEach((input, index) => {
        input.addEventListener('input', (e) => {
            if (e.target.value.length === 1 && index < 5) {
                inputs[index + 1].focus();
            }
        });
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && !e.target.value && index > 0) {
                inputs[index - 1].focus();
            }
        });
        input.addEventListener('paste', (e) => {
            e.preventDefault();
            const paste = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6);
            paste.split('').forEach((char, i) => {
                if (inputs[i]) inputs[i].value = char;
            });
            if (paste.length === 6) inputs[5].focus();
        });
    });
}

// Listen for OAuth popup success
window.addEventListener('message', (event) => {
    if (event.origin !== window.location.origin) return;
    if (event.data && event.data.type === 'login-success') {
        if (loginPopup) loginPopup.close();
        closeLoginModal();
        window.location.reload();
    }
});

// Close on Escape
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeLoginModal();
});

window.showLoginModal = showLoginModal;
window.closeLoginModal = closeLoginModal;

