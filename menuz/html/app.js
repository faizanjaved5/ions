// ION Network Application - Vanilla JS - FULL IMPLEMENTATION
// ============================================================

// State Management
const state = {
    theme: localStorage.getItem('ion-theme') || 'dark',
    currentMenu: null,
    searchQuery: '',
    selectedRegion: 'featured',
    selectedCountry: null,
    selectedNetwork: null,
    selectedInitiative: null,
    selectedShopCategory: null,
    selectedConnection: null,
    mobileMenuOpen: false,
    useBebasFont: false,
    activeTab: 'Store',
    visibleCounts: {},
    mobileView: 'list',
    stores: []
};

// Data cache
const dataCache = {
    menuData: null,
    networksData: null,
    initiativesData: null,
    shopsData: null,
    connectionsData: null,
    mallStores: null
};

// Initialize the application
function init() {
    updateTheme();
    loadSVGSprites();
    setupEventListeners();
    setupNavigation();
    loadAllData();
}

// Theme Management
function updateTheme() {
    const html = document.documentElement;
    if (state.theme === 'dark') {
        html.classList.add('dark');
        const sun = document.getElementById('sun-icon');
        const moon = document.getElementById('moon-icon');
        if (sun) sun.classList.remove('hidden');
        if (moon) moon.classList.add('hidden');
    } else {
        html.classList.remove('dark');
        const sun = document.getElementById('sun-icon');
        const moon = document.getElementById('moon-icon');
        if (sun) sun.classList.add('hidden');
        if (moon) moon.classList.remove('hidden');
    }
    localStorage.setItem('ion-theme', state.theme);
}

function toggleTheme() {
    state.theme = state.theme === 'dark' ? 'light' : 'dark';
    updateTheme();
}

// Event Listeners
function setupEventListeners() {
    const themeToggle = document.getElementById('theme-toggle');
    if (themeToggle) themeToggle.addEventListener('click', toggleTheme);
    
    const searchDesktop = document.getElementById('search-btn-desktop');
    if (searchDesktop) searchDesktop.addEventListener('click', () => openSearchDialog());
    
    const searchMobile = document.getElementById('search-btn-mobile');
    if (searchMobile) searchMobile.addEventListener('click', () => openSearchDialog());
    
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    if (mobileMenuBtn) mobileMenuBtn.addEventListener('click', () => openMobileMenu());
    
    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('modal-backdrop')) {
            closeAllModals();
        }
    });
    
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeAllModals();
            closeMobileMenu();
        }
    });
}

// Navigation Setup
function setupNavigation() {
    const desktopNav = document.getElementById('desktop-nav');
    if (!desktopNav) return;
    
    const navItems = [
        { label: 'ION Local', menu: 'local' },
        { label: 'ION Networks', menu: 'networks' },
        { label: 'IONITIATIVES', menu: 'initiatives' },
        { label: 'ION Mall', menu: 'mall' },
        { label: 'CONNECT.IONS', menu: 'connections', isSpecial: true }
    ];
    
    navItems.forEach(item => {
        const button = createNavButton(item);
        desktopNav.appendChild(button);
    });
}

function createNavButton(item) {
    const button = document.createElement('button');
    button.className = 'group gap-0 px-3 py-2 font-bebas text-lg uppercase tracking-wider text-gray-400 hover:text-white rounded-md hover:bg-gray-700/50 transition-colors';
    
    if (item.isSpecial) {
        button.innerHTML = `CONNECT<span class="text-primary group-hover:text-white">.ION</span>S`;
    } else {
        const parts = item.label.split('ION');
        if (parts.length > 1) {
            button.innerHTML = `${parts[0]}<span class="text-primary group-hover:text-white">ION</span>${parts[1]}`;
        } else {
            button.textContent = item.label;
        }
    }
    
    button.addEventListener('click', () => openMenu(item.menu));
    return button;
}

// Data Loading
async function loadAllData() {
    try {
        const [menuData, networksData, initiativesData, shopsData, connectionsData, mallStores] = await Promise.all([
            fetch('data/menuData.json').then(r => r.json()),
            fetch('data/networksMenuData.json').then(r => r.json()),
            fetch('data/initiativesMenuData.json').then(r => r.json()),
            fetch('data/shopsMenuData.json').then(r => r.json()),
            fetch('data/connectionsMenuData.json').then(r => r.json()),
            fetch('data/mallOfChampionsStores.json').then(r => r.json())
        ]);
        
        dataCache.menuData = menuData;
        dataCache.networksData = networksData;
        dataCache.initiativesData = initiativesData;
        dataCache.shopsData = shopsData;
        dataCache.connectionsData = connectionsData;
        dataCache.mallStores = mallStores;
        state.stores = mallStores;
        
    } catch (error) {
        console.error('Error loading data:', error);
    }
}

// Menu Management
function openMenu(menuType) {
    state.currentMenu = menuType;
    state.searchQuery = '';
    
    // Reset relevant state based on menu type
    if (menuType === 'local') {
        state.selectedRegion = 'featured';
        state.selectedCountry = null;
    } else if (menuType === 'mall') {
        state.selectedShopCategory = null;
    }
    
    state.selectedNetwork = null;
    state.selectedInitiative = null;
    state.selectedConnection = null;
    state.mobileView = 'list';
    
    switch (menuType) {
        case 'local':
            showIONLocalMenu();
            break;
        case 'networks':
            showIONNetworksMenu();
            break;
        case 'initiatives':
            showIONInitiativesMenu();
            break;
        case 'mall':
            showIONMallMenu();
            break;
        case 'connections':
            showIONConnectionsMenu();
            break;
    }
}

function closeAllModals() {
    const container = document.getElementById('modals-container');
    if (container) {
        container.innerHTML = '';
    }
    state.currentMenu = null;
    state.searchQuery = '';
}

function createModal(content, maxWidth = '960px') {
    const container = document.getElementById('modals-container');
    container.innerHTML = `
        <div class="modal-backdrop fade-in">
            <div class="modal-content" style="max-width: ${maxWidth}; width: calc(100vw - 2rem);">
                ${content}
            </div>
        </div>
    `;
}

// Format text with ION highlighting and trademark
function formatTextWithHighlights(text) {
    let formatted = text;
    formatted = formatted.replace(/\bION\b/g, '<span class="text-primary font-medium">ION</span>');
    formatted = formatted.replace(/™/g, '<sup class="text-[0.5em] font-light opacity-35">™</sup>');
    return formatted;
}

// Utility: Get country code
const countryCodeMap = {
    "usa": "us", "canada": "ca", "mexico": "mx", "belize": "bz", "costa-rica": "cr",
    "el-salvador": "sv", "guatemala": "gt", "honduras": "hn", "nicaragua": "ni", "panama": "pa",
    "argentina": "ar", "bolivia": "bo", "brazil": "br", "chile": "cl", "colombia": "co",
    "ecuador": "ec", "guyana": "gy", "paraguay": "py", "peru": "pe", "suriname": "sr",
    "uruguay": "uy", "venezuela": "ve", "albania": "al", "andorra": "ad", "austria": "at",
    "belgium": "be", "bosnia": "ba", "bulgaria": "bg", "croatia": "hr", "czech-republic": "cz",
    "denmark": "dk", "england": "gb", "estonia": "ee", "finland": "fi", "france": "fr",
    "germany": "de", "greece": "gr", "hungary": "hu", "iceland": "is", "ireland": "ie",
    "italy": "it", "latvia": "lv", "lithuania": "lt", "luxembourg": "lu", "malta": "mt",
    "montenegro": "me", "netherlands": "nl", "norway": "no", "poland": "pl", "portugal": "pt",
    "romania": "ro", "scotland": "gb-sct", "serbia": "rs", "slovakia": "sk", "slovenia": "si",
    "spain": "es", "sweden": "se", "switzerland": "ch", "wales": "gb-wls",
    // Caribbean
    "antigua": "ag", "bahamas": "bs", "barbados": "bb", "cuba": "cu", "dominica": "dm",
    "dominican-republic": "do", "grenada": "gd", "haiti": "ht", "jamaica": "jm", 
    "saint-kitts": "kn", "saint-lucia": "lc", "saint-vincent": "vc", "trinidad": "tt",
    // Middle East
    "iran": "ir", "iraq": "iq", "israel": "il", "kuwait": "kw", "lebanon": "lb",
    "qatar": "qa", "saudi-arabia": "sa", "turkey": "tr", "uae": "ae",
    // Africa
    "egypt": "eg", "kenya": "ke", "morocco": "ma", "nigeria": "ng", "south-africa": "za",
    // Asia-Pacific
    "australia": "au", "bangladesh": "bd", "china": "cn", "fiji": "fj", "india": "in",
    "indonesia": "id", "japan": "jp", "kiribati": "ki", "malaysia": "my", "marshall-islands": "mh",
    "micronesia-country": "fm", "nauru": "nr", "new-zealand": "nz", "pakistan": "pk", "palau": "pw",
    "papua": "pg", "philippines": "ph", "russia": "ru", "samoa": "ws", "singapore": "sg",
    "solomon": "sb", "south-korea": "kr", "thailand": "th", "tonga": "to", "tuvalu": "tv",
    "ukraine": "ua", "vanuatu": "vu", "vietnam": "vn"
};

// State code mapping
const stateCodeMap = {
    "alabama": "AL", "alaska": "AK", "arizona": "AZ", "arkansas": "AR", "california": "CA",
    "colorado": "CO", "connecticut": "CT", "delaware": "DE", "florida": "FL", "georgia": "GA",
    "hawaii": "HI", "idaho": "ID", "illinois": "IL", "indiana": "IN", "iowa": "IA",
    "kansas": "KS", "kentucky": "KY", "louisiana": "LA", "maine": "ME", "maryland": "MD",
    "massachusetts": "MA", "michigan": "MI", "minnesota": "MN", "mississippi": "MS", "missouri": "MO",
    "montana": "MT", "nebraska": "NE", "nevada": "NV", "new hampshire": "NH", "new jersey": "NJ",
    "new york": "NY", "north carolina": "NC", "north dakota": "ND", "ohio": "OH", "oklahoma": "OK",
    "oregon": "OR", "pennsylvania": "PA", "rhode island": "RI", "south carolina": "SC", "south dakota": "SD",
    "tennessee": "TN", "texas": "TX", "utah": "UT", "vermont": "VT", "virginia": "VA",
    "washington": "WA", "washington dc": "DC", "west virginia": "WV", "wisconsin": "WI", "wyoming": "WY"
};

// Generate URL helper
function generateUrl(name) {
    return `https://ions.com/ion-${name.toLowerCase().replace(/\s+/g, '-')}`;
}

// ========================================
// ION LOCAL MENU
// ========================================
function showIONLocalMenu() {
    if (!dataCache.menuData) return;
    
    const isMobile = window.innerWidth < 768;
    const content = `
        <div class="flex h-[520px] flex-col rounded-lg border border-border bg-card overflow-hidden">
            ${renderLocalMenuHeader()}
            <div class="flex flex-1 overflow-hidden">
                ${renderLocalMenuSidebar()}
                ${renderLocalMenuContent()}
            </div>
        </div>
    `;
    
    createModal(content);
    attachLocalMenuListeners();
}

function renderLocalMenuHeader() {
    return `
        <div class="px-4 py-3 border-b border-border">
            <div class="flex items-center justify-between gap-4 mb-3">
                <div class="flex items-center gap-3 min-w-0">
                    ${state.selectedCountry ? `
                        <button onclick="handleLocalBack()" class="h-8 w-8 flex items-center justify-center hover:bg-accent/50 rounded-md transition-colors">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </button>
                    ` : ''}
                    <h2 class="text-lg font-bold">
                        <span class="text-primary">ION</span> <span class="text-foreground">LOCAL NETWORK</span>
                    </h2>
                </div>
                <div class="flex-1 max-w-md relative hidden md:block">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" placeholder="Search ION Local Channels" id="local-search-input" value="${state.searchQuery}"
                        class="w-full pl-10 pr-4 py-2 text-sm uppercase tracking-wide rounded-sm focus:outline-none focus:ring-1 focus:ring-ring bg-input text-foreground placeholder:text-muted-foreground">
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="toggleBebasFont()" class="h-8 w-8 flex items-center justify-center hover:bg-accent/50 rounded-md transition-colors">
                        <span class="${state.useBebasFont ? 'font-bebas' : ''}">Aa</span>
                    </button>
                    <button onclick="toggleTheme()" class="h-8 w-8 flex items-center justify-center hover:bg-accent/50 rounded-md transition-colors">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${state.theme === 'dark' ? 'M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z' : 'M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z'}"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    `;
}

function renderLocalMenuSidebar() {
    const bebasClass = state.useBebasFont ? 'font-bebas text-lg font-normal whitespace-nowrap uppercase tracking-wider' : 'text-sm uppercase tracking-wide';
    const padding = state.useBebasFont ? 'py-2' : 'py-3';
    
    let html = `
        <div class="w-48 md:w-60 border-r border-border overflow-y-auto">
            <button onclick="selectRegion('featured')" 
                class="flex w-full items-center justify-between px-4 ${padding} text-left border-b border-border transition-all ${bebasClass} ${state.selectedRegion === 'featured' ? 'border-l-2 border-l-primary bg-[hsl(var(--menu-item-active))]' : 'hover:bg-[hsl(var(--menu-item-hover))]'}">
                <span><span class="text-primary font-medium">ION</span> FEATURED CHANNELS</span>
                <svg class="w-4 h-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </button>
    `;
    
    dataCache.menuData.regions.forEach(region => {
        html += `
            <button onclick="selectRegion('${region.id}')"
                class="flex w-full items-center justify-between px-4 ${padding} text-left border-b border-border transition-all ${bebasClass} ${state.selectedRegion === region.id ? 'border-l-2 border-l-primary bg-[hsl(var(--menu-item-active))]' : 'hover:bg-[hsl(var(--menu-item-hover))]'}">
                <span><span class="text-primary font-medium">ION</span> ${region.name}</span>
                <svg class="w-4 h-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </button>
        `;
    });
    
    html += `</div>`;
    return html;
}

function renderLocalMenuContent() {
    if (state.searchQuery) {
        return `<div class="flex-1 p-2 overflow-y-auto">${renderLocalSearchResults()}</div>`;
    }
    
    if (state.selectedRegion === 'featured') {
        return `<div class="flex-1 p-2 overflow-y-auto">${renderFeaturedChannels()}</div>`;
    }
    
    if (state.selectedCountry) {
        return `<div class="flex-1 p-2 overflow-y-auto">${renderCountryStates()}</div>`;
    }
    
    return `<div class="flex-1 p-2 overflow-y-auto">${renderRegionCountries()}</div>`;
}

function renderFeaturedChannels() {
    const bebasClass = state.useBebasFont ? 'font-bebas text-lg' : 'text-xs';
    const padding = state.useBebasFont ? 'py-2.5' : 'py-2.5';
    
    let html = '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-[0.3rem]">';
    dataCache.menuData.featuredChannels.forEach(channel => {
        html += `
            <a href="${channel.url}" target="_blank" rel="noopener noreferrer"
                class="group flex items-center gap-2 px-3 ${padding} text-muted-foreground hover:bg-[hsl(var(--menu-item-hover))] hover:text-primary rounded transition-all ${bebasClass} uppercase tracking-wide">
                <svg class="w-3.5 h-3.5 flex-shrink-0 opacity-30 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <span class="truncate flex-1">${formatTextWithHighlights(channel.name)}</span>
            </a>
        `;
    });
    html += '</div>';
    return html;
}

function renderRegionCountries() {
    const region = dataCache.menuData.regions.find(r => r.id === state.selectedRegion);
    if (!region || !region.countries) return '<p class="text-center text-muted-foreground py-8">No countries found</p>';
    
    const bebasClass = state.useBebasFont ? 'font-bebas text-lg' : 'text-xs';
    const padding = state.useBebasFont ? 'py-2.5' : 'py-2.5';
    
    let html = '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-[0.3rem]">';
    region.countries.forEach(country => {
        const hasSubItems = (country.states && country.states.length > 0) || (country.cities && country.cities.length > 0);
        const countryCode = countryCodeMap[country.id] || country.id;
        
        if (hasSubItems) {
            html += `
                <button onclick="selectCountry('${country.id}')" type="button"
                    class="group flex items-center gap-2 px-3 ${padding} text-left text-muted-foreground hover:bg-[hsl(var(--menu-item-hover))] hover:text-primary rounded transition-all justify-between w-full ${bebasClass} uppercase tracking-wide">
                    <div class="flex items-center gap-2 flex-1 min-w-0">
                        <img src="https://iblog.bz/assets/flags/${countryCode}.svg" 
                             alt="${country.name}" 
                             class="w-5 h-4 object-cover flex-shrink-0"
                             onerror="this.style.display='none'"
                             onload="this.style.display='block'">
                        <span class="truncate flex-1">${formatTextWithHighlights(country.name)}</span>
                    </div>
                    <svg class="w-4 h-4 opacity-50 group-hover:opacity-100 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
            `;
        } else {
            const url = generateUrl(country.name);
            html += `
                <a href="${url}" target="_blank" rel="noopener noreferrer"
                    class="group flex items-center gap-2 px-3 ${padding} text-left text-muted-foreground hover:bg-[hsl(var(--menu-item-hover))] hover:text-primary rounded transition-all ${bebasClass} uppercase tracking-wide">
                    <img src="https://iblog.bz/assets/flags/${countryCode}.svg" 
                         alt="${country.name}" 
                         class="w-5 h-4 object-cover flex-shrink-0"
                         onerror="this.style.display='none'"
                         onload="this.style.display='block'">
                    <span class="truncate flex-1">${formatTextWithHighlights(country.name)}</span>
                </a>
            `;
        }
    });
    html += '</div>';
    return html;
}

function renderCountryStates() {
    const region = dataCache.menuData.regions.find(r => r.id === state.selectedRegion);
    if (!region) return '';
    
    const country = region.countries.find(c => c.id === state.selectedCountry);
    if (!country) return '';
    
    const bebasClass = state.useBebasFont ? 'font-bebas text-lg' : 'text-xs';
    const padding = state.useBebasFont ? 'py-2.5' : 'py-2.5';
    
    let html = '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-[0.3rem]">';
    
    if (country.states) {
        country.states.forEach(state => {
            const stateUrl = state.url || generateUrl(state.name);
            const stateCode = stateCodeMap[state.name.toLowerCase()];
            
            html += `
                <a href="${stateUrl}" target="_blank" rel="noopener noreferrer"
                    class="group flex items-center gap-2 px-3 ${padding} text-muted-foreground hover:bg-[hsl(var(--menu-item-hover))] hover:text-primary rounded transition-all ${bebasClass} uppercase tracking-wide">
                    <svg class="w-3.5 h-3.5 flex-shrink-0 opacity-30 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span class="truncate flex-1">${formatTextWithHighlights(state.name)}</span>
                    ${stateCode ? `<span class="text-muted-foreground/60 text-xs ml-auto flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">(${stateCode})</span>` : ''}
                </a>
            `;
        });
    }
    
    if (country.cities) {
        country.cities.forEach(city => {
            const cityUrl = city.url || generateUrl(city.name);
            
            html += `
                <a href="${cityUrl}" target="_blank" rel="noopener noreferrer"
                    class="group flex items-center gap-2 px-3 ${padding} text-muted-foreground hover:bg-[hsl(var(--menu-item-hover))] hover:text-primary rounded transition-all ${bebasClass} uppercase tracking-wide">
                    <svg class="w-3.5 h-3.5 flex-shrink-0 opacity-30 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span class="truncate flex-1">${formatTextWithHighlights(city.name)}</span>
                </a>
            `;
        });
    }
    
    html += '</div>';
    return html;
}

function renderLocalSearchResults() {
    // Simple search implementation
    const query = state.searchQuery.toLowerCase();
    let results = [];
    
    // Search featured channels
    dataCache.menuData.featuredChannels.forEach(channel => {
        if (channel.name.toLowerCase().includes(query)) {
            results.push({ type: 'featured', item: channel });
        }
    });
    
    // Search regions/countries/states
    dataCache.menuData.regions.forEach(region => {
        region.countries?.forEach(country => {
            if (country.name.toLowerCase().includes(query)) {
                results.push({ type: 'country', item: country, regionId: region.id });
            }
            country.states?.forEach(state => {
                if (state.name.toLowerCase().includes(query)) {
                    results.push({ type: 'state', item: state });
                }
            });
        });
    });
    
    if (results.length === 0) {
        return `<p class="py-8 text-center text-sm text-muted-foreground">No results found for "${state.searchQuery}"</p>`;
    }
    
    const bebasClass = state.useBebasFont ? 'font-bebas text-lg' : 'text-xs';
    const padding = state.useBebasFont ? 'py-2.5' : 'py-2.5';
    
    let html = '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-[0.3rem]">';
    results.forEach(result => {
        const url = result.item.url || generateUrl(result.item.name);
        html += `
            <a href="${url}" target="_blank" rel="noopener noreferrer"
                class="group flex items-center gap-2 px-3 ${padding} text-muted-foreground hover:bg-[hsl(var(--menu-item-hover))] hover:text-primary rounded transition-all ${bebasClass} uppercase tracking-wide">
                <svg class="w-3.5 h-3.5 flex-shrink-0 opacity-30 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                </svg>
                <span class="truncate flex-1">${formatTextWithHighlights(result.item.name)}</span>
            </a>
        `;
    });
    html += '</div>';
    return html;
}

function attachLocalMenuListeners() {
    const searchInput = document.getElementById('local-search-input');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            state.searchQuery = e.target.value;
            // Update only the content area, not the entire modal
            const contentArea = document.querySelector('.flex-1.overflow-y-auto');
            if (contentArea) {
                contentArea.innerHTML = state.searchQuery 
                    ? renderLocalSearchResults()
                    : (state.selectedRegion === 'featured' 
                        ? renderFeaturedChannels() 
                        : (state.selectedCountry 
                            ? renderCountryStates() 
                            : renderRegionCountries()));
            }
        });
    }
}

function selectRegion(regionId) {
    state.selectedRegion = regionId;
    state.selectedCountry = null;
    state.searchQuery = '';
    // Update only the content area, not the entire modal
    const contentArea = document.querySelector('.flex.flex-1.overflow-hidden > .flex-1.overflow-y-auto');
    if (contentArea) {
        contentArea.innerHTML = state.selectedRegion === 'featured' 
            ? renderFeaturedChannels() 
            : renderRegionCountries();
    }
    // Update sidebar to reflect active state
    const buttons = document.querySelectorAll('.w-48.md\\:w-60 button');
    buttons.forEach(btn => {
        const regionMatch = btn.getAttribute('onclick')?.match(/selectRegion\('([^']+)'\)/);
        if (regionMatch) {
            const btnRegion = regionMatch[1];
            if (btnRegion === regionId) {
                btn.classList.add('border-l-2', 'border-l-primary', 'bg-[hsl(var(--menu-item-active))]');
                btn.classList.remove('hover:bg-[hsl(var(--menu-item-hover))]');
            } else {
                btn.classList.remove('border-l-2', 'border-l-primary', 'bg-[hsl(var(--menu-item-active))]');
                btn.classList.add('hover:bg-[hsl(var(--menu-item-hover))]');
            }
        }
    });
}

function selectCountry(countryId) {
    state.selectedCountry = countryId;
    // Update only the content area
    const contentArea = document.querySelector('.flex.flex-1.overflow-hidden > .flex-1.overflow-y-auto');
    if (contentArea) {
        contentArea.innerHTML = renderCountryStates();
    }
}

function handleLocalBack() {
    if (state.selectedCountry) {
        state.selectedCountry = null;
        // Update only the content area
        const contentArea = document.querySelector('.flex.flex-1.overflow-hidden > .flex-1.overflow-y-auto');
        if (contentArea) {
            contentArea.innerHTML = renderRegionCountries();
        }
    }
}

function toggleBebasFont() {
    state.useBebasFont = !state.useBebasFont;
    if (state.currentMenu === 'local') showIONLocalMenu();
    else if (state.currentMenu === 'networks') showIONNetworksMenu();
    else if (state.currentMenu === 'initiatives') showIONInitiativesMenu();
    else if (state.currentMenu === 'mall') showIONMallMenu();
    else if (state.currentMenu === 'connections') showIONConnectionsMenu();
}

// ========================================
// ION NETWORKS MENU
// ========================================
function showIONNetworksMenu() {
    if (!dataCache.networksData) return;
    
    const bebasClass = state.useBebasFont ? 'font-bebas text-lg' : 'text-sm';
    const padding = state.useBebasFont ? 'py-2.5' : 'py-3';
    
    let html = `
        <div class="flex h-[520px] flex-col rounded-lg border border-border bg-card overflow-hidden">
            ${renderNetworksHeader()}
            <div class="flex-1 p-2 overflow-y-auto">
                ${renderNetworksContent()}
            </div>
        </div>
    `;
    
    createModal(html);
    attachNetworksListeners();
}

function renderNetworksHeader() {
    return `
        <div class="px-4 py-3 border-b border-border">
            <div class="flex items-center justify-between gap-4 mb-3">
                <h2 class="text-lg font-bold">
                    <span class="text-primary">ION</span> <span class="text-foreground">NETWORKS</span>
                </h2>
                <div class="flex-1 max-w-md relative hidden md:block">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" placeholder="SEARCH ION NETWORKS" id="networks-search-input" value="${state.searchQuery}"
                        class="w-full pl-10 pr-4 py-2 text-sm uppercase tracking-wide rounded-sm focus:outline-none focus:ring-1 focus:ring-ring bg-input text-foreground placeholder:text-muted-foreground">
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="toggleBebasFont()" class="h-8 w-8 flex items-center justify-center hover:bg-accent/50 rounded-md transition-colors">
                        <span class="${state.useBebasFont ? 'font-bebas' : ''}">Aa</span>
                    </button>
                    <button onclick="toggleTheme()" class="h-8 w-8 flex items-center justify-center hover:bg-accent/50 rounded-md transition-colors">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${state.theme === 'dark' ? 'M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z' : 'M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z'}"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    `;
}

function renderNetworksContent() {
    const bebasClass = state.useBebasFont ? 'font-bebas text-lg' : 'text-sm';
    const padding = state.useBebasFont ? 'py-2.5' : 'py-3';
    
    let networks = dataCache.networksData.networks;
    
    // Filter by search query
    if (state.searchQuery) {
        const query = state.searchQuery.toLowerCase();
        networks = networks.filter(n => n.title.toLowerCase().includes(query));
        
        if (networks.length === 0) {
            return `<p class="py-8 text-center text-sm text-muted-foreground">No results found for "${state.searchQuery}"</p>`;
        }
    }
    
    let html = '<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-[0px]">';
    networks.forEach(network => {
        html += `
            <a href="${network.url || '#'}" target="_blank" rel="noopener noreferrer"
                class="group flex items-center justify-between rounded-lg border border-border/50 bg-card px-3 ${padding} transition-all hover:border-primary/50 hover:bg-accent/50 ${bebasClass}">
                <span class="text-sm font-medium text-card-foreground group-hover:text-primary">${formatTextWithHighlights(network.title)}</span>
                <svg class="w-4 h-4 opacity-50 group-hover:opacity-100 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        `;
    });
    html += '</div>';
    return html;
}

function attachNetworksListeners() {
    const searchInput = document.getElementById('networks-search-input');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            state.searchQuery = e.target.value;
            // Update only the content area
            const contentArea = document.querySelector('.flex-1.overflow-y-auto');
            if (contentArea) {
                contentArea.innerHTML = renderNetworksContent();
            }
        });
    }
}

// ========================================
// ION INITIATIVES MENU (Simplified)
// ========================================
function showIONInitiativesMenu() {
    if (!dataCache.initiativesData) return;
    
    const bebasClass = state.useBebasFont ? 'font-bebas text-lg' : 'text-sm';
    const padding = state.useBebasFont ? 'py-2.5' : 'py-3';
    
    let html = `
        <div class="flex h-[520px] flex-col rounded-lg border border-border bg-card overflow-hidden">
            <div class="px-4 py-3 border-b border-border">
                <h2 class="text-lg font-bold"><span class="text-primary">ION</span>ITIATIVES</h2>
            </div>
            <div class="flex-1 p-2 overflow-y-auto">
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-[0px]">
    `;
    
    dataCache.initiativesData.initiatives.forEach(initiative => {
        html += `
            <a href="${initiative.url || '#'}" target="_blank" rel="noopener noreferrer"
                class="group flex items-center justify-between rounded-lg border border-border/50 bg-card px-3 ${padding} transition-all hover:border-primary/50 hover:bg-accent/50 ${bebasClass}">
                <span class="text-sm font-medium text-card-foreground group-hover:text-primary">${formatTextWithHighlights(initiative.title)}</span>
            </a>
        `;
    });
    
    html += `
                </div>
            </div>
        </div>
    `;
    
    createModal(html);
}

// ========================================
// ION MALL MENU WITH STORE CARDS
// ========================================
function showIONMallMenu() {
    if (!dataCache.shopsData || !dataCache.mallStores) return;
    
    const content = `
        <div class="flex h-[520px] flex-col rounded-lg border border-border bg-card overflow-hidden">
            ${renderMallHeader()}
            <div class="flex flex-1 overflow-hidden">
                ${renderMallSidebar()}
                ${renderMallContent()}
            </div>
        </div>
    `;
    
    createModal(content);
    attachMallListeners();
}

function renderMallHeader() {
    return `
        <div class="px-4 py-3 border-b border-border">
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-lg font-bold"><span class="text-primary">ION</span> MALL</h2>
                <input type="text" placeholder="Search the Mall of Champions" id="mall-search-input" value="${state.searchQuery}"
                    class="flex-1 max-w-md px-4 py-2 text-sm uppercase tracking-wide rounded-sm focus:outline-none focus:ring-1 focus:ring-ring bg-input text-foreground placeholder:text-muted-foreground">
                <button onclick="toggleTheme()" class="h-8 w-8 flex items-center justify-center hover:bg-accent/50 rounded-md">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${state.theme === 'dark' ? 'M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z' : 'M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z'}"/>
                    </svg>
                </button>
            </div>
        </div>
    `;
}

function renderMallSidebar() {
    const bebasClass = state.useBebasFont ? 'font-bebas text-lg' : 'text-sm';
    const padding = state.useBebasFont ? 'py-2.5' : 'py-3';
    
    let html = `<div class="w-48 md:w-56 border-r border-border overflow-y-auto">`;
    
    dataCache.shopsData.categories.forEach(category => {
        const parts = category.name.split('ION');
        html += `
            <button type="button" onclick="selectShopCategory('${category.id}')"
                class="flex w-full items-center justify-between px-4 ${padding} text-left border-b border-border transition-all ${bebasClass} uppercase tracking-wide ${state.selectedShopCategory === category.id ? 'border-l-2 border-l-primary bg-[hsl(var(--menu-item-active))]' : 'hover:bg-[hsl(var(--menu-item-hover))]'}">
                <span>${parts.length > 1 ? `${parts[0]}<span class="text-primary font-medium">ION</span>${parts[1]}` : category.name}</span>
                <svg class="w-4 h-4 text-muted-foreground flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </button>
        `;
    });
    
    html += `</div>`;
    return html;
}

function renderMallContent() {
    let inner = '';
    if (!state.selectedShopCategory) {
        inner = `
            <div class="flex h-full items-center justify-center">
                <div class="text-center">
                    <svg class="mx-auto h-16 w-16 text-muted-foreground/30 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                    </svg>
                    <p class="text-sm text-muted-foreground">Select a category to browse products</p>
                </div>
            </div>`;
    } else {
        const category = dataCache.shopsData.categories.find(c => c.id === state.selectedShopCategory);
        if (category) {
            inner = category.id === 'all-stores' ? renderStoresTabs() : renderCategoryItems(category);
        }
    }
    return `<div id="mall-content" class="flex-1 p-2 overflow-y-auto">${inner}</div>`;
}

function renderCategoryItems(category) {
    if (!category.items || category.items.length === 0) {
        return `
            <a href="https://mallofchampions.com/${category.url}" target="_blank" rel="noopener noreferrer"
                class="flex items-center justify-center rounded-lg border-2 border-dashed border-primary/30 bg-primary/5 p-8 transition-all hover:border-primary/50 hover:bg-primary/10">
                <div class="text-center">
                    <svg class="mx-auto h-12 w-12 text-primary mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                    </svg>
                    <p class="text-sm font-medium text-card-foreground">Visit Store</p>
                    <p class="text-xs text-muted-foreground mt-1">Browse all products</p>
                </div>
            </a>
        `;
    }
    
    let html = '<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-[0.35rem]">';
    category.items.forEach(item => {
        html += `
            <a href="https://mallofchampions.com/${item.url}" target="_blank" rel="noopener noreferrer"
                class="group flex flex-col items-center justify-center rounded-lg border border-border/50 bg-card p-4 transition-all hover:border-primary hover:bg-primary/5 hover:shadow-lg hover:scale-105">
                <div class="mb-2 flex h-16 w-16 items-center justify-center rounded-full bg-primary/10 group-hover:bg-primary/30 transition-all duration-200">
                    <svg width="32" height="32" class="text-primary group-hover:scale-110 transition-transform">
                        <use href="#ion-local-shop"/>
                    </svg>
                </div>
                <span class="text-xs text-center font-medium text-card-foreground group-hover:text-primary transition-colors leading-tight break-words whitespace-normal">
                    ${item.name}
                </span>
            </a>
        `;
    });
    html += '</div>';
    return html;
}

function renderStoresTabs() {
    // Group stores by tab
    const storesByTab = {};
    dataCache.mallStores.forEach(store => {
        if (!storesByTab[store.tab]) {
            storesByTab[store.tab] = [];
        }
        storesByTab[store.tab].push(store);
    });
    
    const tabs = ['View All', ...Object.keys(storesByTab).sort((a, b) => {
        if (a === 'Store') return -1;
        if (b === 'Store') return 1;
        return a.localeCompare(b);
    })];
    
    const activeTab = state.activeTab || tabs[0];
    
    let html = '<div class="space-y-4"><div class="flex gap-2 overflow-x-auto pb-2">';
    tabs.forEach(tab => {
        const count = tab === 'View All' ? dataCache.mallStores.length : storesByTab[tab]?.length || 0;
        html += `
            <button onclick="switchTab('${tab}')" 
                class="tab-trigger ${activeTab === tab ? 'active' : ''} px-3 py-2 text-xs font-medium rounded-md whitespace-nowrap bg-muted/50 hover:bg-muted transition-colors ${activeTab === tab ? 'bg-background text-foreground' : ''}">
                ${tab} (${count})
            </button>
        `;
    });
    html += '</div>';
    
    // Render stores
    const storesToShow = activeTab === 'View All' ? dataCache.mallStores : storesByTab[activeTab] || [];
    html += '<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">';
    storesToShow.slice(0, 30).forEach(store => {
        const fullUrl = `https://mallofchampions.com/collections${store.url}`;
        
        // Check if this is a school store - use local logos
        let imageUrl = '';
        if (store.tab === 'School' && store.s) {
            // Convert store name to filename format (e.g., "Auburn University" -> "Auburn_University.svg")
            const logoFilename = store.s.replace(/\s+/g, '_');
            // Try both .svg and .png extensions
            imageUrl = `assets/Logos/${logoFilename}.svg`;
        } else {
            // Use Mall of Champions CDN
            imageUrl = store.img.startsWith('http') ? store.img : `https://mallofchampions.com/cdn/shop/products${store.img}`;
        }
        
        html += `
            <a href="${fullUrl}" target="_blank" rel="noopener noreferrer"
                class="store-card group flex flex-col items-center justify-center rounded-lg border border-border/50 bg-card p-4 transition-all hover:border-primary hover:bg-primary/5 hover:shadow-lg hover:scale-105">
                <div class="store-card-img-wrapper mb-3 flex h-20 w-20 items-center justify-center rounded-lg bg-white/90 dark:bg-white/95 p-2 overflow-hidden border border-border/20 transition-all duration-200">
                    <img src="${imageUrl}" alt="${store.s} logo" 
                        class="store-card-img max-h-full max-w-full object-contain transition-transform duration-200"
                        onerror="if(this.src.endsWith('.svg')){this.src=this.src.replace('.svg','.png')}else{this.style.display='none';this.nextElementSibling.style.display='flex'}">
                    <svg class="hidden h-8 w-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                    </svg>
                </div>
                <span class="text-xs text-center font-medium text-card-foreground group-hover:text-primary transition-colors leading-tight break-words whitespace-normal">
                    ${store.s}
                </span>
            </a>
        `;
    });
    html += '</div></div>';
    
    return html;
}

function attachMallListeners() {
    const searchInput = document.getElementById('mall-search-input');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            state.searchQuery = e.target.value;
            // Update content without re-rendering entire modal
            const contentArea = document.getElementById('mall-content');
            if (contentArea && state.searchQuery) {
                // Filter stores based on search
                const query = state.searchQuery.toLowerCase();
                const filtered = dataCache.mallStores.filter(s => s.s.toLowerCase().includes(query));
                
                if (filtered.length === 0) {
                    contentArea.innerHTML = `<p class="py-8 text-center text-sm text-muted-foreground">No results found for "${state.searchQuery}"</p>`;
                } else {
                    // Render filtered results
                    let html = '<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">';
                    filtered.slice(0, 30).forEach(store => {
                        const fullUrl = `https://mallofchampions.com/collections${store.url}`;
                let imageUrl = '';
                if (store.tab === 'School' && store.s) {
                    const logoFilename = store.s.replace(/\s+/g, '_');
                    imageUrl = `assets/Logos/${logoFilename}.svg`;
                } else {
                    imageUrl = store.img.startsWith('http') ? store.img : `https://mallofchampions.com/cdn/shop/products${store.img}`;
                }
                        
                        html += `
                            <a href="${fullUrl}" target="_blank" rel="noopener noreferrer"
                                class="group flex flex-col items-center justify-center rounded-lg border border-border/50 bg-card p-4 transition-all hover:border-primary hover:bg-primary/5 hover:shadow-lg hover:scale-105">
                                <div class="mb-3 flex h-20 w-20 items-center justify-center rounded-lg bg-white/90 dark:bg-white/95 p-2 overflow-hidden border border-border/20">
                                <img src="${imageUrl}" alt="${store.s} logo" 
                                    class="max-h-full max-w-full object-contain"
                                    onerror="if(this.src.endsWith('.svg')){this.src=this.src.replace('.svg','.png')}else{this.style.display='none';this.nextElementSibling.style.display='flex'}">
                                <svg class="hidden h-8 w-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                </svg>
                                </div>
                                <span class="text-xs text-center font-medium text-card-foreground group-hover:text-primary transition-colors leading-tight break-words whitespace-normal">
                                    ${store.s}
                                </span>
                            </a>
                        `;
                    });
                    html += '</div>';
                    contentArea.innerHTML = html;
                }
            }
        });
    }
}

function selectShopCategory(categoryId) {
    state.selectedShopCategory = categoryId;
    // Update only the content area
    const wrapper = document.getElementById('mall-content');
    if (wrapper) {
        wrapper.outerHTML = renderMallContent();
    }
    // Update sidebar to reflect active state
    const buttons = document.querySelectorAll('.w-48.md\\:w-56 button');
    buttons.forEach(btn => {
        const catMatch = btn.getAttribute('onclick')?.match(/selectShopCategory\('([^']+)'\)/);
        if (catMatch) {
            const btnCat = catMatch[1];
            if (btnCat === categoryId) {
                btn.classList.add('border-l-2', 'border-l-primary', 'bg-[hsl(var(--menu-item-active))]');
                btn.classList.remove('hover:bg-[hsl(var(--menu-item-hover))]');
            } else {
                btn.classList.remove('border-l-2', 'border-l-primary', 'bg-[hsl(var(--menu-item-active))]');
                btn.classList.add('hover:bg-[hsl(var(--menu-item-hover))]');
            }
        }
    });
}

function switchTab(tab) {
    state.activeTab = tab;
    const wrapper = document.getElementById('mall-content');
    if (wrapper && state.selectedShopCategory === 'all-stores') {
        wrapper.innerHTML = renderStoresTabs();
    }
}

// ========================================
// ION CONNECTIONS MENU
// ========================================
function showIONConnectionsMenu() {
    if (!dataCache.connectionsData) return;
    
    const bebasClass = state.useBebasFont ? 'font-bebas text-lg' : 'text-sm';
    const padding = state.useBebasFont ? 'py-2.5' : 'py-3';
    
    let html = `
        <div class="flex h-[520px] flex-col rounded-lg border border-border bg-card overflow-hidden">
            <div class="px-4 py-3 border-b border-border">
                <h2 class="text-lg font-bold">CONNECT.<span class="text-primary">ION</span>S</h2>
            </div>
            <div class="flex-1 p-2 overflow-y-auto">
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-[0px]">
    `;
    
    dataCache.connectionsData.connections.forEach(connection => {
        html += `
            <a href="${connection.url || '#'}" target="_blank" rel="noopener noreferrer"
                class="group flex items-center justify-between rounded-lg border border-border/50 bg-card px-3 ${padding} transition-all hover:border-primary/50 hover:bg-accent/50 ${bebasClass}">
                <span class="text-sm font-medium text-card-foreground group-hover:text-primary">${formatTextWithHighlights(connection.title)}</span>
            </a>
        `;
    });
    
    html += `
                </div>
            </div>
        </div>
    `;
    
    createModal(html);
}

// ========================================
// SEARCH DIALOG
// ========================================
function openSearchDialog() {
    const content = `
        <div class="p-6">
            <h2 class="text-2xl font-bebas uppercase tracking-wider mb-2">
                Search <span class="text-primary">ION</span>
            </h2>
            <p class="text-sm text-muted-foreground mb-4">Search across all ION Local Channels</p>
            <div class="flex items-center gap-2">
                <svg class="h-5 w-5 text-muted-foreground flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input type="text" placeholder="Type your search here..." class="flex-1 px-4 py-2 bg-input text-foreground border border-border rounded-md focus:outline-none focus:ring-2 focus:ring-ring" id="search-input" autofocus>
                <button class="px-4 py-2 bg-primary text-primary-foreground font-bebas uppercase tracking-wider rounded-md hover:bg-primary/90 transition-colors" onclick="performSearch()">
                    Search
                </button>
            </div>
        </div>
    `;
    createModal(content, '600px');
    
    setTimeout(() => {
        document.getElementById('search-input')?.focus();
    }, 100);
    
    document.getElementById('search-input')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            performSearch();
        }
    });
}

function performSearch() {
    const input = document.getElementById('search-input');
    const query = input?.value || '';
    console.log('Search for:', query);
}

// ========================================
// MOBILE MENU
// ========================================
function openMobileMenu() {
    state.mobileMenuOpen = true;
    const sheet = document.getElementById('mobile-sheet');
    const content = document.getElementById('mobile-sheet-content');
    
    content.innerHTML = `
        <div class="flex flex-col gap-4 py-4">
            <div class="flex items-center gap-2 px-3 py-2 border rounded-md bg-background/50 mx-4">
                <svg class="h-4 w-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input type="text" placeholder="SEARCH ION" class="flex-1 border-0 bg-transparent p-0 h-auto font-bebas uppercase placeholder:text-muted-foreground focus:outline-none" readonly onclick="closeMobileMenu(); openSearchDialog();">
            </div>
            
            <div class="grid grid-cols-2 gap-2 px-4">
                <button class="px-3 py-2 bg-primary text-primary-foreground font-bebas uppercase tracking-wider rounded-md hover:bg-primary/90 transition-colors text-sm">
                    <svg class="inline h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                    </svg>
                    Upload
                </button>
                <button class="px-3 py-2 border border-primary text-primary hover:bg-primary hover:text-white font-bebas uppercase tracking-wider rounded-md transition-colors text-sm">
                    Sign<span>ION</span>
                </button>
            </div>
            
            <div class="border-t border-border"></div>
            
            <div class="flex flex-col gap-1 px-2">
                <button onclick="closeMobileMenu(); openMenu('local');" class="px-3 py-2 text-left font-bebas text-lg uppercase hover:bg-accent/50 rounded-md transition-colors">
                    <span class="text-primary">ION</span> Local
                </button>
                <button onclick="closeMobileMenu(); openMenu('networks');" class="px-3 py-2 text-left font-bebas text-lg uppercase hover:bg-accent/50 rounded-md transition-colors">
                    <span class="text-primary">ION</span> Networks
                </button>
                <button onclick="closeMobileMenu(); openMenu('initiatives');" class="px-3 py-2 text-left font-bebas text-lg uppercase hover:bg-accent/50 rounded-md transition-colors">
                    <span class="text-primary">ION</span>ITIATIVES
                </button>
                <button onclick="closeMobileMenu(); openMenu('mall');" class="px-3 py-2 text-left font-bebas text-lg uppercase hover:bg-accent/50 rounded-md transition-colors">
                    <span class="text-primary">ION</span> Mall
                </button>
                <button onclick="closeMobileMenu(); openMenu('connections');" class="px-3 py-2 text-left font-bebas text-lg uppercase hover:bg-accent/50 rounded-md transition-colors">
                    CONNECT<span class="text-primary">.ION</span>S
                </button>
                <button class="px-3 py-2 text-left font-bebas text-lg uppercase hover:bg-accent/50 rounded-md transition-colors">
                    PressPass<span class="text-primary">.ION</span>
                </button>
            </div>
            
            <div class="border-t border-border"></div>
            
            <button onclick="toggleTheme();" class="px-3 py-2 mx-2 text-left text-gray-400 hover:text-white hover:bg-accent/50 rounded-md transition-colors">
                <svg class="inline h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                Toggle theme
            </button>
        </div>
    `;
    
    sheet.classList.remove('hidden');
    setTimeout(() => {
        content.classList.add('slide-in-right');
    }, 10);
}

function closeMobileMenu() {
    const sheet = document.getElementById('mobile-sheet');
    sheet.classList.add('hidden');
    state.mobileMenuOpen = false;
}

// ========================================
// SVG SPRITES LOADER
// ========================================
function loadSVGSprites() {
    fetch('svg-sprites.html')
        .then(response => response.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const svgSprite = doc.querySelector('svg[style*="display:none"]');
            if (svgSprite) {
                document.getElementById('svg-sprites').innerHTML = svgSprite.innerHTML;
            }
        })
        .catch(error => console.error('Error loading SVG sprites:', error));
}

// ========================================
// UTILITY FUNCTIONS
// ========================================
function isMobile() {
    return window.innerWidth < 768;
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

// Expose functions to global scope
window.closeMobileMenu = closeMobileMenu;
window.openMenu = openMenu;
window.toggleTheme = toggleTheme;
window.performSearch = performSearch;
window.closeAllModals = closeAllModals;
window.selectRegion = selectRegion;
window.selectCountry = selectCountry;
window.handleLocalBack = handleLocalBack;
window.toggleBebasFont = toggleBebasFont;
window.selectShopCategory = selectShopCategory;
window.switchTab = switchTab;