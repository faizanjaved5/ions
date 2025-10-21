<script>
    (function() {
        'use strict';
        
        let isSearchExpanded = false;
        let ionActiveMegaMenu = null;
        let ionMegaMenuTimeout = null;
        let currentMenuChildren = null;

        // Format label with ION highlighting
        function ionFormatLabel(label) {
            const safeLabel = String(label).replace(/[<>'"&]/g, function(match) {
                const escapeMap = {'<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;', '&': '&amp;'};
                return escapeMap[match];
            });
            return safeLabel.replace(/ION/g, '<span class="ion-text">ION</span>');
        }

        // Mega Menu Functions
        function showMegaMenu(menuName, children) {
            if (isSearchExpanded) return;
            if (!children || children.length === 0) return;
            
            clearTimeout(ionMegaMenuTimeout);
            
            const megaMenu = document.getElementById('ionMegaMenu');
            const megaMenuGrid = document.getElementById('ionMegaMenuGrid');
            
            if (!megaMenu || !megaMenuGrid) return;

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

            megaMenuGrid.innerHTML = renderMegaMenuContent(children);
            megaMenu.classList.add('active');
            megaMenu.setAttribute('aria-hidden', 'false');
            ionActiveMegaMenu = menuName;
            currentMenuChildren = children;
            
            // Setup z-index handling after content is rendered
            setupChildrenZIndex();
        }

        function hideMegaMenu() {
            ionMegaMenuTimeout = setTimeout(() => {
                const megaMenu = document.getElementById('ionMegaMenu');
                if (!megaMenu) return;
                
                megaMenu.classList.remove('active');
                megaMenu.setAttribute('aria-hidden', 'true');
                
                document.querySelectorAll('.ion-nav-item').forEach(item => {
                    item.classList.remove('active');
                    const link = item.querySelector('.ion-nav-link');
                    if (link) link.setAttribute('aria-expanded', 'false');
                });
                ionActiveMegaMenu = null;
                currentMenuChildren = null;
            }, 150);
        }

        function keepMegaMenuOpen() {
            clearTimeout(ionMegaMenuTimeout);
        }

        function renderMegaMenuContent(items) {
            return items.map((item, index) => {
                const hasChildren = item.children && item.children.length > 0;
                const itemId = 'mega-' + item.label.replace(/[^a-zA-Z0-9]/g, '_') + '_' + index;
                
                return `
                    <div class="ion-mega-menu-section">
                        <div class="ion-mega-menu-item ${hasChildren ? 'has-children' : ''}" 
                             role="menuitem">
                            <div class="ion-mega-menu-title">
                                <a href="${item.url}" style="text-decoration: none; color: inherit; display: flex; align-items: center; justify-content: space-between; width: 100%;">
                                    <span>${ionFormatLabel(item.label)}</span>
                                    ${hasChildren ? `
                                        <svg class="ion-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                        </svg>
                                    ` : ''}
                                </a>
                            </div>
                            ${hasChildren ? `
                                <div id="${itemId}" class="ion-mega-menu-children">
                                    ${renderMegaMenuChildren(item.children)}
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `;
            }).join('');
        }

        function renderMegaMenuChildren(items) {
            return items.map(item => {
                const hasGrandChildren = item.children && item.children.length > 0;
                return `
                    <div class="ion-mega-menu-child ${hasGrandChildren ? 'has-grandchildren' : ''}" 
                         role="menuitem">
                        ${hasGrandChildren ? `
                            <div class="ion-mega-menu-child-wrapper">
                                <span class="ion-mega-menu-child-title">${ionFormatLabel(item.label)}</span>
                                <svg class="ion-chevron-small" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 14px; height: 14px; margin-left: 0.5rem;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </div>
                        ` : `
                            <a href="${item.url}" class="ion-mega-menu-child-link">
                                <span class="ion-mega-menu-child-title">${ionFormatLabel(item.label)}</span>
                            </a>
                        `}
                        ${hasGrandChildren ? `
                            <div class="ion-mega-menu-grandchildren">
                                ${item.children.map(grandchild => `
                                    <a href="/ion/pullpage.php?url=${grandchild.url}" class="ion-mega-menu-grandchild" role="menuitem">
                                        ${ionFormatLabel(grandchild.label)}
                                    </a>
                                `).join('')}
                            </div>
                        ` : ''}
                    </div>
                `;
            }).join('');
        }

        function toggleMegaMenuExpansion(itemId) {
            const element = document.getElementById(itemId);
            if (!element) return;
            
            const chevron = element.parentElement.querySelector('.ion-chevron');
            element.classList.toggle('expanded');
            
            if (chevron) {
                chevron.style.transform = element.classList.contains('expanded') ? 'rotate(90deg)' : 'rotate(0deg)';
            }
        }

        // Search Functions
        function openSearch() {
            if (isSearchExpanded) return;
            
            const searchSection = document.getElementById('ionSearchSection');
            const collapsedSearch = document.getElementById('ionSearchCollapsed');
            const expandedSearch = document.getElementById('ionSearchExpanded');
            
            if (!searchSection || !expandedSearch || !collapsedSearch) return;
            
            searchSection.classList.add('expanded');
            collapsedSearch.style.display = 'none';
            expandedSearch.style.display = 'flex';
            isSearchExpanded = true;
            
            setTimeout(() => {
                const input = expandedSearch.querySelector('.ion-search-input');
                if (input) input.focus();
            }, 150);
        }

        function closeSearch() {
            if (!isSearchExpanded) return;
            
            const searchSection = document.getElementById('ionSearchSection');
            const expandedSearch = document.getElementById('ionSearchExpanded');
            const collapsedSearch = document.getElementById('ionSearchCollapsed');
            
            if (!searchSection || !expandedSearch || !collapsedSearch) return;
            
            searchSection.classList.remove('expanded');
            expandedSearch.style.display = 'none';
            collapsedSearch.style.display = 'flex';
            isSearchExpanded = false;
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
            const element = document.getElementById(itemName);
            if (!element) return;
            
            const isExpanded = element.classList.contains('expanded');
            element.classList.toggle('expanded');
            
            const chevron = element.parentElement.querySelector('.ion-chevron');
            if (chevron) {
                chevron.style.transform = isExpanded ? 'rotate(0deg)' : 'rotate(90deg)';
            }
        }

        // Theme Toggle
        function toggleTheme() {
            const html = document.documentElement;
            const currentTheme = html.getAttribute('data-theme') || 'dark';
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            html.setAttribute('data-theme', newTheme);
            
            if (typeof localStorage !== 'undefined') {
                localStorage.setItem('ion-theme-preference', newTheme);
            }
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
            const searchCollapsed = document.getElementById('ionSearchCollapsed');
            const searchClose = document.getElementById('ionSearchClose');
            const searchBackdrop = document.getElementById('ionSearchBackdrop');
            
            if (searchCollapsed) {
                searchCollapsed.addEventListener('click', function(e) {
                    e.preventDefault();
                    openSearch();
                });
            }
            
            if (searchClose) {
                searchClose.addEventListener('click', function(e) {
                    e.preventDefault();
                    closeSearch();
                });
            }
            
            if (searchBackdrop) {
                searchBackdrop.addEventListener('click', function(e) {
                    e.preventDefault();
                    closeSearch();
                });
            }

            // Mega menu listeners
            document.querySelectorAll('.ion-nav-item').forEach(item => {
                item.addEventListener('mouseleave', hideMegaMenu);
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

            // Global click handler for closing mega menu
            document.addEventListener('click', function(e) {
                const megaMenu = document.getElementById('ionMegaMenu');
                const navBar = document.querySelector('.ion-nav-bar');
                
                if (navBar && megaMenu && !navBar.contains(e.target) && !megaMenu.contains(e.target)) {
                    hideMegaMenu();
                }
            });

            // Keyboard navigation
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

            // Window resize handler
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    closeMobileMenu();
                } else {
                    hideMegaMenu();
                }
            });
        }

        function setupChildrenZIndex() {
            // Attach hover listeners to sections for JavaScript fallback (browsers without :has() support)
            const sections = document.querySelectorAll('.ion-mega-menu-section');
            
            sections.forEach(function(section) {
                // On mouseenter of the section, raise it
                section.addEventListener('mouseenter', function() {
                    // Remove z-raised from all sections
                    document.querySelectorAll('.ion-mega-menu-section').forEach(s => {
                        s.classList.remove('z-raised');
                    });
                    // Elevate current section
                    section.classList.add('z-raised');
                });
                
                // On mouseleave, remove z-raised with delay
                section.addEventListener('mouseleave', function() {
                    setTimeout(function() {
                        if (!section.matches(':hover')) {
                            section.classList.remove('z-raised');
                        }
                    }, 100);
                });
            });
            
            // CSS handles visibility automatically - no JavaScript needed for show/hide!
            // We only manage z-index above for proper layering
        }

        // Make functions globally available
        window.showMegaMenu = showMegaMenu;
        window.hideMegaMenu = hideMegaMenu;
        window.keepMegaMenuOpen = keepMegaMenuOpen;
        window.openSearch = openSearch;
        window.closeSearch = closeSearch;
        window.toggleMobileMenu = toggleMobileMenu;
        window.closeMobileMenu = closeMobileMenu;
        window.toggleMobileSubmenu = toggleMobileSubmenu;
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