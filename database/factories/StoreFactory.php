<?php

namespace Database\Factories;

use App\Enums\StoreStatus;
use App\Models\Seller;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    protected $model = Store::class;

    public function definition(): array
    {
        $name = fake()->company() . ' ' . fake()->randomElement(['Retail', 'Store', 'Supermarket', 'Mart', 'Direct']);

        return [
            'seller_id' => Seller::factory(),
            'name' => $name,
            'slug' => Str::slug($name) . '-' . fake()->unique()->numerify('####'),
            'description' => fake()->paragraph(),
            'logo' => null,
            'banner' => null,
            'phone' => fake()->numerify('98########'),
            'email' => fake()->safeEmail(),
            'status' => StoreStatus::ACTIVE,
            'is_onestall_owned' => false,
            'accepts_quick_commerce' => true,
            'accepts_pickup' => true,
            'rating_avg' => fake()->randomFloat(2, 4.0, 5.0),
            'rating_count' => fake()->numberBetween(10, 500),
        ];
    }

    public function onestallOwned(): static
    {
        return $this->state(fn () => [
            'is_onestall_owned' => true,
        ]);
    }
}
