<?php

declare(strict_types=1);

namespace App\Services\Fulfilment;

use App\Enums\FulfilmentType;
use Carbon\Carbon;

class FulfilmentPlanGroup
{
    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function __construct(
        public int $sellerId,
        public string $sellerName,
        public ?int $storeId,
        public ?string $storeName,
        public ?int $inventoryLocationId,
        public ?string $inventoryLocationName,
        public string $sourceType,
        public FulfilmentType $fulfilmentType,
        public string $deliveryBadge,
        public ?float $distanceKm,
        public int $estimatedMinutes,
        public Carbon $estimatedDeliveryAt,
        public float $deliveryFee,
        public float $subtotal,
        public array $items = []
    ) {}

    public function toArray(): array
    {
        return [
            'seller_id' => $this->sellerId,
            'seller_name' => $this->sellerName,
            'store_id' => $this->storeId,
            'store_name' => $this->storeName,
            'inventory_location_id' => $this->inventoryLocationId,
            'inventory_location_name' => $this->inventoryLocationName,
            'source_type' => $this->sourceType,
            'fulfilment_type' => $this->fulfilmentType->value,
            'delivery_badge' => $this->deliveryBadge,
            'distance_km' => $this->distanceKm,
            'estimated_minutes' => $this->estimatedMinutes,
            'estimated_delivery_at' => $this->estimatedDeliveryAt->toIso8601String(),
            'formatted_eta' => $this->formatEta(),
            'delivery_fee' => $this->deliveryFee,
            'formatted_delivery_fee' => $this->deliveryFee == 0 ? 'FREE' : format_currency($this->deliveryFee),
            'subtotal' => $this->subtotal,
            'formatted_subtotal' => format_currency($this->subtotal),
            'items' => $this->items,
            'item_count' => count($this->items),
        ];
    }

    protected function formatEta(): string
    {
        if ($this->estimatedMinutes <= 60) {
            return "{$this->estimatedMinutes} mins";
        }
        if ($this->estimatedMinutes <= 1440) {
            $hours = (int) round($this->estimatedMinutes / 60);
            return "Today ({$hours} hours)";
        }
        $days = (int) ceil($this->estimatedMinutes / 1440);
        return "{$days} - " . ($days + 2) . " days";
    }
}
