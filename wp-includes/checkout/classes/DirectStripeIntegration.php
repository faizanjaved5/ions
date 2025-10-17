<?php

require_once __DIR__ . '/../config/config.php';

/**
 * Direct Stripe Integration (Bypass Sperse)
 * For testing Stripe payments without Sperse platform
 */
class DirectStripeIntegration 
{
    private $stripeSecretKey;
    private $stripePublishableKey;
    
    public function __construct() 
    {
        // Read Stripe keys from environment. Never hardcode keys in source control.
        $this->stripeSecretKey      = getenv('STRIPE_SECRET_KEY') ?: '';
        $this->stripePublishableKey = getenv('STRIPE_PUBLISHABLE_KEY') ?: '';
        if (!$this->stripeSecretKey || !$this->stripePublishableKey) {
            throw new Exception('Stripe keys are not configured in environment');
        }
    }
    
    /**
     * Create Stripe Checkout Session
     */
    public function createCheckoutSession($cartItems, $customerData, $successUrl, $cancelUrl, $suppressDebug = false) 
    {
        // Prepare line items for Stripe
        $lineItems = [];
        
        foreach ($cartItems as $item) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => strtolower($item['currencyId'] ?? 'usd'),
                    'product_data' => [
                        'name' => $item['name']
                    ],
                    'unit_amount' => intval($item['price'] * 100) // Convert to cents
                ],
                'quantity' => $item['quantity']
            ];
        }
        
        // Create Stripe session
        $sessionData = [
            'payment_method_types' => ['card'],
            'line_items' => $lineItems,
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'customer_email' => $customerData['email'],
            'billing_address_collection' => 'required'
        ];
        
        return $this->makeStripeRequest('checkout/sessions', $sessionData, 'POST', $suppressDebug);
    }
    
    /**
     * Make request to Stripe API
     */
    private function makeStripeRequest($endpoint, $data = null, $method = 'GET', $suppressDebug = false) 
    {
        $url = 'https://api.stripe.com/v1/' . $endpoint;
        
        if (Config::isDebugMode() && !$suppressDebug) {
            echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 10px 0; border-radius: 4px;'>";
            echo "<strong>🔌 Making Direct Stripe API Call:</strong><br>";
            echo "Endpoint: " . htmlspecialchars($url) . "<br>";
            echo "Method: " . $method . "<br>";
            echo "</div>";
        }
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->stripeSecretKey,
                'Content-Type: application/x-www-form-urlencoded'
            ],
        ]);
        
        if ($data && $method === 'POST') {
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
        }
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        
        if ($error) {
            throw new Exception('Stripe API request failed: ' . $error);
        }
        
        $responseData = json_decode($response, true);
        
        if (Config::isDebugMode() && !$suppressDebug) {
            echo "<div style='background: #e2e3e5; border: 1px solid #d6d8db; padding: 10px; margin: 10px 0; border-radius: 4px;'>";
            echo "<strong>📨 Stripe API Response (HTTP " . $httpCode . "):</strong><br>";
            echo "<pre style='background: white; padding: 10px; border-radius: 4px; overflow-x: auto; max-height: 200px;'>";
            echo htmlspecialchars(json_encode($responseData, JSON_PRETTY_PRINT));
            echo "</pre>";
            echo "</div>";
        }
        
        if ($httpCode !== 200) {
            $errorMessage = isset($responseData['error']['message']) 
                ? $responseData['error']['message'] 
                : 'Unknown Stripe API error';
            throw new Exception('Stripe API Error (HTTP ' . $httpCode . '): ' . $errorMessage);
        }
        
        return $responseData;
    }
}

?>