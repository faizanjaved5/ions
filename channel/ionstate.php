<?php
/**
 * ION Geographic Hierarchy - Level 3: Cities/Towns
 * Display cities/towns for a specific state/province or country
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

// Pagination setup
$cities_per_page = 24;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $cities_per_page;

// Search filter
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';

// Determine if we're showing cities for a state or directly for a country
$isStateLevel = isset($stateName) && !empty($stateName);

if ($isStateLevel) {
    // Level 3: Show cities in a specific state
    $stmt = $pdo->prepare("
        SELECT state_name, state_code, country_code, country_name
        FROM IONLocalNetwork
        WHERE state_name = :state_name
        LIMIT 1
    ");
    $stmt->execute([':state_name' => $stateName]);
    $locationData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$locationData) {
        http_response_code(404);
        echo "State not found";
        exit;
    }
    
    $pageTitle = $locationData['state_name'];
    $pageSubtitle = "Popular cities and towns in " . $locationData['state_name'];
    $countryCode = $locationData['country_code'];
    
    // Build search condition
    $search_condition = '';
    $search_params = [':state_name' => $stateName];
    
    if (!empty($search_query)) {
        $search_condition = " AND (city_name LIKE :search OR description LIKE :search OR title LIKE :search)";
        $search_params[':search'] = '%' . $search_query . '%';
    }
    
    // Get total count for pagination
    $countSql = "SELECT COUNT(*) as total
                 FROM IONLocalNetwork
                 WHERE state_name = :state_name
                   AND city_name IS NOT NULL
                   AND city_name != ''"
                   . $search_condition;
    
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($search_params);
    $total_cities = (int)$stmt->fetchColumn();
    $total_pages = ceil($total_cities / $cities_per_page);
    
    // Fetch cities for this state with pagination
    $sql = "SELECT 
        id,
        slug,
        city_name,
        state_name,
        country_code,
        population,
        description,
        title
    FROM IONLocalNetwork
    WHERE state_name = :state_name
      AND city_name IS NOT NULL
      AND city_name != ''"
      . $search_condition . "
    ORDER BY CAST(population AS UNSIGNED) DESC, city_name ASC
    LIMIT " . (int)$cities_per_page . " OFFSET " . (int)$offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($search_params);
    $cities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Back link - go to country states page
    $countrySlug = 'ion-' . strtolower(str_replace(' ', '-', $locationData['country_name']));
    $backLink = '/' . $countrySlug;
    $backText = 'Back to ' . $locationData['country_name'];
    $pageEmoji = '🏴';
    
} else {
    // Level 2: Show cities directly for a country (smaller countries without state hierarchy)
    
    // Country name mapping (canonical names)
    $countryNames = [
        'US' => 'United States',
        'CA' => 'Canada',
        'MX' => 'Mexico',
        'GB' => 'United Kingdom',
        'AU' => 'Australia',
        'NZ' => 'New Zealand',
        'DE' => 'Germany',
        'FR' => 'France',
        'ES' => 'Spain',
        'IT' => 'Italy',
        'JP' => 'Japan',
        'CN' => 'China',
        'IN' => 'India',
        'BR' => 'Brazil',
        'AR' => 'Argentina',
        'ZA' => 'South Africa',
        'AE' => 'United Arab Emirates',
        'BG' => 'Bulgaria',
        'IS' => 'Iceland',
        'PH' => 'Philippines',
        'SA' => 'Saudi Arabia',
    ];
    
    $countryName = $countryNames[$countryCode] ?? null;
    
    if (!$countryName) {
        // Fallback to database if not in map
        $stmt = $pdo->prepare("
            SELECT country_name
            FROM IONLocalNetwork
            WHERE country_code = :code
            LIMIT 1
        ");
        $stmt->execute([':code' => $countryCode]);
        $locationData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$locationData || empty($locationData['country_name'])) {
            http_response_code(404);
            echo "Country not found";
            exit;
        }
        
        $countryName = $locationData['country_name'];
    }
    
    $pageTitle = $countryName;
    $pageSubtitle = "Popular cities and towns in " . $countryName;
    
    // Build search condition
    $search_condition = '';
    $search_params = [':code' => $countryCode];
    
    if (!empty($search_query)) {
        $search_condition = " AND (city_name LIKE :search OR description LIKE :search OR title LIKE :search)";
        $search_params[':search'] = '%' . $search_query . '%';
    }
    
    // Get total count for pagination
    $countSql = "SELECT COUNT(*) as total
                 FROM IONLocalNetwork
                 WHERE country_code = :code
                   AND city_name IS NOT NULL
                   AND city_name != ''"
                   . $search_condition;
    
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($search_params);
    $total_cities = (int)$stmt->fetchColumn();
    $total_pages = ceil($total_cities / $cities_per_page);
    
    // Fetch cities for this country with pagination
    $sql = "SELECT 
        id,
        slug,
        city_name,
        state_name,
        country_code,
        population,
        description,
        title
    FROM IONLocalNetwork
    WHERE country_code = :code
      AND city_name IS NOT NULL
      AND city_name != ''"
      . $search_condition . "
    ORDER BY CAST(population AS UNSIGNED) DESC, city_name ASC
    LIMIT " . (int)$cities_per_page . " OFFSET " . (int)$offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($search_params);
    $cities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Back link
    $backLink = '/channel/';
    $backText = 'Back to Locations';

    // Country emoji flags
    $countryEmojis = [
    'US' => '🇺🇸',
    'CA' => '🇨🇦',
    'MX' => '🇲🇽',
    'GB' => '🇬🇧',
    'AU' => '🇦🇺',
    ];
    $pageEmoji = $countryEmojis[$countryCode] ?? '🏳️';
}

// Helper function to format population
function formatPopulation($pop) {
    if (empty($pop) || $pop == 0) return '';
    $popInt = is_numeric($pop) ? (int)$pop : 0;
    if ($popInt >= 1000000) {
        return 'Population: ' . number_format($popInt / 1000000, 1) . 'M';
    } elseif ($popInt >= 1000) {
        return 'Population: ' . number_format($popInt / 1000) . 'K';
    } elseif ($popInt > 0) {
        return 'Population: ' . number_format($popInt);
    }
    return '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ION <?= htmlspecialchars($pageTitle) ?> - Towns & Cities</title>
    <meta name="description" content="<?= htmlspecialchars($pageSubtitle) ?>">
    
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
                <a href="<?= htmlspecialchars($backLink) ?>" class="header-flag" style="text-decoration:none;cursor:pointer;display:inline-block;" title="<?= htmlspecialchars($backText) ?>">
                    <img src="/assets/flags/<?= strtolower($countryCode) ?>.svg" 
                         alt="<?= htmlspecialchars($pageTitle) ?>" 
                         style="width: 3rem; height: 2.25rem; object-fit: cover;transition:opacity 0.2s;"
                         onmouseover="this.style.opacity='0.7'"
                         onmouseout="this.style.opacity='1'">
                </a>
                <h1 class="header-title">ION <?= htmlspecialchars($pageTitle) ?></h1>
            </div>
            <p class="header-subtitle"><?= htmlspecialchars($pageSubtitle) ?></p>
        </header>

        <!-- Locations Heading with Search and Count -->
        <div style="display:flex;justify-content:space-between;align-items:center;margin:0 0 2rem 0;gap:16px;flex-wrap:wrap;">
            <h2 style="margin:0;font-size:1.125rem;color:hsl(var(--foreground));flex-shrink:0;">
                Locations
                <?php if ($total_cities > 0): ?>
                    <span style="font-size:0.875rem;color:hsl(var(--muted-foreground));font-weight:400;margin-left:8px;">(<?= number_format($total_cities) ?> total)</span>
                <?php endif; ?>
            </h2>
            
            <!-- Search Box -->
            <form method="GET" id="city-search-form" style="display:flex;gap:8px;align-items:center;flex:1;max-width:400px;min-width:200px;">
                <?php if ($isStateLevel): ?>
                    <!-- Keep the state context in the URL -->
                <?php else: ?>
                    <input type="hidden" name="country" value="<?= htmlspecialchars($countryCode) ?>">
                <?php endif; ?>
                <div style="position:relative;flex:1;">
                    <input 
                        type="search" 
                        name="q" 
                        id="location-search"
                        value="<?= htmlspecialchars($search_query) ?>" 
                        placeholder="Search locations..."
                        style="width:100%;padding:8px 36px 8px 12px;background:hsl(var(--card));border:1px solid hsl(var(--border));border-radius:8px;color:hsl(var(--foreground));font-size:13px;"
                    >
                    <svg style="position:absolute;right:10px;top:50%;transform:translateY(-50%);width:18px;height:18px;color:hsl(var(--muted-foreground));pointer-events:none;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
                <?php if (!empty($search_query)): ?>
                    <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" style="padding:8px 12px;background:hsl(var(--muted));border:1px solid hsl(var(--border));border-radius:6px;color:hsl(var(--muted-foreground));text-decoration:none;font-size:13px;white-space:nowrap;">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="grid grid-cols-3" id="cities-grid">
            <?php foreach ($cities as $city): 
                $cityName = $city['city_name'];
                $citySlug = $city['slug'];
                $population = formatPopulation($city['population']);
                $description = $city['description'] ?? $city['title'] ?? $cityName;
                
                // Truncate description if too long
                if (strlen($description) > 60) {
                    $description = substr($description, 0, 57) . '...';
                }
            ?>
                <a href="/<?= htmlspecialchars($citySlug) ?>" class="card">
                    <div class="card-header">
                        <div class="card-gradient"></div>
                        <div>
                            <h2 class="card-title">ION <?= htmlspecialchars($cityName) ?></h2>
                            <p class="card-description"><?= htmlspecialchars($description) ?></p>
                            <?php if ($population): ?>
                                <p class="card-meta">
                                    <span class="card-meta-dot card-meta-dot-small"></span>
                                    <?= htmlspecialchars($population) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
            
            <?php if (empty($cities)): ?>
                <div class="card">
                    <div class="card-header">
                        <div class="card-gradient"></div>
                        <div>
                            <h2 class="card-title">No Cities Found</h2>
                            <p class="card-description"><?= !empty($search_query) ? 'No locations match your search.' : 'This location doesn\'t have any cities or towns in our network yet.' ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination" style="display:flex;justify-content:center;align-items:center;gap:8px;margin:3rem 0 2rem 0;flex-wrap:wrap;">
                <?php if ($current_page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $current_page - 1])) ?>" class="page-btn" style="padding:8px 12px;background:hsl(var(--card));border:1px solid hsl(var(--border));border-radius:6px;color:hsl(var(--foreground));text-decoration:none;font-size:14px;">← Previous</a>
                <?php endif; ?>
                
                <?php
                // Show page numbers with smart ellipsis
                $range = 2;
                for ($i = 1; $i <= $total_pages; $i++):
                    if ($i == 1 || $i == $total_pages || ($i >= $current_page - $range && $i <= $current_page + $range)):
                ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="page-btn <?= $i == $current_page ? 'active' : '' ?>" style="padding:8px 12px;background:<?= $i == $current_page ? 'hsl(var(--primary))' : 'hsl(var(--card))' ?>;border:1px solid hsl(var(--border));border-radius:6px;color:<?= $i == $current_page ? 'hsl(var(--primary-foreground))' : 'hsl(var(--foreground))' ?>;text-decoration:none;font-size:14px;min-width:40px;text-align:center;"><?= $i ?></a>
                <?php
                    elseif ($i == $current_page - $range - 1 || $i == $current_page + $range + 1):
                ?>
                    <span style="padding:8px;color:hsl(var(--muted-foreground));">...</span>
                <?php
                    endif;
                endfor;
                ?>
                
                <?php if ($current_page < $total_pages): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $current_page + 1])) ?>" class="page-btn" style="padding:8px 12px;background:hsl(var(--card));border:1px solid hsl(var(--border));border-radius:6px;color:hsl(var(--foreground));text-decoration:none;font-size:14px;">Next →</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="/channel/ionchannel.js"></script>
    
    <!-- AJAX Search Script -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('location-search');
        const searchForm = document.getElementById('city-search-form');
        const citiesGrid = document.getElementById('cities-grid');
        const container = document.querySelector('.container');
        
        if (!searchInput || !searchForm || !citiesGrid) return;
        
        let searchTimeout;
        const DEBOUNCE_DELAY = 500; // 500ms delay before auto-search
        let currentPage = <?= $current_page ?>;
        let isLoading = false;
        
        // Prevent default form submission
        searchForm.addEventListener('submit', function(e) {
            e.preventDefault();
        });
        
        // AJAX search function
        function performSearch(query, page = 1) {
            if (isLoading) return;
            
            isLoading = true;
            query = query.trim();
            
            // Add loading state
            searchInput.classList.add('loading');
            citiesGrid.style.opacity = '0.5';
            
            // Build URL with query parameters
            const url = new URL(window.location.href);
            url.searchParams.set('q', query);
            url.searchParams.set('page', page);
            
            // Fetch new results
            fetch(url.toString())
                .then(response => response.text())
                .then(html => {
                    // Parse the HTML response
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    
                    // Extract the new grid content
                    const newGrid = doc.getElementById('cities-grid');
                    const newPagination = doc.querySelector('.pagination');
                    const newCount = doc.querySelector('h2 span');
                    
                    if (newGrid) {
                        citiesGrid.innerHTML = newGrid.innerHTML;
                    }
                    
                    // Update pagination
                    const oldPagination = container.querySelector('.pagination');
                    if (newPagination) {
                        if (oldPagination) {
                            oldPagination.innerHTML = newPagination.innerHTML;
                        } else {
                            citiesGrid.insertAdjacentHTML('afterend', newPagination.outerHTML);
                        }
                        attachPaginationListeners();
                    } else if (oldPagination) {
                        oldPagination.remove();
                    }
                    
                    // Update count
                    const countSpan = document.querySelector('h2 span');
                    if (newCount && countSpan) {
                        countSpan.textContent = newCount.textContent;
                    }
                    
                    // Update URL without page reload
                    history.pushState({}, '', url.toString());
                    
                    // Remove loading state
                    searchInput.classList.remove('loading');
                    citiesGrid.style.opacity = '1';
                    isLoading = false;
                    
                    // Scroll to top of results
                    citiesGrid.scrollIntoView({ behavior: 'smooth', block: 'start' });
                })
                .catch(error => {
                    console.error('Search error:', error);
                    searchInput.classList.remove('loading');
                    citiesGrid.style.opacity = '1';
                    isLoading = false;
                });
        }
        
        // Auto-search with debouncing
        searchInput.addEventListener('input', function(e) {
            const query = e.target.value;
            
            // Clear existing timeout
            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }
            
            // Debounce: Wait for user to stop typing
            searchTimeout = setTimeout(() => {
                performSearch(query, 1);
            }, DEBOUNCE_DELAY);
        });
        
        // Handle Enter key press
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                
                // Clear debounce timeout
                if (searchTimeout) {
                    clearTimeout(searchTimeout);
                }
                
                // Search immediately
                performSearch(e.target.value, 1);
            }
        });
        
        // Attach pagination click handlers
        function attachPaginationListeners() {
            const paginationLinks = document.querySelectorAll('.pagination a.page-btn');
            paginationLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Extract page number from URL
                    const url = new URL(this.href);
                    const page = parseInt(url.searchParams.get('page')) || 1;
                    const query = searchInput.value;
                    
                    // Perform AJAX search with new page
                    performSearch(query, page);
                    
                    // Scroll to top of results
                    citiesGrid.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            });
        }
        
        // Initialize pagination listeners
        attachPaginationListeners();
        
        // Handle clear button click
        document.addEventListener('click', function(e) {
            if (e.target.textContent.includes('Clear')) {
                e.preventDefault();
                searchInput.value = '';
                performSearch('', 1);
            }
        });
        
        console.log('✅ Location AJAX search initialized');
    });
    </script>
    
    <style>
    /* Search Box Focus State */
    #location-search:focus {
        outline: none;
        border-color: hsl(var(--primary));
        box-shadow: 0 0 0 3px hsl(var(--primary) / 0.1);
    }
    
    /* Loading spinner for search input */
    #location-search.loading {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 24 24'%3E%3Cpath fill='%233b82f6' d='M12,1A11,11,0,1,0,23,12,11,11,0,0,0,12,1Zm0,19a8,8,0,1,1,8-8A8,8,0,0,1,12,20Z' opacity='.25'/%3E%3Cpath fill='%233b82f6' d='M10.14,1.16a11,11,0,0,0-9,8.92A1.59,1.59,0,0,0,2.46,12,1.52,1.52,0,0,0,4.11,10.7a8,8,0,0,1,6.66-6.61A1.42,1.42,0,0,0,12,2.69h0A1.57,1.57,0,0,0,10.14,1.16Z'%3E%3CanimateTransform attributeName='transform' type='rotate' dur='0.75s' values='0 12 12;360 12 12' repeatCount='indefinite'/%3E%3C/path%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 40px center;
        background-size: 18px;
    }
    
    /* AJAX Loading States */
    #cities-grid {
        transition: opacity 0.2s ease;
    }
    
    /* Smooth fade for city cards */
    #cities-grid .card {
        animation: fadeIn 0.3s ease;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    /* Mobile responsive pagination */
    @media (max-width: 640px) {
        .pagination {
            font-size: 12px;
        }
        
        .pagination a.page-btn {
            padding: 6px 10px !important;
            font-size: 12px !important;
            min-width: 32px !important;
        }
    }
    </style>
</body>
</html>
