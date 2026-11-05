<?php

declare(strict_types=1);

namespace App\Services\AI\SellerAssistant;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Services\AI\AiManager;
use App\Services\AI\Contracts\SellerAiAssistantInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

class SellerAiAssistantService implements SellerAiAssistantInterface
{
    public function __construct(
        protected AiManager $aiManager
    ) {}

    public function generateCatalogueContent(
        Seller $seller,
        string $title,
        string $category,
        ?string $brand = null,
        array $keyFeatures = [],
        string $tone = 'professional'
    ): array {
        $start = microtime(true);
        $cleanTitle = trim($title);

        if (!$this->aiManager->isFeatureEnabled('seller_assistant')) {
            return [
                'title' => $cleanTitle,
                'description' => "<p>{$cleanTitle}</p>",
                'key_highlights' => $keyFeatures,
                'meta_title' => $cleanTitle,
                'meta_description' => "Buy {$cleanTitle} at best prices on OneStall.",
                'tags' => [strtolower($category)],
            ];
        }

        // Generate enhanced title
        $brandPrefix = $brand ? "{$brand} " : "";
        $enhancedTitle = ucwords("{$brandPrefix}{$cleanTitle}");

        // Build engaging key highlights
        $highlights = !empty($keyFeatures) ? $keyFeatures : [
            "Premium quality {$category} crafted for maximum durability and reliability.",
            "Certified authentic and sourced directly by {$seller->business_name}.",
            "Safe, eco-friendly packaging and rapid fulfillment via OneStall logistics.",
            "100% genuine product backed by easy returns and customer warranty.",
        ];

        // Format rich description HTML
        $bulletsHtml = implode("\n", array_map(fn ($h) => "<li>" . e($h) . "</li>", $highlights));
        $descriptionHtml = <<<HTML
<div class="product-ai-description">
    <p>Discover the superior quality of the <strong>{$enhancedTitle}</strong>. Designed specifically for discerning buyers looking for premium {$category}, this product combines exquisite craftsmanship with lasting performance.</p>
    
    <h3>Key Highlights & Features:</h3>
    <ul>
        {$bulletsHtml}
    </ul>

    <h3>Why Choose {$seller->business_name}?</h3>
    <p>Every order is handled with stringent quality control, verified batch inspection, and fast doorstep delivery.</p>
</div>
HTML;

        // Meta Tags
        $metaTitle = "Buy {$enhancedTitle} Online | Best Price on OneStall";
        $metaDesc = "Shop authentic {$enhancedTitle} from {$seller->business_name}. Enjoy fast shipping, verified quality, and secure payments across India.";

        // Tags
        $tags = array_unique(array_filter([
            strtolower(Str::slug($cleanTitle)),
            strtolower(Str::slug($category)),
            $brand ? strtolower(Str::slug($brand)) : null,
            'top-rated',
            'trending',
        ]));

        $output = [
            'title' => $enhancedTitle,
            'description' => $descriptionHtml,
            'key_highlights' => $highlights,
            'meta_title' => substr($metaTitle, 0, 70),
            'meta_description' => substr($metaDesc, 0, 160),
            'tags' => array_values($tags),
        ];

        $this->aiManager->log(
            feature: 'seller_assistant',
            action: 'generate_catalogue_content',
            sellerId: $seller->id,
            promptSummary: "Generated description for '{$cleanTitle}' in {$category}",
            input: [
                'title' => $cleanTitle,
                'category' => $category,
                'brand' => $brand,
                'features' => $keyFeatures,
                'tone' => $tone,
            ],
            output: $output,
            latencyMs: (microtime(true) - $start) * 1000,
            status: 'success'
        );

        return $output;
    }

    public function suggestPricing(Seller $seller, ProductVariant $variant): array
    {
        $start = microtime(true);

        // Multi-Tenant Isolation Check: Merchant MUST own this product
        $product = $variant->product;
        if (!$product || (int) $product->seller_id !== (int) $seller->id) {
            throw new AuthorizationException("Unauthorized: Variant #{$variant->id} does not belong to merchant #{$seller->id}.");
        }

        $currentPrice = (float) $variant->selling_price;
        $currentMrp = (float) ($variant->mrp > 0 ? $variant->mrp : $currentPrice * 1.25);

        if (!$this->aiManager->isFeatureEnabled('seller_assistant')) {
            return [
                'current_selling_price' => $currentPrice,
                'current_mrp' => $currentMrp,
                'category_avg_price' => $currentPrice,
                'recommended_price' => $currentPrice,
                'suggested_discount_pct' => 0,
                'rationale' => 'AI pricing suggestion is currently disabled.',
                'competitor_sample_size' => 0,
            ];
        }

        // Benchmark against competitor products in same category
        $competitorVariants = ProductVariant::query()
            ->whereHas('product', function ($pq) use ($product, $seller) {
                $pq->where('category_id', $product->category_id)
                    ->where('seller_id', '!=', $seller->id)
                    ->where('status', ProductStatus::ACTIVE);
            })
            ->where('selling_price', '>', 0)
            ->where('is_active', true)
            ->get();

        $sampleSize = $competitorVariants->count();
        $categoryAvg = $sampleSize > 0
            ? (float) round($competitorVariants->avg('selling_price'), 2)
            : $currentPrice;

        $minCompetitor = $sampleSize > 0
            ? (float) $competitorVariants->min('selling_price')
            : $currentPrice;

        // Pricing strategy:
        // If current price is significantly above category average, recommend competitive price point
        // while never exceeding MRP and maintaining healthy psychology pricing ending in 9 or 99
        if ($sampleSize > 0 && $currentPrice > $categoryAvg * 1.1) {
            $rawRecommended = ($categoryAvg + $currentPrice) / 2;
            $recommendedPrice = round($rawRecommended / 10) * 10 - 1; // e.g. 489, 499
            $rationale = "Your current price (₹{$currentPrice}) is above the category average of ₹{$categoryAvg} (sampled across {$sampleSize} merchant products). Adjusting to ₹{$recommendedPrice} positions your listing competitively without triggering a price war.";
        } elseif ($sampleSize > 0 && $currentPrice < $categoryAvg * 0.75) {
            $rawRecommended = $currentPrice * 1.15;
            $recommendedPrice = round($rawRecommended / 10) * 10 - 1;
            $rationale = "Your price is significantly below market average (₹{$categoryAvg}). You have headroom to raise to ₹{$recommendedPrice} to improve margin without losing conversion.";
        } else {
            $recommendedPrice = $currentPrice;
            $rationale = "Your current price (₹{$currentPrice}) is well-balanced within the market benchmark (₹{$categoryAvg}) across {$sampleSize} competitor listings.";
        }

        // Ensure recommended does not exceed MRP
        $recommendedPrice = min($recommendedPrice, $currentMrp);
        $discountPct = $currentMrp > $recommendedPrice
            ? (int) round((($currentMrp - $recommendedPrice) / $currentMrp) * 100)
            : 0;

        $output = [
            'current_selling_price' => $currentPrice,
            'current_mrp' => $currentMrp,
            'category_avg_price' => $categoryAvg,
            'recommended_price' => (float) $recommendedPrice,
            'suggested_discount_pct' => $discountPct,
            'rationale' => $rationale,
            'competitor_sample_size' => $sampleSize,
        ];

        $this->aiManager->log(
            feature: 'seller_assistant',
            action: 'suggest_pricing',
            sellerId: $seller->id,
            promptSummary: "Pricing suggestion for Variant #{$variant->id} ('{$variant->name}')",
            input: ['variant_id' => $variant->id, 'current_price' => $currentPrice],
            output: $output,
            latencyMs: (microtime(true) - $start) * 1000,
            status: 'success'
        );

        return $output;
    }
}
