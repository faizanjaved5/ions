<?php
/**
 * Debug Version - Smart Dynamic ION city routing handler
 * Last updated: 2025-08-10 (Merged: preserve original logic + add @profile handling)
 * By Omar Sayed
 * Notes:
 * - All original logic (debug logging, required-file checks, IONDatabase usage,
 * transients, canonicalization, Unsplash fallback, 404 template, globals for template) is preserved.
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

// CRITICAL: If pass=1 is set, we should not be here (htaccess should skip)
// This prevents redirect loops - exit immediately and let WordPress handle it
if (isset($_GET['pass']) && $_GET['pass'] == '1') {
    debug_log("pass=1 detected - this should have been handled by WordPress. Exiting to prevent loop.");
    return;
}

$dynamic_enabled = true; // (original)
if (!$dynamic_enabled) {
    debug_log("Dynamic rendering disabled");
    return;
}
// Check if required files exist (original)
$required_files = [
    __DIR__ . '/config/database.php',
    __DIR__ . '/channel/helper-functions.php',
    __DIR__ . '/channel/ioncity.php'
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
    require_once(__DIR__ . '/channel/helper-functions.php');
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
// =================== EARLY STATIC FILE SKIP ===================
// Skip dynamic processing for common static assets (prevents redirects/processing)
function ion_original_path(): string {
    // Prefer envs set by .htaccess first
    foreach (['ORIG_URI', 'UNENCODED_URL', 'REDIRECT_URL', 'REDIRECT_REQUEST_URI', 'REQUEST_URI'] as $k) {
        if (!empty($_SERVER[$k])) {
            $path = parse_url($_SERVER[$k], PHP_URL_PATH);
            if (is_string($path) && $path !== '') return $path;
        }
    }
    return '/';
}
$request_path = ion_original_path();
debug_log("Request path: $request_path");
$static_exts = ['ico', 'png', 'jpg', 'jpeg', 'gif', 'css', 'js', 'woff', 'ttf', 'svg', 'mp4', 'webm', 'ogg'];
if (preg_match('/\.(' . implode('|', $static_exts) . ')$/i', $request_path)) {
    debug_log("Static asset: $request_path - abort");
    return;
}
// Add more patterns if needed, e.g.:
// if (strpos($request_path, '/wp-admin/') === 0 || strpos($request_path, '/assets/') === 0) {
//     debug_log("Non-dynamic path: $request_path - abort");
//     return;
// }

// Skip video shortlink directory - should be handled by v/index.php
if (preg_match('#^/v(/|$)#', $request_path)) {
    debug_log("Video shortlink path: $request_path - skipping iondynamic");
    // If this is /v/ without a slug, redirect to home
    if ($request_path === '/v' || $request_path === '/v/') {
        header('Location: /');
        exit();
    }
    // Otherwise, let v/index.php handle it
    return;
}
// ==============================================================
// =================== DOMAIN ➜ SLUG OVERRIDE (ALWAYS) ===================
// If the request host is a mapped domain, ALWAYS override $_GET['slug']
// and mark this request as a parked/mapped domain view.
// Prefer explicit envs from .htaccess; fall back to Apache defaults
$__ion_host = $_SERVER['VHOST'] ?? $_SERVER['HTTP_HOST'] ?? '';
$__ion_orig_uri = $_SERVER['ORIG_URI'] ?? $_SERVER['REQUEST_URI'] ?? '/';
debug_log("Env host={$__ion_host} | ORIG_URI={$__ion_orig_uri} | REQ_URI=" . ($_SERVER['REQUEST_URI'] ?? ''));
// Normalize host for lookups (treat www. the same as apex)
$__ion_host = preg_replace('/^www\./i', '', $__ion_host);
$__PRIMARY_HOSTS = [
    'ions.com',
    'www.ions.com',
    'iblog.bz',
    'www.iblog.bz',
    // add any other “primary” hosts that should NOT force override
];
$__IS_MAPPED_DOMAIN = false;
try {
    if (!empty($__ion_host) && !in_array(strtolower($__ion_host), array_map('strtolower', $__PRIMARY_HOSTS), true)) {
        global $wpdb;
        if (empty($wpdb) || !($wpdb instanceof IONDatabase)) {
            $wpdb = new IONDatabase();
        }
        if (!method_exists($wpdb, 'isConnected') || $wpdb->isConnected()) {
            $___row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT slug FROM IONLocalNetwork WHERE custom_domain = %s OR domain = %s LIMIT 1",
                    $__ion_host, $__ion_host
                )
            );
            if ($___row && !empty($___row->slug)) {
                $_GET['slug'] = $___row->slug; // force override
                $__IS_MAPPED_DOMAIN = true; // flag for later logic
                if (function_exists('debug_log')) {
                    debug_log("FORCE OVERRIDE by domain: {$__ion_host} -> slug={$___row->slug}");
                }
            } else {
                if (function_exists('debug_log')) {
                    debug_log("Domain not mapped: {$__ion_host} (no override)");
                }
            }
        } else {
            if (function_exists('debug_log')) {
                debug_log("Domain override skipped: DB connection not available");
            }
        }
    }
} catch (Throwable $e) {
    if (function_exists('debug_log')) {
        debug_log("Domain override error: " . $e->getMessage());
    }
}
// =================== HEURISTIC MAPPED DETECTION (FALLBACK) ===================
// If DB didn't map, check if host follows "ion[-]slug.tld" pattern and treat as mapped
if (!$__IS_MAPPED_DOMAIN && !empty($__ion_host)) {
    // Only for non-primary hosts (consistent with DB logic)
    if (!in_array(strtolower($__ion_host), array_map('strtolower', $__PRIMARY_HOSTS), true)) {
        $hostForHeuristic = $__ion_host; // Already normalized
        if (preg_match('/^ion-?([a-z0-9\-]+)\./', $hostForHeuristic, $m) && !empty($m[1])) {
            $__IS_MAPPED_DOMAIN = true;
            $heuristic_slug = 'ion-' . strtolower($m[1]);
            if (empty($_GET['slug'])) {
                $_GET['slug'] = $heuristic_slug;
                debug_log("Early heuristic slug set: $heuristic_slug");
            }
            debug_log("Heuristic mapped detection: host=$hostForHeuristic -> mapped=true");
        }
    }
}
// Update global flag
$GLOBALS['ION_IS_MAPPED_DOMAIN'] = $__IS_MAPPED_DOMAIN;
// Cache-Control for mapped (already exists, but ensure it's after this)
if (!headers_sent() && !empty($GLOBALS['ION_IS_MAPPED_DOMAIN'])) {
    header('Cache-Control: private, no-store, no-cache, must-revalidate');
    header('X-ION-Route: mapped');
    debug_log('Mapped-domain request: ' . ($_SERVER['HTTP_HOST'] ?? ''));
}
// Expose the flag for later (canonicalization/template/etc.)
// ======================================================================
/* ============================= ION PROFILES: @username (ADDED) =============================
   Supports:
     - Rewrite form: ^@([A-Za-z0-9._-]+)/?$ iondynamic.php?route=profile&handle=$1 [QSA,L]
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
// ---------------- Get and validate slug (FINAL) ----------------
// 1) If the domain->slug override didn't fire, try to derive from the original path
if (empty($_GET['slug'])) {
    $path = ion_original_path();
    // Match any slug pattern (ion-something OR other-slug-format)
    // First try ion- prefix (most common for channels)
    if (preg_match('#/(ion-[a-z0-9\-]+)(?:/|$)#i', $path, $m)) {
        $_GET['slug'] = strtolower($m[1]);
        debug_log("Fallback slug from path (ion- prefix): {$_GET['slug']} (path={$path})");
    } 
    // Then try any slug-like pattern (for academy, studio, etc.)
    elseif (preg_match('#/([a-z0-9]+(?:-[a-z0-9]+)+)(?:/|$)#i', $path, $m)) {
        $_GET['slug'] = strtolower($m[1]);
        debug_log("Fallback slug from path (generic): {$_GET['slug']} (path={$path})");
    } else {
        debug_log("No slug in path (path={$path})");
    }
}
// 2) Normalize slug (now that $_GET['slug'] may have been set)
$subpath = sanitize_text_field($_GET['subpath'] ?? '');
$slug_raw = $_GET['slug'] ?? '';
$slug_raw = str_replace('+', '-', $slug_raw);
$slug = strtolower(sanitize_title($slug_raw));
debug_log("Processed slug: '$slug' from raw: '$slug_raw'");
// 3) Ignore favicon
if (in_array($slug, ['favicon', 'favicon.ico'], true)) {
    debug_log("Ignored slug: $slug");
    return;
}
// Last-chance: if mapped domain and slug still empty, try domain->slug again
if ($slug === '' && !empty($GLOBALS['ION_IS_MAPPED_DOMAIN'])) {
    try {
        $hostForLookup = preg_replace('/^www\./i', '', ($_SERVER['HTTP_HOST'] ?? ''));
        if (!empty($hostForLookup)) {
            if (empty($wpdb) || !($wpdb instanceof IONDatabase)) {
                $wpdb = new IONDatabase();
            }
            if (!method_exists($wpdb, 'isConnected') || $wpdb->isConnected()) {
                $row2 = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT slug FROM IONLocalNetwork WHERE custom_domain = %s OR domain = %s LIMIT 1",
                        $hostForLookup, $hostForLookup
                    )
                );
                if ($row2 && !empty($row2->slug)) {
                    $_GET['slug'] = $row2->slug;
                    $slug_raw = $_GET['slug'];
                    $slug = strtolower(sanitize_title(str_replace('+','-',$slug_raw)));
                    debug_log("Last-chance domain lookup set slug={$slug} for host={$hostForLookup}");
                } else {
                    debug_log("Last-chance lookup found no row for host={$hostForLookup}");
                }
            } else {
                debug_log("Last-chance lookup skipped: DB not connected");
            }
        }
    } catch (Throwable $ee) {
        debug_log("Last-chance lookup error: " . $ee->getMessage());
    }
}
// 4) Bail if still empty
if ($slug === '') {
    debug_log("Empty slug provided (host=" . ($_SERVER['HTTP_HOST'] ?? '') . ", ORIG_URI=" . ($_SERVER['ORIG_URI'] ?? '') . ", REQ_URI=" . ($_SERVER['REQUEST_URI'] ?? '') . ")");
    http_response_code(400);
    echo '<h2>Invalid request: missing slug.</h2>';
    exit;
}
debug_log("Processing request for slug: $slug");
if (!headers_sent()) header("X-ION-Debug-Slug: " . $slug);

// =================== GEOGRAPHIC HIERARCHY ROUTING (ADDED) ===================
// Check if this slug is a country or state before trying to load as a city
// This allows /ion-united-states, /ion-canada, /ion-arizona etc. to work
$countryMap = [
    'ion-united-states' => 'US', 'ion-usa' => 'US', 'ion-canada' => 'CA',
    'ion-mexico' => 'MX', 'ion-united-kingdom' => 'GB', 'ion-uk' => 'GB',
    'ion-australia' => 'AU', 'ion-new-zealand' => 'NZ', 'ion-germany' => 'DE',
    'ion-france' => 'FR', 'ion-spain' => 'ES', 'ion-italy' => 'IT',
    'ion-japan' => 'JP', 'ion-china' => 'CN', 'ion-india' => 'IN',
    'ion-brazil' => 'BR', 'ion-argentina' => 'AR', 'ion-south-africa' => 'ZA',
    'ion-uae' => 'AE', 'ion-bulgaria' => 'BG', 'ion-iceland' => 'IS',
    'ion-philippines' => 'PH', 'ion-saudi-arabia' => 'SA',
];

$countriesWithStates = ['US', 'CA', 'MX'];

if (isset($countryMap[$slug])) {
    debug_log("Geographic routing: Country detected - $slug");
    // Set up for ioncountry.php or direct cities
    $countryCode = $countryMap[$slug];
    $theme = $_SESSION['theme'] ?? $_COOKIE['theme'] ?? 'dark';
    
    // Initialize PDO for geographic pages
    $config = require __DIR__ . '/config/config.php';
    try {
        $pdo = new PDO(
            "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4",
            $config['username'],
            $config['password'],
            [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ]
        );
    } catch (PDOException $e) {
        http_response_code(500);
        echo "Database connection error.";
        exit;
    }
    
    if (in_array($countryCode, $countriesWithStates)) {
        // Show states/provinces
        require __DIR__ . '/channel/ioncountry.php';
    } else {
        // Show cities directly
        $hierarchyLevel = 'cities';
        require __DIR__ . '/channel/ionstate.php';
    }
    exit; // Stop further processing
}

// Check if this is a state slug
try {
    global $wpdb;
    if (empty($wpdb)) {
        $wpdb = new IONDatabase();
    }
    
    // Extract state name from slug (remove 'ion-' prefix if present)
    $stateSlugSearch = preg_replace('/^ion-/', '', $slug);
    $stateSlugSearch = str_replace('-', ' ', $stateSlugSearch);
    
    $stateCheck = $wpdb->get_row($wpdb->prepare(
        "SELECT DISTINCT state_name, state_code, country_code FROM IONLocalNetwork 
         WHERE LOWER(REPLACE(state_name, ' ', '-')) = %s 
         OR LOWER(state_name) = %s 
         LIMIT 1",
        strtolower($stateSlugSearch),
        strtolower($stateSlugSearch)
    ));
    
    if ($stateCheck && !empty($stateCheck->state_name)) {
        debug_log("Geographic routing: State detected - " . $stateCheck->state_name);
        $stateName = $stateCheck->state_name;
        $countryCode = $stateCheck->country_code;
        $theme = $_SESSION['theme'] ?? $_COOKIE['theme'] ?? 'dark';
        
        // Initialize PDO for geographic pages
        $config = require __DIR__ . '/config/config.php';
        try {
            $pdo = new PDO(
                "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4",
                $config['username'],
                $config['password'],
                [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ]
            );
        } catch (PDOException $e) {
            http_response_code(500);
            echo "Database connection error.";
            exit;
        }
        
        require __DIR__ . '/channel/ionstate.php';
        exit; // Stop further processing
    }
} catch (Exception $e) {
    debug_log("Geographic routing check error: " . $e->getMessage());
    // Continue to city lookup on error
}
// =================== END GEOGRAPHIC HIERARCHY ROUTING ===================

// =================== TOPIC/NETWORK ROUTING ===================
// Check if this slug is a topic/network before trying city lookup
// This allows /ion-sports-network, /ion-comedy-network etc. to work
try {
    global $wpdb;
    if (empty($wpdb)) {
        $wpdb = new IONDatabase();
    }
    
    debug_log("Checking if slug '$slug' is a topic/network...");
    
    // First, check if there are duplicate slugs for debugging
    $check_duplicates = $wpdb->get_results($wpdb->prepare(
        "SELECT id, network_name, slug FROM IONNetworks WHERE LOWER(slug) = LOWER(%s) ORDER BY id ASC",
        $slug
    ));
    
    if (count($check_duplicates) > 1) {
        $dup_info = array_map(function($r) { return "ID:{$r->id}={$r->network_name}"; }, $check_duplicates);
        debug_log("WARNING: Found " . count($check_duplicates) . " duplicate slugs for '$slug': " . implode(", ", $dup_info));
    }
    
    // Try exact slug match (case-insensitive)
    // Added ORDER BY id ASC to ensure consistent results when there are duplicate slugs
    $topic = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM IONNetworks WHERE LOWER(slug) = LOWER(%s) ORDER BY id ASC LIMIT 1",
        $slug
    ));
    
    if ($topic && !empty($topic->slug)) {
        debug_log("Topic/Network detected: ID=" . $topic->id . ", Name=" . $topic->network_name . " (slug: " . $topic->slug . ")");
        
        // Set up for iontopic.php
        $theme = $_SESSION['theme'] ?? $_COOKIE['theme'] ?? 'dark';
        
        // Initialize PDO for topic pages
        $config = require __DIR__ . '/config/config.php';
        try {
            $pdo = new PDO(
                "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4",
                $config['username'],
                $config['password'],
                [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ]
            );
        } catch (PDOException $e) {
            http_response_code(500);
            echo "Database connection error.";
            exit;
        }
        
        // Load the topic page
        require __DIR__ . '/channel/iontopic.php';
        exit; // Stop further processing
    } else {
        debug_log("Slug '$slug' is not a topic/network, continuing to city lookup...");
    }
} catch (Exception $e) {
    debug_log("Topic routing check error: " . $e->getMessage());
    // Continue to city lookup on error
}
// =================== END TOPIC/NETWORK ROUTING ===================

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
    debug_log("City not found in database for slug: $slug");
    // Fallback: if no city found, redirect to clean URL with only ?pass=1
    // Remove slug and subpath parameters added by .htaccess rewrite
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    parse_str($qs, $qarr);
    
    // Remove internal rewrite parameters
    unset($qarr['slug']);
    unset($qarr['subpath']);
    
    // Add pass=1 to skip .htaccess rewrite
    $qarr['pass'] = '1';
    
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $redirect_url = $scheme . '://' . $host . '/' . $slug;
    
    if (!empty($qarr)) {
        $redirect_url .= '?' . http_build_query($qarr);
    }
    
    debug_log("No city found. Passthrough to WordPress: $redirect_url");
    header("Location: $redirect_url", true, 307);
    exit;
}
debug_log("City found: " . $city->city_name . " (Status: " . ($city->status ?? 'Unknown') . ")");
// =================== HEURISTIC CUSTOM DOMAIN SETTING & STATUS NORMALIZATION ===================
// For heuristic mapped domains, set custom_domain on city object if missing (for template validation)
// Also normalize status to lowercase for consistency
if (!empty($GLOBALS['ION_IS_MAPPED_DOMAIN']) && empty($city->custom_domain)) {
    $city->custom_domain = preg_replace('/^www\./i', '', $_SERVER['HTTP_HOST'] ?? '');
    debug_log("Heuristic set custom_domain: " . $city->custom_domain . " for slug: " . $city->slug);
}
$city->status = strtolower($city->status ?? '');
debug_log("Normalized status to: " . $city->status . " for slug: " . $city->slug);
// =====================================================================
// ---------------- Canonicalization (mapped-domain safe) ----------------
try {
    $req_uri = $_SERVER['REQUEST_URI'] ?? '/';
    $parsed = @parse_url($req_uri);
    $path_only = trim((string)($parsed['path'] ?? ''), '/'); // e.g. "", "ion-dallas"
    $qs = isset($parsed['query']) && $parsed['query'] !== '' ? ('?' . $parsed['query']) : '';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $is_mapped = !empty($GLOBALS['ION_IS_MAPPED_DOMAIN']);
    $slug_lc = strtolower($city->slug ?? '');
    if ($is_mapped) {
        // ✅ For mapped/parked domains: canonical URL is the domain root (no slug in path)
        if ($path_only !== '') {
            $redirect_url = $scheme . '://' . $host . '/' . $qs; // collapse to "/"
            debug_log("Mapped canonical: remove path '{$path_only}' -> {$redirect_url}");
            if (!headers_sent()) {
                header('X-ION-Canonical: root');
                header("Location: {$redirect_url}", true, 301);
            }
            exit;
        }
        // Already at root — serve content as-is (no redirect)
    } else {
        // 🌐 On primary hosts only, keep canonical path at "/{slug}"
        if ($slug_lc !== '' && $path_only !== $slug_lc) {
            $redirect_url = $scheme . '://' . $host . '/' . $slug_lc . $qs;
            debug_log("Primary host canonical -> slug path: {$redirect_url}");
            if (!headers_sent()) {
                header('X-ION-Canonical: slug');
                header("Location: {$redirect_url}", true, 301);
            }
            exit;
        }
    }
} catch (Throwable $e) {
    debug_log("Canonicalization error: " . $e->getMessage());
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
                        'image_path' => $image_path,
                        'image_credit' => $attribution,
                        'image_alt' => $image_alt
                    ],
                    ['slug' => $city->slug]
                );
                if ($updated !== false) {
                    $city->image_path = $image_path;
                    $city->image_credit = $attribution;
                    $city->image_alt = $image_alt;
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
$GLOBALS['ion_city_data'] = $city;
$GLOBALS['ion_image_source'] = $image_path;
$GLOBALS['ion_image_credit'] = $city->image_credit ?? $attribution;
$GLOBALS['ion_image_alt'] = $city->image_alt ?? '';
// Load template (original)
// ---------------- Template selection based on "type" ----------------
$page_type = strtolower(trim($city->type ?? 'city'));
$GLOBALS['ion_page_type'] = $page_type; // expose to templates if needed
// Choose the template by type
if ($page_type === 'country') {
    $template_path = __DIR__ . '/channel/ioncountry.php';
} elseif ($page_type === 'state' || $page_type === 'province') {
    $template_path = __DIR__ . '/channel/ionstate.php';
} else {
    $template_path = __DIR__ . '/channel/ioncity.php';
}
// Safety fallback if the chosen file is missing
if (!file_exists($template_path)) {
    debug_log("Chosen template missing for type={$page_type}: $template_path — falling back to city template");
    $template_path = __DIR__ . '/channel/ioncity.php';
}
// --------------------------------------------------------------------
debug_log("Loading template: $template_path");
if (file_exists($template_path)) {
    // Set up variables for template
    $city = $GLOBALS['ion_city_data'];
    $image_path = $GLOBALS['ion_image_source'];
    $image_credit = $GLOBALS['ion_image_credit'];
    $image_alt = $GLOBALS['ion_image_alt'];
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
    echo '<h2>Critical error: Missing ioncity.php template in /channel/ directory.</h2>';
    echo '<p>Expected path: ' . htmlspecialchars($template_path) . '</p>';
}
debug_log("=== End of iondynamic.php ===");
?>