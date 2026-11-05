<?php

declare(strict_types=1);

namespace App\Services\AI\Contracts;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;

interface SellerAiAssistantInterface
{
    /**
     * Generate an SEO-optimized product title, description, key highlights, and meta tags.
     *
     * @param Seller $seller Calling merchant (for scoping and authorization)
     * @param string $title Working product name
     * @param string $category Category or taxonomy name
     * @param string|null $brand Brand name
     * @param array<string> $keyFeatures Key specs, materials, or features
     * @param string $tone Writing tone ('engaging', 'professional', 'minimalist')
     * @return array{title: string, description: string, key_highlights: array<string>, meta_title: string, meta_description: string, tags: array<string>}
     */
    public function generateCatalogueContent(
        Seller $seller,
        string $title,
        string $category,
        ?string $brand = null,
        array $keyFeatures = [],
        string $tone = 'professional'
    ): array;

    /**
     * Generate intelligent, data-driven pricing suggestions for a seller's product variant.
     *
     * @param Seller $seller Calling merchant (must own the variant)
     * @param ProductVariant $variant
     * @return array{current_selling_price: float, current_mrp: float, category_avg_price: float, recommended_price: float, suggested_discount_pct: int, rationale: string, competitor_sample_size: int}
     */
    public function suggestPricing(Seller $seller, ProductVariant $variant): array;
}
