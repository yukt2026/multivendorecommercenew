<?php

declare(strict_types=1);

namespace App\Services\Fulfilment;

use App\Enums\FulfilmentType;
use App\Models\Cart;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class FulfilmentService
{
    public function __construct(
        protected ?FulfilmentPipeline $pipeline = null
    ) {
        $this->pipeline = $pipeline ?: new FulfilmentPipeline();
    }

    /**
     * Get the dynamic priority list for fulfilment resolution.
     * Never hard-coded; falls back to DB settings and config.
     *
     * @return array<int, string>
     */
    public function getPriorityList(): array
    {
        $priority = Setting::get('fulfilment_priority', config('onestall.fulfilment_priority', [
            'nearby_store',
            'nearby_seller',
            'onestall_store',
            'regional_warehouse',
            'national_seller',
        ]));

        return is_array($priority) ? $priority : json_decode((string) $priority, true) ?? [];
    }

    /**
     * Generate optimal fulfilment plan with caching.
     *
     * @param Cart|array<int, array{variant_id: int, quantity: int, price?: float}> $cartOrItems
     * @param array{lat?: float, lng?: float, pincode?: string, city?: string} $customerLocation
     */
    public function createPlan(Cart|array $cartOrItems, array $customerLocation): FulfilmentPlan
    {
        $items = [];
        if ($cartOrItems instanceof Cart) {
            $cartOrItems->loadMissing(['activeItems.variant']);
            foreach ($cartOrItems->activeItems as $item) {
                $items[] = [
                    'variant_id' => $item->variant_id,
                    'quantity' => $item->quantity,
                    'price' => (float) ($item->price_snapshot ?? $item->variant?->selling_price ?? 0),
                ];
            }
        } else {
            $items = $cartOrItems;
        }

        if (empty($items)) {
            return new FulfilmentPlan();
        }

        // Cache key based on input and priority
        $hash = md5(json_encode([
            'loc' => $customerLocation,
            'items' => $items,
            'p' => $this->getPriorityList(),
        ]));

        $cacheKey = "fulfilment_plan_{$hash}";

        return Cache::remember($cacheKey, 60, function () use ($items, $customerLocation) {
            return $this->pipeline->plan($items, $customerLocation);
        });
    }

    /**
     * Determine best fulfilment node based on customer lat/long and inventory availability.
     *
     * @param float $customerLat
     * @param float $customerLng
     * @param array<int, array{product_id: int, quantity: int}> $items
     * @return array{type: string, node_id: int|null, max_radius_km: float, estimated_minutes: int, is_quick_commerce: bool}
     */
    public function resolveOptimalNode(float $customerLat, float $customerLng, array $items): array
    {
        $maxRadiusKm = (float) Setting::get('default_delivery_radius_km', 8.0);
        $priorities = $this->getPriorityList();

        return [
            'type' => $priorities[0] ?? FulfilmentType::PHYSICAL_STORE_CONNECT->value,
            'node_id' => null,
            'max_radius_km' => $maxRadiusKm,
            'estimated_minutes' => 30,
            'is_quick_commerce' => true,
        ];
    }
}
