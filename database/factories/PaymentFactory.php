<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'gateway' => 'razorpay',
            'gateway_order_id' => 'order_' . fake()->bothify('??????????????'),
            'gateway_payment_id' => 'pay_' . fake()->bothify('??????????????'),
            'method' => PaymentMethod::UPI,
            'amount' => fake()->randomFloat(2, 500, 5000),
            'currency' => 'INR',
            'status' => PaymentStatus::PAID,
            'raw_response' => ['status' => 'authorized'],
            'verified_at' => now(),
        ];
    }
}
