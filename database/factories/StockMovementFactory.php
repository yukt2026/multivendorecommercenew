<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StockMovementType;
use App\Models\Inventory;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StockMovement>
 */
class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    public function definition(): array
    {
        return [
            'inventory_id' => Inventory::factory(),
            'type' => StockMovementType::PURCHASE,
            'quantity' => fake()->numberBetween(10, 100),
            'reference_type' => null,
            'reference_id' => null,
            'note' => fake()->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
