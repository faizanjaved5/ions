<?php
/**
 * Channel Search API
 * Provides autocomplete/search functionality for ION channels
 */

session_start();

// Set JSON content type
header('Content-Type: application/json');

// Include database connection
require_once '../config/database.php';

// Only handle GET requests for search
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

try {
    // Get user role for permission check (allow guest/unauthenticated users)
    $is_authenticated = isset($_SESSION['user_email']) && !empty($_SESSION['user_email']);
    $user_role = $_SESSION['user_role'] ?? 'Guest';
    
    $search_term = trim($_GET['q'] ?? '');
    $limit = min(intval($_GET['limit'] ?? 20), 50); // Max 50 results
    
    if (empty($search_term)) {
        echo json_encode(['success' => true, 'channels' => [], 'user_role' => $user_role]);
        exit();
    }
    
    // Search IONLocalNetwork table for channels (matching ionlocalblast.php logic)
    // Use LOWER() for case-insensitive search
    $search_pattern = '%' . strtolower($search_term) . '%';
    
    $channels = $db->get_results(
        "SELECT 
            slug,
            city_name,
            channel_name,
            population,
            state_name,
            state_code,
            country_name,
            country_code,
            latitude,
            longitude,
            custom_domain
        FROM IONLocalNetwork
        WHERE 
            slug IS NOT NULL 
            AND city_name IS NOT NULL 
            AND (
                LOWER(city_name) LIKE ?
                OR LOWER(state_name) LIKE ?
                OR LOWER(country_name) LIKE ?
                OR LOWER(channel_name) LIKE ?
                OR LOWER(slug) LIKE ?
                OR LOWER(custom_domain) LIKE ?
                OR LOWER(state_code) LIKE ?
            )
        ORDER BY 
            CASE 
                WHEN LOWER(city_name) LIKE ? THEN 1
                WHEN LOWER(slug) LIKE ? THEN 2
                ELSE 3
            END,
            population DESC,
            city_name ASC
        LIMIT ?",
        $search_pattern,
        $search_pattern,
        $search_pattern,
        $search_pattern,
        $search_pattern,
        $search_pattern,
        $search_pattern,
        strtolower($search_term) . '%',  // Exact prefix match gets priority
        strtolower($search_term) . '%',
        $limit
    );
    
    // Format results for autocomplete
    $formatted_channels = [];
    foreach ($channels as $channel) {
        $state_display = $channel->state_code ?? $channel->state_name ?? '';
        $display_name = $channel->city_name;
        if ($state_display) {
            $display_name .= ', ' . $state_display;
        }
        if (!empty($channel->country_code) && $channel->country_code !== 'US') {
            $display_name .= ' (' . $channel->country_code . ')';
        }
        
        $formatted_channels[] = [
            'slug' => $channel->slug,
            'name' => $channel->city_name,
            'channel_name' => $channel->channel_name ?? $channel->city_name,
            'state' => $state_display,
            'country' => $channel->country_code ?? '',
            'display' => $display_name,
            'population' => $channel->population ?? 0
        ];
    }
    
    echo json_encode([
        'success' => true,
        'channels' => $formatted_channels,
        'count' => count($formatted_channels),
        'user_role' => $user_role,
        'is_authenticated' => $is_authenticated,
        'can_multi_select' => in_array($user_role, ['Admin', 'Owner'])
    ]);
    
} catch (Exception $e) {
    error_log('Channel search error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Search failed']);
}
?>

