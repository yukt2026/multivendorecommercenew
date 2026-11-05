<?php

declare(strict_types=1);

namespace App\Services\Fulfilment\Strategies;

use App\Enums\FulfilmentType;
use App\Models\Inventory;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Services\Fulfilment\CandidateMatch;

class NearbySellerStrategy implements FulfilmentStrategyInterface
{
    public function getIdentifier(): string
    {
        return 'nearby_seller';
    }

    public function getPriority(): int
    {
        return 20;
    }

    public function evaluate(
        ProductVariant $variant,
        int $requestedQty,
        array $customerLocation,
        Inventory $inventory
    ): ?CandidateMatch {
        $location = $inventory->location;
        $sellerId = $variant->product?->seller_id;
        if (!$location || !$sellerId || (int) $location->seller_id !== (int) $sellerId) {
            return null;
        }

        // If location is attached to a physical store, ensure store is open
        if ($location->store && !$location->store->isOpenNow()) {
            return null;
        }

        $custLat = $customerLocation['lat'] ?? null;
        $custLng = $customerLocation['lng'] ?? null;
        if ($custLat === null || $custLng === null || $location->lat === null || $location->lng === null) {
            return null;
        }

        $distanceKm = $this->calculateDistance((float) $custLat, (float) $custLng, (float) $location->lat, (float) $location->lng);
        $maxRadiusKm = (float) Setting::get('nearby_seller_radius_km', 25.0);

        if ($distanceKm > $maxRadiusKm) {
            return null;
        }

        $available = (int) ($inventory->quantity_on_hand - $inventory->quantity_reserved);
        if ($available <= 0) {
            return null;
        }

        $allocated = min($requestedQty, $available);
        $isQuick = (bool) ($variant->product?->is_quick_capable && $distanceKm <= (float) Setting::get('default_delivery_radius_km', 8.0));
        $estimatedMinutes = $isQuick ? 30 : 240; // 30m if within 8km quick, else 4-hour local delivery

        return new CandidateMatch(
            strategyIdentifier: $this->getIdentifier(),
            sourceType: 'nearby_seller',
            sellerId: $sellerId,
            storeId: $location->store_id,
            storeName: $location->store?->name ?? $location->name,
            inventoryLocationId: $location->id,
            locationName: $location->name,
            inventoryId: $inventory->id,
            fulfilmentType: $isQuick ? FulfilmentType::QUICK : FulfilmentType::STANDARD,
            distanceKm: $distanceKm,
            estimatedMinutes: $estimatedMinutes,
            allocatedQuantity: $allocated,
            priorityScore: 20
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
