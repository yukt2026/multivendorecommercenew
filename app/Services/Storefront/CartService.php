<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Inventory;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CartService
{
    /**
     * Get or create active shopping cart for user or guest session.
     */
    public function getActiveCart(?User $user, string $sessionId): Cart
    {
        if ($user) {
            $customer = $user->customer;
            $cart = Cart::where('is_active', true)
                ->where(function ($q) use ($user, $customer) {
                    $q->where('user_id', $user->id);
                    if ($customer) {
                        $q->orWhere('customer_id', $customer->id);
                    }
                })
                ->latest('id')
                ->first();

            if (!$cart) {
                $cart = Cart::create([
                    'user_id' => $user->id,
                    'customer_id' => $customer?->id,
                    'session_id' => $sessionId,
                    'is_active' => true,
                ]);
            } else {
                // Ensure customer_id is tied if newly created
                if (!$cart->customer_id && $customer) {
                    $cart->customer_id = $customer->id;
                    $cart->saveQuietly();
                }
            }

            return $cart;
        }

        $cart = Cart::where('is_active', true)
            ->where('session_id', $sessionId)
            ->latest('id')
            ->first();

        if (!$cart) {
            $cart = Cart::create([
                'session_id' => $sessionId,
                'is_active' => true,
            ]);
        }

        return $cart;
    }

    /**
     * Add item variant to cart or increment quantity.
     */
    public function addItem(Cart $cart, int $variantId, int $quantity = 1): CartItem
    {
        $variant = ProductVariant::with(['product.media', 'product.seller'])->findOrFail($variantId);

        return DB::transaction(function () use ($cart, $variant, $quantity) {
            $item = CartItem::where('cart_id', $cart->id)
                ->where('variant_id', $variant->id)
                ->first();

            if ($item) {
                if ($item->is_saved_for_later) {
                    $item->is_saved_for_later = false;
                }
                $item->quantity += max(1, $quantity);
                $item->price_snapshot = $variant->selling_price;
                $item->save();
            } else {
                $item = CartItem::create([
                    'cart_id' => $cart->id,
                    'variant_id' => $variant->id,
                    'quantity' => max(1, $quantity),
                    'price_snapshot' => $variant->selling_price,
                    'is_saved_for_later' => false,
                ]);
            }

            return $item;
        });
    }

    /**
     * Update cart item quantity or remove if <= 0.
     */
    public function updateQuantity(Cart $cart, int $cartItemId, int $quantity): ?CartItem
    {
        $item = CartItem::where('cart_id', $cart->id)->where('id', $cartItemId)->first();
        if (!$item) {
            return null;
        }

        if ($quantity <= 0) {
            $item->delete();
            return null;
        }

        $item->quantity = $quantity;
        $item->save();

        return $item;
    }

    /**
     * Remove item completely from cart.
     */
    public function removeItem(Cart $cart, int $cartItemId): bool
    {
        return (bool) CartItem::where('cart_id', $cart->id)->where('id', $cartItemId)->delete();
    }

    /**
     * Clear all items from cart.
     */
    public function clearCart(Cart $cart): void
    {
        CartItem::where('cart_id', $cart->id)->delete();
    }

    /**
     * Move cart item to Saved for Later.
     */
    public function moveToSavedForLater(Cart $cart, int $cartItemId): ?CartItem
    {
        $item = CartItem::where('cart_id', $cart->id)->where('id', $cartItemId)->first();
        if (!$item) {
            return null;
        }

        $item->is_saved_for_later = true;
        $item->save();

        return $item;
    }

    /**
     * Move saved item back to active Cart.
     */
    public function moveToCart(Cart $cart, int $cartItemId): ?CartItem
    {
        $item = CartItem::where('cart_id', $cart->id)->where('id', $cartItemId)->first();
        if (!$item) {
            return null;
        }

        $item->is_saved_for_later = false;
        $item->save();

        return $item;
    }

    /**
     * Merge guest session cart into user's account cart upon login.
     */
    public function mergeSessionCart(string $sessionId, User $user): void
    {
        $guestCart = Cart::where('session_id', $sessionId)
            ->whereNull('user_id')
            ->where('is_active', true)
            ->with('items')
            ->first();

        if (!$guestCart || $guestCart->items->isEmpty()) {
            return;
        }

        $userCart = $this->getActiveCart($user, $sessionId);

        DB::transaction(function () use ($guestCart, $userCart) {
            foreach ($guestCart->items as $item) {
                $existing = CartItem::where('cart_id', $userCart->id)
                    ->where('variant_id', $item->variant_id)
                    ->first();

                if ($existing) {
                    $existing->quantity += $item->quantity;
                    if ($existing->is_saved_for_later && !$item->is_saved_for_later) {
                        $existing->is_saved_for_later = false;
                    }
                    $existing->save();
                } else {
                    $item->cart_id = $userCart->id;
                    $item->save();
                }
            }

            // Carry over coupon code if user cart doesn't have one
            if ($guestCart->coupon_code && !$userCart->coupon_code) {
                $userCart->coupon_code = $guestCart->coupon_code;
                $userCart->save();
            }

            $guestCart->is_active = false;
            $guestCart->save();
        });
    }

    /**
     * Validate a coupon against the cart and customer.
     *
     * @return array{valid: bool, message: string, coupon?: Coupon, discount?: float, qualifying_subtotal?: float}
     */
    public function validateCoupon(Coupon $coupon, Cart $cart, ?User $user = null): array
    {
        if (!$coupon->is_active) {
            return ['valid' => false, 'message' => 'This coupon is no longer active.'];
        }

        if ($coupon->starts_at && now()->lt($coupon->starts_at)) {
            return ['valid' => false, 'message' => 'This coupon has not started yet.'];
        }

        if ($coupon->ends_at && now()->gt($coupon->ends_at)) {
            return ['valid' => false, 'message' => 'This coupon has expired.'];
        }

        if ($coupon->usage_limit !== null && $coupon->usages()->count() >= $coupon->usage_limit) {
            return ['valid' => false, 'message' => 'Coupon usage limit has been reached.'];
        }

        if ($user && $coupon->per_user_limit !== null) {
            $userUsageCount = $coupon->usages()->where('user_id', $user->id)->count();
            if ($userUsageCount >= $coupon->per_user_limit) {
                return ['valid' => false, 'message' => 'You have reached the maximum usage limit for this coupon.'];
            }
        }

        $activeItems = CartItem::where('cart_id', $cart->id)
            ->where('is_saved_for_later', false)
            ->with(['variant.product'])
            ->get();

        if ($activeItems->isEmpty()) {
            return ['valid' => false, 'message' => 'Your cart is empty.'];
        }

        $qualifyingSubtotal = 0.0;
        $matchingItemsCount = 0;

        foreach ($activeItems as $item) {
            $variant = $item->variant;
            $product = $variant?->product;
            if (!$variant || !$product) {
                continue;
            }

            // Check seller restriction
            if ($coupon->seller_id && (int) $product->seller_id !== (int) $coupon->seller_id) {
                continue;
            }

            // Check applies_to restriction (categories / products)
            if (!empty($coupon->applies_to)) {
                $categories = $coupon->applies_to['categories'] ?? [];
                $products = $coupon->applies_to['products'] ?? [];

                $matchesCategory = empty($categories) || in_array($product->category_id, $categories);
                $matchesProduct = empty($products) || in_array($product->id, $products);

                if (!$matchesCategory || !$matchesProduct) {
                    continue;
                }
            }

            $price = (float) $variant->selling_price;
            $qualifyingSubtotal += $price * $item->quantity;
            $matchingItemsCount++;
        }

        if ($matchingItemsCount === 0) {
            if ($coupon->seller_id) {
                $sellerName = $coupon->seller?->business_name ?? 'the specified seller';
                return ['valid' => false, 'message' => "This coupon is only valid for products sold by {$sellerName}."];
            }
            return ['valid' => false, 'message' => 'This coupon does not apply to any products in your cart.'];
        }

        if ($qualifyingSubtotal < (float) $coupon->min_order) {
            return [
                'valid' => false,
                'message' => 'Minimum order amount of ' . format_currency((float) $coupon->min_order) . ' required to apply this coupon.',
            ];
        }

        // Calculate discount
        $discount = 0.0;
        $couponType = is_object($coupon->type) ? $coupon->type->value : (string) $coupon->type;
        if ($couponType === 'percent') {
            $discount = round(($qualifyingSubtotal * (float) $coupon->value) / 100, 2);
            if ($coupon->max_discount !== null && $discount > (float) $coupon->max_discount) {
                $discount = (float) $coupon->max_discount;
            }
        } else {
            $discount = min((float) $coupon->value, $qualifyingSubtotal);
        }

        return [
            'valid' => true,
            'coupon' => $coupon,
            'discount' => $discount,
            'qualifying_subtotal' => $qualifyingSubtotal,
            'message' => 'Coupon ' . $coupon->code . ' applied successfully!',
        ];
    }

    /**
     * Apply coupon code to cart.
     */
    public function applyCoupon(Cart $cart, string $code, ?User $user = null): array
    {
        $code = trim(strtoupper($code));
        $coupon = Coupon::where('code', $code)->first();

        if (!$coupon) {
            return [
                'success' => false,
                'message' => "Coupon code '{$code}' is invalid.",
            ];
        }

        $result = $this->validateCoupon($coupon, $cart, $user);
        if (!$result['valid']) {
            return [
                'success' => false,
                'message' => $result['message'],
            ];
        }

        $cart->coupon_code = $coupon->code;
        $cart->save();

        return [
            'success' => true,
            'message' => $result['message'],
            'discount' => $result['discount'] ?? 0.0,
            'coupon_code' => $coupon->code,
        ];
    }

    /**
     * Remove coupon code from cart.
     */
    public function removeCoupon(Cart $cart): void
    {
        $cart->coupon_code = null;
        $cart->save();
    }

    /**
     * Accept current prices and update price snapshots.
     */
    public function acceptPriceChanges(Cart $cart): void
    {
        $cart->loadMissing('items.variant');
        foreach ($cart->items as $item) {
            if ($item->variant) {
                $item->price_snapshot = $item->variant->selling_price;
                $item->save();
            }
        }
    }

    /**
     * Validate available stock for all active items in cart.
     *
     * @return array{has_issues: bool, issues: array<int, array<string, mixed>>}
     */
    public function validateStock(Cart $cart): array
    {
        $activeItems = CartItem::where('cart_id', $cart->id)
            ->where('is_saved_for_later', false)
            ->with(['variant.product', 'variant.inventories'])
            ->get();

        $issues = [];
        foreach ($activeItems as $item) {
            $variant = $item->variant;
            if (!$variant) {
                continue;
            }

            $hasInventory = $variant->inventories->isNotEmpty();
            if ($hasInventory) {
                $available = (int) $variant->inventories->sum(fn ($i) => max(0, $i->quantity_on_hand - $i->quantity_reserved));
                if ($available <= 0) {
                    $issues[] = [
                        'item_id' => $item->id,
                        'variant_id' => $variant->id,
                        'product_title' => $variant->product?->title ?? 'Item',
                        'variant_name' => $variant->name,
                        'requested_quantity' => $item->quantity,
                        'available_quantity' => 0,
                        'status' => 'out_of_stock',
                        'message' => "Item '{$variant->product?->title}' ({$variant->name}) is out of stock.",
                    ];
                } elseif ($available < $item->quantity) {
                    $issues[] = [
                        'item_id' => $item->id,
                        'variant_id' => $variant->id,
                        'product_title' => $variant->product?->title ?? 'Item',
                        'variant_name' => $variant->name,
                        'requested_quantity' => $item->quantity,
                        'available_quantity' => $available,
                        'status' => 'insufficient_stock',
                        'message' => "Only {$available} unit(s) available for '{$variant->product?->title}' ({$variant->name}).",
                    ];
                }
            }
        }

        return [
            'has_issues' => !empty($issues),
            'issues' => $issues,
        ];
    }

    /**
     * Get complete structured summary for Cart Page, Mini-Cart Drawer, and AJAX endpoints.
     */
    public function getSummary(Cart $cart, ?User $user = null): array
    {
        $cart->load([
            'items.variant.media',
            'items.variant.product.media',
            'items.variant.product.seller.stores',
            'items.variant.inventories',
        ]);

        $activeItemsData = [];
        $savedItemsData = [];
        $totalMrp = 0.0;
        $subtotal = 0.0;
        $totalActiveCount = 0;
        $priceChangesDetected = false;
        $stockIssuesDetected = false;

        foreach ($cart->items as $item) {
            $variant = $item->variant;
            $product = $variant?->product;
            $seller = $product?->seller;

            $currentPrice = (float) ($variant?->selling_price ?? $item->price_snapshot ?? 0);
            $snapshotPrice = (float) ($item->price_snapshot ?? $currentPrice);
            $mrp = (float) ($variant?->mrp ?? $currentPrice);

            // Stock calculation
            $hasInventory = $variant && $variant->inventories->isNotEmpty();
            $availableStock = $hasInventory
                ? (int) $variant->inventories->sum(fn ($i) => max(0, $i->quantity_on_hand - $i->quantity_reserved))
                : 100;
            $isOutOfStock = $hasInventory && $availableStock <= 0;
            $isLowStock = $hasInventory && $availableStock < $item->quantity;

            if ($item->is_saved_for_later) {
                // Image resolution
                $imageUrl = $this->resolveMediaUrl($variant, $product);

                $savedItemsData[] = [
                    'id' => $item->id,
                    'variant_id' => $item->variant_id,
                    'product_id' => $product?->id,
                    'title' => $product?->title ?? 'Product Item',
                    'slug' => $product?->slug,
                    'variant_name' => $variant?->name ?? 'Standard',
                    'image_url' => $imageUrl,
                    'price' => $currentPrice,
                    'formatted_price' => format_currency($currentPrice),
                    'mrp' => $mrp,
                    'formatted_mrp' => format_currency($mrp),
                    'quantity' => $item->quantity,
                    'is_out_of_stock' => $isOutOfStock,
                    'seller_name' => $seller?->business_name ?? 'OneStall Partner',
                ];
                continue;
            }

            // Price change detection
            $priceDiff = round($currentPrice - $snapshotPrice, 2);
            $priceChanged = abs($priceDiff) > 0.009;
            if ($priceChanged) {
                $priceChangesDetected = true;
            }

            if ($isOutOfStock || $isLowStock) {
                $stockIssuesDetected = true;
            }

            $lineSubtotal = $currentPrice * $item->quantity;
            $lineMrp = $mrp * $item->quantity;

            $subtotal += $lineSubtotal;
            $totalMrp += $lineMrp;
            $totalActiveCount += $item->quantity;

            $imageUrl = $this->resolveMediaUrl($variant, $product);

            $activeItemsData[] = [
                'id' => $item->id,
                'variant_id' => $item->variant_id,
                'product_id' => $product?->id,
                'title' => $product?->title ?? 'Product Item',
                'slug' => $product?->slug,
                'variant_name' => $variant?->name ?? 'Standard',
                'sku' => $variant?->sku,
                'image_url' => $imageUrl,
                'price' => $currentPrice,
                'formatted_price' => format_currency($currentPrice),
                'snapshot_price' => $snapshotPrice,
                'mrp' => $mrp,
                'formatted_mrp' => format_currency($mrp),
                'quantity' => $item->quantity,
                'subtotal' => $lineSubtotal,
                'formatted_subtotal' => format_currency($lineSubtotal),
                'discount_percent' => ($mrp > $currentPrice && $mrp > 0) ? (int) round((($mrp - $currentPrice) / $mrp) * 100) : 0,
                'price_changed' => $priceChanged,
                'price_diff' => $priceDiff,
                'price_diff_direction' => $priceDiff > 0 ? 'increased' : ($priceDiff < 0 ? 'decreased' : 'unchanged'),
                'is_out_of_stock' => $isOutOfStock,
                'is_low_stock' => $isLowStock,
                'available_stock' => $availableStock,
                'is_quick_capable' => (bool) ($product?->is_quick_capable ?? false),
                'seller_id' => $seller?->id,
                'seller_name' => $seller?->business_name ?? 'Seller',
            ];
        }

        // Group active items by seller/store with delivery mode
        $groupedBySeller = $this->buildSellerGroups($activeItemsData);

        // Coupon calculation
        $couponDiscount = 0.0;
        $couponDetails = null;
        $couponError = null;

        if ($cart->coupon_code) {
            $coupon = Coupon::where('code', $cart->coupon_code)->first();
            if ($coupon) {
                $validation = $this->validateCoupon($coupon, $cart, $user ?? $cart->user);
                if ($validation['valid']) {
                    $couponDiscount = (float) ($validation['discount'] ?? 0.0);
                    $couponDetails = [
                        'code' => $coupon->code,
                        'type' => is_object($coupon->type) ? $coupon->type->value : (string) $coupon->type,
                        'value' => (float) $coupon->value,
                        'discount' => $couponDiscount,
                        'formatted_discount' => format_currency($couponDiscount),
                        'min_order' => (float) $coupon->min_order,
                    ];
                } else {
                    $couponError = $validation['message'];
                }
            } else {
                $couponError = "Coupon '{$cart->coupon_code}' is no longer available.";
            }
        }

        // Delivery fee from dynamic settings
        $baseDeliveryFee = (float) setting('base_delivery_fee', 40.00);
        $freeDeliveryThreshold = (float) setting('free_delivery_threshold', 499.00);
        $freeQualified = $subtotal >= $freeDeliveryThreshold;
        $freeRemaining = max(0, $freeDeliveryThreshold - $subtotal);
        $deliveryFee = $subtotal > 0 ? ($freeQualified ? 0.00 : $baseDeliveryFee) : 0.00;

        // Product discount
        $productDiscount = max(0.0, $totalMrp - $subtotal);

        // Taxes (inclusive estimate from dynamic settings)
        $defaultGstRate = (float) setting('default_gst_rate', 18.00);
        $estimatedTax = $subtotal > 0 ? round($subtotal * ($defaultGstRate / (100 + $defaultGstRate)), 2) : 0.00;

        // Total payable
        $totalPayable = max(0.0, $subtotal - $couponDiscount + $deliveryFee);

        // Total savings
        $deliverySavings = ($freeQualified && $subtotal > 0) ? $baseDeliveryFee : 0.0;
        $totalSavings = $productDiscount + $couponDiscount + $deliverySavings;

        return [
            'cart_id' => $cart->id,
            'items' => $activeItemsData,
            'active_items' => $activeItemsData,
            'saved_items' => $savedItemsData,
            'grouped_by_seller' => $groupedBySeller,
            'item_count' => $totalActiveCount,
            'saved_count' => count($savedItemsData),
            'total_mrp' => $totalMrp,
            'formatted_total_mrp' => format_currency($totalMrp),
            'subtotal' => $subtotal,
            'formatted_subtotal' => format_currency($subtotal),
            'product_discount' => $productDiscount,
            'formatted_product_discount' => format_currency($productDiscount),
            'coupon_code' => $cart->coupon_code,
            'coupon_discount' => $couponDiscount,
            'formatted_coupon_discount' => format_currency($couponDiscount),
            'coupon_details' => $couponDetails,
            'coupon_error' => $couponError,
            'delivery_fee' => $deliveryFee,
            'formatted_delivery_fee' => $deliveryFee == 0.0 ? 'FREE' : format_currency($deliveryFee),
            'base_delivery_fee' => $baseDeliveryFee,
            'free_delivery_threshold' => $freeDeliveryThreshold,
            'free_delivery_qualified' => $freeQualified,
            'free_delivery_remaining' => $freeRemaining,
            'formatted_free_remaining' => format_currency($freeRemaining),
            'progress_percent' => $freeDeliveryThreshold > 0 ? min(100, (int) round(($subtotal / $freeDeliveryThreshold) * 100)) : 100,
            'estimated_tax' => $estimatedTax,
            'formatted_estimated_tax' => format_currency($estimatedTax),
            'tax_inclusive' => (bool) setting('tax_inclusive', true),
            'total_payable' => $totalPayable,
            'formatted_total_payable' => format_currency($totalPayable),
            'total_savings' => $totalSavings,
            'formatted_total_savings' => format_currency($totalSavings),
            'price_changes_detected' => $priceChangesDetected,
            'stock_issues_detected' => $stockIssuesDetected,
        ];
    }

    /**
     * Group items by seller/store with delivery information.
     */
    protected function buildSellerGroups(array $activeItems): array
    {
        $grouped = [];
        $bySeller = collect($activeItems)->groupBy('seller_id');

        foreach ($bySeller as $sellerId => $items) {
            $sellerId = (int) $sellerId;
            $first = $items->first();
            $sellerName = $first['seller_name'] ?? 'OneStall Seller';

            // Check if all products in group are quick capable
            $allQuick = $items->every(fn ($item) => !empty($item['is_quick_capable']));

            $deliveryType = $allQuick ? 'quick' : 'standard';
            $badge = $allQuick ? '⚡ Quick Delivery (15-30 mins)' : '📦 Standard Delivery (2-4 Days)';
            $eta = $allQuick ? '15 - 30 mins' : '2 - 4 business days';

            $groupSubtotal = $items->sum('subtotal');

            $grouped[] = [
                'seller_id' => $sellerId,
                'seller_name' => $sellerName,
                'delivery_type' => $deliveryType,
                'delivery_badge' => $badge,
                'estimated_eta' => $eta,
                'is_quick' => $allQuick,
                'subtotal' => $groupSubtotal,
                'formatted_subtotal' => format_currency($groupSubtotal),
                'items' => $items->values()->all(),
            ];
        }

        return $grouped;
    }

    /**
     * Resolve thumbnail URL for variant or product.
     */
    protected function resolveMediaUrl(?ProductVariant $variant, ?object $product): ?string
    {
        if ($variant && $variant->relationLoaded('media') && $variant->media->isNotEmpty()) {
            $path = $variant->media->first()->path;
            return (str_starts_with($path, 'http://') || str_starts_with($path, 'https://'))
                ? $path
                : asset('storage/' . $path);
        }

        if ($product && method_exists($product, 'relationLoaded') && $product->relationLoaded('media') && $product->media->isNotEmpty()) {
            $path = $product->media->first()->path;
            return (str_starts_with($path, 'http://') || str_starts_with($path, 'https://'))
                ? $path
                : asset('storage/' . $path);
        }

        return null;
    }
}
