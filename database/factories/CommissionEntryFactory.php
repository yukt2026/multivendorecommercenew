<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CommissionEntry;
use App\Models\CommissionRule;
use App\Models\FulfilmentOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CommissionEntry>
 */
class CommissionEntryFactory extends Factory
{
    protected $model = CommissionEntry::class;

    public function definition(): array
    {
        $baseAmount = fake()->randomFloat(2, 500, 5000);
        $commission = round($baseAmount * 0.10, 2);
        $gatewayFee = round($baseAmount * 0.02, 2);
        $shipping = 0.00;
        $netPayable = $baseAmount - $commission - $gatewayFee;

        return [
            'fulfilment_order_id' => FulfilmentOrder::factory(),
            'rule_id' => CommissionRule::factory(),
            'base_amount' => $baseAmount,
            'commission' => $commission,
            'gateway_fee' => $gatewayFee,
            'shipping_adjustment' => $shipping,
            'net_payable' => $netPayable,
        ];
    }
}
