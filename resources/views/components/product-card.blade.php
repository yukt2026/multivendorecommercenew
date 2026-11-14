@props([
    'product',
    'compact' => false,
])

@php
    $variant = $product->relationLoaded('defaultVariant') && $product->defaultVariant
        ? $product->defaultVariant
        : ($product->relationLoaded('variants') ? $product->variants->first() : $product->defaultVariant);
    $variantId = $variant?->id ?? 0;
    $price = $product->effective_price;
    $mrp = $product->effective_mrp;
    $discount = $product->discount_percent;
    $imgUrl = $product->primaryImageUrl();
    $rating = 4.5;
    $reviewCount = $product->reviews_count ?? ($product->relationLoaded('reviews') ? $product->reviews->count() : 18);
@endphp

<div class="group relative flex flex-col bg-white rounded-2xl border border-slate-200/80 hover:border-indigo-300 hover:shadow-xl transition-all duration-300 overflow-hidden p-2.5 sm:p-4"
     x-data="{
        variantId: {{ $variantId }},
        productId: {{ $product->id }},
        get inCartQty() {
            return $store.cart.getItemQuantity(this.variantId, this.productId);
        },
        get cartItem() {
            return $store.cart.items.find(i => Number(i.variant_id) === Number(this.variantId)) ||
                   $store.cart.items.find(i => Number(i.product_id) === Number(this.productId));
        },
        addToCart() {
            $store.cart.addItem(this.variantId, this.productId, 1);
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
        }
     }">

    <!-- Top Badge Row -->
    <div class="absolute top-2 left-2 right-2 flex items-center justify-between z-10 pointer-events-none">
        <div class="flex flex-col gap-0.5 sm:gap-1">
            @if($product->is_quick_capable)
                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[8px] sm:text-[10px] font-black bg-amber-400 text-slate-950 shadow-xs border border-amber-300">
                    ⚡ 15m
                </span>
            @endif

            @if($discount > 0)
                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[8px] sm:text-[10px] font-bold bg-emerald-600 text-white shadow-xs">
                    {{ $discount }}% OFF
                </span>
            @endif
        </div>

        <!-- Wishlist Button -->
        <button type="button"
                @click.prevent.stop="$store.wishlist.toggle(productId)"
                class="pointer-events-auto w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-white/90 backdrop-blur-md shadow-xs border border-slate-200 flex items-center justify-center text-slate-400 hover:text-rose-500 hover:scale-110 active:scale-95 transition"
                :title="$store.wishlist.has(productId) ? 'Remove from wishlist' : 'Add to wishlist'"
                :aria-label="$store.wishlist.has(productId) ? 'Remove from wishlist' : 'Add to wishlist'">
            <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 transition-colors"
                 :class="$store.wishlist.has(productId) ? 'text-rose-500 fill-rose-500' : 'text-slate-400 fill-none'"
                 stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
            </svg>
        </button>
    </div>

    <!-- Image Container with Lazy Load -->
    <a href="{{ route('product.show', $product->slug) }}" class="block relative aspect-square rounded-xl bg-slate-50 overflow-hidden mb-2 sm:mb-3 group-hover:opacity-95 transition">
        <img src="{{ $imgUrl }}"
             alt="{{ $product->title }}"
             loading="lazy"
             decoding="async"
             width="240"
             height="240"
             onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 300 300\' width=\'300\' height=\'300\'><rect width=\'300\' height=\'300\' rx=\'20\' fill=\'%23f1f5f9\'/><circle cx=\'150\' cy=\'130\' r=\'55\' fill=\'%23e2e8f0\'/><text x=\'150\' y=\'145\' font-size=\'38\' text-anchor=\'middle\'>🛍️</text><text x=\'150\' y=\'230\' font-family=\'sans-serif\' font-size=\'13\' font-weight=\'bold\' fill=\'%23475569\' text-anchor=\'middle\'>OneStall</text></svg>';"
             class="w-full h-full object-contain p-1.5 sm:p-2 group-hover:scale-105 transition-transform duration-300">
    </a>

    <!-- Brand / Store Info -->
    <div class="flex items-center justify-between text-[10px] sm:text-[11px] text-slate-500 mb-1">
        <a href="{{ $product->brand ? route('brand.show', $product->brand->slug) : '#' }}" class="truncate font-medium text-indigo-600 hover:underline max-w-[85px] sm:max-w-[120px]">
            {{ $product->brand?->name ?? $product->seller?->business_name ?? 'OneStall Direct' }}
        </a>
        <div class="flex items-center text-amber-500 font-semibold space-x-0.5">
            <svg class="w-3 h-3 sm:w-3.5 sm:h-3.5 fill-current" viewBox="0 0 20 20">
                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
            </svg>
            <span>{{ number_format($rating, 1) }}</span>
        </div>
    </div>

    <!-- Product Title -->
    <a href="{{ route('product.show', $product->slug) }}" class="block flex-1 min-w-0">
        <h3 class="text-[11px] sm:text-sm font-semibold text-slate-900 group-hover:text-indigo-600 transition line-clamp-2 leading-tight"
            title="{{ $product->title }}">
            {{ $product->title }}
        </h3>
        @if($variant && $variant->name && $variant->name !== 'Default')
            <p class="text-[10px] text-slate-400 mt-0.5 truncate">{{ $variant->name }}</p>
        @endif
    </a>

    <!-- Price & Quantity Action Footer -->
    <div class="mt-2 sm:mt-3 pt-2 border-t border-slate-100 flex items-end justify-between gap-1.5">
        <!-- Price Display (Stacked cleanly) -->
        <div class="min-w-0 flex-1">
            <div class="text-xs sm:text-sm font-black text-slate-900 tracking-tight leading-none">
                {{ format_currency($price) }}
            </div>
            @if($mrp > $price)
                <div class="text-[9px] sm:text-[11px] text-slate-400 line-through leading-none mt-1">
                    {{ format_currency($mrp) }}
                </div>
            @endif
        </div>

        <!-- Add to Cart / Qty Stepper (Never overflow) -->
        <div class="flex-shrink-0">
            <!-- Button when 0 in cart -->
            <template x-if="inCartQty === 0">
                <button type="button"
                        @click.prevent.stop="addToCart()"
                        class="px-2.5 sm:px-3.5 py-1 sm:py-1.5 rounded-lg sm:rounded-xl bg-indigo-50 hover:bg-indigo-600 text-indigo-700 hover:text-white font-extrabold text-[10px] sm:text-xs uppercase tracking-wider transition-all shadow-2xs hover:shadow flex items-center space-x-0.5 border border-indigo-200 hover:border-indigo-600 active:scale-95">
                    <span>ADD</span>
                    <span class="text-xs font-black">+</span>
                </button>
            </template>

            <!-- Quantity Stepper when >= 1 in cart -->
            <template x-if="inCartQty > 0">
                <div class="inline-flex items-center rounded-lg sm:rounded-xl bg-indigo-600 text-white font-bold text-xs shadow-md border border-indigo-700 overflow-hidden">
                    <button type="button"
                            @click.prevent.stop="decrement()"
                            class="px-2 py-1 hover:bg-indigo-700 active:bg-indigo-800 transition flex items-center justify-center font-black text-xs"
                            aria-label="Decrease quantity">
                        -
                    </button>
                    <span class="px-1.5 py-0.5 text-xs font-black min-w-[20px] text-center" x-text="inCartQty"></span>
                    <button type="button"
                            @click.prevent.stop="increment()"
                            class="px-2 py-1 hover:bg-indigo-700 active:bg-indigo-800 transition flex items-center justify-center font-black text-xs"
                            aria-label="Increase quantity">
                        +
                    </button>
                </div>
            </template>
        </div>
    </div>
</div>
