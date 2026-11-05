import './bootstrap';
import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';

// Expose Alpine and Chart globally for Blade inline components
window.Alpine = Alpine;
window.Chart = Chart;

// Global Storefront Cart Store
Alpine.store('cart', {
    open: false,
    items: [],
    savedItems: [],
    groupedBySeller: [],
    itemCount: 0,
    savedCount: 0,
    subtotal: 0,
    formattedSubtotal: '₹0.00',
    totalMrp: 0,
    formattedTotalMrp: '₹0.00',
    productDiscount: 0,
    formattedProductDiscount: '₹0.00',
    couponCode: null,
    couponDiscount: 0,
    formattedCouponDiscount: '₹0.00',
    couponError: null,
    deliveryFee: 0,
    formattedDeliveryFee: 'FREE',
    freeThreshold: 499,
    freeRemaining: 499,
    freeQualified: false,
    progressPercent: 0,
    totalPayable: 0,
    formattedTotalPayable: '₹0.00',
    totalSavings: 0,
    formattedTotalSavings: '₹0.00',
    priceChangesDetected: false,
    stockIssuesDetected: false,
    loading: false,

    init() {
        this.fetchSummary();
    },

    toggleDrawer() {
        this.open = !this.open;
    },

    async fetchSummary() {
        try {
            const res = await fetch('/api/storefront/cart/summary', {
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            if (data && data.cart) {
                this.applySummary(data.cart);
            }
        } catch (e) {
            console.error('Failed to load cart summary', e);
        }
    },

    applySummary(cart) {
        this.items = cart.items || [];
        this.savedItems = cart.saved_items || [];
        this.groupedBySeller = cart.grouped_by_seller || [];
        this.itemCount = cart.item_count || 0;
        this.savedCount = cart.saved_count || 0;
        this.subtotal = cart.subtotal || 0;
        this.formattedSubtotal = cart.formatted_subtotal || '₹0.00';
        this.totalMrp = cart.total_mrp || 0;
        this.formattedTotalMrp = cart.formatted_total_mrp || '₹0.00';
        this.productDiscount = cart.product_discount || 0;
        this.formattedProductDiscount = cart.formatted_product_discount || '₹0.00';
        this.couponCode = cart.coupon_code || null;
        this.couponDiscount = cart.coupon_discount || 0;
        this.formattedCouponDiscount = cart.formatted_coupon_discount || '₹0.00';
        this.couponError = cart.coupon_error || null;
        this.deliveryFee = cart.delivery_fee || 0;
        this.formattedDeliveryFee = cart.formatted_delivery_fee || 'FREE';
        this.freeThreshold = cart.free_delivery_threshold || 499;
        this.freeRemaining = cart.free_delivery_remaining || 0;
        this.freeQualified = cart.free_delivery_qualified || false;
        this.progressPercent = cart.progress_percent || 0;
        this.totalPayable = cart.total_payable || 0;
        this.formattedTotalPayable = cart.formatted_total_payable || '₹0.00';
        this.totalSavings = cart.total_savings || 0;
        this.formattedTotalSavings = cart.formatted_total_savings || '₹0.00';
        this.priceChangesDetected = cart.price_changes_detected || false;
        this.stockIssuesDetected = cart.stock_issues_detected || false;
    },

    getItemQuantity(variantId, productId = null) {
        if (variantId) {
            const found = this.items.find(i => Number(i.variant_id) === Number(variantId));
            return found ? found.quantity : 0;
        }
        if (productId) {
            const found = this.items.find(i => Number(i.product_id) === Number(productId));
            return found ? found.quantity : 0;
        }
        return 0;
    },

    async addItem(variantId, productId = null, quantity = 1) {
        this.loading = true;
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const res = await fetch('/api/storefront/cart/add', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ variant_id: variantId, product_id: productId, quantity: quantity })
            });
            const data = await res.json();
            if (data && data.cart) {
                this.applySummary(data.cart);
            }
        } catch (e) {
            console.error('Add to cart error', e);
        } finally {
            this.loading = false;
        }
    },

    async updateQuantity(itemId, quantity) {
        this.loading = true;
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const res = await fetch(`/api/storefront/cart/update/${itemId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ quantity: quantity })
            });
            const data = await res.json();
            if (data && data.cart) {
                this.applySummary(data.cart);
            }
        } catch (e) {
            console.error('Update quantity error', e);
        } finally {
            this.loading = false;
        }
    },

    async removeItem(itemId) {
        this.loading = true;
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const res = await fetch(`/api/storefront/cart/remove/${itemId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                }
            });
            const data = await res.json();
            if (data && data.cart) {
                this.applySummary(data.cart);
            }
        } catch (e) {
            console.error('Remove item error', e);
        } finally {
            this.loading = false;
        }
    },

    async saveForLater(itemId) {
        this.loading = true;
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const res = await fetch(`/api/storefront/cart/save-for-later/${itemId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                }
            });
            const data = await res.json();
            if (data && data.cart) {
                this.applySummary(data.cart);
            }
        } catch (e) {
            console.error('Save for later error', e);
        } finally {
            this.loading = false;
        }
    },

    async moveToCart(itemId) {
        this.loading = true;
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const res = await fetch(`/api/storefront/cart/move-to-cart/${itemId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                }
            });
            const data = await res.json();
            if (data && data.cart) {
                this.applySummary(data.cart);
            }
        } catch (e) {
            console.error('Move to cart error', e);
        } finally {
            this.loading = false;
        }
    },

    async applyCoupon(code) {
        this.loading = true;
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const res = await fetch('/api/storefront/cart/coupon/apply', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ coupon_code: code })
            });
            const data = await res.json();
            if (data && data.cart) {
                this.applySummary(data.cart);
            }
            return data;
        } catch (e) {
            console.error('Apply coupon error', e);
            return { status: 'error', message: 'Failed to apply coupon' };
        } finally {
            this.loading = false;
        }
    },

    async removeCoupon() {
        this.loading = true;
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const res = await fetch('/api/storefront/cart/coupon/remove', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                }
            });
            const data = await res.json();
            if (data && data.cart) {
                this.applySummary(data.cart);
            }
        } catch (e) {
            console.error('Remove coupon error', e);
        } finally {
            this.loading = false;
        }
    },

    async acceptPriceChanges() {
        this.loading = true;
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const res = await fetch('/api/storefront/cart/accept-price-changes', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                }
            });
            const data = await res.json();
            if (data && data.cart) {
                this.applySummary(data.cart);
            }
        } catch (e) {
            console.error('Accept price changes error', e);
        } finally {
            this.loading = false;
        }
    }
});

// Global Storefront Wishlist Store
Alpine.store('wishlist', {
    ids: [],
    count: 0,

    init() {
        this.fetchStatus();
    },

    async fetchStatus() {
        try {
            const res = await fetch('/api/storefront/wishlist/status', {
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            if (data) {
                this.ids = data.ids || [];
                this.count = data.count || 0;
            }
        } catch (e) {
            console.error('Wishlist status error', e);
        }
    },

    has(productId) {
        return this.ids.includes(Number(productId));
    },

    async toggle(productId) {
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const res = await fetch('/api/storefront/wishlist/toggle', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ product_id: productId })
            });
            const data = await res.json();
            if (data && data.status === 'success') {
                this.count = data.wishlist_count;
                if (data.is_wishlisted) {
                    if (!this.ids.includes(Number(productId))) this.ids.push(Number(productId));
                } else {
                    this.ids = this.ids.filter(id => id !== Number(productId));
                }
            }
        } catch (e) {
            console.error('Wishlist toggle error', e);
        }
    },

    async moveToCart(productId) {
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const res = await fetch(`/api/storefront/wishlist/move-to-cart/${productId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                }
            });
            const data = await res.json();
            if (data && data.status === 'success') {
                this.ids = this.ids.filter(id => id !== Number(productId));
                this.count = this.ids.length;
                if (data.cart) {
                    Alpine.store('cart').applySummary(data.cart);
                }
            }
            return data;
        } catch (e) {
            console.error('Wishlist move-to-cart error', e);
            return { status: 'error', message: 'Failed to move to cart' };
        }
    },

    async remove(productId) {
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const res = await fetch(`/api/storefront/wishlist/remove/${productId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                }
            });
            const data = await res.json();
            if (data && data.status === 'success') {
                this.ids = this.ids.filter(id => id !== Number(productId));
                this.count = this.ids.length;
            }
        } catch (e) {
            console.error('Wishlist remove error', e);
        }
    }
});

// Global Storefront Location Store
Alpine.store('location', {
    modalOpen: false,
    current: {
        city: 'Bengaluru',
        pincode: '560001',
        state: 'Karnataka',
        address_label: 'Bengaluru 560001',
        lat: 12.9716,
        lng: 77.5946
    },
    popular: [],
    saved: [],
    searchQuery: '',
    searchResults: [],
    detecting: false,
    errorMessage: '',

    init() {
        this.loadCurrent();
    },

    async loadCurrent() {
        try {
            const res = await fetch('/api/storefront/location/current', {
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            if (data) {
                if (data.current) this.current = data.current;
                if (data.popular) this.popular = data.popular;
                if (data.saved) this.saved = data.saved;
                this.searchResults = this.popular;
            }
        } catch (e) {
            console.error('Location load error', e);
        }
    },

    async search() {
        try {
            const res = await fetch(`/api/storefront/location/search?q=${encodeURIComponent(this.searchQuery)}`, {
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            this.searchResults = data.results || [];
        } catch (e) {
            console.error('Search location error', e);
        }
    },

    async setLocation(loc) {
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const res = await fetch('/api/storefront/location/set', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify(loc)
            });
            const data = await res.json();
            if (data && data.location) {
                this.current = data.location;
                this.modalOpen = false;
                // Reload page to refresh nearby store feeds & delivery zones
                window.location.reload();
            }
        } catch (e) {
            console.error('Set location error', e);
        }
    },

    detectCurrentLocation() {
        if (!navigator.geolocation) {
            this.errorMessage = 'Geolocation is not supported by your browser.';
            return;
        }

        this.detecting = true;
        this.errorMessage = '';

        navigator.geolocation.getCurrentPosition(
            async (position) => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                await this.setLocation({
                    lat: lat,
                    lng: lng,
                    city: 'Current Location',
                    pincode: 'Live GPS',
                    state: 'India',
                    address_label: `Near My GPS (${lat.toFixed(2)}, ${lng.toFixed(2)})`,
                    is_detected: true
                });
                this.detecting = false;
            },
            (err) => {
                this.detecting = false;
                this.errorMessage = 'Location access denied or timed out. Please choose a city below.';
                console.warn('Geolocation error:', err.message);
            },
            { timeout: 10000, enableHighAccuracy: true }
        );
    }
});

// Initialize Alpine.js
Alpine.start();
