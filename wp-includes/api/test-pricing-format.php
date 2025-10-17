<?php
/**
 * Test Pricing API Format
 * Simple test to see what format the API is actually returning
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Include database connection
require_once '../config/database.php';

// Use global database connection
global $db;
$wpdb = $db;

if (!$wpdb) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Test function (copy of what should be in get-pricing.php)
function testGetPricingBySlug($slug) {
    global $wpdb;
    
    $query = "
        SELECT 
            id, slug, city_name, state_code, country_code,
            price_monthly, price_quarterly, price_semi_annual, price_annual,
            currency, pricing_label, pricing_type
        FROM IONLocalNetworkPricing 
        WHERE slug = %s
        LIMIT 1
    ";
    
    $result = $wpdb->get_row($wpdb->prepare($query, $slug));
    
    if ($result) {
        return [
            'success' => true,
            'pricing' => [
                'monthly' => (float)$result->price_monthly,
                'quarterly' => (float)$result->price_quarterly,
                'semi_annual' => (float)$result->price_semi_annual,
                'annual' => (float)$result->price_annual,
                'currency' => $result->currency,
                'label' => $result->pricing_label,
                'type' => $result->pricing_type
            ],
            'location' => [
                'id' => $result->id,
                'slug' => $result->slug,
                'name' => $result->city_name,
                'state' => $result->state_code,
                'country' => $result->country_code
            ],
            'debug' => [
                'timestamp' => date('Y-m-d H:i:s'),
                'function' => 'testGetPricingBySlug',
                'file' => 'test-pricing-format.php'
            ]
        ];
    }
    
    return [
        'success' => false,
        'error' => 'Location not found',
        'debug' => [
            'timestamp' => date('Y-m-d H:i:s'),
            'function' => 'testGetPricingBySlug',
            'file' => 'test-pricing-format.php'
        ]
    ];
}

// Test with a known slug
$test_slug = $_GET['slug'] ?? 'ion-ochlocknee';
$result = testGetPricingBySlug($test_slug);

echo json_encode($result, JSON_PRETTY_PRINT);
?>
