<?php

declare(strict_types=1);

namespace App\Services\Fulfilment\Strategies;

use App\Enums\FulfilmentType;
use App\Enums\InventoryLocationType;
use App\Models\Inventory;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Services\Fulfilment\CandidateMatch;

class OneStallStoreStrategy implements FulfilmentStrategyInterface
{
    public function getIdentifier(): string
    {
        return 'onestall_store';
    }

    public function getPriority(): int
    {
        return 30;
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
        if ($typeValue !== 'onestall_store' && $typeValue !== InventoryLocationType::ONESTALL_STORE->value) {
            return null;
        }

        $custLat = $customerLocation['lat'] ?? null;
        $custLng = $customerLocation['lng'] ?? null;
        $distanceKm = 0.0;
        if ($custLat !== null && $custLng !== null && $location->lat !== null && $location->lng !== null) {
            $distanceKm = $this->calculateDistance((float) $custLat, (float) $custLng, (float) $location->lat, (float) $location->lng);
            $maxRadiusKm = (float) Setting::get('onestall_store_radius_km', 15.0);
            if ($distanceKm > $maxRadiusKm) {
                return null;
            }
        }

        $available = (int) ($inventory->quantity_on_hand - $inventory->quantity_reserved);
        if ($available <= 0) {
            return null;
        }

        $allocated = min($requestedQty, $available);
        $sellerId = $location->seller_id ?: $variant->product?->seller_id ?: 1;

        return new CandidateMatch(
            strategyIdentifier: $this->getIdentifier(),
            sourceType: 'onestall_store',
            sellerId: (int) $sellerId,
            storeId: $location->store_id,
            storeName: 'OneStall Hub (' . $location->name . ')',
            inventoryLocationId: $location->id,
            locationName: $location->name,
            inventoryId: $inventory->id,
            fulfilmentType: FulfilmentType::QUICK,
            distanceKm: $distanceKm,
            estimatedMinutes: 30,
            allocatedQuantity: $allocated,
            priorityScore: 30
        );
    }

    protected function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return round($earthRadius * $c, 2);
    }
}
