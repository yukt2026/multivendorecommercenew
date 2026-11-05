<?php

declare(strict_types=1);

namespace App\Services\Fulfilment\Strategies;

use App\Models\Inventory;
use App\Models\ProductVariant;
use App\Services\Fulfilment\CandidateMatch;

interface FulfilmentStrategyInterface
{
    /**
     * Unique identifier matching DB setting (e.g. 'nearby_store', 'nearby_seller', etc.)
     */
    public function getIdentifier(): string;

    /**
     * Priority index (lower number = higher priority).
     */
    public function getPriority(): int;

    /**
     * Evaluate if the candidate inventory satisfies the strategy criteria.
     *
     * @param ProductVariant $variant
     * @param int $requestedQty
     * @param array{lat?: float, lng?: float, pincode?: string, city?: string} $customerLocation
     * @param Inventory $inventory
     * @return CandidateMatch|null
     */
    public function evaluate(
        ProductVariant $variant,
        int $requestedQty,
        array $customerLocation,
        Inventory $inventory
    ): ?CandidateMatch;
}
