<?php

declare(strict_types=1);

namespace App\Services\AI\Recommendations;

use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\AI\AiManager;
use App\Services\AI\Contracts\RecommendationServiceInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MarketplaceRecommendationService implements RecommendationServiceInterface
{
    public function __construct(
        protected AiManager $aiManager
    ) {}

    public function getSimilarProducts(Product $product, int $limit = 6): Collection
    {
        $start = microtime(true);

        if (!$this->aiManager->isFeatureEnabled('recommendations')) {
            return collect();
        }

        $price = (float) ($product->defaultVariant?->selling_price ?? 0);
        $minPrice = $price > 0 ? $price * 0.6 : 0;
        $maxPrice = $price > 0 ? $price * 1.5 : 100000;

        $results = Product::query()
            ->where('id', '!=', $product->id)
            ->where('status', ProductStatus::ACTIVE)
            ->where(function ($q) use ($product) {
                $q->where('category_id', $product->category_id);
                if ($product->brand_id) {
                    $q->orWhere('brand_id', $product->brand_id);
                }
            })
            ->whereHas('variants', function ($vq) use ($minPrice, $maxPrice) {
                if ($minPrice > 0) {
                    $vq->whereBetween('selling_price', [$minPrice, $maxPrice]);
                }
            })
            ->with(['media', 'defaultVariant', 'seller.stores'])
            ->inRandomOrder()
            ->take($limit)
            ->get();

        $this->aiManager->log(
            feature: 'recommendations',
            action: 'similar_products',
            promptSummary: "Similar products for #{$product->id} ('{$product->title}')",
            input: ['product_id' => $product->id, 'limit' => $limit],
            output: ['count' => $results->count(), 'product_ids' => $results->pluck('id')->toArray()],
            latencyMs: (microtime(true) - $start) * 1000,
            status: 'success'
        );

        return $results;
    }

    public function getFrequentlyBoughtTogether(Product $product, int $limit = 4): Collection
    {
        $start = microtime(true);

        if (!$this->aiManager->isFeatureEnabled('recommendations')) {
            return collect();
        }

        // 1. Find fulfilment orders containing this product
        $fulfilmentOrderIds = OrderItem::query()
            ->where('product_id', $product->id)
            ->pluck('fulfilment_order_id')
            ->unique()
            ->filter()
            ->take(100);

        $frequentlyBoughtIds = collect();

        if ($fulfilmentOrderIds->isNotEmpty()) {
            $frequentlyBoughtIds = OrderItem::query()
                ->whereIn('fulfilment_order_id', $fulfilmentOrderIds)
                ->where('product_id', '!=', $product->id)
                ->select('product_id', DB::raw('count(*) as frequency'))
                ->groupBy('product_id')
                ->orderByDesc('frequency')
                ->take($limit)
                ->pluck('product_id');
        }

        // Fallback if not enough co-occurrence history exists
        if ($frequentlyBoughtIds->count() < $limit) {
            $needed = $limit - $frequentlyBoughtIds->count();
            $fallbackIds = Product::query()
                ->where('status', ProductStatus::ACTIVE)
                ->where('id', '!=', $product->id)
                ->whereNotIn('id', $frequentlyBoughtIds)
                ->where(function ($q) use ($product) {
                    $q->where('seller_id', $product->seller_id)
                        ->orWhere('category_id', $product->category_id);
                })
                ->take($needed)
                ->pluck('id');

            $frequentlyBoughtIds = $frequentlyBoughtIds->merge($fallbackIds);
        }

        $results = Product::query()
            ->whereIn('id', $frequentlyBoughtIds)
            ->where('status', ProductStatus::ACTIVE)
            ->with(['media', 'defaultVariant', 'seller.stores'])
            ->get();

        $this->aiManager->log(
            feature: 'recommendations',
            action: 'frequently_bought_together',
            promptSummary: "Frequently bought together for #{$product->id} ('{$product->title}')",
            input: ['product_id' => $product->id, 'limit' => $limit],
            output: ['count' => $results->count(), 'product_ids' => $results->pluck('id')->toArray()],
            latencyMs: (microtime(true) - $start) * 1000,
            status: 'success'
        );

        return $results;
    }

    public function getPersonalizedRecommendations(?User $user, int $limit = 8): Collection
    {
        $start = microtime(true);

        if (!$this->aiManager->isFeatureEnabled('recommendations')) {
            return collect();
        }

        $preferredCategoryIds = collect();

        if ($user && $user->customer) {
            $preferredCategoryIds = OrderItem::query()
                ->whereHas('order', fn ($oq) => $oq->where('customer_id', $user->customer->id))
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->select('products.category_id', DB::raw('count(*) as count'))
                ->groupBy('products.category_id')
                ->orderByDesc('count')
                ->take(3)
                ->pluck('category_id');
        }

        $query = Product::query()
            ->where('status', ProductStatus::ACTIVE)
            ->with(['media', 'defaultVariant', 'seller.stores']);

        if ($preferredCategoryIds->isNotEmpty()) {
            $query->whereIn('category_id', $preferredCategoryIds);
        }

        $results = $query->inRandomOrder()->take($limit)->get();

        $this->aiManager->log(
            feature: 'recommendations',
            action: 'personalized_recommendations',
            userId: $user?->id,
            promptSummary: "Personalized picks for User #" . ($user?->id ?? 'Guest'),
            input: ['user_id' => $user?->id, 'preferred_categories' => $preferredCategoryIds->toArray()],
            output: ['count' => $results->count(), 'product_ids' => $results->pluck('id')->toArray()],
            latencyMs: (microtime(true) - $start) * 1000,
            status: 'success'
        );

        return $results;
    }
}
