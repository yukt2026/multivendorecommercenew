<?php

namespace Database\Factories;

use App\Enums\SellerStatus;
use App\Enums\SellerType;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Seller>
 */
class SellerFactory extends Factory
{
    protected $model = Seller::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'business_name' => fake()->company(),
            'legal_name' => fake()->company() . ' Private Limited',
            'seller_type' => fake()->randomElement(SellerType::cases()),
            'pan' => strtoupper(fake()->bothify('?????####?')),
            'gstin' => '29' . strtoupper(fake()->bothify('?????####?')) . '1Z5',
            'status' => SellerStatus::APPROVED,
            'commission_override' => null, // falls back to platform settings
            'approved_at' => now(),
            'approved_by' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => SellerStatus::PENDING,
            'approved_at' => null,
            'approved_by' => null,
        ]);
    }

    public function kycSubmitted(): static
    {
        return $this->state(fn () => [
            'status' => SellerStatus::KYC_SUBMITTED,
            'approved_at' => null,
            'approved_by' => null,
        ]);
    }
}
