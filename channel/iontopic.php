<?php
/**
 * ION Channel Pages with Dynamic Hero Slider, Video.js Support, and Video Tracking
 * by Omar Sayed
 * Last updated: 2025-08-03
 * Enhanced: Video.js support for local videos, native players for streaming platforms, video tracking integration
 */

$config = require __DIR__ . '/../config/config.php';      // Load configtracking
include_once      __DIR__ . '/helper-functions.php';      // Include helper functions (polyfills, etc.)

// require_once      __DIR__ . '/../tracking/tracking.php';  // Include video tracking system
// Include video tracking system (first try ../tracking/, then city/tracking/ as fallback)
$__track_main = __DIR__ . '/../tracking/tracking.php';
$__track_alt  = __DIR__ . '/tracking/tracking.php';

if (file_exists($__track_main)) {
    require_once $__track_main;
} elseif (file_exists($__track_alt)) {
    require_once $__track_alt;
} else {
    // No tracking library available: provide a no-op stub to avoid fatals
    if (!class_exists('VideoTracker')) {
        class VideoTracker {
            public function __construct($pdo = null, $redis = null) {}
            public function getTrackingScript($slug, $cityName) { return '<!-- tracking disabled -->'; }
            public function getStats($a=null,$b=null,$c=null){ return []; }
        }
    }
}

// PDO connection for database (replace $wpdb)
try {
    $pdo = new PDO("mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}", $config['username'], $config['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo '<!-- Debug: PDO connection successful -->';
} catch (PDOException $e) {
    echo '<!-- Debug: PDO connection failed: ' . htmlspecialchars($e->getMessage()) . ' -->';
    echo "<p>Database connection failed. Check config.php for host, dbname, username, password.</p>";
    return;
}

// Initialize video tracker
$redis = null;
if (isset($config['redis_enabled']) && $config['redis_enabled']) {
    try {
        $redis = new Redis();
        $redis->connect($config['redis_host'] ?? '127.0.0.1', $config['redis_port'] ?? 6379);
        if (isset($config['redis_password'])) {
            $redis->auth($config['redis_password']);
        }
    } catch (Exception $e) {
        $redis = null;
    }
}
$videoTracker = new VideoTracker($pdo, $redis);

// Enhanced search handling function
function handleSearchRequest($pdo, $city) {
    if (!isset($_GET['search']) || empty(trim($_GET['search']))) {
        return null;
    }
    
    $query = trim($_GET['search']);
    
    // Use the centralized search API with better error handling
    $search_api_url = "http://" . $_SERVER['HTTP_HOST'] . "/city/search.php?q=" . urlencode($query) . "&limit=30";
    
    // Add city context if available
    if ($city && !empty($city->city_name)) {
        $search_api_url .= "&city=" . urlencode($city->slug ?? $city->city_name);
    }
    
    // Enhanced error handling with timeout and context
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'ignore_errors' => true,
            'user_agent' => 'ION-Search/1.0'
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ]);
    
    $search_response = @file_get_contents($search_api_url, false, $context);
    
    if (!$search_response) {
        error_log("Search API failed for query: $query, URL: $search_api_url");
        return ['error' => 'Search temporarily unavailable'];
    }
    
    $search_data = json_decode($search_response, true);
    if (!$search_data || isset($search_data['error'])) {
        error_log("Search API returned error: " . ($search_data['error'] ?? 'Invalid JSON response'));
        return ['error' => 'Search failed'];
    }
    
    // Enhanced local filtering with better matching
    $results = $search_data['results'] ?? [];
    if ($city && !empty($results)) {
        $local_results = [];
        $other_results = [];
        
        foreach ($results as $result) {
            $is_local = false;
            
            // Check multiple fields for local relevance
            $text_to_check = strtolower(
                ($result['title'] ?? '') . ' ' . 
                ($result['excerpt'] ?? '') . ' ' . 
                ($result['location'] ?? '') . ' ' .
                ($result['description'] ?? '')
            );
            
            $city_name_lower = strtolower($city->city_name ?? '');
            $state_name_lower = strtolower($city->state_name ?? '');
            
            // Check for city name
            if ($city_name_lower && strpos($text_to_check, $city_name_lower) !== false) {
                $is_local = true;
            }
            // Check for state name as secondary indicator
            elseif ($state_name_lower && strpos($text_to_check, $state_name_lower) !== false) {
                $is_local = true;
            }
            
            if ($is_local) {
                $local_results[] = $result;
            } else {
                $other_results[] = $result;
            }
        }
        
        // Combine with local results first, limit total to 30
        $results = array_merge($local_results, array_slice($other_results, 0, 30 - count($local_results)));
    }
    
    return [
        'query' => $query,
        'results' => $results,
        'total' => count($results),
        'local_count' => isset($local_results) ? count($local_results) : 0
    ];
}

// Fetch topic/network from IONNetworks (instead of IONLocalNetwork)
// Extract slug properly from URL if not passed from iondynamic.php
if (!isset($topic) || empty($topic)) {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    // Extract the slug from the path (e.g., /ion-business-network -> ion-business-network)
    if (preg_match('#/(ion-[a-z0-9\-]+)(?:/|$)#i', $path, $matches)) {
        $slug = strtolower($matches[1]);
    } else {
        $slug = trim($path, '/');
    }
    echo '<!-- Debug: Topic slug extracted from URL: ' . htmlspecialchars($slug) . ' -->';
    
    try {
        // Added ORDER BY id ASC to ensure consistent results when there are duplicate slugs
        $stmt = $pdo->prepare("SELECT * FROM IONNetworks WHERE LOWER(slug) = LOWER(:slug) ORDER BY id ASC LIMIT 1");
        $stmt->execute([':slug' => $slug]);
        $topic = $stmt->fetch(PDO::FETCH_OBJ);
        
        if (!$topic) {
            echo '<!-- Debug: No topic found for slug in DB. Check if row exists with slug "' . htmlspecialchars($slug) . '". -->';
            echo "<p>This ION Topic/Network is not available yet.</p>";
            return;
        }
        echo '<!-- Debug: Topic fetched successfully: ID=' . htmlspecialchars($topic->id) . ', Name=' . htmlspecialchars($topic->network_name) . ' (slug: ' . htmlspecialchars($topic->slug) . ') -->';
    } catch (PDOException $e) {
        echo '<!-- Debug: Topic query failed: ' . htmlspecialchars($e->getMessage()) . ' -->';
        echo "<p>Failed to fetch topic data. Check table 'IONNetworks' and columns (e.g., slug, network_name).</p>";
        return;
    }
} else {
    // Topic was passed from iondynamic.php
    $slug = $topic->slug;
    echo '<!-- Debug: Topic passed from iondynamic.php: ' . htmlspecialchars($topic->network_name) . ' (slug: ' . htmlspecialchars($slug) . ') -->';
}

// Fetch Unsplash background image if image_path is empty
$unsplash_image = null;
if (empty($topic->image_path)) {
    echo '<!-- Debug: Fetching Unsplash image for: ' . htmlspecialchars($topic->network_name) . ' -->';
    
    // Include Unsplash helper
    require_once __DIR__ . '/../unsplash.php';
    
    // Fetch image for the network/topic name (strip ™ and other special chars for better search)
    $searchQuery = str_replace(['™', '®', 'ION ', ' Network'], '', $topic->network_name);
    echo '<!-- Debug: Unsplash search query: ' . htmlspecialchars($searchQuery) . ' -->';
    
    $unsplash_image = fetchUnsplashBackgroundImage($searchQuery, null, null, false);
    
    if ($unsplash_image) {
        echo '<!-- Debug: Unsplash image fetched: ' . ($unsplash_image['is_fallback'] ? 'FALLBACK' : 'SUCCESS') . ' -->';
        echo '<!-- Debug: Image URL: ' . htmlspecialchars($unsplash_image['image']) . ' -->';
        
        // Update the IONNetworks table with the fetched image (even if fallback)
        try {
            $stmt = $pdo->prepare("UPDATE IONNetworks SET image_path = :image_path WHERE id = :id");
            $stmt->execute([
                ':image_path' => $unsplash_image['image'],
                ':id' => $topic->id
            ]);
            // Update the topic object
            $topic->image_path = $unsplash_image['image'];
            echo '<!-- Debug: Image saved to database for topic ID: ' . htmlspecialchars($topic->id) . ' -->';
        } catch (PDOException $e) {
            error_log("Failed to save Unsplash image for topic {$topic->id}: " . $e->getMessage());
            echo '<!-- Debug ERROR: Failed to save image: ' . htmlspecialchars($e->getMessage()) . ' -->';
        }
    } else {
        echo '<!-- Debug: Unsplash fetch returned NULL -->';
    }
} else {
    echo '<!-- Debug: Using existing image_path from database: ' . htmlspecialchars($topic->image_path) . ' -->';
}

// Create a city-like object for backward compatibility with existing functions
// Generate a better default description if none exists
$defaultDescription = "Watch the latest " . str_replace(['™', '®'], '', $topic->network_name) . " videos and content on ION";

$city = (object)[
    'city_name' => $topic->network_name,
    'channel_name' => $topic->network_name,
    'title' => $topic->network_name,
    'description' => !empty($topic->description) ? $topic->description : $defaultDescription,
    'state_name' => '',
    'country_name' => 'Network',
    'slug' => $topic->slug,
    'page_URL' => '',
    'seo_title' => $topic->network_name . ' on ION',
    'seo_description' => !empty($topic->description) ? $topic->description : $defaultDescription,
    'seo_keywords' => $topic->network_name . ', ION',
    'image_path' => $topic->image_path ?? '/assets/default-hero.jpg',
    'parent_id' => $topic->parent_id ?? null,
    'level' => $topic->level ?? 0,
    'network_group' => $topic->group ?? null
];

// Add JavaScript context for search functionality integration
echo '<script>';
echo 'window.currentCitySlug = ' . json_encode($slug) . ';';
echo 'window.currentCityName = ' . json_encode($city->city_name) . ';';
echo '</script>';

// Handle search request
$search_results = handleSearchRequest($pdo, $city);

// Include the CSS file content
$css_file = __DIR__ . '/ioncity.css';
$css_content = file_exists($css_file) ? file_get_contents($css_file) : '';

// Enhanced SEO function with search result optimization and tracking
function ion_custom_seo_output() {
    global $city, $search_results, $videoTracker, $slug;
    
    // Modify title if search results exist
    if ($search_results && !isset($search_results['error'])) {
        $seo_title = "Search Results for \"{$search_results['query']}\" - ION " . $city->city_name;
        // Add local results count if available
        if (isset($search_results['local_count']) && $search_results['local_count'] > 0) {
            $seo_title .= " ({$search_results['local_count']} local results)";
        }
    } else {
        $seo_title = esc_html($city->seo_title ?: "{$city->channel_name} - Local Channel for {$city->city_name}, {$city->state_name}");
    }
    
    $seo_description = esc_html($city->seo_description ?: "Explore videos, events, and updates from {$city->city_name}, {$city->state_name}, {$city->country_name}. Powered by ION.");
    $seo_keywords = esc_html($city->seo_keywords ?: "{$city->city_name}, {$city->state_name}, {$city->country_name}, local events, community news");
    
    echo "<title>$seo_title</title>\n";
    echo "<meta name='description' content='$seo_description' />\n";
    echo "<meta name='keywords' content='$seo_keywords' />\n";
    echo "<meta name='msapplication-TileColor' content='#ffffff'>\n";
    echo "<meta name='msapplication-TileImage' content='https://iblog.bz/assets/icons/ms-icon-144x144.png'>\n";
    echo "<meta property='og:title' content='$seo_title' />\n";
    echo "<meta property='og:description' content='$seo_description' />\n";
    echo "<meta property='og:image' content='https://iblog.bz/assets/ionog.png' />\n";
    echo "<meta property='og:type' content='website' />\n";
    echo "<meta name='twitter:card' content='summary_large_image' />\n";
    echo "<meta name='twitter:title' content='$seo_title' />\n";
    echo "<meta name='twitter:description' content='$seo_description' />\n";
    echo "<meta name='twitter:image' content='https://iblog.bz/assets/ionx.png' />\n";
    echo "<meta name='theme-color' content='#ffffff'>\n";
    echo "<link rel='apple-touch-icon' sizes='57x57' href='https://iblog.bz/assets/icons/apple-icon-57x57.png'>\n";
    echo "<link rel='apple-touch-icon' sizes='60x60' href='https://iblog.bz/assets/icons/apple-icon-60x60.png'>\n";
    echo "<link rel='apple-touch-icon' sizes='72x72' href='https://iblog.bz/assets/icons/apple-icon-72x72.png'>\n";
    echo "<link rel='apple-touch-icon' sizes='76x76' href='https://iblog.bz/assets/icons/apple-icon-76x76.png'>\n";
    echo "<link rel='apple-touch-icon' sizes='114x114' href='https://iblog.bz/assets/icons/apple-icon-114x114.png'>\n";
    echo "<link rel='apple-touch-icon' sizes='120x120' href='https://iblog.bz/assets/icons/apple-icon-120x120.png'>\n";
    echo "<link rel='apple-touch-icon' sizes='144x144' href='https://iblog.bz/assets/icons/apple-icon-144x144.png'>\n";
    echo "<link rel='apple-touch-icon' sizes='152x152' href='https://iblog.bz/assets/icons/apple-icon-152x152.png'>\n";
    echo "<link rel='apple-touch-icon' sizes='180x180' href='https://iblog.bz/assets/icons/apple-icon-180x180.png'>\n";
    echo "<link rel='icon' type='image/png' sizes='192x192' href='https://iblog.bz/assets/icons/android-icon-192x192.png'>\n";
    echo "<link rel='icon' type='image/png' sizes='32x32' href='https://iblog.bz/assets/icons/favicon-32x32.png'>\n";
    echo "<link rel='icon' type='image/png' sizes='96x96' href='https://iblog.bz/assets/icons/favicon-96x96.png'>\n";
    echo "<link rel='icon' type='image/png' sizes='16x16' href='https://iblog.bz/assets/icons/favicon-16x16.png'>\n";  
    echo "<link rel='icon' type='image/x-icon' href='https://iblog.bz/assets/icons/favicon.ico'>\n";
    echo "<link rel='manifest' href='https://iblog.bz/assets/icons/manifest.json'>\n";
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo "<link rel='canonical' href='" . htmlspecialchars($city->page_URL ?: get_permalink(), ENT_QUOTES, 'UTF-8') . "' />\n";
    echo '<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap" rel="stylesheet">';
    
    // Add Video.js CDN links
    echo '<link href="https://vjs.zencdn.net/8.6.1/video-js.css" rel="stylesheet">';
    echo '<script src="https://vjs.zencdn.net/8.6.1/video.min.js"></script>';
    
    // Output video tracking script with corrected endpoint
    echo str_replace('/api/video-track.php', '/tracking/tracking-api.php', $videoTracker->getTrackingScript($slug, $city->city_name));
    
    // Google Analytics tracking code
    echo '<!-- Google tag (gtag.js) -->';
    echo '<script async src="https://www.googletagmanager.com/gtag/js?id=G-PXVLDZ9E7H"></script>';
    echo '<script>';
    echo 'window.dataLayer = window.dataLayer || [];';
    echo 'function gtag(){dataLayer.push(arguments)};';
    echo "gtag('js', new Date());";
    echo "gtag('config', 'G-PXVLDZ9E7H');";
    echo '</script>';
    
    echo "<script type='application/ld+json'>" . json_encode([
        "@context" => "https://schema.org",
        "@type" => "BreadcrumbList",
        "itemListElement" => [
            [
                "@type"    => "ListItem",
                "position" => 1,
                "name"     => "Home",
                "item"     => home_url()
            ],
            [
                "@type"    => "ListItem",
                "position" => 2,
                "name"     => $city->city_name,
                "item"     => get_permalink()
            ]
        ]
    ]) . "</script>\n";

    echo "<script type='application/ld+json'>" . json_encode([
        "@context"            => "https://schema.org",
        "@type"               => "Place",
        "name"                => $city->channel_name ?: $city->city_name,
        "address"             => [
            "@type"           => "PostalAddress",
            "addressLocality" => $city->city_name,
            "addressRegion"   => $city->state_name,
            "addressCountry"  => $city->country_name
        ],
        "description" => $city->seo_description,
        "url" => get_permalink()
    ]) . "</script>\n";
}

// Enhanced function to detect video type
function getVideoType($url) {
    $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
    $local_video_extensions = ['mp4', 'webm', 'ogg', 'mov', 'avi', 'm4v', 'mkv'];
    
    if (in_array($ext, $local_video_extensions)) {
        return ['type' => 'local', 'format' => $ext];
    } elseif (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false) {
        return ['type' => 'youtube'];
    } elseif (strpos($url, 'vimeo.com') !== false) {
        return ['type' => 'vimeo'];
    } elseif (strpos($url, 'muvi.com') !== false) {
        return ['type' => 'muvi'];
    } elseif (strpos($url, 'rumble.com') !== false) {
        return ['type' => 'rumble'];
    } elseif (strpos($url, '/uploads/') !== false || strpos($url, 'ions.com') !== false) {
        // Check if it's a self-hosted video by URL pattern
        if (in_array($ext, $local_video_extensions)) {
            return ['type' => 'local', 'format' => $ext];
        }
    }
    
    return ['type' => 'unknown'];
}

// Basic HTML document structure
echo '<!DOCTYPE html>';
echo '<html lang="en">';
echo '<head>';
echo '<meta charset="UTF-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';

// Call SEO function
ion_custom_seo_output();

// Output CSS styles
echo '<style>';
echo $css_content;

// Additional Video.js customization styles
echo "
/* Video.js customizations for ION */
.video-js {
    width: 100%;
    height: 100%;
}

.vjs-big-play-button {
    font-size: 3em;
    line-height: 1.5em;
    height: 1.5em;
    width: 3em;
    display: block;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    padding: 0;
    cursor: pointer;
    opacity: 1;
    border: 0.06666em solid #fff;
    background-color: rgba(43, 51, 63, 0.7);
    border-radius: 0.3em;
    transition: all 0.4s;
}

.vjs-big-play-button:hover {
    background-color: rgba(43, 51, 63, 0.9);
}

.video-js .vjs-control-bar {
    display: flex;
    width: 100%;
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 3em;
    background-color: rgba(43, 51, 63, 0.7);
}

.video-js:hover .vjs-control-bar {
    background-color: rgba(43, 51, 63, 0.9);
}

/* Preview video container */
.preview-video-container {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: none;
}

.carousel-item:hover .preview-video-container {
    display: block;
}

.carousel-item:hover .video-thumbnail,
.carousel-item:hover .play-icon-overlay {
    display: none;
}

/* Modal video container */
.video-modal-content .video-js {
    width: 100%;
    height: 100%;
    border-radius: 16px;
}

/* Responsive video sizing */
@media (max-width: 768px) {
    .video-modal-content .video-js {
        border-radius: 0;
    }
}
";

// Hero section styles remain the same
echo "
/* Ensure navbar stays above hero slider */
#ion-navbar-root,
#ion-navbar-root *,
nav,
header nav {
    z-index: 9999 !important;
}

/* Hero section without search */
.ion-hero {
    display    : flex;
    position   : relative;
    overflow   : hidden;
    height     : 42vh;
    min-height : 400px;
    width      : 100%;
    background-color: #1a1a1a; /* Fallback */
    margin: 0;
    color: white;
    z-index: 1;
    box-sizing: border-box;
}

.slider {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    margin: 0;
}

.slide {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    transition: opacity 1s ease-in-out;
}

.slide.active {
    opacity: 1;
}

.slide img, .slide video, .slide iframe {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.slide-text {
    position: absolute;
    bottom: 50%;
    left: 50%;
    transform: translate(-50%, 50%);
    color: white;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.7);
    z-index: 10;
    text-align: center;
    padding: 2rem;
    border-radius: 0.5rem;
}

.slide-text h1 {
    font-size: 4.5rem;
    font-weight: 800;
    font-family: 'Bebas Neue', 'Sans-serif';
    letter-spacing: 2px;
    margin-bottom: 1rem;
}

.slide-text p {
    font-size: 1.1rem;
    margin-bottom: 1.5rem;
}

.slide-text .btn {
    padding: 0.75rem 1.5rem;
    background-color: #b28254;
    color: white;
    font-weight: 600;
    border-radius: 0.375rem;
    text-decoration: none;
    display: inline-block;
    transition: background-color 0.3s ease;
}

.slide-text .btn:hover {
    background-color: var(--accent-green);
}

.slider-controls {
    position: absolute;
    top: 50%;
    left: 0;
    width: 100%;
    transform: translateY(-50%);
    display: flex;
    justify-content: space-between;
    padding: 0 15px;
    box-sizing: border-box;
    z-index: 11;
}

.slider-controls button {
    background: rgba(0,0,0,0.5);
    border: 2px solid rgba(255,255,255,0.3);
    color: white;
    font-size: 32px;
    cursor: pointer;
    opacity: 0.8;
    padding: 10px 15px;
    border-radius: 50%;
    transition: all 0.3s ease;
    backdrop-filter: blur(5px);
}

.slider-controls button:hover {
    opacity: 1;
    background: rgba(0,0,0,0.7);
    border-color: rgba(255,255,255,0.6);
    transform: scale(1.1);
}

/* Static hero fallback styles (when no slider media available) */
.ion-hero.static {
    background-image: url('" . esc_url($city->image_path ?? '') . "');
    background-size: cover;
    background-position: center center;
    background-repeat: no-repeat;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    text-align: center;
    padding: 2rem;
}

.ion-hero.static::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    height: 100%;
    width: 100%;
    background: linear-gradient(rgba(0,0,0,0.55), rgba(0,0,0,0.55));
    z-index: 0;
}

.ion-hero.static > * {
    position: relative;
    z-index: 1;
}

/* Responsive adjustments */
@media screen and (max-width: 768px) {
    .ion-hero {
        height: 50vh;
    }
    
    .slide-text {
        padding: 1.5rem;
        bottom: 40%;
    }
    
    .slide-text h1 {
        font-size: 3rem;
    }
    
    .slider-controls button {
        font-size: 24px;
        padding: 8px 12px;
    }
}

@media screen and (max-width: 480px) {
    .ion-hero {
        height: 60vh;
    }
    
    .slide-text {
        padding: 1rem;
        bottom: 35%;
    }
    
    .slide-text h1 {
        font-size: 2.5rem;
    }
    
    .slide-text p {
        font-size: 1rem;
    }
}
";

echo '</style>';
echo '</head>';
echo '<body>';
?>

<!-- ION Navbar (React component loaded via JavaScript) -->
<link rel="preload" as="style" href="/menu/ion-navbar.css">
<div id="ion-navbar-root"></div>

<!-- ION Navbar Embed: script setup -->
<script>
    // Minimal globals expected by some libraries
    window.process = window.process || {
        env: {
            NODE_ENV: 'production'
        }
    };
    window.global = window.global || window;
</script>
<script src="/menu/ion-navbar.iife.js"></script>
<script>
    (function() {
        if (window.IONNavbar && typeof window.IONNavbar.mount === 'function') {
            window.IONNavbar.mount('#ion-navbar-root', {
                useShadowDom: true,
                cssHref: '/menu/ion-navbar.css'
            });
        }
        
        // Remove white border line from navbar
        setTimeout(() => {
            const navbar = document.getElementById('ion-navbar-root');
            if (navbar) {
                try {
                    if (navbar.shadowRoot) {
                        const style = document.createElement('style');
                        style.textContent = `
                            * { border-top: none !important; border-bottom: none !important; }
                            nav { 
                                border-top: none !important; 
                                border-bottom: none !important;
                                z-index: 9999 !important;
                                position: relative !important;
                            }
                            header { 
                                border-top: none !important; 
                                border-bottom: none !important;
                                z-index: 9999 !important;
                            }
                            /* Dropdown menus */
                            [role="menu"],
                            .dropdown,
                            .dropdown-menu,
                            ul[role="menu"] {
                                z-index: 10000 !important;
                                position: relative !important;
                            }
                        `;
                        navbar.shadowRoot.appendChild(style);
                    }
                    navbar.style.borderTop = 'none';
                    navbar.style.borderBottom = 'none';
                    navbar.style.zIndex = '9999';
                    navbar.style.position = 'relative';
                } catch (e) {
                    console.log('Could not remove navbar border:', e);
                }
            }
        }, 1000);
    })();
</script>

<?php
// Get image_path from city data and process into media array
$media_string = $city->image_path ?? '';
$media_string = trim($media_string, "'\"");
$media = array_filter(array_map('trim', preg_split('/[ ,\n\r;|]+/', $media_string)));

// Debug for slider
echo '<!-- Debug: Raw media string from DB: ' . htmlspecialchars($media_string) . ' -->';
echo '<!-- Debug: Parsed media array count: ' . count($media) . ' -->';
echo '<!-- Debug: Parsed media array: ' . htmlspecialchars(implode(', ', $media)) . ' -->';

// If no media from database, fall back to static image
$has_slider_media = !empty($media);
echo '<!-- Debug: Has slider media: ' . ($has_slider_media ? 'Yes' : 'No') . ' -->';
?>

<section class="ion-hero<?= !$has_slider_media ? ' static' : '' ?>">
    <?php if ($has_slider_media): ?>
        <div class="slider">
            <?php foreach ($media as $index => $url): ?>
                <?php
                // Determine media type based on URL
                $video_info = getVideoType($url);
                $type = $video_info['type'];
                $video_id = '';
                
                if ($type === 'local') {
                    // Local video file
                    $ext = $video_info['format'];
                } elseif ($type === 'youtube') {
                    // Extract YouTube video ID
                    if (strpos($url, 'youtu.be') !== false) {
                        $video_id = substr(parse_url($url, PHP_URL_PATH), 1);
                    } else {
                        parse_str(parse_url($url, PHP_URL_QUERY), $query);
                        $video_id = $query['v'] ?? '';
                    }
                } elseif ($type === 'vimeo') {
                    // Extract Vimeo video ID
                    $parts = explode('/', trim(parse_url($url, PHP_URL_PATH), '/'));
                    $video_id = end($parts);
                } elseif ($type === 'unknown') {
                    // Treat as image
                    $type = 'image';
                }
                
                echo '<!-- Debug: Media item #' . ($index + 1) . ' URL: ' . esc_url($url) . ', Type: ' . $type . ', ID: ' . $video_id . ' -->';
                ?>
                <div class="slide <?php if ($index === 0) echo 'active'; ?>">
                    <?php if ($type === 'image'): ?>
                        <img src="<?php echo esc_url($url); ?>" alt="<?= esc_attr($city->channel_name) ?> Slide <?php echo $index + 1; ?>" loading="<?php echo ($index === 0) ? 'eager' : 'lazy'; ?>">
                    <?php elseif ($type === 'local'): ?>
                        <video class="video-js vjs-default-skin vjs-big-play-centered" data-setup='{"autoplay": true, "muted": true, "loop": true, "controls": false, "preload": "auto"}'>
                            <source src="<?php echo esc_url($url); ?>" type="video/<?php echo $ext; ?>">
                        </video>
                    <?php elseif ($type === 'youtube' && $video_id): ?>
                        <iframe src="https://www.youtube.com/embed/<?php echo esc_attr($video_id); ?>?autoplay=1&mute=1&loop=1&playlist=<?php echo esc_attr($video_id); ?>&controls=0&modestbranding=1&rel=0&playsinline=1" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                    <?php elseif ($type === 'vimeo' && $video_id): ?>
                        <iframe src="https://player.vimeo.com/video/<?php echo esc_attr($video_id); ?>?autoplay=1&loop=1&muted=1&background=1&controls=0" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>
                    <?php endif; ?>
                    
                    <?php // Add text overlay only to the first slide ?>
                    <?php if ($index === 0): ?>
                        <div class="slide-text">
                            <h1>Welcome to <?= esc_html($city->channel_name ?: $city->title) ?></h1>
                            <p><?= esc_html($city->description ?: 'Explore content from ' . $city->channel_name) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Navigation arrows (only show if multiple slides) -->
        <?php if (count($media) > 1): ?>
            <div class="slider-controls">
                <button class="prev" aria-label="Previous slide">&lt;</button>
                <button class="next" aria-label="Next slide">&gt;</button>
            </div>
        <?php endif; ?>
        
    <?php else: ?>
        <!-- Static fallback hero content WITHOUT search -->
        <h1>Welcome to <?= esc_html($city->channel_name ?: $city->title) ?></h1>
        <p><?= esc_html($city->description) ?></p>
        
        <?php if (!empty($city->custom_domain) && $city->status == "Live") : ?>
            <a href="<?= esc_url($city->custom_domain) ?>" class="btn" target="_blank" style="margin-top: 1rem;">Visit this channel →</a>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php
// Include and render ION Featured Videos carousel (only if not searching)
if (!$search_results) {
    $carousel_path = __DIR__ . '/../includes/featured-videos.php';
    if (file_exists($carousel_path)) {
        require_once $carousel_path;
        renderFeaturedVideosCarousel($pdo, 'channel', $slug);
    } else {
        error_log('ION Featured Videos: Component file not found at ' . $carousel_path);
    }
}
?>

<?php
// Search results display section
if ($search_results): ?>
    <?php if (isset($search_results['error'])): ?>
        <section class="video-carousel" style="text-align: center; padding: 4rem 2rem;">
            <h2 style="color: #ef4444;">Search Error</h2>
            <p style="color: #a4b3d0; font-size: 1.1rem;">
                <?= htmlspecialchars($search_results['error']) ?>
            </p>
            <p style="margin-top: 2rem;">
                <a href="<?= $_SERVER['PHP_SELF'] ?>" style="color: #b28254; text-decoration: none; font-weight: 600;">
                    ← Back to <?= htmlspecialchars($city->city_name) ?> Channel
                </a>
            </p>
        </section>
    <?php else: ?>
        <section class="video-carousel">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h2>Search Results for "<?= htmlspecialchars($search_results['query']) ?>"</h2>
                    <?php if (isset($search_results['local_count']) && $search_results['local_count'] > 0): ?>
                        <p style="color: #22c55e; font-size: 0.9rem; margin: 0.5rem 0 0 0;">
                            <?= $search_results['local_count'] ?> local results • <?= $search_results['total'] - $search_results['local_count'] ?> regional results
                        </p>
                    <?php endif; ?>
                </div>
                <div style="color: #a4b3d0; font-size: 1rem;">
                    <?= $search_results['total'] ?> results total
                    <a href="<?= $_SERVER['PHP_SELF'] ?>" style="color: #b28254; text-decoration: none; margin-left: 1rem; font-weight: 600;">
                        ← Back to Channel
                    </a>
                </div>
            </div>
            
            <?php if (empty($search_results['results'])): ?>
                <div style="text-align: center; padding: 3rem 2rem; color: #a4b3d0;">
                    <svg style="width: 4rem; height: 4rem; margin-bottom: 1rem; opacity: 0.6;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 12h6m-6-4h6m2 5.291A7.962 7.962 0 0112 15c-2.34 0-4.5-.9-6.134-2.3"></path>
                    </svg>
                    <h3 style="font-size: 1.5rem; margin-bottom: 0.5rem;">No results found</h3>
                    <p style="font-size: 1.1rem;">Try different keywords or browse our content below</p>
                    
                    <!-- Enhanced search suggestions -->
                    <div style="margin-top: 2rem; padding: 1.5rem; background: #2d3748; border-radius: 0.5rem; border: 1px solid #4a5568;">
                        <h4 style="color: #fff; margin-bottom: 1rem;">Try searching for:</h4>
                        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; justify-content: center;">
                            <?php 
                            $suggestions = ['events', 'news', 'sports', 'entertainment', 'business', 'community'];
                            foreach ($suggestions as $suggestion): ?>
                                <a href="?search=<?= urlencode($suggestion) ?>" 
                                   style="padding: 0.5rem 1rem; background: #4a5568; color: #e2e8f0; border-radius: 0.25rem; text-decoration: none; font-size: 0.9rem; transition: all 0.3s ease;">
                                    <?= htmlspecialchars($suggestion) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="carousel-container" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 1rem; overflow-x: visible;">
                    <?php 
                    // Limit to first 12 results for cleaner display
                    $display_results = array_slice($search_results['results'], 0, 12);
                    foreach ($display_results as $result): 
                        $thumbnail = $result['thumbnail'] ?? 'https://iblog.bz/assets/ionthumbnail.png';
                        $title = $result['title'] ?? 'Untitled';
                        $source = $result['source'] ?? $result['type'] ?? 'content';
                        $link = $result['link'] ?? '#';
                        $is_local = false;
                        
                        // Check if this is a local result
                        if (isset($result['location']) || 
                            (isset($result['title']) && stripos($result['title'], $city->city_name) !== false) ||
                            (isset($result['excerpt']) && stripos($result['excerpt'], $city->city_name) !== false)) {
                            $is_local = true;
                        }
                        
                        // Determine video info for modal
                        $video_info = getVideoType($link);
                        $video_type = $video_info['type'];
                        $video_id = '';
                        
                        if ($video_type === 'youtube' && strpos($link, 'youtube.com') !== false) {
                            preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $link, $matches);
                            $video_id = $matches[1] ?? '';
                        }
                    ?>
                        <div class="carousel-item <?= $is_local ? 'local-result' : '' ?>">
                            <a href="<?= $link !== '#' ? htmlspecialchars($link) : '#' ?>" 
                               <?= $result['type'] === 'video' && $video_type !== 'unknown' ? 'rel="noopener" class="video-thumb" data-video-type="' . htmlspecialchars($video_type) . '" data-video-id="' . htmlspecialchars($video_id) . '" data-video-url="' . htmlspecialchars($link) . '" data-video-title="' . htmlspecialchars($title) . '"' : 'target="_blank" rel="noopener"' ?>>
                                <img class="video-thumbnail" 
                                     src="<?= htmlspecialchars($thumbnail) ?>" 
                                     alt="<?= htmlspecialchars($title) ?>" 
                                     onerror="this.onerror=null; this.src='https://iblog.bz/assets/ionthumbnail.png';">
                                
                                <?php if ($result['type'] === 'video'): ?>
                                    <div class="play-icon-overlay">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="rgba(255,255,255,0.8)">
                                            <path d="M8 5v14l11-7z"></path>
                                        </svg>
                                    </div>
                                <?php endif; ?>
                                
                                <div style="position: absolute; top: 0.5rem; right: 0.5rem; background: rgba(31,41,55,0.9); color: <?= $is_local ? '#22c55e' : '#b28254' ?>; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.7rem; font-weight: 600; text-transform: uppercase;">
                                    <?= $is_local ? 'LOCAL' : htmlspecialchars($source) ?>
                                </div>
                                
                                <?php if ($is_local): ?>
                                    <div style="position: absolute; top: 0.5rem; left: 0.5rem; background: rgba(34,197,94,0.9); color: white; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.7rem; font-weight: 600;">
                                        📍 <?= htmlspecialchars($city->city_name) ?>
                                    </div>
                                <?php endif; ?>
                            </a>
                            <div class="video-card-info">
                                <p><?= htmlspecialchars($title) ?></p>
                                <?php if (!empty($result['excerpt'])): ?>
                                    <small style="color: #a4b3d0; display: block; margin-top: 0.5rem; line-height: 1.3;">
                                        <?= htmlspecialchars(substr($result['excerpt'], 0, 100)) ?>...
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($search_results['total'] > 12): ?>
                    <div style="text-align: center; margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #4a5568;">
                        <p style="color: #a4b3d0; margin-bottom: 1rem;">
                            Showing 12 of <?= $search_results['total'] ?> results
                        </p>
                        <a href="/search-results.php?q=<?= urlencode($search_results['query']) ?>&city=<?= urlencode($city->slug) ?>" 
                           style="display: inline-block; padding: 0.75rem 1.5rem; background: #b28254; color: #161821; border-radius: 0.375rem; text-decoration: none; font-weight: 600; transition: all 0.3s ease;">
                            View All Results
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>
    
    <!-- Enhanced styling for local results -->
    <style>
    .carousel-item.local-result {
        border: 2px solid #22c55e;
        box-shadow: 0 4px 15px rgba(34, 197, 94, 0.2);
    }
    
    .carousel-item.local-result:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(34, 197, 94, 0.3);
    }
    </style>
    
<?php 
// End search results section
endif;

// Only show regular content categories if we're not displaying search results
if (!$search_results || isset($search_results['error'])):

// Helper function to get the least used API key and increment its usage
function get_api_key($query = '') {
    global $youtube_api_keys;

    // Collect usage for all keys
    $usages = [];
    $dateToday = date('Y-m-d');
    foreach ($youtube_api_keys as $idx => $key) {
        $transientKey = 'yt_usage_' . md5($key) . '_' . $dateToday;
        $usages[$idx] = get_transient($transientKey) ?: 0;
    }

    // Sort by usage ascending to pick the least used key
    asort($usages);
    $keyIndexes = array_keys($usages);
    $keyIndex = $keyIndexes[0];
    $apiKey = $youtube_api_keys[$keyIndex];

    // Increment usage
    $usageTransientKey = 'yt_usage_' . md5($apiKey) . '_' . $dateToday;
    $usageCount = $usages[$keyIndex] + 1;
    set_transient($usageTransientKey, $usageCount, DAY_IN_SECONDS * 2);

    // Log the usage with URL
    $currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    $logMessage = "Used YouTube API key $apiKey (usage: $usageCount/15 today) for query: '$query' on URL: $currentUrl";
    error_log($logMessage);

    return $apiKey;
}

// Helper function for YouTube API requests with quota retry logic
function youtube_api_request($base_url, $params, $context) {
    global $youtube_api_keys;

    $tries = 0;
    while ($tries < count($youtube_api_keys)) {
        $apiKey = get_api_key($context);
        $full_params = $params;
        $full_params['key'] = $apiKey;
        $url = $base_url . '?' . http_build_query($full_params);

        $response = wp_remote_get($url);
        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code != 200) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            if (isset($data['error']['code']) && $data['error']['code'] == 403 && in_array($data['error']['errors'][0]['reason'], ['quotaExceeded', 'dailyLimitExceeded'])) {
                $dateToday = date('Y-m-d');
                $usageTransientKey = 'yt_usage_' . md5($apiKey) . '_' . $dateToday;
                set_transient($usageTransientKey, 9999, DAY_IN_SECONDS * 2);
                error_log("Quota exceeded for YouTube API key $apiKey in context '$context', retrying with next key...");
                $tries++;
                continue;
            }
            return new WP_Error('api_error', 'YouTube API error', $body);
        }

        return $response;
    }

    error_log("All YouTube API keys exhausted for context: $context");
    return new WP_Error('quota_exhausted', 'All API keys exhausted');
}

function fetch_youtube_videos($query, $maxResults = 6) {
    $base_url = 'https://www.googleapis.com/youtube/v3/search';
    $params = [
        'part' => 'snippet',
        'type' => 'video',
        'order' => 'date',
        'q' => $query,
        'maxResults' => $maxResults
    ];
    $response = youtube_api_request($base_url, $params, $query);
    if (is_wp_error($response)) {
        return [];
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (empty($data['items'])) {
        error_log("No videos found for query: $query");
        return [];
    }

    $videos = [];
    $videoIds = [];
    foreach ($data['items'] as $item) {
        if (!isset($item['id']['videoId'])) continue;
        $videoId = $item['id']['videoId'];
        $videoIds[] = $videoId;
        $videos[$videoId] = [
            'videoId' => $videoId,
            'title' => $item['snippet']['title'],
            'thumbnail' => $item['snippet']['thumbnails']['medium']['url'],
            'publishedAt' => $item['snippet']['publishedAt'],
            'channelId' => $item['snippet']['channelId'],
            'channelTitle' => $item['snippet']['channelTitle'],
            'description' => $item['snippet']['description'],
        ];
    }

    // Fetch additional details using videos.list
    if ($videoIds) {
        $base_url = 'https://www.googleapis.com/youtube/v3/videos';
        $params = [
            'part' => 'snippet,statistics,contentDetails',
            'id' => implode(',', $videoIds)
        ];
        $response = youtube_api_request($base_url, $params, 'videos.list for ' . $query);
        if (is_wp_error($response)) {
            return array_values($videos); // Return partial data if details fail
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (isset($data['items'])) {
            $categoryIds = [];
            foreach ($data['items'] as $item) {
                $videoId = $item['id'];
                if (!isset($videos[$videoId])) continue;
                $videos[$videoId]['tags'] = implode(', ', $item['snippet']['tags'] ?? []);
                $videos[$videoId]['categoryId'] = $item['snippet']['categoryId'] ?? '';
                $videos[$videoId]['viewCount'] = $item['statistics']['viewCount'] ?? 0;
                // Fetch transcript if captions available
                if (($item['contentDetails']['caption'] ?? 'false') === 'true') {
                    $videos[$videoId]['transcript'] = fetch_transcript($videoId);
                } else {
                    $videos[$videoId]['transcript'] = '';
                }
                if (!empty($videos[$videoId]['categoryId'])) {
                    $categoryIds[] = $videos[$videoId]['categoryId'];
                }
            }

            // Fetch category titles
            $categoryIds = array_unique($categoryIds);
            if ($categoryIds) {
                $base_url = 'https://www.googleapis.com/youtube/v3/videoCategories';
                $params = [
                    'part' => 'snippet',
                    'id' => implode(',', $categoryIds),
                    'regionCode' => 'US'
                ];
                $response = youtube_api_request($base_url, $params, 'videoCategories.list for ' . $query);
                if (!is_wp_error($response)) {
                    $body = wp_remote_retrieve_body($response);
                    $catData = json_decode($body, true);
                    if (isset($catData['items'])) {
                        $catMap = [];
                        foreach ($catData['items'] as $catItem) {
                            $catMap[$catItem['id']] = $catItem['snippet']['title'] ?? '';
                        }
                        foreach ($videos as &$video) {
                            $video['categoryTitle'] = $catMap[$video['categoryId']] ?? '';
                        }
                    }
                }
            }
        }
    }

    return array_values($videos);
}

function fetch_transcript($videoId) {
    $transcript = '';

    // List captions
    $base_url = 'https://www.googleapis.com/youtube/v3/captions';
    $params = [
        'part' => 'snippet',
        'videoId' => $videoId
    ];
    $response = youtube_api_request($base_url, $params, 'captions.list for ' . $videoId);
    if (is_wp_error($response)) {
        return $transcript;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    if (isset($data['items']) && !empty($data['items'])) {
        // Prefer English caption track
        $captionId = null;
        foreach ($data['items'] as $track) {
            if (strtolower($track['snippet']['language'] ?? '') === 'en') {
                $captionId = $track['id'];
                break;
            }
        }
        if (!$captionId) {
            $captionId = $data['items'][0]['id']; // Fallback to first
        }

        // Download caption as SRT
        $base_url = 'https://www.googleapis.com/youtube/v3/captions/' . $captionId;
        $params = ['tfmt' => 'srt'];
        $response = youtube_api_request($base_url, $params, 'captions.download for ' . $videoId);
        if (is_wp_error($response)) {
            return $transcript;
        }

        $srt = wp_remote_retrieve_body($response);
        $transcript = parse_srt_to_text($srt);
    }

    return $transcript;
}

function parse_srt_to_text($srt) {
    $text = '';
    $blocks = explode("\n\n", trim($srt));
    foreach ($blocks as $block) {
        $lines = explode("\n", $block);
        if (count($lines) >= 3) {
            // Skip index and time, take text lines
            $textLines = array_slice($lines, 2);
            $text .= implode(' ', $textLines) . ' ';
        }
    }
    return trim($text);
}

// Enhanced get_video_info function
function get_video_info($video) {
    $info = [
        'type' => 'youtube', // Default to youtube
        'id' => $video['videoId'] ?? '',
        'url' => $video['video_link'] ?? 'https://www.youtube.com/watch?v=' . ($video['videoId'] ?? ''),
        'thumbnail' => $video['thumbnail'] ?? '',
    ];

    if (!empty($video['video_link'])) {
        $url = $video['video_link'];
        $video_type_info = getVideoType($url);
        $info['type'] = $video_type_info['type'];
        
        // Check if it's a self-hosted video by URL pattern
        if ($info['type'] === 'unknown' && (strpos($url, '/uploads/') !== false || strpos($url, 'ions.com') !== false)) {
            $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
            $local_video_extensions = ['mp4', 'webm', 'ogg', 'mov', 'avi', 'm4v', 'mkv'];
            if (in_array($ext, $local_video_extensions)) {
                $info['type'] = 'local';
                $info['format'] = $ext;
                $info['id'] = md5($url);
            }
        }
        
        if ($info['type'] === 'local') {
            $info['format'] = $video_type_info['format'] ?? pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
            $info['id'] = md5($url); // Create unique ID for local videos
        } elseif ($info['type'] === 'vimeo') {
            $path_parts = explode('/', trim(parse_url($url, PHP_URL_PATH), '/'));
            $info['id'] = end($path_parts);
        } elseif ($info['type'] === 'muvi') {
            $path = parse_url($url, PHP_URL_PATH);
            $path_parts = explode('/', trim($path, '/'));
            $embed_pos = array_search('embed', $path_parts);
            if ($embed_pos !== false && isset($path_parts[$embed_pos + 1])) {
                $info['id'] = $path_parts[$embed_pos + 1];
            } else {
                $info['id'] = end($path_parts);
            }
        } elseif ($info['type'] === 'rumble') {
            $path_parts = explode('/', trim(parse_url($url, PHP_URL_PATH), '/'));
            $info['id'] = $path_parts[1] ?? end($path_parts);
        }
    }

    if (empty($info['thumbnail'])) {
        $info['thumbnail'] = home_url('/assets/ionthumbnail.png');
    }

    return $info;
}

function get_stored_videos($slug, $category, $maxResults) {
    global $pdo, $topic;
    
    // Check if we're in topic mode (topic object exists)
    $isTopicMode = isset($topic) && !empty($topic->slug);
    
    if ($isTopicMode) {
        // TOPIC MODE: Filter by ion_network and ion_category fields
        // Get the topic's network name and use it for filtering
        $topicSlug = $topic->slug;
        $topicSlugWithoutPrefix = preg_replace('/^ion-(.+)$/', '$1', $topicSlug);
        
        $stmt = $pdo->prepare("
            SELECT 
                video_id AS videoId, 
                title, 
                thumbnail, 
                video_link,
                published_at,
                channel_title,
                description,
                tags,
                view_count,
                NULL as channel_published_at,
                NULL as channel_expires_at,
                'active' as channel_status,
                0 as priority
            FROM IONLocalVideos 
            WHERE (
                ion_network = :topicSlug 
                OR ion_network = :topicSlugWithoutPrefix
                OR ion_category = :category
                OR ion_category = :topicSlugWithoutPrefix
            )
            AND status = 'Approved'
            AND visibility = 'Public'
            
            ORDER BY published_at DESC
            LIMIT :maxResults
        ");
        
        $stmt->bindParam(':topicSlug', $topicSlug, PDO::PARAM_STR);
        $stmt->bindParam(':topicSlugWithoutPrefix', $topicSlugWithoutPrefix, PDO::PARAM_STR);
        $stmt->bindParam(':category', $category, PDO::PARAM_STR);
        $stmt->bindParam(':maxResults', $maxResults, PDO::PARAM_INT);
    } else {
        // CITY MODE: Original location-based filtering
        $stmt = $pdo->prepare("
            SELECT 
                v.video_id AS videoId, 
                v.title, 
                v.thumbnail, 
                v.video_link,
                v.published_at,
                v.channel_title,
                v.description,
                v.tags,
                v.view_count,
                vc.published_at as channel_published_at,
                vc.expires_at as channel_expires_at,
                vc.status as channel_status,
                vc.priority
            FROM IONLocalVideos v
            INNER JOIN IONLocalBlast vc ON v.id = vc.video_id
            WHERE vc.channel_slug = :slug 
            AND v.ion_category = :category
            AND vc.status = 'active'
            AND (vc.published_at IS NULL OR vc.published_at <= NOW())
            AND (vc.expires_at IS NULL OR vc.expires_at > NOW())
            AND v.status = 'Approved'
            AND v.visibility = 'Public'
            
            UNION
            
            SELECT 
                video_id AS videoId, 
                title, 
                thumbnail, 
                video_link,
                published_at,
                channel_title,
                description,
                tags,
                view_count,
                NULL as channel_published_at,
                NULL as channel_expires_at,
                'active' as channel_status,
                0 as priority
            FROM IONLocalVideos 
            WHERE slug = :slug2 
            AND ion_category = :category2
            AND status = 'Approved'
            AND visibility = 'Public'
            
            ORDER BY priority DESC, channel_published_at DESC, published_at DESC
            LIMIT :maxResults
        ");
        
        $stmt->bindParam(':slug', $slug, PDO::PARAM_STR);
        $stmt->bindParam(':category', $category, PDO::PARAM_STR);
        $stmt->bindParam(':slug2', $slug, PDO::PARAM_STR);
        $stmt->bindParam(':category2', $category, PDO::PARAM_STR);
        $stmt->bindParam(':maxResults', $maxResults, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_OBJ);
    
    return $results ?: [];
}

function store_video($slug, $category, $video) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        $published_at = date('Y-m-d H:i:s', strtotime($video['publishedAt']));
        
        // First, insert/update the video in IONLocalVideos
        $stmt = $pdo->prepare("
            INSERT INTO IONLocalVideos (
                video_id, title, thumbnail, video_link, published_at, 
                channel_id, channel_title, description, tags, category_id, 
                category_title, view_count, transcript, status, visibility, 
                source, videotype, format, age, geo, upload_status
            ) VALUES (
                :video_id, :title, :thumbnail, :video_link, :published_at,
                :channel_id, :channel_title, :description, :tags, :category_id,
                :category_title, :view_count, :transcript, 'Approved', 'Public',
                'Youtube', 'Youtube', 'Video', 'Everyone', 'None', 'Completed'
            ) ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                thumbnail = VALUES(thumbnail),
                video_link = VALUES(video_link),
                published_at = VALUES(published_at),
                channel_id = VALUES(channel_id),
                channel_title = VALUES(channel_title),
                description = VALUES(description),
                tags = VALUES(tags),
                category_id = VALUES(category_id),
                category_title = VALUES(category_title),
                view_count = VALUES(view_count),
                transcript = VALUES(transcript)
        ");
        
        $stmt->execute([
            ':video_id' => $video['videoId'],
            ':title' => $video['title'],
            ':thumbnail' => $video['thumbnail'],
            ':video_link' => $video['video_link'] ?? 'https://www.youtube.com/watch?v=' . $video['videoId'],
            ':published_at' => $published_at,
            ':channel_id' => $video['channelId'] ?? '',
            ':channel_title' => $video['channelTitle'] ?? '',
            ':description' => $video['description'] ?? '',
            ':tags' => $video['tags'] ?? '',
            ':category_id' => $video['categoryId'] ?? '',
            ':category_title' => $video['categoryTitle'] ?? '',
            ':view_count' => $video['viewCount'] ?? 0,
            ':transcript' => $video['transcript'] ?? '',
        ]);
        
        // Then, insert/update the channel assignment in IONLocalBlast
        $stmt = $pdo->prepare("
            INSERT INTO IONLocalBlast (
                video_id, channel_slug, category, published_at, 
                status, priority, added_at
            ) VALUES (
                :video_id, :channel_slug, :category, :published_at,
                'active', 0, NOW()
            ) ON DUPLICATE KEY UPDATE
                published_at = VALUES(published_at),
                status = 'active',
                priority = GREATEST(priority, VALUES(priority))
        ");
        
        $stmt->execute([
            ':video_id' => $video['videoId'],
            ':channel_slug' => $slug,
            ':category' => $category,
            ':published_at' => $published_at,
        ]);
        
        $pdo->commit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error storing video {$video['videoId']} for channel {$slug}: " . $e->getMessage());
        throw $e;
    }
}

// Multi-channel video management functions


/**
 * Remove a video from a specific channel
 */
function remove_video_from_channel($video_id, $channel_slug, $category = null) {
    global $pdo;
    
    $sql = "DELETE FROM IONLocalBlast WHERE video_id = :video_id AND channel_slug = :channel_slug";
    $params = [':video_id' => $video_id, ':channel_slug' => $channel_slug];
    
    if ($category) {
        $sql .= " AND category = :category";
        $params[':category'] = $category;
    }
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

/**
 * Get all channels where a video is published
 */
function get_video_channels($video_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            vc.channel_slug,
            vc.category,
            vc.published_at,
            vc.expires_at,
            vc.status,
            vc.priority,
            n.city_name,
            n.channel_name
        FROM IONLocalBlast vc
        LEFT JOIN IONLocalNetwork n ON vc.channel_slug = n.slug
        WHERE vc.video_id = :video_id
        ORDER BY vc.priority DESC, vc.published_at DESC
    ");
    
    $stmt->execute([':video_id' => $video_id]);
    return $stmt->fetchAll(PDO::FETCH_OBJ);
}

/**
 * Update video channel schedule
 */
function update_video_channel_schedule($video_id, $channel_slug, $category, $published_at = null, $expires_at = null, $priority = null) {
    global $pdo;
    
    $updates = [];
    $params = [':video_id' => $video_id, ':channel_slug' => $channel_slug, ':category' => $category];
    
    if ($published_at !== null) {
        $updates[] = "published_at = :published_at";
        $params[':published_at'] = $published_at;
    }
    
    if ($expires_at !== null) {
        $updates[] = "expires_at = :expires_at";
        $params[':expires_at'] = $expires_at;
    }
    
    if ($priority !== null) {
        $updates[] = "priority = :priority";
        $params[':priority'] = $priority;
    }
    
    if (empty($updates)) {
        return false;
    }
    
    $sql = "UPDATE IONLocalBlast SET " . implode(', ', $updates) . 
           " WHERE video_id = :video_id AND channel_slug = :channel_slug AND category = :category";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

/**
 * Get videos scheduled for a specific time range
 */
function get_scheduled_videos($start_date = null, $end_date = null) {
    global $pdo;
    
    $start_date = $start_date ?: date('Y-m-d H:i:s');
    $end_date = $end_date ?: date('Y-m-d H:i:s', strtotime('+1 day'));
    
    $stmt = $pdo->prepare("
        SELECT 
            v.video_id,
            v.title,
            v.thumbnail,
            v.video_link,
            vc.channel_slug,
            vc.category,
            vc.published_at,
            vc.expires_at,
            vc.status,
            n.city_name,
            n.channel_name
        FROM IONLocalVideos v
        INNER JOIN IONLocalBlast vc ON v.id = vc.video_id
        LEFT JOIN IONLocalNetwork n ON vc.channel_slug = n.slug
        WHERE vc.published_at BETWEEN :start_date AND :end_date
        AND vc.status = 'scheduled'
        ORDER BY vc.published_at ASC
    ");
    
    $stmt->execute([':start_date' => $start_date, ':end_date' => $end_date]);
    return $stmt->fetchAll(PDO::FETCH_OBJ);
}

/**
 * Activate scheduled videos (run this as a cron job)
 */
function activate_scheduled_videos() {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE IONLocalBlast 
        SET status = 'active' 
        WHERE status = 'scheduled' 
        AND published_at <= NOW()
    ");
    
    return $stmt->execute();
}

/**
 * Deactivate expired videos (run this as a cron job)
 */
function deactivate_expired_videos() {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE IONLocalBlast 
        SET status = 'expired' 
        WHERE status = 'active' 
        AND expires_at IS NOT NULL 
        AND expires_at <= NOW()
    ");
    
    return $stmt->execute();
}

/**
 * Fallback function to fetch content using cURL
 */
function fetch_with_curl($url) {
    if (!function_exists('curl_init')) {
        return false;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ($httpCode === 200) ? $result : false;
}

function fetch_google_news_rss($query, $category, $max_items = 5) {
    $url = "https://news.google.com/rss/search?q=" . urlencode($query) . "&hl=en-US&gl=US&ceid=US:en";
    
    // Create SSL context to handle SSL issues
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ]);
    
    $rss = @file_get_contents($url, false, $context);
    if (!$rss) {
        // Fallback: try with cURL if file_get_contents fails
        $rss = fetch_with_curl($url);
        if (!$rss) return [];
    }
    $xml = simplexml_load_string($rss);
    if (!$xml) return [];
    $items = [];
    $i = 0;
    foreach ($xml->channel->item as $item) {
        if ($i >= $max_items) break;
        $title = (string)$item->title;
        $link = (string)$item->link;
        $date = strtotime((string)$item->pubDate);
        // Parse source from title (e.g., "Title - Source")
        $parts = explode(' - ', $title);
        $source = count($parts) > 1 ? array_pop($parts) : 'Unknown Source';
        $title = implode(' - ', $parts);
        // Image from description (if any)
        $description = (string)$item->description;
        $image_url = '';
        if (preg_match('/<img src="([^"]+)"/', $description, $matches)) {
            $image_url = $matches[1];
        }
        $items[] = [
            'title' => esc_html($title),
            'link' => esc_url($link),
            'date' => $date,
            'source' => esc_html($source),
            'image' => esc_url($image_url),
            'category' => $category
        ];
        $i++;
    }
    return $items;
}

// Main content sections - only show if not displaying search results
$location_query = "{$city->city_name}, {$city->state_name}, {$city->country_name}";
// Categories use slug format and match IONNetworks slugs
$categories = ['ion-sports-network', 'ion-entertainment-network', 'ion-business-network', 'ion-kids-network', 'ion-events-network', 'ion-news-network'];
$max_results = 10;

foreach ($categories as $category):
    $videos = get_stored_videos($slug, $category, $max_results);
    if (empty($videos)) {
        $cache_key = 'yt_' . md5($slug . $category . $max_results);
        $videos = get_transient($cache_key);
        $from_cache = ($videos !== false);
        if ($videos === false) {
            $query = "$category in $location_query";
            $videos = fetch_youtube_videos($query, $max_results);
            if (!empty($videos)) {
                set_transient($cache_key, $videos, HOUR_IN_SECONDS * 24);
            }
        }
        // Store to DB if videos found (from cache or API)
        if (!empty($videos)) {
            foreach ($videos as $video) {
                store_video($slug, $category, $video);
            }
        }
    }
    if (empty($videos)) continue; 
    // Convert slug to display name (e.g., 'ion-sports-network' → 'ION Sports™ Network')
    require_once __DIR__ . '/../includes/ioncategories.php';
    $category_display_name = get_ion_category_name_from_slug($category) ?: ucwords(str_replace(['-', 'ion', 'network'], [' ', 'ION', 'Network'], $category));
    // Remove "ION " and "™ Network" for heading since we already have network name
    $category_short = str_replace(['ION ', '™ Network'], ['', ''], $category_display_name);
    ?>
    <section class="video-carousel">
        <h2><?= esc_html($city->city_name) ?> <?= esc_html($category_short) ?></h2>
        <div class="carousel-container">
            <?php foreach ($videos as $video): 
                $video_info = get_video_info((array)$video);
                
                $preview_url = '';
                $preview_type = $video_info['type'];
                
                if ($preview_type === 'youtube') {
                    $preview_url = 'https://www.youtube.com/embed/' . esc_attr($video_info['id']) . '?autoplay=1&mute=1&controls=0&loop=1&playlist=' . esc_attr($video_info['id']);
                } elseif ($preview_type === 'vimeo') {
                    $preview_url = 'https://player.vimeo.com/video/' . esc_attr($video_info['id']) . '?autoplay=1&muted=1&background=1';
                } elseif ($preview_type === 'wistia') {
                    $preview_url = 'https://fast.wistia.net/embed/iframe/' . esc_attr($video_info['id']) . '?autoplay=1&muted=1&controls=0';
                } elseif ($preview_type === 'rumble') {
                    $preview_url = 'https://rumble.com/embed/v' . esc_attr($video_info['id']) . '/?autoplay=1&muted=1';
                } elseif ($preview_type === 'muvi') {
                    $preview_url = 'https://embed.muvi.com/embed/' . esc_attr($video_info['id']) . '?autoplay=1';
                } elseif ($preview_type === 'local') {
                    // For local videos, we'll handle preview differently
                    $preview_url = $video_info['url'];
                }
            ?>
                <div class="carousel-item">
                    <a href="<?= esc_url($video_info['url']) ?>" rel="noopener" class="video-thumb" 
                       data-video-type="<?= esc_attr($video_info['type']) ?>" 
                       data-video-id="<?= esc_attr($video_info['id']) ?>" 
                       data-video-url="<?= esc_attr($video_info['url']) ?>"
                       data-video-title="<?= esc_attr($video->title) ?>"
                       data-video-category="<?= esc_attr($category) ?>"
                       <?= $video_info['type'] === 'local' ? 'data-video-format="' . esc_attr($video_info['format']) . '"' : '' ?>
                       onclick="return false;">
                        <img class="video-thumbnail" src="<?= esc_url($video_info['thumbnail']) ?>" alt="<?= esc_attr($video->title) ?>" onerror="this.onerror=null; this.src='<?= home_url('/assets/ionthumbnail.png') ?>';">
                        <div class="play-icon-overlay">
                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="rgba(255,255,255,0.8)"><path d="M8 5v14l11-7z"></path></svg>
                        </div>
                        <?php if ($preview_type === 'local'): ?>
                            <div class="preview-video-container">
                                <video class="preview-video video-js vjs-default-skin" muted loop>
                                    <source src="<?= esc_url($preview_url) ?>" type="video/<?= esc_attr($video_info['format']) ?>">
                                </video>
                            </div>
                        <?php elseif (!empty($preview_url) && $preview_type !== 'local'): ?>
                            <iframe class="preview-iframe" loading="lazy" src="<?= $preview_url ?>" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen></iframe>
                        <?php endif; ?>
                    </a>
                    <div class="video-card-info">
                        <p><?= esc_html($video->title) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endforeach;

// News section (using simple category names for Google News RSS)
$news_cache_key = 'ion_news_' . $slug;
$all_news_items = get_transient($news_cache_key);

if ($all_news_items === false) {
    // Note: These are for Google News queries, not video categories
    $news_categories = ['Sports', 'Entertainment', 'Business', 'News'];
    $all_news_items = [];
    $location_query_news = "{$city->city_name}, {$city->state_name}";

    foreach ($news_categories as $category) {
        $query = "$category in $location_query_news";
        $news_items = fetch_google_news_rss($query, $category, 5); // get 5 per category
        if (!empty($news_items)) {
            $all_news_items = array_merge($all_news_items, $news_items);
        }
    }

    if (!empty($all_news_items)) {
        // Sort all news items by date descending
        usort($all_news_items, function($a, $b) {
            return $b['date'] <=> $a['date'];
        });

        // Take top 9 articles overall
        $all_news_items = array_slice($all_news_items, 0, 9);
        set_transient($news_cache_key, $all_news_items, HOUR_IN_SECONDS * 6); // Cache for 6 hours
    }
}

if (!empty($all_news_items)): ?>
<section class="ion-news">
    <h2>ION <?= esc_html(strtoupper($city->city_name)) ?> LATEST</h2>
    <p class="news-subtitle">Stay updated with the latest news and developments from <?= esc_html($city->city_name) ?> and the surrounding metro area</p>
    <div class="news-container">
        <?php 
        $placeholder_image = 'https://ions.com/assets/ionthumbnail.png';
        foreach ($all_news_items as $item): 
            $image_to_display = !empty($item['image']) ? $item['image'] : $placeholder_image;
        ?>
            <div class="news-item">
                <a href="<?= $item['link'] ?>" target="_blank" rel="noopener" title="<?= esc_attr($item['title']) ?>">
                    <div class="news-item-image-wrapper">
                        <span class="news-item-category"><?= esc_html($item['category']) ?></span>
                    </div>
                    <div class="news-item-content">
                        <h4><?= $item['title'] ?></h4>
                        <p class="news-source">Published: <?= date('M j, Y', $item['date']) ?></p>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif;

// End regular content (only show when not displaying search results)
endif; ?>

<!-- City/Location/Population section removed for topic pages -->

<section class="info-blocks">
    <div class="info-block blue">
        <h3>AVENUE I</h3>
        <p>Your gateway to innovative content and digital experiences</p>
    </div>
    <div class="info-block red">
        <h3>MALL OF CHAMPIONS</h3>
        <p>Celebrating excellence in sports and community achievement</p>
    </div>
    <div class="info-block green">
        <h3>CONNECT.IONS</h3>
        <p>Building bridges in the community through shared stories</p>
    </div>
</section>

<?php
// Optional: Display video tracking stats
$cityStats = $videoTracker->getStats(null, null, $slug);
if (!empty($cityStats)) {
    $totalImpressions = array_sum(array_column($cityStats, 'total_impressions'));
    $totalClicks = array_sum(array_column($cityStats, 'total_clicks'));
    $avgCTR = $totalImpressions > 0 ? round(($totalClicks / $totalImpressions) * 100, 2) : 0;
?>
<div class="video-stats" style="text-align: center; padding: 2rem; background: rgba(255,255,255,0.05); margin-top: 3rem; border-top: 1px solid rgba(255,255,255,0.1);">
    <h3 style="color: #b28254; margin-bottom: 1rem;">Channel Video Performance</h3>
    <div style="display: flex; justify-content: center; gap: 3rem; flex-wrap: wrap;">
        <div>
            <p style="color: #a4b3d0; margin: 0;">Total Views</p>
            <p style="font-size: 2rem; font-weight: bold; margin: 0;"><?= number_format($totalImpressions) ?></p>
        </div>
        <div>
            <p style="color: #a4b3d0; margin: 0;">Total Clicks</p>
            <p style="font-size: 2rem; font-weight: bold; margin: 0;"><?= number_format($totalClicks) ?></p>
        </div>
        <div>
            <p style="color: #a4b3d0; margin: 0;">Avg CTR</p>
            <p style="font-size: 2rem; font-weight: bold; margin: 0; color: <?= $avgCTR > 5 ? '#22c55e' : ($avgCTR > 2 ? '#f59e0b' : '#ef4444') ?>"><?= $avgCTR ?>%</p>
        </div>
    </div>
</div>
<?php } ?>

<!-- Video Modal -->
<div id="videoModal" class="video-modal">
  <div class="video-modal-content">
    <span class="video-close">&times;</span>
    <div id="videoContainer"></div>
  </div>
</div>

<!-- Article Modal -->
<div id="articleModal" class="article-modal">
    <div class="article-modal-content">
        <span class="article-close">&times;</span>
        <iframe id="articleIframe" width="100%" height="100%" frameborder="0"></iframe>
    </div>
</div>

<?php if ($has_slider_media && count($media) > 1): ?>
<script>
// Hero Slider JavaScript with Video.js support
document.addEventListener('DOMContentLoaded', function() {
    let currentSlide = 0;
    const slides = document.querySelectorAll('.ion-hero .slide');
    const totalSlides = slides.length;
    let slideInterval;
    let heroVideoPlayers = [];

    if (totalSlides <= 1) return; // Don't run slider for single slide

    // Initialize Video.js for hero slider videos
    slides.forEach((slide, index) => {
        const videoElement = slide.querySelector('video');
        if (videoElement && videoElement.classList.contains('video-js')) {
            const player = videojs(videoElement, {
                autoplay: index === 0,
                muted: true,
                loop: true,
                controls: false,
                preload: 'auto'
            });
            heroVideoPlayers.push({ index, player });
        }
    });

    function showSlide(index) {
        slides.forEach((slide, i) => {
            slide.classList.toggle('active', i === index);
            
            // Handle Video.js players
            heroVideoPlayers.forEach(({ index: playerIndex, player }) => {
                if (playerIndex === index) {
                    player.play();
                } else {
                    player.pause();
                    player.currentTime(0);
                }
            });
            
            // Handle YouTube/Vimeo iframes (pause via postMessage)
            const iframe = slide.querySelector('iframe');
            if (iframe && i !== index) {
                try {
                    iframe.contentWindow.postMessage('{"event":"command","func":"pauseVideo","args":""}', '*');
                } catch (e) {
                    // Ignore cross-origin errors
                }
            }
        });
    }

    function nextSlide() {
        currentSlide = (currentSlide + 1) % totalSlides;
        showSlide(currentSlide);
    }

    function prevSlide() {
        currentSlide = (currentSlide - 1 + totalSlides) % totalSlides;
        showSlide(currentSlide);
    }

    function startAutoSlide() {
        slideInterval = setInterval(nextSlide, 5000);
    }

    function stopAutoSlide() {
        if (slideInterval) {
            clearInterval(slideInterval);
            slideInterval = null;
        }
    }

    // Start auto-slide
    startAutoSlide();

    // Navigation events
    const nextBtn = document.querySelector('.ion-hero .next');
    const prevBtn = document.querySelector('.ion-hero .prev');
    
    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            stopAutoSlide();
            nextSlide();
            // Restart auto-slide after manual interaction
            setTimeout(startAutoSlide, 3000);
        });
    }
    
    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            stopAutoSlide();
            prevSlide();
            // Restart auto-slide after manual interaction
            setTimeout(startAutoSlide, 3000);
        });
    }

    // Pause auto-slide on hover
    const heroSection = document.querySelector('.ion-hero');
    if (heroSection) {
        heroSection.addEventListener('mouseenter', stopAutoSlide);
        heroSection.addEventListener('mouseleave', startAutoSlide);
    }

    // Initial show
    showSlide(currentSlide);
});
</script>
<?php endif; ?>

<script>
// Enhanced video modal functionality with Video.js support
document.addEventListener("DOMContentLoaded", function () {
    // Initialize Video.js for preview videos
    const previewVideos = document.querySelectorAll('.preview-video');
    const previewPlayers = new Map();
    
    previewVideos.forEach(video => {
        const player = videojs(video, {
            autoplay: false,
            muted: true,
            loop: true,
            controls: false,
            preload: 'none'
        });
        previewPlayers.set(video, player);
    });

    // Handle preview on hover
    document.querySelectorAll('.carousel-item').forEach(item => {
        const previewContainer = item.querySelector('.preview-video-container');
        const previewVideo = item.querySelector('.preview-video');
        const previewIframe = item.querySelector('.preview-iframe');
        let hoverTimeout = null;
        let originalIframeSrc = '';
        
        // Handle Video.js players (local videos)
        if (previewVideo && previewPlayers.has(previewVideo)) {
            const player = previewPlayers.get(previewVideo);
            
            item.addEventListener('mouseenter', () => {
                hoverTimeout = setTimeout(() => {
                    if (previewContainer) {
                        player.play().catch(err => console.log('⚠️ Video play failed:', err));
                    }
                }, 300); // 300ms delay to avoid loading on quick mouse-overs
            });
            
            item.addEventListener('mouseleave', () => {
                if (hoverTimeout) {
                    clearTimeout(hoverTimeout);
                    hoverTimeout = null;
                }
                
                setTimeout(() => {
                    if (previewContainer) {
                        player.pause();
                        player.currentTime(0);
                    }
                }, 500); // 500ms delay to avoid stopping if user quickly hovers back
            });
        }
        
        // CRITICAL FIX: Handle iframes (YouTube, Vimeo, Muvi, etc.)
        if (previewIframe) {
            // Store original src on first load
            if (!originalIframeSrc && previewIframe.src && !previewIframe.src.includes('about:blank')) {
                originalIframeSrc = previewIframe.src;
            }
            
            item.addEventListener('mouseenter', () => {
                hoverTimeout = setTimeout(() => {
                    // If iframe was stopped, restart it
                    if (previewIframe.src === 'about:blank' || previewIframe.src.includes('about:blank')) {
                        if (originalIframeSrc) {
                            console.log('🔄 Restarting stopped iframe on hover');
                            previewIframe.src = originalIframeSrc;
                        }
                    } else if (!originalIframeSrc) {
                        // First time - store the original src
                        originalIframeSrc = previewIframe.src;
                    }
                }, 300); // 300ms delay
            });
            
            item.addEventListener('mouseleave', () => {
                if (hoverTimeout) {
                    clearTimeout(hoverTimeout);
                    hoverTimeout = null;
                }
                
                // Stop iframe to prevent background audio
                setTimeout(() => {
                    if (previewIframe && originalIframeSrc && !previewIframe.src.includes('about:blank')) {
                        console.log('⏹️ Stopping iframe audio');
                        previewIframe.src = 'about:blank';
                    }
                }, 500); // 500ms delay to avoid stopping if user quickly hovers back
            });
        }
    });

    // Video Modal Functions
    const modal = document.getElementById("videoModal");
    const videoContainer = document.getElementById("videoContainer");
    const closeBtn = document.querySelector(".video-close");
    let currentModalPlayer = null;

    function openVideoModal(videoType, videoId, videoUrl, videoFormat) {
        // Clear previous content
        videoContainer.innerHTML = '';
        
        // Normalize video type - treat 'self-hosted' as 'local'
        const normalizedType = (videoType === 'self-hosted' || videoType === 'local') ? 'local' : videoType;
        
        if (normalizedType === 'local') {
            // Create Video.js player for local videos
            const videoElement = document.createElement('video');
            videoElement.className = 'video-js vjs-default-skin vjs-big-play-centered';
            videoElement.setAttribute('controls', '');
            videoElement.setAttribute('preload', 'auto');
            videoElement.style.width = '100%';
            videoElement.style.height = '100%';
            
            const sourceElement = document.createElement('source');
            sourceElement.src = videoUrl;
            sourceElement.type = `video/${videoFormat || 'mp4'}`;
            
            videoElement.appendChild(sourceElement);
            videoContainer.appendChild(videoElement);
            
            // Initialize Video.js
            currentModalPlayer = videojs(videoElement, {
                controls: true,
                autoplay: true,
                preload: 'auto',
                fluid: true,
                responsive: true
            });
            
        } else {
            // Handle streaming platforms
            const iframe = document.createElement('iframe');
            iframe.setAttribute('width', '100%');
            iframe.setAttribute('height', '100%');
            iframe.setAttribute('frameborder', '0');
            iframe.setAttribute('allowfullscreen', '');
            
            let videoSrc = '';
            switch (normalizedType) {
                case "youtube":
                    videoSrc = `https://www.youtube.com/embed/${videoId}?autoplay=1&rel=0&modestbranding=1`;
                    iframe.setAttribute('allow', 'autoplay; fullscreen; picture-in-picture; encrypted-media');
                    break;
                case "vimeo":
                    const vimeoId = videoUrl.split('/').pop();
                    videoSrc = `https://player.vimeo.com/video/${vimeoId}?autoplay=1&title=0&byline=0&portrait=0`;
                    iframe.setAttribute('allow', 'autoplay; fullscreen; picture-in-picture');
                    break;
                case "muvi":
                    videoSrc = `https://embed.muvi.com/embed/${videoId}`;
                    break;
                case "rumble":
                    videoSrc = `https://rumble.com/embed/${videoId}/?pub=4`;
                    break;
                default:
                    window.open(videoUrl, '_blank');
                    return;
            }
            
            iframe.src = videoSrc;
            videoContainer.appendChild(iframe);
        }
        
        // Show modal with animation
        modal.classList.add('is-visible');
        document.body.classList.add('modal-open');
    }

    function closeVideoModal() {
        modal.classList.remove('is-visible');
        document.body.classList.remove('modal-open');
        
        // Clean up Video.js player if it exists
        if (currentModalPlayer) {
            currentModalPlayer.dispose();
            currentModalPlayer = null;
        }
        
        // Clear container
        videoContainer.innerHTML = '';
    }

    // Make functions globally available
    window.openVideoModal = openVideoModal;
    window.closeVideoModal = closeVideoModal;

    // Enhanced video thumbnail click handlers
    document.querySelectorAll(".video-thumb").forEach(thumb => {
        thumb.addEventListener("click", function (e) {
            e.preventDefault();
            e.stopPropagation();
            
            const videoType = this.getAttribute("data-video-type");
            const videoId = this.getAttribute("data-video-id");
            const videoUrl = this.getAttribute("data-video-url");
            let videoFormat = this.getAttribute("data-video-format");
            
            // Handle both 'local' and 'self-hosted' as local videos
            const isLocalVideo = videoType === 'local' || videoType === 'self-hosted';
            const effectiveVideoType = isLocalVideo ? 'local' : videoType;
            
            // Extract format from URL if not provided
            if (isLocalVideo && !videoFormat && videoUrl) {
                const urlParts = videoUrl.split('.');
                videoFormat = urlParts[urlParts.length - 1].toLowerCase();
            }
            
            console.log('Opening video modal:', {
                type: effectiveVideoType,
                id: videoId,
                url: videoUrl,
                format: videoFormat
            });
            
            openVideoModal(effectiveVideoType, videoId, videoUrl, videoFormat);
            return false;
        });
    });

    // Modal event listeners
    closeBtn.addEventListener('click', closeVideoModal);
    
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeVideoModal();
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.classList.contains('is-visible')) {
            closeVideoModal();
        }
    });

    // Article Modal functionality
    const articleModal = document.getElementById("articleModal");
    const articleIframe = document.getElementById("articleIframe");
    const closeArticleBtn = document.querySelector(".article-close");

    document.querySelectorAll(".news-item a").forEach(link => {
        link.addEventListener("click", function (e) {
            e.preventDefault();
            const articleUrl = this.getAttribute("href");
            articleIframe.src = articleUrl;
            articleModal.style.display = "block";
        });
    });

    closeArticleBtn.onclick = function () {
        articleModal.style.display = "none";
        articleIframe.src = "";
    };

    window.onclick = function (event) {
        if (event.target == articleModal) {
            articleModal.style.display = "none";
            articleIframe.src = "";
        }
    };
    
    // Enhanced search result interactions
    document.querySelectorAll('.carousel-item.local-result').forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px) scale(1.02)';
        });
        
        item.addEventListener('mouseleave', function() {
            this.style.transform = '';
        });
    });
});
</script>

<?php 
include __DIR__ . '/ionfooter.php';  // Include the footer
?>
</body>
</html>