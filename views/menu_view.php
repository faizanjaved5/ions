
    <link rel="stylesheet" href="/ion/css/menu.css">
    <style>
        /* Additional page content styles */
        .page-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 4rem 1rem;
            min-height: calc(100vh - 5rem);
        }
        
        .welcome-section {
            text-align: center;
            padding: 4rem 2rem;
            background: var(--card);
            border-radius: 12px;
            border: 1px solid var(--ion-border);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .welcome-section h1 {
            font-size: 3rem;
            color: var(--ion-blue);
            margin-bottom: 1.5rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 400;
        }
        
        .welcome-section p {
            font-size: 1.25rem;
            color: var(--foreground);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            opacity: 0.8;
        }
        
        /* Ensure all parent containers allow overflow for flyout menus */
        .ion-mega-menu {
            overflow: visible !important;
        }
        
        .ion-mega-menu-content {
            overflow: visible !important;
        }
        
        .ion-mega-menu-grid {
            overflow: visible !important;
        }
        
        .ion-mega-menu-section {
            overflow: visible !important;
            position: relative;
            z-index: 1;
        }
        
        /* When section is hovered, raise it */
        .ion-mega-menu-section:hover {
            z-index: 10005 !important;
        }
        
        /* Add invisible bridge on section to prevent gaps when moving to children */
        .ion-mega-menu-section::before {
            content: '';
            position: absolute;
            right: -0.25rem;
            top: 0;
            width: 0.75rem;
            height: 100%;
            background: transparent;
            z-index: 10000;
            pointer-events: auto;
        }
        
        /* When any child is hovered, raise the entire section */
        .ion-mega-menu-section:has(.ion-mega-menu-child:hover) {
            z-index: 10005 !important;
        }
        
        /* Keep section raised when hovering grandchildren */
        .ion-mega-menu-section:has(.ion-mega-menu-grandchildren:hover) {
            z-index: 10005 !important;
        }
        
        /* Mega Menu Enhancements - Flyout Style */
        .ion-mega-menu-item {
            position: relative;
            z-index: 1;
            overflow: visible !important;
        }
        
        .ion-mega-menu-item.has-children {
            position: relative;
            z-index: 1;
            overflow: visible !important;
        }
        
        .ion-mega-menu-item:hover {
            z-index: 10002;
        }
        
        .ion-mega-menu-child.has-grandchildren {
            position: relative;
            overflow: visible !important;
            z-index: 1;
        }
        
        .ion-mega-menu-child {
            overflow: visible !important;
            position: relative;
            z-index: 1;
        }
        
        /* Ensure hovered child items rise above siblings */
        .ion-mega-menu-child:hover {
            z-index: 10003 !important;
        }
        
        .ion-mega-menu-child-wrapper {
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            padding: 0.5rem 0.75rem;
            border-radius: 0.375rem;
            transition: all 0.2s ease;
            position: relative;
        }
        
        .ion-mega-menu-child-wrapper:hover {
            background: var(--ion-nav-hover);
        }
        
        /* Anchor tag styles for children without grandchildren */
        .ion-mega-menu-child-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.5rem 0.75rem;
            border-radius: 0.375rem;
            transition: all 0.2s ease;
            text-decoration: none;
            color: var(--foreground);
            width: 100%;
        }
        
        .ion-mega-menu-child-link:hover {
            background: var(--ion-nav-hover);
            color: var(--ion-gold);
            border-left: 2px solid var(--ion-gold);
            padding-left: 1rem;
            transform: translateX(2px);
        }
        
        /* Create an invisible bridge between child and grandchildren to prevent gaps */
        .ion-mega-menu-child.has-grandchildren::after {
            content: '';
            position: absolute;
            right: -0.25rem;
            top: 0;
            width: 0.75rem;
            height: 100%;
            background: transparent;
            z-index: 10003;
            pointer-events: auto;
        }
        
        /* Ensure grandchildren overlap slightly with parent to prevent gaps */
        .ion-mega-menu-child.has-grandchildren:hover::after {
            right: -0.5rem;
            width: 1rem;
        }
        
        /* Override menu.css - Children appear on the RIGHT side */
        .ion-mega-menu-children {
            display: none !important;
            position: absolute;
            left: 100%;
            top: 0;
            min-width: 250px;
            margin-left: 0.1rem;
            background: var(--card);
            border: 1px solid var(--ion-border);
            border-radius: 0.5rem;
            padding: 0.5rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
            z-index: 10000 !important;
        }
        
        /* Show children when hovering the ENTIRE SECTION - much easier to access! */
        .ion-mega-menu-section:hover .ion-mega-menu-children {
            display: block !important;
            animation: slideRight 0.2s ease;
        }
        
        /* Also keep children visible when hovering the children menu itself */
        .ion-mega-menu-children:hover {
            display: block !important;
        }
        
        /* Grandchildren also appear on the RIGHT */
        .ion-mega-menu-grandchildren {
            display: none !important;
            position: absolute;
            left: 100%;
            top: 0;
            min-width: 220px;
            margin-left: 0.1rem;
            background: var(--ion-darker);
            border: 1px solid var(--ion-border);
            border-radius: 0.5rem;
            padding: 0.5rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
            z-index: 10004 !important;
        }
        
        /* Pure CSS hover - Show grandchildren when hovering parent child */
        .ion-mega-menu-child.has-grandchildren:hover > .ion-mega-menu-grandchildren {
            display: block !important;
            animation: slideRight 0.2s ease;
        }
        
        /* CRITICAL: Keep grandchildren visible when hovering the grandchildren menu itself */
        .ion-mega-menu-grandchildren:hover {
            display: block !important;
        }
        
        @keyframes slideRight {
            from {
                opacity: 0;
                transform: translateX(-10px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-5px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateX(-5px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .ion-mega-menu-grandchild {
            display: block;
            padding: 0.5rem 0.75rem;
            font-size: 0.9rem;
            color: var(--ion-blue);
            border-radius: 0.375rem;
            margin-bottom: 0.25rem;
            transition: all 0.2s ease;
            cursor: pointer;
            border-left: 2px solid transparent;
            text-decoration: none;
        }
        
        .ion-mega-menu-grandchild:hover {
            background: var(--ion-muted);
            color: var(--ion-gold);
            border-left-color: var(--ion-gold);
            padding-left: 1rem;
            transform: translateX(2px);
        }
        
        .ion-chevron-small {
            opacity: 0.6;
            transition: opacity 0.2s ease;
        }
        
        .ion-mega-menu-child-wrapper:hover .ion-chevron-small {
            opacity: 1;
            color: var(--ion-gold);
        }
        
        @media (max-width: 768px) {
            .welcome-section h1 {
                font-size: 2rem;
            }
            
            .welcome-section p {
                font-size: 1rem;
            }
            
            .page-content {
                padding: 2rem 1rem;
            }
        }
    </style>

    <!-- ION Networks Navigation Header -->
    <nav class="ion-navigation ourownnavigation" role="navigation" aria-label="Main Navigation">
        <div class="ion-nav-container">
            <div class="ion-nav-bar">
                <!-- Logo Section -->
                <div class="ion-logo-section">
                    <a href="/" class="ion-logo-link" aria-label="ION Networks Home">
                        <img src="<?php echo htmlspecialchars($logoUrl ?? '/ion/omar/menu/ion-logo-gold.png'); ?>" 
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
    <div id="ionMobileMenu ourownnavigation" class="ion-mobile-menu" role="dialog" aria-modal="true" aria-hidden="true">
        <div class="ion-mobile-menu-panel">
            <div class="ion-mobile-menu-header">
                <a href="/" class="ion-mobile-menu-logo-link">
                    <img src="<?php echo htmlspecialchars($logoUrl ?? '/ion/omar/menu/ion-logo-gold.png'); ?>" 
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

    


