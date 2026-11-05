<?php

declare(strict_types=1);

namespace App\Services\AI\Contracts;

use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\SellerDemandForecast;
use Illuminate\Support\Collection;

interface DemandForecastingInterface
{
    /**
     * Compute sales run rate, days to stockout, and reorder suggestions for a seller's variant.
     *
     * @param Seller $seller Calling merchant (must own the variant)
     * @param ProductVariant $variant
     * @param int $leadTimeDays Supplier restocking lead time (default 7 days)
     * @return SellerDemandForecast
     */
    public function forecastForVariant(Seller $seller, ProductVariant $variant, int $leadTimeDays = 7): SellerDemandForecast;

    /**
     * Generate low-stock alerts and demand predictions for all active products of a seller.
     *
     * @param Seller $seller Calling merchant
     * @param int $leadTimeDays
     * @return Collection<int, SellerDemandForecast>
     */
    public function generateForecastForSeller(Seller $seller, int $leadTimeDays = 7): Collection;
}
