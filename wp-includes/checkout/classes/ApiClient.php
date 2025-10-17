<?php

require_once __DIR__ . '/../config/config.php';

/**
 * ApiClient - Handles API communication with Sperse platform
 * Extracted from Next.js api.service.ts
 */
class ApiClient 
{
    private $baseUrl;
    private $timeout;
    
    public function __construct() 
    {
        $this->baseUrl = Config::API_URL;
        $this->timeout = 30; // 30 seconds timeout
    }
    
    /**
     * Submit product request to Sperse API
     * Equivalent to submitProductRequest in Next.js
     */
    public function submitProductRequest($requestData) 
    {
        $url = $this->baseUrl . Config::API_ENDPOINTS['SUBMIT_PRODUCT_REQUEST'];
        
        $headers = [
            'Content-Type: application/json;odata.metadata=minimal;odata.streaming=true',
            'Accept: application/json;odata.metadata=minimal;odata.streaming=true'
        ];
        
        try {
            $response = $this->makeHttpRequest('POST', $url, $requestData, $headers);
            
            if ($response['status'] === 200) {
                $responseData = json_decode($response['body'], true);
                
                // Debug: Show full API response even on success
                if (Config::isDebugMode()) {
                    echo "<div style='background: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; margin: 10px 0; border-radius: 4px;'>";
                    echo "<strong>✅ API Success Response:</strong><br>";
                    echo "<pre style='background: white; padding: 10px; border-radius: 4px; overflow-x: auto; max-height: 300px;'>";
                    echo htmlspecialchars(json_encode($responseData, JSON_PRETTY_PRINT));
                    echo "</pre>";
                    echo "</div>";
                }
                
                return $responseData['result'] ?? null;
            } else {
                // Enhanced error logging with detailed API response
                $errorData = [
                    'url' => $url,
                    'status' => $response['status'],
                    'body' => $response['body'],
                    'request_data' => $requestData
                ];
                
                $this->logError('API Error', $errorData);
                
                // Try to parse error message from response
                $responseData = json_decode($response['body'], true);
                if ($responseData && isset($responseData['error']['message'])) {
                    throw new Exception('Sperse API Error: ' . $responseData['error']['message']);
                } else {
                    throw new Exception('Sperse API Error (HTTP ' . $response['status'] . '): ' . $response['body']);
                }
            }
            
        } catch (Exception $e) {
            $this->logError('API Exception', [
                'message' => $e->getMessage(),
                'url' => $url
            ]);
            throw new Exception(Config::getErrorMessage('API_ERROR'));
        }
    }
    
    /**
     * Get domain variables
     */
    public function getDomainVariables($domain) 
    {
        $url = $this->baseUrl . '/api/services/CRM/ContactLandingPage/GetDomainVariables';
        $url .= '?domain=' . urlencode($domain);
        
        try {
            $response = $this->makeHttpRequest('GET', $url);
            
            if ($response['status'] === 200) {
                $responseData = json_decode($response['body'], true);
                return $responseData['result'] ?? null;
            }
            
            return null;
            
        } catch (Exception $e) {
            $this->logError('Domain Variables Error', [
                'message' => $e->getMessage(),
                'domain' => $domain
            ]);
            return null;
        }
    }
    
    /**
     * Get public contact info
     */
    public function getPublicContactInfo($tenancyName) 
    {
        $url = $this->baseUrl . '/api/services/CRM/ContactLandingPage/GetPublicContactInfo';
        $url .= '?tenancyName=' . urlencode($tenancyName);
        
        try {
            $response = $this->makeHttpRequest('GET', $url);
            
            if ($response['status'] === 200) {
                $responseData = json_decode($response['body'], true);
                return $responseData['result'] ?? null;
            }
            
            return null;
            
        } catch (Exception $e) {
            $this->logError('Contact Info Error', [
                'message' => $e->getMessage(),
                'tenancyName' => $tenancyName
            ]);
            return null;
        }
    }
    
    /**
     * Get coupon information
     */
    public function getCouponInfo($couponCode, $tenantId, $currencyId = 'USD') 
    {
        $url = $this->baseUrl . Config::API_ENDPOINTS['GET_COUPON_INFO'];
        $url .= '?tenantId=' . $tenantId . '&couponCode=' . urlencode($couponCode) . '&currencyId=' . $currencyId;
        
        try {
            $response = $this->makeHttpRequest('GET', $url);
            
            if ($response['status'] === 200) {
                $responseData = json_decode($response['body'], true);
                $result = $responseData['result'] ?? null;
                
                if ($result) {
                    return [
                        'valid' => true,
                        'discount' => $result['discountAmount'] ?? 0,
                        'percentage' => $result['discountPercentage'] ?? 0,
                        'code' => $couponCode
                    ];
                }
            }
            
            return ['valid' => false];
            
        } catch (Exception $e) {
            $this->logError('Coupon Info Error', [
                'message' => $e->getMessage(),
                'couponCode' => $couponCode
            ]);
            return ['valid' => false];
        }
    }
    
    /**
     * Get tax estimate
     */
    public function getTaxEstimate($params) 
    {
        $url = $this->baseUrl . Config::API_ENDPOINTS['GET_TAX_ESTIMATE'];
        
        $queryParams = http_build_query([
            'tenantId' => $params['tenantId'],
            'paymentGateway' => $params['paymentGateway'] ?? 'Stripe',
            'stateId' => $params['stateId'] ?? '',
            'countryId' => $params['countryId'] ?? 'US',
            'amount' => $params['amount'] ?? 0
        ]);
        
        $url .= '?' . $queryParams;
        
        try {
            $response = $this->makeHttpRequest('GET', $url);
            
            if ($response['status'] === 200) {
                $responseData = json_decode($response['body'], true);
                return $responseData['result'] ?? null;
            }
            
            return null;
            
        } catch (Exception $e) {
            $this->logError('Tax Estimate Error', [
                'message' => $e->getMessage(),
                'params' => $params
            ]);
            return null;
        }
    }
    
    /**
     * Make HTTP request using cURL
     */
    private function makeHttpRequest($method, $url, $data = null, $headers = []) 
    {
        $curl = curl_init();
        
        // Basic cURL options
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => !Config::isDevelopment(), // Disable SSL verification in dev
            CURLOPT_USERAGENT => 'PHP-Checkout-MVP/1.0'
        ]);
        
        // Add data for POST requests
        if ($method === 'POST' && $data !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        // Execute request
        $response = curl_exec($curl);
        $httpStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        
        curl_close($curl);
        
        if ($error) {
            throw new Exception("cURL Error: " . $error);
        }
        
        return [
            'status' => $httpStatus,
            'body' => $response
        ];
    }
    
    /**
     * Log errors for debugging
     */
    private function logError($type, $data) 
    {
        if (Config::isDebugMode()) {
            $logEntry = [
                'timestamp' => date('Y-m-d H:i:s'),
                'type' => $type,
                'data' => $data
            ];
            
            error_log("API Client Error: " . json_encode($logEntry));
            
            // Also display in browser for immediate debugging
            echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; margin: 10px 0; border-radius: 4px; font-family: monospace;'>";
            echo "<strong style='color: #721c24;'>🚨 API Debug Info:</strong><br><br>";
            
            if (isset($data['status'])) {
                echo "<strong>HTTP Status:</strong> " . $data['status'] . "<br>";
            }
            
            if (isset($data['url'])) {
                echo "<strong>API Endpoint:</strong> " . htmlspecialchars($data['url']) . "<br><br>";
            }
            
            if (isset($data['body'])) {
                echo "<strong>API Response:</strong><br>";
                $responseData = json_decode($data['body'], true);
                if ($responseData) {
                    echo "<pre style='background: white; padding: 10px; border-radius: 4px; overflow-x: auto;'>";
                    echo htmlspecialchars(json_encode($responseData, JSON_PRETTY_PRINT));
                    echo "</pre>";
                } else {
                    echo "<pre style='background: white; padding: 10px; border-radius: 4px;'>";
                    echo htmlspecialchars($data['body']);
                    echo "</pre>";
                }
            }
            
            if (isset($data['request_data'])) {
                echo "<br><strong>Request Data Sent:</strong><br>";
                echo "<pre style='background: #e9ecef; padding: 10px; border-radius: 4px; overflow-x: auto;'>";
                echo htmlspecialchars(json_encode($data['request_data'], JSON_PRETTY_PRINT));
                echo "</pre>";
            }
            
            echo "</div>";
        }
    }
    
    /**
     * Test API connectivity
     */
    public function testConnection() 
    {
        try {
            $response = $this->makeHttpRequest('GET', $this->baseUrl . '/api/health');
            return $response['status'] === 200;
        } catch (Exception $e) {
            return false;
        }
    }
}

?>
