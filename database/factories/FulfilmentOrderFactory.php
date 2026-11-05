<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FulfilmentType;
use App\Models\FulfilmentOrder;
use App\Models\Order;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FulfilmentOrder>
 */
class FulfilmentOrderFactory extends Factory
{
    protected $model = FulfilmentOrder::class;

    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 500, 10000);
        $commission = round($subtotal * 0.10, 2);
        $sellerPayable = $subtotal - $commission;

        return [
            'order_id' => Order::factory(),
            'fulfilment_order_number' => 'FO-' . strtoupper(fake()->unique()->bothify('####-????')),
            'seller_id' => Seller::factory(),
            'store_id' => null,
            'inventory_location_id' => null,
            'fulfilment_type' => FulfilmentType::STANDARD,
            'status' => 'placed',
            'subtotal' => $subtotal,
            'commission_amount' => $commission,
            'seller_payable' => $sellerPayable,
            'delivery_fee' => 50.00,
            'estimated_delivery_at' => now()->addDays(3),
            'delivered_at' => null,
            'cancelled_at' => null,
        ];
    }
}
