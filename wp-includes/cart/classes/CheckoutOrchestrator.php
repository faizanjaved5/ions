<?php
require_once __DIR__ . '/../../config/cart.php';
require_once __DIR__ . '/SperseApiManager.php';
require_once __DIR__ . '/ValidationManager.php';

/**
 * CheckoutOrchestrator - Master controller for the entire checkout flow
 * Coordinates between product selection, validation, payments, and Sperse API
 */
class CheckoutOrchestrator
{
    private $sperseApi;
    private $validationManager;
    private $sessionManager;
    private $errors = [];
    private $warnings = [];
    private $tenantId;
    
    public function __construct($tenantId = null)
    {
        $this->tenantId = $tenantId ?? Config::get('DEFAULT_TENANT_ID');
        $this->sperseApi = new SperseApiManager($this->tenantId);
        $this->validationManager = new ValidationManager($this->tenantId);
        $this->initializeSession();
    }
    
    /**
     * Initialize and load product for checkout
     */
    public function initializeProduct($tenantId, $publicName, $optionId = null)
    {
        try {
            // Fetch product information from Sperse
            $productData = $this->sperseApi->getProductInfo($tenantId, $publicName, $optionId);
            
            if (!$productData) {
                throw new Exception(Config::getErrorMessage('PRODUCT_NOT_FOUND'));
            }
            
            // Get checkout field configuration
            $checkoutFields = $this->sperseApi->getCheckoutFields($tenantId);
            
            // Store in session
            $_SESSION['sperse_checkout'] = [
                'tenantId' => $tenantId,
                'publicName' => $publicName,
                'optionId' => $optionId,
                'productData' => $productData,
                'checkoutFields' => $checkoutFields,
                'selectedOption' => $this->getSelectedPriceOption($productData, $optionId),
                'availablePaymentMethods' => $this->getAvailablePaymentMethods($productData),
                'initialized' => time()
            ];
            
            return [
                'success' => true,
                'productData' => $productData,
                'checkoutFields' => $checkoutFields,
                'selectedOption' => $_SESSION['sperse_checkout']['selectedOption'],
                'availablePaymentMethods' => $_SESSION['sperse_checkout']['availablePaymentMethods']
            ];
            
        } catch (Exception $e) {
            $this->addError('initialization', $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Process coupon application
     */
    public function applyCoupon($couponCode)
    {
        if (!Config::enableCouponSupport()) {
            throw new Exception('Coupon support is not enabled');
        }
        
        if (!isset($_SESSION['sperse_checkout']['tenantId'])) {
            throw new Exception('Product not initialized. Please start from product selection.');
        }
        
        try {
            $tenantId = $_SESSION['sperse_checkout']['tenantId'];
            $productData = $_SESSION['sperse_checkout']['productData'];
            $currencyId = $productData['currencyId'] ?? 'USD';
            
            // Validate and get coupon information
            $couponInfo = $this->sperseApi->getCouponInfo($tenantId, $couponCode, $currencyId);
            
            if ($couponInfo && ($couponInfo['isValid'] ?? false)) {
                // Store coupon information in session
                $_SESSION['sperse_checkout']['coupon'] = [
                    'code' => $couponCode,
                    'info' => $couponInfo,
                    'applied' => time()
                ];
                
                return [
                    'success' => true,
                    'message' => Config::getSuccessMessage('COUPON_APPLIED'),
                    'couponInfo' => $couponInfo,
                    'discount' => $this->calculateDiscount($couponInfo)
                ];
            } else {
                throw new Exception(Config::getErrorMessage('COUPON_INVALID'));
            }
            
        } catch (Exception $e) {
            $this->addError('coupon', $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Remove applied coupon
     */
    public function removeCoupon()
    {
        if (isset($_SESSION['sperse_checkout']['coupon'])) {
            unset($_SESSION['sperse_checkout']['coupon']);
            return ['success' => true, 'message' => 'Coupon removed successfully'];
        }
        
        return ['success' => false, 'error' => 'No coupon to remove'];
    }
    
    /**
     * Process checkout form submission
     */
    public function processCheckout($formData, $paymentGateway)
    {
        try {
            // Validate session and product data
            if (!isset($_SESSION['sperse_checkout']['productData'])) {
                throw new Exception('Product not initialized. Please start from product selection.');
            }
            
            $checkoutData = $_SESSION['sperse_checkout'];
            $checkoutFields = $checkoutData['checkoutFields'];
            
            // Validate payment gateway is available
            if (!in_array($paymentGateway, $checkoutData['availablePaymentMethods'])) {
                throw new Exception(Config::getErrorMessage('PAYMENT_GATEWAY_DISABLED'));
            }
            
            // Comprehensive form validation
            if (!$this->validationManager->validateFormData($formData, $checkoutFields)) {
                $errors = $this->validationManager->getFormattedErrors();
                throw new Exception(Config::getErrorMessage('VALIDATION_FAILED') . ' ' . implode(', ', $errors));
            }
            
            // Build Sperse request
            $requestData = $this->buildSperseRequest($formData, $paymentGateway, $checkoutData);
            
            // Submit to Sperse API
            $result = $this->sperseApi->submitProductRequest($requestData, $checkoutFields);
            
            if ($result['success']) {
                // Store payment information in session
                $_SESSION['sperse_checkout']['payment'] = [
                    'gateway' => $paymentGateway,
                    'invoiceId' => $result['invoiceId'],
                    'invoiceXref' => $result['invoiceXref'],
                    'submitted' => time()
                ];
                
                return [
                    'success' => true,
                    'paymentUrl' => $result['paymentUrl'],
                    'invoiceId' => $result['invoiceId'],
                    'message' => Config::getSuccessMessage('PAYMENT_INITIATED')
                ];
            } else {
                throw new Exception('Payment processing failed');
            }
            
        } catch (Exception $e) {
            $this->addError('checkout', $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Build Sperse API request from form data
     */
    private function buildSperseRequest($formData, $paymentGateway, $checkoutData)
    {
        $productData = $checkoutData['productData'];
        $selectedOption = $checkoutData['selectedOption'];
        $tenantId = $checkoutData['tenantId'];
        
        // Build product array
        $products = [[
            'productId' => $productData['id'],
            'optionId' => $selectedOption['id'],
            'unit' => $selectedOption['unit'],
            'price' => $selectedOption['fee'],
            'quantity' => 1
        ]];
        
        // Build base request
        $requestData = [
            'tenantId' => $tenantId,
            'paymentGateway' => $paymentGateway,
            'embeddedPayment' => false,
            'products' => $products,
            'firstName' => $formData['firstName'],
            'lastName' => $formData['lastName'],
            'email' => $formData['email'],
            'successUrl' => $this->generateSuccessUrl($tenantId),
            'cancelUrl' => $this->generateCancelUrl()
        ];
        
        // Add optional fields
        $optionalFields = ['phone', 'dob', 'companyName', 'comments'];
        foreach ($optionalFields as $field) {
            if (!empty($formData[$field])) {
                $requestData[$field] = $formData[$field];
            }
        }
        
        // Add custom fields
        for ($i = 1; $i <= Config::get('MAX_CUSTOM_FIELDS'); $i++) {
            $fieldName = "customField{$i}";
            if (!empty($formData[$fieldName])) {
                $requestData[$fieldName] = $formData[$fieldName];
            }
        }
        
        // Add addresses if provided
        if (!empty($formData['billingAddress'])) {
            $requestData['billingAddress'] = $this->formatAddress($formData['billingAddress']);
        }
        
        if (!empty($formData['shippingAddress'])) {
            $requestData['shippingAddress'] = $this->formatAddress($formData['shippingAddress']);
        } elseif (!empty($formData['billingAddress']) && Config::get('DEFAULT_SHIPPING_SAME_AS_BILLING')) {
            $requestData['shippingAddress'] = $this->formatAddress($formData['billingAddress']);
        }
        
        // Add coupon if applied
        if (isset($checkoutData['coupon']['code'])) {
            $requestData['couponCode'] = $checkoutData['coupon']['code'];
        }
        
        // Add affiliate code if provided
        if (!empty($formData['affiliateCode']) && Config::enableAffiliateTracking()) {
            $requestData['affiliateCode'] = $formData['affiliateCode'];
        }
        
        return $requestData;
    }
    
    /**
     * Format address for Sperse API
     */
    private function formatAddress($address)
    {
        return [
            'streetAddress' => $address['streetAddress'] ?? '',
            'neighborhood' => $address['neighborhood'] ?? '',
            'city' => $address['city'] ?? '',
            'stateId' => $address['state'] ?? '',
            'stateName' => $address['stateName'] ?? $address['state'] ?? '',
            'zip' => $address['zip'] ?? '',
            'countryId' => $address['countryId'] ?? 'US',
            'countryName' => $address['countryName'] ?? 'United States'
        ];
    }
    
    /**
     * Get selected price option from product data
     */
    private function getSelectedPriceOption($productData, $optionId = null)
    {
        if (!isset($productData['priceOptions']) || empty($productData['priceOptions'])) {
            return null;
        }
        
        // If no optionId specified, return the first option
        if ($optionId === null) {
            return $productData['priceOptions'][0];
        }
        
        // Find the specific option
        foreach ($productData['priceOptions'] as $option) {
            if ($option['id'] == $optionId) {
                return $option;
            }
        }
        
        // If not found, return the first option as fallback
        return $productData['priceOptions'][0];
    }
    
    /**
     * Get available payment methods from product data
     */
    private function getAvailablePaymentMethods($productData)
    {
        $methods = [];
        
        if (!isset($productData['data'])) {
            return $methods;
        }
        
        $data = $productData['data'];
        
        // Check for Stripe
        if (isset($data['stripeConfigured']) && $data['stripeConfigured'] === true) {
            $methods[] = 'Stripe';
        }
        
        // Check for PayPal
        if (isset($data['paypalClientId']) && !empty($data['paypalClientId'])) {
            $methods[] = 'PayPal';
        }
        
        // Check for Spreedly
        if (isset($data['spreedlyConfiguration']) && !empty($data['spreedlyConfiguration']['environmentKey'])) {
            $methods[] = 'Spreedly';
        }
        
        return $methods;
    }
    
    /**
     * Calculate discount from coupon information
     */
    private function calculateDiscount($couponInfo)
    {
        if (!$couponInfo || !($couponInfo['isValid'] ?? false)) {
            return 0;
        }
        
        // This would need to be calculated based on the product price and coupon type
        // For now, return the discount amount or percentage
        return [
            'type' => isset($couponInfo['amountOff']) ? 'amount' : 'percent',
            'value' => $couponInfo['amountOff'] ?? $couponInfo['percentOff'] ?? 0,
            'currency' => $couponInfo['currency'] ?? 'USD'
        ];
    }
    
    /**
     * Generate success URL
     */
    private function generateSuccessUrl($tenantId)
    {
        $baseUrl = $this->getBaseUrl();
        return "{$baseUrl}/cart/success.php?tenant={$tenantId}&invoice={initialInvoiceXref}";
    }
    
    /**
     * Generate cancel URL
     */
    private function generateCancelUrl()
    {
        $baseUrl = $this->getBaseUrl();
        return "{$baseUrl}/cart/checkout.php";
    }
    
    /**
     * Get base URL
     */
    private function getBaseUrl()
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . $host;
    }
    
    /**
     * Initialize session management
     */
    private function initializeSession()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Generate CSRF token if not exists
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = Config::generateCsrfToken();
        }
        
        // Check session timeout
        if (isset($_SESSION['sperse_checkout']['initialized'])) {
            $sessionAge = time() - $_SESSION['sperse_checkout']['initialized'];
            if ($sessionAge > Config::get('SESSION_TIMEOUT')) {
                unset($_SESSION['sperse_checkout']);
                $this->addError('session', Config::getErrorMessage('SESSION_EXPIRED'));
            }
        }
    }
    
    /**
     * Validate CSRF token
     */
    public function validateCsrfToken($token)
    {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Get current checkout state
     */
    public function getCheckoutState()
    {
        return $_SESSION['sperse_checkout'] ?? null;
    }
    
    /**
     * Clear checkout session
     */
    public function clearCheckoutSession()
    {
        if (isset($_SESSION['sperse_checkout'])) {
            unset($_SESSION['sperse_checkout']);
        }
    }
    
    /**
     * Add error message
     */
    private function addError($field, $message)
    {
        $this->errors[$field] = $message;
    }
    
    /**
     * Add warning message
     */
    private function addWarning($field, $message)
    {
        $this->warnings[$field] = $message;
    }
    
    /**
     * Get errors
     */
    public function getErrors()
    {
        return $this->errors;
    }
    
    /**
     * Get warnings
     */
    public function getWarnings()
    {
        return $this->warnings;
    }
    
    /**
     * Check if there are any errors
     */
    public function hasErrors()
    {
        return !empty($this->errors);
    }
    
    /**
     * Get validation manager
     */
    public function getValidationManager()
    {
        return $this->validationManager;
    }
    
    /**
     * Get Sperse API manager
     */
    public function getSperseApiManager()
    {
        return $this->sperseApi;
    }
}
