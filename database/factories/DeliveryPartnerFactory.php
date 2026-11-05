<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DeliveryPartner;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DeliveryPartner>
 */
class DeliveryPartnerFactory extends Factory
{
    protected $model = DeliveryPartner::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->name(),
            'phone' => fake()->unique()->numerify('98########'),
            'vehicle' => 'Electric Scooter',
            'vehicle_number' => 'KA-01-EQ-' . fake()->numerify('####'),
            'is_active' => true,
            'current_lat' => fake()->latitude(12.9, 13.0),
            'current_lng' => fake()->longitude(77.5, 77.7),
        ];
    }
}
