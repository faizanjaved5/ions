<?php
/**
 * Debug Version - Smart Dynamic ION city routing handler
 * Last updated: 2025-08-10 (Merged: preserve original logic + add @profile handling)
 * By Omar Sayed
 * Notes:
 * - All original logic (debug logging, required-file checks, IONDatabase usage,
 *   transients, canonicalization, Unsplash fallback, 404 template, globals for template) is preserved.
 * - Added a lightweight @username profile route that exits early if matched.
 * 
 * The iondynamic.php is a central dynamic routing handler that serves both city pages and @username profile pages with caching, logging, and image handling.
 * It does the following:
 * - Enables or disables dynamic routing globally via a toggle.
 * - Includes required config, database, helper, and Unsplash background image logic (with fallbacks).
 * - Handles @username profile routes (/route=profile&handle=... or /@handle) before any city logic, loading profile/ionprofile.php.
 * - Validates the incoming slug and ignores unwanted requests (e.g., favicon).
 * - Connects to the database and retrieves city data from IONLocalNetwork, using transient caching.
 * - Logs every visit and whether the city was found in cache or DB.
 * - Sends a 404 with the proper template if no matching city is found.
 * - Canonicalizes the URL and redirects to the proper city slug if needed.
 * - Fetches or refreshes the background image via Unsplash, updates DB, and caches results.
 * - Sets global template variables for city name, image, alt text, and credits.
 * - Loads the ioncity.php template to render the city page.
 */

// Enable error reporting for debugging (original)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log function for debugging (original)
function debug_log($message) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents(__DIR__ . '/debug.log', "[$timestamp] $message\n", FILE_APPEND);
}

debug_log("=== Starting iondynamic.php ===");

$dynamic_enabled = true;  // (original)
if (!$dynamic_enabled) {
    debug_log("Dynamic rendering disabled");
    return;
}

// Check if required files exist (original)
$required_files = [
    __DIR__ . '/config/database.php',
    __DIR__ . '/city/helper-functions.php',
    __DIR__ . '/city/ioncity.php'
];

foreach ($required_files as $file) {
    if (!file_exists($file)) {
        debug_log("MISSING FILE: $file");
        die("Missing required file: $file");
    } else {
        debug_log("Found file: $file");
    }
}

// Include required files with error handling (original)
try {
    debug_log("Including database.php");
    require_once(__DIR__ . '/config/database.php');
    debug_log("Database.php included successfully");

    debug_log("Including helper-functions.php");
    require_once(__DIR__ . '/city/helper-functions.php');
    debug_log("Helper-functions.php included successfully");

    // Check if unsplash.php exists before including (original with fallback)
    if (file_exists(__DIR__ . '/unsplash.php')) {
        debug_log("Including unsplash.php");
        require_once(__DIR__ . '/unsplash.php');
        debug_log("Unsplash.php included successfully");
    } else {
        debug_log("WARNING: unsplash.php not found, using fallback function");
        if (!function_exists('fetchUnsplashBackgroundImage')) {
            function fetchUnsplashBackgroundImage($city, $state, $country, $return_data = false) {
                return [
                    'image' => '/assets/default-hero.jpg',
                    'alt' => 'Default hero image',
                    'credit' => 'Default image',
                    'is_fallback' => true
                ];
            }
        }
    }
} catch (Exception $e) {
    debug_log("ERROR including files: " . $e->getMessage());
    die("Error including required files: " . $e->getMessage());
}

/* ============================= ION PROFILES: @username (ADDED) =============================
   Supports:
     - Rewrite form:    ^@([A-Za-z0-9._-]+)/?$  iondynamic.php?route=profile&handle=$1 [QSA,L]
     - Direct path hit: /@username
   This block is self-contained, uses native PHP only, logs via debug_log, and exits on match.
   It does not alter or rely on the city routing below.
------------------------------------------------------------------------------------------- */
(function () {
    // Determine if the request is a profile route
    $is_profile = false;
    $handle = '';

    // 1) Querystring-based route (via .htaccess rewrite)
    if (isset($_GET['route']) && $_GET['route'] === 'profile' && !empty($_GET['handle'])) {
        $handle = $_GET['handle'];
        $is_profile = true;
    } else {
        // 2) Path-based fallback: /@username
        $req = $_SERVER['REQUEST_URI'] ?? '';
        $path = is_string($req) ? parse_url($req, PHP_URL_PATH) : '/';
        if (is_string($path) && preg_match('#^/@([A-Za-z0-9._-]{1,64})/?$#', $path, $m)) {
            $handle = $m[1] ?? '';
            $_GET['handle'] = $handle; // make available to renderer if it expects it
            $is_profile = true;
        }
    }

    if ($is_profile) {
        // Validate handle
        if (!preg_match('/^[A-Za-z0-9._-]{1,64}$/', $handle)) {
            debug_log("Profile route invalid handle");
            http_response_code(404);
            echo '<h2>Profile not found.</h2>';
            exit;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $ts = date('Y-m-d H:i:s');
        debug_log("Profile view: @$handle | $ip | $ts");

        $profile_file = __DIR__ . '/profile/ionprofile.php';
        if (file_exists($profile_file)) {
            require $profile_file;
            exit; // Important: stop here, do not fall through to city routing
        }

        debug_log("Profile file missing: $profile_file");
        http_response_code(404);
        echo '<h2>Profile not found.</h2>';
        exit;
    }
})();
/* =========================== END ION PROFILES: @username (ADDED) ========================== */

// Get and validate slug (original)
$slug_raw   = $_GET['slug'] ?? '';
$subpath    = sanitize_text_field($_GET['subpath'] ?? '');
$slug_raw   = str_replace('+', '-', $slug_raw);
$slug       = strtolower(sanitize_title($slug_raw));
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
$timestamp  = date('Y-m-d H:i:s');

debug_log("Processed slug: '$slug' from raw: '$slug_raw'");

// Ignore favicon requests (original)
$ignored_slugs = ['favicon', 'favicon.ico'];
if (in_array($slug, $ignored_slugs)) {
    debug_log("Ignored slug: $slug");
    return;
}

if (empty($slug)) {
    debug_log("Empty slug provided");
    http_response_code(400);
    echo '<h2>Invalid request: missing slug.</h2>';
    exit;
}

debug_log("Processing request for slug: $slug");

// Initialize database connection (original)
try {
    debug_log("Initializing database connection");
    global $wpdb;
    $wpdb = new IONDatabase();

    if (!$wpdb->isConnected()) {
        debug_log("ERROR: Database connection failed");
        http_response_code(500);
        echo '<h2>Database connection failed.</h2>';
        exit;
    }
    debug_log("Database connection successful");
} catch (Exception $e) {
    debug_log("ERROR initializing database: " . $e->getMessage());
    http_response_code(500);
    echo '<h2>Database initialization error: ' . $e->getMessage() . '</h2>';
    exit;
}

// Look up city data (original with transients)
try {
    debug_log("Looking up city data for slug: $slug");
    $cache_key = 'ion_city_' . $slug;
    $city = get_transient($cache_key);

    if ($city === false) {
        debug_log("No cache found, querying database");
        $city = $wpdb->get_row($wpdb->prepare("SELECT * FROM IONLocalNetwork WHERE LOWER(slug) = %s", $slug));
        if ($city) {
            debug_log("City found in database: " . $city->city_name);
            set_transient($cache_key, $city, HOUR_IN_SECONDS * 24);
        } else {
            debug_log("No city found in database for slug: $slug");
        }
    } else {
        debug_log("City loaded from cache: " . $city->city_name);
    }
} catch (Exception $e) {
    debug_log("ERROR querying database: " . $e->getMessage());
    http_response_code(500);
    echo '<h2>Database query error: ' . $e->getMessage() . '</h2>';
    exit;
}

if (!$city || empty($city->slug)) {
    debug_log("City not found, showing 404");
    http_response_code(404);
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    $template_404 = __DIR__ . '/city/404.php';
    if (file_exists($template_404)) {
        debug_log("Including 404 template");
        include $template_404;
    } else {
        debug_log("404 template not found");
        echo '<h2>404 Not Found</h2>';
        echo '<p>The requested city page could not be found.</p>';
    }
    exit;
}

debug_log("City found: " . $city->city_name . " (Status: " . ($city->status ?? 'Unknown') . ")");

// Handle URL canonicalization (original)
$current_path = '';
try {
    if (!empty($_SERVER['REQUEST_URI'])) {
        $parsed_url = @parse_url($_SERVER['REQUEST_URI']);
        if (!empty($parsed_url['path'])) {
            $parsed_path = trim($parsed_url['path'], '/');
            if (!empty($parsed_path)) {
                $current_path = strtolower($parsed_path);
                if (!empty($city->slug) && $current_path !== strtolower($city->slug)) {
                    $redirect_url = (function () {
                        // Prefer site URL if available in helpers; fallback to host header
                        if (function_exists('get_site_url')) {
                            return rtrim(get_site_url(), '/') . '/';
                        }
                        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                        return $scheme . '://' . $host . '/';
                    })() . $city->slug;

                    debug_log("Redirecting to canonical URL: $redirect_url");
                    header("Location: $redirect_url", true, 301);
                    exit;
                }
            }
        }
    }
} catch (Throwable $e) {
    debug_log("Path processing error: " . $e->getMessage());
}

// Process images / Unsplash (original semantics preserved)
debug_log("Processing images");
$refresh = isset($_GET['refresh']) || isset($_GET['refresh_image']);
$image_path = $city->image_path ?? '';
$image_alt = '';
$attribution = '';

if (empty($image_path) || $refresh) {
    debug_log("Fetching background image");
    try {
        $data = fetchUnsplashBackgroundImage($city->city_name, $city->state_name, $city->country_name, true);
        if (!empty($data)) {
            $image_path = $data['image'];
            $image_alt = $data['alt'];
            $attribution = $data['credit'];

            if (empty($data['is_fallback'])) {
                debug_log("Updating image in database");
                $updated = $wpdb->update(
                    'IONLocalNetwork',
                    [
                        'image_path'   => $image_path,
                        'image_credit' => $attribution,
                        'image_alt'    => $image_alt
                    ],
                    ['slug' => $city->slug]
                );

                if ($updated !== false) {
                    $city->image_path   = $image_path;
                    $city->image_credit = $attribution;
                    $city->image_alt    = $image_alt;
                    set_transient($cache_key, $city, HOUR_IN_SECONDS * 24);
                    debug_log("Image updated successfully");
                } else {
                    debug_log("Image DB update returned false (no change?)");
                }
            } else {
                debug_log("Using fallback image, skipping DB update");
            }
        }
    } catch (Exception $e) {
        debug_log("Error fetching background image: " . $e->getMessage());
    }
}

// Set template variables (original)
debug_log("Setting template variables");
$GLOBALS['ion_city_data']   = $city;
$GLOBALS['ion_image_source'] = $image_path;
$GLOBALS['ion_image_credit'] = $city->image_credit ?? $attribution;
$GLOBALS['ion_image_alt']    = $city->image_alt ?? '';

// Load template (original)
$template_path = __DIR__ . '/city/ioncity.php';
debug_log("Loading template: $template_path");

if (file_exists($template_path)) {
    // Set up variables for template
    $city         = $GLOBALS['ion_city_data'];
    $image_path   = $GLOBALS['ion_image_source'];
    $image_credit = $GLOBALS['ion_image_credit'];
    $image_alt    = $GLOBALS['ion_image_alt'];

    debug_log("Including template file");
    try {
        include $template_path;
        debug_log("Template included successfully");
    } catch (Exception $e) {
        debug_log("ERROR in template: " . $e->getMessage());
        http_response_code(500);
        echo '<h2>Template error: ' . htmlspecialchars($e->getMessage()) . '</h2>';
    }
} else {
    debug_log("Template file not found: $template_path");
    http_response_code(500);
    echo '<h2>Critical error: Missing ioncity.php template in /city/ directory.</h2>';
    echo '<p>Expected path: ' . htmlspecialchars($template_path) . '</p>';
}

debug_log("=== End of iondynamic.php ===");
?>