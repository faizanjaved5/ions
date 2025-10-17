<?php

require_once __DIR__ . '/../config/config.php';

/**
 * ValidationHelper - Form validation utilities
 * Extracted from Next.js Zod validation schemas
 */
class ValidationHelper 
{
    /**
     * Validate product data
     */
    public function validateProduct($productData) 
    {
        $errors = [];
        $required = ['id', 'name', 'price', 'type'];
        
        foreach ($required as $field) {
            if (!isset($productData[$field]) || empty($productData[$field])) {
                $errors[] = "Product {$field} is required";
            }
        }
        
        // Validate price
        if (isset($productData['price']) && !is_numeric($productData['price'])) {
            $errors[] = "Product price must be a valid number";
        }
        
        // Validate quantity
        if (isset($productData['quantity'])) {
            $quantity = (int)$productData['quantity'];
            if ($quantity < 1) {
                $errors[] = "Product quantity must be at least 1";
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Validate customer information
     * Based on Next.js checkout form validation
     */
    public function validateCustomerInfo($customerData) 
    {
        $errors = [];
        
        // Required fields
        $required = ['firstName', 'lastName', 'email'];
        
        foreach ($required as $field) {
            if (!isset($customerData[$field]) || empty(trim($customerData[$field]))) {
                $errors[] = Config::getErrorMessage('MISSING_REQUIRED_FIELD') . ": {$field}";
            }
        }
        
        // Validate first name
        if (isset($customerData['firstName'])) {
            $firstName = trim($customerData['firstName']);
            if (strlen($firstName) < 1 || strlen($firstName) > 50) {
                $errors[] = "First name must be between 1 and 50 characters";
            }
        }
        
        // Validate last name
        if (isset($customerData['lastName'])) {
            $lastName = trim($customerData['lastName']);
            if (strlen($lastName) < 1 || strlen($lastName) > 50) {
                $errors[] = "Last name must be between 1 and 50 characters";
            }
        }
        
        // Validate email
        if (isset($customerData['email'])) {
            $email = trim($customerData['email']);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = Config::getErrorMessage('INVALID_EMAIL');
            } elseif (strlen($email) > 100) {
                $errors[] = "Email must be less than 100 characters";
            }
        }
        
        // Validate phone (optional)
        if (!empty($customerData['phone'])) {
            $phone = preg_replace('/[^0-9+]/', '', $customerData['phone']);
            if (!preg_match('/^\+?[1-9]\d{10,14}$/', $phone)) {
                $errors[] = Config::getErrorMessage('INVALID_PHONE');
            }
        }
        
        // Validate company name (optional)
        if (!empty($customerData['companyName']) && strlen($customerData['companyName']) > 100) {
            $errors[] = "Company name must be less than 100 characters";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Validate address data
     * Based on Next.js address validation schema
     */
    public function validateAddress($addressData) 
    {
        $errors = [];
        
        // Required fields
        $required = ['street', 'city', 'state', 'zip', 'country'];
        
        foreach ($required as $field) {
            if (!isset($addressData[$field]) || empty(trim($addressData[$field]))) {
                $errors[] = ucfirst($field) . " is required";
            }
        }
        
        // Validate street address
        if (isset($addressData['street'])) {
            $street = trim($addressData['street']);
            if (strlen($street) > 255) {
                $errors[] = "Street address must be less than 255 characters";
            }
        }
        
        // Validate city
        if (isset($addressData['city'])) {
            $city = trim($addressData['city']);
            if (strlen($city) > 50) {
                $errors[] = "City must be less than 50 characters";
            }
        }
        
        // Validate state
        if (isset($addressData['state'])) {
            $state = trim($addressData['state']);
            if (strlen($state) > 50) {
                $errors[] = "State must be less than 50 characters";
            }
        }
        
        // Validate ZIP code
        if (isset($addressData['zip'])) {
            $zip = trim($addressData['zip']);
            
            // Basic ZIP validation (can be enhanced for specific countries)
            if (!preg_match('/^\d{5}(-\d{4})?$/', $zip) && !preg_match('/^[A-Z]\d[A-Z] \d[A-Z]\d$/', $zip)) {
                $errors[] = "Please enter a valid ZIP/Postal code";
            }
        }
        
        // Validate country
        if (isset($addressData['country'])) {
            $country = trim($addressData['country']);
            if (strlen($country) !== 2) {
                $errors[] = "Please select a valid country";
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Validate payment data
     */
    public function validatePaymentData($paymentData) 
    {
        $errors = [];
        
        // Validate payment gateway
        if (!isset($paymentData['paymentGateway']) || 
            !in_array($paymentData['paymentGateway'], ['Stripe', 'PayPal'])) {
            $errors[] = "Please select a valid payment method";
        }
        
        // Validate tenant ID
        if (!isset($paymentData['tenantId']) || !is_numeric($paymentData['tenantId'])) {
            $errors[] = "Invalid tenant ID";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Sanitize input data
     */
    public function sanitizeInput($data) 
    {
        if (is_array($data)) {
            return array_map([$this, 'sanitizeInput'], $data);
        }
        
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate CSRF token
     */
    public function validateCsrfToken($token, $sessionToken) 
    {
        return hash_equals($sessionToken, $token);
    }
    
    /**
     * Validate coupon code format
     */
    public function validateCouponCode($couponCode) 
    {
        if (empty($couponCode)) {
            return ['valid' => false, 'error' => 'Coupon code is required'];
        }
        
        // Basic coupon code validation
        if (strlen($couponCode) > 36) {
            return ['valid' => false, 'error' => 'Coupon code is too long'];
        }
        
        if (!preg_match('/^[A-Za-z0-9\-_]+$/', $couponCode)) {
            return ['valid' => false, 'error' => 'Coupon code contains invalid characters'];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Validate price input
     */
    public function validatePrice($price) 
    {
        if (!is_numeric($price)) {
            return ['valid' => false, 'error' => 'Price must be a valid number'];
        }
        
        $price = (float)$price;
        
        if ($price < 0) {
            return ['valid' => false, 'error' => 'Price cannot be negative'];
        }
        
        if ($price > 999999.99) {
            return ['valid' => false, 'error' => 'Price is too high'];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Format and validate phone number
     */
    public function formatPhoneNumber($phone, $countryCode = 'US') 
    {
        // Remove all non-numeric characters except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Basic US phone number formatting
        if ($countryCode === 'US' && strlen($phone) === 10) {
            return '+1' . $phone;
        }
        
        return $phone;
    }
}

?>
