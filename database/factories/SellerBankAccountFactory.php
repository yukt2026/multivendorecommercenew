<?php

namespace Database\Factories;

use App\Models\Seller;
use App\Models\SellerBankAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SellerBankAccount>
 */
class SellerBankAccountFactory extends Factory
{
    protected $model = SellerBankAccount::class;

    public function definition(): array
    {
        return [
            'seller_id' => Seller::factory(),
            'account_holder' => fake()->name(),
            'account_no' => fake()->numerify('############'),
            'ifsc' => 'HDFC000' . fake()->numerify('####'),
            'bank_name' => 'HDFC Bank',
            'branch_name' => 'Indiranagar Branch',
            'is_primary' => true,
            'verified_at' => now(),
        ];
    }
}
