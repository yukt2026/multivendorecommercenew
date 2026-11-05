@extends('layouts.storefront')

@section('title', 'Shopping Cart - OneStall.in')

@section('content')
<div class="min-h-screen bg-slate-50 py-6 sm:py-8"
     x-data="{
        couponInput: '{{ $summary['coupon_code'] ?? '' }}',
        couponLoading: false,
        couponMessage: '{{ $summary['coupon_error'] ?? '' }}',
        couponSuccess: false,
        async handleApplyCoupon() {
            if (!this.couponInput.trim()) return;
            this.couponLoading = true;
            this.couponMessage = '';
            const res = await $store.cart.applyCoupon(this.couponInput.trim());
            this.couponLoading = false;
            this.couponMessage = res.message;
            this.couponSuccess = res.status === 'success';
        },
        async handleRemoveCoupon() {
            this.couponLoading = true;
            await $store.cart.removeCoupon();
            this.couponLoading = false;
            this.couponInput = '';
            this.couponMessage = 'Coupon removed';
            this.couponSuccess = true;
        }
     }">

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Breadcrumb -->
        <nav class="flex items-center space-x-2 text-xs font-semibold text-slate-500 mb-6">
            <a href="{{ route('home') }}" class="hover:text-indigo-600 transition">Home</a>
            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span class="text-slate-900 font-bold">Shopping Cart</span>
        </nav>

        <!-- Flash messages -->
        @if (session('success'))
            <div class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-semibold flex items-center justify-between shadow-sm">
                <div class="flex items-center space-x-2">
                    <svg class="w-5 h-5 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span>{{ session('success') }}</span>
                </div>
            </div>
        @endif

        @if (session('error'))
            <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-sm font-semibold flex items-center justify-between shadow-sm">
                <div class="flex items-center space-x-2">
                    <svg class="w-5 h-5 text-rose-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    <span>{{ session('error') }}</span>
                </div>
            </div>
        @endif

        <!-- Price Change Warning Banner -->
        @if ($summary['price_changes_detected'])
            <div class="mb-6 p-4 sm:p-5 rounded-2xl bg-amber-50 border border-amber-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-sm">
                <div class="flex items-start space-x-3">
                    <div class="p-2 bg-amber-100 rounded-xl text-amber-700 flex-shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                    <div>
                        <h4 class="text-sm font-extrabold text-amber-900">Price updates in your cart</h4>
                        <p class="text-xs text-amber-700 mt-0.5">The prices of one or more items have changed since they were added to your cart. Please review them below.</p>
                    </div>
                </div>
                <form action="{{ route('cart.accept-price-changes') }}" method="POST" class="flex-shrink-0">
                    @csrf
                    <button type="submit" @click.prevent="$store.cart.acceptPriceChanges()"
                            class="w-full sm:w-auto px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs shadow-sm active:scale-95 transition">
                        Accept Updated Prices
                    </button>
                </form>
            </div>
        @endif

        <!-- Stock Issue Warning Banner -->
        @if ($summary['stock_issues_detected'])
            <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 flex items-center space-x-3 text-rose-800 shadow-sm">
                <svg class="w-5 h-5 text-rose-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div class="text-xs font-semibold">
                    Some items in your cart have limited stock or are currently sold out. Please adjust quantities before checking out.
                </div>
            </div>
        @endif

        <div class="flex items-center justify-between pb-4 mb-6 border-b border-slate-200">
            <div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Shopping Cart</h1>
                <p class="text-xs text-slate-500 font-medium mt-1">
                    <span x-text="$store.cart.itemCount">{{ $summary['item_count'] }}</span> active item(s) ready for checkout
                </p>
            </div>

            @if ($summary['item_count'] > 0)
                <form action="{{ route('cart.clear') }}" method="POST">
                    @csrf
                    <button type="submit" class="text-xs font-bold text-slate-500 hover:text-rose-600 transition">
                        Clear Cart
                    </button>
                </form>
            @endif
        </div>

        @if ($summary['item_count'] === 0 && empty($summary['saved_items']))
            <!-- Empty Cart State -->
            <div class="bg-white rounded-3xl p-10 sm:p-16 text-center border border-slate-200 shadow-sm max-w-2xl mx-auto my-8">
                <div class="w-24 h-24 mx-auto mb-6 rounded-3xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-500">
                    <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                    </svg>
                </div>
                <h3 class="text-xl font-extrabold text-slate-900 tracking-tight">Your Cart is Empty</h3>
                <p class="text-xs sm:text-sm text-slate-500 mt-2 max-w-md mx-auto">
                    Explore millions of verified hyperlocal essentials and branded products with instant 15-30m delivery in your neighborhood!
                </p>
                <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                    <a href="{{ route('home') }}"
                       class="px-6 py-3 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-xs shadow-md shadow-indigo-500/20 active:scale-95 transition">
                        Start Shopping
                    </a>
                    <a href="{{ route('wishlist.index') }}"
                       class="px-6 py-3 rounded-2xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-extrabold text-xs transition">
                        View Wishlist
                    </a>
                </div>
            </div>
        @else
            <!-- Main Cart Grid: Left Items, Right Bill Summary -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                
                <!-- Left: Items Grouped by Seller / Store -->
                <div class="lg:col-span-8 space-y-6">

                    <!-- Free Delivery Progress Card -->
                    <div class="p-4 sm:p-5 rounded-3xl bg-gradient-to-r from-emerald-500/10 via-teal-500/10 to-indigo-500/10 border border-emerald-200/80">
                        <div class="flex items-center justify-between text-xs font-bold text-slate-800 mb-2">
                            <span class="flex items-center space-x-1.5">
                                <span class="text-base">🚚</span>
                                <span x-show="$store.cart.freeQualified" class="text-emerald-700">Congratulations! You qualify for <strong>FREE Delivery</strong></span>
                                <span x-show="!$store.cart.freeQualified">
                                    Add <strong class="text-indigo-600" x-text="$store.cart.formattedFreeRemaining || '{{ $summary['formatted_free_remaining'] }}'">{{ $summary['formatted_free_remaining'] }}</strong> more for <strong>FREE Delivery</strong>
                                </span>
                            </span>
                            <span class="text-[11px] text-slate-500" x-text="'Threshold: ' + ($store.cart.freeThreshold ? '₹' + $store.cart.freeThreshold : '{{ format_currency($summary['free_delivery_threshold']) }}')"></span>
                        </div>
                        <div class="w-full bg-slate-200 rounded-full h-2 overflow-hidden">
                            <div class="bg-gradient-to-r from-emerald-500 to-teal-500 h-2 rounded-full transition-all duration-500"
                                 :style="'width: ' + ($store.cart.progressPercent || {{ $summary['progress_percent'] }}) + '%'"></div>
                        </div>
                    </div>

                    <!-- Groups by Seller -->
                    @forelse ($summary['grouped_by_seller'] as $group)
                        <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-sm">
                            <!-- Group Header: Seller Name + Delivery Mode & ETA -->
                            <div class="p-4 sm:p-5 bg-slate-50/70 border-b border-slate-200/80 flex flex-wrap items-center justify-between gap-3">
                                <div class="flex items-center space-x-2.5">
                                    <div class="w-8 h-8 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center font-extrabold text-xs">
                                        🏪
                                    </div>
                                    <div>
                                        <div class="text-xs font-bold text-slate-900 flex items-center space-x-2">
                                            <span>Sold by: <strong>{{ $group['seller_name'] }}</strong></span>
                                        </div>
                                        <div class="text-[11px] text-slate-500">Fulfilled from local store / hub</div>
                                    </div>
                                </div>

                                <div class="flex items-center space-x-2">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-[11px] font-black {{ $group['is_quick'] ? 'bg-amber-100 text-amber-900 border border-amber-300' : 'bg-slate-100 text-slate-700 border border-slate-200' }}">
                                        {{ $group['delivery_badge'] }}
                                    </span>
                                    <span class="text-[11px] text-slate-500 font-semibold">ETA: {{ $group['estimated_eta'] }}</span>
                                </div>
                            </div>

                            <!-- Items in Seller Group -->
                            <div class="divide-y divide-slate-100">
                                @foreach ($group['items'] as $item)
                                    <div class="p-4 sm:p-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 hover:bg-slate-50/50 transition">
                                        <!-- Item Details -->
                                        <div class="flex items-start space-x-4">
                                            <a href="{{ $item['slug'] ? route('product.show', $item['slug']) : '#' }}" class="w-20 h-20 rounded-2xl bg-slate-100 border border-slate-200 overflow-hidden flex-shrink-0 flex items-center justify-center">
                                                @if (!empty($item['image_url']))
                                                    <img src="{{ $item['image_url'] }}" alt="{{ $item['title'] }}" class="w-full h-full object-cover">
                                                @else
                                                    <span class="text-xs font-bold text-slate-400">No Image</span>
                                                @endif
                                            </a>

                                            <div class="space-y-1">
                                                <a href="{{ $item['slug'] ? route('product.show', $item['slug']) : '#' }}" class="text-sm font-extrabold text-slate-900 hover:text-indigo-600 transition line-clamp-1">
                                                    {{ $item['title'] }}
                                                </a>
                                                <div class="flex flex-wrap items-center gap-2 text-[11px] text-slate-500">
                                                    <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 font-medium">Variant: {{ $item['variant_name'] }}</span>
                                                    @if ($item['is_quick_capable'])
                                                        <span class="px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 font-bold text-[10px]">⚡ Quick Capable</span>
                                                    @endif
                                                </div>

                                                <!-- Stock Warning -->
                                                @if ($item['is_out_of_stock'])
                                                    <div class="text-[11px] font-black text-rose-600 flex items-center space-x-1">
                                                        <span>● Currently Out of Stock</span>
                                                    </div>
                                                @elseif ($item['is_low_stock'])
                                                    <div class="text-[11px] font-bold text-amber-600 flex items-center space-x-1">
                                                        <span>⚠️ Only {{ $item['available_stock'] }} left in stock</span>
                                                    </div>
                                                @endif

                                                <!-- Price Change Alert -->
                                                @if ($item['price_changed'])
                                                    <div class="text-[11px] font-bold {{ $item['price_diff'] > 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                                                        Price updated: was {{ format_currency($item['snapshot_price']) }}, now {{ $item['formatted_price'] }}
                                                    </div>
                                                @endif

                                                <div class="flex items-baseline space-x-2 pt-1">
                                                    <span class="text-base font-black text-slate-900">{{ $item['formatted_price'] }}</span>
                                                    @if ($item['mrp'] > $item['price'])
                                                        <span class="text-xs text-slate-400 line-through">{{ $item['formatted_mrp'] }}</span>
                                                        <span class="text-[11px] font-black text-emerald-600">{{ $item['discount_percent'] }}% OFF</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Quantity Stepper & Actions -->
                                        <div class="flex sm:flex-col items-center sm:items-end justify-between w-full sm:w-auto gap-3 pt-2 sm:pt-0 border-t sm:border-t-0 border-slate-100">
                                            <!-- Subtotal for line -->
                                            <div class="text-right">
                                                <div class="text-xs text-slate-400">Total</div>
                                                <div class="text-sm font-black text-slate-900">{{ $item['formatted_subtotal'] }}</div>
                                            </div>

                                            <!-- Stepper -->
                                            <div class="inline-flex items-center rounded-2xl bg-slate-100 p-1 border border-slate-200">
                                                <!-- Decrement Form -->
                                                <form action="{{ route('cart.update', $item['id']) }}" method="POST">
                                                    @csrf
                                                    <input type="hidden" name="quantity" value="{{ $item['quantity'] - 1 }}">
                                                    <button type="submit" @click.prevent="$store.cart.updateQuantity({{ $item['id'] }}, {{ $item['quantity'] - 1 }})"
                                                            class="w-7 h-7 rounded-xl bg-white hover:bg-slate-200 font-black text-slate-800 text-xs flex items-center justify-center shadow-sm active:scale-95 transition">
                                                        -
                                                    </button>
                                                </form>

                                                <span class="w-8 text-center text-xs font-black text-slate-900">{{ $item['quantity'] }}</span>

                                                <!-- Increment Form -->
                                                <form action="{{ route('cart.update', $item['id']) }}" method="POST">
                                                    @csrf
                                                    <input type="hidden" name="quantity" value="{{ $item['quantity'] + 1 }}">
                                                    <button type="submit" @click.prevent="$store.cart.updateQuantity({{ $item['id'] }}, {{ $item['quantity'] + 1 }})"
                                                            class="w-7 h-7 rounded-xl bg-white hover:bg-slate-200 font-black text-slate-800 text-xs flex items-center justify-center shadow-sm active:scale-95 transition">
                                                        +
                                                    </button>
                                                </form>
                                            </div>

                                            <!-- Save for Later & Remove -->
                                            <div class="flex items-center space-x-3 text-xs">
                                                <form action="{{ route('cart.save-for-later', $item['id']) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" @click.prevent="$store.cart.saveForLater({{ $item['id'] }})"
                                                            class="text-indigo-600 hover:text-indigo-800 font-semibold hover:underline transition">
                                                        Save for Later
                                                    </button>
                                                </form>

                                                <span class="text-slate-300">|</span>

                                                <form action="{{ route('cart.remove', $item['id']) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" @click.prevent="$store.cart.removeItem({{ $item['id'] }})"
                                                            class="text-rose-500 hover:text-rose-700 font-semibold hover:underline transition">
                                                        Remove
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <!-- Group Subtotal Footer -->
                            <div class="p-3.5 bg-slate-50 border-t border-slate-100 flex items-center justify-between text-xs text-slate-600 font-medium">
                                <span>Subtotal from this seller ({{ count($group['items']) }} item{{ count($group['items']) > 1 ? 's' : '' }}):</span>
                                <span class="font-extrabold text-slate-900">{{ $group['formatted_subtotal'] }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="p-8 bg-white rounded-3xl border border-slate-200 text-center text-slate-500 text-sm">
                            No active items in cart.
                        </div>
                    @endforelse

                    <!-- Saved for Later Section -->
                    @if (!empty($summary['saved_items']))
                        <div class="mt-10 pt-6 border-t border-slate-200">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <h2 class="text-lg font-black text-slate-900 tracking-tight">Saved for Later</h2>
                                    <p class="text-xs text-slate-500 font-medium">Items you saved to buy later ({{ count($summary['saved_items']) }})</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                @foreach ($summary['saved_items'] as $saved)
                                    <div class="bg-white p-4 rounded-3xl border border-slate-200 flex items-start space-x-3 shadow-sm hover:shadow-md transition">
                                        <div class="w-16 h-16 rounded-2xl bg-slate-100 border border-slate-200 overflow-hidden flex-shrink-0 flex items-center justify-center">
                                            @if (!empty($saved['image_url']))
                                                <img src="{{ $saved['image_url'] }}" alt="{{ $saved['title'] }}" class="w-full h-full object-cover">
                                            @else
                                                <span class="text-[10px] text-slate-400">No Image</span>
                                            @endif
                                        </div>
                                        <div class="flex-1 min-w-0 space-y-1">
                                            <a href="{{ $saved['slug'] ? route('product.show', $saved['slug']) : '#' }}" class="text-xs font-bold text-slate-900 hover:text-indigo-600 line-clamp-1">
                                                {{ $saved['title'] }}
                                            </a>
                                            <div class="text-[10px] text-slate-500">{{ $saved['variant_name'] }} • {{ $saved['seller_name'] }}</div>
                                            <div class="text-xs font-black text-slate-900">{{ $saved['formatted_price'] }}</div>

                                            <div class="pt-2 flex items-center space-x-2 text-[11px]">
                                                <form action="{{ route('cart.move-to-cart', $saved['id']) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" @click.prevent="$store.cart.moveToCart({{ $saved['id'] }})"
                                                            class="px-2.5 py-1 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-extrabold transition">
                                                        Move to Cart
                                                    </button>
                                                </form>

                                                <form action="{{ route('cart.remove', $saved['id']) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" @click.prevent="$store.cart.removeItem({{ $saved['id'] }})"
                                                            class="text-rose-500 hover:text-rose-700 font-semibold transition">
                                                        Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                </div>

                <!-- Right: Order Summary & Coupon Card -->
                <div class="lg:col-span-4 space-y-6 lg:sticky lg:top-24">
                    
                    <!-- Coupon Card -->
                    <div class="bg-white rounded-3xl p-5 border border-slate-200 shadow-sm space-y-4">
                        <div class="flex items-center space-x-2 text-xs font-extrabold text-slate-900 uppercase tracking-wider">
                            <span>🏷️</span>
                            <span>Apply Coupon / Voucher</span>
                        </div>

                        <!-- Active coupon applied pill -->
                        @if ($summary['coupon_code'] && empty($summary['coupon_error']))
                            <div class="p-3 rounded-2xl bg-emerald-50 border border-emerald-200 flex items-center justify-between">
                                <div class="space-y-0.5">
                                    <div class="flex items-center space-x-1.5">
                                        <span class="px-2 py-0.5 rounded bg-emerald-600 text-white font-black text-[11px] tracking-wide">{{ $summary['coupon_code'] }}</span>
                                        <span class="text-xs font-bold text-emerald-800">Applied</span>
                                    </div>
                                    <div class="text-[11px] text-emerald-700 font-semibold">You saved {{ $summary['formatted_coupon_discount'] }}!</div>
                                </div>
                                <form action="{{ route('cart.coupon.remove') }}" method="POST">
                                    @csrf
                                    <button type="submit" @click.prevent="handleRemoveCoupon()"
                                            class="p-1.5 text-rose-500 hover:text-rose-700 hover:bg-rose-100 rounded-xl transition text-xs font-bold">
                                        Remove
                                    </button>
                                </form>
                            </div>
                        @else
                            <!-- Coupon Input Form -->
                            <form action="{{ route('cart.coupon.apply') }}" method="POST" class="space-y-2">
                                @csrf
                                <div class="flex items-center space-x-2">
                                    <input type="text" name="coupon_code" x-model="couponInput"
                                           placeholder="Enter coupon code"
                                           class="flex-1 uppercase text-xs font-extrabold tracking-wider px-3.5 py-2.5 rounded-2xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                           value="{{ $summary['coupon_code'] ?? '' }}">
                                    <button type="submit" @click.prevent="handleApplyCoupon()"
                                            :disabled="couponLoading"
                                            class="px-4 py-2.5 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-xs shadow-md shadow-indigo-500/20 active:scale-95 transition disabled:opacity-50">
                                        <span x-show="!couponLoading">Apply</span>
                                        <span x-show="couponLoading" class="animate-pulse">...</span>
                                    </button>
                                </div>
                                <div x-show="couponMessage" x-text="couponMessage"
                                     :class="couponSuccess ? 'text-emerald-600' : 'text-rose-500'"
                                     class="text-[11px] font-semibold mt-1">
                                    {{ $summary['coupon_error'] ?? '' }}
                                </div>
                            </form>
                        @endif
                    </div>

                    <!-- Order Summary Card -->
                    <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm space-y-5">
                        <h3 class="text-base font-extrabold text-slate-900 border-b border-slate-100 pb-3">Bill Details</h3>

                        <div class="space-y-2.5 text-xs text-slate-600">
                            <!-- Total MRP -->
                            <div class="flex items-center justify-between">
                                <span>Total MRP</span>
                                <span class="font-bold text-slate-800" x-text="$store.cart.formattedTotalMrp || '{{ $summary['formatted_total_mrp'] }}'">{{ $summary['formatted_total_mrp'] }}</span>
                            </div>

                            <!-- Product Discount -->
                            <div class="flex items-center justify-between text-emerald-600">
                                <span>Product Discount</span>
                                <span class="font-bold" x-text="'- ' + ($store.cart.formattedProductDiscount || '{{ $summary['formatted_product_discount'] }}')">- {{ $summary['formatted_product_discount'] }}</span>
                            </div>

                            <!-- Coupon Discount -->
                            <div class="flex items-center justify-between text-emerald-600" x-show="($store.cart.couponDiscount || {{ $summary['coupon_discount'] }}) > 0">
                                <span>Coupon Savings</span>
                                <span class="font-bold" x-text="'- ' + ($store.cart.formattedCouponDiscount || '{{ $summary['formatted_coupon_discount'] }}')">- {{ $summary['formatted_coupon_discount'] }}</span>
                            </div>

                            <!-- Delivery Fee -->
                            <div class="flex items-center justify-between">
                                <span class="flex items-center space-x-1">
                                    <span>Delivery Fee</span>
                                    <span class="text-[10px] text-slate-400" title="Free on orders over {{ format_currency($summary['free_delivery_threshold']) }}">ℹ️</span>
                                </span>
                                <span :class="($store.cart.freeQualified || {{ $summary['free_delivery_qualified'] ? 'true' : 'false' }}) ? 'text-emerald-600 font-extrabold' : 'font-bold text-slate-800'"
                                      x-text="($store.cart.freeQualified || {{ $summary['free_delivery_qualified'] ? 'true' : 'false' }}) ? 'FREE' : ($store.cart.formattedDeliveryFee || '{{ $summary['formatted_delivery_fee'] }}')">
                                    {{ $summary['formatted_delivery_fee'] }}
                                </span>
                            </div>

                            <!-- Taxes -->
                            <div class="flex items-center justify-between text-[11px] text-slate-400 pt-1 border-t border-slate-100">
                                <span>Estimated GST (Included)</span>
                                <span class="font-semibold">{{ $summary['formatted_estimated_tax'] }}</span>
                            </div>

                            <!-- Total Payable -->
                            <div class="flex items-center justify-between text-base font-black text-slate-900 pt-3 border-t border-slate-200">
                                <span>Total Payable</span>
                                <span class="text-indigo-600 text-lg" x-text="$store.cart.formattedTotalPayable || '{{ $summary['formatted_total_payable'] }}'">{{ $summary['formatted_total_payable'] }}</span>
                            </div>
                        </div>

                        <!-- Total Savings Badge -->
                        @if ($summary['total_savings'] > 0)
                            <div class="p-3 rounded-2xl bg-emerald-50 border border-emerald-200/80 text-emerald-800 text-xs font-bold text-center">
                                🎉 You are saving <span x-text="$store.cart.formattedTotalSavings || '{{ $summary['formatted_total_savings'] }}'">{{ $summary['formatted_total_savings'] }}</span> on this order!
                            </div>
                        @endif

                        <!-- Proceed to Checkout Button -->
                        <a href="{{ route('checkout.index') }}"
                           class="w-full py-4 px-6 rounded-2xl bg-gradient-to-r from-emerald-600 via-teal-600 to-indigo-600 hover:from-emerald-700 hover:to-indigo-700 text-white font-extrabold text-sm flex items-center justify-between shadow-lg shadow-emerald-500/25 active:scale-95 transition">
                            <span>Proceed to Checkout</span>
                            <span class="flex items-center space-x-1">
                                <span x-text="$store.cart.formattedTotalPayable || '{{ $summary['formatted_total_payable'] }}'">{{ $summary['formatted_total_payable'] }}</span>
                                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </span>
                        </a>

                        <!-- Safe & Secure Badges -->
                        <div class="pt-4 border-t border-slate-100 space-y-2 text-[11px] text-slate-500">
                            <div class="flex items-center space-x-2">
                                <svg class="w-4 h-4 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                <span>100% Safe & Secure Payments (UPI, Cards, NetBanking, COD)</span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <svg class="w-4 h-4 text-indigo-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                <span>Easy Hyperlocal Returns & Rapid Payout Guarantee</span>
                            </div>
                        </div>

                    </div>

                </div>

            </div>
        @endif

    </div>
</div>
@endsection
