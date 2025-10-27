<?php
/**
 * ION Geographic Hierarchy - Level 2: States/Provinces
 * Display states/provinces for a specific country
 */

if (!isset($pdo) || !isset($countryCode)) {
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

// Fetch country details
$stmt = $pdo->prepare("
    SELECT country_name, country_code
    FROM IONLocalNetwork
    WHERE country_code = :code
    LIMIT 1
");
$stmt->execute([':code' => $countryCode]);
$countryData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$countryData) {
    http_response_code(404);
    echo "Country not found";
    exit;
}

$countryName = $countryData['country_name'];

// Fetch all states/provinces for this country
$sql = "SELECT 
    state_code,
    state_name,
    country_code,
    COUNT(DISTINCT id) as city_count,
    SUM(CAST(population AS UNSIGNED)) as total_population
FROM IONLocalNetwork
WHERE country_code = :code
  AND state_name IS NOT NULL
  AND state_name != ''
GROUP BY state_code, state_name, country_code
ORDER BY total_population DESC, state_name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([':code' => $countryCode]);
$states = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Country emoji flags
$countryEmojis = [
    'US' => '🇺🇸',
    'CA' => '🇨🇦',
    'MX' => '🇲🇽',
    'GB' => '🇬🇧',
    'AU' => '🇦🇺',
];
$countryEmoji = $countryEmojis[$countryCode] ?? '🏳️';

// Determine grid columns (US uses 4, others use 3)
$gridCols = ($countryCode === 'US') ? 'grid-cols-4' : 'grid-cols-3';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ION <?= htmlspecialchars($countryName) ?> - States</title>
    <meta name="description" content="Select a state to explore popular towns and cities across <?= htmlspecialchars($countryName) ?>">
    
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
            <div class="header-title-wrapper">
                <a href="/channel/" class="header-flag" style="text-decoration:none;cursor:pointer;display:inline-block;" title="Back to All Countries">
                    <img src="/assets/flags/<?= strtolower($countryCode) ?>.svg" 
                         alt="<?= htmlspecialchars($countryName) ?>" 
                         style="width: 3rem; height: 2.25rem; object-fit: cover;transition:opacity 0.2s;"
                         onmouseover="this.style.opacity='0.7'"
                         onmouseout="this.style.opacity='1'">
                </a>
                <h1 class="header-title">ION <?= htmlspecialchars($countryName) ?></h1>
            </div>
            <p class="header-subtitle">Select a state to explore popular towns and cities</p>
        </header>

        <div class="grid <?= $gridCols ?>">
            <?php foreach ($states as $state): 
                $stateName = $state['state_name'];
                $stateSlug = 'ion-' . strtolower(str_replace(' ', '-', $stateName));
            ?>
                <a href="/<?= htmlspecialchars($stateSlug) ?>" class="card">
                    <div class="card-header card-header-compact">
                        <div class="card-gradient"></div>
                        <div class="card-title-wrapper">
                            <span class="card-flag">🏴</span>
                            <h2 class="card-title">ION <?= htmlspecialchars($stateName) ?></h2>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
            
            <?php if (empty($states)): ?>
                <div class="card">
                    <div class="card-header">
                        <div class="card-gradient"></div>
                        <div>
                            <h2 class="card-title">No States/Provinces Found</h2>
                            <p class="card-description">This country doesn't have any states or provinces in our network yet.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="ionchannel.js"></script>
</body>
</html>
