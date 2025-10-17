// ION Local Menu - Complete Business Logic from React
// Maintains 100% of all business rules and interactions

let useBebasFont = false;
let currentRegion = 'featured';
let currentCountry = null;
let searchQuery = '';

// Country code mapping (for flag URLs and search)
const countryCodeMap = {
    "usa": "us", "canada": "ca", "mexico": "mx", "belize": "bz",
    "costa-rica": "cr", "el-salvador": "sv", "guatemala": "gt",
    "honduras": "hn", "nicaragua": "ni", "panama": "pa",
    "argentina": "ar", "bolivia": "bo", "brazil": "br", "chile": "cl",
    "colombia": "co", "ecuador": "ec", "guyana": "gy", "paraguay": "py",
    "peru": "pe", "suriname": "sr", "uruguay": "uy", "venezuela": "ve"
};

// State code mapping (for display and search)
const stateCodeMap = {
    "alabama": "AL", "alaska": "AK", "arizona": "AZ", "arkansas": "AR",
    "california": "CA", "colorado": "CO", "connecticut": "CT", "delaware": "DE",
    "florida": "FL", "georgia": "GA", "hawaii": "HI", "idaho": "ID",
    "illinois": "IL", "indiana": "IN", "iowa": "IA", "kansas": "KS",
    "kentucky": "KY", "louisiana": "LA", "maine": "ME", "maryland": "MD",
    "massachusetts": "MA", "michigan": "MI", "minnesota": "MN", "mississippi": "MS",
    "missouri": "MO", "montana": "MT", "nebraska": "NE", "nevada": "NV",
    "new hampshire": "NH", "new jersey": "NJ", "new york": "NY", "north carolina": "NC",
    "north dakota": "ND", "ohio": "OH", "oklahoma": "OK", "oregon": "OR",
    "pennsylvania": "PA", "rhode island": "RI", "south carolina": "SC", "south dakota": "SD",
    "tennessee": "TN", "texas": "TX", "utah": "UT", "vermont": "VT",
    "virginia": "VA", "washington": "WA", "washington dc": "DC", "west virginia": "WV",
    "wisconsin": "WI", "wyoming": "WY"
};

/**
 * BUSINESS RULE 1: Region Click Handler
 * - Sets selected region
 * - Clears selected country
 * - CLEARS SEARCH QUERY (critical!)
 * - Updates UI to show region content
 */
function showRegion(regionId) {
    currentRegion = regionId;
    currentCountry = null;
    
    // CRITICAL: Clear search query when clicking sidebar
    searchQuery = '';
    const searchInput = document.getElementById('ion-local-search');
    if (searchInput) {
        searchInput.value = '';
    }
    
    // Update sidebar active states (only show when NO search)
    document.querySelectorAll('.ion-sidebar-item').forEach(item => {
        item.classList.remove('active');
        if (item.dataset.region === regionId) {
            item.classList.add('active');
        }
    });
    
    // Hide ALL region contents
    document.querySelectorAll('.ion-region-content').forEach(content => {
        content.classList.remove('active');
    });
    
    // Show selected region content
    const regionContent = document.getElementById(`region-${regionId}`);
    if (regionContent) {
        regionContent.classList.add('active');
        
        // Show countries grid (if exists)
        const countriesDiv = document.getElementById(`region-${regionId}-countries`);
        if (countriesDiv) {
            countriesDiv.style.display = 'grid';
        }
        
        // Hide all country detail views
        document.querySelectorAll(`[id^="country-${regionId}-"]`).forEach(countryContent => {
            countryContent.classList.remove('active');
        });
    }
    
    // Hide search results
    hideSearchResults();
}

/**
 * BUSINESS RULE 2: Country Click Handler
 * - Shows states/cities for that country
 * - Hides countries grid
 */
function showCountry(regionId, countryId) {
    currentCountry = countryId;
    
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
}

/**
 * BUSINESS RULE 3: Search Handler
 * - Searches through ALL data (regions, countries, states, cities, featured)
 * - Matches by name AND codes (country codes, state codes)
 * - Shows search results ONLY when query exists
 * - Hides normal views when searching
 */
function searchIONLocal(query) {
    searchQuery = query.trim();
    
    if (searchQuery.length === 0) {
        // No search - show current view
        hideSearchResults();
        if (currentCountry) {
            showCountry(currentRegion, currentCountry);
        } else {
            showRegion(currentRegion);
        }
        return;
    }
    
    // Search is active - hide normal views
    hideAllViews();
    
    const queryLower = searchQuery.toLowerCase();
    const results = [];
    
    // Get menu data from the page
    const menuData = getMenuDataFromDOM();
    
    // Search featured channels
    menuData.featured.forEach(channel => {
        if (channel.name.toLowerCase().includes(queryLower)) {
            results.push({
                type: 'featured',
                name: channel.name,
                url: channel.url
            });
        }
    });
    
    // Search regions, countries, states, cities
    menuData.regions.forEach(region => {
        if (region.name.toLowerCase().includes(queryLower)) {
            results.push({
                type: 'region',
                name: region.name,
                id: region.id
            });
        }
        
        region.countries.forEach(country => {
            const countryCode = countryCodeMap[country.id] || country.id;
            const matchesName = country.name.toLowerCase().includes(queryLower);
            const matchesCode = countryCode.toLowerCase().includes(queryLower);
            
            if (matchesName || matchesCode) {
                results.push({
                    type: 'country',
                    name: country.name,
                    id: country.id,
                    regionId: region.id,
                    countryCode: countryCode.toUpperCase(),
                    hasSubItems: country.hasSubItems
                });
            }
            
            // Search states
            country.states.forEach(state => {
                const stateCode = stateCodeMap[state.name.toLowerCase()] || '';
                const matchesStateName = state.name.toLowerCase().includes(queryLower);
                const matchesStateCode = stateCode.toLowerCase().includes(queryLower);
                
                if (matchesStateName || matchesStateCode) {
                    results.push({
                        type: 'state',
                        name: state.name,
                        url: state.url,
                        stateCode: stateCode,
                        countryId: country.id,
                        regionId: region.id
                    });
                }
            });
            
            // Search cities
            country.cities.forEach(city => {
                if (city.name.toLowerCase().includes(queryLower)) {
                    results.push({
                        type: 'city',
                        name: city.name,
                        url: city.url,
                        countryId: country.id,
                        regionId: region.id
                    });
                }
            });
        });
    });
    
    // Display search results
    displaySearchResults(results);
}

/**
 * Display search results with proper formatting
 */
function displaySearchResults(results) {
    // Create or get search results container
    let resultsContainer = document.getElementById('ion-search-results');
    if (!resultsContainer) {
        resultsContainer = document.createElement('div');
        resultsContainer.id = 'ion-search-results';
        resultsContainer.className = 'ion-items-grid';
        const menuContent = document.querySelector('.ion-menu-content');
        if (menuContent) {
            menuContent.appendChild(resultsContainer);
        }
    }
    
    resultsContainer.innerHTML = '';
    resultsContainer.style.display = 'grid';
    
    if (results.length === 0) {
        resultsContainer.innerHTML = '<div style="grid-column: 1/-1; padding: 2rem; text-align: center; color: hsl(var(--muted-foreground));">No results found</div>';
        return;
    }
    
    results.forEach(result => {
        if (result.type === 'country' && result.hasSubItems) {
            // Country with sub-items - button that navigates
            const button = document.createElement('button');
            button.className = 'ion-item-button';
            button.onclick = () => {
                // BUSINESS RULE: Clicking country in search navigates AND clears search
                showRegion(result.regionId);
                setTimeout(() => showCountry(result.regionId, result.id), 50);
            };
            button.innerHTML = `
                <img src="https://iblog.bz/assets/flags/${result.countryCode.toLowerCase()}.svg" 
                     alt="${result.name}" class="country-flag-img" />
                <span class="item-text"><span class="text-primary">ION</span> ${result.name}</span>
                <span class="country-code">(${result.countryCode})</span>
                <svg class="chevron-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            `;
            resultsContainer.appendChild(button);
        } else {
            // Link item (state, city, featured, or country without sub-items)
            const link = document.createElement('a');
            link.href = result.url || '#';
            link.className = 'ion-item-link';
            link.target = '_blank';
            
            let iconSvg = `<svg class="item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>`;
            
            let codeSpan = '';
            if (result.stateCode) {
                codeSpan = `<span class="state-code">(${result.stateCode})</span>`;
            } else if (result.countryCode) {
                codeSpan = `<span class="country-code">(${result.countryCode})</span>`;
                iconSvg = `<img src="https://iblog.bz/assets/flags/${result.countryCode.toLowerCase()}.svg" 
                               alt="${result.name}" class="country-flag-img" />`;
            }
            
            link.innerHTML = `
                ${iconSvg}
                <span class="item-text"><span class="text-primary">ION</span> ${result.name}</span>
                ${codeSpan}
                <svg class="external-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                </svg>
            `;
            
            // Prevent default and open in new tab
            link.onclick = (e) => {
                if (result.url && result.url !== '#') {
                    e.preventDefault();
                    window.open(result.url, '_blank');
                }
            };
            
            resultsContainer.appendChild(link);
        }
    });
}

/**
 * Hide search results and show normal view
 */
function hideSearchResults() {
    const resultsContainer = document.getElementById('ion-search-results');
    if (resultsContainer) {
        resultsContainer.style.display = 'none';
    }
}

/**
 * Hide all views (for when search is active)
 */
function hideAllViews() {
    // Hide all region contents
    document.querySelectorAll('.ion-region-content').forEach(content => {
        content.style.display = 'none';
    });
}

/**
 * Extract menu data from DOM (since it's server-rendered)
 */
function getMenuDataFromDOM() {
    const data = {
        featured: [],
        regions: []
    };
    
    // Extract featured channels
    document.querySelectorAll('#region-featured .ion-item-link').forEach(link => {
        data.featured.push({
            name: link.querySelector('.item-text').textContent.replace('ION', '').trim(),
            url: link.href
        });
    });
    
    // Extract regions and their data
    document.querySelectorAll('[id^="region-"]:not(#region-featured)').forEach(regionDiv => {
        const regionId = regionDiv.id.replace('region-', '');
        const regionName = document.querySelector(`[data-region="${regionId}"] .text-foreground`)?.textContent || '';
        
        const region = {
            id: regionId,
            name: regionName,
            countries: []
        };
        
        // Extract countries
        const countriesGrid = document.getElementById(`region-${regionId}-countries`);
        if (countriesGrid) {
            countriesGrid.querySelectorAll('.ion-item-button, .ion-item-link').forEach(item => {
                const countryName = item.querySelector('.item-text').textContent.replace('ION', '').trim();
                const countryId = item.onclick ? item.onclick.toString().match(/'([^']+)'/)?.[1] : null;
                const hasSubItems = item.classList.contains('ion-item-button');
                
                const country = {
                    name: countryName,
                    id: countryId || countryName.toLowerCase().replace(/\s+/g, '-'),
                    hasSubItems: hasSubItems,
                    states: [],
                    cities: []
                };
                
                // Extract states/cities if country has sub-items
                if (hasSubItems && countryId) {
                    const countryContent = document.getElementById(`country-${regionId}-${countryId}`);
                    if (countryContent) {
                        countryContent.querySelectorAll('.ion-item-link').forEach(link => {
                            const itemName = link.querySelector('.item-text').textContent.replace('ION', '').trim();
                            const itemUrl = link.href;
                            
                            if (link.querySelector('.state-code')) {
                                country.states.push({ name: itemName, url: itemUrl });
                            } else {
                                country.cities.push({ name: itemName, url: itemUrl });
                            }
                        });
                    }
                }
                
                region.countries.push(country);
            });
        }
        
        data.regions.push(region);
    });
    
    return data;
}

/**
 * Font toggle (Bebas Neue)
 */
function toggleFont() {
    useBebasFont = !useBebasFont;
    if (useBebasFont) {
        document.body.classList.add('bebas-font');
    } else {
        document.body.classList.remove('bebas-font');
    }
}

// Initialize on load
document.addEventListener('DOMContentLoaded', function() {
    // Set up search input listener
    const searchInput = document.getElementById('ion-local-search');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            searchIONLocal(e.target.value);
        });
        
        // Auto-focus search input
        searchInput.focus();
    }
    
    // Initialize with featured channels
    showRegion('featured');
});
