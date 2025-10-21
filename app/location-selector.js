/**
 * ION Location Selector
 * Handles location search and selection for user profiles
 * Based on channel-selector.js but simplified for single location selection
 */

class IONLocationSelector {
    constructor(inputId, resultsId, hiddenFieldId) {
        this.inputId = inputId;
        this.resultsId = resultsId;
        this.hiddenFieldId = hiddenFieldId;
        this.selectedLocation = null;
        this.searchTimeout = null;
        this.init();
    }

    init() {
        const searchInput = document.getElementById(this.inputId);
        const resultsContainer = document.getElementById(this.resultsId);

        if (!searchInput || !resultsContainer) {
            console.warn('Location selector elements not found:', this.inputId, this.resultsId);
            return;
        }

        // Search input handler with debounce
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.trim();
            
            clearTimeout(this.searchTimeout);
            
            if (query.length < 2) {
                resultsContainer.classList.remove('show');
                return;
            }

            this.searchTimeout = setTimeout(() => {
                this.searchLocations(query);
            }, 300);
        });

        // Close results when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest(`#${this.inputId}`) && !e.target.closest(`#${this.resultsId}`)) {
                resultsContainer.classList.remove('show');
            }
        });

        // Handle Enter key to select first result
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const firstResult = resultsContainer.querySelector('.location-search-result-item');
                if (firstResult) {
                    firstResult.click();
                }
            }
        });

        // Handle focus - show results if there's a value
        searchInput.addEventListener('focus', (e) => {
            const query = e.target.value.trim();
            if (query.length >= 2) {
                this.searchLocations(query);
            }
        });

        console.log('✅ ION Location Selector initialized for', this.inputId);
    }

    async searchLocations(query) {
        const resultsContainer = document.getElementById(this.resultsId);
        
        try {
            const response = await fetch(`/app/location-search-api.php?q=${encodeURIComponent(query)}&limit=20`);
            const data = await response.json();

            if (!data.success) {
                throw new Error(data.error || 'Search failed');
            }

            this.displayResults(data.locations);
            
        } catch (error) {
            console.error('Location search error:', error);
            resultsContainer.innerHTML = '<div class="location-search-empty">❌ Search failed. Please try again.</div>';
            resultsContainer.classList.add('show');
        }
    }

    displayResults(locations) {
        const resultsContainer = document.getElementById(this.resultsId);
        
        if (!locations || locations.length === 0) {
            resultsContainer.innerHTML = '<div class="location-search-empty">No locations found</div>';
            resultsContainer.classList.add('show');
            return;
        }

        resultsContainer.innerHTML = locations.map(location => `
            <div class="location-search-result-item" data-location='${JSON.stringify(location)}'>
                <div class="location-result-name">${this.escapeHtml(location.display)}</div>
                ${location.country_name && location.country !== 'US' ? 
                    `<div class="location-result-details">${this.escapeHtml(location.country_name)}</div>` : 
                    ''}
            </div>
        `).join('');

        // Add click handlers
        resultsContainer.querySelectorAll('.location-search-result-item').forEach(item => {
            item.addEventListener('click', () => {
                const location = JSON.parse(item.dataset.location);
                this.selectLocation(location);
                resultsContainer.classList.remove('show');
            });
        });

        resultsContainer.classList.add('show');
    }

    selectLocation(location) {
        this.selectedLocation = location;
        
        // Update the input field with the display value
        const searchInput = document.getElementById(this.inputId);
        if (searchInput) {
            searchInput.value = location.display;
        }

        // Update hidden field if it exists
        const hiddenField = document.getElementById(this.hiddenFieldId);
        if (hiddenField) {
            hiddenField.value = location.display;
        }

        console.log('📍 Location selected:', location.display);
    }

    setLocation(locationString) {
        // Set location from a string (for edit mode)
        const searchInput = document.getElementById(this.inputId);
        if (searchInput && locationString) {
            searchInput.value = locationString;
        }

        const hiddenField = document.getElementById(this.hiddenFieldId);
        if (hiddenField && locationString) {
            hiddenField.value = locationString;
        }

        this.selectedLocation = {
            display: locationString
        };
    }

    getSelectedLocation() {
        // Return the display string
        const hiddenField = document.getElementById(this.hiddenFieldId);
        return hiddenField ? hiddenField.value : (this.selectedLocation?.display || '');
    }

    clear() {
        this.selectedLocation = null;
        const searchInput = document.getElementById(this.inputId);
        if (searchInput) {
            searchInput.value = '';
        }
        const hiddenField = document.getElementById(this.hiddenFieldId);
        if (hiddenField) {
            hiddenField.value = '';
        }
    }

    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}
