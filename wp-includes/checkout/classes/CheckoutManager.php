<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/ApiClient.php';
require_once __DIR__ . '/ValidationHelper.php';

/**
 * CheckoutManager - Core checkout functionality
 * Extracted from Next.js checkout components
 */
class CheckoutManager 
{
    private $apiClient;
    private $validator;
    private $errors = [];
    private $sessionData = [];
    
    public function __construct() 
    {
        $this->apiClient = new ApiClient();
        $this->validator = new ValidationHelper();
        $this->initSession();
    }
    
    /**
     * Initialize session
     */
    private function initSession() 
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['checkout'])) {
            $_SESSION['checkout'] = [
                'csrf_token' => $this->generateCsrfToken(),
                'cart_items' => [],
                'customer_info' => [],
                'billing_address' => [],
                'shipping_address' => [],
                'payment_method' => null,
                'coupon_code' => null,
                'tax_amount' => 0,
                'total_amount' => 0
            ];
        }
        
        $this->sessionData = &$_SESSION['checkout'];
    }
    
    /**
     * Generate CSRF token
     */
    private function generateCsrfToken() 
    {
        return bin2hex(random_bytes(Config::CSRF_TOKEN_LENGTH));
    }
    
    /**
     * Validate CSRF token
     */
    public function validateCsrfToken($token) 
    {
        return hash_equals($this->sessionData['csrf_token'], $token);
    }
    
    /**
     * Add product to cart
     * Equivalent to Next.js increaseCartQuantity
     */
    public function addToCart($productData) 
    {
        try {
            // Validate product data
            $validation = $this->validator->validateProduct($productData);
            if (!$validation['valid']) {
                $this->errors = $validation['errors'];
                return false;
            }
            
            $productId = $productData['id'];
            $optionId = $productData['optionId'] ?? null;
            $quantity = (int)($productData['quantity'] ?? 1);
            
            // Check if item already exists in cart
            $cartKey = $productId . '_' . ($optionId ?? 'default');
            
            if (isset($this->sessionData['cart_items'][$cartKey])) {
                $this->sessionData['cart_items'][$cartKey]['quantity'] += $quantity;
            } else {
                $this->sessionData['cart_items'][$cartKey] = [
                    'id' => $productId,
                    'optionId' => $optionId,
                    'name' => $productData['name'],
                    'price' => (float)$productData['price'],
                    'quantity' => $quantity,
                    'type' => $productData['type'],
                    'unit' => $productData['unit'] ?? 'piece',
                    'currencyId' => $productData['currencyId'] ?? Config::DEFAULT_CURRENCY
                ];
            }
            
            $this->calculateTotals();
            return true;
            
        } catch (Exception $e) {
            $this->errors[] = $e->getMessage();
            return false;
        }
    }
    
    /**
     * Remove item from cart
     */
    public function removeFromCart($productId, $optionId = null) 
    {
        $cartKey = $productId . '_' . ($optionId ?? 'default');
        
        if (isset($this->sessionData['cart_items'][$cartKey])) {
            unset($this->sessionData['cart_items'][$cartKey]);
            $this->calculateTotals();
            return true;
        }
        
        return false;
    }
    
    /**
     * Update customer information
     * Equivalent to Next.js form data processing
     */
    public function updateCustomerInfo($customerData) 
    {
        try {
            // Validate customer data
            $validation = $this->validator->validateCustomerInfo($customerData);
            if (!$validation['valid']) {
                $this->errors = $validation['errors'];
                return false;
            }
            
            $this->sessionData['customer_info'] = [
                'firstName' => $customerData['firstName'],
                'lastName' => $customerData['lastName'],
                'email' => $customerData['email'],
                'phone' => $customerData['phone'] ?? '',
                'dob' => $customerData['dob'] ?? '',
                'companyName' => $customerData['companyName'] ?? '',
                'customField1' => $customerData['customField1'] ?? '',
                'customField2' => $customerData['customField2'] ?? '',
                'customField3' => $customerData['customField3'] ?? '',
                'customField4' => $customerData['customField4'] ?? '',
                'customField5' => $customerData['customField5'] ?? ''
            ];
            
            return true;
            
        } catch (Exception $e) {
            $this->errors[] = $e->getMessage();
            return false;
        }
    }
    
    /**
     * Update billing address
     */
    public function updateBillingAddress($addressData) 
    {
        try {
            $validation = $this->validator->validateAddress($addressData);
            if (!$validation['valid']) {
                $this->errors = $validation['errors'];
                return false;
            }
            
            $this->sessionData['billing_address'] = [
                'streetAddress' => $addressData['street'],
                'city' => $addressData['city'],
                'stateName' => $addressData['state'],
                'zip' => $addressData['zip'],
                'countryId' => $addressData['country']
            ];
            
            return true;
            
        } catch (Exception $e) {
            $this->errors[] = $e->getMessage();
            return false;
        }
    }
    
    /**
     * Update shipping address
     */
    public function updateShippingAddress($addressData, $sameAsBilling = false) 
    {
        if ($sameAsBilling) {
            $this->sessionData['shipping_address'] = $this->sessionData['billing_address'];
            return true;
        }
        
        try {
            $validation = $this->validator->validateAddress($addressData);
            if (!$validation['valid']) {
                $this->errors = $validation['errors'];
                return false;
            }
            
            $this->sessionData['shipping_address'] = [
                'streetAddress' => $addressData['street'],
                'city' => $addressData['city'],
                'stateName' => $addressData['state'],
                'zip' => $addressData['zip'],
                'countryId' => $addressData['country']
            ];
            
            return true;
            
        } catch (Exception $e) {
            $this->errors[] = $e->getMessage();
            return false;
        }
    }
    
    /**
     * Apply coupon code
     */
    public function applyCoupon($couponCode, $tenantId) 
    {
        try {
            $couponInfo = $this->apiClient->getCouponInfo($couponCode, $tenantId);
            
            if ($couponInfo && $couponInfo['valid']) {
                $this->sessionData['coupon_code'] = $couponCode;
                $this->sessionData['coupon_discount'] = $couponInfo['discount'];
                $this->calculateTotals();
                return true;
            } else {
                $this->errors[] = Config::getErrorMessage('COUPON_INVALID');
                return false;
            }
            
        } catch (Exception $e) {
            $this->errors[] = $e->getMessage();
            return false;
        }
    }
    
    /**
     * Calculate totals including tax and discounts
     */
    private function calculateTotals() 
    {
        $subtotal = 0;
        
        foreach ($this->sessionData['cart_items'] as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }
        
        // Apply coupon discount
        $discount = 0;
        if (isset($this->sessionData['coupon_discount'])) {
            $discount = $this->sessionData['coupon_discount'];
        }
        
        $discountedSubtotal = $subtotal - $discount;
        
        // Calculate tax if enabled
        $tax = 0;
        if (Config::TAX_CALCULATION_ENABLED) {
            // Tax calculation would be implemented here
            // For MVP, we'll use a simple 8% tax rate
            $tax = $discountedSubtotal * 0.08;
        }
        
        $this->sessionData['subtotal'] = $subtotal;
        $this->sessionData['discount'] = $discount;
        $this->sessionData['tax_amount'] = $tax;
        $this->sessionData['total_amount'] = $discountedSubtotal + $tax;
    }
    
    /**
     * Submit payment request to Sperse API
     * Equivalent to Next.js getSubmitRequest function
     */
    public function submitPaymentRequest($paymentGateway, $tenantId) 
    {
        try {
            // Validate checkout data
            if (!$this->validateCheckoutData()) {
                return false;
            }
            
            // Check if mock payment mode is enabled
            if (Config::get('MOCK_PAYMENT_MODE')) {
                return $this->mockPaymentRequest($paymentGateway, $tenantId);
            }
            
                    // Check if using direct Stripe integration
        if (Config::get('USE_DIRECT_STRIPE') && $paymentGateway === 'Stripe') {
            return $this->directStripePayment($tenantId);
        }
        
        // Check if using direct PayPal integration
        if (Config::get('USE_DIRECT_PAYPAL') && $paymentGateway === 'PayPal') {
            return $this->directPayPalPayment($tenantId);
        }
            
            // Prepare product data
            $products = [];
            foreach ($this->sessionData['cart_items'] as $item) {
                $products[] = [
                    'productId' => (int)$item['id'],
                    'optionId' => $item['optionId'] ? (int)$item['optionId'] : null,
                    'unit' => $item['unit'],
                    'price' => (float)$item['price'],
                    'quantity' => (int)$item['quantity']
                ];
            }
            
            // Prepare request data
            $requestData = [
                'products' => $products,
                'tenantId' => (int)$tenantId,
                'paymentGateway' => $paymentGateway,
                'embeddedPayment' => false,
                'firstName' => $this->sessionData['customer_info']['firstName'],
                'lastName' => $this->sessionData['customer_info']['lastName'],
                'email' => $this->sessionData['customer_info']['email'],
                'phone' => $this->sessionData['customer_info']['phone'],
                'successUrl' => $this->getSuccessUrl($tenantId),
                'cancelUrl' => $this->getCancelUrl()
            ];
            
            // Add optional fields
            if (!empty($this->sessionData['coupon_code'])) {
                $requestData['couponCode'] = $this->sessionData['coupon_code'];
            }
            
            if (!empty($this->sessionData['billing_address'])) {
                $requestData['billingAddress'] = $this->sessionData['billing_address'];
            }
            
            if (!empty($this->sessionData['shipping_address'])) {
                $requestData['shippingAddress'] = $this->sessionData['shipping_address'];
            }
            
            // Debug output before API call
            if (Config::isDebugMode()) {
                echo "<div style='background: #e2e3e5; border: 1px solid #d6d8db; padding: 10px; margin: 10px 0; border-radius: 4px;'>";
                echo "<strong>🔍 About to make API call to Sperse...</strong><br>";
                echo "Endpoint: " . Config::getApiUrl('/SubmitProductRequest') . "<br>";
                echo "Tenant ID: " . $requestData['tenantId'] . "<br>";
                echo "Payment Gateway: " . $paymentGateway . "<br>";
                echo "Mock Mode: " . (Config::get('MOCK_PAYMENT_MODE') ? 'ON' : 'OFF') . "<br>";
                echo "</div>";
            }
            
            // Submit to API
            $response = $this->apiClient->submitProductRequest($requestData);
            
            if ($response && isset($response['paymentData'])) {
                return [
                    'success' => true,
                    'paymentUrl' => $response['paymentData'],
                    'invoiceId' => $response['initialInvoicePublicId'] ?? null
                ];
            } else {
                $this->errors[] = 'API call succeeded but no payment data returned. Check Sperse configuration.';
                return false;
            }
            
        } catch (Exception $e) {
            // Pass through the specific API error message
            $this->errors[] = $e->getMessage();
            return false;
        }
    }
    
    /**
     * Direct Stripe payment integration (bypass Sperse)
     */
    private function directStripePayment($tenantId) 
    {
        try {
            require_once __DIR__ . '/DirectStripeIntegration.php';
            $stripe = new DirectStripeIntegration();
            
            // Prepare cart items for Stripe
            $cartItems = [];
            foreach ($this->sessionData['cart_items'] as $item) {
                $cartItems[] = [
                    'name' => $item['name'],
                    'price' => (float)$item['price'],
                    'quantity' => (int)$item['quantity'],
                    'currencyId' => 'USD'
                ];
            }
            
            // Prepare customer data
            $customerData = [
                'email' => $this->sessionData['customer_info']['email'],
                'firstName' => $this->sessionData['customer_info']['firstName'],
                'lastName' => $this->sessionData['customer_info']['lastName']
            ];
            
            // Create Stripe checkout session
            $successUrl = $this->getSuccessUrl($tenantId) . '&direct_stripe=true&session_id={CHECKOUT_SESSION_ID}';
            $cancelUrl = $this->getCancelUrl();
            
            // Suppress debug output to allow proper redirect
            $session = $stripe->createCheckoutSession($cartItems, $customerData, $successUrl, $cancelUrl, true);
            
            if ($session && isset($session['url'])) {
                // Debug output after successful session creation
                if (Config::isDebugMode()) {
                    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; margin: 10px 0; border-radius: 4px;'>";
                    echo "<strong>✅ Stripe Checkout Session Created Successfully!</strong><br>";
                    echo "Session ID: " . htmlspecialchars($session['id']) . "<br>";
                    echo "Redirecting to: " . htmlspecialchars($session['url']) . "<br>";
                    echo "<small>You should be redirected automatically in 3 seconds...</small>";
                    echo "</div>";
                    echo "<script>setTimeout(function(){ window.location.href = '" . addslashes($session['url']) . "'; }, 3000);</script>";
                }
                
                return [
                    'success' => true,
                    'paymentUrl' => $session['url'],
                    'invoiceId' => $session['id'] ?? 'STRIPE_' . uniqid()
                ];
            } else {
                $this->errors[] = 'Failed to create Stripe checkout session';
                return false;
            }
            
        } catch (Exception $e) {
            $this->errors[] = 'Stripe integration error: ' . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Direct PayPal payment integration (bypass Sperse)
     */
    private function directPayPalPayment($tenantId) 
    {
        try {
            require_once __DIR__ . '/DirectPayPalIntegration.php';
            $paypal = new DirectPayPalIntegration();
            
            // Prepare cart items for PayPal
            $cartItems = [];
            foreach ($this->sessionData['cart_items'] as $item) {
                $cartItems[] = [
                    'name' => $item['name'],
                    'price' => (float)$item['price'],
                    'quantity' => (int)$item['quantity'],
                    'currencyId' => 'USD'
                ];
            }
            
            // Prepare customer data
            $customerData = [
                'email' => $this->sessionData['customer_info']['email'],
                'firstName' => $this->sessionData['customer_info']['firstName'],
                'lastName' => $this->sessionData['customer_info']['lastName']
            ];
            
            // Create PayPal order
            $successUrl = $this->getSuccessUrl($tenantId) . '&direct_paypal=true&order_id={order_id}';
            $cancelUrl = $this->getCancelUrl();
            
            // Suppress debug output to allow proper redirect
            $order = $paypal->createOrder($cartItems, $customerData, $successUrl, $cancelUrl, true);
            
            if ($order && isset($order['id'])) {
                // Get approval URL
                $approvalUrl = $paypal->getApprovalUrl($order);
                
                if ($approvalUrl) {
                    // Debug output after successful order creation
                    if (Config::isDebugMode()) {
                        echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; margin: 10px 0; border-radius: 4px;'>";
                        echo "<strong>✅ PayPal Order Created Successfully!</strong><br>";
                        echo "Order ID: " . htmlspecialchars($order['id']) . "<br>";
                        echo "Redirecting to: " . htmlspecialchars($approvalUrl) . "<br>";
                        echo "<small>You should be redirected automatically in 3 seconds...</small>";
                        echo "</div>";
                        echo "<script>setTimeout(function(){ window.location.href = '" . addslashes($approvalUrl) . "'; }, 3000);</script>";
                    }
                    
                    return [
                        'success' => true,
                        'paymentUrl' => $approvalUrl,
                        'invoiceId' => $order['id']
                    ];
                } else {
                    $this->errors[] = 'Failed to get PayPal approval URL';
                    return false;
                }
            } else {
                $this->errors[] = 'Failed to create PayPal order';
                return false;
            }
            
        } catch (Exception $e) {
            $this->errors[] = 'PayPal integration error: ' . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Mock payment request for testing without API credentials
     */
    private function mockPaymentRequest($paymentGateway, $tenantId) 
    {
        // Simulate processing delay
        usleep(500000); // 0.5 second delay
        
        // Generate mock invoice ID
        $invoiceId = 'MOCK_' . strtoupper(uniqid());
        
        // Create mock success URL with invoice ID
        $successUrl = $this->getSuccessUrl($tenantId);
        $successUrl = str_replace('{initialInvoiceXref}', $invoiceId, $successUrl);
        
        return [
            'success' => true,
            'paymentUrl' => $successUrl . '&mock=true&gateway=' . $paymentGateway,
            'invoiceId' => $invoiceId
        ];
    }
    
    /**
     * Validate all checkout data before submission
     */
    private function validateCheckoutData() 
    {
        $valid = true;
        $this->errors = [];
        
        // Check cart has items
        if (empty($this->sessionData['cart_items'])) {
            $this->errors[] = 'Cart is empty';
            $valid = false;
        }
        
        // Check customer info
        if (empty($this->sessionData['customer_info']['firstName']) || 
            empty($this->sessionData['customer_info']['lastName']) ||
            empty($this->sessionData['customer_info']['email'])) {
            $this->errors[] = 'Customer information is incomplete';
            $valid = false;
        }
        
        return $valid;
    }
    
    /**
     * Get success URL
     */
    private function getSuccessUrl($tenantId) 
    {
        $baseUrl = $this->getBaseUrl();
        // Get current directory path to ensure correct routing
        $currentPath = dirname($_SERVER['PHP_SELF']);
        return $baseUrl . $currentPath . "/success.php?tenant={$tenantId}&invoice={initialInvoiceXref}";
    }
    
    /**
     * Get cancel URL
     */
    private function getCancelUrl() 
    {
        $baseUrl = $this->getBaseUrl();
        $currentPath = dirname($_SERVER['PHP_SELF']);
        return $baseUrl . $currentPath . "/checkout.php";
    }
    
    /**
     * Get base URL
     */
    private function getBaseUrl() 
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        return $protocol . '://' . $host;
    }
    
    /**
     * Get current errors
     */
    public function getErrors() 
    {
        return $this->errors;
    }
    
    /**
     * Get session data
     */
    public function getSessionData() 
    {
        return $this->sessionData;
    }
    
    /**
     * Clear checkout session
     */
    public function clearSession() 
    {
        unset($_SESSION['checkout']);
        $this->initSession();
    }
}

?>