<?php
// PHP logic to handle the multi-step form
session_start();

// Load dependencies first
// In a real application, these paths would need to be configured correctly.
// require_once __DIR__ . '/../config/database.php';
// require_once __DIR__ . '/../config/phpmailer/PHPMailer.php';
// require_once __DIR__ . '/../config/phpmailer/Exception.php';
// require_once __DIR__ . '/../config/phpmailer/SMTP.php';
// $config = require __DIR__ . '/../config/config.php';

// Check if a message is set from a redirect
$message = $_GET['message'] ?? '';
$message_type = $_GET['status'] ?? '';

// Generate correct Google OAuth URL
// In a real application, this would use a config file.
$google_oauth_url = '#'; // Placeholder URL for demo
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ION Admin Console</title>
  <link rel="stylesheet" href="join.css" type="text/css">
</head>
<body>
  <!-- Background effects -->
  <div class="background-effects">
      <div class="bg-blur-1"></div>
      <div class="bg-blur-2"></div>
      <div class="bg-blur-3"></div>
  </div>

  <div class="container">
    <div class="logo">
      <img src="https://ions.com/menu/ion-logo-gold.png" alt="ION Network" style="height: 70px; width: auto; margin: -15px auto 20px;">
    </div>
    <div class="title">ION Console</div>
    <div class="subtitle">The Network of Champions!</div>

    <div id="messageBox"><?= htmlspecialchars($message) ?></div>

    <!-- Step 1: Social login or email Input -->
    <center>
    <a href="<?= htmlspecialchars($google_oauth_url) ?>" class="btn btn-outline" style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); margin-bottom: 10px;">
        <svg class="icon-google" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48">
            <path fill="#FFC107" d="M43.611,20.083H42V20H24v8h11.303c-1.649,4.657-6.08,8-11.303,8c-6.627,0-12-5.373-12-12c0-6.627,5.373-12,12-12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C12.955,4,4,12.955,4,24c0,11.045,8.955,20,20,20c11.045,0,20-8.955,20-20C44,22.659,43.862,21.35,43.611,20.083z"></path>
            <path fill="#FF3D00" d="M6.306,14.691l6.571,4.819C14.655,15.108,18.961,12,24,12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C16.318,4,9.656,8.337,6.306,14.691z"></path>
            <path fill="#4CAF50" d="M24,44c5.166,0,9.86-1.977,13.409-5.192l-6.19-5.238C29.211,35.091,26.715,36,24,36c-5.202,0-9.619-3.317-11.283-7.946l-6.522,5.025C9.505,39.556,16.227,44,24,44z"></path>
            <path fill="#1976D2" d="M43.611,20.083H42V20H24v8h11.303c-0.792,2.237-2.231,4.166-4.087,5.571c0.001-0.001,0.002-0.001,0.003-0.002l6.19,5.238C36.971,39.205,44,34,44,24C44,22.659,43.862,21.35,43.611,20.083z"></path>
        </svg>
        Continue with Google
    </a>
    
    <div class="separator-container">
        <div class="separator"></div>
        <span class="separator-text">-or-</span>
    </div>
    
    <form id="emailForm" method="POST" action="sendotp.php">
      <input type="email" name="email" id="emailInput" placeholder="Enter your email" required class="input">
      <button class="btn btn-primary" type="submit">🔐 Continue with Email</button>
    </form>

    <p style="font-size: 0.875rem; color: #d1d5db; margin-top: 1rem;">
        Don't have an account?
        <a href="join.php" class="link">Sign up here</a>
    </p>

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
    // Simplified JS for demo
    const emailForm  = document.getElementById('emailForm');
    const otpForm    = document.getElementById('otpForm');
    const emailInput = document.getElementById('emailInput');
    const otpEmail   = document.getElementById('otpEmail');
    const otpDigits  = document.querySelectorAll('.otp-digit');
    const messageBox = document.getElementById('messageBox');

    const urlParams = new URLSearchParams(window.location.search);
    const messageParam = urlParams.get('message');
    const statusParam = urlParams.get('status');

    if (messageParam) {
        messageBox.textContent = messageParam;
        if (statusParam === 'success') {
            messageBox.style.color = '#10b981';
        } else if (statusParam === 'error') {
            messageBox.style.color = '#ef4444';
        } else {
            messageBox.style.color = '#fbbf24';
        }
    }
    
    // Check if we should show OTP form immediately
    <?php if (isset($_GET['show_otp']) && isset($_SESSION['pending_email'])): ?>
    document.addEventListener('DOMContentLoaded', function() {
      emailForm.classList.add('hidden');
      otpForm.classList.remove('hidden');
      otpEmail.value = '<?= htmlspecialchars($_SESSION['pending_email']) ?>';
      startCountdown();
      addResendButton();
      otpDigits[0].focus();
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
        messageBox.style.color = data.status === 'success' ? "#10b981" : "#ef4444";
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
        messageBox.style.color = "#ef4444";
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
