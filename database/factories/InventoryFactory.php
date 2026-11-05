<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Inventory;
use App\Models\InventoryLocation;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Inventory>
 */
class InventoryFactory extends Factory
{
    protected $model = Inventory::class;

    public function definition(): array
    {
        $onHand = fake()->numberBetween(10, 100);
        $reserved = fake()->numberBetween(0, 5);

        return [
            'variant_id' => ProductVariant::factory(),
            'location_id' => InventoryLocation::factory(),
            'quantity_on_hand' => $onHand,
            'quantity_reserved' => $reserved,
            'low_stock_threshold' => 10,
            'is_quick_available' => true,
        ];
    }
}
