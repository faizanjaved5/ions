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
    // Ensure shared login styles are present for visual parity with /login
    try {
        const needShared = !document.querySelector('link[href*="shared-login.css"]');
        if (needShared) {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = '/login/shared-login.css?v=' + Date.now();
            document.head.appendChild(link);
        }
    } catch (e) {
        // ignore failures; modal will still function
    }
    
    // Create modal overlay
    const overlay = document.createElement('div');
    overlay.id = 'ion-login-modal-overlay';
    overlay.className = 'ion-login-modal-overlay active';
    
    overlay.innerHTML = `
        <div class="ion-login-modal">
            <button class="ion-login-close" onclick="closeLoginModal()">&times;</button>
            <div class="ion-login-content">
                <div class="ion-login-logo">
                    <img src="https://ions.com/menu/ion-logo-gold.png" alt="ION Logo" style="width:auto;filter:drop-shadow(0 4px 8px rgba(0,0,0,0.3));" />
                </div>
                <div class="ion-login-heading">ION Console</div>
                <div class="ion-login-subtitle">Welcome back!</div>
                
                <button class="ion-login-google-btn" onclick="handleGoogleLogin()">
                    <svg width="20" height="20" viewBox="0 0 48 48" class="flex-shrink-0">
                        <path fill="#FFC107" d="M43.611,20.083H42V20H24v8h11.303c-1.649,4.657-6.08,8-11.303,8c-6.627,0-12-5.373-12-12c0-6.627,5.373-12,12-12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C12.955,4,4,12.955,4,24c0,11.045,8.955,20,20,20c11.045,0,20-8.955,20-20C44,22.659,43.862,21.35,43.611,20.083z"/>
                        <path fill="#FF3D00" d="M6.306,14.691l6.571,4.819C14.655,15.108,18.961,12,24,12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C16.318,4,9.656,8.337,6.306,14.691z"/>
                        <path fill="#4CAF50" d="M24,44c5.166,0,9.86-1.977,13.409-5.192l-6.19-5.238C29.211,35.091,26.715,36,24,36c-5.202,0-9.619-3.317-11.283-7.946l-6.522,5.025C9.505,39.556,16.227,44,24,44z"/>
                        <path fill="#1976D2" d="M43.611,20.083H42V20H24v8h11.303c-0.792,2.237-2.231,4.166-4.087,5.571c0.001-0.001,0.002-0.001,0.003-0.002l6.19,5.238C36.971,39.205,44,34,44,24C44,22.659,43.862,21.35,43.611,20.083z"/>
                    </svg>
                    <span>Continue with Google</span>
                </button>
                
                <div class="ion-login-divider"><span>or</span></div>
                
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
                <div class="ion-login-join">Don't have an account? <a href="/join/">Create one</a></div>
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
    // Open Google OAuth flow in a centered popup via /login?oauth=1
    const width = 500;
    const height = 600;
    const left = (window.screen.width - width) / 2;
    const top = (window.screen.height - height) / 2;

    const url = `/login/?oauth=1&return_to=${encodeURIComponent(currentReturnUrl)}`;
    loginPopup = window.open(
        url,
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

