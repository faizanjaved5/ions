<?php
/**
 * ION Networks Navigation WordPress Include
 * Optimized for WordPress theme integration
 * 
 * Usage: <?php include 'ion-navigation-wp-include.php'; ?>
 */

// Prevent direct access
if (!defined('ABSPATH') && !defined('ION_NAV_ALLOW_DIRECT')) {
    // Allow direct access for testing if ION_NAV_ALLOW_DIRECT is defined
    if (!defined('ION_NAV_ALLOW_DIRECT')) {
        exit('Direct access not allowed');
    }
}

// Include menu data
$menu_data_path = __DIR__ . '/menu-data.php';
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
function ion_format_menu_label($label) {
    if ($label === 'Connect.IONS') {
        return 'CONNECT.<span class="ion-text">IONS</span>';
    }
    return preg_replace('/ION/', '<span class="ion-text">ION</span>', $label);
}

/**
 * Get the logo URL with fallback options
 */
function ion_get_logo_url() {
    $logo_paths = [
        'ion-logo-gold.png',
        get_template_directory_uri() . '/assets/images/ion-logo-gold.png',
        get_template_directory_uri() . '/images/ion-logo-gold.png',
        plugins_url('ion-logo-gold.png', __FILE__),
        '/wp-content/themes/' . get_template() . '/ion-logo-gold.png'
    ];
    
    foreach ($logo_paths as $path) {
        if (file_exists(str_replace(home_url(), ABSPATH, $path)) || filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }
    }
    
    // Fallback to a default logo or text
    return 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="120" height="40" viewBox="0 0 120 40"><text x="10" y="25" font-family="Arial" font-size="18" fill="#b28254">ION</text></svg>');
}

/**
 * Enqueue required styles and scripts (WordPress way)
 */
function ion_nav_enqueue_assets() {
    if (function_exists('wp_enqueue_style')) {
        wp_enqueue_style('ion-navigation', get_template_directory_uri() . '/ion-navigation.css', [], '1.0.0');
        wp_enqueue_style('bebas-neue', 'https://fonts.googleapis.com/css2?family=Bebas+Neue:wght@400&display=swap', [], null);
    } else {
        // Fallback for non-WordPress environments
        echo '<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue:wght@400&display=swap" rel="stylesheet">';
        echo '<link rel="stylesheet" href="ion-navigation.css">';
    }
}

/**
 * Render mobile menu items recursively
 */
function ion_render_mobile_menu_items($items, $level = 0, $parent_id = '') {
    foreach ($items as $index => $item) {
        $hasChildren = isset($item['c']) && !empty($item['c']);
        $safe_name = preg_replace('/[^a-zA-Z0-9]/', '_', $item['n']);
        $itemId = 'mobile-' . $parent_id . $safe_name . '_' . $index;
        
        echo '<div class="ion-mobile-menu-item">';
        echo '<div class="ion-mobile-menu-link" onclick="' . 
             ($hasChildren ? "toggleMobileSubmenu('" . esc_js($item['n']) . "')" : "window.location.href='" . esc_url($item['u']) . "'") . 
             '">';
        echo ion_format_menu_label(esc_html($item['n']));
        
        if ($hasChildren) {
            echo '<svg class="ion-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                  </svg>';
        }
        echo '</div>';
        
        if ($hasChildren) {
            echo '<div id="' . esc_attr($itemId) . '" class="ion-mobile-submenu">';
            ion_render_mobile_menu_items($item['c'], $level + 1, $safe_name . '_');
            echo '</div>';
        }
        
        echo '</div>';
    }
}

// Enqueue assets
ion_nav_enqueue_assets();

// Filter out search item for main navigation
$mainNavItems = array_filter($menuItems, function($item) {
    return !isset($item['is_search']) || !$item['is_search'];
});

$logo_url = ion_get_logo_url();
?>

<!-- ION Networks Navigation Header -->
<nav class="ion-navigation" role="navigation" aria-label="Main Navigation">
    <div class="ion-nav-container">
        <div class="ion-nav-bar">
            <!-- Logo Section -->
            <div class="ion-logo-section">
                <a href="<?php echo esc_url(home_url('/')); ?>" class="ion-logo-link" aria-label="ION Networks Home">
                    <img src="<?php echo esc_url($logo_url); ?>" alt="ION Networks Logo" class="ion-logo" />
                </a>
            </div>

            <!-- Desktop Navigation -->
            <div class="ion-desktop-nav" role="menubar">
                <?php foreach ($mainNavItems as $index => $item): ?>
                    <div class="ion-nav-item" data-menu="<?php echo esc_attr($item['n']); ?>" role="none">
                        <a href="<?php echo esc_url($item['u']); ?>" 
                           class="ion-nav-link"
                           role="menuitem"
                           <?php if (isset($item['c']) && !empty($item['c'])): ?>
                           aria-haspopup="true"
                           aria-expanded="false"
                           <?php endif; ?>
                           onmouseenter="showMegaMenu('<?php echo esc_js($item['n']); ?>')"
                           onclick="<?php echo (isset($item['c']) && !empty($item['c'])) ? 'return false;' : ''; ?>">
                            <?php echo ion_format_menu_label(esc_html($item['n'])); ?>
                            <?php if (isset($item['c']) && !empty($item['c'])): ?>
                                <svg class="ion-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            <?php endif; ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Right Side Actions -->
            <div class="ion-nav-actions">
                <!-- Search Section -->
                <button class="ion-search-section" onclick="openSearch()" aria-label="Search ION Network">
                    <svg class="ion-search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    <span class="ion-search-text">SEARCH <span class="ion-text">ION</span> NETWORK</span>
                </button>

                <!-- Upload Button -->
                <a href="https://ions.com/uploader/" class="ion-btn ion-btn-upload" aria-label="Upload Content">
                    <svg class="ion-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                    </svg>
                    <span class="ion-hidden ion-sm-inline">UPLOAD</span>
                </a>
                
                <!-- Sign ION Button -->
                <a href="#" class="ion-btn ion-btn-sign" onclick="openSignUp(); return false;" aria-label="Sign up for ION">
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

    <!-- Enhanced Mega Menu Container -->
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

<!-- Enhanced Mobile Menu -->
<div id="ionMobileMenu" class="ion-mobile-menu" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="mobile-menu-title">
    <div class="ion-mobile-menu-panel">
        <div class="ion-mobile-menu-header">
            <a href="<?php echo esc_url(home_url('/')); ?>" class="ion-mobile-menu-logo-link">
                <img src="<?php echo esc_url($logo_url); ?>" alt="ION Networks Logo" class="ion-mobile-menu-logo" />
            </a>
            <button class="ion-mobile-menu-close" onclick="closeMobileMenu()" aria-label="Close Mobile Menu">
                <svg class="ion-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        
        <div class="ion-mobile-menu-content" role="menu">
            <h2 id="mobile-menu-title" class="sr-only">Mobile Navigation Menu</h2>
            <?php ion_render_mobile_menu_items($mainNavItems); ?>
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

    // Enhanced Mega Menu Functions with accessibility
    function showMegaMenu(menuName) {
        clearTimeout(ionMegaMenuTimeout);
        
        const megaMenu = document.getElementById('ionMegaMenu');
        const megaMenuGrid = document.getElementById('ionMegaMenuGrid');
        
        // Find the menu item
        const menuItem = ionMenuData.find(item => item.n === menuName);
        if (!menuItem || !menuItem.c || menuItem.c.length === 0) {
            hideMegaMenu();
            return;
        }

        // Update active state and ARIA attributes
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

        // Populate and show mega menu
        megaMenuGrid.innerHTML = renderMegaMenuContent(menuItem.c);
        megaMenu.classList.add('active');
        megaMenu.setAttribute('aria-hidden', 'false');
        ionActiveMegaMenu = menuName;
        
        // Focus management
        const firstMenuItem = megaMenu.querySelector('.ion-mega-menu-title');
        if (firstMenuItem) {
            firstMenuItem.focus();
        }
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

    // Enhanced mega menu content rendering
    function renderMegaMenuContent(items) {
        return items.map((item, index) => {
            const hasChildren = item.c && item.c.length > 0;
            const itemId = 'mega-' + item.n.replace(/[^a-zA-Z0-9]/g, '_') + '_' + index;
            
            return `
                <div class="ion-mega-menu-section">
                    <div class="ion-mega-menu-item ${hasChildren ? 'has-children' : ''}" 
                         ${hasChildren ? `onclick="toggleMegaMenuExpansion('${itemId}')"` : `onclick="navigateToUrl('${item.u}')"`}
                         role="menuitem"
                         tabindex="0"
                         ${hasChildren ? 'aria-haspopup="true" aria-expanded="false"' : ''}
                         onkeydown="handleMegaMenuKeydown(event, '${hasChildren ? itemId : item.u}', ${hasChildren})">
                        <div class="ion-mega-menu-title">
                            <span>${ionFormatLabel(item.n)}</span>
                            ${hasChildren ? `
                                <svg class="ion-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            ` : ''}
                        </div>
                        ${hasChildren ? `
                            <div id="${itemId}" class="ion-mega-menu-children" role="menu">
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
                     tabindex="0"
                     onkeydown="handleChildKeydown(event, '${item.u}')">
                    <span class="ion-mega-menu-child-title">${ionFormatLabel(item.n)}</span>
                    ${hasGrandChildren ? `
                        <div class="ion-mega-menu-grandchildren" role="menu">
                            ${item.c.map(grandchild => `
                                <div class="ion-mega-menu-grandchild" 
                                     onclick="navigateToUrl('${grandchild.u}')"
                                     role="menuitem"
                                     tabindex="0"
                                     onkeydown="handleChildKeydown(event, '${grandchild.u}')">
                                    ${ionFormatLabel(grandchild.n)}
                                </div>
                            `).join('')}
                        </div>
                    ` : ''}
                </div>
            `;
        }).join('');
    }

    // Navigation helper
    function navigateToUrl(url) {
        if (url && url !== '#') {
            window.location.href = url;
        }
    }

    // Keyboard navigation handlers
    function handleMegaMenuKeydown(event, target, hasChildren) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            if (hasChildren) {
                toggleMegaMenuExpansion(target);
            } else {
                navigateToUrl(target);
            }
        } else if (event.key === 'Escape') {
            hideMegaMenu();
            // Return focus to the trigger
            const activeNavLink = document.querySelector('.ion-nav-item.active .ion-nav-link');
            if (activeNavLink) {
                activeNavLink.focus();
            }
        }
    }

    function handleChildKeydown(event, url) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            navigateToUrl(url);
        } else if (event.key === 'Escape') {
            hideMegaMenu();
        }
    }

    function toggleMegaMenuExpansion(itemId) {
        const element = document.getElementById(itemId);
        const chevron = element.parentElement.querySelector('.ion-chevron');
        const menuItem = element.parentElement;
        
        const isExpanded = element.classList.contains('expanded');
        element.classList.toggle('expanded');
        
        // Update ARIA attributes
        menuItem.setAttribute('aria-expanded', !isExpanded);
        
        if (chevron) {
            chevron.style.transform = !isExpanded ? 'rotate(90deg)' : 'rotate(0deg)';
        }
    }

    // Enhanced Mobile Menu Functions
    function toggleMobileMenu() {
        const mobileMenu = document.getElementById('ionMobileMenu');
        const menuButton = document.querySelector('.ion-mobile-menu-btn');
        
        mobileMenu.classList.add('active');
        mobileMenu.setAttribute('aria-hidden', 'false');
        menuButton.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
        document.body.classList.add('ion-mobile-menu-open');
        
        // Focus the close button
        const closeButton = mobileMenu.querySelector('.ion-mobile-menu-close');
        if (closeButton) {
            setTimeout(() => closeButton.focus(), 300);
        }
    }

    function closeMobileMenu() {
        const mobileMenu = document.getElementById('ionMobileMenu');
        const menuButton = document.querySelector('.ion-mobile-menu-btn');
        
        mobileMenu.classList.remove('active');
        mobileMenu.setAttribute('aria-hidden', 'true');
        menuButton.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
        document.body.classList.remove('ion-mobile-menu-open');
        
        // Return focus to menu button
        if (menuButton) {
            menuButton.focus();
        }
    }

    function toggleMobileSubmenu(itemName) {
        const safeItemName = itemName.replace(/[^a-zA-Z0-9]/g, '_');
        const itemId = 'mobile-' + safeItemName;
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
        
        // Update chevron rotation
        const chevron = element.parentElement.querySelector('.ion-chevron');
        if (chevron) {
            chevron.style.transform = isExpanded ? 'rotate(0deg)' : 'rotate(90deg)';
        }
    }

    // Additional functionality
    function openSearch() {
        // Implement search functionality
        console.log('Search functionality to be implemented');
        // You can integrate with WordPress search or custom search
        if (typeof window.ionSearchModal !== 'undefined') {
            window.ionSearchModal.open();
        } else {
            // Fallback to WordPress search
            const searchUrl = '<?php echo esc_url(home_url("/?s=")); ?>';
            const query = prompt('Search ION Network:');
            if (query) {
                window.location.href = searchUrl + encodeURIComponent(query);
            }
        }
    }

    function openSignUp() {
        // Implement sign up functionality
        console.log('Sign up functionality to be implemented');
        // You can integrate with WordPress login/registration
        if (typeof window.ionAuthModal !== 'undefined') {
            window.ionAuthModal.openSignUp();
        } else {
            // Fallback to WordPress registration
            window.location.href = '<?php echo esc_url(wp_registration_url()); ?>';
        }
    }

    function toggleTheme() {
        const html = document.documentElement;
        const currentTheme = html.getAttribute('data-theme') || 'dark';
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        
        html.setAttribute('data-theme', newTheme);
        
        // Save preference
        if (typeof localStorage !== 'undefined') {
            localStorage.setItem('ion-theme-preference', newTheme);
        }
        
        // Trigger custom event for theme change
        const event = new CustomEvent('ionThemeChanged', { 
            detail: { theme: newTheme, previousTheme: currentTheme }
        });
        document.dispatchEvent(event);
    }

    // Initialize theme from saved preference
    function initializeTheme() {
        if (typeof localStorage !== 'undefined') {
            const savedTheme = localStorage.getItem('ion-theme-preference');
            if (savedTheme) {
                document.documentElement.setAttribute('data-theme', savedTheme);
            }
        }
    }

    // Enhanced event listeners
    function initializeEventListeners() {
        // Add hover listeners to navigation items
        document.querySelectorAll('.ion-nav-item').forEach(item => {
            item.addEventListener('mouseleave', () => {
                hideMegaMenu();
            });
        });

        // Close mega menu when clicking outside
        document.addEventListener('click', function(e) {
            const megaMenu = document.getElementById('ionMegaMenu');
            const navBar = document.querySelector('.ion-nav-bar');
            
            if (!navBar.contains(e.target) && !megaMenu.contains(e.target)) {
                const activeMegaMenu = document.getElementById('ionMegaMenu');
                if (activeMegaMenu.classList.contains('active')) {
                    hideMegaMenu();
                }
            }
        });

        // Close mobile menu when clicking outside
        document.addEventListener('click', function(e) {
            const mobileMenu = document.getElementById('ionMobileMenu');
            const mobileMenuPanel = mobileMenu.querySelector('.ion-mobile-menu-panel');
            const mobileMenuBtn = document.querySelector('.ion-mobile-menu-btn');
            
            if (mobileMenu.classList.contains('active') && 
                !mobileMenuPanel.contains(e.target) && 
                !mobileMenuBtn.contains(e.target)) {
                closeMobileMenu();
            }
        });

        // Handle window resize
        let resizeTimeout;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                if (window.innerWidth > 768) {
                    closeMobileMenu();
                    // Clear mobile expanded states
                    ionExpandedMobileItems.clear();
                    document.querySelectorAll('.ion-mobile-submenu').forEach(el => {
                        el.classList.remove('expanded');
                    });
                } else {
                    // Close mega menu on mobile
                    const megaMenu = document.getElementById('ionMegaMenu');
                    if (megaMenu.classList.contains('active')) {
                        hideMegaMenu();
                    }
                }
            }, 250);
        });

        // Handle escape key globally
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const mobileMenu = document.getElementById('ionMobileMenu');
                const megaMenu = document.getElementById('ionMegaMenu');
                
                if (mobileMenu.classList.contains('active')) {
                    closeMobileMenu();
                } else if (megaMenu.classList.contains('active')) {
                    hideMegaMenu();
                }
            }
        });

        // Prevent scroll when mobile menu is open
        document.addEventListener('touchmove', function(e) {
            if (document.body.classList.contains('ion-mobile-menu-open')) {
                e.preventDefault();
            }
        }, { passive: false });
    }

    // Make functions globally available
    window.showMegaMenu = showMegaMenu;
    window.hideMegaMenu = hideMegaMenu;
    window.keepMegaMenuOpen = keepMegaMenuOpen;
    window.toggleMobileMenu = toggleMobileMenu;
    window.closeMobileMenu = closeMobileMenu;
    window.toggleMobileSubmenu = toggleMobileSubmenu;
    window.openSearch = openSearch;
    window.openSignUp = openSignUp;
    window.toggleTheme = toggleTheme;

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            initializeTheme();
            initializeEventListeners();
        });
    } else {
        initializeTheme();
        initializeEventListeners();
    }

    // Performance optimization: Preload critical menu data
    if ('requestIdleCallback' in window) {
        requestIdleCallback(() => {
            // Preload first level menu items for faster mega menu display
            ionMenuData.forEach(item => {
                if (item.c && item.c.length > 0) {
                    // Pre-render menu content template
                    const template = document.createElement('template');
                    template.innerHTML = renderMegaMenuContent([item]);
                }
            });
        });
    }

})();
</script>

<!-- Screen reader only styles -->
<style>
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

/* Additional mobile menu open styles */
body.ion-mobile-menu-open {
    position: fixed;
    width: 100%;
    height: 100%;
}

/* High contrast mode improvements */
@media (prefers-contrast: high) {
    .ion-nav-link:focus,
    .ion-btn:focus {
        outline: 3px solid;
        outline-offset: 2px;
    }
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
    .ion-mega-menu,
    .ion-mobile-menu,
    .ion-mobile-menu-panel {
        transition: none !important;
        animation: none !important;
    }
}
</style>

<?php
/**
 * Hook for additional customization
 * Other plugins/themes can hook into this to add custom functionality
 */
if (function_exists('do_action')) {
    do_action('ion_navigation_after_render');
}
?>