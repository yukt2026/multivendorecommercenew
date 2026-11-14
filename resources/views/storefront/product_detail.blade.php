@extends('layouts.storefront')

@section('hideBottomNav', 'true')

@section('content')
@php
    $primaryMedia = $product->media->first();
    $mediaList = $product->media;
@endphp

<div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-2.5 sm:py-6 space-y-4 sm:space-y-8 pb-24 sm:pb-12 w-full max-w-full overflow-x-hidden min-w-0"
     x-data="{
        activeVariantId: {{ $defaultVariant ? $defaultVariant->id : ($product->variants->first()?->id ?? 0) }},
        variants: {{ json_encode($variantsData) }},
        selectedMediaUrl: '{{ $product->primaryImageUrl() ?? '' }}',
        selectedMediaType: '{{ $primaryMedia?->type?->value ?? 'image' }}',
        pincode: '{{ $location['pincode'] ?? '560001' }}',
        pincodeResult: null,
        pincodeLoading: false,
        reviewModalOpen: false,
        reviewRating: 5,
        reviewTitle: '',
        reviewBody: '',
        reviewSubmitting: false,
        reviewMessage: '',
        questionText: '',
        questionSubmitting: false,
        questionMessage: '',

        get currentVariant() {
            return this.variants.find(v => Number(v.id) === Number(this.activeVariantId)) || this.variants[0] || {};
        },

        get inCartQty() {
            return $store.cart.getItemQuantity(this.currentVariant.id, {{ $product->id }});
        },

        get cartItem() {
            return $store.cart.items.find(i => Number(i.variant_id) === Number(this.currentVariant.id)) ||
                   $store.cart.items.find(i => Number(i.product_id) === Number({{ $product->id }}));
        },

        addToCart() {
            $store.cart.addItem(this.currentVariant.id, {{ $product->id }}, 1);
        },

        increment() {
            if (this.cartItem) {
                $store.cart.updateQuantity(this.cartItem.id, this.inCartQty + 1);
            } else {
                this.addToCart();
            }
        },

        decrement() {
            if (this.cartItem) {
                $store.cart.updateQuantity(this.cartItem.id, this.inCartQty - 1);
            }
        },

        selectVariant(vId) {
            this.activeVariantId = vId;
            const found = this.variants.find(v => Number(v.id) === Number(vId));
            if (found && found.image_url) {
                this.selectedMediaUrl = found.image_url;
                this.selectedMediaType = 'image';
            }
        },

        async checkPincode() {
            if (!this.pincode || this.pincode.length < 5) return;
            this.pincodeLoading = true;
            try {
                const csrf = document.querySelector('meta[name=\'csrf-token\']')?.getAttribute('content');
                const targetUrl = window.apiUrl ? window.apiUrl('/api/storefront/pincode/check') : '/api/storefront/pincode/check';
                const res = await fetch(targetUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify({
                        pincode: this.pincode,
                        product_id: {{ $product->id }},
                        variant_id: this.activeVariantId
                    })
                });
                this.pincodeResult = await res.json();
            } catch (e) {
                console.error(e);
            } finally {
                this.pincodeLoading = false;
            }
        },

        async submitReview() {
            if (!this.reviewTitle || !this.reviewBody) return;
            this.reviewSubmitting = true;
            try {
                const csrf = document.querySelector('meta[name=\'csrf-token\']')?.getAttribute('content');
                const res = await fetch('{{ route('product.review.submit', $product->slug) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify({
                        rating: this.reviewRating,
                        title: this.reviewTitle,
                        body: this.reviewBody
                    })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    this.reviewMessage = data.message;
                    setTimeout(() => {
                        this.reviewModalOpen = false;
                        window.location.reload();
                    }, 1500);
                } else {
                    this.reviewMessage = data.message || 'Failed to submit review.';
                }
            } catch (e) {
                this.reviewMessage = 'Error submitting review. Please sign in first.';
            } finally {
                this.reviewSubmitting = false;
            }
        },

        async submitQuestion() {
            if (!this.questionText) return;
            this.questionSubmitting = true;
            try {
                const csrf = document.querySelector('meta[name=\'csrf-token\']')?.getAttribute('content');
                const res = await fetch('{{ route('product.question.submit', $product->slug) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify({ question: this.questionText })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    this.questionMessage = data.message;
                    this.questionText = '';
                } else {
                    this.questionMessage = data.message || 'Failed to post question.';
                }
            } catch (e) {
                this.questionMessage = 'Error posting question. Please sign in first.';
            } finally {
                this.questionSubmitting = false;
            }
        }
     }">

    <!-- Breadcrumb Navigation -->
    <nav class="flex items-center space-x-2 text-xs text-slate-500 overflow-x-auto no-scrollbar scrollbar-none [&::-webkit-scrollbar]:hidden py-1"
         style="-ms-overflow-style: none; scrollbar-width: none;">
        @foreach($breadcrumbs as $crumb)
            @if(!$loop->last)
                <a href="{{ $crumb['url'] }}" class="hover:text-indigo-600 font-medium whitespace-nowrap">{{ $crumb['name'] }}</a>
                <span class="text-slate-300">/</span>
            @else
                <span class="font-bold text-slate-800 whitespace-nowrap truncate max-w-xs">{{ $crumb['name'] }}</span>
            @endif
        @endforeach
    </nav>

    <!-- Main Product Showcase Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 sm:gap-8 items-start">

        <!-- Left Column: Image / Video Gallery (5 Cols) -->
        <div class="lg:col-span-5 space-y-3 sm:space-y-4 lg:sticky lg:top-24">
            <div class="p-2.5 sm:p-4 bg-white rounded-2xl sm:rounded-3xl border border-slate-200/80 shadow-xs sm:shadow-sm relative group overflow-hidden">
                <!-- Main Zoom Viewport -->
                <div class="aspect-square rounded-xl sm:rounded-2xl bg-slate-50/80 flex items-center justify-center overflow-hidden relative">
                    <template x-if="selectedMediaType === 'video'">
                        <video :src="selectedMediaUrl" controls class="w-full h-full object-contain"></video>
                    </template>
                    <template x-if="selectedMediaType !== 'video'">
                        <img :src="selectedMediaUrl"
                             alt="{{ $product->title }}"
                             class="w-full h-full object-contain p-2 sm:p-4 group-hover:scale-105 transition-transform duration-300">
                    </template>

                    <!-- Quick Commerce Badge -->
                    @if($product->is_quick_capable)
                        <span class="absolute top-2.5 left-2.5 inline-flex items-center space-x-1 px-2 py-0.5 sm:px-2.5 sm:py-1 rounded-lg text-[10px] sm:text-xs font-black bg-amber-400 text-slate-950 shadow-xs border border-amber-300">
                            <span>⚡ 15 MIN DELIVERY</span>
                        </span>
                    @endif

                    <!-- Wishlist Floating Toggle Button -->
                    <button type="button"
                            @click="$store.wishlist.toggle({{ $product->id }})"
                            class="absolute top-2.5 right-2.5 w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-white/95 backdrop-blur-md shadow-xs border border-slate-200 flex items-center justify-center text-slate-400 hover:text-rose-500 hover:scale-110 active:scale-95 transition cursor-pointer"
                            aria-label="Toggle wishlist">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 transition-colors"
                             :class="$store.wishlist.has({{ $product->id }}) ? 'text-rose-500 fill-rose-500' : 'text-slate-400 fill-none'"
                             stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                        </svg>
                    </button>
                </div>

                <!-- Thumbnails Row -->
                @if($mediaList->count() > 1)
                    <div class="flex items-center space-x-2 sm:space-x-3 mt-2.5 sm:mt-4 overflow-x-auto pb-1 no-scrollbar scrollbar-none [&::-webkit-scrollbar]:hidden"
                         style="-ms-overflow-style: none; scrollbar-width: none;">
                        @foreach($mediaList as $m)
                            @php
                                $mUrl = (str_starts_with($m->path, 'http://') || str_starts_with($m->path, 'https://')) ? $m->path : asset('storage/' . $m->path);
                                $isVideo = ($m->type?->value ?? '') === 'video' || str_ends_with($m->path, '.mp4');
                            @endphp
                            <button type="button"
                                    @click="selectedMediaUrl = '{{ $mUrl }}'; selectedMediaType = '{{ $isVideo ? 'video' : 'image' }}'"
                                    :class="selectedMediaUrl === '{{ $mUrl }}' ? 'ring-2 ring-indigo-600 border-indigo-600' : 'opacity-70 hover:opacity-100 border-slate-200'"
                                    class="w-13 h-13 sm:w-16 sm:h-16 rounded-xl border bg-slate-50 flex items-center justify-center overflow-hidden shrink-0 transition cursor-pointer">
                                @if($isVideo)
                                    <span class="text-[10px] sm:text-xs font-bold text-indigo-600">▶ Video</span>
                                @else
                                    <img src="{{ $mUrl }}" alt="{{ $product->title }} preview" loading="lazy" decoding="async" class="w-full h-full object-contain p-1">
                                @endif
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Trust Highlights (Desktop View: below Gallery) -->
            <div class="hidden lg:grid grid-cols-3 gap-2 text-center text-slate-600 text-xs font-medium">
                <div class="p-2 sm:p-2.5 rounded-xl sm:rounded-2xl bg-white border border-slate-200/80 shadow-2xs">
                    <span class="text-base sm:text-lg block">🛡️</span>
                    <span class="text-[10px] sm:text-[11px] font-bold text-slate-800">100% Genuine</span>
                </div>
                <div class="p-2 sm:p-2.5 rounded-xl sm:rounded-2xl bg-white border border-slate-200/80 shadow-2xs">
                    <span class="text-base sm:text-lg block">🔄</span>
                    <span class="text-[10px] sm:text-[11px] font-bold text-slate-800">{{ $product->return_window_days }}-Day Return</span>
                </div>
                <div class="p-2 sm:p-2.5 rounded-xl sm:rounded-2xl bg-white border border-slate-200/80 shadow-2xs">
                    <span class="text-base sm:text-lg block">💵</span>
                    <span class="text-[10px] sm:text-[11px] font-bold text-slate-800">{{ $product->is_cod_allowed ? 'COD Available' : 'Prepaid Only' }}</span>
                </div>
            </div>
        </div>

        <!-- Right Column: Product Info, Variants, Pincode & Buy Box (7 Cols) -->
        <div class="lg:col-span-7 space-y-4 sm:space-y-6">

            <!-- Title & Rating Header -->
            <div class="space-y-1.5 sm:space-y-2">
                @if($product->brand)
                    <div class="flex items-center space-x-1.5">
                        <a href="{{ route('brand.show', $product->brand->slug) }}" class="text-xs font-bold text-indigo-600 hover:underline uppercase tracking-wider">
                            {{ $product->brand->name }}
                        </a>
                        @if($product->brand->is_verified)
                            <span class="text-blue-500 text-xs" title="Verified Brand">✓</span>
                        @endif
                    </div>
                @endif

                <h1 class="text-lg sm:text-2xl lg:text-3xl font-black text-slate-900 tracking-tight leading-snug">
                    {{ $product->title }}
                </h1>

                <!-- Rating & Reviews Badge -->
                <div class="flex flex-wrap items-center gap-2 sm:gap-3 text-xs">
                    <div class="inline-flex items-center space-x-1 px-2 py-0.5 rounded-md sm:rounded-lg bg-emerald-600 text-white font-bold text-[11px] sm:text-xs">
                        <span>★ {{ number_format($avgRating, 1) }}</span>
                    </div>
                    <a href="#reviews" class="text-slate-500 hover:text-indigo-600 font-medium text-[11px] sm:text-xs">
                        {{ $totalReviews }} Ratings &amp; Reviews
                    </a>
                    <span class="text-slate-300 hidden sm:inline">&bull;</span>
                    <span class="text-slate-400 text-[10px] sm:text-xs">HSN: {{ $product->hsn_code ?? '330499' }}</span>
                </div>
            </div>

            <!-- Price Display (Reactive to Variant Selection) -->
            <div class="p-4 sm:p-5 rounded-2xl sm:rounded-3xl bg-slate-900 text-white shadow-md space-y-1 sm:space-y-1.5">
                <div class="flex flex-wrap items-baseline gap-2 sm:gap-3">
                    <span class="text-2xl sm:text-4xl font-black tracking-tight text-white" x-text="currentVariant.formatted_selling_price || '{{ format_currency($product->effective_price) }}'"></span>
                    <template x-if="currentVariant.mrp && Number(currentVariant.mrp) > Number(currentVariant.selling_price)">
                        <span class="text-xs sm:text-sm text-slate-400 line-through font-medium" x-text="currentVariant.formatted_mrp"></span>
                    </template>
                    <template x-if="currentVariant.discount_percent > 0">
                        <span class="px-2 py-0.5 rounded-md text-[10px] sm:text-xs font-black bg-amber-400 text-slate-950 shadow-2xs" x-text="currentVariant.discount_percent + '% OFF'"></span>
                    </template>
                </div>
                <p class="text-[10px] sm:text-[11px] text-slate-400">Inclusive of all taxes &bull; GST {{ (int) $product->tax_percent }}% included</p>
            </div>

            <!-- Variant Selector -->
            @if($product->variants->count() > 1)
                <div class="p-4 sm:p-5 rounded-2xl sm:rounded-3xl bg-white border border-slate-200/80 shadow-xs space-y-2.5 sm:space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-900">
                            Select Option: <span class="text-indigo-600 font-extrabold" x-text="currentVariant.name"></span>
                        </h3>
                        <span class="text-[10px] font-semibold text-slate-400">{{ $product->variants->count() }} options</span>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <template x-for="v in variants" :key="v.id">
                            <button type="button"
                                    @click="selectVariant(v.id)"
                                    :class="Number(activeVariantId) === Number(v.id) ? 'border-indigo-600 bg-indigo-50 text-indigo-700 ring-2 ring-indigo-600/30' : 'border-slate-200 hover:border-slate-300 text-slate-700 bg-white'"
                                    class="px-3.5 py-1.5 sm:px-4 sm:py-2 rounded-xl sm:rounded-2xl border text-xs font-bold transition flex items-center space-x-1.5 cursor-pointer">
                                <span x-text="v.name"></span>
                                <span class="text-[10px] sm:text-[11px] font-medium text-slate-400" x-text="v.formatted_selling_price"></span>
                            </button>
                        </template>
                    </div>

                    <div class="text-[10px] sm:text-[11px] text-slate-500 pt-1 flex items-center justify-between border-t border-slate-100">
                        <span>SKU: <strong class="text-slate-800 font-mono" x-text="currentVariant.sku"></strong></span>
                        <span :class="currentVariant.in_stock ? 'text-emerald-600 font-bold' : 'text-rose-500 font-bold'"
                              x-text="currentVariant.in_stock ? '✓ In Stock (' + currentVariant.stock + ' ready)' : 'Out of Stock'"></span>
                    </div>
                </div>
            @endif

            <!-- Add to Cart & Primary Action Stepper -->
            <div class="p-3.5 sm:p-5 rounded-2xl sm:rounded-3xl bg-white border border-slate-200/80 shadow-xs flex items-center gap-2.5 sm:gap-4">
                <div class="flex-1 min-w-0">
                    <template x-if="inCartQty === 0">
                        <button type="button"
                                @click="addToCart()"
                                :disabled="!currentVariant.in_stock"
                                class="w-full py-3 px-3 sm:py-3.5 sm:px-6 rounded-xl sm:rounded-2xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white font-extrabold text-xs sm:text-sm shadow-md active:scale-95 transition flex items-center justify-center space-x-1.5 cursor-pointer">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                            <span x-text="currentVariant.in_stock ? 'ADD TO CART' : 'OUT OF STOCK'">ADD TO CART</span>
                        </button>
                    </template>

                    <template x-if="inCartQty > 0">
                        <div class="flex items-center justify-between p-1.5 sm:p-2 rounded-xl sm:rounded-2xl bg-indigo-50 border border-indigo-200">
                            <span class="text-[11px] sm:text-xs font-bold text-indigo-700 pl-2">In Cart:</span>
                            <div class="inline-flex items-center rounded-lg sm:rounded-xl bg-indigo-600 text-white font-bold text-xs sm:text-sm shadow-2xs overflow-hidden">
                                <button type="button" @click="decrement()" class="px-3 py-1.5 sm:px-4 sm:py-2 hover:bg-indigo-700 active:bg-indigo-800 font-black">-</button>
                                <span class="px-2.5 py-1.5 sm:px-4 sm:py-2 font-black min-w-[24px] sm:min-w-[32px] text-center" x-text="inCartQty"></span>
                                <button type="button" @click="increment()" class="px-3 py-1.5 sm:px-4 sm:py-2 hover:bg-indigo-700 active:bg-indigo-800 font-black">+</button>
                            </div>
                        </div>
                    </template>
                </div>

                <button type="button"
                        @click="if (inCartQty === 0) addToCart(); $store.cart.open = true"
                        :disabled="!currentVariant.in_stock"
                        class="flex-1 sm:flex-initial py-3 px-3 sm:py-3.5 sm:px-6 rounded-xl sm:rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 text-white font-extrabold text-xs sm:text-sm shadow-md active:scale-95 transition whitespace-nowrap cursor-pointer text-center">
                    BUY NOW
                </button>
            </div>

            <!-- Delivery & Pincode Checker (Quick Commerce vs Standard Fulfilment) -->
            <div class="p-4 sm:p-5 rounded-2xl sm:rounded-3xl bg-white border border-slate-200/80 shadow-xs space-y-2.5 sm:space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-900 flex items-center space-x-1.5">
                        <span>🚚 Delivery & Availability Check</span>
                    </h3>
                    <span class="text-[11px] text-indigo-600 font-semibold" x-text="$store.location.current.city || 'Select City'"></span>
                </div>

                <div class="flex items-center space-x-2">
                    <input type="text"
                           x-model="pincode"
                           maxlength="6"
                           placeholder="Enter 6-digit Pincode"
                           class="flex-1 min-w-0 text-xs py-2.5 px-3 rounded-xl border border-slate-200 focus:ring-indigo-500 font-medium">
                    <button type="button"
                            @click="checkPincode()"
                            :disabled="pincodeLoading"
                            class="px-4 sm:px-5 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs shadow-sm transition shrink-0 cursor-pointer">
                        <span x-text="pincodeLoading ? 'Checking...' : 'Check'">Check</span>
                    </button>
                </div>

                <!-- Result Notice -->
                <div x-show="pincodeResult" class="p-3 rounded-xl sm:rounded-2xl bg-slate-50 border border-slate-200 text-xs space-y-1">
                    <div class="font-extrabold text-slate-900 flex items-center space-x-1.5" x-text="pincodeResult ? pincodeResult.estimated_delivery : ''"></div>
                    <div class="text-[11px] text-slate-500" x-text="pincodeResult ? pincodeResult.message : ''"></div>
                </div>
            </div>

            <!-- Trust Highlights (Mobile View: after Delivery Box) -->
            <div class="lg:hidden grid grid-cols-3 gap-1.5 text-center text-slate-600 text-xs font-medium">
                <div class="p-2 rounded-xl bg-white border border-slate-200/80 shadow-2xs">
                    <span class="text-base block">🛡️</span>
                    <span class="text-[10px] font-bold text-slate-800">100% Genuine</span>
                </div>
                <div class="p-2 rounded-xl bg-white border border-slate-200/80 shadow-2xs">
                    <span class="text-base block">🔄</span>
                    <span class="text-[10px] font-bold text-slate-800">{{ $product->return_window_days }}-Day Return</span>
                </div>
                <div class="p-2 rounded-xl bg-white border border-slate-200/80 shadow-2xs">
                    <span class="text-base block">💵</span>
                    <span class="text-[10px] font-bold text-slate-800">{{ $product->is_cod_allowed ? 'COD Available' : 'Prepaid Only' }}</span>
                </div>
            </div>

            <!-- Seller / Store Info Card -->
            @if($product->seller)
                @php
                    $sellerStore = $product->seller->stores->first();
                @endphp
                <div class="p-3.5 sm:p-5 rounded-2xl sm:rounded-3xl bg-white border border-slate-200/80 shadow-xs flex items-center justify-between gap-3">
                    <div class="flex items-center space-x-2.5 sm:space-x-3 min-w-0">
                        <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl sm:rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-black text-base sm:text-lg shrink-0">
                            🏪
                        </div>
                        <div class="min-w-0">
                            <span class="text-[9px] sm:text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Sold & Fulfilled By</span>
                            <h4 class="text-xs sm:text-sm font-extrabold text-slate-900 truncate">
                                {{ $sellerStore?->name ?? $product->seller->business_name }}
                            </h4>
                            <div class="flex items-center space-x-1.5 text-[10px] sm:text-[11px] text-slate-500 truncate">
                                <span class="text-amber-600 font-bold">★ {{ number_format($sellerStore?->rating_avg ?? 4.8, 1) }}</span>
                                <span>&bull;</span>
                                <span class="truncate">{{ $sellerStore?->location?->city ?? 'Verified Merchant' }}</span>
                            </div>
                        </div>
                    </div>

                    @if($sellerStore)
                        <a href="{{ route('store.show', $sellerStore->slug) }}" class="px-3 py-1.5 sm:px-4 sm:py-2 rounded-xl bg-indigo-50 hover:bg-indigo-600 text-indigo-700 hover:text-white font-bold text-[11px] sm:text-xs transition shrink-0 whitespace-nowrap">
                            Visit Store &rarr;
                        </a>
                    @endif
                </div>
            @endif

            <!-- Multivendor / Other Sellers Buy-Box -->
            @if($otherSellers->isNotEmpty())
                <div class="p-4 sm:p-5 rounded-2xl sm:rounded-3xl bg-white border border-slate-200/80 shadow-xs space-y-3">
                    <div class="flex items-center justify-between">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-900">
                            Other Sellers Offering This Product
                        </h4>
                        <span class="text-[11px] text-slate-400">{{ $otherSellers->count() }} offers</span>
                    </div>

                    <div class="divide-y divide-slate-100">
                        @foreach($otherSellers as $os)
                            <div class="py-2.5 flex items-center justify-between gap-2 text-xs">
                                <div>
                                    <span class="font-bold text-slate-900">{{ $os->seller?->business_name ?? 'Marketplace Vendor' }}</span>
                                    <div class="text-[10px] text-slate-400">Standard Delivery in 2-3 days</div>
                                </div>
                                <div class="text-right">
                                    <span class="font-black text-slate-900">{{ format_currency($os->effective_price) }}</span>
                                    <div>
                                        <a href="{{ route('product.show', $os->slug) }}" class="text-[10px] font-bold text-indigo-600 hover:underline">View Offer</a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>

    </div>

    <!-- Product Description & Specifications Section -->
    <div class="p-4 sm:p-8 rounded-2xl sm:rounded-3xl bg-white border border-slate-200/80 shadow-xs space-y-4 sm:space-y-6">
        <div>
            <h2 class="text-base sm:text-lg font-black text-slate-900">Product Overview</h2>
            <div class="mt-2.5 text-xs sm:text-sm text-slate-600 leading-relaxed prose max-w-none">
                {!! nl2br(e($product->description ?: $product->short_desc)) !!}
            </div>
        </div>

        <!-- Specifications Table -->
        @if($specGroups->isNotEmpty())
            <div class="pt-4 sm:pt-6 border-t border-slate-100 space-y-3 sm:space-y-4">
                <h3 class="text-xs sm:text-sm font-black text-slate-900 uppercase tracking-wider">Specifications</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 sm:gap-4">
                    @foreach($specGroups as $group => $specs)
                        <div class="rounded-xl sm:rounded-2xl border border-slate-200/80 overflow-hidden text-xs">
                            <div class="bg-slate-50 px-3.5 py-2 font-bold text-slate-800 border-b border-slate-200/80 text-[11px] sm:text-xs">
                                {{ $group ?: 'General' }}
                            </div>
                            <div class="p-3 sm:p-4 divide-y divide-slate-100">
                                @foreach($specs as $spec)
                                    <div class="py-1.5 flex justify-between gap-3 text-[11px] sm:text-xs">
                                        <span class="text-slate-500 font-medium">{{ $spec->key }}</span>
                                        <span class="text-slate-900 font-semibold text-right">{{ $spec->value }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <!-- Ratings & Customer Reviews Section -->
    <section id="reviews" class="p-4 sm:p-8 rounded-2xl sm:rounded-3xl bg-white border border-slate-200/80 shadow-xs space-y-4 sm:space-y-6">
        <div class="flex items-center justify-between gap-3 pb-3 sm:pb-4 border-b border-slate-100">
            <div>
                <h2 class="text-base sm:text-lg font-black text-slate-900">Ratings &amp; Reviews</h2>
                <p class="text-[11px] sm:text-xs text-slate-500">Verified purchase reviews from genuine buyers</p>
            </div>

            <button type="button"
                    @click="reviewModalOpen = true"
                    class="px-3.5 py-1.5 sm:px-4 sm:py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-sm transition shrink-0 cursor-pointer">
                Write Review
            </button>
        </div>

        <!-- Ratings Scorecard & Breakdown Progress Bars -->
        <div class="grid grid-cols-1 md:grid-cols-12 gap-4 sm:gap-6 items-center">
            <div class="md:col-span-4 flex items-center sm:block space-x-4 sm:space-x-0 space-y-0 sm:space-y-2">
                <div class="flex items-baseline space-x-1.5">
                    <span class="text-4xl sm:text-5xl font-black text-slate-900">{{ number_format($avgRating, 1) }}</span>
                    <span class="text-slate-400 text-base sm:text-lg font-bold">/ 5</span>
                </div>
                <div>
                    <div class="flex items-center space-x-0.5 text-amber-400 text-sm sm:text-base">
                        @for($i = 1; $i <= 5; $i++)
                            <span>{{ $i <= round($avgRating) ? '★' : '☆' }}</span>
                        @endfor
                    </div>
                    <p class="text-[10px] sm:text-xs text-slate-500 mt-0.5">{{ $totalReviews }} total ratings across India</p>
                </div>
            </div>

            <!-- Breakdown Bars -->
            <div class="md:col-span-8 space-y-1.5 text-xs">
                @for($star = 5; $star >= 1; $star--)
                    @php
                        $count = $ratingBreakdown[$star] ?? 0;
                        $percent = $totalReviews > 0 ? (int) round(($count / $totalReviews) * 100) : 0;
                    @endphp
                    <div class="flex items-center space-x-2.5 sm:space-x-3 text-[11px] sm:text-xs">
                        <span class="w-7 sm:w-8 text-right font-bold text-slate-700">{{ $star }} ★</span>
                        <div class="flex-1 bg-slate-100 rounded-full h-2 overflow-hidden">
                            <div class="bg-amber-400 h-2 rounded-full" style="width: {{ $percent }}%"></div>
                        </div>
                        <span class="w-7 sm:w-8 text-slate-400 text-[10px] sm:text-[11px]">{{ $count }}</span>
                    </div>
                @endfor
            </div>
        </div>

        <!-- Reviews List -->
        <div class="space-y-3 sm:space-y-4 pt-3 sm:pt-4 divide-y divide-slate-100">
            @forelse($product->reviews as $review)
                <div class="pt-3 sm:pt-4 space-y-1 sm:space-y-1.5">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <span class="px-2 py-0.5 rounded-md bg-emerald-600 text-white text-[10px] font-black">
                                ★ {{ $review->rating }}
                            </span>
                            <h4 class="text-xs font-bold text-slate-900">{{ $review->title }}</h4>
                        </div>
                        <span class="text-[10px] sm:text-[11px] text-slate-400">{{ $review->created_at->format('M d, Y') }}</span>
                    </div>

                    <p class="text-[11px] sm:text-xs text-slate-600 leading-relaxed">{{ $review->body }}</p>

                    <div class="flex items-center space-x-2 text-[10px] sm:text-[11px] text-slate-500 pt-0.5">
                        <span>{{ $review->user?->name ?? 'Verified Customer' }}</span>
                        @if($review->is_verified_purchase)
                            <span class="text-emerald-600 font-bold flex items-center space-x-0.5">
                                <span>✓</span>
                                <span>Verified Purchase</span>
                            </span>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-xs text-slate-500 py-4 text-center">No reviews yet for this product. Be the first to write one!</p>
            @endforelse
        </div>
    </section>

    <!-- Q&A Section -->
    <section class="p-4 sm:p-8 rounded-2xl sm:rounded-3xl bg-white border border-slate-200/80 shadow-xs space-y-4 sm:space-y-6">
        <div class="pb-3 sm:pb-4 border-b border-slate-100">
            <h2 class="text-base sm:text-lg font-black text-slate-900">Customer Questions &amp; Answers</h2>
            <p class="text-[11px] sm:text-xs text-slate-500">Have questions about this item? Ask our seller and verified buyers</p>
        </div>

        <!-- Ask a Question Input -->
        <div class="p-3.5 sm:p-4 rounded-xl sm:rounded-2xl bg-slate-50 border border-slate-200 space-y-2">
            <div class="flex flex-col sm:flex-row gap-2">
                <input type="text"
                       x-model="questionText"
                       placeholder="Have a question? Ask it here..."
                       class="flex-1 min-w-0 text-xs p-2.5 rounded-xl border border-slate-200 bg-white focus:ring-indigo-500">
                <button type="button"
                        @click="submitQuestion()"
                        :disabled="questionSubmitting || !questionText"
                        class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 disabled:opacity-50 text-white font-bold text-xs shadow-sm transition whitespace-nowrap cursor-pointer">
                    <span x-text="questionSubmitting ? 'Posting...' : 'Ask Question'">Ask Question</span>
                </button>
            </div>
            <div x-show="questionMessage" x-text="questionMessage" class="text-xs font-semibold text-emerald-600"></div>
        </div>

        <!-- Questions List -->
        <div class="space-y-3 sm:space-y-4 divide-y divide-slate-100">
            @forelse($product->questions as $q)
                <div class="pt-3 sm:pt-4 space-y-2 text-xs">
                    <div class="font-bold text-slate-900 flex items-start space-x-2">
                        <span class="text-indigo-600 font-black">Q:</span>
                        <span>{{ $q->question }}</span>
                    </div>

                    @if($q->answers && $q->answers->isNotEmpty())
                        @foreach($q->answers as $ans)
                            <div class="pl-4 sm:pl-5 space-y-1 border-l-2 border-indigo-200 text-slate-700">
                                <div class="flex items-center space-x-2">
                                    <span class="text-emerald-600 font-bold">A:</span>
                                    <span>{{ $ans->answer }}</span>
                                </div>
                                <div class="text-[10px] text-slate-400">
                                    Answered by {{ $ans->user?->name ?? 'OneStall Seller' }} &bull; {{ $ans->created_at->format('M d, Y') }}
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="pl-4 sm:pl-5 text-slate-400 text-[11px] italic">No answers yet. The seller will answer shortly.</div>
                    @endif
                </div>
            @empty
                <p class="text-xs text-slate-500 text-center py-4">No questions asked yet about this product.</p>
            @endforelse
        </div>
    </section>

    <!-- Related Products -->
    @if($relatedProducts->isNotEmpty())
        <section class="space-y-3 sm:space-y-4">
            <h2 class="text-base sm:text-lg font-black text-slate-900">Related Products in this Category</h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2.5 sm:gap-4">
                @foreach($relatedProducts as $rel)
                    <x-product-card :product="$rel" />
                @endforeach
            </div>
        </section>
    @endif

    <!-- Review Submission Modal -->
    <div x-show="reviewModalOpen"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto flex items-end sm:items-center justify-center p-0 sm:p-4"
         role="dialog" aria-modal="true">
        <!-- Backdrop with Blur -->
        <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm transition-opacity"
             @click="reviewModalOpen = false"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"></div>

        <div class="relative bg-white rounded-t-3xl sm:rounded-3xl max-w-md w-full p-5 sm:p-6 shadow-2xl border border-slate-200/90 transform transition-all relative z-10 space-y-4 max-h-[92vh] overflow-y-auto"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-12 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave-end="opacity-0 translate-y-12 sm:scale-95">

            <!-- Header -->
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center space-x-2.5">
                    <span class="w-8 h-8 rounded-xl bg-amber-400 text-slate-950 flex items-center justify-center text-sm font-black shadow-xs">★</span>
                    <div>
                        <h3 class="text-sm font-black text-slate-900 leading-tight">Write a Review</h3>
                        <p class="text-[10px] text-slate-500">Share your verified shopping experience</p>
                    </div>
                </div>
                <button type="button" @click="reviewModalOpen = false" class="w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition active:scale-95 cursor-pointer">&times;</button>
            </div>

            <div class="space-y-3.5 text-xs">
                <!-- Interactive Star Selector -->
                <div class="space-y-1">
                    <label class="block text-[11px] font-bold text-slate-700 uppercase tracking-wider">Your Rating <span class="text-rose-500">*</span></label>
                    <div class="flex items-center space-x-2">
                        <template x-for="star in [1, 2, 3, 4, 5]" :key="star">
                            <button type="button"
                                    @click="reviewRating = star"
                                    class="text-2xl transition-transform hover:scale-125 focus:outline-hidden cursor-pointer"
                                    :class="star <= reviewRating ? 'text-amber-400' : 'text-slate-200'">
                                ★
                            </button>
                        </template>
                        <span class="text-xs font-bold text-slate-700 ml-2" x-text="reviewRating === 5 ? 'Excellent (5/5)' : (reviewRating === 4 ? 'Very Good (4/5)' : (reviewRating === 3 ? 'Average (3/5)' : (reviewRating === 2 ? 'Poor (2/5)' : 'Terrible (1/5)')))"></span>
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="block text-[11px] font-bold text-slate-700 uppercase tracking-wider">Review Headline <span class="text-rose-500">*</span></label>
                    <input type="text" x-model="reviewTitle" placeholder="e.g. Fresh quality, fast delivery!"
                           class="w-full text-xs p-2.5 rounded-xl border border-slate-200 bg-slate-50/50 focus:bg-white focus:border-indigo-600 focus:ring-4 focus:ring-indigo-500/10 font-semibold text-slate-800 transition">
                </div>

                <div class="space-y-1">
                    <label class="block text-[11px] font-bold text-slate-700 uppercase tracking-wider">Detailed Review <span class="text-rose-500">*</span></label>
                    <textarea x-model="reviewBody" rows="4" placeholder="What did you like or dislike about this product? Packaging, quality, taste, freshness..."
                              class="w-full text-xs p-2.5 rounded-xl border border-slate-200 bg-slate-50/50 focus:bg-white focus:border-indigo-600 focus:ring-4 focus:ring-indigo-500/10 font-medium text-slate-800 transition"></textarea>
                </div>

                <div x-show="reviewMessage" x-text="reviewMessage" class="font-bold text-indigo-600 text-xs"></div>

                <div class="pt-2 flex justify-end space-x-2 border-t border-slate-100">
                    <button type="button" @click="reviewModalOpen = false" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 font-bold text-xs hover:bg-slate-50 transition cursor-pointer">Cancel</button>
                    <button type="button" @click="submitReview()" :disabled="reviewSubmitting || !reviewTitle || !reviewBody"
                            class="px-5 py-2 rounded-xl bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 text-white font-extrabold text-xs shadow-md shadow-indigo-600/25 active:scale-95 transition disabled:opacity-50 cursor-pointer">
                        <span x-text="reviewSubmitting ? 'Submitting...' : 'Submit Review'">Submit Review</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Sticky Bottom Buy Bar (sm:hidden) -->
    <div class="sm:hidden fixed bottom-0 left-0 right-0 z-40 bg-white/95 backdrop-blur-md border-t border-slate-200/90 px-4 py-2.5 shadow-[0_-8px_24px_rgba(0,0,0,0.08)] flex items-center justify-between gap-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
        <!-- Price & Variant Meta -->
        <div class="min-w-0 flex flex-col justify-center">
            <div class="flex items-baseline space-x-1.5 whitespace-nowrap">
                <span class="text-base font-black text-slate-900 tracking-tight whitespace-nowrap" x-text="currentVariant.formatted_selling_price || '{{ format_currency($product->effective_price) }}'">
                    {{ format_currency($product->effective_price) }}
                </span>
                <template x-if="currentVariant.mrp && Number(currentVariant.mrp) > Number(currentVariant.selling_price)">
                    <span class="text-xs text-slate-400 line-through font-medium whitespace-nowrap" x-text="currentVariant.formatted_mrp"></span>
                </template>
                <template x-if="currentVariant.discount_percent > 0">
                    <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200/80 px-1.5 py-0.5 rounded-md whitespace-nowrap" x-text="currentVariant.discount_percent + '% OFF'"></span>
                </template>
            </div>
            <div class="text-[10px] text-slate-500 font-medium truncate max-w-[170px]" x-text="currentVariant.name || 'Inclusive of all taxes'">
                Inclusive of all taxes
            </div>
        </div>

        <!-- Action Stepper / Buy Buttons -->
        <div class="flex items-center space-x-2 shrink-0">
            <template x-if="inCartQty === 0">
                <button type="button"
                        @click="addToCart()"
                        :disabled="!currentVariant.in_stock"
                        class="py-2.5 px-6 rounded-2xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white font-extrabold text-xs shadow-md shadow-indigo-600/20 active:scale-95 transition flex items-center space-x-1.5 cursor-pointer whitespace-nowrap">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                    <span x-text="currentVariant.in_stock ? 'Add to Cart' : 'Out of Stock'">Add to Cart</span>
                </button>
            </template>

            <template x-if="inCartQty > 0">
                <div class="flex items-center space-x-2">
                    <div class="inline-flex items-center rounded-xl bg-indigo-50 border border-indigo-200/90 text-indigo-700 shadow-2xs">
                        <button type="button" @click="decrement()" class="w-8 h-8 flex items-center justify-center hover:bg-indigo-100 font-black text-sm active:scale-90 transition cursor-pointer" aria-label="Decrease quantity">-</button>
                        <span class="w-7 text-center text-xs font-black text-indigo-950" x-text="inCartQty"></span>
                        <button type="button" @click="increment()" class="w-8 h-8 flex items-center justify-center hover:bg-indigo-100 font-black text-sm active:scale-90 transition cursor-pointer" aria-label="Increase quantity">+</button>
                    </div>

                    <button type="button"
                            @click="$store.cart.open = true"
                            class="py-2 px-3.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 text-white font-extrabold text-xs shadow-md shadow-emerald-600/20 active:scale-95 transition flex items-center space-x-1 whitespace-nowrap cursor-pointer">
                        <span>View Cart</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </button>
                </div>
            </template>
        </div>
    </div>

</div>
@endsection
