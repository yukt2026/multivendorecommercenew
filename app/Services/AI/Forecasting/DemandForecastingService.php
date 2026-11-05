<?php

declare(strict_types=1);

namespace App\Services\AI\Forecasting;

use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\SellerDemandForecast;
use App\Services\AI\AiManager;
use App\Services\AI\Contracts\DemandForecastingInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;

class DemandForecastingService implements DemandForecastingInterface
{
    public function __construct(
        protected AiManager $aiManager
    ) {}

    public function forecastForVariant(Seller $seller, ProductVariant $variant, int $leadTimeDays = 7): SellerDemandForecast
    {
        $start = microtime(true);

        // Authorization check: Variant must belong to calling seller
        $product = $variant->product;
        if (!$product || (int) $product->seller_id !== (int) $seller->id) {
            throw new AuthorizationException("Unauthorized: Variant #{$variant->id} does not belong to merchant #{$seller->id}.");
        }

        // 1. Calculate historical sales over past 30 days
        $unitsSold30d = (int) OrderItem::query()
            ->where('variant_id', $variant->id)
            ->whereHas('fulfilmentOrder', function ($fo) use ($seller) {
                $fo->where('seller_id', $seller->id)
                    ->whereNotIn('status', ['cancelled', 'rejected']);
            })
            ->where('created_at', '>=', now()->subDays(30))
            ->sum('qty');

        // Recent 7-day velocity to capture emerging trends
        $unitsSold7d = (int) OrderItem::query()
            ->where('variant_id', $variant->id)
            ->whereHas('fulfilmentOrder', function ($fo) use ($seller) {
                $fo->where('seller_id', $seller->id)
                    ->whereNotIn('status', ['cancelled', 'rejected']);
            })
            ->where('created_at', '>=', now()->subDays(7))
            ->sum('qty');

        // Weighted daily run rate (7-day weighted 60%, 30-day weighted 40%)
        $rate7d = $unitsSold7d / 7.0;
        $rate30d = $unitsSold30d / 30.0;
        $dailyVelocity = round(($rate7d * 0.6) + ($rate30d * 0.4), 2);

        // 2. Fetch current physical stock
        $variant->loadMissing('inventories');
        $availableStock = (int) $variant->inventories->sum(fn ($i) => max(0, $i->quantity_on_hand - $i->quantity_reserved));

        // 3. Compute Days to Stockout
        if ($dailyVelocity > 0) {
            $daysToStockout = round($availableStock / $dailyVelocity, 1);
        } else {
            $daysToStockout = $availableStock > 0 ? 999.0 : 0.0;
        }

        // 4. Forecast 30-day demand and recommended reorder
        $predictedDemand30d = round($dailyVelocity * 30, 2);
        $safetyStock = (int) ceil($dailyVelocity * 3); // 3 days safety buffer
        $reorderTarget = (int) ceil(($dailyVelocity * $leadTimeDays) + $safetyStock);
        $recommendedReorderQty = max(0, $reorderTarget - $availableStock);

        // 5. Categorize Stock Health
        if ($availableStock <= 0 || ($dailyVelocity > 0 && $daysToStockout <= 2.0)) {
            $stockStatus = 'critical_stockout';
        } elseif ($daysToStockout <= (float) $leadTimeDays) {
            $stockStatus = 'low_stock';
        } elseif ($daysToStockout > 60.0 && $availableStock > 50) {
            $stockStatus = 'overstocked';
        } else {
            $stockStatus = 'healthy';
        }

        // 6. Upsert Forecast Record
        $forecast = SellerDemandForecast::updateOrCreate(
            [
                'seller_id' => $seller->id,
                'variant_id' => $variant->id,
            ],
            [
                'daily_sales_velocity' => $dailyVelocity,
                'days_to_stockout' => $daysToStockout,
                'predicted_demand_30d' => $predictedDemand30d,
                'recommended_reorder_qty' => $recommendedReorderQty,
                'stock_status' => $stockStatus,
                'forecast_metadata' => [
                    'available_stock' => $availableStock,
                    'units_sold_30d' => $unitsSold30d,
                    'units_sold_7d' => $unitsSold7d,
                    'lead_time_days' => $leadTimeDays,
                    'safety_stock' => $safetyStock,
                ],
            ]
        );

        $this->aiManager->log(
            feature: 'demand_forecasting',
            action: 'predict_stockout',
            sellerId: $seller->id,
            promptSummary: "Demand forecast for Variant #{$variant->id} ('{$variant->name}')",
            input: ['variant_id' => $variant->id, 'available_stock' => $availableStock],
            output: [
                'daily_velocity' => $dailyVelocity,
                'days_to_stockout' => $daysToStockout,
                'status' => $stockStatus,
                'reorder_qty' => $recommendedReorderQty,
            ],
            latencyMs: (microtime(true) - $start) * 1000,
            status: 'success'
        );

        return $forecast;
    }

    public function generateForecastForSeller(Seller $seller, int $leadTimeDays = 7): Collection
    {
        $variants = ProductVariant::query()
            ->whereHas('product', fn ($pq) => $pq->where('seller_id', $seller->id))
            ->where('is_active', true)
            ->with(['inventories', 'product'])
            ->get();

        $forecasts = collect();
        foreach ($variants as $variant) {
            $forecasts->push($this->forecastForVariant($seller, $variant, $leadTimeDays));
        }

        return $forecasts;
    }
}
