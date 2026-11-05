<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SettlementStatus;
use App\Models\Seller;
use App\Models\SellerSettlement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SellerSettlement>
 */
class SellerSettlementFactory extends Factory
{
    protected $model = SellerSettlement::class;

    public function definition(): array
    {
        $gross = fake()->randomFloat(2, 5000, 50000);
        $commission = round($gross * 0.10, 2);
        $adjustments = 0.00;
        $net = $gross - $commission - $adjustments;

        return [
            'seller_id' => Seller::factory(),
            'settlement_number' => 'SETTLE-' . strtoupper(fake()->unique()->bothify('####-????')),
            'period_start' => now()->subDays(14)->toDateString(),
            'period_end' => now()->subDay()->toDateString(),
            'gross' => $gross,
            'commission' => $commission,
            'adjustments' => $adjustments,
            'net' => $net,
            'status' => SettlementStatus::PENDING,
            'paid_at' => null,
            'reference' => null,
        ];
    }
}
