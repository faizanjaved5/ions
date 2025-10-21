<?php
require_once __DIR__ . '/../../config/cart.php';

/**
 * ValidationManager - Comprehensive validation for Sperse checkout
 * Handles client-side and server-side validation with granular rules
 */
class ValidationManager
{
    private $errors = [];
    private $warnings = [];
    private $tenantConfig = [];
    
    public function __construct($tenantId = null)
    {
        if ($tenantId) {
            $this->tenantConfig = Config::getTenantConfig($tenantId);
        }
    }
    
    /**
     * Validate all form data
     */
    public function validateFormData($data, $checkoutFields = [])
    {
        $this->errors = [];
        $this->warnings = [];
        
        // Validate required fields
        $this->validateRequiredFields($data, $checkoutFields);
        
        // Validate field formats
        $this->validateFieldFormats($data);
        
        // Validate addresses if provided
        if (isset($data['billingAddress'])) {
            $this->validateAddress($data['billingAddress'], 'billing');
        }
        
        if (isset($data['shippingAddress'])) {
            $this->validateAddress($data['shippingAddress'], 'shipping');
        }
        
        // Validate coupon if provided
        if (isset($data['couponCode']) && !empty($data['couponCode'])) {
            $this->validateCoupon($data['couponCode']);
        }
        
        // Validate affiliate code if provided
        if (isset($data['affiliateCode']) && !empty($data['affiliateCode'])) {
            $this->validateAffiliateCode($data['affiliateCode']);
        }
        
        return empty($this->errors);
    }
    
    /**
     * Validate required fields based on checkout configuration
     */
    private function validateRequiredFields($data, $checkoutFields)
    {
        // Standard required fields
        $standardRequired = ['firstName', 'lastName', 'email'];
        
        foreach ($standardRequired as $field) {
            if (empty($data[$field])) {
                $this->addError($field, Config::getErrorMessage('REQUIRED_FIELD_MISSING'));
            }
        }
        
        // Dynamic required fields from checkout configuration
        foreach ($checkoutFields as $field) {
            $fieldName = $this->getFieldName($field);
            if ($field['isRequired'] && empty($data[$fieldName])) {
                $displayName = $field['displayName'] ?? ucfirst($fieldName);
                $this->addError($fieldName, "{$displayName} is required");
            }
        }
        
        // Tenant-specific requirements
        if (Config::enableAddressValidation()) {
            if (Config::get('REQUIRE_BILLING_ADDRESS') && empty($data['billingAddress'])) {
                $this->addError('billingAddress', 'Billing address is required');
            }
            
            if (Config::get('REQUIRE_SHIPPING_ADDRESS') && empty($data['shippingAddress'])) {
                $this->addError('shippingAddress', 'Shipping address is required');
            }
        }
    }
    
    /**
     * Validate field formats using configuration rules
     */
    private function validateFieldFormats($data)
    {
        foreach ($data as $field => $value) {
            if (empty($value)) continue;
            
            $rule = Config::getValidationRule($field);
            if (!$rule) continue;
            
            // Check length constraints
            if (isset($rule['minLength']) && strlen($value) < $rule['minLength']) {
                $this->addError($field, "Minimum length is {$rule['minLength']} characters");
            }
            
            if (isset($rule['maxLength']) && strlen($value) > $rule['maxLength']) {
                $this->addError($field, "Maximum length is {$rule['maxLength']} characters");
            }
            
            // Check pattern matching
            if (isset($rule['pattern']) && !preg_match($rule['pattern'], $value)) {
                $this->addError($field, $rule['message']);
            }
        }
    }
    
    /**
     * Validate address information
     */
    private function validateAddress($address, $type = 'billing')
    {
        if (!Config::enableAddressValidation()) {
            return true;
        }
        
        $requiredFields = ['streetAddress', 'city', 'state', 'zip', 'countryId'];
        
        foreach ($requiredFields as $field) {
            $rule = Config::getAddressFieldRule($field);
            if (!$rule) continue;
            
            $value = $address[$field] ?? '';
            
            // Check if required
            if ($rule['required'] && empty($value)) {
                $this->addError("{$type}Address.{$field}", $rule['message']);
                continue;
            }
            
            if (empty($value)) continue;
            
            // Check length constraints
            if (isset($rule['maxLength']) && strlen($value) > $rule['maxLength']) {
                $this->addError("{$type}Address.{$field}", "Maximum length is {$rule['maxLength']} characters");
            }
            
            // Check pattern matching
            if (isset($rule['pattern']) && !preg_match($rule['pattern'], $value)) {
                $this->addError("{$type}Address.{$field}", $rule['message']);
            }
        }
        
        // Validate country-specific ZIP formats
        $this->validateCountrySpecificZip($address, $type);
        
        return empty($this->errors);
    }
    
    /**
     * Validate country-specific ZIP code formats
     */
    private function validateCountrySpecificZip($address, $type)
    {
        $countryId = $address['countryId'] ?? '';
        $zip = $address['zip'] ?? '';
        
        if (empty($zip) || empty($countryId)) return;
        
        $zipPatterns = [
            'US' => '/^\d{5}(-\d{4})?$/',
            'CA' => '/^[A-Za-z]\d[A-Za-z][ -]?\d[A-Za-z]\d$/',
            'GB' => '/^[A-Za-z]{1,2}\d[A-Za-z\d]?\s?\d[A-Za-z]{2}$/',
            'DE' => '/^\d{5}$/',
            'FR' => '/^\d{5}$/',
            'AU' => '/^\d{4}$/',
        ];
        
        if (isset($zipPatterns[$countryId])) {
            if (!preg_match($zipPatterns[$countryId], $zip)) {
                $this->addError("{$type}Address.zip", "Invalid ZIP/postal code format for {$countryId}");
            }
        }
    }
    
    /**
     * Validate coupon code format
     */
    private function validateCoupon($couponCode)
    {
        if (!Config::enableCouponSupport()) {
            $this->addWarning('couponCode', 'Coupon codes are not supported');
            return false;
        }
        
        $rule = Config::getValidationRule('couponCode');
        
        if (strlen($couponCode) > $rule['maxLength']) {
            $this->addError('couponCode', "Coupon code cannot exceed {$rule['maxLength']} characters");
            return false;
        }
        
        if (!preg_match($rule['pattern'], $couponCode)) {
            $this->addError('couponCode', $rule['message']);
            return false;
        }
        
        // Additional coupon-specific validation
        if (!Config::get('COUPON_CASE_SENSITIVE')) {
            // Convert to uppercase for consistency
            $couponCode = strtoupper($couponCode);
        }
        
        return true;
    }
    
    /**
     * Validate affiliate code format
     */
    private function validateAffiliateCode($affiliateCode)
    {
        if (!Config::enableAffiliateTracking()) {
            $this->addWarning('affiliateCode', 'Affiliate tracking is not enabled');
            return false;
        }
        
        $rule = Config::getValidationRule('affiliateCode');
        
        if (strlen($affiliateCode) > $rule['maxLength']) {
            $this->addError('affiliateCode', "Affiliate code cannot exceed {$rule['maxLength']} characters");
            return false;
        }
        
        if (!preg_match($rule['pattern'], $affiliateCode)) {
            $this->addError('affiliateCode', $rule['message']);
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate custom fields
     */
    public function validateCustomFields($data, $customFieldsConfig)
    {
        foreach ($customFieldsConfig as $field) {
            $fieldName = $field['fieldName'];
            $value = $data[$fieldName] ?? '';
            
            // Check if required
            if ($field['isRequired'] && empty($value)) {
                $displayName = $field['displayName'] ?? ucfirst($fieldName);
                $this->addError($fieldName, "{$displayName} is required");
                continue;
            }
            
            if (empty($value)) continue;
            
            // Check length constraints
            $maxLength = $field['maxLength'] ?? Config::get('CUSTOM_FIELD_MAX_LENGTH');
            if (strlen($value) > $maxLength) {
                $this->addError($fieldName, "Maximum length is {$maxLength} characters");
            }
            
            // Check field type specific validation
            switch ($field['type']) {
                case 'email':
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $this->addError($fieldName, 'Please enter a valid email address');
                    }
                    break;
                    
                case 'phone':
                    $rule = Config::getValidationRule('phone');
                    if (!preg_match($rule['pattern'], $value)) {
                        $this->addError($fieldName, $rule['message']);
                    }
                    break;
                    
                case 'number':
                    if (!is_numeric($value)) {
                        $this->addError($fieldName, 'Please enter a valid number');
                    }
                    break;
                    
                case 'date':
                    if (!strtotime($value)) {
                        $this->addError($fieldName, 'Please enter a valid date');
                    }
                    break;
            }
        }
    }
    
    /**
     * Generate client-side validation JavaScript
     */
    public function generateClientValidationJS($checkoutFields = [])
    {
        if (!Config::enableClientValidation()) {
            return '';
        }
        
        $rules = [];
        
        // Add standard validation rules
        foreach (Config::VALIDATION_RULES as $field => $rule) {
            $rules[$field] = [
                'required' => $rule['required'] ?? false,
                'minLength' => $rule['minLength'] ?? null,
                'maxLength' => $rule['maxLength'] ?? null,
                'pattern' => $rule['pattern'] ?? null,
                'message' => $rule['message'] ?? ''
            ];
        }
        
        // Add dynamic field rules
        foreach ($checkoutFields as $field) {
            $fieldName = $this->getFieldName($field);
            $rules[$fieldName] = [
                'required' => $field['isRequired'] ?? false,
                'message' => $field['displayName'] ?? ucfirst($fieldName) . ' is required'
            ];
        }
        
        $js = "
        <script>
        const ValidationRules = " . json_encode($rules) . ";
        const ValidationManager = {
            errors: {},
            
            validateField: function(fieldName, value) {
                const rule = ValidationRules[fieldName];
                if (!rule) return true;
                
                this.errors[fieldName] = [];
                
                // Required check
                if (rule.required && (!value || value.trim() === '')) {
                    this.errors[fieldName].push(rule.message || fieldName + ' is required');
                    return false;
                }
                
                if (!value) return true;
                
                // Length checks
                if (rule.minLength && value.length < rule.minLength) {
                    this.errors[fieldName].push('Minimum length is ' + rule.minLength + ' characters');
                }
                
                if (rule.maxLength && value.length > rule.maxLength) {
                    this.errors[fieldName].push('Maximum length is ' + rule.maxLength + ' characters');
                }
                
                // Pattern check
                if (rule.pattern) {
                    const regex = new RegExp(rule.pattern.slice(1, -1)); // Remove / delimiters
                    if (!regex.test(value)) {
                        this.errors[fieldName].push(rule.message || 'Invalid format');
                    }
                }
                
                return this.errors[fieldName].length === 0;
            },
            
            validateForm: function(formData) {
                this.errors = {};
                let isValid = true;
                
                for (const fieldName in ValidationRules) {
                    const value = formData[fieldName] || '';
                    if (!this.validateField(fieldName, value)) {
                        isValid = false;
                    }
                }
                
                return isValid;
            },
            
            showFieldError: function(fieldName, messages) {
                const field = document.querySelector('[name=\"' + fieldName + '\"]');
                const errorContainer = document.querySelector('#error-' + fieldName);
                
                if (field) {
                    field.classList.add('error');
                }
                
                if (errorContainer) {
                    errorContainer.innerHTML = messages.join('<br>');
                    errorContainer.style.display = 'block';
                }
            },
            
            clearFieldError: function(fieldName) {
                const field = document.querySelector('[name=\"' + fieldName + '\"]');
                const errorContainer = document.querySelector('#error-' + fieldName);
                
                if (field) {
                    field.classList.remove('error');
                }
                
                if (errorContainer) {
                    errorContainer.innerHTML = '';
                    errorContainer.style.display = 'none';
                }
            },
            
            displayErrors: function() {
                for (const fieldName in this.errors) {
                    if (this.errors[fieldName].length > 0) {
                        this.showFieldError(fieldName, this.errors[fieldName]);
                    } else {
                        this.clearFieldError(fieldName);
                    }
                }
            }
        };
        
        // Auto-bind validation to form fields
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            if (!form) return;
            
            // Add real-time validation
            form.addEventListener('blur', function(e) {
                if (e.target.name && ValidationRules[e.target.name]) {
                    ValidationManager.validateField(e.target.name, e.target.value);
                    ValidationManager.displayErrors();
                }
            }, true);
            
            // Validate on form submit
            form.addEventListener('submit', function(e) {
                const formData = new FormData(form);
                const data = {};
                for (const [key, value] of formData.entries()) {
                    data[key] = value;
                }
                
                if (!ValidationManager.validateForm(data)) {
                    e.preventDefault();
                    ValidationManager.displayErrors();
                    
                    // Scroll to first error
                    const firstError = document.querySelector('.error');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
            });
        });
        </script>";
        
        return $js;
    }
    
    /**
     * Helper to map field names
     */
    private function getFieldName($field)
    {
        if (is_array($field) && isset($field['fieldName'])) {
            $fieldName = $field['fieldName'];
            
            // Map backend field names to frontend field names
            $fieldMap = [
                'PhoneNumber' => 'phone',
                'DateOfBirth' => 'dob',
                'Company' => 'companyName'
            ];
            
            return $fieldMap[$fieldName] ?? strtolower($fieldName);
        }
        
        return $field;
    }
    
    /**
     * Add validation error
     */
    private function addError($field, $message)
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }
    
    /**
     * Add validation warning
     */
    private function addWarning($field, $message)
    {
        if (!isset($this->warnings[$field])) {
            $this->warnings[$field] = [];
        }
        $this->warnings[$field][] = $message;
    }
    
    /**
     * Get validation errors
     */
    public function getErrors()
    {
        return $this->errors;
    }
    
    /**
     * Get validation warnings
     */
    public function getWarnings()
    {
        return $this->warnings;
    }
    
    /**
     * Check if validation passed
     */
    public function isValid()
    {
        return empty($this->errors);
    }
    
    /**
     * Get formatted error messages for display
     */
    public function getFormattedErrors()
    {
        $formatted = [];
        foreach ($this->errors as $field => $messages) {
            $formatted[] = implode(', ', $messages);
        }
        return $formatted;
    }
}
