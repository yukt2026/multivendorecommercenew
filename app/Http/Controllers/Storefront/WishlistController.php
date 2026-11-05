<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Wishlist;
use App\Services\Storefront\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class WishlistController extends Controller
{
    public function __construct(
        protected CartService $cartService
    ) {}

    /**
     * Customer's Wishlist page (/wishlist).
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $shareUrl = null;

        if ($user) {
            $customer = $user->customer ?: Customer::firstOrCreate(['user_id' => $user->id]);

            if (empty($customer->wishlist_share_token)) {
                $customer->wishlist_share_token = Str::random(32);
                $customer->saveQuietly();
            }

            $shareUrl = route('wishlist.share', ['token' => $customer->wishlist_share_token]);

            $products = Product::whereHas('wishlists', function ($q) use ($customer) {
                $q->where('customer_id', $customer->id);
            })
            ->with(['media', 'brand', 'seller', 'variants.inventories', 'defaultVariant'])
            ->latest('id')
            ->paginate(16);
        } else {
            $guestIds = $request->session()->get('guest_wishlist', []);
            $products = Product::whereIn('id', $guestIds)
                ->with(['media', 'brand', 'seller', 'variants.inventories', 'defaultVariant'])
                ->latest('id')
                ->paginate(16);
        }

        return view('storefront.wishlist', [
            'products' => $products,
            'shareUrl' => $shareUrl,
            'isSharedView' => false,
            'sharedOwner' => null,
        ]);
    }

    /**
     * Public Shared Wishlist page (/wishlist/share/{token}).
     */
    public function share(Request $request, string $token): View
    {
        $customer = Customer::where('wishlist_share_token', $token)->with('user')->firstOrFail();

        $products = Product::whereHas('wishlists', function ($q) use ($customer) {
            $q->where('customer_id', $customer->id);
        })
        ->with(['media', 'brand', 'seller', 'variants.inventories', 'defaultVariant'])
        ->latest('id')
        ->paginate(16);

        return view('storefront.wishlist', [
            'products' => $products,
            'shareUrl' => route('wishlist.share', ['token' => $token]),
            'isSharedView' => true,
            'sharedOwner' => $customer->user?->name ?? 'A OneStall Customer',
        ]);
    }

    /**
     * Toggle item in wishlist.
     */
    public function toggle(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
        ]);

        $productId = (int) $validated['product_id'];
        $user = $request->user();

        if ($user) {
            $customer = $user->customer ?: Customer::firstOrCreate(['user_id' => $user->id]);

            $existing = Wishlist::where('customer_id', $customer->id)
                ->where('product_id', $productId)
                ->first();

            if ($existing) {
                $existing->delete();
                $isWishlisted = false;
            } else {
                Wishlist::create([
                    'customer_id' => $customer->id,
                    'product_id' => $productId,
                ]);
                $isWishlisted = true;
            }

            $count = Wishlist::where('customer_id', $customer->id)->count();
        } else {
            // Guest session wishlist
            $wishlist = $request->session()->get('guest_wishlist', []);
            if (in_array($productId, $wishlist, true)) {
                $wishlist = array_values(array_diff($wishlist, [$productId]));
                $isWishlisted = false;
            } else {
                $wishlist[] = $productId;
                $isWishlisted = true;
            }
            $request->session()->put('guest_wishlist', $wishlist);
            $count = count($wishlist);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'product_id' => $productId,
                'is_wishlisted' => $isWishlisted,
                'wishlist_count' => $count,
                'message' => $isWishlisted ? 'Added to wishlist' : 'Removed from wishlist',
            ]);
        }

        return back()->with('success', $isWishlisted ? 'Added to your wishlist.' : 'Removed from your wishlist.');
    }

    /**
     * Move item from wishlist to active cart.
     */
    public function moveToCart(Request $request, int $productId): JsonResponse|RedirectResponse
    {
        $product = Product::with(['variants', 'defaultVariant'])->findOrFail($productId);
        $variant = $product->defaultVariant ?: $product->variants->first();

        if (!$variant) {
            if ($request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => 'No variant available.'], 422);
            }
            return back()->with('error', 'No variant available for this product.');
        }

        $sessionId = $request->session()->getId();
        $cart = $this->cartService->getActiveCart($request->user(), $sessionId);
        $this->cartService->addItem($cart, $variant->id, 1);

        // Remove from wishlist
        $user = $request->user();
        if ($user && $user->customer) {
            Wishlist::where('customer_id', $user->customer->id)->where('product_id', $productId)->delete();
        } else {
            $wishlist = $request->session()->get('guest_wishlist', []);
            $wishlist = array_values(array_diff($wishlist, [$productId]));
            $request->session()->put('guest_wishlist', $wishlist);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Item moved to cart',
                'cart' => $this->cartService->getSummary($cart, $request->user()),
            ]);
        }

        return back()->with('success', "'{$product->title}' moved to your cart.");
    }

    /**
     * Remove item directly from wishlist.
     */
    public function remove(Request $request, int $productId): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        if ($user && $user->customer) {
            Wishlist::where('customer_id', $user->customer->id)->where('product_id', $productId)->delete();
        } else {
            $wishlist = $request->session()->get('guest_wishlist', []);
            $wishlist = array_values(array_diff($wishlist, [$productId]));
            $request->session()->put('guest_wishlist', $wishlist);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Item removed from wishlist',
            ]);
        }

        return back()->with('success', 'Item removed from your wishlist.');
    }

    /**
     * Get current wishlist item ids and total count.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user && $user->customer) {
            $ids = Wishlist::where('customer_id', $user->customer->id)->pluck('product_id')->toArray();
        } else {
            $ids = $request->session()->get('guest_wishlist', []);
        }

        return response()->json([
            'ids' => $ids,
            'count' => count($ids),
        ]);
    }
}
