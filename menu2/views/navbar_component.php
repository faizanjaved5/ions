<?php
/**
 * ION Networks Navigation Navbar Component
 * Reusable navbar that can be injected into pulled pages
 */

// Helper function to render mobile menu items
function renderMobileMenuItems($items, $level = 0) {
    $html = '';
    foreach ($items as $item) {
        $hasChildren = !empty($item['children']);
        $itemId = 'mobile-' . preg_replace('/[^a-zA-Z0-9]/', '_', $item['label']);
        
        $html .= '<div class="ion-mobile-menu-item">';
        if ($hasChildren) {
            $html .= '<div class="ion-mobile-menu-link" onclick="toggleMobileSubmenu(\'' . $itemId . '\')">';
            $html .= htmlspecialchars($item['label']);
            $html .= '<svg class="ion-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>';
            $html .= '</div>';
            $html .= '<div id="' . $itemId . '" class="ion-mobile-submenu">';
            $html .= renderMobileMenuItems($item['children'], $level + 1);
            $html .= '</div>';
        } else {
            $html .= '<a href="' . htmlspecialchars($item['url']) . '" class="ion-mobile-menu-link">';
            $html .= htmlspecialchars($item['label']);
            $html .= '</a>';
        }
        $html .= '</div>';
    }
    return $html;
}
?>
<!-- ION Networks Navigation Header -->
<nav class="ion-navigation" role="navigation" aria-label="Main Navigation">
    <div class="ion-nav-container">
        <div class="ion-nav-bar">
            <!-- Logo Section -->
            <div class="ion-logo-section">
                <a href="/" class="ion-logo-link" aria-label="ION Networks Home">
                    <img src="<?php echo htmlspecialchars($logoUrl); ?>" 
                         alt="ION Networks Logo" class="ion-logo" />
                </a>
            </div>

            <!-- Desktop Navigation -->
            <div class="ion-desktop-nav" role="menubar">
                <!-- Dynamic Navigation Items from ion_menus table -->
                <?php if (!empty($menus)): ?>
                    <?php foreach ($menus as $menu): ?>
                        <div class="ion-nav-item" 
                             data-menu="<?php echo htmlspecialchars($menu['name']); ?>" 
                             role="none">
                            <a href="#" 
                               class="ion-nav-link"
                               role="menuitem"
                               <?php if (!empty($menu['items'])): ?>
                               aria-haspopup="true"
                               aria-expanded="false"
                               onmouseenter="showMegaMenu('<?php echo htmlspecialchars($menu['name'], ENT_QUOTES); ?>', <?php echo htmlspecialchars(json_encode($menu['items'])); ?>)"
                               <?php endif; ?>
                               onclick="return false;">
                                <?php 
                                // Highlight ION text in menu names
                                $menuName = htmlspecialchars($menu['name']);
                                $menuName = preg_replace('/ION/', '<span class="ion-text">ION</span>', $menuName);
                                echo $menuName;
                                ?>
                                <?php if (!empty($menu['items'])): ?>
                                    <svg class="ion-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                <?php endif; ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <!-- Search Section -->
                <div class="ion-search-section" id="ionSearchSection" aria-label="Search ION">
                    <button class="ion-search-collapsed" id="ionSearchCollapsed" type="button" aria-label="Open Search">
                        <svg class="ion-search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        <span class="ion-search-text">SEARCH <span class="ion-text">ION</span></span>
                    </button>
                    
                    <form class="ion-search-expanded" id="ionSearchExpanded" action="/search/" method="GET" style="display: none;">
                        <input type="text" name="q" class="ion-search-input" placeholder="Search menus, content..." 
                               required autocomplete="off" />
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
<div id="ionMobileMenu" class="ion-mobile-menu" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="ion-mobile-menu-panel">
        <div class="ion-mobile-menu-header">
            <a href="/" class="ion-mobile-menu-logo-link">
                <img src="<?php echo htmlspecialchars($logoUrl); ?>" 
                     alt="ION Networks Logo" class="ion-mobile-menu-logo" />
            </a>
            <button class="ion-mobile-menu-close" onclick="closeMobileMenu()" aria-label="Close Mobile Menu">
                <svg class="ion-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        
        <div class="ion-mobile-menu-content" role="menu">
            <!-- Mobile menu items from database -->
            <div class="ion-mobile-search">
                <form action="/search.php" method="GET">
                    <input type="text" name="q" placeholder="Search ION Network..." autocomplete="off">
                    <button type="submit">Search</button>
                </form>
            </div>
            
            <?php if (!empty($menus)): ?>
                <?php foreach ($menus as $menu): ?>
                    <?php if (!empty($menu['items'])): ?>
                        <?php echo renderMobileMenuItems($menu['items']); ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include the JavaScript for the navbar
$navbarScriptPath = __DIR__ . '/navbar_scripts.php';
if (file_exists($navbarScriptPath)) {
    include $navbarScriptPath;
}
?>

