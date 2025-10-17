/**
 * ION Channel Selector
 * Handles channel search, selection, and multi-channel distribution
 */

class IONChannelSelector {
    constructor() {
        this.selectedChannels = [];
        this.searchTimeout = null;
        this.userRole = 'Guest';
        this.canMultiSelect = false;
        this.init();
    }

    init() {
        const searchInput = document.getElementById('channelSearch');
        const resultsContainer = document.getElementById('channelSearchResults');

        if (!searchInput || !resultsContainer) {
            console.warn('Channel selector elements not found');
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
                this.searchChannels(query);
            }, 300);
        });

        // Close results when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.channel-search-container')) {
                resultsContainer.classList.remove('show');
            }
        });

        // Handle Enter key to select first result
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const firstResult = resultsContainer.querySelector('.channel-search-result-item');
                if (firstResult) {
                    firstResult.click();
                }
            }
        });

        console.log('✅ ION Channel Selector initialized');
    }

    loadChannels(channels) {
        console.log('🔄 loadChannels called with:', channels);
        
        if (!channels || !Array.isArray(channels)) {
            console.warn('⚠️ Invalid channels data provided to loadChannels:', channels);
            return;
        }
        
        if (channels.length === 0) {
            console.log('ℹ️ Empty channels array provided');
            return;
        }
        
        // Convert from simple array to full channel objects
        this.selectedChannels = channels.map(ch => {
            console.log('🔄 Processing channel:', ch);
            return {
                slug: ch.slug || ch,
                name: ch.name || ch.slug,
                channel_name: ch.channel_name || ch.name || ch.slug,
                state: ch.state || '',
                country: ch.country || 'US',
                display: ch.display || ch.name || ch.slug,
                population: ch.population || 0
            };
        });
        
        console.log('📺 Converted selected channels:', this.selectedChannels);
        
        this.renderSelectedChannels();
        this.updateHiddenField();
        
        console.log('✅ Loaded ' + this.selectedChannels.length + ' channels in edit mode');
        console.log('📝 Hidden field value:', document.getElementById('selectedChannels')?.value);
    }

    async searchChannels(query) {
        const resultsContainer = document.getElementById('channelSearchResults');
        
        try {
            const response = await fetch(`/app/channel-search-api.php?q=${encodeURIComponent(query)}&limit=20`);
            const data = await response.json();

            if (!data.success) {
                throw new Error(data.error || 'Search failed');
            }

            // Store user permissions
            this.userRole = data.user_role || 'Guest';
            this.canMultiSelect = data.can_multi_select || false;
            
            console.log('👤 User role:', this.userRole, '| Can multi-select:', this.canMultiSelect);

            this.displayResults(data.channels);
            
        } catch (error) {
            console.error('Channel search error:', error);
            resultsContainer.innerHTML = '<div class="channel-search-empty">❌ Search failed. Please try again.</div>';
            resultsContainer.classList.add('show');
        }
    }

    displayResults(channels) {
        const resultsContainer = document.getElementById('channelSearchResults');
        
        if (!channels || channels.length === 0) {
            resultsContainer.innerHTML = '<div class="channel-search-empty">No channels found</div>';
            resultsContainer.classList.add('show');
            return;
        }

        // Filter out already selected channels
        const selectedSlugs = this.selectedChannels.map(ch => ch.slug);
        const availableChannels = channels.filter(ch => !selectedSlugs.includes(ch.slug));

        if (availableChannels.length === 0) {
            resultsContainer.innerHTML = '<div class="channel-search-empty">All matching channels already selected</div>';
            resultsContainer.classList.add('show');
            return;
        }

        resultsContainer.innerHTML = availableChannels.map(channel => `
            <div class="channel-search-result-item" data-channel='${JSON.stringify(channel)}'>
                <div class="channel-result-name">${this.escapeHtml(channel.display)}</div>
                <div class="channel-result-details">Slug: ${this.escapeHtml(channel.slug)}</div>
            </div>
        `).join('');

        // Add click handlers
        resultsContainer.querySelectorAll('.channel-search-result-item').forEach(item => {
            item.addEventListener('click', () => {
                const channel = JSON.parse(item.dataset.channel);
                this.addChannel(channel);
                resultsContainer.classList.remove('show');
                document.getElementById('channelSearch').value = '';
            });
        });

        resultsContainer.classList.add('show');
    }

    addChannel(channel) {
        // Check if already added
        if (this.selectedChannels.some(ch => ch.slug === channel.slug)) {
            return;
        }

        // Check multi-channel permission
        if (this.selectedChannels.length >= 1 && !this.canMultiSelect) {
            this.showPermissionMessage();
            return;
        }

        this.selectedChannels.push(channel);
        this.renderSelectedChannels();
        this.updateHiddenField();
        
        console.log('📍 Channel added:', channel.slug, 'Total:', this.selectedChannels.length);
    }

    showPermissionMessage() {
        const resultsContainer = document.getElementById('channelSearchResults');
        resultsContainer.innerHTML = `
            <div class="channel-search-empty" style="color: #f59e0b; padding: 15px;">
                🔒 <strong>Admin/Owner Permission Required</strong><br>
                <span style="font-size: 13px;">Only Admins and Owners can select multiple channels. You can select one primary channel.</span>
            </div>
        `;
        resultsContainer.classList.add('show');
        
        // Hide message after 4 seconds
        setTimeout(() => {
            resultsContainer.classList.remove('show');
        }, 4000);
    }

    removeChannel(slug) {
        this.selectedChannels = this.selectedChannels.filter(ch => ch.slug !== slug);
        this.renderSelectedChannels();
        this.updateHiddenField();
        
        console.log('❌ Channel removed:', slug, 'Total:', this.selectedChannels.length);
    }

    renderSelectedChannels() {
        const container = document.getElementById('selectedChannelsList');
        
        if (this.selectedChannels.length === 0) {
            const helpText = this.canMultiSelect 
                ? 'No channels selected. Search to add multiple channels.' 
                : 'No channels selected. Search to add your primary channel.';
            container.innerHTML = `<div style="color: var(--text-secondary); font-size: 13px; padding: 8px 0;">${helpText}</div>`;
            return;
        }

        const canReorder = this.canMultiSelect && this.selectedChannels.length > 1;
        
        container.innerHTML = this.selectedChannels.map((channel, index) => `
            <div class="selected-channel-item" data-slug="${this.escapeHtml(channel.slug)}" ${canReorder ? 'draggable="true"' : ''}>
                ${canReorder ? '<span class="selected-channel-drag">⋮⋮</span>' : ''}
                <div class="selected-channel-info">
                    <div class="selected-channel-name">${this.escapeHtml(channel.display)}</div>
                    <div class="selected-channel-slug">${this.escapeHtml(channel.slug)}</div>
                </div>
                ${index === 0 ? '<span class="selected-channel-primary-badge">Primary</span>' : ''}
                <button type="button" class="selected-channel-remove" onclick="channelSelector.removeChannel('${this.escapeHtml(channel.slug)}')" title="Remove channel">×</button>
            </div>
        `).join('');

        // Add drag & drop functionality only if user can multi-select
        if (canReorder) {
            this.initDragAndDrop();
        }
    }

    initDragAndDrop() {
        const items = document.querySelectorAll('.selected-channel-item');
        let draggedItem = null;

        items.forEach(item => {
            item.addEventListener('dragstart', (e) => {
                draggedItem = item;
                item.style.opacity = '0.5';
            });

            item.addEventListener('dragend', (e) => {
                item.style.opacity = '1';
            });

            item.addEventListener('dragover', (e) => {
                e.preventDefault();
            });

            item.addEventListener('drop', (e) => {
                e.preventDefault();
                if (draggedItem !== item) {
                    // Reorder channels
                    const draggedSlug = draggedItem.dataset.slug;
                    const targetSlug = item.dataset.slug;
                    
                    const draggedIndex = this.selectedChannels.findIndex(ch => ch.slug === draggedSlug);
                    const targetIndex = this.selectedChannels.findIndex(ch => ch.slug === targetSlug);
                    
                    // Move dragged item to target position
                    const [removed] = this.selectedChannels.splice(draggedIndex, 1);
                    this.selectedChannels.splice(targetIndex, 0, removed);
                    
                    this.renderSelectedChannels();
                    this.updateHiddenField();
                    
                    console.log('🔄 Channels reordered. New primary:', this.selectedChannels[0]?.slug);
                }
            });
        });
    }

    updateHiddenField() {
        const hiddenField = document.getElementById('selectedChannels');
        if (hiddenField) {
            // Store as JSON array of channel slugs
            const slugs = this.selectedChannels.map(ch => ch.slug);
            hiddenField.value = JSON.stringify(slugs);
        }
    }

    getSelectedChannels() {
        return this.selectedChannels;
    }

    getPrimaryChannel() {
        return this.selectedChannels[0] || null;
    }

    getDistributionChannels() {
        return this.selectedChannels.slice(1); // All except first (primary)
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Load existing channels (for edit mode)
    loadChannels(channels) {
        if (!channels || channels.length === 0) return;
        
        // channels can be array of slugs or array of channel objects
        channels.forEach(channel => {
            if (typeof channel === 'string') {
                // Just a slug, create minimal channel object
                this.selectedChannels.push({
                    slug: channel,
                    name: channel,
                    display: channel
                });
            } else {
                this.selectedChannels.push(channel);
            }
        });
        
        this.renderSelectedChannels();
        this.updateHiddenField();
    }
}

// Initialize on page load
let channelSelector;
document.addEventListener('DOMContentLoaded', () => {
    channelSelector = new IONChannelSelector();
    
    // Make it globally available
    window.channelSelector = channelSelector;
});

