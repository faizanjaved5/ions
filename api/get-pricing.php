<?php
/**
 * API endpoint to get pricing information for ION Local Network locations
 * Supports both individual location pricing and bulk pricing requests
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Include database connection
require_once '../config/database.php';
// Note: Pricing data is public, no session required

// Use global database connection
global $db;
$wpdb = $db;

if (!$wpdb) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

/**
 * Get pricing for a single location by slug
 */
function getPricingBySlug($slug) {
    global $wpdb;
    
    // Join IONLocalNetwork with IONLocalPricing to get pricing data
    $query = "
        SELECT 
            n.id, n.slug, n.city_name, n.state_code, n.country_code,
            n.pricing_type, n.pricing_tier_id, n.pricing_currency,
            p.tier_name, p.tier_level,
            p.price_monthly, p.price_quarterly, p.price_semi_annual, p.price_annual,
            p.currency, p.description
        FROM IONLocalNetwork n
        LEFT JOIN IONLocalPricing p ON n.pricing_tier_id = p.id
        WHERE n.slug = %s
        LIMIT 1
    ";
    
    error_log("GET-PRICING API: Querying for slug: $slug");
    
    try {
        $result = $wpdb->get_row($wpdb->prepare($query, $slug));
        
        // Log database errors
        if ($wpdb->last_error) {
            error_log("GET-PRICING API: Database error: " . $wpdb->last_error);
            return [
                'success' => false,
                'error' => 'Database error: ' . $wpdb->last_error
            ];
        }
        
        if ($result) {
            error_log("GET-PRICING API: Found pricing for slug: $slug");
            
            // Use pricing tier data if available, otherwise return zeros
            $monthly = $result->price_monthly ? (float)$result->price_monthly : 0;
            $quarterly = $result->price_quarterly ? (float)$result->price_quarterly : 0;
            $semi_annual = $result->price_semi_annual ? (float)$result->price_semi_annual : 0;
            $annual = $result->price_annual ? (float)$result->price_annual : 0;
            $currency = $result->currency ?: $result->pricing_currency ?: 'USD';
            $label = $result->tier_name ?: 'Standard';
            
            return [
                'success' => true,
                'pricing' => [
                    'monthly' => $monthly,
                    'quarterly' => $quarterly,
                    'semi_annual' => $semi_annual,
                    'annual' => $annual,
                    'currency' => $currency,
                    'label' => $label,
                    'type' => $result->pricing_type ?: 'tier'
                ],
                'location' => [
                    'id' => $result->id,
                    'slug' => $result->slug,
                    'name' => $result->city_name,
                    'state' => $result->state_code,
                    'country' => $result->country_code
                ]
            ];
        }
        
        error_log("GET-PRICING API: No location found for slug: $slug");
        
    } catch (Throwable $e) {
        error_log("GET-PRICING API: Exception: " . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Exception: ' . $e->getMessage()
        ];
    }
    
    return [
        'success' => false,
        'error' => 'Location not found'
    ];
}

/**
 * Get pricing for multiple locations by slugs
 */
function getPricingBulk($slugs) {
    global $wpdb;
    
    if (empty($slugs) || !is_array($slugs)) {
        return [
            'success' => false,
            'error' => 'Invalid slugs provided'
        ];
    }
    
    // Limit to 100 slugs for performance
    $slugs = array_slice($slugs, 0, 100);
    
    // Create placeholders for prepared statement
    $placeholders = implode(',', array_fill(0, count($slugs), '%s'));
    
    // Join IONLocalNetwork with IONLocalPricing
    $query = "
        SELECT 
            n.id, n.slug, n.city_name, n.state_code, n.country_code,
            n.pricing_type, n.pricing_tier_id, n.pricing_currency,
            p.tier_name, p.tier_level,
            p.price_monthly, p.price_quarterly, p.price_semi_annual, p.price_annual,
            p.currency, p.description
        FROM IONLocalNetwork n
        LEFT JOIN IONLocalPricing p ON n.pricing_tier_id = p.id
        WHERE n.slug IN ($placeholders)
    ";
    
    $results = $wpdb->get_results($wpdb->prepare($query, $slugs));
    
    $locations = [];
    foreach ($results as $result) {
        $monthly = $result->price_monthly ? (float)$result->price_monthly : 0;
        $quarterly = $result->price_quarterly ? (float)$result->price_quarterly : 0;
        $semi_annual = $result->price_semi_annual ? (float)$result->price_semi_annual : 0;
        $annual = $result->price_annual ? (float)$result->price_annual : 0;
        $currency = $result->currency ?: $result->pricing_currency ?: 'USD';
        $label = $result->tier_name ?: 'Standard';
        
        $locations[$result->slug] = [
            'id' => $result->id,
            'slug' => $result->slug,
            'name' => $result->city_name,
            'state' => $result->state_code,
            'country' => $result->country_code,
            'pricing' => [
                'monthly' => $monthly,
                'quarterly' => $quarterly,
                'semi_annual' => $semi_annual,
                'annual' => $annual,
                'currency' => $currency,
                'label' => $label,
                'type' => $result->pricing_type ?: 'tier'
            ]
        ];
    }
    
    return [
        'success' => true,
        'locations' => $locations,
        'count' => count($locations)
    ];
}

/**
 * Get all pricing tiers
 */
function getAllTiers() {
    global $wpdb;
    
    $query = "
        SELECT 
            tier_name, tier_level, 
            price_monthly, price_quarterly, price_semi_annual, price_annual,
            currency, description
        FROM IONLocalPricing 
        WHERE status = 'active'
        ORDER BY tier_level ASC
    ";
    
    $results = $wpdb->get_results($query);
    
    $tiers = [];
    foreach ($results as $result) {
        $tiers[] = [
            'name' => $result->tier_name,
            'level' => $result->tier_level,
            'pricing' => [
                'monthly' => (float)$result->price_monthly,
                'quarterly' => (float)$result->price_quarterly,
                'semi_annual' => (float)$result->price_semi_annual,
                'annual' => (float)$result->price_annual,
                'currency' => $result->currency
            ],
            'description' => $result->description
        ];
    }
    
    return [
        'success' => true,
        'tiers' => $tiers
    ];
}

// Handle requests
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    
    if (isset($_GET['action'])) {
        switch ($_GET['action']) {
            case 'get_pricing':
                if (isset($_GET['slug'])) {
                    $response = getPricingBySlug($_GET['slug']);
                } else {
                    $response = ['success' => false, 'error' => 'Slug parameter required'];
                }
                break;
                
            case 'get_bulk':
                if (isset($_GET['slugs'])) {
                    $slugs = explode(',', $_GET['slugs']);
                    $slugs = array_map('trim', $slugs);
                    $response = getPricingBulk($slugs);
                } else {
                    $response = ['success' => false, 'error' => 'Slugs parameter required'];
                }
                break;
                
            case 'get_tiers':
                $response = getAllTiers();
                break;
                
            default:
                $response = ['success' => false, 'error' => 'Invalid action'];
        }
    } else {
        $response = ['success' => false, 'error' => 'Action parameter required'];
    }
    
} elseif ($method === 'POST') {
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        $response = ['success' => false, 'error' => 'Invalid JSON payload'];
    } else {
        switch ($input['action'] ?? '') {
            case 'get_pricing':
                if (isset($input['slug'])) {
                    $response = getPricingBySlug($input['slug']);
                } else {
                    $response = ['success' => false, 'error' => 'Slug parameter required'];
                }
                break;
                
            case 'get_bulk':
                if (isset($input['slugs']) && is_array($input['slugs'])) {
                    $response = getPricingBulk($input['slugs']);
                } else {
                    $response = ['success' => false, 'error' => 'Slugs array required'];
                }
                break;
                
            case 'get_tiers':
                $response = getAllTiers();
                break;
                
            default:
                $response = ['success' => false, 'error' => 'Invalid action'];
        }
    }
    
} else {
    http_response_code(405);
    $response = ['success' => false, 'error' => 'Method not allowed'];
}

echo json_encode($response);
?>
