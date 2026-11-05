<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 500, 15000);
        $tax = round($subtotal * 0.18, 2);
        $deliveryFee = 50.00;
        $discount = 0.00;
        $total = $subtotal + $tax + $deliveryFee - $discount;

        return [
            'order_number' => 'ORD-' . strtoupper(fake()->unique()->bothify('####-????')),
            'customer_id' => Customer::factory(),
            'address_snapshot' => [
                'name' => fake()->name(),
                'phone' => fake()->phoneNumber(),
                'line1' => fake()->streetAddress(),
                'line2' => 'Near Landmark',
                'city' => 'Bengaluru',
                'state' => 'Karnataka',
                'pincode' => '560001',
                'lat' => 12.9716,
                'lng' => 77.5946,
            ],
            'subtotal' => $subtotal,
            'discount' => $discount,
            'delivery_fee' => $deliveryFee,
            'tax' => $tax,
            'total' => $total,
            'payment_method' => PaymentMethod::UPI,
            'payment_status' => PaymentStatus::PENDING,
            'status' => OrderStatus::PLACED,
            'notes' => fake()->sentence(),
        ];
    }
}
