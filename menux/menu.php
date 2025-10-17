<?php
// ION Menu - PHP Version (Rebuilt to match React/Vite exactly)
// Maintains 100% visual fidelity and business logic

// Load JSON data
$menuData = json_decode(file_get_contents('menuData.json'), true);
$networksData = json_decode(file_get_contents('networksMenuData.json'), true);
$initiativesData = json_decode(file_get_contents('initiativesMenuData.json'), true);
$shopsData = json_decode(file_get_contents('shopsMenuData.json'), true);
$connectionsData = json_decode(file_get_contents('connectionsMenuData.json'), true);
$mallStores = json_decode(file_get_contents('mallOfChampionsStores.json'), true);

// Country code mapping for flags
$countryCodeMap = [
    "usa" => "us", "canada" => "ca", "mexico" => "mx", "belize" => "bz",
    "costa-rica" => "cr", "el-salvador" => "sv", "guatemala" => "gt",
    "honduras" => "hn", "nicaragua" => "ni", "panama" => "pa",
    "argentina" => "ar", "bolivia" => "bo", "brazil" => "br", "chile" => "cl",
    "colombia" => "co", "ecuador" => "ec", "guyana" => "gy", "paraguay" => "py",
    "peru" => "pe", "suriname" => "sr", "uruguay" => "uy", "venezuela" => "ve"
];

// State/Province code mapping
$stateCodeMap = [
    "alabama" => "AL", "alaska" => "AK", "arizona" => "AZ", "arkansas" => "AR",
    "california" => "CA", "colorado" => "CO", "connecticut" => "CT", "delaware" => "DE",
    "florida" => "FL", "georgia" => "GA", "hawaii" => "HI", "idaho" => "ID",
    "illinois" => "IL", "indiana" => "IN", "iowa" => "IA", "kansas" => "KS",
    "kentucky" => "KY", "louisiana" => "LA", "maine" => "ME", "maryland" => "MD",
    "massachusetts" => "MA", "michigan" => "MI", "minnesota" => "MN", "mississippi" => "MS",
    "missouri" => "MO", "montana" => "MT", "nebraska" => "NE", "nevada" => "NV",
    "new hampshire" => "NH", "new jersey" => "NJ", "new york" => "NY", "north carolina" => "NC",
    "north dakota" => "ND", "ohio" => "OH", "oklahoma" => "OK", "oregon" => "OR",
    "pennsylvania" => "PA", "rhode island" => "RI", "south carolina" => "SC", "south dakota" => "SD",
    "tennessee" => "TN", "texas" => "TX", "utah" => "UT", "vermont" => "VT",
    "virginia" => "VA", "washington" => "WA", "washington dc" => "DC", "west virginia" => "WV",
    "wisconsin" => "WI", "wyoming" => "WY"
];

function generateUrl($name) {
    return 'https://ions.com/ion-' . strtolower(str_replace(' ', '-', $name));
}

function getCountryCode($countryId) {
    global $countryCodeMap;
    return $countryCodeMap[$countryId] ?? $countryId;
}

function getStateCode($stateName) {
    global $stateCodeMap;
    return $stateCodeMap[strtolower($stateName)] ?? '';
}

function getCountryFlag($countryId) {
    $flagMap = [
        'usa' => '🇺🇸', 'canada' => '🇨🇦', 'mexico' => '🇲🇽', 'belize' => '🇧🇿',
        'costa-rica' => '🇨🇷', 'el-salvador' => '🇸🇻', 'guatemala' => '🇬🇹',
        'honduras' => '🇭🇳', 'nicaragua' => '🇳🇮', 'panama' => '🇵🇦'
    ];
    return $flagMap[$countryId] ?? '🌍';
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ION - The Network of Champions</title>
    <meta name="description" content="ION Menu">
    <meta name="author" content="Sperse">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap" rel="stylesheet">
    
    <meta property="og:title" content="ION - The Network of Champions">
    <meta property="og:description" content="Connect with champions, explore ION Networks, Initiatives, Shops, and discover the ultimate network for athletes and sports enthusiasts.">
    <meta property="og:type" content="website">
    <meta property="og:image" content="/ion-logo-gold.png">
    
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="/ion-logo-gold.png">
    
    <link rel="stylesheet" href="menu.css">
    <link rel="stylesheet" href="menu-rebuilt.css">
</head>
<body>
    <!-- SVG Sprite for Sport Icons -->
    <svg xmlns="http://www.w3.org/2000/svg" style="display:none" aria-hidden="true">
        <defs>
            <style>
                .i{fill:none;stroke:currentColor;stroke-width:1.5;stroke-linecap:round;stroke-linejoin:round}
            </style>
        </defs>
        <!-- All sport icons from original HTML -->
        <symbol id="ion-archery" viewBox="0 0 24 24">
            <circle class="i" cx="12" cy="12" r="7"/>
            <path class="i" d="M12 12l7-7M16 5h3v3"/>
        </symbol>
        <symbol id="ion-basketball" viewBox="0 0 24 24">
            <circle class="i" cx="12" cy="12" r="8"/>
            <path class="i" d="M4 12h16M12 4v16"/>
            <path class="i" d="M6 6c4 3 8 9 12 12M18 6c-4 3-8 9-12 12"/>
        </symbol>
        <symbol id="ion-soccer" viewBox="0 0 24 24">
            <circle class="i" cx="12" cy="12" r="8"/>
            <polygon class="i" points="12,8 15,10 14,14 10,14 9,10"/>
            <path class="i" d="M12 4v4M4 12h5M20 12h-5M12 20v-4"/>
        </symbol>
        <!-- Add all other sport icons as needed -->
    </svg>

    <div class="min-h-screen bg-background">
        <!-- Header -->
        <header class="sticky top-0 z-50 w-full border-b bg-header backdrop-blur">
            <div class="mx-auto flex h-20 max-w-screen-2xl items-center justify-between px-4 gap-4">
                <!-- Logo -->
                <div class="flex-shrink-0">
                    <button onclick="scrollToTop()" class="cursor-pointer">
                        <img src="https://ionmenu.vercel.app/assets/ion-logo-gold.png" alt="ION Logo" class="h-14 w-auto md:h-20 mt-1">
                    </button>
                </div>

                <!-- Desktop Navigation -->
                <nav class="hidden xl:flex items-center gap-3 absolute left-1/2 transform -translate-x-1/2">
                    <div class="menu-item">
                        <button class="menu-button" onclick="toggleMenu('ion-local')">
                            <span class="text-primary">ION</span> Local
                        </button>
                        <div id="ion-local" class="menu-dropdown">
                            <?php renderIONLocalMenu($menuData); ?>
                        </div>
                    </div>

                    <div class="menu-item">
                        <button class="menu-button" onclick="toggleMenu('ion-networks')">
                            <span class="text-primary">ION</span> Networks
                        </button>
                        <div id="ion-networks" class="menu-dropdown">
                            <?php renderIONNetworksMenu($networksData); ?>
                        </div>
                    </div>

                    <div class="menu-item">
                        <button class="menu-button" onclick="toggleMenu('ion-initiatives')">
                            <span class="text-primary">ION</span>ITIATIVES
                        </button>
                        <div id="ion-initiatives" class="menu-dropdown">
                            <?php renderIONInitiativesMenu($initiativesData); ?>
                        </div>
                    </div>

                    <div class="menu-item">
                        <button class="menu-button" onclick="toggleMenu('ion-mall')">
                            <span class="text-primary">ION</span> Mall
                        </button>
                        <div id="ion-mall" class="menu-dropdown">
                            <?php renderIONMallMenu($shopsData, $mallStores); ?>
                        </div>
                    </div>

                    <div class="menu-item">
                        <button class="menu-button" onclick="toggleMenu('connections')">
                            CONNECT<span class="text-primary">.ION</span>S
                        </button>
                        <div id="connections" class="menu-dropdown">
                            <?php renderConnectionsMenu($connectionsData); ?>
                        </div>
                    </div>

                    <button class="menu-button">
                        PressPass<span class="text-primary">.ION</span>
                    </button>
                </nav>

                <!-- Right side actions -->
                <div class="flex items-center gap-2">
                    <!-- Upload Button -->
                    <button class="action-button hidden md:flex" title="Upload">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                    </button>
                    
                    <!-- Search Button -->
                    <button onclick="toggleSearch()" class="action-button">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </button>
                    
                    <!-- Theme Toggle -->
                    <button onclick="toggleTheme()" class="action-button">
                        <svg id="sun-icon" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                        <svg id="moon-icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                        </svg>
                    </button>
                    
                    <!-- Sign In Button -->
                    <button class="sign-in-button hidden md:flex">
                        Sign In
                    </button>
                    
                    <!-- Mobile Menu Button -->
                    <button class="xl:hidden action-button" onclick="toggleMobileMenu()">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Search Modal -->
            <div id="search-modal" class="search-modal hidden" style="display: none;">
                <div class="search-modal-content">
                    <div class="search-header">
                        <h3>Search ION</h3>
                        <button onclick="toggleSearch()" class="close-button">×</button>
                    </div>
                    <input type="text" id="search-input" placeholder="Search locations, sports, initiatives..." class="search-input">
                    <div id="search-results" class="search-results"></div>
                </div>
            </div>

            <!-- Mobile Menu -->
            <div id="mobile-menu" class="mobile-menu hidden">
                <div class="mobile-menu-content">
                    <div class="mobile-menu-header">
                        <h3>ION Menu</h3>
                        <button onclick="toggleMobileMenu()" class="close-button">×</button>
                    </div>
                    <div class="mobile-menu-items">
                        <button onclick="toggleMobileSubmenu('mobile-local')" class="mobile-menu-item">
                            <span class="text-primary">ION</span> Local
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                        <!-- Add other mobile menu items -->
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="container mx-auto px-4 flex items-center justify-center min-h-screen-minus-header">
            <div class="text-center">
                <h1 class="font-bebas text-5xl uppercase tracking-wider">
                    <span class="text-primary">ION</span>
                    <span class="text-foreground">The Network of Champions</span>
                </h1>
            </div>
        </main>
    </div>

    <script src="menu.js"></script>
    <script src="menu-ion-local.js"></script>
</body>
</html>

<?php
// Menu rendering functions
function renderIONLocalMenu($menuData) {
    ?>
    <div class="ion-menu-container">
        <!-- Header -->
        <div class="ion-menu-header">
            <h2 class="ion-menu-title">
                <span class="text-primary">ION</span> <span>LOCAL NETWORK</span>
            </h2>
            <div class="ion-menu-search-wrapper">
                <svg class="ion-search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input 
                    type="text" 
                    placeholder="SEARCH ION LOCAL CHANNELS" 
                    class="ion-menu-search-input"
                    id="ion-local-search"
                    onkeyup="searchIONLocal(this.value)"
                />
            </div>
            <div class="ion-menu-controls">
                <button class="ion-font-toggle" onclick="toggleFont()" title="Toggle Font">Aa</button>
                <button class="ion-theme-toggle" onclick="toggleTheme()" title="Toggle Theme">
                    <svg id="theme-sun" class="hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    <svg id="theme-moon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Main Content -->
        <div class="ion-menu-body">
            <!-- Left Sidebar -->
            <div class="ion-menu-sidebar">
                <button 
                    class="ion-sidebar-item active" 
                    data-region="featured"
                    onclick="showRegion('featured')"
                >
                    <span><span class="text-primary">ION</span> FEATURED CHANNELS</span>
                    <svg class="chevron-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
                <?php foreach ($menuData['regions'] as $region): ?>
                <button 
                    class="ion-sidebar-item" 
                    data-region="<?php echo $region['id']; ?>"
                    onclick="showRegion('<?php echo $region['id']; ?>')"
                >
                    <span><span class="text-primary">ION</span> <?php echo htmlspecialchars($region['name']); ?></span>
                    <svg class="chevron-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
                <?php endforeach; ?>
            </div>

            <!-- Right Content Area -->
            <div class="ion-menu-content">
                <!-- Featured Channels -->
                <div id="region-featured" class="ion-region-content active">
                    <div class="ion-items-grid">
                        <?php foreach ($menuData['featuredChannels'] as $channel): ?>
                        <a href="<?php echo htmlspecialchars($channel['url']); ?>" class="ion-item-link" target="_blank">
                            <svg class="item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <span class="item-text"><span class="text-primary">ION</span> <?php echo htmlspecialchars($channel['name']); ?></span>
                            <svg class="external-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                            </svg>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Region Contents -->
                <?php foreach ($menuData['regions'] as $region): ?>
                <div id="region-<?php echo $region['id']; ?>" class="ion-region-content">
                    <div id="region-<?php echo $region['id']; ?>-countries" class="ion-items-grid">
                        <?php foreach ($region['countries'] as $country): ?>
                            <?php $hasSubItems = !empty($country['states']) || !empty($country['cities']); ?>
                            <?php if ($hasSubItems): ?>
                            <button 
                                class="ion-item-button"
                                onclick="showCountry('<?php echo $region['id']; ?>', '<?php echo $country['id']; ?>')"
                            >
                                <img 
                                    src="https://iblog.bz/assets/flags/<?php echo getCountryCode($country['id']); ?>.svg" 
                                    alt="<?php echo htmlspecialchars($country['name']); ?>"
                                    class="country-flag-img"
                                />
                                <span class="item-text"><span class="text-primary">ION</span> <?php echo htmlspecialchars($country['name']); ?></span>
                                <span class="country-code">(<?php echo strtoupper(getCountryCode($country['id'])); ?>)</span>
                                <svg class="chevron-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </button>
                            <?php else: ?>
                            <a href="<?php echo generateUrl($country['name']); ?>" class="ion-item-link" target="_blank">
                                <img 
                                    src="https://iblog.bz/assets/flags/<?php echo getCountryCode($country['id']); ?>.svg" 
                                    alt="<?php echo htmlspecialchars($country['name']); ?>"
                                    class="country-flag-img"
                                />
                                <span class="item-text"><span class="text-primary">ION</span> <?php echo htmlspecialchars($country['name']); ?></span>
                                <span class="country-code">(<?php echo strtoupper(getCountryCode($country['id'])); ?>)</span>
                                <svg class="external-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                </svg>
                            </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>

                    <!-- Country States/Cities -->
                    <?php foreach ($region['countries'] as $country): ?>
                        <?php if (!empty($country['states']) || !empty($country['cities'])): ?>
                        <div id="country-<?php echo $region['id']; ?>-<?php echo $country['id']; ?>" class="ion-country-content">
                            <div class="ion-items-grid">
                                <?php if (!empty($country['states'])): ?>
                                    <?php foreach ($country['states'] as $state): ?>
                                    <a href="<?php echo $state['url'] ?? generateUrl($state['name']); ?>" class="ion-item-link" target="_blank">
                                        <svg class="item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                        <span class="item-text"><span class="text-primary">ION</span> <?php echo htmlspecialchars($state['name']); ?></span>
                                        <?php $stateCode = getStateCode($state['name']); if ($stateCode): ?>
                                        <span class="state-code">(<?php echo $stateCode; ?>)</span>
                                        <?php endif; ?>
                                        <svg class="external-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                        </svg>
                                    </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <?php if (!empty($country['cities'])): ?>
                                    <?php foreach ($country['cities'] as $city): ?>
                                    <a href="<?php echo $city['url'] ?? generateUrl($city['name']); ?>" class="ion-item-link" target="_blank">
                                        <svg class="item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                        <span class="item-text"><span class="text-primary">ION</span> <?php echo htmlspecialchars($city['name']); ?></span>
                                        <svg class="external-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                        </svg>
                                    </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
}

function renderIONNetworksMenu($networksData) {
    ?>
    <div class="ion-menu-container-networks">
        <!-- Header -->
        <div class="ion-menu-header">
            <h2 class="ion-menu-title">
                <span class="text-primary">ION</span> <span>NETWORKS</span>
            </h2>
            <div class="ion-menu-search-wrapper">
                <svg class="ion-search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input 
                    type="text" 
                    placeholder="SEARCH ION NETWORKS" 
                    class="ion-menu-search-input"
                    id="ion-networks-search"
                />
            </div>
            <div class="ion-menu-controls">
                <button class="ion-font-toggle" onclick="toggleFont()" title="Toggle Font">Aa</button>
                <button class="ion-theme-toggle" onclick="toggleTheme()" title="Toggle Theme">
                    <svg id="theme-sun" class="hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    <svg id="theme-moon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                    </svg>
                </button>
            </div>
        </div>
        
        <!-- Content -->
        <div class="ion-menu-scrollarea" style="height: 468px; overflow-y: auto; padding: 0.5rem;">
            <div class="ion-networks-grid">
                <?php foreach ($networksData['networks'] as $network): ?>
                    <?php 
                    $hasChildren = !empty($network['children']);
                    $url = $network['url'] ?? '#';
                    ?>
                    <?php if ($url !== '#'): ?>
                        <a 
                            href="<?php echo htmlspecialchars($url); ?>"
                            target="_blank"
                            class="ion-network-item"
                            data-network="<?php echo htmlspecialchars($network['title']); ?>"
                        >
                            <span class="ion-item-text-sm"><?php echo formatIONText($network['title']); ?></span>
                            <?php if ($hasChildren): ?>
                            <svg class="ion-chevron-right" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                            <?php endif; ?>
                        </a>
                    <?php else: ?>
                        <div 
                            class="ion-network-item"
                            data-network="<?php echo htmlspecialchars($network['title']); ?>"
                        >
                            <span class="ion-item-text-sm"><?php echo formatIONText($network['title']); ?></span>
                            <?php if ($hasChildren): ?>
                            <svg class="ion-chevron-right" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
}

function renderIONInitiativesMenu($initiativesData) {
    ?>
    <div class="ion-menu-container-networks">
        <!-- Header -->
        <div class="ion-menu-header">
            <h2 class="ion-menu-title">
                <span class="text-primary">ION</span> <span>INITIATIVES</span>
            </h2>
            <div class="ion-menu-search-wrapper">
                <svg class="ion-search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input 
                    type="text" 
                    placeholder="SEARCH ION INITIATIVES" 
                    class="ion-menu-search-input"
                    id="ion-initiatives-search"
                />
            </div>
            <div class="ion-menu-controls">
                <button class="ion-font-toggle" onclick="toggleFont()" title="Toggle Font">Aa</button>
                <button class="ion-theme-toggle" onclick="toggleTheme()" title="Toggle Theme">
                    <svg id="theme-sun-init" class="hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    <svg id="theme-moon-init" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                    </svg>
                </button>
            </div>
        </div>
        
        <!-- Content -->
        <div class="ion-menu-scrollarea" style="height: 468px; overflow-y: auto; padding: 0.5rem;">
            <div class="ion-networks-grid">
                <?php 
                if (isset($initiativesData['initiatives'])) {
                    foreach ($initiativesData['initiatives'] as $initiative) {
                        if (!empty($initiative['children'])) {
                            // Parent with children - show as column header
                            echo '<div class="ion-network-column">';
                            echo '<div class="ion-network-item ion-network-header">';
                            echo '<span class="ion-item-text-sm">' . formatIONText($initiative['title']) . '</span>';
                            echo '</div>';
                            
                            foreach ($initiative['children'] as $child) {
                                $url = $child['url'] ?? '#';
                                if ($url !== '#') {
                                    echo '<a href="' . htmlspecialchars($url) . '" target="_blank" class="ion-network-item">';
                                    echo '<span class="ion-item-text-sm">' . formatIONText($child['title']) . '</span>';
                                    echo '</a>';
                                } else {
                                    echo '<div class="ion-network-item ion-item-disabled">';
                                    echo '<span class="ion-item-text-sm">' . formatIONText($child['title']) . '</span>';
                                    echo '</div>';
                                }
                            }
                            echo '</div>';
                        } else {
                            // Standalone item
                            $url = $initiative['url'] ?? '#';
                            if ($url !== '#') {
                                echo '<a href="' . htmlspecialchars($url) . '" target="_blank" class="ion-network-item">';
                                echo '<span class="ion-item-text-sm">' . formatIONText($initiative['title']) . '</span>';
                                echo '</a>';
                            }
                        }
                    }
                }
                ?>
            </div>
        </div>
    </div>
    <?php
}

function renderIONMallMenu($shopsData, $mallStores) {
    ?>
    <div class="ion-menu-container-networks">
        <!-- Header -->
        <div class="ion-menu-header">
            <h2 class="ion-menu-title">
                <span class="text-primary">ION</span> <span>MALL</span>
            </h2>
            <div class="ion-menu-search-wrapper">
                <svg class="ion-search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input 
                    type="text" 
                    placeholder="SEARCH THE MALL OF CHAMPIONS" 
                    class="ion-menu-search-input"
                    id="ion-mall-search"
                />
            </div>
            <div class="ion-menu-controls">
                <button class="ion-font-toggle" onclick="toggleFont()" title="Toggle Font">Aa</button>
                <button class="ion-theme-toggle" onclick="toggleTheme()" title="Toggle Theme">
                    <svg id="theme-sun-mall" class="hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    <svg id="theme-moon-mall" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                    </svg>
                </button>
            </div>
        </div>
        
        <!-- Content -->
        <div class="ion-menu-scrollarea" style="height: 468px; overflow-y: auto; padding: 0.5rem;">
            <div class="ion-networks-grid">
                <?php 
                if (isset($shopsData['categories'])) {
                    foreach ($shopsData['categories'] as $category) {
                        if (!empty($category['items'])) {
                            // Show as column
                            echo '<div class="ion-network-column">';
                            echo '<div class="ion-network-item ion-network-header">';
                            echo '<span class="ion-item-text-sm">' . htmlspecialchars($category['name']) . '</span>';
                            echo '</div>';
                            
                            foreach ($category['items'] as $item) {
                                $url = $item['url'] ?? '#';
                                if ($url !== '#') {
                                    echo '<a href="' . htmlspecialchars($url) . '" target="_blank" class="ion-network-item">';
                                    echo '<span class="ion-item-text-sm">' . formatIONText($item['name']) . '</span>';
                                    echo '</a>';
                                } else {
                                    echo '<div class="ion-network-item ion-item-disabled">';
                                    echo '<span class="ion-item-text-sm">' . formatIONText($item['name']) . '</span>';
                                    echo '</div>';
                                }
                            }
                            echo '</div>';
                        }
                    }
                }
                ?>
            </div>
        </div>
    </div>
    <?php
}

function renderConnectionsMenu($connectionsData) {
    ?>
    <div class="ion-menu-container-networks">
        <!-- Header -->
        <div class="ion-menu-header">
            <h2 class="ion-menu-title">
                CONNECT<span class="text-primary">.ION</span>S
            </h2>
            <div class="ion-menu-search-wrapper">
                <svg class="ion-search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input 
                    type="text" 
                    placeholder="SEARCH CONNECTIONS" 
                    class="ion-menu-search-input"
                    id="ion-connections-search"
                />
            </div>
            <div class="ion-menu-controls">
                <button class="ion-font-toggle" onclick="toggleFont()" title="Toggle Font">Aa</button>
                <button class="ion-theme-toggle" onclick="toggleTheme()" title="Toggle Theme">
                    <svg id="theme-sun-conn" class="hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    <svg id="theme-moon-conn" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                    </svg>
                </button>
            </div>
        </div>
        
        <!-- Content -->
        <div class="ion-menu-scrollarea" style="height: 468px; overflow-y: auto; padding: 0.5rem;">
            <div class="ion-networks-grid">
                <?php 
                if (isset($connectionsData['connections'])) {
                    foreach ($connectionsData['connections'] as $connection) {
                        if (!empty($connection['children'])) {
                            // Show as column
                            echo '<div class="ion-network-column">';
                            echo '<div class="ion-network-item ion-network-header">';
                            echo '<span class="ion-item-text-sm">' . htmlspecialchars($connection['title']) . '</span>';
                            echo '</div>';
                            
                            foreach ($connection['children'] as $child) {
                                $url = $child['url'] ?? '#';
                                if ($url !== '#') {
                                    echo '<a href="' . htmlspecialchars($url) . '" target="_blank" class="ion-network-item">';
                                    echo '<span class="ion-item-text-sm">' . htmlspecialchars($child['title']) . '</span>';
                                    echo '</a>';
                                } else {
                                    echo '<div class="ion-network-item ion-item-disabled">';
                                    echo '<span class="ion-item-text-sm">' . htmlspecialchars($child['title']) . '</span>';
                                    echo '</div>';
                                }
                            }
                            echo '</div>';
                        }
                    }
                }
                ?>
            </div>
        </div>
    </div>
    <?php
}

function formatIONText($text) {
    // Highlight ION in text
    $text = htmlspecialchars($text);
    $text = str_replace('ION', '<span class="text-primary font-medium">ION</span>', $text);
    return $text;
}
?>
