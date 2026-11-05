<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        $mrp = fake()->randomFloat(2, 100, 5000);
        $sellingPrice = round($mrp * fake()->randomFloat(2, 0.7, 0.95), 2);
        $costPrice = round($sellingPrice * fake()->randomFloat(2, 0.6, 0.8), 2);

        return [
            'product_id' => Product::factory(),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-????-#####')),
            'barcode' => fake()->unique()->ean13(),
            'name' => fake()->word() . ' Variant',
            'attributes' => [
                'color' => fake()->safeColorName(),
                'size' => fake()->randomElement(['S', 'M', 'L', 'XL', 'Standard']),
            ],
            'mrp' => $mrp,
            'selling_price' => $sellingPrice,
            'cost_price' => $costPrice,
            'weight_grams' => fake()->numberBetween(50, 2000),
            'length' => fake()->randomFloat(2, 5, 50),
            'width' => fake()->randomFloat(2, 5, 50),
            'height' => fake()->randomFloat(2, 2, 30),
            'is_default' => true,
            'is_active' => true,
        ];
    }
}
