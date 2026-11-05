<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Enums\SellerStatus;
use App\Enums\StoreStatus;
use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\Seller\StoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class StoreController extends Controller
{
    public function __construct(
        protected StoreService $storeService
    ) {}

    /**
     * Display public storefront for customers (/store/{slug}).
     */
    public function show(Request $request, string $slug): View
    {
        $store = Store::query()
            ->with(['seller', 'location', 'timings', 'deliveryZones'])
            ->where('slug', $slug)
            ->first();

        // 404 / inactive handling for suspended, unapproved, or non-existent stores
        if (!$store || $store->status === StoreStatus::SUSPENDED || $store->seller->status !== SellerStatus::APPROVED) {
            abort(404, 'The requested merchant storefront is currently offline or unavailable.');
        }

        $categorySlug = $request->query('category');
        $sort = $request->query('sort');

        $data = $this->storeService->getPublicStorefrontData($store, $categorySlug ? (string) $categorySlug : null, $sort ? (string) $sort : null);

        $isFollowing = false;
        if (Auth::check()) {
            $isFollowing = $store->followers()->where('user_id', Auth::id())->exists();
        }

        return view('storefront.store', array_merge($data, [
            'isFollowing' => $isFollowing,
            'activeTab' => $request->query('tab', 'featured'),
            'seoType' => 'store',
            'seoModel' => $store,
        ]));
    }

    /**
     * Customer follow / favorite toggle for this store.
     */
    public function toggleFollow(Request $request, string $slug): JsonResponse|RedirectResponse
    {
        if (!Auth::check()) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Please sign in to follow this store.'], 401);
            }
            return redirect()->route('login')->with('info', 'Please sign in to save your favorite stores.');
        }

        $store = Store::where('slug', $slug)->firstOrFail();
        $user = Auth::user();

        $existing = $store->followers()->where('user_id', $user->id)->first();

        if ($existing) {
            $existing->delete();
            $store->decrement('rating_count');
            $following = false;
            $message = "You unfollowed {$store->name}.";
        } else {
            $store->followers()->create(['user_id' => $user->id]);
            $store->increment('rating_count');
            $following = true;
            $message = "You are now following {$store->name} for instant order updates!";
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'following' => $following,
                'followers_count' => $store->fresh()->rating_count,
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }
}
