<?php
/**
 * Sperse Multi-Tenant Checkout Configuration
 * Enhanced version with granular validation and feature support
 */
class Config 
{
    // Core API Configuration
    const API_URL                  = 'https://beta.sperse.com';
    const SPERSE_API_BASE          = 'https://beta.sperse.com/api/services/CRM/PublicProduct';
    
    // Default Configuration
    const DEFAULT_DOMAIN_TENANT    = 'imarketer-sperse.vercel.app';
    const DEFAULT_TENANT_ID        = 1133;
    
    // Development Settings
    const DEBUG_MODE               = true;
    const DEV_MODE                 = true;
    const MOCK_PAYMENT_MODE        = false;
    
    // Payment Flow Configuration
    const USE_SPERSE_ORCHESTRATION = true;    // Always use Sperse as quarterback
    const USE_DIRECT_STRIPE        = false;   // Only for testing/fallback
    const USE_DIRECT_PAYPAL        = false;   // Only for testing/fallback
    
    // Security Settings
    const SESSION_TIMEOUT          = 3600; // 1 hour
    const CSRF_TOKEN_LENGTH        = 32;
    const ENABLE_RATE_LIMITING     = true;
    const MAX_REQUESTS_PER_MINUTE  = 60;
    
    // Validation Settings
    const ENABLE_CLIENT_VALIDATION = true;
    const ENABLE_SERVER_VALIDATION = true;
    const STRICT_EMAIL_VALIDATION  = true;
    const REQUIRE_PHONE_VALIDATION = false;
    
    // Address Validation Settings
    const ENABLE_ADDRESS_VALIDATION = true;
    const REQUIRE_BILLING_ADDRESS   = true;
    const REQUIRE_SHIPPING_ADDRESS  = false;
    const DEFAULT_SHIPPING_SAME_AS_BILLING = true;
    
    // Coupon & Discount Settings
    const ENABLE_COUPON_SUPPORT     = true;
    const ALLOW_MULTIPLE_COUPONS    = false;
    const COUPON_CASE_SENSITIVE     = false;
    const MAX_COUPON_LENGTH         = 36;
    
    // Affiliate & Tracking Settings
    const ENABLE_AFFILIATE_TRACKING = true;
    const AFFILIATE_CODE_LENGTH     = 50;
    const TRACK_USER_AGENT          = true;
    const TRACK_REFERRER            = true;
    const TRACK_ENTRY_URL           = true;
    
    // Custom Fields Configuration
    const MAX_CUSTOM_FIELDS         = 5;
    const CUSTOM_FIELD_MAX_LENGTH   = 255;
    
    // Payment Gateway URLs
    const STRIPE_API_VERSION        = '2024-06-20';
    const PAYPAL_SDK_URL            = 'https://www.paypal.com/sdk/js';
    
    // Field Validation Rules
    const VALIDATION_RULES          = [
        'firstName'                 => [
            'required'              => true,
            'minLength'             => 1,
            'maxLength'             => 50,
            'pattern'               => '/^[a-zA-Z\s\-\'\.]+$/',
            'message'               => 'First name is required and can only contain letters, spaces, hyphens, apostrophes, and periods'
        ],
        'lastName' => [
            'required' => true,
            'minLength' => 1,
            'maxLength' => 50,
            'pattern' => '/^[a-zA-Z\s\-\'\.]+$/',
            'message' => 'Last name is required and can only contain letters, spaces, hyphens, apostrophes, and periods'
        ],
        'email' => [
            'required' => true,
            'maxLength' => 100,
            'pattern' => '/^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\"[^\"]+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/',
            'message' => 'Please enter a valid email address'
        ],
        'phone' => [
            'required' => false,
            'pattern' => '/^\+?1?\d{10,15}$/',
            'message' => 'Please enter a valid phone number (10-15 digits, optional +1 prefix)'
        ],
        'zip' => [
            'required' => true,
            'pattern' => '/^\d{5}(-\d{4})?$/',
            'message' => 'Please enter a valid ZIP code (e.g., 12345 or 12345-6789)'
        ],
        'companyName' => [
            'required' => false,
            'maxLength' => 100,
            'message' => 'Company name cannot exceed 100 characters'
        ],
        'couponCode' => [
            'required' => false,
            'maxLength' => 36,
            'pattern' => '/^[A-Za-z0-9\-_]+$/',
            'message' => 'Coupon code can only contain letters, numbers, hyphens, and underscores'
        ],
        'affiliateCode' => [
            'required' => false,
            'maxLength' => 50,
            'pattern' => '/^[A-Za-z0-9\-_]+$/',
            'message' => 'Affiliate code can only contain letters, numbers, hyphens, and underscores'
        ]
    ];
    
    // Address field requirements
    const ADDRESS_FIELDS = [
        'streetAddress' => [
            'required' => true,
            'maxLength' => 255,
            'message' => 'Street address is required'
        ],
        'city' => [
            'required' => true,
            'maxLength' => 50,
            'message' => 'City is required'
        ],
        'state' => [
            'required' => true,
            'maxLength' => 50,
            'message' => 'State is required'
        ],
        'zip' => [
            'required' => true,
            'pattern' => '/^\d{5}(-\d{4})?$/',
            'message' => 'Valid ZIP code is required'
        ],
        'countryId' => [
            'required' => true,
            'maxLength' => 2,
            'pattern' => '/^[A-Z]{2}$/',
            'message' => 'Valid country code is required'
        ]
    ];
    
    // Error Messages
    const ERROR_MESSAGES = [
        'VALIDATION_FAILED'          => 'Please correct the errors below and try again.',
        'PRODUCT_NOT_FOUND'          => 'The requested product was not found or is no longer available.',
        'PAYMENT_GATEWAY_DISABLED'   => 'The selected payment method is not available for this product.',
        'COUPON_INVALID'             => 'The coupon code is invalid or has expired.',
        'COUPON_NOT_APPLICABLE'      => 'This coupon is not applicable to the selected product.',
        'API_ERROR'                  => 'There was an error processing your request. Please try again.',
        'SESSION_EXPIRED'            => 'Your session has expired. Please start over.',
        'CSRF_INVALID'               => 'Security token is invalid. Please refresh the page and try again.',
        'RATE_LIMIT_EXCEEDED'        => 'Too many requests. Please wait a moment and try again.',
        'TENANT_NOT_FOUND'           => 'The specified tenant configuration was not found.',
        'PAYMENT_PROCESSING_ERROR'   => 'There was an error processing your payment. Please try again.',
        'REQUIRED_FIELD_MISSING'     => 'Please fill in all required fields.',
        'INVALID_EMAIL_FORMAT'       => 'Please enter a valid email address.',
        'INVALID_PHONE_FORMAT'       => 'Please enter a valid phone number.',
        'INVALID_ZIP_FORMAT'         => 'Please enter a valid ZIP code.',
        'ADDRESS_VALIDATION_FAILED'  => 'Please verify your address information.',
        'CUSTOM_FIELD_REQUIRED'      => 'This field is required for your order.'
    ];
    
    // Success Messages
    const SUCCESS_MESSAGES = [
        'PRODUCT_ADDED'              => 'Product added to cart successfully!',
        'COUPON_APPLIED'             => 'Coupon applied successfully!',
        'ADDRESS_VALIDATED'          => 'Address information validated successfully.',
        'PAYMENT_INITIATED'          => 'Payment processing initiated. You will be redirected to complete your payment.',
        'ORDER_CREATED'              => 'Your order has been created successfully!'
    ];
    
    /**
     * Get configuration value
     */
    public static function get($key)
    {
        return defined("self::$key") ? constant("self::$key") : null;
    }
    
    /**
     * Get API URL with endpoint
     */
    public static function getApiUrl($endpoint = '')
    {
        return self::API_URL . $endpoint;
    }
    
    /**
     * Get Sperse API URL with endpoint
     */
    public static function getSperseApiUrl($endpoint = '')
    {
        return self::SPERSE_API_BASE . $endpoint;
    }
    
    /**
     * Check if debug mode is enabled
     */
    public static function isDebugMode()
    {
        return self::DEBUG_MODE;
    }
    
    /**
     * Check if development mode is enabled
     */
    public static function isDevMode()
    {
        return self::DEV_MODE;
    }
    
    /**
     * Get validation rule for a field
     */
    public static function getValidationRule($field)
    {
        return self::VALIDATION_RULES[$field] ?? null;
    }
    
    /**
     * Get address field requirements
     */
    public static function getAddressFieldRule($field)
    {
        return self::ADDRESS_FIELDS[$field] ?? null;
    }
    
    /**
     * Get error message
     */
    public static function getErrorMessage($key)
    {
        return self::ERROR_MESSAGES[$key] ?? 'An unexpected error occurred.';
    }
    
    /**
     * Get success message
     */
    public static function getSuccessMessage($key)
    {
        return self::SUCCESS_MESSAGES[$key] ?? 'Operation completed successfully.';
    }
    
    /**
     * Check if Sperse orchestration is enabled
     */
    public static function useSperseOrchestration()
    {
        return self::USE_SPERSE_ORCHESTRATION;
    }
    
    /**
     * Check if client-side validation is enabled
     */
    public static function enableClientValidation()
    {
        return self::ENABLE_CLIENT_VALIDATION;
    }
    
    /**
     * Check if server-side validation is enabled
     */
    public static function enableServerValidation()
    {
        return self::ENABLE_SERVER_VALIDATION;
    }
    
    /**
     * Check if address validation is enabled
     */
    public static function enableAddressValidation()
    {
        return self::ENABLE_ADDRESS_VALIDATION;
    }
    
    /**
     * Check if coupon support is enabled
     */
    public static function enableCouponSupport()
    {
        return self::ENABLE_COUPON_SUPPORT;
    }
    
    /**
     * Check if affiliate tracking is enabled
     */
    public static function enableAffiliateTracking()
    {
        return self::ENABLE_AFFILIATE_TRACKING;
    }
    
    /**
     * Generate secure CSRF token
     */
    public static function generateCsrfToken()
    {
        return bin2hex(random_bytes(self::CSRF_TOKEN_LENGTH));
    }
    
    /**
     * Get tenant-specific configuration
     */
    public static function getTenantConfig($tenantId)
    {
        // This could be extended to load tenant-specific settings from database or cache
        return [
            'tenantId'                 => $tenantId,
            'requireBillingAddress'    => self::REQUIRE_BILLING_ADDRESS,
            'requireShippingAddress'   => self::REQUIRE_SHIPPING_ADDRESS,
            'enableCoupons'            => self::ENABLE_COUPON_SUPPORT,
            'enableAffiliateTracking'  => self::ENABLE_AFFILIATE_TRACKING,
            'customFields'             => []
        ];
    }
}
