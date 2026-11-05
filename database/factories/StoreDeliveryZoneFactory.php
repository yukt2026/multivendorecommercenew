<?php

namespace Database\Factories;

use App\Enums\DeliveryZoneType;
use App\Models\Store;
use App\Models\StoreDeliveryZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoreDeliveryZone>
 */
class StoreDeliveryZoneFactory extends Factory
{
    protected $model = StoreDeliveryZone::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'type' => DeliveryZoneType::RADIUS,
            'radius_km' => 8.00,
            'pincodes' => ['560001', '560038', '560008'],
            'min_order_value' => 99.00,
            'delivery_fee' => 40.00,
            'is_quick' => true,
        ];
    }
}
