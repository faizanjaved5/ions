/**
 * ION Shopping Cart Manager
 * Handles cart operations with interval selection and duplicate prevention
 */

class IONCartManager {
    constructor() {
        this.cart = {
            items: [],
            total: 0
        };
        this.loadCart();
        this.intervals = ['monthly', 'quarterly', 'semi_annual', 'annual'];
    }

    /**
     * Load cart from localStorage
     */
    loadCart() {
        const savedCart = localStorage.getItem('ionBlastCart');
        if (savedCart) {
            this.cart = JSON.parse(savedCart);
        }
        this.updateDisplay();
    }

    /**
     * Save cart to localStorage
     */
    saveCart() {
        localStorage.setItem('ionBlastCart', JSON.stringify(this.cart));
    }

    /**
     * Add item to cart with duplicate prevention
     * @param {string} type - 'channel' or 'package'
     * @param {Object} item - Item details
     */
    addItem(type, item) {
        // Check if a video is selected
        if (typeof selectedVideo !== 'undefined' && !selectedVideo) {
            this.showFeedback('⚠️ Please select a video first before adding items to cart', 'error');
            return false;
        }

        // Create unique identifier based on channel/package name (not interval)
        const baseId = type === 'package' ? item.id : item.slug || item.id;
        
        // Check if this channel/package already exists (regardless of interval)
        const existingIndex = this.cart.items.findIndex(cartItem => {
            const cartBaseId = cartItem.type === 'package' ? cartItem.id : cartItem.slug || cartItem.id;
            return cartBaseId === baseId;
        });

        if (existingIndex !== -1) {
            // Update existing item with new interval and pricing
            this.cart.items[existingIndex] = {
                ...this.cart.items[existingIndex],
                interval: item.interval || 'monthly',
                price: parseFloat(item.price),
                duration: item.duration || item.interval || 'monthly'
            };
            this.showFeedback(`Updated ${item.name} with ${item.interval || 'monthly'} billing`);
        } else {
            // Add new item
            const cartItem = {
                id: baseId,
                slug: item.slug || baseId,
                name: item.name,
                type: type,
                interval: item.interval || 'monthly',
                price: parseFloat(item.price),
                duration: item.duration || item.interval || 'monthly',
                ...item
            };
            this.cart.items.push(cartItem);
            this.showFeedback(`Added ${item.name} to cart`);
        }

        this.calculateTotal();
        this.saveCart();
        this.updateDisplay();
        return true;
    }

    /**
     * Remove item from cart
     * @param {string} itemId - Item identifier
     */
    removeItem(itemId) {
        this.cart.items = this.cart.items.filter(item => {
            const cartBaseId = item.type === 'package' ? item.id : item.slug || item.id;
            return cartBaseId !== itemId;
        });
        this.calculateTotal();
        this.saveCart();
        this.updateDisplay();
    }

    /**
     * Update item interval and pricing
     * @param {string} itemId - Item identifier
     * @param {string} newInterval - New billing interval
     * @param {Object} pricing - Pricing object with all intervals
     */
    updateItemInterval(itemId, newInterval, pricing) {
        const itemIndex = this.cart.items.findIndex(item => {
            const cartBaseId = item.type === 'package' ? item.id : item.slug || item.id;
            return cartBaseId === itemId;
        });

        if (itemIndex !== -1) {
            const priceKey = newInterval === 'semi_annual' ? 'semi_annual' : newInterval;
            const newPrice = pricing[priceKey] || pricing.monthly;
            
            this.cart.items[itemIndex].interval = newInterval;
            this.cart.items[itemIndex].price = parseFloat(newPrice);
            this.cart.items[itemIndex].duration = newInterval;

            this.calculateTotal();
            this.saveCart();
            this.updateDisplay();
            this.showFeedback(`Updated to ${newInterval} billing`);
        }
    }

    /**
     * Calculate cart total
     */
    calculateTotal() {
        this.cart.total = this.cart.items.reduce((sum, item) => sum + parseFloat(item.price || 0), 0);
    }

    /**
     * Clear entire cart
     */
    clearCart() {
        this.cart = { items: [], total: 0 };
        localStorage.removeItem('ionBlastCart');
        this.updateDisplay();
    }

    /**
     * Update cart display
     */
    updateDisplay() {
        const cartBtn = document.getElementById('cartBtn');
        const cartCount = document.getElementById('cartCount');
        
        if (cartBtn && cartCount) {
            const totalItems = this.cart.items.length;
            
            if (totalItems === 0) {
                cartBtn.classList.add('disabled');
                cartCount.classList.add('hidden');
                cartCount.textContent = '0';
            } else {
                cartBtn.classList.remove('disabled');
                cartCount.classList.remove('hidden');
                cartCount.textContent = totalItems.toString();
            }
        }
    }

    /**
     * Show cart modal with interval selectors
     */
    showModal() {
        if (this.cart.items.length === 0) {
            this.showFeedback('Cart is empty');
            return;
        }

        const modal = document.createElement('div');
        modal.className = 'cart-modal';
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        `;

        modal.innerHTML = `
            <div style="background: white; border-radius: 12px; max-width: 700px; width: 90%; max-height: 80vh; overflow-y: auto; padding: 24px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #e5e7eb; padding-bottom: 16px;">
                    <h2 style="margin: 0; color: #1f2937; font-size: 1.5rem;">Shopping Cart</h2>
                    <button onclick="document.body.removeChild(this.closest('.cart-modal'))" style="background: #ef4444; color: white; border: none; border-radius: 6px; width: 32px; height: 32px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: bold;">×</button>
                </div>
                <div class="cart-items-list">
                    ${this.cart.items.map(item => this.renderCartItem(item)).join('')}
                </div>
                <div style="margin-top: 20px; padding-top: 16px; border-top: 2px solid #e5e7eb;">
                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 1.25rem; font-weight: 700; margin-bottom: 16px;">
                        <span>Total:</span>
                        <span style="color: #059669;">$${this.cart.total.toFixed(2)}</span>
                    </div>
                    <div style="display: flex; gap: 12px;">
                        <button onclick="cartManager.clearCart(); document.body.removeChild(this.closest('.cart-modal'))" style="background: #6b7280; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-weight: 600;">Clear Cart</button>
                        <button onclick="cartManager.proceedToCheckout()" style="background: #059669; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-weight: 600; flex: 1;">Proceed to Checkout</button>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Add event listeners for interval changes
        this.attachIntervalListeners();
    }

    /**
     * Render individual cart item with interval selector
     */
    renderCartItem(item) {
        const intervals = [
            { key: 'monthly', label: 'Monthly' },
            { key: 'quarterly', label: 'Quarterly' },
            { key: 'semi_annual', label: 'Semi-Annual' },
            { key: 'annual', label: 'Annual' }
        ];

        const baseId = item.type === 'package' ? item.id : item.slug || item.id;

        return `
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px 0; border-bottom: 1px solid #f3f4f6;">
                <div style="flex: 1;">
                    <div style="font-weight: 600; color: #1f2937; margin-bottom: 4px;">${item.name}</div>
                    <div style="font-size: 0.875rem; color: #6b7280; margin-bottom: 8px;">${item.type === 'package' ? 'Package' : 'Channel'}</div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <label style="font-size: 0.875rem; color: #4b5563;">Billing:</label>
                        <select 
                            data-item-id="${baseId}"
                            data-item-type="${item.type}"
                            class="interval-selector"
                            style="padding: 4px 8px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.875rem;"
                        >
                            ${intervals.map(interval => `
                                <option value="${interval.key}" ${item.interval === interval.key ? 'selected' : ''}>
                                    ${interval.label}
                                </option>
                            `).join('')}
                        </select>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span style="font-weight: 600; color: #059669; font-size: 1.125rem;">$${parseFloat(item.price).toFixed(2)}</span>
                    <button 
                        onclick="cartManager.removeItem('${baseId}'); document.body.removeChild(this.closest('.cart-modal')); cartManager.showModal();" 
                        style="background: #ef4444; color: white; border: none; border-radius: 4px; padding: 6px 12px; cursor: pointer; font-size: 12px;"
                    >
                        Remove
                    </button>
                </div>
            </div>
        `;
    }

    /**
     * Attach event listeners for interval selectors
     */
    attachIntervalListeners() {
        const selectors = document.querySelectorAll('.interval-selector');
        selectors.forEach(selector => {
            selector.addEventListener('change', async (e) => {
                const itemId = e.target.dataset.itemId;
                const itemType = e.target.dataset.itemType;
                const newInterval = e.target.value;
                
                // Get pricing for this item
                try {
                    const pricing = await this.fetchItemPricing(itemId, itemType);
                    if (pricing) {
                        this.updateItemInterval(itemId, newInterval, pricing);
                        // Refresh modal
                        document.body.removeChild(document.querySelector('.cart-modal'));
                        this.showModal();
                    }
                } catch (error) {
                    console.error('Error updating interval:', error);
                    this.showFeedback('Error updating billing interval', 'error');
                }
            });
        });
    }

    /**
     * Fetch pricing for an item
     */
    async fetchItemPricing(itemId, itemType) {
        if (itemType === 'package') {
            // For packages, we might need different logic
            return null; // Implement package pricing fetch if needed
        } else {
            // For channels, fetch from pricing API
            try {
                const response = await fetch('../api/get-pricing.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=get_pricing&slug=${encodeURIComponent(itemId)}`
                });
                
                const data = await response.json();
                if (data.success && data.pricing) {
                    return data.pricing;
                }
            } catch (error) {
                console.error('Error fetching pricing:', error);
            }
        }
        return null;
    }

    /**
     * Show feedback message
     */
    showFeedback(message, type = 'success') {
        const feedback = document.createElement('div');
        const backgroundColor = type === 'error' ? '#ef4444' : '#10b981';
        feedback.style.cssText = `
            position: fixed;
            top: 100px;
            right: 20px;
            background: ${backgroundColor};
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            z-index: 1001;
            animation: slideInRight 0.3s ease;
            max-width: 300px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        `;
        feedback.textContent = message;
        document.body.appendChild(feedback);
        
        const duration = type === 'error' ? 4000 : 3000;
        setTimeout(() => {
            if (document.body.contains(feedback)) {
                document.body.removeChild(feedback);
            }
        }, duration);
    }

    /**
     * Proceed to checkout
     */
    proceedToCheckout() {
        // Implement checkout logic here
        this.showFeedback('Proceeding to checkout...');
        console.log('Cart items for checkout:', this.cart.items);
    }
}

// Global cart manager instance
let cartManager;

// Initialize on DOM load
document.addEventListener('DOMContentLoaded', function() {
    cartManager = new IONCartManager();
});

// CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
`;
document.head.appendChild(style);
