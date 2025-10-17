<?php
/**
 * ION Networks Navigation - FIXED SEARCH VERSION
 * Complete search functionality with proper UI behavior
 */

// Auto-include CSS if not already included
if (!defined('ION_CSS_INCLUDED')) {
    define('ION_CSS_INCLUDED', true);
    
    // Determine the correct path to the CSS file
    $css_path = '';
    $menu_dir = dirname(__FILE__);
    $document_root = $_SERVER['DOCUMENT_ROOT'];
    
    // Calculate relative path from current page to menu directory
    $current_dir = dirname($_SERVER['SCRIPT_FILENAME']);
    $relative_path = str_replace($document_root, '', $menu_dir);
    
    // Try different path combinations
    $possible_paths = [
        $relative_path . '/menu.css',
        '../menu/menu.css',
        './menu/menu.css',
        '/menu/menu.css'
    ];
    
    foreach ($possible_paths as $path) {
        if (file_exists($document_root . $path)) {
            $css_path = $path;
            break;
        }
    }
    
    // Output CSS link
    if ($css_path) {
        echo '<link rel="stylesheet" href="' . $css_path . '">' . "\n";
    } else {
        // Fallback: inline critical CSS
        echo '<style>';
        include __DIR__ . '/menu.css';
        echo '</style>' . "\n";
    }
}

// Include menu data
$menu_data_path = __DIR__ . '/menudata.php';
if (file_exists($menu_data_path)) {
    require_once $menu_data_path;
} else {
    // Fallback menu data if file doesn't exist
    $menuItems = [
        ['n' => 'ION Networks', 'u' => '/', 'c' => []],
        ['n' => 'About', 'u' => '/about/', 'c' => []],
        ['n' => 'Contact', 'u' => '/contact/', 'c' => []]
    ];
}

/**
 * Format menu label to highlight ION text
 */
function formatMenuLabel($label) {
    if ($label === 'Connect.IONS') {
        return 'CONNECT.<span class="ion-text">IONS</span>';
    }
    return preg_replace('/ION/', '<span class="ion-text">ION</span>', $label);
}

/**
 * Get the logo URL with fallback options
 */
function ion_get_logo_url() {
    $menu_dir = dirname(__FILE__);
    
    $logo_files = [
        $menu_dir . '/ion-logo-gold.png',
        __DIR__ . '/ion-logo-gold.png',
        'menu/ion-logo-gold.png',
        '../menu/ion-logo-gold.png'
    ];
    
    foreach ($logo_files as $file) {
        if (file_exists($file)) {
            // Convert absolute path to web path
            $web_path = str_replace($_SERVER['DOCUMENT_ROOT'], '', $file);
            return $web_path;
        }
    }
    
    // Fallback SVG logo
    return 'data:image/svg+xml;base64,' . base64_encode('
        <svg xmlns="http://www.w3.org/2000/svg" width="120" height="40" viewBox="0 0 120 40">
            <text x="10" y="25" font-family="Arial" font-size="18" fill="#b28254" font-weight="bold">ION</text>
            <text x="10" y="35" font-family="Arial" font-size="8" fill="#a4b3d0">NETWORKS</text>
        </svg>
    ');
}

/**
 * Render mobile menu items recursively
 */
function renderMobileMenuItems($items, $level = 0, $parent_id = '') {
    foreach ($items as $index => $item) {
        $hasChildren = isset($item['c']) && !empty($item['c']);
        $safe_name = preg_replace('/[^a-zA-Z0-9]/', '_', $item['n']);
        $itemId = 'mobile-' . $parent_id . $safe_name . '_' . $index;
        
        echo '<div class="ion-mobile-menu-item">';
        echo '<div class="ion-mobile-menu-link" onclick="' . 
             ($hasChildren ? "toggleMobileSubmenu('" . htmlspecialchars($item['n'], ENT_QUOTES, 'UTF-8') . "')" : "window.location.href='" . htmlspecialchars($item['u'], ENT_QUOTES, 'UTF-8') . "'") . 
             '">';
        echo formatMenuLabel(htmlspecialchars($item['n'], ENT_NOQUOTES, 'UTF-8'));
        
        if ($hasChildren) {
            echo '<svg class="ion-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                  </svg>';
        }
        echo '</div>';
        
        if ($hasChildren) {
            echo '<div id="' . htmlspecialchars($itemId, ENT_QUOTES, 'UTF-8') . '" class="ion-mobile-submenu">';
            renderMobileMenuItems($item['c'], $level + 1, $safe_name . '_');
            echo '</div>';
        }
        
        echo '</div>';
    }
}

// Filter out search item for main navigation
$mainNavItems = array_filter($menuItems, function($item) {
    return !isset($item['is_search']) || !$item['is_search'];
});

$logo_url = ion_get_logo_url();

// Get current search query if exists
$current_search = '';
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $current_search = htmlspecialchars(trim($_GET['search']), ENT_QUOTES, 'UTF-8');
} elseif (isset($_GET['q']) && !empty(trim($_GET['q']))) {
    $current_search = htmlspecialchars(trim($_GET['q']), ENT_QUOTES, 'UTF-8');
}
?>

<!-- ION Networks Navigation Header -->
<nav class="ion-navigation" role="navigation" aria-label="Main Navigation">
    <div class="ion-nav-container">
        <div class="ion-nav-bar">
            <!-- Logo Section -->
            <div class="ion-logo-section">
                <a href="/" class="ion-logo-link" aria-label="ION Networks Home">
                    <img src="<?php echo htmlspecialchars($logo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="ION Networks Logo" class="ion-logo" />
                </a>
            </div>

            <!-- Desktop Navigation -->
            <div class="ion-desktop-nav" role="menubar">
                <!-- Navigation Items -->
                <?php foreach ($mainNavItems as $index => $item): ?>
                    <div class="ion-nav-item" data-menu="<?php echo htmlspecialchars($item['n'], ENT_QUOTES, 'UTF-8'); ?>" role="none">
                        <a href="<?php echo htmlspecialchars($item['u'], ENT_QUOTES, 'UTF-8'); ?>" 
                           class="ion-nav-link"
                           role="menuitem"
                           <?php if (isset($item['c']) && !empty($item['c'])): ?>
                           aria-haspopup="true"
                           aria-expanded="false"
                           <?php endif; ?>
                           onmouseenter="showMegaMenu('<?php echo htmlspecialchars($item['n'], ENT_QUOTES, 'UTF-8'); ?>')"
                           onclick="<?php echo (isset($item['c']) && !empty($item['c'])) ? 'return false;' : ''; ?>">
                            <?php echo formatMenuLabel($item['n']); ?>
                            <?php if (isset($item['c']) && !empty($item['c'])): ?>
                                <svg class="ion-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            <?php endif; ?>
                        </a>
                    </div>
                <?php endforeach; ?>
                
                <!-- Search Section - Integrated into nav -->
                <div class="ion-search-section" id="ionSearchSection" aria-label="Search ION">
                    <button class="ion-search-collapsed" id="ionSearchCollapsed" type="button" aria-label="Open Search">
                        <svg class="ion-search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        <span class="ion-search-text">SEARCH <span class="ion-text">ION</span></span>
                    </button>
                    
                    <form class="ion-search-expanded" id="ionSearchExpanded" action="/search/" method="GET" style="display: none;">
                        <input type="text" name="q" class="ion-search-input" placeholder="Search videos, content, locations..." 
                               value="<?php echo $current_search; ?>" required autocomplete="off" />
                        <button type="submit" class="ion-search-button">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                            Search
                        </button>
                        <button type="button" class="ion-search-close" id="ionSearchClose" aria-label="Close Search">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
            
                <!-- Search Backdrop -->
                <div class="ion-search-backdrop" id="ionSearchBackdrop"></div>

                <!-- Upload Button -->
                <a href="https://ions.com/uploader/" class="ion-btn ion-btn-upload" aria-label="Upload Content">
                    <svg class="ion-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                    </svg>
                    <span class="ion-hidden ion-sm-inline">UPLOAD</span>
                </a>
                
                <!-- Sign ION Button -->
                <a href="../app/index.php" class="ion-btn ion-btn-sign" onclick="openSignUp(); return false;" aria-label="Sign up for ION">
                    SIGN <span class="ion-text">ION</span>
                </a>

                <!-- Theme Toggle -->
                <button class="ion-btn ion-btn-icon" onclick="toggleTheme()" aria-label="Toggle Theme">
                    <svg class="ion-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                    </svg>
                </button>

                <!-- Mobile Menu Button -->
                <button class="ion-mobile-menu-btn" onclick="toggleMobileMenu()" aria-label="Open Mobile Menu" aria-expanded="false">
                    <svg class="ion-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Mega Menu Container -->
    <div id="ionMegaMenu" 
         class="ion-mega-menu" 
         role="menu"
         aria-hidden="true"
         onmouseenter="keepMegaMenuOpen()" 
         onmouseleave="hideMegaMenu()">
        <div class="ion-mega-menu-content">
            <div id="ionMegaMenuGrid" class="ion-mega-menu-grid">
                <!-- Dynamic content populated by JavaScript -->
            </div>
        </div>
    </div>
</nav>

<!-- Mobile Menu -->
<div id="ionMobileMenu" class="ion-mobile-menu" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="mobile-menu-title">
    <div class="ion-mobile-menu-panel">
        <div class="ion-mobile-menu-header">
            <a href="/" class="ion-mobile-menu-logo-link">
                <img src="<?php echo htmlspecialchars($logo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="ION Networks Logo" class="ion-mobile-menu-logo" />
            </a>
            <button class="ion-mobile-menu-close" onclick="closeMobileMenu()" aria-label="Close Mobile Menu">
                <svg class="ion-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        
        <div class="ion-mobile-menu-content" role="menu">
            <h2 id="mobile-menu-title" class="sr-only">Mobile Navigation Menu</h2>
            
            <!-- Mobile Search -->
            <div class="ion-mobile-search">
                <form action="/search.php" method="GET">
                    <input type="text" name="q" placeholder="Search ION Network..." 
                           value="<?php echo $current_search; ?>" autocomplete="off">
                    <button type="submit">Search</button>
                </form>
            </div>
            
            <?php renderMobileMenuItems($mainNavItems); ?>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';
    
    // Menu data and state management
    const ionMenuData = <?php echo json_encode($menuItems, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    let ionActiveMegaMenu = null;
    let ionMegaMenuTimeout = null;
    const ionExpandedMobileItems = new Set();
    let isSearchExpanded = false;

    // Enhanced label formatting with XSS protection
    function ionFormatLabel(label) {
        const safeLabel = label.replace(/[<>'"&]/g, function(match) {
            const escapeMap = {'<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;', '&': '&amp;'};
            return escapeMap[match];
        });
        
        if (safeLabel === 'Connect.IONS') {
            return 'CONNECT.<span class="ion-text">IONS</span>';
        }
        return safeLabel.replace(/ION/g, '<span class="ion-text">ION</span>');
    }

    // FIXED: Header Search Functions - Completely rewritten
    function openSearch() {
        if (isSearchExpanded) {
            closeSearch();
            return;
        }
        
        const searchSection = document.getElementById('ionSearchSection');
        const backdrop = document.getElementById('ionSearchBackdrop');
        const collapsedSearch = document.getElementById('ionSearchCollapsed');
        const expandedSearch = document.getElementById('ionSearchExpanded');
        
        if (!searchSection || !expandedSearch || !collapsedSearch || !backdrop) {
            console.error('Search elements not found');
            return;
        }
        
        // Create wrapper for expanded state
        const wrapper = document.createElement('div');
        wrapper.className = 'ion-search-wrapper-expanded';
        
        // Move the form to the wrapper
        expandedSearch.style.display = 'flex';
        
        // Prevent body scroll
        document.body.style.overflow = 'hidden';
        
        // Show backdrop
        backdrop.classList.add('active');
        
        // Transform search section
        searchSection.classList.add('expanded');
        collapsedSearch.style.display = 'none';
        expandedSearch.style.display = 'flex';
        isSearchExpanded = true;
        
        // Hide other nav elements
        const navItems = document.querySelectorAll('.ion-nav-item, .ion-btn');
        navItems.forEach(item => {
            if (!item.closest('#ionSearchSection')) {
                item.style.opacity = '0';
                item.style.pointerEvents = 'none';
            }
        });
        
        // Focus on input after animation
        setTimeout(() => {
            const input = expandedSearch.querySelector('.ion-search-input');
            if (input) {
                input.focus();
                if (input.value) {
                    input.select();
                }
            }
        }, 150);
        
        // Update accessibility
        searchSection.setAttribute('aria-expanded', 'true');
        
        console.log('Search opened successfully');
    }

    function closeSearch() {
        if (!isSearchExpanded) return;
        
        const searchSection = document.getElementById('ionSearchSection');
        const backdrop = document.getElementById('ionSearchBackdrop');
        const collapsedSearch = document.getElementById('ionSearchCollapsed');
        const expandedSearch = document.getElementById('ionSearchExpanded');
        
        if (!searchSection || !expandedSearch || !collapsedSearch || !backdrop) return;
        
        // Restore body scroll
        document.body.style.overflow = '';
        
        // Hide backdrop
        backdrop.classList.remove('active');
        
        // Transform search section back
        searchSection.classList.remove('expanded');
        expandedSearch.style.display = 'none';
        collapsedSearch.style.display = 'flex';
        isSearchExpanded = false;
        
        // Show other nav elements
        const navItems = document.querySelectorAll('.ion-nav-item, .ion-btn');
        navItems.forEach(item => {
            item.style.opacity = '';
            item.style.pointerEvents = '';
        });
        
        // Update accessibility
        searchSection.setAttribute('aria-expanded', 'false');
        
        console.log('Search closed successfully');
    }

    // Mega Menu Functions (keeping existing functionality)
    function showMegaMenu(menuName) {
        if (isSearchExpanded) return; // Don't show mega menu when search is expanded
        
        clearTimeout(ionMegaMenuTimeout);
        
        const megaMenu = document.getElementById('ionMegaMenu');
        const megaMenuGrid = document.getElementById('ionMegaMenuGrid');
        
        const menuItem = ionMenuData.find(item => item.n === menuName);
        if (!menuItem || !menuItem.c || menuItem.c.length === 0) {
            hideMegaMenu();
            return;
        }

        // Update active state
        document.querySelectorAll('.ion-nav-item').forEach(item => {
            item.classList.remove('active');
            const link = item.querySelector('.ion-nav-link');
            if (link) link.setAttribute('aria-expanded', 'false');
        });
        
        const activeItem = document.querySelector(`[data-menu="${menuName}"]`);
        if (activeItem) {
            activeItem.classList.add('active');
            const link = activeItem.querySelector('.ion-nav-link');
            if (link) link.setAttribute('aria-expanded', 'true');
        }

        megaMenuGrid.innerHTML = renderMegaMenuContent(menuItem.c);
        megaMenu.classList.add('active');
        megaMenu.setAttribute('aria-hidden', 'false');
        ionActiveMegaMenu = menuName;
    }

    function hideMegaMenu() {
        ionMegaMenuTimeout = setTimeout(() => {
            const megaMenu = document.getElementById('ionMegaMenu');
            megaMenu.classList.remove('active');
            megaMenu.setAttribute('aria-hidden', 'true');
            
            document.querySelectorAll('.ion-nav-item').forEach(item => {
                item.classList.remove('active');
                const link = item.querySelector('.ion-nav-link');
                if (link) link.setAttribute('aria-expanded', 'false');
            });
            ionActiveMegaMenu = null;
        }, 150);
    }

    function keepMegaMenuOpen() {
        clearTimeout(ionMegaMenuTimeout);
    }

    function renderMegaMenuContent(items) {
        return items.map((item, index) => {
            const hasChildren = item.c && item.c.length > 0;
            const itemId = 'mega-' + item.n.replace(/[^a-zA-Z0-9]/g, '_') + '_' + index;
            
            return `
                <div class="ion-mega-menu-section">
                    <div class="ion-mega-menu-item ${hasChildren ? 'has-children' : ''}" 
                         ${hasChildren ? `onclick="toggleMegaMenuExpansion('${itemId}')"` : `onclick="navigateToUrl('${item.u}')"`}
                         role="menuitem"
                         tabindex="0">
                        <div class="ion-mega-menu-title">
                            <span>${ionFormatLabel(item.n)}</span>
                            ${hasChildren ? `
                                <svg class="ion-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            ` : ''}
                        </div>
                        ${hasChildren ? `
                            <div id="${itemId}" class="ion-mega-menu-children">
                                ${renderMegaMenuChildren(item.c)}
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;
        }).join('');
    }

    function renderMegaMenuChildren(items) {
        return items.map(item => {
            const hasGrandChildren = item.c && item.c.length > 0;
            return `
                <div class="ion-mega-menu-child ${hasGrandChildren ? 'has-grandchildren' : ''}" 
                     onclick="${hasGrandChildren ? 'event.stopPropagation()' : `navigateToUrl('${item.u}')`}"
                     role="menuitem"
                     tabindex="0">
                    <span class="ion-mega-menu-child-title">${ionFormatLabel(item.n)}</span>
                    ${hasGrandChildren ? `
                        <div class="ion-mega-menu-grandchildren">
                            ${item.c.map(grandchild => `
                                <div class="ion-mega-menu-grandchild" 
                                     onclick="navigateToUrl('${grandchild.u}')"
                                     role="menuitem"
                                     tabindex="0">
                                    ${ionFormatLabel(grandchild.n)}
                                </div>
                            `).join('')}
                        </div>
                    ` : ''}
                </div>
            `;
        }).join('');
    }

    function toggleMegaMenuExpansion(itemId) {
        const element = document.getElementById(itemId);
        const chevron = element.parentElement.querySelector('.ion-chevron');
        
        element.classList.toggle('expanded');
        
        if (chevron) {
            chevron.style.transform = element.classList.contains('expanded') ? 'rotate(90deg)' : 'rotate(0deg)';
        }
    }

    function navigateToUrl(url) {
        if (url && url !== '#') {
            window.location.href = url;
        }
    }

    // Mobile Menu Functions
    function toggleMobileMenu() {
        const mobileMenu = document.getElementById('ionMobileMenu');
        const menuButton = document.querySelector('.ion-mobile-menu-btn');
        
        mobileMenu.classList.add('active');
        mobileMenu.setAttribute('aria-hidden', 'false');
        menuButton.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
    }

    function closeMobileMenu() {
        const mobileMenu = document.getElementById('ionMobileMenu');
        const menuButton = document.querySelector('.ion-mobile-menu-btn');
        
        mobileMenu.classList.remove('active');
        mobileMenu.setAttribute('aria-hidden', 'true');
        menuButton.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    }

    function toggleMobileSubmenu(itemName) {
        const safeItemName = itemName.replace(/[^a-zA-Z0-9]/g, '_');
        const itemId = 'mobile-' + safeItemName + '_0';
        const element = document.getElementById(itemId);
        
        if (!element) return;
        
        const isExpanded = ionExpandedMobileItems.has(itemName);
        
        if (isExpanded) {
            element.classList.remove('expanded');
            ionExpandedMobileItems.delete(itemName);
        } else {
            element.classList.add('expanded');
            ionExpandedMobileItems.add(itemName);
        }
        
        const chevron = element.parentElement.querySelector('.ion-chevron');
        if (chevron) {
            chevron.style.transform = isExpanded ? 'rotate(0deg)' : 'rotate(90deg)';
        }
    }

    // Utility Functions
    function openSignUp() {
        window.location.href = '/signup.php';
    }

    function toggleTheme() {
        const html = document.documentElement;
        const currentTheme = html.getAttribute('data-theme') || 'dark';
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        
        html.setAttribute('data-theme', newTheme);
        
        if (typeof localStorage !== 'undefined') {
            localStorage.setItem('ion-theme-preference', newTheme);
        }
        
        // Trigger custom event
        const event = new CustomEvent('ionThemeChanged', { 
            detail: { theme: newTheme, previousTheme: currentTheme }
        });
        document.dispatchEvent(event);
    }

    // Initialize theme
    function initializeTheme() {
        if (typeof localStorage !== 'undefined') {
            const savedTheme = localStorage.getItem('ion-theme-preference');
            if (savedTheme) {
                document.documentElement.setAttribute('data-theme', savedTheme);
            }
        }
    }

    // Event Listeners
    function initializeEventListeners() {
        // FIXED: Search event listeners - completely rewritten
        const searchCollapsed = document.getElementById('ionSearchCollapsed');
        const searchExpanded = document.getElementById('ionSearchExpanded');
        const searchInput = searchExpanded?.querySelector('.ion-search-input');
        const searchClose = document.getElementById('ionSearchClose');
        const searchBackdrop = document.getElementById('ionSearchBackdrop');
        
        // Search button click
        if (searchCollapsed) {
            searchCollapsed.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('Search button clicked');
                openSearch();
            });
        }
        
        // Close button click
        if (searchClose) {
            searchClose.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('Close button clicked');
                closeSearch();
            });
        }
        
        // Backdrop click
        if (searchBackdrop) {
            searchBackdrop.addEventListener('click', function(e) {
                e.preventDefault();
                console.log('Backdrop clicked');
                closeSearch();
            });
        }
        
        // Prevent form from closing search when clicking inside
        if (searchExpanded) {
            searchExpanded.addEventListener('click', function(e) {
                e.stopPropagation();
            });
            
            // Form submission validation
            searchExpanded.addEventListener('submit', function(e) {
                const input = this.querySelector('input[name="q"]');
                const query = input.value.trim();
                
                if (!query) {
                    e.preventDefault();
                    input.focus();
                    input.style.borderColor = '#ef4444';
                    setTimeout(() => {
                        input.style.borderColor = '';
                    }, 2000);
                    return false;
                }
                
                // Show loading state
                const button = this.querySelector('button[type="submit"]');
                if (button) {
                    button.textContent = 'Searching...';
                    button.disabled = true;
                }
            });
        }
        
        // Input focus behavior
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                this.style.borderColor = '';
            });
            
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeSearch();
                }
            });
        }

        // Mega menu listeners
        document.querySelectorAll('.ion-nav-item').forEach(item => {
            item.addEventListener('mouseleave', hideMegaMenu);
        });

        // Global click handler for closing elements
        document.addEventListener('click', function(e) {
            // Don't close search if clicking on search elements
            const searchSection = document.getElementById('ionSearchSection');
            if (searchSection && searchSection.contains(e.target)) {
                return;
            }
            
            // Close mega menu when clicking outside
            const megaMenu = document.getElementById('ionMegaMenu');
            const navBar = document.querySelector('.ion-nav-bar');
            
            if (navBar && megaMenu && !navBar.contains(e.target) && !megaMenu.contains(e.target)) {
                hideMegaMenu();
            }
        });

        // Mobile menu listeners
        document.addEventListener('click', function(e) {
            const mobileMenu = document.getElementById('ionMobileMenu');
            const mobileMenuPanel = mobileMenu?.querySelector('.ion-mobile-menu-panel');
            const mobileMenuBtn = document.querySelector('.ion-mobile-menu-btn');
            
            if (mobileMenu && mobileMenu.classList.contains('active') && 
                mobileMenuPanel && !mobileMenuPanel.contains(e.target) && 
                mobileMenuBtn && !mobileMenuBtn.contains(e.target)) {
                closeMobileMenu();
            }
        });

        // Window resize handler
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                closeMobileMenu();
                ionExpandedMobileItems.clear();
                document.querySelectorAll('.ion-mobile-submenu').forEach(el => {
                    el.classList.remove('expanded');
                });
            } else {
                hideMegaMenu();
            }
            
            // Auto-close search on very small screens if needed
            if (window.innerWidth <= 480 && isSearchExpanded) {
                // Keep search open but adjust layout
                console.log('Small screen detected, search remains open');
            }
        });

        // Enhanced keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const mobileMenu = document.getElementById('ionMobileMenu');
                const megaMenu = document.getElementById('ionMegaMenu');
                
                if (isSearchExpanded) {
                    closeSearch();
                } else if (mobileMenu && mobileMenu.classList.contains('active')) {
                    closeMobileMenu();
                } else if (megaMenu && megaMenu.classList.contains('active')) {
                    hideMegaMenu();
                }
            }
        });
    }

    // Make functions globally available
    window.showMegaMenu = showMegaMenu;
    window.hideMegaMenu = hideMegaMenu;
    window.keepMegaMenuOpen = keepMegaMenuOpen;
    window.toggleMobileMenu = toggleMobileMenu;
    window.closeMobileMenu = closeMobileMenu;
    window.toggleMobileSubmenu = toggleMobileSubmenu;
    window.openSearch = openSearch;
    window.closeSearch = closeSearch;
    window.openSignUp = openSignUp;
    window.toggleTheme = toggleTheme;

    // Initialize
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            initializeTheme();
            initializeEventListeners();
        });
    } else {
        initializeTheme();
        initializeEventListeners();
    }

})();
</script>