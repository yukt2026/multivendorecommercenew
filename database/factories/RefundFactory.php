<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RefundStatus;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Refund>
 */
class RefundFactory extends Factory
{
    protected $model = Refund::class;

    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'fulfilment_order_id' => null,
            'amount' => fake()->randomFloat(2, 100, 1000),
            'reason' => 'Customer requested return',
            'status' => RefundStatus::PROCESSED,
            'gateway_refund_id' => 'rfnd_' . fake()->bothify('??????????????'),
            'raw_response' => ['status' => 'processed'],
            'processed_at' => now(),
        ];
    }
}
