<?php

declare(strict_types=1);

namespace App\Services\Fulfilment\Strategies;

use App\Enums\FulfilmentType;
use App\Enums\InventoryLocationType;
use App\Models\Inventory;
use App\Models\ProductVariant;
use App\Services\Fulfilment\CandidateMatch;

class RegionalWarehouseStrategy implements FulfilmentStrategyInterface
{
    public function getIdentifier(): string
    {
        return 'regional_warehouse';
    }

    public function getPriority(): int
    {
        return 40;
    }

    public function evaluate(
        ProductVariant $variant,
        int $requestedQty,
        array $customerLocation,
        Inventory $inventory
    ): ?CandidateMatch {
        $location = $inventory->location;
        if (!$location) {
            return null;
        }

        $typeValue = is_object($location->type) ? $location->type->value : (string) $location->type;
        if ($typeValue !== 'warehouse' && $typeValue !== InventoryLocationType::WAREHOUSE->value) {
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
            sourceType: 'regional_warehouse',
            sellerId: (int) $sellerId,
            storeId: null,
            storeName: null,
            inventoryLocationId: $location->id,
            locationName: 'Warehouse - ' . $location->name,
            inventoryId: $inventory->id,
            fulfilmentType: FulfilmentType::STANDARD,
            distanceKm: 45.0,
            estimatedMinutes: 1440, // 24 hours / 1 day
            allocatedQuantity: $allocated,
            priorityScore: 40
        );
    }
}
