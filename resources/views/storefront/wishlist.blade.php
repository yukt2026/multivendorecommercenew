@extends('layouts.storefront')

@section('title', $isSharedView ? ($sharedOwner . '\'s Wishlist - OneStall.in') : 'My Wishlist - OneStall.in')

@section('content')
<div class="min-h-screen bg-slate-50 py-6 sm:py-8"
     x-data="{
        copied: false,
        shareUrl: '{{ $shareUrl ?? '' }}',
        copyShareUrl() {
            if (!this.shareUrl) return;
            navigator.clipboard.writeText(this.shareUrl);
            this.copied = true;
            setTimeout(() => { this.copied = false; }, 3000);
        }
     }">

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Breadcrumbs -->
        <nav class="flex items-center space-x-2 text-xs font-semibold text-slate-500 mb-6">
            <a href="{{ route('home') }}" class="hover:text-indigo-600 transition">Home</a>
            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span class="text-slate-900 font-bold">{{ $isSharedView ? 'Shared Wishlist' : 'My Wishlist' }}</span>
        </nav>

        <!-- Flash messages -->
        @if (session('success'))
            <div class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-semibold flex items-center space-x-2 shadow-sm">
                <svg class="w-5 h-5 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if (session('error'))
            <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-sm font-semibold flex items-center space-x-2 shadow-sm">
                <svg class="w-5 h-5 text-rose-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        <!-- Shared View Banner -->
        @if ($isSharedView)
            <div class="mb-8 p-5 sm:p-6 rounded-3xl bg-gradient-to-r from-pink-500/10 via-rose-500/10 to-indigo-500/10 border border-pink-200 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center space-x-3.5">
                    <div class="w-12 h-12 rounded-2xl bg-pink-100 text-pink-600 flex items-center justify-center text-xl font-bold flex-shrink-0">
                        🎁
                    </div>
                    <div>
                        <h2 class="text-base sm:text-lg font-extrabold text-slate-900">Wishlist shared by {{ $sharedOwner }}</h2>
                        <p class="text-xs text-slate-600 mt-0.5">Explore their curated list of favorite products and add items to your cart.</p>
                    </div>
                </div>

                <a href="{{ route('home') }}" class="px-5 py-2.5 rounded-2xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-800 font-bold text-xs shadow-sm transition">
                    Explore OneStall
                </a>
            </div>
        @endif

        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 mb-6 border-b border-slate-200">
            <div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight flex items-center space-x-2">
                    <span>{{ $isSharedView ? ($sharedOwner . '\'s Wishlist') : 'My Wishlist' }}</span>
                    <span class="text-sm font-bold px-2.5 py-0.5 rounded-full bg-rose-100 text-rose-700">
                        {{ $products->total() }} items
                    </span>
                </h1>
                <p class="text-xs text-slate-500 font-medium mt-1">
                    {{ $isSharedView ? 'Items chosen by this shopper' : 'Keep track of the items you love and move them directly to your cart' }}
                </p>
            </div>

            <!-- Share Wishlist Button (if customer is viewing own wishlist) -->
            @if (!$isSharedView && $shareUrl)
                <div class="flex items-center space-x-2">
                    <button type="button" @click="copyShareUrl()"
                            class="px-4 py-2.5 rounded-2xl bg-white border border-slate-200 hover:border-slate-300 text-slate-800 font-extrabold text-xs flex items-center space-x-2 shadow-sm active:scale-95 transition">
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                        <span x-text="copied ? 'Link Copied! ✓' : 'Share Wishlist'">Share Wishlist</span>
                    </button>
                </div>
            @endif
        </div>

        @if ($products->isEmpty())
            <!-- Empty State -->
            <div class="bg-white rounded-3xl p-10 sm:p-16 text-center border border-slate-200 shadow-sm max-w-2xl mx-auto my-8">
                <div class="w-24 h-24 mx-auto mb-6 rounded-3xl bg-rose-50 border border-rose-100 flex items-center justify-center text-rose-500">
                    <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                    </svg>
                </div>
                <h3 class="text-xl font-extrabold text-slate-900 tracking-tight">Your Wishlist is Empty</h3>
                <p class="text-xs sm:text-sm text-slate-500 mt-2 max-w-md mx-auto">
                    Explore products across top categories and click the heart icon on any product card to save it for later.
                </p>
                <div class="mt-8">
                    <a href="{{ route('home') }}"
                       class="px-6 py-3 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-xs shadow-md shadow-indigo-500/20 active:scale-95 transition inline-block">
                        Explore Trending Products
                    </a>
                </div>
            </div>
        @else
            <!-- Product Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6">
                @foreach ($products as $product)
                    @php
                        $variant = $product->defaultVariant ?: $product->variants->first();
                        $price = (float) ($variant?->selling_price ?? 0);
                        $mrp = (float) ($variant?->mrp ?? $price);
                        $discountPct = ($mrp > $price && $mrp > 0) ? (int) round((($mrp - $price) / $mrp) * 100) : 0;
                        
                        $hasInv = $variant && $variant->inventories->isNotEmpty();
                        $available = $hasInv ? (int) $variant->inventories->sum(fn ($i) => max(0, $i->quantity_on_hand - $i->quantity_reserved)) : 100;
                        $isOut = $hasInv && $available <= 0;

                        $primaryMedia = $variant?->media->first() ?: $product->media->first();
                        $imageUrl = null;
                        if ($primaryMedia) {
                            $path = $primaryMedia->path;
                            $imageUrl = (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) ? $path : asset('storage/' . $path);
                        }
                    @endphp

                    <div class="group bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-md transition flex flex-col justify-between">
                        <div>
                            <!-- Thumbnail -->
                            <div class="relative aspect-square bg-slate-100 overflow-hidden">
                                <a href="{{ route('product.show', $product->slug) }}" class="block w-full h-full">
                                    @if ($imageUrl)
                                        <img src="{{ $imageUrl }}" alt="{{ $product->title }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center text-xs font-bold text-slate-400">No Image</div>
                                    @endif
                                </a>

                                @if ($discountPct > 0)
                                    <span class="absolute top-2.5 left-2.5 px-2 py-0.5 rounded-full text-[10px] font-black bg-rose-500 text-white shadow-sm">
                                        {{ $discountPct }}% OFF
                                    </span>
                                @endif

                                @if ($product->is_quick_capable)
                                    <span class="absolute bottom-2.5 left-2.5 px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-400 text-slate-950 shadow-sm">
                                        ⚡ 15-30m
                                    </span>
                                @endif

                                @if (!$isSharedView)
                                    <!-- Remove button -->
                                    <form action="{{ route('wishlist.remove', $product->id) }}" method="POST" class="absolute top-2.5 right-2.5">
                                        @csrf
                                        <button type="submit" @click.prevent="$store.wishlist.remove({{ $product->id }}); $el.closest('.group').remove()"
                                                class="w-8 h-8 rounded-full bg-white/90 hover:bg-white text-rose-500 flex items-center justify-center shadow-md transition"
                                                title="Remove from Wishlist">
                                            <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                                        </button>
                                    </form>
                                @endif
                            </div>

                            <!-- Product Info -->
                            <div class="p-4 space-y-1.5">
                                <div class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">
                                    {{ $product->brand?->name ?? ($product->seller?->business_name ?? 'OneStall') }}
                                </div>
                                <a href="{{ route('product.show', $product->slug) }}" class="text-xs sm:text-sm font-extrabold text-slate-900 hover:text-indigo-600 line-clamp-2 transition leading-snug">
                                    {{ $product->title }}
                                </a>

                                <!-- Price -->
                                <div class="flex items-baseline space-x-2 pt-1">
                                    <span class="text-base font-black text-slate-900">{{ format_currency($price) }}</span>
                                    @if ($mrp > $price)
                                        <span class="text-xs text-slate-400 line-through">{{ format_currency($mrp) }}</span>
                                    @endif
                                </div>

                                <!-- Stock Status -->
                                @if ($isOut)
                                    <div class="text-[11px] font-black text-rose-500">Out of Stock</div>
                                @else
                                    <div class="text-[11px] font-bold text-emerald-600">In Stock</div>
                                @endif
                            </div>
                        </div>

                        <!-- Card Action -->
                        <div class="p-4 pt-0">
                            @if (!$isOut && $variant)
                                <form action="{{ route('wishlist.move-to-cart', $product->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" @click.prevent="$store.wishlist.moveToCart({{ $product->id }}); $el.closest('.group').remove()"
                                            class="w-full py-2.5 px-4 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-xs shadow-md shadow-indigo-500/20 active:scale-95 transition flex items-center justify-center space-x-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                                        <span>Move to Cart</span>
                                    </button>
                                </form>
                            @else
                                <button disabled class="w-full py-2.5 px-4 rounded-2xl bg-slate-100 text-slate-400 font-bold text-xs cursor-not-allowed">
                                    Currently Unavailable
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Pagination -->
            <div class="mt-8">
                {{ $products->links() }}
            </div>
        @endif

    </div>
</div>
@endsection
