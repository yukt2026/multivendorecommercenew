<?php

declare(strict_types=1);

namespace App\Services\Fulfilment\Strategies;

use App\Enums\FulfilmentType;
use App\Models\Inventory;
use App\Models\ProductVariant;
use App\Services\Fulfilment\CandidateMatch;

class NationalSellerStrategy implements FulfilmentStrategyInterface
{
    public function getIdentifier(): string
    {
        return 'national_seller';
    }

    public function getPriority(): int
    {
        return 50;
    }

    public function evaluate(
        ProductVariant $variant,
        int $requestedQty,
        array $customerLocation,
        Inventory $inventory
    ): ?CandidateMatch {
        $location = $inventory->location;
        if (!$location || !$location->is_active) {
            return null;
        }

        $available = (int) ($inventory->quantity_on_hand - $inventory->quantity_reserved);
        if ($available <= 0) {
            return null;
        }

        $allocated = min($requestedQty, $available);
        $sellerId = $location->seller_id ?: $variant->product?->seller_id ?: 1;

        return new CandidateMatch(
            strategyIdentifier: $this->getIdentifier(),
            sourceType: 'national_seller',
            sellerId: (int) $sellerId,
            storeId: $location->store_id,
            storeName: $location->store?->name,
            inventoryLocationId: $location->id,
            locationName: $location->name,
            inventoryId: $inventory->id,
            fulfilmentType: FulfilmentType::STANDARD,
            distanceKm: 250.0,
            estimatedMinutes: 2880, // 2 days / 48 hours
            allocatedQuantity: $allocated,
            priorityScore: 50
        );
    }
}
