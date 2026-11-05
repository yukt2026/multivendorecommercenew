<?php

namespace Database\Factories;

use App\Enums\AddressType;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerAddress>
 */
class CustomerAddressFactory extends Factory
{
    protected $model = CustomerAddress::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'user_id' => fn (array $attributes) => Customer::find($attributes['customer_id'])->user_id ?? User::factory(),
            'type' => fake()->randomElement(AddressType::cases()),
            'name' => fake()->name(),
            'phone' => fake()->numerify('98########'),
            'line1' => fake()->streetAddress(),
            'line2' => 'Building #' . fake()->numerify('##') . ', 2nd Floor',
            'landmark' => fake()->word() . ' Circle',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560001',
            'lat' => 12.9716 + (fake()->randomFloat(4, -0.05, 0.05)),
            'lng' => 77.5946 + (fake()->randomFloat(4, -0.05, 0.05)),
            'is_default' => true,
        ];
    }
}
