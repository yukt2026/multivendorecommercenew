<?php

namespace Database\Factories;

use App\Enums\SellerDocumentStatus;
use App\Enums\SellerDocumentType;
use App\Models\Seller;
use App\Models\SellerDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SellerDocument>
 */
class SellerDocumentFactory extends Factory
{
    protected $model = SellerDocument::class;

    public function definition(): array
    {
        return [
            'seller_id' => Seller::factory(),
            'type' => fake()->randomElement(SellerDocumentType::cases()),
            'file_path' => 'documents/sellers/' . fake()->uuid() . '.pdf',
            'status' => SellerDocumentStatus::APPROVED,
            'remarks' => 'Verified successfully with regulatory portal',
        ];
    }
}
