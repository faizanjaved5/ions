<?php
// PHP logic to handle the multi-step form
session_start();

$step = $_GET['step'] ?? '1';
$message = '';
$message_type = '';

// --- BEGIN SIMULATED BACKEND LOGIC ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'register':
                $fullname = trim($_POST['fullname'] ?? '');
                $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);

                if (empty($fullname) || !$email) {
                    $message = '❌ Please provide a valid full name and email address.';
                    $message_type = 'error';
                    $step = '1'; // Stay on step 1
                } else {
                    // In a real application, you would perform a database insert here.
                    // e.g., $db->insert('IONEERS', ['fullname' => $fullname, 'email' => $email, 'user_role' => 'Guest']);
                    // For this demo, we'll just simulate success.
                    $_SESSION['user_email'] = $email;
                    $_SESSION['user_fullname'] = $fullname;
                    $_SESSION['user_role'] = 'Guest'; // Set a default role
                    $message = '✅ Registration successful! You can now choose to upgrade.';
                    $message_type = 'success';
                    header("Location: join.php?step=2");
                    exit;
                }
                break;
            case 'upgrade':
                $plan = $_POST['plan'] ?? '';
                $cardNumber = $_POST['card_number'] ?? '';
                $expiry = $_POST['expiry'] ?? '';
                $cvc = $_POST['cvc'] ?? '';

                if (empty($plan) || empty($cardNumber) || empty($expiry) || empty($cvc)) {
                    $message = '❌ Please fill out all payment details.';
                    $message_type = 'error';
                    $step = '3'; // Stay on step 3
                } else {
                    // In a real app, this would be a payment gateway API call.
                    // If payment is approved:
                    $_SESSION['user_role'] = 'Member';
                    // Then redirect to the app dashboard
                    header("Location: /app?status=success&message=Upgrade and login successful!");
                    exit;
                }
                break;
            case 'skip_offer':
                // In a real app, this would redirect the free user to the dashboard.
                header("Location: /app?status=info&message=Skipped upgrade, logging in as a free user.");
                exit;
                break;
        }
    }
}
// --- END SIMULATED BACKEND LOGIC ---

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join ION Console | Start Your 7-Day Free Trial</title>
    <meta name="description" content="Join thousands of successful businesses using ION Console. Start your 7-day free trial with no credit card required.">
    <link rel="stylesheet" href="join-gemi.css" type="text/css">
</head>
<body>
    <main class="main-container">
        <!-- Background effects -->
        <div class="background-effects">
            <div class="bg-blur-1"></div>
            <div class="bg-blur-2"></div>
            <div class="bg-blur-3"></div>
        </div>

        <div class="container">
            <div class="card">
                <!-- Toast Notification Container -->
                <div class="toast-container" id="toastContainer"></div>
                
                <!-- Step 1: Registration -->
                <div id="step1" class="grid" style="display: <?= $step === '1' ? 'grid' : 'none' ?>;">
                    <!-- Left Side - Registration Form -->
                    <div class="left-panel">
                        <div class="form-container">
                            <div class="text-center">
                                <div class="logo-container step1">
                                    <img src="https://ions.com/menu/ion-logo-gold.png" alt="ION Network" class="ion-logo">
                                </div>
                                <div>
                                    <h1 class="header-title">Get started today!</h1>
                                    <p class="header-subtitle">7 day free trial. No card required.</p>
                                </div>
                            </div>
                            
                            <form method="POST" action="join.php?step=1" style="display: flex; flex-direction: column; gap: 1rem;">
                                <input type="hidden" name="action" value="register">
                                <input
                                    type="text"
                                    name="fullname"
                                    placeholder="Full Name"
                                    class="input"
                                    required
                                />
                                <input
                                    type="email"
                                    name="email"
                                    placeholder="Email Address"
                                    class="input"
                                    required
                                />
                                <button type="submit" class="btn btn-primary">
                                    Continue with Email
                                </button>
                            </form>
                            
                            <div class="separator-container">
                                <div class="separator"></div>
                                <span class="separator-text">-or-</span>
                            </div>

                            <button onclick="handleGoogle()" class="btn" style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2);">
                                <svg class="icon-google" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48">
                                    <path fill="#FFC107" d="M43.611,20.083H42V20H24v8h11.303c-1.649,4.657-6.08,8-11.303,8c-6.627,0-12-5.373-12-12c0-6.627,5.373-12,12-12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C12.955,4,4,12.955,4,24c0,11.045,8.955,20,20,20c11.045,0,20-8.955,20-20C44,22.659,43.862,21.35,43.611,20.083z"></path>
                                    <path fill="#FF3D00" d="M6.306,14.691l6.571,4.819C14.655,15.108,18.961,12,24,12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C16.318,4,9.656,8.337,6.306,14.691z"></path>
                                    <path fill="#4CAF50" d="M24,44c5.166,0,9.86-1.977,13.409-5.192l-6.19-5.238C29.211,35.091,26.715,36,24,36c-5.202,0-9.619-3.317-11.283-7.946l-6.522,5.025C9.505,39.556,16.227,44,24,44z"></path>
                                    <path fill="#1976D2" d="M43.611,20.083H42V20H24v8h11.303c-0.792,2.237-2.231,4.166-4.087,5.571c0.001-0.001,0.002-0.001,0.003-0.002l6.19,5.238C36.971,39.205,44,34,44,24C44,22.659,43.862,21.35,43.611,20.083z"></path>
                                </svg>
                                Continue with Google
                            </button>

                            <div class="text-center">
                                <p style="font-size: 0.875rem; color: #d1d5db;">
                                    Already have an account?
                                    <a href="index.php" class="link">Log in here</a>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Right Side - Promotional Content -->
                    <div class="right-panel">
                        <!-- Background Graphics and other promotional content from original mockup -->
                        <div class="form-container">
                            <div class="text-center">
                                <h2 style="font-size: 1.5rem; font-weight: bold; color: white; margin-bottom: 1rem;">
                                    Grow your sales with your own ION Channel
                                </h2>
                                <p style="font-size: 0.875rem; color: #d1d5db; font-style: italic;">
                                    "ION has been a transformative platform for my business, allowing me to 10x my sales since I started using it."
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Pro Upgrade -->
                <div id="step2" class="grid" style="display: <?= $step === '2' ? 'grid' : 'none' ?>;">
                    <!-- Left Side - Pro Features -->
                    <div class="left-panel">
                        <div class="form-container">
                            <div class="text-center">
                                <div class="logo-container step2-3">
                                    <img src="https://ions.com/menu/ion-logo-gold.png" alt="ION Network" class="ion-logo">
                                </div>
                                <div>
                                    <h1 class="header-title">Get powerful tools as a Pro</h1>
                                    <p class="header-subtitle">Upgrade to ION Pro and create all the advanced features to maximize your online presence.</p>
                                </div>
                            </div>
                            
                            <form method="POST" action="join.php?step=3" style="display: flex; flex-direction: column; gap: 1rem;">
                                <input type="hidden" name="action" value="set_plan">
                                <label style="display: flex; align-items: center; justify-content: space-between; padding: 1rem; border: 2px solid rgba(137, 105, 72, 0.4); border-radius: 0.5rem; cursor: pointer; transition: all 0.2s; background: rgba(137, 105, 72, 0.1);">
                                    <span>
                                        <span style="font-weight: bold; color: #896948;">Monthly Plan</span>
                                        <br>
                                        <span style="font-size: 0.875rem; color: #d1d5db;">Billed $7.99/mo</span>
                                    </span>
                                    <input type="radio" name="plan" value="monthly" required>
                                </label>
                                <label style="display: flex; align-items: center; justify-content: space-between; padding: 1rem; border: 2px solid rgba(137, 105, 72, 0.4); border-radius: 0.5rem; cursor: pointer; transition: all 0.2s; background: rgba(137, 105, 72, 0.1);">
                                    <span>
                                        <span style="font-weight: bold; color: #896948;">Annual Plan</span>
                                        <br>
                                        <span style="font-size: 0.875rem; color: #d1d5db;">Billed $60/yr ($5/mo)</span>
                                    </span>
                                    <input type="radio" name="plan" value="annual" required>
                                </label>
                                <button type="submit" class="btn btn-primary">
                                    Continue to Checkout
                                </button>
                            </form>
                            
                            <form method="POST" action="join.php" style="text-align: center; margin-top: 1rem;">
                                <input type="hidden" name="action" value="skip_offer">
                                <button type="submit" class="btn btn-ghost btn-sm">
                                    Skip this offer →
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Right Side - Pricing & Design Preview -->
                    <div class="right-panel right-panel-step2">
                        <!-- Background Graphics from original mockup -->
                        <div class="form-container">
                            <div class="text-center">
                                <h3 style="font-size: 1.25rem; font-weight: bold; color: white;">Exclusive Offer</h3>
                                <div style="font-size: 1.875rem; font-weight: bold; color: #896948; margin-bottom: 1rem;">
                                    Starting at $5/mo
                                </div>
                                <p style="font-size: 0.875rem; color: #d1d5db;">Upgrade now and lock in your discounted rate.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Checkout Form -->
                <div id="step3" class="grid" style="display: <?= $step === '3' ? 'grid' : 'none' ?>;">
                    <div class="left-panel">
                        <div class="form-container">
                            <div class="text-center">
                                <div class="logo-container step2-3">
                                    <img src="https://ions.com/menu/ion-logo-gold.png" alt="ION Network" class="ion-logo">
                                </div>
                                <div>
                                    <h1 class="header-title">Checkout</h1>
                                    <p class="header-subtitle">Enter your payment details for your new plan.</p>
                                </div>
                            </div>

                            <form method="POST" action="join.php">
                                <input type="hidden" name="action" value="upgrade">
                                <div style="display: flex; flex-direction: column; gap: 1rem;">
                                    <input type="text" name="card_number" placeholder="Card Number" class="input" required>
                                    <div style="display: flex; gap: 1rem;">
                                        <input type="text" name="expiry" placeholder="MM/YY" class="input" style="flex-grow: 1;" required>
                                        <input type="text" name="cvc" placeholder="CVC" class="input" style="width: 5rem;" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        Confirm & Checkout
                                    </button>
                                </div>
                            </form>
                            <div class="navigation-buttons" style="position: static; margin-top: 1rem;">
                                <a href="join.php?step=2" class="btn btn-ghost btn-sm btn-back" style="margin-left: auto;">
                                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="15,18 9,12 15,6"/>
                                    </svg>
                                    Back
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="right-panel right-panel-step2">
                        <div class="form-container">
                            <div class="text-center">
                                <div class="pricing-card" style="background: rgba(137, 105, 72, 0.1); border: 1px solid rgba(137, 105, 72, 0.4);">
                                    <h3 class="pricing-title">Your Plan Summary</h3>
                                    <div class="pricing-amount">
                                        <!-- This will be dynamically updated based on the plan selected -->
                                        $7.99/mo
                                    </div>
                                    <p class="pricing-description">Billed monthly. Cancel anytime.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        const message = '<?= $message ?>';
        const message_type = '<?= $message_type ?>';

        document.addEventListener('DOMContentLoaded', () => {
            if (message) {
                showToast(message_type === 'success' ? 'Success!' : 'Error', message, message_type);
            }
        });

        function showToast(title, description, type) {
            const toastContainer = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = 'toast';
            toast.innerHTML = `
                <div class="toast-title">${title}</div>
                <div class="toast-description">${description}</div>
            `;
            if (type === 'success') {
                toast.style.borderColor = '#10b981';
            } else if (type === 'error') {
                toast.style.borderColor = '#ef4444';
            }
            toastContainer.appendChild(toast);
            
            setTimeout(() => {
                toast.remove();
            }, 3000);
        }

        // Simulating Google OAuth flow
        function handleGoogle() {
             showToast('Demo Action', 'Redirecting to Google OAuth flow...', 'info');
             // In a real application, you would redirect to the Google OAuth URL here.
             // window.location.href = '<?= $google_oauth_url ?>';
             setTimeout(() => {
                 // After a successful OAuth, the user is redirected to the join page with the next step
                 window.location.href = 'join.php?step=2';
             }, 2000);
        }
    </script>
</body>
</html>