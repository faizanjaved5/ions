/**
 * ION Pricing Card JavaScript
 * Handles elegant pricing display overlays for city directory cards
 */

class CityPricingOverlay {
  constructor(cardElement, options = {}) {
    this.card = cardElement;
    this.isHovered = false;
    this.isLoading = false;
    this.pricingData = null;
    this.options = {
      animationDuration: 250,
      hoverDelay: 100, // Reduced from 200ms to 100ms
      cacheTimeout: 300000, // 5 minutes
      ...options
    };
    
    this.init();
  }

  init() {
    // Ensure card has proper positioning
    if (getComputedStyle(this.card).position === 'static') {
      this.card.style.position = 'relative';
    }
    
    this.createOverlay();
    this.attachEventListeners();
    
    // Pre-render the overlay for instant display
    this.prepareOverlayForPerformance();
  }

  createOverlay() {
    // Create overlay container
    this.overlay = document.createElement('div');
    this.overlay.className = 'city-pricing-overlay';
    
    // Create tier header
    this.tierHeader = document.createElement('div');
    this.tierHeader.className = 'city-tier-header';
    this.tierHeader.textContent = 'Loading...';
    
    // Create close button
    this.closeButton = document.createElement('button');
    this.closeButton.className = 'city-pricing-close';
    this.closeButton.innerHTML = '×';
    this.closeButton.setAttribute('aria-label', 'Close pricing');
    this.closeButton.addEventListener('click', (e) => {
      e.stopPropagation();
      this.hideOverlay();
    });
    
    // Add close button to header
    this.tierHeader.appendChild(this.closeButton);
    
    // Create pricing grid
    this.pricingGrid = document.createElement('div');
    this.pricingGrid.className = 'city-pricing-grid';
    
    this.overlay.appendChild(this.tierHeader);
    this.overlay.appendChild(this.pricingGrid);
    this.card.appendChild(this.overlay);
  }

  attachEventListeners() {
    this.card.addEventListener('mouseenter', this.handleMouseEnter.bind(this));
    this.card.addEventListener('mouseleave', this.handleMouseLeave.bind(this));
    
    // Touch support for mobile
    this.card.addEventListener('touchstart', this.handleTouchStart.bind(this));
    this.card.addEventListener('touchend', this.handleTouchEnd.bind(this));
  }

  prepareOverlayForPerformance() {
    // Force browser to calculate styles and prepare for animation
    this.overlay.style.willChange = 'transform, opacity';
    
    // Trigger a layout to pre-calculate positioning
    this.overlay.offsetHeight;
    
    // Pre-create the loading state structure for instant display
    this.createLoadingStructure();
  }

  createLoadingStructure() {
    // Pre-build loading HTML to avoid DOM manipulation during hover
    this.loadingHTML = `
      <div class="city-pricing-loading">
        <div class="spinner"></div>
        <div>Getting latest prices...</div>
      </div>
    `;
    
    // Pre-build error HTML template
    this.errorHTMLTemplate = (message) => `
      <div class="city-pricing-error">
        <div>${message}</div>
        <div style="font-size: 0.75rem; margin-top: 0.5rem; opacity: 0.8;">
          Please try again later
        </div>
      </div>
    `;
  }

  handleMouseEnter() {
    if (this.hoverTimeout) {
      clearTimeout(this.hoverTimeout);
    }
    
    this.hoverTimeout = setTimeout(() => {
      this.showOverlay();
    }, this.options.hoverDelay);
  }

  handleMouseLeave() {
    if (this.hoverTimeout) {
      clearTimeout(this.hoverTimeout);
    }
    
    this.hideOverlay();
  }

  handleTouchStart(e) {
    e.preventDefault();
    this.showOverlay();
  }

  handleTouchEnd() {
    // Hide overlay after a delay on mobile
    setTimeout(() => {
      this.hideOverlay();
    }, 3000);
  }

  async showOverlay() {
    if (this.isHovered) return;
    
    this.isHovered = true;
    this.overlay.classList.remove('animate-out');
    this.overlay.classList.add('animate-in');
    
    // Load pricing data if not already loaded
    if (!this.pricingData && !this.isLoading) {
      await this.loadPricingData();
    }
    
    // Trigger custom event
    this.card.dispatchEvent(new CustomEvent('cityPricingShow', {
      detail: { 
        slug: this.getSlug(),
        pricing: this.pricingData 
      }
    }));
  }

  hideOverlay() {
    if (!this.isHovered) return;
    
    this.isHovered = false;
    this.overlay.classList.remove('animate-in');
    this.overlay.classList.add('animate-out');
    
    // Trigger custom event
    this.card.dispatchEvent(new CustomEvent('cityPricingHide', {
      detail: { 
        slug: this.getSlug() 
      }
    }));
  }

  getSlug() {
    // Try multiple ways to get the city slug
    return this.card.dataset.slug || 
           this.card.dataset.citySlug ||
           this.card.id ||
           this.extractSlugFromCard();
  }

  extractSlugFromCard() {
    // Extract slug from card content if not in data attributes
    const linkElement = this.card.querySelector('a[href*="/city/"]');
    if (linkElement) {
      const href = linkElement.getAttribute('href');
      const match = href.match(/\/city\/([^\/\?]+)/);
      return match ? match[1] : null;
    }
    
    // Try to extract from other patterns
    const cityName = this.card.querySelector('h3, h2, .city-name');
    if (cityName) {
      return this.slugify(cityName.textContent.trim());
    }
    
    return null;
  }

  slugify(text) {
    return text
      .toLowerCase()
      .replace(/[^\w\s-]/g, '')
      .replace(/[\s_-]+/g, '-')
      .replace(/^-+|-+$/g, '');
  }

  async loadPricingData() {
    const slug = this.getSlug();
    if (!slug) {
      this.showError('City not found');
      return;
    }

    // Check cache first
    const cacheKey = `city_pricing_${slug}`;
    const cached = CityPricingCache.get(cacheKey);
    if (cached) {
      this.pricingData = cached;
      this.displayPricing(cached);
      return;
    }

    this.isLoading = true;
    this.showLoading();

    try {
      const response = await fetch(`/api/get-pricing.php?action=get_pricing&slug=${encodeURIComponent(slug)}`);
      
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const data = await response.json();
      
      if (data.success && data.pricing) {
        // Correct format: data.pricing
        this.pricingData = data.pricing;
        CityPricingCache.set(cacheKey, data.pricing);
        this.displayPricing(data.pricing);
      } else if (data.success && data.location && data.location.pricing) {
        // Legacy format: data.location.pricing
        this.pricingData = data.location.pricing;
        CityPricingCache.set(cacheKey, data.location.pricing);
        this.displayPricing(data.location.pricing);
      } else {
        throw new Error(data.message || data.error || 'No pricing data available');
      }
    } catch (error) {
      console.error('Error loading pricing for', slug, error);
      this.showError('Pricing unavailable');
    } finally {
      this.isLoading = false;
    }
  }

  showLoading() {
    this.overlay.classList.add('loading');
    this.tierHeader.textContent = 'Loading Pricing...';
    this.pricingGrid.innerHTML = this.loadingHTML;
  }

  showError(message) {
    this.overlay.classList.remove('loading');
    this.overlay.classList.add('error');
    
    // Remove any existing tier classes and add error styling
    this.overlay.classList.remove('tier-1', 'tier-2', 'tier-3', 'tier-4', 'tier-5', 'bundle', 'other', 'custom');
    
    this.tierHeader.textContent = 'Pricing Error';
    this.pricingGrid.innerHTML = this.errorHTMLTemplate(message);
  }

  displayPricing(pricing) {
    this.overlay.classList.remove('loading', 'error');
    
    // Remove any existing tier classes
    this.overlay.classList.remove('tier-1', 'tier-2', 'tier-3', 'tier-4', 'tier-5', 'bundle', 'other', 'custom');
    
    // Add appropriate tier class based on pricing data
    const tierClass = this.getTierClass(pricing);
    this.overlay.classList.add(tierClass);
    
    // Update tier header
    this.tierHeader.textContent = pricing.label || 'Pricing';
    
    // Format pricing data
    const periods = [
      { 
        key: 'monthly', 
        label: 'Monthly', 
        value: pricing.monthly,
        class: 'monthly'
      },
      { 
        key: 'quarterly', 
        label: 'Quarterly', 
        value: pricing.quarterly,
        class: 'quarterly'
      },
      { 
        key: 'semi_annual', 
        label: 'Semi-Annual', 
        value: pricing.semi_annual,
        class: 'semi-annual'
      },
      { 
        key: 'annual', 
        label: 'Annual', 
        value: pricing.annual,
        class: 'annual'
      }
    ];

    // Build pricing grid HTML
    const gridHTML = periods.map(period => {
      const formattedPrice = this.formatPrice(period.value, pricing.currency);
      return `
        <div class="city-pricing-item ${period.class}">
          <div class="city-pricing-label">${period.label}</div>
          <div class="city-pricing-value">${formattedPrice}</div>
        </div>
      `;
    }).join('');

    this.pricingGrid.innerHTML = gridHTML;
  }

  getTierClass(pricing) {
    // Determine tier class based on pricing label or type
    const label = (pricing.label || '').toLowerCase();
    
    if (label.includes('tier 1')) return 'tier-1';
    if (label.includes('tier 2')) return 'tier-2';
    if (label.includes('tier 3')) return 'tier-3';
    if (label.includes('tier 4')) return 'tier-4';
    if (label.includes('tier 5')) return 'tier-5';
    if (label.includes('bundle')) return 'bundle';
    if (label.includes('custom')) return 'custom';
    
    // Check pricing type if available
    if (pricing.type) {
      const type = pricing.type.toLowerCase();
      if (type === 'bundle') return 'bundle';
      if (type === 'custom') return 'custom';
      if (type === 'tier') {
        // Try to extract tier number from label
        const tierMatch = label.match(/tier\s*(\d+)/);
        if (tierMatch) {
          const tierNum = parseInt(tierMatch[1]);
          if (tierNum >= 1 && tierNum <= 5) {
            return `tier-${tierNum}`;
          }
        }
      }
    }
    
    // Default fallback based on price ranges (monthly pricing)
    if (pricing.monthly) {
      const monthly = parseFloat(pricing.monthly);
      if (monthly <= 3.99) return 'tier-1';  // Tier 1: Up to $3.99
      if (monthly <= 4.99) return 'tier-2';  // Tier 2: $4.00-$4.99
      if (monthly <= 5.99) return 'tier-3';  // Tier 3: $5.00-$5.99
      if (monthly <= 7.99) return 'tier-4';  // Tier 4: $6.00-$7.99
      return 'tier-5';                       // Tier 5: $8.00+
    }
    
    return 'other';
  }

  formatPrice(amount, currency = 'USD') {
    if (amount === null || amount === undefined) {
      return 'N/A';
    }

    const formatter = new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: currency,
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });

    return formatter.format(parseFloat(amount));
  }

  updatePricing(newPricing) {
    this.pricingData = newPricing;
    if (this.isHovered && !this.isLoading) {
      this.displayPricing(newPricing);
    }
  }

  destroy() {
    this.card.removeEventListener('mouseenter', this.handleMouseEnter);
    this.card.removeEventListener('mouseleave', this.handleMouseLeave);
    this.card.removeEventListener('touchstart', this.handleTouchStart);
    this.card.removeEventListener('touchend', this.handleTouchEnd);
    
    if (this.overlay && this.overlay.parentNode) {
      this.overlay.parentNode.removeChild(this.overlay);
    }
    
    if (this.hoverTimeout) {
      clearTimeout(this.hoverTimeout);
    }
  }
}

/**
 * Simple cache system for pricing data
 */
class CityPricingCache {
  static cache = new Map();
  static maxAge = 300000; // 5 minutes

  static set(key, data) {
    this.cache.set(key, {
      data: data,
      timestamp: Date.now()
    });
  }

  static get(key) {
    const item = this.cache.get(key);
    if (!item) return null;

    if (Date.now() - item.timestamp > this.maxAge) {
      this.cache.delete(key);
      return null;
    }

    return item.data;
  }

  static clear() {
    this.cache.clear();
  }
}

/**
 * Auto-initialization system
 */
function initializeCityPricingOverlays() {
  // Common selectors for city cards
  const selectors = [
    '.city',
    '.city-card', 
    '.location-card',
    '.directory-card',
    '[data-city-slug]',
    '[data-slug]'
  ];

  selectors.forEach(selector => {
    const cards = document.querySelectorAll(selector);
    cards.forEach(card => {
      if (!card.cityPricingOverlay) {
        card.cityPricingOverlay = new CityPricingOverlay(card);
      }
    });
  });
}

/**
 * Manual initialization for specific cards
 */
function initializeCityCard(cardElement, options = {}) {
  if (cardElement && !cardElement.cityPricingOverlay) {
    cardElement.cityPricingOverlay = new CityPricingOverlay(cardElement, options);
    return cardElement.cityPricingOverlay;
  }
  return null;
}

/**
 * Refresh all pricing data
 */
function refreshAllPricing() {
  CityPricingCache.clear();
  
  document.querySelectorAll('.city-pricing-overlay').forEach(overlay => {
    const card = overlay.closest('.city, .city-card, .location-card, .directory-card');
    if (card && card.cityPricingOverlay) {
      card.cityPricingOverlay.pricingData = null;
    }
  });
}

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
  initializeCityPricingOverlays();
});

// Re-initialize when new content is added dynamically
const observer = new MutationObserver(function(mutations) {
  mutations.forEach(function(mutation) {
    if (mutation.type === 'childList') {
      mutation.addedNodes.forEach(function(node) {
        if (node.nodeType === Node.ELEMENT_NODE) {
          // Check if the added node is a city card
          const selectors = ['.city', '.city-card', '.location-card', '.directory-card'];
          selectors.forEach(selector => {
            if (node.matches && node.matches(selector)) {
              initializeCityCard(node);
            }
            // Also check children
            const childCards = node.querySelectorAll ? node.querySelectorAll(selector) : [];
            childCards.forEach(card => initializeCityCard(card));
          });
        }
      });
    }
  });
});

// Start observing
observer.observe(document.body, {
  childList: true,
  subtree: true
});

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
  module.exports = { 
    CityPricingOverlay, 
    CityPricingCache,
    initializeCityPricingOverlays, 
    initializeCityCard,
    refreshAllPricing
  };
}

// Global access
window.CityPricingOverlay = CityPricingOverlay;
window.CityPricingCache = CityPricingCache;
window.initializeCityPricingOverlays = initializeCityPricingOverlays;
window.initializeCityCard = initializeCityCard;
window.refreshAllPricing = refreshAllPricing;
