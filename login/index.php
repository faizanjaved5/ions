<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Try to load config with error handling
$config_path = __DIR__ . '/../config/config.php';
if (!file_exists($config_path)) {
    die('Configuration file not found. Please ensure config.php exists in /config/ directory.');
}

$config = require $config_path;

// Set default values if not in config
$google_client_id = $config['google_client_id'] ?? '';
$google_redirect_uri = $config['google_redirect_uri'] ?? '';

// Only generate OAuth URL if we have the required config
$google_oauth_url = '';
if ($google_client_id && $google_redirect_uri) {
    $google_oauth_url = 'https://accounts.google.com/o/oauth2/auth?' . http_build_query([
        'client_id' => $google_client_id,
        'redirect_uri' => $google_redirect_uri,
        'scope' => 'email profile',
        'response_type' => 'code',
        'access_type' => 'online',
        'prompt' => 'select_account'
    ]);
}

// Handle return URL for post-login redirect
if (isset($_GET['return_to']) && !empty($_GET['return_to'])) {
    $_SESSION['redirect_after_login'] = $_GET['return_to'];
}

// Check for any messages
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=5.0, user-scalable=yes">
  <meta name="HandheldFriendly" content="true">
  <meta name="MobileOptimized" content="320">
  <title>ION Admin Console - Login</title>
  <link rel="stylesheet" href="login.css?v=<?php echo filemtime('login.css'); ?>" type="text/css">
  <style>
    /* Critical inline styles for immediate rendering */
    body {
      margin: 0;
      padding: 10px;
      background: #1a1a1a;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .container {
      width: 100%;
      max-width: 400px;
      margin: 0 auto;
    }
    .logo img {
      height: 100px;
      width: auto;
    }
    .join-link {
      text-align: center;
      margin-top: 20px;
      font-size: 14px;
      color: #cccccc;
    }
    .join-link a {
      color: #896948;
      text-decoration: none;
      font-weight: 600;
    }
    .join-link a:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="logo">
      <img src="https://ions.com/menu/ion-logo-gold.png" alt="ION Logo">
    </div>
    <div class="title">ION Console</div>
    <div class="subtitle">Welcome back!</div>

    <div id="messageBox"><?= htmlspecialchars($message) ?></div>

    <!-- Step 1: Social login or email Input -->
    <center>
    <?php if ($google_oauth_url): ?>
    <a href="<?= htmlspecialchars($google_oauth_url) ?>" class="inline-flex items-center justify-center rounded-md font-medium transition-colors duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 no-underline border-2 border-google-border text-google-text bg-transparent hover:bg-google-bg/10 px-6 py-3 text-sm gap-3 flex-row">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 48 48" class="flex-shrink-0">
        <path fill="#FFC107" d="M43.611,20.083H42V20H24v8h11.303c-1.649,4.657-6.08,8-11.303,8c-6.627,0-12-5.373-12-12c0-6.627,5.373-12,12-12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C12.955,4,4,12.955,4,24c0,11.045,8.955,20,20,20c11.045,0,20-8.955,20-20C44,22.659,43.862,21.35,43.611,20.083z"/>
        <path fill="#FF3D00" d="M6.306,14.691l6.571,4.819C14.655,15.108,18.961,12,24,12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C16.318,4,9.656,8.337,6.306,14.691z"/>
        <path fill="#4CAF50" d="M24,44c5.166,0,9.86-1.977,13.409-5.192l-6.19-5.238C29.211,35.091,26.715,36,24,36c-5.202,0-9.619-3.317-11.283-7.946l-6.522,5.025C9.505,39.556,16.227,44,24,44z"/>
        <path fill="#1976D2" d="M43.611,20.083H42V20H24v8h11.303c-0.792,2.237-2.231,4.166-4.087,5.571c0.001-0.001,0.002-0.001,0.003-0.002l6.19,5.238C36.971,39.205,44,34,44,24C44,22.659,43.862,21.35,43.611,20.083z"/>
      </svg>
      Continue with Google
    </a>
    
    <br/>-or-<br/><br/>
    <?php endif; ?>
    
    <form id="emailForm" method="POST" action="sendotp.php">
      <input type="email" name="email" id="emailInput" placeholder="Enter your email" required>
      <button class="btn" type="submit">🔐 Continue with Email</button>
    </form>

    <div class="join-link">
      Don't have an account? <a href="/join/">Create one</a>
    </div>

    </center>

    <!-- Step 2: OTP Input -->
    <form id="otpForm" class="hidden" method="POST" action="verifyotp.php">
      <input type="hidden" name="email" id="otpEmail">
      <div class="otp-boxes">
        <?php for ($i = 0; $i < 6; $i++): ?>
          <input type="text" name="otp[]" maxlength="1" class="otp-digit" inputmode="numeric" pattern="[0-9]*">
        <?php endfor; ?>
      </div>
      <div class="timer">Expires in <span id="countdown">10:00</span></div>
      <button class="btn" type="submit">Verify Code</button>
    </form>
  </div>

  <script>
    const emailForm  = document.getElementById('emailForm');
    const otpForm    = document.getElementById('otpForm');
    const emailInput = document.getElementById('emailInput');
    const otpEmail   = document.getElementById('otpEmail');
    const otpDigits  = document.querySelectorAll('.otp-digit');
    const messageBox = document.getElementById('messageBox');

    // Set initial message color if there's a predefined message
    <?php if (!empty($message_type)): ?>
      <?php if ($message_type === 'timeout'): ?>
        messageBox.style.color = '#d97706'; // Mustard/amber color for session timeouts
      <?php elseif ($message_type === 'error'): ?>
        messageBox.style.color = '#ff4d4d'; // Red for actual errors
      <?php else: ?>
        messageBox.style.color = '#00cc66'; // Green for success
      <?php endif; ?>
    <?php endif; ?>

    // Handle OAuth error messages
    const urlParams = new URLSearchParams(window.location.search);
    const errorParam = urlParams.get('error');
    if (errorParam) {
      console.log('OAuth Error Parameter:', errorParam);
      
      if (errorParam === 'no_code') {
        messageBox.style.color = '#ff4d4d';
        messageBox.textContent = '❌ Google OAuth failed: No authorization code received. Please try again.';
      } else if (errorParam === 'unauthorized') {
        messageBox.style.color = '#ff4d4d';
        messageBox.textContent = '❌ Access Denied: Your email is not authorized. Please contact an administrator.';
      }
    }

    // Check if we should show OTP form immediately
    <?php if (isset($_GET['show_otp']) && isset($_SESSION['pending_email'])): ?>
    document.addEventListener('DOMContentLoaded', function() {
      emailForm.classList.add('hidden');
      otpForm.classList.remove('hidden');
      emailInput.value = '<?= htmlspecialchars($_SESSION['pending_email']) ?>';
      otpEmail.value = '<?= htmlspecialchars($_SESSION['pending_email']) ?>';
      startCountdown();
      addResendButton();
      otpDigits[0].focus();
      
      <?php if (isset($_SESSION['otp_error'])): ?>
      messageBox.style.color = "#ff4d4d";
      messageBox.textContent = "<?= htmlspecialchars($_SESSION['otp_error']) ?>";
      <?php unset($_SESSION['otp_error']); ?>
      <?php endif; ?>
    });
    <?php endif; ?>

    function addResendButton() {
      if (document.getElementById('resend-btn')) return;
      
      const resendBtn = document.createElement('button');
      resendBtn.id = 'resend-btn';
      resendBtn.type = 'button';
      resendBtn.className = 'btn';
      resendBtn.textContent = 'Request New Code';
      resendBtn.style.marginTop = '10px';
      resendBtn.style.backgroundColor = '#6c757d';
      resendBtn.style.fontSize = '14px';
      
      resendBtn.addEventListener('click', function() {
        otpForm.classList.add('hidden');
        emailForm.classList.remove('hidden');
        messageBox.style.color = "#ccc";
        messageBox.textContent = "Enter your email to receive a new code.";
        emailInput.focus();
        resendBtn.remove();
        otpDigits.forEach(digit => digit.value = '');
      });
      
      const verifyBtn = otpForm.querySelector('.btn');
      verifyBtn.parentNode.insertBefore(resendBtn, verifyBtn.nextSibling);
    }

    emailForm.addEventListener('submit', function(e) {
      e.preventDefault();
      const formData = new FormData(emailForm);
      messageBox.style.color = "#ccc";
      messageBox.textContent = "Sending email... please wait.";

      fetch('sendotp.php', {
        method: 'POST',
        body: formData
      })
      .then(res => {
        if (!res.ok) {
          throw new Error('Network response was not ok');
        }
        return res.json();
      })
      .then(data => {
        messageBox.style.color = data.status === 'success' ? "#00cc66" : "#ff4d4d";
        messageBox.textContent = data.message;
        if (data.status === 'success') {
          setTimeout(() => {
            emailForm.classList.add('hidden');
            otpForm.classList.remove('hidden');
            otpEmail.value = emailInput.value;
            startCountdown();
            addResendButton();
            otpDigits[0].focus();
          }, 1000);
        }
      })
      .catch(err => {
        console.error('Fetch error:', err);
        messageBox.style.color = "#ff4d4d";
        messageBox.textContent = "❌ An error occurred. Please try again later.";
      });
    });

    otpDigits.forEach((box, idx) => {
      box.addEventListener('input', (e) => {
        const val = e.target.value.replace(/\D/g, '');

        if (val.length === 6) {
          val.split('').forEach((char, i) => {
            if (otpDigits[i]) otpDigits[i].value = char;
          });
          autoSubmitOTP();
          return;
        }

        if (val.length === 1) {
          box.value = val;
          if (idx < 5) otpDigits[idx + 1].focus();
          autoSubmitOTP();
        }
      });

      box.addEventListener('keydown', (e) => {
        if (e.key === 'Backspace' && !box.value && idx > 0) {
          otpDigits[idx - 1].focus();
        }
      });

      box.addEventListener('paste', (e) => {
        const paste = e.clipboardData.getData('text').replace(/\D/g, '');
        if (paste.length === 6) {
          e.preventDefault();
          paste.split('').forEach((char, i) => {
            if (otpDigits[i]) otpDigits[i].value = char;
          });
          autoSubmitOTP();
        }
      });
    });

    function autoSubmitOTP() {
      const allFilled = [...otpDigits].every(d => d.value.length === 1);
      if (allFilled) {
        const digits = [...otpDigits].map(d => d.value).join('');
        const existingHidden = otpForm.querySelector('input[name="otp"]');
        if (existingHidden) existingHidden.remove();

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'otp';
        input.value = digits;
        otpForm.appendChild(input);
        otpForm.submit();
      }
    }

    function startCountdown() {
      let time = 600; // 10 minutes
      const display = document.getElementById('countdown');
      const interval = setInterval(() => {
        const min = String(Math.floor(time / 60)).padStart(2, '0');
        const sec = String(time % 60).padStart(2, '0');
        display.textContent = `${min}:${sec}`;
        time--;
        if (time < 0) clearInterval(interval);
      }, 1000);
    }

    otpForm.addEventListener('submit', function(e) {
      const digits = [...otpDigits].map(d => d.value).join('');
      const existingHidden = otpForm.querySelector('input[name="otp"]');
      if (existingHidden) existingHidden.remove();

      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'otp';
      input.value = digits;
      otpForm.appendChild(input);
    });
  </script>
</body>
</html>