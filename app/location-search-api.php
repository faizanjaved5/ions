<?php
/**
 * Location Search API
 * Provides autocomplete/search functionality for user locations from ION channels
 */

session_start();

// Set JSON content type
header('Content-Type: application/json');

// Include database connection
require_once '../config/database.php';

// Check authentication
if (!isset($_SESSION['user_email']) || empty($_SESSION['user_email'])) {
    echo json_encode(['success' => false, 'error' => 'User not authenticated']);
    exit();
}

// Only handle GET requests for search
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

try {
    $search_term = trim($_GET['q'] ?? '');
    $limit = min(intval($_GET['limit'] ?? 20), 50); // Max 50 results
    
    if (empty($search_term)) {
        echo json_encode(['success' => true, 'locations' => []]);
        exit();
    }
    
    // Search IONLocalNetwork table for locations (cities)
    // Use LOWER() for case-insensitive search
    $search_pattern = '%' . strtolower($search_term) . '%';
    
    $locations = $db->get_results(
        "SELECT DISTINCT
            city_name,
            state_name,
            state_code,
            country_name,
            country_code,
            population,
            latitude,
            longitude
        FROM IONLocalNetwork
        WHERE 
            city_name IS NOT NULL 
            AND (
                LOWER(city_name) LIKE ?
                OR LOWER(state_name) LIKE ?
                OR LOWER(country_name) LIKE ?
                OR LOWER(state_code) LIKE ?
            )
        ORDER BY 
            CASE 
                WHEN LOWER(city_name) LIKE ? THEN 1
                WHEN LOWER(state_name) LIKE ? THEN 2
                ELSE 3
            END,
            population DESC,
            city_name ASC
        LIMIT ?",
        $search_pattern,
        $search_pattern,
        $search_pattern,
        $search_pattern,
        strtolower($search_term) . '%',  // Exact prefix match gets priority
        strtolower($search_term) . '%',
        $limit
    );
    
    // Format results for autocomplete
    $formatted_locations = [];
    $seen_locations = []; // Track duplicates
    
    foreach ($locations as $location) {
        $state_display = $location->state_code ?? $location->state_name ?? '';
        $city_name = $location->city_name;
        
        // Create display format: "City, ST" or "City, State" or "City, Country"
        $display_name = $city_name;
        if ($state_display) {
            $display_name .= ', ' . $state_display;
        }
        if (!empty($location->country_code) && $location->country_code !== 'US') {
            $display_name .= ', ' . $location->country_code;
        }
        
        // Create unique key to avoid duplicates
        $unique_key = strtolower($city_name . '|' . $state_display . '|' . ($location->country_code ?? 'US'));
        
        // Skip if we've already added this location
        if (isset($seen_locations[$unique_key])) {
            continue;
        }
        
        $seen_locations[$unique_key] = true;
        
        $formatted_locations[] = [
            'city' => $city_name,
            'state' => $state_display,
            'state_name' => $location->state_name ?? '',
            'country' => $location->country_code ?? 'US',
            'country_name' => $location->country_name ?? '',
            'display' => $display_name,
            'population' => $location->population ?? 0,
            'latitude' => $location->latitude ?? null,
            'longitude' => $location->longitude ?? null
        ];
    }
    
    echo json_encode([
        'success' => true,
        'locations' => $formatted_locations,
        'count' => count($formatted_locations)
    ]);
    
} catch (Exception $e) {
    error_log('Location search error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Search failed']);
}
?>
