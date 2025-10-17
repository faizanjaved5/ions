<?php
require_once __DIR__ . '/../../config/cart.php';
require_once __DIR__ . '/ValidationManager.php';

/**
 * SperseApiManager - Enhanced API client for Sperse multi-tenant platform
 * Handles all communication with Sperse APIs including validation and error handling
 */
class SperseApiManager
{
    private $apiClient;
    private $validationManager;
    private $tenantId;
    private $lastResponse;
    private $lastError;
    
    public function __construct($tenantId = null)
    {
        $this->tenantId = $tenantId;
        $this->validationManager = new ValidationManager($tenantId);
    }
    
    /**
     * Get product information from Sperse API
     */
    public function getProductInfo($tenantId, $publicName, $optionId = null)
    {
        $endpoint = '/GetProductInfo';
        $params = [
            'tenantId' => $tenantId,
            'publicName' => $publicName
        ];
        
        if ($optionId) {
            $params['optionId'] = $optionId;
        }
        
        $this->logDebug("🔍 Fetching Product Info", [
            'tenant' => $tenantId,
            'product' => $publicName,
            'option' => $optionId,
            'endpoint' => Config::getSperseApiUrl($endpoint)
        ]);
        
        try {
            $response = $this->makeApiCall('GET', $endpoint, $params);
            
            if ($response && isset($response['result'])) {
                $this->logDebug("✅ Product Info Retrieved", [
                    'product' => $response['result']['name'] ?? 'Unknown',
                    'type' => $response['result']['type'] ?? 'Unknown',
                    'priceOptions' => count($response['result']['priceOptions'] ?? []),
                    'paymentMethods' => $this->extractPaymentMethods($response['result'])
                ]);
                
                return $response['result'];
            }
            
            throw new Exception('Invalid response format from GetProductInfo API');
            
        } catch (Exception $e) {
            $this->logError("❌ GetProductInfo Error", $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Submit product request to Sperse API with comprehensive validation
     */
    public function submitProductRequest($requestData, $checkoutFields = [])
    {
        // Validate request data before submission
        if (!$this->validateRequestData($requestData, $checkoutFields)) {
            throw new Exception('Request validation failed: ' . implode(', ', $this->validationManager->getFormattedErrors()));
        }
        
        // Enhance request data with tracking information
        $enhancedData = $this->enhanceRequestData($requestData);
        
        $this->logDebug("🎯 Submitting Product Request to Sperse", [
            'tenant' => $enhancedData['tenantId'],
            'gateway' => $enhancedData['paymentGateway'],
            'products' => count($enhancedData['products']),
            'hasAddress' => isset($enhancedData['billingAddress']),
            'hasCoupon' => !empty($enhancedData['couponCode']),
            'hasAffiliate' => !empty($enhancedData['affiliateCode'])
        ]);
        
        try {
            $response = $this->makeApiCall('POST', '/SubmitProductRequest', [], $enhancedData);
            
            if ($response && isset($response['result'])) {
                $this->logDebug("✅ Payment Request Successful", [
                    'paymentUrl' => substr($response['result']['paymentData'] ?? '', 0, 50) . '...',
                    'invoiceId' => $response['result']['initialInvoicePublicId'] ?? 'N/A'
                ]);
                
                return [
                    'success' => true,
                    'paymentUrl' => $response['result']['paymentData'],
                    'invoiceId' => $response['result']['initialInvoicePublicId'] ?? null,
                    'invoiceXref' => $response['result']['initialInvoiceXref'] ?? null
                ];
            }
            
            throw new Exception('Invalid response format from SubmitProductRequest API');
            
        } catch (Exception $e) {
            $this->logError("❌ SubmitProductRequest Error", $e->getMessage());
            throw new Exception('Payment processing failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Get coupon information from Sperse API
     */
    public function getCouponInfo($tenantId, $couponCode, $currencyId = null)
    {
        if (!Config::enableCouponSupport()) {
            throw new Exception('Coupon support is not enabled');
        }
        
        // Validate coupon code format
        if (!$this->validationManager->validateCoupon($couponCode)) {
            throw new Exception('Invalid coupon code format');
        }
        
        $endpoint = '/GetCouponInfo';
        $params = [
            'tenantId' => $tenantId,
            'couponCode' => $couponCode
        ];
        
        if ($currencyId) {
            $params['currencyId'] = $currencyId;
        }
        
        $this->logDebug("🎫 Validating Coupon", [
            'tenant' => $tenantId,
            'coupon' => $couponCode,
            'currency' => $currencyId
        ]);
        
        try {
            $response = $this->makeApiCall('GET', $endpoint, $params);
            
            if ($response && isset($response['result'])) {
                $couponInfo = $response['result'];
                
                $this->logDebug("✅ Coupon Validation Result", [
                    'valid' => $couponInfo['isValid'] ?? false,
                    'discount' => $couponInfo['amountOff'] ?? $couponInfo['percentOff'] ?? 'N/A',
                    'duration' => $couponInfo['duration'] ?? 'N/A'
                ]);
                
                return $couponInfo;
            }
            
            return null;
            
        } catch (Exception $e) {
            $this->logError("❌ Coupon Validation Error", $e->getMessage());
            throw new Exception('Failed to validate coupon: ' . $e->getMessage());
        }
    }
    
    /**
     * Get checkout field configuration from Sperse
     */
    public function getCheckoutFields($tenantId)
    {
        // This would typically come from a Sperse API endpoint
        // For now, we'll return a default configuration based on tenant settings
        
        $tenantConfig = Config::getTenantConfig($tenantId);
        
        $defaultFields = [
            [
                'fieldName' => 'firstName',
                'displayName' => 'First Name',
                'isRequired' => true,
                'type' => 'text',
                'order' => 1
            ],
            [
                'fieldName' => 'lastName',
                'displayName' => 'Last Name',
                'isRequired' => true,
                'type' => 'text',
                'order' => 2
            ],
            [
                'fieldName' => 'email',
                'displayName' => 'Email Address',
                'isRequired' => true,
                'type' => 'email',
                'order' => 3
            ],
            [
                'fieldName' => 'phone',
                'displayName' => 'Phone Number',
                'isRequired' => false,
                'type' => 'tel',
                'order' => 4
            ],
            [
                'fieldName' => 'companyName',
                'displayName' => 'Company Name',
                'isRequired' => false,
                'type' => 'text',
                'order' => 5
            ]
        ];
        
        // Add billing address fields if required
        if ($tenantConfig['requireBillingAddress']) {
            $defaultFields[] = [
                'fieldName' => 'Billing',
                'displayName' => 'Billing Address',
                'isRequired' => true,
                'type' => 'address',
                'order' => 10
            ];
        }
        
        // Add shipping address fields if required
        if ($tenantConfig['requireShippingAddress']) {
            $defaultFields[] = [
                'fieldName' => 'Shipping',
                'displayName' => 'Shipping Address',
                'isRequired' => true,
                'type' => 'address',
                'order' => 11
            ];
        }
        
        // Add custom fields from tenant configuration
        foreach ($tenantConfig['customFields'] as $index => $customField) {
            $defaultFields[] = [
                'fieldName' => 'customField' . ($index + 1),
                'displayName' => $customField['displayName'],
                'isRequired' => $customField['isRequired'],
                'type' => $customField['type'] ?? 'text',
                'order' => 20 + $index
            ];
        }
        
        return $defaultFields;
    }
    
    /**
     * Validate request data before submission
     */
    private function validateRequestData($data, $checkoutFields)
    {
        if (!Config::enableServerValidation()) {
            return true;
        }
        
        return $this->validationManager->validateFormData($data, $checkoutFields);
    }
    
    /**
     * Enhance request data with tracking and metadata
     */
    private function enhanceRequestData($data)
    {
        $enhanced = $data;
        
        // Add tracking information if enabled
        if (Config::enableAffiliateTracking()) {
            if (Config::get('TRACK_USER_AGENT')) {
                $enhanced['userAgent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
            }
            
            if (Config::get('TRACK_REFERRER')) {
                $enhanced['refererUrl'] = $_SERVER['HTTP_REFERER'] ?? '';
            }
            
            if (Config::get('TRACK_ENTRY_URL')) {
                $enhanced['entryUrl'] = $_SERVER['REQUEST_URI'] ?? '';
            }
            
            // Add client IP
            $enhanced['clientIp'] = $this->getClientIP();
        }
        
        // Ensure required structure
        $enhanced['embeddedPayment'] = false; // Always use hosted checkout
        
        // Add source tracking
        $enhanced['sourceCode'] = 'PHP_CHECKOUT_MVP';
        $enhanced['channelCode'] = 'WEB';
        
        return $enhanced;
    }
    
    /**
     * Extract available payment methods from product data
     */
    private function extractPaymentMethods($productData)
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
     * Make API call to Sperse
     */
    private function makeApiCall($method, $endpoint, $params = [], $data = null)
    {
        $url = Config::getSperseApiUrl($endpoint);
        
        if ($method === 'GET' && !empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $headers = [
            'Content-Type: application/json;odata.metadata=minimal;odata.streaming=true',
            'Accept: application/json;odata.metadata=minimal;odata.streaming=true'
        ];
        
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3
        ]);
        
        if ($method === 'POST' && $data) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        
        if ($error) {
            throw new Exception("API request failed: {$error}");
        }
        
        $this->lastResponse = [
            'httpCode' => $httpCode,
            'body' => $response,
            'url' => $url
        ];
        
        if ($httpCode >= 400) {
            $errorData = json_decode($response, true);
            $errorMessage = $errorData['error']['message'] ?? "HTTP {$httpCode} error";
            throw new Exception($errorMessage);
        }
        
        $responseData = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from API');
        }
        
        return $responseData;
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP()
    {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs (from proxies)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    /**
     * Log debug information
     */
    private function logDebug($title, $data = [])
    {
        if (!Config::isDebugMode()) {
            return;
        }
        
        echo "<div style='background: #e7f1ff; border: 1px solid #b3d9ff; padding: 10px; border-radius: 4px; margin: 10px 0;'>";
        echo "<strong>{$title}</strong><br>";
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            echo "<strong>{$key}:</strong> " . htmlspecialchars($value) . "<br>";
        }
        
        echo "</div>";
    }
    
    /**
     * Log error information
     */
    private function logError($title, $message)
    {
        if (Config::isDebugMode()) {
            echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 4px; margin: 10px 0;'>";
            echo "<strong>{$title}</strong><br>";
            echo htmlspecialchars($message);
            echo "</div>";
        }
        
        // Log to error log as well
        error_log("Sperse API Error: {$title} - {$message}");
        
        $this->lastError = $message;
    }
    
    /**
     * Get last API response
     */
    public function getLastResponse()
    {
        return $this->lastResponse;
    }
    
    /**
     * Get last error
     */
    public function getLastError()
    {
        return $this->lastError;
    }
    
    /**
     * Get validation manager instance
     */
    public function getValidationManager()
    {
        return $this->validationManager;
    }
}
