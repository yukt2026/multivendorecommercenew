<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FulfilmentOrder;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $price = fake()->randomFloat(2, 200, 3000);
        $qty = fake()->numberBetween(1, 4);
        $tax = round($price * $qty * 0.18, 2);
        $total = ($price * $qty) + $tax;

        return [
            'fulfilment_order_id' => FulfilmentOrder::factory(),
            'product_id' => Product::factory(),
            'variant_id' => ProductVariant::factory(),
            'title' => fake()->words(3, true),
            'variant_name' => 'Default Variant',
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####-????')),
            'qty' => $qty,
            'price' => $price,
            'tax' => $tax,
            'discount' => 0.00,
            'total' => $total,
        ];
    }
}
