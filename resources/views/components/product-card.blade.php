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
    $productReviews = $product->relationLoaded('reviews') ? $product->reviews : null;
    $hasReviews = $productReviews && $productReviews->isNotEmpty();
    $rating = $hasReviews ? (float) $productReviews->avg('rating') : ($product->reviews_avg_rating ? (float) $product->reviews_avg_rating : null);
    $reviewCount = $hasReviews ? $productReviews->count() : ($product->reviews_count ?? 0);
@endphp

<div class="group relative flex flex-col justify-between bg-white rounded-3xl border border-slate-200/90 hover:border-indigo-300 hover:shadow-xl transition-all duration-300 overflow-hidden p-3 sm:p-4 shadow-xs"
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

    <div>
        <!-- Top Badge Row -->
        <div class="absolute top-2.5 left-2.5 right-2.5 flex items-start justify-between z-10 pointer-events-none">
            <div class="flex flex-col gap-1 items-start">
                @if($product->is_quick_capable)
                    <span class="inline-flex items-center space-x-0.5 px-2 py-0.5 rounded-lg text-[9px] sm:text-[10px] font-black bg-amber-400 text-slate-950 shadow-xs border border-amber-300">
                        <span>⚡</span>
                        <span>15m</span>
                    </span>
                @endif

                @if($discount > 0)
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-lg text-[8px] sm:text-[10px] font-black bg-rose-500 text-white shadow-xs">
                        {{ $discount }}% OFF
                    </span>
                @endif
            </div>

            <!-- Wishlist Button -->
            <button type="button"
                    @click.prevent.stop="$store.wishlist.toggle(productId)"
                    class="pointer-events-auto w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-white/95 backdrop-blur-md shadow-xs border border-slate-200/80 flex items-center justify-center text-slate-400 hover:text-rose-500 hover:scale-110 active:scale-95 transition"
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
        <a href="{{ route('product.show', $product->slug) }}" class="block relative aspect-square rounded-2xl bg-slate-50/80 border border-slate-100 overflow-hidden mb-2.5 sm:mb-3 group-hover:scale-[1.02] transition-transform duration-300">
            <img src="{{ $imgUrl }}"
                 alt="{{ $product->title }}"
                 loading="lazy"
                 decoding="async"
                 width="240"
                 height="240"
                 onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 300 300\' width=\'300\' height=\'300\'><rect width=\'300\' height=\'300\' rx=\'28\' fill=\'%23f8fafc\'/><circle cx=\'150\' cy=\'135\' r=\'52\' fill=\'%23eef2f6\'/><path d=\'M132 118 h36 l6 38 h-48 z\' fill=\'%236366f1\' fill-opacity=\'0.15\' stroke=\'%236366f1\' stroke-width=\'3\' stroke-linejoin=\'round\'/><path d=\'M141 118 v-7 a9 9 0 0 1 18 0 v7\' fill=\'none\' stroke=\'%236366f1\' stroke-width=\'3\' stroke-linecap=\'round\'/><circle cx=\'150\' cy=\'137\' r=\'3\' fill=\'%236366f1\'/><text x=\'150\' y=\'232\' font-family=\'sans-serif\' font-size=\'12\' font-weight=\'800\' fill=\'%2394a3b8\' letter-spacing=\'2\' text-anchor=\'middle\'>ONESTALL</text></svg>';"
                 class="w-full h-full object-contain p-2 sm:p-3 group-hover:scale-105 transition-transform duration-300">
        </a>

        <!-- Brand / Store Info -->
        <div class="flex items-center justify-between text-[10px] sm:text-[11px] text-slate-500 mb-1">
            <a href="{{ $product->brand ? route('brand.show', $product->brand->slug) : '#' }}" class="truncate font-bold text-indigo-600 hover:underline max-w-[85px] sm:max-w-[120px]">
                {{ $product->brand?->name ?? $product->seller?->business_name ?? 'OneStall Direct' }}
            </a>
            @if($rating)
                <div class="flex items-center text-amber-500 font-bold space-x-0.5">
                    <svg class="w-3 h-3 sm:w-3.5 sm:h-3.5 fill-current" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                    </svg>
                    <span>{{ number_format($rating, 1) }}</span>
                </div>
            @else
                <span class="text-[10px] text-slate-400 font-medium">★ New</span>
            @endif
        </div>

        <!-- Product Title -->
        <a href="{{ route('product.show', $product->slug) }}" class="block min-w-0">
            <h3 class="text-xs sm:text-sm font-bold text-slate-900 group-hover:text-indigo-600 transition line-clamp-2 leading-snug"
                title="{{ $product->title }}">
                {{ $product->title }}
            </h3>
            @if($variant && $variant->name && $variant->name !== 'Default' && $variant->name !== 'Standard Pack')
                <p class="text-[10px] text-slate-400 mt-0.5 truncate">{{ $variant->name }}</p>
            @endif
        </a>
    </div>

    <!-- Price & Quantity Action Footer -->
    <div class="mt-2.5 sm:mt-3 pt-2.5 border-t border-slate-100 flex items-end justify-between gap-1.5">
        <!-- Price Display -->
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

        <!-- Add to Cart / Qty Stepper -->
        <div class="flex-shrink-0">
            <!-- Button when 0 in cart -->
            <template x-if="inCartQty === 0">
                <button type="button"
                        @click.prevent.stop="addToCart()"
                        class="px-3 sm:px-4 py-1.5 rounded-xl bg-indigo-50 hover:bg-indigo-600 text-indigo-700 hover:text-white font-extrabold text-[10px] sm:text-xs uppercase tracking-wider transition-all shadow-2xs hover:shadow flex items-center space-x-1 border border-indigo-200 hover:border-indigo-600 active:scale-95">
                    <span>ADD</span>
                    <span class="text-xs font-black">+</span>
                </button>
            </template>

            <!-- Quantity Stepper when >= 1 in cart -->
            <template x-if="inCartQty > 0">
                <div class="inline-flex items-center rounded-xl bg-indigo-600 text-white font-bold text-xs shadow-md border border-indigo-700 overflow-hidden">
                    <button type="button"
                            @click.prevent.stop="decrement()"
                            class="px-2.5 py-1.5 hover:bg-indigo-700 active:bg-indigo-800 transition flex items-center justify-center font-black text-xs"
                            aria-label="Decrease quantity">
                        -
                    </button>
                    <span class="px-2 py-0.5 text-xs font-black min-w-[20px] text-center" x-text="inCartQty"></span>
                    <button type="button"
                            @click.prevent.stop="increment()"
                            class="px-2.5 py-1.5 hover:bg-indigo-700 active:bg-indigo-800 transition flex items-center justify-center font-black text-xs"
                            aria-label="Increase quantity">
                        +
                    </button>
                </div>
            </template>
        </div>
    </div>
</div>
