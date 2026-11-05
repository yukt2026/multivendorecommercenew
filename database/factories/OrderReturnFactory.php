<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\QCStatus;
use App\Enums\ReturnStatus;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderReturn>
 */
class OrderReturnFactory extends Factory
{
    protected $model = OrderReturn::class;

    public function definition(): array
    {
        return [
            'return_number' => 'RET-' . strtoupper(fake()->unique()->bothify('####-????')),
            'order_item_id' => OrderItem::factory(),
            'reason' => 'Defective item or wrong size',
            'status' => ReturnStatus::REQUESTED,
            'pickup_shipment_id' => null,
            'qc_status' => QCStatus::PENDING,
            'refund_id' => null,
            'rejection_reason' => null,
        ];
    }
}
