@extends('layouts.storefront')

@section('content')
@php
    $primaryMedia = $product->media->first();
    $mediaList = $product->media;
@endphp

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-8"
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
                const res = await fetch('/api/storefront/pincode/check', {
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
    <nav class="flex items-center space-x-2 text-xs text-slate-500 overflow-x-auto no-scrollbar">
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
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

        <!-- Left Column: Image / Video Gallery (5 Cols) -->
        <div class="lg:col-span-5 space-y-4 sticky top-24">
            <div class="p-4 bg-white rounded-3xl border border-slate-200/80 shadow-sm relative group overflow-hidden">
                <!-- Main Zoom Viewport -->
                <div class="aspect-square rounded-2xl bg-slate-50 flex items-center justify-center overflow-hidden relative">
                    <template x-if="selectedMediaType === 'video'">
                        <video :src="selectedMediaUrl" controls class="w-full h-full object-contain"></video>
                    </template>
                    <template x-if="selectedMediaType !== 'video'">
                        <img :src="selectedMediaUrl"
                             alt="{{ $product->title }}"
                             class="w-full h-full object-contain p-4 group-hover:scale-110 transition-transform duration-300">
                    </template>

                    <!-- Quick Commerce Badge -->
                    @if($product->is_quick_capable)
                        <span class="absolute top-3 left-3 inline-flex items-center space-x-1 px-2.5 py-1 rounded-lg text-xs font-black bg-amber-400 text-slate-950 shadow-md border border-amber-300">
                            <span>⚡ 15 MIN DELIVERY</span>
                        </span>
                    @endif

                    <!-- Wishlist Floating Toggle Button -->
                    <button type="button"
                            @click="$store.wishlist.toggle({{ $product->id }})"
                            class="absolute top-3 right-3 w-10 h-10 rounded-full bg-white shadow-md border border-slate-200 flex items-center justify-center text-slate-400 hover:text-rose-500 hover:scale-110 transition">
                        <svg class="w-5 h-5"
                             :class="$store.wishlist.has({{ $product->id }}) ? 'text-rose-500 fill-rose-500' : 'text-slate-400 fill-none'"
                             stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                        </svg>
                    </button>
                </div>

                <!-- Thumbnails Row -->
                @if($mediaList->count() > 1)
                    <div class="flex items-center space-x-3 mt-4 overflow-x-auto pb-1 no-scrollbar">
                        @foreach($mediaList as $m)
                            @php
                                $mUrl = (str_starts_with($m->path, 'http://') || str_starts_with($m->path, 'https://')) ? $m->path : asset('storage/' . $m->path);
                                $isVideo = ($m->type?->value ?? '') === 'video' || str_ends_with($m->path, '.mp4');
                            @endphp
                            <button type="button"
                                    @click="selectedMediaUrl = '{{ $mUrl }}'; selectedMediaType = '{{ $isVideo ? 'video' : 'image' }}'"
                                    :class="selectedMediaUrl === '{{ $mUrl }}' ? 'ring-2 ring-indigo-600' : 'opacity-70 hover:opacity-100'"
                                    class="w-16 h-16 rounded-xl border border-slate-200 bg-slate-50 flex items-center justify-center overflow-hidden flex-shrink-0 transition">
                                @if($isVideo)
                                    <span class="text-xs font-bold text-indigo-600">▶ Video</span>
                                @else
                                    <img src="{{ $mUrl }}" alt="{{ $product->title }} preview" loading="lazy" decoding="async" class="w-full h-full object-contain p-1">
                                @endif
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Trust Highlights -->
            <div class="grid grid-cols-3 gap-2 text-center text-slate-600 text-xs font-medium">
                <div class="p-2.5 rounded-2xl bg-white border border-slate-200/80">
                    <span class="text-lg block">🛡️</span>
                    <span class="text-[11px] font-bold text-slate-800">100% Genuine</span>
                </div>
                <div class="p-2.5 rounded-2xl bg-white border border-slate-200/80">
                    <span class="text-lg block">🔄</span>
                    <span class="text-[11px] font-bold text-slate-800">{{ $product->return_window_days }}-Day Return</span>
                </div>
                <div class="p-2.5 rounded-2xl bg-white border border-slate-200/80">
                    <span class="text-lg block">💵</span>
                    <span class="text-[11px] font-bold text-slate-800">{{ $product->is_cod_allowed ? 'COD Available' : 'Prepaid Only' }}</span>
                </div>
            </div>
        </div>

        <!-- Right Column: Product Info, Variants, Pincode & Buy Box (7 Cols) -->
        <div class="lg:col-span-7 space-y-6">

            <!-- Title & Rating Header -->
            <div class="space-y-2">
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

                <h1 class="text-xl sm:text-3xl font-black text-slate-900 tracking-tight leading-snug">
                    {{ $product->title }}
                </h1>

                <!-- Rating & Reviews Badge -->
                <div class="flex items-center space-x-3 text-xs">
                    <div class="inline-flex items-center space-x-1 px-2.5 py-0.5 rounded-lg bg-emerald-600 text-white font-bold">
                        <span>★ {{ number_format($avgRating, 1) }}</span>
                    </div>
                    <a href="#reviews" class="text-slate-500 hover:text-indigo-600 font-medium">
                        {{ $totalReviews }} Ratings &amp; Reviews
                    </a>
                    <span class="text-slate-300">&bull;</span>
                    <span class="text-slate-500">HSN: {{ $product->hsn_code ?? '330499' }}</span>
                </div>
            </div>

            <!-- Price Display (Reactive to Variant Selection) -->
            <div class="p-5 rounded-3xl bg-slate-900 text-white shadow-lg space-y-1">
                <div class="flex items-baseline space-x-3">
                    <span class="text-3xl sm:text-4xl font-black tracking-tight text-white" x-text="currentVariant.formatted_selling_price || '{{ format_currency($product->effective_price) }}'"></span>
                    <span class="text-sm text-slate-400 line-through" x-show="currentVariant.mrp > currentVariant.selling_price" x-text="currentVariant.formatted_mrp"></span>
                    <span class="px-2 py-0.5 rounded-md text-xs font-black bg-amber-400 text-slate-950" x-show="currentVariant.discount_percent > 0" x-text="currentVariant.discount_percent + '% OFF'"></span>
                </div>
                <p class="text-[11px] text-slate-400">Inclusive of all taxes &bull; GST {{ (int) $product->tax_percent }}% included</p>
            </div>

            <!-- Variant Selector -->
            @if($product->variants->count() > 1)
                <div class="p-5 rounded-3xl bg-white border border-slate-200/80 shadow-xs space-y-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-900">
                        Select Option / Variant: <span class="text-indigo-600" x-text="currentVariant.name"></span>
                    </h3>

                    <div class="flex flex-wrap gap-2.5">
                        <template x-for="v in variants" :key="v.id">
                            <button type="button"
                                    @click="selectVariant(v.id)"
                                    :class="Number(activeVariantId) === Number(v.id) ? 'border-indigo-600 bg-indigo-50 text-indigo-700 ring-2 ring-indigo-600' : 'border-slate-200 hover:border-slate-300 text-slate-700 bg-white'"
                                    class="px-4 py-2 rounded-2xl border text-xs font-bold transition flex items-center space-x-2">
                                <span x-text="v.name"></span>
                                <span class="text-[11px] text-slate-400 font-normal" x-text="v.formatted_selling_price"></span>
                            </button>
                        </template>
                    </div>

                    <div class="text-[11px] text-slate-500 pt-1 flex items-center space-x-2">
                        <span>SKU: <strong class="text-slate-800" x-text="currentVariant.sku"></strong></span>
                        <span>&bull;</span>
                        <span :class="currentVariant.in_stock ? 'text-emerald-600 font-bold' : 'text-rose-500 font-bold'"
                              x-text="currentVariant.in_stock ? '✓ In Stock (' + currentVariant.stock + ' units ready)' : 'Out of Stock'"></span>
                    </div>
                </div>
            @endif

            <!-- Add to Cart & Primary Action Stepper -->
            <div class="p-5 rounded-3xl bg-white border border-slate-200/80 shadow-xs flex flex-col sm:flex-row items-center gap-4">
                <template x-if="inCartQty === 0">
                    <button type="button"
                            @click="addToCart()"
                            :disabled="!currentVariant.in_stock"
                            class="w-full sm:flex-1 py-3.5 px-6 rounded-2xl bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 disabled:opacity-50 text-white font-extrabold text-sm shadow-md active:scale-95 transition flex items-center justify-center space-x-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                        <span x-text="currentVariant.in_stock ? 'ADD TO CART' : 'OUT OF STOCK'">ADD TO CART</span>
                    </button>
                </template>

                <template x-if="inCartQty > 0">
                    <div class="w-full sm:flex-1 flex items-center justify-between p-2 rounded-2xl bg-indigo-50 border border-indigo-200">
                        <span class="text-xs font-bold text-indigo-700 pl-3">Item in Cart:</span>
                        <div class="inline-flex items-center rounded-xl bg-indigo-600 text-white font-bold text-sm shadow-sm overflow-hidden">
                            <button type="button" @click="decrement()" class="px-4 py-2 hover:bg-indigo-700 active:bg-indigo-800 font-black text-base">-</button>
                            <span class="px-4 py-2 font-black min-w-[32px] text-center" x-text="inCartQty"></span>
                            <button type="button" @click="increment()" class="px-4 py-2 hover:bg-indigo-700 active:bg-indigo-800 font-black text-base">+</button>
                        </div>
                    </div>
                </template>

                <button type="button"
                        @click="if (inCartQty === 0) addToCart(); $store.cart.open = true"
                        class="w-full sm:w-auto px-6 py-3.5 rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white font-extrabold text-sm shadow-md active:scale-95 transition whitespace-nowrap">
                    BUY NOW
                </button>
            </div>

            <!-- Delivery & Pincode Checker (Quick Commerce vs Standard Fulfilment) -->
            <div class="p-5 rounded-3xl bg-white border border-slate-200/80 shadow-xs space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-900 flex items-center space-x-1.5">
                        <span>🚚 Delivery & Availability Check</span>
                    </h3>
                    <span class="text-[11px] text-indigo-600 font-semibold" x-text="$store.location.current.city || 'Bengaluru'"></span>
                </div>

                <div class="flex items-center space-x-2">
                    <input type="text"
                           x-model="pincode"
                           maxlength="6"
                           placeholder="Enter 6-digit Pincode"
                           class="w-full max-w-[200px] text-xs py-2 px-3 rounded-xl border border-slate-200 focus:ring-indigo-500">
                    <button type="button"
                            @click="checkPincode()"
                            :disabled="pincodeLoading"
                            class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs shadow-sm transition">
                        <span x-text="pincodeLoading ? 'Checking...' : 'Check'">Check</span>
                    </button>
                </div>

                <!-- Result Notice -->
                <div x-show="pincodeResult" class="p-3 rounded-2xl bg-slate-50 border border-slate-200 text-xs space-y-1">
                    <div class="font-extrabold text-slate-900 flex items-center space-x-1.5" x-text="pincodeResult ? pincodeResult.estimated_delivery : ''"></div>
                    <div class="text-[11px] text-slate-500" x-text="pincodeResult ? pincodeResult.message : ''"></div>
                </div>
            </div>

            <!-- Seller / Store Info Card -->
            @if($product->seller)
                @php
                    $sellerStore = $product->seller->stores->first();
                @endphp
                <div class="p-5 rounded-3xl bg-white border border-slate-200/80 shadow-xs flex items-center justify-between gap-4">
                    <div class="flex items-center space-x-3 min-w-0">
                        <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-black text-lg flex-shrink-0">
                            🏪
                        </div>
                        <div class="min-w-0">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Sold & Fulfilled By</span>
                            <h4 class="text-sm font-extrabold text-slate-900 truncate">
                                {{ $sellerStore?->name ?? $product->seller->business_name }}
                            </h4>
                            <div class="flex items-center space-x-2 text-[11px] text-slate-500">
                                <span>★ {{ number_format($sellerStore?->rating_avg ?? 4.8, 1) }} Seller Rating</span>
                                <span>&bull;</span>
                                <span>{{ $sellerStore?->location?->city ?? 'Bengaluru' }}</span>
                            </div>
                        </div>
                    </div>

                    @if($sellerStore)
                        <a href="{{ route('store.show', $sellerStore->slug) }}" class="px-4 py-2 rounded-xl bg-indigo-50 hover:bg-indigo-600 text-indigo-700 hover:text-white font-bold text-xs transition flex-shrink-0">
                            Visit Storefront &rarr;
                        </a>
                    @endif
                </div>
            @endif

            <!-- Multivendor / Other Sellers Buy-Box -->
            @if($otherSellers->isNotEmpty())
                <div class="p-5 rounded-3xl bg-white border border-slate-200/80 shadow-xs space-y-3">
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
    <div class="p-6 sm:p-8 rounded-3xl bg-white border border-slate-200/80 shadow-xs space-y-6">
        <div>
            <h2 class="text-lg font-black text-slate-900">Product Overview</h2>
            <div class="mt-3 text-xs sm:text-sm text-slate-600 leading-relaxed prose max-w-none">
                {!! nl2br(e($product->description ?: $product->short_desc)) !!}
            </div>
        </div>

        <!-- Specifications Table -->
        @if($specGroups->isNotEmpty())
            <div class="pt-6 border-t border-slate-100 space-y-4">
                <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider">Specifications</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($specGroups as $group => $specs)
                        <div class="rounded-2xl border border-slate-200/80 overflow-hidden text-xs">
                            <div class="bg-slate-50 px-4 py-2 font-bold text-slate-800 border-b border-slate-200/80">
                                {{ $group ?: 'General' }}
                            </div>
                            <div class="p-4 divide-y divide-slate-100">
                                @foreach($specs as $spec)
                                    <div class="py-1.5 flex justify-between gap-4">
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
    <section id="reviews" class="p-6 sm:p-8 rounded-3xl bg-white border border-slate-200/80 shadow-xs space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-100">
            <div>
                <h2 class="text-lg font-black text-slate-900">Ratings &amp; Customer Reviews</h2>
                <p class="text-xs text-slate-500">Verified purchase reviews from genuine buyers</p>
            </div>

            <button type="button"
                    @click="reviewModalOpen = true"
                    class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-sm transition self-start sm:self-auto">
                Write a Review
            </button>
        </div>

        <!-- Ratings Scorecard & Breakdown Progress Bars -->
        <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-center">
            <div class="md:col-span-4 text-center sm:text-left space-y-2">
                <div class="flex items-baseline space-x-2">
                    <span class="text-5xl font-black text-slate-900">{{ number_format($avgRating, 1) }}</span>
                    <span class="text-slate-400 text-lg">/ 5</span>
                </div>
                <div class="flex items-center space-x-1 text-amber-400 text-base">
                    @for($i = 1; $i <= 5; $i++)
                        <span>{{ $i <= round($avgRating) ? '★' : '☆' }}</span>
                    @endfor
                </div>
                <p class="text-xs text-slate-500">{{ $totalReviews }} total ratings across India</p>
            </div>

            <!-- Breakdown Bars -->
            <div class="md:col-span-8 space-y-1.5 text-xs">
                @for($star = 5; $star >= 1; $star--)
                    @php
                        $count = $ratingBreakdown[$star] ?? 0;
                        $percent = $totalReviews > 0 ? (int) round(($count / $totalReviews) * 100) : 0;
                    @endphp
                    <div class="flex items-center space-x-3">
                        <span class="w-8 text-right font-bold text-slate-700">{{ $star }} ★</span>
                        <div class="flex-1 bg-slate-100 rounded-full h-2 overflow-hidden">
                            <div class="bg-amber-400 h-2 rounded-full" style="width: {{ $percent }}%"></div>
                        </div>
                        <span class="w-8 text-slate-400 text-[11px]">{{ $count }}</span>
                    </div>
                @endfor
            </div>
        </div>

        <!-- Reviews List -->
        <div class="space-y-4 pt-4 divide-y divide-slate-100">
            @forelse($product->reviews as $review)
                <div class="pt-4 space-y-1.5">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <span class="px-2 py-0.5 rounded-md bg-emerald-600 text-white text-[10px] font-black">
                                ★ {{ $review->rating }}
                            </span>
                            <h4 class="text-xs font-bold text-slate-900">{{ $review->title }}</h4>
                        </div>
                        <span class="text-[11px] text-slate-400">{{ $review->created_at->format('M d, Y') }}</span>
                    </div>

                    <p class="text-xs text-slate-600 leading-relaxed">{{ $review->body }}</p>

                    <div class="flex items-center space-x-2 text-[11px] text-slate-500 pt-1">
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
    <section class="p-6 sm:p-8 rounded-3xl bg-white border border-slate-200/80 shadow-xs space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-100">
            <div>
                <h2 class="text-lg font-black text-slate-900">Customer Questions &amp; Answers</h2>
                <p class="text-xs text-slate-500">Have questions about this item? Ask our seller and verified buyers</p>
            </div>
        </div>

        <!-- Ask a Question Input -->
        <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-2">
            <div class="flex gap-2">
                <input type="text"
                       x-model="questionText"
                       placeholder="Have a question? Ask it here..."
                       class="w-full text-xs p-2.5 rounded-xl border border-slate-200 bg-white focus:ring-indigo-500">
                <button type="button"
                        @click="submitQuestion()"
                        :disabled="questionSubmitting || !questionText"
                        class="px-5 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs shadow-sm transition whitespace-nowrap">
                    <span x-text="questionSubmitting ? 'Posting...' : 'Ask Question'">Ask Question</span>
                </button>
            </div>
            <div x-show="questionMessage" x-text="questionMessage" class="text-xs font-semibold text-emerald-600"></div>
        </div>

        <!-- Questions List -->
        <div class="space-y-4 divide-y divide-slate-100">
            @forelse($product->questions as $q)
                <div class="pt-4 space-y-2 text-xs">
                    <div class="font-bold text-slate-900 flex items-start space-x-2">
                        <span class="text-indigo-600 font-black">Q:</span>
                        <span>{{ $q->question }}</span>
                    </div>

                    @if($q->answers && $q->answers->isNotEmpty())
                        @foreach($q->answers as $ans)
                            <div class="pl-5 space-y-1 border-l-2 border-indigo-200 text-slate-700">
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
                        <div class="pl-5 text-slate-400 text-[11px] italic">No answers yet. The seller will answer shortly.</div>
                    @endif
                </div>
            @empty
                <p class="text-xs text-slate-500 text-center py-4">No questions asked yet about this product.</p>
            @endforelse
        </div>
    </section>

    <!-- Related Products -->
    @if($relatedProducts->isNotEmpty())
        <section class="space-y-4">
            <h2 class="text-lg font-black text-slate-900">Related Products in this Category</h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                @foreach($relatedProducts as $rel)
                    <x-product-card :product="$rel" />
                @endforeach
            </div>
        </section>
    @endif

    <!-- Mobile Sticky Add-to-Cart Bar (sm:hidden) -->
    <div class="sm:hidden fixed bottom-14 left-0 right-0 z-40 bg-white/95 backdrop-blur-md border-t border-slate-200 p-3 shadow-2xl flex items-center justify-between gap-3">
        <div class="min-w-0 flex-1">
            <div class="text-xs font-black text-slate-900 truncate" x-text="currentVariant.name"></div>
            <div class="text-sm font-extrabold text-indigo-600" x-text="currentVariant.formatted_selling_price"></div>
        </div>

        <template x-if="inCartQty === 0">
            <button type="button"
                    @click="addToCart()"
                    class="px-5 py-2.5 rounded-xl bg-indigo-600 text-white font-bold text-xs uppercase shadow-md active:scale-95">
                ADD
            </button>
        </template>

        <template x-if="inCartQty > 0">
            <div class="inline-flex items-center rounded-xl bg-indigo-600 text-white font-bold text-xs shadow-md overflow-hidden">
                <button type="button" @click="decrement()" class="px-3 py-2 hover:bg-indigo-700">-</button>
                <span class="px-2.5 py-2 min-w-[24px] text-center" x-text="inCartQty"></span>
                <button type="button" @click="increment()" class="px-3 py-2 hover:bg-indigo-700">+</button>
            </div>
        </template>
    </div>

    <!-- Review Submission Modal -->
    <div x-show="reviewModalOpen"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen p-4 text-center">
            <div x-show="reviewModalOpen"
                 @click="reviewModalOpen = false"
                 class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm transition-opacity"></div>

            <div class="inline-block w-full max-w-md p-6 my-8 text-left bg-white rounded-3xl shadow-2xl border border-slate-200 transform transition-all relative z-10 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-sm font-extrabold text-slate-900">Write a Product Review</h3>
                    <button type="button" @click="reviewModalOpen = false" class="text-slate-400 hover:text-slate-600">&times;</button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Your Rating</label>
                        <select x-model="reviewRating" class="w-full text-xs p-2 rounded-xl border border-slate-200">
                            <option value="5">★★★★★ - Excellent (5/5)</option>
                            <option value="4">★★★★☆ - Very Good (4/5)</option>
                            <option value="3">★★★☆☆ - Average (3/5)</option>
                            <option value="2">★★☆☆☆ - Poor (2/5)</option>
                            <option value="1">★☆☆☆☆ - Terrible (1/5)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Review Headline</label>
                        <input type="text" x-model="reviewTitle" placeholder="e.g. Great quality, fast delivery!" class="w-full text-xs p-2 rounded-xl border border-slate-200">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Detailed Review</label>
                        <textarea x-model="reviewBody" rows="4" placeholder="What did you like or dislike about this product?" class="w-full text-xs p-2 rounded-xl border border-slate-200"></textarea>
                    </div>

                    <div x-show="reviewMessage" x-text="reviewMessage" class="font-bold text-indigo-600 text-xs"></div>

                    <div class="pt-2 flex justify-end space-x-2">
                        <button type="button" @click="reviewModalOpen = false" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 font-bold">Cancel</button>
                        <button type="button" @click="submitReview()" :disabled="reviewSubmitting" class="px-5 py-2 rounded-xl bg-indigo-600 text-white font-bold">
                            <span x-text="reviewSubmitting ? 'Submitting...' : 'Submit Review'">Submit Review</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
