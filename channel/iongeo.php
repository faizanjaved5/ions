<?php
/**
 * ION Geographic Hierarchy System
 * Dynamic routing for Countries → States/Provinces → Cities/Towns
 * 
 * Routes:
 * - /channel/ or /channel/countries → Show all countries
 * - /channel/united-states → Show US states
 * - /channel/canada → Show Canadian provinces
 * - /channel/mexico → Show Mexican states
 * - /channel/arizona → Show Arizona cities/towns
 * - /channel/miami → Show Miami channel page (existing ioncity.php)
 * 
 * Data source: IONLocalNetwork table
 */

declare(strict_types=1);

// Start session for authentication checks
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---- Bootstrap / Config ----------------------------------------------------
$root = dirname(__DIR__);
$configPath = $root . '/config/config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    echo "Configuration missing.";
    exit;
}
$config = require $configPath;

// Database connection
try {
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ]
    );
} catch (Throwable $e) {
    http_response_code(500);
    echo "Database connection error.";
    exit;
}

// ---- Input & Slug Detection ------------------------------------------------
$slug = $_GET['slug'] ?? '';
$slug = trim(strtolower($slug)); // Case-insensitive

// Theme detection (from session/cookie)
$theme = $_SESSION['theme'] ?? $_COOKIE['theme'] ?? $_GET['theme'] ?? 'dark';
if (!in_array($theme, ['light', 'dark'])) {
    $theme = 'dark';
}

// ---- Helper: Map country slugs to country codes ----------------------------
$countryMap = [
    'united-states' => 'US',
    'usa' => 'US',
    'canada' => 'CA',
    'mexico' => 'MX',
    'united-kingdom' => 'GB',
    'uk' => 'GB',
    'australia' => 'AU',
    'new-zealand' => 'NZ',
    'germany' => 'DE',
    'france' => 'FR',
    'spain' => 'ES',
    'italy' => 'IT',
    'japan' => 'JP',
    'china' => 'CN',
    'india' => 'IN',
    'brazil' => 'BR',
    'argentina' => 'AR',
    'south-africa' => 'ZA',
    'pakistan' => 'PK',
    'indonesia' => 'ID',
];

// Countries that have states/provinces as mid-level hierarchy
$countriesWithStates = ['US', 'CA', 'MX'];

// ---- Determine Hierarchy Level ---------------------------------------------
$hierarchyLevel = 'unknown';
$countryCode = null;
$stateName = null;
$cityName = null;

if (empty($slug) || $slug === 'countries' || $slug === 'index') {
    // Level 1: Show all countries
    $hierarchyLevel = 'countries';
} elseif (isset($countryMap[$slug])) {
    // Level 2: Show states/provinces for major countries, or cities for smaller countries
    $countryCode = $countryMap[$slug];
    
    if (in_array($countryCode, $countriesWithStates)) {
        $hierarchyLevel = 'states';
    } else {
        $hierarchyLevel = 'cities';
    }
} else {
    // Check if slug is a state/province or city
    // First, try to find it as a state
    $stmt = $pdo->prepare("
        SELECT DISTINCT state_code, state_name, country_code, country_name
        FROM IONLocalNetwork
        WHERE LOWER(REPLACE(state_name, ' ', '-')) = :slug
           OR LOWER(state_name) = :slug_original
        LIMIT 1
    ");
    $stmt->execute([
        ':slug' => $slug,
        ':slug_original' => str_replace('-', ' ', $slug)
    ]);
    $stateData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($stateData) {
        // Level 3: Show cities/towns in this state
        $hierarchyLevel = 'cities';
        $countryCode = $stateData['country_code'];
        $stateName = $stateData['state_name'];
    } else {
        // Check if it's a city (fallback to existing ioncity.php)
        $stmt = $pdo->prepare("
            SELECT id, city_name, state_name, country_code
            FROM IONLocalNetwork
            WHERE LOWER(slug) = :slug
            LIMIT 1
        ");
        $stmt->execute([':slug' => $slug]);
        $cityData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($cityData) {
            // This is a city - redirect to existing ioncity.php
            $hierarchyLevel = 'city';
            $cityName = $cityData['city_name'];
        } else {
            // 404: Slug not found
            $hierarchyLevel = 'notfound';
        }
    }
}

// ---- Route to Appropriate View ---------------------------------------------
switch ($hierarchyLevel) {
    case 'countries':
        require __DIR__ . '/ionworld.php';
        break;
        
    case 'states':
        require __DIR__ . '/ioncountry.php';
        break;
        
    case 'cities':
        require __DIR__ . '/ionstate.php';
        break;
        
    case 'city':
        // Redirect to existing city page
        require __DIR__ . '/ioncity.php';
        break;
        
    case 'notfound':
    default:
        http_response_code(404);
        echo "<!DOCTYPE html>
<html lang=\"en\">
<head>
    <meta charset=\"UTF-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <title>Location Not Found - ION</title>
    <link rel=\"stylesheet\" href=\"/channel/IONGeo/styles.css\">
</head>
<body>
    <div class=\"container\">
        <header class=\"header\">
            <h1 class=\"header-title\">Location Not Found</h1>
            <p class=\"header-subtitle\">The location you're looking for doesn't exist in our network.</p>
        </header>
        <a href=\"/channel/\" class=\"back-link\">
            <svg xmlns=\"http://www.w3.org/2000/svg\" fill=\"none\" viewBox=\"0 0 24 24\" stroke-width=\"2\" stroke=\"currentColor\">
                <path stroke-linecap=\"round\" stroke-linejoin=\"round\" d=\"M15 19l-7-7 7-7\"/>
            </svg>
            Browse All Locations
        </a>
    </div>
</body>
</html>";
        exit;
}

