<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $title = fake()->sentence(3);

        return [
            'seller_id' => Seller::factory(),
            'brand_id' => Brand::factory(),
            'category_id' => Category::factory(),
            'title' => rtrim($title, '.'),
            'slug' => Str::slug($title) . '-' . fake()->unique()->numberBetween(100, 9999),
            'short_desc' => fake()->sentence(8),
            'description' => fake()->paragraphs(2, true),
            'status' => ProductStatus::ACTIVE,
            'product_type' => ProductType::SIMPLE,
            'hsn_code' => fake()->numerify('########'),
            'tax_percent' => fake()->randomElement([0.00, 5.00, 12.00, 18.00]),
            'return_window_days' => 7,
            'is_returnable' => true,
            'is_cod_allowed' => true,
            'is_quick_capable' => fake()->boolean(50),
            'meta_title' => $title,
            'meta_description' => fake()->sentence(),
        ];
    }
}
