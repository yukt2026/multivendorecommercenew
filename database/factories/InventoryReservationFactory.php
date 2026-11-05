<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ReservationStatus;
use App\Models\Inventory;
use App\Models\InventoryReservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InventoryReservation>
 */
class InventoryReservationFactory extends Factory
{
    protected $model = InventoryReservation::class;

    public function definition(): array
    {
        return [
            'inventory_id' => Inventory::factory(),
            'order_ref' => 'ORD-' . fake()->unique()->numerify('######'),
            'cart_ref' => 'CART-' . fake()->unique()->numerify('######'),
            'quantity' => fake()->numberBetween(1, 5),
            'expires_at' => now()->addMinutes(15),
            'status' => ReservationStatus::ACTIVE,
        ];
    }
}
