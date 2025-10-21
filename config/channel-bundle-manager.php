<?php
/**
 * Channel Bundle Manager
 * 
 * This script allows you to create, manage, and update channel bundles
 * Handles both small bundles (dozens) and large bundles (thousands of channels)
 */

// Enable error reporting and logging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Simple error reporting
error_log("PHP Error reporting enabled");

// Debug: Log all requests
error_log("=== Channel Bundle Manager Request Started ===");
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Request URI: " . $_SERVER['REQUEST_URI']);
error_log("POST data: " . print_r($_POST, true));

// Prevent city system interference
if (defined('ION_DYNAMIC_LOADED') || strpos($_SERVER['REQUEST_URI'], 'iondynamic.php') !== false) {
    die('This page cannot be accessed through the city routing system.');
}

// Start output buffering
ob_start();
session_start();

// Include database connection
error_log("Attempting to include database file...");
try {
require_once '../config/database.php';
    error_log("Database file included successfully");
} catch (Exception $e) {
    error_log("Error including database file: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database file error: ' . $e->getMessage()]);
    exit;
} catch (Error $e) {
    error_log("Fatal error including database file: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fatal database file error: ' . $e->getMessage()]);
    exit;
}

// Get PDO connection
try {
    global $db;
$pdo = $db->getPDO();
    error_log("Database connection obtained successfully");
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection error: ' . $e->getMessage()]);
    exit;
}

// Test database connection
try {
    $test_query = $pdo->query("SELECT 1");
    error_log("Database connection successful");
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection error: ' . $e->getMessage()]);
    exit;
}

// Check admin access
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['Admin', 'Owner'])) {
    header('Location: ../login/');
    exit;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    error_log("AJAX request received: " . $_POST['action']);
    ob_start(); // Start output buffering to prevent warnings from corrupting JSON
    ob_clean();
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'create_bundle':
                $result = create_bundle($_POST);
                break;
            case 'get_bundles':
                $result = get_bundles($_POST);
                break;
            case 'update_bundle':
                $result = update_bundle($_POST);
                break;
            case 'delete_bundle':
                $result = delete_bundle($_POST);
                break;
            case 'get_channels':
                try {
                $result = get_channels($_POST);
                } catch (Exception $e) {
                    error_log("AJAX Error in get_channels: " . $e->getMessage());
                    $result = [
                        'success' => false,
                        'channels' => [],
                        'message' => 'Server error: ' . $e->getMessage(),
                        'debug' => [
                            'error' => $e->getMessage(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine()
                        ]
                    ];
                }
                break;
            case 'bulk_add_channels':
                $result = bulk_add_channels($_POST);
                break;
            case 'get_bundles_by_group':
                $result = get_bundles_by_group($_POST);
                break;
            case 'get_channel_groups':
                $result = get_channel_groups($_POST);
                break;
            case 'test':
                $result = ['success' => true, 'message' => 'AJAX is working!', 'data' => $_POST];
                break;
            case 'test_db':
                try {
                    $stmt = $pdo->query('SELECT COUNT(*) as count FROM IONLocalNetwork');
                    $count = $stmt->fetch(PDO::FETCH_ASSOC);
                    $result = ['success' => true, 'message' => 'Database connected!', 'count' => $count['count']];
                } catch (Exception $e) {
                    $result = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
                }
                break;
            default:
                $result = ['success' => false, 'message' => 'Invalid action'];
        }
        ob_end_clean(); // Clean any output before sending JSON
        echo json_encode($result);
    } catch (Exception $e) {
        ob_end_clean(); // Clean any output before sending JSON
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

/**
 * Create a new channel bundle
 */
function create_bundle($data) {
    global $pdo;
    
    $bundle_name = trim($data['bundle_name'] ?? '');
    $bundle_slug = trim($data['bundle_slug'] ?? '');
    $description = trim($data['description'] ?? '');
    $price = floatval($data['price'] ?? 0);
    $price_90 = floatval($data['price_90'] ?? 0);
    $price_180 = floatval($data['price_180'] ?? 0);
    $price_365 = floatval($data['price_365'] ?? 0);
    $currency = trim($data['currency'] ?? 'USD');
    $channel_group = trim($data['channel_group'] ?? 'General');
    $image_url = trim($data['image_url'] ?? '');
    $sort_order = intval($data['sort_order'] ?? 0);
    $categories = json_decode($data['categories'] ?? '[]', true);
    $channels = json_decode($data['channels'] ?? '[]', true);
    
    // Auto-generate image URL if not provided
    if (empty($image_url) && !empty($bundle_slug)) {
        $image_url = "https://avenuei.com/cdn/shop/products/avenuei-{$bundle_slug}-distribution.png";
    }
    
    if (empty($bundle_name) || empty($bundle_slug)) {
        return ['success' => false, 'message' => 'Bundle name and slug are required'];
    }
    
    // Validate slug format
    if (!preg_match('/^[a-z0-9-]+$/', $bundle_slug)) {
        return ['success' => false, 'message' => 'Bundle slug must contain only lowercase letters, numbers, and hyphens'];
    }
    
    try {
        $pdo->beginTransaction();
        
        // Insert bundle
        $stmt = $pdo->prepare("
            INSERT INTO IONLocalBundles (
                bundle_name, bundle_slug, description, price, price_90, price_180, price_365, currency,
                channel_group, image_url, sort_order,
                channel_count, channels, categories, status, created_by
            ) VALUES (
                :bundle_name, :bundle_slug, :description, :price, :price_90, :price_180, :price_365, :currency,
                :channel_group, :image_url, :sort_order,
                :channel_count, :channels, :categories, 'active', :created_by
            )
        ");
        
        $stmt->execute([
            ':bundle_name' => $bundle_name,
            ':bundle_slug' => $bundle_slug,
            ':description' => $description,
            ':price' => $price,
            ':price_90' => $price_90,
            ':price_180' => $price_180,
            ':price_365' => $price_365,
            ':currency' => $currency,
            ':channel_group' => $channel_group,
            ':image_url' => $image_url,
            ':sort_order' => $sort_order,
            ':channel_count' => count($channels),
            ':channels' => json_encode($channels),
            ':categories' => json_encode($categories),
            ':created_by' => $_SESSION['username'] ?? 'admin'
        ]);
        
        $bundle_id = $pdo->lastInsertId();
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Bundle created successfully',
            'bundle_id' => $bundle_id
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Error creating bundle: ' . $e->getMessage()];
    }
}

/**
 * Get all bundles with pagination
 */
function get_bundles($data) {
    global $pdo;
    
    try {
        error_log("get_bundles called with data: " . print_r($data, true));
        
        $page = intval($data['page'] ?? 1);
        $limit = intval($data['limit'] ?? 20);
        $search = trim($data['search'] ?? '');
        $offset = ($page - 1) * $limit;
        
        $group = trim($data['group'] ?? '');
        
        $sql = "SELECT * FROM IONLocalBundles WHERE 1=1";
        $params = [];
        
        if (!empty($search)) {
            $sql .= " AND (bundle_name LIKE :search OR description LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }
        
        if (!empty($group)) {
            $sql .= " AND channel_group = :group";
            $params[':group'] = $group;
        }
        
        $sql .= " ORDER BY sort_order ASC, created_at DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $bundles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get total count
        $count_sql = "SELECT COUNT(*) FROM IONLocalBundles WHERE 1=1";
        if (!empty($search)) {
            $count_sql .= " AND (bundle_name LIKE :search OR description LIKE :search)";
        }
        
        $count_stmt = $pdo->prepare($count_sql);
        foreach ($params as $key => $value) {
            $count_stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $count_stmt->execute();
        $total = $count_stmt->fetchColumn();
        
        return [
            'success' => true,
            'bundles' => $bundles,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ];
    } catch (Exception $e) {
        error_log("Error in get_bundles: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ];
    }
}


/**
 * Get coordinates for a zip/postal code using a geocoding service
 */
function get_coordinates_for_zip($zip_code) {
    try {
        global $db;
        $pdo = $db->getPDO();
        
        // Check if IONGeoCodes table exists and has data
        $table_check = $pdo->query("SHOW TABLES LIKE 'IONGeoCodes'")->fetchAll();
        if (empty($table_check)) {
            error_log("IONGeoCodes table does not exist");
            return null;
        }
        
        $count_check = $pdo->query("SELECT COUNT(*) FROM IONGeoCodes")->fetchColumn();
        error_log("IONGeoCodes table has $count_check records");
        
        // Query IONGeoCodes table directly
        $sql = "SELECT geo_point, official_usps_city_name, official_state_name, zip_code FROM IONGeoCodes WHERE zip_code = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$zip_code]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Debug: Log what we found
        error_log("Zip code search for '$zip_code' - Found: " . ($result ? 'YES' : 'NO'));
        if ($result) {
            error_log("Zip code result: " . json_encode($result));
        } else {
            // Let's see what zip codes are actually in the table
            $debug_sql = "SELECT zip_code, official_usps_city_name FROM IONGeoCodes WHERE zip_code LIKE ? LIMIT 5";
            $debug_stmt = $pdo->prepare($debug_sql);
            $debug_stmt->execute(['%' . $zip_code . '%']);
            $debug_results = $debug_stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Similar zip codes found: " . json_encode($debug_results));
            
            // Try a broader search for New York area zip codes
            if ($zip_code === '10101') {
                $ny_sql = "SELECT zip_code, official_usps_city_name, official_state_name FROM IONGeoCodes WHERE official_usps_city_name LIKE '%New York%' OR official_usps_city_name LIKE '%Manhattan%' LIMIT 10";
                $ny_stmt = $pdo->prepare($ny_sql);
                $ny_stmt->execute();
                $ny_results = $ny_stmt->fetchAll(PDO::FETCH_ASSOC);
                error_log("New York area zip codes: " . json_encode($ny_results));
            }
        }
        
        if ($result && !empty($result['geo_point'])) {
            // Parse geo_point format: "33.03425, -96.89673"
            $coords = explode(', ', $result['geo_point']);
            if (count($coords) === 2) {
                $coords = [
                    'lat' => floatval(trim($coords[0])),
                    'lng' => floatval(trim($coords[1]))
                ];
                error_log("Zip code '$zip_code' found in IONGeoCodes: " . json_encode($coords) . " ({$result['official_usps_city_name']}, {$result['official_state_name']})");
                return $coords;
            } else {
                error_log("Invalid geo_point format for zip '$zip_code': {$result['geo_point']}");
                return null;
            }
        } else {
            // Fallback: Try to find a nearby zip code in the same area
            error_log("Zip code '$zip_code' not found in IONGeoCodes table, trying fallback search");
            
            // For New York area, try to find any Manhattan zip code
            if ($zip_code === '10101') {
                $fallback_sql = "SELECT geo_point, official_usps_city_name, official_state_name FROM IONGeoCodes WHERE zip_code LIKE '10%' AND (official_usps_city_name LIKE '%New York%' OR official_usps_city_name LIKE '%Manhattan%') LIMIT 1";
                $fallback_stmt = $pdo->prepare($fallback_sql);
                $fallback_stmt->execute();
                $fallback_result = $fallback_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($fallback_result && !empty($fallback_result['geo_point'])) {
                    $coords = explode(', ', $fallback_result['geo_point']);
                    if (count($coords) === 2) {
                        $coords = [
                            'lat' => floatval(trim($coords[0])),
                            'lng' => floatval(trim($coords[1]))
                        ];
                        error_log("Fallback zip code found for '$zip_code': " . json_encode($coords) . " ({$fallback_result['official_usps_city_name']}, {$fallback_result['official_state_name']})");
                        return $coords;
                    }
                }
            }
            
            // General fallback: Try to find any zip code with similar prefix
            $prefix = substr($zip_code, 0, 3);
            $fallback_sql = "SELECT geo_point, official_usps_city_name, official_state_name, zip_code FROM IONGeoCodes WHERE zip_code LIKE ? LIMIT 1";
            $fallback_stmt = $pdo->prepare($fallback_sql);
            $fallback_stmt->execute([$prefix . '%']);
            $fallback_result = $fallback_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($fallback_result && !empty($fallback_result['geo_point'])) {
                $coords = explode(', ', $fallback_result['geo_point']);
                if (count($coords) === 2) {
                    $coords = [
                        'lat' => floatval(trim($coords[0])),
                        'lng' => floatval(trim($coords[1]))
                    ];
                    error_log("General fallback zip code found for '$zip_code': " . json_encode($coords) . " ({$fallback_result['official_usps_city_name']}, {$fallback_result['official_state_name']}) - using zip {$fallback_result['zip_code']}");
                    return $coords;
                }
            }
            
            error_log("No fallback found for zip code '$zip_code'");
            return null;
        }
        
    } catch (Exception $e) {
        error_log("Error looking up zip code '$zip_code': " . $e->getMessage());
        return null;
    }
}

/**
 * Find city coordinates by searching the zipcodes table
 */
function find_city_coordinates($city_name) {
    try {
        global $db;
        $pdo = $db->getPDO();
        
        // Search for city name in IONGeoCodes table - prioritize exact matches
        $sql = "SELECT geo_point, official_usps_city_name, official_state_name, population FROM IONGeoCodes WHERE official_usps_city_name LIKE ? ORDER BY 
                CASE 
                    WHEN official_usps_city_name = ? THEN 1
                    WHEN official_usps_city_name LIKE ? THEN 2
                    ELSE 3
                END, population DESC LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['%' . $city_name . '%', $city_name, $city_name . '%']);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && !empty($result['geo_point'])) {
            // Parse geo_point format: "33.03425, -96.89673"
            $coords = explode(', ', $result['geo_point']);
            if (count($coords) === 2) {
                $coords = [
                    'lat' => floatval(trim($coords[0])),
                    'lng' => floatval(trim($coords[1]))
                ];
                error_log("City '$city_name' found in IONGeoCodes: " . json_encode($coords) . " ({$result['official_usps_city_name']}, {$result['official_state_name']})");
                return $coords;
            } else {
                error_log("Invalid geo_point format for city '$city_name': {$result['geo_point']}");
                return null;
            }
        } else {
            error_log("City '$city_name' not found in IONGeoCodes table");
            return null;
        }
        
    } catch (Exception $e) {
        error_log("Error looking up city '$city_name': " . $e->getMessage());
        return null;
    }
}

/**
 * Calculate distance between two coordinates using Haversine formula
 */
function calculate_distance($lat1, $lng1, $lat2, $lng2) {
    $earth_radius = 3959; // Earth's radius in miles
    
    $d_lat = deg2rad($lat2 - $lat1);
    $d_lng = deg2rad($lng2 - $lng1);
    
    $a = sin($d_lat/2) * sin($d_lat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($d_lng/2) * sin($d_lng/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return $earth_radius * $c;
}

/**
 * Get available channels for selection
 */
function get_channels($data) {
    global $pdo;
    
    try {
        error_log("get_channels called with data: " . print_r($data, true));
        
        $search = trim($data['search'] ?? '');
        $limit = intval($data['limit'] ?? 100);
        
        // First, let's check if the table has any data
        $count_sql = "SELECT COUNT(*) FROM IONLocalNetwork";
        $count_stmt = $pdo->prepare($count_sql);
        $count_stmt->execute();
        $total_count = $count_stmt->fetchColumn();
        
        error_log("Total channels in database: " . $total_count);
        
        if ($total_count == 0) {
            return [
                'success' => false,
                'message' => 'No channels found in database. Please add some channels first.'
            ];
        }
        
        $sql = "SELECT slug, city_name, channel_name, population, state_name, state_code, country_name, country_code, latitude, longitude FROM IONLocalNetwork WHERE slug IS NOT NULL AND city_name IS NOT NULL";
        $param_values = [];
        
        $zip_coords = null; // Initialize zip coordinates variable
        if (!empty($search)) {
            // Check if search term looks like a zip/postal code (4-6 digits)
            $is_zip_4 = preg_match('/^\d{4}$/', $search);
            $is_zip_5 = preg_match('/^\d{5}(-\d{4})?$/', $search);
            $is_zip_6 = preg_match('/^\d{6}$/', $search);
            $is_zip_code = $is_zip_4 || $is_zip_5 || $is_zip_6;
            
            // Check if search term looks like a city name (for nearby city search)
            $is_city_search = !$is_zip_code && (strlen($search) >= 3);
            
            error_log("Zip code detection - Search: '$search', 4-digit match: " . ($is_zip_4 ? 'true' : 'false') . ", 5-digit match: " . ($is_zip_5 ? 'true' : 'false') . ", 6-digit match: " . ($is_zip_6 ? 'true' : 'false') . ", Is zip: " . ($is_zip_code ? 'true' : 'false'));
            
            if ($is_zip_code) {
                error_log("ZIP CODE DETECTED: $search");
            } else {
                error_log("NOT DETECTED AS ZIP CODE: $search");
            }
            
            if ($is_zip_code) {
                // This is a zip/postal code - implement location-based search
                $zip_coords = get_coordinates_for_zip($search);
                error_log("Zip coordinates lookup for '$search': " . ($zip_coords ? json_encode($zip_coords) : 'null'));
                
                // If no exact match and it's a partial zip (4 digits), try to find a matching 5-digit zip
                if (!$zip_coords && $is_zip_4) {
                    error_log("No exact match for 4-digit zip '$search', trying to find 5-digit match...");
                    $zip_coords = get_coordinates_for_zip($search . '0'); // Try adding a 0
                    if (!$zip_coords) {
                        $zip_coords = get_coordinates_for_zip($search . '1'); // Try adding a 1
                    }
                    if (!$zip_coords) {
                        $zip_coords = get_coordinates_for_zip($search . '2'); // Try adding a 2
                    }
                    error_log("Partial zip fallback result: " . ($zip_coords ? json_encode($zip_coords) : 'no match found'));
                }
                
                if ($zip_coords) {
                    // For zip code search, get ALL channels with coordinates first
                    // We'll filter by distance after fetching
                    $sql .= " AND latitude IS NOT NULL AND longitude IS NOT NULL AND latitude != '' AND longitude != ''";
                    error_log("Zip code search: Getting all channels with coordinates for distance filtering");
                    // No LIKE parameters needed for zip code search with coordinates
                    // We'll add the LIMIT parameter later
                } else {
                    // Fallback to text search if zip code not found in our mapping
                    $sql .= " AND (city_name LIKE ? OR channel_name LIKE ? OR slug LIKE ? OR state_name LIKE ? OR state_code LIKE ? OR country_name LIKE ? OR country_code LIKE ?)";
                    $search_param = '%' . $search . '%';
                    $param_values = array_fill(0, 7, $search_param);
                }
            } elseif ($is_city_search) {
                // City name search - find the city coordinates and search nearby
                error_log("CITY SEARCH DETECTED: $search");
                
                // First, try to find the city in our IONGeoCodes table
                $city_coords = find_city_coordinates($search);
                if ($city_coords) {
                    $zip_coords = $city_coords;
                    error_log("City coordinates found for '$search': " . json_encode($city_coords));
                    $sql .= " AND latitude IS NOT NULL AND longitude IS NOT NULL AND latitude != '' AND longitude != ''";
                } else {
                    error_log("No city coordinates found for '$search'");
                    // Fallback to text search if city not found
                    $sql .= " AND (city_name LIKE ? OR channel_name LIKE ? OR slug LIKE ? OR state_name LIKE ? OR state_code LIKE ? OR country_name LIKE ? OR country_code LIKE ?)";
                    $search_param = '%' . $search . '%';
                    $param_values = array_fill(0, 7, $search_param);
                }
            } else {
                // Regular text search including state and country fields
                $sql .= " AND (city_name LIKE ? OR channel_name LIKE ? OR slug LIKE ? OR state_name LIKE ? OR state_code LIKE ? OR country_name LIKE ? OR country_code LIKE ?)";
                $search_param = '%' . $search . '%';
                $param_values = array_fill(0, 7, $search_param);
            }
        }
        
        $sql .= " ORDER BY city_name, channel_name LIMIT ?";
        $param_values[] = $limit;
        
        error_log("SQL Query: " . $sql);
        error_log("Parameters: " . print_r($param_values, true));
        error_log("Search term: " . $search);
        error_log("Zip coords: " . ($zip_coords ? json_encode($zip_coords) : 'null'));
        
        $stmt = $pdo->prepare($sql);
        $execute_result = $stmt->execute($param_values);
        error_log("SQL execute result: " . ($execute_result ? 'success' : 'failed'));
        
        $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Channels fetched from database: " . count($channels));
        
        // Debug: Test if there are any channels with coordinates at all
        if ($zip_coords && count($channels) == 0) {
            error_log("No channels found with coordinates. Testing basic coordinate query...");
            $test_sql = "SELECT COUNT(*) FROM IONLocalNetwork WHERE latitude IS NOT NULL AND longitude IS NOT NULL";
            $test_stmt = $pdo->prepare($test_sql);
            $test_stmt->execute();
            $coord_count = $test_stmt->fetchColumn();
            error_log("Total channels with coordinates: " . $coord_count);
        }
        
        // If this was a zip code search, get ALL channels with coordinates and filter by distance
        if (!empty($search) && $zip_coords !== null) {
            error_log("=== ZIP CODE RADIUS SEARCH ===");
            error_log("Search term: " . $search);
            error_log("Zip coordinates: " . json_encode($zip_coords));
            
        // For zip code searches, get ALL channels with coordinates, not just text matches
        $all_channels_sql = "SELECT slug, city_name, channel_name, population, state_name, state_code, country_name, country_code, latitude, longitude FROM IONLocalNetwork WHERE slug IS NOT NULL AND city_name IS NOT NULL AND latitude IS NOT NULL AND longitude IS NOT NULL AND latitude != '' AND longitude != '' ORDER BY city_name, channel_name LIMIT 5000";
        $all_stmt = $pdo->prepare($all_channels_sql);
        $all_stmt->execute();
        $all_channels = $all_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("Total channels with coordinates: " . count($all_channels));
            error_log("Channels before distance filtering: " . count($all_channels));
            
            $filtered_channels = [];
            foreach ($all_channels as $channel) {
                if (!empty($channel['latitude']) && !empty($channel['longitude'])) {
                    $distance = calculate_distance(
                        $zip_coords['lat'], 
                        $zip_coords['lng'], 
                        floatval($channel['latitude']), 
                        floatval($channel['longitude'])
                    );
                    
                    error_log("Channel: " . $channel['city_name'] . " (" . $channel['state_name'] . ") - Distance: " . $distance . " miles");
                    
                    // Add channels within configurable radius (30 miles)
                    $search_radius = 30; // Increased from 20 to 30 miles
                    if ($distance <= $search_radius) {
                        $channel['distance'] = round($distance, 1);
                        $filtered_channels[] = $channel;
                        error_log("✓ Added channel: " . $channel['city_name'] . " (" . $channel['state_name'] . ") - " . $distance . " miles");
                    } else {
                        error_log("✗ Skipped channel: " . $channel['city_name'] . " (" . $channel['state_name'] . ") - " . $distance . " miles (too far)");
                    }
                } else {
                    error_log("Channel " . $channel['city_name'] . " has missing coordinates: lat=" . $channel['latitude'] . ", lng=" . $channel['longitude']);
                }
            }
            
            // Sort by exact city match first, then by distance (closest first)
            usort($filtered_channels, function($a, $b) use ($search) {
                // First, prioritize channels from the exact city being searched
                $a_is_exact_city = stripos($a['city_name'], $search) !== false;
                $b_is_exact_city = stripos($b['city_name'], $search) !== false;
                
                if ($a_is_exact_city && !$b_is_exact_city) return -1;
                if (!$a_is_exact_city && $b_is_exact_city) return 1;
                
                // If both or neither are exact city matches, sort by distance
                return $a['distance'] <=> $b['distance'];
            });
            
            error_log("=== RADIUS SEARCH COMPLETE ===");
            error_log("Channels after distance filtering: " . count($filtered_channels));
            error_log("Search radius used: 30 miles");
            
            // Debug: Show the closest channels found
            if (count($filtered_channels) > 0) {
                error_log("Closest channels found:");
                for ($i = 0; $i < min(5, count($filtered_channels)); $i++) {
                    $ch = $filtered_channels[$i];
                    error_log("  " . ($i+1) . ". " . $ch['city_name'] . " (" . $ch['state_name'] . ") - " . $ch['distance'] . " miles");
                }
            } else {
                error_log("No channels found within 30 miles of zip code coordinates");
            }
            
            $channels = $filtered_channels;
        }
        
        error_log("Channels found: " . count($channels));
        if (count($channels) > 0) {
            error_log("First channel sample: " . print_r($channels[0], true));
        } else {
            error_log("No channels found - this might indicate a database issue");
        }
        
        return [
            'success' => true,
            'channels' => $channels,
            'debug' => [
                'total_count' => $total_count,
                'channels_returned' => count($channels),
                'search_term' => $search,
                'limit' => $limit,
                'zip_coords' => $zip_coords,
                'is_zip_code' => isset($is_zip_code) ? $is_zip_code : false,
                'sql_query' => $sql,
                'param_count' => count($param_values),
                'zip_codes_loaded_count' => 0, // Not applicable for database approach
                'zip_codes_file_exists' => file_exists(__DIR__ . '/zipcodes.php'),
                'zip_codes_approach' => 'database_table'
            ]
        ];
    } catch (Exception $e) {
        error_log("Error in get_channels: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return [
            'success' => false,
            'channels' => [],
            'message' => 'Database error: ' . $e->getMessage(),
            'debug' => [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]
        ];
    }
}

/**
 * Bulk add channels to a bundle (for large bundles)
 */
function bulk_add_channels($data) {
    global $pdo;
    
    $bundle_id = intval($data['bundle_id'] ?? 0);
    $channels = json_decode($data['channels'] ?? '[]', true);
    
    if ($bundle_id <= 0 || empty($channels)) {
        return ['success' => false, 'message' => 'Bundle ID and channels are required'];
    }
    
    try {
        $pdo->beginTransaction();
        
        // Get current bundle
        $stmt = $pdo->prepare("SELECT channels, channel_count FROM IONLocalBundles WHERE id = :id");
        $stmt->execute([':id' => $bundle_id]);
        $bundle = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$bundle) {
            throw new Exception('Bundle not found');
        }
        
        $current_channels = json_decode($bundle['channels'], true) ?: [];
        $new_channels = array_unique(array_merge($current_channels, $channels));
        
        // Update bundle
        $update_stmt = $pdo->prepare("
            UPDATE IONLocalBundles 
            SET channels = :channels, channel_count = :channel_count, updated_at = NOW()
            WHERE id = :id
        ");
        
        $update_stmt->execute([
            ':channels' => json_encode($new_channels),
            ':channel_count' => count($new_channels),
            ':id' => $bundle_id
        ]);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Channels added successfully',
            'added_count' => count($channels),
            'total_channels' => count($new_channels)
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Error adding channels: ' . $e->getMessage()];
    }
}

/**
 * Update an existing bundle
 */
function update_bundle($data) {
    global $pdo;
    
    $bundle_id = intval($data['bundle_id'] ?? 0);
    $bundle_name = trim($data['bundle_name'] ?? '');
    $description = trim($data['description'] ?? '');
    $price = floatval($data['price'] ?? 0);
    $channels = json_decode($data['channels'] ?? '[]', true);
    $categories = json_decode($data['categories'] ?? '[]', true);
    
    if ($bundle_id <= 0) {
        return ['success' => false, 'message' => 'Bundle ID is required'];
    }
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            UPDATE IONLocalBundles 
            SET bundle_name = :bundle_name, description = :description, price = :price,
                channels = :channels, channel_count = :channel_count, 
                categories = :categories, updated_at = NOW()
            WHERE id = :id
        ");
        
        $stmt->execute([
            ':bundle_name' => $bundle_name,
            ':description' => $description,
            ':price' => $price,
            ':channels' => json_encode($channels),
            ':channel_count' => count($channels),
            ':categories' => json_encode($categories),
            ':id' => $bundle_id
        ]);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Bundle updated successfully'
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Error updating bundle: ' . $e->getMessage()];
    }
}

/**
 * Delete a bundle
 */
function delete_bundle($data) {
    global $pdo;
    
    $bundle_id = intval($data['bundle_id'] ?? 0);
    
    if ($bundle_id <= 0) {
        return ['success' => false, 'message' => 'Bundle ID is required'];
    }
    
    try {
        // Soft delete: set deleted_at timestamp instead of removing record
        $stmt = $pdo->prepare("UPDATE IONLocalBundles SET deleted_at = NOW() WHERE id = :id");
        $stmt->execute([':id' => $bundle_id]);
        
        if ($stmt->rowCount() > 0) {
            return [
                'success' => true,
                'message' => 'Bundle deleted successfully'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Bundle not found or already deleted'
            ];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error deleting bundle: ' . $e->getMessage()];
    }
}

/**
 * Get bundles grouped by category
 */
function get_bundles_by_group($data) {
    global $pdo;
    
    try {
        $sql = "SELECT channel_group, 
                       COUNT(*) as bundle_count,
                       GROUP_CONCAT(CONCAT('{\"id\":', id, ',\"name\":\"', bundle_name, '\",\"slug\":\"', bundle_slug, '\",\"image\":\"', COALESCE(image_url, ''), '\",\"channels\":', channel_count, '}') SEPARATOR ',') as bundles
                FROM IONLocalBundles 
                WHERE status = 'active' AND deleted_at IS NULL
                GROUP BY channel_group 
                ORDER BY MIN(sort_order) ASC, channel_group ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        
        $groups = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $bundles = json_decode('[' . $row['bundles'] . ']', true);
            $groups[] = [
                'group_name' => $row['channel_group'],
                'bundle_count' => intval($row['bundle_count']),
                'bundles' => $bundles
            ];
        }
        
        return [
            'success' => true,
            'groups' => $groups
        ];
        
    } catch (Exception $e) {
        error_log("Error in get_bundles_by_group: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ];
    }
}

/**
 * Get available channel groups
 */
function get_channel_groups($data) {
    global $pdo;
    
    try {
        $sql = "SELECT DISTINCT channel_group, COUNT(*) as count 
                FROM IONLocalBundles 
                WHERE status = 'active'
                GROUP BY channel_group 
                ORDER BY channel_group ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        
        $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'groups' => $groups
        ];
        
    } catch (Exception $e) {
        error_log("Error in get_channel_groups: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Channel Bundle Manager - ION Platform</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .header h1 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #7f8c8d;
            font-size: 16px;
        }
        
        .main-layout {
            display: grid;
            grid-template-columns: 55% 45%;
            gap: 10px;
            margin-bottom: 30px;
            width: 100%;
            box-sizing: border-box;
        }
        
        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            width: 100%;
            box-sizing: border-box;
            overflow: hidden;
        }
        
        .card h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 1.4em;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .pricing-row {
            grid-template-columns: 1fr 1fr 1fr 1fr auto;
        }
        
        .pricing-row .form-group {
            min-width: 0;
        }
        
        .currency-group {
            min-width: 80px;
            max-width: 100px;
        }
        
        .pricing-row label {
            font-size: 0.9em;
            font-weight: 500;
            color: #2c3e50;
        }
        
        .pricing-row input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9em;
        }
        
        /* Channel Selector Styles */
        .channel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .channel-search-container {
            display: flex;
            align-items: center;
        }
        
        .channel-selector {
            max-height: 300px;
            overflow-y: auto;
            overflow-x: hidden;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
            background: white;
            width: 100%;
            box-sizing: border-box;
        }
        
        .channel-item {
            display: flex;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
            width: 100%;
        }
        
        .channel-item:last-child {
            border-bottom: none;
        }
        
        .channel-checkbox {
            transform: scale(1.5);
            margin-right: 12px;
            flex-shrink: 0;
            width: auto;
        }
        
        .channel-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            overflow: hidden;
        }
        
        .channel-name {
            font-weight: 500;
            color: #2c3e50;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 2px;
        }
        
        .channel-slug {
            font-size: 0.8em;
            color: #7f8c8d;
            font-family: monospace;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .loading {
            text-align: center;
            color: #7f8c8d;
            padding: 20px;
            font-style: italic;
        }
        
        .error {
            text-align: center;
            color: #e74c3c;
            padding: 20px;
            background: #fdf2f2;
            border: 1px solid #fecaca;
            border-radius: 4px;
        }
        
        /* Selected Channels Styles */
        .selected-channels {
            margin-top: 10px;
            min-height: 40px;
        }
        
        .selected-channel {
            display: inline-block;
            background: #e3f2fd;
            color: #1565c0;
            padding: 6px 12px;
            margin: 4px 6px 4px 0;
            border-radius: 16px;
            font-size: 0.9em;
            position: relative;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .selected-channel:hover {
            background: #bbdefb;
        }
        
        .remove-channel {
            margin-left: 8px;
            font-weight: bold;
            font-size: 1.2em;
            color: #e74c3c;
            cursor: pointer;
            opacity: 0.7;
            transition: opacity 0.2s ease;
        }
        
        .remove-channel:hover {
            opacity: 1;
        }
        
        .selected-channels-actions {
            margin-top: 10px;
            text-align: right;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8em;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn:hover {
            background: #2980b9;
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: #95a5a6;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        .btn-danger {
            background: #e74c3c;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .btn-success {
            background: #27ae60;
        }
        
        .btn-success:hover {
            background: #229954;
        }
        
        .channel-selector {
            max-height: 300px;
            overflow-y: auto;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            padding: 10px;
        }
        
        .channel-item {
            display: grid;
            grid-template-columns: auto 1fr 120px 80px auto;
            align-items: center;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 5px;
            cursor: pointer;
            transition: background 0.2s ease;
            gap: 15px;
        }
        
        .channel-item:hover {
            background: #f8f9fa;
        }
        
        .channel-item.selected {
            background: #3498db;
            color: white;
        }
        
        .channel-item input[type="checkbox"] {
            margin-right: 10px;
        }
        
        .selected-channels {
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .selected-channel {
            display: inline-block;
            background: #3498db;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            margin: 2px;
            font-size: 12px;
        }
        
        .bundle-list {
            height: calc(100vh - 400px);
            min-height: 90%;
            overflow-y: auto;
        }
        
        .bundle-item {
            border: 1px solid #e1e8ed;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        
        .bundle-item:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .bundle-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .bundle-name {
            font-weight: 600;
            color: #2c3e50;
            font-size: 16px;
        }
        
        .bundle-meta {
            color: #7f8c8d;
            font-size: 14px;
        }
        
        .pricing-tiers {
            font-weight: 500;
            color: #27ae60;
        }
        
        .bundle-actions {
            display: flex;
            gap: 10px;
            position: relative;
        }
        
        .bundle-actions .btn {
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s ease;
        }
        
        .bundle-actions:hover .btn {
            opacity: 1;
            visibility: visible;
        }
        
        .bundle-actions:hover .delete-trashcan {
            opacity: 1;
            visibility: visible;
        }
        
        .delete-trashcan {
            position: absolute;
            top: 30px;
            left: 50%;
            transform: translateX(-50%);
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 6px 8px;
            font-size: 12px;
            cursor: pointer;
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s ease;
            z-index: 10;
        }
        
        .delete-trashcan:hover {
            background: #dc3545;
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }
        
        .pagination button {
            padding: 8px 12px;
            border: 1px solid #e1e8ed;
            background: white;
            cursor: pointer;
            border-radius: 4px;
        }
        
        .pagination button:hover {
            background: #f8f9fa;
        }
        
        .pagination button.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        .loading {
            text-align: center;
            padding: 20px;
            color: #7f8c8d;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        
        .bulk-upload {
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 2px dashed #e1e8ed;
        }
        
        .file-input {
            margin-bottom: 15px;
        }
        
        .file-input input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #e1e8ed;
            border-radius: 4px;
        }
        
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #e1e8ed;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }
        
        .progress-fill {
            height: 100%;
            background: #3498db;
            transition: width 0.3s ease;
        }
        
        @media (max-width: 768px) {
            .main-layout {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .bundle-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
        
        /* Bundle Grouping Styles */
        .bundle-group {
            margin-bottom: 2rem;
        }
        
        .group-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #3498db;
        }
        
        .bundle-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
        }
        
        .bundle-card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 0;
            margin-bottom: 12px;
            display: flex;
            overflow: hidden;
            height: 100px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .bundle-card:hover {
            border-color: #3498db;
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.2);
        }
        
        .bundle-image {
            width: 100px;
            height: 100px;
            flex-shrink: 0;
            overflow: hidden;
            background: #f8f9fa;
        }
        
        .bundle-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .bundle-content {
            flex: 1;
            padding: 8px 12px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100px;
        }
        
        .bundle-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 4px;
        }
        
        .bundle-info {
            flex: 1;
        }
        
        .bundle-actions {
            display: flex;
            gap: 8px;
            margin-left: 10px;
        }
        
        .bundle-pricing {
            margin: 0px 0;
        }
        
        .pricing-tiers {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            align-items: center;
        }
        
        .price-tier {
            font-size: 0.8em;
            color: #27ae60;
            font-weight: 500;
        }
        
        .currency {
            font-size: 0.8em;
            color: #7f8c8d;
            margin-left: 4px;
        }
        
        .bundle-channels {
            font-size: 0.8em;
            color: #666;
            margin-top: 2px;
            line-height: 1.2;
        }
        
        .bundle-name {
            font-size: 1em;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 2px;
            line-height: 1.2;
        }
        
        .bundle-description {
            color: #666;
            margin-bottom: 2px;
            line-height: 1.2;
            font-size: 0.8em;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .bundle-meta {
            gap: 2px;
            margin-bottom: 0px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .bundle-count {
            background: #e3f2fd;
            color: #1565c0;
            padding: 1px 4px;
            border-radius: 6px;
            font-size: 0.7em;
            font-weight: 500;
        }
        
        .bundle-group {
            background: #ffffff;
            color: #7b1fa2;
            padding: 1px 4px;
            border-radius: 6px;
            font-size: 0.7em;
            font-weight: 500;
        }
        
        /* Channel Selection Styles */
        .channel-item {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s ease;
        }
        
        .channel-item:hover {
            background-color: #f8f9fa;
        }
        
        .channel-checkbox {
            margin-right: 12px;
            transform: scale(1.5);
            cursor: pointer;
            width: auto !important;
            flex-shrink: 0;
        }
        
        .channel-info {
            min-width: 0;
        }
        
        .channel-name {
            font-weight: 500;
            color: #333;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .channel-slug {
            font-size: 0.85em;
            color: #666;
            font-family: monospace;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .channel-location {
            min-width: 120px;
        }
        
        .location-state {
            font-size: 0.9em;
            color: #333;
            font-weight: 500;
            line-height: 1.2;
        }
        
        .location-country {
            font-size: 0.8em;
            color: #666;
            line-height: 1.2;
        }
        
        
        .channel-population {
            min-width: 80px;
            text-align: center;
        }
        
        .population-value {
            font-size: 0.9em;
            color: #333;
            font-weight: 600;
        }
        
        .channel-actions {
            display: flex;
            align-items: center;
            margin-left: 8px;
        }
        
        .channel-link-icon {
            display: inline-block;
            width: 20px;
            height: 20px;
            text-align: center;
            line-height: 20px;
            font-size: 14px;
            text-decoration: none;
            color: #007cba;
            border: 1px solid #007cba;
            border-radius: 3px;
            transition: all 0.2s ease;
        }
        
        .channel-link-icon:hover {
            background-color: #007cba;
            color: white;
            transform: scale(1.1);
        }
        
        
        
        
        
        .help-text {
            font-size: 0.8rem;
            color: #7f8c8d;
            margin-top: 0.25rem;
        }
        
        /* Bundle Header Section */
        .bundle-header-section {
            position: relative;
            margin-bottom: 1.5rem;
        }
        
        .bundle-header-section h2 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            position: relative;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #3498db;
            width: 100%;
        }
        
        .bundle-counter {
            background: #3498db;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        /* Search Container */
        .search-container {
            position: absolute;
            top: 0;
            right: 0;
            display: flex;
            align-items: center;
        }
        
        .search-field {
            display: flex;
            align-items: center;
            background: white;
            border: 2px solid #e1e8ed;
            border-radius: 25px;
            padding: 0.1rem 0.5rem;
            transition: all 0.3s ease;
            width: 150px;
        }
        
        .search-field:focus-within {
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        .search-field input {
            border: none;
            outline: none;
            background: transparent;
            flex: 1;
            padding: 0.25rem 0.5rem;
            font-size: 0.9rem;
        }
        
        .search-field input::placeholder {
            color: #95a5a6;
        }
        
        .search-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #7f8c8d;
            transition: color 0.2s ease;
        }
        
        .search-btn:hover {
            color: #3498db;
        }
        
        .search-icon {
            font-size: 1rem;
        }
        
        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e1e8ed;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            max-height: 300px;
            overflow-y: auto;
            margin-top: 0.5rem;
        }
        
        .search-result-item {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #f1f3f4;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        
        .search-result-item:hover {
            background-color: #f8f9fa;
        }
        
        .search-result-item:last-child {
            border-bottom: none;
        }
        
        .search-result-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }
        
        .search-result-meta {
            font-size: 0.8rem;
            color: #7f8c8d;
        }
        
        .search-result-name mark {
            background: #fff3cd;
            color: #856404;
            padding: 0.1rem 0.2rem;
            border-radius: 3px;
        }
        
        @media (max-width: 768px) {
            .bundle-grid {
                grid-template-columns: 1fr;
            }
            
            .bundle-card {
                margin-bottom: 1rem;
            }
            
            .bundle-header-section {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .search-field {
                min-width: 100%;
            }
            
            .pricing-row {
                grid-template-columns: 1fr 1fr 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .pricing-row {
                grid-template-columns: 1fr;
            }
            
            .channel-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .channel-search-container {
                width: 100%;
            }
            
            .channel-search-container input {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div>
            <h1>Channel Bundle Manager</h1>
            <p>Create and manage channel bundles for video distribution. Supports bundles with thousands of channels.</p>
                </div>
                <button type="button" class="btn btn-success" onclick="document.getElementById('bundleForm').dispatchEvent(new Event('submit'))" style="margin-top: 0;">
                    Create Bundle
                </button>
            </div>
        </div>
        
        <div class="main-layout">
            <!-- Create Bundle Form -->
            <div class="card">
                <h2>Create New Bundle</h2>
                <form id="bundleForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="bundle_name">Bundle Name *</label>
                            <input type="text" id="bundle_name" name="bundle_name" required>
                        </div>
                        <div class="form-group">
                            <label for="bundle_slug">Bundle Slug *</label>
                            <input type="text" id="bundle_slug" name="bundle_slug" required 
                                   placeholder="e.g., major-cities-bundle">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="form-row pricing-row">
                        <div class="form-group">
                            <label for="price">Price: 30 Days</label>
                            <input type="number" id="price" name="price" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="price_90">Price: 90 Days</label>
                            <input type="number" id="price_90" name="price_90" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="price_180">Price: 180 Days</label>
                            <input type="number" id="price_180" name="price_180" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="price_365">Price: 365 Days</label>
                            <input type="number" id="price_365" name="price_365" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="form-group currency-group">
                            <label for="currency">Currency</label>
                            <select id="currency" name="currency">
                                <option value="USD">USD</option>
                                <option value="EUR">EUR</option>
                                <option value="GBP">GBP</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="channel_group">Channel Group *</label>
                            <select id="channel_group" name="channel_group" required>
                                <option value="">Select a group...</option>
                                <option value="Featured Towns">Featured Towns</option>
                                <option value="Featured Regions">Featured Regions</option>
                                <option value="Featured Collaborations">Featured Collaborations</option>
                                <option value="General">General</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="sort_order">Sort Order</label>
                            <input type="number" id="sort_order" name="sort_order" min="0" value="0" style="width: 100px;">
                            <small class="help-text">Lower numbers appear first (0 = highest priority)</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="image_url">Image URL</label>
                        <input type="url" id="image_url" name="image_url" 
                               placeholder="https://avenuei.com/cdn/shop/products/avenuei-ion-alabama-distribution.png"
                               style="width: 100%;">
                        <small class="help-text">Leave empty to auto-generate from bundle slug</small>
                    </div>
                    
                    <div class="form-group">
                        <div class="channel-header">
                            <label>Select Channels</label>
                            <div class="channel-search-container">
                                <input type="text" id="channelSearch" placeholder="Search by name, city, state, country, or zip code..." 
                                       onkeyup="filterChannels()" style="width: 300px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                        </div>
                        <div class="channel-selector" id="channelSelector">
                            <div class="loading">Loading channels...</div>
                        </div>
                        <div class="selected-channels" id="selectedChannels">
                            <p>No channels selected</p>
                        </div>
                        <div class="selected-channels-actions" id="selectedChannelsActions" style="display: none;">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="clearAllChannels()">
                                Clear All Selected
                            </button>
                        </div>
                    </div>
                    
                    <div class="bulk-upload">
                        <h3>Bulk Upload (for large bundles)</h3>
                        <p>Upload a CSV file with channel slugs (one per line) for bundles with hundreds/thousands of channels.</p>
                        <div class="file-input">
                            <input type="file" id="channelFile" accept=".csv,.txt">
                        </div>
                        <button type="button" class="btn btn-secondary" onclick="processBulkUpload()">
                            Process Bulk Upload
                        </button>
                        <div class="progress-bar" id="progressBar" style="display: none;">
                            <div class="progress-fill" id="progressFill"></div>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Bundle List -->
            <div class="card">
                <div class="bundle-header-section">
                    <h2>Existing Bundles <span class="bundle-counter" id="bundleCounter">(0)</span></h2>
                    <div class="search-container">
                        <div class="search-field" id="searchField">
                            <input type="text" id="bundleSearch" placeholder="Search bundles..." 
                                   onkeyup="handleBundleSearch(event)" onblur="hideSearchResults()">
                            <button class="search-btn" onclick="toggleSearch()">
                                <span class="search-icon">🔍</span>
                            </button>
                        </div>
                        <div class="search-results" id="searchResults" style="display: none;"></div>
                    </div>
                </div>
                <div class="bundle-list" id="bundleList">
                    <div class="loading">Loading bundles...</div>
                </div>
                <div class="pagination" id="bundlePagination"></div>
            </div>
        </div>
    </div>

    <script>
        let allChannels = [];
        let selectedChannelSlugs = [];
        let currentPage = 1;
        let totalPages = 1;
        let allBundles = [];
        let filteredBundles = [];
        let searchTimeout = null;
        
        // Load page on startup
        document.addEventListener('DOMContentLoaded', function() {
            testDatabaseConnection();
            showChannelSearchInstructions();
            loadBundles();
        });
        
        // Show channel search instructions
        function showChannelSearchInstructions() {
            const container = document.getElementById('channelSelector');
            container.innerHTML = `
                <div style="text-align: center; padding: 40px 20px; color: #666; background: #f8f9fa; border-radius: 8px; border: 2px dashed #dee2e6;">
                    <div style="font-size: 48px; margin-bottom: 20px;">🔍</div>
                    <h3 style="color: #2c3e50; margin-bottom: 15px;">Search for Channels</h3>
                    <p style="margin-bottom: 20px; line-height: 1.6;">
                        Use the search box above to find channels by:
                    </p>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
                        <div style="padding: 10px; background: white; border-radius: 6px; border-left: 4px solid #3498db;">
                            <strong>📍 Zip Code</strong><br>
                            <small>e.g., 90210, 10001</small>
                        </div>
                        <div style="padding: 10px; background: white; border-radius: 6px; border-left: 4px solid #27ae60;">
                            <strong>🏙️ City Name</strong><br>
                            <small>e.g., Los Angeles, New York</small>
                        </div>
                        <div style="padding: 10px; background: white; border-radius: 6px; border-left: 4px solid #e74c3c;">
                            <strong>🗺️ State/Country</strong><br>
                            <small>e.g., California, Texas, USA</small>
                        </div>
                        <div style="padding: 10px; background: white; border-radius: 6px; border-left: 4px solid #f39c12;">
                            <strong>🏷️ Channel Name</strong><br>
                            <small>e.g., ION Los Angeles</small>
                        </div>
                        <div style="padding: 10px; background: white; border-radius: 6px; border-left: 4px solid #9b59b6;">
                            <strong>🔗 Channel Slug</strong><br>
                            <small>e.g., ion-my-town</small>
                        </div>
                    </div>
                    <p style="font-size: 14px; color: #7f8c8d;">
                        <strong>Zip Code Search:</strong> Find all channels within 20 miles of the entered zip code<br>
                        <strong>Text Search:</strong> Search across city names, states, countries, and channel names
                    </p>
                </div>
            `;
        }
        
        // Test database connection
        async function testDatabaseConnection() {
            try {
                console.log('🔍 Testing database connection...');
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=test_db'
                });
                
                const data = await response.json();
                console.log('🔍 Database test result:', data);
                
                if (data.success) {
                    console.log('✅ Database connected! Channel count:', data.count);
                } else {
                    console.error('❌ Database connection failed:', data.message);
                }
            } catch (error) {
                console.error('❌ Database test error:', error);
            }
        }
        
        // Load channels
        async function loadChannels() {
            try {
                console.log('🔄 Loading channels...');
                document.getElementById('channelSelector').innerHTML = '<div class="loading">Loading channels...</div>';
                
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=get_channels'
                });
                
                console.log('📡 Response status:', response.status);
                console.log('📡 Response headers:', response.headers);
                
                const responseText = await response.text();
                console.log('📄 Channel response length:', responseText.length);
                console.log('📄 Channel response preview:', responseText.substring(0, 1000));
                
                let data;
                try {
                    data = JSON.parse(responseText);
                    console.log('✅ JSON parsed successfully');
                } catch (parseError) {
                    console.error('❌ Channel JSON parse error:', parseError);
                    console.error('❌ Raw response:', responseText);
                    document.getElementById('channelSelector').innerHTML = 
                        '<div class="error">Server returned invalid JSON. Check console for details.<br>Response: ' + responseText.substring(0, 200) + '...</div>';
                    return;
                }
                
                console.log('📊 Channel parsed data:', data);
                
                if (data.success) {
                    allChannels = data.channels || [];
                    console.log('✅ Channels loaded successfully:', allChannels.length);
                    console.log('📋 First few channels:', allChannels.slice(0, 3));
                    
                    if (allChannels.length === 0) {
                        document.getElementById('channelSelector').innerHTML = 
                            '<div class="error">No channels found in database. Please add some channels first.</div>';
                    } else {
                        displayChannels(allChannels);
                    }
                } else {
                    console.error('❌ Failed to load channels:', data.message);
                    document.getElementById('channelSelector').innerHTML = 
                        '<div class="error">Error loading channels: ' + data.message + '</div>';
                }
            } catch (error) {
                console.error('❌ Error loading channels:', error);
                document.getElementById('channelSelector').innerHTML = 
                    '<div class="error">Network error loading channels: ' + error.message + '</div>';
            }
        }
        
        // Display channels
        function displayChannels(channelsToShow = allChannels) {
            const container = document.getElementById('channelSelector');
            
            console.log('🎯 Displaying channels:', channelsToShow.length, 'channels');
            console.log('🎯 Sample channel:', channelsToShow[0]);
            console.log('🎯 All channels:', channelsToShow);
            
            if (channelsToShow.length === 0) {
                container.innerHTML = '<div class="error">No channels available</div>';
                return;
            }
            
            const html = channelsToShow.map((channel, index) => {
                console.log(`🎯 Channel ${index}:`, channel);
                const channelName = channel.channel_name || channel.city_name || 'Unnamed Channel';
                const channelSlug = channel.slug || 'no-slug';
                const population = channel.population ? parseInt(channel.population.replace(/,/g, '')).toLocaleString() : 'N/A';
                
                // Debug: Show raw data
                console.log(`🎯 Channel ${index} processed:`, {
                    original: channel,
                    name: channelName,
                    slug: channelSlug,
                    population: population
                });
                
                const distanceInfo = channel.distance ? ` (${channel.distance} mi)` : '';
                const populationFormatted = population !== 'N/A' ? `P:${population}` : 'P:N/A';
                
                return `
                    <div class="channel-item">
                        <input type="checkbox" 
                               id="channel_${channelSlug}" 
                               value="${channelSlug}" 
                               class="channel-checkbox"
                               onchange="toggleChannel('${channelSlug}')">
                        <div class="channel-info">
                            <div class="channel-name">${channelName}${distanceInfo}</div>
                            <div class="channel-slug">${channelSlug}</div>
                        </div>
                        <div class="channel-location">
                            <div class="location-state">${channel.state_name || ''}</div>
                            <div class="location-country">${channel.country_name || ''}</div>
                        </div>
                        <div class="channel-population">
                            <span class="population-value">${populationFormatted}</span>
                        </div>
                        <div class="channel-actions">
                            <a href="https://ions.com/${channelSlug}" 
                               target="_blank" 
                               class="channel-link-icon" 
                               title="Open channel in new window">
                                🔗
                            </a>
                        </div>
                    </div>
                `;
            }).join('');
            
            console.log('🎯 Generated HTML length:', html.length);
            console.log('🎯 Generated HTML preview:', html.substring(0, 500));
            
            container.innerHTML = html;
        }
        
        // Filter channels based on search
        async function filterChannels() {
            const searchTerm = document.getElementById('channelSearch').value.trim();
            
            // If search is empty, show instructions
            if (searchTerm === '') {
                showChannelSearchInstructions();
                return;
            }
            
            // For all searches, make a server request to get results
            await searchChannelsFromServer(searchTerm);
        }
        
        // Search channels from server (for zip codes and other server-side searches)
        async function searchChannelsFromServer(searchTerm) {
            try {
                console.log('Searching channels from server:', searchTerm);
                document.getElementById('channelSelector').innerHTML = '<div class="loading">Searching channels...</div>';
                
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=get_channels&search=${encodeURIComponent(searchTerm)}&limit=1000`
                });
                
                console.log('Search response status:', response.status);
                const responseText = await response.text();
                console.log('Search response length:', responseText.length);
                console.log('Search response preview:', responseText.substring(0, 500));
                
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('Search JSON parse error:', parseError);
                    console.error('Response text (first 500 chars):', responseText.substring(0, 500));
                    document.getElementById('channelSelector').innerHTML = 
                        '<div class="error">Server returned invalid response. Check console for details.</div>';
                    return;
                }
                
                console.log('Search parsed data:', data);
                
                if (data.success) {
                    displayChannels(data.channels);
                } else {
                    document.getElementById('channelSelector').innerHTML = 
                        '<div class="error">Error searching channels: ' + data.message + '</div>';
                }
            } catch (error) {
                console.error('Error searching channels:', error);
                document.getElementById('channelSelector').innerHTML = 
                    '<div class="error">Error searching channels: ' + error.message + '</div>';
            }
        }
        
        // Toggle channel selection
        function toggleChannel(slug) {
            const checkbox = document.querySelector(`input[value="${slug}"]`);
            
            if (selectedChannelSlugs.includes(slug)) {
                // Remove from selection
                selectedChannelSlugs = selectedChannelSlugs.filter(s => s !== slug);
                if (checkbox) checkbox.checked = false;
            } else {
                // Add to selection
                selectedChannelSlugs.push(slug);
                if (checkbox) checkbox.checked = true;
            }
            updateSelectedChannels();
        }
        
        // Update selected channels display
        function updateSelectedChannels() {
            const container = document.getElementById('selectedChannels');
            const actionsContainer = document.getElementById('selectedChannelsActions');
            
            if (selectedChannelSlugs.length === 0) {
                container.innerHTML = '<p>No channels selected</p>';
                actionsContainer.style.display = 'none';
                return;
            }
            
            const selectedChannels = allChannels.filter(c => selectedChannelSlugs.includes(c.slug));
            container.innerHTML = selectedChannels.map(channel => 
                `<span class="selected-channel" data-slug="${channel.slug}">
                    ${channel.channel_name || channel.slug}
                    <span class="remove-channel" onclick="removeChannel('${channel.slug}')" title="Remove channel">×</span>
                </span>`
            ).join('');
            
            actionsContainer.style.display = 'block';
        }
        
        // Remove channel from selection
        function removeChannel(slug) {
            selectedChannelSlugs = selectedChannelSlugs.filter(s => s !== slug);
            
            // Uncheck the checkbox in the main list
            const checkbox = document.querySelector(`input[value="${slug}"]`);
            if (checkbox) {
                checkbox.checked = false;
            }
            
            updateSelectedChannels();
        }
        
        // Clear all selected channels
        function clearAllChannels() {
            // Uncheck all checkboxes
            selectedChannelSlugs.forEach(slug => {
                const checkbox = document.querySelector(`input[value="${slug}"]`);
                if (checkbox) {
                    checkbox.checked = false;
                }
            });
            
            // Clear the selection array
            selectedChannelSlugs = [];
            updateSelectedChannels();
        }
        
        // Auto-generate slug from name
        document.getElementById('bundle_name').addEventListener('input', function() {
            const name = this.value;
            const slug = name.toLowerCase()
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-')
                .trim('-');
            document.getElementById('bundle_slug').value = slug;
        });
        
        
        // Display channels in selector
        function displayChannels(channelsToShow = null) {
            const container = document.getElementById('channelSelector');
            container.innerHTML = '';
            
            const channels = channelsToShow || allChannels;
            
            if (!channels || channels.length === 0) {
                container.innerHTML = '<div class="loading">No channels available</div>';
                return;
            }
            
            const html = channels.map((channel, index) => {
                const channelName = channel.channel_name || channel.city_name || 'Unnamed Channel';
                const channelSlug = channel.slug || 'no-slug';
                const population = channel.population ? parseInt(channel.population.replace(/,/g, '')).toLocaleString() : 'N/A';
                const distanceInfo = channel.distance ? ` (${channel.distance} mi)` : '';
                const populationFormatted = population !== 'N/A' ? `P:${population}` : 'P:N/A';
                
                return `
                    <div class="channel-item">
                        <input type="checkbox" 
                               id="channel_${channelSlug}" 
                               value="${channelSlug}" 
                               class="channel-checkbox"
                               onchange="toggleChannel('${channelSlug}')">
                    <div class="channel-info">
                            <div class="channel-name">${channelName}${distanceInfo}</div>
                            <div class="channel-slug">${channelSlug}</div>
                        </div>
                        <div class="channel-location">
                            <div class="location-state">${channel.state_name || ''}</div>
                            <div class="location-country">${channel.country_name || ''}</div>
                        </div>
                        <div class="channel-population">
                            <span class="population-value">${populationFormatted}</span>
                        </div>
                        <div class="channel-actions">
                            <a href="https://ions.com/${channelSlug}" 
                               target="_blank" 
                               class="channel-link-icon" 
                               title="Open channel in new window">
                                🔗
                            </a>
                        </div>
                    </div>
                `;
            }).join('');
            
            container.innerHTML = html;
        }
        
        
        // Toggle channel selection
        function toggleChannel(slug) {
            const checkbox = document.querySelector(`input[value="${slug}"]`);
            
            if (selectedChannelSlugs.includes(slug)) {
                // Remove from selection
                selectedChannelSlugs = selectedChannelSlugs.filter(s => s !== slug);
                if (checkbox) checkbox.checked = false;
            } else {
                // Add to selection
                selectedChannelSlugs.push(slug);
                if (checkbox) checkbox.checked = true;
            }
            updateSelectedChannels();
        }
        
        // Update selected channels display
        function updateSelectedChannels() {
            const container = document.getElementById('selectedChannels');
            const actionsContainer = document.getElementById('selectedChannelsActions');
            
            if (selectedChannelSlugs.length === 0) {
                container.innerHTML = '<p>No channels selected</p>';
                actionsContainer.style.display = 'none';
                return;
            }
            
            const selectedChannels = allChannels.filter(c => selectedChannelSlugs.includes(c.slug));
            container.innerHTML = selectedChannels.map(channel => 
                `<span class="selected-channel" data-slug="${channel.slug}">
                    ${channel.channel_name}
                    <span class="remove-channel" onclick="removeChannel('${channel.slug}')" title="Remove channel">×</span>
                </span>`
            ).join('');
            
            actionsContainer.style.display = 'block';
        }
        
        // Remove channel from selection
        function removeChannel(slug) {
            selectedChannelSlugs = selectedChannelSlugs.filter(s => s !== slug);
            
            // Uncheck the checkbox in the main list
            const checkbox = document.querySelector(`input[value="${slug}"]`);
            if (checkbox) {
                checkbox.checked = false;
            }
            
            updateSelectedChannels();
        }
        
        // Clear all selected channels
        function clearAllChannels() {
            // Uncheck all checkboxes
            selectedChannelSlugs.forEach(slug => {
                const checkbox = document.querySelector(`input[value="${slug}"]`);
                if (checkbox) {
                    checkbox.checked = false;
                }
            });
            
            // Clear the selection array
            selectedChannelSlugs = [];
            updateSelectedChannels();
        }
        
        // Process bulk upload
        function processBulkUpload() {
            const fileInput = document.getElementById('channelFile');
            const file = fileInput.files[0];
            
            if (!file) {
                alert('Please select a file');
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                const content = e.target.result;
                const lines = content.split('\n').map(line => line.trim()).filter(line => line);
                
                // Add channels to selection
                selectedChannelSlugs = [...new Set([...selectedChannelSlugs, ...lines])];
                updateSelectedChannels();
                
                // Update checkboxes
                lines.forEach(slug => {
                    const checkbox = document.querySelector(`input[value="${slug}"]`);
                    if (checkbox) {
                        checkbox.checked = true;
                    }
                });
                
                alert(`Added ${lines.length} channels from file. Total selected: ${selectedChannelSlugs.length}`);
            };
            reader.readAsText(file);
        }
        
        // Load bundles
        async function loadBundles(page = 1) {
            try {
                console.log('Loading bundles...');
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=get_bundles&page=${page}&limit=1000`
                });
                
                console.log('Bundle response status:', response.status);
                const responseText = await response.text();
                console.log('Bundle raw response length:', responseText.length);
                console.log('Bundle raw response (first 500 chars):', responseText.substring(0, 500));
                console.log('Bundle raw response (last 500 chars):', responseText.substring(Math.max(0, responseText.length - 500)));
                
                // If response is empty, show more details
                if (responseText.length === 0) {
                    console.error('Empty bundle response received!');
                    console.log('Bundle response headers:', response.headers);
                    console.log('Bundle response status:', response.status);
                    console.log('Bundle response statusText:', response.statusText);
                }
                
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('Bundle JSON parse error:', parseError);
                    document.getElementById('bundleList').innerHTML = 
                        '<div class="error">Server returned invalid response for bundles. Check console for details.</div>';
                    return;
                }
                
                console.log('Bundle parsed data:', data);
                
                if (data.success) {
                    allBundles = data.bundles;
                    filteredBundles = [...allBundles];
                    displayBundles(data.bundles);
                    updateBundleCounter();
                    currentPage = data.page;
                    totalPages = data.total_pages;
                    updatePagination();
                } else {
                    document.getElementById('bundleList').innerHTML = 
                        '<div class="error">Error loading bundles: ' + data.message + '</div>';
                }
            } catch (error) {
                console.error('Error loading bundles:', error);
                document.getElementById('bundleList').innerHTML = 
                    '<div class="error">Error loading bundles: ' + error.message + '</div>';
            }
        }
        
        // Display bundles grouped by category
        function displayBundles(bundles) {
            const container = document.getElementById('bundleList');
            if (bundles.length === 0) {
                container.innerHTML = '<p>No bundles found</p>';
                return;
            }
            
            // Group bundles by channel_group
            const groupedBundles = bundles.reduce((groups, bundle) => {
                const group = bundle.channel_group || 'General';
                if (!groups[group]) {
                    groups[group] = [];
                }
                groups[group].push(bundle);
                return groups;
            }, {});
            
            container.innerHTML = Object.keys(groupedBundles).map(groupName => {
                const groupBundles = groupedBundles[groupName];
                return `
                    <div class="bundle-group">
                        <h3 class="group-title">${groupName} (${groupBundles.length})</h3>
                        <div class="bundle-grid">
                            ${groupBundles.map(bundle => {
                                const channels = JSON.parse(bundle.channels || '[]');
                                const imageUrl = bundle.image_url || `https://avenuei.com/cdn/shop/products/avenuei-${bundle.bundle_slug}-distribution.png`;
                                const channelSlugs = channels.slice(0, 3).join(', ');
                                const moreCount = channels.length > 3 ? channels.length - 3 : 0;
                                return `
                                    <div class="bundle-card">
                                        <div class="bundle-image">
                                            <img src="${imageUrl}" alt="${bundle.bundle_name}" 
                                                 onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxMDAiIGhlaWdodD0iMTAwIiBmaWxsPSIjRjNGNEY2Ii8+CjxwYXRoIGQ9Ik00MCA0MEg2MFY2MEg0MFY0MFoiIGZpbGw9IiM5Q0EzQUYiLz4KPHN2ZyB4PSI0NSIgeT0iNDUiIHdpZHRoPSIxMCIgaGVpZ2h0PSIxMCIgdmlld0JveD0iMCAwIDEwIDEwIiBmaWxsPSJub25lIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPgo8cGF0aCBkPSJNMi41IDEuMjVMMy43NSAyLjVMNSAzLjc1VjEuMjVIMi41WiIgZmlsbD0id2hpdGUiLz4KPC9zdmc+Cjwvc3ZnPgo='">
                                        </div>
                                        <div class="bundle-content">
                                            <div class="bundle-header">
                                                <div class="bundle-info">
                                                    <div class="bundle-name">${bundle.bundle_name}</div>
                                                    <div class="bundle-description">${bundle.description || 'No description available'}</div>
                                                    <div class="bundle-meta">
                                                        <span class="bundle-count">${bundle.channel_count} channels</span>
                                                        <span class="bundle-group">${bundle.channel_group}</span>
                                                    </div>
                                                </div>
                                                <div class="bundle-actions">
                                                    <button class="btn btn-small btn-secondary" onclick="editBundle(${bundle.id})">
                                                        Edit
                                                    </button>
                                                    <button class="delete-trashcan" onclick="deleteBundle(${bundle.id})" title="Delete Bundle">
                                                        🗑️
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="bundle-pricing">
                                                <div class="pricing-tiers">
                                                    <span class="price-tier">30d:$${bundle.price}</span>
                                                    <span class="price-tier">90d:$${bundle.price_90}</span>
                                                    <span class="price-tier">180d:$${bundle.price_180}</span>
                                                    <span class="price-tier">365d:$${bundle.price_365}</span>
                                                    <span class="currency">${bundle.currency}</span>
                                                </div>
                                            </div>
                                            <div class="bundle-channels">
                                                <strong>Channels:</strong> ${channelSlugs}${moreCount > 0 ? ` (+${moreCount} more)` : ''}
                                            </div>
                                        </div>
                                    </div>
                                `;
                            }).join('')}
                        </div>
                    </div>
                `;
            }).join('');
        }
        
        // Update pagination
        function updatePagination() {
            const container = document.getElementById('bundlePagination');
            if (totalPages <= 1) {
                container.innerHTML = '';
                return;
            }
            
            let html = '';
            for (let i = 1; i <= totalPages; i++) {
                html += `<button class="${i === currentPage ? 'active' : ''}" onclick="loadBundles(${i})">${i}</button>`;
            }
            container.innerHTML = html;
        }
        
        // Update bundle counter
        function updateBundleCounter() {
            const counter = document.getElementById('bundleCounter');
            const total = allBundles.length;
            counter.textContent = `(${total.toLocaleString()})`;
        }
        
        // Toggle search field
        function toggleSearch() {
            const searchField = document.getElementById('searchField');
            const searchInput = document.getElementById('bundleSearch');
            
            if (searchField.style.minWidth === '200px') {
                searchField.style.minWidth = '300px';
                searchInput.focus();
            } else {
                searchField.style.minWidth = '200px';
                searchInput.blur();
                hideSearchResults();
            }
        }
        
        // Handle bundle search
        function handleBundleSearch(event) {
            const query = event.target.value.toLowerCase().trim();
            
            // Clear previous timeout
            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }
            
            // Debounce search
            searchTimeout = setTimeout(() => {
                if (query.length === 0) {
                    hideSearchResults();
                    filteredBundles = [...allBundles];
                    displayBundles(allBundles);
                    return;
                }
                
                // Filter bundles
                filteredBundles = allBundles.filter(bundle => 
                    bundle.bundle_name.toLowerCase().includes(query) ||
                    bundle.description.toLowerCase().includes(query) ||
                    bundle.channel_group.toLowerCase().includes(query) ||
                    bundle.bundle_slug.toLowerCase().includes(query)
                );
                
                // Show search results
                showSearchResults(filteredBundles, query);
                
                // Update display
                displayBundles(filteredBundles);
            }, 300);
        }
        
        // Show search results dropdown
        function showSearchResults(bundles, query) {
            const resultsContainer = document.getElementById('searchResults');
            
            if (bundles.length === 0) {
                resultsContainer.innerHTML = '<div class="search-result-item">No bundles found</div>';
            } else {
                const html = bundles.slice(0, 10).map(bundle => `
                    <div class="search-result-item" onclick="selectSearchResult('${bundle.bundle_name}')">
                        <div class="search-result-name">${highlightText(bundle.bundle_name, query)}</div>
                        <div class="search-result-meta">
                            ${bundle.channel_group} • ${bundle.channel_count} channels • 
                            $${bundle.price} (30d) • $${bundle.price_90} (90d) • $${bundle.price_180} (180d) • $${bundle.price_365} (365d)
                        </div>
                    </div>
                `).join('');
                
                resultsContainer.innerHTML = html;
            }
            
            resultsContainer.style.display = 'block';
        }
        
        // Hide search results
        function hideSearchResults() {
            const resultsContainer = document.getElementById('searchResults');
            resultsContainer.style.display = 'none';
        }
        
        // Select search result
        function selectSearchResult(bundleName) {
            const searchInput = document.getElementById('bundleSearch');
            searchInput.value = bundleName;
            hideSearchResults();
            
            // Filter to show only this bundle
            filteredBundles = allBundles.filter(bundle => 
                bundle.bundle_name.toLowerCase() === bundleName.toLowerCase()
            );
            displayBundles(filteredBundles);
        }
        
        // Highlight search text
        function highlightText(text, query) {
            if (!query) return text;
            const regex = new RegExp(`(${query})`, 'gi');
            return text.replace(regex, '<mark>$1</mark>');
        }
        
        // Handle form submission
        document.getElementById('bundleForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            if (selectedChannelSlugs.length === 0) {
                alert('Please select at least one channel');
                return;
            }
            
            const formData = new FormData(this);
            formData.append('action', 'create_bundle');
            formData.append('channels', JSON.stringify(selectedChannelSlugs));
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                if (data.success) {
                    alert('Bundle created successfully!');
                    this.reset();
                    selectedChannelSlugs = [];
                    updateSelectedChannels();
                    loadBundles();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                console.error('Error creating bundle:', error);
                alert('Error creating bundle');
            }
        });
        
        // Delete bundle
        async function deleteBundle(bundleId) {
            if (!confirm('Are you sure you want to delete this bundle? This action can be undone by restoring from the database.')) {
                return;
            }
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete_bundle&bundle_id=${bundleId}`
                });
                
                const data = await response.json();
                if (data.success) {
                    alert('Bundle deleted successfully');
                    loadBundles();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                console.error('Error deleting bundle:', error);
                alert('Error deleting bundle');
            }
        }
        
        // Edit bundle (placeholder)
        function editBundle(bundleId) {
            alert('Edit functionality coming soon! Bundle ID: ' + bundleId);
        }
    </script>
</body>
</html>

<?php
// Clean up output buffer
ob_end_flush();
?>
