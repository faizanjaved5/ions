<?php
$config = require __DIR__ . '/../config/config.php';

// Generate Google OAuth URL for registration
$google_oauth_url = './google-oauth.php?redirect=/join.php&state=register';

// Check for messages
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join ION Console | Start Your 7-Day Free Trial</title>
    <meta name="description" content="Join thousands of successful businesses using ION Console. Start your 7-day free trial with no credit card required.">
    <link rel="stylesheet" href="login.css?v=<?php echo filemtime('login.css'); ?>" type="text/css">
    <link rel="stylesheet" href="join.css?v=<?php echo filemtime('join.css'); ?>" type="text/css">
</head>
<body>
    <div class="join-container">
        <div class="join-card">
            <!-- Single container for all steps -->
            <div class="join-content">
                <!-- Step 1: Registration -->
                <div id="step1" class="step-content active">
                    <div class="join-grid">
                        <!-- Left Panel - Registration Form -->
                        <div class="left-panel">
                            <div class="form-container">
                                <!-- Step Indicator -->
                                <div class="step-indicator">
                                    <div class="step-dot active" data-step="1"></div>
                                    <div class="step-dot" data-step="2"></div>
                                    <div class="step-dot" data-step="3"></div>
                                </div>

                                <!-- Logo and Header -->
                                <div class="text-center">
                                    <div class="logo-container">
                                        <img src="https://ions.com/menu/ion-logo-gold.png" alt="ION Network">
                                    </div>
                                    <h1 class="title">Get started today!</h1>
                                    <p class="subtitle">7 day free trial. No card required.</p>
                                </div>

                                <div id="messageBox"><?php echo htmlspecialchars($message); ?></div>

                                <!-- Registration Form -->
                                <form id="registrationForm" class="ajax-form">
                                    <div class="form-group">
                                        <label for="fullname">Full Name</label>
                                        <input type="text" id="fullname" name="fullname" class="input" placeholder="John Doe" required>
                                    </div>

                                    <div class="form-group">
                                        <label for="email">Email Address</label>
                                        <input type="email" id="email" name="email" class="input" placeholder="john@example.com" required>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        🚀 Start Free Trial
                                    </button>
                                </form>

                                <div class="text-center" style="margin-top: 20px;">
                                    <p style="font-size: 14px; color: #9ca3af;">
                                        Already have an account? 
                                        <a href="index.php" class="link">Log in here</a>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Right Panel - Benefits -->
                        <div class="right-panel">
                            <div class="floating-shapes">
                                <div class="shape shape-1"></div>
                                <div class="shape shape-2"></div>
                                <div class="shape shape-3"></div>
                            </div>

                            <div class="form-container">
                                <h2 class="panel-title">Grow your sales with your own ION Channel</h2>

                                <div class="testimonial">
                                    <div class="testimonial-avatar">JD</div>
                                    <p class="testimonial-text">
                                        "ION has been a transformative platform for my business, allowing me to 10x my sales since I started using it."
                                    </p>
                                    <p class="testimonial-author">Jane Doe, CEO of TechCorp</p>
                                    <div class="stars">
                                        <span class="star">★</span>
                                        <span class="star">★</span>
                                        <span class="star">★</span>
                                        <span class="star">★</span>
                                        <span class="star">★</span>
                                    </div>
                                </div>

                                <ul class="features-list">
                                    <li>
                                        <span class="check-icon">✓</span>
                                        <div>
                                            <strong>Unlimited Content Creation</strong><br>
                                            Create and manage unlimited posts, videos, and campaigns
                                        </div>
                                    </li>
                                    <li>
                                        <span class="check-icon">✓</span>
                                        <div>
                                            <strong>Advanced Analytics</strong><br>
                                            Track your performance with detailed insights and reports
                                        </div>
                                    </li>
                                    <li>
                                        <span class="check-icon">✓</span>
                                        <div>
                                            <strong>24/7 Support</strong><br>
                                            Get help whenever you need it from our expert team
                                        </div>
                                    </li>
                                    <li>
                                        <span class="check-icon">✓</span>
                                        <div>
                                            <strong>No Setup Fees</strong><br>
                                            Start immediately with zero upfront costs
                                        </div>
                                    </li>
                                </ul>

                                <div class="security-badges">
                                    <div class="security-badge">
                                        <span>🔒</span> SSL Secured
                                    </div>
                                    <div class="security-badge">
                                        <span>✓</span> GDPR Compliant
                                    </div>
                                    <div class="security-badge">
                                        <span>⭐</span> 5-Star Rated
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Pro Upgrade Offer -->
                <div id="step2" class="step-content">
                    <div class="join-grid">
                        <div class="left-panel">
                            <div class="form-container">
                                <!-- Step Indicator -->
                                <div class="step-indicator">
                                    <div class="step-dot" data-step="1"></div>
                                    <div class="step-dot active" data-step="2"></div>
                                    <div class="step-dot" data-step="3"></div>
                                </div>

                                <div class="text-center">
                                    <h1 class="title">Unlock Pro Features <span class="pro-badge">LIMITED OFFER</span></h1>
                                    <p class="subtitle">Get powerful tools to maximize your success</p>
                                </div>

                                <ul class="features-list">
                                    <li>
                                        <span class="check-icon">✓</span>
                                        <div>
                                            <strong>Premium Content Management Tools</strong><br>
                                            Advanced templates, AI-powered content creation, and bulk scheduling
                                        </div>
                                    </li>
                                    <li>
                                        <span class="check-icon">✓</span>
                                        <div>
                                            <strong>100 Creations Upload Capacity</strong><br>
                                            Upload and manage up to 100 high-quality creations per month
                                        </div>
                                    </li>
                                    <li>
                                        <span class="check-icon">✓</span>
                                        <div>
                                            <strong>Priority Moderation</strong><br>
                                            Get your content approved 10x faster with dedicated review
                                        </div>
                                    </li>
                                    <li>
                                        <span class="check-icon">✓</span>
                                        <div>
                                            <strong>Advanced Analytics & Insights</strong><br>
                                            Deep dive into your performance with pro-level metrics
                                        </div>
                                    </li>
                                    <li>
                                        <span class="check-icon">✓</span>
                                        <div>
                                            <strong>White-Label Options</strong><br>
                                            Customize your channel with your own branding
                                        </div>
                                    </li>
                                </ul>

                                <div class="navigation-buttons">
                                    <button type="button" class="btn btn-secondary btn-small" onclick="skipUpgrade()">
                                        Skip for now →
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="right-panel">
                            <div class="floating-shapes">
                                <div class="shape shape-1"></div>
                                <div class="shape shape-2"></div>
                                <div class="shape shape-3"></div>
                            </div>

                            <div class="form-container">
                                <h2 class="panel-title text-center">Choose Your Plan</h2>

                                <div class="pricing-toggle">
                                    <div class="pricing-option active" onclick="togglePricing('monthly')">Monthly</div>
                                    <div class="pricing-option" onclick="togglePricing('annual')">Annual</div>
                                </div>

                                <div id="monthlyPricing" class="pricing-card">
                                    <h3 class="pricing-title">Pro Monthly</h3>
                                    <div class="price-amount">$7.99<span class="price-period">/mo</span></div>
                                    <p class="pricing-description">Billed monthly, cancel anytime</p>
                                </div>

                                <div id="annualPricing" class="pricing-card popular hidden">
                                    <span class="popular-label">BEST VALUE</span>
                                    <h3 class="pricing-title">Pro Annual</h3>
                                    <div class="price-amount">$5.00<span class="price-period">/mo</span></div>
                                    <p class="pricing-description">Billed $60 annually</p>
                                    <div class="savings-badge">Save 37% ($36/year)</div>
                                </div>

                                <button type="button" class="btn btn-primary" onclick="showPaymentForm()">
                                    Continue to Payment
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Payment -->
                <div id="step3" class="step-content">
                    <div class="join-grid">
                        <div class="left-panel">
                            <div class="form-container">
                                <!-- Step Indicator -->
                                <div class="step-indicator">
                                    <div class="step-dot" data-step="1"></div>
                                    <div class="step-dot" data-step="2"></div>
                                    <div class="step-dot active" data-step="3"></div>
                                </div>

                                <div class="text-center">
                                    <h1 class="title">Complete Your Order</h1>
                                    <p class="subtitle">Secure payment powered by Stripe</p>
                                </div>

                                <div id="orderSummary" class="order-summary">
                                    <h3>Order Summary</h3>
                                    <div class="order-details">
                                        <span id="planName">Pro Monthly</span>
                                        <span id="planPrice">$7.99/mo</span>
                                    </div>
                                </div>

                                <form id="paymentForm" class="ajax-form">
                                    <input type="hidden" name="plan_type" id="planType" value="monthly">
                                    <input type="hidden" name="user_id" id="userId" value="">

                                    <div class="form-group card-input-group">
                                        <label for="cardNumber">Card Number</label>
                                        <span class="card-icon">💳</span>
                                        <input type="text" id="cardNumber" name="card_number" class="input" placeholder="1234 5678 9012 3456" maxlength="19" required>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="expiry">Expiry Date</label>
                                            <input type="text" id="expiry" name="expiry" class="input" placeholder="MM/YY" maxlength="5" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="cvc">CVC</label>
                                            <input type="text" id="cvc" name="cvc" class="input" placeholder="123" maxlength="4" required>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="cardName">Cardholder Name</label>
                                        <input type="text" id="cardName" name="card_name" class="input" placeholder="John Doe" required>
                                    </div>

                                    <button type="submit" class="btn btn-primary" id="paymentButton">
                                        🔒 Complete Payment
                                    </button>
                                </form>

                                <div class="security-badges">
                                    <div class="security-badge">
                                        <span>🔒</span> 256-bit SSL
                                    </div>
                                    <div class="security-badge">
                                        <span>💳</span> PCI Compliant
                                    </div>
                                    <div class="security-badge">
                                        <span>🛡️</span> Secure Checkout
                                    </div>
                                </div>

                                <div class="navigation-buttons">
                                    <button type="button" class="btn btn-secondary btn-small" onclick="goToStep(2)">
                                        ← Back
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="right-panel">
                            <div class="floating-shapes">
                                <div class="shape shape-1"></div>
                                <div class="shape shape-2"></div>
                                <div class="shape shape-3"></div>
                            </div>

                            <div class="form-container">
                                <h2 class="panel-title text-center">Your Pro Benefits Start Immediately</h2>

                                <div class="benefits-box">
                                    <h3>What happens next:</h3>
                                    <ul>
                                        <li>✅ Instant access to all Pro features</li>
                                        <li>✅ Welcome email with getting started guide</li>
                                        <li>✅ Personal onboarding call within 24 hours</li>
                                        <li>✅ Access to exclusive Pro community</li>
                                    </ul>
                                </div>

                                <div class="guarantee-box">
                                    <p class="guarantee-title">30-Day Money Back Guarantee</p>
                                    <p class="guarantee-text">
                                        If you're not completely satisfied, get a full refund within 30 days. No questions asked.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentStep = 1;
        let selectedPlan = 'monthly';

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Check for OAuth errors
            const urlParams = new URLSearchParams(window.location.search);
            const errorParam = urlParams.get('error');
            if (errorParam) {
                if (errorParam === 'oauth_failed') {
                    showMessage('Google sign-up failed. Please try again.', 'error');
                }
            }
            
            // Check if we should show step 2 directly (from Google OAuth)
            const step = urlParams.get('step');
            const userId = urlParams.get('user_id');
            if (step === '2' && userId) {
                sessionStorage.setItem('pendingUserId', userId);
                goToStep(2);
            }
        });

        // Handle registration form submission
        document.getElementById('registrationForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitButton = e.target.querySelector('button[type="submit"]');
            const originalText = submitButton.innerHTML;
            submitButton.disabled = true;
            submitButton.classList.add('btn-loading');
            submitButton.innerHTML = '<span class="spinner"></span>';
            
            const formData = new FormData(this);
            
            try {
                const response = await fetch('./register.php', {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    // Store user data temporarily
                    sessionStorage.setItem('pendingUserId', data.user_id);
                    sessionStorage.setItem('userEmail', formData.get('email'));
                    document.getElementById('userId').value = data.user_id;
                    
                    // Show success message
                    showMessage('✓ Account created successfully!', 'success');
                    
                    // Move to step 2 after a short delay
                    setTimeout(() => {
                        goToStep(2);
                    }, 1000);
                } else {
                    showMessage(data.message || 'Registration failed. Please try again.', 'error');
                }
            } catch (error) {
                console.error('Registration error:', error);
                showMessage('An error occurred. Please try again. ' + error.message, 'error');
            } finally {
                submitButton.disabled = false;
                submitButton.classList.remove('btn-loading');
                submitButton.innerHTML = originalText;
            }
        });

        // Handle payment form submission
        document.getElementById('paymentForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitButton = document.getElementById('paymentButton');
            const originalText = submitButton.innerHTML;
            submitButton.disabled = true;
            submitButton.classList.add('btn-loading');
            submitButton.innerHTML = '<span class="spinner"></span>';
            
            const formData = new FormData(this);
            formData.append('user_id', sessionStorage.getItem('pendingUserId'));
            
            try {
                const response = await fetch('./process-payment.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    showMessage('✓ Payment successful! Redirecting to dashboard...', 'success');
                    
                    // Clear session storage
                    sessionStorage.clear();
                    
                    // Redirect to dashboard after a short delay
                    setTimeout(() => {
                        window.location.href = '/app/';
                    }, 1500);
                } else {
                    showMessage(data.message || 'Payment failed. Please try again.', 'error');
                }
            } catch (error) {
                showMessage('An error occurred. Please try again.', 'error');
            } finally {
                submitButton.disabled = false;
                submitButton.classList.remove('btn-loading');
                submitButton.innerHTML = originalText;
            }
        });

        // Navigation functions
        function goToStep(step) {
            // Hide all steps
            document.querySelectorAll('.step-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Show selected step
            document.getElementById(`step${step}`).classList.add('active');
            
            // Update step indicators
            document.querySelectorAll('.step-dot').forEach(dot => {
                dot.classList.remove('active');
                if (parseInt(dot.dataset.step) === step) {
                    dot.classList.add('active');
                }
            });
            
            currentStep = step;
        }

        async function skipUpgrade() {
            const userId = sessionStorage.getItem('pendingUserId');
            
            if (!userId) {
                showMessage('Session expired. Please start over.', 'error');
                return;
            }
            
            // Create form data for skip upgrade
            const formData = new FormData();
            formData.append('action', 'skip_upgrade');
            formData.append('user_id', userId);
            
            try {
                const response = await fetch('./process-payment.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    showMessage(data.message, 'success');
                    sessionStorage.clear();
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1000);
                } else {
                    showMessage(data.message || 'Error skipping upgrade.', 'error');
                }
            } catch (error) {
                showMessage('An error occurred. Please try again.', 'error');
            }
        }

        function togglePricing(type) {
            selectedPlan = type;
            const monthlyOption = document.querySelector('.pricing-option:first-child');
            const annualOption = document.querySelector('.pricing-option:last-child');
            const monthlyCard = document.getElementById('monthlyPricing');
            const annualCard = document.getElementById('annualPricing');
            
            if (type === 'monthly') {
                monthlyOption.classList.add('active');
                annualOption.classList.remove('active');
                monthlyCard.classList.remove('hidden');
                annualCard.classList.add('hidden');
            } else {
                monthlyOption.classList.remove('active');
                annualOption.classList.add('active');
                monthlyCard.classList.add('hidden');
                annualCard.classList.remove('hidden');
            }
        }

        function showPaymentForm() {
            // Update plan type in hidden field
            document.getElementById('planType').value = selectedPlan;
            
            // Update order summary
            if (selectedPlan === 'monthly') {
                document.getElementById('planName').textContent = 'Pro Monthly';
                document.getElementById('planPrice').textContent = '$7.99/mo';
            } else {
                document.getElementById('planName').textContent = 'Pro Annual';
                document.getElementById('planPrice').textContent = '$60/year ($5/mo)';
            }
            
            goToStep(3);
        }

        function showMessage(message, type) {
            const messageBox = document.getElementById('messageBox');
            messageBox.textContent = message;
            messageBox.className = type === 'error' ? 'error' : 'success';
            messageBox.style.color = type === 'error' ? '#ff4d4d' : '#00cc66';
        }

        // Format card number input
        document.getElementById('cardNumber').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s/g, '');
            let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
            e.target.value = formattedValue;
        });

        // Format expiry date input
        document.getElementById('expiry').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.slice(0, 2) + '/' + value.slice(2, 4);
            }
            e.target.value = value;
        });

        // Only allow numbers in CVC
        document.getElementById('cvc').addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '');
        });
    </script>
</body>
</html>