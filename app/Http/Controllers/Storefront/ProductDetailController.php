<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Enums\ProductStatus;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductQuestion;
use App\Models\Review;
use App\Services\Fulfilment\FulfilmentService;
use App\Services\Storefront\CartService;
use App\Services\Storefront\LocationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductDetailController extends Controller
{
    public function __construct(
        protected FulfilmentService $fulfilmentService,
        protected LocationService $locationService,
        protected CartService $cartService
    ) {}

    /**
     * Product Detail Page (/p/{slug}).
     */
    public function show(Request $request, string $slug): View
    {
        $product = Product::where('slug', $slug)
            ->active()
            ->with([
                'media',
                'variants.inventories.location',
                'variants.media',
                'brand',
                'category.parent',
                'seller.stores.location',
                'specifications',
                'reviews' => fn ($q) => $q->where('status', ReviewStatus::APPROVED)->with('user')->latest(),
                'questions' => fn ($q) => $q->where('is_approved', true)->with(['answers.user', 'user'])->latest(),
            ])
            ->firstOrFail();

        $location = $this->locationService->getCurrentLocation($request);

        // Calculate Ratings Breakdown
        $reviews = $product->reviews;
        $totalReviews = $reviews->count();
        $avgRating = $totalReviews > 0 ? (float) round($reviews->avg('rating'), 1) : 4.5;
        $ratingBreakdown = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        if ($totalReviews > 0) {
            foreach ($reviews as $rev) {
                $r = min(5, max(1, (int) $rev->rating));
                $ratingBreakdown[$r]++;
            }
        }

        // Group Specifications
        $specGroups = $product->specifications->groupBy(fn ($s) => $s->group ?? $s->group_name ?? 'General');

        // Other sellers offering similar products in category
        $otherSellers = Product::active()
            ->where('id', '!=', $product->id)
            ->where(function ($q) use ($product) {
                $q->where('category_id', $product->category_id)
                    ->orWhere('brand_id', $product->brand_id);
            })
            ->where('seller_id', '!=', $product->seller_id)
            ->with(['seller.stores', 'defaultVariant', 'media'])
            ->take(3)
            ->get();

        // Related Products
        $relatedProducts = Product::active()
            ->where('id', '!=', $product->id)
            ->where('category_id', $product->category_id)
            ->with(['media', 'defaultVariant', 'variants', 'brand', 'seller'])
            ->take(8)
            ->get();

        // Breadcrumbs
        $breadcrumbs = [
            ['name' => 'Home', 'url' => route('home')],
        ];
        if ($product->category) {
            if ($product->category->parent) {
                $breadcrumbs[] = [
                    'name' => $product->category->parent->name,
                    'url' => route('category.show', $product->category->parent->slug),
                ];
            }
            $breadcrumbs[] = [
                'name' => $product->category->name,
                'url' => route('category.show', $product->category->slug),
            ];
        }
        $breadcrumbs[] = [
            'name' => $product->title,
            'url' => route('product.show', $product->slug),
        ];

        // Format Variants for Alpine.js Reactive Switcher
        $variantsData = $product->variants->map(function ($v) {
            $stock = $v->inventories->sum(fn ($i) => max(0, $i->quantity_on_hand - $i->quantity_reserved));
            $img = $v->media->first()?->path
                ? asset('storage/' . $v->media->first()->path)
                : null;

            return [
                'id' => $v->id,
                'sku' => $v->sku,
                'name' => $v->name,
                'attributes' => $v->attributes ?? [],
                'selling_price' => (float) $v->selling_price,
                'formatted_selling_price' => format_currency((float) $v->selling_price),
                'mrp' => (float) $v->mrp,
                'formatted_mrp' => format_currency((float) $v->mrp),
                'discount_percent' => ($v->mrp > $v->selling_price && $v->mrp > 0)
                    ? (int) round((($v->mrp - $v->selling_price) / $v->mrp) * 100)
                    : 0,
                'stock' => $stock,
                'in_stock' => $stock > 0,
                'image_url' => $img,
            ];
        });

        // JSON-LD Structured Schema
        $defaultVar = $product->defaultVariant ?? $product->variants->first();
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->title,
            'image' => $product->primaryImageUrl() ?? '',
            'description' => strip_tags($product->short_desc ?? $product->description ?? ''),
            'sku' => $defaultVar?->sku ?? '1S-' . $product->id,
            'brand' => [
                '@type' => 'Brand',
                'name' => $product->brand?->name ?? 'OneStall',
            ],
            'offers' => [
                '@type' => 'Offer',
                'url' => route('product.show', $product->slug),
                'priceCurrency' => setting('currency', 'INR'),
                'price' => $product->effective_price,
                'itemCondition' => 'https://schema.org/NewCondition',
                'availability' => ($defaultVar && $defaultVar->inventories->sum('quantity_on_hand') > 0)
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
                'seller' => [
                    '@type' => 'Organization',
                    'name' => $product->seller?->business_name ?? 'OneStall Seller',
                ],
            ],
            'aggregateRating' => [
                '@type' => 'AggregateRating',
                'ratingValue' => (string) $avgRating,
                'reviewCount' => max(1, $totalReviews),
            ],
        ];

        return view('storefront.product_detail', [
            'product' => $product,
            'seoType' => 'product',
            'seoModel' => $product,
            'variantsData' => $variantsData,
            'defaultVariant' => $defaultVar,
            'avgRating' => $avgRating,
            'totalReviews' => $totalReviews,
            'ratingBreakdown' => $ratingBreakdown,
            'specGroups' => $specGroups,
            'otherSellers' => $otherSellers,
            'relatedProducts' => $relatedProducts,
            'breadcrumbs' => $breadcrumbs,
            'location' => $location,
            'schemaJson' => json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
        ]);
    }

    /**
     * Check pincode delivery capabilities (Quick Commerce vs Standard Delivery).
     */
    public function checkPincode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pincode' => ['required', 'string', 'max:10'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'variant_id' => ['nullable', 'integer'],
        ]);

        $pincode = trim($validated['pincode']);
        $product = Product::with(['seller.stores.location'])->findOrFail($validated['product_id']);

        // Check if seller's store covers this pincode or within quick delivery zone
        $isQuickEligible = (bool) $product->is_quick_capable;
        $nearbyStore = null;

        if ($isQuickEligible && $product->seller) {
            $nearbyStore = $product->seller->stores()
                ->where('status', 'active')
                ->where('accepts_quick_commerce', true)
                ->whereHas('location', fn ($l) => $l->where('pincode', $pincode))
                ->first();
        }

        $freeThreshold = (float) setting('free_delivery_threshold', 499.00);
        $baseDeliveryFee = (float) setting('base_delivery_fee', 40.00);

        if ($nearbyStore) {
            return response()->json([
                'status' => 'success',
                'pincode' => $pincode,
                'is_deliverable' => true,
                'is_quick_commerce' => true,
                'estimated_delivery' => '⚡ 15-30 Minutes Express Delivery',
                'delivery_fee' => 0,
                'fulfilled_by' => $nearbyStore->name,
                'cod_available' => (bool) $product->is_cod_allowed,
                'message' => 'Express 15-minute quick delivery available from ' . $nearbyStore->name,
            ]);
        }

        // Standard courier delivery
        return response()->json([
            'status' => 'success',
            'pincode' => $pincode,
            'is_deliverable' => true,
            'is_quick_commerce' => false,
            'estimated_delivery' => '📦 Delivery in 2 - 3 business days',
            'delivery_fee' => $baseDeliveryFee,
            'fulfilled_by' => 'OneStall Pan-India Logistics',
            'cod_available' => (bool) $product->is_cod_allowed,
            'message' => 'Standard pan-India shipping available to pincode ' . $pincode,
        ]);
    }

    /**
     * Submit customer review.
     */
    public function submitReview(Request $request, string $slug): JsonResponse
    {
        $product = Product::where('slug', $slug)->active()->firstOrFail();

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:1000'],
        ]);

        $user = $request->user();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please sign in to submit a review.',
            ], 401);
        }

        // Check if verified purchaser
        $isVerified = false;
        if ($user->customer) {
            $isVerified = \App\Models\OrderItem::where('product_id', $product->id)
                ->whereHas('fulfilmentOrder.order', fn ($o) => $o->where('customer_id', $user->customer->id))
                ->exists();
        }

        $review = Review::create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'rating' => (int) $validated['rating'],
            'title' => $validated['title'],
            'body' => $validated['body'],
            'is_verified_purchase' => $isVerified,
            'status' => ReviewStatus::APPROVED, // Approved by default or moderation
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Thank you! Your review has been submitted.',
            'review' => [
                'id' => $review->id,
                'user_name' => $user->name,
                'rating' => $review->rating,
                'title' => $review->title,
                'body' => $review->body,
                'is_verified' => $review->is_verified_purchase,
                'date' => $review->created_at->format('M d, Y'),
            ],
        ]);
    }

    /**
     * Submit customer question.
     */
    public function submitQuestion(Request $request, string $slug): JsonResponse
    {
        $product = Product::where('slug', $slug)->active()->firstOrFail();

        $validated = $request->validate([
            'question' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $user = $request->user();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please sign in to ask a question.',
            ], 401);
        }

        $question = ProductQuestion::create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'question' => $validated['question'],
            'is_approved' => true,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Your question has been posted! Sellers or fellow buyers will answer soon.',
            'question' => [
                'id' => $question->id,
                'question' => $question->question,
                'user_name' => $user->name,
                'date' => $question->created_at->format('M d, Y'),
            ],
        ]);
    }
}
