<?php

/**
 * PHP Checkout MVP Configuration
 * Converted from Next.js .env file
 */

class Config 
{
    // Environment
    const ENV = 'development';
    
    // API Configuration
    const API_URL               = 'https://beta.sperse.com';
    const SPERSE_API_BASE       = 'https://beta.sperse.com/api/services/CRM/PublicProduct';
    
    // Default Domain Configuration
    const DEFAULT_DOMAIN_TENANT = 'imarketer-sperse.vercel.app';
    
    // Default Tenant ID (replace with your actual tenant ID)
    const DEFAULT_TENANT_ID      = 36;
    
    // External Services
    const SENJA_API_URL          = 'https://api.senja.io';
    
    // Payment Gateway URLs
    const STRIPE_API_VERSION     = '2024-06-20';
    const PAYPAL_SDK_URL         = 'https://www.paypal.com/sdk/js';
    
    // Development Settings
    const DEBUG_MODE             = true;
    const DEV_MODE               = true;
    const MOCK_PAYMENT_MODE      = false; // Enable mock payments for testing
    const USE_DIRECT_STRIPE      = true; // Use direct Stripe integration instead of Sperse API
    const USE_DIRECT_PAYPAL      = true; // Use direct PayPal integration instead of Sperse API
    
    // Security Settings
    const SESSION_TIMEOUT        = 3600; // 1 hour
    const CSRF_TOKEN_LENGTH      = 32;
    
    // Checkout Configuration
    const DEFAULT_CURRENCY        = 'USD';
    const DEFAULT_COUNTRY         = 'US';
    const TAX_CALCULATION_ENABLED = true;
    
    // Error Messages
    const ERROR_MESSAGES = [
        'MISSING_REQUIRED_FIELD' => 'This field is required',
        'INVALID_EMAIL'          => 'Please enter a valid email address',
        'INVALID_PHONE'          => 'Please enter a valid phone number',
        'PAYMENT_FAILED'         => 'Payment processing failed. Please try again.',
        'API_ERROR'              => 'Connection error. Please try again.',
        'INVALID_ADDRESS'        => 'Please enter a valid address',
        'COUPON_INVALID'         => 'Coupon code is invalid or expired'
    ];
    
    // Success Messages
    const SUCCESS_MESSAGES = [
        'PAYMENT_SUCCESS'        => 'Payment completed successfully!',
        'ORDER_CREATED'          => 'Your order has been created successfully',
        'EMAIL_SENT'             => 'Confirmation email has been sent'
    ];
    
    // API Endpoints
    const API_ENDPOINTS = [
        'SUBMIT_PRODUCT_REQUEST' => '/SubmitProductRequest',
        'GET_DOMAIN_VARIABLES'   => '/ContactLandingPage/GetDomainVariables',
        'GET_CONTACT_INFO'       => '/ContactLandingPage/GetPublicContactInfo',
        'GET_COUPON_INFO'        => '/GetCouponInfo',
        'GET_TAX_ESTIMATE'       => '/GetTaxEstimate'
    ];
    
    // Payment Gateway Settings
    const PAYMENT_GATEWAYS = [
        'STRIPE' => [
            'name'               => 'Stripe',
            'enabled'            => true,
            'test_mode'          => true
        ],
        'PAYPAL' => [
            'name'               => 'PayPal',
            'enabled'            => true,
            'test_mode'          => true
        ]
    ];
    
    /**
     * Get configuration value
     */
    public static function get($key, $default = null) 
    {
        return defined("self::$key") ? constant("self::$key") : $default;
    }
    
    /**
     * Get API URL with endpoint
     */
    public static function getApiUrl($endpoint = '') 
    {
        return self::API_URL . '/api/services/CRM/PublicProduct' . $endpoint;
    }
    
    /**
     * Get error message
     */
    public static function getErrorMessage($key) 
    {
        return self::ERROR_MESSAGES[$key] ?? 'An error occurred';
    }
    
    /**
     * Get success message
     */
    public static function getSuccessMessage($key) 
    {
        return self::SUCCESS_MESSAGES[$key] ?? 'Operation completed successfully';
    }
    
    /**
     * Check if debug mode is enabled
     */
    public static function isDebugMode() 
    {
        return self::DEBUG_MODE;
    }
    
    /**
     * Check if in development environment
     */
    public static function isDevelopment() 
    {
        return self::ENV === 'development';
    }
}

// Load environment-specific overrides if they exist
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

?>
