<?php

declare(strict_types=1);

namespace App\Services\Fulfilment\Strategies;

use App\Enums\FulfilmentType;
use App\Enums\InventoryLocationType;
use App\Models\Inventory;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Services\Fulfilment\CandidateMatch;

class NearbyStoreStrategy implements FulfilmentStrategyInterface
{
    public function getIdentifier(): string
    {
        return 'nearby_store';
    }

    public function getPriority(): int
    {
        return 10;
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

        $store = $location->store;
        if (!$store || ($store->status !== \App\Enums\StoreStatus::ACTIVE && !$store->is_active)) {
            return null;
        }

        // 1. Must be quick delivery capable
        if (!$variant->product || !$variant->product->is_quick_capable || !$store->accepts_quick_commerce) {
            return null;
        }

        // 2. Check if store is open
        if (!$store->isOpenNow()) {
            return null;
        }

        // 3. Check store capacity (max concurrent quick orders)
        if (!$store->isUnderQuickCapacity()) {
            return null;
        }

        // 4. Check customer distance
        $custLat = $customerLocation['lat'] ?? null;
        $custLng = $customerLocation['lng'] ?? null;
        if ($custLat === null || $custLng === null) {
            return null;
        }

        $storeLocation = $store->location ?: $location;
        $storeLat = $storeLocation->lat;
        $storeLng = $storeLocation->lng;
        if ($storeLat === null || $storeLng === null) {
            return null;
        }

        $distanceKm = $this->calculateDistance((float) $custLat, (float) $custLng, (float) $storeLat, (float) $storeLng);
        $maxRadiusKm = $store->effectiveQuickRadius();

        if ($distanceKm > $maxRadiusKm) {
            return null;
        }

        // 5. Check available stock
        $available = (int) ($inventory->quantity_on_hand - $inventory->quantity_reserved);
        if ($available <= 0) {
            return null;
        }

        $allocated = min($requestedQty, $available);

        // Dynamic realistic ETA: distance + prep time + store load
        $etaDetails = $store->calculateQuickEta($distanceKm);
        $estimatedMinutes = $etaDetails['max_minutes'];

        return new CandidateMatch(
            strategyIdentifier: $this->getIdentifier(),
            sourceType: 'nearby_store',
            sellerId: (int) ($store->seller_id ?: $variant->product->seller_id),
            storeId: $store->id,
            storeName: $store->name,
            inventoryLocationId: $location->id,
            locationName: $location->name,
            inventoryId: $inventory->id,
            fulfilmentType: FulfilmentType::QUICK,
            distanceKm: $distanceKm,
            estimatedMinutes: $estimatedMinutes,
            allocatedQuantity: $allocated,
            priorityScore: 10
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
