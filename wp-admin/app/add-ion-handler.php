<?php
// Start output buffering to catch any unexpected output
ob_start();

// Set error reporting to catch all issues
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to browser (will break JSON)

require_once $_SERVER['DOCUMENT_ROOT'] . '/login/session.php';

// Handle media upload requests FIRST (before any database operations)
if (isset($_GET['action']) && $_GET['action'] === 'upload_media') {
    header('Content-Type: application/json');
    
    if (!isset($_FILES['media_file']) || $_FILES['media_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error occurred']);
        exit;
    }
    
    $config = include(__DIR__ . '/config/config.php');
    $upload_dir = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . $config['mediaUploadPath'];
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            echo json_encode(['success' => false, 'error' => 'Failed to create upload directory']);
            exit;
        }
    }
    
    $uploaded_file = $_FILES['media_file'];
    
    // Validate file type
    $allowed_types = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/jpg',
        'video/mp4', 'video/webm', 'video/avi', 'video/mov'
    ];
    
    if (!in_array($uploaded_file['type'], $allowed_types)) {
        echo json_encode(['success' => false, 'error' => 'Invalid file type. Only images (JPG, PNG, GIF, WebP) and videos (MP4, WebM, AVI, MOV) are allowed.']);
        exit;
    }
    
    // Check size limits based on file type
    $is_image = strpos($uploaded_file['type'], 'image/') === 0;
    $size_limit = $is_image ? (5 * 1024 * 1024) : (100 * 1024 * 1024); // 5MB for images, 100MB for videos
    
    if ($uploaded_file['size'] > $size_limit) {
        $limit_text = $is_image ? '5MB' : '100MB';
        echo json_encode(['success' => false, 'error' => "File size too large. Maximum {$limit_text} allowed."]);
        exit;
    }
    
    // Generate unique filename
    $file_extension = pathinfo($uploaded_file['name'], PATHINFO_EXTENSION);
    $unique_filename = 'media_' . time() . '_' . uniqid() . '.' . $file_extension;
    $full_path = $upload_dir . $unique_filename;
    
    // Move uploaded file
    if (move_uploaded_file($uploaded_file['tmp_name'], $full_path)) {
        $file_url = $config['mediaUploadPrefix'] . $unique_filename;
        echo json_encode([
            'success' => true, 
            'url' => $file_url,
            'filename' => $uploaded_file['name']
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save uploaded file']);
    }
    exit;
}

// Load database environment
require_once($_SERVER['DOCUMENT_ROOT'] . '/config/database.php');

// Initialize database table name
$wpdb = $db;
$table = 'IONLocalNetwork';

// Add a function to clean output and send JSON
function send_json_response($data) {
    // Clean any unexpected output
    if (ob_get_length()) {
        ob_clean();
    }
    
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Function to generate a unique, properly formatted slug
function generate_unique_slug($city_name, $state_code = '', $existing_id = null, $table = 'IONLocalNetwork') {
    global $wpdb;
    
    // Generate base slug from city name
    $base_slug = 'ion-' . preg_replace('/[^a-z0-9-]/', '', str_replace(' ', '-', strtolower($city_name)));
    $slug = $base_slug;
    
    error_log("SLUG GENERATION: Base slug generated: '$slug'");
    
    // Check for existing slug, excluding current record if updating
    $where_clause = "slug = %s";
    $params = [$slug];
    
    if ($existing_id) {
        $where_clause .= " AND id != %d";
        $params[] = $existing_id;
    }
    
    $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE $where_clause", $params));
    
    if ($existing) {
        error_log("SLUG GENERATION: Slug '$slug' already exists, checking state code option");
        
        // If we have a state code, try appending it
        if ($state_code) {
            $slug_with_state = $base_slug . '-' . strtolower($state_code);
            
            // Check if slug with state code is unique
            $where_clause_state = "slug = %s";
            $params_state = [$slug_with_state];
            
            if ($existing_id) {
                $where_clause_state .= " AND id != %d";
                $params_state[] = $existing_id;
            }
            
            $existing_with_state = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE $where_clause_state", $params_state));
            
            if (!$existing_with_state) {
                $slug = $slug_with_state;
                error_log("SLUG GENERATION: Using slug with state code: '$slug'");
            } else {
                error_log("SLUG GENERATION: Slug with state code '$slug_with_state' also exists");
                return false; // Indicate that no unique slug could be generated
            }
        } else {
            error_log("SLUG GENERATION: No state code available for uniqueness");
            return false; // Cannot make unique without state code
        }
    }
    
    error_log("SLUG GENERATION: Final unique slug: '$slug'");
    return $slug;
}

if (isset($_GET['action']) && $_GET['action'] === 'fetch_geo') {
    $town = trim($_GET['town'] ?? '');
    $state = trim($_GET['state'] ?? '');
    $country = trim($_GET['country'] ?? '');
    $lat = '';
    $lon = '';
    $pop = '';
    $display_name = '';

    error_log("GEO FETCH: Starting geo data fetch for town='$town', state='$state', country='$country'");

    if ($town && $country) {
        // Build search query with better formatting for Banff, Canada, Alberta
        $search_parts = [$town];
        if ($state && $state !== 'All States/Provinces') {
            $search_parts[] = $state;
        }
        $search_parts[] = $country;
        $query = urlencode(implode(', ', $search_parts));
        
        error_log("GEO FETCH: Built query: '$query' from parts: " . json_encode($search_parts));
        
        // Use OpenStreetMap Nominatim API with better parameters
        $url = "https://nominatim.openstreetmap.org/search?q=$query&format=json&limit=3&addressdetails=1&extratags=1";
        $opts = [
            "http" => [
                "header" => "User-Agent: IONLocalNetwork/1.0 (https://ions.com)\r\n",
                "timeout" => 10
            ]
        ];
        $context = stream_context_create($opts);
        
        error_log("GEO FETCH: Querying Nominatim: $url");
        $response = @file_get_contents($url, false, $context);
        
        if ($response) {
            $data = json_decode($response, true);
            error_log("GEO FETCH: Nominatim returned " . count($data) . " results");
            
            // Find the best match - prefer cities/towns over other types
            $best_result = null;
            foreach ($data as $result) {
                $place_type = $result['type'] ?? '';
                $class = $result['class'] ?? '';
                
                // Prefer cities, towns, villages over other place types
                if (in_array($place_type, ['city', 'town', 'village', 'municipality']) || 
                    $class === 'place') {
                    $best_result = $result;
                    break;
                }
                
                // If no perfect match, use first result
                if (!$best_result) {
                    $best_result = $result;
                }
            }
            
            if ($best_result) {
                $lat = $best_result['lat'] ?? '';
                $lon = $best_result['lon'] ?? '';
                $display_name = $best_result['display_name'] ?? '';
                
                error_log("GEO FETCH: Selected result - lat=$lat, lon=$lon, display_name=$display_name");
                
                // Try to get population from the extratags or address details
                if (isset($best_result['extratags']['population'])) {
                    $pop = preg_replace('/[^0-9]/', '', $best_result['extratags']['population']);
                    error_log("GEO FETCH: Found population in extratags: $pop");
                }
            }
        } else {
            error_log("GEO FETCH: Failed to get response from Nominatim");
        }

        // If we still don't have population, try alternative approach with GeoNames
        if (!$pop && $lat && $lon) {
            error_log("GEO FETCH: Attempting to get population from GeoNames");
            
            // GeoNames API (requires registration but has a free tier)
            // Alternative: try REST Countries API for country-level data
            $geonames_url = "http://api.geonames.org/findNearbyPlaceNameJSON?lat=$lat&lng=$lon&maxRows=1&username=demo";
            $geonames_response = @file_get_contents($geonames_url, false, $context);
            
            if ($geonames_response) {
                $geonames_data = json_decode($geonames_response, true);
                if (isset($geonames_data['geonames'][0]['population'])) {
                    $pop = $geonames_data['geonames'][0]['population'];
                    error_log("GEO FETCH: Found population from GeoNames: $pop");
                }
            }
        }
        
        // Final fallback: try to extract population from city name search in a different way
        if (!$pop && $town) {
            error_log("GEO FETCH: Attempting fallback population search");
            
            // Try Nominatim search with specific population query
            $pop_query = urlencode("$town population statistics");
            $pop_url = "https://nominatim.openstreetmap.org/search?q=$pop_query&format=json&limit=1&extratags=1";
            $pop_response = @file_get_contents($pop_url, false, $context);
            
            if ($pop_response) {
                $pop_data = json_decode($pop_response, true);
                if (isset($pop_data[0]['extratags']['population'])) {
                    $pop = preg_replace('/[^0-9]/', '', $pop_data[0]['extratags']['population']);
                    error_log("GEO FETCH: Found population from fallback search: $pop");
                }
            }
        }
    }

    // Validate and clean up the data
    if ($lat && $lon) {
        // Ensure coordinates are numeric and within valid ranges
        $lat = (float) $lat;
        $lon = (float) $lon;
        
        if ($lat >= -90 && $lat <= 90 && $lon >= -180 && $lon <= 180) {
            $result = [
                'success' => true,
                'lat' => number_format($lat, 6, '.', ''),
                'lon' => number_format($lon, 6, '.', ''),
                'display_name' => $display_name
            ];
            
            // Only include population if we found a reasonable value
            if ($pop && is_numeric($pop) && $pop > 0) {
                $result['pop'] = $pop;
            }
            
            error_log("GEO FETCH: Success - returning: " . json_encode($result));
            echo json_encode($result);
        } else {
            error_log("GEO FETCH: Invalid coordinates - lat=$lat, lon=$lon");
            echo json_encode([
                'success' => false,
                'error' => 'Invalid coordinates received from geocoding service'
            ]);
        }
    } else {
        error_log("GEO FETCH: No coordinates found");
        echo json_encode([
            'success' => false,
            'error' => 'Could not find coordinates for this location. Please check the city and country names.'
        ]);
    }
    exit;
}

// Get city data for editing
if (isset($_GET['action']) && $_GET['action'] === 'get_city_data') {
    $city_id = intval($_GET['city_id'] ?? 0);
    
    if (!$city_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid city ID']);
        exit;
    }
    
    $city = $wpdb->get_row("SELECT * FROM $table WHERE id = %d", $city_id);
    
    if ($city) {
        echo json_encode(['success' => true, 'city' => $city]);
    } else {
        echo json_encode(['success' => false, 'message' => 'City not found']);
    }
    exit;
}

// Delete city record and associated videos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_city') {
    $city_id = intval($_POST['city_id'] ?? 0);
    
    if (!$city_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid city ID']);
        exit;
    }
    
    // Get city data to get the slug for video deletion
    $city = $wpdb->get_row("SELECT slug FROM $table WHERE id = %d", $city_id);
    
    if (!$city) {
        echo json_encode(['success' => false, 'message' => 'City not found']);
        exit;
    }
    
    $slug = $city->slug;
    
    // Delete associated videos first
    $videos_deleted = $wpdb->delete('IONVideos', ['slug' => $slug]);
    error_log("DELETE CITY: Deleted $videos_deleted videos for slug: $slug");
    
    // Delete the city record
    $result = $wpdb->delete($table, ['id' => $city_id]);
    
    if ($result !== false) {
        error_log("DELETE CITY: Successfully deleted city ID: $city_id");
        echo json_encode(['success' => true, 'message' => 'City and associated videos deleted successfully']);
    } else {
        error_log("DELETE CITY: Failed to delete city ID: $city_id");
        echo json_encode(['success' => false, 'message' => 'Failed to delete city']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_ion'])) {
    // Add error logging for debugging
    error_log("ION CREATE: Starting form submission");
    
    // Validate required fields
    $city_name       = trim($_POST['city_name'] ?? '');
    $country_code    = trim($_POST['country'] ?? '');
    $country_name    = ''; // Lookup based on code
    $state_code      = trim($_POST['state'] ?? '');
    $state_name      = ''; // Lookup
    $custom_domain   = trim($_POST['custom_domain'] ?? '');
    $status          = $_POST['status'] ?? 'Draft';
    $population      = trim($_POST['population'] ?? '');
    $latitude        = trim($_POST['latitude'] ?? '');
    $longitude       = trim($_POST['longitude'] ?? '');
    $channel_name    = trim($_POST['channel_name'] ?? '');
    $title           = trim($_POST['title'] ?? '');
    $description     = trim($_POST['description'] ?? '');
    $seo_title       = trim($_POST['seo_title'] ?? '');
    $seo_description = trim($_POST['seo_meta_description'] ?? '');
    // $subtitle = trim($_POST['description'] ?? ''); // Alias for subtitle - REMOVED: column doesn't exist

    error_log("ION CREATE: Required fields - city_name: '$city_name', country_code: '$country_code'");

    if (!$city_name || !$country_code) {
        error_log("ION CREATE: Missing required fields");
        echo json_encode(['error' => 'Required fields missing']);
        exit;
    }

    // Generate lookup and slug BEFORE handling image upload
    $lookup = preg_replace('/[^a-z]/', '', strtolower($city_name));
    $slug = generate_unique_slug($city_name, $state_code, null, $table);
    
    if ($slug === false) {
        error_log("ION CREATE: Failed to generate unique slug for city: '$city_name', state: '$state_code'");
        echo json_encode(['error' => 'Unable to generate unique slug. A similar entry may already exist.']);
        exit;
    }
    
    $page_url = 'https://ions.com/' . $slug;

    error_log("ION CREATE: Generated slug: '$slug', lookup: '$lookup'");

    // Handle image processing
    $image_path = '';
    $image_source_type = $_POST['image_source_type'] ?? 'url';
    
    if ($image_source_type === 'url') {
        // Use provided URL directly - check for both image_path and image_url
        $image_path = trim($_POST['image_path'] ?? $_POST['image_url'] ?? '');
        error_log("ION CREATE: Using image URL: '$image_path'");
    } elseif ($image_source_type === 'upload' && isset($_FILES['image_file'])) {
        error_log("ION CREATE: Processing image upload");
        // Handle file upload - ensure proper path handling using config
        $config = include(__DIR__ . '/config/config.php');
        $upload_dir = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . $config['mediaUploadPath'];
        $web_path = $config['mediaUploadPath'];
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                error_log("ION CREATE: Failed to create upload directory");
                echo json_encode(['error' => 'Failed to create upload directory']);
                exit;
            }
        }
        
        $uploaded_file = $_FILES['image_file'];
        
        // Validate file
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($uploaded_file['type'], $allowed_types)) {
            error_log("ION CREATE: Invalid file type: " . $uploaded_file['type']);
            echo json_encode(['error' => 'Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.']);
            exit;
        }
        
        if ($uploaded_file['size'] > $max_size) {
            error_log("ION CREATE: File too large: " . $uploaded_file['size']);
            echo json_encode(['error' => 'File size too large. Maximum 5MB allowed.']);
            exit;
        }
        
        if ($uploaded_file['error'] !== UPLOAD_ERR_OK) {
            error_log("ION CREATE: Upload error: " . $uploaded_file['error']);
            echo json_encode(['error' => 'File upload error.']);
            exit;
        }
        
        // Generate unique filename
        $file_extension = pathinfo($uploaded_file['name'], PATHINFO_EXTENSION);
        $unique_filename = $slug . '_' . time() . '.' . $file_extension;
        $full_path = $upload_dir . $unique_filename;
        
        // Move uploaded file
        if (move_uploaded_file($uploaded_file['tmp_name'], $full_path)) {
            // Save as full URL for consistency with UPDATE and user requirements
            $image_path = $config['mediaUploadPrefix'] . $unique_filename;
            error_log("ION CREATE: Image uploaded successfully: '$image_path'");
        } else {
            error_log("ION CREATE: Failed to move uploaded file");
            echo json_encode(['error' => 'Failed to save uploaded file.']);
            exit;
        }
    }

    // Duplicate checking is now handled by generate_unique_slug function

    // Comprehensive country name lookup
    $countries = require_once(__DIR__ . '/countries.php');
    $country_name = '';
    foreach ($countries as $country) {
        if ($country['code'] === $country_code) {
            $country_name = $country['name'];
            break;
        }
    }
    
    if (!$country_name) {
        error_log("ION CREATE: Could not find country name for code: '$country_code'");
        // Special handling for common codes
        if ($country_code === 'US') {
            $country_name = 'United States';
        } else {
            $country_name = $country_code; // Fallback to code
        }
    }

    error_log("ION CREATE: Country resolved - code: '$country_code', name: '$country_name'");

    // State name from code, need a map
    $state_map = [
        'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California',
        'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware', 'FL' => 'Florida', 'GA' => 'Georgia',
        'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa',
        'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland',
        'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi', 'MO' => 'Missouri',
        'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey',
        'NM' => 'New Mexico', 'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio',
        'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina',
        'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah', 'VT' => 'Vermont',
        'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming',
        // Canada
        'AB' => 'Alberta', 'BC' => 'British Columbia', 'MB' => 'Manitoba', 'NB' => 'New Brunswick',
        'NL' => 'Newfoundland and Labrador', 'NT' => 'Northwest Territories', 'NS' => 'Nova Scotia',
        'NU' => 'Nunavut', 'ON' => 'Ontario', 'PE' => 'Prince Edward Island', 'QC' => 'Quebec',
        'SK' => 'Saskatchewan', 'YT' => 'Yukon',
    ];
    $state_name = $state_map[$state_code] ?? '';

    // Generate if not provided
    if (!$channel_name) {
        $channel_name = 'ION ' . $city_name . ($state_code ? ', ' . $state_name . ' (' . $state_code . ')' : '') . ', ' . $country_name;
    }
    if (!$title) {
        $title = 'Welcome to ION ' . $city_name . ($state_name ? ' ' . $state_name : '');
    }
    if (!$description) {
        $description = "Got your ION $city_name? Explore sports, entertainment, events, businesses & more in $city_name. Stay updated and tune in now!";
    }
    if (!$seo_title) {
        $seo_title = "ION " . ucwords(strtolower($city_name)) . " Local Community Network";
        if ($state_name) $seo_title .= " | " . $state_name;
    }

    error_log("ION CREATE: Generated fields - channel_name: '$channel_name', title: '$title'");

    // Prepare insert data
    $insert_data = [
        'channel_name' => $channel_name,
        'custom_domain' => $custom_domain,
        'status' => $status,
        'type' => $city_name ? 'town' : ($state_code ? 'state' : 'country'),
        'lookup' => $lookup,
        'slug' => $slug,
        'page_URL' => $page_url,
        'city_name' => $city_name,
        'state_code' => $state_code,
        'state_name' => $state_name,
        'country_code' => $country_code,
        'country_name' => $country_name,
        'population' => $population ? " $population " : '',
        'latitude' => $latitude,
        'longitude' => $longitude,
        'title' => $title,
        'description' => $description,
        'seo_title' => $seo_title,
        'seo_description' => $seo_description,
        'image_path' => $image_path,
        'created_at' => date('Y-m-d H:i:s'),
        'cloudflare_active' => 'missing',
    ];

    error_log("ION CREATE: Attempting database insert");
    error_log("ION CREATE: Insert data: " . json_encode($insert_data));

    // Insert
    $insert = $wpdb->insert($table, $insert_data);

    if ($insert === false) {
        error_log("ION CREATE: Database insert failed");
        error_log("ION CREATE: wpdb last error: " . $wpdb->last_error);
        error_log("ION CREATE: wpdb last query: " . $wpdb->last_query);
        echo json_encode(['error' => 'Insert failed: ' . $wpdb->last_error]);
    } else {
        error_log("ION CREATE: Database insert successful, ID: " . $wpdb->insert_id);
        echo json_encode(['success' => true]);
    }
    exit;
}

// Update existing ION record
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_ion'])) {
    // Add error logging for debugging
    error_log("ION UPDATE: Starting form submission");
    error_log("ION UPDATE: POST data: " . print_r($_POST, true));
    
    // Check if wpdb is available
    if (!isset($wpdb) || !$wpdb) {
        error_log("ION UPDATE: Database connection not available");
        send_json_response(['error' => 'Database connection error']);
    }
    
    $city_id = intval($_POST['city_id'] ?? 0);
    
    if (!$city_id) {
        error_log("ION UPDATE: No city ID provided");
        send_json_response(['error' => 'Invalid city ID']);
    }
    
    // Validate required fields
    $city_name = trim($_POST['city_name'] ?? '');
    $country_code = trim($_POST['country'] ?? '');
    $state_code = trim($_POST['state'] ?? '');
    $custom_domain = trim($_POST['custom_domain'] ?? '');
    $status = $_POST['status'] ?? 'Draft';
    $population = trim($_POST['population'] ?? '');
    $latitude = trim($_POST['latitude'] ?? '');
    $longitude = trim($_POST['longitude'] ?? '');
    $channel_name = trim($_POST['channel_name'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $seo_title = trim($_POST['seo_title'] ?? '');
    $seo_description = trim($_POST['seo_meta_description'] ?? '');

    error_log("ION UPDATE: Required fields - city_id: $city_id, city_name: '$city_name', country_code: '$country_code'");

    if (!$city_name || !$country_code) {
        error_log("ION UPDATE: Missing required fields");
        send_json_response(['error' => 'Required fields missing']);
    }

    // Get existing record to preserve certain fields
    $existing = $wpdb->get_row("SELECT * FROM $table WHERE id = %d", $city_id);
    
    if (!$existing) {
        error_log("ION UPDATE: Record not found for ID: " . $city_id);
        send_json_response(['error' => 'Record not found']);
    }

    // Handle image (stored in image_path column)
    $image_path = '';
    $image_source_type = $_POST['image_source_type'] ?? 'url';
    
    if ($image_source_type === 'url') {
        $image_path = trim($_POST['image_url'] ?? '');
        error_log("ION UPDATE: Using image URL: '$image_path'");
    } elseif ($image_source_type === 'upload' && isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
        error_log("ION UPDATE: Processing image upload");
        
        // Use same directory structure as CREATE - ensure proper path handling
        $upload_dir = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/assets/headers/';
        $web_path = '/assets/headers/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                error_log("ION UPDATE: Failed to create upload directory");
                send_json_response(['error' => 'Failed to create upload directory']);
            }
        }
        
        $uploaded_file = $_FILES['image_file'];
        
        // Validate file
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($uploaded_file['type'], $allowed_types)) {
            error_log("ION UPDATE: Invalid file type: " . $uploaded_file['type']);
            send_json_response(['error' => 'Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.']);
        }
        
        if ($uploaded_file['size'] > $max_size) {
            error_log("ION UPDATE: File too large: " . $uploaded_file['size']);
            send_json_response(['error' => 'File size too large. Maximum 5MB allowed.']);
        }
        
        // Generate unique filename using city name or existing slug
        $file_extension = pathinfo($uploaded_file['name'], PATHINFO_EXTENSION);
        $slug_for_filename = trim($_POST['channel_name'] ?? '') ?: $existing->slug;
        $unique_filename = $slug_for_filename . '_' . time() . '.' . $file_extension;
        $full_path = $upload_dir . $unique_filename;
        
        // Move uploaded file
        if (move_uploaded_file($uploaded_file['tmp_name'], $full_path)) {
            // Save as full URL as requested
            $image_path = 'https://ions.com' . $web_path . $unique_filename;
            error_log("ION UPDATE: Image uploaded successfully: '$image_path'");
        } else {
            error_log("ION UPDATE: Failed to move uploaded file");
            send_json_response(['error' => 'Failed to save uploaded file.']);
        }
    } else {
        // Keep existing image if no new one provided
        $image_path = $existing->image_path ?? '';
        error_log("ION UPDATE: Keeping existing image: '$image_path'");
    }

    // Countries mapping (same as create)
    $country_map = [];
    $country_name = '';
    
    try {
        require_once('countries.php');
        if (isset($countries) && is_array($countries)) {
            foreach ($countries as $country) {
                if (isset($country['code']) && isset($country['name'])) {
                    $country_map[$country['code']] = $country['name'];
                }
            }
            $country_name = $country_map[$country_code] ?? '';
        }
    } catch (Exception $e) {
        error_log("ION UPDATE: Error loading countries.php: " . $e->getMessage());
        // Use fallback country name with special handling for US
        if ($country_code === 'US') {
            $country_name = 'United States';
        } else {
            $country_name = $country_code;
        }
    }

    // State mapping (same as create) 
    $state_map = [
        'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California',
        'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware', 'FL' => 'Florida', 'GA' => 'Georgia',
        'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa',
        'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland',
        'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi', 'MO' => 'Missouri',
        'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey',
        'NM' => 'New Mexico', 'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio',
        'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina',
        'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah', 'VT' => 'Vermont',
        'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming',
        'AB' => 'Alberta', 'BC' => 'British Columbia', 'MB' => 'Manitoba', 'NB' => 'New Brunswick',
        'NL' => 'Newfoundland and Labrador', 'NS' => 'Nova Scotia', 'NT' => 'Northwest Territories',
        'NU' => 'Nunavut', 'ON' => 'Ontario', 'PE' => 'Prince Edward Island', 'QC' => 'Quebec',
        'SK' => 'Saskatchewan', 'YT' => 'Yukon',
    ];
    $state_name = $state_map[$state_code] ?? '';

    // Generate slug if needed
    $new_slug = $existing->slug; // Default to existing slug
    if ($city_name !== $existing->city_name || $state_code !== $existing->state_code) {
        $generated_slug = generate_unique_slug($city_name, $state_code, $city_id, $table);
        if ($generated_slug === false) {
            error_log("ION UPDATE: Failed to generate unique slug for city: '$city_name', state: '$state_code'");
            send_json_response(['error' => 'Unable to generate unique slug. A similar entry may already exist.']);
        }
        $new_slug = $generated_slug;
        error_log("ION UPDATE: Generated new slug: '$new_slug'");
    }

    // Prepare update data
    $update_data = [
        'city_name' => $city_name,
        'country_code' => $country_code,
        'country_name' => $country_name,
        'state_code' => $state_code,
        'state_name' => $state_name,
        'county_name' => trim($_POST['county_name'] ?? ''),
        'custom_domain' => $custom_domain,
        'status' => $status,
        'population' => $population,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'slug' => $new_slug,
        'title' => $title,
        'description' => $description,
        'seo_title' => $seo_title,
        'seo_description' => $seo_description,
        'image_path' => $image_path,
        'updated_at' => date('Y-m-d H:i:s')
    ];

    error_log("ION UPDATE: Attempting to update record with data: " . print_r($update_data, true));

    $result = $wpdb->update($table, $update_data, ['id' => $city_id]);

    if ($result === false) {
        error_log("ION UPDATE: Database update failed");
        error_log("ION UPDATE: wpdb last error: " . $wpdb->last_error);
        send_json_response(['error' => 'Update failed: ' . $wpdb->last_error]);
    } else {
        error_log("ION UPDATE: Database update successful for ID: " . $city_id);
        send_json_response(['success' => true]);
    }
}
?> 