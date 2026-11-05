<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Storefront\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(
        protected CartService $cartService
    ) {}

    /**
     * Full Cart Page (/cart).
     */
    public function index(Request $request): View
    {
        $sessionId = $request->session()->getId();
        $cart = $this->cartService->getActiveCart($request->user(), $sessionId);
        $summary = $this->cartService->getSummary($cart, $request->user());

        return view('storefront.cart', [
            'cart' => $cart,
            'summary' => $summary,
        ]);
    }

    /**
     * Get active cart summary (JSON for mini-cart & live state).
     */
    public function summary(Request $request): JsonResponse
    {
        $sessionId = $request->session()->getId();
        $cart = $this->cartService->getActiveCart($request->user(), $sessionId);

        return response()->json([
            'status' => 'success',
            'cart' => $this->cartService->getSummary($cart, $request->user()),
        ]);
    }

    /**
     * Add item variant (or default variant of product) to cart.
     */
    public function add(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $variantId = $validated['variant_id'] ?? null;
        if (!$variantId && !empty($validated['product_id'])) {
            $product = Product::with('defaultVariant')->findOrFail($validated['product_id']);
            $variantId = $product->defaultVariant?->id ?? $product->variants()->first()?->id;
        }

        if (!$variantId) {
            if ($request->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No variant available for this product.',
                ], 422);
            }
            return back()->with('error', 'No variant available for this product.');
        }

        $sessionId = $request->session()->getId();
        $cart = $this->cartService->getActiveCart($request->user(), $sessionId);
        $this->cartService->addItem($cart, (int) $variantId, (int) ($validated['quantity'] ?? 1));

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Item added to cart',
                'cart' => $this->cartService->getSummary($cart, $request->user()),
            ]);
        }

        return back()->with('success', 'Item added to your cart.');
    }

    /**
     * Update quantity of an item in cart.
     */
    public function update(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:50'],
        ]);

        $sessionId = $request->session()->getId();
        $cart = $this->cartService->getActiveCart($request->user(), $sessionId);
        $this->cartService->updateQuantity($cart, $id, (int) $validated['quantity']);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Cart updated',
                'cart' => $this->cartService->getSummary($cart, $request->user()),
            ]);
        }

        return back()->with('success', 'Cart updated successfully.');
    }

    /**
     * Remove item from cart.
     */
    public function remove(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $sessionId = $request->session()->getId();
        $cart = $this->cartService->getActiveCart($request->user(), $sessionId);
        $this->cartService->removeItem($cart, $id);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Item removed from cart',
                'cart' => $this->cartService->getSummary($cart, $request->user()),
            ]);
        }

        return back()->with('success', 'Item removed from cart.');
    }

    /**
     * Move item to Saved for Later.
     */
    public function saveForLater(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $sessionId = $request->session()->getId();
        $cart = $this->cartService->getActiveCart($request->user(), $sessionId);
        $this->cartService->moveToSavedForLater($cart, $id);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Item moved to saved for later',
                'cart' => $this->cartService->getSummary($cart, $request->user()),
            ]);
        }

        return back()->with('success', 'Item saved for later.');
    }

    /**
     * Move saved item back to active Cart.
     */
    public function moveToCart(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $sessionId = $request->session()->getId();
        $cart = $this->cartService->getActiveCart($request->user(), $sessionId);
        $this->cartService->moveToCart($cart, $id);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Item moved to cart',
                'cart' => $this->cartService->getSummary($cart, $request->user()),
            ]);
        }

        return back()->with('success', 'Item moved back to cart.');
    }

    /**
     * Apply coupon code to cart.
     */
    public function applyCoupon(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'coupon_code' => ['required', 'string', 'max:50'],
        ]);

        $sessionId = $request->session()->getId();
        $cart = $this->cartService->getActiveCart($request->user(), $sessionId);
        $result = $this->cartService->applyCoupon($cart, $validated['coupon_code'], $request->user());

        if ($request->wantsJson()) {
            return response()->json([
                'status' => $result['success'] ? 'success' : 'error',
                'message' => $result['message'],
                'cart' => $this->cartService->getSummary($cart, $request->user()),
            ], $result['success'] ? 200 : 422);
        }

        if ($result['success']) {
            return back()->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    /**
     * Remove applied coupon from cart.
     */
    public function removeCoupon(Request $request): JsonResponse|RedirectResponse
    {
        $sessionId = $request->session()->getId();
        $cart = $this->cartService->getActiveCart($request->user(), $sessionId);
        $this->cartService->removeCoupon($cart);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Coupon removed',
                'cart' => $this->cartService->getSummary($cart, $request->user()),
            ]);
        }

        return back()->with('success', 'Coupon removed.');
    }

    /**
     * Accept price changes on items in cart.
     */
    public function acceptPriceChanges(Request $request): JsonResponse|RedirectResponse
    {
        $sessionId = $request->session()->getId();
        $cart = $this->cartService->getActiveCart($request->user(), $sessionId);
        $this->cartService->acceptPriceChanges($cart);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Prices accepted',
                'cart' => $this->cartService->getSummary($cart, $request->user()),
            ]);
        }

        return back()->with('success', 'Price changes accepted.');
    }

    /**
     * Clear all items from cart.
     */
    public function clear(Request $request): JsonResponse|RedirectResponse
    {
        $sessionId = $request->session()->getId();
        $cart = $this->cartService->getActiveCart($request->user(), $sessionId);
        $this->cartService->clearCart($cart);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Cart cleared',
                'cart' => $this->cartService->getSummary($cart, $request->user()),
            ]);
        }

        return back()->with('success', 'Cart cleared.');
    }
}
