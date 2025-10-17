// ION Menu JavaScript - Rebuilt to match React exactly
// Maintains 100% business logic from React version

// Global state
let currentOpenMenu = null;
let currentTheme = localStorage.getItem('ion-theme') || 'dark';
let searchData = null;
let useBebasFont = false;
let currentRegion = 'featured';
let currentCountry = null;

// ION Local Menu Functions
function showRegion(regionId) {
    // Update sidebar active state
    document.querySelectorAll('.ion-sidebar-item').forEach(item => {
        item.classList.remove('active');
        if (item.dataset.region === regionId) {
            item.classList.add('active');
        }
    });
    
    // Hide all region contents
    document.querySelectorAll('.ion-region-content').forEach(content => {
        content.classList.remove('active');
    });
    
    // Show selected region
    const regionContent = document.getElementById(`region-${regionId}`);
    if (regionContent) {
        regionContent.classList.add('active');
        
        // Show countries, hide any open country content
        const countriesDiv = document.getElementById(`region-${regionId}-countries`);
        if (countriesDiv) {
            countriesDiv.style.display = 'grid';
        }
        
        // Hide all country contents in this region
        document.querySelectorAll(`[id^="country-${regionId}-"]`).forEach(countryContent => {
            countryContent.classList.remove('active');
        });
    }
    
    currentRegion = regionId;
    currentCountry = null;
}

function showCountry(regionId, countryId) {
    // Hide countries grid
    const countriesDiv = document.getElementById(`region-${regionId}-countries`);
    if (countriesDiv) {
        countriesDiv.style.display = 'none';
    }
    
    // Hide all country contents in this region
    document.querySelectorAll(`[id^="country-${regionId}-"]`).forEach(countryContent => {
        countryContent.classList.remove('active');
    });
    
    // Show selected country content
    const countryContent = document.getElementById(`country-${regionId}-${countryId}`);
    if (countryContent) {
        countryContent.classList.add('active');
    }
    
    currentCountry = countryId;
}

function searchIONLocal(query) {
    if (query.length < 2) {
        // Show current view if search is cleared
        if (currentCountry) {
            showCountry(currentRegion, currentCountry);
        } else {
            showRegion(currentRegion);
        }
        return;
    }
    
    query = query.toLowerCase();
    
    // Hide all items
    document.querySelectorAll('.ion-item-link, .ion-item-button').forEach(item => {
        const text = item.textContent.toLowerCase();
        if (text.includes(query)) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
}

function toggleFont() {
    useBebasFont = !useBebasFont;
    if (useBebasFont) {
        document.body.classList.add('bebas-font');
    } else {
        document.body.classList.remove('bebas-font');
    }
}

// Initialize on DOM load
document.addEventListener('DOMContentLoaded', function() {
    initializeTheme();
    loadSearchData();
    setupEventListeners();
    setupClickOutside();
});

// Theme management
function initializeTheme() {
    document.documentElement.className = currentTheme;
    updateThemeIcons();
}

function toggleTheme() {
    currentTheme = currentTheme === 'dark' ? 'light' : 'dark';
    document.documentElement.className = currentTheme;
    localStorage.setItem('ion-theme', currentTheme);
    updateThemeIcons();
}

function updateThemeIcons() {
    const sunIcon = document.getElementById('sun-icon');
    const moonIcon = document.getElementById('moon-icon');
    
    if (currentTheme === 'dark') {
        sunIcon.classList.add('hidden');
        moonIcon.classList.remove('hidden');
    } else {
        sunIcon.classList.remove('hidden');
        moonIcon.classList.add('hidden');
    }
}

// Menu management
function toggleMenu(menuId) {
    const menu = document.getElementById(menuId);
    
    // Close current menu if different
    if (currentOpenMenu && currentOpenMenu !== menuId) {
        closeMenu(currentOpenMenu);
    }
    
    if (menu.classList.contains('show')) {
        closeMenu(menuId);
    } else {
        openMenu(menuId);
    }
}

function openMenu(menuId) {
    const menu = document.getElementById(menuId);
    menu.classList.add('show');
    currentOpenMenu = menuId;
    
    // Add animation delay for smooth appearance
    setTimeout(() => {
        menu.classList.add('animate-in');
    }, 10);
}

function closeMenu(menuId) {
    const menu = document.getElementById(menuId);
    menu.classList.remove('show', 'animate-in');
    
    if (currentOpenMenu === menuId) {
        currentOpenMenu = null;
    }
}

function closeAllMenus() {
    const menus = document.querySelectorAll('.menu-dropdown');
    menus.forEach(menu => {
        menu.classList.remove('show', 'animate-in');
    });
    currentOpenMenu = null;
}

// Search functionality
function toggleSearch() {
    const modal = document.getElementById('search-modal');
    const input = document.getElementById('search-input');
    
    if (modal.classList.contains('hidden') || modal.style.display === 'none') {
        modal.classList.remove('hidden');
        modal.style.display = 'flex';
        setTimeout(() => {
            input.focus();
        }, 100);
    } else {
        modal.classList.add('hidden');
        modal.style.display = 'none';
        input.value = '';
        clearSearchResults();
    }
}

function loadSearchData() {
    // Combine all menu data for search
    Promise.all([
        fetch('menuData.json').then(r => r.json()),
        fetch('networksMenuData.json').then(r => r.json()),
        fetch('initiativesMenuData.json').then(r => r.json()),
        fetch('shopsMenuData.json').then(r => r.json()),
        fetch('connectionsMenuData.json').then(r => r.json())
    ]).then(([menu, networks, initiatives, shops, connections]) => {
        searchData = {
            locations: extractLocations(menu),
            sports: extractSports(networks),
            initiatives: extractInitiatives(initiatives),
            shops: extractShops(shops),
            connections: extractConnections(connections)
        };
        
        // Setup search input listener
        const searchInput = document.getElementById('search-input');
        searchInput.addEventListener('input', handleSearch);
    }).catch(error => {
        console.error('Error loading search data:', error);
    });
}

function extractLocations(menuData) {
    const locations = [];
    menuData.regions.forEach(region => {
        region.countries.forEach(country => {
            if (country.states) {
                country.states.forEach(state => {
                    locations.push({
                        name: state.name,
                        url: state.url || generateUrl(state.name),
                        type: 'location',
                        country: country.name,
                        region: region.name
                    });
                });
            }
            if (country.cities) {
                country.cities.forEach(city => {
                    locations.push({
                        name: city.name,
                        url: city.url || generateUrl(city.name),
                        type: 'location',
                        country: country.name,
                        region: region.name
                    });
                });
            }
        });
    });
    return locations;
}

function extractSports(networksData) {
    const sports = [];
    if (networksData.networks) {
        networksData.networks.forEach(network => {
            sports.push({
                name: network.title,
                url: network.url || '#',
                type: 'network',
                category: 'Networks'
            });
            if (network.children) {
                network.children.forEach(child => {
                    sports.push({
                        name: child.title,
                        url: child.url || '#',
                        type: 'network',
                        category: network.title
                    });
                });
            }
        });
    }
    return sports;
}

function extractInitiatives(initiativesData) {
    const initiatives = [];
    if (initiativesData.initiatives) {
        initiativesData.initiatives.forEach(initiative => {
            initiatives.push({
                name: initiative.title,
                url: initiative.url || '#',
                type: 'initiative',
                category: 'Initiatives'
            });
            if (initiative.children) {
                initiative.children.forEach(child => {
                    initiatives.push({
                        name: child.title,
                        url: child.url || '#',
                        type: 'initiative',
                        category: initiative.title
                    });
                });
            }
        });
    }
    return initiatives;
}

function extractShops(shopsData) {
    const shops = [];
    
    if (shopsData.categories) {
        shopsData.categories.forEach(category => {
            if (category.items) {
                category.items.forEach(item => {
                    shops.push({
                        name: item.name,
                        url: item.url || '#',
                        type: 'shop',
                        category: category.name
                    });
                });
            }
        });
    }
    
    return shops;
}

function extractConnections(connectionsData) {
    const connections = [];
    if (connectionsData.connections) {
        connectionsData.connections.forEach(connection => {
            connections.push({
                name: connection.title,
                url: connection.url || '#',
                type: 'connection',
                category: 'Connections'
            });
            if (connection.children) {
                connection.children.forEach(child => {
                    connections.push({
                        name: child.title,
                        url: child.url || '#',
                        type: 'connection',
                        category: connection.title
                    });
                    if (child.children) {
                        child.children.forEach(grandchild => {
                            connections.push({
                                name: grandchild.title,
                                url: grandchild.url || '#',
                                type: 'connection',
                                category: child.title
                            });
                        });
                    }
                });
            }
        });
    }
    return connections;
}

function handleSearch(event) {
    const query = event.target.value.toLowerCase().trim();
    
    if (query.length < 2) {
        clearSearchResults();
        return;
    }
    
    if (!searchData) return;
    
    const results = [];
    
    // Search all categories
    Object.values(searchData).forEach(category => {
        category.forEach(item => {
            if (item.name.toLowerCase().includes(query) ||
                (item.description && item.description.toLowerCase().includes(query)) ||
                (item.category && item.category.toLowerCase().includes(query))) {
                results.push(item);
            }
        });
    });
    
    displaySearchResults(results.slice(0, 10)); // Limit to 10 results
}

function displaySearchResults(results) {
    const container = document.getElementById('search-results');
    
    if (results.length === 0) {
        container.innerHTML = '<div class="search-no-results">No results found</div>';
        return;
    }
    
    container.innerHTML = results.map(result => `
        <a href="${result.url}" class="search-result-item">
            <div class="search-result-content">
                <div class="search-result-name">${result.name}</div>
                <div class="search-result-meta">
                    <span class="search-result-type">${result.type}</span>
                    ${result.category ? `<span class="search-result-category">${result.category}</span>` : ''}
                </div>
                ${result.description ? `<div class="search-result-description">${result.description}</div>` : ''}
            </div>
            <svg class="search-result-arrow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
        </a>
    `).join('');
}

function clearSearchResults() {
    document.getElementById('search-results').innerHTML = '';
}

// Mobile menu
function toggleMobileMenu() {
    const menu = document.getElementById('mobile-menu');
    menu.classList.toggle('show');
    menu.classList.toggle('hidden');
}

function toggleMobileSubmenu(submenuId) {
    const submenu = document.getElementById(submenuId);
    submenu.classList.toggle('show');
}

// Tab functionality for ION Mall
function switchTab(tabId) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remove active class from all tabs
    document.querySelectorAll('.menu-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Show selected tab content
    document.getElementById(tabId).classList.add('active');
    
    // Add active class to clicked tab
    event.target.classList.add('active');
}

// Menu filtering
function filterMenuItems(input, contentId) {
    const query = input.value.toLowerCase();
    const content = document.getElementById(contentId);
    const items = content.querySelectorAll('[class*="menu-"][class*="-link"], .store-card');
    
    items.forEach(item => {
        const text = item.textContent.toLowerCase();
        if (text.includes(query)) {
            item.style.display = '';
            item.parentElement.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });
    
    // Hide empty categories
    const categories = content.querySelectorAll('.menu-category, .menu-region, .menu-country');
    categories.forEach(category => {
        const visibleItems = category.querySelectorAll('[class*="menu-"][class*="-link"]:not([style*="display: none"]), .store-card:not([style*="display: none"])');
        if (visibleItems.length === 0) {
            category.style.display = 'none';
        } else {
            category.style.display = '';
        }
    });
}

// Utility functions
function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function generateUrl(name) {
    return `https://ions.com/ion-${name.toLowerCase().replace(/\s+/g, '-')}`;
}

// Event listeners
function setupEventListeners() {
    // Keyboard shortcuts
    document.addEventListener('keydown', function(event) {
        // ESC key closes menus and modals
        if (event.key === 'Escape') {
            closeAllMenus();
            
            const searchModal = document.getElementById('search-modal');
            if (!searchModal.classList.contains('hidden')) {
                toggleSearch();
            }
            
            const mobileMenu = document.getElementById('mobile-menu');
            if (mobileMenu.classList.contains('show')) {
                toggleMobileMenu();
            }
        }
        
        // Ctrl/Cmd + K opens search
        if ((event.ctrlKey || event.metaKey) && event.key === 'k') {
            event.preventDefault();
            toggleSearch();
        }
    });
    
    // Handle menu hover for desktop
    const menuItems = document.querySelectorAll('.menu-item');
    menuItems.forEach(item => {
        const button = item.querySelector('.menu-button');
        const dropdown = item.querySelector('.menu-dropdown');
        
        let hoverTimeout;
        
        item.addEventListener('mouseenter', () => {
            clearTimeout(hoverTimeout);
            const menuId = dropdown.id;
            openMenu(menuId);
        });
        
        item.addEventListener('mouseleave', () => {
            hoverTimeout = setTimeout(() => {
                const menuId = dropdown.id;
                closeMenu(menuId);
            }, 150);
        });
    });
}

function setupClickOutside() {
    document.addEventListener('click', function(event) {
        // Close menus when clicking outside
        if (!event.target.closest('.menu-item') && !event.target.closest('.menu-dropdown')) {
            closeAllMenus();
        }
        
        // Close search modal when clicking outside
        const searchModal = document.getElementById('search-modal');
        if ((!searchModal.classList.contains('hidden') && searchModal.style.display !== 'none') && 
            !event.target.closest('.search-modal-content') && 
            !event.target.closest('[onclick="toggleSearch()"]')) {
            toggleSearch();
        }
        
        // Close mobile menu when clicking outside
        const mobileMenu = document.getElementById('mobile-menu');
        if (mobileMenu.classList.contains('show') && 
            !event.target.closest('.mobile-menu-content') && 
            !event.target.closest('[onclick="toggleMobileMenu()"]')) {
            toggleMobileMenu();
        }
    });
}

// Responsive behavior
window.addEventListener('resize', function() {
    // Close mobile menu on resize to desktop
    if (window.innerWidth >= 1280) { // xl breakpoint
        const mobileMenu = document.getElementById('mobile-menu');
        if (mobileMenu.classList.contains('show')) {
            toggleMobileMenu();
        }
    }
    
    // Close all menus on mobile
    if (window.innerWidth < 1280) {
        closeAllMenus();
    }
});

// Performance optimization: Debounce search
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Replace direct search handler with debounced version
const debouncedSearch = debounce(handleSearch, 300);

// Update search input listener
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('search-input');
    if (searchInput) {
        searchInput.removeEventListener('input', handleSearch);
        searchInput.addEventListener('input', debouncedSearch);
    }
});

// Accessibility improvements
document.addEventListener('DOMContentLoaded', function() {
    // Add ARIA attributes
    const menuButtons = document.querySelectorAll('.menu-button');
    menuButtons.forEach((button, index) => {
        button.setAttribute('aria-haspopup', 'true');
        button.setAttribute('aria-expanded', 'false');
        
        const dropdown = button.parentElement.querySelector('.menu-dropdown');
        if (dropdown) {
            dropdown.setAttribute('aria-hidden', 'true');
            dropdown.setAttribute('role', 'menu');
        }
    });
    
    // Update ARIA attributes when menus open/close
    const originalOpenMenu = openMenu;
    const originalCloseMenu = closeMenu;
    
    window.openMenu = function(menuId) {
        originalOpenMenu(menuId);
        const menu = document.getElementById(menuId);
        const button = menu.parentElement.querySelector('.menu-button');
        if (button) {
            button.setAttribute('aria-expanded', 'true');
        }
        menu.setAttribute('aria-hidden', 'false');
    };
    
    window.closeMenu = function(menuId) {
        originalCloseMenu(menuId);
        const menu = document.getElementById(menuId);
        const button = menu.parentElement.querySelector('.menu-button');
        if (button) {
            button.setAttribute('aria-expanded', 'false');
        }
        menu.setAttribute('aria-hidden', 'true');
    };
});
