<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InventoryLocationType;
use App\Models\InventoryLocation;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InventoryLocation>
 */
class InventoryLocationFactory extends Factory
{
    protected $model = InventoryLocation::class;

    public function definition(): array
    {
        return [
            'type' => InventoryLocationType::WAREHOUSE,
            'store_id' => null,
            'seller_id' => Seller::factory(),
            'name' => fake()->city() . ' Fulfillment Center',
            'lat' => fake()->latitude(12.8, 13.1),
            'lng' => fake()->longitude(77.5, 77.8),
            'is_active' => true,
        ];
    }
}
