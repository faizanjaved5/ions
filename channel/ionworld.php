<?php
/**
 * ION Geographic Hierarchy - Level 1: Countries
 * Display all countries with their flags, sorted by size or alphabetically
 */

if (!isset($pdo)) {
    die('Direct access not allowed');
}

// Detect theme preference (default to dark)
$theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'dark';

// Check if current viewer is logged in (for navbar)
$current_user_id = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$is_viewer_logged_in = !empty($current_user_id);
$current_viewer = null;

if ($is_viewer_logged_in) {
    try {
        $stmt = $pdo->prepare("SELECT user_id, handle, fullname, email, photo_url FROM IONEERS WHERE user_id = ?");
        $stmt->execute([$current_user_id]);
        $current_viewer = $stmt->fetch(PDO::FETCH_OBJ);
    } catch (Exception $e) {
        error_log('Error fetching current viewer data: ' . $e->getMessage());
    }
}

// Fetch all unique countries from IONLocalNetwork
$sql = "SELECT 
    country_code,
    MAX(country_name) as country_name,
    COUNT(DISTINCT id) as location_count,
    SUM(CAST(population AS UNSIGNED)) as total_population,
    COUNT(DISTINCT state_code) as region_count
FROM IONLocalNetwork
WHERE country_code IS NOT NULL 
  AND country_code != ''
  AND country_name IS NOT NULL
  AND country_name != ''
GROUP BY country_code
ORDER BY total_population DESC, country_name ASC";

$stmt = $pdo->query($sql);
$countries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Map country codes to emoji flags and slugs
$countryData = [
    'US' => ['emoji' => '🇺🇸', 'slug' => 'united-states'],
    'CA' => ['emoji' => '🇨🇦', 'slug' => 'canada'],
    'MX' => ['emoji' => '🇲🇽', 'slug' => 'mexico'],
    'GB' => ['emoji' => '🇬🇧', 'slug' => 'united-kingdom'],
    'AU' => ['emoji' => '🇦🇺', 'slug' => 'australia'],
    'NZ' => ['emoji' => '🇳🇿', 'slug' => 'new-zealand'],
    'DE' => ['emoji' => '🇩🇪', 'slug' => 'germany'],
    'FR' => ['emoji' => '🇫🇷', 'slug' => 'france'],
    'ES' => ['emoji' => '🇪🇸', 'slug' => 'spain'],
    'IT' => ['emoji' => '🇮🇹', 'slug' => 'italy'],
    'JP' => ['emoji' => '🇯🇵', 'slug' => 'japan'],
    'CN' => ['emoji' => '🇨🇳', 'slug' => 'china'],
    'IN' => ['emoji' => '🇮🇳', 'slug' => 'india'],
    'BR' => ['emoji' => '🇧🇷', 'slug' => 'brazil'],
    'AR' => ['emoji' => '🇦🇷', 'slug' => 'argentina'],
    'ZA' => ['emoji' => '🇿🇦', 'slug' => 'south-africa'],
    'AE' => ['emoji' => '🇦🇪', 'slug' => 'uae'],
    'BG' => ['emoji' => '🇧🇬', 'slug' => 'bulgaria'],
    'IS' => ['emoji' => '🇮🇸', 'slug' => 'iceland'],
    'PH' => ['emoji' => '🇵🇭', 'slug' => 'philippines'],
    'SA' => ['emoji' => '🇸🇦', 'slug' => 'saudi-arabia'],
    'GR' => ['emoji' => '🇬🇷', 'slug' => 'greece'],
    'RS' => ['emoji' => '🇷🇸', 'slug' => 'serbia'],
    'BA' => ['emoji' => '🇧🇦', 'slug' => 'bosnia-herzegovina'],
    'HR' => ['emoji' => '🇭🇷', 'slug' => 'croatia'],
    'CH' => ['emoji' => '🇨🇭', 'slug' => 'switzerland'],
    'PK' => ['emoji' => '🇵🇰', 'slug' => 'pakistan'],
    'ID' => ['emoji' => '🇮🇩', 'slug' => 'indonesia'],
];

// Countries with states (show regions)
$countriesWithStates = ['US', 'CA', 'MX'];

// Description templates
$descriptions = [
    'US' => 'Explore states and popular towns across America',
    'CA' => 'Discover provinces and cities throughout Canada',
    'MX' => 'Navigate states and cities across Mexico',
    'GB' => 'Browse regions and towns in the UK',
    'AU' => 'Navigate states and territories of Australia',
    'FR' => 'Discover cities and towns in France',
    'DE' => 'Discover cities and towns in Germany',
    'ES' => 'Discover cities and towns in Spain',
    'BG' => 'Discover cities and towns in Bulgaria',
    'AE' => 'Discover cities and towns in UAE',
    'PK' => 'Discover cities and towns in Pakistan',
    'ID' => 'Discover cities and towns in Indonesia',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ION Explore Locations</title>
    <meta name="description" content="Navigate through countries, states, and discover the best towns worldwide">
    
    <!-- ION Navbar Embed: fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap" rel="stylesheet">
    <link rel="preload" as="style" href="/menu/ion-navbar.css">
    
    <link rel="stylesheet" href="/channel/ionchannel.css">
</head>
<body data-theme="<?= htmlspecialchars($theme) ?>" class="<?= $theme === 'dark' ? 'dark' : '' ?>">

<!-- ION Navbar (React component loaded via JavaScript) -->
<div id="ion-navbar-root"></div>

<!-- ION Navbar Embed: script setup -->
<script>
    window.process = window.process || { env: { NODE_ENV: 'production' } };
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
    })();
</script>

    <div class="container">
        <header class="header">
            <h1 class="header-title">Explore Locations</h1>
            <p class="header-subtitle">Navigate through countries, states, and discover the best towns</p>
        </header>

        <div class="grid grid-cols-2">
            <?php foreach ($countries as $country): 
                $code = $country['country_code'];
                $name = $country['country_name'];
                
                // Skip if country name is invalid
                if (empty($name) || $name === 'ION' || strlen($name) < 3) {
                    continue;
                }
                
                $emoji = $countryData[$code]['emoji'] ?? '🏳️';
                $slug = $countryData[$code]['slug'] ?? strtolower(str_replace(' ', '-', $name));
                $regionCount = (int)$country['region_count'];
                $locationCount = (int)$country['location_count'];
                
                // Determine if this country has states/provinces
                $hasStates = in_array($code, $countriesWithStates);
                
                // Description
                $description = $descriptions[$code] ?? "Discover cities and towns in $name";
                
                // Meta text
                if ($hasStates && $regionCount > 1) {
                    $metaText = "$regionCount regions available";
                } else {
                    $metaText = "$locationCount location" . ($locationCount != 1 ? 's' : '') . " available";
                }
            ?>
                <a href="/ion-<?= htmlspecialchars($slug) ?>" class="card country-card">
                    <div class="card-header">
                        <div class="card-gradient"></div>
                        <div>
                            <div class="card-title-wrapper">
                                <span class="card-flag">
                                    <img src="/assets/flags/<?= strtolower($code) ?>.svg" 
                                         alt="<?= htmlspecialchars($name) ?>" 
                                         style="width: 2.5rem; height: 1.875rem; object-fit: cover;">
                                </span>
                                <h2 class="card-title card-title-large">ION <?= htmlspecialchars($name) ?></h2>
                            </div>
                            <p class="card-description"><?= htmlspecialchars($description) ?></p>
                            <p class="card-meta">
                                <span class="card-meta-dot"></span>
                                <?= htmlspecialchars($metaText) ?>
                            </p>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="ionchannel.js"></script>
</body>
</html>
