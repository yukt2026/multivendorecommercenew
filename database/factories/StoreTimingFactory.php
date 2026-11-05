<?php

namespace Database\Factories;

use App\Models\Store;
use App\Models\StoreTiming;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoreTiming>
 */
class StoreTimingFactory extends Factory
{
    protected $model = StoreTiming::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'day_of_week' => fake()->numberBetween(0, 6),
            'opens_at' => '07:00:00',
            'closes_at' => '23:00:00',
            'is_closed' => false,
        ];
    }
}
