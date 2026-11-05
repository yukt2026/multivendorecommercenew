<?php

namespace Database\Factories;

use App\Models\Store;
use App\Models\StoreLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoreLocation>
 */
class StoreLocationFactory extends Factory
{
    protected $model = StoreLocation::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'address' => fake()->streetAddress() . ', 100 Feet Road, Indiranagar',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560038',
            'lat' => 12.9784 + (fake()->randomFloat(4, -0.04, 0.04)),
            'lng' => 77.6408 + (fake()->randomFloat(4, -0.04, 0.04)),
        ];
    }
}
